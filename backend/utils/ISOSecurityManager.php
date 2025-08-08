<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/ActivityLogger.php';
require_once __DIR__ . '/RateLimiter.php';

/**
 * ISOSecurityManager - Sistema di sicurezza avanzato per gestione ISO
 * 
 * Gestisce permessi granulari, audit trail completo, sicurezza operazioni,
 * rate limiting avanzato e conformità GDPR per il sistema documentale ISO.
 * 
 * Features:
 * - Permessi granulari per operazioni ISO
 * - Audit trail completo GDPR-compliant  
 * - Rate limiting avanzato per sicurezza
 * - Validazione input robusta
 * - Controllo accessi multi-livello
 * - Logging eventi sicurezza
 * - Backup automatico operazioni critiche
 * 
 * @package Nexio\Utils
 * @version 1.0.0
 */
class ISOSecurityManager
{
    private static $instance = null;
    private $logger;
    private $rateLimiter;
    
    // Definizioni permessi ISO
    private const ISO_PERMISSIONS = [
        // Gestione strutture
        'iso_structure_create' => 'Creare strutture documentali ISO',
        'iso_structure_modify' => 'Modificare strutture documentali ISO', 
        'iso_structure_delete' => 'Eliminare strutture documentali ISO',
        'iso_structure_export' => 'Esportare strutture documentali ISO',
        
        // Gestione cartelle
        'iso_folder_create' => 'Creare cartelle ISO',
        'iso_folder_modify' => 'Modificare cartelle ISO',
        'iso_folder_delete' => 'Eliminare cartelle ISO',
        'iso_folder_view' => 'Visualizzare cartelle ISO',
        
        // Gestione documenti
        'iso_document_upload' => 'Caricare documenti ISO',
        'iso_document_download' => 'Scaricare documenti ISO',
        'iso_document_modify' => 'Modificare documenti ISO',
        'iso_document_delete' => 'Eliminare documenti ISO',
        'iso_document_approve' => 'Approvare documenti ISO',
        'iso_document_version' => 'Gestire versioni documenti ISO',
        
        // Compliance e audit
        'iso_compliance_view' => 'Visualizzare stato conformità ISO',
        'iso_compliance_audit' => 'Eseguire audit conformità ISO',
        'iso_audit_view' => 'Visualizzare audit trail',
        'iso_audit_export' => 'Esportare audit trail',
        
        // Amministrazione
        'iso_admin_config' => 'Configurare sistema ISO',
        'iso_admin_users' => 'Gestire utenti sistema ISO',
        'iso_admin_permissions' => 'Gestire permessi ISO',
        'iso_admin_backup' => 'Gestire backup sistema ISO'
    ];
    
    // Operazioni critiche che richiedono backup
    private const CRITICAL_OPERATIONS = [
        'iso_structure_delete',
        'iso_folder_delete', 
        'iso_document_delete',
        'iso_structure_create'
    ];
    
    // Rate limits per operazioni
    private const RATE_LIMITS = [
        'iso_structure_create' => ['limit' => 5, 'window' => 86400], // 5 al giorno
        'iso_structure_delete' => ['limit' => 2, 'window' => 86400], // 2 al giorno
        'iso_document_upload' => ['limit' => 100, 'window' => 3600], // 100 all'ora
        'iso_document_download' => ['limit' => 200, 'window' => 3600], // 200 all'ora
        'iso_export' => ['limit' => 10, 'window' => 3600], // 10 all'ora
        'iso_audit_access' => ['limit' => 50, 'window' => 3600] // 50 all'ora
    ];

