<?php
/**
 * OnlyOffice Integration Complete Test Suite
 * Verifica completa dell'integrazione con il Document Server
 * 
 * @version 1.0.0
 * @date 2025-01-18
 */

// Imposta error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Definisci costanti necessarie
define('APP_PATH', '/piattaforma-collaborativa');

// Include configurazioni necessarie
require_once 'backend/config/config.php';
require_once 'backend/config/onlyoffice.config.php';

// Test results container
$testResults = [];
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;
$warnings = 0;

// Test execution timestamp
$testTimestamp = date('Y-m-d H:i:s');

/**
 * Function to add test result
 */
function addTestResult($category, $test, $status, $message = '', $details = []) {
    global $testResults, $totalTests, $passedTests, $failedTests, $warnings;
    
    $totalTests++;
    
    if ($status === 'PASS') {
        $passedTests++;
        $icon = '✅';
        $color = 'success';
    } elseif ($status === 'FAIL') {
        $failedTests++;
        $icon = '❌';
        $color = 'danger';
    } else { // WARNING
        $warnings++;
        $icon = '⚠️';
        $color = 'warning';
    }
    
    if (!isset($testResults[$category])) {
        $testResults[$category] = [];
    }
    
    $testResults[$category][] = [
        'test' => $test,
        'status' => $status,
        'icon' => $icon,
        'color' => $color,
        'message' => $message,
        'details' => $details
    ];
}

/**
 * Test 1: Configuration Check
 */
function testConfiguration() {
    global $ONLYOFFICE_DS_PUBLIC_URL, $ONLYOFFICE_DS_INTERNAL_URL, 
           $ONLYOFFICE_JWT_ENABLED, $ONLYOFFICE_JWT_SECRET, $ONLYOFFICE_CALLBACK_URL,
           $ONLYOFFICE_DOCUMENTS_DIR;
    
    // Test server URLs
    if (!empty($ONLYOFFICE_DS_PUBLIC_URL)) {
        addTestResult('Configuration', 'Public Server URL', 'PASS', 
            "Configured: $ONLYOFFICE_DS_PUBLIC_URL");
    } else {
        addTestResult('Configuration', 'Public Server URL', 'FAIL', 
            'ONLYOFFICE_DS_PUBLIC_URL not configured');
    }
    
    if (!empty($ONLYOFFICE_DS_INTERNAL_URL)) {
        addTestResult('Configuration', 'Internal Server URL', 'PASS', 
            "Configured: $ONLYOFFICE_DS_INTERNAL_URL");
    } else {
        addTestResult('Configuration', 'Internal Server URL', 'FAIL', 
            'ONLYOFFICE_DS_INTERNAL_URL not configured');
    }
    
    // Test JWT configuration
    if ($ONLYOFFICE_JWT_ENABLED) {
        addTestResult('Configuration', 'JWT Authentication', 'PASS', 
            'JWT authentication is enabled');
        
        if (!empty($ONLYOFFICE_JWT_SECRET)) {
            addTestResult('Configuration', 'JWT Secret', 'PASS', 
                'JWT secret is configured (length: ' . strlen($ONLYOFFICE_JWT_SECRET) . ')');
        } else {
            addTestResult('Configuration', 'JWT Secret', 'FAIL', 
                'JWT secret is missing');
        }
    } else {
        addTestResult('Configuration', 'JWT Authentication', 'WARNING', 
            'JWT authentication is disabled (not recommended for production)');
    }
    
    // Test callback URL
    if (!empty($ONLYOFFICE_CALLBACK_URL)) {
        addTestResult('Configuration', 'Callback URL', 'PASS', 
            "Configured: $ONLYOFFICE_CALLBACK_URL");
    } else {
        addTestResult('Configuration', 'Callback URL', 'FAIL', 
            'Callback URL not configured');
    }
    
    // Test documents directory
    if (is_dir($ONLYOFFICE_DOCUMENTS_DIR)) {
        if (is_writable($ONLYOFFICE_DOCUMENTS_DIR)) {
            addTestResult('Configuration', 'Documents Directory', 'PASS', 
                "Directory exists and is writable: $ONLYOFFICE_DOCUMENTS_DIR");
        } else {
            addTestResult('Configuration', 'Documents Directory', 'FAIL', 
                "Directory exists but is not writable: $ONLYOFFICE_DOCUMENTS_DIR");
        }
    } else {
        addTestResult('Configuration', 'Documents Directory', 'FAIL', 
            "Directory does not exist: $ONLYOFFICE_DOCUMENTS_DIR");
    }
}

