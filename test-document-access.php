<?php
/**
 * Test Document Access - Verifica accesso ai documenti da OnlyOffice
 */

// Test URLs per accesso ai documenti
$testUrls = [
    'localhost' => 'http://localhost/piattaforma-collaborativa/documents/onlyoffice/new.docx',
    'host.docker.internal' => 'http://host.docker.internal/piattaforma-collaborativa/documents/onlyoffice/new.docx',
    '127.0.0.1' => 'http://127.0.0.1/piattaforma-collaborativa/documents/onlyoffice/new.docx',
    'direct-file-server' => 'http://localhost:8083/documents/onlyoffice/new.docx',
    'machine-ip' => 'http://192.168.1.100/piattaforma-collaborativa/documents/onlyoffice/new.docx' // Sostituisci con IP della tua macchina
];

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Test Document Access</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .test-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        h1 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        .test-result {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            border-left: 4px solid;
        }
        .test-result.success {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        .test-result.error {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        .test-result.warning {
            background: #fff3cd;
            border-color: #ffc107;
            color: #856404;
        }
        .url-code {
            font-family: 'Courier New', monospace;
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            word-break: break-all;
        }
        .info-box {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        button {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px;
        }
        button:hover {
            background: #2980b9;
        }
        .recommendations {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
        }
        .recommendations h3 {
            color: #004085;
            margin-top: 0;
        }
        .recommendations ul {
            margin: 10px 0;
        }
        .recommendations li {
            margin: 5px 0;
        }
        .code-block {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            overflow-x: auto;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1>🔍 Test Accesso Documenti OnlyOffice</h1>
        
        <div class="info-box">
            <strong>ℹ️ Info:</strong> Questo script verifica quali URL possono essere utilizzati per accedere ai documenti dal container Docker di OnlyOffice.
        </div>
        
        <h2>Test Accesso URLs</h2>
        <div id="test-results">
            <?php foreach ($testUrls as $name => $url): ?>
            <div class="test-result warning">
                <strong><?php echo $name; ?>:</strong><br>
                <span class="url-code"><?php echo $url; ?></span><br>
                <small>Status: Da testare nel browser o con curl</small>
            </div>
            <?php endforeach; ?>
        </div>
        
        <h2>Test con JavaScript (CORS)</h2>
        <button onclick="testAllUrls()">🚀 Testa Tutti gli URL</button>
        <button onclick="checkDockerNetwork()">🐳 Verifica Network Docker</button>
        <div id="js-results"></div>
        
        <div class="recommendations">
            <h3>📋 Raccomandazioni per Windows Docker Desktop</h3>
            <ul>
                <li><strong>Usa host.docker.internal:</strong> Questo è il modo consigliato per accedere all'host da container Docker su Windows</li>
                <li><strong>Verifica il file esista:</strong> Assicurati che <code>new.docx</code> esista in <code>documents/onlyoffice/</code></li>
                <li><strong>Controlla i permessi:</strong> Il file deve essere leggibile dal web server</li>
                <li><strong>Network Docker:</strong> Verifica che i container siano sulla stessa rete</li>
            </ul>
            
            <h4>Configurazione Consigliata:</h4>
            <div class="code-block">
// In test-onlyoffice-http-working.php, modifica l'URL del documento:
document: {
    url: "http://host.docker.internal/piattaforma-collaborativa/documents/onlyoffice/new.docx",
    // Oppure se hai un file server dedicato:
    // url: "http://localhost:8083/documents/onlyoffice/new.docx",
}
            </div>
            
            <h4>Test con curl dal container:</h4>
            <div class="code-block">
# Entra nel container OnlyOffice
docker exec -it onlyoffice-document-server bash

# Testa l'accesso al documento
curl -I http://host.docker.internal/piattaforma-collaborativa/documents/onlyoffice/new.docx

# O con wget
wget --spider http://host.docker.internal/piattaforma-collaborativa/documents/onlyoffice/new.docx
            </div>
        </div>
        
        <h2>Informazioni Sistema</h2>
        <div class="test-result">
            <strong>Document Path:</strong> <?php echo realpath('documents/onlyoffice'); ?><br>
            <strong>Files in directory:</strong>
            <ul>
                <?php
                $files = glob('documents/onlyoffice/*.docx');
                foreach ($files as $file) {
                    $size = filesize($file);
                    $modified = date('Y-m-d H:i:s', filemtime($file));
                    echo "<li><code>" . basename($file) . "</code> - {$size} bytes - Modified: {$modified}</li>";
                }
                ?>
            </ul>
        </div>
    </div>
    
    <script>
        function testAllUrls() {
            const resultsDiv = document.getElementById('js-results');
            resultsDiv.innerHTML = '<h3>Test JavaScript (CORS Check)</h3>';
            
            const urls = <?php echo json_encode($testUrls); ?>;
            
            Object.entries(urls).forEach(([name, url]) => {
                // Test con fetch (soggetto a CORS)
                fetch(url, { 
                    method: 'HEAD',
                    mode: 'no-cors' // Evita errori CORS nel browser
                })
                .then(response => {
                    addResult(name, url, 'Richiesta inviata (no-cors mode)', 'warning');
                })
                .catch(error => {
                    addResult(name, url, 'Errore: ' + error.message, 'error');
                });
                
                // Test con XMLHttpRequest per più dettagli
                const xhr = new XMLHttpRequest();
                xhr.open('HEAD', url, true);
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 200) {
                            addResult(name + ' (XHR)', url, 'Accessibile! Status: ' + xhr.status, 'success');
                        } else if (xhr.status === 0) {
                            addResult(name + ' (XHR)', url, 'CORS bloccato o non raggiungibile', 'warning');
                        } else {
                            addResult(name + ' (XHR)', url, 'Status: ' + xhr.status, 'error');
                        }
                    }
                };
                xhr.send();
            });
        }
        
        function addResult(name, url, status, type) {
            const resultsDiv = document.getElementById('js-results');
            const resultDiv = document.createElement('div');
            resultDiv.className = 'test-result ' + type;
            resultDiv.innerHTML = `
                <strong>${name}:</strong><br>
                <span class="url-code">${url}</span><br>
                <small>Status: ${status}</small>
            `;
            resultsDiv.appendChild(resultDiv);
        }
        
        function checkDockerNetwork() {
            const resultsDiv = document.getElementById('js-results');
            resultsDiv.innerHTML = '<h3>Docker Network Info</h3>';
            
            const info = `
                <div class="test-result info">
                    <strong>Per verificare la configurazione Docker:</strong><br>
                    <pre>
# Lista container in esecuzione
docker ps

# Verifica network
docker network ls
docker network inspect bridge

# Verifica connettività dal container
docker exec -it onlyoffice-document-server ping host.docker.internal -c 4

# Verifica risoluzione DNS
docker exec -it onlyoffice-document-server nslookup host.docker.internal
                    </pre>
                </div>
            `;
            resultsDiv.innerHTML += info;
        }
    </script>
</body>
</html>