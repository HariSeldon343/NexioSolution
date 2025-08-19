<?php
/**
 * OnlyOffice Callback Handler - Simplified for Docker Testing
 * Handles document save callbacks from OnlyOffice without JWT validation
 */

require_once __DIR__ . '/../config/config.php';

// Set JSON response headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Log all callbacks for debugging
$logFile = __DIR__ . '/../../logs/onlyoffice-callback.log';

try {
    // Read request payload
    $input = file_get_contents('php://input');
    
    // Log the callback
    $logEntry = date('Y-m-d H:i:s') . " - Callback received\n";
    $logEntry .= "Method: " . $_SERVER['REQUEST_METHOD'] . "\n";
    $logEntry .= "Query: " . json_encode($_GET) . "\n";
    $logEntry .= "Body: " . $input . "\n";
    $logEntry .= "Remote IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";
    $logEntry .= str_repeat('-', 50) . "\n";
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    
    if (empty($input)) {
        // Empty request - OnlyOffice health check
        echo json_encode(['error' => 0]);
        exit;
    }
    
    // Decode JSON payload
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Invalid JSON payload');
    }
    
    // Extract callback information
    $status = isset($data['status']) ? intval($data['status']) : 0;
    $key = $data['key'] ?? '';
    $url = $data['url'] ?? '';
    $users = $data['users'] ?? [];
    
    // Get document ID from query string
    $documentId = $_GET['id'] ?? '';
    
    // Log status
    $statusMessages = [
        0 => 'No document with the key identifier could be found',
        1 => 'Document is being edited',
        2 => 'Document is ready for saving',
        3 => 'Document saving error has occurred',
        4 => 'Document is closed with no changes',
        6 => 'Document is being edited, but the current document state is saved',
        7 => 'Error has occurred while force saving the document'
    ];
    
    $logEntry = date('Y-m-d H:i:s') . " - Status: {$status} - " . ($statusMessages[$status] ?? 'Unknown') . "\n";
    $logEntry .= "Document ID: {$documentId}\n";
    $logEntry .= "Key: {$key}\n";
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    
    // Handle different callback statuses
    switch ($status) {
        case 0:
            // Document not found - should not happen
            error_log("OnlyOffice: Document not found - key: {$key}");
            break;
            
        case 1:
            // Document is being edited
            error_log("OnlyOffice: Document opened for editing - id: {$documentId}");
            if (!empty($users)) {
                error_log("OnlyOffice: Active users: " . json_encode($users));
            }
            break;
            
        case 2:
        case 6:
            // Document is ready for saving (2) or force save (6)
            if (!empty($url)) {
                error_log("OnlyOffice: Saving document - id: {$documentId}, url: {$url}");
                
                // Download the updated document from OnlyOffice
                $documentsDir = realpath(__DIR__ . '/../../documents/onlyoffice');
                
                if ($documentsDir && $documentId) {
                    // Sanitize document ID
                    $documentId = preg_replace('/[^a-zA-Z0-9_-]/', '', $documentId);
                    $targetPath = $documentsDir . '/' . $documentId . '.docx';
                    
                    // Create backup of existing file
                    if (file_exists($targetPath)) {
                        $backupPath = $documentsDir . '/backup_' . $documentId . '_' . time() . '.docx';
                        copy($targetPath, $backupPath);
                        error_log("OnlyOffice: Backup created - {$backupPath}");
                    }
                    
                    // Download new version from OnlyOffice
                    $context = stream_context_create([
                        'http' => [
                            'timeout' => 30,
                            'method' => 'GET',
                            'header' => "User-Agent: Nexio OnlyOffice Client\r\n"
                        ]
                    ]);
                    
                    $content = @file_get_contents($url, false, $context);
                    
                    if ($content !== false) {
                        // Save the new version
                        if (file_put_contents($targetPath, $content) !== false) {
                            error_log("OnlyOffice: Document saved successfully - {$targetPath}");
                            
                            // Update database if needed
                            if ($documentId && is_numeric($documentId)) {
                                try {
                                    $stmt = db_query(
                                        "UPDATE documenti SET 
                                         data_modifica = NOW(),
                                         versione = versione + 1
                                         WHERE id = ?",
                                        [$documentId]
                                    );
                                    error_log("OnlyOffice: Database updated for document {$documentId}");
                                } catch (Exception $e) {
                                    error_log("OnlyOffice: Database update failed - " . $e->getMessage());
                                }
                            }
                        } else {
                            error_log("OnlyOffice: Failed to save document to {$targetPath}");
                        }
                    } else {
                        error_log("OnlyOffice: Failed to download document from {$url}");
                    }
                }
            }
            break;
            
        case 3:
            // Document saving error
            error_log("OnlyOffice: Document saving error - id: {$documentId}");
            break;
            
        case 4:
            // Document closed with no changes
            error_log("OnlyOffice: Document closed without changes - id: {$documentId}");
            break;
            
        case 7:
            // Force save error
            error_log("OnlyOffice: Force save error - id: {$documentId}");
            break;
    }
    
    // Always return success to OnlyOffice
    echo json_encode(['error' => 0]);
    
} catch (Exception $e) {
    error_log("OnlyOffice Callback Error: " . $e->getMessage());
    
    // Log error
    $logEntry = date('Y-m-d H:i:s') . " - ERROR: " . $e->getMessage() . "\n";
    $logEntry .= str_repeat('=', 50) . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    
    // Return error to OnlyOffice
    http_response_code(500);
    echo json_encode([
        'error' => 1,
        'message' => $e->getMessage()
    ]);
}
?>