<?php
/**
 * API per download cartelle come ZIP
 * Crea archivi ZIP al volo con controllo permessi
 * 
 * @package Nexio
 * @version 1.0.0
 */

require_once '../config/config.php';
require_once '../middleware/Auth.php';

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
$folderId = isset($_GET['folder_id']) ? intval($_GET['folder_id']) : null;

if (!$folderId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID cartella mancante']);
    exit;
}

try {
    // Verifica cartella e permessi
    $stmt = db_query("SELECT * FROM cartelle WHERE id = ?", [$folderId]);
    $folder = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$folder) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Cartella non trovata']);
        exit;
    }
    
    // Verifica permessi
    if (!$isSuperAdmin && $folder['azienda_id'] != $companyId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Accesso negato']);
        exit;
    }
    
    // Crea ZIP temporaneo
    $zipPath = sys_get_temp_dir() . '/nexio_folder_' . uniqid() . '.zip';
    $zip = new ZipArchive();
    
    if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
        throw new Exception('Impossibile creare archivio ZIP');
    }
    
    // Aggiungi file ricorsivamente
    $filesAdded = addFolderToZip($zip, $folderId, $folder['nome'], $folder['azienda_id']);
    
    $zip->close();
    
    if ($filesAdded === 0) {
        unlink($zipPath);
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Cartella vuota']);
        exit;
    }
    
    // Log download
    if (class_exists('ActivityLogger')) {
        ActivityLogger::getInstance()->log('cartella_scaricata', 'cartelle', $folderId, [
            'files_count' => $filesAdded,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }
    
    // Prepara download
    $fileName = sanitizeFileName($folder['nome']) . '_' . date('Y-m-d') . '.zip';
    $fileSize = filesize($zipPath);
    
    // Pulisci output buffer
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Headers per download
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    header('Pragma: public');
    
    // Invia file
    readfile($zipPath);
    
    // Elimina file temporaneo
    unlink($zipPath);
    
    exit;
    
} catch (Exception $e) {
    error_log("Errore download cartella: " . $e->getMessage());
    
    if (isset($zipPath) && file_exists($zipPath)) {
        unlink($zipPath);
    }
    
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore durante la creazione dell\'archivio']);
}

/**
 * Aggiunge ricorsivamente file di una cartella al ZIP
 */
function addFolderToZip($zip, $folderId, $folderPath, $companyId) {
    $filesAdded = 0;
    
    // Recupera documenti nella cartella
    $stmt = db_query("
        SELECT id, titolo, file_path 
        FROM documenti 
        WHERE cartella_id = ? AND azienda_id = ?
    ", [$folderId, $companyId]);
    
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($documents as $doc) {
        $filePath = UPLOAD_PATH . '/' . $doc['file_path'];
        
        if (file_exists($filePath)) {
            $fileName = sanitizeFileName($doc['titolo']) . '.' . pathinfo($doc['file_path'], PATHINFO_EXTENSION);
            $zipPath = $folderPath . '/' . $fileName;
            
            $zip->addFile($filePath, $zipPath);
            $filesAdded++;
        }
    }
    
    // Recupera sottocartelle
    $stmt = db_query("
        SELECT id, nome 
        FROM cartelle 
        WHERE parent_id = ? AND azienda_id = ?
    ", [$folderId, $companyId]);
    
    $subfolders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($subfolders as $subfolder) {
        $subfolderPath = $folderPath . '/' . sanitizeFileName($subfolder['nome']);
        $filesAdded += addFolderToZip($zip, $subfolder['id'], $subfolderPath, $companyId);
    }
    
    return $filesAdded;
}

/**
 * Sanitizza nome file per ZIP
 */
function sanitizeFileName($fileName) {
    // Rimuovi caratteri non validi
    $fileName = preg_replace('/[^\w\s\-\.\(\)àèéìòùÀÈÉÌÒÙ]/u', '', $fileName);
    // Sostituisci spazi multipli
    $fileName = preg_replace('/\s+/', ' ', $fileName);
    // Trim
    $fileName = trim($fileName);
    
    return $fileName ?: 'file';
}