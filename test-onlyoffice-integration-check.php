<?php
/**
 * OnlyOffice Integration Test Script
 * Verifies all components of the OnlyOffice integration
 */

// Disable error display to get clean JSON output
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Load configuration
require_once 'backend/config/config.php';
require_once 'backend/config/onlyoffice.config.php';

// Test results array
$tests = [];
$hasErrors = false;

// Helper function to add test result
function addTest($name, $status, $details = '', $isError = false) {
    global $tests, $hasErrors;
    if ($isError) $hasErrors = true;
    $tests[] = [
        'test' => $name,
        'status' => $status ? 'PASS' : 'FAIL',
        'details' => $details
    ];
}

// 1. Check OnlyOffice Server Configuration
$serverUrl = $ONLYOFFICE_DS_PUBLIC_URL ?? '';
addTest(
    'OnlyOffice Server URL Configuration',
    !empty($serverUrl),
    "URL: $serverUrl",
    empty($serverUrl)
);

// 2. Check JWT Configuration
$jwtEnabled = $ONLYOFFICE_JWT_ENABLED ?? false;
$jwtSecret = $ONLYOFFICE_JWT_SECRET ?? '';
addTest(
    'JWT Security Configuration',
    $jwtEnabled && !empty($jwtSecret),
    "JWT Enabled: " . ($jwtEnabled ? 'Yes' : 'No') . ", Secret: " . (empty($jwtSecret) ? 'NOT SET' : 'SET'),
    !$jwtEnabled || empty($jwtSecret)
);

