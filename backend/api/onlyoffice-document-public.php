<?php
/**
 * OnlyOffice Document Public API
 * Serve i documenti a OnlyOffice Document Server
 * IMPORTANTE: Questo endpoint deve essere accessibile da OnlyOffice container
 */

// Headers per CORS e caching
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: *');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once '../config/config.php';
require_once '../middleware/Auth.php';

// Per OnlyOffice non richiediamo autenticazione standard
// ma verifichiamo il documento ID
$docId = $_GET['doc'] ?? null;

if (!$docId) {
    http_response_code(400);
    die('Document ID required');
}

// Recupera documento dal database
$stmt = db_query("SELECT * FROM documenti WHERE id = ?", [$docId]);
$document = $stmt->fetch();

if (!$document) {
    http_response_code(404);
    die('Document not found');
}

// Determina il percorso del file
$filePath = null;

// Controlla prima in documents/onlyoffice/
if (!empty($document['filename'])) {
    $onlyofficePath = __DIR__ . '/../../documents/onlyoffice/' . $document['filename'];
    if (file_exists($onlyofficePath)) {
        $filePath = $onlyofficePath;
    }
}

// Se non trovato, controlla nel percorso standard uploads
if (!$filePath && !empty($document['percorso_file'])) {
    $uploadsPath = __DIR__ . '/../../' . $document['percorso_file'];
    if (file_exists($uploadsPath)) {
        $filePath = $uploadsPath;
    }
}

// Se ancora non trovato, prova con path relativo
if (!$filePath && !empty($document['percorso_file'])) {
    if (file_exists($document['percorso_file'])) {
        $filePath = $document['percorso_file'];
    }
}

// Se non troviamo il file, creiamo un documento vuoto di default
if (!$filePath) {
    // Crea un documento vuoto basato sull'estensione
    $ext = pathinfo($document['filename'] ?? 'document.docx', PATHINFO_EXTENSION);
    if (!$ext) $ext = 'docx';
    
    $tempFile = __DIR__ . '/../../documents/onlyoffice/' . $document['id'] . '_temp.' . $ext;
    
    // Crea directory se non esiste
    $dir = dirname($tempFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    // Crea file vuoto o copia da template
    $templatePath = __DIR__ . '/../../templates/blank.' . $ext;
    if (file_exists($templatePath)) {
        copy($templatePath, $tempFile);
    } else {
        // Crea contenuto minimo basato sul tipo
        if ($ext === 'txt') {
            file_put_contents($tempFile, '');
        } else {
            // Per formati Office, creiamo un file minimo
            // In produzione, dovresti avere template vuoti pre-creati
            file_put_contents($tempFile, '');
        }
    }
    
    $filePath = $tempFile;
}

// Verifica che il file esista ora
if (!file_exists($filePath)) {
    http_response_code(404);
    die('File not found: ' . $filePath);
}

// Determina il MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $filePath);
finfo_close($finfo);

// Se MIME type non riconosciuto, usa quello basato sull'estensione
if (!$mimeType || $mimeType === 'application/octet-stream') {
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimeTypes = [
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'doc' => 'application/msword',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xls' => 'application/vnd.ms-excel',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'ppt' => 'application/vnd.ms-powerpoint',
        'odt' => 'application/vnd.oasis.opendocument.text',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        'odp' => 'application/vnd.oasis.opendocument.presentation',
        'txt' => 'text/plain',
        'rtf' => 'application/rtf',
        'csv' => 'text/csv'
    ];
    $mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';
}

// Headers per il download
header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . ($document['filename'] ?? 'document') . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Invia il file
readfile($filePath);

// Log accesso (opzionale)
error_log("OnlyOffice accessed document ID: $docId, File: $filePath");

exit;