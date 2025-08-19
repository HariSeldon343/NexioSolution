<?php
/**
 * Test OnlyOffice Configuration per ambienti multipli
 */

require_once 'backend/config/onlyoffice.config.php';
require_once 'backend/config/config.php';

// Determina ambiente
$isProduction = OnlyOfficeConfig::isProduction();
$isDockerDesktop = OnlyOfficeConfig::isDockerDesktop();
$currentHost = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Test documento ID 22
$documentId = 22;
$stmt = db_query("SELECT * FROM documenti WHERE id = ?", [$documentId]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);

// URLs generati
$documentServerUrl = OnlyOfficeConfig::getDocumentServerUrl();
$documentUrl = $isProduction 
    ? OnlyOfficeConfig::PROD_APP_PUBLIC_URL . '/backend/api/onlyoffice-document-serve.php?id=' . $documentId
    : OnlyOfficeConfig::DOCKER_HOST_INTERNAL . '/piattaforma-collaborativa/backend/api/onlyoffice-document-serve.php?id=' . $documentId;
$callbackUrl = OnlyOfficeConfig::getCallbackUrl($documentId);
$apiUrl = $documentServerUrl . 'web-apps/apps/api/documents/api.js';

// Test connessione OnlyOffice
$connectionTest = OnlyOfficeConfig::testConnection();

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test OnlyOffice Environment Configuration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .status-ok { color: green; font-weight: bold; }
        .status-error { color: red; font-weight: bold; }
        .status-warning { color: orange; font-weight: bold; }
        .url-box { 
            background: #f8f9fa; 
            padding: 10px; 
            border-radius: 5px; 
            margin: 5px 0;
            word-break: break-all;
            font-family: monospace;
        }
        .test-section {
            margin: 20px 0;
            padding: 20px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1>🔧 Test OnlyOffice Configuration</h1>
        
        <!-- Environment Detection -->
        <div class="test-section">
            <h2>🌍 Environment Detection</h2>
            <table class="table">
                <tr>
                    <td><strong>Current Host:</strong></td>
                    <td><?= htmlspecialchars($currentHost) ?></td>
                </tr>
                <tr>
                    <td><strong>Environment:</strong></td>
                    <td class="<?= $isProduction ? 'status-ok' : 'status-warning' ?>">
                        <?= $isProduction ? '🚀 PRODUCTION' : '💻 DEVELOPMENT' ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>Docker Desktop:</strong></td>
                    <td class="<?= $isDockerDesktop ? 'status-ok' : '' ?>">
                        <?= $isDockerDesktop ? '✅ Yes (Windows/Mac)' : '❌ No' ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>PHP OS:</strong></td>
                    <td><?= PHP_OS_FAMILY ?></td>
                </tr>
            </table>
        </div>
        
        <!-- URLs Configuration -->
        <div class="test-section">
            <h2>🔗 Generated URLs</h2>
            
            <h4>OnlyOffice Document Server:</h4>
            <div class="url-box"><?= htmlspecialchars($documentServerUrl) ?></div>
            
            <h4>OnlyOffice API JS:</h4>
            <div class="url-box"><?= htmlspecialchars($apiUrl) ?></div>
            
            <h4>Document URL (for OnlyOffice):</h4>
            <div class="url-box"><?= htmlspecialchars($documentUrl) ?></div>
            
            <h4>Callback URL:</h4>
            <div class="url-box"><?= htmlspecialchars($callbackUrl) ?></div>
        </div>
        
        <!-- Document Info -->
        <?php if ($document): ?>
        <div class="test-section">
            <h2>📄 Test Document (ID: <?= $documentId ?>)</h2>
            <table class="table">
                <tr>
                    <td><strong>Title:</strong></td>
                    <td><?= htmlspecialchars($document['titolo']) ?></td>
                </tr>
                <tr>
                    <td><strong>File Name:</strong></td>
                    <td><?= htmlspecialchars($document['nome_file'] ?? 'N/A') ?></td>
                </tr>
                <tr>
                    <td><strong>File Path:</strong></td>
                    <td><?= htmlspecialchars($document['percorso_file'] ?? $document['file_path'] ?? 'N/A') ?></td>
                </tr>
                <tr>
                    <td><strong>MIME Type:</strong></td>
                    <td><?= htmlspecialchars($document['mime_type'] ?? 'N/A') ?></td>
                </tr>
            </table>
        </div>
        <?php else: ?>
        <div class="test-section">
            <h2 class="status-error">❌ Document Not Found</h2>
            <p>Document with ID <?= $documentId ?> not found in database.</p>
        </div>
        <?php endif; ?>
        
        <!-- Connection Test -->
        <div class="test-section">
            <h2>🔌 OnlyOffice Connection Test</h2>
            <table class="table">
                <tr>
                    <td><strong>Status:</strong></td>
                    <td class="<?= $connectionTest['success'] ? 'status-ok' : 'status-error' ?>">
                        <?= $connectionTest['success'] ? '✅ Connected' : '❌ Failed' ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>HTTP Code:</strong></td>
                    <td><?= $connectionTest['http_code'] ?></td>
                </tr>
                <tr>
                    <td><strong>Test URL:</strong></td>
                    <td><?= htmlspecialchars($connectionTest['url']) ?></td>
                </tr>
                <?php if ($connectionTest['error']): ?>
                <tr>
                    <td><strong>Error:</strong></td>
                    <td class="status-error"><?= htmlspecialchars($connectionTest['error']) ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        
        <!-- Quick Tests -->
        <div class="test-section">
            <h2>🚀 Quick Tests</h2>
            
            <div class="row">
                <div class="col-md-6">
                    <h4>Test Document Serve API:</h4>
                    <a href="backend/api/onlyoffice-document-serve.php?id=<?= $documentId ?>" 
                       target="_blank" class="btn btn-primary">
                        🔍 Test Document API
                    </a>
                </div>
                
                <div class="col-md-6">
                    <h4>Open in OnlyOffice Editor:</h4>
                    <a href="onlyoffice-editor.php?id=<?= $documentId ?>" 
                       target="_blank" class="btn btn-success">
                        📝 Open Editor
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Configuration Summary -->
        <div class="test-section">
            <h2>📋 Configuration Summary</h2>
            <pre><?php
            $config = [
                'Environment' => $isProduction ? 'PRODUCTION' : 'DEVELOPMENT',
                'JWT Enabled' => OnlyOfficeConfig::JWT_ENABLED ? 'Yes' : 'No',
                'Max File Size' => number_format(OnlyOfficeConfig::MAX_FILE_SIZE / 1048576, 2) . ' MB',
                'Conversion Timeout' => OnlyOfficeConfig::CONVERSION_TIMEOUT / 1000 . ' seconds',
                'Documents Path' => OnlyOfficeConfig::DOCUMENTS_PATH
            ];
            print_r($config);
            ?></pre>
        </div>
        
        <!-- Instructions -->
        <div class="test-section">
            <h2>📚 Instructions</h2>
            <ol>
                <li><strong>Development Setup:</strong>
                    <ul>
                        <li>Ensure OnlyOffice Docker container is running on port 8082</li>
                        <li>Container name should be: nexio-documentserver</li>
                        <li>Use: <code>docker ps</code> to verify</li>
                    </ul>
                </li>
                <li><strong>Production Setup:</strong>
                    <ul>
                        <li>OnlyOffice should be accessible at: https://app.nexiosolution.it/onlyoffice/</li>
                        <li>Configure Cloudflare Tunnel or reverse proxy</li>
                        <li>Ensure SSL certificates are valid</li>
                    </ul>
                </li>
                <li><strong>Troubleshooting:</strong>
                    <ul>
                        <li>If connection fails, check Docker container status</li>
                        <li>Verify firewall rules allow port 8082 (dev) or 443 (prod)</li>
                        <li>Check logs: <code>docker logs nexio-documentserver</code></li>
                    </ul>
                </li>
            </ol>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>