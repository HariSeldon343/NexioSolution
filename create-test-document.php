<?php
/**
 * Script per creare documenti di test per OnlyOffice
 * Crea sia file fisici che record nel database
 */

require_once 'backend/config/config.php';

// Crea un documento DOCX valido per test
$docId = 22;
$testDocPath = __DIR__ . '/documents/onlyoffice/test_document_' . $docId . '.docx';

// Crea directory se non esiste
$dir = dirname($testDocPath);
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
    echo "Directory creata: $dir\n";
}

// Crea un DOCX minimo valido (è un file ZIP con struttura specifica)
function createMinimalDocx($path) {
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        return false;
    }
    
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
    
    // Document
    $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <w:p>
            <w:r>
                <w:t>Test Document for OnlyOffice - ID ' . $docId . '</w:t>
            </w:r>
        </w:p>
    </w:body>
</w:document>';
    $zip->addFromString('word/document.xml', $document);
    
    $zip->close();
    return true;
}

// Crea il documento
if (createMinimalDocx($testDocPath)) {
    echo "✓ Documento DOCX creato: $testDocPath\n";
    echo "  Dimensione: " . filesize($testDocPath) . " bytes\n";
} else {
    echo "✗ Errore nella creazione del documento\n";
}

// Verifica nel database
try {
    $stmt = db_query("SELECT * FROM documenti WHERE id = ?", [$docId]);
    $doc = $stmt->fetch();
    
    if (!$doc) {
        // Crea record nel database
        $stmt = db_query(
            "INSERT INTO documenti (id, nome, percorso_file, tipo_documento, azienda_id, created_at) 
             VALUES (?, ?, ?, ?, NULL, NOW())
             ON DUPLICATE KEY UPDATE percorso_file = VALUES(percorso_file)",
            [$docId, 'Test Document ' . $docId, 'documents/onlyoffice/test_document_' . $docId . '.docx', 'documento']
        );
        echo "✓ Record creato nel database per documento ID $docId\n";
    } else {
        echo "✓ Documento già presente nel database:\n";
        echo "  Nome: " . $doc['nome'] . "\n";
        echo "  Percorso: " . $doc['percorso_file'] . "\n";
    }
} catch (Exception $e) {
    echo "✗ Errore database: " . $e->getMessage() . "\n";
}

// Test accesso diretto via HTTP
echo "\n=== Test Accesso HTTP ===\n";

$testUrls = [
    'Diretto al file' => "http://localhost/piattaforma-collaborativa/documents/onlyoffice/test_document_{$docId}.docx",
    'Via API document' => "http://localhost/piattaforma-collaborativa/backend/api/onlyoffice-document.php?doc={$docId}",
    'Via simple download' => "http://localhost/piattaforma-collaborativa/backend/api/simple-download.php?doc={$docId}"
];

foreach ($testUrls as $name => $url) {
    echo "\n$name:\n";
    echo "  URL: $url\n";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200) {
        echo "  ✓ HTTP $httpCode - OK\n";
    } else {
        echo "  ✗ HTTP $httpCode - Errore\n";
    }
}

echo "\n=== Prossimi passi ===\n";
echo "1. Esegui test-docker-connectivity.bat per verificare la connessione Docker\n";
echo "2. Prova ad aprire: http://localhost/piattaforma-collaborativa/onlyoffice-editor.php?doc={$docId}\n";
echo "3. Se fallisce, controlla test-onlyoffice-diagnostics.php\n";
?>