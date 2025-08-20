<?php
/**
 * Verifica configurazione OnlyOffice su HTTP porta 8082
 */

require_once 'backend/config/onlyoffice.config.php';

echo "=== VERIFICA CONFIGURAZIONE ONLYOFFICE ===\n\n";

// 1. Verifica URL configurato
$dsUrl = OnlyOfficeConfig::getDocumentServerPublicUrl();
echo "1. Document Server URL configurato: " . $dsUrl . "\n";

// 2. Verifica che sia HTTP e porta 8082 in locale
if (OnlyOfficeConfig::isLocal()) {
    if ($dsUrl === 'http://localhost:8082/') {
        echo "   ✓ Configurazione locale corretta (HTTP porta 8082)\n";
    } else {
        echo "   ✗ ERRORE: Configurazione locale errata!\n";
    }
} else {
    echo "   Ambiente di produzione\n";
}

// 3. Test connessione al Document Server
echo "\n2. Test connessione al Document Server:\n";
$testUrl = $dsUrl . 'web-apps/apps/api/documents/api.js';
echo "   Testing: " . $testUrl . "\n";

$ch = curl_init($testUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200 && strpos($response, 'DocsAPI') !== false) {
    echo "   ✓ DocsAPI.js caricato correttamente\n";
    echo "   ✓ OnlyOffice è raggiungibile e funzionante\n";
} else {
    echo "   ✗ ERRORE: Impossibile caricare DocsAPI.js\n";
    echo "   HTTP Code: " . $httpCode . "\n";
}

// 4. Verifica URL interni (host.docker.internal)
echo "\n3. URL interni per il container:\n";
echo "   File Server Internal: " . OnlyOfficeConfig::FILESERVER_INTERNAL_BASE . "\n";
echo "   (Usa host.docker.internal per comunicazione container->host)\n";

// 5. Test generazione configurazione
echo "\n4. Test generazione configurazione editor:\n";
$testDoc = ['id' => 1, 'nome' => 'Test Document', 'filename' => 'test.docx'];
$testUser = ['id' => 1, 'nome' => 'Test User'];
$config = OnlyOfficeConfig::getEditorConfig($testDoc, $testUser);

echo "   Document URL: " . $config['document']['url'] . "\n";
echo "   Callback URL: " . $config['editorConfig']['callbackUrl'] . "\n";

// 6. Verifica container Docker
echo "\n5. Verifica container Docker:\n";
$dockerOutput = shell_exec('docker ps --filter "ancestor=onlyoffice/documentserver" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}" 2>&1');
if ($dockerOutput) {
    echo $dockerOutput;
} else {
    echo "   Impossibile verificare container Docker\n";
}

echo "\n=== VERIFICA COMPLETATA ===\n";

// Se chiamato via browser, formatta l'output
if (php_sapi_name() !== 'cli') {
    $output = ob_get_contents();
    ob_clean();
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
}
?>