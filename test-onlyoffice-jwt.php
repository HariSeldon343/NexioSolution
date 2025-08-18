<?php
/**
 * Test OnlyOffice JWT Configuration
 * Run this script to verify JWT is properly configured
 */

require_once 'backend/config/config.php';
require_once 'backend/config/onlyoffice.config.php';

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>OnlyOffice JWT Configuration Test</title>
    <style>
        body { font-family: sans-serif; max-width: 1200px; margin: 20px auto; padding: 20px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .info { color: blue; }
        .test-section { 
            background: #f5f5f5; 
            padding: 15px; 
            margin: 10px 0; 
            border-radius: 5px;
            border-left: 4px solid #2196F3;
        }
        code { 
            background: #e0e0e0; 
            padding: 2px 5px; 
            border-radius: 3px;
            font-family: monospace;
        }
        pre {
            background: #263238;
            color: #aed581;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .critical {
            background: #ffebee;
            border-left-color: #f44336;
        }
        .good {
            background: #e8f5e9;
            border-left-color: #4caf50;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            background: #f5f5f5;
        }
    </style>
</head>
<body>
    <h1>🔐 OnlyOffice JWT Security Configuration Test</h1>
    
    <?php
    $tests_passed = 0;
    $tests_failed = 0;
    $critical_issues = [];
    
    // Test 1: Check if JWT is enabled
    echo '<div class="test-section ' . (ONLYOFFICE_JWT_ENABLED ? 'good' : 'critical') . '">';
    echo '<h3>1. JWT Authentication Status</h3>';
    if (ONLYOFFICE_JWT_ENABLED) {
        echo '<p class="success">✅ JWT Authentication is ENABLED</p>';
        $tests_passed++;
    } else {
        echo '<p class="error">❌ JWT Authentication is DISABLED - CRITICAL SECURITY ISSUE!</p>';
        echo '<p>Fix: Set <code>ONLYOFFICE_JWT_ENABLED = true</code> in onlyoffice.config.php</p>';
        $critical_issues[] = 'JWT is disabled';
        $tests_failed++;
    }
    echo '</div>';
    
    // Test 2: Check JWT Secret
    echo '<div class="test-section">';
    echo '<h3>2. JWT Secret Configuration</h3>';
    if (defined('ONLYOFFICE_JWT_SECRET')) {
        $secret_length = strlen(ONLYOFFICE_JWT_SECRET);
        if ($secret_length >= 32) {
            echo '<p class="success">✅ JWT Secret is configured (Length: ' . $secret_length . ' chars)</p>';
            $tests_passed++;
        } else {
            echo '<p class="warning">⚠️ JWT Secret is too short (Length: ' . $secret_length . ' chars, minimum 32 recommended)</p>';
            $tests_failed++;
        }
        
        // Check if it's the default secret
        if (strpos(ONLYOFFICE_JWT_SECRET, 'nexio-jwt-secret') !== false) {
            echo '<p class="warning">⚠️ Using default JWT secret - Change this for production!</p>';
            $critical_issues[] = 'Using default JWT secret';
        }
    } else {
        echo '<p class="error">❌ JWT Secret is not defined</p>';
        $critical_issues[] = 'JWT Secret not defined';
        $tests_failed++;
    }
    echo '</div>';
    
    // Test 3: Server URLs Configuration
    echo '<div class="test-section">';
    echo '<h3>3. OnlyOffice Server URLs</h3>';
    echo '<table>';
    echo '<tr><th>Setting</th><th>Value</th><th>Status</th></tr>';
    
    $urls = [
        'Public URL' => ONLYOFFICE_DS_PUBLIC_URL ?? 'Not set',
        'Internal URL' => ONLYOFFICE_DS_INTERNAL_URL ?? 'Not set',
        'Callback URL' => ONLYOFFICE_CALLBACK_URL ?? 'Not set'
    ];
    
    foreach ($urls as $name => $url) {
        $is_https = strpos($url, 'https://') === 0;
        $is_localhost = strpos($url, 'localhost') !== false;
        
        echo '<tr>';
        echo '<td>' . $name . '</td>';
        echo '<td><code>' . htmlspecialchars($url) . '</code></td>';
        echo '<td>';
        
        if ($url === 'Not set') {
            echo '<span class="error">❌ Not configured</span>';
        } elseif ($is_localhost) {
            echo '<span class="warning">⚠️ Using localhost (OK for dev)</span>';
        } elseif (!$is_https && strpos($url, 'http://') === 0) {
            echo '<span class="warning">⚠️ Not using HTTPS (Required for production)</span>';
        } else {
            echo '<span class="success">✅ Properly configured</span>';
        }
        
        echo '</td></tr>';
    }
    echo '</table>';
    echo '</div>';
    
    // Test 4: JWT Token Generation
    echo '<div class="test-section">';
    echo '<h3>4. JWT Token Generation Test</h3>';
    
    if (function_exists('generateJWT')) {
        try {
            $test_payload = [
                'document' => ['key' => 'test123'],
                'editorConfig' => ['user' => ['id' => '1', 'name' => 'Test User']],
                'iat' => time(),
                'exp' => time() + 3600
            ];
            
            $token = generateJWT($test_payload);
            
            if ($token) {
                echo '<p class="success">✅ JWT token generation works</p>';
                echo '<p>Sample token (first 50 chars): <code>' . substr($token, 0, 50) . '...</code></p>';
                $tests_passed++;
                
                // Test token verification
                if (function_exists('verifyJWT')) {
                    $verified = verifyJWT($token);
                    if ($verified) {
                        echo '<p class="success">✅ JWT token verification works</p>';
                        $tests_passed++;
                    } else {
                        echo '<p class="error">❌ JWT token verification failed</p>';
                        $tests_failed++;
                    }
                }
            } else {
                echo '<p class="error">❌ Failed to generate JWT token</p>';
                $tests_failed++;
            }
        } catch (Exception $e) {
            echo '<p class="error">❌ Error: ' . $e->getMessage() . '</p>';
            $tests_failed++;
        }
    } else {
        echo '<p class="error">❌ JWT generation function not found</p>';
        $tests_failed++;
    }
    echo '</div>';
    
    // Test 5: Security Headers
    echo '<div class="test-section">';
    echo '<h3>5. Security Configuration</h3>';
    echo '<table>';
    echo '<tr><th>Security Feature</th><th>Status</th></tr>';
    
    $security_features = [
        'Rate Limiting' => defined('ONLYOFFICE_RATE_LIMIT') && ONLYOFFICE_RATE_LIMIT > 0,
        'IP Whitelisting' => defined('ONLYOFFICE_ALLOWED_IPS') && !empty(ONLYOFFICE_ALLOWED_IPS),
        'CORS Configuration' => defined('ONLYOFFICE_CORS_ORIGINS') && ONLYOFFICE_CORS_ORIGINS !== '*',
        'Debug Mode' => !ONLYOFFICE_DEBUG,
        'HTTPS Enforcement' => defined('ONLYOFFICE_FORCE_HTTPS') && ONLYOFFICE_FORCE_HTTPS
    ];
    
    foreach ($security_features as $feature => $enabled) {
        echo '<tr>';
        echo '<td>' . $feature . '</td>';
        echo '<td>';
        if ($feature === 'Debug Mode') {
            // Inverted logic for debug mode
            if ($enabled) {
                echo '<span class="success">✅ Disabled (Good for production)</span>';
            } else {
                echo '<span class="warning">⚠️ Enabled (OK for development)</span>';
            }
        } else {
            if ($enabled) {
                echo '<span class="success">✅ Enabled</span>';
            } else {
                echo '<span class="info">ℹ️ Not configured (Optional)</span>';
            }
        }
        echo '</td></tr>';
    }
    echo '</table>';
    echo '</div>';
    
    // Test 6: Database Tables
    echo '<div class="test-section">';
    echo '<h3>6. Database Tables Check</h3>';
    
    try {
        $tables_to_check = [
            'documenti_versioni_extended' => 'Document versioning',
            'onlyoffice_sessions' => 'Active sessions tracking',
            'onlyoffice_collaborative_actions' => 'Collaboration tracking'
        ];
        
        foreach ($tables_to_check as $table => $description) {
            $stmt = db_query("SHOW TABLES LIKE ?", [$table]);
            if ($stmt->rowCount() > 0) {
                echo '<p class="success">✅ Table <code>' . $table . '</code> exists (' . $description . ')</p>';
                $tests_passed++;
            } else {
                echo '<p class="warning">⚠️ Table <code>' . $table . '</code> not found - Run database migration</p>';
            }
        }
    } catch (Exception $e) {
        echo '<p class="error">❌ Database error: ' . $e->getMessage() . '</p>';
    }
    echo '</div>';
    
    // Summary
    echo '<div class="test-section ' . (empty($critical_issues) ? 'good' : 'critical') . '">';
    echo '<h2>📊 Test Summary</h2>';
    echo '<p>Tests Passed: <span class="success">' . $tests_passed . '</span></p>';
    echo '<p>Tests Failed: <span class="error">' . $tests_failed . '</span></p>';
    
    if (!empty($critical_issues)) {
        echo '<h3 class="error">⚠️ Critical Issues Found:</h3>';
        echo '<ul>';
        foreach ($critical_issues as $issue) {
            echo '<li class="error">' . $issue . '</li>';
        }
        echo '</ul>';
        echo '<p class="error"><strong>⛔ System is NOT ready for production!</strong></p>';
    } else {
        echo '<p class="success"><strong>✅ Basic security configuration is OK</strong></p>';
    }
    
    // Recommendations
    echo '<h3>📋 Production Checklist:</h3>';
    echo '<ol>';
    echo '<li>Generate a strong JWT secret: <code>openssl rand -hex 32</code></li>';
    echo '<li>Set <code>ONLYOFFICE_JWT_ENABLED = true</code> (never disable in production)</li>';
    echo '<li>Configure HTTPS URLs for all services</li>';
    echo '<li>Set up proper CORS origins (not *)</li>';
    echo '<li>Disable debug mode in production</li>';
    echo '<li>Configure rate limiting</li>';
    echo '<li>Set up monitoring and logging</li>';
    echo '<li>Run database migrations for version tracking</li>';
    echo '</ol>';
    echo '</div>';
    
    // Environment Variables Template
    echo '<div class="test-section">';
    echo '<h3>🔧 Environment Configuration Template</h3>';
    echo '<p>Create a <code>.env</code> file with these settings:</p>';
    echo '<pre>';
    echo '# OnlyOffice Configuration
ONLYOFFICE_JWT_ENABLED=true
ONLYOFFICE_JWT_SECRET=' . bin2hex(random_bytes(32)) . '
ONLYOFFICE_DS_PUBLIC_URL=https://office.yourdomain.com
ONLYOFFICE_DS_INTERNAL_URL=http://onlyoffice-ds:80
ONLYOFFICE_CALLBACK_URL=https://yourdomain.com/backend/api/onlyoffice-callback.php
ONLYOFFICE_DEBUG=false
ONLYOFFICE_FORCE_HTTPS=true
ONLYOFFICE_RATE_LIMIT=100
ONLYOFFICE_CORS_ORIGINS=https://yourdomain.com,https://office.yourdomain.com';
    echo '</pre>';
    echo '</div>';
    ?>
    
    <div style="margin-top: 30px; padding: 20px; background: #f0f0f0; border-radius: 5px;">
        <p><strong>Next Steps:</strong></p>
        <ol>
            <li>Fix any critical issues identified above</li>
            <li>Configure environment variables for production</li>
            <li>Test document editing with OnlyOffice</li>
            <li>Monitor callback endpoint for proper JWT validation</li>
            <li>Set up regular security audits</li>
        </ol>
    </div>
</body>
</html>