<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test OnlyOffice - Docker Desktop Windows</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: #2c3e50;
            color: white;
            padding: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .critical-info {
            background: #e74c3c;
            color: white;
            padding: 20px;
            font-weight: bold;
        }
        .critical-info h2 {
            margin: 0 0 10px 0;
        }
        .url-comparison {
            background: #f8f9fa;
            padding: 20px;
            margin: 20px;
            border-radius: 10px;
        }
        .url-comparison table {
            width: 100%;
            border-collapse: collapse;
        }
        .url-comparison th {
            background: #3498db;
            color: white;
            padding: 10px;
            text-align: left;
        }
        .url-comparison td {
            padding: 10px;
            border-bottom: 1px solid #dee2e6;
            font-family: 'Courier New', monospace;
            font-size: 12px;
        }
        .url-comparison .correct {
            background: #d4edda;
        }
        .url-comparison .wrong {
            background: #f8d7da;
        }
        .test-section {
            padding: 20px;
        }
        .test-button {
            background: #27ae60;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
        }
        .test-button:hover {
            background: #229954;
        }
        .log {
            background: #2c3e50;
            color: #2ecc71;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            margin: 20px;
            border-radius: 5px;
            max-height: 300px;
            overflow-y: auto;
        }
        .log-entry {
            margin: 5px 0;
        }
        .log-entry.error {
            color: #e74c3c;
        }
        .log-entry.success {
            color: #2ecc71;
        }
        .log-entry.warning {
            color: #f39c12;
        }
        #editor-container {
            height: 600px;
            margin: 20px;
            border: 2px solid #3498db;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🐳 Test OnlyOffice - Docker Desktop per Windows</h1>
        </div>
        
        <div class="critical-info">
            <h2>⚠️ CONFIGURAZIONE CRITICA PER DOCKER DESKTOP</h2>
            <p>Su Docker Desktop per Windows, il container OnlyOffice DEVE usare <strong>host.docker.internal</strong> per comunicare con l'host.</p>
            <p>Dal punto di vista del container, "localhost" punta al container stesso, NON all'host Windows!</p>
        </div>
        
        <div class="url-comparison">
            <h3>📋 Confronto URL - Browser vs Container</h3>
            <table>
                <thead>
                    <tr>
                        <th>Contesto</th>
                        <th>URL Tipo</th>
                        <th>❌ ERRATO</th>
                        <th>✅ CORRETTO</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Browser</strong></td>
                        <td>Script API</td>
                        <td class="wrong">-</td>
                        <td class="correct">http://localhost:8082/web-apps/apps/api/documents/api.js</td>
                    </tr>
                    <tr>
                        <td><strong>OnlyOffice Container</strong></td>
                        <td>Document URL</td>
                        <td class="wrong">http://localhost/piattaforma-collaborativa/documents/...</td>
                        <td class="correct">http://host.docker.internal/piattaforma-collaborativa/documents/...</td>
                    </tr>
                    <tr>
                        <td><strong>OnlyOffice Container</strong></td>
                        <td>Callback URL</td>
                        <td class="wrong">http://localhost/piattaforma-collaborativa/backend/api/...</td>
                        <td class="correct">http://host.docker.internal/piattaforma-collaborativa/backend/api/...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="test-section">
            <h3>🧪 Test di Connettività</h3>
            <button class="test-button" onclick="testHostDockerInternal()">Test host.docker.internal</button>
            <button class="test-button" onclick="testDocumentAccess()">Test Accesso Documento</button>
            <button class="test-button" onclick="testCallbackUrl()">Test Callback URL</button>
            <button class="test-button" onclick="loadEditor()">📝 Carica Editor</button>
        </div>
        
        <div class="log" id="log">
            <div class="log-entry">Sistema pronto per test Docker Desktop...</div>
        </div>
        
        <div id="editor-container"></div>
    </div>

    <!-- OnlyOffice API - Il browser carica da localhost -->
    <script type="text/javascript" src="http://localhost:8082/web-apps/apps/api/documents/api.js"></script>
    
    <script>
        let docEditor = null;
        
        function log(message, type = 'info') {
            const logDiv = document.getElementById('log');
            const entry = document.createElement('div');
            entry.className = `log-entry ${type}`;
            const timestamp = new Date().toLocaleTimeString();
            entry.textContent = `[${timestamp}] ${message}`;
            logDiv.insertBefore(entry, logDiv.firstChild);
        }
        
        async function testHostDockerInternal() {
            log('Testing host.docker.internal connectivity...', 'warning');
            
            // Test dal browser verso il nostro server PHP che verifica la connettività
            try {
                const response = await fetch('test-docker-connectivity.php');
                const result = await response.text();
                log('Docker connectivity test result: ' + result, 'success');
            } catch (error) {
                log('Cannot test from browser directly. Run: docker exec nexio-onlyoffice ping host.docker.internal', 'warning');
            }
        }
        
        async function testDocumentAccess() {
            log('Testing document access...', 'warning');
            
            // URL che OnlyOffice userà (con host.docker.internal)
            const containerUrl = 'http://host.docker.internal/piattaforma-collaborativa/documents/onlyoffice/test_document_1755605547.docx';
            // URL che il browser può testare
            const browserUrl = 'http://localhost/piattaforma-collaborativa/documents/onlyoffice/test_document_1755605547.docx';
            
            log('Container will use: ' + containerUrl, 'info');
            log('Browser testing: ' + browserUrl, 'info');
            
            try {
                const response = await fetch(browserUrl);
                if (response.ok) {
                    log('✅ Document accessible from browser (localhost)', 'success');
                    log('⚠️ OnlyOffice container will use host.docker.internal instead', 'warning');
                } else {
                    log('❌ Document not accessible: HTTP ' + response.status, 'error');
                }
            } catch (error) {
                log('❌ Error accessing document: ' + error.message, 'error');
            }
        }
        
        async function testCallbackUrl() {
            log('Testing callback URL...', 'warning');
            
            const containerCallback = 'http://host.docker.internal/piattaforma-collaborativa/backend/api/onlyoffice-callback.php';
            const browserCallback = 'http://localhost/piattaforma-collaborativa/backend/api/onlyoffice-callback.php';
            
            log('Container will use: ' + containerCallback, 'info');
            log('Browser testing: ' + browserCallback, 'info');
            
            try {
                const response = await fetch(browserCallback, {
                    method: 'GET'
                });
                if (response.ok) {
                    log('✅ Callback endpoint accessible from browser', 'success');
                    log('⚠️ OnlyOffice will POST to host.docker.internal version', 'warning');
                } else {
                    log('⚠️ Callback returned: HTTP ' + response.status, 'warning');
                }
            } catch (error) {
                log('❌ Error accessing callback: ' + error.message, 'error');
            }
        }
        
        function loadEditor() {
            log('Initializing OnlyOffice Editor for Docker Desktop...', 'warning');
            
            const container = document.getElementById('editor-container');
            container.innerHTML = '';
            
            const docKey = 'docker_test_' + Date.now();
            
            const config = {
                document: {
                    fileType: "docx",
                    key: docKey,
                    title: "Docker Desktop Test Document.docx",
                    // CRITICO: Per Docker Desktop Windows DEVE essere host.docker.internal
                    url: "http://host.docker.internal/piattaforma-collaborativa/documents/onlyoffice/test_document_1755605547.docx",
                    permissions: {
                        edit: true,
                        download: true,
                        print: true
                    }
                },
                documentType: "word",
                editorConfig: {
                    // CRITICO: Anche il callback DEVE usare host.docker.internal
                    callbackUrl: "http://host.docker.internal/piattaforma-collaborativa/backend/api/onlyoffice-callback.php?doc=" + docKey,
                    lang: "it-IT",
                    mode: "edit",
                    user: {
                        id: "docker_test_user",
                        name: "Docker Desktop Test User"
                    },
                    customization: {
                        autosave: true,
                        forcesave: true
                    }
                },
                events: {
                    onReady: function() {
                        log('✅ Editor ready!', 'success');
                        log('✅ Docker Desktop configuration working!', 'success');
                    },
                    onDocumentReady: function() {
                        log('✅ Document loaded successfully!', 'success');
                    },
                    onError: function(event) {
                        console.error('OnlyOffice Error:', event);
                        if (event && event.data) {
                            const errorCode = event.data.errorCode;
                            const errorDesc = event.data.errorDescription || '';
                            
                            if (errorCode === -4) {
                                log('❌ Cannot download document - OnlyOffice cannot reach the URL', 'error');
                                log('❌ Make sure to use host.docker.internal, not localhost!', 'error');
                            } else {
                                log(`❌ Error ${errorCode}: ${errorDesc}`, 'error');
                            }
                        }
                    }
                },
                type: "desktop",
                width: "100%",
                height: "100%"
            };
            
            log('Document URL for container: ' + config.document.url, 'warning');
            log('Callback URL for container: ' + config.editorConfig.callbackUrl, 'warning');
            log('Initializing editor...', 'info');
            
            try {
                docEditor = new DocsAPI.DocEditor("editor-container", config);
                log('Editor initialized successfully', 'success');
            } catch (error) {
                log('❌ Failed to initialize editor: ' + error.message, 'error');
            }
        }
        
        // Auto-check on load
        window.addEventListener('load', () => {
            log('Page loaded - Docker Desktop configuration', 'success');
            
            if (typeof DocsAPI !== 'undefined') {
                log('✅ OnlyOffice API loaded', 'success');
            } else {
                log('⚠️ Waiting for OnlyOffice API...', 'warning');
                setTimeout(() => {
                    if (typeof DocsAPI !== 'undefined') {
                        log('✅ OnlyOffice API now available', 'success');
                    } else {
                        log('❌ OnlyOffice API not loading - check container status', 'error');
                    }
                }, 2000);
            }
        });
    </script>
</body>
</html>