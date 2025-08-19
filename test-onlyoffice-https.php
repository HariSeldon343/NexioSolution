<?php
/**
 * Test OnlyOffice con HTTPS
 * Verifica integrazione con HTTPS su porta 8443
 */

// Importa configurazione
require_once 'backend/config/onlyoffice.config.php';

// Crea un documento di test se non esiste
$testDoc = 'documents/onlyoffice/test_https_' . time() . '.docx';
$templateDoc = 'documents/onlyoffice/45.docx';

if (file_exists($templateDoc)) {
    copy($templateDoc, $testDoc);
    $docName = basename($testDoc);
} else {
    // Crea un documento vuoto
    $docName = 'test_https_' . time() . '.docx';
    $testDoc = 'documents/onlyoffice/' . $docName;
    file_put_contents($testDoc, ''); // OnlyOffice gestirà il formato
}

// Configurazione editor
$config = [
    'document' => [
        'fileType' => 'docx',
        'key' => md5($docName . time()),
        'title' => $docName,
        'url' => OnlyOfficeConfig::getFileServerUrl() . 'documents/onlyoffice/' . $docName,
        'permissions' => [
            'comment' => true,
            'download' => true,
            'edit' => true,
            'fillForms' => true,
            'print' => true
        ]
    ],
    'documentType' => 'word',
    'editorConfig' => [
        'callbackUrl' => OnlyOfficeConfig::getCallbackUrl($docName),
        'lang' => 'it',
        'user' => [
            'id' => '1',
            'name' => 'Test User HTTPS',
            'group' => 'Admin'
        ],
        'customization' => [
            'autosave' => true,
            'forcesave' => false,
            'hideRightMenu' => false,
            'goback' => [
                'text' => 'Torna ai test',
                'url' => 'test-onlyoffice-final-check.php'
            ]
        ]
    ]
];

