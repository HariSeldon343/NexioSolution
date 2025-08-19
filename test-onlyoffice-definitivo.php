<?php
/**
 * Test OnlyOffice DEFINITIVO - Versione Corretta e Funzionante
 * 
 * CORREZIONI APPLICATE:
 * 1. URL api.js hardcoded completo: http://localhost:8082/web-apps/apps/api/documents/api.js
 * 2. Parametro "chat" spostato da customization a permissions
 * 3. URLs con host.docker.internal per comunicazione container->host
 * 4. JWT disabilitato per test iniziale
 */

session_start();

// Include OnlyOffice configuration
require_once __DIR__ . '/backend/config/onlyoffice.config.php';

// Generate unique test token
$_SESSION['onlyoffice_test_token'] = bin2hex(random_bytes(16));

// Document configuration
$docId = 22;  // Test document ID
$docKey = 'test_definitivo_' . time() . '_' . rand(1000, 9999);

// URLs for container->host communication
$documentUrl = "http://host.docker.internal/piattaforma-collaborativa/backend/api/onlyoffice-document-public.php?doc=" . $docId;
$callbackUrl = "http://host.docker.internal/piattaforma-collaborativa/backend/api/onlyoffice-callback.php?doc=" . $docId;

// OnlyOffice server URL (hardcoded for clarity)
$onlyofficeServerUrl = "http://localhost:8080";

// Complete configuration with correct structure
$config = [
    "type" => "desktop",
    "documentType" => "word",
    "document" => [
        "title" => "Test Document DEFINITIVO",
        "url" => $documentUrl,
        "fileType" => "docx",
        "key" => $docKey,
        "permissions" => [
            "comment" => true,
            "download" => true,
            "edit" => true,
            "fillForms" => true,
            "modifyFilter" => true,
            "modifyContentControl" => true,
            "review" => true,
            "chat" => false,  // CORRECTLY placed in permissions, NOT in customization
            "print" => true
        ]
    ],
    "editorConfig" => [
        "mode" => "edit",
        "lang" => "it",
        "callbackUrl" => $callbackUrl,
        "user" => [
            "id" => "test_user_" . session_id(),
            "name" => "Test User Definitivo"
        ],
        "customization" => [
            "autosave" => true,
            // NO "chat" here - it's deprecated in customization
            "comments" => true,
            "compactHeader" => false,
            "compactToolbar" => false,
            "feedback" => false,
            "forcesave" => false,
            "help" => true,
            "hideRightMenu" => false,
            "toolbarNoTabs" => false,
            "logo" => [
                "url" => "http://localhost/piattaforma-collaborativa/assets/images/nexio-logo.svg",
                "imageEmbedded" => false
            ]
        ]
    ],
    "events" => [
        "onReady" => "onDocumentReady",
        "onDocumentStateChange" => "onDocumentStateChange",
        "onError" => "onError",
        "onWarning" => "onWarning",
        "onInfo" => "onInfo",
        "onRequestSaveAs" => "onRequestSaveAs",
        "onDownloadAs" => "onDownloadAs"
    ]
];

