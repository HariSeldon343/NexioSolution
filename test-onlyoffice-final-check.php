<?php
/**
 * VERIFICA FINALE CONFIGURAZIONE ONLYOFFICE HTTPS
 * Test completo della configurazione Docker con HTTPS su porta 8443
 */

// Importa configurazione
require_once 'backend/config/onlyoffice.config.php';

// Definizioni URL
$ONLYOFFICE_HTTP_URL = 'http://localhost:8080';
$ONLYOFFICE_HTTPS_URL = 'https://localhost:8443';
$FILESERVER_URL = 'http://localhost:8081';

// Test connessioni
function testUrl($url, $name, $verifySSL = true) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    // Disabilita verifica SSL per HTTPS localhost
    if (!$verifySSL && strpos($url, 'https://') === 0) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'name' => $name,
        'url' => $url,
        'success' => $httpCode == 200,
        'http_code' => $httpCode,
        'error' => $error,
        'response_length' => strlen($response)
    ];
}

// Esegui test
$tests = [
    testUrl($ONLYOFFICE_HTTP_URL . '/healthcheck', 'OnlyOffice HTTP Healthcheck'),
    testUrl($ONLYOFFICE_HTTPS_URL . '/healthcheck', 'OnlyOffice HTTPS Healthcheck', false),
    testUrl($FILESERVER_URL . '/', 'File Server Root'),
    testUrl($FILESERVER_URL . '/documents/', 'File Server Documents')
];

// Test configurazione classe
$configTest = OnlyOfficeConfig::testConnection();

// Verifica file di esempio
$testFile = 'documents/onlyoffice/45.docx';
$fileExists = file_exists($testFile);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OnlyOffice HTTPS - Verifica Finale</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .config-table {
            width: 100%;
            border-collapse: collapse;
        }
        .config-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
        }
        .config-table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        .status.success {
            background: #d4edda;
            color: #155724;
        }
        .status.error {
            background: #f8d7da;
            color: #721c24;
        }
        .status.warning {
            background: #fff3cd;
            color: #856404;
        }
        .test-result {
            margin: 15px 0;
            padding: 15px;
            border-left: 4px solid;
            background: #f8f9fa;
        }
        .test-result.success {
            border-color: #28a745;
        }
        .test-result.error {
            border-color: #dc3545;
        }
        .code-block {
            background: #f4f4f4;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            overflow-x: auto;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 5px;
        }
        .button:hover {
            background: #5a67d8;
        }
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .alert.info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .alert.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
    </style>
</head>
<body>

<div class="header">
    <h1>🔒 OnlyOffice HTTPS - Verifica Finale</h1>
    <p>Test completo della configurazione Docker con HTTPS su porta 8443</p>
    <p>Data: <?php echo date('Y-m-d H:i:s'); ?></p>
</div>

<!-- Configurazione Attuale -->
<div class="section">
    <h2>📋 Configurazione Attuale</h2>
    <table class="config-table">
        <tr>
            <th>Parametro</th>
            <th>Valore</th>
            <th>Stato</th>
        </tr>
        <tr>
            <td>OnlyOffice Public URL</td>
            <td><code><?php echo OnlyOfficeConfig::ONLYOFFICE_DS_PUBLIC_URL; ?></code></td>
            <td><span class="status <?php echo strpos(OnlyOfficeConfig::ONLYOFFICE_DS_PUBLIC_URL, 'https://') === 0 ? 'success' : 'warning'; ?>">
                <?php echo strpos(OnlyOfficeConfig::ONLYOFFICE_DS_PUBLIC_URL, 'https://') === 0 ? 'HTTPS ✓' : 'HTTP'; ?>
            </span></td>
        </tr>
        <tr>
            <td>File Server URL</td>
            <td><code><?php echo OnlyOfficeConfig::FILESERVER_PUBLIC_URL; ?></code></td>
            <td><span class="status success">Configurato</span></td>
        </tr>
        <tr>
            <td>JWT Enabled</td>
            <td><code><?php echo OnlyOfficeConfig::JWT_ENABLED ? 'true' : 'false'; ?></code></td>
            <td><span class="status <?php echo !OnlyOfficeConfig::JWT_ENABLED ? 'warning' : 'success'; ?>">
                <?php echo !OnlyOfficeConfig::JWT_ENABLED ? 'Disabilitato (Test)' : 'Abilitato'; ?>
            </span></td>
        </tr>
        <tr>
            <td>Documents Path</td>
            <td><code><?php echo OnlyOfficeConfig::DOCUMENTS_PATH; ?></code></td>
            <td><span class="status <?php echo is_dir(OnlyOfficeConfig::DOCUMENTS_PATH) ? 'success' : 'error'; ?>">
                <?php echo is_dir(OnlyOfficeConfig::DOCUMENTS_PATH) ? 'Esiste ✓' : 'Non trovato'; ?>
            </span></td>
        </tr>
    </table>
