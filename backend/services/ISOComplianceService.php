<?php

namespace Nexio\Services;

use Exception;
use PDO;
use Nexio\Utils\ActivityLogger;
use Nexio\Utils\ISOStructureManager;
use Nexio\Services\ISOSecurityService;
use Nexio\Services\ISOStorageService;

/**
 * ISO Compliance Service - Core business logic for ISO document management
 * 
 * This service handles all core ISO compliance operations including:
 * - Company configuration initialization
 * - Folder structure creation from templates
 * - Document CRUD operations with versioning
 * - Bulk operations and search functionality
 * - Permission checks and compliance validation
 * 
 * @package Nexio\Services
 * @version 1.0.0
 * @author Claude Code
 */
class ISOComplianceService
{
    /** @var ISOComplianceService|null Singleton instance */
    private static ?ISOComplianceService $instance = null;
    
    /** @var ActivityLogger Activity logger instance */
    private ActivityLogger $logger;
    
    /** @var ISOStructureManager Structure manager instance */
    private ISOStructureManager $structureManager;
    
    /** @var ISOSecurityService Security service instance */
    private ISOSecurityService $securityService;
    
    /** @var ISOStorageService Storage service instance */
    private ISOStorageService $storageService;
    
    /** @var array Cache for company configurations */
    private array $configCache = [];
    
    /** @var array Performance metrics */
    private array $metrics = [
        'operations_count' => 0,
        'total_execution_time' => 0.0,
        'cache_hits' => 0,
        'cache_misses' => 0
    ];
    
    /**
     * Private constructor for Singleton pattern
     */
    private function __construct()
    {
        $this->logger = ActivityLogger::getInstance();
        $this->structureManager = ISOStructureManager::getInstance();
        $this->securityService = ISOSecurityService::getInstance();
        $this->storageService = ISOStorageService::getInstance();
    }
    
