<?php
/**
 * Test OnlyOffice con host.docker.internal - Versione Definitiva
 * 
 * IMPORTANTE: Questo file dimostra la configurazione CORRETTA per OnlyOffice
 * usando host.docker.internal per la comunicazione dal container Docker
 */

require_once 'backend/config/onlyoffice.config.php';

// Simula un documento di test
$testDocId = $_GET['doc'] ?? '22';
$testFilename = $_GET['filename'] ?? 'test_document_' . $testDocId . '.docx';

// Genera configurazione usando la classe definitiva
$documentKey = OnlyOfficeConfig::generateDocumentKey($testDocId);
$documentUrl = OnlyOfficeConfig::getDocumentUrl($testDocId, $testFilename);
$callbackUrl = OnlyOfficeConfig::getCallbackUrl($testDocId);
$onlyofficeApiUrl = OnlyOfficeConfig::getDocumentServerPublicUrl() . 'web-apps/apps/api/documents/api.js';

// Configurazione completa per l'editor
$config = [
    'document' => [
        'fileType' => pathinfo($testFilename, PATHINFO_EXTENSION),
        'key' => $documentKey,
        'title' => 'Test Document ' . $testDocId,
        'url' => $documentUrl,
        'permissions' => [
            'download' => true,
            'edit' => true,
            'print' => true,
            'review' => true,
            'chat' => false // IMPORTANTE: chat in permissions, NON in customization
        ]
    ],
    'documentType' => OnlyOfficeConfig::getDocumentType(pathinfo($testFilename, PATHINFO_EXTENSION)),
    'editorConfig' => [
        'callbackUrl' => $callbackUrl,
        'mode' => 'edit',
        'lang' => 'it',
        'user' => [
            'id' => 'test-user-1',
            'name' => 'Test User'
        ],
        'customization' => [
            'autosave' => true,
            'compactHeader' => false,
            'feedback' => false,
            'forcesave' => false
            // NON mettere 'chat' qui - è deprecato!
        ]
    ],
    'type' => 'desktop'
];

