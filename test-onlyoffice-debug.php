<?php
/**
 * OnlyOffice Debug Test - Versione semplificata per debugging
 * Bypassa JWT e autenticazione per isolare il problema
 */


// Include OnlyOffice configuration
require_once __DIR__ . '/backend/config/onlyoffice.config.php';
// Configurazione diretta - nessuna dipendenza
$ONLYOFFICE_DS_URL = $ONLYOFFICE_DS_PUBLIC_URL;  // URL corretto del container Docker

// File di test - usa uno esistente
$testFile = 'documents/onlyoffice/new.docx';
$fullPath = __DIR__ . '/' . $testFile;

if (!file_exists($fullPath)) {
    die("ERROR: File di test non trovato: $fullPath");
}

// Genera URL per il documento
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

// URL diretto al file (senza API, senza JWT)
$documentUrl = $protocol . '://' . $host . $basePath . '/' . $testFile;

// Configurazione minima per OnlyOffice
$config = [
    'documentType' => 'word',
    'document' => [
        'title' => 'Test Document Debug',
        'url' => $documentUrl,
        'fileType' => 'docx',
        'key' => 'debug_' . time(), // Chiave unica per forzare reload
        'permissions' => [
            'edit' => false,  // Solo visualizzazione per test
            'download' => true,
            'print' => true
        ]
    ],
    'editorConfig' => [
        'mode' => 'view',
        'lang' => 'it',
        'user' => [
            'id' => 'test_user',
            'name' => 'Test User'
        ]
    ],
    'type' => 'embedded',
    'width' => '100%',
    'height' => '100%'
];

