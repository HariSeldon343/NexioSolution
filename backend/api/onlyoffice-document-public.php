<?php
/**
 * OnlyOffice Document Public Endpoint for Testing
 * This endpoint serves documents without authentication for OnlyOffice container access
 * SECURITY WARNING: This is for testing only! Remove in production.
 */

// No authentication required for this test endpoint
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get document ID from request
$docId = $_GET['doc'] ?? null;

if (!$docId) {
    http_response_code(400);
    die('Missing document ID');
}

// For testing, we'll use a static test document
$testDocPath = __DIR__ . '/../../documents/onlyoffice/new.docx';

// Check if file exists
if (!file_exists($testDocPath)) {
    // Try to create a simple test document
    $testDocPath = __DIR__ . '/../../test-document-public.docx';
    if (!file_exists($testDocPath)) {
        http_response_code(404);
        die('Test document not found');
    }
}

// Serve the file
$filename = 'document_' . $docId . '.docx';
$filesize = filesize($testDocPath);

// Clear any output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Set headers for file download
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . $filesize);
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Output file
readfile($testDocPath);
exit;