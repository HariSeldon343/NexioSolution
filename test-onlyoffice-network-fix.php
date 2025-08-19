<?php
/**
 * Test OnlyOffice Network Fix
 * Tests document access from Docker container
 */

session_start();

// Generate test token
$_SESSION['onlyoffice_test_token'] = bin2hex(random_bytes(16));

// Document configuration
$docId = 22;
$docKey = 'test_' . time() . '_' . rand(1000, 9999);

// Use the public endpoint that doesn't require authentication
$documentUrl = "http://host.docker.internal/piattaforma-collaborativa/backend/api/onlyoffice-document-public.php?doc=" . $docId;

// Callback URL (also public for testing)
$callbackUrl = "http://host.docker.internal/piattaforma-collaborativa/backend/api/onlyoffice-callback.php?doc=" . $docId;

// OnlyOffice server URL (accessible from browser)
$onlyofficeUrl = "http://localhost:8080";

// Configuration for OnlyOffice
$config = [
    "type" => "desktop",
    "documentType" => "word",
    "document" => [
        "title" => "Test Document Network Fix",
        "url" => $documentUrl,
        "fileType" => "docx",
        "key" => $docKey,
        "permissions" => [
            "comment" => true,
            "download" => true,
            "edit" => true,
            "fillForms" => true,
            "modifyFilter" => true,
            "modifyContentControl" => true,
            "review" => true,
            "chat" => false,
            "print" => true
        ]
    ],
    "editorConfig" => [
        "mode" => "edit",
        "lang" => "it",
        "callbackUrl" => $callbackUrl,
        "user" => [
            "id" => "test_user_" . session_id(),
            "name" => "Test User"
        ],
        "customization" => [
            "autosave" => true,
            "chat" => false,
            "comments" => true,
            "compactHeader" => false,
            "compactToolbar" => false,
            "feedback" => false,
            "forcesave" => false,
            "help" => true,
            "hideRightMenu" => false,
            "toolbarNoTabs" => false
        ]
    ]
];

