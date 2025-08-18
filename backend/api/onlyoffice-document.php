<?php
/**
 * OnlyOffice Document Server API - Enhanced with Security
 * Secure document serving with JWT validation and multi-tenant support
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/onlyoffice.config.php';
require_once __DIR__ . '/../middleware/Auth.php';

// Apply security headers
applyOnlyOfficeSecurityHeaders();

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Get document ID and token
    $documentId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $accessToken = $_GET['token'] ?? '';
    
    if (!$documentId) {
        throw new Exception('Document ID required');
    }
    
    // Verify access token
    if (!verifyAccessToken($accessToken, $documentId)) {
        http_response_code(403);
        throw new Exception('Invalid or expired access token');
    }
    
    // Get document from database with multi-tenant check
    $document = getDocumentWithSecurity($documentId, $accessToken);
    
    if (!$document) {
        http_response_code(404);
        throw new Exception('Document not found or access denied');
    }
    
    // Check if file exists
    $filePath = __DIR__ . '/../../' . $document['percorso_file'];
    
    // If file doesn't exist in old path, check new OnlyOffice directory
    if (!file_exists($filePath)) {
        $fileName = $documentId . '_' . time() . '.' . pathinfo($document['nome_file'], PATHINFO_EXTENSION);
        $filePath = $ONLYOFFICE_DOCUMENTS_DIR . '/' . $fileName;
    }
    
    // If still doesn't exist, check original upload path
    if (!file_exists($filePath)) {
        $filePath = __DIR__ . '/../../uploads/documenti/' . $document['nome_file'];
    }
    
    // Final fallback - create from database content if available
    if (!file_exists($filePath) && !empty($document['contenuto_html'])) {
        $filePath = createDocumentFromContent($document);
    }
    
    if (!file_exists($filePath)) {
        // For new documents, create an empty template
        if (isset($_GET['new']) && $_GET['new'] === '1') {
            $filePath = createEmptyDocument($document);
        } else {
            http_response_code(404);
            throw new Exception('Document file not found on server');
        }
    }
    
    // Determine MIME type
    $mimeType = $document['mime_type'] ?? mime_content_type($filePath);
    $extension = strtolower(pathinfo($document['nome_file'], PATHINFO_EXTENSION));
    
    // Map extensions to MIME types if needed
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
    
    if (isset($mimeMap[$extension])) {
        $mimeType = $mimeMap[$extension];
    }
    
    // Set headers for file download
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: inline; filename="' . basename($document['nome_file']) . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: private, max-age=3600');
    header('ETag: "' . md5_file($filePath) . '"');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($filePath)) . ' GMT');
    
    // Handle range requests for large files
    if (isset($_SERVER['HTTP_RANGE'])) {
        handleRangeRequest($filePath);
    } else {
        // Output file content
        readfile($filePath);
    }
    
    // Log document access
    logDocumentAccess($documentId, $document['azienda_id']);
    
} catch (Exception $e) {
    error_log("OnlyOffice Document API Error: " . $e->getMessage());
    
    // Don't expose detailed errors to client
    if (!headers_sent()) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Document access error'
        ]);
    }
}

/**
 * Verify access token
 */
function verifyAccessToken($token, $documentId) {
    if (empty($token)) {
        return false;
    }
    
    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        return false;
    }
    
    list($payload, $signature) = $parts;
    
    // Verify signature
    $expectedSignature = hash_hmac('sha256', $payload, getSecretKey());
    if ($signature !== $expectedSignature) {
        return false;
    }
    
    // Decode and verify payload
    $data = json_decode(base64_decode($payload), true);
    if (!$data) {
        return false;
    }
    
    // Check document ID matches
    if ($data['document_id'] != $documentId) {
        return false;
    }
    
    // Check expiration
    if (isset($data['expires']) && $data['expires'] < time()) {
        return false;
    }
    
    return true;
}

/**
 * Get document with security checks
 */
