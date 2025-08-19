<?php
/**
 * OnlyOffice Minimal Test - Versione ultra-semplificata
 * NO JWT, NO AUTH, solo test basico di connettività
 */

// URL diretto a OnlyOffice
$ONLYOFFICE_URL = 'http://localhost:8082';

// Test con un documento pubblico di esempio
$testDocumentUrl = 'https://api.onlyoffice.com/editors/assets/docs/samples/sample.docx';

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OnlyOffice Minimal Test</title>
    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: #f5f5f5;
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .info {
            background: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .info code {
            background: #fff;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 14px;
        }
        #editor {
            width: 100%;
            height: 600px;
            border: 2px solid #007bff;
            background: white;
            margin-top: 20px;
        }
        .status {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
        }
        .status.loading {
            background: #fff3cd;
            color: #856404;
        }
        .status.success {
            background: #d4edda;
            color: #155724;
        }
        .status.error {
            background: #f8d7da;
            color: #721c24;
        }
        button {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 5px;
        }
        button:hover {
            background: #0056b3;
        }
        .log {
            background: #000;
            color: #0f0;
            padding: 10px;
            font-family: monospace;
            font-size: 12px;
            max-height: 200px;
            overflow-y: auto;
            margin-top: 20px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <h1>🔬 OnlyOffice Minimal Test</h1>
    
    <div class="info">
        <strong>Test Configuration:</strong><br>
        OnlyOffice URL: <code><?php echo $ONLYOFFICE_URL; ?></code><br>
        Test Document: <code>Sample DOCX from OnlyOffice</code><br>
        JWT: <code>DISABLED</code><br>
        Auth: <code>DISABLED</code>
    </div>
    
    <div id="status" class="status loading">
        ⏳ Waiting to initialize...
    </div>
    
    <div>
        <button onclick="testSimple()">📝 Test Simple (No JWT)</button>
        <button onclick="testWithLocalFile()">📁 Test Local File</button>
        <button onclick="checkAPI()">🔍 Check API Status</button>
        <button onclick="location.reload()">🔄 Reload</button>
    </div>
    
    <div id="editor"></div>
    
    <div class="log" id="log"></div>
    
    <!-- OnlyOffice API -->
    <script type="text/javascript" src="<?php echo $ONLYOFFICE_URL; ?>/web-apps/apps/api/documents/api.js"></script>
    
    <script>
        function log(msg, type = 'info') {
            const logEl = document.getElementById('log');
            const time = new Date().toLocaleTimeString();
            const color = type === 'error' ? '#f00' : (type === 'success' ? '#0f0' : '#0ff');
            logEl.innerHTML += `<div style="color: ${color}">[${time}] ${msg}</div>`;
            logEl.scrollTop = logEl.scrollHeight;
            console.log(`[OnlyOffice Test] ${msg}`);
        }
        
        function updateStatus(msg, type) {
            const status = document.getElementById('status');
            status.className = 'status ' + type;
            status.innerHTML = msg;
        }
        
        // Check if API loaded
        window.onload = function() {
            if (typeof DocsAPI !== 'undefined') {
                log('✅ DocsAPI loaded successfully', 'success');
                updateStatus('✅ OnlyOffice API is ready', 'success');
            } else {
                log('❌ DocsAPI not found!', 'error');
                updateStatus('❌ OnlyOffice API failed to load', 'error');
            }
        };
        
        // Test with simple configuration (no JWT)
        function testSimple() {
            log('Starting simple test without JWT...');
            
            if (typeof DocsAPI === 'undefined') {
                alert('DocsAPI not loaded! Check if OnlyOffice is running on port 8082');
                return;
            }
            
            // Clear editor
            document.getElementById('editor').innerHTML = '';
            
            // Minimal configuration
            const config = {
                documentType: 'word',
                document: {
                    title: 'Sample Document',
                    url: '<?php echo $testDocumentUrl; ?>',
                    fileType: 'docx',
                    key: 'test_' + Date.now(),
                    permissions: {
                        edit: false,
                        download: true
                    }
                },
                editorConfig: {
                    mode: 'view',
                    lang: 'it'
                },
                type: 'embedded',
                events: {
                    onAppReady: function() {
                        log('✅ Editor ready!', 'success');
                        updateStatus('✅ Editor loaded successfully', 'success');
                    },
                    onError: function(e) {
                        log('❌ Error: ' + JSON.stringify(e.data), 'error');
                        updateStatus('❌ Error loading document', 'error');
                    }
                }
            };
            
            log('Config: ' + JSON.stringify(config, null, 2));
            
            try {
                window.docEditor = new DocsAPI.DocEditor('editor', config);
                log('Editor instance created');
            } catch (e) {
                log('Failed to create editor: ' + e.message, 'error');
            }
        }
        
        // Test with local file
        function testWithLocalFile() {
            log('Testing with local file...');
            
            if (typeof DocsAPI === 'undefined') {
                alert('DocsAPI not loaded!');
                return;
            }
            
            // Use a local file URL (must be accessible from OnlyOffice container)
            const localUrl = 'http://host.docker.internal/piattaforma-collaborativa/documents/onlyoffice/new.docx';
            
            document.getElementById('editor').innerHTML = '';
            
            const config = {
                documentType: 'word',
                document: {
                    title: 'Local Test Document',
                    url: localUrl,
                    fileType: 'docx',
                    key: 'local_' + Date.now()
                },
                editorConfig: {
                    mode: 'view',
                    lang: 'it'
                },
                type: 'embedded',
                events: {
                    onAppReady: function() {
                        log('✅ Local file loaded!', 'success');
                        updateStatus('✅ Local file opened', 'success');
                    },
                    onError: function(e) {
                        log('❌ Error with local file: ' + JSON.stringify(e.data), 'error');
                        updateStatus('❌ Could not load local file', 'error');
                        log('Note: OnlyOffice must be able to access http://host.docker.internal/', 'error');
                    }
                }
            };
            
            log('Trying local URL: ' + localUrl);
            
            try {
                window.docEditor = new DocsAPI.DocEditor('editor', config);
                log('Editor created for local file');
            } catch (e) {
                log('Failed: ' + e.message, 'error');
            }
        }
        
        // Check API status
        async function checkAPI() {
            log('Checking OnlyOffice API status...');
            
            // Check if API object exists
            if (typeof DocsAPI !== 'undefined') {
                log('✅ DocsAPI object exists', 'success');
                
                // Check version if available
                if (DocsAPI.DocEditor && DocsAPI.DocEditor.version) {
                    log('Version: ' + DocsAPI.DocEditor.version(), 'success');
                }
            } else {
                log('❌ DocsAPI not found', 'error');
            }
            
            // Try to fetch from OnlyOffice server
            try {
                const response = await fetch('<?php echo $ONLYOFFICE_URL; ?>/web-apps/apps/api/documents/api.js', {
                    method: 'HEAD',
                    mode: 'no-cors'
                });
                log('✅ OnlyOffice server is reachable', 'success');
            } catch (e) {
                log('⚠️ Could not reach OnlyOffice server (CORS)', 'error');
            }
            
            updateStatus('Check complete - see log', 'success');
        }
        
        // Auto-check on load
        setTimeout(function() {
            log('Auto-checking API status...');
            if (typeof DocsAPI === 'undefined') {
                updateStatus('⚠️ OnlyOffice API not loaded - Is Docker running?', 'error');
                log('Run: docker ps | grep onlyoffice', 'error');
                log('Expected: Container running on port 8082', 'error');
            }
        }, 1000);
    </script>
</body>
</html>