// Informazioni di debug
$debugInfo = [
    'environment' => OnlyOfficeConfig::isLocal() ? 'LOCAL' : 'PRODUCTION',
    'document_server_url' => OnlyOfficeConfig::getDocumentServerPublicUrl(),
    'document_url_internal' => $documentUrl,
    'callback_url_internal' => $callbackUrl,
    'api_url_public' => $onlyofficeApiUrl,
    'document_public_url' => OnlyOfficeConfig::getPublicDocumentUrl($testDocId),
    'host_docker_internal_base' => OnlyOfficeConfig::FILESERVER_INTERNAL_BASE,
    'public_base' => OnlyOfficeConfig::getFileServerPublicBase()
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <title>Test OnlyOffice - Host.Docker.Internal</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            margin: 0 0 10px 0;
            color: #333;
        }
        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: bold;
        }
        .status.success { background: #4CAF50; color: white; }
        .status.error { background: #f44336; color: white; }
        .status.warning { background: #ff9800; color: white; }
        .debug-panel {
            background: #263238;
            color: #aed581;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            overflow-x: auto;
        }
        .debug-title {
            color: #ffeb3b;
            margin-bottom: 10px;
            font-weight: bold;
        }
        .debug-item {
            margin: 5px 0;
            padding: 5px 0;
            border-bottom: 1px solid #37474f;
        }
        .debug-key {
            color: #80deea;
            display: inline-block;
            min-width: 250px;
        }
        .debug-value {
            color: #c5e1a5;
        }
        .editor-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        #placeholder {
            height: 700px;
            width: 100%;
        }
        .test-controls {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        button {
            background: #2196F3;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-right: 10px;
        }
        button:hover {
            background: #1976D2;
        }
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .alert.info { background: #e3f2fd; color: #1565c0; border-left: 4px solid #2196F3; }
        .alert.success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #4CAF50; }
        .alert.error { background: #ffebee; color: #c62828; border-left: 4px solid #f44336; }
        pre {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 Test OnlyOffice con Host.Docker.Internal</h1>
            <p>Configurazione definitiva per Docker Desktop</p>
            <span class="status <?= OnlyOfficeConfig::isLocal() ? 'warning' : 'success' ?>">
                Ambiente: <?= OnlyOfficeConfig::isLocal() ? 'LOCALE' : 'PRODUZIONE' ?>
            </span>
        </div>

        <div class="alert info">
            <strong>ℹ️ Informazioni Importanti:</strong>
            <ul>
                <li>OnlyOffice nel container Docker DEVE usare <code>host.docker.internal</code> per raggiungere l'applicazione</li>
                <li>Il browser usa <code>localhost</code> (o il dominio in produzione)</li>
                <li>Il parametro <code>chat</code> deve essere in <code>permissions</code>, NON in <code>customization</code></li>
                <li>Verifica che il Document Server sia attivo su porta 8082 (HTTP) o 8443 (HTTPS)</li>
            </ul>
        </div>

        <div class="debug-panel">
            <div class="debug-title">📊 CONFIGURAZIONE DEBUG</div>
            <?php foreach ($debugInfo as $key => $value): ?>
            <div class="debug-item">
                <span class="debug-key"><?= str_replace('_', ' ', strtoupper($key)) ?>:</span>
                <span class="debug-value"><?= htmlspecialchars($value) ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="test-controls">
            <h3>🧪 Test di Connettività</h3>
            <button onclick="testDocumentAccess()">Test Document Access</button>
            <button onclick="testCallbackAccess()">Test Callback Access</button>
            <button onclick="testDocumentServer()">Test Document Server</button>
            <button onclick="viewConfiguration()">View Full Config</button>
            <div id="test-results" style="margin-top: 20px;"></div>
        </div>

        <div class="editor-container">
            <div id="placeholder">
                <div style="padding: 50px; text-align: center; color: #666;">
                    <h2>OnlyOffice Editor</h2>
                    <p>L'editor verrà caricato qui...</p>
                </div>
            </div>
        </div>

        <div class="debug-panel" style="margin-top: 20px;">
            <div class="debug-title">📋 CONFIGURAZIONE ONLYOFFICE</div>
            <pre id="config-display"><?= json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?></pre>
        </div>
    </div>

    <!-- Carica OnlyOffice API -->
    <script type="text/javascript" src="<?= htmlspecialchars($onlyofficeApiUrl) ?>"></script>
    
    <script type="text/javascript">
        // Configurazione globale
        const config = <?= json_encode($config, JSON_UNESCAPED_SLASHES) ?>;
        const debugInfo = <?= json_encode($debugInfo, JSON_UNESCAPED_SLASHES) ?>;
        
        console.log('=== OnlyOffice Host.Docker.Internal Test ===');
        console.log('Debug Info:', debugInfo);
        console.log('Configuration:', config);
        
        // Inizializza editor al caricamento
        window.onload = function() {
            initializeEditor();
        };
        
        function initializeEditor() {
            try {
                if (typeof DocsAPI === 'undefined') {
                    throw new Error('DocsAPI non disponibile. Verifica che il Document Server sia raggiungibile.');
                }
                
                // Inizializza l'editor
                window.docEditor = new DocsAPI.DocEditor("placeholder", config);
                console.log('✅ Editor inizializzato con successo');
                
                // Event handlers
                window.docEditor.onReady = function() {
                    console.log('✅ Editor pronto');
                    showResult('Editor caricato con successo!', 'success');
                };
                
                window.docEditor.onDocumentStateChange = function(event) {
                    console.log('📝 Stato documento:', event.data);
                };
                
                window.docEditor.onError = function(event) {
                    console.error('❌ Errore editor:', event);
                    showResult('Errore: ' + (event.data ? event.data.message : 'Errore sconosciuto'), 'error');
                };
                
            } catch (error) {
                console.error('❌ Errore inizializzazione:', error);
                showResult('Errore inizializzazione: ' + error.message, 'error');
            }
        }
        
        function testDocumentAccess() {
            showResult('Testing document access...', 'info');
            
            // Test dall'interno del browser (usa URL pubblico)
            fetch(debugInfo.document_public_url)
                .then(response => {
                    if (response.ok) {
                        showResult('✅ Document access successful (HTTP ' + response.status + ')', 'success');
                    } else {
                        showResult('❌ Document access failed (HTTP ' + response.status + ')', 'error');
                    }
                })
                .catch(error => {
                    showResult('❌ Document access error: ' + error.message, 'error');
                });
        }
        
        function testCallbackAccess() {
            showResult('Testing callback endpoint...', 'info');
            
            // Test semplice GET (non è il metodo corretto ma verifica la raggiungibilità)
            fetch(debugInfo.public_base + 'backend/api/onlyoffice-callback.php?doc=22')
                .then(response => {
                    showResult('Callback endpoint responded with HTTP ' + response.status, 
                              response.ok ? 'success' : 'warning');
                })
                .catch(error => {
                    showResult('❌ Callback access error: ' + error.message, 'error');
                });
        }
        
        function testDocumentServer() {
            showResult('Testing Document Server availability...', 'info');
            
            // Verifica che DocsAPI sia disponibile
            if (typeof DocsAPI !== 'undefined') {
                showResult('✅ Document Server API loaded successfully', 'success');
            } else {
                showResult('❌ Document Server API not available', 'error');
            }
        }
        
        function viewConfiguration() {
            const configDisplay = document.getElementById('config-display');
            configDisplay.scrollIntoView({ behavior: 'smooth' });
            showResult('Configuration displayed below', 'info');
        }
        
        function showResult(message, type) {
            const resultsDiv = document.getElementById('test-results');
            const alertClass = type === 'success' ? 'success' : 
                              type === 'error' ? 'error' : 'info';
            
            resultsDiv.innerHTML = `
                <div class="alert ${alertClass}">
                    ${message}
                </div>
            `;
        }
    </script>
</body>
</html>