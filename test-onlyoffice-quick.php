<?php
/**
 * Quick OnlyOffice Configuration Test
 * Verifica rapida che costanti e variabili siano definite correttamente
 */

// Include configuration files
require_once 'backend/config/config.php';
require_once 'backend/config/onlyoffice.config.php';

// Test results
$results = [];

// Test 1: Check if constants are defined
$constants_to_check = [
    'ONLYOFFICE_JWT_ENABLED',
    'ONLYOFFICE_JWT_SECRET',
    'ONLYOFFICE_DS_PUBLIC_URL',
    'ONLYOFFICE_DS_INTERNAL_URL',
    'ONLYOFFICE_CALLBACK_URL'
];

echo "<!DOCTYPE html>\n<html>\n<head>\n<title>OnlyOffice Quick Test</title>\n";
echo "<style>body{font-family:sans-serif;margin:20px;}.ok{color:green;}.err{color:red;}.warn{color:orange;}</style>\n</head>\n<body>\n";
echo "<h1>OnlyOffice Configuration Quick Test</h1>\n";

echo "<h2>1. Constants Check</h2>\n<ul>\n";
foreach ($constants_to_check as $const) {
    if (defined($const)) {
        $value = constant($const);
        $display_value = is_bool($value) ? ($value ? 'true' : 'false') : substr($value, 0, 50);
        echo "<li class='ok'>✅ $const is defined: <code>$display_value</code></li>\n";
    } else {
        echo "<li class='err'>❌ $const is NOT defined</li>\n";
    }
}
echo "</ul>\n";

// Test 2: Check if variables exist
echo "<h2>2. Variables Check</h2>\n<ul>\n";
$variables_to_check = [
    'ONLYOFFICE_JWT_ENABLED',
    'ONLYOFFICE_JWT_SECRET',
    'ONLYOFFICE_DS_PUBLIC_URL',
    'ONLYOFFICE_DS_INTERNAL_URL',
    'ONLYOFFICE_CALLBACK_URL'
];

foreach ($variables_to_check as $var) {
    if (isset($GLOBALS[$var])) {
        $value = $GLOBALS[$var];
        $display_value = is_bool($value) ? ($value ? 'true' : 'false') : substr($value, 0, 50);
        echo "<li class='ok'>✅ \$$var exists: <code>$display_value</code></li>\n";
    } else {
        echo "<li class='warn'>⚠️ \$$var does not exist as global</li>\n";
    }
}
echo "</ul>\n";

// Test 3: Check if functions exist
echo "<h2>3. Functions Check</h2>\n<ul>\n";
$functions_to_check = ['generateJWT', 'verifyJWT', 'validateOnlyOfficeConfig'];

foreach ($functions_to_check as $func) {
    if (function_exists($func)) {
        echo "<li class='ok'>✅ Function $func() exists</li>\n";
    } else {
        echo "<li class='err'>❌ Function $func() NOT found</li>\n";
    }
}
echo "</ul>\n";

// Test 4: JWT Token Test
echo "<h2>4. JWT Token Generation Test</h2>\n";
if (function_exists('generateJWT')) {
    try {
        $test_payload = [
            'document' => ['key' => 'test123'],
            'editorConfig' => ['user' => ['id' => '1', 'name' => 'Test']],
            'iat' => time()
        ];
        $token = generateJWT($test_payload);
        if ($token) {
            echo "<p class='ok'>✅ JWT token generated successfully</p>\n";
            echo "<p>Token preview: <code>" . substr($token, 0, 40) . "...</code></p>\n";
            
            // Try to verify
            if (function_exists('verifyJWT')) {
                $verified = verifyJWT($token);
                if ($verified) {
                    echo "<p class='ok'>✅ JWT token verified successfully</p>\n";
                } else {
                    echo "<p class='err'>❌ JWT token verification failed</p>\n";
                }
            }
        } else {
            echo "<p class='err'>❌ Failed to generate JWT token</p>\n";
        }
    } catch (Exception $e) {
        echo "<p class='err'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    }
} else {
    echo "<p class='err'>❌ generateJWT function not found</p>\n";
}

// Test 5: Configuration Summary
echo "<h2>5. Configuration Summary</h2>\n";
echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>\n";
echo "<tr><th>Setting</th><th>Value</th><th>Status</th></tr>\n";

// JWT Status
$jwt_enabled = defined('ONLYOFFICE_JWT_ENABLED') ? ONLYOFFICE_JWT_ENABLED : false;
echo "<tr><td>JWT Enabled</td><td>" . ($jwt_enabled ? 'Yes' : 'No') . "</td>";
echo "<td class='" . ($jwt_enabled ? 'ok' : 'warn') . "'>" . ($jwt_enabled ? '✅ Enabled' : '⚠️ Disabled (OK for dev)') . "</td></tr>\n";

// Server URL
$server_url = defined('ONLYOFFICE_DS_PUBLIC_URL') ? ONLYOFFICE_DS_PUBLIC_URL : 'Not set';
$is_https = strpos($server_url, 'https://') === 0;
echo "<tr><td>Server URL</td><td><code>" . htmlspecialchars($server_url) . "</code></td>";
echo "<td class='" . ($is_https ? 'ok' : 'warn') . "'>" . ($is_https ? '✅ HTTPS' : '⚠️ HTTP (OK for dev)') . "</td></tr>\n";

// JWT Secret
if (defined('ONLYOFFICE_JWT_SECRET')) {
    $secret_len = strlen(ONLYOFFICE_JWT_SECRET);
    echo "<tr><td>JWT Secret</td><td>Configured (" . $secret_len . " chars)</td>";
    echo "<td class='" . ($secret_len >= 32 ? 'ok' : 'warn') . "'>" . ($secret_len >= 32 ? '✅ Secure' : '⚠️ Too short') . "</td></tr>\n";
} else {
    echo "<tr><td>JWT Secret</td><td>Not configured</td><td class='err'>❌ Missing</td></tr>\n";
}

echo "</table>\n";

// Summary
echo "<h2>Summary</h2>\n";
$all_ok = defined('ONLYOFFICE_JWT_ENABLED') && 
          defined('ONLYOFFICE_JWT_SECRET') && 
          defined('ONLYOFFICE_DS_PUBLIC_URL') &&
          function_exists('generateJWT');

if ($all_ok) {
    echo "<div style='padding:10px;background:#d4edda;color:#155724;border:1px solid #c3e6cb;border-radius:5px;'>\n";
    echo "<strong>✅ Basic configuration is working!</strong><br>\n";
    echo "Constants and functions are properly defined. ";
    if (!$jwt_enabled) {
        echo "JWT is disabled (OK for development). Enable it for production.";
    }
    echo "</div>\n";
} else {
    echo "<div style='padding:10px;background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;border-radius:5px;'>\n";
    echo "<strong>❌ Configuration issues detected!</strong><br>\n";
    echo "Please check the configuration file and ensure all constants are defined.\n";
    echo "</div>\n";
}

echo "\n<hr>\n";
echo "<p><a href='test-onlyoffice-jwt.php'>Run Full Test →</a></p>\n";
echo "</body>\n</html>";
?>