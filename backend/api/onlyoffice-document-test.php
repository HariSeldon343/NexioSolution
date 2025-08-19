<?php
/**
 * OnlyOffice Document API - TEST VERSION WITHOUT AUTHENTICATION
 * For testing OnlyOffice integration only
 */

require_once __DIR__ . '/../config/config.php';

// Set headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Get document ID
    $documentId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if (!$documentId) {
        throw new Exception('Document ID required');
    }
    
    // Get document from database (no security check for testing)
    $stmt = db_query(
        "SELECT d.*, a.nome AS nome_azienda 
         FROM documenti d 
         LEFT JOIN aziende a ON d.azienda_id = a.id 
         WHERE d.id = ?",
        [$documentId]
    );
    
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        http_response_code(404);
        throw new Exception('Document not found');
    }
    
    // Check if file exists
    $filePath = __DIR__ . '/../../' . $document['percorso_file'];
    
    if (!file_exists($filePath)) {
        // If it's a new document request, create an empty file
        if (isset($_GET['new']) && $_GET['new'] === '1') {
            // Create empty DOCX file
            $emptyDocx = __DIR__ . '/../../documents/onlyoffice/empty.docx';
            if (!file_exists($emptyDocx)) {
                // Create a minimal DOCX structure if template doesn't exist
                $dir = dirname($filePath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                // Copy from a template or create empty file
                file_put_contents($filePath, '');
                
                // Actually, we need a valid DOCX file. Let's create one.
                $templatePath = __DIR__ . '/../../documents/onlyoffice/new.docx';
                if (file_exists($templatePath)) {
                    copy($templatePath, $filePath);
                } else {
                    // Create minimal DOCX
                    http_response_code(404);
                    throw new Exception('Template file not found and cannot create empty document');
                }
            } else {
                copy($emptyDocx, $filePath);
            }
        } else {
            http_response_code(404);
            throw new Exception('Document file not found: ' . $document['percorso_file']);
        }
    }
    
    // Determine MIME type
    $extension = strtolower(pathinfo($document['nome_file'], PATHINFO_EXTENSION));
    
    // Map extensions to MIME types
    $mimeMap = [
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'doc' => 'application/msword',
        'xls' => 'application/vnd.ms-excel',
        'ppt' => 'application/vnd.ms-powerpoint',
        'odt' => 'application/vnd.oasis.opendocument.text',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        'odp' => 'application/vnd.oasis.opendocument.presentation',
        'pdf' => 'application/pdf',
        'txt' => 'text/plain',
        'csv' => 'text/csv',
        'rtf' => 'application/rtf'
    ];
    
    $mimeType = $mimeMap[$extension] ?? 'application/octet-stream';
    
    // Set headers for file download
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: inline; filename="' . basename($document['nome_file']) . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: private, max-age=3600');
    header('ETag: "' . md5_file($filePath) . '"');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($filePath)) . ' GMT');
    
    // Output file content
    readfile($filePath);
    
    // Log access
    error_log("OnlyOffice TEST API: Document $documentId accessed successfully");
    
} catch (Exception $e) {
    error_log('OnlyOffice TEST API Error: ' . $e->getMessage());
    
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    
    echo json_encode([
        'error' => $e->getMessage(),
        'debug' => [
            'document_id' => $documentId ?? null,
            'file_path' => $filePath ?? null,
            'file_exists' => isset($filePath) ? file_exists($filePath) : false
        ]
    ]);
}
?>