// Debug output
$debugInfo = [
    'document_url' => $documentUrl,
    'callback_url' => $callbackUrl,
    'onlyoffice_url' => $onlyofficeUrl,
    'document_key' => $docKey,
    'session_id' => session_id()
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test OnlyOffice - Network Fix</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .debug-info {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .debug-info h2 {
            margin-top: 0;
            color: #333;
        }
        .debug-item {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .debug-label {
            font-weight: 600;
            width: 200px;
            color: #666;
        }
        .debug-value {
            flex: 1;
            font-family: 'Courier New', monospace;
            color: #333;
            word-break: break-all;
        }
        .test-buttons {
            margin: 20px 0;
        }
        .test-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
            font-size: 14px;
        }
        .test-btn:hover {
            background: #0056b3;
        }
        .test-btn.success {
            background: #28a745;
        }
        .test-btn.error {
            background: #dc3545;
        }
        #editor-container {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            min-height: 600px;
        }
        #editor {
            width: 100%;
            height: 600px;
            border: none;
        }
        .status {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .status.info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Test OnlyOffice - Network Fix</h1>
        
        <div class="debug-info">
            <h2>📊 Configurazione Debug</h2>
            <?php foreach ($debugInfo as $key => $value): ?>
            <div class="debug-item">
                <div class="debug-label"><?php echo ucfirst(str_replace('_', ' ', $key)); ?>:</div>
                <div class="debug-value"><?php echo htmlspecialchars($value); ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="test-buttons">
            <button class="test-btn" onclick="testDocumentAccess()">🔍 Test Accesso Documento</button>
            <button class="test-btn" onclick="testContainerAccess()">🐳 Test Accesso Container</button>
            <button class="test-btn" onclick="loadEditor()">📝 Carica Editor</button>
            <button class="test-btn" onclick="location.reload()">🔄 Ricarica Pagina</button>
        </div>

        <div id="status-container"></div>

        <div id="editor-container">
            <div id="editor"></div>
        </div>
    </div>

    <script type="text/javascript" src="<?php echo $onlyofficeUrl; ?>/web-apps/apps/api/documents/api.js"></script>
    <script>
        const config = <?php echo json_encode($config, JSON_PRETTY_PRINT); ?>;
        let docEditor = null;

        function showStatus(message, type = 'info') {
            const container = document.getElementById('status-container');
            const status = document.createElement('div');
            status.className = `status ${type}`;
            status.innerHTML = `${new Date().toLocaleTimeString()} - ${message}`;
            container.appendChild(status);
            
            // Auto-remove after 10 seconds
            setTimeout(() => {
                status.remove();
            }, 10000);
        }

        function testDocumentAccess() {
            showStatus('Testing document access from browser...', 'info');
            
            fetch('<?php echo $documentUrl; ?>', { method: 'HEAD' })
                .then(response => {
                    if (response.ok) {
                        showStatus('✅ Document accessible from browser!', 'success');
                    } else {
                        showStatus(`❌ Document access failed: ${response.status} ${response.statusText}`, 'error');
                    }
                })
                .catch(error => {
                    showStatus(`❌ Network error: ${error.message}`, 'error');
                });
        }

        function testContainerAccess() {
            showStatus('Testing container access via PHP...', 'info');
            
            // This would need a separate PHP endpoint to test from server-side
            fetch('backend/api/test-container-access.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showStatus('✅ Container can access host!', 'success');
                    } else {
                        showStatus(`❌ Container access failed: ${data.error}`, 'error');
                    }
                })
                .catch(error => {
                    showStatus('⚠️ Cannot test container access (endpoint not available)', 'info');
                });
        }

        function loadEditor() {
            showStatus('Loading OnlyOffice editor...', 'info');
            
            try {
                // Destroy existing editor if present
                if (docEditor) {
                    docEditor.destroyEditor();
                    docEditor = null;
                }
                
                // Add event handlers to config
                config.events = {
                    onReady: function() {
                        showStatus('✅ Editor ready!', 'success');
                        console.log('OnlyOffice Editor is ready');
                    },
                    onDocumentStateChange: function(event) {
                        if (event.data) {
                            showStatus(`📝 Document modified`, 'info');
                        }
                    },
                    onError: function(event) {
                        console.error('OnlyOffice Error:', event);
                        showStatus(`❌ OnlyOffice Error: ${JSON.stringify(event.data)}`, 'error');
                        
                        // Detailed error analysis
                        if (event.data && event.data.errorCode) {
                            let errorMsg = '';
                            switch(event.data.errorCode) {
                                case -1:
                                    errorMsg = 'Unknown error';
                                    break;
                                case -2:
                                    errorMsg = 'Callback error';
                                    break;
                                case -3:
                                    errorMsg = 'Internal server error';
                                    break;
                                case -4:
                                    errorMsg = 'Failed to download document - Network/Access issue';
                                    break;
                                case -5:
                                    errorMsg = 'Unsupported document format';
                                    break;
                                case -6:
                                    errorMsg = 'Document has invalid key';
                                    break;
                                case -7:
                                    errorMsg = 'Error while converting document';
                                    break;
                                case -8:
                                    errorMsg = 'Token validation error';
                                    break;
                                default:
                                    errorMsg = `Error code: ${event.data.errorCode}`;
                            }
                            showStatus(`🔍 Error details: ${errorMsg}`, 'error');
                        }
                    },
                    onWarning: function(event) {
                        showStatus(`⚠️ Warning: ${JSON.stringify(event.data)}`, 'info');
                    },
                    onInfo: function(event) {
                        console.log('OnlyOffice Info:', event.data);
                    }
                };
                
                // Initialize the editor
                docEditor = new DocsAPI.DocEditor("editor", config);
                showStatus('Editor initialization started...', 'info');
                
            } catch (error) {
                showStatus(`❌ Failed to initialize editor: ${error.message}`, 'error');
                console.error('Editor initialization error:', error);
            }
        }

        // Auto-load editor on page load
        window.addEventListener('load', function() {
            setTimeout(loadEditor, 1000);
        });

        // Debug: Log the configuration
        console.log('OnlyOffice Configuration:', config);
    </script>
</body>
</html>