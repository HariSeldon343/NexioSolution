<?php
/**
 * Test OnlyOffice Server Status - Verifica DEFINITIVA
 * Questo script verifica la connessione e lo stato del server OnlyOffice
 */

// Configurazione semplice
$config = [
    'server_url' => 'http://localhost:8082',
    'jwt_enabled' => false,
    'test_document' => __DIR__ . '/documents/test/sample.docx'
];

// Array per i risultati
$results = [];

// Funzione helper per test
function testUrl($url, $description) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'method' => 'GET',
            'ignore_errors' => true
        ]
    ]);
    
    $result = @file_get_contents($url, false, $context);
    $headers = $http_response_header ?? [];
    $status = 'error';
    $message = 'Non raggiungibile';
    
    if ($result !== false) {
        // Estrai status code
        if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $headers[0] ?? '', $matches)) {
            $statusCode = intval($matches[1]);
            if ($statusCode === 200) {
                $status = 'success';
                $message = 'OK (200)';
            } else {
                $status = 'warning';
                $message = "Status: $statusCode";
            }
        }
    }
    
    return [
        'test' => $description,
        'url' => $url,
        'status' => $status,
        'message' => $message,
        'response_preview' => $result ? substr($result, 0, 200) : null
    ];
}

// Test 1: Server principale
$results[] = testUrl($config['server_url'], 'Server OnlyOffice');

// Test 2: API JavaScript
$results[] = testUrl($config['server_url'] . '/web-apps/apps/api/documents/api.js', 'API JavaScript');

// Test 3: Healthcheck
$healthUrl = $config['server_url'] . '/healthcheck';
$healthResult = testUrl($healthUrl, 'Healthcheck');
if ($healthResult['response_preview'] && strpos($healthResult['response_preview'], 'true') !== false) {
    $healthResult['status'] = 'success';
    $healthResult['message'] = 'Server attivo';
}
$results[] = $healthResult;

// Test 4: Info endpoint
$results[] = testUrl($config['server_url'] . '/info', 'Info Endpoint');

// Test 5: Verifica documento di test
if (file_exists($config['test_document'])) {
    $results[] = [
        'test' => 'Documento di test',
        'url' => 'Local file',
        'status' => 'success',
        'message' => 'File esistente: ' . basename($config['test_document']),
        'response_preview' => 'Size: ' . filesize($config['test_document']) . ' bytes'
    ];
} else {
    $results[] = [
        'test' => 'Documento di test',
        'url' => 'Local file',
        'status' => 'error',
        'message' => 'File non trovato',
        'response_preview' => null
    ];
}

// Test 6: Verifica se Docker è in esecuzione
$dockerRunning = false;
exec('docker ps 2>&1', $output, $returnCode);
if ($returnCode === 0) {
    foreach ($output as $line) {
        if (strpos($line, 'onlyoffice') !== false) {
            $dockerRunning = true;
            break;
        }
    }
}

