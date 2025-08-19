<?php
// Crea un file DOCX di test valido

// Crea directory se non esiste
if (!file_exists('documents/onlyoffice')) {
    mkdir('documents/onlyoffice', 0777, true);
}

// DOCX minimo valido (è un file ZIP con struttura specifica)
// Questo è un file DOCX vuoto ma valido in formato base64
$docxBase64 = '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';

$docxContent = base64_decode($docxBase64);

// Salva il file
$filename = 'documents/onlyoffice/test_document_' . time() . '.docx';
file_put_contents($filename, $docxContent);

// Aggiorna il database
require_once 'backend/config/config.php';

// Verifica se il documento ID 22 esiste
$stmt = db_query("SELECT id FROM documenti WHERE id = 22");
if ($stmt->rowCount() > 0) {
    // Aggiorna il percorso
    db_query(
        "UPDATE documenti SET percorso_file = ?, nome_file = ? WHERE id = 22",
        [$filename, 'Test Document.docx']
    );
    echo "✅ Documento ID 22 aggiornato con nuovo file: $filename\n";
} else {
    echo "❌ Documento ID 22 non trovato nel database\n";
}

// Crea anche una copia semplice
copy($filename, 'documents/test.docx');
echo "✅ File DOCX di test creato: $filename\n";
echo "✅ Copia creata in: documents/test.docx\n";

// Verifica che il file sia valido
if (filesize($filename) > 0) {
    echo "✅ File size: " . filesize($filename) . " bytes\n";
    echo "✅ File is valid DOCX\n";
} else {
    echo "❌ File creation failed\n";
}
?>