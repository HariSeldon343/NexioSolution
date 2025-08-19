<?php
/**
 * Test OnlyOffice Unificato con Porte Corrette
 * Configurazione allineata con i container Docker attivi:
 * - nexio-onlyoffice: porta 8443 (HTTPS)
 * - nexio-fileserver: porta 8083 (HTTP)
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configurazione corretta delle porte (verificate con docker ps)
define('ONLYOFFICE_DS_URL', 'https://localhost:8443');  // HTTPS su porta 8443
define('ONLYOFFICE_FS_URL', 'http://localhost:8083');   // File server Nginx su porta 8083
define('APP_BASE_URL', 'http://localhost/piattaforma-collaborativa');

// Simula utente autenticato per test
if (!isset($_SESSION['user'])) {
    $_SESSION['user'] = [
        'id' => 1,
        'nome' => 'Test',
        'cognome' => 'User',
        'email' => 'test@nexio.local'
    ];
}

// Genera documento di test
$docId = $_GET['doc'] ?? 'test_document_' . time();
$docFilename = $docId . '.docx';
$docPath = __DIR__ . '/documents/onlyoffice/' . $docFilename;

// Crea documento di test se non esiste
if (!file_exists($docPath)) {
    // Crea directory se non esiste
    $dir = dirname($docPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    
    // Crea un file DOCX minimo
    $zip = new ZipArchive();
    if ($zip->open($docPath, ZipArchive::CREATE) === TRUE) {
        // Struttura minima DOCX
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>');
        
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>');
        
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <w:p>
            <w:r>
                <w:t>Test Document - Created at ' . date('Y-m-d H:i:s') . '</w:t>
            </w:r>
        </w:p>
    </w:body>
</w:document>');
        
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
</Relationships>');
        
        $zip->close();
    } else {
        die("Impossibile creare il documento di test");
    }
}

// URL del documento accessibile via file server
$documentUrl = ONLYOFFICE_FS_URL . '/piattaforma-collaborativa/documents/onlyoffice/' . $docFilename;

// URL di callback per salvare le modifiche (usa APP_BASE_URL, non file server)
$callbackUrl = APP_BASE_URL . '/backend/api/onlyoffice-callback.php?doc=' . $docId;

// Configurazione editor
$config = [
    'document' => [
        'fileType' => 'docx',
        'key' => md5($docId . '_' . filemtime($docPath)),
        'title' => 'Test Document ' . $docId,
        'url' => $documentUrl,
        'permissions' => [
            'comment' => true,
            'download' => true,
            'edit' => true,
            'fillForms' => true,
            'modifyFilter' => true,
            'modifyContentControl' => true,
            'review' => true,
            'commentGroups' => [],
            'userInfoGroups' => []
        ]
    ],
    'documentType' => 'word',
    'editorConfig' => [
        'callbackUrl' => $callbackUrl,
        'lang' => 'it',
        'mode' => 'edit',
        'user' => [
            'id' => (string)$_SESSION['user']['id'],
            'name' => $_SESSION['user']['nome'] . ' ' . $_SESSION['user']['cognome']
        ],
        'customization' => [
            'autosave' => true,
            'comments' => true,
            'compactHeader' => false,
            'compactToolbar' => false,
            'forcesave' => true,
            'help' => true,
            'hideRightMenu' => false,
            'plugins' => true,
            'spellcheck' => true,
            'toolbarHideFileName' => false,
            'toolbarNoTabs' => false,
            'trackChanges' => false,
            'unit' => 'cm',
            'zoom' => 100,
            'logo' => [
                'image' => APP_BASE_URL . '/assets/images/nexio-logo.svg',
                'url' => APP_BASE_URL
            ],
            'goback' => [
                'text' => 'Torna a Nexio',
                'url' => APP_BASE_URL . '/filesystem.php'
            ]
        ]
    ],
    'type' => 'desktop',
    'width' => '100%',
    'height' => '100%'
];

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test OnlyOffice Unificato - Porte Corrette</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f5f5;
        }
        
        .header {
            background: #1a73e8;
            color: white;
            padding: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 1.5rem;
            margin: 0;
        }
        
        .info-panel {
            background: white;
            padding: 1rem;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
        }
        
        .info-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 0.75rem;
        }
        
        .info-card h3 {
            color: #495057;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-item {
            display: flex;
            margin-bottom: 0.25rem;
            font-size: 0.875rem;
        }
        
        .info-label {
            font-weight: 600;
            color: #6c757d;
            margin-right: 0.5rem;
            min-width: 100px;
        }
        
        .info-value {
            color: #212529;
            word-break: break-all;
            font-family: 'Courier New', monospace;
        }
        
        .status {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-size: 0.75rem;
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
        
        .status.warning {
            background: #fff3cd;
            color: #856404;
        }
        
        #editor-container {
            height: calc(100vh - 250px);
            background: white;
            position: relative;
        }
        
        #onlyoffice-editor {
            width: 100%;
            height: 100%;
            border: none;
        }
        
        .loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
        }
        
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #1a73e8;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            margin: 1rem;
        }
        
        .actions {
            margin-top: 0.5rem;
        }
        
        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: #1a73e8;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.875rem;
            margin-right: 0.5rem;
            cursor: pointer;
            border: none;
        }
        
        .btn:hover {
            background: #1557b0;
        }
        
        .btn.secondary {
            background: #6c757d;
        }
        
        .btn.secondary:hover {
            background: #545b62;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🧪 Test OnlyOffice Unificato - Configurazione Corretta</h1>
    </div>
    
    <div class="info-panel">
        <div class="info-grid">
            <div class="info-card">
                <h3>📡 Configurazione Server</h3>
                <div class="info-item">
                    <span class="info-label">DS URL:</span>
                    <span class="info-value"><?php echo ONLYOFFICE_DS_URL; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">FS URL:</span>
                    <span class="info-value"><?php echo ONLYOFFICE_FS_URL; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">App URL:</span>
                    <span class="info-value"><?php echo APP_BASE_URL; ?></span>
                </div>
            </div>
            
            <div class="info-card">
                <h3>📄 Documento</h3>
                <div class="info-item">
                    <span class="info-label">ID:</span>
                    <span class="info-value"><?php echo $docId; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">File:</span>
                    <span class="info-value"><?php echo $docFilename; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Exists:</span>
                    <span class="info-value">
                        <?php if (file_exists($docPath)): ?>
                            <span class="status success">YES</span>
                        <?php else: ?>
                            <span class="status error">NO</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            
            <div class="info-card">
                <h3>🔗 URLs</h3>
                <div class="info-item">
                    <span class="info-label">Document:</span>
                    <span class="info-value" style="font-size: 0.75rem;"><?php echo $documentUrl; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Callback:</span>
                    <span class="info-value" style="font-size: 0.75rem;"><?php echo $callbackUrl; ?></span>
                </div>
            </div>
        </div>
        
        <div class="actions">
            <button class="btn" onclick="testConnection()">🔍 Test Connessione</button>
            <button class="btn secondary" onclick="location.reload()">🔄 Ricarica</button>
            <a href="<?php echo $documentUrl; ?>" target="_blank" class="btn secondary">📥 Download Diretto</a>
        </div>
    </div>
    
    <div id="editor-container">
        <div class="loading">
            <div class="spinner"></div>
            <p>Caricamento editor OnlyOffice...</p>
        </div>
        <div id="onlyoffice-editor"></div>
    </div>
    
    <!-- OnlyOffice API - Usa URL completo HTTPS -->
    <script type="text/javascript" src="https://localhost:8443/web-apps/apps/api/documents/api.js"></script>
    
    <script type="text/javascript">
        // Configurazione editor
        const editorConfig = <?php echo json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>;
        
        // Funzione per testare la connessione
        async function testConnection() {
            try {
                const urls = [
                    '<?php echo ONLYOFFICE_DS_URL; ?>/healthcheck',
                    '<?php echo $documentUrl; ?>',
                    '<?php echo APP_BASE_URL; ?>/backend/api/get-csrf-token.php'
                ];
                
                console.log('Testing connections...');
                
                for (const url of urls) {
                    console.log(`Testing: ${url}`);
                    try {
                        const response = await fetch(url, {
                            method: 'GET',
                            mode: 'no-cors'
                        });
                        console.log(`✓ ${url} - Reachable`);
                    } catch (error) {
                        console.error(`✗ ${url} - ${error.message}`);
                    }
                }
                
                alert('Test completato! Controlla la console per i dettagli.');
            } catch (error) {
                console.error('Test error:', error);
                alert('Errore nel test: ' + error.message);
            }
        }
        
        // Inizializza editor quando la pagina è pronta
        window.addEventListener('load', function() {
            console.log('Initializing OnlyOffice editor...');
            console.log('Configuration:', editorConfig);
            
            try {
                // Nascondi il loader
                document.querySelector('.loading').style.display = 'none';
                
                // Inizializza l'editor
                window.docEditor = new DocsAPI.DocEditor("onlyoffice-editor", editorConfig);
                
                console.log('✓ Editor initialized successfully');
                
                // Event handlers
                window.docEditor.events = {
                    onReady: function() {
                        console.log('✓ Editor ready');
                    },
                    onDocumentStateChange: function(event) {
                        console.log('Document state changed:', event.data);
                    },
                    onError: function(event) {
                        console.error('Editor error:', event);
                        document.getElementById('editor-container').innerHTML = 
                            '<div class="error-message">Errore nell\'editor: ' + JSON.stringify(event) + '</div>';
                    },
                    onWarning: function(event) {
                        console.warn('Editor warning:', event);
                    },
                    onInfo: function(event) {
                        console.info('Editor info:', event);
                    }
                };
                
            } catch (error) {
                console.error('Failed to initialize editor:', error);
                document.getElementById('editor-container').innerHTML = 
                    '<div class="error-message">' +
                    '<h3>Errore di inizializzazione</h3>' +
                    '<p>' + error.message + '</p>' +
                    '<p>Verifica che OnlyOffice sia attivo su ' + '<?php echo ONLYOFFICE_DS_URL; ?>' + '</p>' +
                    '<p>Controlla la console del browser per maggiori dettagli.</p>' +
                    '</div>';
            }
        });
        
        // Debug info
        console.log('=== OnlyOffice Test Configuration ===');
        console.log('Document Server:', '<?php echo ONLYOFFICE_DS_URL; ?>');
        console.log('File Server:', '<?php echo ONLYOFFICE_FS_URL; ?>');
        console.log('Document URL:', '<?php echo $documentUrl; ?>');
        console.log('Callback URL:', '<?php echo $callbackUrl; ?>');
        console.log('=====================================');
    </script>
</body>
</html>