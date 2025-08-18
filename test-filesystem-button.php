<?php
require_once 'backend/middleware/Auth.php';
require_once 'backend/config/config.php';

$auth = Auth::getInstance();
$auth->requireAuth();

// Test diretto della query dell'API
$testQuery = "
    SELECT d.id, d.titolo as nome, d.file_path, d.mime_type, d.tipo_documento,
           COALESCE(d.dimensione_file, d.file_size, 0) as dimensione_file,
           d.azienda_id, a.nome as azienda_nome
    FROM documenti d
    LEFT JOIN aziende a ON d.azienda_id = a.id
    WHERE d.cartella_id IS NULL
    AND (d.mime_type LIKE '%word%' OR d.mime_type LIKE '%document%' OR d.file_path LIKE '%.docx' OR d.file_path LIKE '%.doc')
    LIMIT 5
";

$stmt = db_query($testQuery);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Funzione per verificare se un documento è editabile (copiata da filesystem.php)
function isDocumentEditable($file) {
    $editableTypes = [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.ms-powerpoint',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/vnd.oasis.opendocument.presentation',
        'text/plain',
        'text/csv',
        'application/rtf'
    ];
    
    if (isset($file['mime_type']) && in_array($file['mime_type'], $editableTypes)) {
        return true;
    }
    
    $fileName = $file['nome'] ?? $file['file_path'] ?? '';
    $editableExtensions = ['docx', 'doc', 'xlsx', 'xls', 'pptx', 'ppt', 'odt', 'ods', 'odp', 'txt', 'csv', 'rtf'];
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    return in_array($extension, $editableExtensions);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Filesystem Button</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .btn-onlyoffice {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%) !important;
            color: white !important;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-onlyoffice:hover {
            background: linear-gradient(135deg, #20c997 0%, #28a745 100%) !important;
            transform: scale(1.05);
        }
        .document-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background: #f8f9fa;
        }
        .editable-badge {
            background: #28a745;
            color: white;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            margin-left: 10px;
        }
        .not-editable-badge {
            background: #dc3545;
            color: white;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1>Test Bottone Modifica Filesystem</h1>
        
        <div class="alert alert-info">
            <h5>Test della funzione isDocumentEditable() e del bottone OnlyOffice</h5>
            <p>Questa pagina testa se i documenti vengono riconosciuti come editabili e se il bottone appare correttamente.</p>
        </div>

        <h3>Documenti trovati nel database:</h3>
        
        <?php if (empty($documents)): ?>
            <div class="alert alert-warning">
                Nessun documento Word trovato nel database.
            </div>
        <?php else: ?>
            <?php foreach ($documents as $doc): ?>
                <?php $isEditable = isDocumentEditable($doc); ?>
                <div class="document-card">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5>
                                <i class="fas fa-file-word text-primary"></i>
                                <?php echo htmlspecialchars($doc['nome']); ?>
                                <?php if ($isEditable): ?>
                                    <span class="editable-badge">EDITABILE</span>
                                <?php else: ?>
                                    <span class="not-editable-badge">NON EDITABILE</span>
                                <?php endif; ?>
                            </h5>
                            <small class="text-muted">
                                ID: <?php echo $doc['id']; ?> | 
                                MIME: <?php echo htmlspecialchars($doc['mime_type'] ?? 'N/A'); ?><br>
                                File Path: <?php echo htmlspecialchars($doc['file_path'] ?? 'N/A'); ?><br>
                                Nome (from query): <?php echo htmlspecialchars($doc['nome'] ?? 'N/A'); ?>
                            </small>
                        </div>
                        <div class="col-md-4 text-end">
                            <?php if ($isEditable): ?>
                                <button class="btn-onlyoffice" 
                                        onclick="editDocument(event, <?php echo $doc['id']; ?>)"
                                        title="Apri con OnlyOffice">
                                    <i class="fas fa-edit"></i> Modifica con OnlyOffice
                                </button>
                            <?php else: ?>
                                <button class="btn btn-secondary" disabled>
                                    <i class="fas fa-ban"></i> Non modificabile
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <h6>Debug Info:</h6>
                        <pre style="background: #fff; padding: 10px; border-radius: 5px;">
<?php 
$debugInfo = [
    'id' => $doc['id'],
    'nome' => $doc['nome'],
    'file_path' => $doc['file_path'],
    'mime_type' => $doc['mime_type'],
    'is_editable' => $isEditable,
    'extension_from_nome' => pathinfo($doc['nome'] ?? '', PATHINFO_EXTENSION),
    'extension_from_path' => pathinfo($doc['file_path'] ?? '', PATHINFO_EXTENSION)
];
echo json_encode($debugInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
                        </pre>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="mt-5">
            <h3>Test JavaScript Functions</h3>
            <button class="btn btn-primary" onclick="testFunctions()">Test All Functions</button>
            <div id="test-results" class="mt-3"></div>
        </div>
    </div>

    <script>
    // Funzione editDocument copiata da filesystem.php
    function editDocument(event, fileId) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        console.log('Opening OnlyOffice editor for document ID:', fileId);
        const url = 'onlyoffice-editor.php?id=' + fileId;
        console.log('Opening URL:', url);
        window.open(url, '_blank');
    }

    // Funzione isDocumentEditable per test JavaScript
    function isDocumentEditable(file) {
        console.log('Checking if editable:', file);
        
        const editableTypes = [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.ms-powerpoint',
            'application/vnd.oasis.opendocument.text',
            'application/vnd.oasis.opendocument.spreadsheet',
            'application/vnd.oasis.opendocument.presentation',
            'text/plain',
            'text/csv',
            'application/rtf'
        ];
        
        if (file.mime_type && editableTypes.includes(file.mime_type)) {
            console.log('File is editable by MIME type:', file.mime_type);
            return true;
        }
        
        const fileName = file.nome || file.file_path || '';
        if (!fileName) {
            console.log('No filename found, not editable');
            return false;
        }
        
        const editableExtensions = ['docx', 'doc', 'xlsx', 'xls', 'pptx', 'ppt', 'odt', 'ods', 'odp', 'txt', 'csv', 'rtf'];
        const extension = fileName.split('.').pop().toLowerCase();
        const isEditable = editableExtensions.includes(extension);
        
        console.log('File extension:', extension, 'Is editable:', isEditable);
        return isEditable;
    }

    function testFunctions() {
        const results = document.getElementById('test-results');
        results.innerHTML = '';
        
        // Test 1: Check if functions exist
        const functionsExist = {
            'editDocument': typeof editDocument === 'function',
            'isDocumentEditable': typeof isDocumentEditable === 'function'
        };
        
        // Test 2: Test isDocumentEditable with sample data
        const testFile = {
            nome: 'test.docx',
            mime_type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        };
        
        const testResults = {
            'Functions Exist': functionsExist,
            'Test File Editable': isDocumentEditable(testFile),
            'Console Log Check': 'Check browser console for detailed logs'
        };
        
        results.innerHTML = `
            <div class="alert alert-success">
                <h5>Test Results:</h5>
                <pre>${JSON.stringify(testResults, null, 2)}</pre>
            </div>
        `;
        
        console.log('Test Results:', testResults);
    }
    </script>
</body>
</html>