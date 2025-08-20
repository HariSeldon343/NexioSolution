<?php
/**
 * Test finale OnlyOffice HTTP:8082
 * Verifica completa configurazione e funzionamento
 */

session_start();
require_once 'backend/config/config.php';

// Configurazione HTTP:8082 (NO HTTPS!)
$DS_URL = 'http://localhost:8082';
$API_URL = $DS_URL . '/web-apps/apps/api/documents/api.js';
$INTERNAL_HOST = 'host.docker.internal';
$PLATFORM_URL = 'http://' . $INTERNAL_HOST . '/piattaforma-collaborativa';

// Test ID documento
$documentId = isset($_GET['doc_id']) ? $_GET['doc_id'] : 'test_' . time();
$documentKey = $documentId . '_' . time();

// Path documento di test
$testDocPath = __DIR__ . '/documents/onlyoffice/test_document_' . time() . '.docx';
$testDocUrl = $PLATFORM_URL . '/documents/onlyoffice/' . basename($testDocPath);

// Crea documento di test se non esiste
if (!file_exists($testDocPath)) {
    // Crea directory se non esiste
    $dir = dirname($testDocPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    
    // Copia un documento di esempio o crea uno vuoto
    $sampleDoc = __DIR__ . '/documents/test.docx';
    if (file_exists($sampleDoc)) {
        copy($sampleDoc, $testDocPath);
    } else {
        // Crea un documento minimo
        file_put_contents($testDocPath, 'Test document content');
    }
}

// Callback URL usando host.docker.internal
$callbackUrl = $PLATFORM_URL . '/backend/api/onlyoffice-callback-simple.php';

// Configurazione editor
$editorConfig = [
    'document' => [
        'fileType' => 'docx',
        'key' => $documentKey,
        'title' => 'Test Document Final HTTP',
        'url' => $testDocUrl,
        'permissions' => [
            'download' => true,
            'edit' => true,
            'print' => true
        ]
    ],
    'documentType' => 'word',
    'editorConfig' => [
        'callbackUrl' => $callbackUrl,
        'lang' => 'it',
        'mode' => 'edit',
        'user' => [
            'id' => 'test_user_' . session_id(),
            'name' => 'Test User HTTP'
        ],
        'customization' => [
            'forcesave' => true,
            'autosave' => true
        ]
    ],
    'type' => 'desktop',
    'width' => '100%',
    'height' => '600px'
];

// Test connettività
$connectivityTests = [];

// 1. Test Document Server
$ch = curl_init($DS_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$dsResponse = curl_exec($ch);
$dsHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$connectivityTests['document_server'] = [
    'url' => $DS_URL,
    'status' => $dsHttpCode == 200 || $dsHttpCode == 302,
    'http_code' => $dsHttpCode,
    'message' => $dsHttpCode == 200 || $dsHttpCode == 302 ? 'OK' : 'Errore: HTTP ' . $dsHttpCode
];

// 2. Test API JavaScript
$ch = curl_init($API_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$apiResponse = curl_exec($ch);
$apiHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$connectivityTests['api_js'] = [
    'url' => $API_URL,
    'status' => $apiHttpCode == 200,
    'http_code' => $apiHttpCode,
    'message' => $apiHttpCode == 200 ? 'OK' : 'Errore: HTTP ' . $apiHttpCode,
    'js_loaded' => strpos($apiResponse, 'DocsAPI') !== false
];

// 3. Test documento
$connectivityTests['test_document'] = [
    'path' => $testDocPath,
    'exists' => file_exists($testDocPath),
    'size' => file_exists($testDocPath) ? filesize($testDocPath) : 0,
    'url' => $testDocUrl,
    'message' => file_exists($testDocPath) ? 'OK' : 'File non trovato'
];

// 4. Test callback URL (verifica solo sintassi)
$callbackPath = $_SERVER['DOCUMENT_ROOT'] . '/piattaforma-collaborativa/backend/api/onlyoffice-callback-simple.php';
$connectivityTests['callback'] = [
    'url' => $callbackUrl,
    'file_exists' => file_exists($callbackPath),
    'message' => file_exists($callbackPath) ? 'OK' : 'File callback non trovato'
];

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Finale OnlyOffice HTTP:8082</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .status-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .status-card h3 {
            color: #555;
            margin-bottom: 10px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-indicator {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }
        
        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        
        .status-dot.success {
            background: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
        }
        
        .status-dot.error {
            background: #ef4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2);
        }
        
        .status-dot.warning {
            background: #f59e0b;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2);
        }
        
        .status-text {
            font-size: 14px;
            color: #666;
        }
        
        .url-display {
            background: #f5f5f5;
            padding: 8px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            word-break: break-all;
            margin-top: 5px;
        }
        
        .config-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .config-section h2 {
            color: #333;
            margin-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        
        .config-grid {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 10px;
            align-items: start;
        }
        
        .config-label {
            font-weight: 600;
            color: #555;
            padding-top: 5px;
        }
        
        .config-value {
            background: #f8f9fa;
            padding: 8px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            word-break: break-all;
        }
        
        .editor-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            min-height: 700px;
        }
        
        .editor-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .editor-title {
            color: #333;
            font-size: 20px;
            font-weight: 600;
        }
        
        .editor-status {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 15px;
            background: #f0f9ff;
            border-radius: 5px;
            font-size: 14px;
        }
        
        #onlyoffice-editor {
            width: 100%;
            height: 600px;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            background: #fafafa;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert.error {
            background: #fee;
            border: 1px solid #fcc;
            color: #c00;
        }
        
        .alert.success {
            background: #efe;
            border: 1px solid #cfc;
            color: #060;
        }
        
        .alert.warning {
            background: #ffeaa7;
            border: 1px solid #fdcb6e;
            color: #6c5ce7;
        }
        
        .console-output {
            background: #1e1e1e;
            color: #00ff00;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            margin-top: 20px;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .console-line {
            margin-bottom: 5px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
        }
        
        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .btn-secondary:hover {
            background: #cbd5e0;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .loading {
            animation: pulse 2s infinite;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>🧪 Test Finale OnlyOffice - HTTP:8082</h1>
            <p style="color: #666; margin-top: 5px;">
                Configurazione: HTTP (porta 8082) | host.docker.internal | <?php echo date('Y-m-d H:i:s'); ?>
            </p>
        </div>

        <!-- Status Tests -->
        <div class="status-grid">
            <?php foreach ($connectivityTests as $testName => $test): ?>
            <div class="status-card">
                <h3><?php echo str_replace('_', ' ', ucfirst($testName)); ?></h3>
                <div class="status-indicator">
                    <div class="status-dot <?php echo isset($test['status']) && $test['status'] ? 'success' : 'error'; ?>"></div>
                    <span class="status-text"><?php echo $test['message']; ?></span>
                </div>
                <?php if (isset($test['url'])): ?>
                <div class="url-display"><?php echo htmlspecialchars($test['url']); ?></div>
                <?php endif; ?>
                <?php if (isset($test['http_code'])): ?>
                <div style="margin-top: 5px; font-size: 12px; color: #999;">
                    HTTP Code: <?php echo $test['http_code']; ?>
                    <?php if (isset($test['js_loaded'])): ?>
                    | JS Loaded: <?php echo $test['js_loaded'] ? '✓' : '✗'; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Configuration Display -->
        <div class="config-section">
            <h2>📋 Configurazione Attuale</h2>
            <div class="config-grid">
                <div class="config-label">Document Server:</div>
                <div class="config-value"><?php echo $DS_URL; ?></div>
                
                <div class="config-label">API JavaScript:</div>
                <div class="config-value"><?php echo $API_URL; ?></div>
                
                <div class="config-label">Internal Host:</div>
                <div class="config-value"><?php echo $INTERNAL_HOST; ?></div>
                
                <div class="config-label">Platform URL:</div>
                <div class="config-value"><?php echo $PLATFORM_URL; ?></div>
                
                <div class="config-label">Document URL:</div>
                <div class="config-value"><?php echo $testDocUrl; ?></div>
                
                <div class="config-label">Callback URL:</div>
                <div class="config-value"><?php echo $callbackUrl; ?></div>
                
                <div class="config-label">Document Key:</div>
                <div class="config-value"><?php echo $documentKey; ?></div>
                
                <div class="config-label">Session ID:</div>
                <div class="config-value"><?php echo session_id(); ?></div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($connectivityTests['document_server']['status'] && $connectivityTests['api_js']['status']): ?>
        <div class="alert success">
            ✅ <strong>Sistema Pronto!</strong> OnlyOffice Document Server è attivo su HTTP:8082 e l'API JavaScript è caricabile.
        </div>
        <?php else: ?>
        <div class="alert error">
            ⚠️ <strong>Problemi Rilevati!</strong> Verificare la configurazione del Document Server o la connettività di rete.
        </div>
        <?php endif; ?>
        
        <?php if (!$connectivityTests['test_document']['exists']): ?>
        <div class="alert warning">
            📄 <strong>Documento di test creato:</strong> <?php echo basename($testDocPath); ?>
        </div>
        <?php endif; ?>

        <!-- Editor Container -->
        <div class="editor-container">
            <div class="editor-header">
                <div class="editor-title">📝 OnlyOffice Editor</div>
                <div class="editor-status">
                    <span id="editor-status-text" class="loading">Caricamento...</span>
                </div>
            </div>
            
            <div id="onlyoffice-editor"></div>
            
            <!-- Console Output -->
            <div class="console-output" id="console-output">
                <div class="console-line">[<?php echo date('H:i:s'); ?>] Inizializzazione test...</div>
                <div class="console-line">[<?php echo date('H:i:s'); ?>] Document Server: <?php echo $DS_URL; ?></div>
                <div class="console-line">[<?php echo date('H:i:s'); ?>] API endpoint: <?php echo $API_URL; ?></div>
                <div class="console-line">[<?php echo date('H:i:s'); ?>] Caricamento editor...</div>
            </div>
            
            <!-- Action Buttons -->
            <div class="action-buttons">
                <button class="btn btn-primary" onclick="reloadEditor()">🔄 Ricarica Editor</button>
                <button class="btn btn-secondary" onclick="window.location.reload()">🔃 Ricarica Pagina</button>
                <button class="btn btn-secondary" onclick="testConnection()">🧪 Test Connessione</button>
            </div>
        </div>
    </div>

    <!-- OnlyOffice Script -->
    <script src="<?php echo $API_URL; ?>"></script>
    
    <script>
        // Console logging helper
        function logToConsole(message, type = 'info') {
            const consoleOutput = document.getElementById('console-output');
            const time = new Date().toLocaleTimeString('it-IT');
            const line = document.createElement('div');
            line.className = 'console-line';
            line.textContent = `[${time}] ${message}`;
            
            if (type === 'error') {
                line.style.color = '#ff6b6b';
            } else if (type === 'success') {
                line.style.color = '#51cf66';
            } else if (type === 'warning') {
                line.style.color = '#ffd43b';
            }
            
            consoleOutput.appendChild(line);
            consoleOutput.scrollTop = consoleOutput.scrollHeight;
            
            // Also log to browser console
            console.log(`[OnlyOffice Test] ${message}`);
        }
        
        // Update editor status
        function updateEditorStatus(status, type = 'info') {
            const statusEl = document.getElementById('editor-status-text');
            statusEl.textContent = status;
            statusEl.className = '';
            
            if (type === 'success') {
                statusEl.style.color = '#10b981';
            } else if (type === 'error') {
                statusEl.style.color = '#ef4444';
            } else if (type === 'loading') {
                statusEl.className = 'loading';
                statusEl.style.color = '#667eea';
            } else {
                statusEl.style.color = '#666';
            }
        }
        
        // Configuration from PHP
        const editorConfig = <?php echo json_encode($editorConfig); ?>;
        
        // Add event handlers
        editorConfig.events = {
            onReady: function() {
                logToConsole('Editor pronto e funzionante!', 'success');
                updateEditorStatus('Editor Pronto', 'success');
            },
            onDocumentStateChange: function(event) {
                if (event.data) {
                    logToConsole('Documento modificato', 'warning');
                    updateEditorStatus('Documento Modificato', 'warning');
                } else {
                    logToConsole('Documento salvato', 'success');
                    updateEditorStatus('Documento Salvato', 'success');
                }
            },
            onError: function(event) {
                logToConsole('Errore: ' + event.data, 'error');
                updateEditorStatus('Errore', 'error');
                console.error('OnlyOffice Error:', event);
            },
            onWarning: function(event) {
                logToConsole('Warning: ' + event.data, 'warning');
                console.warn('OnlyOffice Warning:', event);
            },
            onInfo: function(event) {
                logToConsole('Info: ' + JSON.stringify(event.data));
                console.info('OnlyOffice Info:', event);
            },
            onRequestSaveAs: function(event) {
                logToConsole('Save As richiesto: ' + event.data.title);
            },
            onRequestClose: function() {
                logToConsole('Chiusura editor richiesta');
                if (confirm('Chiudere l\'editor?')) {
                    window.location.reload();
                }
            }
        };
        
        // Initialize editor
        let docEditor = null;
        
        function initEditor() {
            try {
                logToConsole('Inizializzazione DocsAPI...');
                updateEditorStatus('Inizializzazione...', 'loading');
                
                if (typeof DocsAPI === 'undefined') {
                    throw new Error('DocsAPI non disponibile - verificare che ' + '<?php echo $API_URL; ?>' + ' sia caricabile');
                }
                
                logToConsole('DocsAPI disponibile, creazione editor...');
                
                // Create editor instance
                docEditor = new DocsAPI.DocEditor('onlyoffice-editor', editorConfig);
                
                logToConsole('Editor creato con successo');
                
            } catch (error) {
                logToConsole('Errore inizializzazione: ' + error.message, 'error');
                updateEditorStatus('Errore Inizializzazione', 'error');
                console.error('Init Error:', error);
                
                // Show fallback message
                document.getElementById('onlyoffice-editor').innerHTML = `
                    <div style="padding: 50px; text-align: center; color: #666;">
                        <h3 style="color: #ef4444;">⚠️ Impossibile caricare l'editor</h3>
                        <p style="margin-top: 10px;">${error.message}</p>
                        <p style="margin-top: 20px; font-size: 14px;">
                            Verificare che OnlyOffice Document Server sia attivo su<br>
                            <code style="background: #f5f5f5; padding: 5px; border-radius: 3px;">
                                <?php echo $DS_URL; ?>
                            </code>
                        </p>
                    </div>
                `;
            }
        }
        
        // Reload editor
        function reloadEditor() {
            logToConsole('Ricaricamento editor...');
            updateEditorStatus('Ricaricamento...', 'loading');
            
            if (docEditor) {
                docEditor.destroyEditor();
                docEditor = null;
            }
            
            setTimeout(initEditor, 500);
        }
        
        // Test connection
        function testConnection() {
            logToConsole('Test connessione in corso...');
            updateEditorStatus('Test Connessione...', 'loading');
            
            fetch('<?php echo $DS_URL; ?>/healthcheck')
                .then(response => {
                    if (response.ok) {
                        logToConsole('Connessione OK - HTTP ' + response.status, 'success');
                        updateEditorStatus('Connessione OK', 'success');
                    } else {
                        logToConsole('Connessione fallita - HTTP ' + response.status, 'error');
                        updateEditorStatus('Connessione Fallita', 'error');
                    }
                })
                .catch(error => {
                    logToConsole('Errore connessione: ' + error.message, 'error');
                    updateEditorStatus('Errore Connessione', 'error');
                });
        }
        
        // Start initialization when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            logToConsole('DOM caricato, attesa DocsAPI...');
            
            // Check if DocsAPI is already available
            if (typeof DocsAPI !== 'undefined') {
                initEditor();
            } else {
                // Wait for DocsAPI to load
                let checkCount = 0;
                const checkInterval = setInterval(function() {
                    checkCount++;
                    
                    if (typeof DocsAPI !== 'undefined') {
                        clearInterval(checkInterval);
                        logToConsole('DocsAPI caricato dopo ' + checkCount + ' tentativi');
                        initEditor();
                    } else if (checkCount > 30) {
                        clearInterval(checkInterval);
                        logToConsole('Timeout caricamento DocsAPI dopo 30 secondi', 'error');
                        updateEditorStatus('Timeout Caricamento', 'error');
                        
                        document.getElementById('onlyoffice-editor').innerHTML = `
                            <div style="padding: 50px; text-align: center; color: #666;">
                                <h3 style="color: #ef4444;">⏱️ Timeout Caricamento</h3>
                                <p style="margin-top: 10px;">
                                    Impossibile caricare DocsAPI da<br>
                                    <code style="background: #f5f5f5; padding: 5px; border-radius: 3px;">
                                        <?php echo $API_URL; ?>
                                    </code>
                                </p>
                                <button class="btn btn-primary" onclick="window.location.reload()" style="margin-top: 20px;">
                                    Riprova
                                </button>
                            </div>
                        `;
                    } else {
                        logToConsole('Attesa DocsAPI... tentativo ' + checkCount + '/30');
                    }
                }, 1000);
            }
        });
        
        // Log initial configuration
        console.log('OnlyOffice Configuration:', editorConfig);
        console.log('Document Server URL:', '<?php echo $DS_URL; ?>');
        console.log('API URL:', '<?php echo $API_URL; ?>');
        console.log('Internal Host:', '<?php echo $INTERNAL_HOST; ?>');
    </script>
</body>
</html>