// Debug information
$debugInfo = [
    'document_url' => $documentUrl,
    'callback_url' => $callbackUrl,
    'onlyoffice_server' => $onlyofficeServerUrl,
    'api_js_url' => $onlyofficeServerUrl . '/web-apps/apps/api/documents/api.js',
    'document_key' => $docKey,
    'session_id' => session_id(),
    'jwt_enabled' => $ONLYOFFICE_JWT_ENABLED ? 'YES' : 'NO'
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OnlyOffice Test DEFINITIVO - Versione Corretta</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        h1 {
            color: white;
            text-align: center;
            margin-bottom: 30px;
            font-size: 2rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        
        .debug-panel {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .debug-panel h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.5rem;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        
        .debug-grid {
            display: grid;
            gap: 15px;
        }
        
        .debug-item {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 20px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .debug-label {
            font-weight: 600;
            color: #555;
        }
        
        .debug-value {
            font-family: 'Courier New', monospace;
            color: #333;
            word-break: break-all;
            background: white;
            padding: 8px;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .controls {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }
        
        .btn-info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: #333;
        }
        
        .status-container {
            max-height: 200px;
            overflow-y: auto;
            margin-bottom: 20px;
        }
        
        .status {
            padding: 12px 20px;
            margin: 10px 0;
            border-radius: 8px;
            font-weight: 500;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(-20px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .status.success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .status.error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .status.info {
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }
        
        .status.warning {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeeba 100%);
            color: #856404;
            border-left: 4px solid #ffc107;
        }
        
        .editor-wrapper {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            min-height: 700px;
            position: relative;
        }
        
        #editor {
            width: 100%;
            height: 700px;
            border: none;
        }
        
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.95);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .loading-text {
            margin-top: 20px;
            color: #667eea;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .config-display {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .config-display pre {
            margin: 0;
            font-size: 0.85rem;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 OnlyOffice Test DEFINITIVO - Versione Corretta</h1>
        
        <div class="debug-panel">
            <h2>📊 Informazioni di Debug</h2>
            <div class="debug-grid">
                <?php foreach ($debugInfo as $key => $value): ?>
                <div class="debug-item">
                    <div class="debug-label"><?php echo ucfirst(str_replace('_', ' ', $key)); ?>:</div>
                    <div class="debug-value"><?php echo htmlspecialchars($value); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="controls">
            <button class="btn btn-success" onclick="testConnection()">
                🔌 Test Connessione
            </button>
            <button class="btn btn-info" onclick="testDocumentAccess()">
                📄 Test Accesso Documento
            </button>
            <button class="btn btn-primary" onclick="loadEditor()">
                ✏️ Carica Editor
            </button>
            <button class="btn btn-warning" onclick="showConfig()">
                ⚙️ Mostra Config
            </button>
            <button class="btn btn-info" onclick="location.reload()">
                🔄 Ricarica
            </button>
        </div>
        
        <div id="status-container" class="status-container"></div>
        
        <div class="editor-wrapper">
            <div id="loading-overlay" class="loading-overlay" style="display: none;">
                <div class="spinner"></div>
                <div class="loading-text">Caricamento OnlyOffice...</div>
            </div>
            <div id="editor"></div>
        </div>
        
        <div id="config-display" class="debug-panel" style="display: none;">
            <h2>⚙️ Configurazione OnlyOffice</h2>
            <div class="config-display">
                <pre><?php echo json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?></pre>
            </div>
        </div>
    </div>

    <!-- CRITICAL: Full URL hardcoded, NOT using PHP variable -->
    <script type="text/javascript" src="http://localhost:8080/web-apps/apps/api/documents/api.js"></script>
    
    <script>
        // Configuration from PHP
        const config = <?php echo json_encode($config, JSON_PRETTY_PRINT); ?>;
        let docEditor = null;
        
        // Status message function
        function showStatus(message, type = 'info') {
            const container = document.getElementById('status-container');
            const status = document.createElement('div');
            status.className = `status ${type}`;
            const timestamp = new Date().toLocaleTimeString();
            status.innerHTML = `<strong>${timestamp}</strong> - ${message}`;
            container.appendChild(status);
            
            // Auto-scroll to latest
            container.scrollTop = container.scrollHeight;
            
            // Auto-remove after 30 seconds
            setTimeout(() => {
                status.style.opacity = '0';
                setTimeout(() => status.remove(), 300);
            }, 30000);
        }
        
        // Test connection to OnlyOffice
        function testConnection() {
            showStatus('🔌 Testing connection to OnlyOffice server...', 'info');
            
            // Test if DocsAPI is loaded
            if (typeof DocsAPI === 'undefined') {
                showStatus('❌ DocsAPI not loaded! Check if OnlyOffice is running on port 8082', 'error');
                return;
            }
            
            showStatus('✅ DocsAPI loaded successfully!', 'success');
            
            // Test document URL access
            fetch('<?php echo $documentUrl; ?>', { method: 'HEAD' })
                .then(response => {
                    if (response.ok) {
                        showStatus('✅ Document URL accessible!', 'success');
                    } else {
                        showStatus(`⚠️ Document URL returned status: ${response.status}`, 'warning');
                    }
                })
                .catch(error => {
                    showStatus(`❌ Cannot access document URL: ${error.message}`, 'error');
                });
        }
        
        // Test document access
        function testDocumentAccess() {
            showStatus('📄 Testing document access...', 'info');
            
            // Test document URL
            fetch('<?php echo $documentUrl; ?>')
                .then(response => {
                    if (response.ok) {
                        return response.blob();
                    }
                    throw new Error(`HTTP ${response.status}`);
                })
                .then(blob => {
                    const size = (blob.size / 1024).toFixed(2);
                    showStatus(`✅ Document loaded successfully! Size: ${size} KB`, 'success');
                })
                .catch(error => {
                    showStatus(`❌ Document access failed: ${error.message}`, 'error');
                });
        }
        
        // Load OnlyOffice editor
        function loadEditor() {
            showStatus('🚀 Initializing OnlyOffice editor...', 'info');
            
            // Check if API is loaded
            if (typeof DocsAPI === 'undefined') {
                showStatus('❌ DocsAPI is not defined! OnlyOffice script not loaded.', 'error');
                showStatus('📍 Expected script URL: http://localhost:8080/web-apps/apps/api/documents/api.js', 'error');
                return;
            }
            
            // Show loading overlay
            document.getElementById('loading-overlay').style.display = 'flex';
            
            try {
                // Destroy existing editor if present
                if (docEditor) {
                    showStatus('🔄 Destroying existing editor instance...', 'info');
                    docEditor.destroyEditor();
                    docEditor = null;
                }
                
                // Event handlers
                window.onDocumentReady = function() {
                    showStatus('✅ Editor ready! You can now edit the document.', 'success');
                    document.getElementById('loading-overlay').style.display = 'none';
                    console.log('OnlyOffice Editor Ready');
                };
                
                window.onDocumentStateChange = function(event) {
                    if (event.data) {
                        showStatus('📝 Document modified', 'info');
                    }
                };
                
                window.onError = function(event) {
                    document.getElementById('loading-overlay').style.display = 'none';
                    console.error('OnlyOffice Error:', event);
                    showStatus(`❌ OnlyOffice Error: ${JSON.stringify(event.data)}`, 'error');
                    
                    // Detailed error analysis
                    if (event.data && event.data.errorCode) {
                        let errorDetails = '';
                        switch(event.data.errorCode) {
                            case -1: errorDetails = 'Unknown error occurred'; break;
                            case -2: errorDetails = 'Callback URL error'; break;
                            case -3: errorDetails = 'Internal server error'; break;
                            case -4: errorDetails = 'Cannot download document - Check URLs and network'; break;
                            case -5: errorDetails = 'Unsupported document format'; break;
                            case -6: errorDetails = 'Invalid document key'; break;
                            case -7: errorDetails = 'Error converting document'; break;
                            case -8: errorDetails = 'Token validation failed'; break;
                            case -20: errorDetails = 'Too many connections'; break;
                            case -21: errorDetails = 'Password required'; break;
                            case -22: errorDetails = 'Database error'; break;
                            case -23: errorDetails = 'Expired session'; break;
                            case -24: errorDetails = 'User not found'; break;
                            case -25: errorDetails = 'Access denied'; break;
                            default: errorDetails = `Unknown error code: ${event.data.errorCode}`;
                        }
                        showStatus(`🔍 Error details: ${errorDetails}`, 'error');
                    }
                };
                
                window.onWarning = function(event) {
                    showStatus(`⚠️ Warning: ${JSON.stringify(event.data)}`, 'warning');
                };
                
                window.onInfo = function(event) {
                    console.log('OnlyOffice Info:', event.data);
                };
                
                window.onRequestSaveAs = function(event) {
                    console.log('Save As requested:', event.data);
                };
                
                window.onDownloadAs = function(event) {
                    console.log('Download As requested:', event.data);
                };
                
                // Initialize the editor
                showStatus('📝 Creating DocEditor instance...', 'info');
                docEditor = new DocsAPI.DocEditor("editor", config);
                showStatus('✨ Editor initialization started!', 'success');
                
            } catch (error) {
                document.getElementById('loading-overlay').style.display = 'none';
                showStatus(`❌ Failed to initialize editor: ${error.message}`, 'error');
                console.error('Editor initialization error:', error);
                console.error('Stack trace:', error.stack);
            }
        }
        
        // Show configuration
        function showConfig() {
            const configDisplay = document.getElementById('config-display');
            if (configDisplay.style.display === 'none') {
                configDisplay.style.display = 'block';
                showStatus('⚙️ Configuration displayed', 'info');
            } else {
                configDisplay.style.display = 'none';
            }
        }
        
        // Auto-load editor after 2 seconds
        window.addEventListener('load', function() {
            showStatus('🔍 Page loaded, checking OnlyOffice availability...', 'info');
            
            // Check if DocsAPI is available
            setTimeout(() => {
                if (typeof DocsAPI !== 'undefined') {
                    showStatus('✅ OnlyOffice API detected!', 'success');
                    setTimeout(loadEditor, 1000);
                } else {
                    showStatus('❌ OnlyOffice API not found. Is the server running on port 8080?', 'error');
                    showStatus('💡 Try: docker ps to check if container is running', 'warning');
                }
            }, 2000);
        });
        
        // Debug: Log configuration
        console.log('OnlyOffice Configuration:', config);
        console.log('OnlyOffice Server URL: http://localhost:8080');
        console.log('Document URL:', '<?php echo $documentUrl; ?>');
        console.log('Callback URL:', '<?php echo $callbackUrl; ?>');
    </script>
</body>
</html>