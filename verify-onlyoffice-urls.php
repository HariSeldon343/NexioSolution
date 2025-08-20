<?php
/**
 * Verify OnlyOffice URLs Configuration
 * Checks all critical URLs are correctly configured
 */

header('Content-Type: text/plain; charset=utf-8');

echo "OnlyOffice URL Configuration Verification\n";
echo "=========================================\n\n";

// Check 1: OnlyOffice API availability
echo "1. Checking OnlyOffice API (http://localhost:8082/)...\n";
$apiUrl = 'http://localhost:8082/web-apps/apps/api/documents/api.js';
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200 && strpos($response, 'DocsAPI') !== false) {
    echo "   ✓ API accessible and valid\n";
} else {
    echo "   ✗ API not accessible (HTTP $httpCode)\n";
}

// Check 2: Document public endpoint
echo "\n2. Checking document public endpoint...\n";
$docPublicUrl = 'http://host.docker.internal/piattaforma-collaborativa/backend/api/onlyoffice-document-public.php';
echo "   URL: $docPublicUrl\n";
echo "   Note: This URL is for internal container use only\n";

// Check 3: Callback endpoint
echo "\n3. Checking callback endpoint...\n";
$callbackUrl = 'http://host.docker.internal/piattaforma-collaborativa/backend/api/onlyoffice-callback.php';
echo "   URL: $callbackUrl\n";
echo "   Note: This URL is for internal container use only\n";

// Check 4: Verify no HTTPS:8443 references
echo "\n4. Checking for incorrect HTTPS:8443 references...\n";
$files = [
    'test-onlyoffice-definitivo.php',
    'backend/api/onlyoffice-document-public.php',
    'backend/api/onlyoffice-callback.php',
    'backend/api/onlyoffice-prepare.php',
    'backend/api/onlyoffice-document.php'
];

$issues = [];
foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if (strpos($content, '8443') !== false || strpos($content, 'https://localhost:8082') !== false) {
            $issues[] = $file;
        }
    }
}

if (empty($issues)) {
    echo "   ✓ No incorrect HTTPS:8443 references found\n";
} else {
    echo "   ✗ Found incorrect references in:\n";
    foreach ($issues as $issue) {
        echo "      - $issue\n";
    }
}

// Check 5: Container connectivity test
echo "\n5. Testing container connectivity...\n";
$healthUrl = 'http://localhost:8082/healthcheck';
$ch = curl_init($healthUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 3);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "   ✓ OnlyOffice container is healthy\n";
} else {
    echo "   ✗ OnlyOffice container not responding (HTTP $httpCode)\n";
    echo "   Run: docker ps | grep onlyoffice\n";
}

// Summary
echo "\n=========================================\n";
echo "CONFIGURATION SUMMARY:\n";
echo "- OnlyOffice Server: http://localhost:8082/\n";
echo "- Internal URLs: http://host.docker.internal/piattaforma-collaborativa/\n";
echo "- API.js: http://localhost:8082/web-apps/apps/api/documents/api.js\n";
echo "\nIMPORTANT:\n";
echo "- NEVER use HTTPS:8443 (not configured)\n";
echo "- ALWAYS use HTTP:8082 for OnlyOffice\n";
echo "- ALWAYS use host.docker.internal for container->host communication\n";

// Test URLs
echo "\n\nTEST PAGES:\n";
echo "- Simple test: http://localhost/piattaforma-collaborativa/onlyoffice-test-http.php\n";
echo "- Full test: http://localhost/piattaforma-collaborativa/test-onlyoffice-definitivo.php\n";
echo "- Config check: http://localhost/piattaforma-collaborativa/test-onlyoffice-http.php\n";
?>