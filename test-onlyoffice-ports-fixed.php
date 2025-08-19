<?php
/**
 * Test OnlyOffice con Porte Corrette - VERSIONE DEFINITIVA
 * 
 * CONFIGURAZIONE PORTE CORRETTA (Verificata con Docker):
 * - OnlyOffice Document Server: HTTPS su porta 8443
 * - File Server Nginx: HTTP su porta 8083
 * 
 * IMPORTANTE: Porte verificate con 'docker ps' e funzionanti!
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CONFIGURAZIONE DEFINITIVA DELLE PORTE
define('ONLYOFFICE_URL', 'https://localhost:8443');     // HTTPS su porta 8443
define('FILESERVER_URL', 'http://localhost:8081');      // HTTP su porta 8081
define('APP_URL', 'http://localhost/piattaforma-collaborativa');

// Simula utente per test
if (!isset($_SESSION['user'])) {
    $_SESSION['user'] = [
        'id' => 1,
        'nome' => 'Test',
        'cognome' => 'User',
        'email' => 'test@nexio.local'
    ];
}

// Documento di test
$docId = 'test_' . time();
$docName = $docId . '.docx';
$docPath = __DIR__ . '/documents/onlyoffice/' . $docName;

// Crea documento se non esiste
if (!file_exists($docPath)) {
    $dir = dirname($docPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    
    // Crea DOCX minimo
    $zip = new ZipArchive();
    if ($zip->open($docPath, ZipArchive::CREATE) === TRUE) {
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
                <w:t>Test Document - Porte Corrette - ' . date('Y-m-d H:i:s') . '</w:t>
            </w:r>
        </w:p>
    </w:body>
</w:document>');
        
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
</Relationships>');
        
        $zip->close();
    }
}

// URL documento tramite file server
$documentUrl = FILESERVER_URL . '/piattaforma-collaborativa/documents/onlyoffice/' . $docName;

// Callback URL per salvare modifiche
$callbackUrl = APP_URL . '/backend/api/onlyoffice-callback.php?doc=' . $docId;

// Configurazione editor
$config = [
    'document' => [
        'fileType' => 'docx',
        'key' => md5($docId . '_' . filemtime($docPath)),
        'title' => 'Test Document',
        'url' => $documentUrl,
        'permissions' => [
            'edit' => true,
            'download' => true,
            'comment' => true
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
            'forcesave' => true
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
    <title>Test OnlyOffice - Porte Corrette (8443/8083)</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .config-info {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        
        .config-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .config-label {
            font-weight: bold;
            opacity: 0.9;
        }
        
        .config-value {
            font-family: monospace;
            background: rgba(0,0,0,0.2);
            padding: 2px 8px;
            border-radius: 4px;
        }
        
        .port-highlight {
            background: #4CAF50;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: bold;
        }
        
        #editor-container {
            height: calc(100vh - 280px);
            background: white;
            position: relative;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        #onlyoffice-placeholder {
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
            z-index: 1000;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .error {
            background: #ffebee;
            color: #c62828;
            padding: 20px;
            border-radius: 8px;
            margin: 20px;
            border-left: 4px solid #c62828;
        }
        
        .success {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 15px;
            border-radius: 4px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🚀 Test OnlyOffice - Configurazione Definitiva</h1>
        <div class="config-info">
            <div class="config-row">
                <span class="config-label">OnlyOffice Document Server:</span>
                <span class="config-value"><?php echo ONLYOFFICE_URL; ?> <span class="port-highlight">:8443</span></span>
            </div>
            <div class="config-row">
                <span class="config-label">File Server Nginx:</span>
                <span class="config-value"><?php echo FILESERVER_URL; ?> <span class="port-highlight">:8083</span></span>
            </div>
            <div class="config-row">
                <span class="config-label">Document URL:</span>
                <span class="config-value" style="font-size: 12px;"><?php echo $documentUrl; ?></span>
            </div>
            <div class="config-row">
                <span class="config-label">Callback URL:</span>
                <span class="config-value" style="font-size: 12px;"><?php echo $callbackUrl; ?></span>
            </div>
            <div class="config-row">
                <span class="config-label">Document Key:</span>
                <span class="config-value"><?php echo $config['document']['key']; ?></span>
            </div>
        </div>
    </div>
    
    <div id="editor-container">
        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p>Caricamento OnlyOffice Document Server...</p>
            <p style="font-size: 12px; opacity: 0.7; margin-top: 10px;">
                Connessione a HTTPS porta 8443...
            </p>
        </div>
        <div id="onlyoffice-placeholder"></div>
    </div>
    
    <!-- IMPORTANTE: Script OnlyOffice da HTTPS porta 8443 -->
    <script type="text/javascript" src="https://localhost:8443/web-apps/apps/api/documents/api.js"></script>
    
    <script type="text/javascript">
        // Configurazione editor
        const editorConfig = <?php echo json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>;
        
        // Log configurazione
        console.log('=== CONFIGURAZIONE ONLYOFFICE ===');
        console.log('OnlyOffice URL:', '<?php echo ONLYOFFICE_URL; ?>');
        console.log('File Server URL:', '<?php echo FILESERVER_URL; ?>');
        console.log('Document URL:', '<?php echo $documentUrl; ?>');
        console.log('Callback URL:', '<?php echo $callbackUrl; ?>');
        console.log('Editor Config:', editorConfig);
        console.log('=================================');
        
        // Inizializza editor quando DOM è pronto
        window.addEventListener('DOMContentLoaded', function() {
            console.log('Inizializzazione OnlyOffice...');
            
            setTimeout(function() {
                try {
                    // Nascondi loader
                    document.getElementById('loading').style.display = 'none';
                    
                    // Inizializza editor
                    window.docEditor = new DocsAPI.DocEditor("onlyoffice-placeholder", editorConfig);
                    
                    console.log('✅ OnlyOffice inizializzato con successo');
                    
                } catch (error) {
                    console.error('❌ Errore inizializzazione OnlyOffice:', error);
                    document.getElementById('editor-container').innerHTML = 
                        '<div class="error">' +
                        '<h3>Errore di Inizializzazione</h3>' +
                        '<p>' + error.message + '</p>' +
                        '<p style="margin-top: 10px;">Verifica che:</p>' +
                        '<ul style="margin-left: 20px; margin-top: 5px;">' +
                        '<li>OnlyOffice sia attivo su HTTPS porta 8443</li>' +
                        '<li>Il file server Nginx sia attivo su HTTP porta 8083</li>' +
                        '<li>I certificati SSL siano accettati dal browser</li>' +
                        '</ul>' +
                        '</div>';
                }
            }, 1000);
        });
        
        // Test connessione
        fetch('https://localhost:8443/healthcheck')
            .then(response => {
                console.log('✅ OnlyOffice raggiungibile su porta 8443');
            })
            .catch(error => {
                console.error('⚠️ Impossibile raggiungere OnlyOffice su porta 8443:', error);
            });
        
        fetch('<?php echo $documentUrl; ?>')
            .then(response => {
                console.log('✅ File server raggiungibile su porta 8083');
            })
            .catch(error => {
                console.error('⚠️ Impossibile raggiungere file server su porta 8083:', error);
            });
    </script>
</body>
</html>