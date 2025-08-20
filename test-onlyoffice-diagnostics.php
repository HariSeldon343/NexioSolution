<?php
/**
 * Diagnostica completa per OnlyOffice - Trova la configurazione corretta
 */

// Ottieni tutti gli IP possibili
function getAllPossibleIPs() {
    $ips = [];
    
    // IP locale
    $ips['localhost'] = '127.0.0.1';
    
    // IP della macchina
    $hostname = gethostname();
    $localIP = gethostbyname($hostname);
    if ($localIP !== $hostname) {
        $ips['hostname'] = $localIP;
    }
    
    // IP dalle interfacce di rete
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        exec('ipconfig', $output);
        foreach ($output as $line) {
            if (preg_match('/IPv4.*?:\s*(\d+\.\d+\.\d+\.\d+)/', $line, $matches)) {
                if ($matches[1] !== '127.0.0.1') {
                    $ips['network_' . count($ips)] = $matches[1];
                }
            }
        }
    }
    
    // Docker Gateway (comune)
    $ips['docker_gateway'] = '172.17.0.1';
    
    // Host Docker Internal
    $ips['host_docker_internal'] = 'host.docker.internal';
    
    return $ips;
}

$docId = $_GET['doc'] ?? 22;
$ips = getAllPossibleIPs();

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>OnlyOffice Diagnostica Completa</title>
    <style>
        body {
            font-family: monospace;
            background: #1a1a1a;
            color: #0f0;
            padding: 20px;
        }
        h1, h2 { color: #0ff; }
        .test-section {
            background: #000;
            border: 1px solid #0f0;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .success { color: #0f0; }
        .error { color: #f00; }
        .warning { color: #ff0; }
        .code {
            background: #222;
            padding: 10px;
            border-left: 3px solid #0ff;
            margin: 10px 0;
            overflow-x: auto;
        }
        button {
            background: #0f0;
            color: #000;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            font-weight: bold;
            margin: 5px;
        }
        button:hover { background: #0ff; }
        .ip-test {
            display: inline-block;
            margin: 5px;
            padding: 8px;
            border: 1px solid #333;
        }
        .ip-test.working { border-color: #0f0; background: #001a00; }
        .ip-test.failed { border-color: #f00; background: #1a0000; }
    </style>
</head>
<body>

<h1>🔧 OnlyOffice Diagnostica Completa</h1>

<div class="test-section">
    <h2>1. Configurazione Sistema</h2>
    <?php
    echo "<p>PHP Version: " . PHP_VERSION . "</p>";
    echo "<p>Server: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
    echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
    echo "<p>Script Path: " . __FILE__ . "</p>";
    ?>
</div>

<div class="test-section">
    <h2>2. Test IP Disponibili</h2>
    <p>Testa quale IP può essere raggiunto dal container Docker:</p>
    
    <?php foreach ($ips as $name => $ip): ?>
        <div class="ip-test" id="ip-<?php echo $name; ?>">
            <strong><?php echo $name; ?>:</strong> <?php echo $ip; ?><br>
            <button onclick="testIP('<?php echo $ip; ?>', '<?php echo $name; ?>')">Test dal Browser</button>
            <button onclick="copyDockerCommand('<?php echo $ip; ?>')">Copia Comando Docker</button>
            <div id="result-<?php echo $name; ?>"></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="test-section">
    <h2>3. URLs da Testare per Documento <?php echo $docId; ?></h2>
    <div class="code">
    <?php
    foreach ($ips as $name => $ip) {
        echo "// Con $name ($ip)\n";
        echo "Document URL: http://$ip/piattaforma-collaborativa/backend/api/onlyoffice-document.php?doc=$docId\n";
        echo "Callback URL: http://$ip/piattaforma-collaborativa/backend/api/onlyoffice-callback.php?doc=$docId\n\n";
    }
    ?>
    </div>
</div>

<div class="test-section">
    <h2>4. Test Container Docker</h2>
    <p>Esegui questi comandi in PowerShell/CMD per testare dal container:</p>
    <div class="code" id="docker-commands">
# Trova il nome del container
docker ps --format "table {{.Names}}" | findstr -i onlyoffice

# Sostituisci CONTAINER_NAME con il nome trovato sopra
# Test con vari IP (sostituisci IP_ADDRESS con uno degli IP sopra che funziona)
docker exec CONTAINER_NAME curl -I http://IP_ADDRESS/piattaforma-collaborativa/backend/api/onlyoffice-document.php?doc=<?php echo $docId; ?>
    </div>
</div>

<div class="test-section">
    <h2>5. Configurazione Suggerita</h2>
    <div id="suggested-config" class="code">
        <p class="warning">⏳ Esegui prima i test sopra per determinare la configurazione corretta...</p>
    </div>
</div>

<div class="test-section">
    <h2>6. Test OnlyOffice API</h2>
    <button onclick="testOnlyOfficeAPI()">Test API OnlyOffice</button>
    <div id="api-test-result"></div>
</div>

<script>
function testIP(ip, name) {
    const resultDiv = document.getElementById('result-' + name);
    const testUrl = `http://${ip}/piattaforma-collaborativa/backend/api/onlyoffice-document.php?doc=<?php echo $docId; ?>`;
    
    resultDiv.innerHTML = '<span class="warning">Testing...</span>';
    
    // Test via fetch (dal browser)
    fetch('/piattaforma-collaborativa/backend/api/test-connectivity.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({url: testUrl})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            resultDiv.innerHTML = '<span class="success">✓ Funziona!</span>';
            document.getElementById('ip-' + name).classList.add('working');
            updateSuggestedConfig(ip, name);
        } else {
            resultDiv.innerHTML = '<span class="error">✗ Fallito</span>';
            document.getElementById('ip-' + name).classList.add('failed');
        }
    })
    .catch(err => {
        resultDiv.innerHTML = '<span class="error">✗ Errore: ' + err + '</span>';
    });
}

function copyDockerCommand(ip) {
    const cmd = `docker exec nexio-onlyoffice curl -I http://${ip}/piattaforma-collaborativa/backend/api/onlyoffice-document.php?doc=<?php echo $docId; ?>`;
    navigator.clipboard.writeText(cmd);
    alert('Comando copiato negli appunti!');
}

function updateSuggestedConfig(ip, name) {
    const configDiv = document.getElementById('suggested-config');
    configDiv.innerHTML = `
        <h3 class="success">✓ Configurazione Funzionante Trovata!</h3>
        <p>Usa <strong>${name} (${ip})</strong> in onlyoffice-editor.php:</p>
        <pre>
// In onlyoffice-editor.php, modifica le righe del documentUrl e callbackUrl:

\$baseUrl = 'http://${ip}/piattaforma-collaborativa';
\$documentUrl = \$baseUrl . '/backend/api/onlyoffice-document.php?doc=' . \$docId;
\$callbackUrl = \$baseUrl . '/backend/api/onlyoffice-callback.php?doc=' . \$docId;
        </pre>
        <p>Oppure crea una costante in backend/config/config.php:</p>
        <pre>
// Aggiungi in config.php
define('ONLYOFFICE_HOST_URL', 'http://${ip}/piattaforma-collaborativa');
        </pre>
    `;
}

function testOnlyOfficeAPI() {
    const resultDiv = document.getElementById('api-test-result');
    resultDiv.innerHTML = '<p class="warning">Testing OnlyOffice API...</p>';
    
    // Test se l'API è raggiungibile
    const script = document.createElement('script');
    script.src = 'http://localhost:8080/web-apps/apps/api/documents/api.js';
    script.onload = () => {
        if (typeof DocsAPI !== 'undefined') {
            resultDiv.innerHTML = '<p class="success">✓ OnlyOffice API caricata correttamente!</p>';
        } else {
            resultDiv.innerHTML = '<p class="error">✗ API caricata ma DocsAPI non definito</p>';
        }
    };
    script.onerror = () => {
        resultDiv.innerHTML = '<p class="error">✗ Impossibile caricare OnlyOffice API da localhost:8080</p>';
    };
    document.head.appendChild(script);
}

// Auto-test all IPs on load
window.onload = function() {
    console.log('Starting automatic IP tests...');
    <?php foreach ($ips as $name => $ip): ?>
    setTimeout(() => testIP('<?php echo $ip; ?>', '<?php echo $name; ?>'), <?php echo 1000 * array_search($name, array_keys($ips)); ?>);
    <?php endforeach; ?>
};
</script>

</body>
</html>