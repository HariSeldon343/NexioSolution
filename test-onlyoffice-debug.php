<?php
/**
 * Test debug per OnlyOffice - verifica apertura documento
 */

// Test 1: Verifica file DOCX valido
echo "<h2>Test OnlyOffice Debug</h2>";

// Verifica che il file sia un DOCX valido
$testFile = __DIR__ . '/documents/onlyoffice/test_document_1755542731.docx';
if (!file_exists($testFile)) {
    die("❌ File test non trovato: $testFile");
}

// Verifica magic bytes
$handle = fopen($testFile, 'rb');
$bytes = fread($handle, 4);
fclose($handle);

if (substr($bytes, 0, 2) !== 'PK') {
    die("❌ File non è un DOCX valido (non inizia con PK)");
}
echo "✅ File DOCX valido trovato<br>";

// Test 2: Verifica configurazione OnlyOffice
require_once 'backend/config/onlyoffice.config.php';

echo "<h3>Configurazione OnlyOffice:</h3>";
echo "- Server URL: " . $ONLYOFFICE_DS_PUBLIC_URL . "<br>";
echo "- JWT Enabled: " . ($ONLYOFFICE_JWT_ENABLED ? 'Sì' : 'No') . "<br>";
echo "- Callback URL: " . $ONLYOFFICE_CALLBACK_URL . "<br>";

// Test 3: Verifica connessione al server
$healthUrl = $ONLYOFFICE_DS_PUBLIC_URL . '/healthcheck';
$context = stream_context_create([
    'http' => [
        'timeout' => 5,
        'ignore_errors' => true
    ]
]);
$health = @file_get_contents($healthUrl, false, $context);

if ($health === false) {
    echo "❌ OnlyOffice Document Server non raggiungibile<br>";
} else {
    echo "✅ OnlyOffice Document Server attivo<br>";
}

// Test 4: Verifica database
require_once 'backend/config/config.php';

$stmt = db_query("SELECT id, titolo, percorso_file, mime_type FROM documenti WHERE percorso_file LIKE '%test_document%' LIMIT 1");
$doc = $stmt->fetch(PDO::FETCH_ASSOC);

if ($doc) {
    echo "<h3>Documento nel database:</h3>";
    echo "- ID: " . $doc['id'] . "<br>";
    echo "- Titolo: " . $doc['titolo'] . "<br>";
    echo "- Percorso: " . $doc['percorso_file'] . "<br>";
    echo "- MIME Type: " . $doc['mime_type'] . "<br>";
    
    // Link per test
    echo "<h3>Link di test:</h3>";
    echo "<a href='onlyoffice-editor.php?id=" . $doc['id'] . "' target='_blank' class='btn btn-primary'>Apri in OnlyOffice (view)</a> ";
    echo "<a href='onlyoffice-editor.php?id=" . $doc['id'] . "&mode=edit' target='_blank' class='btn btn-success'>Apri in OnlyOffice (edit)</a><br><br>";
} else {
    // Crea documento di test nel database
    echo "<h3>Creazione documento di test...</h3>";
    
    $query = "INSERT INTO documenti (codice, titolo, percorso_file, file_path, mime_type, dimensione_file, tipo_documento, azienda_id, creato_da, data_caricamento) 
              VALUES (?, ?, ?, ?, ?, ?, ?, NULL, 1, NOW())";
    
    $fileSize = filesize($testFile);
    $params = [
        'TEST_' . time(),
        'Test Document OnlyOffice',
        'documents/onlyoffice/test_document_1755542731.docx',
        'documents/onlyoffice/test_document_1755542731.docx',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        $fileSize,
        'documento'
    ];
    
    try {
        db_query($query, $params);
        $docId = db_connection()->lastInsertId();
        echo "✅ Documento creato con ID: $docId<br>";
        echo "<a href='onlyoffice-editor.php?id=$docId' target='_blank' class='btn btn-primary'>Apri in OnlyOffice</a><br>";
    } catch (Exception $e) {
        echo "❌ Errore creazione documento: " . $e->getMessage() . "<br>";
    }
}

// Test 5: Verifica JavaScript errors
?>
<script>
window.onerror = function(msg, url, line, col, error) {
    console.error('JavaScript Error:', {
        message: msg,
        source: url,
        line: line,
        column: col,
        error: error
    });
    
    var errorDiv = document.createElement('div');
    errorDiv.style.color = 'red';
    errorDiv.innerHTML = '❌ JavaScript Error: ' + msg + ' (Line: ' + line + ')';
    document.body.appendChild(errorDiv);
    return false;
};

console.log('Test debug script loaded successfully');
</script>

<h3>Console del browser:</h3>
<p>Apri la console del browser (F12) per vedere eventuali errori JavaScript quando apri OnlyOffice.</p>