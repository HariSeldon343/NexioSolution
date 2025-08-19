<?php
// Test per verificare che l'API onlyoffice-auth.php funzioni correttamente
require_once 'backend/middleware/Auth.php';
require_once 'backend/config/config.php';

$auth = Auth::getInstance();
$auth->requireAuth();

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Test OnlyOffice Token API</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            padding: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 { color: #2c3e50; text-align: center; }
        .test-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .info { color: #17a2b8; }
        pre {
            background: #2c3e50;
            color: #4ec9b0;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
        }
        button {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            margin: 10px;
        }
        button:hover {
            transform: scale(1.05);
        }
        .status-box {
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }
        .status-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .status-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Test OnlyOffice Token API</h1>
        
        <div class="test-section">
            <h2>Test 1: Genera Token per Documento</h2>
            <button onclick="testGenerateToken(22)">Test Token per Documento ID 22</button>
            <div id="token-result"></div>
        </div>

        <div class="test-section">
            <h2>Test 2: Verifica Stato Server OnlyOffice</h2>
            <button onclick="testServerStatus()">Verifica Stato Server</button>
            <div id="status-result"></div>
        </div>

        <div class="test-section">
            <h2>Test 3: API Diretta con Fetch</h2>
            <button onclick="testDirectAPI()">Test API Diretta</button>
            <div id="api-result"></div>
        </div>

        <div class="test-section">
            <h2>Test 4: Apri Editor Completo</h2>
            <button onclick="openEditor()">Apri OnlyOffice Editor (ID 22)</button>
        </div>

        <div class="test-section">
            <h2>Console Log</h2>
            <pre id="console-log">Pronto per il test...</pre>
        </div>
    </div>

    <script>
        function log(message, type = 'info') {
            const logEl = document.getElementById('console-log');
            const timestamp = new Date().toLocaleTimeString();
            const color = type === 'error' ? '#dc3545' : type === 'success' ? '#28a745' : '#17a2b8';
            logEl.innerHTML += `\n<span style="color: ${color}">[${timestamp}] ${message}</span>`;
            console.log(message);
        }

        function testGenerateToken(documentId) {
            log(`Testing token generation for document ${documentId}...`);
            
            fetch('backend/api/onlyoffice-auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'generate_token',
                    document_id: documentId
                })
            })
            .then(response => {
                log(`Response status: ${response.status}`);
                return response.text(); // Get text first to check what we receive
            })
            .then(text => {
                log(`Raw response: ${text.substring(0, 200)}...`);
                
                // Try to parse as JSON
                try {
                    const data = JSON.parse(text);
                    
                    const resultDiv = document.getElementById('token-result');
                    if (data.success) {
                        resultDiv.innerHTML = `
                            <div class="status-success">
                                <span class="success">✅ Token generato con successo!</span><br>
                                Token: <code>${data.token}</code><br>
                                Document URL: <code>${data.document_url || 'N/A'}</code><br>
                                Can Edit: ${data.can_edit ? 'Sì' : 'No'}
                            </div>
                        `;
                        log('Token generated successfully!', 'success');
                    } else {
                        resultDiv.innerHTML = `
                            <div class="status-error">
                                <span class="error">❌ Errore: ${data.error}</span>
                            </div>
                        `;
                        log(`Error: ${data.error}`, 'error');
                    }
                } catch (e) {
                    document.getElementById('token-result').innerHTML = `
                        <div class="status-error">
                            <span class="error">❌ ERRORE PARSING JSON!</span><br>
                            Questo è il problema che impedisce a OnlyOffice di funzionare.<br>
                            La risposta non è JSON valido:<br>
                            <pre>${text.substring(0, 500)}</pre>
                        </div>
                    `;
                    log(`JSON Parse Error: ${e.message}`, 'error');
                    log(`Response was: ${text.substring(0, 200)}`, 'error');
                }
            })
            .catch(error => {
                document.getElementById('token-result').innerHTML = `
                    <div class="status-error">
                        <span class="error">❌ Errore di rete: ${error.message}</span>
                    </div>
                `;
                log(`Network error: ${error.message}`, 'error');
            });
        }

        function testServerStatus() {
            log('Testing server status...');
            
            fetch('backend/api/onlyoffice-auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'check_server'
                })
            })
            .then(response => response.json())
            .then(data => {
                const resultDiv = document.getElementById('status-result');
                if (data.success && data.server_available) {
                    resultDiv.innerHTML = `
                        <div class="status-success">
                            <span class="success">✅ Server OnlyOffice attivo!</span><br>
                            URL: ${data.server_url}<br>
                            Version: ${data.version || 'N/A'}
                        </div>
                    `;
                    log('Server is available!', 'success');
                } else {
                    resultDiv.innerHTML = `
                        <div class="status-error">
                            <span class="error">❌ Server non disponibile</span>
                        </div>
                    `;
                    log('Server not available', 'error');
                }
            })
            .catch(error => {
                document.getElementById('status-result').innerHTML = `
                    <div class="status-error">
                        <span class="error">❌ Errore: ${error.message}</span>
                    </div>
                `;
                log(`Error: ${error.message}`, 'error');
            });
        }

        function testDirectAPI() {
            log('Testing direct API call...');
            
            // Test diretto senza autenticazione per vedere la risposta raw
            fetch('backend/api/onlyoffice-auth.php?test=1')
            .then(response => response.text())
            .then(text => {
                const resultDiv = document.getElementById('api-result');
                resultDiv.innerHTML = `
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                        <strong>Risposta Raw:</strong><br>
                        <pre style="background: white; padding: 10px;">${text.substring(0, 500)}</pre>
                    </div>
                `;
                log(`API test complete. Response length: ${text.length} bytes`);
            })
            .catch(error => {
                document.getElementById('api-result').innerHTML = `
                    <div class="status-error">
                        <span class="error">❌ Errore: ${error.message}</span>
                    </div>
                `;
                log(`Error: ${error.message}`, 'error');
            });
        }

        function openEditor() {
            log('Opening OnlyOffice editor...', 'success');
            window.open('/piattaforma-collaborativa/onlyoffice-editor.php?id=22', '_blank');
        }

        // Test automatico al caricamento
        window.addEventListener('DOMContentLoaded', function() {
            log('Test page loaded. Ready for testing.', 'success');
            log('Click the buttons above to test different API endpoints.');
        });
    </script>
</body>
</html>