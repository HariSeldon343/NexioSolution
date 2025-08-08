<?php

namespace Nexio\Services;

use Exception;
use PDO;
use Nexio\Middleware\Auth;
use Nexio\Utils\ActivityLogger;
use Nexio\Utils\DataEncryption;

/**
 * ISO Security Service - Security and permissions management for ISO compliance
 * 
 * This service handles all security aspects including:
 * - User permission checks at folder and document levels
 * - Super admin access management across companies
 * - GDPR compliance with retention policies and audit trails
 * - File validation and security scanning
 * - Access control and authorization
 * 
 * @package Nexio\Services
 * @version 1.0.0
 * @author Claude Code
 */
class ISOSecurityService
{
    /** @var ISOSecurityService|null Singleton instance */
    private static ?ISOSecurityService $instance = null;
    
    /** @var Auth Authentication instance */
    private Auth $auth;
    
    /** @var ActivityLogger Activity logger instance */
    private ActivityLogger $logger;
    
    /** @var DataEncryption Encryption utility */
    private DataEncryption $encryption;
    
    /** @var array Permission cache */
    private array $permissionCache = [];
    
    /** @var array Folder access cache */
    private array $folderAccessCache = [];
    
    /** @var array Role hierarchy for permission inheritance */
    private const ROLE_HIERARCHY = [
        'super_admin' => 100,
        'utente_speciale' => 90,
        'admin' => 80,
        'manager' => 60,
        'staff' => 40,
        'user' => 20,
        'cliente' => 10
    ];
    
    /** @var array Permission levels */
    private const PERMISSION_LEVELS = [
        'none' => 0,
        'view' => 10,
        'create' => 20,
        'update' => 30,
        'delete' => 40,
        'manage' => 50,
        'full' => 100
    ];
    
    /** @var array GDPR retention periods in days */
    private const RETENTION_PERIODS = [
        'documento' => 2555,        // 7 years
        'log_attivita' => 1095,     // 3 years
        'audit_trail' => 3650,      // 10 years
        'versione_documento' => 3650, // 10 years
        'backup' => 365             // 1 year
    ];
    
    /**
     * Private constructor for Singleton pattern
     */
    private function __construct()
    {
        $this->auth = Auth::getInstance();
        $this->logger = ActivityLogger::getInstance();
        $this->encryption = new DataEncryption();
    }
    
    /**
     * Get singleton instance
     * 
     * @return ISOSecurityService
     */
    public static function getInstance(): ISOSecurityService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     * 
     * @throws Exception
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
    