// Log di debug
error_log("ONLYOFFICE DEBUG - Document URL: " . $documentUrl);
error_log("ONLYOFFICE DEBUG - Config: " . json_encode($config, JSON_PRETTY_PRINT));
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OnlyOffice Debug Test</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }
        .debug-info {
            background: #f0f0f0;
            padding: 20px;
            border-bottom: 2px solid #333;
        }
        .debug-info h1 {
            margin: 0 0 10px 0;
            color: #333;
        }
        .debug-info pre {
            background: white;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow-x: auto;
            max-height: 200px;
            overflow-y: auto;
        }
        .status {
            margin: 10px 0;
            padding: 10px;
            border-radius: 4px;
        }
        .status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .status.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        #editor-container {
            width: 100%;
            height: 600px;
            border: 2px solid #007bff;
        }
        .loading {
            text-align: center;
            padding: 50px;
            font-size: 18px;
            color: #666;
        }
        .controls {
            margin: 10px 0;
        }
        .controls button {
            padding: 10px 20px;
            margin-right: 10px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .controls button:hover {
            background: #0056b3;
        }
        .logs {
            background: #000;
            color: #0f0;
            padding: 10px;
            font-family: monospace;
            font-size: 12px;
            max-height: 300px;
            overflow-y: auto;
            margin-top: 10px;
        }
        .log-entry {
            margin: 2px 0;
        }
        .log-entry.error { color: #f00; }
        .log-entry.warning { color: #ff0; }
        .log-entry.info { color: #0ff; }
    </style>
</head>
<body>
    <div class="debug-info">
        <h1>🔧 OnlyOffice Debug Test</h1>
        
        <!-- Status checks -->
        <div class="status" id="file-status">
            Checking file...
        </div>
        
        <div class="status" id="url-status">
            Checking URL accessibility...
        </div>
        
        <div class="status" id="onlyoffice-status">
            Checking OnlyOffice server...
        </div>
        
        <!-- Configuration display -->
        <details>
            <summary><strong>📋 Configuration</strong></summary>
            <pre><?php echo json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?></pre>
        </details>
        
        <!-- URLs -->
        <details>
            <summary><strong>🔗 URLs</strong></summary>
            <pre>
Document URL: <?php echo $documentUrl; ?>

OnlyOffice API: <?php echo $ONLYOFFICE_DS_URL; ?>/web-apps/apps/api/documents/api.js
Test direct access: <a href="<?php echo $documentUrl; ?>" target="_blank"><?php echo $documentUrl; ?></a>
            </pre>
        </details>
        
        <!-- Controls -->
        <div class="controls">
            <button onclick="initEditor()">🚀 Initialize Editor</button>
            <button onclick="testDocumentUrl()">🔍 Test Document URL</button>
            <button onclick="testOnlyOfficeApi()">🔍 Test OnlyOffice API</button>
            <button onclick="clearLogs()">🗑️ Clear Logs</button>
            <button onclick="location.reload()">🔄 Reload Page</button>
        </div>
        
        <!-- Console logs -->
        <div class="logs" id="console-logs">
            <div class="log-entry info">[<?php echo date('H:i:s'); ?>] Page loaded</div>
        </div>
    </div>
    
    <!-- Editor container -->
    <div id="editor-container">
        <div class="loading">
            ⏳ Editor not initialized. Click "Initialize Editor" to start.
        </div>
    </div>
    
    <!-- OnlyOffice API Script -->
    <script type="text/javascript" src="<?php echo $ONLYOFFICE_DS_URL; ?>/web-apps/apps/api/documents/api.js" 
            onerror="onScriptError()" 
            onload="onScriptLoaded()"></script>
    
    <script>
        // Configuration from PHP
        const config = <?php echo json_encode($config); ?>;
        const documentUrl = <?php echo json_encode($documentUrl); ?>;
        const onlyofficeUrl = <?php echo json_encode($ONLYOFFICE_DS_URL); ?>;
        
        // Logging
        function addLog(message, type = 'info') {
            const time = new Date().toLocaleTimeString();
            const logContainer = document.getElementById('console-logs');
            const entry = document.createElement('div');
            entry.className = `log-entry ${type}`;
            entry.textContent = `[${time}] ${message}`;
            logContainer.appendChild(entry);
            logContainer.scrollTop = logContainer.scrollHeight;
            
            // Also log to browser console
            console.log(`[ONLYOFFICE DEBUG] ${message}`);
        }
        
        function clearLogs() {
            document.getElementById('console-logs').innerHTML = '';
            addLog('Logs cleared');
        }
        
        // Status updates
        function updateStatus(elementId, message, type) {
            const element = document.getElementById(elementId);
            element.className = `status ${type}`;
            element.textContent = message;
        }
        
        // Script loading handlers
        function onScriptLoaded() {
            addLog('OnlyOffice API script loaded successfully', 'info');
            updateStatus('onlyoffice-status', '✅ OnlyOffice API loaded', 'success');
            
            // Check if DocsAPI is available
            if (typeof DocsAPI !== 'undefined') {
                addLog('DocsAPI object is available', 'info');
            } else {
                addLog('WARNING: DocsAPI object not found!', 'error');
                updateStatus('onlyoffice-status', '❌ DocsAPI not available', 'error');
            }
        }
        
        function onScriptError() {
            addLog('ERROR: Failed to load OnlyOffice API script', 'error');
            updateStatus('onlyoffice-status', '❌ Failed to load OnlyOffice API', 'error');
        }
        
        // Test document URL accessibility
        async function testDocumentUrl() {
            addLog('Testing document URL accessibility...', 'info');
            
            try {
                const response = await fetch(documentUrl, { method: 'HEAD' });
                if (response.ok) {
                    addLog(`Document URL is accessible (Status: ${response.status})`, 'info');
                    updateStatus('url-status', '✅ Document URL is accessible', 'success');
                } else {
                    addLog(`Document URL returned status: ${response.status}`, 'error');
                    updateStatus('url-status', `❌ Document URL error: ${response.status}`, 'error');
                }
            } catch (error) {
                addLog(`Failed to test document URL: ${error.message}`, 'error');
                updateStatus('url-status', '❌ Cannot access document URL', 'error');
            }
        }
        
        // Test OnlyOffice API endpoint
        async function testOnlyOfficeApi() {
            addLog('Testing OnlyOffice API endpoint...', 'info');
            
            try {
                const response = await fetch(onlyofficeUrl + '/web-apps/apps/api/documents/api.js', { 
                    method: 'HEAD',
                    mode: 'no-cors' // Try without CORS for testing
                });
                addLog('OnlyOffice API endpoint reachable', 'info');
                updateStatus('onlyoffice-status', '✅ OnlyOffice server reachable', 'success');
            } catch (error) {
                addLog(`OnlyOffice API test failed: ${error.message}`, 'warning');
                updateStatus('onlyoffice-status', '⚠️ OnlyOffice server check failed (may still work)', 'warning');
            }
        }
        
        // Initialize editor
        function initEditor() {
            addLog('Initializing OnlyOffice editor...', 'info');
            
            // Check if DocsAPI is available
            if (typeof DocsAPI === 'undefined') {
                addLog('ERROR: DocsAPI is not defined. OnlyOffice script may not be loaded.', 'error');
                alert('OnlyOffice API not loaded. Please check that OnlyOffice Document Server is running.');
                return;
            }
            
            try {
                // Clear the container
                document.getElementById('editor-container').innerHTML = '';
                
                // Add event handlers to config
                const editorConfig = {
                    ...config,
                    events: {
                        onAppReady: function() {
                            addLog('✅ Editor is ready!', 'info');
                        },
                        onDocumentStateChange: function(event) {
                            addLog(`Document state changed: ${JSON.stringify(event.data)}`, 'info');
                        },
                        onError: function(event) {
                            addLog(`ERROR: ${JSON.stringify(event.data)}`, 'error');
                            console.error('OnlyOffice Error:', event);
                        },
                        onWarning: function(event) {
                            addLog(`WARNING: ${JSON.stringify(event.data)}`, 'warning');
                            console.warn('OnlyOffice Warning:', event);
                        },
                        onInfo: function(event) {
                            addLog(`Info: ${JSON.stringify(event.data)}`, 'info');
                        },
                        onRequestOpen: function(event) {
                            addLog(`Request open: ${JSON.stringify(event.data)}`, 'info');
                        }
                    }
                };
                
                addLog('Creating editor with config: ' + JSON.stringify(editorConfig, null, 2), 'info');
                
                // Create editor instance
                window.docEditor = new DocsAPI.DocEditor('editor-container', editorConfig);
                
                addLog('Editor instance created', 'info');
                
            } catch (error) {
                addLog(`Failed to initialize editor: ${error.message}`, 'error');
                console.error('Init error:', error);
            }
        }
        
        // Auto-run tests on page load
        window.addEventListener('load', async function() {
            addLog('Running automatic tests...', 'info');
            
            // Check file
            <?php if (file_exists($fullPath)): ?>
                updateStatus('file-status', '✅ File exists: <?php echo $testFile; ?>', 'success');
                addLog('File check passed', 'info');
            <?php else: ?>
                updateStatus('file-status', '❌ File not found', 'error');
                addLog('File not found!', 'error');
            <?php endif; ?>
            
            // Test URLs
            await testDocumentUrl();
            
            // Note about OnlyOffice
            addLog('NOTE: If OnlyOffice Document Server is running in Docker, make sure:', 'warning');
            addLog('  1. Container is running: docker ps', 'warning');
            addLog('  2. Port 8443 is accessible: https://localhost:8443', 'warning');
            addLog('  3. No firewall blocking connections', 'warning');
        });
    </script>
</body>
</html>