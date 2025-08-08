<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Dashboard'; ?> - <?php echo APP_NAME; ?></title>
    
    <?php 
    // Cache busting per CSS/JS - forza refresh quando files cambiano
    $css_version = filemtime(dirname(__DIR__) . '/assets/css/style.css') ?: time();
    $js_version = time(); // Per JavaScript
    ?>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?php echo APP_PATH; ?>/assets/images/nexio-icon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo APP_PATH; ?>/assets/images/nexio-icon.svg">
    <link rel="apple-touch-icon" href="<?php echo APP_PATH; ?>/assets/images/nexio-icon.svg">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- CSS principale con cache busting -->
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/style.css?v=<?php echo $css_version; ?>">
    
    <style>
        /* Layout principale */
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }
        
        .app-container {
            display: flex;
            min-height: 100vh;
            background: #f7fafc;
        }
        
        /* Stili per le carte e sezioni */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card,
        .recent-items {
            background: white !important;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 25px;
        }
        
        .recent-items h2 {
            margin-bottom: 20px;
            color: #2d3748;
        }
        
        .recent-item {
            padding: 15px 0;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .recent-item:last-child {
            border-bottom: none;
        }
        
        /* Messaggi di stato vuoto */
        .recent-items p[style*="text-align: center"] {
            background: rgba(113, 128, 150, 0.05);
            border-radius: 8px;
            margin: 10px;
            padding: 20px !important;
        }
        
        /* Bottoni */
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn-primary {
            background: #4299e1;
            color: white;
        }
        
        .btn-secondary {
            background: #e2e8f0;
            color: #2d3748;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Include Sidebar Componente -->
        <?php 
        // Include sidebar solo se l'utente è autenticato
        if (isset($auth) && $auth->isAuthenticated()) {
            include __DIR__ . '/sidebar.php'; 
        }
        ?>
        
        <!-- Contenuto Principale -->
        <main class="main-content"> 