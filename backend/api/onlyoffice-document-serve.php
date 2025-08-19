<?php
/**
 * OnlyOffice Document Server API
 * Serve documenti per OnlyOffice con autenticazione semplificata
 */

// Headers per CORS e contenuto
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

// Gestione richieste OPTIONS per CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Configurazione
require_once '../config/config.php';
require_once '../config/onlyoffice.config.php';

// Verifica parametro documento
$documentId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$documentId) {
    http_response_code(400);
    die('Document ID required');
}

// Recupera documento dal database
try {
    $stmt = db_query("SELECT * FROM documenti WHERE id = ?", [$documentId]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        http_response_code(404);
        die('Document not found');
    }
    
    // Determina il percorso del file
    $percorsoFile = $document['percorso_file'] ?? $document['file_path'] ?? '';
    if (empty($percorsoFile)) {
        http_response_code(404);
        die('File path not found');
    }
    
    // Gestisci diversi formati di percorso
    if (strpos($percorsoFile, 'uploads/') === false && strpos($percorsoFile, 'documents/') === false) {
        $percorsoFile = 'uploads/documenti/' . $percorsoFile;
    }
    
    // Costruisci percorso completo
    $baseDir = dirname(dirname(dirname(__FILE__)));
    $filePath = $baseDir . '/' . $percorsoFile;
    
    // Verifica esistenza file
    if (!file_exists($filePath)) {
        // Prova percorsi alternativi
        $alternativePaths = [
            $baseDir . '/uploads/documenti/' . basename($percorsoFile),
            $baseDir . '/documents/onlyoffice/' . basename($percorsoFile),
            $baseDir . '/' . $percorsoFile
        ];
        
        $found = false;
        foreach ($alternativePaths as $altPath) {
            if (file_exists($altPath)) {
                $filePath = $altPath;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            http_response_code(404);
            die('File not found on server');
        }
    }
    
    // Ottieni informazioni sul file
    $fileSize = filesize($filePath);
    $fileName = $document['nome_file'] ?? basename($percorsoFile);
    $mimeType = $document['mime_type'] ?? mime_content_type($filePath);
    
    // Se mime_type non è disponibile, determinalo dall'estensione
    if (!$mimeType || $mimeType === 'application/octet-stream') {
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $mimeTypes = [
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'doc' => 'application/msword',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'rtf' => 'application/rtf',
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
            'odp' => 'application/vnd.oasis.opendocument.presentation'
        ];
        $mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';
    }
    
    // Imposta headers per il download
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: inline; filename="' . $fileName . '"');
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Headers aggiuntivi per OnlyOffice
    header('X-Document-Id: ' . $documentId);
    header('X-Document-Name: ' . $fileName);
    
    // Invia il file
    readfile($filePath);
    
} catch (Exception $e) {
    error_log("OnlyOffice Document Serve Error: " . $e->getMessage());
    http_response_code(500);
    die('Server error: ' . $e->getMessage());
}