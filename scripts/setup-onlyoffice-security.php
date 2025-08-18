<?php
/**
 * OnlyOffice Security Setup and Testing Script
 * Run this script to verify and configure OnlyOffice security settings
 */

require_once __DIR__ . '/../backend/config/config.php';
require_once __DIR__ . '/../backend/config/onlyoffice.config.php';

// Colors for console output
$colors = [
    'reset' => "\033[0m",
    'red' => "\033[31m",
    'green' => "\033[32m",
    'yellow' => "\033[33m",
    'blue' => "\033[34m",
    'cyan' => "\033[36m"
];

function printHeader($text) {
    global $colors;
    echo "\n{$colors['cyan']}========================================{$colors['reset']}\n";
    echo "{$colors['cyan']}$text{$colors['reset']}\n";
    echo "{$colors['cyan']}========================================{$colors['reset']}\n\n";
}

function printSuccess($text) {
    global $colors;
    echo "{$colors['green']}✓ $text{$colors['reset']}\n";
}

function printWarning($text) {
    global $colors;
    echo "{$colors['yellow']}⚠ $text{$colors['reset']}\n";
}

function printError($text) {
    global $colors;
    echo "{$colors['red']}✗ $text{$colors['reset']}\n";
}

function printInfo($text) {
    global $colors;
    echo "{$colors['blue']}ℹ $text{$colors['reset']}\n";
}

// Start setup
printHeader("OnlyOffice Security Configuration Check");

// 1. Check environment
printInfo("Checking environment...");
$isProduction = (getenv('APP_ENV') === 'production') || 
                (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] !== 'localhost');

if ($isProduction) {
    printWarning("Running in PRODUCTION mode - strict security enforced");
} else {
    printInfo("Running in DEVELOPMENT mode");
}

// 2. Check JWT configuration
printHeader("JWT Security Configuration");

if ($ONLYOFFICE_JWT_ENABLED) {
    printSuccess("JWT authentication is ENABLED");
    
    if (!empty($ONLYOFFICE_JWT_SECRET)) {
        $secretLength = strlen($ONLYOFFICE_JWT_SECRET);
        if ($secretLength < 32) {
            printWarning("JWT secret key is short ($secretLength chars). Recommend at least 32 characters.");
        } else {
            printSuccess("JWT secret key is configured (length: $secretLength)");
        }
        
        // Test JWT generation
        try {
            $testPayload = ['test' => 'data', 'iat' => time()];
            $token = generateOnlyOfficeJWT($testPayload);
            if (!empty($token)) {
                printSuccess("JWT token generation working");
                
                // Test verification
                $result = verifyOnlyOfficeJWT($token);
                if ($result['valid']) {
                    printSuccess("JWT token verification working");
                } else {
                    printError("JWT token verification failed: " . $result['error']);
                }
            } else {
                printError("JWT token generation failed");
            }
        } catch (Exception $e) {
            printError("JWT test failed: " . $e->getMessage());
        }
    } else {
        printError("JWT secret key is NOT configured!");
        if ($isProduction) {
            die("\n{$colors['red']}CRITICAL: JWT secret must be set in production!{$colors['reset']}\n\n");
        }
    }
    
    printInfo("JWT Algorithm: $ONLYOFFICE_JWT_ALGORITHM");
    printInfo("JWT Header: $ONLYOFFICE_JWT_HEADER");
} else {
    if ($isProduction) {
        printError("JWT authentication is DISABLED in production!");
        printWarning("This is a serious security risk!");
    } else {
        printWarning("JWT authentication is DISABLED (acceptable for development)");
    }
}

// 3. Check server configuration
printHeader("Server Configuration");

printInfo("Public URL: $ONLYOFFICE_DS_PUBLIC_URL");
printInfo("Internal URL: $ONLYOFFICE_DS_INTERNAL_URL");
printInfo("Callback URL: $ONLYOFFICE_CALLBACK_URL");

// Test server connectivity
printInfo("Testing OnlyOffice server connectivity...");
if (getOnlyOfficeServerStatus()) {
    printSuccess("OnlyOffice server is reachable");
} else {
    printWarning("OnlyOffice server is not reachable (may be offline or misconfigured)");
}

// 4. Check security settings
printHeader("Security Settings");

// Rate limiting
if ($ONLYOFFICE_RATE_LIMIT > 0) {
    printSuccess("Rate limiting enabled: $ONLYOFFICE_RATE_LIMIT requests/minute");
} else {
    printWarning("Rate limiting is disabled");
}

