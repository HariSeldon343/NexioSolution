<?php
/**
 * Script per correggere tutti i file di test OnlyOffice
 * Aggiunge l'include della configurazione e usa $ONLYOFFICE_DS_PUBLIC_URL
 */

$files_to_check = [
    'test-onlyoffice-jwt.php',
    'test-onlyoffice-quick.php',
    'test-onlyoffice-integration-check.php',
    'test-onlyoffice-final.php',
    'test-onlyoffice-open.php',
    'test-onlyoffice-complete.php',
    'test-onlyoffice-button-debug.php',
    'test-onlyoffice-button.php',
    'test-onlyoffice-link.php',
    'test-onlyoffice-verification.php',
    'test-onlyoffice-simple.php',
    'test-onlyoffice-cli.php',
    'test-onlyoffice-open-document.php',
    'test-onlyoffice-auth-fix.php',
    'test-onlyoffice-direct.php',
    'test-onlyoffice-sim.php',
    'test-onlyoffice-token.php',
    'test-onlyoffice-debug.php',
    'test-onlyoffice-status.php',
    'test-onlyoffice-integration.php',
    'test-onlyoffice-docker.php',
    'test-onlyoffice-connection.php'
];

$base_path = __DIR__;
$fixed_count = 0;
$already_ok_count = 0;
$error_count = 0;

echo "🔧 Fixing OnlyOffice URLs in test files...\n\n";

foreach ($files_to_check as $file) {
    $file_path = $base_path . '/' . $file;
    
    if (!file_exists($file_path)) {
        echo "⚠️  File not found: $file\n";
        continue;
    }
    
    $content = file_get_contents($file_path);
    $original_content = $content;
    
    // Check if already includes the config
    $has_config = strpos($content, 'backend/config/onlyoffice.config.php') !== false;
    
    if ($has_config) {
        echo "✅ Already configured: $file\n";
        $already_ok_count++;
        continue;
    }
    
    // Patterns to detect hardcoded URLs
    $patterns = [
        '/\$ONLYOFFICE_URL\s*=\s*[\'"]http:\/\/localhost:\d+[\'"];?/' => 'hardcoded_url',
        '/\$onlyofficeUrl\s*=\s*[\'"]http:\/\/localhost:\d+[\'"];?/' => 'hardcoded_url_var',
        '/\$ONLYOFFICE_SERVER\s*=\s*[\'"]http:\/\/localhost:\d+[\'"];?/' => 'hardcoded_server',
        '/\$ONLYOFFICE_DS_URL\s*=\s*[\'"]http:\/\/localhost:\d+[\'"];?/' => 'hardcoded_ds_url'
    ];
    
    $found_hardcoded = false;
    foreach ($patterns as $pattern => $type) {
        if (preg_match($pattern, $content)) {
            $found_hardcoded = true;
            echo "🔍 Found $type in $file\n";
            break;
        }
    }
    
    if (!$found_hardcoded) {
        echo "ℹ️  No hardcoded URL found in: $file\n";
        continue;
    }
    
    // Find the right place to add the include
    // After <?php tag and any initial comments
    $include_added = false;
    
    // Pattern to find the opening PHP tag and any initial comments
    if (preg_match('/(<\?php\s*(?:\/\*\*.*?\*\/\s*)?)/s', $content, $matches, PREG_OFFSET_CAPTURE)) {
        $insert_pos = $matches[0][1] + strlen($matches[0][0]);
        
        // Add the include statement
        $include_statement = "\n// Include OnlyOffice configuration\nrequire_once __DIR__ . '/backend/config/onlyoffice.config.php';\n";
        
        $content = substr($content, 0, $insert_pos) . $include_statement . substr($content, $insert_pos);
        $include_added = true;
    }
    
    if ($include_added) {
        // Replace hardcoded URLs with configuration variable
        $content = preg_replace(
            '/\$ONLYOFFICE_URL\s*=\s*[\'"]http:\/\/localhost:\d+[\'"];?/',
            '$ONLYOFFICE_URL = $ONLYOFFICE_DS_PUBLIC_URL;',
            $content
        );
        
        $content = preg_replace(
            '/\$onlyofficeUrl\s*=\s*[\'"]http:\/\/localhost:\d+[\'"];?/',
            '$onlyofficeUrl = $ONLYOFFICE_DS_PUBLIC_URL;',
            $content
        );
        
        $content = preg_replace(
            '/\$ONLYOFFICE_SERVER\s*=\s*[\'"]http:\/\/localhost:\d+[\'"];?/',
            '$ONLYOFFICE_SERVER = $ONLYOFFICE_DS_PUBLIC_URL;',
            $content
        );
        
        $content = preg_replace(
            '/\$ONLYOFFICE_DS_URL\s*=\s*[\'"]http:\/\/localhost:\d+[\'"];?/',
            '$ONLYOFFICE_DS_URL = $ONLYOFFICE_DS_PUBLIC_URL;',
            $content
        );
        
        // Save the file
        if (file_put_contents($file_path, $content)) {
            echo "✨ Fixed: $file\n";
            $fixed_count++;
        } else {
            echo "❌ Error saving: $file\n";
            $error_count++;
        }
    } else {
        echo "⚠️  Could not add include to: $file\n";
        $error_count++;
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "📊 Summary:\n";
echo "  ✨ Fixed: $fixed_count files\n";
echo "  ✅ Already OK: $already_ok_count files\n";
echo "  ❌ Errors: $error_count files\n";
echo str_repeat("=", 50) . "\n";

// Now verify OnlyOffice is accessible
echo "\n🔍 Verifying OnlyOffice accessibility...\n";

require_once __DIR__ . '/backend/config/onlyoffice.config.php';

$api_url = $ONLYOFFICE_DS_PUBLIC_URL . '/web-apps/apps/api/documents/api.js';
echo "Testing: $api_url\n";

$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code == 200) {
    echo "✅ OnlyOffice API is accessible at $ONLYOFFICE_DS_PUBLIC_URL\n";
} else {
    echo "❌ OnlyOffice API not accessible (HTTP $http_code)\n";
    echo "   Make sure OnlyOffice Docker container is running on port 8082\n";
}
?>