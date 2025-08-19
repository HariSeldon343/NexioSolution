<?php
// Funzione per ottenere l'IP locale della macchina
function getLocalIP() {
    // Metodo 1: Prova con hostname
    $hostname = gethostname();
    $ip = gethostbyname($hostname);
    
    // Se otteniamo 127.0.0.1, proviamo altri metodi
    if ($ip === '127.0.0.1' || $ip === $hostname) {
        // Metodo 2: Cerca IP nelle interfacce di rete
        $output = shell_exec('ipconfig');
        if ($output) {
            // Cerca IPv4 Address pattern
            preg_match_all('/IPv4.*?: ([\d.]+)/', $output, $matches);
            foreach ($matches[1] as $possibleIP) {
                // Ignora localhost e IP virtuali
                if (!in_array($possibleIP, ['127.0.0.1', '0.0.0.0']) && 
                    !str_starts_with($possibleIP, '169.254.')) {
                    return $possibleIP;
                }
            }
        }
        
        // Metodo 3: Usa $_SERVER se disponibile
        if (!empty($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] !== '127.0.0.1') {
            return $_SERVER['SERVER_ADDR'];
        }
        
        // Default fallback
        return '192.168.1.100'; // Sostituisci con il tuo IP se nessun metodo funziona
    }
    
    return $ip;
}

