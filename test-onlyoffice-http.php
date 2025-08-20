<?php
/**
 * Test OnlyOffice HTTP Configuration
 * Verifica che tutte le configurazioni puntino a HTTP:8082
 */

require_once __DIR__ . '/backend/config/onlyoffice.config.php';

// Test document ID
$docId = 'test_' . time();

// Generate URLs using the configuration class
$documentUrl = OnlyOfficeConfig::getDocumentUrl($docId);
$callbackUrl = OnlyOfficeConfig::getCallbackUrl($docId);
$serverUrl = OnlyOfficeConfig::getDocumentServerPublicUrl();

echo "OnlyOffice HTTP Configuration Test\n";
echo "===================================\n\n";

// Check Document Server URL
echo "1. Document Server URL: " . $serverUrl . "\n";
if ($serverUrl === 'http://localhost:8082/') {
    echo "   ✓ Correct: Using HTTP on port 8082\n";
} else {
    echo "   ✗ ERROR: Should be http://localhost:8082/\n";
}

// Check Document URL
echo "\n2. Document URL: " . $documentUrl . "\n";
if (strpos($documentUrl, 'host.docker.internal') !== false) {
    echo "   ✓ Correct: Using host.docker.internal\n";
} else {
    echo "   ✗ ERROR: Should use host.docker.internal\n";
}

// Check Callback URL
echo "\n3. Callback URL: " . $callbackUrl . "\n";
if (strpos($callbackUrl, 'host.docker.internal') !== false) {
    echo "   ✓ Correct: Using host.docker.internal\n";
} else {
    echo "   ✗ ERROR: Should use host.docker.internal\n";
}

// Check API.js URL
$apiUrl = $serverUrl . 'web-apps/apps/api/documents/api.js';
echo "\n4. API.js URL: " . $apiUrl . "\n";
if ($apiUrl === 'http://localhost:8082/web-apps/apps/api/documents/api.js') {
    echo "   ✓ Correct: Using HTTP on port 8082\n";
} else {
    echo "   ✗ ERROR: Should be http://localhost:8082/web-apps/apps/api/documents/api.js\n";
}

// Test container connectivity
echo "\n5. Testing OnlyOffice Server Connection...\n";
$ch = curl_init('http://localhost:8082/healthcheck');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "   ✓ OnlyOffice server is reachable on HTTP:8082\n";
} else {
    echo "   ✗ Cannot reach OnlyOffice server (HTTP code: $httpCode)\n";
}

echo "\n===================================\n";
echo "Configuration Summary:\n";
echo "- Public access: http://localhost:8082/\n";
echo "- Internal URLs: http://host.docker.internal/piattaforma-collaborativa/\n";
echo "- JWT: " . (OnlyOfficeConfig::JWT_ENABLED ? 'Enabled' : 'Disabled') . "\n";

// HTML version for browser
if (php_sapi_name() !== 'cli') {
    echo "<pre>";
    echo htmlspecialchars(ob_get_contents());
    echo "</pre>";
}
?>