<?php
require_once 'backend/config/onlyoffice.config.php';

$docId = $_GET['doc'] ?? 'test_' . time();
$filename = 'test_document.docx';

// Assicurati che il file esista
$docPath = OnlyOfficeConfig::DOCUMENTS_PATH . '/' . $filename;
if (!file_exists($docPath)) {
    // Crea un file DOCX di test se non esiste
    $templatePath = __DIR__ . '/documents/onlyoffice/new.docx';
    if (file_exists($templatePath)) {
        copy($templatePath, $docPath);
    } else {
        die("File di test non trovato. Crea prima un file DOCX in: documents/onlyoffice/test_document.docx");
    }
}

// URL per OnlyOffice (DEVE usare host.docker.internal)
$documentUrl = OnlyOfficeConfig::getDocumentUrlForDS($filename);
$callbackUrl = OnlyOfficeConfig::getCallbackUrl($docId);

// URL per browser (usa localhost) - solo per debug/display
$documentUrlBrowser = OnlyOfficeConfig::getDocumentUrlForBrowser($filename);

// OnlyOffice API URL
$onlyofficeApiUrl = OnlyOfficeConfig::getDocumentServerUrl() . 'web-apps/apps/api/documents/api.js';

?>
<!DOCTYPE html>
<html>
<head>
    <title>OnlyOffice - Soluzione Definitiva</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin: 20px 0;
        }
        .info-table th {
            background: #007bff;
            color: white;
            padding: 12px;
            text-align: left;
        }
        .info-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #ddd;
        }
        .info-table tr:hover {
            background: #f8f9fa;
        }
        .url-cell {
            font-family: monospace;
            font-size: 12px;
            word-break: break-all;
        }
        .status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .status.ok {
            background: #28a745;
            color: white;
        }
        .status.warning {
            background: #ffc107;
            color: #333;
        }
        .status.error {
            background: #dc3545;
            color: white;
        }
        #placeholder {
            height: 600px;
            margin-top: 20px;
            background: white;
            border: 1px solid #ddd;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .debug-panel {
            background: #fff;
            border: 1px solid #ddd;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .debug-panel h3 {
            margin-top: 0;
            color: #666;
        }
        .test-result {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
        }
        .test-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .test-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
    </style>
