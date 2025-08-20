<?php
/**
 * API per testare la connettività a un URL
 * Usata dalla pagina di diagnostica
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$url = $input['url'] ?? '';

if (empty($url)) {
    echo json_encode(['success' => false, 'error' => 'URL mancante']);
    exit;
}

// Test connettività
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($httpCode >= 200 && $httpCode < 400) {
    echo json_encode([
        'success' => true,
        'httpCode' => $httpCode,
        'url' => $url
    ]);
} else {
    echo json_encode([
        'success' => false,
        'httpCode' => $httpCode,
        'error' => $error ?: 'Connection failed',
        'url' => $url
    ]);
}
?>