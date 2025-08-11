<?php
require_once 'backend/config/config.php';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifica CDN - Nexio</title>
    
    <!-- Test Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Test Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- Test Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f8f9fa;
            padding: 40px;
        }
        .test-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .status-ok {
            color: #28a745;
        }
        .status-error {
            color: #dc3545;
        }
        .test-icon {
            font-size: 24px;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">
            <i class="fas fa-network-wired"></i> Verifica Risorse CDN
        </h1>
        
        <div class="test-card">
            <h3>Test Bootstrap CSS</h3>
            <div id="bootstrap-test">
                <button class="btn btn-primary">Bottone Bootstrap</button>
                <button class="btn btn-success ms-2">Success</button>
                <button class="btn btn-danger ms-2">Danger</button>
            </div>
            <p class="mt-3">
                <span id="bootstrap-status" class="status-error">
                    <i class="fas fa-times-circle"></i> Verifica in corso...
                </span>
            </p>
        </div>
        
        <div class="test-card">
            <h3>Test Font Awesome</h3>
            <div>
                <i class="fas fa-check-circle test-icon status-ok"></i>
                <i class="fas fa-user test-icon"></i>
                <i class="fas fa-cog test-icon"></i>
                <i class="fas fa-heart test-icon" style="color: #e74c3c;"></i>
            </div>
            <p class="mt-3">
                <span id="fontawesome-status" class="status-error">
                    <i class="fas fa-times-circle"></i> Verifica in corso...
                </span>
            </p>
        </div>
        
        <div class="test-card">
            <h3>Test Chart.js</h3>
            <canvas id="testChart" width="400" height="100"></canvas>
            <p class="mt-3">
                <span id="chartjs-status" class="status-error">
                    <i class="fas fa-times-circle"></i> Verifica in corso...
                </span>
            </p>
        </div>
        
        <div class="test-card">
            <h3>Headers CSP Attuali</h3>
            <pre id="csp-headers" style="background: #f4f4f4; padding: 15px; border-radius: 8px; overflow-x: auto;">
<?php
$headers = headers_list();
foreach ($headers as $header) {
    if (stripos($header, 'Content-Security-Policy') !== false) {
        echo htmlspecialchars($header) . "\n";
    }
}
if (empty($headers)) {
    echo "Nessun header CSP impostato";
}
?>
            </pre>
        </div>
        
        <div class="test-card">
            <h3>Informazioni Ambiente</h3>
            <ul>
                <li><strong>Host:</strong> <?php echo $_SERVER['HTTP_HOST']; ?></li>
                <li><strong>Produzione:</strong> <?php echo (strpos($_SERVER['HTTP_HOST'], 'nexiosolution.it') !== false) ? 'Sì' : 'No'; ?></li>
                <li><strong>Protocollo:</strong> <?php echo isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'HTTPS' : 'HTTP'; ?></li>
                <li><strong>User Agent:</strong> <?php echo htmlspecialchars($_SERVER['HTTP_USER_AGENT']); ?></li>
            </ul>
        </div>
    </div>
    
    <!-- Test Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        // Test Bootstrap
        window.addEventListener('load', function() {
            // Check Bootstrap
            if (typeof window.bootstrap !== 'undefined') {
                document.getElementById('bootstrap-status').innerHTML = 
                    '<i class="fas fa-check-circle"></i> Bootstrap caricato correttamente';
                document.getElementById('bootstrap-status').className = 'status-ok';
            } else {
                document.getElementById('bootstrap-status').innerHTML = 
                    '<i class="fas fa-times-circle"></i> Bootstrap non caricato - CSP blocca il CDN';
            }
            
            // Check Font Awesome
            const faIcon = document.querySelector('.fa-check-circle');
            if (faIcon && window.getComputedStyle(faIcon, ':before').content !== 'none') {
                document.getElementById('fontawesome-status').innerHTML = 
                    '<i class="fas fa-check-circle"></i> Font Awesome caricato correttamente';
                document.getElementById('fontawesome-status').className = 'status-ok';
            } else {
                document.getElementById('fontawesome-status').innerHTML = 
                    '<i class="fas fa-times-circle"></i> Font Awesome non caricato - CSP blocca il CDN';
            }
            
            // Check Chart.js
            if (typeof Chart !== 'undefined') {
                document.getElementById('chartjs-status').innerHTML = 
                    '<i class="fas fa-check-circle"></i> Chart.js caricato correttamente';
                document.getElementById('chartjs-status').className = 'status-ok';
                
                // Create sample chart
                const ctx = document.getElementById('testChart').getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: ['Gen', 'Feb', 'Mar', 'Apr', 'Mag'],
                        datasets: [{
                            label: 'Test Data',
                            data: [12, 19, 3, 5, 2],
                            backgroundColor: 'rgba(54, 162, 235, 0.5)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            } else {
                document.getElementById('chartjs-status').innerHTML = 
                    '<i class="fas fa-times-circle"></i> Chart.js non caricato - CSP blocca il CDN';
            }
        });
        
        // Check for CSP violations in console
        document.addEventListener('securitypolicyviolation', function(e) {
            console.error('CSP Violation:', {
                blockedURI: e.blockedURI,
                violatedDirective: e.violatedDirective,
                originalPolicy: e.originalPolicy
            });
        });
    </script>
</body>
</html>