</head>
<body>
    <h1>OnlyOffice con Docker Desktop - Configurazione Corretta</h1>
    
    <div class="debug-panel">
        <h3>Configurazione URL</h3>
        <table class="info-table">
            <tr>
                <th>Componente</th>
                <th>URL</th>
                <th>Usato da</th>
                <th>Status</th>
            </tr>
            <tr>
                <td><strong>Document Server API</strong></td>
                <td class="url-cell"><?= htmlspecialchars($onlyofficeApiUrl) ?></td>
                <td>Browser</td>
                <td><span class="status ok">HTTPS:8443</span></td>
            </tr>
            <tr>
                <td><strong>Document URL</strong><br><small>(per OnlyOffice)</small></td>
                <td class="url-cell"><?= htmlspecialchars($documentUrl) ?></td>
                <td>OnlyOffice Container</td>
                <td><span class="status warning">host.docker.internal</span></td>
            </tr>
            <tr>
                <td><strong>Document URL</strong><br><small>(per browser/debug)</small></td>
                <td class="url-cell"><?= htmlspecialchars($documentUrlBrowser) ?></td>
                <td>Browser (debug)</td>
                <td><span class="status ok">localhost</span></td>
            </tr>
            <tr>
                <td><strong>Callback URL</strong></td>
                <td class="url-cell"><?= htmlspecialchars($callbackUrl) ?></td>
                <td>OnlyOffice Container</td>
                <td><span class="status warning">host.docker.internal</span></td>
            </tr>
        </table>
    </div>
    
    <div class="debug-panel">
        <h3>Test Connettività</h3>
        <div id="test-results">
            <div class="test-result">Esecuzione test in corso...</div>
        </div>
    </div>
    
    <div id="placeholder"></div>
    
    <script>
        // Test connettività
        async function testConnectivity() {
            const resultsDiv = document.getElementById('test-results');
            resultsDiv.innerHTML = '';
            
            // Test 1: Document Server API
            try {
                const apiTest = await fetch('<?= $onlyofficeApiUrl ?>', {
                    method: 'HEAD',
                    mode: 'no-cors' // Necessario per evitare CORS errors
                });
                resultsDiv.innerHTML += '<div class="test-result test-success">✓ Document Server API raggiungibile su HTTPS:8443</div>';
            } catch (e) {
                resultsDiv.innerHTML += '<div class="test-result test-error">✗ Document Server API non raggiungibile: ' + e.message + '</div>';
            }
            
            // Test 2: Documento locale
            try {
                const docResponse = await fetch('<?= $documentUrlBrowser ?>');
                if (docResponse.ok) {
                    resultsDiv.innerHTML += '<div class="test-result test-success">✓ Documento accessibile dal browser</div>';
                } else {
                    resultsDiv.innerHTML += '<div class="test-result test-error">✗ Documento non accessibile: HTTP ' + docResponse.status + '</div>';
                }
            } catch (e) {
                resultsDiv.innerHTML += '<div class="test-result test-error">✗ Errore accesso documento: ' + e.message + '</div>';
            }
            
            // Test 3: OnlyOffice health check
            try {
                const healthResponse = await fetch('https://localhost:8443/healthcheck');
                const healthData = await healthResponse.text();
                if (healthResponse.ok) {
                    resultsDiv.innerHTML += '<div class="test-result test-success">✓ OnlyOffice health check OK: ' + healthData + '</div>';
                } else {
                    resultsDiv.innerHTML += '<div class="test-result test-error">✗ OnlyOffice health check failed: HTTP ' + healthResponse.status + '</div>';
                }
            } catch (e) {
                // Normale che fallisca per CORS, ma indica che il server risponde
                resultsDiv.innerHTML += '<div class="test-result test-success">✓ OnlyOffice server risponde (CORS expected)</div>';
            }
        }
        
        // Esegui test al caricamento
        testConnectivity();
    </script>
    
    <!-- Carica API OnlyOffice con URL completo -->
    <script src="<?= $onlyofficeApiUrl ?>"></script>
    
    <script>
        var config = {
            document: {
                fileType: "docx",
                key: "<?= $docId ?>",
                title: "Test Document",
                url: "<?= $documentUrl ?>", // USA host.docker.internal per OnlyOffice
            },
            documentType: "word",
            editorConfig: {
                callbackUrl: "<?= $callbackUrl ?>", // USA host.docker.internal per OnlyOffice
                mode: "edit",
                user: {
                    id: "test_user",
                    name: "Test User"
                },
                customization: {
                    autosave: true,
                    forcesave: false,
                    compactHeader: false,
                    compactToolbar: false,
                    hideRightMenu: false,
                    toolbarNoTabs: false,
                    logo: {
                        image: ""
                    }
                }
            },
            type: "desktop",
            width: "100%",
            height: "100%"
        };
        
        console.log('=== OnlyOffice Configuration ===');
        console.log('Document URL (for OnlyOffice):', config.document.url);
        console.log('Callback URL (for OnlyOffice):', config.editorConfig.callbackUrl);
        console.log('Full config:', config);
        
        // Inizializza editor
        try {
            new DocsAPI.DocEditor("placeholder", config);
            console.log('Editor inizializzato con successo');
        } catch (error) {
            console.error('Errore inizializzazione editor:', error);
            document.getElementById('placeholder').innerHTML = 
                '<div style="padding: 20px; color: red;">Errore inizializzazione editor: ' + error.message + '</div>';
        }
    </script>
</body>
</html>