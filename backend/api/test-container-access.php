<?php
/**
 * Test Container Access
 * Verifies that OnlyOffice container can access the host
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$testUrl = "http://host.docker.internal/piattaforma-collaborativa/test-document-public.docx";

// Try to access the URL using curl from PHP (simulating container access)
$ch = curl_init($testUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($httpCode === 200) {
    echo json_encode([
        'success' => true,
        'message' => 'Container can access host successfully',
        'http_code' => $httpCode,
        'test_url' => $testUrl
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => $error ?: "HTTP $httpCode",
        'http_code' => $httpCode,
        'test_url' => $testUrl
    ]);
}