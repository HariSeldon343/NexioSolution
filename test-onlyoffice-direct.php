<?php
/**
 * Test diretto OnlyOffice - bypassa tutti i controlli
 * Usa questo per verificare che OnlyOffice funzioni correttamente
 */

// Minimal auth check
session_start();
if (!isset($_SESSION['user_id'])) {
    die("Non autenticato - fai login prima");
}

// Include config
require_once 'backend/config/config.php';
require_once 'backend/config/onlyoffice.config.php';

// Get document ID (default to test doc)
$documentId = isset($_GET['id']) ? intval($_GET['id']) : 22;

// Direct query - no permission checks
$stmt = db_query("SELECT * FROM documenti WHERE id = ?", [$documentId]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    die("Documento $documentId non trovato nel database");
}

// Debug output
echo "<h2>Debug Info:</h2>";
echo "<pre>";
echo "Document ID: " . $documentId . "\n";
echo "Titolo: " . ($document['titolo'] ?? 'N/A') . "\n";
echo "Nome file: " . ($document['nome_file'] ?? 'N/A') . "\n";
echo "File path: " . ($document['file_path'] ?? 'N/A') . "\n";
echo "Percorso file: " . ($document['percorso_file'] ?? 'N/A') . "\n";

// Determine actual file path
$percorsoFile = $document['percorso_file'] ?? $document['file_path'] ?? '';
if (empty($percorsoFile)) {
    die("\nERRORE: Nessun percorso file trovato!");
}

// Add prefix if needed
if (strpos($percorsoFile, 'uploads/') === false && strpos($percorsoFile, 'documents/') === false) {
    $percorsoFile = 'uploads/documenti/' . $percorsoFile;
}

$filePath = __DIR__ . '/' . $percorsoFile;
echo "\nPercorso completo: " . $filePath . "\n";
echo "File esiste: " . (file_exists($filePath) ? 'SI' : 'NO') . "\n";

if (!file_exists($filePath)) {
    // Try alternative paths
    $alternatives = [
        __DIR__ . '/uploads/documenti/' . basename($document['file_path'] ?? ''),
        __DIR__ . '/documents/onlyoffice/' . basename($document['file_path'] ?? ''),
        __DIR__ . '/' . ($document['file_path'] ?? '')
    ];
    
    echo "\nProvo percorsi alternativi:\n";
    foreach ($alternatives as $alt) {
        echo "- $alt : " . (file_exists($alt) ? 'TROVATO!' : 'non esiste') . "\n";
        if (file_exists($alt)) {
            $filePath = $alt;
            $percorsoFile = str_replace(__DIR__ . '/', '', $alt);
            break;
        }
    }
}

if (!file_exists($filePath)) {
    die("\nERRORE: File non trovato in nessun percorso!");
}

echo "\nFile trovato! Procedo con OnlyOffice...\n";

// Determine filename
$nomeFile = $document['nome_file'] ?? '';
if (empty($nomeFile)) {
    if (!empty($document['file_path'])) {
        $nomeFile = preg_replace('/^[a-f0-9]+_/', '', basename($document['file_path']));
    } elseif (!empty($document['titolo'])) {
        $ext = pathinfo($document['file_path'] ?? '', PATHINFO_EXTENSION);
        $nomeFile = $document['titolo'] . ($ext ? '.' . $ext : '.docx');
    } else {
        $nomeFile = 'document.docx';
    }
}

echo "Nome file per OnlyOffice: " . $nomeFile . "\n";

$extension = strtolower(pathinfo($nomeFile, PATHINFO_EXTENSION));
echo "Estensione: " . $extension . "\n";

// Determine document type
$documentType = 'word';
if (in_array($extension, ['xlsx', 'xls'])) {
    $documentType = 'cell';
} elseif (in_array($extension, ['pptx', 'ppt'])) {
    $documentType = 'slide';
}

echo "Tipo documento: " . $documentType . "\n";

// Build URLs
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

$documentUrl = $protocol . '://' . $host . $basePath . '/backend/api/onlyoffice-document.php?id=' . $documentId;
echo "Document URL: " . $documentUrl . "\n";

$callbackUrl = $protocol . '://' . $host . $basePath . '/backend/api/onlyoffice-callback.php?id=' . $documentId;
echo "Callback URL: " . $callbackUrl . "\n";

echo "OnlyOffice Server: " . $ONLYOFFICE_DS_PUBLIC_URL . "\n";

echo "</pre>";

// Build minimal config
$config = [
    'documentType' => $documentType,
    'document' => [
        'title' => $nomeFile,
        'url' => $documentUrl . '&token=test',
        'fileType' => $extension,
        'key' => md5($documentId . '_' . time()),
        'permissions' => [
            'download' => true,
            'edit' => true,
            'print' => true
        ]
    ],
    'editorConfig' => [
        'mode' => 'edit',
        'lang' => 'it',
        'callbackUrl' => $callbackUrl,
        'user' => [
            'id' => (string)$_SESSION['user_id'],
            'name' => 'Test User'
        ],
        'customization' => [
            'forcesave' => true,
            'autosave' => true
        ]
    ]
];

?>

<!DOCTYPE html>
<html>
<head>
    <title>Test OnlyOffice Direct</title>
    <style>
        body { margin: 0; padding: 0; }
        #editor { width: 100%; height: 100vh; }
        .controls { 
            position: fixed; 
            top: 10px; 
            right: 10px; 
            z-index: 1000;
            background: white;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="controls">
        <button onclick="location.href='filesystem.php'">Torna ai Documenti</button>
        <button onclick="testSave()">Test Save</button>
        <button onclick="showConfig()">Mostra Config</button>
    </div>
    
    <div id="editor"></div>
    
    <script src="<?php echo $ONLYOFFICE_DS_PUBLIC_URL; ?>/web-apps/apps/api/documents/api.js"></script>
    <script>
        var config = <?php echo json_encode($config); ?>;
        
        console.log('OnlyOffice Config:', config);
        
        // Add event handlers
        config.events = {
            onAppReady: function() {
                console.log('OnlyOffice Ready!');
                alert('OnlyOffice caricato correttamente!');
            },
            onError: function(event) {
                console.error('OnlyOffice Error:', event);
                alert('Errore OnlyOffice: ' + JSON.stringify(event));
            },
            onDocumentStateChange: function(event) {
                console.log('Document state:', event);
            }
        };
        
        // Initialize editor
        try {
            var docEditor = new DocsAPI.DocEditor("editor", config);
            console.log('Editor inizializzato');
        } catch(e) {
            console.error('Errore inizializzazione:', e);
            alert('Errore: ' + e.message);
        }
        
        function testSave() {
            console.log('Testing save...');
            if (docEditor) {
                docEditor.downloadAs();
            }
        }
        
        function showConfig() {
            console.log('Current config:', config);
            alert('Config mostrata in console (F12)');
        }
    </script>
</body>
</html>