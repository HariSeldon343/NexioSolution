<?php
/**
 * Final test for OnlyOffice auth endpoint
 */

// No output before this!
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['csrf_token'] = 'test';
$_SESSION['azienda_id'] = 1;

// Make HTTP request to the endpoint
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost/piattaforma-collaborativa/backend/api/onlyoffice-auth.php?action=generate_token&document_id=1");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, "PHPSESSID=" . session_id());
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-CSRF-Token: ' . $_SESSION['csrf_token']
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Now we can output
echo "=== OnlyOffice Auth Endpoint Test ===\n\n";
echo "Endpoint: backend/api/onlyoffice-auth.php?action=generate_token\n";
echo "HTTP Status Code: $httpCode\n";

if ($error) {
    echo "❌ CURL Error: $error\n";
} else {
    echo "Response Length: " . strlen($response) . " bytes\n\n";
    
    // Parse JSON
    $json = json_decode($response, true);
    $jsonError = json_last_error();
    
    if ($jsonError === JSON_ERROR_NONE) {
        echo "✅ VALID JSON RESPONSE!\n";
        echo "===================\n";
        
        if (isset($json['success'])) {
            if ($json['success'] === true) {
                echo "✅ SUCCESS: true\n";
                if (isset($json['token'])) {
                    echo "✅ Token generated: " . substr($json['token'], 0, 50) . "...\n";
                }
            } else {
                echo "⚠️ SUCCESS: false\n";
                if (isset($json['error'])) {
                    echo "Error message: " . $json['error'] . "\n";
                }
            }
        }
        
        echo "\nFull JSON structure:\n";
        print_r($json);
    } else {
        echo "❌ INVALID JSON!\n";
        echo "JSON Error: " . json_last_error_msg() . "\n\n";
        
        // Check for common errors in response
        if (strpos($response, 'Fatal error') !== false) {
            echo "❌ FATAL ERROR DETECTED IN RESPONSE!\n";
            echo "Response:\n" . $response . "\n";
        } else {
            echo "Raw response (first 500 chars):\n";
            echo substr($response, 0, 500) . "\n";
        }
    }
}

echo "\n=== Test Complete ===\n";
if ($jsonError === JSON_ERROR_NONE && isset($json['success'])) {
    echo "✅ The endpoint is working correctly and returning valid JSON!\n";
} else {
    echo "❌ The endpoint needs further investigation.\n";
}
?>