</div>

<!-- Test Connessioni -->
<div class="section">
    <h2>🔌 Test Connessioni</h2>
    
    <?php foreach ($tests as $test): ?>
    <div class="test-result <?php echo $test['success'] ? 'success' : 'error'; ?>">
        <strong><?php echo $test['name']; ?></strong><br>
        URL: <code><?php echo $test['url']; ?></code><br>
        Stato: <span class="status <?php echo $test['success'] ? 'success' : 'error'; ?>">
            <?php echo $test['success'] ? 'OK (HTTP ' . $test['http_code'] . ')' : 'ERRORE (' . ($test['error'] ?: 'HTTP ' . $test['http_code']) . ')'; ?>
        </span>
        <?php if ($test['success']): ?>
            - Response size: <?php echo $test['response_length']; ?> bytes
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    
    <!-- Test configurazione classe -->
    <div class="test-result <?php echo $configTest['success'] ? 'success' : 'error'; ?>">
        <strong>OnlyOfficeConfig::testConnection()</strong><br>
        URL: <code><?php echo $configTest['url']; ?></code><br>
        HTTPS: <span class="status <?php echo $configTest['is_https'] ? 'success' : 'warning'; ?>">
            <?php echo $configTest['is_https'] ? 'Sì ✓' : 'No'; ?>
        </span><br>
        Stato: <span class="status <?php echo $configTest['success'] ? 'success' : 'error'; ?>">
            <?php echo $configTest['success'] ? 'Connesso ✓' : 'Errore: ' . $configTest['error']; ?>
        </span>
    </div>
</div>

<!-- Test API JavaScript -->
<div class="section">
    <h2>🌐 Test API JavaScript</h2>
    <div id="js-test-results">
        <p>Caricamento test JavaScript...</p>
    </div>
</div>

<!-- Docker Status -->
<div class="section">
    <h2>🐳 Stato Docker Containers</h2>
    <div class="code-block">
        <?php
        $dockerStatus = shell_exec('docker ps --filter "name=nexio-" --format "table {{.Names}}\t{{.Ports}}\t{{.Status}}" 2>&1');
        echo htmlspecialchars($dockerStatus ?: 'Impossibile ottenere lo stato dei container');
        ?>
    </div>
</div>

<!-- Test File di Esempio -->
<div class="section">
    <h2>📄 Test File di Esempio</h2>
    <div class="test-result <?php echo $fileExists ? 'success' : 'warning'; ?>">
        <strong>File: <?php echo $testFile; ?></strong><br>
        Stato: <span class="status <?php echo $fileExists ? 'success' : 'warning'; ?>">
            <?php echo $fileExists ? 'Presente ✓' : 'Non trovato'; ?>
        </span>
        <?php if ($fileExists): ?>
            <br>Dimensione: <?php echo number_format(filesize($testFile) / 1024, 2); ?> KB
            <br>URL Document Server: <code><?php echo OnlyOfficeConfig::getDocumentUrl('45.docx'); ?></code>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Links -->
<div class="section">
    <h2>🔗 Link Rapidi</h2>
    <a href="<?php echo $ONLYOFFICE_HTTP_URL; ?>/welcome/" target="_blank" class="button">OnlyOffice HTTP Welcome</a>
    <a href="<?php echo $ONLYOFFICE_HTTPS_URL; ?>/welcome/" target="_blank" class="button">OnlyOffice HTTPS Welcome</a>
    <a href="<?php echo $FILESERVER_URL; ?>/documents/" target="_blank" class="button">File Server Documents</a>
    <a href="test-onlyoffice-https.php" class="button">Test Editor HTTPS</a>
</div>

