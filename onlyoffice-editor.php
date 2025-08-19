<?php
require_once 'backend/config/onlyoffice.config.php';
require_once 'backend/config/config.php';
require_once 'backend/middleware/Auth.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$docId = $_GET['id'] ?? null;
if (!$docId) {
    die('Document ID required');
}

// Recupera documento dal database
$stmt = db_query("SELECT * FROM documenti WHERE id = ?", [$docId]);
$document = $stmt->fetch();

if (!$document) {
    die('Document not found');
}

// Genera configurazione OnlyOffice
$documentKey = md5($document['id'] . '_' . time());

// Determina estensione file
$fileExt = pathinfo($document['filename'] ?? 'document.docx', PATHINFO_EXTENSION);
if (!$fileExt) {
    $fileExt = 'docx'; // default
}

// URL per OnlyOffice (SEMPRE host.docker.internal)
$documentUrl = OnlyOfficeConfig::getDocumentUrl($docId);
$callbackUrl = OnlyOfficeConfig::getCallbackUrl($docId);

// URL pubblico del Document Server per caricare api.js
$onlyofficeApiUrl = OnlyOfficeConfig::getDocumentServerPublicUrl() . 'web-apps/apps/api/documents/api.js';

// Determina tipo di documento
$documentType = OnlyOfficeConfig::getDocumentType($fileExt);

$config = [
    'document' => [
        'fileType' => $fileExt,
        'key' => $documentKey,
        'title' => $document['nome'] ?? 'Documento',
        'url' => $documentUrl,
        'permissions' => [
            'download' => true,
            'edit' => true,
            'print' => true,
            'review' => true
        ]
    ],
    'documentType' => $documentType,
    'editorConfig' => [
        'callbackUrl' => $callbackUrl,
        'mode' => 'edit',
        'lang' => 'it',
        'user' => [
            'id' => (string)$auth->getUserId(),
            'name' => $auth->getUser()['nome'] ?? 'Utente'
        ],
        'customization' => [
            'autosave' => true,
            'compactHeader' => false,
            'compactToolbar' => false,
            'feedback' => false,
            'forcesave' => false
        ]
    ],
    'type' => 'desktop'
];
?>
<!DOCTYPE html>
<html>
<head>
    <title>OnlyOffice Editor - <?= htmlspecialchars($document['nome'] ?? 'Documento') ?></title>
    <meta charset="UTF-8">
    <style>
        body { margin: 0; padding: 0; overflow: hidden; }
        #placeholder { width: 100%; height: 100vh; }
        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            font-family: Arial, sans-serif;
            color: #666;
        }
        .error {
            padding: 20px;
            color: #d32f2f;
            background: #ffebee;
            border: 1px solid #ffcdd2;
            border-radius: 4px;
            margin: 20px;
            font-family: Arial, sans-serif;
        }
    </style>
</head>
<body>
    <div id="placeholder">
        <div class="loading">Caricamento editor in corso...</div>
    </div>
    
    <!-- Carica API OnlyOffice con URL completo -->
    <script type="text/javascript" src="<?= htmlspecialchars($onlyofficeApiUrl) ?>"></script>
    
    <script type="text/javascript">
        // Configurazione per OnlyOffice
        var config = <?= json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        
        // Debug info (rimuovere in produzione)
        console.log('OnlyOffice Configuration:', config);
        console.log('API URL:', '<?= htmlspecialchars($onlyofficeApiUrl) ?>');
        console.log('Environment:', '<?= OnlyOfficeConfig::isLocal() ? "LOCAL" : "PRODUCTION" ?>');
        console.log('Document URL:', config.document.url);
        console.log('Callback URL:', config.editorConfig.callbackUrl);
        
        // Inizializza editor quando API è pronta
        window.onload = function() {
            try {
                if (typeof DocsAPI === 'undefined') {
                    throw new Error('OnlyOffice API non caricata. Verificare la connessione al Document Server.');
                }
                
                window.docEditor = new DocsAPI.DocEditor("placeholder", config);
                console.log('Editor inizializzato con successo');
            } catch (error) {
                console.error('Errore inizializzazione OnlyOffice:', error);
                document.getElementById('placeholder').innerHTML = 
                    '<div class="error">' +
                    '<h3>Errore caricamento editor</h3>' +
                    '<p>' + error.message + '</p>' +
                    '<p>Verificare che OnlyOffice Document Server sia raggiungibile.</p>' +
                    '<p>URL API: <?= htmlspecialchars($onlyofficeApiUrl) ?></p>' +
                    '</div>';
            }
        };
        
        // Gestione errori di caricamento script
        window.onerror = function(msg, url, line, col, error) {
            console.error('Errore JavaScript:', {
                message: msg,
                source: url,
                line: line,
                column: col,
                error: error
            });
            return false;
        };
    </script>
</body>
</html>