$localIP = getLocalIP();
$documentUrl = "http://{$localIP}/piattaforma-collaborativa/documents/onlyoffice/test_document_1755605547.docx";
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test OnlyOffice con IP Locale</title>
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
        .ip-info {
            background: #3498db;
            color: white;
            padding: 15px 20px;
            font-size: 16px;
        }
        .ip-info code {
            background: rgba(255,255,255,0.2);
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
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
        .controls button.success {
            background: #27ae60;
        }
        .controls button.success:hover {
            background: #229954;
        }
        .controls button.danger {
            background: #e74c3c;
        }
        .controls button.danger:hover {
            background: #c0392b;
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
        .test-results {
            padding: 20px;
            background: #ecf0f1;
        }
        .test-item {
            margin: 10px 0;
            padding: 10px;
            background: white;
            border-radius: 5px;
            display: flex;
            align-items: center;
        }
        .test-item.success {
            border-left: 4px solid #27ae60;
        }
        .test-item.error {
            border-left: 4px solid #e74c3c;
        }
        .test-item.warning {
            border-left: 4px solid #f39c12;
        }
        .test-icon {
            font-size: 20px;
            margin-right: 10px;
        }
        .test-details {
            flex: 1;
        }
        .test-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .test-message {
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 Test OnlyOffice con IP Locale Automatico</h1>
        </div>
        
        <div class="ip-info">
            📍 IP Locale Rilevato: <code><?php echo $localIP; ?></code><br>
            📄 URL Documento: <code><?php echo $documentUrl; ?></code>
        </div>
        
        <div class="controls">
            <button onclick="testConnections()" class="success">🔍 Test Connessioni</button>
            <button onclick="testDocumentAccess()">📄 Test Accesso Documento</button>
            <button onclick="loadEditor()">📝 Carica Editor</button>
            <button onclick="clearAll()" class="danger">🗑️ Pulisci Tutto</button>
        </div>
        
        <div class="test-results" id="test-results" style="display:none;">
            <h3>Risultati Test Connettività</h3>
            <div id="test-content"></div>
        </div>
        
        <div id="editor-container">
            <div id="placeholder">
                <h2>Editor OnlyOffice</h2>
                <p>IP Locale: <?php echo $localIP; ?></p>
                <p>Clicca "Test Connessioni" per verificare la configurazione</p>
            </div>
        </div>
        
        <div class="status" id="status">
            <div class="status-line">
                <span class="timestamp"><?php echo date('H:i:s'); ?></span>
                Sistema pronto. IP locale: <?php echo $localIP; ?>
            </div>
        </div>
    </div>

    <!-- OnlyOffice DocsAPI Script -->
    <script type="text/javascript" src="http://localhost:8082/web-apps/apps/api/documents/api.js"></script>
    
    <script>
        let docEditor = null;
        const localIP = '<?php echo $localIP; ?>';
        const documentUrl = '<?php echo $documentUrl; ?>';
        
        function addLog(message, type = 'info') {
            const statusDiv = document.getElementById('status');
            const timestamp = new Date().toLocaleTimeString();
            const statusLine = document.createElement('div');
            statusLine.className = `status-line ${type}`;
            statusLine.innerHTML = `<span class="timestamp">${timestamp}</span> ${message}`;
            statusDiv.insertBefore(statusLine, statusDiv.firstChild);
            
            while (statusDiv.children.length > 20) {
                statusDiv.removeChild(statusDiv.lastChild);
            }
        }
        
        function addTestResult(title, message, type = 'info') {
            const content = document.getElementById('test-content');
            const icons = {
                success: '✅',
                error: '❌',
                warning: '⚠️',
                info: 'ℹ️'
            };
            
            const item = document.createElement('div');
            item.className = `test-item ${type}`;
            item.innerHTML = `
                <div class="test-icon">${icons[type]}</div>
                <div class="test-details">
                    <div class="test-title">${title}</div>
                    <div class="test-message">${message}</div>
                </div>
            `;
            content.appendChild(item);
        }
        
        async function testConnections() {
            const resultsDiv = document.getElementById('test-results');
            const content = document.getElementById('test-content');
            resultsDiv.style.display = 'block';
            content.innerHTML = '';
            
            addLog('Inizio test connettività con IP locale: ' + localIP, 'warning');
            
            // Test 1: OnlyOffice API
            try {
                addLog('Test OnlyOffice API...', 'info');
                const response = await fetch('http://localhost:8082/healthcheck', { mode: 'no-cors' });
                addTestResult('OnlyOffice API', 'Server raggiungibile su porta 8082', 'success');
                addLog('✅ OnlyOffice API raggiungibile', 'success');
            } catch (error) {
                addTestResult('OnlyOffice API', 'Server non raggiungibile: ' + error.message, 'error');
                addLog('❌ OnlyOffice API non raggiungibile', 'error');
            }
            
            // Test 2: Documento con IP locale
            try {
                addLog('Test accesso documento con IP locale...', 'info');
                const response = await fetch(documentUrl);
                if (response.ok) {
                    const size = response.headers.get('content-length');
                    addTestResult('Documento (IP Locale)', 
                        `Accessibile dal browser - Dimensione: ${size ? (size/1024).toFixed(2) + ' KB' : 'sconosciuta'}`, 
                        'success');
                    addLog('✅ Documento accessibile con IP: ' + localIP, 'success');
                } else {
                    addTestResult('Documento (IP Locale)', 
                        `HTTP ${response.status} - ${response.statusText}`, 
                        'error');
                    addLog('❌ Documento non accessibile: HTTP ' + response.status, 'error');
                }
            } catch (error) {
                addTestResult('Documento (IP Locale)', 
                    'Errore accesso: ' + error.message, 
                    'error');
                addLog('❌ Errore accesso documento: ' + error.message, 'error');
            }
            
            // Test 3: host.docker.internal
            try {
                addLog('Test host.docker.internal...', 'info');
                const hostUrl = documentUrl.replace(localIP, 'host.docker.internal');
                const response = await fetch(hostUrl);
                if (response.ok) {
                    addTestResult('host.docker.internal', 
                        'Funziona - il container può usare questo host', 
                        'success');
                    addLog('✅ host.docker.internal funziona', 'success');
                } else {
                    addTestResult('host.docker.internal', 
                        `HTTP ${response.status} - Usa IP locale invece`, 
                        'warning');
                }
            } catch (error) {
                addTestResult('host.docker.internal', 
                    'Non disponibile - Usa IP locale: ' + localIP, 
                    'warning');
                addLog('⚠️ host.docker.internal non disponibile, usa IP', 'warning');
            }
            
            // Test 4: DocsAPI
            if (typeof DocsAPI !== 'undefined') {
                addTestResult('DocsAPI JavaScript', 
                    'Libreria OnlyOffice caricata correttamente', 
                    'success');
                addLog('✅ DocsAPI disponibile', 'success');
            } else {
                addTestResult('DocsAPI JavaScript', 
                    'Libreria non caricata - verifica connessione a porta 8082', 
                    'error');
                addLog('❌ DocsAPI non disponibile', 'error');
            }
            
            // Riepilogo
            addLog('Test completati. Controlla i risultati sopra.', 'info');
        }
        
        async function testDocumentAccess() {
            addLog('Test accesso documento da OnlyOffice container...', 'warning');
            
            // Simula quello che fa OnlyOffice
            addLog('URL che OnlyOffice proverà: ' + documentUrl, 'info');
            
            try {
                const response = await fetch(documentUrl);
                if (response.ok) {
                    const blob = await response.blob();
                    addLog(`✅ Documento scaricabile - Dimensione: ${(blob.size/1024).toFixed(2)} KB`, 'success');
                    addLog('Il documento dovrebbe essere accessibile da OnlyOffice', 'success');
                } else {
                    addLog(`❌ HTTP ${response.status} - Il documento non è accessibile`, 'error');
                    addLog('OnlyOffice non potrà caricare questo documento', 'error');
                }
            } catch (error) {
                addLog('❌ Errore di rete: ' + error.message, 'error');
                addLog('Verifica che Apache sia in ascolto su tutte le interfacce', 'warning');
            }
        }
        
        function loadEditor() {
            if (docEditor) {
                addLog('Editor già caricato', 'warning');
                return;
            }
            
            addLog('Inizializzo editor con IP locale: ' + localIP, 'warning');
            
            const container = document.getElementById('editor-container');
            container.innerHTML = '';
            
            try {
                // Test accesso documento prima di caricare editor
                fetch(documentUrl)
                    .then(response => {
                        if (response.ok) {
                            addLog('✅ Documento accessibile dal browser', 'success');
                        } else {
                            addLog('❌ Documento non accessibile: HTTP ' + response.status, 'error');
                        }
                    })
                    .catch(error => {
                        addLog('❌ Errore accesso documento: ' + error.message, 'error');
                    });
                
                const config = {
                    document: {
                        fileType: "docx",
                        key: "test_ip_" + Date.now(),
                        title: "Test Document con IP.docx",
                        url: documentUrl, // Usa IP locale rilevato
                        permissions: {
                            edit: true,
                            download: true,
                            print: true
                        }
                    },
                    documentType: "word",
                    editorConfig: {
                        callbackUrl: `http://${localIP}/piattaforma-collaborativa/backend/api/onlyoffice-callback.php`,
                        lang: "it-IT",
                        mode: "edit",
                        user: {
                            id: "test_user_ip",
                            name: "Test User (IP: " + localIP + ")"
                        },
                        customization: {
                            autosave: true,
                            forcesave: true
                        }
                    },
                    events: {
                        onReady: function() {
                            addLog('✅ Editor pronto con IP: ' + localIP, 'success');
                        },
                        onDocumentReady: function() {
                            addLog('✅ Documento caricato correttamente', 'success');
                        },
                        onError: function(event) {
                            console.error('OnlyOffice Error:', event);
                            if (event && event.data) {
                                let errorMsg = 'Errore sconosciuto';
                                switch(event.data.errorCode) {
                                    case -4:
                                        errorMsg = 'OnlyOffice non può scaricare il documento. Verifica che Apache sia accessibile su IP: ' + localIP;
                                        break;
                                    case -3:
                                        errorMsg = 'Errore interno del server OnlyOffice';
                                        break;
                                    case -6:
                                        errorMsg = 'Chiave documento non valida';
                                        break;
                                    default:
                                        errorMsg = `Codice errore: ${event.data.errorCode} - ${event.data.errorDescription || ''}`;
                                }
                                addLog('❌ ' + errorMsg, 'error');
                            }
                        }
                    },
                    type: "desktop",
                    width: "100%",
                    height: "100%"
                };
                
                addLog('📋 Configurazione con IP: ' + localIP, 'info');
                addLog('📄 Document URL: ' + config.document.url, 'info');
                addLog('🔄 Callback URL: ' + config.editorConfig.callbackUrl, 'info');
                
                docEditor = new DocsAPI.DocEditor("editor-container", config);
                addLog('Editor inizializzato', 'success');
                
            } catch (error) {
                addLog('❌ Errore inizializzazione: ' + error.message, 'error');
                console.error('Init error:', error);
            }
        }
        
        function clearAll() {
            if (docEditor) {
                try {
                    docEditor.destroyEditor();
                    addLog('Editor rimosso', 'info');
                } catch (e) {
                    console.error('Errore rimozione:', e);
                }
                docEditor = null;
            }
            
            document.getElementById('test-results').style.display = 'none';
            document.getElementById('editor-container').innerHTML = `
                <div id="placeholder">
                    <h2>Editor OnlyOffice</h2>
                    <p>IP Locale: ${localIP}</p>
                    <p>Clicca "Test Connessioni" per verificare la configurazione</p>
                </div>
            `;
        }
        
        // Auto-test all'avvio
        window.addEventListener('load', () => {
            addLog('Pagina caricata con IP locale: ' + localIP, 'success');
            addLog('URL documento configurato: ' + documentUrl, 'info');
            
            setTimeout(() => {
                if (typeof DocsAPI !== 'undefined') {
                    addLog('✅ OnlyOffice DocsAPI disponibile', 'success');
                } else {
                    addLog('⚠️ DocsAPI non ancora caricato', 'warning');
                }
            }, 1000);
        });
    </script>
</body>
</html>