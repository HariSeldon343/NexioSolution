<?php
/**
 * Simulated test without HTTP request
 */

// Disable all errors for clean output
error_reporting(0);
ini_set('display_errors', '0');

// Simulate environment
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/piattaforma-collaborativa/backend/api/onlyoffice-auth.php';
$_SERVER['SCRIPT_NAME'] = '/piattaforma-collaborativa/backend/api/onlyoffice-auth.php';
$_SERVER['PHP_SELF'] = '/piattaforma-collaborativa/backend/api/onlyoffice-auth.php';
$_SERVER['HTTP_ACCEPT'] = 'application/json';

// Initialize session
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'test';
$_SESSION['csrf_token'] = 'test-token';
$_SESSION['azienda_id'] = 1;

// Set action - test with existing document
$_GET['action'] = 'generate_token';
$_GET['document_id'] = '22'; // Document that exists

// Capture output
ob_start();

// Include the API directly
include 'backend/api/onlyoffice-auth.php';

$output = ob_get_contents();
ob_end_clean();

// Re-enable error reporting for test output
error_reporting(E_ALL);

echo "=== Simulated OnlyOffice Auth Test ===\n\n";

if (empty($output)) {
    echo "❌ No output received\n";
} else {
    echo "✓ Output received: " . strlen($output) . " bytes\n\n";
    
    // Parse JSON
    $json = json_decode($output, true);
    
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✅ VALID JSON!\n";
        echo "================\n";
        
        if (isset($json['success'])) {
            if ($json['success'] === true) {
                echo "✅ Success: true\n";
                
                if (isset($json['token'])) {
                    echo "✅ Token: " . substr($json['token'], 0, 40) . "...\n";
                }
                if (isset($json['server_available'])) {
                    echo "ℹ️ Server available: " . ($json['server_available'] ? 'Yes' : 'No') . "\n";
                }
            } else {
                echo "⚠️ Success: false\n";
                if (isset($json['error'])) {
                    echo "Error: " . $json['error'] . "\n";
                }
            }
        }
        
        echo "\nFull response:\n";
        print_r($json);
        
    } else {
        echo "❌ INVALID JSON: " . json_last_error_msg() . "\n";
        echo "\nRaw output:\n";
        echo $output . "\n";
        
        // Check for errors
        if (strpos($output, 'Fatal error') !== false) {
            echo "\n❌ FATAL ERROR DETECTED!\n";
        }
    }
}

echo "\n=== Test Complete ===\n";
?>