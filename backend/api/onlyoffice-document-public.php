<?php
/**
 * OnlyOffice Document Public API
 * Serve i documenti al Document Server OnlyOffice
 * Accessibile via host.docker.internal dal container
 */

// CORS headers per permettere accesso dal container
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
header('Access-Control-Allow-Headers: *');

// Gestione richieste OPTIONS per CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Log della richiesta per debug
error_log("=== OnlyOffice Document Request ===");
error_log("Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Query String: " . $_SERVER['QUERY_STRING']);
error_log("User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'));

$docId = $_GET['doc'] ?? null;
$filename = $_GET['filename'] ?? null;

if (!$docId) {
    http_response_code(400);
    error_log("ERROR: Document ID required");
    die('Document ID required');
}

error_log("Document ID: $docId");
error_log("Filename: " . ($filename ?: 'not specified'));

// Percorsi possibili per i documenti
$basePaths = [
    __DIR__ . '/../../documents/onlyoffice/',
    __DIR__ . '/../../uploads/documenti/',
    __DIR__ . '/../../documents/',
    __DIR__ . '/../../'
];

$filePath = null;

// Cerca il file
foreach ($basePaths as $basePath) {
    // Se è specificato un filename, prova quello
    if ($filename) {
        $testPath = $basePath . $filename;
        error_log("Checking: $testPath");
        if (file_exists($testPath)) {
            $filePath = $testPath;
            error_log("Found file at: $filePath");
            break;
        }
    }
    
    // Prova con diversi pattern basati sull'ID
    $patterns = [
        $basePath . $docId . '.docx',
        $basePath . 'doc_' . $docId . '.docx',
        $basePath . 'document_' . $docId . '.docx',
        $basePath . 'test_document_' . $docId . '.docx',
        $basePath . 'new_' . $docId . '.docx',
        $basePath . $docId . '.xlsx',
        $basePath . $docId . '.pptx',
        $basePath . $docId . '.pdf'
    ];
    
    foreach ($patterns as $pattern) {
        error_log("Checking pattern: $pattern");
        if (file_exists($pattern)) {
            $filePath = $pattern;
            error_log("Found file at: $filePath");
            break 2;
        }
    }
}

// Se non trova il file, crea un documento di test
if (!$filePath) {
    error_log("File not found, creating test document");
    
    // Assicurati che la directory esista
    $testDir = __DIR__ . '/../../documents/onlyoffice/';
    if (!is_dir($testDir)) {
        mkdir($testDir, 0777, true);
        error_log("Created directory: $testDir");
    }
    
    $filePath = $testDir . 'test_document_' . $docId . '.docx';
    
    // Cerca un template DOCX esistente
    $templatePath = null;
    $templatePaths = [
        __DIR__ . '/../../documents/onlyoffice/template.docx',
        __DIR__ . '/../../documents/template.docx',
        __DIR__ . '/template.docx'
    ];
    
    foreach ($templatePaths as $path) {
        if (file_exists($path)) {
            $templatePath = $path;
            break;
        }
    }
    
    if ($templatePath) {
        // Usa il template esistente
        copy($templatePath, $filePath);
        error_log("Created test document from template: $filePath");
    } else {
        // Crea un file DOCX minimo
        // DOCX è un formato ZIP, quindi creiamo un file vuoto base
        $zip = new ZipArchive();
        if ($zip->open($filePath, ZipArchive::CREATE) === TRUE) {
            // Contenuto minimo per un DOCX valido
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>');
            
            $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>');
            
            $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <w:p>
            <w:r>
                <w:t>Test Document ' . $docId . '</w:t>
            </w:r>
        </w:p>
        <w:p>
            <w:r>
                <w:t>This is a test document created for OnlyOffice integration.</w:t>
            </w:r>
        </w:p>
    </w:body>
</w:document>');
            
            $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
</Relationships>');
            
            $zip->close();
            error_log("Created minimal DOCX file: $filePath");
        } else {
            // Fallback: crea un file di testo semplice
            file_put_contents($filePath, "Test document " . $docId);
            error_log("Created simple text file: $filePath");
        }
    }
}

// Verifica che il file esista ora
if (!file_exists($filePath)) {
    http_response_code(404);
    error_log("ERROR: File not found after creation attempt: $filePath");
    die('File not found');
}

// Determina il MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $filePath);
finfo_close($finfo);

// Correzione per i file Office
$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
switch ($ext) {
    case 'docx':
        $mimeType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        break;
    case 'xlsx':
        $mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        break;
    case 'pptx':
        $mimeType = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
        break;
    case 'doc':
        $mimeType = 'application/msword';
        break;
    case 'xls':
        $mimeType = 'application/vnd.ms-excel';
        break;
    case 'ppt':
        $mimeType = 'application/vnd.ms-powerpoint';
        break;
}

// Serve il file
$fileSize = filesize($filePath);
$fileName = basename($filePath);

error_log("Serving file: $fileName");
error_log("MIME Type: $mimeType");
error_log("File Size: $fileSize bytes");

// Headers per servire il file
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . $fileSize);
header('Content-Disposition: inline; filename="' . $fileName . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Output del file
readfile($filePath);

error_log("File served successfully");
exit;