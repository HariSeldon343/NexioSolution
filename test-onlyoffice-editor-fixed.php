<?php
/**
 * Test completo dell'editor OnlyOffice
 * Verifica configurazione, connettività e inizializzazione
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configurazione
require_once 'backend/config/config.php';

// Test configuration
$documentServerUrl = 'http://localhost:8082';
$apiJsUrl = $documentServerUrl . '/web-apps/apps/api/documents/api.js';
$testDocPath = '/documents/onlyoffice/test_document.docx';
$testDocUrl = 'http://localhost/piattaforma-collaborativa' . $testDocPath;

// Funzione per testare URL
function testUrl($url, $description) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'url' => $url,
        'description' => $description,
        'status' => $httpCode,
        'success' => ($httpCode >= 200 && $httpCode < 300),
        'error' => $error,
        'content_preview' => $httpCode == 200 ? substr($response, 0, 200) : null
    ];
}

// Crea documento di test se non esiste
$testDocFullPath = __DIR__ . $testDocPath;
if (!file_exists($testDocFullPath)) {
    // Crea directory se non esiste
    $dir = dirname($testDocFullPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    
    // Copia un documento di esempio o crea uno nuovo
    $sampleDoc = __DIR__ . '/documents/test.docx';
    if (file_exists($sampleDoc)) {
        copy($sampleDoc, $testDocFullPath);
    } else {
        // Crea un semplice file DOCX di test
        file_put_contents($testDocFullPath, 'Test document content');
    }
}

// Esegui test
$tests = [];

// 1. Test DocsAPI.js
$tests[] = testUrl($apiJsUrl, 'OnlyOffice API JavaScript');

// 2. Test Document Server root
$tests[] = testUrl($documentServerUrl, 'OnlyOffice Document Server');

// 3. Test healthcheck
$tests[] = testUrl($documentServerUrl . '/healthcheck', 'OnlyOffice Healthcheck');

// 4. Test documento locale
$tests[] = testUrl($testDocUrl, 'Documento di test locale');

// 5. Test info endpoint
$tests[] = testUrl($documentServerUrl . '/info', 'OnlyOffice Info Endpoint');

// Genera configurazione editor
$editorConfig = [
    'document' => [
        'fileType' => 'docx',
        'key' => 'test_' . time(),
        'title' => 'Test Document',
        'url' => $testDocUrl,
        'permissions' => [
            'edit' => true,
            'download' => true,
            'print' => true
        ]
    ],
    'documentType' => 'word',
    'editorConfig' => [
        'lang' => 'it',
        'mode' => 'edit',
        'callbackUrl' => 'http://localhost/piattaforma-collaborativa/backend/api/onlyoffice-callback.php',
        'user' => [
            'id' => 'test_user_' . session_id(),
            'name' => 'Test User'
        ],
        'customization' => [
            'autosave' => true,
            'forcesave' => true,
            'chat' => false,
            'comments' => false,
            'zoom' => 100
        ]
    ],
    'type' => 'desktop',
    'width' => '100%',
    'height' => '600px'
];

// Genera JWT token se configurato
$jwtSecret = 'nexio_jwt_secret_2025';
if ($jwtSecret) {
    function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
    $payload = json_encode($editorConfig);
    
    $base64Header = base64url_encode($header);
    $base64Payload = base64url_encode($payload);
    
    $signature = hash_hmac('sha256', $base64Header . '.' . $base64Payload, $jwtSecret, true);
    $base64Signature = base64url_encode($signature);
    
    $jwtToken = $base64Header . '.' . $base64Payload . '.' . $base64Signature;
    $editorConfig['token'] = $jwtToken;
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test OnlyOffice Editor - Report Completo</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
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
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .status-success {
            background: #10b981;
            color: white;
        }
        
        .status-error {
            background: #ef4444;
            color: white;
        }
        
        .status-warning {
            background: #f59e0b;
            color: white;
        }
        
        .test-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .test-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .test-card h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .test-item {
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
            border-left: 4px solid;
        }
        
        .test-success {
            background: #d1fae5;
            border-color: #10b981;
        }
        
        .test-error {
            background: #fee2e2;
            border-color: #ef4444;
        }
        
        .test-warning {
            background: #fed7aa;
            border-color: #f59e0b;
        }
        
        .test-url {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: #6b7280;
            word-break: break-all;
            margin-top: 5px;
        }
        
        .config-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .config-section h2 {
            color: #333;
            margin-bottom: 15px;
            font-size: 20px;
        }
        
        .config-code {
            background: #1f2937;
            color: #10b981;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            overflow-x: auto;
            white-space: pre;
        }
        
        .editor-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .editor-section h2 {
            color: #333;
            margin-bottom: 15px;
            font-size: 20px;
        }
        
        #onlyoffice-editor {
            width: 100%;
            height: 600px;
            border: 2px solid #e5e7eb;
            border-radius: 5px;
            background: #f9fafb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #6b7280;
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
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2563eb;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
        }
        
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        
        .btn-warning:hover {
            background: #d97706;
        }
        
        .info-box {
            background: #eff6ff;
            border: 1px solid #3b82f6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .info-box h4 {
            color: #1e40af;
            margin-bottom: 10px;
        }
        
        .info-box ul {
            margin-left: 20px;
            color: #1e40af;
        }
        
        .loader {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3b82f6;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>
                🔬 Test OnlyOffice Editor
                <?php
                $allTestsPassed = true;
                foreach ($tests as $test) {
                    if (!$test['success']) {
                        $allTestsPassed = false;
                        break;
                    }
                }
                ?>
                <span class="status-badge <?php echo $allTestsPassed ? 'status-success' : 'status-error'; ?>">
                    <?php echo $allTestsPassed ? '✓ Tutti i test passati' : '✗ Alcuni test falliti'; ?>
                </span>
            </h1>
            <p style="color: #6b7280; margin-top: 10px;">
                Test eseguito: <?php echo date('Y-m-d H:i:s'); ?> | 
                Server: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?> | 
                PHP: <?php echo phpversion(); ?>
            </p>
        </div>

        <!-- Test Results Grid -->
        <div class="test-grid">
            <?php foreach ($tests as $test): ?>
            <div class="test-card">
                <h3><?php echo htmlspecialchars($test['description']); ?></h3>
                <div class="test-item <?php echo $test['success'] ? 'test-success' : 'test-error'; ?>">
                    <strong>Status HTTP:</strong> <?php echo $test['status'] ?: 'N/A'; ?>
                    <?php if ($test['error']): ?>
                        <br><strong>Errore:</strong> <?php echo htmlspecialchars($test['error']); ?>
                    <?php endif; ?>
                    <div class="test-url"><?php echo htmlspecialchars($test['url']); ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Configuration Section -->
        <div class="config-section">
            <h2>📋 Configurazione Editor</h2>
            <div class="info-box">
                <h4>URLs Configurati:</h4>
                <ul>
                    <li><strong>Document Server:</strong> <?php echo htmlspecialchars($documentServerUrl); ?></li>
                    <li><strong>API JS:</strong> <?php echo htmlspecialchars($apiJsUrl); ?></li>
                    <li><strong>Documento Test:</strong> <?php echo htmlspecialchars($testDocUrl); ?></li>
                    <li><strong>Callback URL:</strong> <?php echo htmlspecialchars($editorConfig['editorConfig']['callbackUrl']); ?></li>
                </ul>
            </div>
            <div class="config-code"><?php echo htmlspecialchars(json_encode($editorConfig, JSON_PRETTY_PRINT)); ?></div>
        </div>

        <!-- Editor Section -->
        <div class="editor-section">
            <h2>📝 Editor OnlyOffice</h2>
            
            <div class="action-buttons">
                <button class="btn btn-primary" onclick="initializeEditor()">
                    🚀 Inizializza Editor
                </button>
                <button class="btn btn-success" onclick="testConnection()">
                    🔌 Test Connessione
                </button>
                <button class="btn btn-warning" onclick="reloadPage()">
                    🔄 Ricarica Pagina
                </button>
            </div>
            
            <div id="editor-status" style="margin: 20px 0; padding: 15px; border-radius: 5px; display: none;"></div>
            
            <div id="onlyoffice-editor">
                <div class="loader" style="display: none;" id="loader"></div>
                <span id="editor-message">Clicca "Inizializza Editor" per avviare</span>
            </div>
        </div>
    </div>

    <script>
        // Configurazione editor globale
        const editorConfig = <?php echo json_encode($editorConfig); ?>;
        const documentServerUrl = '<?php echo $documentServerUrl; ?>';
        let docEditor = null;

        function showStatus(message, type = 'info') {
            const statusDiv = document.getElementById('editor-status');
            statusDiv.style.display = 'block';
            statusDiv.className = '';
            
            if (type === 'success') {
                statusDiv.style.background = '#d1fae5';
                statusDiv.style.color = '#065f46';
                statusDiv.style.border = '1px solid #10b981';
            } else if (type === 'error') {
                statusDiv.style.background = '#fee2e2';
                statusDiv.style.color = '#991b1b';
                statusDiv.style.border = '1px solid #ef4444';
            } else {
                statusDiv.style.background = '#dbeafe';
                statusDiv.style.color = '#1e40af';
                statusDiv.style.border = '1px solid #3b82f6';
            }
            
            statusDiv.innerHTML = message;
        }

        function testConnection() {
            showStatus('🔍 Testing connessione al Document Server...', 'info');
            
            fetch(documentServerUrl + '/healthcheck')
                .then(response => {
                    if (response.ok) {
                        showStatus('✅ Connessione al Document Server OK!', 'success');
                    } else {
                        showStatus('❌ Document Server non risponde correttamente (HTTP ' + response.status + ')', 'error');
                    }
                })
                .catch(error => {
                    showStatus('❌ Errore connessione: ' + error.message, 'error');
                });
        }

        function initializeEditor() {
            const editorDiv = document.getElementById('onlyoffice-editor');
            const loader = document.getElementById('loader');
            const message = document.getElementById('editor-message');
            
            // Show loader
            loader.style.display = 'block';
            message.style.display = 'none';
            
            showStatus('📦 Caricamento DocsAPI.js...', 'info');
            
            // Remove existing script if present
            const existingScript = document.getElementById('onlyoffice-api-script');
            if (existingScript) {
                existingScript.remove();
            }
            
            // Load OnlyOffice API
            const script = document.createElement('script');
            script.id = 'onlyoffice-api-script';
            script.src = documentServerUrl + '/web-apps/apps/api/documents/api.js';
            
            script.onload = function() {
                showStatus('✅ DocsAPI.js caricato con successo! Inizializzazione editor...', 'success');
                
                try {
                    // Clear the container
                    editorDiv.innerHTML = '';
                    
                    // Initialize editor
                    docEditor = new DocsAPI.DocEditor('onlyoffice-editor', {
                        ...editorConfig,
                        events: {
                            onReady: function() {
                                console.log('Editor ready');
                                showStatus('✅ Editor OnlyOffice pronto!', 'success');
                            },
                            onError: function(event) {
                                console.error('Editor error:', event);
                                showStatus('❌ Errore editor: ' + JSON.stringify(event), 'error');
                            },
                            onWarning: function(event) {
                                console.warn('Editor warning:', event);
                                showStatus('⚠️ Warning editor: ' + JSON.stringify(event), 'warning');
                            },
                            onInfo: function(event) {
                                console.info('Editor info:', event);
                            }
                        }
                    });
                    
                    console.log('Editor initialized:', docEditor);
                    
                } catch (error) {
                    console.error('Initialization error:', error);
                    showStatus('❌ Errore inizializzazione: ' + error.message, 'error');
                    loader.style.display = 'none';
                    message.style.display = 'block';
                    message.textContent = 'Errore inizializzazione editor';
                }
            };
            
            script.onerror = function(error) {
                console.error('Script load error:', error);
                showStatus('❌ Impossibile caricare DocsAPI.js. Verifica che OnlyOffice sia attivo su ' + documentServerUrl, 'error');
                loader.style.display = 'none';
                message.style.display = 'block';
                message.textContent = 'Errore caricamento API';
            };
            
            document.head.appendChild(script);
        }

        function reloadPage() {
            window.location.reload();
        }

        // Auto-test connection on load
        window.addEventListener('load', function() {
            setTimeout(testConnection, 1000);
        });
    </script>
</body>
</html>