<?php
/**
 * Test Mobile Paths - Verifica configurazione percorsi dinamici
 */

require_once 'mobile/config.php';

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Mobile Paths</title>
    <style>
        body {
            font-family: -apple-system, system-ui, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #2563eb;
        }
        
        h2 {
            color: #1e293b;
            margin-top: 0;
        }
        
        .test-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .test-item {
            padding: 12px;
            background: #f8fafc;
            border-radius: 4px;
            border-left: 4px solid #2563eb;
        }
        
        .test-item strong {
            display: block;
            margin-bottom: 4px;
            color: #1e293b;
        }
        
        .test-item code {
            color: #059669;
            background: #ecfdf5;
            padding: 2px 6px;
            border-radius: 3px;
            word-break: break-all;
        }
        
        .success {
            color: #059669;
            background: #ecfdf5;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
        }
        
        .error {
            color: #dc2626;
            background: #fef2f2;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
        }
        
        .links {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        
        .links a {
            display: inline-block;
            padding: 8px 16px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background 0.2s;
        }
        
        .links a:hover {
            background: #1d4ed8;
        }
        
        pre {
            background: #1e293b;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-ok {
            background: #10b981;
            color: white;
        }
        
        .status-error {
            background: #ef4444;
            color: white;
        }
    </style>
</head>
<body>
    <h1>🧪 Test Mobile Paths Configuration</h1>
    
    <!-- Server Info -->
    <div class="card">
        <h2>📊 Server Information</h2>
        <div class="test-grid">
            <div class="test-item">
                <strong>Server Host:</strong>
                <code><?php echo $_SERVER['HTTP_HOST']; ?></code>
            </div>
            <div class="test-item">
                <strong>Protocol:</strong>
                <code><?php echo isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'HTTPS' : 'HTTP'; ?></code>
            </div>
            <div class="test-item">
                <strong>Script Path:</strong>
                <code><?php echo $_SERVER['SCRIPT_NAME']; ?></code>
            </div>
            <div class="test-item">
                <strong>Document Root:</strong>
                <code><?php echo $_SERVER['DOCUMENT_ROOT']; ?></code>
            </div>
        </div>
    </div>
    
    <!-- Costanti PHP -->
    <div class="card">
        <h2>🔧 PHP Constants (from config.php)</h2>
        <div class="test-grid">
            <div class="test-item">
                <strong>BASE_PATH:</strong>
                <code><?php echo BASE_PATH; ?></code>
            </div>
            <div class="test-item">
                <strong>BASE_URL:</strong>
                <code><?php echo BASE_URL; ?></code>
            </div>
            <div class="test-item">
                <strong>FULL_URL:</strong>
                <code><?php echo FULL_URL; ?></code>
            </div>
            <div class="test-item">
                <strong>MOBILE_URL:</strong>
                <code><?php echo MOBILE_URL; ?></code>
            </div>
            <div class="test-item">
                <strong>API_URL:</strong>
                <code><?php echo API_URL; ?></code>
            </div>
            <div class="test-item">
                <strong>ASSETS_URL:</strong>
                <code><?php echo ASSETS_URL; ?></code>
            </div>
        </div>
    </div>
    
    <!-- Helper Functions -->
    <div class="card">
        <h2>🛠️ Helper Functions Output</h2>
        <div class="test-grid">
            <div class="test-item">
                <strong>url('login.php'):</strong>
                <code><?php echo url('login.php'); ?></code>
            </div>
            <div class="test-item">
                <strong>api_url('login.php'):</strong>
                <code><?php echo api_url('login.php'); ?></code>
            </div>
            <div class="test-item">
                <strong>asset_url('css/style.css'):</strong>
                <code><?php echo asset_url('css/style.css'); ?></code>
            </div>
            <div class="test-item">
                <strong>mobile_url('index.php'):</strong>
                <code><?php echo mobile_url('index.php'); ?></code>
            </div>
        </div>
    </div>
    
    <!-- JavaScript Config -->
    <div class="card">
        <h2>💻 JavaScript Configuration</h2>
        <pre><?php echo htmlspecialchars(js_config()); ?></pre>
        
        <h3>Test JavaScript Access:</h3>
        <div id="js-test" class="test-item">
            <strong>JavaScript Config Values:</strong>
            <div id="js-values">Loading...</div>
        </div>
    </div>
    
    <!-- File Checks -->
    <div class="card">
        <h2>📁 Critical Files Check</h2>
        <?php
        $files_to_check = [
            'mobile/index.php' => 'Mobile Index',
            'mobile/login.php' => 'Mobile Login',
            'mobile/manifest.php' => 'PWA Manifest',
            'mobile/sw-dynamic.js' => 'Service Worker',
            'mobile/offline.html' => 'Offline Page',
            'mobile/config.php' => 'Config File',
            'mobile/icons/icon-192x192.png' => 'PWA Icon',
            'backend/api/folders-api.php' => 'Folders API',
            'assets/images/nexio-icon.svg' => 'Logo'
        ];
        
        foreach ($files_to_check as $file => $name) {
            $full_path = dirname(__FILE__) . '/' . $file;
            $exists = file_exists($full_path);
            echo '<div class="test-item">';
            echo '<strong>' . $name . ':</strong> ';
            echo '<code>' . $file . '</code> ';
            echo '<span class="status-badge ' . ($exists ? 'status-ok">EXISTS' : 'status-error">MISSING') . '</span>';
            echo '</div>';
        }
        ?>
    </div>
    
    <!-- Test Links -->
    <div class="card">
        <h2>🔗 Test Links</h2>
        <div class="links">
            <a href="<?php echo mobile_url('index.php'); ?>" target="_blank">Mobile Home</a>
            <a href="<?php echo mobile_url('login.php'); ?>" target="_blank">Mobile Login</a>
            <a href="<?php echo mobile_url('manifest.php'); ?>" target="_blank">PWA Manifest</a>
            <a href="<?php echo mobile_url('sw-dynamic.js'); ?>" target="_blank">Service Worker</a>
            <a href="<?php echo mobile_url('offline.html'); ?>" target="_blank">Offline Page</a>
            <a href="<?php echo api_url('check-auth.php'); ?>" target="_blank">Check Auth API</a>
        </div>
    </div>
    
    <!-- Environment Detection -->
    <div class="card">
        <h2>🌍 Environment Detection</h2>
        <?php
        $is_localhost = in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1', '::1']);
        $is_production = strpos($_SERVER['HTTP_HOST'], 'nexiosolution.it') !== false;
        ?>
        <div class="test-grid">
            <div class="test-item">
                <strong>Environment:</strong>
                <code><?php echo $is_localhost ? 'Development (localhost)' : ($is_production ? 'Production' : 'Unknown'); ?></code>
            </div>
            <div class="test-item">
                <strong>Base URL Detection:</strong>
                <code><?php echo $is_production ? 'Should be /piattaforma-collaborativa' : 'Should be /piattaforma-collaborativa'; ?></code>
            </div>
        </div>
        
        <?php if ($is_production): ?>
        <div class="success">
            ✅ Production environment detected. URLs will use HTTPS and app.nexiosolution.it domain.
        </div>
        <?php else: ?>
        <div class="success">
            ✅ Development environment detected. URLs will use localhost.
        </div>
        <?php endif; ?>
    </div>
    
    <!-- PWA Test -->
    <div class="card">
        <h2>📱 PWA Installation Test</h2>
        <button onclick="testPWA()" style="padding: 10px 20px; background: #2563eb; color: white; border: none; border-radius: 4px; cursor: pointer;">
            Test PWA Installation
        </button>
        <div id="pwa-result" style="margin-top: 10px;"></div>
    </div>
    
    <?php echo js_config(); ?>
    
    <script>
        // Display JavaScript config values
        document.addEventListener('DOMContentLoaded', function() {
            const jsValues = document.getElementById('js-values');
            if (window.NexioConfig) {
                let html = '<ul style="margin: 10px 0; padding-left: 20px;">';
                for (const [key, value] of Object.entries(window.NexioConfig)) {
                    html += `<li><strong>${key}:</strong> <code>${value}</code></li>`;
                }
                html += '</ul>';
                jsValues.innerHTML = html;
            } else {
                jsValues.innerHTML = '<span style="color: red;">NexioConfig not found!</span>';
            }
        });
        
        // Test PWA
        function testPWA() {
            const result = document.getElementById('pwa-result');
            
            // Check if service worker is supported
            if ('serviceWorker' in navigator) {
                result.innerHTML = '<div class="success">✅ Service Worker supported</div>';
                
                // Try to register service worker
                const swPath = `${window.NexioConfig.BASE_URL}/mobile/sw-dynamic.js`;
                navigator.serviceWorker.register(swPath)
                    .then(registration => {
                        result.innerHTML += '<div class="success">✅ Service Worker registered successfully</div>';
                        console.log('SW registered:', registration);
                    })
                    .catch(error => {
                        result.innerHTML += `<div class="error">❌ Service Worker registration failed: ${error.message}</div>`;
                        console.error('SW registration failed:', error);
                    });
                    
                // Check manifest
                fetch(`${window.NexioConfig.BASE_URL}/mobile/manifest.php`)
                    .then(response => response.json())
                    .then(manifest => {
                        result.innerHTML += '<div class="success">✅ Manifest loaded successfully</div>';
                        console.log('Manifest:', manifest);
                    })
                    .catch(error => {
                        result.innerHTML += `<div class="error">❌ Manifest load failed: ${error.message}</div>`;
                    });
            } else {
                result.innerHTML = '<div class="error">❌ Service Worker not supported in this browser</div>';
            }
        }
    </script>
</body>
</html>