<!-- Checklist Deployment -->
<div class="section">
    <h2>✅ Checklist per Deployment su Cloudflare</h2>
    <div class="alert info">
        <h3>Configurazioni da modificare per produzione:</h3>
        <ol>
            <li>
                <strong>Certificati SSL validi</strong>
                <ul>
                    <li>Sostituire certificati self-signed con certificati Cloudflare</li>
                    <li>Configurare Cloudflare Tunnel per HTTPS automatico</li>
                </ul>
            </li>
            <li>
                <strong>JWT Security</strong>
                <ul>
                    <li>Abilitare JWT_ENABLED = true in produzione</li>
                    <li>Generare JWT_SECRET sicuro (min 32 caratteri)</li>
                </ul>
            </li>
            <li>
                <strong>URL Pubblici</strong>
                <ul>
                    <li>Aggiornare PRODUCTION_URL in OnlyOfficeConfig</li>
                    <li>Configurare PRODUCTION_DS_URL per OnlyOffice pubblico</li>
                </ul>
            </li>
            <li>
                <strong>Docker Compose</strong>
                <ul>
                    <li>Rimuovere port mapping diretti (usare reverse proxy)</li>
                    <li>Configurare volumi persistenti su storage affidabile</li>
                    <li>Impostare limiti di risorse appropriati</li>
                </ul>
            </li>
            <li>
                <strong>Network Security</strong>
                <ul>
                    <li>Configurare firewall per bloccare accesso diretto alle porte</li>
                    <li>Utilizzare Cloudflare WAF per protezione aggiuntiva</li>
                    <li>Implementare rate limiting</li>
                </ul>
            </li>
        </ol>
    </div>
</div>

<script>
// Test JavaScript API
async function testJavaScriptAPI() {
    const results = document.getElementById('js-test-results');
    results.innerHTML = '<h3>Esecuzione test JavaScript...</h3>';
    
    const tests = [
        {
            name: 'OnlyOffice API Script (HTTPS)',
            url: 'https://localhost:8443/web-apps/apps/api/documents/api.js',
            type: 'script'
        },
        {
            name: 'OnlyOffice API Script (HTTP)',
            url: 'http://localhost:8080/web-apps/apps/api/documents/api.js',
            type: 'script'
        },
        {
            name: 'File Server Test',
            url: 'http://localhost:8081/',
            type: 'fetch'
        }
    ];
    
    let html = '';
    
    for (const test of tests) {
        html += `<div class="test-result">`;
        html += `<strong>${test.name}</strong><br>`;
        html += `URL: <code>${test.url}</code><br>`;
        
        if (test.type === 'script') {
            // Test caricamento script
            try {
                const script = document.createElement('script');
                script.src = test.url;
                
                await new Promise((resolve, reject) => {
                    script.onload = resolve;
                    script.onerror = reject;
                    setTimeout(reject, 5000); // Timeout 5 secondi
                    document.head.appendChild(script);
                });
                
                // Verifica se DocsAPI è disponibile
                if (test.url.includes('8443') && typeof DocsAPI !== 'undefined') {
                    html += `<span class="status success">Caricato ✓ - DocsAPI disponibile</span>`;
                } else if (test.url.includes('8080') && typeof DocsAPI !== 'undefined') {
                    html += `<span class="status success">Caricato ✓ - DocsAPI disponibile</span>`;
                } else {
                    html += `<span class="status warning">Caricato ma DocsAPI non trovato</span>`;
                }
            } catch (error) {
                html += `<span class="status error">Errore caricamento</span>`;
            }
        } else {
            // Test fetch
            try {
                const response = await fetch(test.url, {
                    method: 'HEAD',
                    mode: 'no-cors'
                });
                html += `<span class="status success">Raggiungibile ✓</span>`;
            } catch (error) {
                html += `<span class="status error">Non raggiungibile</span>`;
            }
        }
        
        html += `</div>`;
    }
    
    // Mostra risultati
    results.innerHTML = html;
    
    // Test finale DocsAPI
    if (typeof DocsAPI !== 'undefined') {
        results.innerHTML += `
            <div class="alert success">
                <strong>✓ DocsAPI Caricato Correttamente!</strong><br>
                L'API JavaScript di OnlyOffice è disponibile e pronta all'uso.
            </div>
        `;
    } else {
        results.innerHTML += `
            <div class="alert warning">
                <strong>⚠ DocsAPI Non Disponibile</strong><br>
                L'API JavaScript di OnlyOffice non è stata caricata. Questo potrebbe essere dovuto a:
                <ul>
                    <li>Container OnlyOffice ancora in avvio</li>
                    <li>Problemi con certificati SSL</li>
                    <li>Blocco CORS del browser</li>
                </ul>
            </div>
        `;
    }
}

// Esegui test al caricamento
setTimeout(testJavaScriptAPI, 1000);
</script>

</body>
</html>