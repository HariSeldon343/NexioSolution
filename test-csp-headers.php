<?php
/**
 * Test CSP Headers Configuration
 * This script tests the Content Security Policy headers on production environment
 */

// Include configuration
require_once 'backend/config/config.php';

// Display current environment
echo "<h2>Environment Information</h2>";
echo "<p><strong>Current Host:</strong> " . ($_SERVER['HTTP_HOST'] ?? 'undefined') . "</p>";
echo "<p><strong>Is Production:</strong> " . (strpos($_SERVER['HTTP_HOST'] ?? '', 'app.nexiosolution.it') !== false ? 'Yes' : 'No') . "</p>";

// Check if production config is loaded
if (defined('SECURITY_HEADERS')) {
    echo "<h2>CSP Configuration (from production-config.php)</h2>";
    echo "<pre style='background: #f4f4f4; padding: 10px; overflow-x: auto;'>";
    
    $csp = SECURITY_HEADERS['Content-Security-Policy'];
    // Format CSP for better readability
    $csp_formatted = str_replace('; ', ";\n    ", $csp);
    echo htmlspecialchars($csp_formatted);
    echo "</pre>";
    
    echo "<h3>CSP Analysis:</h3>";
    echo "<ul>";
    
    // Check for required CDNs
    $required_cdns = [
        'cdn.jsdelivr.net' => 'Bootstrap & Chart.js',
        'cdnjs.cloudflare.com' => 'Font Awesome',
        'fonts.googleapis.com' => 'Google Fonts',
        'fonts.gstatic.com' => 'Google Font Files'
    ];
    
    foreach ($required_cdns as $cdn => $description) {
        if (strpos($csp, $cdn) !== false) {
            echo "<li style='color: green;'>✓ $cdn ($description) - ALLOWED</li>";
        } else {
            echo "<li style='color: red;'>✗ $cdn ($description) - NOT ALLOWED</li>";
        }
    }
    echo "</ul>";
} else {
    echo "<p style='color: orange;'>Security headers not defined. This is normal for local development.</p>";
}

// Show actual response headers
echo "<h2>Actual Response Headers</h2>";
echo "<p>These are the headers that will be sent to the browser:</p>";
echo "<pre style='background: #f4f4f4; padding: 10px;'>";
$headers = headers_list();
if (empty($headers)) {
    echo "No headers set yet (this is normal in local development).";
} else {
    foreach ($headers as $header) {
        echo htmlspecialchars($header) . "\n";
    }
}
echo "</pre>";

// Test external resource loading
echo "<h2>External Resource Loading Test</h2>";
echo "<p>Testing if external resources can be loaded:</p>";

?>
<!DOCTYPE html>
<html>
<head>
    <!-- Test Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Test Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- Test Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> If you can see this icon, Font Awesome is loading correctly.
        </div>
        
        <div class="btn btn-primary">
            If this button is styled, Bootstrap CSS is loading correctly.
        </div>
        
        <p style="font-family: 'Roboto', sans-serif; margin-top: 10px;">
            If this text appears in Roboto font, Google Fonts is loading correctly.
        </p>
        
        <canvas id="testChart" width="200" height="100"></canvas>
    </div>
    
    <!-- Test Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Test if Chart.js loads
        if (typeof Chart !== 'undefined') {
            document.write('<p style="color: green;">✓ Chart.js loaded successfully</p>');
            
            // Create a simple test chart
            var ctx = document.getElementById('testChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Test'],
                    datasets: [{
                        label: 'CSP Test',
                        data: [100],
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    }]
                }
            });
        } else {
            document.write('<p style="color: red;">✗ Chart.js failed to load</p>');
        }
    </script>
    
    <!-- Test Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        if (typeof bootstrap !== 'undefined') {
            document.write('<p style="color: green;">✓ Bootstrap JS loaded successfully</p>');
        } else {
            document.write('<p style="color: red;">✗ Bootstrap JS failed to load</p>');
        }
    </script>
    
    <h3>Browser Console</h3>
    <p>Check the browser console (F12) for any CSP violation errors. If you see CSP errors, they will indicate which resources are being blocked.</p>
</body>
</html>