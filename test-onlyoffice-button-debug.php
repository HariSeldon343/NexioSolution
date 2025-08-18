<?php
/**
 * Test debug per verificare il pulsante OnlyOffice in filesystem.php
 */

require_once 'backend/config/config.php';
require_once 'backend/middleware/Auth.php';
require_once 'backend/config/database.php';

// Autenticazione
$auth = Auth::getInstance();
$auth->requireAuth();

$user = $auth->getUser();
$currentAzienda = $auth->getCurrentAzienda();
$aziendaId = $currentAzienda['azienda_id'] ?? $currentAzienda['id'] ?? null;

$pageTitle = 'Test OnlyOffice Button Debug';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
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
            margin-bottom: 20px;
        }
        .test-section {
            margin: 20px 0;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 5px;
        }
        .test-file {
            margin: 10px 0;
            padding: 10px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .btn {
            padding: 8px 16px;
            margin: 5px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: #2d5a9f;
            color: white;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-warning {
            background: #ffc107;
            color: black;
        }
        .debug-info {
            background: #e9ecef;
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
        }
        .status-ok { color: green; }
        .status-error { color: red; }
        .status-warning { color: orange; }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-bug"></i> Debug OnlyOffice Button in Filesystem</h1>
        
        <div class="test-section">
            <h2>1. Test funzione isDocumentEditable()</h2>
            <div id="editableTest"></div>
        </div>

        <div class="test-section">
            <h2>2. Test files dal database</h2>
            <?php
            // Recupera alcuni file dal database
            try {
                $stmt = db_query("
                    SELECT d.*, a.nome as azienda_nome 
                    FROM documenti d 
                    LEFT JOIN aziende a ON d.azienda_id = a.id 
                    WHERE d.tipo = 'file' 
                    ORDER BY d.id DESC 
                    LIMIT 10
                ");
                $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($files) > 0) {
                    foreach ($files as $file) {
                        echo "<div class='test-file'>";
                        echo "<strong>File:</strong> " . htmlspecialchars($file['nome'] ?? 'N/A') . "<br>";
                        echo "<strong>ID:</strong> " . $file['id'] . "<br>";
                        echo "<strong>MIME Type:</strong> " . htmlspecialchars($file['mime_type'] ?? 'N/A') . "<br>";
                        echo "<strong>File Path:</strong> " . htmlspecialchars($file['file_path'] ?? 'N/A') . "<br>";
                        echo "<strong>Tipo Documento:</strong> " . htmlspecialchars($file['tipo_documento'] ?? 'N/A') . "<br>";
                        
                        // Test con JavaScript inline per vedere se è modificabile
                        echo "<div class='debug-info' id='editable-" . $file['id'] . "'></div>";
                        
                        // Pulsanti di test
                        echo "<div style='margin-top: 10px;'>";
                        echo "<button class='btn btn-primary' onclick='testEditDocument(" . $file['id'] . ")'>Test Edit Document</button>";
                        echo "<a href='onlyoffice-editor.php?id=" . $file['id'] . "' target='_blank' class='btn btn-success'>Apri OnlyOffice Diretto</a>";
                        echo "<a href='document-editor.php?id=" . $file['id'] . "' target='_blank' class='btn btn-warning'>Apri Vecchio Editor</a>";
                        echo "</div>";
                        
                        echo "</div>";
                    }
                } else {
                    echo "<p class='status-warning'>Nessun file trovato nel database</p>";
                }
            } catch (Exception $e) {
                echo "<p class='status-error'>Errore database: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
            ?>
        </div>

        <div class="test-section">
            <h2>3. Test JavaScript Functions</h2>
            <button class="btn btn-primary" onclick="testAllFunctions()">Test All Functions</button>
            <div id="jsTestResults" class="debug-info" style="margin-top: 10px;"></div>
        </div>

        <div class="test-section">
            <h2>4. Console Log</h2>
            <p>Apri la console del browser (F12) per vedere i log dettagliati</p>
        </div>
    </div>

    <script>
    // Copia della funzione isDocumentEditable da filesystem.php
    function isDocumentEditable(file) {
        console.log('Testing isDocumentEditable for:', file);
        
        let fileName = file.file_path || file.nome || file.titolo || '';
        
        if (!fileName.includes('.') && file.mime_type) {
            const mimeToExt = {
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'docx',
                'application/msword': 'doc',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'xlsx',
                'application/vnd.ms-excel': 'xls',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation': 'pptx',
                'application/vnd.ms-powerpoint': 'ppt',
                'application/vnd.oasis.opendocument.text': 'odt',
                'application/vnd.oasis.opendocument.spreadsheet': 'ods',
                'application/vnd.oasis.opendocument.presentation': 'odp',
                'text/plain': 'txt',
                'text/csv': 'csv',
                'application/rtf': 'rtf'
            };
            
            const ext = mimeToExt[file.mime_type];
            if (ext) {
                console.log('File is editable based on mime_type:', file.mime_type, '->', ext);
                return true;
            }
        }
        
        const extension = fileName.toLowerCase().split('.').pop();
        const supportedFormats = [
            'docx', 'doc', 'odt', 'rtf', 'txt',
            'xlsx', 'xls', 'ods', 'csv',
            'pptx', 'ppt', 'odp'
        ];
        
        const isEditable = supportedFormats.includes(extension);
        console.log('File editable check:', fileName, 'extension:', extension, 'editable:', isEditable);
        
        return isEditable;
    }

    // Copia della funzione editDocument
    function editDocument(event, fileId) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }
        
        console.log('editDocument called with ID:', fileId);
        window.open('onlyoffice-editor.php?id=' + fileId, '_blank');
    }

    // Test function
    function testEditDocument(fileId) {
        console.log('Testing editDocument with ID:', fileId);
        editDocument(null, fileId);
    }

    // Test all JavaScript functions
    function testAllFunctions() {
        const results = document.getElementById('jsTestResults');
        let html = '';
        
        // Test if functions exist
        const functions = [
            'isDocumentEditable',
            'editDocument',
            'escapeHtml',
            'formatFileSize',
            'getFileIcon',
            'loadFolder',
            'renderFiles'
        ];
        
        html += '<strong>Function Availability:</strong><br>';
        functions.forEach(func => {
            const exists = typeof window[func] === 'function';
            html += func + ': ' + (exists ? '<span class="status-ok">✓ EXISTS</span>' : '<span class="status-error">✗ MISSING</span>') + '<br>';
        });
        
        results.innerHTML = html;
    }

    // Test editable files on page load
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Page loaded, testing files...');
        
        // Test sample files
        const testFiles = [
            { nome: 'test.docx', mime_type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' },
            { nome: 'test.pdf', mime_type: 'application/pdf' },
            { nome: 'test.xlsx', mime_type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' },
            { nome: 'test.txt', mime_type: 'text/plain' },
            { nome: 'test', mime_type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' }
        ];
        
        let html = '<strong>Test Results:</strong><br>';
        testFiles.forEach(file => {
            const editable = isDocumentEditable(file);
            html += file.nome + ' (' + file.mime_type + '): ' + 
                    (editable ? '<span class="status-ok">✓ EDITABLE</span>' : '<span class="status-error">✗ NOT EDITABLE</span>') + '<br>';
        });
        
        document.getElementById('editableTest').innerHTML = html;
        
        // Test actual files from database
        <?php foreach ($files as $file): ?>
        (function() {
            const file = <?php echo json_encode($file); ?>;
            const editable = isDocumentEditable(file);
            const elem = document.getElementById('editable-<?php echo $file['id']; ?>');
            if (elem) {
                elem.innerHTML = 'JavaScript Test: ' + (editable ? 
                    '<span class="status-ok">✓ EDITABLE - Button should appear</span>' : 
                    '<span class="status-error">✗ NOT EDITABLE - Button will not appear</span>');
            }
        })();
        <?php endforeach; ?>
    });
    </script>
</body>
</html>