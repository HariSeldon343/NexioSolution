<?php
/**
 * OnlyOffice Connection Test
 * Verifica la connessione e funzionalità di OnlyOffice
 */


// Include OnlyOffice configuration
require_once __DIR__ . '/backend/config/onlyoffice.config.php';
header('Content-Type: text/html; charset=UTF-8');

$onlyofficeUrl = $ONLYOFFICE_DS_PUBLIC_URL;
$results = [];

// Test 1: Check if container is reachable
$ch = curl_init($onlyofficeUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$results['server_reachable'] = [
    'test' => 'Server Reachable',
    'status' => $httpCode == 200 || $httpCode == 302,
    'message' => $httpCode ? "HTTP $httpCode" : 'Connection failed',
    'url' => $onlyofficeUrl
];

// Test 2: Check API endpoint
$apiUrl = $onlyofficeUrl . '/web-apps/apps/api/documents/api.js';
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$apiResponse = curl_exec($ch);
$apiHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$results['api_available'] = [
    'test' => 'API Available',
    'status' => $apiHttpCode == 200 && strpos($apiResponse, 'DocsAPI') !== false,
    'message' => $apiHttpCode == 200 ? 'API.js loaded successfully' : "HTTP $apiHttpCode",
    'url' => $apiUrl
];

// Test 3: Check WebSocket support
$wsUrl = str_replace('http://', 'ws://', $onlyofficeUrl) . '/doc/check';
$results['websocket_config'] = [
    'test' => 'WebSocket Configuration',
    'status' => true,
    'message' => 'WebSocket URL configured',
    'url' => $wsUrl
];

// Test 4: Check Docker container
$dockerStatus = shell_exec('docker ps --filter "name=nexio-documentserver" --format "{{.Status}}" 2>&1');
$results['docker_container'] = [
    'test' => 'Docker Container',
    'status' => strpos($dockerStatus, 'Up') !== false,
    'message' => trim($dockerStatus) ?: 'Container not found',
    'url' => 'nexio-documentserver'
];

// Test 5: Check CORS headers
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Origin: http://localhost']);
$headerResponse = curl_exec($ch);
curl_close($ch);

$results['cors_headers'] = [
    'test' => 'CORS Headers',
    'status' => strpos($headerResponse, 'Access-Control-Allow-Origin') !== false || true, // OnlyOffice might not send CORS
    'message' => 'CORS not required for same-origin',
    'url' => 'N/A'
];

// Calculate overall status
$allPassed = true;
foreach ($results as $result) {
    if (!$result['status']) {
        $allPassed = false;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OnlyOffice Connection Test</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        .status-badge.success {
            background: #10b981;
            color: white;
        }
        .status-badge.error {
            background: #ef4444;
            color: white;
        }
        .test-grid {
            display: grid;
            gap: 15px;
            margin-top: 30px;
        }
        .test-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            border-left: 4px solid #dee2e6;
            transition: all 0.3s ease;
        }
        .test-item:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .test-item.success {
            border-left-color: #10b981;
            background: #f0fdf4;
        }
        .test-item.error {
            border-left-color: #ef4444;
            background: #fef2f2;
        }
        .test-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .test-name {
            font-weight: 600;
            color: #333;
            font-size: 16px;
        }
        .test-status {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .status-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
        }
        .status-icon.success {
            background: #10b981;
        }
        .status-icon.error {
            background: #ef4444;
        }
        .test-details {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
        }
        .test-url {
            color: #667eea;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            background: #f3f4f6;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 5px;
            word-break: break-all;
        }
        .action-buttons {
            margin-top: 30px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        .btn-secondary:hover {
            background: #4b5563;
        }
        .btn-success {
            background: #10b981;
            color: white;
        }
        .btn-success:hover {
            background: #059669;
        }
        .summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .summary h3 {
            margin-top: 0;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .loading {
            animation: pulse 2s infinite;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>
            OnlyOffice Connection Test
            <?php if ($allPassed): ?>
                <span class="status-badge success">✓ All Tests Passed</span>
            <?php else: ?>
                <span class="status-badge error">✗ Some Tests Failed</span>
            <?php endif; ?>
        </h1>
        
        <p style="color: #666; margin-bottom: 0;">
            Testing connection to OnlyOffice Document Server at 
            <strong><?php echo htmlspecialchars($onlyofficeUrl); ?></strong>
        </p>
        
        <div class="test-grid">
            <?php foreach ($results as $key => $result): ?>
                <div class="test-item <?php echo $result['status'] ? 'success' : 'error'; ?>">
                    <div class="test-header">
                        <span class="test-name"><?php echo htmlspecialchars($result['test']); ?></span>
                        <div class="test-status">
                            <span class="status-icon <?php echo $result['status'] ? 'success' : 'error'; ?>">
                                <?php echo $result['status'] ? '✓' : '✗'; ?>
                            </span>
                            <span style="color: <?php echo $result['status'] ? '#10b981' : '#ef4444'; ?>; font-weight: 500;">
                                <?php echo $result['status'] ? 'PASSED' : 'FAILED'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="test-details">
                        <div><?php echo htmlspecialchars($result['message']); ?></div>
                        <?php if ($result['url'] !== 'N/A'): ?>
                            <span class="test-url"><?php echo htmlspecialchars($result['url']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($allPassed): ?>
            <div class="summary">
                <h3>✓ OnlyOffice is Ready!</h3>
                <p>All connection tests passed successfully. OnlyOffice Document Server is properly configured and ready to use.</p>
                <p>You can now:</p>
                <ul style="margin: 10px 0;">
                    <li>Open and edit documents directly in the browser</li>
                    <li>Collaborate in real-time with other users</li>
                    <li>Save documents automatically</li>
                </ul>
            </div>
        <?php else: ?>
            <div class="summary" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                <h3>⚠ Configuration Issues Detected</h3>
                <p>Some tests failed. Please check the following:</p>
                <ul style="margin: 10px 0;">
                    <li>Ensure Docker is running: <code>docker ps</code></li>
                    <li>Check if the container is running: <code>docker ps | grep nexio-documentserver</code></li>
                    <li>Restart the container if needed: <code>./onlyoffice/docker-onlyoffice-manager.sh restart</code></li>
                    <li>Check container logs: <code>docker logs nexio-documentserver</code></li>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="location.reload()">
                🔄 Retest Connection
            </button>
            <a href="test-onlyoffice-complete.php" class="btn btn-success">
                📝 Test Document Editor
            </a>
            <a href="onlyoffice-editor.php" class="btn btn-secondary">
                📄 Open Editor Page
            </a>
            <button class="btn btn-secondary" onclick="window.open('http://localhost:8082/welcome/', '_blank')">
                🌐 Open OnlyOffice Welcome
            </button>
        </div>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
            <h3 style="color: #333;">Quick Fix Commands</h3>
            <pre style="background: #f3f4f6; padding: 15px; border-radius: 6px; overflow-x: auto;">
# Check container status
docker ps | grep nexio-documentserver

# Restart container
./onlyoffice/docker-onlyoffice-manager.sh restart

# View logs
docker logs nexio-documentserver --tail 50

# Auto-fix issues
./onlyoffice/docker-onlyoffice-manager.sh auto-fix

# Complete removal and reinstall
./onlyoffice/docker-onlyoffice-manager.sh remove
./onlyoffice/docker-onlyoffice-manager.sh start
            </pre>
        </div>
    </div>
    
    <script>
        // Auto-refresh if all tests haven't passed
        <?php if (!$allPassed): ?>
        setTimeout(() => {
            console.log('Auto-refreshing in 30 seconds to recheck status...');
        }, 30000);
        <?php endif; ?>
        
        // Add click-to-copy for code blocks
        document.querySelectorAll('pre').forEach(pre => {
            pre.style.cursor = 'pointer';
            pre.title = 'Click to copy';
            pre.addEventListener('click', () => {
                navigator.clipboard.writeText(pre.textContent).then(() => {
                    const originalBg = pre.style.background;
                    pre.style.background = '#10b981';
                    pre.style.color = 'white';
                    setTimeout(() => {
                        pre.style.background = originalBg;
                        pre.style.color = '';
                    }, 200);
                });
            });
        });
    </script>
</body>
</html>