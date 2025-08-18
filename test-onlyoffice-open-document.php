<?php
/**
 * Test OnlyOffice Document Opening
 * Opens document ID 22 directly in OnlyOffice
 */

// Simula una sessione utente
session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['nome'] = 'Test';
    $_SESSION['cognome'] = 'User';
    $_SESSION['user_role'] = 'super_admin';
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Load configuration
require_once 'backend/config/config.php';
require_once 'backend/config/onlyoffice.config.php';

// Document details
$documentId = 22;
$documentPath = 'documents/onlyoffice/test_document_1755542731.docx';
$documentTitle = 'Dichiarazione_conformita_dispositivo_conversione_updated.docx';

// Generate unique document key
$documentKey = 'doc_' . $documentId . '_' . time();

// Full URL for OnlyOffice to access the document
$documentUrl = 'http://host.docker.internal/piattaforma-collaborativa/' . $documentPath;

// Configuration for OnlyOffice
$config = [
    'document' => [
        'fileType' => 'docx',
        'key' => $documentKey,
        'title' => $documentTitle,
        'url' => $documentUrl,
        'permissions' => [
            'edit' => true,
            'download' => true,
            'print' => true,
            'review' => true
        ]
    ],
    'editorConfig' => [
        'mode' => 'edit',
        'lang' => 'it',
        'callbackUrl' => 'http://host.docker.internal/piattaforma-collaborativa/backend/api/onlyoffice-callback.php?id=' . $documentId,
        'user' => [
            'id' => (string)$_SESSION['user_id'],
            'name' => ($_SESSION['nome'] ?? 'Utente') . ' ' . ($_SESSION['cognome'] ?? '')
        ],
        'customization' => [
            'autosave' => true,
            'forcesave' => true,
            'comments' => true,
            'compactHeader' => false,
            'compactToolbar' => false,
            'hideRightMenu' => false,
            'toolbarNoTabs' => false,
            'showReviewChanges' => true
        ]
    ],
    'type' => 'desktop'
];

// Generate JWT token if enabled
$token = '';
if (ONLYOFFICE_JWT_ENABLED) {
    function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
    $payload = json_encode($config);
    
    $base64Header = base64url_encode($header);
    $base64Payload = base64url_encode($payload);
    
    $signature = base64url_encode(
        hash_hmac('sha256', $base64Header . '.' . $base64Payload, ONLYOFFICE_JWT_SECRET, true)
    );
    
    $token = $base64Header . '.' . $base64Payload . '.' . $signature;
    $config['token'] = $token;
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OnlyOffice Editor - <?php echo htmlspecialchars($documentTitle); ?></title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f5f5f5;
        }
        .header {
            background: #2c3e50;
            color: white;
            padding: 15px 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 500;
        }
        .header .info {
            margin-top: 5px;
            font-size: 14px;
            opacity: 0.9;
        }
        .header .back-btn {
            float: right;
            background: #3498db;
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            margin-top: -35px;
        }
        .header .back-btn:hover {
            background: #2980b9;
        }
        #onlyoffice-placeholder {
            width: 100%;
            height: calc(100vh - 70px);
            background: white;
        }
        .loading {
            text-align: center;
            padding: 50px;
            color: #666;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            margin: 20px;
            border-radius: 4px;
            border: 1px solid #f5c6cb;
        }
        .debug-info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            margin: 20px;
            border-radius: 4px;
            border: 1px solid #bee5eb;
            font-family: monospace;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <a href="filesystem.php" class="back-btn">← Torna ai Documenti</a>
        <h1>Editor Documenti - OnlyOffice</h1>
        <div class="info">
            Documento: <?php echo htmlspecialchars($documentTitle); ?> | 
            ID: <?php echo $documentId; ?> | 
            Utente: <?php echo ($_SESSION['nome'] ?? 'Utente') . ' ' . ($_SESSION['cognome'] ?? ''); ?>
        </div>
    </div>
    
    <div id="onlyoffice-placeholder">
        <div class="loading">
            <p>Caricamento dell'editor in corso...</p>
            <p>Se l'editor non si carica entro qualche secondo, verificare la configurazione di OnlyOffice.</p>
        </div>
    </div>
    
    <div class="debug-info">
        <strong>Debug Info:</strong><br>
        OnlyOffice Server: <?php echo $ONLYOFFICE_DS_PUBLIC_URL; ?><br>
        Document URL: <?php echo $documentUrl; ?><br>
        Document Key: <?php echo $documentKey; ?><br>
        JWT Enabled: <?php echo ONLYOFFICE_JWT_ENABLED ? 'Yes' : 'No'; ?><br>
        Token Length: <?php echo strlen($token); ?> chars
    </div>

    <script type="text/javascript" src="<?php echo $ONLYOFFICE_DS_PUBLIC_URL; ?>/web-apps/apps/api/documents/api.js"></script>
    <script type="text/javascript">
        // Configuration
        var config = <?php echo json_encode($config); ?>;
        
        console.log('OnlyOffice Configuration:', config);
        console.log('OnlyOffice Server:', '<?php echo $ONLYOFFICE_DS_PUBLIC_URL; ?>');
        
        // Initialize editor
        window.onload = function() {
            try {
                // Clear the placeholder
                document.getElementById('onlyoffice-placeholder').innerHTML = '';
                
                // Initialize OnlyOffice editor
                window.docEditor = new DocsAPI.DocEditor("onlyoffice-placeholder", config);
                
                console.log('OnlyOffice editor initialized successfully');
                
                // Hide debug info after successful load
                setTimeout(function() {
                    var debugDiv = document.querySelector('.debug-info');
                    if (debugDiv) {
                        debugDiv.style.display = 'none';
                    }
                }, 3000);
                
            } catch (error) {
                console.error('Failed to initialize OnlyOffice:', error);
                document.getElementById('onlyoffice-placeholder').innerHTML = 
                    '<div class="error">' +
                    '<strong>Errore durante il caricamento dell\'editor:</strong><br>' +
                    error.message + '<br><br>' +
                    'Verificare che il server OnlyOffice sia attivo su: <?php echo $ONLYOFFICE_DS_PUBLIC_URL; ?>' +
                    '</div>';
            }
        };
        
        // Error handling
        window.onerror = function(msg, url, lineNo, columnNo, error) {
            console.error('JavaScript Error:', {
                message: msg,
                source: url,
                lineno: lineNo,
                colno: columnNo,
                error: error
            });
            return false;
        };
    </script>
</body>
</html>