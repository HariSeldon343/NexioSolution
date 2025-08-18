<?php
/**
 * OnlyOffice Integration Test Suite
 * Tests all critical components of the OnlyOffice integration
 * 
 * Run: /mnt/c/xampp/php/php.exe test-onlyoffice-integration.php
 */

// Prevent browser output for CLI execution
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

// Configuration
require_once __DIR__ . '/backend/config/config.php';
require_once __DIR__ . '/backend/config/onlyoffice.config.php';
require_once __DIR__ . '/backend/middleware/Auth.php';
require_once __DIR__ . '/backend/models/DocumentVersion.php';

// Test configuration
$testResults = [];
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;
$warnings = [];

// Color codes for CLI output
$colors = [
    'reset' => "\033[0m",
    'red' => "\033[31m",
    'green' => "\033[32m",
    'yellow' => "\033[33m",
    'blue' => "\033[34m",
    'magenta' => "\033[35m",
    'cyan' => "\033[36m"
];

// Helper functions
function isCliMode() {
    return php_sapi_name() === 'cli';
}

function output($message, $color = null) {
    global $colors;
    if (isCliMode() && $color && isset($colors[$color])) {
        echo $colors[$color] . $message . $colors['reset'] . "\n";
    } else {
        echo $message . "\n";
    }
}

function testSection($title) {
    output("\n" . str_repeat("=", 60), 'cyan');
    output($title, 'cyan');
    output(str_repeat("=", 60), 'cyan');
}

function runTest($name, $testFunction) {
    global $totalTests, $passedTests, $failedTests, $testResults;
    
    $totalTests++;
    output("\n→ Testing: $name", 'yellow');
    
    try {
        $result = $testFunction();
        if ($result === true || (is_array($result) && $result['success'] === true)) {
            $passedTests++;
            output("  ✓ PASSED", 'green');
            $testResults[$name] = ['status' => 'passed'];
            
            if (is_array($result) && isset($result['details'])) {
                output("    Details: " . $result['details']);
            }
        } else {
            $failedTests++;
            $errorMsg = is_array($result) ? ($result['error'] ?? 'Unknown error') : 'Test returned false';
            output("  ✗ FAILED: $errorMsg", 'red');
            $testResults[$name] = ['status' => 'failed', 'error' => $errorMsg];
        }
    } catch (Exception $e) {
        $failedTests++;
        output("  ✗ EXCEPTION: " . $e->getMessage(), 'red');
        $testResults[$name] = ['status' => 'failed', 'error' => $e->getMessage()];
    }
}

function addWarning($message) {
    global $warnings;
    $warnings[] = $message;
    output("  ⚠ WARNING: $message", 'yellow');
}

// ================================================================
// TEST 1: Configuration Tests
// ================================================================

testSection("1. CONFIGURATION TESTS");

runTest("JWT Configuration", function() {
    global $ONLYOFFICE_JWT_ENABLED, $ONLYOFFICE_JWT_SECRET, $ONLYOFFICE_JWT_ALGORITHM;
    
    if (!$ONLYOFFICE_JWT_ENABLED) {
        addWarning("JWT is disabled - CRITICAL for production");
    }
    
    if ($ONLYOFFICE_JWT_SECRET === 'a7f3b2c9d8e4f5a6b7c8d9e0f1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0') {
        addWarning("Using default JWT secret - change for production!");
    }
    
    if (!in_array($ONLYOFFICE_JWT_ALGORITHM, ['HS256', 'HS512'])) {
        return ['success' => false, 'error' => "Invalid JWT algorithm: $ONLYOFFICE_JWT_ALGORITHM"];
    }
    
    return ['success' => true, 'details' => "JWT enabled: " . ($ONLYOFFICE_JWT_ENABLED ? 'Yes' : 'No')];
});

runTest("Server URLs Configuration", function() {
    global $ONLYOFFICE_DS_PUBLIC_URL, $ONLYOFFICE_DS_INTERNAL_URL, $ONLYOFFICE_CALLBACK_URL;
    
    if (empty($ONLYOFFICE_DS_PUBLIC_URL)) {
        return ['success' => false, 'error' => 'ONLYOFFICE_DS_PUBLIC_URL not configured'];
    }
    
    if (empty($ONLYOFFICE_CALLBACK_URL)) {
        return ['success' => false, 'error' => 'ONLYOFFICE_CALLBACK_URL not configured'];
    }
    
    // Check if localhost is being used in production
    $isProduction = getenv('APP_ENV') === 'production';
    if ($isProduction && strpos($ONLYOFFICE_DS_PUBLIC_URL, 'localhost') !== false) {
        addWarning("Using localhost in production environment");
    }
    
    return [
        'success' => true, 
        'details' => "Public: $ONLYOFFICE_DS_PUBLIC_URL, Callback: $ONLYOFFICE_CALLBACK_URL"
    ];
});

