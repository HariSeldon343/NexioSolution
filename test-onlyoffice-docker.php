<?php
// Test OnlyOffice con Docker - Soluzione file server
session_start();
require_once 'backend/config/config.php';
require_once 'backend/config/onlyoffice.config.php';

// Simula utente loggato per test
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'test_user';
    $_SESSION['azienda_id'] = 1;
}

// Genera CSRF token se non esiste
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$testDocId = 'test_' . time();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test OnlyOffice Docker</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        #editor-container { width: 100%; height: 600px; border: 2px solid #333; margin: 20px 0; }
        button { padding: 10px 20px; font-size: 16px; cursor: pointer; margin: 5px; }
        pre { background: #f4f4f4; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Test OnlyOffice Docker Integration</h1>

    <div class="test-section">
        <h2>1. Configurazione</h2>
        <p>OnlyOffice URL: <span class="info"><?php echo $ONLYOFFICE_DS_PUBLIC_URL; ?></span></p>
        <p>JWT Enabled: <span class="<?php echo $ONLYOFFICE_JWT_ENABLED ? 'error' : 'success'; ?>">
            <?php echo $ONLYOFFICE_JWT_ENABLED ? 'SI (disabilitare per test)' : 'NO (OK per test)'; ?>
        </span></p>
        <p>Documents Dir: <span class="info"><?php echo $ONLYOFFICE_DOCUMENTS_DIR; ?></span></p>
    </div>

    <div class="test-section">
        <h2>2. Test Connettività</h2>
        <button onclick="testConnection()">Test Connessione Server</button>
        <div id="connection-result"></div>
    </div>

    <div class="test-section">
        <h2>3. Crea e Apri Documento</h2>
        <button onclick="createAndOpenDocument()">Crea Nuovo Documento</button>
        <button onclick="openExistingDocument()">Apri Documento Esistente</button>
        <div id="doc-result"></div>
    </div>

    <div class="test-section">
        <h2>4. Editor OnlyOffice</h2>
        <div id="editor-container"></div>
    </div>

    <script src="<?php echo $ONLYOFFICE_DS_PUBLIC_URL; ?>/web-apps/apps/api/documents/api.js"></script>
    <script>
    // Test connessione al server OnlyOffice
    function testConnection() {
        fetch('<?php echo $ONLYOFFICE_DS_PUBLIC_URL; ?>/healthcheck')
            .then(response => {
                if (response.ok) {
                    document.getElementById('connection-result').innerHTML = 
                        '<span class="success">✅ Server OnlyOffice raggiungibile</span>';
                } else {
                    throw new Error('Server non raggiungibile');
                }
            })
            .catch(error => {
                document.getElementById('connection-result').innerHTML = 
                    '<span class="error">❌ Errore: ' + error.message + '</span>';
            });
    }

    // Crea nuovo documento e apri nell'editor
    function createAndOpenDocument() {
        const docId = 'new_' + Date.now();
        
        // Prima crea il file fisico tramite API
        fetch('backend/api/onlyoffice-prepare.php?file_id=' + docId, {
            headers: {
                'X-CSRF-Token': '<?php echo $_SESSION['csrf_token']; ?>'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('doc-result').innerHTML = 
                    '<span class="success">✅ Documento creato: ' + docId + '</span>';
                
                // Ora apri il documento nell'editor
                openInEditor(docId, 'Nuovo Documento.docx');
            } else {
                throw new Error(data.error || 'Errore creazione documento');
            }
        })
        .catch(error => {
            document.getElementById('doc-result').innerHTML = 
                '<span class="error">❌ Errore: ' + error.message + '</span>';
        });
    }

    // Apri documento esistente
    function openExistingDocument() {
        // Usa un ID di test esistente
        const docId = '<?php echo $testDocId; ?>';
        openInEditor(docId, 'Test Document.docx');
    }

    // Funzione per aprire il documento nell'editor OnlyOffice
    function openInEditor(docId, title) {
        console.log('Opening document:', docId);
        
        // Configurazione per OnlyOffice
        const config = {
            type: 'desktop',
            documentType: 'word',
            document: {
                title: title,
                // USA NGINX FILE SERVER per servire i documenti (porta 8083)
                url: 'http://localhost:8083/documents/' + docId + '.docx',
                fileType: 'docx',
                key: docId + '_' + Date.now(), // Chiave unica per ogni sessione
                permissions: {
                    edit: true,
                    download: true,
                    print: true,
                    fillForms: true,
                    review: true
                }
            },
            editorConfig: {
                mode: 'edit',
                // Callback URL che OnlyOffice userà per salvare (usa il callback semplificato)
                callbackUrl: 'http://host.docker.internal/piattaforma-collaborativa/backend/api/onlyoffice-callback-simple.php?id=' + docId,
                lang: 'it',
                user: {
                    id: '<?php echo $_SESSION['user_id']; ?>',
                    name: '<?php echo $_SESSION['username']; ?>'
                },
                customization: {
                    forcesave: true,
                    autosave: true,
                    chat: false,
                    comments: false,
                    help: true,
                    hideRightMenu: false,
                    logo: {
                        image: '',
                        imageEmbedded: ''
                    }
                }
            },
            events: {
                onReady: function() {
                    console.log('Editor ready');
                    document.getElementById('doc-result').innerHTML += 
                        '<br><span class="success">✅ Editor caricato</span>';
                },
                onError: function(event) {
                    console.error('Editor error:', event);
                    document.getElementById('doc-result').innerHTML += 
                        '<br><span class="error">❌ Errore editor: ' + JSON.stringify(event.data) + '</span>';
                },
                onDocumentStateChange: function(event) {
                    console.log('Document state changed:', event.data);
                },
                onRequestSaveAs: function(event) {
                    console.log('Save as requested:', event.data);
                }
            }
        };

        console.log('Config:', config);

        // Distruggi editor esistente se presente
        if (window.docEditor) {
            window.docEditor.destroyEditor();
        }

        // Crea nuovo editor
        try {
            window.docEditor = new DocsAPI.DocEditor("editor-container", config);
            console.log('Editor created successfully');
        } catch (error) {
            console.error('Error creating editor:', error);
            document.getElementById('doc-result').innerHTML = 
                '<span class="error">❌ Errore creazione editor: ' + error.message + '</span>';
        }
    }

    // Test iniziale automatico
    window.onload = function() {
        testConnection();
        
        // Crea un file di test
        fetch('backend/api/onlyoffice-prepare.php?file_id=<?php echo $testDocId; ?>', {
            headers: {
                'X-CSRF-Token': '<?php echo $_SESSION['csrf_token']; ?>'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Test document prepared:', data);
            }
        });
    };
    </script>

    <div class="test-section">
        <h2>Debug Info</h2>
        <pre>
Test Document ID: <?php echo $testDocId; ?>
File Path: <?php echo $ONLYOFFICE_DOCUMENTS_DIR . '/' . $testDocId . '.docx'; ?>
File Server URL: http://<?php echo $_SERVER['HTTP_HOST']; ?>/piattaforma-collaborativa/backend/api/onlyoffice-file-server.php?id=<?php echo $testDocId; ?>
OnlyOffice Server: <?php echo $ONLYOFFICE_DS_PUBLIC_URL; ?>
        </pre>
    </div>
</body>
</html>