    /**
     * Get singleton instance
     * 
     * @return ISOComplianceService
     */
    public static function getInstance(): ISOComplianceService
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
     * Initialize company ISO configuration
     * 
     * @param int $companyId Company ID
     * @param string $structureType Structure type (separata, integrata, personalizzata)
     * @param array $activeStandards Active ISO standards
     * @param array $options Additional configuration options
     * @param int|null $userId User performing the operation
     * @return array Operation result
     * @throws Exception
     */
    public function initializeCompanyConfiguration(
        int $companyId,
        string $structureType,
        array $activeStandards,
        array $options = [],
        ?int $userId = null
    ): array {
        $startTime = microtime(true);
        $this->metrics['operations_count']++;
        
        try {
            // Check permissions
            if (!$this->securityService->canManageISOStructure($userId, $companyId)) {
                throw new Exception("Permesso negato per gestire la struttura ISO");
            }
            
            // Validate existing configuration
            $existingConfig = $this->getCompanyConfiguration($companyId);
            if ($existingConfig && $existingConfig['stato'] === 'attiva') {
                throw new Exception("La configurazione ISO è già attiva per questa azienda");
            }
            
            // Initialize structure through structure manager
            $result = $this->structureManager->initializeCompanyStructure(
                $companyId,
                $structureType,
                $activeStandards,
                $options['advanced_config'] ?? [],
                $userId
            );
            
            // Create default document templates if requested
            if ($options['create_default_templates'] ?? true) {
                $this->createDefaultDocumentTemplates($companyId, $activeStandards, $userId);
            }
            
            // Set up default permissions if requested
            if ($options['setup_default_permissions'] ?? true) {
                $this->setupDefaultPermissions($companyId, $userId);
            }
            
            // Clear cache
            $this->clearCompanyCache($companyId);
            
            // Log operation
            $this->logger->log('iso_config_initialized', 'aziende_iso_config', $companyId, [
                'structure_type' => $structureType,
                'active_standards' => $activeStandards,
                'folders_created' => $result['folders_created'],
                'execution_time' => microtime(true) - $startTime
            ]);
            
            $this->metrics['total_execution_time'] += microtime(true) - $startTime;
            
            return [
                'success' => true,
                'message' => 'Configurazione ISO inizializzata con successo',
                'data' => $result
            ];
            
        } catch (Exception $e) {
            $this->logger->log('iso_config_init_failed', 'aziende_iso_config', $companyId, [
                'error' => $e->getMessage(),
                'structure_type' => $structureType,
                'active_standards' => $activeStandards
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Create document with ISO compliance
     * 
     * @param array $documentData Document data
     * @param array|null $fileData File upload data
     * @param int $userId User creating the document
     * @return int Created document ID
     * @throws Exception
     */
    public function createDocument(array $documentData, ?array $fileData, int $userId): int
    {
        $startTime = microtime(true);
        $this->metrics['operations_count']++;
        
        try {
            // Validate required fields
            $this->validateDocumentData($documentData);
            
            // Check permissions
            if (!$this->securityService->canCreateDocument($userId, $documentData['cartella_id'])) {
                throw new Exception("Permesso negato per creare documenti in questa cartella");
            }
            
            // Get folder ISO metadata
            $folderMetadata = $this->getFolderISOMetadata($documentData['cartella_id']);
            if (!$folderMetadata) {
                throw new Exception("Cartella non trovata o non configurata per ISO");
            }
            
            db_begin_transaction();
            
            // Handle file upload if present
            $fileInfo = null;
            if ($fileData) {
                $fileInfo = $this->storageService->storeFile(
                    $fileData,
                    $documentData['azienda_id'],
                    $documentData['cartella_id'],
                    $userId
                );
                
                // Extract content for search
                $documentData['contenuto_ricerca'] = $this->storageService->extractFileContent(
                    $fileInfo['path'],
                    $fileData['type']
                );
            }
            
            // Generate document code based on ISO template
            if (empty($documentData['codice'])) {
                $documentData['codice'] = $this->generateDocumentCode(
                    $documentData['azienda_id'],
                    $folderMetadata['iso_standard_codice'],
                    $documentData['tipo_documento'] ?? 'DOC'
                );
            }
            
            // Calculate next revision date
            $revisionFrequency = $documentData['frequenza_revisione'] ?? 365; // Default 1 year
            $nextRevision = date('Y-m-d', strtotime("+{$revisionFrequency} days"));
            
            // Insert document
            $documentId = db_insert('documenti', [
                'codice' => $documentData['codice'],
                'titolo' => $documentData['titolo'],
                'descrizione' => $documentData['descrizione'] ?? null,
                'cartella_id' => $documentData['cartella_id'],
                'azienda_id' => $documentData['azienda_id'],
                'tipo_documento' => $documentData['tipo_documento'] ?? 'documento',
                'classificazione_id' => $documentData['classificazione_id'] ?? null,
                'file_path' => $fileInfo['path'] ?? null,
                'file_size' => $fileInfo['size'] ?? 0,
                'file_hash' => $fileInfo['hash'] ?? null,
                'contenuto_html' => $documentData['contenuto_html'] ?? null,
                'contenuto_ricerca' => $documentData['contenuto_ricerca'] ?? null,
                'stato' => $documentData['stato'] ?? 'bozza',
                'versione' => 1,
                'creato_da' => $userId,
                'responsabile_id' => $documentData['responsabile_id'] ?? $userId,
                'data_prossima_revisione' => $nextRevision,
                'iso_metadata' => json_encode([
                    'standard_code' => $folderMetadata['iso_standard_codice'],
                    'template_code' => $folderMetadata['iso_template_code'] ?? null,
                    'compliance_level' => $folderMetadata['iso_compliance_level'],
                    'revision_frequency' => $revisionFrequency,
                    'distribution_level' => $documentData['livello_distribuzione'] ?? 'interno',
                    'process_reference' => $documentData['processo_riferimento'] ?? null,
                    'normative_references' => $documentData['riferimenti_normativi'] ?? [],
                    'keywords' => $documentData['parole_chiave'] ?? []
                ])
            ]);
            
            // Create first version
            $this->createDocumentVersion($documentId, 1, $fileInfo, $userId, 'Versione iniziale');
            
            // Handle document relations if specified
            if (!empty($documentData['documenti_correlati'])) {
                $this->createDocumentRelations($documentId, $documentData['documenti_correlati']);
            }
            
            // Set up initial permissions
            $this->securityService->setupDocumentPermissions($documentId, $documentData);
            
            // Schedule revision notification
            $this->scheduleRevisionNotification($documentId, $nextRevision);
            
            db_commit();
            
            // Log activity
            $this->logger->log('iso_document_created', 'documenti', $documentId, [
                'codice' => $documentData['codice'],
                'titolo' => $documentData['titolo'],
                'tipo' => $documentData['tipo_documento'],
                'standard' => $folderMetadata['iso_standard_codice']
            ]);
            
            $this->metrics['total_execution_time'] += microtime(true) - $startTime;
            
            return $documentId;
            
        } catch (Exception $e) {
            db_rollback();
            throw $e;
        }
    }
    
    /**
     * Update document with version control
     * 
     * @param int $documentId Document ID
     * @param array $updateData Update data
     * @param array|null $fileData New file data
     * @param int $userId User performing update
     * @param string $versionNotes Version notes
     * @return array Update result
     * @throws Exception
     */
    public function updateDocument(
        int $documentId,
        array $updateData,
        ?array $fileData,
        int $userId,
        string $versionNotes = ''
    ): array {
        $startTime = microtime(true);
        $this->metrics['operations_count']++;
        
        try {
            // Get current document
            $currentDoc = $this->getDocument($documentId);
            if (!$currentDoc) {
                throw new Exception("Documento non trovato");
            }
            
            // Check permissions
            if (!$this->securityService->canUpdateDocument($userId, $documentId)) {
                throw new Exception("Permesso negato per modificare questo documento");
            }
            
            db_begin_transaction();
            
            // Determine if new version is needed
            $needsNewVersion = $this->requiresNewVersion($currentDoc, $updateData, $fileData);
            
            if ($needsNewVersion) {
                // Create new version
                $newVersion = $currentDoc['versione'] + 1;
                
                // Handle file upload if present
                $fileInfo = null;
                if ($fileData) {
                    $fileInfo = $this->storageService->storeFile(
                        $fileData,
                        $currentDoc['azienda_id'],
                        $currentDoc['cartella_id'],
                        $userId
                    );
                    
                    $updateData['file_path'] = $fileInfo['path'];
                    $updateData['file_size'] = $fileInfo['size'];
                    $updateData['file_hash'] = $fileInfo['hash'];
                    $updateData['contenuto_ricerca'] = $this->storageService->extractFileContent(
                        $fileInfo['path'],
                        $fileData['type']
                    );
                }
                
                // Update document
                $updateData['versione'] = $newVersion;
                $updateData['modificato_da'] = $userId;
                $updateData['data_modifica'] = date('Y-m-d H:i:s');
                
                db_update('documenti', $updateData, 'id = ?', [$documentId]);
                
                // Create version record
                $this->createDocumentVersion(
                    $documentId,
                    $newVersion,
                    $fileInfo,
                    $userId,
                    $versionNotes ?: "Aggiornamento documento"
                );
                
                // Archive previous version if file changed
                if ($fileData && $currentDoc['file_path']) {
                    $this->storageService->archiveFile(
                        $currentDoc['file_path'],
                        $currentDoc['azienda_id'],
                        $documentId,
                        $currentDoc['versione']
                    );
                }
            } else {
                // Minor update without version increment
                $updateData['modificato_da'] = $userId;
                $updateData['data_modifica'] = date('Y-m-d H:i:s');
                
                db_update('documenti', $updateData, 'id = ?', [$documentId]);
            }
            
            // Update ISO metadata if needed
            if (isset($updateData['iso_metadata_update'])) {
                $this->updateDocumentISOMetadata($documentId, $updateData['iso_metadata_update']);
            }
            
            // Check if revision date needs update
            if (isset($updateData['stato']) && $updateData['stato'] === 'approvato') {
                $this->updateRevisionSchedule($documentId);
            }
            
            db_commit();
            
            // Log activity
            $this->logger->log('iso_document_updated', 'documenti', $documentId, [
                'version' => $needsNewVersion ? $newVersion : $currentDoc['versione'],
                'changes' => array_keys($updateData),
                'notes' => $versionNotes
            ]);
            
            $this->metrics['total_execution_time'] += microtime(true) - $startTime;
            
            return [
                'success' => true,
                'document_id' => $documentId,
                'new_version' => $needsNewVersion ? $newVersion : $currentDoc['versione'],
                'version_created' => $needsNewVersion
            ];
            
        } catch (Exception $e) {
            db_rollback();
            throw $e;
        }
    }
    
    /**
     * Delete document (soft delete with audit trail)
     * 
     * @param int $documentId Document ID
     * @param int $userId User performing deletion
     * @param string $reason Deletion reason
     * @return bool Success
     * @throws Exception
     */
    public function deleteDocument(int $documentId, int $userId, string $reason = ''): bool
    {
        try {
            // Check permissions
            if (!$this->securityService->canDeleteDocument($userId, $documentId)) {
                throw new Exception("Permesso negato per eliminare questo documento");
            }
            
            // Get document info
            $document = $this->getDocument($documentId);
            if (!$document) {
                throw new Exception("Documento non trovato");
            }
            
            db_begin_transaction();
            
            // Soft delete document
            db_update('documenti', [
                'eliminato' => 1,
                'data_eliminazione' => date('Y-m-d H:i:s'),
                'eliminato_da' => $userId,
                'motivo_eliminazione' => $reason
            ], 'id = ?', [$documentId]);
            
            // Archive file if exists
            if ($document['file_path']) {
                $this->storageService->archiveFile(
                    $document['file_path'],
                    $document['azienda_id'],
                    $documentId,
                    $document['versione'],
                    true // Mark as deleted
                );
            }
            
            // Cancel scheduled notifications
            $this->cancelDocumentNotifications($documentId);
            
            db_commit();
            
            // Log activity (with GDPR compliance)
            $this->logger->log('iso_document_deleted', 'documenti', $documentId, [
                'codice' => $document['codice'],
                'titolo' => $document['titolo'],
                'reason' => $reason,
                'gdpr_compliant' => true
            ]);
            
            return true;
            
        } catch (Exception $e) {
            db_rollback();
            throw $e;
        }
    }
    
    /**
     * Search documents with advanced filters
     * 
     * @param array $criteria Search criteria
     * @param int $userId User performing search
     * @param array $options Search options (pagination, sorting)
     * @return array Search results
     */
    public function searchDocuments(array $criteria, int $userId, array $options = []): array
    {
        $this->metrics['operations_count']++;
        
        // Build base query
        $sql = "
            SELECT DISTINCT d.*,
                   c.nome as cartella_nome,
                   c.percorso_completo,
                   u.nome as creatore_nome,
                   u.cognome as creatore_cognome,
                   r.nome as responsabile_nome,
                   r.cognome as responsabile_cognome,
                   cl.nome as classificazione_nome
            FROM documenti d
            INNER JOIN cartelle c ON d.cartella_id = c.id
            LEFT JOIN utenti u ON d.creato_da = u.id
            LEFT JOIN utenti r ON d.responsabile_id = r.id
            LEFT JOIN classificazioni cl ON d.classificazione_id = cl.id
            WHERE d.eliminato = 0
        ";
        
        $params = [];
        $conditions = [];
        
        // Apply security filters
        $allowedFolders = $this->securityService->getUserAllowedFolders($userId);
        if (!empty($allowedFolders)) {
            $placeholders = str_repeat('?,', count($allowedFolders) - 1) . '?';
            $conditions[] = "d.cartella_id IN ($placeholders)";
            $params = array_merge($params, $allowedFolders);
        } else {
            // User has no access to any folders
            return ['total' => 0, 'documents' => []];
        }
        
        // Apply search criteria
        if (!empty($criteria['search_text'])) {
            $searchText = '%' . $criteria['search_text'] . '%';
            $conditions[] = "(d.titolo LIKE ? OR d.descrizione LIKE ? OR d.contenuto_ricerca LIKE ? OR d.codice LIKE ?)";
            $params = array_merge($params, [$searchText, $searchText, $searchText, $searchText]);
        }
        
        if (!empty($criteria['azienda_id'])) {
            $conditions[] = "d.azienda_id = ?";
            $params[] = $criteria['azienda_id'];
        }
        
        if (!empty($criteria['tipo_documento'])) {
            $conditions[] = "d.tipo_documento = ?";
            $params[] = $criteria['tipo_documento'];
        }
        
        if (!empty($criteria['stato'])) {
            $conditions[] = "d.stato = ?";
            $params[] = $criteria['stato'];
        }
        
        if (!empty($criteria['classificazione_id'])) {
            $conditions[] = "d.classificazione_id = ?";
            $params[] = $criteria['classificazione_id'];
        }
        
        if (!empty($criteria['iso_standard'])) {
            $conditions[] = "JSON_EXTRACT(d.iso_metadata, '$.standard_code') = ?";
            $params[] = $criteria['iso_standard'];
        }
        
        if (!empty($criteria['revision_due'])) {
            $conditions[] = "d.data_prossima_revisione <= DATE_ADD(CURDATE(), INTERVAL ? DAY)";
            $params[] = $criteria['revision_due'];
        }
        
        // Add conditions to query
        if (!empty($conditions)) {
            $sql .= " AND " . implode(" AND ", $conditions);
        }
        
        // Count total results
        $countSql = "SELECT COUNT(DISTINCT d.id) FROM documenti d " . 
                    "INNER JOIN cartelle c ON d.cartella_id = c.id " .
                    "WHERE d.eliminato = 0" . 
                    (!empty($conditions) ? " AND " . implode(" AND ", $conditions) : "");
        
        $totalCount = db_query($countSql, $params)->fetchColumn();
        
        // Apply sorting
        $orderBy = $options['order_by'] ?? 'd.data_creazione';
        $orderDir = $options['order_dir'] ?? 'DESC';
        $sql .= " ORDER BY {$orderBy} {$orderDir}";
        
        // Apply pagination
        $limit = $options['limit'] ?? 50;
        $offset = $options['offset'] ?? 0;
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        // Execute query
        $stmt = db_query($sql, $params);
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process results
        foreach ($documents as &$doc) {
            $doc['iso_metadata'] = json_decode($doc['iso_metadata'], true);
            $doc['can_edit'] = $this->securityService->canUpdateDocument($userId, $doc['id']);
            $doc['can_delete'] = $this->securityService->canDeleteDocument($userId, $doc['id']);
            $doc['revision_status'] = $this->getRevisionStatus($doc);
        }
        
        return [
            'total' => $totalCount,
            'documents' => $documents,
            'page' => floor($offset / $limit) + 1,
            'per_page' => $limit,
            'total_pages' => ceil($totalCount / $limit)
        ];
    }
    
    /**
     * Perform bulk operations on documents
     * 
     * @param array $documentIds Document IDs
     * @param string $operation Operation type
     * @param array $operationData Operation data
     * @param int $userId User performing operation
     * @return array Operation results
     * @throws Exception
     */
    public function bulkOperation(
        array $documentIds,
        string $operation,
        array $operationData,
        int $userId
    ): array {
        $startTime = microtime(true);
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];
        
        try {
            db_begin_transaction();
            
            foreach ($documentIds as $documentId) {
                try {
                    switch ($operation) {
                        case 'update_status':
                            $this->bulkUpdateStatus($documentId, $operationData['stato'], $userId);
                            break;
                            
                        case 'move':
                            $this->bulkMoveDocument($documentId, $operationData['cartella_id'], $userId);
                            break;
                            
                        case 'assign_responsible':
                            $this->bulkAssignResponsible($documentId, $operationData['responsabile_id'], $userId);
                            break;
                            
                        case 'update_classification':
                            $this->bulkUpdateClassification($documentId, $operationData['classificazione_id'], $userId);
                            break;
                            
                        case 'export':
                            $this->bulkExportDocument($documentId, $operationData['format'], $userId);
                            break;
                            
                        case 'archive':
                            $this->bulkArchiveDocument($documentId, $userId);
                            break;
                            
                        default:
                            throw new Exception("Operazione non supportata: {$operation}");
                    }
                    
                    $results['success']++;
                    
                } catch (Exception $e) {
                    $results['failed']++;
                    $results['errors'][$documentId] = $e->getMessage();
                }
            }
            
            db_commit();
            
            // Log bulk operation
            $this->logger->log('iso_bulk_operation', 'documenti', null, [
                'operation' => $operation,
                'total_documents' => count($documentIds),
                'success' => $results['success'],
                'failed' => $results['failed'],
                'execution_time' => microtime(true) - $startTime
            ]);
            
            $this->metrics['total_execution_time'] += microtime(true) - $startTime;
            
            return $results;
            
        } catch (Exception $e) {
            db_rollback();
            throw $e;
        }
    }
    
    /**
     * Get company configuration with caching
     * 
     * @param int $companyId Company ID
     * @return array|null Configuration data
     */
    public function getCompanyConfiguration(int $companyId): ?array
    {
        $cacheKey = "config_{$companyId}";
        
        if (isset($this->configCache[$cacheKey])) {
            $this->metrics['cache_hits']++;
            return $this->configCache[$cacheKey];
        }
        
        $this->metrics['cache_misses']++;
        
        $config = $this->structureManager->getCompanyConfiguration($companyId);
        
        if ($config) {
            $this->configCache[$cacheKey] = $config;
        }
        
        return $config;
    }
    
    /**
     * Get document by ID
     * 
     * @param int $documentId Document ID
     * @return array|null Document data
     */
    private function getDocument(int $documentId): ?array
    {
        $stmt = db_query("
            SELECT d.*, c.iso_standard_codice, c.iso_metadata as cartella_iso_metadata
            FROM documenti d
            INNER JOIN cartelle c ON d.cartella_id = c.id
            WHERE d.id = ? AND d.eliminato = 0
        ", [$documentId]);
        
        $document = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($document) {
            $document['iso_metadata'] = json_decode($document['iso_metadata'], true);
            $document['cartella_iso_metadata'] = json_decode($document['cartella_iso_metadata'], true);
        }
        
        return $document ?: null;
    }
    
    /**
     * Get folder ISO metadata
     * 
     * @param int $folderId Folder ID
     * @return array|null Metadata
     */
    private function getFolderISOMetadata(int $folderId): ?array
    {
        $stmt = db_query("
            SELECT c.*, 
                   c.iso_metadata,
                   ift.codice as iso_template_code,
                   ift.nome as iso_template_nome
            FROM cartelle c
            LEFT JOIN iso_folder_templates ift ON c.iso_template_id = ift.id
            WHERE c.id = ?
        ", [$folderId]);
        
        $folder = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($folder) {
            $folder['iso_metadata'] = json_decode($folder['iso_metadata'], true);
        }
        
        return $folder ?: null;
    }
    
    /**
     * Create document version record
     * 
     * @param int $documentId Document ID
     * @param int $version Version number
     * @param array|null $fileInfo File information
     * @param int $userId User ID
     * @param string $notes Version notes
     */
    private function createDocumentVersion(
        int $documentId,
        int $version,
        ?array $fileInfo,
        int $userId,
        string $notes
    ): void {
        db_insert('documenti_versioni', [
            'documento_id' => $documentId,
            'versione' => $version,
            'file_path' => $fileInfo['path'] ?? null,
            'file_size' => $fileInfo['size'] ?? 0,
            'file_hash' => $fileInfo['hash'] ?? null,
            'note_versione' => $notes,
            'creato_da' => $userId,
            'data_creazione' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Validate document data
     * 
     * @param array $data Document data
     * @throws Exception
     */
    private function validateDocumentData(array $data): void
    {
        $required = ['titolo', 'cartella_id', 'azienda_id'];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Campo obbligatorio mancante: {$field}");
            }
        }
        
        // Validate document type
        $validTypes = ['documento', 'procedura', 'modulo', 'manuale', 'politica', 'registrazione'];
        if (!empty($data['tipo_documento']) && !in_array($data['tipo_documento'], $validTypes)) {
            throw new Exception("Tipo documento non valido");
        }
        
        // Validate status
        $validStates = ['bozza', 'in_revisione', 'approvato', 'pubblicato', 'obsoleto'];
        if (!empty($data['stato']) && !in_array($data['stato'], $validStates)) {
            throw new Exception("Stato documento non valido");
        }
    }
    
    /**
     * Generate unique document code
     * 
     * @param int $companyId Company ID
     * @param string $standardCode ISO standard code
     * @param string $docType Document type
     * @return string Generated code
     */
    private function generateDocumentCode(int $companyId, string $standardCode, string $docType): string
    {
        // Get prefix mapping
        $prefixMap = [
            'procedura' => 'PRO',
            'modulo' => 'MOD',
            'manuale' => 'MAN',
            'politica' => 'POL',
            'registrazione' => 'REG',
            'documento' => 'DOC'
        ];
        
        $prefix = $prefixMap[strtolower($docType)] ?? 'DOC';
        $year = date('Y');
        
        // Get next number for this combination
        $stmt = db_query("
            SELECT MAX(CAST(SUBSTRING_INDEX(codice, '-', -1) AS UNSIGNED)) as max_num
            FROM documenti
            WHERE azienda_id = ? 
            AND codice LIKE ?
            AND YEAR(data_creazione) = ?
        ", [$companyId, "{$standardCode}-{$prefix}-{$year}-%", $year]);
        
        $maxNum = $stmt->fetchColumn() ?: 0;
        $nextNum = $maxNum + 1;
        
        return sprintf('%s-%s-%s-%04d', $standardCode, $prefix, $year, $nextNum);
    }
    
    /**
     * Determine if document update requires new version
     * 
     * @param array $currentDoc Current document
     * @param array $updateData Update data
     * @param array|null $fileData File data
     * @return bool
     */
    private function requiresNewVersion(array $currentDoc, array $updateData, ?array $fileData): bool
    {
        // File change always requires new version
        if ($fileData !== null) {
            return true;
        }
        
        // Significant content changes
        if (isset($updateData['contenuto_html']) && 
            $updateData['contenuto_html'] !== $currentDoc['contenuto_html']) {
            return true;
        }
        
        // Status change to approved
        if (isset($updateData['stato']) && 
            $updateData['stato'] === 'approvato' && 
            $currentDoc['stato'] !== 'approvato') {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get document revision status
     * 
     * @param array $document Document data
     * @return string Status
     */
    private function getRevisionStatus(array $document): string
    {
        if (empty($document['data_prossima_revisione'])) {
            return 'not_scheduled';
        }
        
        $daysUntilRevision = (strtotime($document['data_prossima_revisione']) - time()) / 86400;
        
        if ($daysUntilRevision < 0) {
            return 'overdue';
        } elseif ($daysUntilRevision <= 30) {
            return 'due_soon';
        } else {
            return 'ok';
        }
    }
    
    /**
     * Create default document templates
     * 
     * @param int $companyId Company ID
     * @param array $standards Active standards
     * @param int|null $userId User ID
     */
    private function createDefaultDocumentTemplates(int $companyId, array $standards, ?int $userId): void
    {
        $templates = [
            'ISO9001' => [
                ['nome' => 'Procedura Gestionale', 'tipo' => 'procedura'],
                ['nome' => 'Modulo di Registrazione', 'tipo' => 'modulo'],
                ['nome' => 'Manuale Qualità', 'tipo' => 'manuale']
            ],
            'ISO14001' => [
                ['nome' => 'Procedura Ambientale', 'tipo' => 'procedura'],
                ['nome' => 'Registro Aspetti Ambientali', 'tipo' => 'registrazione']
            ],
            'ISO45001' => [
                ['nome' => 'Procedura Sicurezza', 'tipo' => 'procedura'],
                ['nome' => 'DVR - Documento Valutazione Rischi', 'tipo' => 'documento']
            ]
        ];
        
        foreach ($standards as $standard) {
            if (isset($templates[$standard])) {
                foreach ($templates[$standard] as $template) {
                    db_insert('template_documenti', [
                        'nome' => $template['nome'],
                        'tipo_documento' => $template['tipo'],
                        'azienda_id' => $companyId,
                        'iso_standard' => $standard,
                        'attivo' => 1,
                        'creato_da' => $userId
                    ]);
                }
            }
        }
    }
    
    /**
     * Setup default permissions for ISO structure
     * 
     * @param int $companyId Company ID
     * @param int|null $userId User ID
     */
    private function setupDefaultPermissions(int $companyId, ?int $userId): void
    {
        // Get company admin users
        $stmt = db_query("
            SELECT ua.utente_id
            FROM utenti_aziende ua
            WHERE ua.azienda_id = ? AND ua.ruolo_azienda = 'admin'
        ", [$companyId]);
        
        $adminUsers = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Grant full access to ISO folders for admins
        foreach ($adminUsers as $adminId) {
            $this->securityService->grantFolderAccess($adminId, $companyId, 'full');
        }
    }
    
    /**
     * Clear company cache
     * 
     * @param int $companyId Company ID
     */
    private function clearCompanyCache(int $companyId): void
    {
        $cacheKey = "config_{$companyId}";
        unset($this->configCache[$cacheKey]);
    }
    
    /**
     * Get service metrics
     * 
     * @return array Metrics data
     */
    public function getMetrics(): array
    {
        return array_merge($this->metrics, [
            'avg_execution_time' => $this->metrics['operations_count'] > 0 
                ? $this->metrics['total_execution_time'] / $this->metrics['operations_count'] 
                : 0,
            'cache_hit_rate' => ($this->metrics['cache_hits'] + $this->metrics['cache_misses']) > 0
                ? $this->metrics['cache_hits'] / ($this->metrics['cache_hits'] + $this->metrics['cache_misses'])
                : 0
        ]);
    }
    
    // Stub methods for features mentioned but not fully implemented
    
    private function createDocumentRelations(int $documentId, array $relatedDocIds): void
    {
        foreach ($relatedDocIds as $relatedId) {
            db_insert('documenti_relazioni', [
                'documento_id' => $documentId,
                'documento_correlato_id' => $relatedId,
                'tipo_relazione' => 'riferimento'
            ]);
        }
    }
    
    private function scheduleRevisionNotification(int $documentId, string $revisionDate): void
    {
        // Implementation would schedule notifications
    }
    
    private function cancelDocumentNotifications(int $documentId): void
    {
        // Implementation would cancel scheduled notifications
    }
    
    private function updateDocumentISOMetadata(int $documentId, array $metadata): void
    {
        $currentDoc = $this->getDocument($documentId);
        $currentMeta = $currentDoc['iso_metadata'] ?? [];
        $updatedMeta = array_merge($currentMeta, $metadata);
        
        db_update('documenti', [
            'iso_metadata' => json_encode($updatedMeta)
        ], 'id = ?', [$documentId]);
    }
    
    private function updateRevisionSchedule(int $documentId): void
    {
        $doc = $this->getDocument($documentId);
        $frequency = $doc['iso_metadata']['revision_frequency'] ?? 365;
        $nextRevision = date('Y-m-d', strtotime("+{$frequency} days"));
        
        db_update('documenti', [
            'data_prossima_revisione' => $nextRevision
        ], 'id = ?', [$documentId]);
    }
    
    // Bulk operation helper methods
    
    private function bulkUpdateStatus(int $documentId, string $status, int $userId): void
    {
        if (!$this->securityService->canUpdateDocument($userId, $documentId)) {
            throw new Exception("Permesso negato per documento ID: {$documentId}");
        }
        
        db_update('documenti', [
            'stato' => $status,
            'modificato_da' => $userId,
            'data_modifica' => date('Y-m-d H:i:s')
        ], 'id = ?', [$documentId]);
    }
    
    private function bulkMoveDocument(int $documentId, int $newFolderId, int $userId): void
    {
        if (!$this->securityService->canUpdateDocument($userId, $documentId) ||
            !$this->securityService->canCreateDocument($userId, $newFolderId)) {
            throw new Exception("Permesso negato per spostare documento ID: {$documentId}");
        }
        
        db_update('documenti', [
            'cartella_id' => $newFolderId,
            'modificato_da' => $userId,
            'data_modifica' => date('Y-m-d H:i:s')
        ], 'id = ?', [$documentId]);
    }
    
    private function bulkAssignResponsible(int $documentId, int $responsibleId, int $userId): void
    {
        if (!$this->securityService->canUpdateDocument($userId, $documentId)) {
            throw new Exception("Permesso negato per documento ID: {$documentId}");
        }
        
        db_update('documenti', [
            'responsabile_id' => $responsibleId,
            'modificato_da' => $userId,
            'data_modifica' => date('Y-m-d H:i:s')
        ], 'id = ?', [$documentId]);
    }
    
    private function bulkUpdateClassification(int $documentId, int $classificationId, int $userId): void
    {
        if (!$this->securityService->canUpdateDocument($userId, $documentId)) {
            throw new Exception("Permesso negato per documento ID: {$documentId}");
        }
        
        db_update('documenti', [
            'classificazione_id' => $classificationId,
            'modificato_da' => $userId,
            'data_modifica' => date('Y-m-d H:i:s')
        ], 'id = ?', [$documentId]);
    }
    
    private function bulkExportDocument(int $documentId, string $format, int $userId): void
    {
        if (!$this->securityService->canViewDocument($userId, $documentId)) {
            throw new Exception("Permesso negato per esportare documento ID: {$documentId}");
        }
        
        // Export implementation would be handled by a separate export service
    }
    
    private function bulkArchiveDocument(int $documentId, int $userId): void
    {
        if (!$this->securityService->canUpdateDocument($userId, $documentId)) {
            throw new Exception("Permesso negato per archiviare documento ID: {$documentId}");
        }
        
        db_update('documenti', [
            'stato' => 'archiviato',
            'data_archiviazione' => date('Y-m-d H:i:s'),
            'archiviato_da' => $userId
        ], 'id = ?', [$documentId]);
    }
}