<?php
/**
 * OnlyOffice Editor - Implementazione definitiva con host.docker.internal
 */

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

// Genera configurazione usando la nuova classe
$documentKey = OnlyOfficeConfig::generateDocumentKey($document['id']);
$filename = $document['filename'] ?? 'document.docx';

// IMPORTANTE: Usa SEMPRE host.docker.internal per gli URL interni
$documentUrl = OnlyOfficeConfig::getDocumentUrl($docId, $filename);
$callbackUrl = OnlyOfficeConfig::getCallbackUrl($docId);

// URL pubblico per caricare api.js (questo va al browser)
$onlyofficeApiUrl = OnlyOfficeConfig::getDocumentServerPublicUrl() . 'web-apps/apps/api/documents/api.js';

// Debug info
error_log("=== OnlyOffice Editor Configuration ===");
error_log("Environment: " . (OnlyOfficeConfig::isLocal() ? "LOCAL" : "PRODUCTION"));
error_log("Document URL (interno): " . $documentUrl);
error_log("Callback URL (interno): " . $callbackUrl);
error_log("API URL (pubblico): " . $onlyofficeApiUrl);

// Configurazione per l'editor
$config = [
    'document' => [
        'fileType' => pathinfo($filename, PATHINFO_EXTENSION),
        'key' => $documentKey,
        'title' => $document['nome'] ?? 'Documento',
        'url' => $documentUrl, // USA host.docker.internal
        'permissions' => [
            'download' => true,
            'edit' => true,
            'print' => true,
            'review' => true,
            'chat' => false // NON in customization!
        ]
    ],
    'documentType' => OnlyOfficeConfig::getDocumentType(pathinfo($filename, PATHINFO_EXTENSION)),
    'editorConfig' => [
        'callbackUrl' => $callbackUrl, // USA host.docker.internal
        'mode' => 'edit',
        'lang' => 'it',
        'user' => [
            'id' => (string)$auth->getUserId(),
            'name' => $auth->getUser()['nome'] ?? 'Utente'
        ],
        'customization' => [
            'autosave' => true,
            'compactHeader' => false,
            'feedback' => false,
            'forcesave' => false
        ]
    ],
    'type' => 'desktop'
];

// Aggiungi JWT se abilitato
if (OnlyOfficeConfig::JWT_ENABLED && OnlyOfficeConfig::JWT_SECRET) {
    $config['token'] = OnlyOfficeConfig::generateJWT($config);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <title>OnlyOffice Editor - <?= htmlspecialchars($document['nome'] ?? 'Documento') ?></title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { 
            margin: 0; 
            padding: 0; 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        #placeholder { 
            height: 100vh; 
            width: 100vw;
        }
        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            background: #f5f5f5;
        }
        .loading-text {
            font-size: 18px;
            color: #666;
        }
    </style>
</head>
<body>
    <div id="placeholder">
        <div class="loading">
            <div class="loading-text">Caricamento documento...</div>
        </div>
    </div>
    
    <!-- IMPORTANTE: Carica API con URL COMPLETO -->
    <script type="text/javascript" src="<?= htmlspecialchars($onlyofficeApiUrl) ?>"></script>
    
    <script type="text/javascript">
        // Debug configuration
        console.log('=== OnlyOffice Configuration ===');
        console.log('Environment:', '<?= OnlyOfficeConfig::isLocal() ? "LOCAL" : "PRODUCTION" ?>');
        console.log('Document URL (interno per DS):', '<?= $documentUrl ?>');
        console.log('Callback URL (interno per DS):', '<?= $callbackUrl ?>');
        console.log('API URL (pubblico):', '<?= $onlyofficeApiUrl ?>');
        console.log('Configuration:', <?= json_encode($config, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>);
        
        // Configurazione per l'editor
        var config = <?= json_encode($config, JSON_UNESCAPED_SLASHES) ?>;
        
        // Inizializza editor
        window.onload = function() {
            try {
                // Verifica che DocsAPI sia disponibile
                if (typeof DocsAPI === 'undefined') {
                    throw new Error('DocsAPI non è disponibile. Verifica che OnlyOffice Document Server sia raggiungibile.');
                }
                
                // Inizializza l'editor
                window.docEditor = new DocsAPI.DocEditor("placeholder", config);
                console.log('Editor inizializzato con successo');
                
                // Eventi dell'editor
                window.docEditor.onReady = function() {
                    console.log('Editor pronto');
                };
                
                window.docEditor.onDocumentStateChange = function(event) {
                    console.log('Stato documento:', event.data);
                };
                
                window.docEditor.onError = function(event) {
                    console.error('Errore editor:', event);
                    alert('Errore: ' + (event.data ? event.data.message : 'Errore sconosciuto'));
                };
                
            } catch (error) {
                console.error('Errore inizializzazione editor:', error);
                document.getElementById('placeholder').innerHTML = 
                    '<div class="loading">' +
                    '<div style="color: red; text-align: center;">' +
                    '<h2>Errore di caricamento</h2>' +
                    '<p>' + error.message + '</p>' +
                    '<p>Verifica che OnlyOffice Document Server sia attivo e raggiungibile.</p>' +
                    '</div>' +
                    '</div>';
            }
        };
        
        // Gestione errori globali
        window.onerror = function(msg, url, lineNo, columnNo, error) {
            console.error('Errore globale:', msg, error);
            return false;
        };
    </script>
</body>
</html>