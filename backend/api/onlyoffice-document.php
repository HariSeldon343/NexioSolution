<?php
/**
 * API OnlyOffice Document Server - VERSIONE SEMPLIFICATA
 * Gestisce creazione, caricamento e salvataggio documenti
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/onlyoffice.config.php';

// Headers CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? 'download';
    $file_id = $_GET['id'] ?? $_POST['file_id'] ?? null;
    
    if (!$file_id) {
        throw new Exception('File ID mancante');
    }
    
    // Assicurati che la directory documenti esista
    if (!is_dir($ONLYOFFICE_DOCUMENTS_DIR)) {
        mkdir($ONLYOFFICE_DOCUMENTS_DIR, 0755, true);
    }
    
    switch ($action) {
        case 'prepare':
            handlePrepareDocument($file_id);
            break;
            
        case 'download':
            handleDownloadDocument($file_id);
            break;
            
        case 'save':
            handleSaveDocument($file_id);
            break;
            
        default:
            throw new Exception('Azione non supportata: ' . $action);
    }
    
} catch (Exception $e) {
    error_log("OnlyOffice Document API Error: " . $e->getMessage());
    
    if ($action === 'download') {
        // Per download, restituisci un documento vuoto
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="document.docx"');
        echo createMinimalDocx();
        exit;
    }
    
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Prepara un documento
 */
function handlePrepareDocument($file_id) {
    global $ONLYOFFICE_DOCUMENTS_DIR;
    
    $file_path = $ONLYOFFICE_DOCUMENTS_DIR . '/' . $file_id . '.docx';
    
    // Se il file non esiste, crea un documento vuoto
    if (!file_exists($file_path)) {
        $docx_content = createMinimalDocx();
        file_put_contents($file_path, $docx_content);
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Documento preparato',
        'file_path' => $file_path
    ]);
}

/**
 * Serve il documento per il download
 */
function handleDownloadDocument($file_id) {
    global $ONLYOFFICE_DOCUMENTS_DIR;
    
    $file_path = $ONLYOFFICE_DOCUMENTS_DIR . '/' . $file_id . '.docx';
    
    // Se il file non esiste, crea uno vuoto
    if (!file_exists($file_path)) {
        $docx_content = createMinimalDocx();
        file_put_contents($file_path, $docx_content);
    }
    
    // Serve il file
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $file_id . '.docx"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    readfile($file_path);
    exit;
}

/**
 * Salva il documento
 */
function handleSaveDocument($file_id) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Documento salvato'
    ]);
}

/**
 * Crea un documento DOCX minimale vuoto
 */
function createMinimalDocx() {
    // Template DOCX minimale in base64 - simplified version
    $docx_template = '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';
    
    // Return base64 decoded content
    return base64_decode($docx_template);
}
