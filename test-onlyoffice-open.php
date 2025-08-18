<?php
/**
 * Test OnlyOffice Document Opening
 * Script semplice per testare l'apertura di un documento con OnlyOffice
 */

session_start();

// Simula un utente autenticato per il test
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'test_user';
    $_SESSION['role'] = 'admin';
    $_SESSION['azienda_id'] = 1;
}

require_once 'backend/config/config.php';
require_once 'backend/config/onlyoffice.config.php';

// Prendi l'ID del documento dal parametro o usa quello di default
$documentId = isset($_GET['id']) ? intval($_GET['id']) : 16;

// Recupera il documento dal database
try {
    $db = db_connection();
    $stmt = $db->prepare("SELECT * FROM documenti WHERE id = ?");
    $stmt->execute([$documentId]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        die("Documento non trovato con ID: $documentId");
    }
    
    // Costruisci il percorso completo del file
    $filePath = __DIR__ . '/' . $document['percorso_file'];
    if (!file_exists($filePath)) {
        die("File non trovato: " . $document['percorso_file']);
    }
    
    // Genera la configurazione per OnlyOffice
    $documentKey = md5($document['id'] . '_' . time());
    $documentUrl = 'http://localhost' . APP_PATH . '/' . $document['percorso_file'];
    
    // Configurazione dell'editor
    $config = [
        'type' => 'desktop',
        'documentType' => 'word',
        'document' => [
            'title' => $document['titolo'],
            'url' => $documentUrl,
            'fileType' => pathinfo($document['percorso_file'], PATHINFO_EXTENSION),
            'key' => $documentKey,
            'permissions' => [
                'comment' => true,
                'download' => true,
                'edit' => true,
                'fillForms' => true,
                'modifyFilter' => true,
                'modifyContentControl' => true,
                'review' => true,
                'print' => true
            ]
        ],
        'editorConfig' => [
            'mode' => 'edit',
            'callbackUrl' => $ONLYOFFICE_CALLBACK_URL . '?doc_id=' . $documentId,
            'lang' => 'it',
            'user' => [
                'id' => (string)$_SESSION['user_id'],
                'name' => $_SESSION['username']
            ],
            'customization' => [
                'autosave' => true,
                'chat' => false,
                'comments' => true,
                'compactHeader' => false,
                'compactToolbar' => false,
                'feedback' => false,
                'forcesave' => true,
                'help' => true,
                'hideRightMenu' => false,
                'plugins' => false,
                'toolbarNoTabs' => false,
                'logo' => [
                    'image' => 'http://localhost' . APP_PATH . '/assets/images/nexio-logo.svg',
                    'imageEmbedded' => 'http://localhost' . APP_PATH . '/assets/images/nexio-logo.svg',
                    'url' => 'http://localhost' . APP_PATH
                ],
                'customer' => [
                    'name' => 'Nexio Platform',
                    'address' => 'Enterprise Document Management'
                ],
                'goback' => [
                    'text' => 'Torna alla lista documenti',
                    'url' => 'http://localhost' . APP_PATH . '/filesystem.php'
                ]
            ]
        ]
    ];
    
    // Genera il JWT token se abilitato
    $token = '';
    if ($ONLYOFFICE_JWT_ENABLED) {
        $token = generateOnlyOfficeJWT($config);
    }
    
} catch (Exception $e) {
    die("Errore database: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test OnlyOffice - <?php echo htmlspecialchars($document['titolo']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f5f5f5;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 1.5rem;
            margin: 0;
        }
        
        .header .info {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-top: 0.5rem;
        }
        
        .test-info {
            background: white;
            padding: 1rem;
            margin: 1rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .test-info h2 {
            font-size: 1.2rem;
            margin-bottom: 1rem;
            color: #333;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        .info-item {
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 4px;
        }
        
        .info-label {
            font-weight: 600;
            color: #666;
            font-size: 0.85rem;
        }
        
        .info-value {
            color: #333;
            margin-top: 0.25rem;
            word-break: break-all;
            font-family: monospace;
            font-size: 0.9rem;
        }
        
        .editor-container {
            margin: 1rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        #onlyoffice-editor {
            width: 100%;
            height: 600px;
            border: none;
        }
        
        .actions {
            padding: 1rem;
            background: white;
            margin: 1rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .status {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: white;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            min-width: 200px;
        }
        
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 0.5rem;
            animation: pulse 2s infinite;
        }
        
        .status-indicator.connected {
            background: #28a745;
        }
        
        .status-indicator.connecting {
            background: #ffc107;
        }
        
        .status-indicator.error {
            background: #dc3545;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            margin: 1rem;
            border-radius: 8px;
            border: 1px solid #f5c6cb;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            margin: 1rem;
            border-radius: 8px;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔬 OnlyOffice Integration Test</h1>
        <div class="info">
            Testing document: <?php echo htmlspecialchars($document['titolo']); ?> (ID: <?php echo $documentId; ?>)
        </div>
    </div>
    
    <div class="test-info">
        <h2>📋 Test Configuration</h2>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Document Server</div>
                <div class="info-value"><?php echo $ONLYOFFICE_DS_PUBLIC_URL; ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Document Key</div>
                <div class="info-value"><?php echo $documentKey; ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Document URL</div>
                <div class="info-value"><?php echo $documentUrl; ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">JWT Enabled</div>
                <div class="info-value"><?php echo $ONLYOFFICE_JWT_ENABLED ? 'Yes ✅' : 'No ❌'; ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Callback URL</div>
                <div class="info-value"><?php echo $ONLYOFFICE_CALLBACK_URL; ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">User</div>
                <div class="info-value"><?php echo $_SESSION['username'] . ' (ID: ' . $_SESSION['user_id'] . ')'; ?></div>
            </div>
        </div>
    </div>
    
    <div class="actions">
        <button class="btn btn-primary" onclick="initEditor()">🚀 Initialize Editor</button>
        <button class="btn btn-secondary" onclick="testConnection()">🔌 Test Connection</button>
        <button class="btn btn-success" onclick="saveDocument()">💾 Force Save</button>
        <a href="filesystem.php" class="btn btn-secondary">📁 Back to Files</a>
        <a href="test-onlyoffice-final.php" class="btn btn-secondary">📊 Full Test Report</a>
    </div>
    
    <div id="status-message"></div>
    
    <div class="editor-container">
        <div id="onlyoffice-editor"></div>
    </div>
    
    <div class="status">
        <span class="status-indicator connecting" id="status-indicator"></span>
        <span id="status-text">Initializing...</span>
    </div>
    
    <!-- OnlyOffice API Script -->
    <script type="text/javascript" src="<?php echo $ONLYOFFICE_DS_PUBLIC_URL; ?>/web-apps/apps/api/documents/api.js"></script>
    
    <script>
        let docEditor = null;
        const config = <?php echo json_encode($config); ?>;
        <?php if ($ONLYOFFICE_JWT_ENABLED && $token): ?>
        config.token = '<?php echo $token; ?>';
        <?php endif; ?>
        
        // Callback handlers
        config.events = {
            onAppReady: function() {
                console.log('OnlyOffice Editor Ready');
                updateStatus('connected', 'Editor Ready');
                showMessage('success', '✅ OnlyOffice editor initialized successfully!');
            },
            onDocumentStateChange: function(event) {
                console.log('Document State Changed:', event);
                if (event.data) {
                    updateStatus('connected', 'Document Modified');
                }
            },
            onError: function(event) {
                console.error('OnlyOffice Error:', event);
                updateStatus('error', 'Error: ' + event.data.errorDescription);
                showMessage('error', '❌ Error: ' + event.data.errorDescription);
            },
            onWarning: function(event) {
                console.warn('OnlyOffice Warning:', event);
                showMessage('error', '⚠️ Warning: ' + event.data.warningDescription);
            },
            onInfo: function(event) {
                console.info('OnlyOffice Info:', event);
            },
            onRequestSaveAs: function(event) {
                console.log('Save As Request:', event);
            },
            onRequestRename: function(event) {
                console.log('Rename Request:', event);
            },
            onCollaborativeChanges: function() {
                console.log('Collaborative Changes Detected');
                updateStatus('connected', 'Collaborative Changes');
            }
        };
        
        function initEditor() {
            try {
                updateStatus('connecting', 'Initializing Editor...');
                
                if (docEditor) {
                    docEditor.destroyEditor();
                }
                
                console.log('Initializing OnlyOffice with config:', config);
                
                docEditor = new DocsAPI.DocEditor("onlyoffice-editor", config);
                
            } catch (e) {
                console.error('Failed to initialize editor:', e);
                updateStatus('error', 'Initialization Failed');
                showMessage('error', '❌ Failed to initialize editor: ' + e.message);
            }
        }
        
        function testConnection() {
            updateStatus('connecting', 'Testing Connection...');
            
            fetch('<?php echo $ONLYOFFICE_DS_PUBLIC_URL; ?>/healthcheck')
                .then(response => response.text())
                .then(data => {
                    if (data.includes('true')) {
                        updateStatus('connected', 'Server Healthy');
                        showMessage('success', '✅ OnlyOffice server is healthy and responding!');
                    } else {
                        updateStatus('error', 'Server Unhealthy');
                        showMessage('error', '⚠️ Server responded but health status unclear');
                    }
                })
                .catch(error => {
                    updateStatus('error', 'Connection Failed');
                    showMessage('error', '❌ Cannot connect to OnlyOffice server: ' + error.message);
                });
        }
        
        function saveDocument() {
            if (docEditor) {
                try {
                    docEditor.downloadAs();
                    showMessage('success', '✅ Save command sent to editor');
                } catch (e) {
                    showMessage('error', '❌ Failed to save: ' + e.message);
                }
            } else {
                showMessage('error', '❌ Editor not initialized');
            }
        }
        
        function updateStatus(type, text) {
            const indicator = document.getElementById('status-indicator');
            const statusText = document.getElementById('status-text');
            
            indicator.className = 'status-indicator ' + type;
            statusText.textContent = text;
        }
        
        function showMessage(type, message) {
            const container = document.getElementById('status-message');
            const messageClass = type === 'success' ? 'success-message' : 'error-message';
            
            container.innerHTML = `<div class="${messageClass}">${message}</div>`;
            
            setTimeout(() => {
                container.innerHTML = '';
            }, 5000);
        }
        
        // Auto-initialize on page load
        window.addEventListener('load', function() {
            setTimeout(initEditor, 1000);
        });
        
        // Log configuration for debugging
        console.log('OnlyOffice Configuration:', {
            server: '<?php echo $ONLYOFFICE_DS_PUBLIC_URL; ?>',
            jwt_enabled: <?php echo $ONLYOFFICE_JWT_ENABLED ? 'true' : 'false'; ?>,
            document_id: <?php echo $documentId; ?>,
            document_url: '<?php echo $documentUrl; ?>',
            callback_url: '<?php echo $ONLYOFFICE_CALLBACK_URL; ?>'
        });
    </script>
</body>
</html>