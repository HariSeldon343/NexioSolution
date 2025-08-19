<?php
/**
 * Test token generation directly
 */

// Initialize environment
error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();
$_SESSION['user_id'] = 1;
$_SESSION['csrf_token'] = 'test';
$_SESSION['azienda_id'] = 1;

// Set action
$_GET['action'] = 'generate_token';
$_GET['document_id'] = '1';

// Capture any output
ob_start();
$errorOccurred = false;
$errorMessage = '';

// Set error handler to catch fatal errors
set_error_handler(function($severity, $message, $file, $line) use (&$errorOccurred, &$errorMessage) {
    $errorOccurred = true;
    $errorMessage = "Error: $message in $file:$line";
    return true;
});

// Set exception handler
set_exception_handler(function($exception) use (&$errorOccurred, &$errorMessage) {
    $errorOccurred = true;
    $errorMessage = "Exception: " . $exception->getMessage();
});

try {
    // Include the API file
    require_once 'backend/api/onlyoffice-auth.php';
} catch (Exception $e) {
    $errorOccurred = true;
    $errorMessage = "Caught exception: " . $e->getMessage();
} catch (Error $e) {
    $errorOccurred = true;
    $errorMessage = "Fatal error: " . $e->getMessage();
}

$output = ob_get_clean();

// Restore error handler
restore_error_handler();
restore_exception_handler();

echo "=== Token Generation Test ===\n\n";

if ($errorOccurred) {
    echo "❌ ERROR OCCURRED:\n";
    echo $errorMessage . "\n\n";
}

if (!empty($output)) {
    echo "Output received (" . strlen($output) . " bytes):\n";
    echo $output . "\n\n";
    
    // Try to decode as JSON
    $json = json_decode($output, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✓ Valid JSON response!\n";
        echo "Structure:\n";
        print_r($json);
    } else {
        echo "❌ Invalid JSON: " . json_last_error_msg() . "\n";
        
        // Check for common errors
        if (strpos($output, 'Fatal error') !== false) {
            echo "❌ FATAL ERROR in output\n";
        }
        if (strpos($output, 'Warning') !== false) {
            echo "⚠ WARNING in output\n";
        }
        if (strpos($output, 'Notice') !== false) {
            echo "ℹ NOTICE in output\n";
        }
    }
} else {
    echo "⚠ No output received\n";
}

// Check PHP error log
$lastError = error_get_last();
if ($lastError && $lastError['type'] === E_ERROR) {
    echo "\n❌ FATAL ERROR in error_get_last():\n";
    print_r($lastError);
}
?>