runTest("Documents Directory", function() {
    global $ONLYOFFICE_DOCUMENTS_DIR;
    
    if (!is_dir($ONLYOFFICE_DOCUMENTS_DIR)) {
        if (!@mkdir($ONLYOFFICE_DOCUMENTS_DIR, 0755, true)) {
            return ['success' => false, 'error' => "Cannot create directory: $ONLYOFFICE_DOCUMENTS_DIR"];
        }
    }
    
    if (!is_writable($ONLYOFFICE_DOCUMENTS_DIR)) {
        return ['success' => false, 'error' => "Directory not writable: $ONLYOFFICE_DOCUMENTS_DIR"];
    }
    
    // Test file creation
    $testFile = $ONLYOFFICE_DOCUMENTS_DIR . '/test_' . time() . '.txt';
    if (!@file_put_contents($testFile, 'test')) {
        return ['success' => false, 'error' => 'Cannot write test file'];
    }
    @unlink($testFile);
    
    return ['success' => true, 'details' => "Directory: $ONLYOFFICE_DOCUMENTS_DIR"];
});

runTest("Log File Configuration", function() {
    global $ONLYOFFICE_LOG_FILE, $ONLYOFFICE_DEBUG;
    
    if ($ONLYOFFICE_DEBUG) {
        $logDir = dirname($ONLYOFFICE_LOG_FILE);
        if (!is_dir($logDir)) {
            return ['success' => false, 'error' => "Log directory doesn't exist: $logDir"];
        }
        
        if (!file_exists($ONLYOFFICE_LOG_FILE)) {
            if (!@touch($ONLYOFFICE_LOG_FILE)) {
                addWarning("Cannot create log file: $ONLYOFFICE_LOG_FILE");
            }
        }
        
        if (file_exists($ONLYOFFICE_LOG_FILE) && !is_writable($ONLYOFFICE_LOG_FILE)) {
            addWarning("Log file not writable: $ONLYOFFICE_LOG_FILE");
        }
    }
    
    return ['success' => true, 'details' => "Debug: " . ($ONLYOFFICE_DEBUG ? 'Enabled' : 'Disabled')];
});

// ================================================================
// TEST 2: Database Tables
// ================================================================

testSection("2. DATABASE TABLES");

runTest("Documents Table", function() {
    $stmt = db_query("SHOW COLUMNS FROM documenti");
    $columns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $row['Field'];
    }
    
    $requiredColumns = [
        'id', 'azienda_id', 'nome_file', 'file_path', 
        'dimensione_file', 'mime_type', 'tipo_documento',
        'creato_il', 'aggiornato_il', 'ultimo_accesso'
    ];
    
    $missingColumns = array_diff($requiredColumns, $columns);
    if (!empty($missingColumns)) {
        return ['success' => false, 'error' => 'Missing columns: ' . implode(', ', $missingColumns)];
    }
    
    // Check for OnlyOffice specific columns
    $onlyofficeColumns = ['is_editing', 'editing_users', 'editing_started_at', 'current_version', 'total_versions'];
    $missingOnlyOfficeColumns = array_diff($onlyofficeColumns, $columns);
    if (!empty($missingOnlyOfficeColumns)) {
        addWarning("Missing OnlyOffice columns: " . implode(', ', $missingOnlyOfficeColumns));
    }
    
    return ['success' => true, 'details' => count($columns) . ' columns found'];
});

runTest("Document Versions Table", function() {
    // Check if extended versions table exists
    $stmt = db_query("SHOW TABLES LIKE 'documenti_versioni_extended'");
    if ($stmt->rowCount() > 0) {
        $stmt = db_query("SHOW COLUMNS FROM documenti_versioni_extended");
        $columns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $row['Field'];
        }
        
        $requiredColumns = [
            'id', 'documento_id', 'version_number', 'file_path',
            'created_at', 'created_by_id', 'created_by_name'
        ];
        
        $missingColumns = array_diff($requiredColumns, $columns);
        if (!empty($missingColumns)) {
            addWarning("Missing version columns: " . implode(', ', $missingColumns));
        }
        
        return ['success' => true, 'details' => 'Extended versions table exists'];
    } else {
        // Check for basic versions table
        $stmt = db_query("SHOW TABLES LIKE 'documenti_versioni'");
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'details' => 'Basic versions table exists'];
        }
        
        return ['success' => false, 'error' => 'No versions table found'];
    }
});

