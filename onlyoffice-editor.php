<?php
/**
 * OnlyOffice Document Editor
 * Full integration with JWT authentication and multi-tenant support
 */

// Initialize authentication
require_once 'backend/middleware/Auth.php';
$auth = Auth::getInstance();
$auth->requireAuth();

// Load configuration
require_once 'backend/config/config.php';
require_once 'backend/config/onlyoffice.config.php';

// Get document ID from request
$documentId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$documentId) {
    header('Location: filesystem.php');
    exit;
}

// Get user information
$user = $auth->getUser();
$userId = $user['id'];
$userName = $user['nome'] . ' ' . $user['cognome'];
$isSuperAdmin = $auth->isSuperAdmin();
$aziendaId = $auth->getCurrentAzienda();

// Fetch document from database
$query = "SELECT d.*, c.nome as cartella_nome, a.nome AS nome_azienda 
          FROM documenti d 
          LEFT JOIN cartelle c ON d.cartella_id = c.id 
          LEFT JOIN aziende a ON d.azienda_id = a.id 
          WHERE d.id = ?";

// Add company filter for non-super admins
if (!$isSuperAdmin && $aziendaId) {
    $query .= " AND (d.azienda_id = ? OR d.azienda_id IS NULL)";
    $stmt = db_query($query, [$documentId, $aziendaId]);
} else {
    $stmt = db_query($query, [$documentId]);
}

$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    $_SESSION['error'] = "Documento non trovato o non hai i permessi per visualizzarlo.";
    header('Location: filesystem.php');
    exit;
}

// Check file existence
$percorsoFile = $document['percorso_file'] ?? '';
if (empty($percorsoFile)) {
    $_SESSION['error'] = "Percorso file non valido.";
    header('Location: filesystem.php');
    exit;
}
$filePath = __DIR__ . '/' . $percorsoFile;
if (!file_exists($filePath)) {
    $_SESSION['error'] = "File non trovato sul server.";
    header('Location: filesystem.php');
    exit;
}

// Determine document type for OnlyOffice
$nomeFile = $document['nome_file'] ?? '';
$extension = $nomeFile ? strtolower(pathinfo($nomeFile, PATHINFO_EXTENSION)) : '';
$documentType = 'word'; // Default

if (isset($ONLYOFFICE_DOCUMENT_TYPES[$extension])) {
    $documentType = $ONLYOFFICE_DOCUMENT_TYPES[$extension];
} else {
    // Map by extension groups
    if (in_array($extension, ['docx', 'doc', 'odt', 'rtf', 'txt'])) {
        $documentType = 'word';
    } elseif (in_array($extension, ['xlsx', 'xls', 'ods', 'csv'])) {
        $documentType = 'cell';
    } elseif (in_array($extension, ['pptx', 'ppt', 'odp'])) {
        $documentType = 'slide';
    } elseif ($extension === 'pdf') {
        $documentType = 'pdf';
    }
}

// Check if format is editable
$canEdit = in_array($extension, ['docx', 'xlsx', 'pptx', 'txt', 'csv']);

// Check user permissions for editing
$userRole = $user['role'] ?? 'utente';
$hasEditPermission = $isSuperAdmin || $userRole === 'utente_speciale' || $document['creato_da'] == $userId;

// Determine mode (view or edit)
$mode = (isset($_GET['mode']) && $_GET['mode'] === 'edit' && $canEdit && $hasEditPermission) ? 'edit' : 'view';

// Generate document key (unique identifier for OnlyOffice)
// Key changes when document is modified to force refresh
$documentKey = md5($document['id'] . '_' . $document['data_modifica'] . '_v' . ($document['versione'] ?? 1));

// Build document URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$documentUrl = $protocol . '://' . $host . $basePath . '/backend/api/onlyoffice-document.php?id=' . $documentId . '&token=';

// Generate callback URL
$callbackUrl = $ONLYOFFICE_CALLBACK_URL . '?id=' . $documentId;

