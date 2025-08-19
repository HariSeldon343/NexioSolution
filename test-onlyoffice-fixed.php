<?php
/**
 * Test OnlyOffice Fixed - Usa HTTP porta 8082
 */

require_once 'backend/config/config.php';
require_once 'backend/config/onlyoffice.config.php';

// Test connessione
$testResult = OnlyOfficeConfig::testConnection();
$onlyofficeUrl = OnlyOfficeConfig::getDocumentServerUrl();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>OnlyOffice Test - HTTP Fixed</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        .status {
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        #onlyoffice-editor {
            width: 100%;
            height: 600px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin: 20px 0;
        }
        pre {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <h1>🔧 OnlyOffice Test - Configurazione Corretta</h1>
    
    <div class="status info">
        <strong>Configurazione Attuale:</strong><br>
        OnlyOffice URL: <?php echo $onlyofficeUrl; ?><br>
        Protocollo: HTTP (sviluppo locale)<br>
        Porta: 8082<br>
        Container: nexio-documentserver
    </div>
    
    <?php if ($testResult['success']): ?>
        <div class="status success">
            <strong>✅ Connessione OnlyOffice OK!</strong><br>
            HTTP Code: <?php echo $testResult['http_code']; ?><br>
            Response: <?php echo $testResult['response']; ?>
        </div>
        
        <h2>Test Editor</h2>
        <div id="onlyoffice-editor"></div>
        
        <!-- Script OnlyOffice - HTTP su porta 8082 -->
        <script type="text/javascript" src="<?php echo $onlyofficeUrl; ?>web-apps/apps/api/documents/api.js"></script>
        
        <script type="text/javascript">
            // Configurazione documento di test
            var config = {
                document: {
                    fileType: "docx",
                    key: "test_<?php echo time(); ?>",
                    title: "Test Document.docx",
                    url: "<?php echo OnlyOfficeConfig::getDocumentUrlForBrowser('test_document_' . time() . '.docx'); ?>",
                    permissions: {
                        edit: true,
                        download: true,
                        print: true
                    }
                },
                documentType: "word",
                editorConfig: {
                    callbackUrl: "<?php echo OnlyOfficeConfig::getCallbackUrl('test'); ?>",
                    lang: "it",
                    mode: "edit",
                    user: {
                        id: "1",
                        name: "Test User"
                    },
                    customization: {
                        autosave: true,
                        compactHeader: false,
                        compactToolbar: false,
                        hideRightMenu: false,
                        logo: {
                            image: "/piattaforma-collaborativa/assets/images/nexio-logo.svg",
                            url: "/piattaforma-collaborativa/"
                        },
                        goback: {
                            text: "Torna a Nexio",
                            url: "/piattaforma-collaborativa/filesystem.php"
                        }
                    }
                },
                width: "100%",
                height: "100%",
                type: "desktop"
            };
            
            // Crea documento di test se non esiste
            <?php
            $testFile = OnlyOfficeConfig::DOCUMENTS_PATH . '/test_document_' . time() . '.docx';
            if (!file_exists($testFile)) {
                // Crea un file DOCX minimo
                $zip = new ZipArchive();
                if ($zip->open($testFile, ZipArchive::CREATE) === TRUE) {
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
                <w:t>Test Document - OnlyOffice Integration</w:t>
            </w:r>
        </w:p>
    </w:body>
</w:document>');
                    
                    $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
</Relationships>');
                    
                    $zip->close();
                    echo "// Documento di test creato: $testFile\n";
                } else {
                    echo "// Errore nella creazione del documento di test\n";
                }
            }
            ?>
            
            // Aggiorna URL documento con il file appena creato
            config.document.url = "http://localhost/piattaforma-collaborativa/documents/onlyoffice/<?php echo basename($testFile); ?>";
            
            console.log('Configurazione OnlyOffice:', config);
            console.log('URL API:', '<?php echo $onlyofficeUrl; ?>web-apps/apps/api/documents/api.js');
            
            // Verifica che DocsAPI sia caricato
            window.addEventListener('load', function() {
                if (typeof DocsAPI !== 'undefined') {
                    console.log('✅ DocsAPI caricato correttamente');
                    
                    // Inizializza editor
                    try {
                        var docEditor = new DocsAPI.DocEditor("onlyoffice-editor", config);
                        console.log('✅ Editor inizializzato');
                    } catch (error) {
                        console.error('❌ Errore inizializzazione editor:', error);
                        document.getElementById('onlyoffice-editor').innerHTML = 
                            '<div class="error" style="padding: 20px;">Errore: ' + error.message + '</div>';
                    }
                } else {
                    console.error('❌ DocsAPI non trovato!');
                    document.getElementById('onlyoffice-editor').innerHTML = 
                        '<div class="error" style="padding: 20px;">DocsAPI non caricato. Verificare la connessione a OnlyOffice.</div>';
                }
            });
        </script>
        
    <?php else: ?>
        <div class="status error">
            <strong>❌ Errore Connessione OnlyOffice</strong><br>
            HTTP Code: <?php echo $testResult['http_code']; ?><br>
            Errore: <?php echo $testResult['error']; ?><br>
            URL testato: <?php echo $testResult['url']; ?>
        </div>
        
        <h3>Debug Info:</h3>
        <pre><?php print_r($testResult); ?></pre>
        
        <h3>Soluzioni:</h3>
        <ol>
            <li>Verificare che Docker sia in esecuzione: <code>docker ps</code></li>
            <li>Avviare OnlyOffice: <code>cd onlyoffice && docker-compose up -d</code></li>
            <li>Controllare i log: <code>docker logs nexio-documentserver</code></li>
            <li>Verificare la porta 8082: <code>netstat -an | findstr :8082</code></li>
        </ol>
    <?php endif; ?>
    
    <h2>Informazioni di Debug</h2>
    <pre>
OnlyOffice URL: <?php echo $onlyofficeUrl; ?>
Document Server Internal: <?php echo OnlyOfficeConfig::ONLYOFFICE_DS_INTERNAL_URL; ?>
Host Docker Internal: <?php echo OnlyOfficeConfig::DOCUMENT_HOST_INTERNAL; ?>
Documents Path: <?php echo OnlyOfficeConfig::DOCUMENTS_PATH; ?>
Is Docker Desktop: <?php echo OnlyOfficeConfig::isDockerDesktop() ? 'Yes' : 'No'; ?>
PHP OS: <?php echo PHP_OS_FAMILY; ?>
    </pre>
</body>
</html>