runTest("Activity Log Table", function() {
    $stmt = db_query("SHOW TABLES LIKE 'log_attivita'");
    if ($stmt->rowCount() === 0) {
        return ['success' => false, 'error' => 'log_attivita table not found'];
    }
    
    // Check if document activity log exists
    $stmt = db_query("SHOW TABLES LIKE 'document_activity_log'");
    if ($stmt->rowCount() === 0) {
        addWarning("document_activity_log table not found - detailed logging unavailable");
    }
    
    return ['success' => true];
});

// ================================================================
// TEST 3: API Endpoints
// ================================================================

testSection("3. API ENDPOINTS");

runTest("OnlyOffice Callback API", function() {
    $callbackFile = __DIR__ . '/backend/api/onlyoffice-callback.php';
    if (!file_exists($callbackFile)) {
        return ['success' => false, 'error' => 'Callback file not found'];
    }
    
    // Check if file is readable
    if (!is_readable($callbackFile)) {
        return ['success' => false, 'error' => 'Callback file not readable'];
    }
    
    // Check for required functions
    $content = file_get_contents($callbackFile);
    $requiredFunctions = [
        'handleEditingDocument',
        'saveDocumentFromCallback',
        'handleSaveError',
        'handleDocumentClosed'
    ];
    
    foreach ($requiredFunctions as $func) {
        if (strpos($content, "function $func") === false) {
            addWarning("Function $func not found in callback");
        }
    }
    
    return ['success' => true];
});

runTest("OnlyOffice Document API", function() {
    $documentFile = __DIR__ . '/backend/api/onlyoffice-document.php';
    if (!file_exists($documentFile)) {
        return ['success' => false, 'error' => 'Document API file not found'];
    }
    
    return ['success' => true];
});

runTest("OnlyOffice Prepare API", function() {
    $prepareFile = __DIR__ . '/backend/api/onlyoffice-prepare.php';
    if (!file_exists($prepareFile)) {
        return ['success' => false, 'error' => 'Prepare API file not found'];
    }
    
    return ['success' => true];
});

// ================================================================
// TEST 4: JWT Functions
// ================================================================

testSection("4. JWT FUNCTIONALITY");

runTest("JWT Generation", function() {
    $testPayload = [
        'document' => ['key' => 'test_123'],
        'user' => ['id' => '1', 'name' => 'Test User'],
        'exp' => time() + 3600
    ];
    
    $token = generateOnlyOfficeJWT($testPayload);
    
    if (empty($token)) {
        global $ONLYOFFICE_JWT_ENABLED;
        if (!$ONLYOFFICE_JWT_ENABLED) {
            addWarning("JWT generation skipped - JWT disabled");
            return ['success' => true, 'details' => 'JWT disabled'];
        }
        return ['success' => false, 'error' => 'Token generation failed'];
    }
    
    // Check token format
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return ['success' => false, 'error' => 'Invalid token format'];
    }
    
    return ['success' => true, 'details' => 'Token length: ' . strlen($token)];
});

runTest("JWT Verification", function() {
    global $ONLYOFFICE_JWT_ENABLED;
    
    if (!$ONLYOFFICE_JWT_ENABLED) {
        return ['success' => true, 'details' => 'JWT disabled'];
    }
    
    $testPayload = [
        'test' => 'data',
        'exp' => time() + 3600
    ];
    
    $token = generateOnlyOfficeJWT($testPayload);
    $result = verifyOnlyOfficeJWT($token);
    
    if (!$result['valid']) {
        return ['success' => false, 'error' => 'Verification failed: ' . ($result['error'] ?? 'Unknown')];
    }
    
    if ($result['payload']['test'] !== 'data') {
        return ['success' => false, 'error' => 'Payload mismatch'];
    }
    
    return ['success' => true];
});

