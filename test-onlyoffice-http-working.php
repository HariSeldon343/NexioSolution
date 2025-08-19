<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test OnlyOffice HTTP (Sviluppo)</title>
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
        .warning {
            background: #f39c12;
            color: white;
            padding: 15px;
            margin: 0;
            font-weight: bold;
        }
        .info {
            background: #3498db;
            color: white;
            padding: 15px;
            margin: 0;
        }
        .success {
            background: #27ae60;
            color: white;
            padding: 15px;
            margin: 0;
        }
        .error {
            background: #e74c3c;
            color: white;
            padding: 15px;
            margin: 0;
        }
        .controls {
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        .controls button {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-right: 10px;
            font-size: 14px;
        }
        .controls button:hover {
            background: #2980b9;
        }
        .controls button.danger {
            background: #e74c3c;
        }
        .controls button.danger:hover {
            background: #c0392b;
        }
        .controls button.success {
            background: #27ae60;
        }
        .controls button.success:hover {
            background: #229954;
        }
        #editor-container {
            height: 600px;
            position: relative;
            background: #f0f0f0;
        }
        #placeholder {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            color: #666;
        }
        #placeholder h2 {
            margin: 0 0 10px 0;
            color: #333;
        }
        .status {
            padding: 20px;
            background: #2c3e50;
            color: white;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            max-height: 200px;
            overflow-y: auto;
        }
        .status-line {
            margin: 5px 0;
            padding: 5px;
            background: rgba(255,255,255,0.1);
            border-radius: 3px;
        }
        .status-line.success {
            background: rgba(46, 204, 113, 0.2);
        }
        .status-line.error {
            background: rgba(231, 76, 60, 0.2);
        }
        .status-line.warning {
            background: rgba(243, 156, 18, 0.2);
        }
        .timestamp {
            color: #95a5a6;
            margin-right: 10px;
        }
        .debug-panel {
            padding: 20px;
            background: #ecf0f1;
            border-top: 1px solid #bdc3c7;
        }
        .debug-panel h3 {
            margin: 0 0 10px 0;
            color: #2c3e50;
        }
        .debug-item {
            margin: 10px 0;
            padding: 10px;
            background: white;
            border-radius: 5px;
            border-left: 4px solid #3498db;
        }
        .debug-item strong {
            color: #2c3e50;
        }
        .loader {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 Test OnlyOffice Document Server - HTTP Mode (Sviluppo)</h1>
        </div>
        
        <div class="warning">
            ⚠️ MODALITÀ HTTP: Solo per sviluppo locale. In produzione usare HTTPS con certificati validi.
        </div>
        
        <div class="info">
            ℹ️ Questo test usa HTTP su porta 8082 per evitare problemi con certificati SSL self-signed in sviluppo.
        </div>
        
        <div class="controls">
            <button onclick="checkConnection()">🔍 Verifica Connessione</button>
            <button onclick="loadEditor()" class="success">📝 Carica Editor</button>
            <button onclick="createNewDocument()">📄 Crea Nuovo Documento</button>
            <button onclick="testWebSocket()">🔌 Test WebSocket</button>
            <button onclick="clearEditor()" class="danger">🗑️ Pulisci Editor</button>
            <button onclick="showDebugInfo()">🐛 Debug Info</button>
        </div>
        
        <div id="editor-container">
            <div id="placeholder">
                <h2>Editor OnlyOffice</h2>
                <p>Clicca "Carica Editor" per iniziare</p>
                <p>Configurazione: HTTP://localhost:8082</p>
            </div>
        </div>
        
        <div class="status" id="status">
            <div class="status-line">
                <span class="timestamp"><?php echo date('H:i:s'); ?></span>
                Sistema pronto. Modalità HTTP attiva.
            </div>
        </div>
        
        <div class="debug-panel" id="debug-panel" style="display:none;">
            <h3>🐛 Informazioni Debug</h3>
            <div id="debug-content"></div>
        </div>
    </div>

    <!-- OnlyOffice DocsAPI Script - HTTP -->
    <script type="text/javascript" src="http://localhost:8082/web-apps/apps/api/documents/api.js"></script>
    
    <script>
        let docEditor = null;
        let isEditorLoaded = false;
        
        // Log status
        function logStatus(message, type = 'info') {
            const statusDiv = document.getElementById('status');
            const timestamp = new Date().toLocaleTimeString();
            const statusLine = document.createElement('div');
            statusLine.className = `status-line ${type}`;
            statusLine.innerHTML = `<span class="timestamp">${timestamp}</span> ${message}`;
            statusDiv.insertBefore(statusLine, statusDiv.firstChild);
            
            // Mantieni solo le ultime 20 righe
            while (statusDiv.children.length > 20) {
                statusDiv.removeChild(statusDiv.lastChild);
            }
        }
        
        // Verifica connessione
        async function checkConnection() {
            logStatus('Verifico connessione HTTP a OnlyOffice...', 'warning');
            
            const endpoints = [
                { url: 'http://localhost:8082/healthcheck', name: 'Health Check' },
                { url: 'http://localhost:8082/web-apps/apps/api/documents/api.js', name: 'API JS' },
                { url: 'http://localhost:8082/coauthoring/CommandService.ashx', name: 'Command Service' },
                { url: 'http://localhost:8083/', name: 'File Server' }
            ];
            
            for (const endpoint of endpoints) {
                try {
                    const response = await fetch(endpoint.url, {
                        method: 'GET',
                        mode: 'no-cors' // Evita problemi CORS
                    });
                    logStatus(`✅ ${endpoint.name}: Raggiungibile`, 'success');
                } catch (error) {
                    logStatus(`❌ ${endpoint.name}: ${error.message}`, 'error');
                }
            }
            
            // Verifica se DocsAPI è caricato
            if (typeof DocsAPI !== 'undefined') {
                logStatus('✅ DocsAPI caricato correttamente', 'success');
            } else {
                logStatus('❌ DocsAPI non trovato - verificare script include', 'error');
            }
        }
        
        // Carica editor
        function loadEditor() {
            if (isEditorLoaded) {
                logStatus('Editor già caricato', 'warning');
                return;
            }
            
            logStatus('Inizializzo editor OnlyOffice (HTTP)...', 'warning');
            
            // Pulisci container
            const container = document.getElementById('editor-container');
            container.innerHTML = '';
            
            try {
                // Configurazione minima per test HTTP
                const config = {
                    document: {
                        fileType: "docx",
                        key: "test_" + Date.now(),
                        title: "Test Document HTTP.docx",
                        // CRITICO: Per Docker Desktop Windows, OnlyOffice DEVE usare host.docker.internal
                        // Dal container Docker, "localhost" punta al container stesso, NON all'host!
                        url: "http://host.docker.internal/piattaforma-collaborativa/documents/onlyoffice/test_document_1755605547.docx",
                        // NOTA IMPORTANTE: host.docker.internal è OBBLIGATORIO per Docker Desktop su Windows
                        // NON usare: localhost, 127.0.0.1, o IP locali - questi non funzioneranno dal container
                        permissions: {
                            edit: true,
                            download: true,
                            print: true
                        }
                    },
                    documentType: "word",
                    editorConfig: {
                        // CRITICO: Anche il callback DEVE usare host.docker.internal per Docker Desktop
                        callbackUrl: "http://host.docker.internal/piattaforma-collaborativa/backend/api/onlyoffice-callback.php",
                        lang: "it-IT",
                        mode: "edit",
                        user: {
                            id: "test_user",
                            name: "Test User HTTP"
                        },
                        customization: {
                            autosave: true,
                            compactHeader: false,
                            compactToolbar: false,
                            feedback: {
                                visible: false
                            },
                            forcesave: true,
                            logo: {
                                image: "http://localhost/piattaforma-collaborativa/assets/images/nexio-logo.svg"
                            }
                        }
                    },
                    events: {
                        onReady: function() {
                            logStatus('✅ Editor pronto (HTTP mode)', 'success');
                            isEditorLoaded = true;
                        },
                        onDocumentReady: function() {
                            logStatus('✅ Documento caricato', 'success');
                        },
                        onError: function(event) {
                            console.error('OnlyOffice Error Details:', event);
                            
                            let errorMessage = 'Errore sconosciuto';
                            let errorDetails = '';
                            
                            if (event && event.data) {
                                console.error('Error Code:', event.data.errorCode);
                                console.error('Error Description:', event.data.errorDescription);
                                
                                // Interpretazione codici errore OnlyOffice
                                switch(event.data.errorCode) {
                                    case -1: 
                                        errorMessage = 'Unknown error';
                                        errorDetails = 'Errore generico del server';
                                        break;
                                    case -2: 
                                        errorMessage = 'Callback URL error';
                                        errorDetails = 'Il server non può raggiungere l\'URL di callback';
                                        break;
                                    case -3: 
                                        errorMessage = 'Internal server error';
                                        errorDetails = 'Errore interno del Document Server';
                                        break;
                                    case -4: 
                                        errorMessage = 'Cannot download document';
                                        errorDetails = 'Il server non può scaricare il documento dall\'URL fornito';
                                        break;
                                    case -5: 
                                        errorMessage = 'Unsupported document format';
                                        errorDetails = 'Formato documento non supportato';
                                        break;
                                    case -6: 
                                        errorMessage = 'Invalid document key';
                                        errorDetails = 'La chiave del documento non è valida o è duplicata';
                                        break;
                                    case -20: 
                                        errorMessage = 'Too many connections';
                                        errorDetails = 'Troppe connessioni al server';
                                        break;
                                    case -21: 
                                        errorMessage = 'Password required';
                                        errorDetails = 'Il documento richiede una password';
                                        break;
                                    case -22: 
                                        errorMessage = 'Database error';
                                        errorDetails = 'Errore database nel Document Server';
                                        break;
                                    default:
                                        if (event.data.errorCode) {
                                            errorMessage = `Error code: ${event.data.errorCode}`;
                                        }
                                        if (event.data.errorDescription) {
                                            errorDetails = event.data.errorDescription;
                                        }
                                }
                                
                                logStatus(`❌ ${errorMessage}: ${errorDetails}`, 'error');
                                logStatus(`📋 Error Data: ${JSON.stringify(event.data)}`, 'error');
                            } else {
                                logStatus(`❌ OnlyOffice Error: ${JSON.stringify(event)}`, 'error');
                            }
                        },
                        onWarning: function(event) {
                            logStatus(`⚠️ Avviso: ${event.data}`, 'warning');
                        },
                        onInfo: function(event) {
                            logStatus(`ℹ️ Info: ${JSON.stringify(event.data)}`, 'info');
                        }
                    },
                    type: "desktop",
                    width: "100%",
                    height: "100%"
                };
                
                logStatus('📋 Document URL: ' + config.document.url, 'warning');
                logStatus('🔑 Document Key: ' + config.document.key, 'info');
                logStatus('Configurazione HTTP: ' + JSON.stringify({
                    documentServer: 'http://localhost:8082',
                    documentUrl: config.document.url,
                    callbackUrl: config.editorConfig.callbackUrl,
                    mode: 'HTTP'
                }), 'info');
                
                // Test accesso documento prima di caricare editor
                fetch(config.document.url)
                    .then(response => {
                        if (response.ok) {
                            logStatus('✅ Documento accessibile dal browser', 'success');
                        } else {
                            logStatus('❌ Documento non accessibile dal browser: HTTP ' + response.status, 'error');
                            logStatus('⚠️ OnlyOffice potrebbe non riuscire a caricare il documento', 'warning');
                        }
                    })
                    .catch(error => {
                        logStatus('❌ Errore accesso documento dal browser: ' + error.message, 'error');
                        logStatus('⚠️ Verifica che il percorso del documento sia corretto', 'warning');
                    });
                
                // Inizializza editor
                docEditor = new DocsAPI.DocEditor("editor-container", config);
                logStatus('Editor inizializzato con successo (HTTP)', 'success');
                
            } catch (error) {
                logStatus(`❌ Errore inizializzazione: ${error.message}`, 'error');
                console.error('Initialization error:', error);
            }
        }
        
        // Crea nuovo documento
        async function createNewDocument() {
            logStatus('Creazione nuovo documento...', 'warning');
            
            try {
                const response = await fetch('backend/api/onlyoffice-prepare.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        type: 'word',
                        title: 'Nuovo Documento HTTP Test'
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    logStatus(`✅ Documento creato: ${result.filename}`, 'success');
                    
                    // Ricarica editor con nuovo documento
                    clearEditor();
                    setTimeout(() => {
                        loadEditorWithDocument(result.filename);
                    }, 500);
                } else {
                    logStatus(`❌ Errore creazione: ${result.error}`, 'error');
                }
            } catch (error) {
                logStatus(`❌ Errore: ${error.message}`, 'error');
            }
        }
        
        // Carica editor con documento specifico
        function loadEditorWithDocument(filename) {
            logStatus(`Carico documento: ${filename}`, 'warning');
            
            const container = document.getElementById('editor-container');
            container.innerHTML = '';
            
            const config = {
                document: {
                    fileType: filename.split('.').pop(),
                    key: filename + "_" + Date.now(),
                    title: filename,
                    url: `http://localhost:8083/documents/onlyoffice/${filename}`,
                    permissions: {
                        edit: true,
                        download: true
                    }
                },
                documentType: "word",
                editorConfig: {
                    // CRITICO: Docker Desktop Windows richiede host.docker.internal
                    callbackUrl: "http://host.docker.internal/piattaforma-collaborativa/backend/api/onlyoffice-callback.php",
                    lang: "it-IT",
                    mode: "edit",
                    user: {
                        id: "test_user",
                        name: "Test User HTTP"
                    }
                },
                events: {
                    onReady: () => {
                        logStatus('✅ Editor pronto con documento', 'success');
                        isEditorLoaded = true;
                    },
                    onError: (event) => {
                        logStatus(`❌ Errore: ${event.data}`, 'error');
                    }
                },
                type: "desktop",
                width: "100%",
                height: "100%"
            };
            
            docEditor = new DocsAPI.DocEditor("editor-container", config);
        }
        
        // Test WebSocket
        function testWebSocket() {
            logStatus('Test connessione WebSocket HTTP...', 'warning');
            
            // Prova WebSocket HTTP (non WSS)
            const wsUrl = 'ws://localhost:8082/doc/';
            logStatus(`Tentativo connessione a: ${wsUrl}`, 'info');
            
            try {
                const ws = new WebSocket(wsUrl);
                
                ws.onopen = () => {
                    logStatus('✅ WebSocket HTTP connesso!', 'success');
                    ws.close();
                };
                
                ws.onerror = (error) => {
                    logStatus(`❌ WebSocket errore: ${error.message || 'Connessione fallita'}`, 'error');
                };
                
                ws.onclose = () => {
                    logStatus('WebSocket chiuso', 'info');
                };
                
                setTimeout(() => {
                    if (ws.readyState === WebSocket.CONNECTING) {
                        logStatus('⚠️ WebSocket timeout - connessione lenta', 'warning');
                        ws.close();
                    }
                }, 5000);
                
            } catch (error) {
                logStatus(`❌ Errore WebSocket: ${error.message}`, 'error');
            }
        }
        
        // Pulisci editor
        function clearEditor() {
            if (docEditor) {
                try {
                    docEditor.destroyEditor();
                    logStatus('Editor rimosso', 'info');
                } catch (e) {
                    console.error('Errore rimozione editor:', e);
                }
                docEditor = null;
                isEditorLoaded = false;
            }
            
            const container = document.getElementById('editor-container');
            container.innerHTML = `
                <div id="placeholder">
                    <h2>Editor OnlyOffice</h2>
                    <p>Clicca "Carica Editor" per iniziare</p>
                    <p>Configurazione: HTTP://localhost:8082</p>
                </div>
            `;
        }
        
        // Mostra debug info
        function showDebugInfo() {
            const panel = document.getElementById('debug-panel');
            const content = document.getElementById('debug-content');
            
            if (panel.style.display === 'none') {
                panel.style.display = 'block';
                
                const debugInfo = {
                    'Modalità': 'HTTP (Sviluppo)',
                    'Document Server': 'http://localhost:8082',
                    'File Server': 'http://localhost:8083',
                    'WebSocket': 'ws://localhost:8082',
                    'DocsAPI Caricato': typeof DocsAPI !== 'undefined' ? 'Sì' : 'No',
                    'Editor Attivo': isEditorLoaded ? 'Sì' : 'No',
                    'Browser': navigator.userAgent,
                    'Protocol': window.location.protocol,
                    'Timestamp': new Date().toISOString()
                };
                
                content.innerHTML = '';
                for (const [key, value] of Object.entries(debugInfo)) {
                    content.innerHTML += `
                        <div class="debug-item">
                            <strong>${key}:</strong> ${value}
                        </div>
                    `;
                }
                
                logStatus('Debug info visualizzato', 'info');
            } else {
                panel.style.display = 'none';
            }
        }
        
        // Auto-check on load
        window.addEventListener('load', () => {
            logStatus('Pagina caricata - Modalità HTTP attiva', 'success');
            
            // Check se DocsAPI è disponibile
            setTimeout(() => {
                if (typeof DocsAPI !== 'undefined') {
                    logStatus('✅ OnlyOffice DocsAPI disponibile (HTTP)', 'success');
                } else {
                    logStatus('⚠️ DocsAPI non ancora caricato', 'warning');
                }
            }, 1000);
        });
        
        // Gestione errori globali
        window.addEventListener('error', (event) => {
            if (event.message.includes('DocsAPI')) {
                logStatus(`❌ Errore DocsAPI: ${event.message}`, 'error');
            }
        });
    </script>
</body>
</html>