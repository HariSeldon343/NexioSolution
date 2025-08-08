<?php
/**
 * Download Handler per file esportati
 * Gestisce il download sicuro dei file esportati dall'editor
 */

require_once '../config/config.php';

try {
    $auth = Auth::getInstance();
    
    if (!$auth->isAuthenticated()) {
        http_response_code(401);
        die('Non autenticato');
    }
    
    $filename = $_GET['file'] ?? '';
    
    if (empty($filename)) {
        http_response_code(400);
        die('Nome file mancante');
    }
    
    // Sanifica il nome del file per sicurezza
    $filename = basename($filename);
    $filepath = sys_get_temp_dir() . '/' . $filename;
    
    if (!file_exists($filepath)) {
        http_response_code(404);
        die('File non trovato');
    }
    
    // Verifica che il file sia stato creato di recente (max 1 ora)
    if (filemtime($filepath) < (time() - 3600)) {
        unlink($filepath);
        http_response_code(410);
        die('File scaduto');
    }
    
    // Determina il content type basato sull'estensione
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $contentTypes = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'html' => 'text/html',
        'htm' => 'text/html'
    ];
    
    $contentType = $contentTypes[$extension] ?? 'application/octet-stream';
    
    // Imposta gli headers per il download
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Invia il file
    readfile($filepath);
    
    // Elimina il file temporaneo dopo il download
    unlink($filepath);
    
} catch (Exception $e) {
    error_log("Download Error: " . $e->getMessage());
    http_response_code(500);
    die('Errore interno del server');
}
?>