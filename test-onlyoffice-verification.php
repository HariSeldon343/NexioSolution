<?php
/**
 * OnlyOffice Integration Verification Script
 * Tests all components of the OnlyOffice integration
 */

// Configuration
require_once 'backend/config/config.php';
require_once 'backend/config/onlyoffice.config.php';

// Test results
$tests = [];

// 1. Test Docker Container
echo "OnlyOffice Integration Verification\n";
echo "=====================================\n\n";

// Test 1: OnlyOffice Server Connectivity
echo "1. Testing OnlyOffice Server Connectivity...\n";
$ch = curl_init($ONLYOFFICE_DS_PUBLIC_URL . '/healthcheck');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "   ✓ OnlyOffice server is healthy (HTTP $httpCode)\n";
    $tests['server'] = true;
} else {
    echo "   ✗ OnlyOffice server not responding (HTTP $httpCode)\n";
    $tests['server'] = false;
}

// Test 2: JWT Configuration
echo "\n2. Testing JWT Configuration...\n";
if (defined('ONLYOFFICE_JWT_SECRET') && !empty(ONLYOFFICE_JWT_SECRET)) {
    echo "   ✓ JWT secret is configured\n";
    $tests['jwt_config'] = true;
} else {
    echo "   ✗ JWT secret not configured\n";
    $tests['jwt_config'] = false;
}

// Test 3: Document Path
echo "\n3. Testing Document Path...\n";
$docPath = __DIR__ . '/documents/onlyoffice/';
if (file_exists($docPath) && is_dir($docPath)) {
    $files = glob($docPath . '*.docx');
    echo "   ✓ Document directory exists with " . count($files) . " .docx files\n";
    $tests['doc_path'] = true;
} else {
    echo "   ✗ Document directory not found\n";
    $tests['doc_path'] = false;
}

// Test 4: Create JWT Token
echo "\n4. Testing JWT Token Generation...\n";
function generateJWT($payload, $secret) {
    $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
    $payload = json_encode($payload);
    
    $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    $signature = hash_hmac('sha256', $base64Header . '.' . $base64Payload, $secret, true);
    $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    return $base64Header . '.' . $base64Payload . '.' . $base64Signature;
}

$testPayload = [
    'document' => [
        'fileType' => 'docx',
        'key' => 'test_' . time(),
        'title' => 'Test Document',
        'url' => 'http://host.docker.internal/piattaforma-collaborativa/documents/onlyoffice/45.docx'
    ],
    'editorConfig' => [
        'mode' => 'view'
    ]
];

try {
    $token = generateJWT($testPayload, ONLYOFFICE_JWT_SECRET);
    echo "   ✓ JWT token generated successfully\n";
    $tests['jwt_generation'] = true;
} catch (Exception $e) {
    echo "   ✗ JWT generation failed: " . $e->getMessage() . "\n";
    $tests['jwt_generation'] = false;
}

// Test 5: OnlyOffice API Call
echo "\n5. Testing OnlyOffice API...\n";
$ch = curl_init($ONLYOFFICE_DS_PUBLIC_URL . '/coauthoring/CommandService.ashx');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'c' => 'info',
    'key' => 'test_' . time()
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "   ✓ OnlyOffice API responding (HTTP $httpCode)\n";
    $tests['api'] = true;
} else {
    echo "   ✗ OnlyOffice API error (HTTP $httpCode)\n";
    $tests['api'] = false;
}

// Test 6: Database Tables
echo "\n6. Testing Database Tables...\n";
try {
    $db = db_connection();
    $stmt = $db->query("SHOW TABLES LIKE 'onlyoffice_sessions'");
    if ($stmt->fetch()) {
        echo "   ✓ OnlyOffice database tables exist\n";
        $tests['database'] = true;
    } else {
        echo "   ✗ OnlyOffice database tables missing\n";
        $tests['database'] = false;
    }
} catch (Exception $e) {
    echo "   ✗ Database error: " . $e->getMessage() . "\n";
    $tests['database'] = false;
}

// Summary
echo "\n=====================================\n";
echo "Test Summary:\n";
$passed = array_sum($tests);
$total = count($tests);
echo "Passed: $passed/$total\n";

if ($passed === $total) {
    echo "\n✓ All tests passed! OnlyOffice is properly configured.\n";
    
    // Provide direct link to test
    echo "\nYou can test the editor at:\n";
    echo "http://localhost/piattaforma-collaborativa/onlyoffice-editor.php\n";
} else {
    echo "\n✗ Some tests failed. Please check the configuration.\n";
    
    // Provide troubleshooting tips
    echo "\nTroubleshooting:\n";
    if (!$tests['server']) {
        echo "- Check if Docker container 'nexio-documentserver' is running\n";
        echo "  Run: docker ps | grep nexio-documentserver\n";
    }
    if (!$tests['jwt_config']) {
        echo "- Check JWT secret in backend/config/onlyoffice.config.php\n";
    }
    if (!$tests['database']) {
        echo "- Run database migration: /mnt/c/xampp/mysql/bin/mysql.exe -u root nexiosol < database/create_onlyoffice_tables.sql\n";
    }
}

// Debug information
echo "\n=====================================\n";
echo "Configuration Details:\n";
echo "OnlyOffice URL: $ONLYOFFICE_DS_PUBLIC_URL\n";
echo "JWT Enabled: " . (ONLYOFFICE_JWT_ENABLED ? 'Yes' : 'No') . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Document Path: " . realpath($docPath) . "\n";