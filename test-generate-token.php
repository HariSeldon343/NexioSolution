<?php
/**
 * Test script to generate a valid access token for OnlyOffice document API
 */

require_once 'backend/config/config.php';

// Function to get secret key (from onlyoffice-document.php)
function getSecretKey() {
    return 'nexio-document-access-secret-2025';
}

// Function to generate access token
function generateAccessToken($documentId, $userId = 1, $expiresIn = 3600) {
    $payload = [
        'document_id' => $documentId,
        'user_id' => $userId,
        'azienda_id' => null, // null per accesso globale
        'expires' => time() + $expiresIn,
        'permissions' => ['read', 'write']
    ];
    
    $payloadEncoded = base64_encode(json_encode($payload));
    $signature = hash_hmac('sha256', $payloadEncoded, getSecretKey());
    
    return $payloadEncoded . '.' . $signature;
}

// Generate token for document ID 22
$documentId = 22;
$token = generateAccessToken($documentId);

echo "Generated access token for document ID $documentId:\n";
echo $token . "\n\n";

// Test URL
$testUrl = "http://host.docker.internal/piattaforma-collaborativa/backend/api/onlyoffice-document.php?id=$documentId&token=$token";
echo "Test URL for Docker container:\n";
echo $testUrl . "\n\n";

// Regular URL for browser
$browserUrl = "http://localhost/piattaforma-collaborativa/backend/api/onlyoffice-document.php?id=$documentId&token=$token";
echo "Test URL for browser:\n";
echo $browserUrl . "\n";
?>