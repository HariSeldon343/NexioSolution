<?php
// Crea un documento DOCX minimo ma valido
$dir = __DIR__ . '/documents/onlyoffice';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$filename = $dir . '/test_document_' . time() . '.docx';
$zip = new ZipArchive();

if ($zip->open($filename, ZipArchive::CREATE) === TRUE) {
    // Struttura minima DOCX
    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>';
    $zip->addFromString('[Content_Types].xml', $contentTypes);
    
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>';
    $zip->addFromString('_rels/.rels', $rels);
    
    $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Test Document OnlyOffice - ' . date('Y-m-d H:i:s') . '</w:t></w:r></w:p><w:p><w:r><w:t>Se vedi questo testo, il documento è stato caricato correttamente!</w:t></w:r></w:p></w:body></w:document>';
    $zip->addFromString('word/document.xml', $document);
    
    $docRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>';
    $zip->addFromString('word/_rels/document.xml.rels', $docRels);
    
    $zip->close();
    
    echo "✅ Documento creato: " . basename($filename) . "\n";
    echo "📁 Path: " . $filename . "\n";
    echo "📏 Size: " . filesize($filename) . " bytes\n";
    echo "\n🔗 URLs da testare:\n";
    echo "1. http://localhost/piattaforma-collaborativa/documents/onlyoffice/" . basename($filename) . "\n";
    echo "2. http://host.docker.internal/piattaforma-collaborativa/documents/onlyoffice/" . basename($filename) . "\n";
} else {
    echo "❌ Errore nella creazione del documento\n";
}
?>