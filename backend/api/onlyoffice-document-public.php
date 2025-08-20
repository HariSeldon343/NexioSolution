<?php
/**
 * OnlyOffice Document Public API
 * Endpoint pubblico per servire documenti al container Docker
 * NON richiede autenticazione perché chiamato dal container
 */

// Log per debug
error_log("=== OnlyOffice Document Public Request ===");
error_log("Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Doc ID: " . ($_GET['doc'] ?? 'none'));
error_log("User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'none'));
error_log("Remote IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'none'));

// CORS headers per Docker
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: *');

// Handle OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include config senza auth
require_once __DIR__ . '/../config/config.php';

$docId = $_GET['doc'] ?? null;
$filename = $_GET['filename'] ?? null;

if (!$docId) {
    error_log("ERROR: No document ID provided");
    http_response_code(400);
    header('Content-Type: text/plain');
    die('Document ID required');
}

error_log("Fetching document ID: $docId");

// Percorsi possibili per i documenti
$basePaths = [
    __DIR__ . '/../../documents/onlyoffice/',
    __DIR__ . '/../../uploads/documenti/',
];

// Array di possibili nomi file
$possibleFiles = [];
if ($filename) {
    $possibleFiles[] = $filename;
}
$possibleFiles[] = 'test_document_' . $docId . '.docx';
$possibleFiles[] = $docId . '.docx';
$possibleFiles[] = 'doc_' . $docId . '.docx';
$possibleFiles[] = 'new.docx'; // Fallback generico

// Cerca il file
$filePath = null;
foreach ($basePaths as $basePath) {
    foreach ($possibleFiles as $file) {
        $testPath = $basePath . $file;
        error_log("Checking: $testPath");
        if (file_exists($testPath)) {
            $filePath = $testPath;
            error_log("FOUND: $testPath");
            break 2;
        }
    }
}

// Se non troviamo il file, proviamo dal database
if (!$filePath) {
    error_log("File not found in filesystem, checking database");
    try {
        $stmt = db_query("SELECT * FROM documenti WHERE id = ?", [$docId]);
        $document = $stmt->fetch();
        
        if ($document && !empty($document['percorso_file'])) {
            $dbPath = __DIR__ . '/../../' . $document['percorso_file'];
            error_log("Database path: $dbPath");
            if (file_exists($dbPath)) {
                $filePath = $dbPath;
                error_log("Found via database: $dbPath");
            }
        }
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
    }
}

// Se ancora non troviamo il file, creiamo un documento vuoto
if (!$filePath) {
    error_log("Creating new empty document");
    
    $newDocPath = __DIR__ . '/../../documents/onlyoffice/';
    if (!is_dir($newDocPath)) {
        mkdir($newDocPath, 0777, true);
    }
    
    $newFilePath = $newDocPath . 'test_document_' . $docId . '.docx';
    
    // Crea un DOCX minimo valido
    $zip = new ZipArchive();
    if ($zip->open($newFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        // Content Types
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        
        // Relationships
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>';
        $zip->addFromString('_rels/.rels', $rels);
        
        // Document content
        $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <w:p>
            <w:r>
                <w:t>Nuovo Documento - Nexio Platform</w:t>
            </w:r>
        </w:p>
        <w:p>
            <w:r>
                <w:t>ID Documento: ' . $docId . '</w:t>
            </w:r>
        </w:p>
        <w:p>
            <w:r>
                <w:t>Data creazione: ' . date('d/m/Y H:i:s') . '</w:t>
            </w:r>
        </w:p>
    </w:body>
</w:document>';
        $zip->addFromString('word/document.xml', $document);
        
        $zip->close();
        $filePath = $newFilePath;
        error_log("Created new DOCX: $filePath");
    } else {
        error_log("ERROR: Failed to create DOCX");
        http_response_code(500);
        die("Could not create document");
    }
}

// Verifica finale che il file esista
if (!file_exists($filePath)) {
    error_log("ERROR: File still doesn't exist: $filePath");
    http_response_code(404);
    header('Content-Type: text/plain');
    die("Document file not found");
}

$fileSize = filesize($filePath);
$fileName = basename($filePath);
$extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

error_log("Serving file: $fileName (size: $fileSize bytes, extension: $extension)");

// Determina il MIME type corretto
$mimeTypes = [
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'doc' => 'application/msword',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'xls' => 'application/vnd.ms-excel',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'ppt' => 'application/vnd.ms-powerpoint',
    'pdf' => 'application/pdf',
    'txt' => 'text/plain',
    'rtf' => 'application/rtf',
    'odt' => 'application/vnd.oasis.opendocument.text',
    'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
    'odp' => 'application/vnd.oasis.opendocument.presentation'
];

$mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';

// Headers per il download
header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . $fileName . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Supporto per range requests (per file grandi)
if (isset($_SERVER['HTTP_RANGE'])) {
    error_log("Range request: " . $_SERVER['HTTP_RANGE']);
    
    $range = $_SERVER['HTTP_RANGE'];
    list($unit, $ranges) = explode('=', $range, 2);
    
    if ($unit !== 'bytes') {
        header('HTTP/1.1 416 Range Not Satisfiable');
        header("Content-Range: bytes */$fileSize");
        exit;
    }
    
    $ranges = explode(',', $ranges)[0];
    list($start, $end) = explode('-', $ranges);
    
    $start = intval($start);
    $end = $end ? intval($end) : $fileSize - 1;
    
    if ($start > $end || $end >= $fileSize) {
        header('HTTP/1.1 416 Range Not Satisfiable');
        header("Content-Range: bytes */$fileSize");
        exit;
    }
    
    $length = $end - $start + 1;
    
    header('HTTP/1.1 206 Partial Content');
    header("Content-Range: bytes $start-$end/$fileSize");
    header("Content-Length: $length");
    
    $fp = fopen($filePath, 'rb');
    fseek($fp, $start);
    
    $remaining = $length;
    while ($remaining > 0 && !feof($fp)) {
        $buffer = fread($fp, min(8192, $remaining));
        echo $buffer;
        $remaining -= strlen($buffer);
        flush();
    }
    
    fclose($fp);
} else {
    // Invia il file completo
    $fp = fopen($filePath, 'rb');
    if ($fp) {
        while (!feof($fp)) {
            echo fread($fp, 8192);
            flush();
        }
        fclose($fp);
        error_log("File sent successfully");
    } else {
        error_log("ERROR: Failed to open file for reading");
        http_response_code(500);
        die("Could not read file");
    }
}

exit;
?>