<?php
require_once 'backend/config/config.php';

// Recupera il documento con ID 22 (quello che abbiamo trovato)
$stmt = db_query("SELECT * FROM documenti WHERE id = 22");
$doc = $stmt->fetch();

echo "<h2>Test Visibilità Pulsante OnlyOffice</h2>";
echo "<pre>";
echo "Documento trovato:\n";
echo "ID: " . $doc['id'] . "\n";
echo "Titolo: " . $doc['titolo'] . "\n";
echo "File Path: " . $doc['file_path'] . "\n";
echo "MIME Type: " . $doc['mime_type'] . "\n";
echo "Percorso File: " . $doc['percorso_file'] . "\n";
echo "</pre>";

// Simula la struttura dati come arriva dal frontend
$fileData = [
    'id' => $doc['id'],
    'nome' => $doc['titolo'],
    'file_path' => $doc['file_path'],
    'mime_type' => $doc['mime_type'],
    'percorso_file' => $doc['percorso_file']
];

?>

<!DOCTYPE html>
<html>
<head>
    <title>Test Pulsante OnlyOffice</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .test-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            font-family: Arial, sans-serif;
        }
        .file-card {
            border: 1px solid #ddd;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            background: #f9f9f9;
        }
        .action-btn {
            padding: 8px 12px;
            margin: 5px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-onlyoffice {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            font-weight: bold;
        }
        .btn-onlyoffice:hover {
            transform: scale(1.05);
        }
        .debug-info {
            background: #333;
            color: #0f0;
            padding: 10px;
            margin: 20px 0;
            font-family: monospace;
            white-space: pre;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1>Test Pulsante OnlyOffice</h1>
        
        <div class="file-card">
            <h3><?php echo htmlspecialchars($doc['titolo']); ?></h3>
            <p>File: <?php echo htmlspecialchars($doc['file_path']); ?></p>
            <p>MIME: <?php echo htmlspecialchars($doc['mime_type']); ?></p>
            
            <div id="button-container"></div>
        </div>
        
        <div class="debug-info" id="debug"></div>
    </div>

    <script>
    // Copia esatta della funzione da filesystem.php
    function isDocumentEditable(file) {
        console.log('Checking if document is editable:', file);
        
        // Verifica se abbiamo il nome del file o il percorso
        const fileName = file.file_path || file.nome || file.titolo || '';
        console.log('File name/path:', fileName);
        
        if (fileName) {
            // Estrai l'estensione dal nome o percorso
            const extension = fileName.toLowerCase().split('.').pop();
            console.log('Extension found:', extension);
            
            // Supporta tutti i formati OnlyOffice
            const supportedFormats = [
                // Word
                'docx', 'doc', 'odt', 'rtf', 'txt',
                // Excel
                'xlsx', 'xls', 'ods', 'csv',
                // PowerPoint
                'pptx', 'ppt', 'odp'
            ];
            
            if (supportedFormats.includes(extension)) {
                console.log('Format is supported!');
                return true;
            }
        }
        
        // Fallback: controlla il mime_type
        const mimeType = file.mime_type || '';
        console.log('Checking MIME type:', mimeType);
        
        const supportedMimes = [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.ms-powerpoint'
        ];
        
        const isSupported = supportedMimes.includes(mimeType);
        console.log('MIME type supported:', isSupported);
        
        return isSupported;
    }
    
    // Test con i dati del documento
    const fileData = <?php echo json_encode($fileData); ?>;
    const isEditable = isDocumentEditable(fileData);
    
    // Mostra risultato
    const debug = document.getElementById('debug');
    debug.textContent = `Dati file:\n${JSON.stringify(fileData, null, 2)}\n\nÈ modificabile? ${isEditable ? 'SÌ ✅' : 'NO ❌'}`;
    
    // Genera il pulsante se è modificabile
    const container = document.getElementById('button-container');
    if (isEditable) {
        container.innerHTML = `
            <button class="action-btn btn-onlyoffice" onclick="alert('Aprirebbe OnlyOffice per documento ID: ${fileData.id}')">
                <i class="fas fa-file-word"></i> Apri con OnlyOffice
            </button>
            <p style="color: green; font-weight: bold;">✅ Il pulsante dovrebbe apparire!</p>
        `;
    } else {
        container.innerHTML = `<p style="color: red;">❌ Il documento non è riconosciuto come modificabile</p>`;
    }
    </script>
</body>
</html>