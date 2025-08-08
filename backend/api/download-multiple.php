<?php
/**
 * Download Multiple API
 * 
 * API per il download multiplo di documenti con creazione ZIP ottimizzata
 * 
 * @package Nexio\API
 * @version 1.0.0
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../middleware/Auth.php';
require_once __DIR__ . '/../utils/MultiFileManager.php';
require_once __DIR__ . '/../utils/ActivityLogger.php';
require_once __DIR__ . '/../utils/ModulesHelper.php';

header('Content-Type: application/json');

use Nexio\Utils\MultiFileManager;
use Nexio\Utils\ActivityLogger;
// ModulesHelper already included above

// Autenticazione
$auth = Auth::getInstance();
if (!$auth->checkSession()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non autenticato']);
    exit;
}

// Verifica modulo gestione documentale
ModulesHelper::requireModule('gestione_documentale');

$user = $auth->getUser();
$currentAzienda = $auth->getCurrentAzienda();
$aziendaId = $currentAzienda['azienda_id'] ?? null;

// Per i super_admin, permettere download senza azienda specifica 
if (!$aziendaId && !$auth->hasRole('super_admin')) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Azienda non selezionata']);
    exit;
}

// Per super_admin senza azienda, usa un valore speciale
if (!$aziendaId && $auth->hasRole('super_admin')) {
    $aziendaId = 0; // Valore speciale per indicare documenti globali del super_admin
}

$multiFileManager = MultiFileManager::getInstance();
$logger = ActivityLogger::getInstance();

try {
    // Controllo metodo
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Metodo non supportato');
    }
    
    // Verifica CSRF token
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        throw new Exception('Token CSRF non valido');
    }
    
    // Parsing input
    $documentIds = [];
    $folderIds = [];
    
    // IDs documenti
    if (!empty($_POST['document_ids'])) {
        if (is_string($_POST['document_ids'])) {
            $documentIds = array_map('intval', explode(',', $_POST['document_ids']));
        } elseif (is_array($_POST['document_ids'])) {
            $documentIds = array_map('intval', $_POST['document_ids']);
        }
    }
    
    // IDs cartelle (espandi a tutti i documenti contenuti)
    if (!empty($_POST['folder_ids'])) {
        if (is_string($_POST['folder_ids'])) {
            $folderIds = array_map('intval', explode(',', $_POST['folder_ids']));
        } elseif (is_array($_POST['folder_ids'])) {
            $folderIds = array_map('intval', $_POST['folder_ids']);
        }
        
        // Espandi cartelle in documenti
        foreach ($folderIds as $folderId) {
            $folderDocuments = getFolderDocuments($folderId, $aziendaId, $_POST['include_subfolders'] ?? false);
            $documentIds = array_merge($documentIds, $folderDocuments);
        }
    }
    
    // Rimuovi duplicati
    $documentIds = array_unique($documentIds);
    
    if (empty($documentIds)) {
        throw new Exception('Nessun documento selezionato per il download');
    }
    
    // Opzioni download
    $options = [
        'preserve_structure' => ($_POST['preserve_structure'] ?? 'true') === 'true',
        'include_metadata' => ($_POST['include_metadata'] ?? 'false') === 'true',
        'add_timestamp' => ($_POST['add_timestamp'] ?? 'false') === 'true',
        'compression_level' => intval($_POST['compression_level'] ?? 6), // 0-9
        'max_file_size' => intval($_POST['max_file_size'] ?? 0), // 0 = no limit
        'exclude_types' => explode(',', $_POST['exclude_types'] ?? ''),
        'filename_prefix' => $_POST['filename_prefix'] ?? 'nexio_export',
        'include_audit_trail' => ($_POST['include_audit_trail'] ?? 'false') === 'true'
    ];
    
    // Log avvio download multiplo
    $logger->log('download_multiplo_avviato', 'download_sessions', null, [
        'azienda_id' => $aziendaId,
        'user_id' => $user['id'],
        'document_count' => count($documentIds),
        'folder_count' => count($folderIds),
        'options' => $options
    ]);
    
    // Filtra documenti per dimensione se richiesto
    if ($options['max_file_size'] > 0) {
        $documentIds = filterDocumentsBySize($documentIds, $aziendaId, $options['max_file_size']);
    }
    
    // Filtra per tipo se richiesto
    if (!empty($options['exclude_types'][0])) {
        $documentIds = filterDocumentsByType($documentIds, $aziendaId, $options['exclude_types']);
    }
    
    if (empty($documentIds)) {
        throw new Exception('Nessun documento valido dopo i filtri applicati');
    }
    
    // Genera ZIP
    $result = $multiFileManager->generateDownloadZip($documentIds, $aziendaId, $options);
    
    if (!$result['success']) {
        throw new Exception('Errore nella creazione del file ZIP');
    }
    
    // Prepara risposta
    $response = [
        'success' => true,
        'message' => 'ZIP creato con successo',
        'data' => [
            'zip_id' => $result['zip_id'],
            'session_id' => $result['session_id'],
            'download_token' => $result['download_token'],
            'size' => $result['size'],
            'files_count' => $result['files_count'],
            'download_url' => "/backend/api/download-zip.php?token=" . $result['download_token'],
            'progress_url' => "/backend/api/download-progress.php?session_id=" . $result['session_id'],
            'expires_at' => date('Y-m-d H:i:s', time() + 3600) // 1 ora
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Download Multiple Error: " . $e->getMessage());
    
    $logger->logError('Errore download multiplo', [
        'error' => $e->getMessage(),
        'azienda_id' => $aziendaId,
        'user_id' => $user['id'],
        'document_ids' => $documentIds ?? [],
        'folder_ids' => $folderIds ?? []
    ]);
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'error_code' => 'DOWNLOAD_FAILED'
    ]);
}

/**
 * Ottieni tutti i documenti di una cartella
 */
