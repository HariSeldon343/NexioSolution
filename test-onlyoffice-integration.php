<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test OnlyOffice Integration</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .test-info {
            background: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .test-info h3 {
            margin-top: 0;
            color: #495057;
        }
        .test-info p {
            margin: 5px 0;
        }
        .editor-container {
            width: 100%;
            height: 600px;
            border: 2px solid #dee2e6;
            border-radius: 5px;
            margin-top: 20px;
            position: relative;
        }
        #onlyoffice-editor {
            width: 100%;
            height: 100%;
        }
        .status {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
        }
        .status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .status.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        .loading {
            text-align: center;
            padding: 50px;
            color: #6c757d;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007bff;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Test Integrazione OnlyOffice</h1>
        
        <div class="test-info">
            <h3>Informazioni Test</h3>
            <p><strong>Documento ID:</strong> 22</p>
            <p><strong>Nome File:</strong> Test Document.docx</p>
            <p><strong>Server OnlyOffice:</strong> http://localhost:8082</p>
            <p><strong>API Endpoint:</strong> http://host.docker.internal/piattaforma-collaborativa/backend/api/onlyoffice-document-test.php</p>
            <p><strong>JWT:</strong> Disabilitato per testing</p>
        </div>
        
        <div id="status-container"></div>
        
        <div class="editor-container">
            <div id="onlyoffice-editor">
                <div class="loading">
                    <div class="spinner"></div>
                    <p>Caricamento editor OnlyOffice...</p>
                </div>
            </div>
        </div>
    </div>

    <script type="text/javascript" src="http://localhost:8082/web-apps/apps/api/documents/api.js"></script>
    <script type="text/javascript">
        function addStatus(message, type = 'info') {
            const container = document.getElementById('status-container');
            const status = document.createElement('div');
            status.className = 'status ' + type;
            status.textContent = message;
            container.appendChild(status);
            console.log(`[${type.toUpperCase()}] ${message}`);
        }
        
        window.onload = function() {
            try {
                addStatus('Inizializzazione editor OnlyOffice...', 'warning');
                
                // Configuration for OnlyOffice
                const config = {
                    "type": "desktop",
                    "documentType": "word",
                    "document": {
                        "title": "Test Document.docx",
                        "url": "http://host.docker.internal/piattaforma-collaborativa/backend/api/onlyoffice-document-test.php?id=22",
                        "fileType": "docx",
                        "key": "test_doc_22_" + Date.now(),
                        "permissions": {
                            "comment": true,
                            "download": true,
                            "edit": true,
                            "fillForms": true,
                            "print": true,
                            "review": true
                        }
                    },
                    "editorConfig": {
                        "mode": "edit",
                        "lang": "it",
                        "user": {
                            "id": "1",
                            "name": "Test User"
                        },
                        "customization": {
                            "autosave": true,
                            "chat": false,
                            "comments": true,
                            "compactHeader": false,
                            "compactToolbar": false,
                            "forcesave": true,
                            "help": true,
                            "hideRightMenu": false,
                            "toolbarNoTabs": false,
                            "unit": "cm",
                            "zoom": 100
                        }
                    },
                    "events": {
                        "onReady": function() {
                            addStatus('Editor OnlyOffice caricato con successo!', 'success');
                        },
                        "onError": function(event) {
                            addStatus('Errore OnlyOffice: ' + JSON.stringify(event), 'error');
                        },
                        "onWarning": function(event) {
                            addStatus('Warning OnlyOffice: ' + JSON.stringify(event), 'warning');
                        },
                        "onInfo": function(event) {
                            console.log("Info:", event);
                        }
                    }
                };
                
                // Log configuration
                console.log('OnlyOffice Configuration:', config);
                
                // Initialize OnlyOffice
                if (typeof DocsAPI !== 'undefined') {
                    addStatus('DocsAPI trovato, creazione editor...', 'warning');
                    window.docEditor = new DocsAPI.DocEditor("onlyoffice-editor", config);
                    addStatus('Editor creato, in attesa di caricamento...', 'warning');
                } else {
                    addStatus('ERRORE: DocsAPI non trovato! Verificare che OnlyOffice sia raggiungibile su http://localhost:8082', 'error');
                }
                
            } catch (error) {
                addStatus('Errore JavaScript: ' + error.message, 'error');
                console.error('Initialization error:', error);
            }
        };
    </script>
</body>
</html>