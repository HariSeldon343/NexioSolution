<?php
/**
 * OnlyOffice Document Preparation
 * Prepara i documenti per l'editing con OnlyOffice
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/onlyoffice.config.php';

// Headers CORS - SECURITY: Restrict to specific origins
$allowedOrigins = [
    'http://localhost',
    'http://localhost:8082',
    'https://office.yourdomain.com'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header('Access-Control-Allow-Origin: http://localhost');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $file_id = $_GET['file_id'] ?? $_POST['file_id'] ?? null;
    
    if (!$file_id) {
        throw new Exception('File ID mancante');
    }
    
    // SECURITY: Sanitize file_id to prevent path traversal
    $file_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $file_id);
    
    if (empty($file_id) || strlen($file_id) > 100) {
        throw new Exception('File ID non valido');
    }
    
    // Assicurati che la directory documenti esista
    if (!is_dir($ONLYOFFICE_DOCUMENTS_DIR)) {
        mkdir($ONLYOFFICE_DOCUMENTS_DIR, 0755, true);
    }
    
    $file_path = $ONLYOFFICE_DOCUMENTS_DIR . '/' . $file_id . '.docx';
    
    // SECURITY: Verify final path is within allowed directory
    $realPath = realpath(dirname($file_path));
    $allowedPath = realpath($ONLYOFFICE_DOCUMENTS_DIR);
    if ($realPath !== $allowedPath) {
        throw new Exception('Invalid file path');
    }
    
    // Se il file non esiste, crea un documento DOCX vuoto
    if (!file_exists($file_path)) {
        // Crea un documento DOCX minimo usando ZipArchive
        $zip = new ZipArchive();
        $tempFile = tempnam(sys_get_temp_dir(), 'docx');
        
        if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            throw new Exception('Impossibile creare il file DOCX');
        }
        
        // [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
    <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
    <Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>
    <Override PartName="/word/fontTable.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.fontTable+xml"/>
</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        
        // _rels/.rels
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>';
        $zip->addEmptyDir('_rels');
        $zip->addFromString('_rels/.rels', $rels);
        
        // word/_rels/document.xml.rels
        $wordRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/>
    <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/fontTable" Target="fontTable.xml"/>
</Relationships>';
        $zip->addEmptyDir('word');
        $zip->addEmptyDir('word/_rels');
        $zip->addFromString('word/_rels/document.xml.rels', $wordRels);
        
        // word/document.xml
        $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <w:p>
            <w:pPr>
                <w:pStyle w:val="Title"/>
            </w:pPr>
            <w:r>
                <w:t>Nuovo Documento Nexio</w:t>
            </w:r>
        </w:p>
        <w:p>
            <w:r>
                <w:t>Inizia a scrivere il tuo documento qui...</w:t>
            </w:r>
        </w:p>
    </w:body>
</w:document>';
        $zip->addFromString('word/document.xml', $document);
        
        // word/styles.xml
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:style w:type="paragraph" w:default="1" w:styleId="Normal">
        <w:name w:val="Normal"/>
    </w:style>
    <w:style w:type="paragraph" w:styleId="Title">
        <w:name w:val="Title"/>
        <w:basedOn w:val="Normal"/>
        <w:pPr>
            <w:spacing w:before="240" w:after="60"/>
            <w:jc w:val="center"/>
        </w:pPr>
        <w:rPr>
            <w:sz w:val="56"/>
        </w:rPr>
    </w:style>
</w:styles>';
        $zip->addFromString('word/styles.xml', $styles);
        
        // word/settings.xml
        $settings = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:zoom w:percent="100"/>
    <w:defaultTabStop w:val="720"/>
</w:settings>';
        $zip->addFromString('word/settings.xml', $settings);
        
        // word/fontTable.xml
        $fontTable = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:fonts xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:font w:name="Calibri">
        <w:family w:val="swiss"/>
        <w:pitch w:val="variable"/>
    </w:font>
    <w:font w:name="Times New Roman">
        <w:family w:val="roman"/>
        <w:pitch w:val="variable"/>
    </w:font>
</w:fonts>';
        $zip->addFromString('word/fontTable.xml', $fontTable);
        
        $zip->close();
        
        // Copia il file temporaneo nella posizione finale
        copy($tempFile, $file_path);
        unlink($tempFile);
        
        error_log("Documento DOCX creato: $file_path");
    }
    
    // Genera URL del documento
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $basePath = dirname(dirname($_SERVER['REQUEST_URI']));
    $document_url = $protocol . "://" . $host . $basePath . "/backend/api/onlyoffice-document.php?id=" . $file_id . "&action=download";
    
    // Genera chiave unica per il documento
    $document_key = $file_id . '_' . filemtime($file_path) . '_' . filesize($file_path);
    
    echo json_encode([
        'success' => true,
        'file_id' => $file_id,
        'file_path' => $file_path,
        'document_url' => $document_url,
        'document_key' => $document_key,
        'file_exists' => true,
        'file_size' => filesize($file_path)
    ]);
    
} catch (Exception $e) {
    error_log("OnlyOffice Prepare Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 