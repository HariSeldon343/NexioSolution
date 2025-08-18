<?php
// Test completo integrazione OnlyOffice
session_start();
require_once 'backend/config/config.php';
require_once 'backend/config/onlyoffice.config.php';

// Simula utente loggato per test
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'test_user';
    $_SESSION['azienda_id'] = 1;
}

// Funzione per generare JWT
function generateTestJWT($payload) {
    global $ONLYOFFICE_JWT_SECRET;
    
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode($payload);
    
    $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $ONLYOFFICE_JWT_SECRET, true);
    $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    return $base64Header . "." . $base64Payload . "." . $base64Signature;
}

// Test 1: Verifica configurazione
echo "<h2>1. Configurazione OnlyOffice</h2>";
echo "Server URL: " . $ONLYOFFICE_DS_PUBLIC_URL . "<br>";
echo "JWT Enabled: " . ($ONLYOFFICE_JWT_ENABLED ? "✅ SI" : "❌ NO") . "<br>";
echo "JWT Secret Length: " . strlen($ONLYOFFICE_JWT_SECRET) . " caratteri<br>";

// Test 2: Verifica connessione al server
echo "<h2>2. Test Connessione Server</h2>";
$ch = curl_init($ONLYOFFICE_DS_PUBLIC_URL . '/healthcheck');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200) {
    echo "✅ Server OnlyOffice raggiungibile (HTTP $httpCode)<br>";
    echo "Response: " . htmlspecialchars($response) . "<br>";
} else {
    echo "❌ Server OnlyOffice non raggiungibile (HTTP $httpCode)<br>";
}

// Test 3: Crea documento di test
echo "<h2>3. Creazione Documento Test</h2>";
$testDocPath = $ONLYOFFICE_DOCUMENTS_DIR . '/test_document_' . time() . '.docx';

// Crea documento DOCX vuoto
$zip = new ZipArchive();
if ($zip->open($testDocPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    // Struttura minima DOCX
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
                <w:t>Test Document OnlyOffice</w:t>
            </w:r>
        </w:p>
    </w:body>
</w:document>');
    
    $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
</Relationships>');
    
    $zip->close();
    echo "✅ Documento test creato: " . basename($testDocPath) . "<br>";
} else {
    echo "❌ Impossibile creare documento test<br>";
}

// Test 4: Inserisci documento nel database
echo "<h2>4. Inserimento in Database</h2>";
try {
    $stmt = db_query("INSERT INTO documenti (titolo, percorso_file, tipo_documento, azienda_id, creato_da, onlyoffice_key) 
                      VALUES (?, ?, 'test', ?, ?, ?)",
                     ['Test OnlyOffice', basename($testDocPath), $_SESSION['azienda_id'], $_SESSION['user_id'], md5(uniqid())]);
    $docId = db_connection()->lastInsertId();
    echo "✅ Documento inserito con ID: $docId<br>";
} catch (Exception $e) {
    echo "❌ Errore inserimento: " . $e->getMessage() . "<br>";
    $docId = null;
}

// Test 5: Genera configurazione editor
if ($docId) {
    echo "<h2>5. Configurazione Editor</h2>";
    
    $documentKey = md5($docId . '_' . time());
    $config = [
        'type' => 'desktop',
        'documentType' => 'word',
        'document' => [
            'title' => 'Test OnlyOffice.docx',
            'url' => 'http://host.docker.internal/piattaforma-collaborativa/backend/api/onlyoffice-document.php?id=' . $docId,
            'fileType' => 'docx',
            'key' => $documentKey,
            'permissions' => [
                'edit' => true,
                'download' => true,
                'print' => true
            ]
        ],
        'editorConfig' => [
            'mode' => 'edit',
            'callbackUrl' => 'http://host.docker.internal/piattaforma-collaborativa/backend/api/onlyoffice-callback.php?id=' . $docId,
            'user' => [
                'id' => (string)$_SESSION['user_id'],
                'name' => $_SESSION['username']
            ],
            'customization' => [
                'forcesave' => true,
                'autosave' => true
            ]
        ]
    ];
    
    // Genera token JWT
    if ($ONLYOFFICE_JWT_ENABLED) {
        $config['token'] = generateTestJWT($config);
        echo "✅ Token JWT generato<br>";
    }
    
    echo "<pre>" . json_encode($config, JSON_PRETTY_PRINT) . "</pre>";
    
    // Test 6: Pulsante per aprire editor
    echo "<h2>6. Test Editor</h2>";
    echo '<div id="placeholder" style="width:100%; height:600px; border:1px solid #ccc; margin:20px 0;"></div>';
    echo '<button onclick="openEditor()">Apri Editor OnlyOffice</button>';
    
    ?>
    <script src="<?php echo $ONLYOFFICE_DS_PUBLIC_URL; ?>/web-apps/apps/api/documents/api.js"></script>
    <script>
    var config = <?php echo json_encode($config); ?>;
    
    function openEditor() {
        console.log('Opening OnlyOffice with config:', config);
        new DocsAPI.DocEditor("placeholder", config);
    }
    </script>
    <?php
}

// Test 7: Verifica Tabelle
echo "<h2>7. Verifica Tabelle</h2>";
try {
    $tables = [
        'documenti' => ['onlyoffice_key', 'is_editing', 'editing_users', 'current_version', 'total_versions'],
        'document_active_editors' => ['document_id', 'user_id', 'user_name'],
        'onlyoffice_sessions' => ['document_id', 'user_id', 'jwt_token'],
        'onlyoffice_locks' => ['document_id', 'user_id', 'lock_type']
    ];
    
    $conn = db_connection();
    
    foreach ($tables as $table => $columns) {
        // Usa query diretta senza placeholder per SHOW TABLES
        $stmt = $conn->query("SHOW TABLES LIKE '$table'");
        if ($stmt && $stmt->rowCount() > 0) {
            echo "✅ Tabella $table esiste<br>";
            
            // Verifica colonne
            foreach ($columns as $col) {
                // Usa query diretta senza placeholder per SHOW COLUMNS
                $stmt2 = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
                if ($stmt2 && $stmt2->rowCount() > 0) {
                    echo "&nbsp;&nbsp;✅ Colonna $col presente<br>";
                } else {
                    echo "&nbsp;&nbsp;❌ Colonna $col mancante<br>";
                }
            }
        } else {
            echo "❌ Tabella $table non trovata<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Errore verifica tabelle: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h2>Riepilogo Test</h2>";
echo "<p>Se tutti i test sono verdi (✅), OnlyOffice è configurato correttamente e pronto all'uso.</p>";
echo "<p>Clicca sul pulsante 'Apri Editor OnlyOffice' per testare l'editing del documento.</p>";
?>