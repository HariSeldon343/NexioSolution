<?php
/**
 * Test OnlyOffice Button in Filesystem
 * Verifica che il bottone Modifica apra correttamente OnlyOffice
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

// Trova un documento DOCX di test
$stmt = db_query("
    SELECT id, nome, file_path, mime_type, dimensione_file, azienda_id
    FROM documenti 
    WHERE (mime_type LIKE '%word%' OR nome LIKE '%.docx' OR nome LIKE '%.doc')
    AND azienda_id = ?
    ORDER BY data_creazione DESC
    LIMIT 5
", [$aziendaId]);

$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test OnlyOffice Button</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f3f4f6;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2d5a9f;
            margin-bottom: 30px;
        }
        .document-card {
            border: 1px solid #e5e7eb;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            background: #f9fafb;
        }
        .document-info {
            margin-bottom: 15px;
        }
        .document-info strong {
            color: #374151;
        }
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #2d5a9f;
            color: white;
        }
        .btn-primary:hover {
            background: #1e3a8a;
        }
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            font-weight: bold;
            box-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);
        }
        .btn-success:hover {
            background: linear-gradient(135deg, #20c997 0%, #28a745 100%);
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(40, 167, 69, 0.5);
        }
        .btn-warning {
            background: #fbbf24;
            color: #374151;
        }
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .alert-info {
            background: #dbeafe;
            color: #1e3a8a;
            border: 1px solid #93c5fd;
        }
        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fcd34d;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }
        .test-result {
            padding: 10px;
            margin: 10px 0;
            border-left: 4px solid;
            background: #f9fafb;
        }
        .test-pass {
            border-color: #10b981;
            background: #ecfdf5;
        }
        .test-fail {
            border-color: #ef4444;
            background: #fef2f2;
        }
        pre {
            background: #1f2937;
            color: #f3f4f6;
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
            font-size: 12px;
        }
        .file-icon {
            font-size: 24px;
            margin-right: 10px;
        }
        .doc-icon { color: #3b82f6; }
        .pdf-icon { color: #ef4444; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Test OnlyOffice Button Integration</h1>
        
        <div class="alert alert-info">
            <strong>Test Objective:</strong> Verificare che il bottone "Modifica" apra correttamente l'editor OnlyOffice
        </div>

        <?php if (empty($documents)): ?>
            <div class="alert alert-warning">
                <strong>⚠️ Nessun documento Word trovato!</strong>
                <p>Non sono stati trovati documenti DOCX/DOC nell'azienda corrente.</p>
                <p>Crea prima un documento di test tramite la pagina filesystem.</p>
            </div>
        <?php else: ?>
            <div class="alert alert-success">
                <strong>✅ Trovati <?php echo count($documents); ?> documenti Word</strong>
            </div>

            <?php foreach ($documents as $doc): ?>
                <div class="document-card">
                    <div class="document-info">
                        <i class="fas fa-file-word file-icon doc-icon"></i>
                        <strong>Nome:</strong> <?php echo htmlspecialchars($doc['nome']); ?>
                    </div>
                    <div class="document-info">
                        <strong>ID:</strong> <?php echo $doc['id']; ?>
                    </div>
                    <div class="document-info">
                        <strong>MIME Type:</strong> <?php echo htmlspecialchars($doc['mime_type'] ?? 'N/A'); ?>
                    </div>
                    <div class="document-info">
                        <strong>Path:</strong> <?php echo htmlspecialchars($doc['file_path'] ?? 'N/A'); ?>
                    </div>
                    <div class="document-info">
                        <strong>Dimensione:</strong> <?php echo number_format($doc['dimensione_file'] / 1024, 2); ?> KB
                    </div>

                    <div class="button-group">
                        <!-- Bottone OnlyOffice (nuovo) -->
                        <a href="onlyoffice-editor.php?id=<?php echo $doc['id']; ?>" 
                           class="btn btn-success" 
                           target="_blank"
                           onclick="console.log('Opening OnlyOffice for document ID: <?php echo $doc['id']; ?>')">
                            <i class="fas fa-file-word"></i> Apri con OnlyOffice
                        </a>

                        <!-- Bottone vecchio editor (per confronto) -->
                        <a href="document-editor.php?id=<?php echo $doc['id']; ?>" 
                           class="btn btn-warning" 
                           target="_blank">
                            <i class="fas fa-edit"></i> Vecchio Editor
                        </a>

                        <!-- Test JavaScript function -->
                        <button class="btn btn-primary" 
                                onclick="testEditDocument(<?php echo $doc['id']; ?>)">
                            <i class="fas fa-vial"></i> Test JS Function
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <hr style="margin: 30px 0;">

        <h2>📊 Test Results</h2>
        <div id="testResults"></div>

        <hr style="margin: 30px 0;">

        <h2>🔧 Debug JavaScript</h2>
        <pre id="debugOutput">Waiting for test...</pre>

        <hr style="margin: 30px 0;">

        <div class="button-group">
            <a href="filesystem.php" class="btn btn-primary">
                <i class="fas fa-folder"></i> Vai a Filesystem
            </a>
            <button class="btn btn-primary" onclick="runAllTests()">
                <i class="fas fa-play"></i> Run All Tests
            </button>
            <button class="btn btn-danger" onclick="location.reload()">
                <i class="fas fa-sync"></i> Reload Page
            </button>
        </div>
    </div>

    <script>
    // Copia della funzione editDocument da filesystem.php
    function editDocument(event, fileId) {
        // Se è passato un event, previeni propagazione
        if (event && typeof event === 'object') {
            event.stopPropagation && event.stopPropagation();
            event.preventDefault && event.preventDefault();
        } else {
            // Se event è in realtà il fileId (chiamata diretta)
            fileId = event;
        }
        
        console.log('editDocument called with fileId:', fileId);
        updateDebug('editDocument called with fileId: ' + fileId);
        
        // Apri l'editor OnlyOffice
        const url = 'onlyoffice-editor.php?id=' + fileId;
        console.log('Opening URL:', url);
        updateDebug('Opening URL: ' + url);
        
        window.open(url, '_blank');
        
        addTestResult('editDocument() function', true, 'Function executed successfully for document ' + fileId);
    }

    // Test wrapper
    function testEditDocument(fileId) {
        updateDebug('Testing editDocument with ID: ' + fileId);
        try {
            editDocument(null, fileId);
        } catch (error) {
            updateDebug('Error: ' + error.message);
            addTestResult('editDocument() function', false, error.message);
        }
    }

    // Update debug output
    function updateDebug(message) {
        const debugEl = document.getElementById('debugOutput');
        const timestamp = new Date().toLocaleTimeString();
        debugEl.textContent += '\n[' + timestamp + '] ' + message;
    }

    // Add test result
    function addTestResult(testName, passed, message) {
        const resultsEl = document.getElementById('testResults');
        const resultDiv = document.createElement('div');
        resultDiv.className = 'test-result ' + (passed ? 'test-pass' : 'test-fail');
        resultDiv.innerHTML = `
            <strong>${passed ? '✅' : '❌'} ${testName}</strong>
            <div>${message}</div>
        `;
        resultsEl.appendChild(resultDiv);
    }

    // Run all tests
    function runAllTests() {
        document.getElementById('testResults').innerHTML = '';
        updateDebug('\n=== Running All Tests ===');
        
        // Test 1: Check if editDocument function exists
        const test1 = typeof editDocument === 'function';
        addTestResult('editDocument function exists', test1, 
            test1 ? 'Function is defined' : 'Function is not defined');
        
        // Test 2: Check if OnlyOffice editor page exists
        fetch('onlyoffice-editor.php')
            .then(response => {
                const test2 = response.ok;
                addTestResult('OnlyOffice editor page exists', test2, 
                    test2 ? 'Page is accessible' : 'Page returned ' + response.status);
            })
            .catch(error => {
                addTestResult('OnlyOffice editor page exists', false, 'Error: ' + error.message);
            });
        
        // Test 3: Check if old editor page exists
        fetch('document-editor.php')
            .then(response => {
                const test3 = response.ok;
                addTestResult('Old editor page exists', test3, 
                    test3 ? 'Page is accessible' : 'Page returned ' + response.status);
            })
            .catch(error => {
                addTestResult('Old editor page exists', false, 'Error: ' + error.message);
            });
        
        updateDebug('Tests completed');
    }

    // Initial debug info
    window.addEventListener('DOMContentLoaded', function() {
        updateDebug('Page loaded');
        updateDebug('editDocument function available: ' + (typeof editDocument === 'function'));
    });
    </script>
</body>
</html>