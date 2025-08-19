<?php
/**
 * OnlyOffice File Server
 * Serves documents to OnlyOffice container without authentication
 * SECURITY: Only allows access from localhost/Docker network
 */

// Allow access only from localhost or Docker network
$allowed_ips = [
    '127.0.0.1',
    'localhost',
    '::1',
    '172.16.0.0/12',  // Docker default network range
    '192.168.0.0/16',  // Common Docker network range
    '10.0.0.0/8'       // Another Docker network range
];

$client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
$is_allowed = false;

// Check if client IP is in allowed range
foreach ($allowed_ips as $allowed) {
    if (strpos($allowed, '/') !== false) {
        // CIDR notation
        list($subnet, $mask) = explode('/', $allowed);
        if (ip2long($client_ip) && ip2long($subnet)) {
            $subnet_decimal = ip2long($subnet);
            $client_decimal = ip2long($client_ip);
            $mask_decimal = -1 << (32 - $mask);
            
            if (($client_decimal & $mask_decimal) == ($subnet_decimal & $mask_decimal)) {
                $is_allowed = true;
                break;
            }
        }
    } else {
        // Direct IP match
        if ($client_ip === $allowed || $client_ip === gethostbyname($allowed)) {
            $is_allowed = true;
            break;
        }
    }
}

// Also check X-Forwarded-For header for Docker proxy
$forwarded_for = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
if ($forwarded_for && !$is_allowed) {
    $forwarded_ips = explode(',', $forwarded_for);
    foreach ($forwarded_ips as $fip) {
        $fip = trim($fip);
        if (in_array($fip, ['127.0.0.1', '::1']) || strpos($fip, '172.') === 0 || strpos($fip, '10.') === 0 || strpos($fip, '192.168.') === 0) {
            $is_allowed = true;
            break;
        }
    }
}

if (!$is_allowed) {
    http_response_code(403);
    die('Access denied. Only localhost and Docker network access allowed.');
}

// Get document ID
$doc_id = $_GET['id'] ?? '';
if (empty($doc_id)) {
    http_response_code(400);
    die('Missing document ID');
}

// Sanitize document ID
$doc_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $doc_id);
if (empty($doc_id) || strlen($doc_id) > 100) {
    http_response_code(400);
    die('Invalid document ID');
}

// Define documents directory
$documents_dir = realpath(__DIR__ . '/../../documents/onlyoffice');
if (!$documents_dir) {
    http_response_code(500);
    die('Documents directory not found');
}

// Build file path
$file_path = $documents_dir . '/' . $doc_id . '.docx';

// Security: Ensure file is within allowed directory
$real_path = realpath($file_path);
if (!$real_path || strpos($real_path, $documents_dir) !== 0) {
    http_response_code(404);
    die('File not found');
}

// Check if file exists
if (!file_exists($real_path)) {
    // Try without extension
    $file_path = $documents_dir . '/' . $doc_id;
    $real_path = realpath($file_path);
    
    if (!$real_path || !file_exists($real_path)) {
        http_response_code(404);
        die('File not found');
    }
}

// Determine MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $real_path);
finfo_close($finfo);

// Map to correct MIME types for Office documents
$mime_map = [
    'application/zip' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/octet-stream' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];

if (isset($mime_map[$mime_type]) && pathinfo($real_path, PATHINFO_EXTENSION) === 'docx') {
    $mime_type = $mime_map[$mime_type];
}

// Get file info
$file_size = filesize($real_path);
$file_name = basename($real_path);
$last_modified = gmdate('D, d M Y H:i:s', filemtime($real_path)) . ' GMT';

// Send headers
header('Content-Type: ' . $mime_type);
header('Content-Length: ' . $file_size);
header('Content-Disposition: inline; filename="' . $file_name . '"');
header('Last-Modified: ' . $last_modified);
header('Cache-Control: private, max-age=3600');
header('Accept-Ranges: bytes');

// CORS headers for OnlyOffice
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
header('Access-Control-Allow-Headers: Range, Authorization');

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Handle HEAD request
if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
    http_response_code(200);
    exit;
}

// Handle Range requests for partial content
if (isset($_SERVER['HTTP_RANGE'])) {
    $range = $_SERVER['HTTP_RANGE'];
    list($range_unit, $range_spec) = explode('=', $range, 2);
    
    if ($range_unit === 'bytes') {
        $ranges = explode(',', $range_spec);
        $range = $ranges[0];
        
        list($start, $end) = explode('-', $range, 2);
        $start = intval($start);
        $end = $end ? intval($end) : $file_size - 1;
        
        if ($start < 0 || $start >= $file_size || $end >= $file_size || $end < $start) {
            http_response_code(416);
            header("Content-Range: bytes */$file_size");
            exit;
        }
        
        $length = $end - $start + 1;
        
        http_response_code(206);
        header("Content-Range: bytes $start-$end/$file_size");
        header("Content-Length: $length");
        
        $fp = fopen($real_path, 'rb');
        fseek($fp, $start);
        echo fread($fp, $length);
        fclose($fp);
        exit;
    }
}

// Send full file
readfile($real_path);

// Log access for debugging
error_log("OnlyOffice file served: $file_name to $client_ip");
?>