// URL Document Server (HTTPS)
$documentServerUrl = OnlyOfficeConfig::getDocumentServerUrl();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OnlyOffice HTTPS Test - Porta 8443</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header h1 {
            margin: 0;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .info {
            margin-top: 10px;
            padding: 10px;
            background: #f0f0f0;
            border-radius: 5px;
            font-size: 14px;
        }
        .info code {
            background: #fff;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        .status.https {
            background: #d4edda;
            color: #155724;
        }
        .status.loading {
            background: #fff3cd;
            color: #856404;
        }
        .status.error {
            background: #f8d7da;
            color: #721c24;
        }
        #editor-container {
            flex: 1;
            position: relative;
            background: white;
        }
        #onlyoffice-editor {
            width: 100%;
            height: 100%;
            border: none;
        }
        .loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            color: white;
        }
        .loading-spinner {
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top: 3px solid white;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 20px;
            border-radius: 5px;
            margin: 20px;
        }
        .controls {
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.95);
            display: flex;
            gap: 10px;
            align-items: center;
        }
        button {
            padding: 8px 16px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        button:hover {
            background: #5a67d8;
        }
    </style>
</head>
<body>

<div class="header">
    <h1>
        🔒 OnlyOffice HTTPS Test
        <span class="status https">HTTPS:8443</span>
        <span id="connection-status" class="status loading">Connessione...</span>
    </h1>
    <div class="info">
        <strong>Document Server:</strong> <code><?php echo htmlspecialchars($documentServerUrl); ?></code><br>
        <strong>Documento:</strong> <code><?php echo htmlspecialchars($docName); ?></code><br>
        <strong>File Server:</strong> <code><?php echo htmlspecialchars(OnlyOfficeConfig::getFileServerUrl()); ?></code>
    </div>
</div>

<div class="controls">
    <button onclick="location.href='test-onlyoffice-final-check.php'">← Torna ai Test</button>
    <button onclick="saveDocument()">💾 Salva Documento</button>
    <button onclick="location.reload()">🔄 Ricarica</button>
    <button onclick="showInfo()">ℹ️ Info Connessione</button>
</div>

<div id="editor-container">
    <div class="loading" id="loading">
        <div class="loading-spinner"></div>
        <div>Caricamento OnlyOffice Editor via HTTPS...</div>
    </div>
    <div id="onlyoffice-editor"></div>
</div>

<script type="text/javascript" src="<?php echo $documentServerUrl; ?>web-apps/apps/api/documents/api.js"></script>
<script type="text/javascript">
    let docEditor = null;
    const config = <?php echo json_encode($config, JSON_PRETTY_PRINT); ?>;
    
    // Aggiungi callback per eventi
    config.events = {
        onReady: function() {
            console.log('✅ OnlyOffice Editor Ready (HTTPS)');
            document.getElementById('loading').style.display = 'none';
            document.getElementById('connection-status').className = 'status https';
            document.getElementById('connection-status').textContent = 'Connesso ✓';
        },
        onError: function(event) {
            console.error('❌ OnlyOffice Error:', event);
            document.getElementById('loading').innerHTML = `
                <div class="error-message">
                    <h3>Errore di connessione</h3>
                    <p>${event.data || 'Impossibile caricare l\'editor'}</p>
                    <p>Verifica che OnlyOffice sia attivo su HTTPS porta 8443</p>
                </div>
            `;
            document.getElementById('connection-status').className = 'status error';
            document.getElementById('connection-status').textContent = 'Errore';
        },
        onDocumentStateChange: function(event) {
            console.log('📄 Document State:', event.data ? 'Modified' : 'Saved');
        },
        onRequestSave: function() {
            console.log('💾 Save requested');
        }
    };
    
    // Inizializza editor quando DocsAPI è disponibile
    function initEditor() {
        if (typeof DocsAPI !== 'undefined') {
            console.log('🚀 Initializing OnlyOffice Editor with HTTPS...');
            console.log('Config:', config);
            
            try {
                docEditor = new DocsAPI.DocEditor("onlyoffice-editor", config);
                console.log('✅ Editor initialized successfully');
            } catch (error) {
                console.error('❌ Failed to initialize editor:', error);
                document.getElementById('loading').innerHTML = `
                    <div class="error-message">
                        <h3>Errore inizializzazione</h3>
                        <p>${error.message}</p>
                    </div>
                `;
                document.getElementById('connection-status').className = 'status error';
                document.getElementById('connection-status').textContent = 'Errore Init';
            }
        } else {
            console.error('❌ DocsAPI not available');
            setTimeout(initEditor, 1000); // Riprova dopo 1 secondo
        }
    }
    
    // Funzioni utility
    function saveDocument() {
        if (docEditor) {
            docEditor.downloadAs();
            console.log('📥 Download initiated');
        }
    }
    
    function showInfo() {
        const info = {
            'Document Server URL': '<?php echo $documentServerUrl; ?>',
            'Protocol': window.location.protocol,
            'HTTPS Enabled': window.location.protocol === 'https:',
            'DocsAPI Available': typeof DocsAPI !== 'undefined',
            'Editor Initialized': docEditor !== null,
            'Document Key': config.document.key
        };
        
        console.table(info);
        alert('Informazioni connessione:\n\n' + Object.entries(info).map(([k, v]) => `${k}: ${v}`).join('\n'));
    }
    
    // Avvia quando la pagina è pronta
    window.addEventListener('load', function() {
        console.log('🔒 Testing HTTPS connection to OnlyOffice...');
        console.log('Document Server URL:', '<?php echo $documentServerUrl; ?>');
        
        // Verifica se DocsAPI è caricato
        if (typeof DocsAPI === 'undefined') {
            console.log('⏳ Waiting for DocsAPI to load...');
            
            // Controlla periodicamente
            let attempts = 0;
            const checkInterval = setInterval(function() {
                attempts++;
                if (typeof DocsAPI !== 'undefined') {
                    console.log('✅ DocsAPI loaded after ' + attempts + ' attempts');
                    clearInterval(checkInterval);
                    initEditor();
                } else if (attempts > 30) {
                    console.error('❌ DocsAPI failed to load after 30 seconds');
                    clearInterval(checkInterval);
                    document.getElementById('loading').innerHTML = `
                        <div class="error-message">
                            <h3>Timeout caricamento</h3>
                            <p>OnlyOffice API non disponibile dopo 30 secondi</p>
                            <p>Verifica che il container sia attivo e HTTPS funzioni su porta 8443</p>
                        </div>
                    `;
                    document.getElementById('connection-status').className = 'status error';
                    document.getElementById('connection-status').textContent = 'Timeout';
                }
            }, 1000);
        } else {
            console.log('✅ DocsAPI already available');
            initEditor();
        }
    });
    
    // Log per debug
    window.addEventListener('error', function(e) {
        console.error('Window Error:', e);
    });
</script>

</body>
</html>