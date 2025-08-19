<?php
require_once 'backend/config/onlyoffice.config.php';
require_once 'backend/config/config.php';
require_once 'backend/middleware/Auth.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$testDocId = $_GET['doc'] ?? 22;
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Verifica Configurazione OnlyOffice</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        h2 {
            color: #555;
            margin-top: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #495057;
        }
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .code {
            font-family: 'Courier New', monospace;
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.9em;
            word-break: break-all;
        }
        .status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 0.9em;
        }
        .status.local {
            background: #d4edda;
            color: #155724;
        }
        .status.production {
            background: #cce5ff;
            color: #004085;
        }
        .links {
            margin: 20px 0;
        }
        .links a {
            display: inline-block;
            margin: 5px 10px 5px 0;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
        }
        .links a:hover {
            background: #0056b3;
        }
        .alert {
            padding: 12px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .alert.info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .alert.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .test-result {
            margin: 20px 0;
            padding: 15px;
            border-radius: 5px;
        }
        .test-result.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .test-result.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Verifica Configurazione OnlyOffice</h1>
        
        <div class="alert info">
            <strong>Ambiente Rilevato:</strong> 
            <span class="status <?= OnlyOfficeConfig::isLocal() ? 'local' : 'production' ?>">
                <?= OnlyOfficeConfig::isLocal() ? 'LOCALE (localhost)' : 'PRODUZIONE (Cloudflare)' ?>
            </span>
        </div>

        <h2>Configurazione URL</h2>
        <table>
            <tr>
                <th style="width: 30%;">Parametro</th>
                <th>Valore</th>
            </tr>
            <tr>
                <td><strong>Ambiente</strong></td>
                <td class="code"><?= OnlyOfficeConfig::isLocal() ? 'LOCALE' : 'PRODUZIONE' ?></td>
            </tr>
            <tr>
                <td><strong>Document Server URL</strong><br>
                    <small>(per caricare API JavaScript)</small></td>
                <td class="code"><?= htmlspecialchars(OnlyOfficeConfig::getDocumentServerPublicUrl()) ?></td>
            </tr>
            <tr>
                <td><strong>File Server Public Base</strong><br>
                    <small>(URL pubblico per browser)</small></td>
                <td class="code"><?= htmlspecialchars(OnlyOfficeConfig::getFileServerPublicBase()) ?></td>
            </tr>
            <tr>
                <td><strong>File Server Internal Base</strong><br>
                    <small>(URL interno per OnlyOffice container)</small></td>
                <td class="code"><?= htmlspecialchars(OnlyOfficeConfig::FILESERVER_INTERNAL_BASE) ?></td>
            </tr>
        </table>

        <h2>URL Generati per Documento Test (ID: <?= $testDocId ?>)</h2>
        <table>
            <tr>
                <th style="width: 30%;">Tipo URL</th>
                <th>URL Generato</th>
            </tr>
            <tr>
                <td><strong>Document URL</strong><br>
                    <small>(usato da OnlyOffice per scaricare il file)</small></td>
                <td class="code"><?= htmlspecialchars(OnlyOfficeConfig::getDocumentUrl($testDocId)) ?></td>
            </tr>
            <tr>
                <td><strong>Callback URL</strong><br>
                    <small>(usato da OnlyOffice per salvare)</small></td>
                <td class="code"><?= htmlspecialchars(OnlyOfficeConfig::getCallbackUrl($testDocId)) ?></td>
            </tr>
            <tr>
                <td><strong>Public Document URL</strong><br>
                    <small>(per download manuale dal browser)</small></td>
                <td class="code"><?= htmlspecialchars(OnlyOfficeConfig::getPublicDocumentUrl($testDocId)) ?></td>
            </tr>
            <tr>
                <td><strong>API JavaScript URL</strong><br>
                    <small>(script da includere nella pagina)</small></td>
                <td class="code"><?= htmlspecialchars(OnlyOfficeConfig::getDocumentServerPublicUrl() . 'web-apps/apps/api/documents/api.js') ?></td>
            </tr>
        </table>

        <h2>Configurazione JWT</h2>
        <table>
            <tr>
                <th style="width: 30%;">Parametro</th>
                <th>Valore</th>
            </tr>
            <tr>
                <td><strong>JWT Abilitato</strong></td>
                <td class="code"><?= OnlyOfficeConfig::JWT_ENABLED ? 'SÌ' : 'NO (Testing Mode)' ?></td>
            </tr>
            <tr>
                <td><strong>JWT Secret</strong></td>
                <td class="code"><?= OnlyOfficeConfig::JWT_ENABLED ? '***HIDDEN***' : 'Non utilizzato' ?></td>
            </tr>
        </table>

        <h2>Test Connessione</h2>
        <div id="connection-test">
            <button onclick="testConnection()" style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer;">
                Test Connessione OnlyOffice
            </button>
            <div id="test-result"></div>
        </div>

        <h2>Link Utili</h2>
        <div class="links">
            <a href="onlyoffice-editor.php?id=<?= $testDocId ?>">Test Editor con documento ID <?= $testDocId ?></a>
            <a href="filesystem.php">File System</a>
            <a href="backend/api/onlyoffice-auth.php?doc=<?= $testDocId ?>" target="_blank">Test API Auth</a>
            <a href="backend/api/onlyoffice-document-public.php?doc=<?= $testDocId ?>" target="_blank">Test Document API</a>
        </div>

        <h2>Istruzioni Docker</h2>
        <div class="alert warning">
            <strong>IMPORTANTE per Docker Desktop (Windows/Mac):</strong>
            <ul style="margin: 10px 0;">
                <li>OnlyOffice container DEVE usare <code>host.docker.internal</code> per raggiungere l'applicazione</li>
                <li>Mai usare <code>localhost</code> negli URL interni</li>
                <li>Il Document Server deve essere raggiungibile su HTTPS porta 8443 in locale</li>
                <li>In produzione, Cloudflare gestisce il routing su <code>/onlyoffice/</code></li>
            </ul>
        </div>

        <h2>Verifica Database</h2>
        <?php
        // Verifica documento di test nel database
        $stmt = db_query("SELECT * FROM documenti WHERE id = ?", [$testDocId]);
        $testDoc = $stmt->fetch();
        
        if ($testDoc):
        ?>
        <div class="test-result success">
            <strong>✓ Documento di test trovato:</strong><br>
            ID: <?= $testDoc['id'] ?><br>
            Nome: <?= htmlspecialchars($testDoc['nome'] ?? 'N/A') ?><br>
            Filename: <?= htmlspecialchars($testDoc['filename'] ?? 'N/A') ?><br>
            Percorso: <?= htmlspecialchars($testDoc['percorso_file'] ?? 'N/A') ?>
        </div>
        <?php else: ?>
        <div class="test-result error">
            <strong>✗ Documento di test non trovato</strong><br>
            Il documento con ID <?= $testDocId ?> non esiste nel database.
        </div>
        <?php endif; ?>
    </div>

    <script>
    function testConnection() {
        const resultDiv = document.getElementById('test-result');
        resultDiv.innerHTML = '<div class="test-result" style="background: #ffeaa7;">Connessione in corso...</div>';
        
        // Test del Document Server
        const apiUrl = '<?= OnlyOfficeConfig::getDocumentServerPublicUrl() ?>web-apps/apps/api/documents/api.js';
        
        // Crea uno script tag per testare il caricamento
        const script = document.createElement('script');
        script.src = apiUrl;
        script.onload = function() {
            if (typeof DocsAPI !== 'undefined') {
                resultDiv.innerHTML = '<div class="test-result success">✓ OnlyOffice Document Server raggiungibile e API caricate correttamente!</div>';
            } else {
                resultDiv.innerHTML = '<div class="test-result error">✗ OnlyOffice API caricate ma DocsAPI non definito</div>';
            }
        };
        script.onerror = function() {
            resultDiv.innerHTML = `<div class="test-result error">
                ✗ Impossibile raggiungere OnlyOffice Document Server<br>
                URL testato: ${apiUrl}<br>
                Verificare che il container Docker sia in esecuzione e raggiungibile.
            </div>`;
        };
        document.head.appendChild(script);
        
        // Test anche l'endpoint di autenticazione
        fetch('backend/api/onlyoffice-auth.php?doc=<?= $testDocId ?>')
            .then(response => response.json())
            .then(data => {
                console.log('Auth API Response:', data);
            })
            .catch(error => {
                console.error('Auth API Error:', error);
            });
    }
    </script>
</body>
</html>