// 3. Check OnlyOffice Server Health
$healthCheck = false;
$healthDetails = '';
if (!empty($serverUrl)) {
    $healthUrl = $serverUrl . '/healthcheck';
    $ch = curl_init($healthUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $healthCheck = ($httpCode === 200 && $response === 'true');
    $healthDetails = "HTTP $httpCode, Response: " . substr($response, 0, 100);
}
addTest(
    'OnlyOffice Server Health Check',
    $healthCheck,
    $healthDetails,
    !$healthCheck
);

// 4. Check Callback URL Configuration
$callbackUrl = $ONLYOFFICE_CALLBACK_URL ?? '';
$isLocalhost = strpos($callbackUrl, 'localhost') !== false || strpos($callbackUrl, '127.0.0.1') !== false;
$hasDockerHost = strpos($callbackUrl, 'host.docker.internal') !== false;

addTest(
    'Callback URL Configuration',
    !empty($callbackUrl) && ($hasDockerHost || !$isLocalhost),
    "URL: $callbackUrl" . ($isLocalhost && !$hasDockerHost ? ' (WARNING: Using localhost, should use host.docker.internal for Docker)' : ''),
    empty($callbackUrl)
);

// 5. Check Documents Directory
$docsDir = $ONLYOFFICE_DOCUMENTS_DIR ?? '';
$dirExists = is_dir($docsDir);
$dirWritable = $dirExists && is_writable($docsDir);

addTest(
    'Documents Directory',
    $dirExists && $dirWritable,
    "Path: $docsDir, Exists: " . ($dirExists ? 'Yes' : 'No') . ", Writable: " . ($dirWritable ? 'Yes' : 'No'),
    !$dirExists || !$dirWritable
);

// 6. Check Database Table Structure
$dbCheck = false;
$dbDetails = '';
try {
    $stmt = db_query("SHOW COLUMNS FROM documenti LIKE 'percorso_file'");
    $hasPercorsoFile = $stmt->rowCount() > 0;
    
    $stmt = db_query("SHOW COLUMNS FROM documenti LIKE 'nome_file'");
    $hasNomeFile = $stmt->rowCount() > 0;
    
    $dbCheck = $hasPercorsoFile && $hasNomeFile;
    $dbDetails = "percorso_file: " . ($hasPercorsoFile ? 'EXISTS' : 'MISSING') . 
                 ", nome_file: " . ($hasNomeFile ? 'EXISTS' : 'MISSING');
} catch (Exception $e) {
    $dbDetails = "Error: " . $e->getMessage();
}

addTest(
    'Database Table Structure',
    $dbCheck,
    $dbDetails,
    !$dbCheck
);

// 7. Check API Endpoints
$apiEndpoints = [
    'onlyoffice-auth.php',
    'onlyoffice-callback.php', 
    'onlyoffice-document.php',
    'onlyoffice-prepare.php',
    'onlyoffice-proxy.php'
];

$apiCheck = true;
$apiDetails = [];
foreach ($apiEndpoints as $endpoint) {
    $path = __DIR__ . '/backend/api/' . $endpoint;
    $exists = file_exists($path);
    if (!$exists) $apiCheck = false;
    $apiDetails[] = basename($endpoint, '.php') . ': ' . ($exists ? 'OK' : 'MISSING');
}

addTest(
    'API Endpoints',
    $apiCheck,
    implode(', ', $apiDetails),
    !$apiCheck
);

// 8. Check JWT Functions
$jwtFunctionsCheck = function_exists('generateOnlyOfficeJWT') && 
                     function_exists('verifyOnlyOfficeJWT') &&
                     function_exists('extractJWTFromRequest');

addTest(
    'JWT Functions',
    $jwtFunctionsCheck,
    'generateOnlyOfficeJWT: ' . (function_exists('generateOnlyOfficeJWT') ? 'OK' : 'MISSING') .
    ', verifyOnlyOfficeJWT: ' . (function_exists('verifyOnlyOfficeJWT') ? 'OK' : 'MISSING'),
    !$jwtFunctionsCheck
);

// 9. Test JWT Token Generation
$jwtTestPassed = false;
$jwtTestDetails = '';
if ($jwtFunctionsCheck && $jwtEnabled) {
    try {
        $testPayload = ['test' => 'data', 'timestamp' => time()];
        $token = generateOnlyOfficeJWT($testPayload);
        $jwtTestPassed = !empty($token) && substr_count($token, '.') === 2;
        $jwtTestDetails = $jwtTestPassed ? 'Token generated successfully' : 'Token generation failed';
    } catch (Exception $e) {
        $jwtTestDetails = 'Error: ' . $e->getMessage();
    }
} else {
    $jwtTestDetails = 'JWT not enabled or functions missing';
}

addTest(
    'JWT Token Generation Test',
    $jwtTestPassed || !$jwtEnabled,
    $jwtTestDetails,
    $jwtEnabled && !$jwtTestPassed
);

// 10. Check File Permissions
$uploadDir = __DIR__ . '/uploads/documenti';
$uploadDirExists = is_dir($uploadDir);
$uploadDirWritable = $uploadDirExists && is_writable($uploadDir);

addTest(
    'Upload Directory Permissions',
    $uploadDirExists && $uploadDirWritable,
    "Path: $uploadDir, Exists: " . ($uploadDirExists ? 'Yes' : 'No') . ", Writable: " . ($uploadDirWritable ? 'Yes' : 'No'),
    !$uploadDirWritable
);

// 11. Check Document Type Mapping
$documentTypesCheck = isset($ONLYOFFICE_DOCUMENT_TYPES) && is_array($ONLYOFFICE_DOCUMENT_TYPES);
$supportedFormats = $ONLYOFFICE_SUPPORTED_FORMATS ?? [];

addTest(
    'Document Type Configuration',
    $documentTypesCheck || !empty($supportedFormats),
    'Supported formats: ' . count($supportedFormats) . ' types',
    false
);

// 12. Test OnlyOffice Connection with JWT
$connectionTest = false;
$connectionDetails = '';
if ($healthCheck && $jwtEnabled) {
    try {
        $testToken = generateOnlyOfficeJWT(['iss' => 'nexio-test']);
        $ch = curl_init($serverUrl . '/healthcheck');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $testToken
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $connectionTest = ($httpCode === 200);
        $connectionDetails = "HTTP $httpCode with JWT auth";
    } catch (Exception $e) {
        $connectionDetails = 'Error: ' . $e->getMessage();
    }
} else {
    $connectionDetails = 'Server not available or JWT not enabled';
}

addTest(
    'OnlyOffice JWT Connection Test',
    $connectionTest || !$jwtEnabled,
    $connectionDetails,
    $jwtEnabled && !$connectionTest
);

// Generate summary
$totalTests = count($tests);
$passedTests = count(array_filter($tests, function($t) { return $t['status'] === 'PASS'; }));
$failedTests = $totalTests - $passedTests;

// Output results
header('Content-Type: application/json');
echo json_encode([
    'summary' => [
        'total' => $totalTests,
        'passed' => $passedTests,
        'failed' => $failedTests,
        'status' => $failedTests === 0 ? 'SUCCESS' : ($failedTests < 3 ? 'WARNING' : 'ERROR')
    ],
    'configuration' => [
        'server_url' => $serverUrl,
        'jwt_enabled' => $jwtEnabled,
        'callback_url' => $callbackUrl,
        'documents_dir' => $docsDir
    ],
    'tests' => $tests,
    'recommendations' => generateRecommendations($tests)
], JSON_PRETTY_PRINT);

function generateRecommendations($tests) {
    $recommendations = [];
    
    foreach ($tests as $test) {
        if ($test['status'] === 'FAIL') {
            switch ($test['test']) {
                case 'OnlyOffice Server Health Check':
                    $recommendations[] = 'Ensure OnlyOffice Docker container is running: docker-compose up -d';
                    $recommendations[] = 'Check if port 8082 is accessible';
                    break;
                case 'JWT Security Configuration':
                    $recommendations[] = 'Set ONLYOFFICE_JWT_ENABLED=true in configuration';
                    $recommendations[] = 'Configure JWT secret key in both OnlyOffice and PHP config';
                    break;
                case 'Callback URL Configuration':
                    $recommendations[] = 'Use host.docker.internal instead of localhost for Docker environments';
                    break;
                case 'Documents Directory':
                    $recommendations[] = 'Create documents directory: mkdir -p documents/onlyoffice';
                    $recommendations[] = 'Set proper permissions: chmod 755 documents/onlyoffice';
                    break;
            }
        }
    }
    
    return array_unique($recommendations);
}
?>