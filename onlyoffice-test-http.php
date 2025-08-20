<?php
/**
 * OnlyOffice Editor Test - HTTP:8082 Configuration
 * Uses correct URLs for Docker Desktop integration
 */

session_start();
require_once __DIR__ . '/backend/config/onlyoffice.config.php';
require_once __DIR__ . '/backend/config/config.php';

// Test document setup
$docId = $_GET['doc'] ?? '1';
$docKey = 'doc_' . $docId . '_' . time();
$docTitle = 'Test Document ' . $docId;

// URLs using host.docker.internal for container access
$documentUrl = "http://host.docker.internal/piattaforma-collaborativa/backend/api/onlyoffice-document-public.php?doc=" . $docId;
$callbackUrl = "http://host.docker.internal/piattaforma-collaborativa/backend/api/onlyoffice-callback.php?doc=" . $docId;

// Editor configuration
$config = [
    'type' => 'desktop',
    'documentType' => 'word',
    'document' => [
        'title' => $docTitle,
        'url' => $documentUrl,
        'fileType' => 'docx',
        'key' => $docKey,
        'permissions' => [
            'download' => true,
            'edit' => true,
            'print' => true,
            'review' => true,
            'comment' => true,
            'fillForms' => true,
            'modifyFilter' => true,
            'modifyContentControl' => true,
            'chat' => false  // In permissions, NOT customization
        ]
    ],
    'editorConfig' => [
        'mode' => 'edit',
        'lang' => 'it',
        'callbackUrl' => $callbackUrl,
        'user' => [
            'id' => session_id(),
            'name' => 'Test User HTTP'
        ],
        'customization' => [
            'autosave' => true,
            'compactHeader' => false,
            'compactToolbar' => false,
            'feedback' => false,
            'forcesave' => false,
            'help' => true,
            'hideRightMenu' => false,
            'toolbarNoTabs' => false
        ]
    ]
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OnlyOffice HTTP Test - <?php echo htmlspecialchars($docTitle); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
        }
        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 10px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .info-item {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            border-left: 3px solid #2a5298;
        }
        .info-label {
            font-weight: 600;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .info-value {
            color: #333;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            word-break: break-all;
        }
        .status {
            padding: 5px 10px;
            border-radius: 3px;
            display: inline-block;
            margin-top: 10px;
            font-size: 14px;
            font-weight: 600;
        }
        .status.success {
            background: #d4edda;
            color: #155724;
        }
        .status.error {
            background: #f8d7da;
            color: #721c24;
        }
        #editor {
            width: 100%;
            height: calc(100vh - 200px);
            background: white;
        }
        .loading {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            color: white;
            z-index: 1000;
        }
        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📝 OnlyOffice Editor - HTTP:8082 Configuration</h1>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Document ID</div>
                <div class="info-value"><?php echo htmlspecialchars($docId); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Document Key</div>
                <div class="info-value"><?php echo htmlspecialchars($docKey); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">OnlyOffice Server</div>
                <div class="info-value">http://localhost:8082/</div>
            </div>
            <div class="info-item">
                <div class="info-label">Document URL (Internal)</div>
                <div class="info-value"><?php echo htmlspecialchars($documentUrl); ?></div>
            </div>
        </div>
        <div id="status" class="status">Initializing...</div>
    </div>
    
    <div id="loading" class="loading">
        <div class="spinner"></div>
        <div>Loading OnlyOffice Editor...</div>
    </div>
    
    <div id="editor"></div>

    <!-- CRITICAL: OnlyOffice API from HTTP:8082 -->
    <script type="text/javascript" src="http://localhost:8082/web-apps/apps/api/documents/api.js"></script>
    
    <script>
        const config = <?php echo json_encode($config, JSON_PRETTY_PRINT); ?>;
        let docEditor = null;
        
        // Event handlers
        function onDocumentReady() {
            console.log('Document ready');
            document.getElementById('status').className = 'status success';
            document.getElementById('status').textContent = '✓ Document loaded successfully';
            document.getElementById('loading').style.display = 'none';
        }
        
        function onDocumentStateChange(event) {
            console.log('Document state changed:', event);
        }
        
        function onError(event) {
            console.error('OnlyOffice error:', event);
            document.getElementById('status').className = 'status error';
            document.getElementById('status').textContent = '✗ Error: ' + (event.data || 'Unknown error');
        }
        
        function onWarning(event) {
            console.warn('OnlyOffice warning:', event);
        }
        
        // Add event handlers to config
        config.events = {
            'onReady': onDocumentReady,
            'onDocumentStateChange': onDocumentStateChange,
            'onError': onError,
            'onWarning': onWarning
        };
        
        // Initialize editor when API is ready
        window.onload = function() {
            if (typeof DocsAPI !== 'undefined') {
                console.log('DocsAPI loaded, initializing editor...');
                console.log('Configuration:', config);
                
                try {
                    docEditor = new DocsAPI.DocEditor('editor', config);
                    console.log('Editor initialized successfully');
                } catch (e) {
                    console.error('Failed to initialize editor:', e);
                    document.getElementById('status').className = 'status error';
                    document.getElementById('status').textContent = '✗ Failed to initialize editor: ' + e.message;
                    document.getElementById('loading').style.display = 'none';
                }
            } else {
                console.error('DocsAPI not loaded');
                document.getElementById('status').className = 'status error';
                document.getElementById('status').textContent = '✗ OnlyOffice API not loaded - check if container is running on port 8082';
                document.getElementById('loading').style.display = 'none';
            }
        };
        
        // Check API loading after 5 seconds
        setTimeout(function() {
            if (typeof DocsAPI === 'undefined') {
                document.getElementById('status').className = 'status error';
                document.getElementById('status').textContent = '✗ OnlyOffice API failed to load - check container status';
                document.getElementById('loading').style.display = 'none';
            }
        }, 5000);
    </script>
</body>
</html>