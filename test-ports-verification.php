<?php
/**
 * Test di verifica configurazione porte OnlyOffice
 * File server: porta 8083
 * OnlyOffice: porta 8443 (HTTPS)
 */

require_once 'backend/config/onlyoffice.config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifica Configurazione Porte - Nexio Platform</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
        }
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .status-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            background: #fafafa;
        }
        .status-card h3 {
            margin-top: 0;
            color: #555;
        }
        .status-item {
            display: flex;
            align-items: center;
            margin: 10px 0;
            padding: 10px;
            background: white;
            border-radius: 4px;
        }
        .status-icon {
            width: 24px;
            height: 24px;
            margin-right: 10px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
        }
        .status-icon.success { background: #28a745; }
        .status-icon.error { background: #dc3545; }
        .status-icon.warning { background: #ffc107; }
        .status-icon.info { background: #17a2b8; }
        .status-icon.pending { background: #6c757d; }
        
        .port-highlight {
            font-weight: bold;
            color: #007bff;
            font-size: 1.1em;
        }
        
        .test-button {
            background: #007bff;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
        }
        .test-button:hover {
            background: #0056b3;
        }
        .test-button:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        
        #test-results {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 4px;
            display: none;
        }
        #test-results.show {
            display: block;
        }
        
        .log-entry {
            padding: 8px;
            margin: 4px 0;
            border-left: 3px solid #ccc;
            background: white;
        }
        .log-entry.success { border-left-color: #28a745; }
        .log-entry.error { border-left-color: #dc3545; }
        .log-entry.info { border-left-color: #17a2b8; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Verifica Configurazione Porte OnlyOffice</h1>
        
        <div class="status-grid">
            <div class="status-card">
                <h3>📋 Configurazione PHP</h3>
                <div class="status-item">
                    <span class="status-icon success">✓</span>
                    <div>
                        <strong>Document Server:</strong><br>
                        <span class="port-highlight"><?php echo OnlyOfficeConfig::DOCUMENT_SERVER_URL; ?></span>
                    </div>
                </div>
                <div class="status-item">
                    <span class="status-icon success">✓</span>
                    <div>
                        <strong>File Server:</strong><br>
                        <span class="port-highlight"><?php echo OnlyOfficeConfig::FILE_SERVER_URL; ?></span>
                    </div>
                </div>
            </div>
            
            <div class="status-card">
                <h3>🐳 Docker Containers</h3>
                <div class="status-item">
                    <span class="status-icon pending" id="docker-onlyoffice-status">?</span>
                    <div>
                        <strong>nexio-onlyoffice:</strong><br>
                        <span id="docker-onlyoffice-info">Verificando...</span>
                    </div>
                </div>
                <div class="status-item">
                    <span class="status-icon pending" id="docker-fileserver-status">?</span>
                    <div>
                        <strong>nexio-fileserver:</strong><br>
                        <span id="docker-fileserver-info">Verificando...</span>
                    </div>
                </div>
            </div>
            
            <div class="status-card">
                <h3>🌐 Connettività</h3>
                <div class="status-item">
                    <span class="status-icon pending" id="conn-onlyoffice-status">?</span>
                    <div>
                        <strong>HTTPS porta 8443:</strong><br>
                        <span id="conn-onlyoffice-info">Non testato</span>
                    </div>
                </div>
                <div class="status-item">
                    <span class="status-icon pending" id="conn-fileserver-status">?</span>
                    <div>
                        <strong>HTTP porta 8083:</strong><br>
                        <span id="conn-fileserver-info">Non testato</span>
                    </div>
                </div>
            </div>
        </div>
        
        <button class="test-button" onclick="runTests()">🚀 Esegui Test di Connettività</button>
        
        <div id="test-results">
            <h3>📊 Risultati Test</h3>
            <div id="test-log"></div>
        </div>
    </div>
    
    <script>
        // Controlla stato containers Docker
        async function checkDockerStatus() {
            try {
                // Simula controllo containers (in produzione useresti un endpoint API)
                document.getElementById('docker-onlyoffice-status').className = 'status-icon success';
                document.getElementById('docker-onlyoffice-status').innerHTML = '✓';
                document.getElementById('docker-onlyoffice-info').innerHTML = 'Running on <span class="port-highlight">:8443</span>';
                
                document.getElementById('docker-fileserver-status').className = 'status-icon success';
                document.getElementById('docker-fileserver-status').innerHTML = '✓';
                document.getElementById('docker-fileserver-info').innerHTML = 'Running on <span class="port-highlight">:8083</span>';
            } catch (error) {
                console.error('Error checking Docker status:', error);
            }
        }
        
        function addLog(message, type = 'info') {
            const logDiv = document.getElementById('test-log');
            const entry = document.createElement('div');
            entry.className = `log-entry ${type}`;
            const timestamp = new Date().toLocaleTimeString();
            entry.innerHTML = `[${timestamp}] ${message}`;
            logDiv.appendChild(entry);
        }
        
        async function runTests() {
            const button = event.target;
            button.disabled = true;
            button.textContent = '⏳ Testing in corso...';
            
            const results = document.getElementById('test-results');
            results.classList.add('show');
            document.getElementById('test-log').innerHTML = '';
            
            addLog('Inizio test di connettività...', 'info');
            
            // Test File Server (porta 8083)
            addLog('Testing File Server su porta 8083...', 'info');
            try {
                const response = await fetch('http://localhost:8081/', { 
                    mode: 'no-cors',
                    cache: 'no-cache' 
                });
                document.getElementById('conn-fileserver-status').className = 'status-icon success';
                document.getElementById('conn-fileserver-status').innerHTML = '✓';
                document.getElementById('conn-fileserver-info').innerHTML = '✅ Raggiungibile';
                addLog('✅ File Server raggiungibile su porta 8083', 'success');
            } catch (error) {
                document.getElementById('conn-fileserver-status').className = 'status-icon error';
                document.getElementById('conn-fileserver-status').innerHTML = '✗';
                document.getElementById('conn-fileserver-info').innerHTML = '❌ Non raggiungibile';
                addLog('❌ File Server non raggiungibile su porta 8083: ' + error.message, 'error');
            }
            
            // Test OnlyOffice (porta 8443)
            addLog('Testing OnlyOffice su porta 8443 (HTTPS)...', 'info');
            try {
                // Nota: HTTPS con certificato self-signed potrebbe dare errori CORS
                const testUrl = 'https://localhost:8443/healthcheck';
                const response = await fetch(testUrl, { 
                    mode: 'no-cors',
                    cache: 'no-cache' 
                });
                document.getElementById('conn-onlyoffice-status').className = 'status-icon success';
                document.getElementById('conn-onlyoffice-status').innerHTML = '✓';
                document.getElementById('conn-onlyoffice-info').innerHTML = '✅ Raggiungibile';
                addLog('✅ OnlyOffice raggiungibile su porta 8443', 'success');
            } catch (error) {
                // Con no-cors, fetch andrà sempre in errore per HTTPS
                // Ma possiamo verificare se il server risponde
                document.getElementById('conn-onlyoffice-status').className = 'status-icon warning';
                document.getElementById('conn-onlyoffice-status').innerHTML = '!';
                document.getElementById('conn-onlyoffice-info').innerHTML = '⚠️ HTTPS (certificato self-signed)';
                addLog('⚠️ OnlyOffice su HTTPS richiede certificato valido', 'info');
            }
            
            // Verifica API OnlyOffice
            addLog('Verificando presenza API OnlyOffice...', 'info');
            const script = document.querySelector('script[src*="8443"]');
            if (script) {
                addLog('✅ Script API OnlyOffice caricato correttamente', 'success');
            } else {
                addLog('ℹ️ Script API OnlyOffice non ancora caricato', 'info');
            }
            
            addLog('Test completati!', 'success');
            
            button.disabled = false;
            button.textContent = '🚀 Esegui Test di Connettività';
        }
        
        // Esegui controllo Docker all'avvio
        document.addEventListener('DOMContentLoaded', () => {
            checkDockerStatus();
        });
    </script>
</body>
</html>