<?php
/**
 * Test semplificato OnlyOffice - Apre direttamente il documento senza autenticazione complessa
 */


// Include OnlyOffice configuration
require_once __DIR__ . '/backend/config/onlyoffice.config.php';
// Configurazione diretta
$ONLYOFFICE_SERVER = $ONLYOFFICE_DS_PUBLIC_URL;
$documentId = 22; // ID del documento nel database con file DOCX valido

// Configurazione minima per test
$config = [
    'type' => 'desktop',
    'documentType' => 'word',
    'document' => [
        'title' => 'Test Document.docx',
        'url' => 'http://localhost/piattaforma-collaborativa/documents/onlyoffice/test_document_1755542731.docx',
        'fileType' => 'docx',
        'key' => 'test_' . time(), // Chiave unica per evitare cache
        'permissions' => [
            'download' => true,
            'edit' => false,
            'print' => true
        ]
    ],
    'editorConfig' => [
        'mode' => 'view',
        'lang' => 'it',
        'user' => [
            'id' => '1',
            'name' => 'Test User'
        ]
    ]
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test OnlyOffice Simple</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }
        .header {
            background: #007bff;
            color: white;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        #onlyoffice-placeholder {
            width: 100%;
            height: calc(100vh - 60px);
            border: none;
        }
        .status {
            padding: 20px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            margin: 20px;
            border-radius: 5px;
        }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Test OnlyOffice Simple</h1>
        <button onclick="location.reload()">Ricarica</button>
    </div>
    
    <div class="status" id="status">
        <h3>Stato del test:</h3>
        <div id="log"></div>
    </div>
    
    <div id="onlyoffice-placeholder"></div>
    
    <!-- Carica OnlyOffice API -->
    <script type="text/javascript" src="<?php echo $ONLYOFFICE_SERVER; ?>/web-apps/apps/api/documents/api.js"></script>
    
    <script type="text/javascript">
        const logElement = document.getElementById('log');
        
        function addLog(message, type = 'info') {
            const entry = document.createElement('div');
            entry.className = type;
            entry.innerHTML = `[${new Date().toLocaleTimeString()}] ${message}`;
            logElement.appendChild(entry);
            console.log(`[${type}] ${message}`);
        }
        
        // Configurazione
        const config = <?php echo json_encode($config); ?>;
        
        addLog('Configurazione caricata', 'info');
        addLog('Document URL: ' + config.document.url, 'info');
        addLog('Document Type: ' + config.documentType, 'info');
        
        // Verifica se DocsAPI è disponibile
        if (typeof DocsAPI === 'undefined') {
            addLog('❌ DocsAPI non disponibile! OnlyOffice Document Server non raggiungibile.', 'error');
            addLog('Verifica che il server sia attivo su <?php echo $ONLYOFFICE_SERVER; ?>', 'error');
        } else {
            addLog('✅ DocsAPI caricato correttamente', 'success');
            
            // Event handlers
            config.events = {
                'onAppReady': function() {
                    addLog('✅ Editor pronto!', 'success');
                    document.getElementById('status').style.display = 'none';
                },
                'onDocumentStateChange': function(event) {
                    addLog('Stato documento: ' + JSON.stringify(event.data), 'info');
                },
                'onError': function(event) {
                    addLog('❌ Errore: ' + JSON.stringify(event.data), 'error');
                },
                'onWarning': function(event) {
                    addLog('⚠️ Warning: ' + JSON.stringify(event.data), 'info');
                }
            };
            
            try {
                addLog('Inizializzazione editor...', 'info');
                
                // Crea l'editor
                window.docEditor = new DocsAPI.DocEditor('onlyoffice-placeholder', config);
                
                addLog('Editor creato, attendi il caricamento...', 'info');
                
            } catch (error) {
                addLog('❌ Errore durante l\'inizializzazione: ' + error.message, 'error');
                console.error(error);
            }
        }
        
        // Monitor errori JavaScript globali
        window.onerror = function(msg, url, line, col, error) {
            addLog(`❌ JS Error: ${msg} (${url}:${line}:${col})`, 'error');
            return false;
        };
    </script>
</body>
</html>