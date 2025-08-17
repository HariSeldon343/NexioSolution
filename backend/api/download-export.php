<?php
/**
 * Download Export - Scarica file temporanei generati dall'export
 * Si aspetta il parametro 'file' con il nome del file da scaricare
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../middleware/Auth.php';

// Richiede autenticazione
$auth = Auth::getInstance();
$auth->requireAuth();

// Ottieni il parametro file
$filename = $_GET['file'] ?? null;

if (!$filename) {
    http_response_code(400);
    die('Parametro file mancante');
}

// Sanitizza il nome del file per sicurezza
$filename = basename($filename);

// Costruisci il percorso completo
$filepath = sys_get_temp_dir() . '/' . $filename;

// Verifica che il file esista
if (!file_exists($filepath)) {
    http_response_code(404);
    die('File non trovato o scaduto');
}

// Verifica che il file non sia troppo vecchio (max 1 ora)
if (time() - filemtime($filepath) > 3600) {
    unlink($filepath); // Elimina file vecchi
    http_response_code(404);
    die('File scaduto');
}

// Determina il content type basato sull'estensione
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$contentType = 'application/octet-stream';

switch ($extension) {
    case 'pdf':
        $contentType = 'application/pdf';
        break;
    case 'doc':
    case 'docx':
        $contentType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        break;
    case 'html':
        $contentType = 'text/html; charset=UTF-8';
        break;
}

// Invia headers per il download
header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Output del file
readfile($filepath);

// Opzionale: elimina il file dopo il download
// unlink($filepath);

exit;
?>