    private function __construct()
    {
        $this->logger = ActivityLogger::getInstance();
        $this->rateLimiter = new RateLimiter();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Verifica permesso per operazione ISO
     * 
     * @param int $userId ID utente
     * @param string $permission Permesso richiesto
     * @param int $companyId ID azienda
     * @param array $context Contesto operazione
     * @return bool
     */
    public function checkPermission($userId, $permission, $companyId, $context = [])
    {
        try {
            // Verifica permesso esistente
            if (!isset(self::ISO_PERMISSIONS[$permission])) {
                $this->logSecurityEvent('invalid_permission_check', [
                    'user_id' => $userId,
                    'permission' => $permission,
                    'company_id' => $companyId
                ]);
                return false;
            }

            // Verifica super admin (sempre autorizzato)
            $auth = Auth::getInstance();
            if ($auth->isSuperAdmin()) {
                return true;
            }

            // Verifica utente speciale per permessi elevati
            if ($auth->isUtenteSpeciale()) {
                $elevatedPermissions = [
                    'iso_structure_create', 'iso_structure_modify', 'iso_structure_delete',
                    'iso_admin_config', 'iso_compliance_audit'
                ];
                
                if (in_array($permission, $elevatedPermissions)) {
                    return true;
                }
            }

            // Verifica permessi specifici utente
            $userPermission = db_query(
                "SELECT ip.* FROM iso_user_permissions iup
                 JOIN iso_permissions ip ON iup.permission_id = ip.id
                 WHERE iup.user_id = ? AND iup.company_id = ? AND ip.code = ? AND iup.active = 1",
                [$userId, $companyId, $permission]
            )->fetch();

            if ($userPermission) {
                // Verifica scadenza permesso
                if ($userPermission['expires_at'] && strtotime($userPermission['expires_at']) < time()) {
                    $this->logSecurityEvent('permission_expired', [
                        'user_id' => $userId,
                        'permission' => $permission,
                        'expired_at' => $userPermission['expires_at']
                    ]);
                    return false;
                }
                return true;
            }

            // Verifica permessi ruolo
            $rolePermission = db_query(
                "SELECT ip.* FROM iso_role_permissions irp
                 JOIN iso_permissions ip ON irp.permission_id = ip.id
                 JOIN utenti u ON u.ruolo = irp.role_name
                 WHERE u.id = ? AND irp.company_id = ? AND ip.code = ? AND irp.active = 1",
                [$userId, $companyId, $permission]
            )->fetch();

            if ($rolePermission) {
                return true;
            }

            // Log accesso negato
            $this->logSecurityEvent('permission_denied', [
                'user_id' => $userId,
                'permission' => $permission,
                'company_id' => $companyId,
                'context' => $context
            ]);

            return false;

        } catch (Exception $e) {
            $this->logger->logError("Errore verifica permessi ISO", [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'permission' => $permission
            ]);
            return false;
        }
    }

    /**
     * Verifica rate limiting per operazione
     * 
     * @param int $userId ID utente
     * @param string $operation Operazione
     * @param array $context Contesto
     * @return bool
     */
    public function checkRateLimit($userId, $operation, $context = [])
    {
        try {
            if (!isset(self::RATE_LIMITS[$operation])) {
                return true; // Nessun limite definito
            }

            $limits = self::RATE_LIMITS[$operation];
            $identifier = "iso_{$operation}_{$userId}";

            $allowed = $this->rateLimiter->check(
                $identifier,
                $operation,
                $limits['limit'],
                $limits['window']
            );

            if (!$allowed) {
                $this->logSecurityEvent('rate_limit_exceeded', [
                    'user_id' => $userId,
                    'operation' => $operation,
                    'limit' => $limits['limit'],
                    'window' => $limits['window'],
                    'context' => $context
                ]);
            }

            return $allowed;

        } catch (Exception $e) {
            $this->logger->logError("Errore verifica rate limit ISO", [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'operation' => $operation
            ]);
            return false;
        }
    }

    /**
     * Autorizza operazione completa (permessi + rate limiting + audit)
     * 
     * @param int $userId ID utente
     * @param string $operation Operazione
     * @param int $companyId ID azienda
     * @param array $context Contesto operazione
     * @return array Risultato autorizzazione
     */
    public function authorizeOperation($userId, $operation, $companyId, $context = [])
    {
        $startTime = microtime(true);
        
        try {
            // Mappa operazione a permesso
            $permission = $this->mapOperationToPermission($operation);
            if (!$permission) {
                return [
                    'authorized' => false,
                    'reason' => 'Operazione non riconosciuta',
                    'code' => 'INVALID_OPERATION'
                ];
            }

            // Verifica permessi
            if (!$this->checkPermission($userId, $permission, $companyId, $context)) {
                return [
                    'authorized' => false,
                    'reason' => 'Permessi insufficienti',
                    'code' => 'INSUFFICIENT_PERMISSIONS'
                ];
            }

            // Verifica rate limiting
            if (!$this->checkRateLimit($userId, $operation, $context)) {
                return [
                    'authorized' => false,
                    'reason' => 'Limite velocità superato',
                    'code' => 'RATE_LIMIT_EXCEEDED'
                ];
            }

            // Backup per operazioni critiche
            $backupId = null;
            if (in_array($permission, self::CRITICAL_OPERATIONS)) {
                $backupId = $this->createOperationBackup($operation, $companyId, $context);
            }

            // Log operazione autorizzata
            $this->logAuditEvent($userId, $operation, $companyId, 'authorized', [
                'permission' => $permission,
                'backup_id' => $backupId,
                'context' => $context,
                'authorization_time' => microtime(true) - $startTime
            ]);

            return [
                'authorized' => true,
                'permission' => $permission,
                'backup_id' => $backupId,
                'authorization_time' => microtime(true) - $startTime
            ];

        } catch (Exception $e) {
            $this->logger->logError("Errore autorizzazione operazione ISO", [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'operation' => $operation,
                'company_id' => $companyId
            ]);

            return [
                'authorized' => false,
                'reason' => 'Errore interno del sistema',
                'code' => 'SYSTEM_ERROR'
            ];
        }
    }

    /**
     * Crea backup pre-operazione per operazioni critiche
     * 
     * @param string $operation Operazione
     * @param int $companyId ID azienda
     * @param array $context Contesto
     * @return int|null ID backup
     */
    private function createOperationBackup($operation, $companyId, $context)
    {
        try {
            $backupData = [];

            switch ($operation) {
                case 'structure_delete':
                    // Backup struttura completa
                    $backupData = $this->backupCompanyStructure($companyId);
                    break;

                case 'folder_delete':
                    if (isset($context['folder_id'])) {
                        $backupData = $this->backupFolder($context['folder_id']);
                    }
                    break;

                case 'document_delete':
                    if (isset($context['document_id'])) {
                        $backupData = $this->backupDocument($context['document_id']);
                    }
                    break;
            }

            if (!empty($backupData)) {
                return db_insert('iso_operation_backups', [
                    'company_id' => $companyId,
                    'operation' => $operation,
                    'backup_data' => json_encode($backupData),
                    'context' => json_encode($context),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }

        } catch (Exception $e) {
            $this->logger->logError("Errore creazione backup operazione", [
                'error' => $e->getMessage(),
                'operation' => $operation,
                'company_id' => $companyId
            ]);
        }

        return null;
    }

    /**
     * Log evento sicurezza
     * 
     * @param string $event Tipo evento
     * @param array $details Dettagli evento
     */
    private function logSecurityEvent($event, $details)
    {
        $this->logger->log('iso_security_event', 'security', null, [
            'event_type' => $event,
            'details' => $details,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        // Log critico per eventi gravi
        $criticalEvents = ['permission_denied', 'rate_limit_exceeded', 'invalid_permission_check'];
        if (in_array($event, $criticalEvents)) {
            error_log("ISO Security Event: {$event} - " . json_encode($details));
        }
    }

    /**
     * Log evento audit
     * 
     * @param int $userId ID utente
     * @param string $operation Operazione
     * @param int $companyId ID azienda
     * @param string $result Risultato
     * @param array $details Dettagli
     */
    private function logAuditEvent($userId, $operation, $companyId, $result, $details)
    {
        try {
            db_insert('iso_audit_trail', [
                'user_id' => $userId,
                'company_id' => $companyId,
                'operation' => $operation,
                'result' => $result,
                'details' => json_encode($details),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("Errore log audit ISO: " . $e->getMessage());
        }
    }

    /**
     * Mappa operazione a permesso
     * 
     * @param string $operation Operazione
     * @return string|null Permesso
     */
    private function mapOperationToPermission($operation)
    {
        $mapping = [
            'structure_create' => 'iso_structure_create',
            'structure_modify' => 'iso_structure_modify',
            'structure_delete' => 'iso_structure_delete',
            'structure_export' => 'iso_structure_export',
            
            'folder_create' => 'iso_folder_create',
            'folder_modify' => 'iso_folder_modify',
            'folder_delete' => 'iso_folder_delete',
            'folder_view' => 'iso_folder_view',
            
            'document_upload' => 'iso_document_upload',
            'document_download' => 'iso_document_download',
            'document_modify' => 'iso_document_modify',
            'document_delete' => 'iso_document_delete',
            'document_approve' => 'iso_document_approve',
            'document_version' => 'iso_document_version',
            
            'compliance_view' => 'iso_compliance_view',
            'compliance_audit' => 'iso_compliance_audit',
            'audit_view' => 'iso_audit_view',
            'audit_export' => 'iso_audit_export'
        ];

        return $mapping[$operation] ?? null;
    }

    /**
     * Backup struttura aziendale completa
     * 
     * @param int $companyId ID azienda
     * @return array Dati backup
     */
    private function backupCompanyStructure($companyId)
    {
        $backup = [
            'company_id' => $companyId,
            'timestamp' => date('Y-m-d H:i:s'),
            'folders' => [],
            'documents' => [],
            'configuration' => null
        ];

        // Backup cartelle
        $folders = db_query(
            "SELECT * FROM cartelle WHERE azienda_id = ? ORDER BY percorso_completo",
            [$companyId]
        )->fetchAll();
        $backup['folders'] = $folders;

        // Backup documenti (metadata, non file fisici)
        $documents = db_query(
            "SELECT * FROM documenti WHERE azienda_id = ? AND stato != 'eliminato'",
            [$companyId]
        )->fetchAll();
        $backup['documents'] = $documents;

        // Backup configurazione ISO
        $config = db_query(
            "SELECT * FROM aziende_iso_config WHERE azienda_id = ?",
            [$companyId]
        )->fetch();
        if ($config) {
            $backup['configuration'] = $config;
        }

        return $backup;
    }

    /**
     * Backup singola cartella
     * 
     * @param int $folderId ID cartella
     * @return array Dati backup
     */
    private function backupFolder($folderId)
    {
        $folder = db_query("SELECT * FROM cartelle WHERE id = ?", [$folderId])->fetch();
        if (!$folder) {
            return [];
        }

        $backup = [
            'folder' => $folder,
            'documents' => [],
            'subfolders' => []
        ];

        // Backup documenti in cartella
        $documents = db_query(
            "SELECT * FROM documenti WHERE cartella_id = ?",
            [$folderId]
        )->fetchAll();
        $backup['documents'] = $documents;

        // Backup sottocartelle
        $subfolders = db_query(
            "SELECT * FROM cartelle WHERE parent_id = ?",
            [$folderId]
        )->fetchAll();
        $backup['subfolders'] = $subfolders;

        return $backup;
    }

    /**
     * Backup singolo documento
     * 
     * @param int $documentId ID documento
     * @return array Dati backup
     */
    private function backupDocument($documentId)
    {
        $document = db_query("SELECT * FROM documenti WHERE id = ?", [$documentId])->fetch();
        if (!$document) {
            return [];
        }

        $backup = [
            'document' => $document,
            'versions' => []
        ];

        // Backup versioni documento
        $versions = db_query(
            "SELECT * FROM documenti_versioni WHERE documento_id = ?",
            [$documentId]
        )->fetchAll();
        $backup['versions'] = $versions;

        return $backup;
    }

    /**
     * Ottieni statistiche sicurezza ISO
     * 
     * @param int $companyId ID azienda
     * @param array $options Opzioni query
     * @return array Statistiche
     */
    public function getSecurityStats($companyId, $options = [])
    {
        $timeRange = $options['time_range'] ?? '7 days';
        $fromDate = date('Y-m-d H:i:s', strtotime("-{$timeRange}"));

        $stats = [
            'audit_events' => 0,
            'security_events' => 0,
            'permission_denials' => 0,
            'rate_limit_hits' => 0,
            'critical_operations' => 0,
            'backups_created' => 0
        ];

        // Eventi audit
        $stats['audit_events'] = db_query(
            "SELECT COUNT(*) FROM iso_audit_trail 
             WHERE company_id = ? AND created_at >= ?",
            [$companyId, $fromDate]
        )->fetchColumn();

        // Eventi sicurezza
        $securityEvents = db_query(
            "SELECT la.dettagli 
             FROM log_attivita la 
             WHERE la.azienda_id = ? 
             AND la.azione = 'iso_security_event' 
             AND la.data_azione >= ?",
            [$companyId, $fromDate]
        )->fetchAll();

        foreach ($securityEvents as $event) {
            $details = json_decode($event['dettagli'], true);
            $eventType = $details['event_type'] ?? '';
            
            $stats['security_events']++;
            
            if ($eventType === 'permission_denied') {
                $stats['permission_denials']++;
            } elseif ($eventType === 'rate_limit_exceeded') {
                $stats['rate_limit_hits']++;
            }
        }

        // Operazioni critiche
        $stats['critical_operations'] = db_query(
            "SELECT COUNT(*) FROM iso_audit_trail 
             WHERE company_id = ? 
             AND operation IN ('" . implode("','", array_keys(self::CRITICAL_OPERATIONS)) . "')
             AND created_at >= ?",
            [$companyId, $fromDate]
        )->fetchColumn();

        // Backup creati
        $stats['backups_created'] = db_query(
            "SELECT COUNT(*) FROM iso_operation_backups 
             WHERE company_id = ? AND created_at >= ?",
            [$companyId, $fromDate]
        )->fetchColumn();

        return $stats;
    }

    /**
     * Ottieni permessi disponibili
     * 
     * @return array Lista permessi
     */
    public function getAvailablePermissions()
    {
        return self::ISO_PERMISSIONS;
    }

    /**
     * Valida input per operazioni ISO
     * 
     * @param string $operation Operazione
     * @param array $input Dati input
     * @return array Risultato validazione
     */
    public function validateInput($operation, $input)
    {
        $errors = [];
        $sanitized = [];

        try {
            switch ($operation) {
                case 'structure_create':
                    $sanitized = $this->validateStructureInput($input, $errors);
                    break;
                    
                case 'folder_create':
                    $sanitized = $this->validateFolderInput($input, $errors);
                    break;
                    
                case 'document_upload':
                    $sanitized = $this->validateDocumentInput($input, $errors);
                    break;
                    
                default:
                    $sanitized = $this->sanitizeGenericInput($input);
            }

            return [
                'valid' => empty($errors),
                'errors' => $errors,
                'sanitized' => $sanitized
            ];

        } catch (Exception $e) {
            return [
                'valid' => false,
                'errors' => ['Errore validazione: ' . $e->getMessage()],
                'sanitized' => []
            ];
        }
    }

    /**
     * Valida input creazione struttura
     */
    private function validateStructureInput($input, &$errors)
    {
        $sanitized = [];

        // Tipo struttura
        if (empty($input['structure_type'])) {
            $errors[] = 'Tipo struttura richiesto';
        } elseif (!in_array($input['structure_type'], ['separata', 'integrata', 'personalizzata'])) {
            $errors[] = 'Tipo struttura non valido';
        } else {
            $sanitized['structure_type'] = $input['structure_type'];
        }

        // Standard attivi
        if (empty($input['active_standards']) || !is_array($input['active_standards'])) {
            $errors[] = 'Standard ISO richiesti';
        } else {
            $validStandards = ['ISO9001', 'ISO14001', 'ISO45001', 'GDPR'];
            $sanitized['active_standards'] = array_intersect($input['active_standards'], $validStandards);
            
            if (empty($sanitized['active_standards'])) {
                $errors[] = 'Nessuno standard valido specificato';
            }
        }

        // Configurazione avanzata
        if (isset($input['advanced_config']) && is_array($input['advanced_config'])) {
            $sanitized['advanced_config'] = $this->sanitizeAdvancedConfig($input['advanced_config']);
        }

        return $sanitized;
    }

    /**
     * Valida input creazione cartella
     */
    private function validateFolderInput($input, &$errors)
    {
        $sanitized = [];

        // Nome cartella
        if (empty($input['nome'])) {
            $errors[] = 'Nome cartella richiesto';
        } else {
            $name = trim($input['nome']);
            if (strlen($name) < 2 || strlen($name) > 100) {
                $errors[] = 'Nome cartella deve essere tra 2 e 100 caratteri';
            } elseif (preg_match('/[<>:"|?*\/\\\\]/', $name)) {
                $errors[] = 'Nome cartella contiene caratteri non validi';
            } else {
                $sanitized['nome'] = $name;
            }
        }

        // Parent ID
        if (isset($input['parent_id'])) {
            $parentId = filter_var($input['parent_id'], FILTER_VALIDATE_INT);
            if ($parentId !== false && $parentId > 0) {
                $sanitized['parent_id'] = $parentId;
            }
        }

        // Azienda ID
        if (empty($input['azienda_id'])) {
            $errors[] = 'ID azienda richiesto';
        } else {
            $aziendaId = filter_var($input['azienda_id'], FILTER_VALIDATE_INT);
            if ($aziendaId === false || $aziendaId <= 0) {
                $errors[] = 'ID azienda non valido';
            } else {
                $sanitized['azienda_id'] = $aziendaId;
            }
        }

        return $sanitized;
    }

    /**
     * Valida input caricamento documento
     */
    private function validateDocumentInput($input, &$errors)
    {
        $sanitized = [];

        // Cartella ID
        if (empty($input['folder_id'])) {
            $errors[] = 'ID cartella richiesto';
        } else {
            $folderId = filter_var($input['folder_id'], FILTER_VALIDATE_INT);
            if ($folderId === false || $folderId <= 0) {
                $errors[] = 'ID cartella non valido';
            } else {
                $sanitized['folder_id'] = $folderId;
            }
        }

        // Metadata
        if (isset($input['metadata']) && is_array($input['metadata'])) {
            $sanitized['metadata'] = $this->sanitizeMetadata($input['metadata']);
        }

        return $sanitized;
    }

    /**
     * Sanifica configurazione avanzata
     */
    private function sanitizeAdvancedConfig($config)
    {
        $sanitized = [];

        if (isset($config['root_folder_name'])) {
            $sanitized['root_folder_name'] = trim(strip_tags($config['root_folder_name']));
        }

        if (isset($config['excluded_folders']) && is_array($config['excluded_folders'])) {
            $sanitized['excluded_folders'] = array_map('trim', $config['excluded_folders']);
        }

        if (isset($config['folder_mappings']) && is_array($config['folder_mappings'])) {
            $sanitized['folder_mappings'] = [];
            foreach ($config['folder_mappings'] as $key => $value) {
                $sanitized['folder_mappings'][trim($key)] = trim(strip_tags($value));
            }
        }

        return $sanitized;
    }

    /**
     * Sanifica metadata generici
     */
    private function sanitizeMetadata($metadata)
    {
        $sanitized = [];

        foreach ($metadata as $key => $value) {
            $key = trim(strip_tags($key));
            
            if (is_string($value)) {
                $sanitized[$key] = trim(strip_tags($value));
            } elseif (is_numeric($value)) {
                $sanitized[$key] = $value;
            } elseif (is_bool($value)) {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Sanifica input generici
     */
    private function sanitizeGenericInput($input)
    {
        if (is_array($input)) {
            $sanitized = [];
            foreach ($input as $key => $value) {
                $key = trim(strip_tags($key));
                
                if (is_array($value)) {
                    $sanitized[$key] = $this->sanitizeGenericInput($value);
                } elseif (is_string($value)) {
                    $sanitized[$key] = trim($value);
                } else {
                    $sanitized[$key] = $value;
                }
            }
            return $sanitized;
        }

        return is_string($input) ? trim($input) : $input;
    }
}