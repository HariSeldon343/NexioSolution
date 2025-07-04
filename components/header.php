<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Dashboard'; ?> - <?php echo APP_NAME; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?php echo APP_PATH; ?>/assets/images/nexio-icon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo APP_PATH; ?>/assets/images/nexio-icon.svg">
    <link rel="apple-touch-icon" href="<?php echo APP_PATH; ?>/assets/images/nexio-icon.svg">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            line-height: 1.6;
        }
        
        .app-container {
            display: flex;
            min-height: 100vh;
            background: #f7fafc;
        }
        
        .sidebar {
            width: 250px;
            background: #2d3748;
            color: white;
            padding: 20px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        
        .sidebar-header {
            margin-bottom: 30px;
            text-align: center;
        }
        
        .sidebar-header h2 {
            color: #4299e1;
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .sidebar-header p {
            font-size: 12px;
            color: #a0aec0;
        }
        
        .sidebar-menu .menu-item {
            margin-bottom: 5px;
        }
        
        .sidebar-menu .menu-item a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #e2e8f0;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .sidebar-menu .menu-item a:hover,
        .sidebar-menu .menu-item a.active {
            background: #4299e1;
            color: white;
        }
        
        .sidebar-menu .menu-item a i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
        }
        
        .main-content {
            margin-left: auto;
            margin-right: auto;
            padding: 20px;
            background: #f7fafc;
            min-height: 100vh;
            /* Responsività basata sulle proporzioni dello schermo */
            width: 80%; /* Default: schermi 16:9/16:10 */
            max-width: calc(100vw - 250px);
            /* Posizionamento per evitare sovrapposizione con sidebar */
            position: relative;
            left: 125px; /* Meta della larghezza sidebar per centrare meglio */
        }
        
        /* Assicura background uniforme per tutte le carte e sezioni */
        .stat-card,
        .recent-items {
            background: white !important;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        /* Messaggi di stato vuoto con background coerente */
        .recent-items p[style*="text-align: center"] {
            background: rgba(113, 128, 150, 0.05);
            border-radius: 8px;
            margin: 10px;
            padding: 20px !important;
        }
        
        /* Schermi ultra-wide (21:9 e superiori) - larghezza 70% */
        @media screen and (min-aspect-ratio: 21/9) {
            .main-content {
                width: 70%;
                left: 125px;
            }
        }
        
        /* Schermi standard (16:9, 16:10) - larghezza 80% */
        @media screen and (min-aspect-ratio: 16/10) and (max-aspect-ratio: 21/9) {
            .main-content {
                width: 80%;
                left: 125px;
            }
        }
        
        /* Mobile responsive - larghezza piena su dispositivi mobili */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                margin-right: 0;
                width: 100% !important;
                max-width: 100vw !important;
                left: 0 !important;
                position: static;
            }
        }
        
        .user-info {
            margin-top: 30px;
            padding: 15px;
            background: #1a202c;
            border-radius: 8px;
            text-align: center;
        }
        
        .user-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .user-role {
            font-size: 12px;
            color: #a0aec0;
        }
        
        .menu-section-title {
            font-size: 12px;
            color: #a0aec0;
            margin-bottom: 15px;
            margin-top: 20px;
            font-weight: bold;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid #e2e8f0;
        }
        
        .recent-items {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid #e2e8f0;
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
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>✦ Nexio</h2>
                <p>Semplifica, Connetti, Cresci Insieme</p>
            </div>
            
            <!-- Menu Navigazione -->
            <nav class="sidebar-menu">
                <div class="menu-section-title">MENU PRINCIPALE</div>
                
                <?php
                $currentPage = basename($_SERVER['PHP_SELF']);
                
                // Menu con controllo permessi
                $isSuperAdmin = false;
                if (isset($auth) && $auth->isAuthenticated()) {
                    $isSuperAdmin = $auth->isSuperAdmin();
                }
                ?>
                
                <div class="menu-item">
                    <a href="<?php echo APP_PATH; ?>/dashboard.php" class="<?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </div>
                
                <?php if ($isSuperAdmin): ?>
                <div class="menu-item">
                    <a href="<?php echo APP_PATH; ?>/aziende.php" class="<?php echo $currentPage == 'aziende.php' ? 'active' : ''; ?>">
                        <i class="fas fa-building"></i>
                        <span>Aziende</span>
                    </a>
                </div>
                <?php endif; ?>
                
                <div class="menu-item">
                    <a href="<?php echo APP_PATH; ?>/documenti.php" class="<?php echo $currentPage == 'documenti.php' ? 'active' : ''; ?>">
                        <i class="fas fa-folder"></i>
                        <span>Documenti</span>
                    </a>
                </div>
                
                <div class="menu-item">
                    <a href="<?php echo APP_PATH; ?>/calendario-eventi.php" class="<?php echo $currentPage == 'calendario-eventi.php' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Calendario</span>
                    </a>
                </div>
                
                <div class="menu-item">
                    <a href="<?php echo APP_PATH; ?>/tickets.php" class="<?php echo $currentPage == 'tickets.php' ? 'active' : ''; ?>">
                        <i class="fas fa-ticket-alt"></i>
                        <span>Supporto</span>
                    </a>
                </div>
                
                <?php if ($isSuperAdmin): ?>
                <div class="menu-item">
                    <a href="<?php echo APP_PATH; ?>/gestione-classificazioni.php" class="<?php echo $currentPage == 'gestione-classificazioni.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tags"></i>
                        <span>Classificazioni</span>
                    </a>
                </div>
                
                <div class="menu-item">
                    <a href="<?php echo APP_PATH; ?>/gestione-moduli.php" class="<?php echo $currentPage == 'gestione-moduli.php' ? 'active' : ''; ?>">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Moduli</span>
                    </a>
                </div>
                
                <div class="menu-item">
                    <a href="<?php echo APP_PATH; ?>/gestione-utenti.php" class="<?php echo $currentPage == 'gestione-utenti.php' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>
                        <span>Utenti</span>
                    </a>
                </div>
                
                <div class="menu-item">
                    <a href="<?php echo APP_PATH; ?>/template-builder-dragdrop.php" class="<?php echo $currentPage == 'template-builder-dragdrop.php' ? 'active' : ''; ?>">
                        <i class="fas fa-file-contract"></i>
                        <span>Template</span>
                    </a>
                </div>
                
                <div class="menu-item">
                    <a href="<?php echo APP_PATH; ?>/log-attivita.php" class="<?php echo $currentPage == 'log-attivita.php' ? 'active' : ''; ?>">
                        <i class="fas fa-history"></i>
                        <span>Log</span>
                    </a>
                </div>
                
                <div class="menu-item">
                    <a href="<?php echo APP_PATH; ?>/configurazione-email.php" class="<?php echo $currentPage == 'configurazione-email.php' ? 'active' : ''; ?>">
                        <i class="fas fa-envelope"></i>
                        <span>Email Config</span>
                    </a>
                </div>
                <?php endif; ?>
                
                <div class="menu-item">
                    <a href="<?php echo APP_PATH; ?>/profilo.php" class="<?php echo $currentPage == 'profilo.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user"></i>
                        <span>Profilo</span>
                    </a>
                </div>
                
                <div class="menu-item">
                    <a href="<?php echo APP_PATH; ?>/logout.php" onclick="return confirm('Sei sicuro di voler uscire?');">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </nav>
            
            <!-- Info Utente -->
            <div class="user-info">
                <div class="user-name">
                    <?php 
                    if (isset($auth) && $auth->isAuthenticated()) {
                        $user = $auth->getUser();
                        echo htmlspecialchars(($user['nome'] ?? '') . ' ' . ($user['cognome'] ?? ''));
                    } else {
                        echo 'Utente';
                    }
                    ?>
                </div>
                <div class="user-role">
                    <?php 
                    if (isset($auth) && $auth->isAuthenticated()) {
                        $user = $auth->getUser();
                        echo htmlspecialchars($user['ruolo'] ?? 'Utente');
                    } else {
                        echo 'Non autenticato';
                    }
                    ?>
                </div>
            </div>
        </aside>
        
        <!-- Contenuto Principale -->
        <main class="main-content"> 