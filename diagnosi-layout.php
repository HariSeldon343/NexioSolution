<?php
require_once 'backend/config/config.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$user = $auth->getUser();
$currentAzienda = $auth->getCurrentAzienda();

$pageTitle = 'Diagnosi Layout';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnosi Layout - Nexio</title>
    
    <!-- CSS Base -->
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/style.css?v=<?php echo time(); ?>">
    
    <!-- Modern Theme CSS -->
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/modern-theme.css?v=<?php echo time(); ?>">
    
    <!-- Layout Fix CSS -->
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/layout-fix.css?v=<?php echo time(); ?>">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .diagnostic-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .diagnostic-section {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .diagnostic-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .diagnostic-item {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
        }
        
        .status-ok {
            color: green;
        }
        
        .status-error {
            color: red;
        }
        
        .path-info {
            background: #f0f0f0;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            font-family: monospace;
            font-size: 0.9em;
        }
        
        .css-test {
            border: 2px solid #333;
            padding: 20px;
            margin: 10px 0;
        }
        
        .sidebar-test {
            background: var(--sidebar-bg);
            color: var(--sidebar-text);
            padding: 20px;
            margin: 10px 0;
            border: 1px solid var(--sidebar-border);
        }
    </style>
</head>
<body>
    <div class="diagnostic-container">
        <h1>Diagnosi Layout</h1>
        
        <div class="diagnostic-section">
            <h2>1. Informazioni di Sistema</h2>
            <div class="path-info">
                <strong>APP_PATH:</strong> <?php echo APP_PATH; ?><br>
                <strong>APP_URL:</strong> <?php echo APP_URL; ?><br>
                <strong>BASE_URL:</strong> <?php echo BASE_URL; ?><br>
                <strong>ROOT_PATH:</strong> <?php echo ROOT_PATH; ?><br>
                <strong>User Agent:</strong> <?php echo $_SERVER['HTTP_USER_AGENT'] ?? 'Non disponibile'; ?>
            </div>
        </div>
        
        <div class="diagnostic-section">
            <h2>2. Caricamento CSS</h2>
            <div class="diagnostic-grid">
                <div class="diagnostic-item">
                    <h3>style.css</h3>
                    <p id="style-css-status">Verificando...</p>
                </div>
                <div class="diagnostic-item">
                    <h3>modern-theme.css</h3>
                    <p id="modern-css-status">Verificando...</p>
                </div>
                <div class="diagnostic-item">
                    <h3>layout-fix.css</h3>
                    <p id="fix-css-status">Verificando...</p>
                </div>
            </div>
        </div>
        
        <div class="diagnostic-section">
            <h2>3. Test Variabili CSS</h2>
            <div class="css-test" style="background-color: var(--bg-primary); color: var(--text-primary);">
                Sfondo primario e testo primario
            </div>
            <div class="css-test" style="background-color: var(--primary-color); color: white;">
                Colore primario
            </div>
            <div class="sidebar-test">
                Test colori sidebar
            </div>
        </div>
        
        <div class="diagnostic-section">
            <h2>4. Test Immagini</h2>
            <div>
                <h3>Logo Nexio:</h3>
                <img src="<?php echo APP_PATH; ?>/assets/images/nexio-logo.svg" alt="Nexio Logo" style="max-width: 200px; border: 1px solid #ddd; padding: 10px;">
                <p>Percorso: <?php echo APP_PATH; ?>/assets/images/nexio-logo.svg</p>
            </div>
        </div>
        
        <div class="diagnostic-section">
            <h2>5. Test JavaScript</h2>
            <p id="js-status">JavaScript non caricato</p>
        </div>
        
        <div class="diagnostic-section">
            <h2>6. Dimensioni Viewport</h2>
            <p>Larghezza: <span id="viewport-width"></span>px</p>
            <p>Altezza: <span id="viewport-height"></span>px</p>
            <p>Device Pixel Ratio: <span id="pixel-ratio"></span></p>
        </div>
        
        <div class="diagnostic-section">
            <h2>7. Elementi Layout</h2>
            <button class="btn btn-primary">Bottone Primario</button>
            <button class="btn btn-secondary">Bottone Secondario</button>
            <button class="btn btn-success">Bottone Success</button>
            <button class="btn btn-danger">Bottone Danger</button>
        </div>
    </div>
    
    <script>
        // Test caricamento CSS
        function checkCSS(filename, elementId) {
            const sheets = document.styleSheets;
            let found = false;
            
            for (let i = 0; i < sheets.length; i++) {
                if (sheets[i].href && sheets[i].href.includes(filename)) {
                    found = true;
                    break;
                }
            }
            
            const element = document.getElementById(elementId);
            if (found) {
                element.innerHTML = '<span class="status-ok">✓ Caricato correttamente</span>';
            } else {
                element.innerHTML = '<span class="status-error">✗ Non caricato</span>';
            }
        }
        
        // Esegui i test
        window.addEventListener('load', function() {
            // Test CSS
            checkCSS('style.css', 'style-css-status');
            checkCSS('modern-theme.css', 'modern-css-status');
            checkCSS('layout-fix.css', 'fix-css-status');
            
            // Test JavaScript
            document.getElementById('js-status').innerHTML = '<span class="status-ok">✓ JavaScript funzionante</span>';
            
            // Dimensioni viewport
            function updateViewport() {
                document.getElementById('viewport-width').textContent = window.innerWidth;
                document.getElementById('viewport-height').textContent = window.innerHeight;
                document.getElementById('pixel-ratio').textContent = window.devicePixelRatio || 1;
            }
            
            updateViewport();
            window.addEventListener('resize', updateViewport);
            
            // Log computed styles
            const testDiv = document.querySelector('.css-test');
            if (testDiv) {
                const styles = window.getComputedStyle(testDiv);
                console.log('Background color:', styles.backgroundColor);
                console.log('Text color:', styles.color);
            }
        });
    </script>
</body>
</html> 