// Build OnlyOffice configuration
$config = [
    'type' => $mode === 'view' ? 'embedded' : 'desktop',
    'documentType' => $documentType,
    'document' => [
        'title' => $document['nome_file'],
        'url' => $documentUrl, // Token will be added by JavaScript
        'fileType' => $extension,
        'key' => $documentKey,
        'info' => [
            'owner' => $document['nome_azienda'] ?? 'Sistema',
            'uploaded' => isset($document['data_caricamento']) ? date('c', strtotime($document['data_caricamento'])) : date('c'),
            'favorite' => false
        ],
        'permissions' => [
            'comment' => $hasEditPermission,
            'download' => true,
            'edit' => $mode === 'edit',
            'fillForms' => true,
            'modifyContentControl' => $hasEditPermission,
            'modifyFilter' => $hasEditPermission,
            'print' => true,
            'review' => $hasEditPermission
        ]
    ],
    'editorConfig' => [
        'actionLink' => null,
        'mode' => $mode,
        'lang' => 'it',
        'callbackUrl' => $mode === 'edit' ? $callbackUrl : null,
        'createUrl' => null,
        'templates' => [],
        'user' => [
            'id' => (string)$userId,
            'name' => $userName,
            'group' => $userRole
        ],
        'embedded' => [
            'saveUrl' => null,
            'embedUrl' => null,
            'shareUrl' => null,
            'toolbarDocked' => 'top'
        ],
        'customization' => [
            'about' => true,
            'comments' => $hasEditPermission,
            'compactHeader' => false,
            'compactToolbar' => true, // Toolbar compatta per ottimizzare lo spazio
            'toolbarHideFileName' => true, // Nasconde il nome del file nella toolbar
            'compatibleFeatures' => false,
            'customer' => [
                'address' => $document['nome_azienda'] ? 'Azienda: ' . $document['nome_azienda'] : '',
                'info' => $document['nome_azienda'] ?? 'Nexio Platform',
                'logo' => $protocol . '://' . $host . $basePath . '/assets/images/nexio-logo.svg',
                'logoDark' => $protocol . '://' . $host . $basePath . '/assets/images/nexio-logo.svg',
                'mail' => 'support@nexio.com',
                'name' => $document['nome_azienda'] ?? 'Nexio',
                'www' => $protocol . '://' . $host . $basePath
            ],
            'feedback' => false,
            'forcesave' => true,
            'goback' => [
                'blank' => false,
                'requestClose' => false,
                'text' => 'Torna ai documenti',
                'url' => $protocol . '://' . $host . $basePath . '/filesystem.php'
            ],
            'help' => true,
            'hideRightMenu' => false,
            'logo' => [
                'image' => $protocol . '://' . $host . $basePath . '/assets/images/nexio-logo.svg',
                'imageDark' => $protocol . '://' . $host . $basePath . '/assets/images/nexio-logo.svg',
                'url' => $protocol . '://' . $host . $basePath . '/dashboard.php'
            ],
            'macros' => false,
            'macrosMode' => 'warn',
            'mentionShare' => true, // Abilita menzioni per collaborazione
            'mobileForceView' => true,
            'plugins' => true, // Abilita plugin se configurati
            'review' => [
                'hideReviewDisplay' => false,
                'showReviewChanges' => false,
                'reviewDisplay' => 'original',
                'trackChanges' => $hasEditPermission,
                'hoverMode' => false
            ],
            'spellcheck' => true,
            'submitForm' => false,
            'toolbarNoTabs' => false,
            'unit' => 'cm',
            'zoom' => 100,
            // Personalizzazione colori tema Nexio
            'uiTheme' => 'theme-dark',
            'color' => '#007bff', // Colore primario Nexio
            // Configurazione mobile ottimizzata
            'mobile' => [
                'forceView' => true,
                'scalable' => true
            ],
            // Opzioni avanzate di collaborazione
            'chat' => $hasEditPermission,
            'commentAuthorOnly' => false,
            'showReviewChanges' => $hasEditPermission,
            'trackChanges' => $hasEditPermission,
            'compactHeader' => true,
            'leftMenu' => true,
            'rightMenu' => true,
            'toolbar' => true,
            'statusBar' => true,
            'autosave' => true,
            'forceSave' => true,
            'hideNotes' => false
        ],
        'coEditing' => [
            'mode' => 'fast', // Modalità collaborazione in tempo reale
            'change' => true,
            'selectionColor' => [
                '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', 
                '#FFEAA7', '#DDA0DD', '#98D8C8', '#FFB6C1'
            ] // Colori per distinguere gli utenti in collaborazione
        ],
        'plugins' => [
            'autostart' => [
                // Lista plugin da avviare automaticamente
                'asc.{FFE1C0F4-904F-41D2-929C-8A1DF57E8C7A}', // Translator
                'asc.{38E022EA-AD92-45FC-B22B-49DF39746DB4}', // Thesaurus
                'asc.{B509123E-6335-40BD-B965-91EB799346EB}'  // Word Counter
            ],
            'pluginsData' => [
                // Configurazione specifica dei plugin
                'asc.{FFE1C0F4-904F-41D2-929C-8A1DF57E8C7A}' => [
                    'settings' => [
                        'defaultLanguage' => 'it'
                    ]
                ]
            ]
        ]
    ],
    'events' => [
        'onAppReady' => 'onAppReady',
        'onDocumentStateChange' => 'onDocumentStateChange',
        'onError' => 'onError',
        'onWarning' => 'onWarning',
        'onInfo' => 'onInfo',
        'onRequestHistory' => null,
        'onRequestHistoryData' => null,
        'onRequestHistoryClose' => null
    ]
];