$results[] = [
    'test' => 'Docker Container',
    'url' => 'docker ps',
    'status' => $dockerRunning ? 'success' : 'error',
    'message' => $dockerRunning ? 'Container OnlyOffice attivo' : 'Container non trovato',
    'response_preview' => null
];

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OnlyOffice Server Status</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        .test-grid {
            display: grid;
            gap: 15px;
            margin-top: 20px;
        }
        .test-item {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 15px;
            align-items: center;
            transition: transform 0.2s;
        }
        .test-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .status-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        .success { background: #4caf50; color: white; }
        .warning { background: #ff9800; color: white; }
        .error { background: #f44336; color: white; }
        .test-details h3 {
            margin: 0 0 5px 0;
            color: #333;
        }
        .test-url {
            font-size: 12px;
            color: #666;
            font-family: monospace;
            word-break: break-all;
        }
        .test-message {
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 14px;
        }
        .success-msg { background: #e8f5e9; color: #2e7d32; }
        .warning-msg { background: #fff3e0; color: #e65100; }
        .error-msg { background: #ffebee; color: #c62828; }
        .response-preview {
            margin-top: 10px;
            padding: 10px;
            background: #f5f5f5;
            border-radius: 4px;
            font-family: monospace;
            font-size: 11px;
            color: #666;
            max-height: 100px;
            overflow: auto;
        }
        .config-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .config-item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .config-item:last-child {
            border-bottom: none;
        }
        .action-buttons {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: opacity 0.2s;
        }
        .btn:hover {
            opacity: 0.8;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-success {
            background: #4caf50;
            color: white;
        }
        .summary {
            display: flex;
            gap: 20px;
            margin: 20px 0;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 8px;
            color: white;
        }
        .summary-item {
            text-align: center;
        }
        .summary-number {
            font-size: 32px;
            font-weight: bold;
        }
        .summary-label {
            font-size: 14px;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 OnlyOffice Server Status Check</h1>
        
        <div class="config-section">
            <h3>⚙️ Configurazione Attuale</h3>
            <div class="config-item">
                <span>Server URL:</span>
                <strong><?php echo htmlspecialchars($config['server_url']); ?></strong>
            </div>
            <div class="config-item">
                <span>JWT Abilitato:</span>
                <strong><?php echo $config['jwt_enabled'] ? 'Sì' : 'No (Test Mode)'; ?></strong>
            </div>
            <div class="config-item">
                <span>Documento Test:</span>
                <strong><?php echo file_exists($config['test_document']) ? 'Presente' : 'Mancante'; ?></strong>
            </div>
        </div>

        <?php
        // Calcola statistiche
        $successCount = count(array_filter($results, fn($r) => $r['status'] === 'success'));
        $warningCount = count(array_filter($results, fn($r) => $r['status'] === 'warning'));
        $errorCount = count(array_filter($results, fn($r) => $r['status'] === 'error'));
        ?>

        <div class="summary">
            <div class="summary-item">
                <div class="summary-number"><?php echo $successCount; ?></div>
                <div class="summary-label">Test OK</div>
            </div>
            <div class="summary-item">
                <div class="summary-number"><?php echo $warningCount; ?></div>
                <div class="summary-label">Warning</div>
            </div>
            <div class="summary-item">
                <div class="summary-number"><?php echo $errorCount; ?></div>
                <div class="summary-label">Errori</div>
            </div>
            <div class="summary-item">
                <div class="summary-number"><?php echo count($results); ?></div>
                <div class="summary-label">Test Totali</div>
            </div>
        </div>

        <div class="test-grid">
            <?php foreach ($results as $result): ?>
                <div class="test-item">
                    <div class="status-icon <?php echo $result['status']; ?>">
                        <?php
                        echo match($result['status']) {
                            'success' => '✓',
                            'warning' => '⚠',
                            'error' => '✗',
                            default => '?'
                        };
                        ?>
                    </div>
                    <div class="test-details">
                        <h3><?php echo htmlspecialchars($result['test']); ?></h3>
                        <div class="test-url"><?php echo htmlspecialchars($result['url']); ?></div>
                        <?php if ($result['response_preview']): ?>
                            <div class="response-preview">
                                <?php echo htmlspecialchars($result['response_preview']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="test-message <?php echo $result['status']; ?>-msg">
                        <?php echo htmlspecialchars($result['message']); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="action-buttons">
            <button class="btn btn-primary" onclick="location.reload()">
                🔄 Ricontrolla
            </button>
            <a href="test-onlyoffice-simple.html" class="btn btn-success" style="text-decoration: none;">
                📝 Apri Editor Test
            </a>
        </div>

        <div style="margin-top: 30px; padding: 20px; background: #f0f0f0; border-radius: 8px;">
            <h3>📋 Prossimi Passi:</h3>
            <?php if ($dockerRunning && $successCount > 2): ?>
                <p style="color: green;">✅ <strong>Il server OnlyOffice sembra funzionante!</strong></p>
                <ol>
                    <li>Apri <a href="test-onlyoffice-simple.html">la pagina di test HTML</a></li>
                    <li>Clicca su "Carica Editor" per testare l'integrazione</li>
                    <li>Se l'editor si carica, l'integrazione base funziona</li>
                </ol>
            <?php else: ?>
                <p style="color: red;">❌ <strong>Ci sono problemi con il server OnlyOffice</strong></p>
                <ol>
                    <li>Verifica che Docker sia in esecuzione: <code>docker ps</code></li>
                    <li>Avvia il container OnlyOffice: <code>docker-compose up -d</code></li>
                    <li>Verifica i log: <code>docker logs onlyoffice-documentserver</code></li>
                    <li>Assicurati che la porta 8082 sia libera</li>
                </ol>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-refresh ogni 30 secondi se ci sono errori
        <?php if ($errorCount > 0): ?>
        setTimeout(() => {
            console.log('Auto-refresh in 30 secondi...');
        }, 30000);
        <?php endif; ?>
    </script>
</body>
</html>