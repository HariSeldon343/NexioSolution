<?php
/**
 * Test per verificare che l'URL OnlyOffice sia corretto dopo il fix
 */

// Include OnlyOffice configuration
require_once __DIR__ . '/backend/config/onlyoffice.config.php';

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifica Fix OnlyOffice</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #333; }
        .info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .test-result {
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            border-left: 4px solid;
        }
        .test-result.success {
            background: #e8f5e9;
            border-color: #4caf50;
            color: #2e7d32;
        }
        .test-result.error {
            background: #ffebee;
            border-color: #f44336;
            color: #c62828;
        }
        .test-result.warning {
            background: #fff3e0;
            border-color: #ff9800;
            color: #e65100;
        }
        code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        #editor {
            width: 100%;
            height: 500px;
            border: 2px solid #2196f3;
            margin-top: 20px;
        }
        button {
            background: #2196f3;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 5px;
        }
        button:hover {
            background: #1976d2;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Verifica Fix OnlyOffice URLs</h1>
        
        <div class="info">
            <h3>Configurazione Corrente:</h3>
            <p><strong>ONLYOFFICE_DS_PUBLIC_URL:</strong> <code><?php echo htmlspecialchars($ONLYOFFICE_DS_PUBLIC_URL); ?></code></p>
            <p><strong>ONLYOFFICE_DS_INTERNAL_URL:</strong> <code><?php echo htmlspecialchars($ONLYOFFICE_DS_INTERNAL_URL); ?></code></p>
            <p><strong>JWT Enabled:</strong> <code><?php echo $ONLYOFFICE_JWT_ENABLED ? 'SI' : 'NO'; ?></code></p>
        </div>

        <div id="test-results">
            <h3>Test di Connettività:</h3>
        </div>

        <button onclick="testAPI()">🔍 Test API JavaScript</button>
        <button onclick="testDocsAPI()">📝 Test DocsAPI Object</button>
        <button onclick="loadEditor()">🚀 Carica Editor</button>

        <div id="editor"></div>
    </div>

    <!-- OnlyOffice API con URL corretto dalla configurazione -->
    <script type="text/javascript" src="<?php echo $ONLYOFFICE_DS_PUBLIC_URL; ?>/web-apps/apps/api/documents/api.js"></script>
    
    <script>
        const ONLYOFFICE_URL = '<?php echo $ONLYOFFICE_DS_PUBLIC_URL; ?>';
        
        function addTestResult(test, success, message) {
            const resultsDiv = document.getElementById('test-results');
            const resultDiv = document.createElement('div');
            resultDiv.className = `test-result ${success ? 'success' : 'error'}`;
            resultDiv.innerHTML = `
                <strong>${success ? '✅' : '❌'} ${test}:</strong><br>
                ${message}
            `;
            resultsDiv.appendChild(resultDiv);
        }

        // Test immediato al caricamento
        window.addEventListener('load', function() {
            // Test 1: Verifica che l'URL sia corretto
            if (ONLYOFFICE_URL === 'http://localhost:8082') {
                addTestResult('URL Configuration', true, `URL corretto: ${ONLYOFFICE_URL}`);
            } else {
                addTestResult('URL Configuration', false, `URL non corretto: ${ONLYOFFICE_URL}`);
            }

            // Test 2: Verifica che DocsAPI sia definito
            if (typeof DocsAPI !== 'undefined') {
                addTestResult('DocsAPI Object', true, 'DocsAPI è definito e disponibile');
            } else {
                addTestResult('DocsAPI Object', false, 'DocsAPI NON è definito - Script non caricato');
            }
        });

        function testAPI() {
            fetch(ONLYOFFICE_URL + '/web-apps/apps/api/documents/api.js')
                .then(response => {
                    if (response.ok) {
                        addTestResult('API JavaScript', true, `API raggiungibile a ${ONLYOFFICE_URL}`);
                    } else {
                        addTestResult('API JavaScript', false, `Errore HTTP: ${response.status}`);
                    }
                })
                .catch(error => {
                    addTestResult('API JavaScript', false, `Errore di rete: ${error.message}`);
                });
        }

        function testDocsAPI() {
            if (typeof DocsAPI !== 'undefined') {
                addTestResult('DocsAPI Test', true, 'DocsAPI è disponibile e pronto');
                console.log('DocsAPI object:', DocsAPI);
            } else {
                addTestResult('DocsAPI Test', false, 'DocsAPI non è ancora caricato');
            }
        }

        function loadEditor() {
            if (typeof DocsAPI === 'undefined') {
                addTestResult('Editor Load', false, 'DocsAPI non disponibile - impossibile caricare editor');
                return;
            }

            const config = {
                type: 'desktop',
                documentType: 'word',
                document: {
                    title: 'Test Document',
                    url: 'https://api.onlyoffice.com/editors/assets/docs/samples/sample.docx',
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
                    }
                }
            };

            try {
                new DocsAPI.DocEditor('editor', config);
                addTestResult('Editor Load', true, 'Editor caricato con successo');
            } catch (error) {
                addTestResult('Editor Load', false, `Errore: ${error.message}`);
            }
        }
    </script>
</body>
</html>