function getDocumentWithSecurity($documentId, $accessToken) {
    // Decode token to get user context
    $parts = explode('.', $accessToken);
    $payload = json_decode(base64_decode($parts[0]), true);
    
    $userId = $payload['user_id'] ?? null;
    $aziendaId = $payload['azienda_id'] ?? null;
    
    // Build query with multi-tenant filtering
    $query = "SELECT d.*, a.nome_azienda 
              FROM documenti d 
              LEFT JOIN aziende a ON d.azienda_id = a.id 
              WHERE d.id = ?";
    
    $params = [$documentId];
    
    // Add company filter if not super admin
    if ($aziendaId) {
        $query .= " AND (d.azienda_id = ? OR d.azienda_id IS NULL)";
        $params[] = $aziendaId;
    }
    
    $stmt = db_query($query, $params);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Create document from HTML content
 */
function createDocumentFromContent($document) {
    global $ONLYOFFICE_DOCUMENTS_DIR;
    
    $extension = pathinfo($document['nome_file'], PATHINFO_EXTENSION) ?: 'docx';
    $fileName = $document['id'] . '_' . time() . '.' . $extension;
    $filePath = $ONLYOFFICE_DOCUMENTS_DIR . '/' . $fileName;
    
    // Convert HTML to document format
    if ($extension === 'docx' && !empty($document['contenuto_html'])) {
        // Simple HTML to DOCX conversion (you'd use a proper library in production)
        $docxContent = createDocxFromHtml($document['contenuto_html']);
        file_put_contents($filePath, $docxContent);
    } else {
        // For other formats, create empty document
        $emptyContent = createMinimalDocument($extension);
        file_put_contents($filePath, $emptyContent);
    }
    
    return $filePath;
}

/**
 * Create empty document template
 */
function createEmptyDocument($document) {
    global $ONLYOFFICE_DOCUMENTS_DIR;
    
    $extension = pathinfo($document['nome_file'], PATHINFO_EXTENSION) ?: 'docx';
    $fileName = $document['id'] . '_empty_' . time() . '.' . $extension;
    $filePath = $ONLYOFFICE_DOCUMENTS_DIR . '/' . $fileName;
    
    $content = createMinimalDocument($extension);
    file_put_contents($filePath, $content);
    
    return $filePath;
}

/**
 * Create minimal document based on extension
 */
function createMinimalDocument($extension) {
    switch ($extension) {
        case 'docx':
            return createMinimalDocx();
        case 'xlsx':
            return createMinimalXlsx();
        case 'pptx':
            return createMinimalPptx();
        case 'txt':
            return "New Document\n\nCreated with Nexio Platform";
        case 'csv':
            return "Column1,Column2,Column3\nData1,Data2,Data3";
        default:
            return createMinimalDocx();
    }
}

/**
 * Create minimal DOCX file
 */
function createMinimalDocx() {
    // Minimal DOCX template in base64
    $template = 'UEsDBBQACAgIAAAAAIdO4kSjAQAAAAEAAABQSwMEFAAICAgAAAAAgU7iRKMBAAEAAAAIAAAAbWltZXR5cGVhcHBsaWNhdGlvbi92bmQub2FzaXMub3BlbmRvY3VtZW50LnRleHRQSwMEFAAICAgAAAAAgU7iRKMAQAAAAAAEAAAAbWV0YS54bWzCrVJbS8MwFH6f4H8IeW+TbkNHW0fRIajgBXTiW5aetrE0J5CkXf+9SdtuQ0QQfDvn+y7nkpzFu4JrWykliQg56LmcCSLjjIhVyF8Xj84FvzseTTJUUhODnCXEyHA4OgyZllLTkJOc0xV4UqWoFkBKtMhRjJPzuP5Syu1WgpkimSDdAFiLNuQz3pJ6yEIeCl4IznQJvtW0sgjH9vpqnBJWVmqGwHa9y0vPG7ieP/CBN/R7Xj/o+f2B40zRwBm4bk+yRnBGUIUSyZQouQz5zVt6H7GtjH39p14vYMbnomQiYlxxJTFzGT9B2u5gQ37nX9yHP1VXZ38FIzT4PzgefQJQSwMEFAAICAgAAAAAgU7iRKMBAAAAAQAAAAhQSwECFAAUAAgICAAAAACBTuJEowEAAAABAAAACAAAAAAAAAAAAAAAAAAAAAAAbWltZXR5cGVQSwECFAAUAAgICAAAAACBTuJEowEAAAABAAAACAAAAAAAAAAAAAAAAAAAAAAAbWV0YS54bWxQSwUGAAAAAAIAAgBwAAAAYgAAAAAA';
    return base64_decode($template);
}

/**
 * Create minimal XLSX file
 */
function createMinimalXlsx() {
    // Minimal XLSX template
    $template = 'UEsDBBQACAgIAAAAAIdO4kSjAQAAAAEAAABQSwMEFAAICAgAAAAAgU7iRKMBAAAACAAAAG1pbWV0eXBlYXBwbGljYXRpb24vdm5kLm9wZW54bWxmb3JtYXRzLW9mZmljZWRvY3VtZW50LnNwcmVhZHNoZWV0bWwuc2hlZXRQSwUGAAAAAAEAAQA2AAAANQAAAAAA';
    return base64_decode($template);
}

/**
 * Create minimal PPTX file
 */
function createMinimalPptx() {
    // Minimal PPTX template
    $template = 'UEsDBBQACAgIAAAAAIdO4kSjAQAAAAEAAABQSwMEFAAICAgAAAAAgU7iRKMBAAAACAAAAG1pbWV0eXBlYXBwbGljYXRpb24vdm5kLm9wZW54bWxmb3JtYXRzLW9mZmljZWRvY3VtZW50LnByZXNlbnRhdGlvbm1sLnByZXNlbnRhdGlvblBLBQYAAAAAAQABADoAAAA5AAAAAAA=';
    return base64_decode($template);
}

/**
 * Convert HTML to DOCX (simplified)
 */
function createDocxFromHtml($html) {
    // This is a simplified version - use a proper library like PHPWord in production
    return createMinimalDocx();
}

/**
 * Handle HTTP range requests for partial content
 */
function handleRangeRequest($filePath) {
    $fileSize = filesize($filePath);
    $range = $_SERVER['HTTP_RANGE'];
    
    list($unit, $ranges) = explode('=', $range, 2);
    
    if ($unit !== 'bytes') {
        header('HTTP/1.1 416 Range Not Satisfiable');
        header("Content-Range: bytes */$fileSize");
        return;
    }
    
    $ranges = explode(',', $ranges)[0]; // Handle only first range
    list($start, $end) = explode('-', $ranges);
    
    $start = intval($start);
    $end = $end ? intval($end) : $fileSize - 1;
    
    if ($start > $end || $end >= $fileSize) {
        header('HTTP/1.1 416 Range Not Satisfiable');
        header("Content-Range: bytes */$fileSize");
        return;
    }
    
    $length = $end - $start + 1;
    
    header('HTTP/1.1 206 Partial Content');
    header("Content-Range: bytes $start-$end/$fileSize");
    header("Content-Length: $length");
    
    $fp = fopen($filePath, 'rb');
    fseek($fp, $start);
    
    $remaining = $length;
    while ($remaining > 0 && !feof($fp)) {
        $buffer = fread($fp, min(8192, $remaining));
        echo $buffer;
        $remaining -= strlen($buffer);
        flush();
    }
    
    fclose($fp);
}

/**
 * Get secret key for token verification
 */
function getSecretKey() {
    global $ONLYOFFICE_JWT_SECRET;
    $appSecret = defined('APP_SECRET') ? APP_SECRET : 'nexio-platform-2024';
    return hash('sha256', $ONLYOFFICE_JWT_SECRET . $appSecret);
}

/**
 * Log document access
 */
function logDocumentAccess($documentId, $aziendaId) {
    try {
        // Update last access timestamp
        db_query(
            "UPDATE documenti SET ultimo_accesso = NOW() WHERE id = ?",
            [$documentId]
        );
        
        // Log in activity table if available
        if (class_exists('ActivityLogger')) {
            require_once __DIR__ . '/../utils/ActivityLogger.php';
            ActivityLogger::log(
                'documento',
                'accesso',
                "Accesso documento via OnlyOffice",
                null,
                $aziendaId,
                $documentId
            );
        }
    } catch (Exception $e) {
        error_log("Failed to log document access: " . $e->getMessage());
    }
}
?>