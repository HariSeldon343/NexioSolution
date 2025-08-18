<?php
/**
 * CLI Test for OnlyOffice Integration
 * Run: php test-onlyoffice-cli.php
 */

// Load configuration
require_once 'backend/config/config.php';
require_once 'backend/config/onlyoffice.config.php';

echo "\n=== OnlyOffice CLI Test ===\n\n";

// 1. Test configuration
echo "1. Configuration:\n";
echo "   - Server URL: " . $ONLYOFFICE_DS_PUBLIC_URL . "\n";
echo "   - JWT Enabled: " . (ONLYOFFICE_JWT_ENABLED ? 'Yes' : 'No') . "\n";
echo "   - JWT Secret: " . (strlen(ONLYOFFICE_JWT_SECRET) > 0 ? 'Set (' . strlen(ONLYOFFICE_JWT_SECRET) . ' chars)' : 'Not set') . "\n\n";

// 2. Test server connectivity
echo "2. Server Connectivity:\n";
$ch = curl_init($ONLYOFFICE_DS_PUBLIC_URL . '/healthcheck');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($httpCode === 200) {
    echo "   ✓ Server is healthy (HTTP $httpCode)\n";
    echo "   Response: $response\n";
} else {
    echo "   ✗ Server error (HTTP $httpCode)\n";
    if ($error) echo "   Error: $error\n";
}
echo "\n";

// 3. Test with a sample document
echo "3. Document Test:\n";

// Find a test document
$testFile = null;
$docPath = __DIR__ . '/documents/onlyoffice/';
if (file_exists($docPath)) {
    $files = glob($docPath . '*.docx');
    if (!empty($files)) {
        $testFile = basename($files[0]);
        echo "   Found test file: $testFile\n";
    }
}

if ($testFile) {
    // Generate document configuration
    $documentKey = 'test_' . time() . '_' . rand(1000, 9999);
    $documentUrl = 'http://host.docker.internal/piattaforma-collaborativa/documents/onlyoffice/' . $testFile;
    
    echo "   Document URL: $documentUrl\n";
    echo "   Document Key: $documentKey\n\n";
    
    // 4. Generate JWT token
    echo "4. JWT Token:\n";
    
    $config = [
        'document' => [
            'fileType' => 'docx',
            'key' => $documentKey,
            'title' => $testFile,
            'url' => $documentUrl,
            'permissions' => [
                'edit' => true,
                'download' => true,
                'print' => true
            ]
        ],
        'editorConfig' => [
            'mode' => 'edit',
            'lang' => 'it',
            'user' => [
                'id' => 'test_user',
                'name' => 'Test User'
            ],
            'customization' => [
                'forcesave' => true,
                'autosave' => true
            ]
        ]
    ];
    
    // Generate JWT
    function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
    $payload = json_encode($config);
    
    $base64Header = base64url_encode($header);
    $base64Payload = base64url_encode($payload);
    
    $signature = base64url_encode(
        hash_hmac('sha256', $base64Header . '.' . $base64Payload, ONLYOFFICE_JWT_SECRET, true)
    );
    
    $token = $base64Header . '.' . $base64Payload . '.' . $signature;
    
    echo "   Token generated (length: " . strlen($token) . ")\n";
    echo "   First 50 chars: " . substr($token, 0, 50) . "...\n\n";
    
    // 5. Test API endpoint
    echo "5. API Test:\n";
    
    $apiUrl = $ONLYOFFICE_DS_PUBLIC_URL . '/coauthoring/CommandService.ashx';
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'c' => 'info',
        'key' => $documentKey
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "   API Response (HTTP $httpCode): $response\n\n";
    
    // 6. Generate editor HTML
    echo "6. Editor HTML:\n";
    echo "   To open the document in OnlyOffice, create an HTML page with:\n\n";
    
    $editorHtml = <<<HTML
<div id="onlyoffice-editor" style="width:100%; height:600px;"></div>
<script src="{$ONLYOFFICE_DS_PUBLIC_URL}/web-apps/apps/api/documents/api.js"></script>
<script>
    var config = {$payload};
    config.token = "{$token}";
    config.type = "desktop";
    var docEditor = new DocsAPI.DocEditor("onlyoffice-editor", config);
</script>
HTML;
    
    echo $editorHtml . "\n\n";
    
    // Save test HTML
    $testHtmlFile = __DIR__ . '/test-onlyoffice-generated.html';
    $fullHtml = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>OnlyOffice Test - {$testFile}</title>
    <meta charset="UTF-8">
    <style>
        body { margin: 0; padding: 20px; font-family: Arial, sans-serif; }
        h1 { color: #333; }
        #onlyoffice-editor { border: 1px solid #ddd; }
    </style>
</head>
<body>
    <h1>OnlyOffice Editor Test</h1>
    <p>Document: {$testFile}</p>
    <div id="onlyoffice-editor" style="width:100%; height:600px;"></div>
    <script src="{$ONLYOFFICE_DS_PUBLIC_URL}/web-apps/apps/api/documents/api.js"></script>
    <script>
        var config = {$payload};
        config.token = "{$token}";
        config.type = "desktop";
        
        console.log('OnlyOffice Config:', config);
        
        try {
            var docEditor = new DocsAPI.DocEditor("onlyoffice-editor", config);
            console.log('Editor initialized successfully');
        } catch(e) {
            console.error('Failed to initialize editor:', e);
            document.getElementById('onlyoffice-editor').innerHTML = 
                '<p style="color:red;">Error: ' + e.message + '</p>';
        }
    </script>
</body>
</html>
HTML;
    
    file_put_contents($testHtmlFile, $fullHtml);
    echo "   Test HTML saved to: $testHtmlFile\n";
    echo "   Open in browser: http://localhost/piattaforma-collaborativa/test-onlyoffice-generated.html\n";
    
} else {
    echo "   ✗ No test documents found in $docPath\n";
}

echo "\n=== Test Complete ===\n";