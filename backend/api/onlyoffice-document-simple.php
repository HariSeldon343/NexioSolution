<?php
/**
 * OnlyOffice Document API - VERSIONE SEMPLIFICATA
 * Endpoint accessibile senza autenticazione per test dal container Docker
 * 
 * ATTENZIONE: Questo file è solo per test/debug!
 * Non usare in produzione senza autenticazione!
 */

// Log TUTTO per debug
error_log("=== OnlyOffice Document Simple Request ===");
error_log("Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Query String: " . $_SERVER['QUERY_STRING']);
error_log("User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'none'));
error_log("Remote IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'none'));
error_log("Headers: " . json_encode(getallheaders()));

// CORS aperto per test
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: *');

// Handle OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Configurazione database diretta
$dbHost = 'localhost';
$dbName = 'nexiosol';
$dbUser = 'root';
$dbPass = '';

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    die("Database connection failed");
}

// Ottieni ID documento
$docId = $_GET['doc'] ?? $_GET['id'] ?? null;

if (!$docId) {
    error_log("No document ID provided");
    http_response_code(400);
    die("Document ID required");
}

error_log("Requesting document ID: $docId");

// Percorsi possibili per i documenti
$possiblePaths = [
    __DIR__ . '/../../documents/onlyoffice/test_document_' . $docId . '.docx',
    __DIR__ . '/../../documents/onlyoffice/' . $docId . '.docx',
    __DIR__ . '/../../uploads/documenti/' . $docId . '.docx',
    __DIR__ . '/../../documents/onlyoffice/new.docx', // Fallback generico
];

// Trova il primo file esistente
$filePath = null;
foreach ($possiblePaths as $path) {
    error_log("Checking path: $path");
    if (file_exists($path)) {
        $filePath = $path;
        error_log("Found file at: $path");
        break;
    }
}

// Se non troviamo il file, creiamo un documento vuoto
if (!$filePath) {
    error_log("No existing file found, creating new document");
    
    $newDocPath = __DIR__ . '/../../documents/onlyoffice/test_document_' . $docId . '.docx';
    $dir = dirname($newDocPath);
    
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
        error_log("Created directory: $dir");
    }
    
    // Crea un DOCX minimo
    $zip = new ZipArchive();
    if ($zip->open($newDocPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        // Content Types
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        
        // Main relationship
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
                <w:t>Documento di test ' . $docId . ' - Nexio Platform</w:t>
            </w:r>
        </w:p>
    </w:body>
</w:document>';
        $zip->addFromString('word/document.xml', $document);
        
        $zip->close();
        $filePath = $newDocPath;
        error_log("Created new DOCX at: $filePath");
    } else {
        error_log("Failed to create DOCX file");
        http_response_code(500);
        die("Could not create document");
    }
}

// Verifica che il file esista ora
if (!file_exists($filePath)) {
    error_log("File still doesn't exist: $filePath");
    http_response_code(404);
    die("Document file not found");
}

$fileSize = filesize($filePath);
$fileName = basename($filePath);

error_log("Serving file: $fileName (size: $fileSize bytes)");

// Headers per il download
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: inline; filename="' . $fileName . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Invia il file
$fp = fopen($filePath, 'rb');
if ($fp) {
    while (!feof($fp)) {
        echo fread($fp, 8192);
        flush();
    }
    fclose($fp);
    error_log("File sent successfully");
} else {
    error_log("Failed to open file for reading");
    http_response_code(500);
    die("Could not read file");
}

exit;
?>