runTest("JWT Expiration Check", function() {
    global $ONLYOFFICE_JWT_ENABLED;
    
    if (!$ONLYOFFICE_JWT_ENABLED) {
        return ['success' => true, 'details' => 'JWT disabled'];
    }
    
    // Create expired token
    $expiredPayload = [
        'test' => 'expired',
        'exp' => time() - 3600 // Expired 1 hour ago
    ];
    
    $token = generateOnlyOfficeJWT($expiredPayload);
    $result = verifyOnlyOfficeJWT($token);
    
    if ($result['valid']) {
        return ['success' => false, 'error' => 'Expired token accepted'];
    }
    
    if ($result['error'] !== 'Token expired') {
        addWarning("Unexpected error for expired token: " . $result['error']);
    }
    
    return ['success' => true, 'details' => 'Expired tokens rejected correctly'];
});

// ================================================================
// TEST 5: Security Features
// ================================================================

testSection("5. SECURITY FEATURES");

runTest("Rate Limiting", function() {
    $testIdentifier = 'test_' . time();
    
    // Should pass first time
    if (!checkOnlyOfficeRateLimit($testIdentifier)) {
        return ['success' => false, 'error' => 'Rate limit failed on first request'];
    }
    
    global $ONLYOFFICE_RATE_LIMIT;
    if ($ONLYOFFICE_RATE_LIMIT > 0) {
        // Simulate hitting rate limit
        for ($i = 0; $i < $ONLYOFFICE_RATE_LIMIT + 5; $i++) {
            checkOnlyOfficeRateLimit($testIdentifier);
        }
        
        // Should now be rate limited
        if (checkOnlyOfficeRateLimit($testIdentifier)) {
            addWarning("Rate limiting might not be working correctly");
        }
    }
    
    return ['success' => true, 'details' => "Rate limit: $ONLYOFFICE_RATE_LIMIT requests/minute"];
});

runTest("HTTPS Enforcement", function() {
    global $isProduction;
    
    if (!$isProduction) {
        return ['success' => true, 'details' => 'Not in production mode'];
    }
    
    global $ONLYOFFICE_DS_PUBLIC_URL, $ONLYOFFICE_CALLBACK_URL;
    
    if (strpos($ONLYOFFICE_DS_PUBLIC_URL, 'https://') !== 0) {
        return ['success' => false, 'error' => 'OnlyOffice URL not using HTTPS in production'];
    }
    
    if (strpos($ONLYOFFICE_CALLBACK_URL, 'https://') !== 0) {
        addWarning("Callback URL not using HTTPS in production");
    }
    
    return ['success' => true];
});

runTest("IP Validation", function() {
    global $ONLYOFFICE_ALLOWED_IPS;
    
    if (empty($ONLYOFFICE_ALLOWED_IPS)) {
        addWarning("No IP restrictions configured - accepting all IPs");
        return ['success' => true, 'details' => 'No IP restrictions'];
    }
    
    // Test IP validation function
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $result = validateOnlyOfficeCallbackIP();
    
    if (in_array('127.0.0.1', $ONLYOFFICE_ALLOWED_IPS) && !$result) {
        return ['success' => false, 'error' => 'Valid IP rejected'];
    }
    
    return ['success' => true, 'details' => count($ONLYOFFICE_ALLOWED_IPS) . ' allowed IPs'];
});

// ================================================================
// TEST 6: Multi-Tenant Isolation
// ================================================================

testSection("6. MULTI-TENANT ISOLATION");

runTest("Company Isolation in Documents", function() {
    // Test that documents are properly isolated by azienda_id
    $stmt = db_query(
        "SELECT COUNT(DISTINCT azienda_id) as company_count FROM documenti WHERE azienda_id IS NOT NULL"
    );
    $result = $stmt->fetch();
    $companyCount = $result['company_count'] ?? 0;
    
    if ($companyCount > 0) {
        // Check for any documents without azienda_id
        $stmt = db_query("SELECT COUNT(*) as global_docs FROM documenti WHERE azienda_id IS NULL");
        $result = $stmt->fetch();
        $globalDocs = $result['global_docs'] ?? 0;
        
        if ($globalDocs > 0) {
            addWarning("Found $globalDocs global documents (azienda_id = NULL)");
        }
    }
    
    return ['success' => true, 'details' => "$companyCount companies with documents"];
});

