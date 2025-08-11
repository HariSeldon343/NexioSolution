<?php
/**
 * Test script for the new login API endpoint
 */

// Test configuration
$baseUrl = 'http://localhost/piattaforma-collaborativa';
$testUsername = 'test_api_user'; // Test user created for API testing
$testPassword = 'Test123!@#'; // Test password

echo "Testing Login API Endpoint\n";
echo "==========================\n\n";

// Test 1: Login with JSON
echo "Test 1: Login with JSON body\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/backend/api/login.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'username' => $testUsername,
    'password' => $testPassword
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$header = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);
curl_close($ch);

echo "HTTP Status Code: $httpCode\n";
echo "Response Headers:\n";
echo "----------------\n";
$headers = explode("\n", $header);
foreach ($headers as $h) {
    if (trim($h)) echo "  $h\n";
}
echo "\nResponse Body:\n";
echo "--------------\n";
$jsonData = json_decode($body, true);
if ($jsonData) {
    echo json_encode($jsonData, JSON_PRETTY_PRINT) . "\n";
} else {
    echo $body . "\n";
}

// Extract session cookie if present
$sessionId = null;
foreach ($headers as $h) {
    if (stripos($h, 'set-cookie:') !== false) {
        preg_match('/PHPSESSID=([^;]+)/', $h, $matches);
        if (isset($matches[1])) {
            $sessionId = $matches[1];
            echo "\nSession ID extracted: $sessionId\n";
        }
    }
}

echo "\n";

// Test 2: Validate session (if login was successful)
if ($httpCode == 200 && $sessionId) {
    echo "Test 2: Validate session\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/backend/api/validate-session.php');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Cookie: PHPSESSID=' . $sessionId
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Status Code: $httpCode\n";
    echo "Response:\n";
    $jsonData = json_decode($response, true);
    if ($jsonData) {
        echo json_encode($jsonData, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo $response . "\n";
    }
    echo "\n";
    
    // Test 3: Logout
    echo "Test 3: Logout\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/backend/api/logout.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Cookie: PHPSESSID=' . $sessionId
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Status Code: $httpCode\n";
    echo "Response:\n";
    $jsonData = json_decode($response, true);
    if ($jsonData) {
        echo json_encode($jsonData, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo $response . "\n";
    }
}

// Test 4: Invalid credentials
echo "\nTest 4: Login with invalid credentials\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/backend/api/login.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'username' => 'invalid_user',
    'password' => 'wrong_password'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status Code: $httpCode\n";
echo "Response:\n";
$jsonData = json_decode($response, true);
if ($jsonData) {
    echo json_encode($jsonData, JSON_PRETTY_PRINT) . "\n";
} else {
    echo $response . "\n";
}

echo "\n==========================\n";
echo "Tests completed!\n";