<?php
/**
 * Simple document serving endpoint for OnlyOffice
 * This bypasses complex authentication to serve documents directly
 */

// Enable CORS for OnlyOffice
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
header('Access-Control-Allow-Headers: *');

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get document ID or filename
$docId = $_GET['doc'] ?? '';
$filename = $_GET['filename'] ?? '';

// Determine file path
if ($filename) {
    // Direct filename provided
    $filePath = __DIR__ . '/../../documents/onlyoffice/' . basename($filename);
} elseif ($docId) {
    // Document ID provided, assume .docx extension
    $filePath = __DIR__ . '/../../documents/onlyoffice/' . basename($docId) . '.docx';
} else {
    // Default test document
    $filePath = __DIR__ . '/../../documents/onlyoffice/test_document_1755605547.docx';
}

// Security: Ensure we're only serving from the onlyoffice directory
$realPath = realpath($filePath);
$allowedPath = realpath(__DIR__ . '/../../documents/onlyoffice');

if (!$realPath || strpos($realPath, $allowedPath) !== 0) {
    error_log("Document serve: Invalid path attempt - " . $filePath);
    http_response_code(403);
    die('Access denied');
}

// Check if file exists
if (!file_exists($realPath)) {
    error_log("Document serve: File not found - " . $realPath);
    http_response_code(404);
    die('File not found: ' . basename($filePath));
}

// Determine content type based on file extension
$ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
$contentTypes = [
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
    'rtf' => 'application/rtf'
];

$contentType = $contentTypes[$ext] ?? 'application/octet-stream';

// Log successful access
error_log("Document serve: Serving file - " . basename($realPath) . " (Type: $contentType, Size: " . filesize($realPath) . " bytes)");

// Send headers
header('Content-Type: ' . $contentType);
header('Content-Length: ' . filesize($realPath));
header('Content-Disposition: inline; filename="' . basename($realPath) . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Output file
readfile($realPath);
exit;