    /**
     * Check if user can manage ISO structure for company
     * 
     * @param int|null $userId User ID
     * @param int $companyId Company ID
     * @return bool
     */
    public function canManageISOStructure(?int $userId, int $companyId): bool
    {
        if (!$userId) {
            return false;
        }
        
        try {
            // Super admin can manage any company
            if ($this->isSuperAdmin($userId)) {
                $this->logPermissionCheck($userId, 'manage_iso_structure', $companyId, true, 'super_admin');
                return true;
            }
            
            // Check if user is company admin
            $stmt = db_query("
                SELECT ruolo_azienda 
                FROM utenti_aziende 
                WHERE utente_id = ? AND azienda_id = ?
            ", [$userId, $companyId]);
            
            $companyRole = $stmt->fetchColumn();
            $hasAccess = $companyRole === 'admin';
            
            $this->logPermissionCheck($userId, 'manage_iso_structure', $companyId, $hasAccess, $companyRole);
            
            return $hasAccess;
            
        } catch (Exception $e) {
            error_log("ISOSecurityService: Error checking ISO structure permission - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if user can create documents in folder
     * 
     * @param int $userId User ID
     * @param int $folderId Folder ID
     * @return bool
     */
    public function canCreateDocument(int $userId, int $folderId): bool
    {
        return $this->checkFolderPermission($userId, $folderId, 'create');
    }
    
    /**
     * Check if user can view document
     * 
     * @param int $userId User ID
     * @param int $documentId Document ID
     * @return bool
     */
    public function canViewDocument(int $userId, int $documentId): bool
    {
        return $this->checkDocumentPermission($userId, $documentId, 'view');
    }
    
    /**
     * Check if user can update document
     * 
     * @param int $userId User ID
     * @param int $documentId Document ID
     * @return bool
     */
    public function canUpdateDocument(int $userId, int $documentId): bool
    {
        return $this->checkDocumentPermission($userId, $documentId, 'update');
    }
    
    /**
     * Check if user can delete document
     * 
     * @param int $userId User ID
     * @param int $documentId Document ID
     * @return bool
     */
    public function canDeleteDocument(int $userId, int $documentId): bool
    {
        return $this->checkDocumentPermission($userId, $documentId, 'delete');
    }
    
    /**
     * Get user's allowed folders
     * 
     * @param int $userId User ID
     * @param int|null $companyId Company ID (optional)
     * @return array Folder IDs
     */
    public function getUserAllowedFolders(int $userId, ?int $companyId = null): array
    {
        $cacheKey = "folders_{$userId}" . ($companyId ? "_{$companyId}" : '');
        
        if (isset($this->folderAccessCache[$cacheKey])) {
            return $this->folderAccessCache[$cacheKey];
        }
        
        try {
            // Super admin has access to all folders
            if ($this->isSuperAdmin($userId)) {
                $sql = "SELECT id FROM cartelle WHERE 1=1";
                $params = [];
                
                if ($companyId) {
                    $sql .= " AND azienda_id = ?";
                    $params[] = $companyId;
                }
                
                $stmt = db_query($sql, $params);
                $folders = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $this->folderAccessCache[$cacheKey] = $folders;
                return $folders;
            }
            
            // Get user's companies and roles
            $userCompanies = $this->getUserCompaniesWithRoles($userId);
            
            if (empty($userCompanies)) {
                return [];
            }
            
            $allowedFolders = [];
            
            foreach ($userCompanies as $company) {
                if ($companyId && $company['azienda_id'] != $companyId) {
                    continue;
                }
                
                // Company admin has access to all company folders
                if ($company['ruolo_azienda'] === 'admin') {
                    $stmt = db_query(
                        "SELECT id FROM cartelle WHERE azienda_id = ?",
                        [$company['azienda_id']]
                    );
                    $companyFolders = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    $allowedFolders = array_merge($allowedFolders, $companyFolders);
                } else {
                    // Check specific folder permissions
                    $stmt = db_query("
                        SELECT DISTINCT fp.cartella_id
                        FROM folder_permissions fp
                        WHERE fp.utente_id = ? 
                        AND fp.azienda_id = ?
                        AND fp.permission_level >= ?
                    ", [$userId, $company['azienda_id'], self::PERMISSION_LEVELS['view']]);
                    
                    $userFolders = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    $allowedFolders = array_merge($allowedFolders, $userFolders);
                    
                    // Check role-based permissions
                    $stmt = db_query("
                        SELECT DISTINCT frp.cartella_id
                        FROM folder_role_permissions frp
                        WHERE frp.ruolo = ?
                        AND frp.azienda_id = ?
                        AND frp.permission_level >= ?
                    ", [$company['ruolo_utente'], $company['azienda_id'], self::PERMISSION_LEVELS['view']]);
                    
                    $roleFolders = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    $allowedFolders = array_merge($allowedFolders, $roleFolders);
                }
            }
            
            $allowedFolders = array_unique($allowedFolders);
            $this->folderAccessCache[$cacheKey] = $allowedFolders;
            
            return $allowedFolders;
            
        } catch (Exception $e) {
            error_log("ISOSecurityService: Error getting allowed folders - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Setup document permissions
     * 
     * @param int $documentId Document ID
     * @param array $documentData Document data including permissions
     * @throws Exception
     */
    public function setupDocumentPermissions(int $documentId, array $documentData): void
    {
        try {
            // Default permissions based on distribution level
            $distributionLevel = $documentData['livello_distribuzione'] ?? 'interno';
            
            switch ($distributionLevel) {
                case 'pubblico':
                    // All authenticated users can view
                    $this->grantDocumentPermission($documentId, null, 'all_users', 'view');
                    break;
                    
                case 'interno':
                    // Company users can view
                    $this->grantDocumentPermission($documentId, null, 'company_users', 'view');
                    break;
                    
                case 'riservato':
                    // Only specific users/roles
                    if (!empty($documentData['utenti_autorizzati'])) {
                        foreach ($documentData['utenti_autorizzati'] as $userId) {
                            $this->grantDocumentPermission($documentId, $userId, 'user', 'view');
                        }
                    }
                    
                    if (!empty($documentData['ruoli_autorizzati'])) {
                        foreach ($documentData['ruoli_autorizzati'] as $role) {
                            $this->grantDocumentPermission($documentId, null, $role, 'view');
                        }
                    }
                    break;
            }
            
            // Grant full access to document creator
            if (!empty($documentData['creato_da'])) {
                $this->grantDocumentPermission($documentId, $documentData['creato_da'], 'user', 'full');
            }
            
            // Grant manage access to responsible
            if (!empty($documentData['responsabile_id'])) {
                $this->grantDocumentPermission($documentId, $documentData['responsabile_id'], 'user', 'manage');
            }
            
        } catch (Exception $e) {
            error_log("ISOSecurityService: Error setting up document permissions - " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Grant folder access to user or company
     * 
     * @param int $userId User ID
     * @param int $companyId Company ID
     * @param string $accessLevel Access level
     * @throws Exception
     */
    public function grantFolderAccess(int $userId, int $companyId, string $accessLevel): void
    {
        try {
            // Get all ISO folders for company
            $stmt = db_query("
                SELECT id FROM cartelle 
                WHERE azienda_id = ? 
                AND iso_standard_codice IS NOT NULL
            ", [$companyId]);
            
            $folders = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $permissionLevel = self::PERMISSION_LEVELS[$accessLevel] ?? self::PERMISSION_LEVELS['view'];
            
            foreach ($folders as $folderId) {
                // Check existing permission
                $existing = db_query("
                    SELECT id FROM folder_permissions 
                    WHERE utente_id = ? AND cartella_id = ?
                ", [$userId, $folderId])->fetch();
                
                if ($existing) {
                    // Update permission
                    db_update('folder_permissions', [
                        'permission_level' => $permissionLevel,
                        'data_modifica' => date('Y-m-d H:i:s')
                    ], 'id = ?', [$existing['id']]);
                } else {
                    // Insert new permission
                    db_insert('folder_permissions', [
                        'utente_id' => $userId,
                        'cartella_id' => $folderId,
                        'azienda_id' => $companyId,
                        'permission_level' => $permissionLevel,
                        'granted_by' => $this->auth->getUser()['id'] ?? null
                    ]);
                }
            }
            
            // Clear cache
            $this->clearUserCache($userId);
            
            // Log permission grant
            $this->logger->log('folder_access_granted', 'folder_permissions', null, [
                'user_id' => $userId,
                'company_id' => $companyId,
                'access_level' => $accessLevel,
                'folders_count' => count($folders)
            ]);
            
        } catch (Exception $e) {
            error_log("ISOSecurityService: Error granting folder access - " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Validate file upload for security
     * 
     * @param array $fileData File upload data
     * @param array $options Validation options
     * @return array Validation result
     */
    public function validateFileUpload(array $fileData, array $options = []): array
    {
        $errors = [];
        
        // Check file size
        $maxSize = $options['max_size'] ?? 52428800; // 50MB default
        if ($fileData['size'] > $maxSize) {
            $errors[] = "File troppo grande. Massimo consentito: " . $this->formatBytes($maxSize);
        }
        
        // Check file extension
        $allowedExtensions = $options['allowed_extensions'] ?? [
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'txt', 'rtf', 'odt', 'ods', 'odp',
            'jpg', 'jpeg', 'png', 'gif', 'bmp',
            'zip', 'rar', '7z'
        ];
        
        $extension = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions)) {
            $errors[] = "Tipo file non consentito: .{$extension}";
        }
        
        // Check MIME type
        if (isset($fileData['tmp_name']) && file_exists($fileData['tmp_name'])) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $fileData['tmp_name']);
            finfo_close($finfo);
            
            $validMimeTypes = $this->getValidMimeTypes();
            if (!isset($validMimeTypes[$extension]) || 
                !in_array($mimeType, (array)$validMimeTypes[$extension])) {
                $errors[] = "MIME type non valido per l'estensione .{$extension}";
            }
            
            // Scan for malware signatures
            if ($options['scan_malware'] ?? true) {
                $malwareFound = $this->scanForMalware($fileData['tmp_name']);
                if ($malwareFound) {
                    $errors[] = "Possibile contenuto malevolo rilevato nel file";
                }
            }
        }
        
        // Check filename for security issues
        if (preg_match('/[<>:"\/\\|?*\x00-\x1f]/', $fileData['name'])) {
            $errors[] = "Nome file contiene caratteri non validi";
        }
        
        // Log validation attempt
        $this->logger->log('file_validation', 'security', null, [
            'filename' => $fileData['name'],
            'size' => $fileData['size'],
            'extension' => $extension,
            'errors' => $errors,
            'validation_passed' => empty($errors)
        ]);
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'sanitized_name' => $this->sanitizeFilename($fileData['name']),
            'extension' => $extension,
            'mime_type' => $mimeType ?? null
        ];
    }
    
    /**
     * Check GDPR compliance for data retention
     * 
     * @param string $dataType Type of data
     * @param \DateTime $creationDate Creation date
     * @return array Compliance status
     */
    public function checkGDPRCompliance(string $dataType, \DateTime $creationDate): array
    {
        $retentionDays = self::RETENTION_PERIODS[$dataType] ?? 2555; // Default 7 years
        $retentionDate = clone $creationDate;
        $retentionDate->modify("+{$retentionDays} days");
        
        $now = new \DateTime();
        $daysRemaining = $now->diff($retentionDate)->days;
        $isExpired = $now > $retentionDate;
        
        return [
            'data_type' => $dataType,
            'retention_days' => $retentionDays,
            'retention_until' => $retentionDate->format('Y-m-d'),
            'is_expired' => $isExpired,
            'days_remaining' => $isExpired ? 0 : $daysRemaining,
            'action_required' => $isExpired ? 'delete' : ($daysRemaining < 30 ? 'review' : 'none')
        ];
    }
    
    /**
     * Create audit trail entry
     * 
     * @param string $action Action performed
     * @param string $entityType Entity type
     * @param int|null $entityId Entity ID
     * @param array $details Additional details
     * @param int|null $userId User performing action
     */
    public function createAuditTrail(
        string $action,
        string $entityType,
        ?int $entityId,
        array $details = [],
        ?int $userId = null
    ): void {
        try {
            $userId = $userId ?? ($this->auth->getUser()['id'] ?? null);
            $companyId = $this->auth->getCurrentCompany();
            
            // Encrypt sensitive data in details
            $encryptedDetails = $this->encryptSensitiveData($details);
            
            db_insert('audit_trail', [
                'utente_id' => $userId,
                'azienda_id' => $companyId,
                'azione' => $action,
                'entita_tipo' => $entityType,
                'entita_id' => $entityId,
                'dettagli' => json_encode($encryptedDetails),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'session_id' => session_id(),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            error_log("ISOSecurityService: Error creating audit trail - " . $e->getMessage());
        }
    }
    
    /**
     * Apply retention policy to old data
     * 
     * @param bool $dryRun Whether to only simulate
     * @return array Results of retention policy application
     */
    public function applyRetentionPolicy(bool $dryRun = true): array
    {
        $results = [
            'documents_to_delete' => 0,
            'logs_to_delete' => 0,
            'versions_to_delete' => 0,
            'total_size_to_free' => 0,
            'errors' => []
        ];
        
        try {
            foreach (self::RETENTION_PERIODS as $dataType => $days) {
                $cutoffDate = date('Y-m-d', strtotime("-{$days} days"));
                
                switch ($dataType) {
                    case 'documento':
                        $stmt = db_query("
                            SELECT id, file_path, file_size 
                            FROM documenti 
                            WHERE data_creazione < ? 
                            AND eliminato = 0
                            AND stato = 'archiviato'
                        ", [$cutoffDate]);
                        
                        while ($doc = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $results['documents_to_delete']++;
                            $results['total_size_to_free'] += $doc['file_size'] ?? 0;
                            
                            if (!$dryRun) {
                                $this->archiveAndDeleteDocument($doc['id']);
                            }
                        }
                        break;
                        
                    case 'log_attivita':
                        $count = db_query("
                            SELECT COUNT(*) 
                            FROM log_attivita 
                            WHERE data_azione < ?
                            AND non_eliminabile = 0
                        ", [$cutoffDate])->fetchColumn();
                        
                        $results['logs_to_delete'] = $count;
                        
                        if (!$dryRun && $count > 0) {
                            // Archive logs before deletion
                            $this->archiveOldLogs($cutoffDate);
                            
                            db_query("
                                DELETE FROM log_attivita 
                                WHERE data_azione < ?
                                AND non_eliminabile = 0
                            ", [$cutoffDate]);
                        }
                        break;
                        
                    case 'versione_documento':
                        $stmt = db_query("
                            SELECT dv.id, dv.file_size, d.versione as current_version
                            FROM documenti_versioni dv
                            JOIN documenti d ON dv.documento_id = d.id
                            WHERE dv.data_creazione < ?
                            AND dv.versione < d.versione - 2  -- Keep last 3 versions minimum
                        ", [$cutoffDate]);
                        
                        while ($version = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $results['versions_to_delete']++;
                            $results['total_size_to_free'] += $version['file_size'] ?? 0;
                            
                            if (!$dryRun) {
                                $this->deleteOldVersion($version['id']);
                            }
                        }
                        break;
                }
            }
            
            // Log retention policy execution
            if (!$dryRun) {
                $this->logger->log('retention_policy_applied', 'gdpr', null, $results);
            }
            
        } catch (Exception $e) {
            $results['errors'][] = $e->getMessage();
            error_log("ISOSecurityService: Error applying retention policy - " . $e->getMessage());
        }
        
        return $results;
    }
    
    /**
     * Check if user is super admin
     * 
     * @param int $userId User ID
     * @return bool
     */
    private function isSuperAdmin(int $userId): bool
    {
        $stmt = db_query("SELECT ruolo FROM utenti WHERE id = ?", [$userId]);
        $role = $stmt->fetchColumn();
        return $role === 'super_admin';
    }
    
    /**
     * Get user companies with roles
     * 
     * @param int $userId User ID
     * @return array Companies with roles
     */
    private function getUserCompaniesWithRoles(int $userId): array
    {
        $stmt = db_query("
            SELECT ua.*, u.ruolo as ruolo_utente
            FROM utenti_aziende ua
            JOIN utenti u ON ua.utente_id = u.id
            WHERE ua.utente_id = ?
        ", [$userId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Check folder permission
     * 
     * @param int $userId User ID
     * @param int $folderId Folder ID
     * @param string $permission Permission type
     * @return bool
     */
    private function checkFolderPermission(int $userId, int $folderId, string $permission): bool
    {
        $cacheKey = "folder_{$userId}_{$folderId}_{$permission}";
        
        if (isset($this->permissionCache[$cacheKey])) {
            return $this->permissionCache[$cacheKey];
        }
        
        try {
            // Super admin has all permissions
            if ($this->isSuperAdmin($userId)) {
                $this->permissionCache[$cacheKey] = true;
                return true;
            }
            
            // Get folder info
            $folder = db_query("
                SELECT azienda_id, creato_da 
                FROM cartelle 
                WHERE id = ?
            ", [$folderId])->fetch();
            
            if (!$folder) {
                $this->permissionCache[$cacheKey] = false;
                return false;
            }
            
            // Check company admin
            $companyRole = db_query("
                SELECT ruolo_azienda 
                FROM utenti_aziende 
                WHERE utente_id = ? AND azienda_id = ?
            ", [$userId, $folder['azienda_id']])->fetchColumn();
            
            if ($companyRole === 'admin') {
                $this->permissionCache[$cacheKey] = true;
                return true;
            }
            
            // Check specific permissions
            $requiredLevel = self::PERMISSION_LEVELS[$permission] ?? 0;
            
            $userPermission = db_query("
                SELECT permission_level 
                FROM folder_permissions 
                WHERE utente_id = ? AND cartella_id = ?
            ", [$userId, $folderId])->fetchColumn();
            
            $hasPermission = ($userPermission >= $requiredLevel);
            
            // Check role-based permissions if no direct permission
            if (!$hasPermission && $companyRole) {
                $rolePermission = db_query("
                    SELECT permission_level 
                    FROM folder_role_permissions 
                    WHERE ruolo = ? AND cartella_id = ?
                ", [$companyRole, $folderId])->fetchColumn();
                
                $hasPermission = ($rolePermission >= $requiredLevel);
            }
            
            $this->permissionCache[$cacheKey] = $hasPermission;
            return $hasPermission;
            
        } catch (Exception $e) {
            error_log("ISOSecurityService: Error checking folder permission - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check document permission
     * 
     * @param int $userId User ID
     * @param int $documentId Document ID
     * @param string $permission Permission type
     * @return bool
     */
    private function checkDocumentPermission(int $userId, int $documentId, string $permission): bool
    {
        $cacheKey = "doc_{$userId}_{$documentId}_{$permission}";
        
        if (isset($this->permissionCache[$cacheKey])) {
            return $this->permissionCache[$cacheKey];
        }
        
        try {
            // Get document info
            $doc = db_query("
                SELECT cartella_id, creato_da, responsabile_id, stato,
                       JSON_EXTRACT(iso_metadata, '$.distribution_level') as distribution_level
                FROM documenti 
                WHERE id = ? AND eliminato = 0
            ", [$documentId])->fetch();
            
            if (!$doc) {
                $this->permissionCache[$cacheKey] = false;
                return false;
            }
            
            // Check folder permission first
            if ($this->checkFolderPermission($userId, $doc['cartella_id'], $permission)) {
                $this->permissionCache[$cacheKey] = true;
                return true;
            }
            
            // Document creator has full access
            if ($doc['creato_da'] == $userId) {
                $this->permissionCache[$cacheKey] = true;
                return true;
            }
            
            // Document responsible has manage access
            if ($doc['responsabile_id'] == $userId && 
                self::PERMISSION_LEVELS[$permission] <= self::PERMISSION_LEVELS['manage']) {
                $this->permissionCache[$cacheKey] = true;
                return true;
            }
            
            // Check specific document permissions
            $requiredLevel = self::PERMISSION_LEVELS[$permission] ?? 0;
            
            $docPermission = db_query("
                SELECT permission_level 
                FROM document_permissions 
                WHERE utente_id = ? AND documento_id = ?
            ", [$userId, $documentId])->fetchColumn();
            
            $hasPermission = ($docPermission >= $requiredLevel);
            
            // Public documents can be viewed by anyone authenticated
            if (!$hasPermission && 
                $permission === 'view' && 
                trim($doc['distribution_level'], '"') === 'pubblico') {
                $hasPermission = true;
            }
            
            $this->permissionCache[$cacheKey] = $hasPermission;
            return $hasPermission;
            
        } catch (Exception $e) {
            error_log("ISOSecurityService: Error checking document permission - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Grant document permission
     * 
     * @param int $documentId Document ID
     * @param int|null $userId User ID
     * @param string $targetType Target type (user, role, all_users, company_users)
     * @param string $permissionLevel Permission level
     */
    private function grantDocumentPermission(
        int $documentId,
        ?int $userId,
        string $targetType,
        string $permissionLevel
    ): void {
        $level = self::PERMISSION_LEVELS[$permissionLevel] ?? self::PERMISSION_LEVELS['view'];
        
        if ($targetType === 'user' && $userId) {
            $existing = db_query("
                SELECT id FROM document_permissions 
                WHERE documento_id = ? AND utente_id = ?
            ", [$documentId, $userId])->fetch();
            
            if ($existing) {
                db_update('document_permissions', [
                    'permission_level' => $level
                ], 'id = ?', [$existing['id']]);
            } else {
                db_insert('document_permissions', [
                    'documento_id' => $documentId,
                    'utente_id' => $userId,
                    'permission_level' => $level,
                    'granted_by' => $this->auth->getUser()['id'] ?? null
                ]);
            }
        } elseif ($targetType !== 'user') {
            // Store role/group permissions
            db_insert('document_role_permissions', [
                'documento_id' => $documentId,
                'target_type' => $targetType,
                'permission_level' => $level
            ]);
        }
    }
    
    /**
     * Log permission check
     * 
     * @param int $userId User ID
     * @param string $action Action attempted
     * @param int $resourceId Resource ID
     * @param bool $granted Whether access was granted
     * @param string|null $reason Reason for decision
     */
    private function logPermissionCheck(
        int $userId,
        string $action,
        int $resourceId,
        bool $granted,
        ?string $reason = null
    ): void {
        try {
            db_insert('permission_checks', [
                'utente_id' => $userId,
                'action' => $action,
                'resource_id' => $resourceId,
                'granted' => $granted ? 1 : 0,
                'reason' => $reason,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            // Log failures should not break the application
            error_log("Failed to log permission check: " . $e->getMessage());
        }
    }
    
    /**
     * Get valid MIME types for extensions
     * 
     * @return array MIME type mappings
     */
    private function getValidMimeTypes(): array
    {
        return [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt' => 'text/plain',
            'rtf' => 'application/rtf',
            'jpg' => ['image/jpeg', 'image/jpg'],
            'jpeg' => ['image/jpeg', 'image/jpg'],
            'png' => 'image/png',
            'gif' => 'image/gif',
            'zip' => ['application/zip', 'application/x-zip-compressed'],
            'rar' => ['application/x-rar-compressed', 'application/x-rar'],
            '7z' => 'application/x-7z-compressed'
        ];
    }
    
    /**
     * Scan file for malware signatures
     * 
     * @param string $filePath File path
     * @return bool Whether malware was found
     */
    private function scanForMalware(string $filePath): bool
    {
        // Basic signature scanning - in production would use ClamAV or similar
        $signatures = [
            'EICAR-STANDARD-ANTIVIRUS-TEST-FILE',
            '<script',
            '<?php',
            'eval(',
            'base64_decode(',
            'shell_exec(',
            'system(',
            'exec(',
            '\x00\x00\x01\x00', // Windows executable
            '\x7fELF', // Linux executable
        ];
        
        $content = file_get_contents($filePath, false, null, 0, 8192); // Check first 8KB
        
        foreach ($signatures as $signature) {
            if (stripos($content, $signature) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Sanitize filename
     * 
     * @param string $filename Original filename
     * @return string Sanitized filename
     */
    private function sanitizeFilename(string $filename): string
    {
        // Remove path components
        $filename = basename($filename);
        
        // Replace dangerous characters
        $filename = preg_replace('/[<>:"\/\\|?*\x00-\x1f]/', '_', $filename);
        
        // Limit length
        if (strlen($filename) > 255) {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $name = pathinfo($filename, PATHINFO_FILENAME);
            $name = substr($name, 0, 250 - strlen($ext));
            $filename = $name . '.' . $ext;
        }
        
        return $filename;
    }
    
    /**
     * Format bytes to human readable
     * 
     * @param int $bytes Bytes
     * @param int $precision Decimal precision
     * @return string Formatted size
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    /**
     * Encrypt sensitive data
     * 
     * @param array $data Data to encrypt
     * @return array Data with sensitive fields encrypted
     */
    private function encryptSensitiveData(array $data): array
    {
        $sensitiveFields = ['password', 'token', 'secret', 'key', 'ssn', 'tax_id'];
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->encryptSensitiveData($value);
            } elseif (is_string($value)) {
                foreach ($sensitiveFields as $field) {
                    if (stripos($key, $field) !== false) {
                        $data[$key] = $this->encryption->encrypt($value);
                        break;
                    }
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Archive and delete old document
     * 
     * @param int $documentId Document ID
     */
    private function archiveAndDeleteDocument(int $documentId): void
    {
        // Implementation would archive to cold storage before deletion
        db_update('documenti', [
            'eliminato' => 1,
            'data_eliminazione' => date('Y-m-d H:i:s'),
            'motivo_eliminazione' => 'GDPR retention policy'
        ], 'id = ?', [$documentId]);
    }
    
    /**
     * Archive old logs
     * 
     * @param string $cutoffDate Cutoff date
     */
    private function archiveOldLogs(string $cutoffDate): void
    {
        // Implementation would export to compressed archive
        // For now, just log the action
        $this->logger->log('logs_archived', 'gdpr', null, [
            'cutoff_date' => $cutoffDate
        ]);
    }
    
    /**
     * Delete old document version
     * 
     * @param int $versionId Version ID
     */
    private function deleteOldVersion(int $versionId): void
    {
        // Get version info
        $version = db_query("
            SELECT file_path FROM documenti_versioni WHERE id = ?
        ", [$versionId])->fetch();
        
        if ($version && $version['file_path']) {
            // Delete physical file
            $fullPath = __DIR__ . '/../../uploads/documenti/' . $version['file_path'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }
        
        // Delete database record
        db_query("DELETE FROM documenti_versioni WHERE id = ?", [$versionId]);
    }
    
    /**
     * Clear user permission cache
     * 
     * @param int $userId User ID
     */
    private function clearUserCache(int $userId): void
    {
        // Clear permission cache for user
        foreach ($this->permissionCache as $key => $value) {
            if (strpos($key, "_{$userId}_") !== false) {
                unset($this->permissionCache[$key]);
            }
        }
        
        // Clear folder access cache
        foreach ($this->folderAccessCache as $key => $value) {
            if (strpos($key, "folders_{$userId}") === 0) {
                unset($this->folderAccessCache[$key]);
            }
        }
    }
    
    /**
     * Get security metrics
     * 
     * @return array Security metrics
     */
    public function getSecurityMetrics(): array
    {
        return [
            'permission_cache_size' => count($this->permissionCache),
            'folder_cache_size' => count($this->folderAccessCache),
            'active_sessions' => db_query("
                SELECT COUNT(DISTINCT session_id) 
                FROM audit_trail 
                WHERE timestamp > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            ")->fetchColumn(),
            'failed_access_attempts_24h' => db_query("
                SELECT COUNT(*) 
                FROM permission_checks 
                WHERE granted = 0 
                AND timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ")->fetchColumn()
        ];
    }
}