// Generate JWT token if enabled
$jwtToken = '';
if ($ONLYOFFICE_JWT_ENABLED) {
    $jwtToken = generateOnlyOfficeJWT($config);
}

// Activity logging
require_once 'backend/utils/ActivityLogger.php';
$logger = ActivityLogger::getInstance();
$logger->log(
    'documento',
    $mode === 'edit' ? 'modifica' : 'visualizzazione',
    $documentId,
    "Apertura documento in OnlyOffice: {$document['nome_file']}"
);

// Page setup
$pageTitle = ($mode === 'edit' ? 'Modifica' : 'Visualizza') . ': ' . htmlspecialchars($document['nome_file']);
$customCSS = [];
$customJS = [];
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Nexio</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <style>
        body {
            margin: 0;
            padding: 0;
            overflow: hidden;
            background: #f8f9fa;
        }
        
        .editor-header {
            background: #fff;
            border-bottom: 1px solid #dee2e6;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .editor-header .document-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .editor-header .document-icon {
            font-size: 24px;
            color: #007bff;
        }
        
        .editor-header .document-title {
            font-size: 18px;
            font-weight: 500;
            color: #333;
            margin: 0;
        }
        
        .editor-header .document-status {
            font-size: 12px;
            color: #6c757d;
            margin: 0;
        }
        
        .editor-header .actions {
            display: flex;
            gap: 10px;
        }
        
        #onlyoffice-editor {
            width: 100%;
            height: calc(100vh - 60px);
            border: none;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.95);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .loading-text {
            margin-top: 20px;
            font-size: 16px;
            color: #666;
        }
        
        .error-message {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 500px;
            display: none;
        }
        
        .error-message.show {
            display: block;
        }
        
        .error-message h3 {
            color: #dc3545;
            margin-bottom: 15px;
        }
        
        .error-message p {
            color: #666;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .editor-header {
                padding: 10px;
            }
            
            .editor-header .document-title {
                font-size: 14px;
            }
            
            .editor-header .document-status {
                display: none;
            }
            
            .editor-header .actions .btn-text {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Editor Header -->
    <div class="editor-header">
        <div class="document-info">
            <i class="document-icon fas fa-file-<?php echo $documentType; ?>"></i>
            <div>
                <h1 class="document-title"><?php echo htmlspecialchars($document['nome_file']); ?></h1>
                <p class="document-status">
                    <?php if ($mode === 'edit'): ?>
                        <span class="badge bg-success">Modalità modifica</span>
                    <?php else: ?>
                        <span class="badge bg-info">Modalità visualizzazione</span>
                    <?php endif; ?>
                    <?php if ($document['nome_azienda']): ?>
                        <span class="ms-2"><?php echo htmlspecialchars($document['nome_azienda']); ?></span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <div class="actions">
            <?php if ($mode === 'view' && $canEdit && $hasEditPermission): ?>
                <a href="?id=<?php echo $documentId; ?>&mode=edit" class="btn btn-primary btn-sm">
                    <i class="fas fa-edit"></i>
                    <span class="btn-text"> Modifica</span>
                </a>
            <?php elseif ($mode === 'edit'): ?>
                <a href="?id=<?php echo $documentId; ?>&mode=view" class="btn btn-secondary btn-sm">
                    <i class="fas fa-eye"></i>
                    <span class="btn-text"> Visualizza</span>
                </a>
            <?php endif; ?>
            
            <a href="filesystem.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left"></i>
                <span class="btn-text"> Torna ai documenti</span>
            </a>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Caricamento documento in corso...</div>
    </div>
    
    <!-- Error Message -->
    <div class="error-message" id="errorMessage">
        <h3><i class="fas fa-exclamation-triangle"></i> Errore</h3>
        <p id="errorText">Si è verificato un errore durante il caricamento del documento.</p>
        <a href="filesystem.php" class="btn btn-primary">Torna ai documenti</a>
    </div>
    
    <!-- OnlyOffice Editor Container -->
    <div id="onlyoffice-editor"></div>
    
    <!-- OnlyOffice Document Server API -->
    <script type="text/javascript" src="<?php echo $ONLYOFFICE_DS_PUBLIC_URL; ?>/web-apps/apps/api/documents/api.js"></script>
    
    <!-- Editor Initialization -->
    <script type="text/javascript">
        // Configuration
        const documentId = <?php echo json_encode($documentId); ?>;
        const config = <?php echo json_encode($config); ?>;
        const jwtToken = <?php echo json_encode($jwtToken); ?>;
        const jwtEnabled = <?php echo json_encode($ONLYOFFICE_JWT_ENABLED); ?>;
        
        // Generate access token for document download
        async function getDocumentAccessToken() {
            try {
                const response = await fetch('backend/api/onlyoffice-auth.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'generate_token',
                        document_id: documentId
                    })
                });
                
                const data = await response.json();
                if (data.success && data.token) {
                    return data.token;
                }
                throw new Error(data.error || 'Failed to generate token');
            } catch (error) {
                console.error('Error getting document token:', error);
                return null;
            }
        }
        
        // Event handlers
        function onAppReady() {
            console.log('OnlyOffice editor ready');
            document.getElementById('loadingOverlay').style.display = 'none';
        }
        
        function onDocumentStateChange(event) {
            console.log('Document state changed:', event.data);
        }
        
        function onError(event) {
            console.error('OnlyOffice error:', event.data);
            document.getElementById('loadingOverlay').style.display = 'none';
            document.getElementById('errorText').textContent = event.data.errorDescription || 'Si è verificato un errore durante il caricamento del documento.';
            document.getElementById('errorMessage').classList.add('show');
        }
        
        function onWarning(event) {
            console.warn('OnlyOffice warning:', event.data);
        }
        
        function onInfo(event) {
            console.info('OnlyOffice info:', event.data);
        }
        
        // Initialize editor
        async function initializeEditor() {
            try {
                // Get document access token
                const accessToken = await getDocumentAccessToken();
                if (!accessToken) {
                    throw new Error('Unable to get document access token');
                }
                
                // Update document URL with token
                config.document.url += accessToken;
                
                // Add JWT token if enabled
                if (jwtEnabled && jwtToken) {
                    config.token = jwtToken;
                }
                
                // Create editor instance
                window.docEditor = new DocsAPI.DocEditor('onlyoffice-editor', config);
                
            } catch (error) {
                console.error('Error initializing editor:', error);
                document.getElementById('loadingOverlay').style.display = 'none';
                document.getElementById('errorText').textContent = 'Impossibile inizializzare l\'editor. ' + error.message;
                document.getElementById('errorMessage').classList.add('show');
            }
        }
        
        // Auto-save notification
        let saveTimeout;
        function showSaveNotification() {
            clearTimeout(saveTimeout);
            const notification = document.createElement('div');
            notification.className = 'save-notification';
            notification.innerHTML = '<i class="fas fa-check-circle"></i> Documento salvato';
            notification.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: #28a745;
                color: white;
                padding: 10px 20px;
                border-radius: 4px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.2);
                z-index: 1000;
                animation: slideIn 0.3s ease;
            `;
            document.body.appendChild(notification);
            
            saveTimeout = setTimeout(() => {
                notification.remove();
            }, 3000);
        }
        
        // Handle visibility change
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                console.log('Editor hidden');
            } else {
                console.log('Editor visible');
            }
        });
        
        // Handle before unload
        window.addEventListener('beforeunload', function(e) {
            if (window.docEditor && config.editorConfig.mode === 'edit') {
                // Only show warning if in edit mode
                const message = 'Hai modifiche non salvate. Sei sicuro di voler uscire?';
                e.returnValue = message;
                return message;
            }
        });
        
        // Initialize on DOM ready
        document.addEventListener('DOMContentLoaded', initializeEditor);
    </script>
</body>
</html>