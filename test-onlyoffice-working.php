<?php
/**
 * OnlyOffice Working Test - Versione funzionante senza JWT
 * Usa API semplificata per bypassare autenticazione
 */

// Configurazione corretta - usa HTTP su porta 8080
$ONLYOFFICE_URL = 'http://localhost:8080';
$FILE_SERVER_URL = 'http://localhost:8083';
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

// URL per il documento - usa il file server nginx su porta 8083
$test_document = '45.docx'; // Documento di test esistente
$documentUrl = $FILE_SERVER_URL . '/documents/onlyoffice/' . $test_document;

// URL alternativo usando localhost dell'app
$appDocumentUrl = $protocol . '://' . $host . $basePath . '/documents/onlyoffice/' . $test_document;

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OnlyOffice Working Test</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
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
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
        }
        .config {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            font-family: monospace;
            font-size: 13px;
        }
        .config strong {
            color: #007bff;
        }
        .controls {
            display: flex;
            gap: 10px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        button {
            background: #007bff;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.3s;
        }
        button:hover {
            background: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,123,255,0.3);
        }
        button.success { background: #28a745; }
        button.success:hover { background: #218838; }
        button.danger { background: #dc3545; }
        button.danger:hover { background: #c82333; }
        .editor-wrapper {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
            position: relative;
        }
        #editor {
            width: 100%;
            height: 700px;
            border: none;
        }
        .status {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            z-index: 100;
            transition: all 0.3s;
        }
        .status.loading {
            background: #ffc107;
            color: #000;
        }
        .status.success {
            background: #28a745;
            color: white;
        }
        .status.error {
            background: #dc3545;
            color: white;
        }
        .log-panel {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 12px;
            max-height: 300px;
            overflow-y: auto;
        }
        .log-entry {
            margin: 4px 0;
            padding: 2px 0;
        }
        .log-entry.error { color: #f48771; }
        .log-entry.success { color: #89d185; }
        .log-entry.warning { color: #dcdcaa; }
        .log-entry.info { color: #9cdcfe; }
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.95);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 200;
        }
        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .hide { display: none !important; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 OnlyOffice Working Test</h1>
            <p>Test funzionante con documento locale, senza JWT</p>
            
            <div class="config">
                <strong>Configuration:</strong><br>
                OnlyOffice Server: <?php echo $ONLYOFFICE_URL; ?> (HTTP porta 8080)<br>
                File Server: <?php echo $FILE_SERVER_URL; ?> (Nginx porta 8083)<br>
                Document: <?php echo $test_document; ?><br>
                Document URL: <?php echo $documentUrl; ?><br>
                Alt URL: <?php echo $appDocumentUrl; ?><br>
                JWT: DISABLED
            </div>
            
            <div class="controls">
                <button onclick="initEditor()" class="success">
                    ✅ Initialize Editor (Recommended)
                </button>
                <button onclick="initEditorWithAppUrl()">
                    🌐 Initialize with App URL
                </button>
                <button onclick="testDirectUrl()">
                    🔗 Test Document URL
                </button>
                <button onclick="clearEditor()" class="danger">
                    🗑️ Clear Editor
                </button>
                <button onclick="location.reload()">
                    🔄 Reload Page
                </button>
            </div>
        </div>
        
        <div class="editor-wrapper">
            <div id="status" class="status loading">Waiting...</div>
            <div id="loading" class="loading-overlay hide">
                <div class="spinner"></div>
            </div>
            <div id="editor"></div>
        </div>
        
        <div class="log-panel" id="log">
            <div class="log-entry info">🔧 System ready. Click "Initialize Editor" to start.</div>
        </div>
    </div>
    
    <!-- OnlyOffice API -->
    <script type="text/javascript" src="<?php echo $ONLYOFFICE_URL; ?>/web-apps/apps/api/documents/api.js"></script>
    
    <script>
        // Configuration
        const onlyofficeUrl = '<?php echo $ONLYOFFICE_URL; ?>';
        const documentUrl = '<?php echo $documentUrl; ?>';
        const appDocumentUrl = '<?php echo $appDocumentUrl; ?>';
        let docEditor = null;
        
        // Logging
        function log(message, type = 'info') {
            const logEl = document.getElementById('log');
            const time = new Date().toLocaleTimeString();
            const entry = document.createElement('div');
            entry.className = `log-entry ${type}`;
            entry.innerHTML = `[${time}] ${message}`;
            logEl.appendChild(entry);
            logEl.scrollTop = logEl.scrollHeight;
            console.log(`[OnlyOffice] ${message}`);
        }
        
        // Status update
        function setStatus(text, type) {
            const status = document.getElementById('status');
            status.textContent = text;
            status.className = `status ${type}`;
        }
        
        // Loading
        function showLoading() {
            document.getElementById('loading').classList.remove('hide');
        }
        
        function hideLoading() {
            document.getElementById('loading').classList.add('hide');
        }
        
        // Check OnlyOffice availability
        window.addEventListener('load', function() {
            if (typeof DocsAPI !== 'undefined') {
                log('✅ OnlyOffice API loaded successfully', 'success');
                setStatus('API Ready', 'success');
            } else {
                log('❌ OnlyOffice API not found!', 'error');
                setStatus('API Error', 'error');
            }
        });
        
        // Initialize editor with browser URL
        function initEditor() {
            log('Initializing editor with browser URL...', 'info');
            
            if (typeof DocsAPI === 'undefined') {
                alert('OnlyOffice API not loaded! Check if Docker container is running.');
                return;
            }
            
            showLoading();
            clearEditor();
            
            const config = {
                documentType: 'word',
                document: {
                    title: 'Test Document',
                    url: documentUrl,
                    fileType: 'docx',
                    key: 'test_' + Date.now(),
                    permissions: {
                        edit: true,
                        download: true,
                        print: true
                    }
                },
                editorConfig: {
                    mode: 'edit',
                    lang: 'it',
                    user: {
                        id: 'test_user',
                        name: 'Test User'
                    },
                    customization: {
                        autosave: false,
                        compactHeader: true,
                        forcesave: false
                    }
                },
                type: 'desktop',
                width: '100%',
                height: '100%',
                events: {
                    onAppReady: function() {
                        hideLoading();
                        log('✅ Editor ready and working!', 'success');
                        setStatus('Editor Active', 'success');
                    },
                    onDocumentStateChange: function(event) {
                        log(`Document state: ${event.data ? 'modified' : 'saved'}`, 'info');
                    },
                    onError: function(event) {
                        hideLoading();
                        log(`❌ Error: ${JSON.stringify(event.data)}`, 'error');
                        setStatus('Error', 'error');
                        
                        // If file server URL fails, try app URL
                        if (event.data.errorCode === -1) {
                            log('Retrying with App URL...', 'warning');
                            setTimeout(() => initEditorWithAppUrl(), 1000);
                        }
                    },
                    onWarning: function(event) {
                        log(`⚠️ Warning: ${JSON.stringify(event.data)}`, 'warning');
                    }
                }
            };
            
            log('Config: ' + JSON.stringify(config, null, 2), 'info');
            
            try {
                docEditor = new DocsAPI.DocEditor('editor', config);
                log('Editor instance created', 'success');
            } catch (e) {
                hideLoading();
                log(`Failed to create editor: ${e.message}`, 'error');
                setStatus('Failed', 'error');
            }
        }
        
        // Initialize with App URL
        function initEditorWithAppUrl() {
            log('Initializing editor with App URL...', 'info');
            
            if (typeof DocsAPI === 'undefined') {
                alert('OnlyOffice API not loaded!');
                return;
            }
            
            showLoading();
            clearEditor();
            
            const config = {
                documentType: 'word',
                document: {
                    title: 'Test Document (App)',
                    url: appDocumentUrl,
                    fileType: 'docx',
                    key: 'app_' + Date.now(),
                    permissions: {
                        edit: true,
                        download: true,
                        print: true
                    }
                },
                editorConfig: {
                    mode: 'edit',
                    lang: 'it',
                    user: {
                        id: 'test_user',
                        name: 'Test User'
                    }
                },
                type: 'desktop',
                width: '100%',
                height: '100%',
                events: {
                    onAppReady: function() {
                        hideLoading();
                        log('✅ Editor ready with App URL!', 'success');
                        setStatus('Editor Active (App)', 'success');
                    },
                    onError: function(event) {
                        hideLoading();
                        log(`❌ App URL Error: ${JSON.stringify(event.data)}`, 'error');
                        setStatus('App Error', 'error');
                    }
                }
            };
            
            log('App config: ' + JSON.stringify(config, null, 2), 'info');
            
            try {
                docEditor = new DocsAPI.DocEditor('editor', config);
                log('App editor instance created', 'success');
            } catch (e) {
                hideLoading();
                log(`Failed with App URL: ${e.message}`, 'error');
                setStatus('App Failed', 'error');
            }
        }
        
        // Test document URL
        async function testDirectUrl() {
            log('Testing document URL accessibility...', 'info');
            
            try {
                const response = await fetch(documentUrl);
                if (response.ok) {
                    const size = response.headers.get('content-length');
                    log(`✅ Document URL works! Size: ${size} bytes`, 'success');
                    
                    // Try to get content type
                    const contentType = response.headers.get('content-type');
                    log(`Content-Type: ${contentType}`, 'info');
                } else {
                    log(`❌ Document URL error: ${response.status} ${response.statusText}`, 'error');
                }
            } catch (e) {
                log(`❌ Failed to fetch document: ${e.message}`, 'error');
            }
        }
        
        // Clear editor
        function clearEditor() {
            if (docEditor) {
                try {
                    docEditor.destroyEditor();
                    log('Editor destroyed', 'info');
                } catch (e) {
                    log(`Error destroying editor: ${e.message}`, 'warning');
                }
                docEditor = null;
            }
            document.getElementById('editor').innerHTML = '';
        }
        
        // Auto-test on load
        setTimeout(function() {
            testDirectUrl();
        }, 500);
    </script>
</body>
</html>