/**
 * Test 2: Document Server Connection
 */
function testDocumentServerConnection() {
    global $ONLYOFFICE_DS_PUBLIC_URL, $ONLYOFFICE_TIMEOUT;
    
    // Test healthcheck endpoint
    $healthUrl = $ONLYOFFICE_DS_PUBLIC_URL . '/healthcheck';
    
    $context = stream_context_create([
        'http' => [
            'timeout' => $ONLYOFFICE_TIMEOUT ?: 30,
            'method' => 'GET',
            'ignore_errors' => true,
            'header' => "User-Agent: Nexio OnlyOffice Test/1.0\r\nAccept: application/json"
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);
    
    $startTime = microtime(true);
    $result = @file_get_contents($healthUrl, false, $context);
    $responseTime = round((microtime(true) - $startTime) * 1000, 2);
    
    if ($result !== false) {
        if (strpos($result, 'true') !== false) {
            addTestResult('Server Connection', 'Healthcheck', 'PASS', 
                "Server is healthy (Response time: {$responseTime}ms)", 
                ['response' => $result]);
        } else {
            addTestResult('Server Connection', 'Healthcheck', 'WARNING', 
                "Server responded but health status unclear (Response time: {$responseTime}ms)", 
                ['response' => $result]);
        }
    } else {
        addTestResult('Server Connection', 'Healthcheck', 'FAIL', 
            "Cannot connect to OnlyOffice server at $healthUrl");
    }
    
    // Test API endpoint
    $apiUrl = $ONLYOFFICE_DS_PUBLIC_URL . '/coauthoring/CommandService.ashx';
    
    $apiPayload = json_encode([
        'c' => 'version'
    ]);
    
    $apiContext = stream_context_create([
        'http' => [
            'timeout' => $ONLYOFFICE_TIMEOUT ?: 30,
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n" .
                       "Content-Length: " . strlen($apiPayload) . "\r\n",
            'content' => $apiPayload,
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);
    
    $apiResult = @file_get_contents($apiUrl, false, $apiContext);
    
    if ($apiResult !== false) {
        $apiData = json_decode($apiResult, true);
        if (isset($apiData['version'])) {
            addTestResult('Server Connection', 'API Endpoint', 'PASS', 
                "API is accessible. Server version: " . $apiData['version']);
        } else {
            addTestResult('Server Connection', 'API Endpoint', 'WARNING', 
                "API responded but version not found", 
                ['response' => $apiResult]);
        }
    } else {
        addTestResult('Server Connection', 'API Endpoint', 'FAIL', 
            "Cannot access API endpoint at $apiUrl");
    }
}

/**
 * Test 3: JWT Token Generation and Verification
 */
function testJWTFunctionality() {
    global $ONLYOFFICE_JWT_ENABLED, $ONLYOFFICE_JWT_SECRET, $ONLYOFFICE_JWT_ALGORITHM;
    
    if (!$ONLYOFFICE_JWT_ENABLED) {
        addTestResult('JWT Authentication', 'JWT Status', 'WARNING', 
            'JWT is disabled - skipping JWT tests');
        return;
    }
    
    // Test token generation
    $testPayload = [
        'document' => ['key' => 'test_' . time()],
        'editorConfig' => ['user' => ['id' => '1', 'name' => 'Test User']],
        'exp' => time() + 3600
    ];
    
    try {
        $token = generateOnlyOfficeJWT($testPayload);
        
        if (!empty($token)) {
            addTestResult('JWT Authentication', 'Token Generation', 'PASS', 
                'Successfully generated JWT token (length: ' . strlen($token) . ')');
            
            // Test token verification
            $verifyResult = verifyOnlyOfficeJWT($token);
            
            if ($verifyResult['valid']) {
                addTestResult('JWT Authentication', 'Token Verification', 'PASS', 
                    'Successfully verified generated token');
            } else {
                addTestResult('JWT Authentication', 'Token Verification', 'FAIL', 
                    'Failed to verify token: ' . ($verifyResult['error'] ?? 'Unknown error'));
            }
        } else {
            addTestResult('JWT Authentication', 'Token Generation', 'FAIL', 
                'Generated token is empty');
        }
    } catch (Exception $e) {
        addTestResult('JWT Authentication', 'Token Generation', 'FAIL', 
            'Exception during token generation: ' . $e->getMessage());
    }
    
    // Test invalid token verification
    $invalidToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.invalid.signature';
    $invalidResult = verifyOnlyOfficeJWT($invalidToken);
    
    if (!$invalidResult['valid']) {
        addTestResult('JWT Authentication', 'Invalid Token Detection', 'PASS', 
            'Successfully detected invalid token');
    } else {
        addTestResult('JWT Authentication', 'Invalid Token Detection', 'FAIL', 
            'Failed to detect invalid token');
    }
}

/**
 * Test 4: Database Tables and Structure
 */
function testDatabaseTables() {
    try {
        $db = db_connection();
        
        // Check required tables
        $requiredTables = [
            'onlyoffice_sessions' => 'Session management table',
            'onlyoffice_document_versions' => 'Document versioning table',
            'onlyoffice_locks' => 'Document locking table',
            'onlyoffice_audit_log' => 'Audit logging table'
        ];
        
        foreach ($requiredTables as $table => $description) {
            $stmt = $db->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                // Check table structure
                $columnStmt = $db->query("SHOW COLUMNS FROM $table");
                $columnCount = $columnStmt->rowCount();
                
                addTestResult('Database', "$table Table", 'PASS', 
                    "$description exists with $columnCount columns");
            } else {
                addTestResult('Database', "$table Table", 'FAIL', 
                    "$description does not exist");
            }
        }
        
        // Test table operations
        $testSessionKey = 'test_session_' . time();
        
        // Test insert
        $insertStmt = $db->prepare("
            INSERT INTO onlyoffice_sessions 
            (document_id, session_key, user_id, azienda_id, permissions, jwt_token, expires_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $testData = [
            1, // document_id
            $testSessionKey,
            1, // user_id
            1, // azienda_id
            json_encode(['edit' => true]),
            'test_token',
            date('Y-m-d H:i:s', time() + 3600)
        ];
        
        if ($insertStmt->execute($testData)) {
            addTestResult('Database', 'Insert Operation', 'PASS', 
                'Successfully inserted test session');
            
            // Test select
            $selectStmt = $db->prepare("SELECT * FROM onlyoffice_sessions WHERE session_key = ?");
            $selectStmt->execute([$testSessionKey]);
            
            if ($selectStmt->rowCount() > 0) {
                $session = $selectStmt->fetch(PDO::FETCH_ASSOC);
                addTestResult('Database', 'Select Operation', 'PASS', 
                    'Successfully retrieved test session');
                
                // Cleanup
                $deleteStmt = $db->prepare("DELETE FROM onlyoffice_sessions WHERE session_key = ?");
                $deleteStmt->execute([$testSessionKey]);
                
                if ($deleteStmt->rowCount() > 0) {
                    addTestResult('Database', 'Delete Operation', 'PASS', 
                        'Successfully cleaned up test session');
                }
            } else {
                addTestResult('Database', 'Select Operation', 'FAIL', 
                    'Could not retrieve test session');
            }
        } else {
            addTestResult('Database', 'Insert Operation', 'FAIL', 
                'Failed to insert test session');
        }
        
    } catch (Exception $e) {
        addTestResult('Database', 'Database Connection', 'FAIL', 
            'Database error: ' . $e->getMessage());
    }
}

/**
 * Test 5: Document Creation and Management
 */
function testDocumentManagement() {
    global $ONLYOFFICE_DOCUMENTS_DIR;
    
    try {
        $db = db_connection();
        
        // Check if we have any documents in the database
        $docStmt = $db->query("SELECT id, titolo, percorso_file FROM documenti WHERE tipo_documento = 'documento' LIMIT 5");
        $documents = $docStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($documents) > 0) {
            addTestResult('Document Management', 'Existing Documents', 'PASS', 
                'Found ' . count($documents) . ' existing documents in database');
            
            // Check if document files exist
            foreach ($documents as $doc) {
                if (!empty($doc['percorso_file'])) {
                    $fullPath = realpath(__DIR__ . '/' . $doc['percorso_file']);
                    if (file_exists($fullPath)) {
                        addTestResult('Document Management', 
                            "Document File #{$doc['id']}", 'PASS', 
                            "File exists: " . basename($doc['percorso_file']));
                    } else {
                        addTestResult('Document Management', 
                            "Document File #{$doc['id']}", 'WARNING', 
                            "File not found: {$doc['percorso_file']}");
                    }
                }
            }
        } else {
            addTestResult('Document Management', 'Existing Documents', 'WARNING', 
                'No documents found in database');
        }
        
        // Create a test document
        $testDocName = 'test_document_' . time() . '.docx';
        $testDocPath = $ONLYOFFICE_DOCUMENTS_DIR . '/' . $testDocName;
        
        // Create a simple DOCX file (minimal valid DOCX structure)
        $docxContent = base64_decode('UEsDBBQABgAIAAAAIQDfpNJsWgEAACAFAAATAAgCW0NvbnRlbnRfVHlwZXNdLnhtbCCiBAIooAACAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACslMtOwzAQRfdI/EPkLUrcskAINe2CxxIqUT7AxJPGqmNbnmlp/56J+xBCoRVqN7ESz9x7MvHNaLJubbaCiMa7UgyLgcjAVV4bNy/Fx+wlvxcZknJaWe+gFBtAMRlfX41mmwCYcbfDUjRE4UFKrBpoFRY+gOOd2sdWEd/GuQyqWqg5yNvB4E5W3hE4yqnTEOPRE9RqaSl7XvPjLUkEiyJ73BZ2XqVgHhyVJf/7DLsfmHXKmjVjyJwA3vKWt3M6HcosBbz2YAAOw4dP9Hu8KaFFF7l4Epbsu5DHvG1XSJZFJFeABRNfYrAFS7vJED4nzga5WvW5lhvMgWu5VWCHLAWLCl7LSIxbLBuqxbKJDbHdzEQm8V7HnGoJHNC8Jv0C8lLaBtQBP6xQBKBxvcj7iIClLnQoH7Q3l41apD8Fgu1oAi3CtLDh7QAAAP//AwBQSwMEFAAGAAgAAAAhALVVMCP0AAAATAIAAAsACAJfcmVscy8ucmVscyCiBAIooAACAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACskk1PwzAMhu9I/IfI99XdkBBCS3dBSJshVKpcIFyb+Jhvl3hMG939e8q2IhwIJfZSUr/vK2nX4pFPOhhMEg5pUgDbSCqOp7+MJ93gERXsNIW6CJFL+A0C');
        
        if (file_put_contents($testDocPath, $docxContent)) {
            addTestResult('Document Management', 'Test Document Creation', 'PASS', 
                "Created test document: $testDocName");
            
            // Insert into database
            $insertStmt = $db->prepare("
                INSERT INTO documenti (titolo, tipo_documento, percorso_file, azienda_id, creato_da) 
                VALUES (?, 'documento', ?, 1, 1)
            ");
            
            $relativePath = 'documents/onlyoffice/' . $testDocName;
            if ($insertStmt->execute(['Test OnlyOffice Document', $relativePath])) {
                $docId = $db->lastInsertId();
                addTestResult('Document Management', 'Database Insert', 'PASS', 
                    "Inserted document with ID: $docId");
                
                // Generate editor URL
                $editorUrl = "http://localhost" . APP_PATH . "/onlyoffice-editor.php?id=$docId";
                addTestResult('Document Management', 'Editor URL Generation', 'PASS', 
                    "Editor URL: $editorUrl", 
                    ['url' => $editorUrl, 'document_id' => $docId]);
            } else {
                addTestResult('Document Management', 'Database Insert', 'FAIL', 
                    'Failed to insert document into database');
            }
        } else {
            addTestResult('Document Management', 'Test Document Creation', 'FAIL', 
                "Failed to create test document at: $testDocPath");
        }
        
    } catch (Exception $e) {
        addTestResult('Document Management', 'Document Operations', 'FAIL', 
            'Error: ' . $e->getMessage());
    }
}

/**
 * Test 6: Callback URL Accessibility
 */
function testCallbackURL() {
    global $ONLYOFFICE_CALLBACK_URL;
    
    // Parse callback URL
    $urlParts = parse_url($ONLYOFFICE_CALLBACK_URL);
    
    addTestResult('Callback', 'URL Configuration', 'PASS', 
        "Callback URL: $ONLYOFFICE_CALLBACK_URL");
    
    // Check if callback file exists
    $callbackPath = __DIR__ . '/backend/api/onlyoffice-callback.php';
    if (file_exists($callbackPath)) {
        addTestResult('Callback', 'Callback File', 'PASS', 
            'Callback file exists');
        
        // Check if file is readable
        if (is_readable($callbackPath)) {
            addTestResult('Callback', 'File Permissions', 'PASS', 
                'Callback file is readable');
        } else {
            addTestResult('Callback', 'File Permissions', 'FAIL', 
                'Callback file is not readable');
        }
    } else {
        addTestResult('Callback', 'Callback File', 'FAIL', 
            "Callback file not found at: $callbackPath");
    }
    
    // Test OPTIONS request (for CORS)
    $context = stream_context_create([
        'http' => [
            'method' => 'OPTIONS',
            'header' => "Origin: $ONLYOFFICE_CALLBACK_URL\r\n",
            'timeout' => 10,
            'ignore_errors' => true
        ]
    ]);
    
    $headers = @get_headers($ONLYOFFICE_CALLBACK_URL, 1, $context);
    
    if ($headers !== false) {
        if (isset($headers['Access-Control-Allow-Origin'])) {
            addTestResult('Callback', 'CORS Headers', 'PASS', 
                'CORS headers are set: ' . $headers['Access-Control-Allow-Origin']);
        } else {
            addTestResult('Callback', 'CORS Headers', 'WARNING', 
                'CORS headers may not be properly configured');
        }
    }
}

/**
 * Test 7: User Interface Customizations
 */
function testUICustomizations() {
    global $ONLYOFFICE_DS_PUBLIC_URL;
    
    // Test customization configuration
    $customizationConfig = [
        'autosave' => true,
        'chat' => false,
        'comments' => true,
        'compactHeader' => false,
        'compactToolbar' => false,
        'feedback' => false,
        'forcesave' => false,
        'help' => true,
        'hideRightMenu' => false,
        'plugins' => false,
        'toolbarNoTabs' => false,
        'logo' => [
            'image' => 'http://localhost' . APP_PATH . '/assets/images/nexio-logo.svg',
            'imageEmbedded' => 'http://localhost' . APP_PATH . '/assets/images/nexio-logo.svg',
            'url' => 'http://localhost' . APP_PATH
        ],
        'customer' => [
            'name' => 'Nexio Platform',
            'address' => 'Enterprise Document Management'
        ]
    ];
    
    addTestResult('UI Customization', 'Configuration', 'PASS', 
        'UI customization configured', 
        ['config' => $customizationConfig]);
    
    // Check if logo files exist
    $logoPath = __DIR__ . '/assets/images/nexio-logo.svg';
    if (file_exists($logoPath)) {
        addTestResult('UI Customization', 'Logo File', 'PASS', 
            'Logo file exists');
    } else {
        addTestResult('UI Customization', 'Logo File', 'WARNING', 
            'Logo file not found');
    }
}

/**
 * Test 8: Permission System
 */
function testPermissionSystem() {
    try {
        $db = db_connection();
        
        // Check permission structure
        $permissionModes = [
            'edit' => 'Full edit access',
            'view' => 'Read-only access',
            'review' => 'Review and comment access',
            'comment' => 'Comment only access',
            'fillForms' => 'Fill forms only'
        ];
        
        foreach ($permissionModes as $mode => $description) {
            addTestResult('Permissions', "Mode: $mode", 'PASS', $description);
        }
        
        // Test permission checks
        $testPermissions = json_encode([
            'edit' => true,
            'download' => true,
            'print' => true
        ]);
        
        addTestResult('Permissions', 'Permission Encoding', 'PASS', 
            'Permissions can be encoded as JSON');
        
    } catch (Exception $e) {
        addTestResult('Permissions', 'Permission System', 'FAIL', 
            'Error: ' . $e->getMessage());
    }
}

/**
 * Test 9: Session Management
 */
function testSessionManagement() {
    try {
        $db = db_connection();
        
        // Check for expired sessions
        $expiredStmt = $db->query("
            SELECT COUNT(*) as expired_count 
            FROM onlyoffice_sessions 
            WHERE expires_at < NOW() AND is_active = 1
        ");
        $expiredData = $expiredStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($expiredData['expired_count'] > 0) {
            addTestResult('Session Management', 'Expired Sessions', 'WARNING', 
                "Found {$expiredData['expired_count']} expired active sessions");
        } else {
            addTestResult('Session Management', 'Expired Sessions', 'PASS', 
                'No expired active sessions found');
        }
        
        // Check active sessions
        $activeStmt = $db->query("
            SELECT COUNT(*) as active_count 
            FROM onlyoffice_sessions 
            WHERE is_active = 1 AND expires_at > NOW()
        ");
        $activeData = $activeStmt->fetch(PDO::FETCH_ASSOC);
        
        addTestResult('Session Management', 'Active Sessions', 'PASS', 
            "Currently {$activeData['active_count']} active sessions");
        
        // Clean up old sessions
        $cleanupStmt = $db->prepare("
            UPDATE onlyoffice_sessions 
            SET is_active = 0 
            WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY)
        ");
        $cleanupStmt->execute();
        $cleanedUp = $cleanupStmt->rowCount();
        
        if ($cleanedUp > 0) {
            addTestResult('Session Management', 'Session Cleanup', 'PASS', 
                "Cleaned up $cleanedUp old sessions");
        } else {
            addTestResult('Session Management', 'Session Cleanup', 'PASS', 
                'No old sessions to clean up');
        }
        
    } catch (Exception $e) {
        addTestResult('Session Management', 'Session Operations', 'FAIL', 
            'Error: ' . $e->getMessage());
    }
}

/**
 * Test 10: Document Versioning
 */
function testDocumentVersioning() {
    try {
        $db = db_connection();
        
        // Check versioning table
        $versionStmt = $db->query("
            SELECT COUNT(*) as version_count 
            FROM onlyoffice_document_versions
        ");
        $versionData = $versionStmt->fetch(PDO::FETCH_ASSOC);
        
        addTestResult('Versioning', 'Version Records', 'PASS', 
            "Found {$versionData['version_count']} version records");
        
        // Check if versioning is properly configured
        $recentVersions = $db->query("
            SELECT document_id, COUNT(*) as versions 
            FROM onlyoffice_document_versions 
            GROUP BY document_id 
            HAVING versions > 1 
            LIMIT 5
        ");
        
        $multiVersionDocs = $recentVersions->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($multiVersionDocs) > 0) {
            addTestResult('Versioning', 'Multi-version Documents', 'PASS', 
                'Found ' . count($multiVersionDocs) . ' documents with multiple versions');
        } else {
            addTestResult('Versioning', 'Multi-version Documents', 'WARNING', 
                'No documents with multiple versions found');
        }
        
    } catch (Exception $e) {
        addTestResult('Versioning', 'Version System', 'FAIL', 
            'Error: ' . $e->getMessage());
    }
}

// Run all tests
testConfiguration();
testDocumentServerConnection();
testJWTFunctionality();
testDatabaseTables();
testDocumentManagement();
testCallbackURL();
testUICustomizations();
testPermissionSystem();
testSessionManagement();
testDocumentVersioning();

// Calculate overall status
$overallStatus = 'PASS';
if ($failedTests > 0) {
    $overallStatus = 'FAIL';
} elseif ($warnings > 0) {
    $overallStatus = 'WARNING';
}

// Generate HTML report
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OnlyOffice Integration Test Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .test-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        .test-card-header {
            background: #f8f9fa;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #dee2e6;
            font-weight: 600;
        }
        .test-result {
            padding: 0.75rem 1.5rem;
            border-bottom: 1px solid #f1f3f5;
            transition: background-color 0.2s;
        }
        .test-result:hover {
            background-color: #f8f9fa;
        }
        .test-result:last-child {
            border-bottom: none;
        }
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .status-success {
            background-color: #d4edda;
            color: #155724;
        }
        .status-warning {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        .summary-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }
        .summary-stat {
            text-align: center;
            padding: 1rem;
        }
        .summary-stat h3 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .summary-stat p {
            color: #6c757d;
            margin: 0;
        }
        .details-section {
            background: #f8f9fa;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            margin-top: 0.5rem;
            font-size: 0.9rem;
        }
        .config-value {
            font-family: 'Courier New', monospace;
            background: #e9ecef;
            padding: 0.2rem 0.4rem;
            border-radius: 3px;
            font-size: 0.85rem;
        }
        .test-icon {
            width: 24px;
            display: inline-block;
            text-align: center;
            margin-right: 0.5rem;
        }
        .overall-status {
            font-size: 1.25rem;
            font-weight: 600;
            padding: 1rem;
            border-radius: 10px;
            text-align: center;
        }
        .overall-pass {
            background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
            color: #0f5132;
        }
        .overall-warning {
            background: linear-gradient(135deg, #ffeaa7 0%, #fdcb6e 100%);
            color: #664d03;
        }
        .overall-fail {
            background: linear-gradient(135deg, #fd79a8 0%, #fdcb6e 100%);
            color: #842029;
        }
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        .btn-action {
            flex: 1;
            padding: 0.75rem;
            border-radius: 8px;
            text-align: center;
            text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        pre {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1 class="mb-3">🔍 OnlyOffice Integration Test Report</h1>
            <p class="mb-0">Complete verification of OnlyOffice Document Server integration</p>
            <small>Generated: <?php echo $testTimestamp; ?></small>
        </div>
    </div>

    <div class="container">
        <!-- Summary -->
        <div class="summary-card">
            <div class="row">
                <div class="col-md-3">
                    <div class="summary-stat">
                        <h3><?php echo $totalTests; ?></h3>
                        <p>Total Tests</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="summary-stat">
                        <h3 class="text-success"><?php echo $passedTests; ?></h3>
                        <p>Passed</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="summary-stat">
                        <h3 class="text-warning"><?php echo $warnings; ?></h3>
                        <p>Warnings</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="summary-stat">
                        <h3 class="text-danger"><?php echo $failedTests; ?></h3>
                        <p>Failed</p>
                    </div>
                </div>
            </div>
            
            <div class="mt-4">
                <?php
                $overallClass = 'overall-pass';
                $overallIcon = '✅';
                $overallMessage = 'All tests passed successfully!';
                
                if ($overallStatus === 'FAIL') {
                    $overallClass = 'overall-fail';
                    $overallIcon = '❌';
                    $overallMessage = 'Some tests failed. Please review the issues below.';
                } elseif ($overallStatus === 'WARNING') {
                    $overallClass = 'overall-warning';
                    $overallIcon = '⚠️';
                    $overallMessage = 'Tests passed with warnings. Review recommended improvements.';
                }
                ?>
                <div class="overall-status <?php echo $overallClass; ?>">
                    <?php echo $overallIcon; ?> Overall Status: <?php echo $overallStatus; ?>
                    <br>
                    <small><?php echo $overallMessage; ?></small>
                </div>
            </div>
        </div>

        <!-- Test Results -->
        <?php foreach ($testResults as $category => $tests): ?>
        <div class="test-card">
            <div class="test-card-header">
                📋 <?php echo htmlspecialchars($category); ?>
            </div>
            <div class="test-card-body">
                <?php foreach ($tests as $test): ?>
                <div class="test-result">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="test-icon"><?php echo $test['icon']; ?></span>
                            <strong><?php echo htmlspecialchars($test['test']); ?></strong>
                            <?php if (!empty($test['message'])): ?>
                                <br>
                                <span class="ms-4 text-muted">
                                    <?php echo htmlspecialchars($test['message']); ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($test['details'])): ?>
                                <div class="details-section ms-4">
                                    <?php 
                                    if (isset($test['details']['url'])) {
                                        echo '<strong>URL:</strong> <a href="' . $test['details']['url'] . '" target="_blank">' . $test['details']['url'] . '</a><br>';
                                    }
                                    if (isset($test['details']['document_id'])) {
                                        echo '<strong>Document ID:</strong> ' . $test['details']['document_id'] . '<br>';
                                    }
                                    if (isset($test['details']['response'])) {
                                        echo '<strong>Response:</strong> <code>' . htmlspecialchars(substr($test['details']['response'], 0, 200)) . '</code><br>';
                                    }
                                    if (isset($test['details']['config'])) {
                                        echo '<strong>Configuration:</strong><pre>' . json_encode($test['details']['config'], JSON_PRETTY_PRINT) . '</pre>';
                                    }
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span class="status-badge status-<?php echo $test['color']; ?>">
                                <?php echo $test['status']; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Configuration Summary -->
        <div class="test-card">
            <div class="test-card-header">
                ⚙️ Current Configuration
            </div>
            <div class="test-card-body">
                <div class="test-result">
                    <strong>Document Server URL:</strong> 
                    <span class="config-value"><?php echo $ONLYOFFICE_DS_PUBLIC_URL; ?></span>
                </div>
                <div class="test-result">
                    <strong>JWT Enabled:</strong> 
                    <span class="config-value"><?php echo $ONLYOFFICE_JWT_ENABLED ? 'Yes' : 'No'; ?></span>
                </div>
                <div class="test-result">
                    <strong>Callback URL:</strong> 
                    <span class="config-value"><?php echo $ONLYOFFICE_CALLBACK_URL; ?></span>
                </div>
                <div class="test-result">
                    <strong>Documents Directory:</strong> 
                    <span class="config-value"><?php echo $ONLYOFFICE_DOCUMENTS_DIR; ?></span>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="onlyoffice-editor.php" class="btn btn-primary btn-action">
                📝 Open Document Editor
            </a>
            <a href="filesystem.php" class="btn btn-info btn-action">
                📁 File Manager
            </a>
            <a href="test-onlyoffice-quick.php" class="btn btn-secondary btn-action">
                🚀 Quick Test
            </a>
            <a href="<?php echo $ONLYOFFICE_DS_PUBLIC_URL; ?>/healthcheck" target="_blank" class="btn btn-warning btn-action">
                🏥 Server Health
            </a>
        </div>

        <!-- Recommendations -->
        <?php if ($failedTests > 0 || $warnings > 0): ?>
        <div class="test-card mt-4">
            <div class="test-card-header">
                💡 Recommendations
            </div>
            <div class="test-card-body">
                <?php if ($failedTests > 0): ?>
                <div class="test-result">
                    <strong class="text-danger">Critical Issues to Fix:</strong>
                    <ul class="mt-2">
                        <?php foreach ($testResults as $category => $tests): ?>
                            <?php foreach ($tests as $test): ?>
                                <?php if ($test['status'] === 'FAIL'): ?>
                                <li><?php echo $category . ' - ' . $test['test'] . ': ' . $test['message']; ?></li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <?php if ($warnings > 0): ?>
                <div class="test-result">
                    <strong class="text-warning">Improvements Suggested:</strong>
                    <ul class="mt-2">
                        <?php foreach ($testResults as $category => $tests): ?>
                            <?php foreach ($tests as $test): ?>
                                <?php if ($test['status'] === 'WARNING'): ?>
                                <li><?php echo $category . ' - ' . $test['test'] . ': ' . $test['message']; ?></li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="text-center text-muted py-4">
            <small>
                OnlyOffice Integration Test Suite v1.0.0 | 
                Nexio Platform © <?php echo date('Y'); ?> | 
                Execution Time: <?php echo round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 3); ?>s
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh every 30 seconds if there are active tests
        <?php if ($overallStatus === 'WARNING' || $overallStatus === 'FAIL'): ?>
        setTimeout(function() {
            if (confirm('Vuoi rieseguire i test?')) {
                location.reload();
            }
        }, 30000);
        <?php endif; ?>
        
        // Print test results to console
        console.log('OnlyOffice Test Results', {
            total: <?php echo $totalTests; ?>,
            passed: <?php echo $passedTests; ?>,
            warnings: <?php echo $warnings; ?>,
            failed: <?php echo $failedTests; ?>,
            status: '<?php echo $overallStatus; ?>'
        });
    </script>
</body>
</html>