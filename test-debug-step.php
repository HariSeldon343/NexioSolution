<?php
/**
 * Debug step by step
 */

echo "Starting debug...\n";

// Step 1: Basic includes
echo "Step 1: Including config...\n";
require_once 'backend/config/config.php';
echo "✓ Config loaded\n";

// Step 2: OnlyOffice config
echo "Step 2: Including OnlyOffice config...\n";
require_once 'backend/config/onlyoffice.config.php';
echo "✓ OnlyOffice config loaded\n";

// Step 3: Test function existence
echo "Step 3: Testing functions...\n";
if (function_exists('generateOnlyOfficeJWT')) {
    echo "✓ generateOnlyOfficeJWT exists\n";
} else {
    echo "❌ generateOnlyOfficeJWT NOT FOUND\n";
}

if (function_exists('getOnlyOfficeServerStatus')) {
    echo "✓ getOnlyOfficeServerStatus exists\n";
} else {
    echo "❌ getOnlyOfficeServerStatus NOT FOUND\n";
}

// Step 4: Test variable existence
echo "Step 4: Testing variables...\n";
if (isset($ONLYOFFICE_DOCUMENT_TYPES)) {
    echo "✓ ONLYOFFICE_DOCUMENT_TYPES exists\n";
    echo "  Contains " . count($ONLYOFFICE_DOCUMENT_TYPES) . " types\n";
} else {
    echo "❌ ONLYOFFICE_DOCUMENT_TYPES NOT FOUND\n";
}

// Step 5: Try to call a simple function
echo "Step 5: Testing simple JWT generation...\n";
try {
    $testPayload = ['test' => 'data'];
    $token = generateOnlyOfficeJWT($testPayload);
    echo "✓ JWT generated: " . substr($token, 0, 50) . "...\n";
} catch (Exception $e) {
    echo "❌ Error generating JWT: " . $e->getMessage() . "\n";
}

// Step 6: Test Auth
echo "Step 6: Including Auth...\n";
try {
    require_once 'backend/middleware/Auth.php';
    echo "✓ Auth loaded\n";
    
    // Initialize session
    session_start();
    $_SESSION['user_id'] = 1;
    
    $auth = Auth::getInstance();
    echo "✓ Auth instance created\n";
} catch (Exception $e) {
    echo "❌ Auth error: " . $e->getMessage() . "\n";
}

echo "\nDebug complete!\n";
?>