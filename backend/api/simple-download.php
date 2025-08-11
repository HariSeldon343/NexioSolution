<?php
/**
 * API per download file semplice
 * 
 * @package Nexio
 * @version 1.0.0
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../middleware/Auth.php';
require_once '../utils/ActivityLogger.php';

// Autenticazione
$auth = Auth::getInstance();
if (!$auth->checkSession()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autenticato']);
    exit;
}

$user = $auth->getUser();
$userId = $user['id'];
$isSuperAdmin = $auth->isSuperAdmin();

// Parametri
$documentId = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$documentId) {
    http_response_code(400);
    echo json_encode(['error' => 'ID documento mancante']);
    exit;
}

try {
    // Recupera documento
    $stmt = db_query("
        SELECT d.*, c.azienda_id as folder_azienda_id
        FROM documenti d
        LEFT JOIN cartelle c ON d.cartella_id = c.id
        WHERE d.id = ?
    ", [$documentId]);
    
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        http_response_code(404);
        echo json_encode(['error' => 'Documento non trovato']);
        exit;
    }
    
    // Verifica accesso
    $currentCompany = $auth->getCurrentCompany();
    $userCompanyId = $currentCompany ? $currentCompany['id'] : null;
    
    // Super admin può accedere a tutto
    // Altri utenti solo ai documenti della loro azienda
    if (!$isSuperAdmin && $document['azienda_id'] != $userCompanyId) {
        http_response_code(403);
        echo json_encode(['error' => 'Accesso negato']);
        exit;
    }
    
    // Percorso file
    $filePath = '../../' . $document['file_path'];
    
    if (!file_exists($filePath)) {
        http_response_code(404);
        echo json_encode(['error' => 'File non trovato sul server']);
        exit;
    }
    
    // Log download
    ActivityLogger::getInstance()->log('documento_scaricato', 'documenti', $documentId, [
        'titolo' => $document['titolo'],
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    // Prepara headers per download
    // Usa il nome del file originale se presente nel path, altrimenti usa il titolo
    $originalFileName = basename($document['file_path']);
    // Se il nome del file inizia con un hash (es: 6896de9806735_), rimuovilo
    if (preg_match('/^[a-f0-9]+_(.+)$/', $originalFileName, $matches)) {
        $fileName = $matches[1];
    } else {
        // Fallback: usa il nome del file così com'è o costruiscilo dal titolo
        if (pathinfo($originalFileName, PATHINFO_EXTENSION)) {
            $fileName = $originalFileName;
        } else {
            $fileName = $document['titolo'];
            $ext = pathinfo($document['file_path'], PATHINFO_EXTENSION);
            if ($ext) {
                $fileName .= '.' . $ext;
            }
        }
    }
    
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
    
    // Invia file
    readfile($filePath);
    exit;
    
} catch (Exception $e) {
    error_log("Errore download: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Errore durante il download']);
}