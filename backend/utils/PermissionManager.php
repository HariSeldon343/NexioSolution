<?php
/**
 * PermissionManager - Sistema di permessi granulari per Nexio ISO
 * 
 * Gestisce permessi granulari per documenti, cartelle, e operazioni di sistema
 * Integrato con Auth.php e sistema multi-tenant esistente
 * 
 * @author Nexio Platform
 * @version 1.0.0
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/Auth.php';
require_once __DIR__ . '/ActivityLogger.php';
require_once __DIR__ . '/RateLimiter.php';

class PermissionManager {
    private static $instance = null;
    private $cache = [];
    private $cacheTimeout = 300; // 5 minuti
    
    // Definizione permessi disponibili
    const PERMISSIONS = [
        // Permessi documenti
        'document_view' => 'Visualizza documento',
        'document_download' => 'Scarica documento',
        'document_upload' => 'Carica documento',
        'document_edit' => 'Modifica documento',
        'document_approve' => 'Approva documento',
        'document_delete' => 'Elimina documento',
        'document_share' => 'Condividi documento',
        'document_version' => 'Gestione versioni',
        
        // Permessi cartelle
        'folder_view' => 'Visualizza cartella',
        'folder_create' => 'Crea cartella',
        'folder_edit' => 'Modifica cartella',
        'folder_delete' => 'Elimina cartella',
        'folder_manage_permissions' => 'Gestione permessi cartella',
        
        // Permessi sistema ISO
        'iso_configure' => 'Configura struttura ISO',
        'iso_manage_compliance' => 'Gestione conformità',
        'iso_audit_access' => 'Accesso audit',
        'iso_structure_admin' => 'Amministrazione struttura',
        
        // Permessi azienda
        'company_view' => 'Visualizza azienda',
        'company_manage' => 'Gestione azienda',
        'company_switch' => 'Cambio azienda',
        'company_users' => 'Gestione utenti azienda',
        
        // Permessi sistema
        'system_admin' => 'Amministrazione sistema',
        'system_logs' => 'Accesso log sistema',
        'system_config' => 'Configurazione sistema'
    ];
    
    // Mapping ruoli -> permessi di base
    const ROLE_PERMISSIONS = [
        'super_admin' => ['*'], // Tutti i permessi
        'utente_speciale' => [
            'document_view', 'document_download', 'document_upload', 'document_edit', 'document_approve',
            'folder_view', 'folder_create', 'folder_edit', 'folder_manage_permissions',
            'iso_configure', 'iso_manage_compliance', 'iso_audit_access', 'iso_structure_admin',
            'company_view', 'company_switch', 'system_logs'
        ],
        'admin' => [
            'document_view', 'document_download', 'document_upload', 'document_edit', 'document_approve', 'document_delete',
            'folder_view', 'folder_create', 'folder_edit', 'folder_delete', 'folder_manage_permissions',
            'company_view', 'company_manage', 'company_users'
        ],
        'manager' => [
            'document_view', 'document_download', 'document_upload', 'document_edit',
            'folder_view', 'folder_create', 'folder_edit',
            'company_view'
        ],
        'staff' => [
            'document_view', 'document_download', 'document_upload',
            'folder_view', 'company_view'
        ],
        'cliente' => [
            'document_view', 'document_download',
            'folder_view', 'company_view'
        ]
    ];
    
    private function __construct() {
        // Costruttore privato per Singleton
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Verifica accesso a documento specifico
     */
    public function checkDocumentAccess($documentId, $permission, $userId = null, $companyId = null) {
        try {
            $auth = Auth::getInstance();
            
            if (!$userId) {
                $user = $auth->getUser();
                $userId = $user ? $user['id'] : null;
            }
            
            if (!$companyId) {
                $companyId = $auth->getCurrentCompany();
            }
            
            if (!$userId || !$companyId) {
                return false;
            }
            
            // Super admin ha sempre accesso
            if ($auth->isSuperAdmin()) {
                return true;
            }
            
            // Cache key per performance
            $cacheKey = "doc_access_{$documentId}_{$userId}_{$permission}_{$companyId}";
            
            if (isset($this->cache[$cacheKey]) && 
                (time() - $this->cache[$cacheKey]['timestamp']) < $this->cacheTimeout) {
                return $this->cache[$cacheKey]['result'];
            }
            
            // Verifica ownership e permessi di base
            $stmt = db_query("
                SELECT d.*, u.ruolo 
                FROM documenti d
                LEFT JOIN utenti u ON u.id = ?
                WHERE d.id = ? AND d.azienda_id = ?
            ", [$userId, $documentId, $companyId]);
            
            $document = $stmt->fetch();
            if (!$document) {
                return $this->cacheResult($cacheKey, false);
            }
            
            // Verifica permessi ruolo base
            $rolePermissions = $this->getRolePermissions($document['ruolo']);
            $permissionKey = 'document_' . $permission;
            
            if (!in_array($permissionKey, $rolePermissions) && !in_array('*', $rolePermissions)) {
                // Verifica permessi specifici per documento
                $specificAccess = $this->checkSpecificDocumentPermission($documentId, $userId, $permission);
                return $this->cacheResult($cacheKey, $specificAccess);
            }
            
            // Verifica permessi addizionali (documenti privati, stati speciali, etc.)
            $additionalChecks = $this->performAdditionalDocumentChecks($document, $permission, $userId);
            
            return $this->cacheResult($cacheKey, $additionalChecks);
            
        } catch (Exception $e) {
            ActivityLogger::getInstance()->logError('permission_check_error', [
                'document_id' => $documentId,
                'user_id' => $userId,
                'permission' => $permission,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Verifica accesso a cartella specifica
     */
    public function checkFolderAccess($folderId, $permission, $userId = null, $companyId = null) {
        try {
            $auth = Auth::getInstance();
            
            if (!$userId) {
                $user = $auth->getUser();
                $userId = $user ? $user['id'] : null;
            }
            
            if (!$companyId) {
                $companyId = $auth->getCurrentCompany();
            }
            
            if (!$userId || !$companyId) {
                return false;
            }
            
            // Super admin ha sempre accesso
            if ($auth->isSuperAdmin()) {
                return true;
            }
            
            // Map simple permissions to detailed ones
            $permissionMap = [
                'read' => 'folder_view',
                'write' => 'folder_edit',
                'delete' => 'folder_delete',
                'create' => 'folder_create'
            ];
            
            // Convert simple permission to detailed if needed
            if (isset($permissionMap[$permission])) {
                $permission = $permissionMap[$permission];
            }
            
            // Cache key
            $cacheKey = "folder_access_{$folderId}_{$userId}_{$permission}_{$companyId}";
            
            if (isset($this->cache[$cacheKey]) && 
                (time() - $this->cache[$cacheKey]['timestamp']) < $this->cacheTimeout) {
                return $this->cache[$cacheKey]['result'];
            }
            
            // Verifica esistenza cartella e azienda
            $stmt = db_query("
                SELECT c.*, u.ruolo 
                FROM cartelle c
                LEFT JOIN utenti u ON u.id = ?
                WHERE c.id = ? AND c.azienda_id = ?
            ", [$userId, $folderId, $companyId]);
            
            $folder = $stmt->fetch();
            if (!$folder) {
                return $this->cacheResult($cacheKey, false);
            }
            
            // Verifica permessi ruolo base
            $rolePermissions = $this->getRolePermissions($folder['ruolo']);
            $permissionKey = 'folder_' . $permission;
            
            if (!in_array($permissionKey, $rolePermissions) && !in_array('*', $rolePermissions)) {
                // Verifica permessi specifici per cartella
                $specificAccess = $this->checkSpecificFolderPermission($folderId, $userId, $permission);
                return $this->cacheResult($cacheKey, $specificAccess);
            }
            
            // Verifica permessi ereditati da cartelle parent
            $inheritedAccess = $this->checkInheritedFolderPermissions($folder, $permission, $userId);
            
            return $this->cacheResult($cacheKey, $inheritedAccess);
            
        } catch (Exception $e) {
            ActivityLogger::getInstance()->logError('folder_permission_check_error', [
                'folder_id' => $folderId,
                'user_id' => $userId,
                'permission' => $permission,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Verifica se utente può eseguire azione specifica
     */
    public function canPerformAction($action, $context = [], $userId = null) {
        try {
            $auth = Auth::getInstance();
            
            if (!$userId) {
                $user = $auth->getUser();
                $userId = $user ? $user['id'] : null;
                $userRole = $user ? $user['ruolo'] : null;
            } else {
                $stmt = db_query("SELECT ruolo FROM utenti WHERE id = ?", [$userId]);
                $userRole = $stmt->fetchColumn();
            }
            
            if (!$userId || !$userRole) {
                return false;
            }
            
            // Super admin può fare tutto
            if ($userRole === 'super_admin') {
                return true;
            }
            
            // Rate limiting per azioni sensibili
            if (in_array($action, ['document_delete', 'folder_delete', 'iso_configure'])) {
                if (!RateLimiter::check("sensitive_action_{$userId}", $action, 10, 3600)) {
                    ActivityLogger::getInstance()->logSecurity('rate_limit_exceeded', [
                        'user_id' => $userId,
                        'action' => $action,
                        'context' => $context
                    ]);
                    return false;
                }
            }
            
            // Verifica permessi specifici per azione
            switch ($action) {
                case 'iso_configure':
                case 'iso_manage_compliance':
                case 'iso_audit_access':
                    return $this->hasISOPermissions($userId, $action);
                    
                case 'company_switch':
                    return $this->canSwitchCompany($userId, $context['target_company_id'] ?? null);
                    
                case 'system_admin':
                case 'system_logs':
                    return in_array($userRole, ['super_admin', 'utente_speciale']);
                    
                default:
                    // Verifica permessi ruolo standard
                    $rolePermissions = $this->getRolePermissions($userRole);
                    return in_array($action, $rolePermissions) || in_array('*', $rolePermissions);
            }
            
        } catch (Exception $e) {
            ActivityLogger::getInstance()->logError('action_permission_check_error', [
                'action' => $action,
                'user_id' => $userId,
                'context' => $context,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Ottieni tutti i permessi di un utente
     */
    public function getUserPermissions($userId, $companyId = null) {
        try {
            $cacheKey = "user_permissions_{$userId}_{$companyId}";
            
            if (isset($this->cache[$cacheKey]) && 
                (time() - $this->cache[$cacheKey]['timestamp']) < $this->cacheTimeout) {
                return $this->cache[$cacheKey]['result'];
            }
            
            // Recupera utente e ruolo
            $stmt = db_query("SELECT ruolo FROM utenti WHERE id = ? AND attivo = 1", [$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return $this->cacheResult($cacheKey, []);
            }
            
            // Permessi base del ruolo
            $rolePermissions = $this->getRolePermissions($user['ruolo']);
            
            // Permessi specifici assegnati
            $specificPermissions = $this->getSpecificUserPermissions($userId, $companyId);
            
            // Merge permessi
            $allPermissions = array_merge($rolePermissions, $specificPermissions);
            $allPermissions = array_unique($allPermissions);
            
            // Se ha *, restituisci tutti i permessi
            if (in_array('*', $allPermissions)) {
                $allPermissions = array_keys(self::PERMISSIONS);
            }
            
            return $this->cacheResult($cacheKey, $allPermissions);
            
        } catch (Exception $e) {
            ActivityLogger::getInstance()->logError('get_user_permissions_error', [
                'user_id' => $userId,
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Assegna permessi specifici (solo admin)
     */
    public function assignPermissions($targetUserId, $permissions, $resourceType = null, $resourceId = null, $adminUserId = null) {
        try {
            $auth = Auth::getInstance();
            
            if (!$adminUserId) {
                $admin = $auth->getUser();
                $adminUserId = $admin ? $admin['id'] : null;
                $adminRole = $admin ? $admin['ruolo'] : null;
            } else {
                $stmt = db_query("SELECT ruolo FROM utenti WHERE id = ?", [$adminUserId]);
                $adminRole = $stmt->fetchColumn();
            }
            
            // Solo admin o superiori possono assegnare permessi
            if (!in_array($adminRole, ['super_admin', 'utente_speciale', 'admin'])) {
                throw new Exception("Privilegi insufficienti per assegnare permessi");
            }
            
            $companyId = $auth->getCurrentCompany();
            if (!$companyId) {
                throw new Exception("Azienda non selezionata");
            }
            
            // Verifica che l'utente target appartenga alla stessa azienda
            $stmt = db_query("
                SELECT ua.azienda_id 
                FROM utenti_aziende ua 
                WHERE ua.utente_id = ? AND ua.azienda_id = ?
            ", [$targetUserId, $companyId]);
            
            if (!$stmt->fetch()) {
                throw new Exception("Utente non appartiene all'azienda corrente");
            }
            
            db_begin_transaction();
            
            try {
                // Rimuovi permessi esistenti per questa risorsa
                if ($resourceType && $resourceId) {
                    if ($resourceType === 'document') {
                        db_delete('document_permissions', 
                            'user_id = ? AND document_id = ? AND azienda_id = ?', 
                            [$targetUserId, $resourceId, $companyId]);
                    } elseif ($resourceType === 'folder') {
                        db_delete('folder_permissions', 
                            'user_id = ? AND folder_id = ? AND azienda_id = ?', 
                            [$targetUserId, $resourceId, $companyId]);
                    }
                }
                
                // Aggiungi nuovi permessi
                foreach ($permissions as $permission) {
                    if ($resourceType === 'document' && $resourceId) {
                        db_insert('document_permissions', [
                            'document_id' => $resourceId,
                            'user_id' => $targetUserId,
                            'permission_type' => $permission,
                            'granted_by' => $adminUserId,
                            'azienda_id' => $companyId
                        ]);
                    } elseif ($resourceType === 'folder' && $resourceId) {
                        db_insert('folder_permissions', [
                            'folder_id' => $resourceId,
                            'user_id' => $targetUserId,
                            'permission_type' => $permission,
                            'granted_by' => $adminUserId,
                            'azienda_id' => $companyId
                        ]);
                    }
                }
                
                // Log attività
                ActivityLogger::getInstance()->log('permissions_assigned', $resourceType, $resourceId, [
                    'target_user_id' => $targetUserId,
                    'permissions' => $permissions,
                    'assigned_by' => $adminUserId
                ]);
                
                // Pulisci cache
                $this->clearUserCache($targetUserId);
                
                db_commit();
                return true;
                
            } catch (Exception $e) {
                db_rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            ActivityLogger::getInstance()->logError('assign_permissions_error', [
                'target_user_id' => $targetUserId,
                'permissions' => $permissions,
                'admin_user_id' => $adminUserId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Verifica permessi ISO specifici
     */
    private function hasISOPermissions($userId, $action) {
        try {
            $stmt = db_query("SELECT ruolo FROM utenti WHERE id = ?", [$userId]);
            $userRole = $stmt->fetchColumn();
            
            // Solo utente_speciale e super_admin hanno permessi ISO completi
            if (in_array($userRole, ['super_admin', 'utente_speciale'])) {
                return true;
            }
            
            // Verifica permessi ISO specifici se esistono
            $stmt = db_query("
                SELECT COUNT(*) 
                FROM iso_user_permissions iup
                JOIN iso_permissions ip ON iup.permission_id = ip.id
                WHERE iup.user_id = ? AND ip.permission_name = ? AND iup.active = 1
            ", [$userId, $action]);
            
            return $stmt->fetchColumn() > 0;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Verifica se utente può cambiare azienda
     */
    private function canSwitchCompany($userId, $targetCompanyId) {
        try {
            // Verifica se l'utente appartiene all'azienda target
            $stmt = db_query("
                SELECT ua.ruolo_azienda 
                FROM utenti_aziende ua
                JOIN aziende a ON ua.azienda_id = a.id
                WHERE ua.utente_id = ? AND ua.azienda_id = ? AND a.stato = 'attiva' AND ua.attivo = 1
            ", [$userId, $targetCompanyId]);
            
            return $stmt->fetch() !== false;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Ottieni permessi specifici di un utente
     */
    private function getSpecificUserPermissions($userId, $companyId) {
        try {
            $permissions = [];
            
            // Permessi documenti
            $stmt = db_query("
                SELECT DISTINCT permission_type 
                FROM document_permissions 
                WHERE user_id = ? AND azienda_id = ?
            ", [$userId, $companyId]);
            
            while ($row = $stmt->fetch()) {
                $permissions[] = 'document_' . $row['permission_type'];
            }
            
            // Permessi cartelle
            $stmt = db_query("
                SELECT DISTINCT permission_type 
                FROM folder_permissions 
                WHERE user_id = ? AND azienda_id = ?
            ", [$userId, $companyId]);
            
            while ($row = $stmt->fetch()) {
                $permissions[] = 'folder_' . $row['permission_type'];
            }
            
            return $permissions;
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Verifica permessi specifici per documento
     */
    private function checkSpecificDocumentPermission($documentId, $userId, $permission) {
        try {
            $stmt = db_query("
                SELECT COUNT(*) 
                FROM document_permissions 
                WHERE document_id = ? AND user_id = ? AND permission_type = ?
            ", [$documentId, $userId, $permission]);
            
            return $stmt->fetchColumn() > 0;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Verifica permessi specifici per cartella
     */
    private function checkSpecificFolderPermission($folderId, $userId, $permission) {
        try {
            $stmt = db_query("
                SELECT COUNT(*) 
                FROM folder_permissions 
                WHERE folder_id = ? AND user_id = ? AND permission_type = ?
            ", [$folderId, $userId, $permission]);
            
            return $stmt->fetchColumn() > 0;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Verifica controlli addizionali per documenti
     */
    private function performAdditionalDocumentChecks($document, $permission, $userId) {
        // Documenti in stato bozza visibili solo al creatore o admin
        if ($document['stato'] === 'bozza' && $permission === 'view') {
            return $document['creato_da'] == $userId || 
                   in_array($document['ruolo'], ['super_admin', 'admin', 'utente_speciale']);
        }
        
        // Documenti archiviati solo leggibili
        if ($document['stato'] === 'archiviato' && in_array($permission, ['edit', 'delete'])) {
            return in_array($document['ruolo'], ['super_admin', 'admin']);
        }
        
        return true;
    }
    
    /**
     * Verifica permessi ereditati per cartelle
     */
    private function checkInheritedFolderPermissions($folder, $permission, $userId) {
        // Se la cartella ha un parent, verifica i permessi del parent
        if ($folder['parent_id']) {
            return $this->checkFolderAccess($folder['parent_id'], $permission, $userId, $folder['azienda_id']);
        }
        
        return true;
    }
    
    /**
     * Ottieni permessi di un ruolo
     */
    private function getRolePermissions($role) {
        return self::ROLE_PERMISSIONS[$role] ?? [];
    }
    
    /**
     * Cache del risultato
     */
    private function cacheResult($key, $result) {
        $this->cache[$key] = [
            'result' => $result,
            'timestamp' => time()
        ];
        return $result;
    }
    
    /**
     * Pulisci cache utente
     */
    private function clearUserCache($userId) {
        foreach (array_keys($this->cache) as $key) {
            if (strpos($key, "_{$userId}_") !== false) {
                unset($this->cache[$key]);
            }
        }
    }
    
    /**
     * Pulisci tutta la cache
     */
    public function clearCache() {
        $this->cache = [];
    }
    
    /**
     * Ottieni elenco di tutti i permessi disponibili
     */
    public function getAvailablePermissions() {
        return self::PERMISSIONS;
    }
    
    /**
     * Verifica IP whitelisting per operazioni super admin
     */
    public function checkIPWhitelist($action) {
        if (!in_array($action, ['system_admin', 'iso_structure_admin'])) {
            return true;
        }
        
        $allowedIPs = [
            '127.0.0.1',
            '::1',
            // Aggiungi altri IP autorizzati qui
        ];
        
        $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
        return in_array($clientIP, $allowedIPs);
    }
}