// IP restrictions
if (!empty($ONLYOFFICE_ALLOWED_IPS)) {
    printSuccess("IP whitelist configured: " . implode(', ', $ONLYOFFICE_ALLOWED_IPS));
} else {
    printInfo("No IP restrictions (accepting callbacks from any IP)");
}

// CORS
if (in_array('*', $ONLYOFFICE_CORS_ORIGINS)) {
    printWarning("CORS allows all origins (consider restricting in production)");
} else {
    printSuccess("CORS restricted to: " . implode(', ', $ONLYOFFICE_CORS_ORIGINS));
}

// 5. Check file system
printHeader("File System Configuration");

// Documents directory
if (is_dir($ONLYOFFICE_DOCUMENTS_DIR)) {
    printSuccess("Documents directory exists: $ONLYOFFICE_DOCUMENTS_DIR");
    
    if (is_writable($ONLYOFFICE_DOCUMENTS_DIR)) {
        printSuccess("Documents directory is writable");
    } else {
        printError("Documents directory is NOT writable");
    }
} else {
    printWarning("Documents directory does not exist: $ONLYOFFICE_DOCUMENTS_DIR");
    printInfo("Attempting to create directory...");
    
    if (@mkdir($ONLYOFFICE_DOCUMENTS_DIR, 0755, true)) {
        printSuccess("Directory created successfully");
    } else {
        printError("Failed to create directory");
    }
}

// Log file
if ($ONLYOFFICE_DEBUG) {
    printInfo("Debug logging is ENABLED");
    printInfo("Log file: $ONLYOFFICE_LOG_FILE");
    
    $logDir = dirname($ONLYOFFICE_LOG_FILE);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    if (is_writable($logDir)) {
        printSuccess("Log directory is writable");
    } else {
        printWarning("Log directory is not writable");
    }
} else {
    printInfo("Debug logging is DISABLED");
}

// 6. Check database tables
printHeader("Database Configuration");

try {
    $db = db_connection();
    
    // Check for required tables
    $requiredTables = [
        'documenti_versioni_extended' => 'Document versioning',
        'document_active_editors' => 'Active editor tracking',
        'document_collaborative_actions' => 'Collaborative action logging',
        'document_activity_log' => 'Activity logging',
        'onlyoffice_sessions' => 'Session management',
        'onlyoffice_rate_limits' => 'Rate limiting',
        'onlyoffice_security_log' => 'Security audit logging'
    ];
    
    foreach ($requiredTables as $table => $description) {
        $stmt = $db->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        
        if ($stmt->rowCount() > 0) {
            printSuccess("Table '$table' exists ($description)");
        } else {
            printWarning("Table '$table' missing - run database migration");
        }
    }
    
    // Check documenti table columns
    $stmt = $db->prepare("SHOW COLUMNS FROM documenti");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $requiredColumns = [
        'is_editing', 'editing_users', 'editing_started_at',
        'current_version', 'total_versions', 'last_error', 'last_error_at'
    ];
    
    $missingColumns = array_diff($requiredColumns, $columns);
    if (empty($missingColumns)) {
        printSuccess("All OnlyOffice columns exist in documenti table");
    } else {
        printWarning("Missing columns in documenti: " . implode(', ', $missingColumns));
    }
    
} catch (Exception $e) {
    printError("Database check failed: " . $e->getMessage());
}

// 7. Generate configuration report
printHeader("Configuration Summary");

$configStatus = [
    'Environment' => $isProduction ? 'PRODUCTION' : 'DEVELOPMENT',
    'JWT Enabled' => $ONLYOFFICE_JWT_ENABLED ? 'Yes' : 'No',
    'JWT Secret Set' => !empty($ONLYOFFICE_JWT_SECRET) ? 'Yes' : 'No',
    'Rate Limiting' => $ONLYOFFICE_RATE_LIMIT > 0 ? "$ONLYOFFICE_RATE_LIMIT/min" : 'Disabled',
    'IP Whitelist' => !empty($ONLYOFFICE_ALLOWED_IPS) ? 'Configured' : 'Open',
    'Debug Mode' => $ONLYOFFICE_DEBUG ? 'Enabled' : 'Disabled',
    'Max File Size' => number_format($ONLYOFFICE_MAX_FILE_SIZE / 1048576, 0) . ' MB',
    'Session Timeout' => number_format($ONLYOFFICE_SESSION_TIMEOUT / 3600, 1) . ' hours'
];

foreach ($configStatus as $key => $value) {
    echo sprintf("%-20s: %s\n", $key, $value);
}

// 8. Security recommendations
printHeader("Security Recommendations");

