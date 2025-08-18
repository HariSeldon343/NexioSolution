<?php
// Test per verificare che il bottone di modifica funzioni
require_once 'backend/config/config.php';
require_once 'backend/middleware/Auth.php';

// Autenticazione
$auth = Auth::getInstance();
$auth->requireAuth();

// Ottieni informazioni utente
$user = $auth->getUser();
$aziendaId = $auth->getCurrentAzienda();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Edit Button Fix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            padding: 20px;
            background: #f5f5f5;
        }
        .test-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .file-item {
            border: 1px solid #ddd;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            background: #fafafa;
        }
        .status {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
        }
        .status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .debug-info {
            background: #f0f0f0;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            font-family: monospace;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Test Edit Button Fix</h1>
        
        <div class="test-card">
            <h3>Test 1: Funzioni Globali</h3>
            <div id="globalFunctionsTest"></div>
        </div>

        <div class="test-card">
            <h3>Test 2: Bottone di Modifica Simulato</h3>
            <?php
            // Ottieni un documento di test
            $stmt = db_query("
                SELECT id, nome, file_path, mime_type 
                FROM documenti 
                WHERE azienda_id = ? 
                AND (mime_type LIKE '%word%' OR mime_type LIKE '%spreadsheet%' OR file_path LIKE '%.docx' OR file_path LIKE '%.xlsx')
                LIMIT 1
            ", [$aziendaId]);
            $doc = $stmt->fetch();
            
            if ($doc):
            ?>
            <div class="file-item">
                <strong>Documento:</strong> <?php echo htmlspecialchars($doc['nome']); ?><br>
                <strong>ID:</strong> <?php echo $doc['id']; ?><br>
                <strong>MIME Type:</strong> <?php echo htmlspecialchars($doc['mime_type']); ?><br>
                
                <!-- Bottone con onclick inline come in filesystem.php -->
                <button class="btn btn-primary btn-sm mt-2" 
                        onclick="editDocument(event, <?php echo $doc['id']; ?>)">
                    <i class="fas fa-edit"></i> Modifica (onclick inline)
                </button>
                
                <!-- Bottone con addEventListener per confronto -->
                <button class="btn btn-success btn-sm mt-2" 
                        id="editBtn"
                        data-id="<?php echo $doc['id']; ?>">
                    <i class="fas fa-edit"></i> Modifica (addEventListener)
                </button>
            </div>
            <?php else: ?>
            <div class="status error">
                Nessun documento modificabile trovato nel database.
            </div>
            <?php endif; ?>
        </div>

        <div class="test-card">
            <h3>Test 3: Console Output</h3>
            <div class="debug-info">
                Apri la console del browser per vedere i messaggi di debug.
            </div>
        </div>
    </div>

    <script>
    // Test 1: Verifica che le funzioni siano globali
    document.addEventListener('DOMContentLoaded', function() {
        const testContainer = document.getElementById('globalFunctionsTest');
        const functionsToTest = [
            'editDocument',
            'isDocumentEditable',
            'openFile',
            'showUploadModal',
            'showNewFolderModal',
            'closeModal',
            'confirmDeleteFS',
            'loadFolder',
            'toggleSelectMode'
        ];
        
        let html = '<ul>';
        let allAvailable = true;
        
        functionsToTest.forEach(funcName => {
            const isAvailable = typeof window[funcName] === 'function';
            if (!isAvailable) allAvailable = false;
            
            html += `<li>
                <strong>${funcName}:</strong> 
                <span style="color: ${isAvailable ? 'green' : 'red'}">
                    ${isAvailable ? '✓ Disponibile' : '✗ Non trovata'}
                </span>
            </li>`;
            
            console.log(`Function ${funcName}:`, isAvailable ? 'Available' : 'NOT FOUND');
        });
        
        html += '</ul>';
        
        if (allAvailable) {
            html = '<div class="status success">✓ Tutte le funzioni sono correttamente esposte globalmente!</div>' + html;
        } else {
            html = '<div class="status error">✗ Alcune funzioni non sono disponibili globalmente</div>' + html;
        }
        
        testContainer.innerHTML = html;
    });
    
    // Definizione locale di editDocument per il test
    function editDocument(event, fileId) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        console.log('✓ editDocument chiamata con successo!');
        console.log('  - Event:', event);
        console.log('  - File ID:', fileId);
        
        alert(`editDocument funziona!\n\nFile ID: ${fileId}\n\nIn produzione aprirebbe: onlyoffice-editor.php?id=${fileId}`);
        
        // In produzione:
        // window.open('onlyoffice-editor.php?id=' + fileId, '_blank');
    }
    
    // Test con addEventListener
    const editBtn = document.getElementById('editBtn');
    if (editBtn) {
        editBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const fileId = this.getAttribute('data-id');
            console.log('addEventListener version called');
            editDocument(e, fileId);
        });
    }
    
    // Esponi la funzione globalmente per il test
    window.editDocument = editDocument;
    
    console.log('=== Test Edit Button Fix ===');
    console.log('La funzione editDocument è disponibile globalmente:', typeof window.editDocument === 'function');
    </script>
</body>
</html>