function getFolderDocuments(int $folderId, int $aziendaId, bool $includeSubfolders = false): array
{
    $sql = "SELECT id FROM documenti WHERE cartella_id = ? AND azienda_id = ? AND stato != 'eliminato'";
    $params = [$folderId, $aziendaId];
    
    if ($includeSubfolders) {
        // Ottieni tutte le sottocartelle
        $subfolders = getSubfolders($folderId, $aziendaId);
        if (!empty($subfolders)) {
            $placeholders = str_repeat('?,', count($subfolders) - 1) . '?';
            $sql = "SELECT id FROM documenti 
                    WHERE (cartella_id = ? OR cartella_id IN ($placeholders)) 
                    AND azienda_id = ? AND stato != 'eliminato'";
            $params = array_merge([$folderId], $subfolders, [$aziendaId]);
        }
    }
    
    $stmt = db_query($sql, $params);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Ottieni tutte le sottocartelle ricorsivamente
 */
function getSubfolders(int $parentId, int $aziendaId): array
{
    $subfolders = [];
    
    $stmt = db_query(
        "SELECT id FROM cartelle WHERE parent_id = ? AND azienda_id = ?",
        [$parentId, $aziendaId]
    );
    $immediateSubfolders = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($immediateSubfolders as $subfolderId) {
        $subfolders[] = $subfolderId;
        $subfolders = array_merge($subfolders, getSubfolders($subfolderId, $aziendaId));
    }
    
    return $subfolders;
}

/**
 * Filtra documenti per dimensione massima
 */
function filterDocumentsBySize(array $documentIds, int $aziendaId, int $maxSize): array
{
    if (empty($documentIds)) return [];
    
    $placeholders = str_repeat('?,', count($documentIds) - 1) . '?';
    $params = array_merge($documentIds, [$aziendaId, $maxSize]);
    
    $stmt = db_query(
        "SELECT id FROM documenti 
         WHERE id IN ($placeholders) AND azienda_id = ? AND dimensione_file <= ?",
        $params
    );
    
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Filtra documenti escludendo tipi specificati
 */
function filterDocumentsByType(array $documentIds, int $aziendaId, array $excludeTypes): array
{
    if (empty($documentIds) || empty($excludeTypes)) return $documentIds;
    
    $placeholders = str_repeat('?,', count($documentIds) - 1) . '?';
    $typePlaceholders = str_repeat('?,', count($excludeTypes) - 1) . '?';
    $params = array_merge($documentIds, [$aziendaId], $excludeTypes);
    
    $stmt = db_query(
        "SELECT id FROM documenti 
         WHERE id IN ($placeholders) AND azienda_id = ? AND tipo_file NOT IN ($typePlaceholders)",
        $params
    );
    
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>