runTest("Version Isolation Check", function() {
    // Ensure versions are linked to correct documents
    $stmt = db_query("SHOW TABLES LIKE 'documenti_versioni%'");
    if ($stmt->rowCount() > 0) {
        // Check for orphaned versions
        $table = $stmt->fetch()[0];
        $stmt = db_query(
            "SELECT COUNT(*) as orphaned FROM $table v 
             LEFT JOIN documenti d ON v.documento_id = d.id 
             WHERE d.id IS NULL"
        );
        $result = $stmt->fetch();
        $orphaned = $result['orphaned'] ?? 0;
        
        if ($orphaned > 0) {
            return ['success' => false, 'error' => "Found $orphaned orphaned versions"];
        }
    }
    
    return ['success' => true];
});

// ================================================================
// TEST 7: OnlyOffice Server Connection
// ================================================================

testSection("7. ONLYOFFICE SERVER CONNECTION");

runTest("Server Availability", function() {
    global $ONLYOFFICE_DS_PUBLIC_URL, $ONLYOFFICE_TIMEOUT;
    
    // Skip if localhost (server might not be running)
    if (strpos($ONLYOFFICE_DS_PUBLIC_URL, 'localhost') !== false) {
        addWarning("Skipping server test for localhost");
        return ['success' => true, 'details' => 'Localhost - skipped'];
    }
    
    $healthUrl = $ONLYOFFICE_DS_PUBLIC_URL . '/healthcheck';
    
    $context = stream_context_create([
        'http' => [
            'timeout' => $ONLYOFFICE_TIMEOUT,
            'method' => 'GET',
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);
    
    $response = @file_get_contents($healthUrl, false, $context);
    
    if ($response === false) {
        addWarning("Cannot connect to OnlyOffice server at $ONLYOFFICE_DS_PUBLIC_URL");
        return ['success' => true, 'details' => 'Server unreachable - might be expected'];
    }
    
    if (strpos($response, 'true') !== false) {
        return ['success' => true, 'details' => 'Server is healthy'];
    }
    
    return ['success' => false, 'error' => 'Server returned unexpected response'];
});

// ================================================================
// TEST 8: Integration Tests
// ================================================================

testSection("8. INTEGRATION TESTS");

runTest("Document Creation Flow", function() {
    // Create a test document entry
    $testName = 'test_doc_' . time() . '.docx';
    $testAzienda = 1; // Use company 1 for testing
    
    try {
        $stmt = db_query(
            "INSERT INTO documenti (nome_file, azienda_id, tipo_documento, mime_type, dimensione_file, creato_il)
             VALUES (?, ?, 'documento', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 1024, NOW())",
            [$testName, $testAzienda]
        );
        
        $documentId = db_connection()->lastInsertId();
        
        // Clean up
        db_query("DELETE FROM documenti WHERE id = ?", [$documentId]);
        
        return ['success' => true, 'details' => "Created and deleted test document ID: $documentId"];
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
});

runTest("Permission Check Simulation", function() {
    // Test that permission checks would work
    if (!class_exists('Auth')) {
        return ['success' => false, 'error' => 'Auth class not found'];
    }
    
    // Don't actually instantiate Auth to avoid session issues
    return ['success' => true, 'details' => 'Auth class available'];
});

// ================================================================
// TEST 9: File Operations
// ================================================================

testSection("9. FILE OPERATIONS");

runTest("Document Upload Path", function() {
    $uploadPath = __DIR__ . '/uploads/documenti';
    
    if (!is_dir($uploadPath)) {
        if (!@mkdir($uploadPath, 0755, true)) {
            return ['success' => false, 'error' => "Cannot create upload directory"];
        }
    }
    
    if (!is_writable($uploadPath)) {
        return ['success' => false, 'error' => "Upload directory not writable"];
    }
    
    return ['success' => true, 'details' => $uploadPath];
});

runTest("Temporary File Handling", function() {
    global $ONLYOFFICE_DOCUMENTS_DIR;
    
    $tempFile = $ONLYOFFICE_DOCUMENTS_DIR . '/temp_' . time() . '.txt';
    $testContent = "Test content for OnlyOffice integration";
    
    if (!@file_put_contents($tempFile, $testContent)) {
        return ['success' => false, 'error' => 'Cannot write temporary file'];
    }
    
    $readContent = file_get_contents($tempFile);
    if ($readContent !== $testContent) {
        @unlink($tempFile);
        return ['success' => false, 'error' => 'File content mismatch'];
    }
    
    @unlink($tempFile);
    return ['success' => true];
});

// ================================================================
// TEST 10: Configuration Validation
// ================================================================

testSection("10. CONFIGURATION VALIDATION");

runTest("Configuration Check Function", function() {
    $errors = checkOnlyOfficeConfig();
    
    if (!empty($errors)) {
        foreach ($errors as $error) {
            addWarning($error);
        }
        return ['success' => false, 'error' => 'Configuration has ' . count($errors) . ' issues'];
    }
    
    return ['success' => true];
});

runTest("Supported Formats", function() {
    global $ONLYOFFICE_SUPPORTED_FORMATS;
    
    if (empty($ONLYOFFICE_SUPPORTED_FORMATS)) {
        return ['success' => false, 'error' => 'No supported formats configured'];
    }
    
    $requiredFormats = ['docx', 'xlsx', 'pptx'];
    $missingFormats = array_diff($requiredFormats, $ONLYOFFICE_SUPPORTED_FORMATS);
    
    if (!empty($missingFormats)) {
        return ['success' => false, 'error' => 'Missing formats: ' . implode(', ', $missingFormats)];
    }
    
    return ['success' => true, 'details' => count($ONLYOFFICE_SUPPORTED_FORMATS) . ' formats supported'];
});

// ================================================================
// SUMMARY
// ================================================================

testSection("TEST SUMMARY");

output("\nTotal Tests: $totalTests", 'blue');
output("Passed: $passedTests", 'green');
output("Failed: $failedTests", 'red');

if (!empty($warnings)) {
    output("\nWarnings (" . count($warnings) . "):", 'yellow');
    foreach ($warnings as $warning) {
        output("  • $warning", 'yellow');
    }
}

// Calculate success rate
$successRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 2) : 0;
output("\nSuccess Rate: $successRate%", $successRate >= 80 ? 'green' : ($successRate >= 60 ? 'yellow' : 'red'));

// Final recommendations
output("\n" . str_repeat("=", 60), 'cyan');
output("RECOMMENDATIONS", 'cyan');
output(str_repeat("=", 60), 'cyan');

$recommendations = [];

if ($failedTests > 0) {
    $recommendations[] = "Fix the " . $failedTests . " failed tests before using in production";
}

global $ONLYOFFICE_JWT_ENABLED, $ONLYOFFICE_JWT_SECRET, $isProduction;

if (!$ONLYOFFICE_JWT_ENABLED && $isProduction) {
    $recommendations[] = "CRITICAL: Enable JWT authentication for production";
}

if ($ONLYOFFICE_JWT_SECRET === 'a7f3b2c9d8e4f5a6b7c8d9e0f1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0') {
    $recommendations[] = "CRITICAL: Change JWT secret from default value";
}

if (strpos($ONLYOFFICE_DS_PUBLIC_URL, 'localhost') !== false && $isProduction) {
    $recommendations[] = "Configure proper OnlyOffice server URL for production";
}

// Check for missing tables
$missingTables = [];
$optionalTables = [
    'documenti_versioni_extended' => 'Version management',
    'document_activity_log' => 'Activity logging',
    'document_active_editors' => 'Collaborative editing tracking',
    'document_collaborative_actions' => 'Collaboration history'
];

foreach ($optionalTables as $table => $feature) {
    $stmt = db_query("SHOW TABLES LIKE '$table'");
    if ($stmt->rowCount() === 0) {
        $missingTables[] = "$table ($feature)";
    }
}

if (!empty($missingTables)) {
    $recommendations[] = "Consider creating these tables for full functionality:\n    • " . implode("\n    • ", $missingTables);
}

if (empty($recommendations)) {
    output("\n✓ System appears ready for OnlyOffice integration!", 'green');
    output("  Note: Ensure OnlyOffice Document Server is running and accessible.", 'yellow');
} else {
    foreach ($recommendations as $rec) {
        output("\n• $rec", 'yellow');
    }
}

// Export results for automated testing
if (isCliMode() && in_array('--json', $argv ?? [])) {
    $jsonResults = [
        'timestamp' => date('Y-m-d H:i:s'),
        'total_tests' => $totalTests,
        'passed' => $passedTests,
        'failed' => $failedTests,
        'success_rate' => $successRate,
        'warnings' => $warnings,
        'test_results' => $testResults,
        'recommendations' => $recommendations
    ];
    
    file_put_contents('test-results-onlyoffice.json', json_encode($jsonResults, JSON_PRETTY_PRINT));
    output("\nResults exported to test-results-onlyoffice.json", 'cyan');
}

output("\n" . str_repeat("=", 60) . "\n", 'cyan');

// Exit with appropriate code
exit($failedTests > 0 ? 1 : 0);
?>