$recommendations = [];

if (!$ONLYOFFICE_JWT_ENABLED && $isProduction) {
    $recommendations[] = "Enable JWT authentication immediately!";
}

if (empty($ONLYOFFICE_JWT_SECRET) && $ONLYOFFICE_JWT_ENABLED) {
    $recommendations[] = "Set a strong JWT secret key (use: openssl rand -hex 32)";
}

if ($ONLYOFFICE_RATE_LIMIT == 0) {
    $recommendations[] = "Enable rate limiting to prevent abuse";
}

if (empty($ONLYOFFICE_ALLOWED_IPS) && $isProduction) {
    $recommendations[] = "Consider restricting callback IPs to OnlyOffice server";
}

if (in_array('*', $ONLYOFFICE_CORS_ORIGINS) && $isProduction) {
    $recommendations[] = "Restrict CORS origins to specific domains";
}

if ($ONLYOFFICE_DEBUG && $isProduction) {
    $recommendations[] = "Disable debug mode in production";
}

if (strpos($ONLYOFFICE_DS_PUBLIC_URL, 'http://') === 0 && $isProduction) {
    $recommendations[] = "Use HTTPS for OnlyOffice server URL";
}

if (strpos($ONLYOFFICE_CALLBACK_URL, 'http://') === 0 && $isProduction) {
    $recommendations[] = "Use HTTPS for callback URL";
}

if (empty($recommendations)) {
    printSuccess("No critical security issues found!");
} else {
    foreach ($recommendations as $i => $rec) {
        echo ($i + 1) . ". $rec\n";
    }
}

// 9. Generate .env file if needed
if (!file_exists(__DIR__ . '/../.env.onlyoffice')) {
    printHeader("Environment File Setup");
    printWarning(".env.onlyoffice file not found");
    printInfo("Copy .env.onlyoffice.example to .env.onlyoffice and configure it");
    
    echo "\nGenerate a secure JWT secret with:\n";
    echo "{$colors['cyan']}openssl rand -hex 32{$colors['reset']}\n";
}

// 10. Test callback endpoint
printHeader("Callback Endpoint Test");

printInfo("Testing callback endpoint security...");

// Simulate a callback request
$callbackUrl = 'http://localhost' . str_replace($_SERVER['DOCUMENT_ROOT'], '', __DIR__) . '/../backend/api/onlyoffice-callback.php';

$testData = [
    'status' => 0,
    'key' => 'test_' . time()
];

if ($ONLYOFFICE_JWT_ENABLED) {
    $token = generateOnlyOfficeJWT($testData);
    $testData['token'] = $token;
}

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => [
            'Content-Type: application/json',
            $ONLYOFFICE_JWT_ENABLED ? "$ONLYOFFICE_JWT_HEADER: Bearer $token" : ''
        ],
        'content' => json_encode($testData),
        'timeout' => 5
    ]
]);

$response = @file_get_contents($callbackUrl, false, $context);

if ($response !== false) {
    $result = json_decode($response, true);
    if (isset($result['error']) && $result['error'] === 0) {
        printSuccess("Callback endpoint is working correctly");
    } else {
        printWarning("Callback endpoint returned an error");
    }
} else {
    printInfo("Could not test callback endpoint (may be normal in CLI environment)");
}

// Final status
printHeader("Setup Complete");

$criticalIssues = 0;
if ($isProduction) {
    if (!$ONLYOFFICE_JWT_ENABLED) $criticalIssues++;
    if (empty($ONLYOFFICE_JWT_SECRET)) $criticalIssues++;
    if (strpos($ONLYOFFICE_DS_PUBLIC_URL, 'http://') === 0) $criticalIssues++;
}

if ($criticalIssues > 0) {
    printError("Found $criticalIssues critical security issues that must be fixed!");
    echo "\n{$colors['red']}⚠️  DO NOT USE IN PRODUCTION UNTIL FIXED! ⚠️{$colors['reset']}\n";
} else {
    printSuccess("OnlyOffice security configuration is ready!");
    echo "\n{$colors['green']}✅ System is configured securely{$colors['reset']}\n";
}

echo "\nNext steps:\n";
echo "1. Run database migration: /mnt/c/xampp/mysql/bin/mysql.exe -u root nexiosol < database/create_onlyoffice_tables.sql\n";
echo "2. Configure .env.onlyoffice with production settings\n";
echo "3. Set up SSL certificates for HTTPS\n";
echo "4. Configure firewall rules for OnlyOffice server\n";
echo "5. Test document editing functionality\n";

echo "\n";
?>