<?php
/**
 * Test ALL methods to connect OnlyOffice to documents
 * This will try every possible method and show which one works
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration
$testDocumentPath = __DIR__ . '/documents/onlyoffice/test_document_' . time() . '.docx';
$testDocumentName = basename($testDocumentPath);

// Create a test document if it doesn't exist
if (!file_exists($testDocumentPath)) {
    // Copy from an existing document or create a minimal one
    $existingDoc = __DIR__ . '/documents/onlyoffice/test_document_1755605547.docx';
    if (file_exists($existingDoc)) {
        copy($existingDoc, $testDocumentPath);
    } else {
        // Find any .docx file to use as template
        $files = glob(__DIR__ . '/documents/onlyoffice/*.docx');
        if (!empty($files)) {
            copy($files[0], $testDocumentPath);
        } else {
            die("No DOCX files found to test with!");
        }
    }
}

// Get Docker gateway IP
$dockerGatewayIP = '';
$output = [];
exec('docker network inspect bridge 2>&1', $output);
$bridgeInfo = implode("\n", $output);
if (preg_match('/"Gateway":\s*"([^"]+)"/', $bridgeInfo, $matches)) {
    $dockerGatewayIP = $matches[1];
}

// Methods to test
$methods = [
    'host.docker.internal' => [
        'url' => "http://host.docker.internal/piattaforma-collaborativa/documents/onlyoffice/{$testDocumentName}",
        'description' => 'Using host.docker.internal (Docker Desktop)'
    ],
    'localhost-gateway' => [
        'url' => $dockerGatewayIP ? "http://{$dockerGatewayIP}/piattaforma-collaborativa/documents/onlyoffice/{$testDocumentName}" : null,
        'description' => "Using Docker gateway IP ({$dockerGatewayIP})"
    ],
    'document-serve-api' => [
        'url' => "http://host.docker.internal/piattaforma-collaborativa/backend/api/document-serve.php?filename={$testDocumentName}",
        'description' => 'Using document-serve.php endpoint with host.docker.internal'
    ],
    'document-serve-gateway' => [
        'url' => $dockerGatewayIP ? "http://{$dockerGatewayIP}/piattaforma-collaborativa/backend/api/document-serve.php?filename={$testDocumentName}" : null,
        'description' => "Using document-serve.php endpoint with gateway IP"
    ],
    'fileserver-nginx' => [
        'url' => "http://nexio-fileserver/documents/onlyoffice/{$testDocumentName}",
        'description' => 'Using nginx fileserver (port 8083)'
    ],
    'direct-ip-172' => [
        'url' => "http://172.17.0.1/piattaforma-collaborativa/documents/onlyoffice/{$testDocumentName}",
        'description' => 'Using common Docker gateway IP 172.17.0.1'
    ],
    'direct-ip-10' => [
        'url' => "http://10.0.75.1/piattaforma-collaborativa/documents/onlyoffice/{$testDocumentName}",
        'description' => 'Using WSL2 gateway IP 10.0.75.1'
    ]
];

// OnlyOffice configuration
$ONLYOFFICE_URL = 'http://localhost:8082';
$JWT_SECRET = 'nexio_jwt_secret_2025_secure_key_with_special_chars_!@#$%';

function createJWT($payload, $secret) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode($payload);
    
    $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $secret, true);
    $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    return $base64Header . "." . $base64Payload . "." . $base64Signature;
}

function testMethod($name, $documentUrl, $description) {
    global $ONLYOFFICE_URL, $JWT_SECRET;
    
    if (!$documentUrl) {
        return ['success' => false, 'error' => 'URL not available'];
    }
    
    // First test if URL is accessible from PHP
    $headers = @get_headers($documentUrl);
    $phpAccessible = $headers && strpos($headers[0], '200') !== false;
    
    // Prepare OnlyOffice config
    $config = [
        'document' => [
            'fileType' => 'docx',
            'key' => 'test_' . uniqid(),
            'title' => 'Test Document',
            'url' => $documentUrl
        ],
        'documentType' => 'word',
        'editorConfig' => [
            'mode' => 'view',
            'callbackUrl' => "http://host.docker.internal/piattaforma-collaborativa/backend/api/onlyoffice-callback.php"
        ]
    ];
    
    $token = createJWT($config, $JWT_SECRET);
    
    // Test from Docker container
    $dockerTest = "docker exec nexio-onlyoffice curl -s -o /dev/null -w '%{http_code}' '{$documentUrl}' 2>&1";
    $dockerAccessible = trim(shell_exec($dockerTest)) === '200';
    
    return [
        'success' => $phpAccessible && $dockerAccessible,
        'php_accessible' => $phpAccessible,
        'docker_accessible' => $dockerAccessible,
        'url' => $documentUrl,
        'token' => $token,
        'config' => $config
    ];
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>OnlyOffice Connection Test - All Methods</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1400px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        h1 { color: #333; }
        .method {
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .method h3 {
            margin-top: 0;
            color: #0066cc;
        }
        .success {
            background: #e7f5e7;
            border-color: #4caf50;
        }
        .partial {
            background: #fff3cd;
            border-color: #ffc107;
        }
        .failure {
            background: #ffebee;
            border-color: #f44336;
        }
        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            font-weight: bold;
            margin-right: 10px;
        }
        .status.ok { background: #4caf50; color: white; }
        .status.fail { background: #f44336; color: white; }
        .status.partial { background: #ffc107; color: white; }
        .url {
            background: #f0f0f0;
            padding: 5px;
            border-radius: 3px;
            word-break: break-all;
            margin: 10px 0;
            font-family: monospace;
        }
        .test-button {
            background: #0066cc;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
        }
        .test-button:hover {
            background: #0052a3;
        }
        #onlyoffice-container {
            width: 100%;
            height: 600px;
            border: 1px solid #ddd;
            margin-top: 20px;
            display: none;
        }
        .info {
            background: #e3f2fd;
            border: 1px solid #1976d2;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        pre {
            background: #263238;
            color: #aed581;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <h1>🔍 OnlyOffice Connection Test - All Methods</h1>
    
    <div class="info">
        <strong>Test Document:</strong> <?php echo htmlspecialchars($testDocumentPath); ?><br>
        <strong>File Exists:</strong> <?php echo file_exists($testDocumentPath) ? '✅ Yes' : '❌ No'; ?><br>
        <strong>File Size:</strong> <?php echo file_exists($testDocumentPath) ? number_format(filesize($testDocumentPath)) . ' bytes' : 'N/A'; ?><br>
        <strong>Docker Gateway IP:</strong> <?php echo $dockerGatewayIP ?: 'Not detected'; ?>
    </div>
    
    <h2>Testing All Connection Methods:</h2>
    
    <?php foreach ($methods as $name => $method): ?>
        <?php 
        $result = testMethod($name, $method['url'], $method['description']);
        $statusClass = $result['success'] ? 'success' : ($result['php_accessible'] || $result['docker_accessible'] ? 'partial' : 'failure');
        ?>
        
        <div class="method <?php echo $statusClass; ?>">
            <h3><?php echo htmlspecialchars($method['description']); ?></h3>
            
            <div>
                <span class="status <?php echo $result['php_accessible'] ? 'ok' : 'fail'; ?>">
                    PHP: <?php echo $result['php_accessible'] ? '✅' : '❌'; ?>
                </span>
                <span class="status <?php echo $result['docker_accessible'] ? 'ok' : 'fail'; ?>">
                    Docker: <?php echo $result['docker_accessible'] ? '✅' : '❌'; ?>
                </span>
                <span class="status <?php echo $result['success'] ? 'ok' : 'fail'; ?>">
                    Overall: <?php echo $result['success'] ? '✅ WORKING' : '❌ FAILED'; ?>
                </span>
            </div>
            
            <div class="url">
                URL: <?php echo htmlspecialchars($method['url'] ?: 'Not available'); ?>
            </div>
            
            <?php if ($result['success']): ?>
                <button class="test-button" onclick="testOnlyOffice('<?php echo $name; ?>')">
                    🚀 Test with OnlyOffice
                </button>
                
                <div style="margin-top: 10px;">
                    <details>
                        <summary>View Configuration</summary>
                        <pre><?php echo json_encode($result['config'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?></pre>
                    </details>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    
    <div id="onlyoffice-container"></div>
    
    <script>
    const configs = <?php 
        $jsConfigs = [];
        foreach ($methods as $name => $method) {
            $result = testMethod($name, $method['url'], $method['description']);
            if ($result['success']) {
                $jsConfigs[$name] = [
                    'config' => $result['config'],
                    'token' => $result['token']
                ];
            }
        }
        echo json_encode($jsConfigs);
    ?>;
    
    function testOnlyOffice(method) {
        if (!configs[method]) {
            alert('Configuration not available for this method');
            return;
        }
        
        const container = document.getElementById('onlyoffice-container');
        container.style.display = 'block';
        container.innerHTML = '<p>Loading OnlyOffice...</p>';
        
        // Scroll to container
        container.scrollIntoView({ behavior: 'smooth' });
        
        // Create iframe
        const iframe = document.createElement('iframe');
        iframe.style.width = '100%';
        iframe.style.height = '100%';
        iframe.style.border = 'none';
        
        // Create a form to post to OnlyOffice
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?php echo $ONLYOFFICE_URL; ?>/';
        form.target = iframe.name = 'onlyoffice_' + Date.now();
        
        // Add config as hidden input
        const configInput = document.createElement('input');
        configInput.type = 'hidden';
        configInput.name = 'config';
        configInput.value = JSON.stringify(configs[method].config);
        form.appendChild(configInput);
        
        // Add token
        const tokenInput = document.createElement('input');
        tokenInput.type = 'hidden';
        tokenInput.name = 'token';
        tokenInput.value = configs[method].token;
        form.appendChild(tokenInput);
        
        container.innerHTML = '';
        container.appendChild(iframe);
        document.body.appendChild(form);
        
        // Load OnlyOffice in iframe
        setTimeout(() => {
            iframe.src = '<?php echo $ONLYOFFICE_URL; ?>/?config=' + 
                       encodeURIComponent(JSON.stringify(configs[method].config)) +
                       '&token=' + configs[method].token;
        }, 100);
    }
    </script>
</body>
</html>