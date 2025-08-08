<?php
/**
 * API Endpoint: Creazione Backup Documentale
 * POST /api/v1/backup/create
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../../middleware/Auth.php';
require_once '../../../models/AdvancedDocument.php';
require_once '../../../utils/ActivityLogger.php';

try {
    // Verifica autenticazione
    $auth = Auth::getInstance();
    $auth->requireAuth();

    // Verifica metodo HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Metodo non consentito', 405);
    }

    // Verifica permessi
    if (!$auth->hasElevatedPrivileges()) {
        throw new Exception('Permessi insufficienti per creare backup', 403);
    }

    // Lettura dati request
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON non valido: ' . json_last_error_msg(), 400);
    }

    // Validazione parametri richiesti
    $aziendaId = (int) ($input['azienda_id'] ?? $auth->getCurrentCompany());
    if ($aziendaId <= 0) {
        throw new Exception('ID azienda non valido', 400);
    }

    // Verifica accesso all'azienda
    if (!$auth->isSuperAdmin() && $auth->getCurrentCompany() !== $aziendaId) {
        throw new Exception('Accesso negato all\'azienda specificata', 403);
    }

    // Opzioni di backup
    $options = [
        'type' => $input['type'] ?? 'full', // full, incremental, selective
        'include_files' => $input['include_files'] ?? true,
        'include_versions' => $input['include_versions'] ?? true,
        'include_metadata' => $input['include_metadata'] ?? true,
        'compression' => $input['compression'] ?? true,
        'encryption' => $input['encryption'] ?? false,
        'description' => $input['description'] ?? '',
        'scheduled' => $input['scheduled'] ?? false
    ];

    // Validazione tipo backup
    $validTypes = ['full', 'incremental', 'selective'];
    if (!in_array($options['type'], $validTypes)) {
        throw new Exception('Tipo backup non valido. Valori consentiti: ' . implode(', ', $validTypes), 400);
    }

    // Filtri per backup selettivo
    $filters = [];
    if ($options['type'] === 'selective') {
        $filters = [
            'cartelle_ids' => $input['filters']['cartelle_ids'] ?? [],
            'tipo_documento' => $input['filters']['tipo_documento'] ?? [],
            'norma_iso' => $input['filters']['norma_iso'] ?? [],
            'data_da' => $input['filters']['data_da'] ?? null,
            'data_a' => $input['filters']['data_a'] ?? null,
            'stati' => $input['filters']['stati'] ?? ['pubblicato'],
            'include_gdpr' => $input['filters']['include_gdpr'] ?? false
        ];
        
        // Validazione filtri
        if (empty($filters['cartelle_ids']) && empty($filters['tipo_documento']) && 
            empty($filters['norma_iso']) && !$filters['data_da']) {
            throw new Exception('Per backup selettivo specificare almeno un filtro', 400);
        }
    }

    // Validazione date per backup incrementale
    if ($options['type'] === 'incremental') {
        $lastBackup = $this->getLastBackupDate($aziendaId);
        if (!$lastBackup) {
            throw new Exception('Backup incrementale richiede un backup completo precedente', 400);
        }
        $filters['data_da'] = $lastBackup;
    }

    // Verifica spazio disco
    $estimatedSize = $this->estimateBackupSize($aziendaId, $options, $filters);
    $availableSpace = disk_free_space(BASE_PATH . '/backups');
    
    if ($estimatedSize > $availableSpace * 0.8) { // Lascia 20% di margine
        throw new Exception('Spazio disco insufficiente per il backup', 507);
    }

    // Inizializzazione documento manager
    $documentManager = AdvancedDocument::getInstance();
    
    // Creazione backup
    $startTime = microtime(true);
    $backupResult = $documentManager->createBackup($aziendaId, array_merge($options, ['filters' => $filters]));
    $executionTime = microtime(true) - $startTime;

    // Calcolo hash per integrità
    $backupHash = hash_file('sha256', $backupResult['file_path']);

    // Salvataggio metadati backup
    $backupRecord = [
        'backup_id' => $backupResult['backup_id'],
        'azienda_id' => $aziendaId,
        'type' => $options['type'],
        'file_path' => $backupResult['file_path'],
        'file_size' => $backupResult['size'],
        'file_hash' => $backupHash,
        'documents_count' => $backupResult['documents_count'],
        'options' => json_encode($options),
        'filters' => json_encode($filters),
        'description' => $options['description'],
        'created_by' => $auth->getUser()['id'],
        'execution_time' => $executionTime,
        'status' => 'completed',
        'created_at' => date('Y-m-d H:i:s')
    ];

    $backupDbId = db_insert('backups_log', $backupRecord);

    // Verifica integrità
    $integrityCheck = $this->verifyBackupIntegrity($backupResult['file_path'], $backupHash);
    
    if (!$integrityCheck['valid']) {
        // Backup corrotto, elimina file
        if (file_exists($backupResult['file_path'])) {
            unlink($backupResult['file_path']);
        }
        throw new Exception('Backup corrotto durante la verifica di integrità', 500);
    }

    // Programmazione pulizia automatica vecchi backup
    if ($input['auto_cleanup'] ?? true) {
        $this->scheduleBackupCleanup($aziendaId);
    }

    // Notifica completamento (se richiesta)
    if ($input['notify_completion'] ?? false) {
        $this->sendBackupNotification($auth->getUser()['id'], $backupResult, $options);
    }

    // Preparazione risposta
    $response = [
        'success' => true,
        'message' => 'Backup creato con successo',
        'data' => [
            'backup_id' => $backupResult['backup_id'],
            'database_id' => $backupDbId,
            'type' => $options['type'],
            'file_size' => $this->formatFileSize($backupResult['size']),
            'file_size_bytes' => $backupResult['size'],
            'documents_count' => $backupResult['documents_count'],
            'execution_time' => round($executionTime, 2),
            'integrity_verified' => true,
            'download_url' => '/api/v1/backup/download/' . $backupResult['backup_id'],
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')) // Link scade in 30 giorni
        ],
        'backup_details' => [
            'options' => $options,
            'filters' => $filters,
            'estimated_size' => $this->formatFileSize($estimatedSize),
            'compression_ratio' => $this->calculateCompressionRatio($estimatedSize, $backupResult['size']),
            'includes' => [
                'documents' => true,
                'folders' => true,
                'classifications' => true,
                'templates' => $options['include_metadata'],
                'files' => $options['include_files'],
                'versions' => $options['include_versions']
            ]
        ],
        'statistics' => [
            'total_backups' => $this->getBackupCount($aziendaId),
            'total_size' => $this->formatFileSize($this->getTotalBackupSize($aziendaId)),
            'last_backup_date' => $this->getLastBackupDate($aziendaId, false)
        ]
    ];

    // Log dell'operazione
    ActivityLogger::getInstance()->log('backup_created_api', 'backups', $backupDbId, [
        'backup_id' => $backupResult['backup_id'],
        'azienda_id' => $aziendaId,
        'type' => $options['type'],
        'size' => $backupResult['size'],
        'documents_count' => $backupResult['documents_count'],
        'execution_time' => $executionTime,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
    ]);

    http_response_code(201);
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Log errore
    if (isset($auth)) {
        ActivityLogger::getInstance()->logError('Errore API backup/create: ' . $e->getMessage(), [
            'input' => $input ?? null,
            'user_id' => $auth->getUser()['id'],
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
    }

    // Risposta errore
    $statusCode = $e->getCode() ?: 500;
    if ($statusCode < 400 || $statusCode >= 600) {
        $statusCode = 500;
    }

    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => $statusCode,
            'message' => $e->getMessage(),
            'type' => get_class($e)
        ],
        'timestamp' => date('c')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

// Funzioni helper

function getLastBackupDate($aziendaId, $formatDate = true) {
    $stmt = db_query(
        "SELECT created_at FROM backups_log 
         WHERE azienda_id = ? AND status = 'completed' 
         ORDER BY created_at DESC LIMIT 1",
        [$aziendaId]
    );
    
    $date = $stmt->fetchColumn();
    if (!$date) return null;
    
    return $formatDate ? date('Y-m-d', strtotime($date)) : $date;
}

function estimateBackupSize($aziendaId, $options, $filters) {
    // Stima dimensione backup basata sui filtri
    $whereConditions = ['azienda_id = ?'];
    $params = [$aziendaId];
    
    // Applica filtri
    if (!empty($filters['cartelle_ids'])) {
        $placeholders = str_repeat('?,', count($filters['cartelle_ids']) - 1) . '?';
        $whereConditions[] = "cartella_id IN ($placeholders)";
        $params = array_merge($params, $filters['cartelle_ids']);
    }
    
    if (!empty($filters['tipo_documento'])) {
        $placeholders = str_repeat('?,', count($filters['tipo_documento']) - 1) . '?';
        $whereConditions[] = "tipo_documento IN ($placeholders)";
        $params = array_merge($params, $filters['tipo_documento']);
    }
    
    if (!empty($filters['data_da'])) {
        $whereConditions[] = "data_creazione >= ?";
        $params[] = $filters['data_da'];
    }
    
    if (!empty($filters['data_a'])) {
        $whereConditions[] = "data_creazione <= ?";
        $params[] = $filters['data_a'];
    }
    
    $sql = "SELECT COALESCE(SUM(file_size), 0) FROM documenti_avanzati WHERE " . implode(' AND ', $whereConditions);
    $stmt = db_query($sql, $params);
    
    $totalSize = $stmt->fetchColumn();
    
    // Aggiungi overhead per metadati e compressione
    $overhead = $totalSize * 0.1; // 10% overhead
    $estimatedSize = $totalSize + $overhead;
    
    // Applica fattore di compressione se abilitata
    if ($options['compression']) {
        $estimatedSize *= 0.7; // Stima compressione 30%
    }
    
    return $estimatedSize;
}

function verifyBackupIntegrity($filePath, $expectedHash) {
    if (!file_exists($filePath)) {
        return ['valid' => false, 'error' => 'File not found'];
    }
    
    $actualHash = hash_file('sha256', $filePath);
    
    if ($actualHash !== $expectedHash) {
        return ['valid' => false, 'error' => 'Hash mismatch'];
    }
    
    // Verifica che sia un ZIP valido
    $zip = new ZipArchive();
    $result = $zip->open($filePath, ZipArchive::CHECKCONS);
    
    if ($result !== TRUE) {
        return ['valid' => false, 'error' => 'Invalid ZIP file'];
    }
    
    $zip->close();
    
    return ['valid' => true];
}

function scheduleBackupCleanup($aziendaId) {
    // Mantieni solo gli ultimi 10 backup
    $oldBackups = db_query(
        "SELECT id, file_path FROM backups_log 
         WHERE azienda_id = ? AND status = 'completed' 
         ORDER BY created_at DESC 
         LIMIT 999 OFFSET 10",
        [$aziendaId]
    )->fetchAll();
    
    foreach ($oldBackups as $backup) {
        // Elimina file
        if (file_exists($backup['file_path'])) {
            unlink($backup['file_path']);
        }
        
        // Aggiorna stato nel database
        db_update('backups_log', 
            ['status' => 'deleted', 'deleted_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [$backup['id']]
        );
    }
}

function sendBackupNotification($userId, $backupResult, $options) {
    // Implementazione notifica via email
    $user = db_query("SELECT email, nome, cognome FROM utenti WHERE id = ?", [$userId])->fetch();
    
    if ($user && $user['email']) {
        $subject = 'Backup documentale completato';
        $message = "Il backup di tipo '{$options['type']}' è stato completato con successo.\n\n";
        $message .= "Dettagli:\n";
        $message .= "- Documenti: {$backupResult['documents_count']}\n";
        $message .= "- Dimensione: " . formatFileSize($backupResult['size']) . "\n";
        $message .= "- ID Backup: {$backupResult['backup_id']}\n";
        
        // Invio email (implementazione dipende dal sistema email configurato)
        // mail($user['email'], $subject, $message);
    }
}

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, 2) . ' ' . $units[$i];
}

function calculateCompressionRatio($originalSize, $compressedSize) {
    if ($originalSize == 0) return 0;
    return round((1 - ($compressedSize / $originalSize)) * 100, 1);
}

function getBackupCount($aziendaId) {
    $stmt = db_query(
        "SELECT COUNT(*) FROM backups_log WHERE azienda_id = ? AND status = 'completed'",
        [$aziendaId]
    );
    return (int) $stmt->fetchColumn();
}

function getTotalBackupSize($aziendaId) {
    $stmt = db_query(
        "SELECT COALESCE(SUM(file_size), 0) FROM backups_log 
         WHERE azienda_id = ? AND status = 'completed'",
        [$aziendaId]
    );
    return (int) $stmt->fetchColumn();
}
?>