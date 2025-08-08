<?php
/**
 * API per download file documenti
 * Supporta download singoli con controllo permessi
 * 
 * @package Nexio
 * @version 1.0.0
 */

require_once '../config/config.php';
require_once '../middleware/Auth.php';
require_once '../utils/PermissionManager.php';

// Autenticazione
$auth = Auth::getInstance();
if (!$auth->checkSession()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non autenticato']);
    exit;
}

$user = $auth->getUser();
$userId = $user['id'];
$isSuperAdmin = $auth->isSuperAdmin();
$companyId = $_SESSION['azienda_id'] ?? null;

// Parametri
$type = $_GET['type'] ?? 'document'; // document o folder
$id = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID mancante']);
    exit;
}

try {
    if ($type === 'document') {
        downloadDocument($id, $userId, $isSuperAdmin, $companyId);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Tipo non supportato']);
    }
} catch (Exception $e) {
    error_log("Errore download: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore durante il download']);
}

/**
 * Download documento
 */
function downloadDocument($documentId, $userId, $isSuperAdmin, $userCompanyId) {
    // Recupera info documento
    $stmt = db_query("
        SELECT d.*, c.azienda_id as folder_company_id
        FROM documenti d
        LEFT JOIN cartelle c ON d.cartella_id = c.id
        WHERE d.id = ?
    ", [$documentId]);
    
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Documento non trovato']);
        exit;
    }
    
    // Verifica permessi con PermissionManager
    $permissionManager = PermissionManager::getInstance();
    
    if (!$permissionManager->checkDocumentAccess($documentId, 'download', $userId, $userCompanyId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Non hai i permessi per scaricare questo documento']);
        exit;
    }
    
    // Percorso file
    $filePath = UPLOAD_PATH . '/' . $document['file_path'];
    
    if (!file_exists($filePath)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'File non trovato']);
        exit;
    }
    
    // Log download
    if (class_exists('ActivityLogger')) {
        ActivityLogger::getInstance()->log('documento_scaricato', 'documenti', $documentId, [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    }
    
    // Prepara headers per download
    $fileName = $document['titolo'] . '.' . pathinfo($document['file_path'], PATHINFO_EXTENSION);
    $fileSize = filesize($filePath);
    $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
    
    // Pulisci output buffer
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Headers per download
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    header('Pragma: public');
    
    // Supporto per download parziali (resume)
    if (isset($_SERVER['HTTP_RANGE'])) {
        $range = $_SERVER['HTTP_RANGE'];
        list($start, $end) = parseRange($range, $fileSize);
        
        header('HTTP/1.1 206 Partial Content');
        header("Content-Range: bytes $start-$end/$fileSize");
        header('Content-Length: ' . ($end - $start + 1));
        
        $file = fopen($filePath, 'rb');
        fseek($file, $start);
        
        $buffer = 1024 * 8;
        $position = $start;
        
        while ($position <= $end && !feof($file)) {
            $readSize = min($buffer, $end - $position + 1);
            echo fread($file, $readSize);
            $position += $readSize;
            flush();
        }
        
        fclose($file);
    } else {
        // Download completo
        readfile($filePath);
    }
    
    exit;
}

/**
 * Verifica accesso condiviso
 */
function hasSharedAccess($userId, $documentId) {
    // TODO: Implementare logica per cartelle condivise
    return false;
}

/**
 * Parse HTTP Range header
 */
function parseRange($range, $fileSize) {
    $start = 0;
    $end = $fileSize - 1;
    
    if (preg_match('/bytes=(\d+)-(\d*)/i', $range, $matches)) {
        $start = intval($matches[1]);
        if (!empty($matches[2])) {
            $end = intval($matches[2]);
        }
    }
    
    return [$start, $end];
}