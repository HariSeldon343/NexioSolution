<?php
/**
 * OnlyOffice Document Public Access
 * Fornisce accesso pubblico ai documenti per OnlyOffice
 * Accessibile via file server su porta 8083
 */

// Abilita CORS per OnlyOffice
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Gestisci richieste OPTIONS per CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Ottieni ID documento
$docId = $_GET['doc'] ?? null;

if (!$docId) {
    http_response_code(400);
    die('Document ID required');
}

// Percorso del documento
$docPath = __DIR__ . '/../../documents/onlyoffice/' . $docId . '.docx';

// Verifica esistenza file
if (!file_exists($docPath)) {
    // Prova anche senza estensione
    $docPath = __DIR__ . '/../../documents/onlyoffice/' . $docId;
    if (!file_exists($docPath)) {
        http_response_code(404);
        die('Document not found: ' . $docId);
    }
}

// Determina il content type basato sull'estensione
$extension = strtolower(pathinfo($docPath, PATHINFO_EXTENSION));
$contentTypes = [
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'doc' => 'application/msword',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'xls' => 'application/vnd.ms-excel',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'ppt' => 'application/vnd.ms-powerpoint',
    'pdf' => 'application/pdf',
    'odt' => 'application/vnd.oasis.opendocument.text',
    'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
    'odp' => 'application/vnd.oasis.opendocument.presentation',
    'txt' => 'text/plain',
    'rtf' => 'application/rtf',
    'csv' => 'text/csv'
];

$contentType = $contentTypes[$extension] ?? 'application/octet-stream';

// Invia headers
header('Content-Type: ' . $contentType);
header('Content-Length: ' . filesize($docPath));
header('Content-Disposition: inline; filename="' . basename($docPath) . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Supporta richieste Range per download parziali
if (isset($_SERVER['HTTP_RANGE'])) {
    $size = filesize($docPath);
    $range = $_SERVER['HTTP_RANGE'];
    
    // Parse range header
    if (preg_match('/bytes=(\d+)-(\d+)?/', $range, $matches)) {
        $start = intval($matches[1]);
        $end = isset($matches[2]) ? intval($matches[2]) : $size - 1;
        
        if ($start > $end || $end >= $size) {
            http_response_code(416);
            header("Content-Range: bytes */$size");
            exit;
        }
        
        http_response_code(206);
        header("Content-Range: bytes $start-$end/$size");
        header("Content-Length: " . ($end - $start + 1));
        
        $fp = fopen($docPath, 'rb');
        fseek($fp, $start);
        $remaining = $end - $start + 1;
        
        while ($remaining > 0 && !feof($fp)) {
            $chunk = min(8192, $remaining);
            echo fread($fp, $chunk);
            $remaining -= $chunk;
            flush();
        }
        
        fclose($fp);
        exit;
    }
}

// Invia il file completo
readfile($docPath);
exit;