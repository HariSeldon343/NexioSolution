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
    
    <!-- FontAwesome 6 - Official CDN Only (NO KITS - they cause CORS issues) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <!-- FontAwesome 5 Fallback (for compatibility) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" integrity="sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <!-- Nexio Light CSS -->
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/nexio-light.css?v=<?php echo time(); ?>">
    
    <!-- Fix colori e UI - Risolve problemi di contrasto e bottoni -->
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/nexio-color-fixes.css?v=<?php echo time(); ?>">
    
    <!-- BUTTON WHITE TEXT FIX - Forces white text on all primary buttons - Added 2025-08-11 -->
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/nexio-button-white-text.css?v=<?php echo time(); ?>">
    
    <!-- Priority Overrides (load last) -->
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/nexio-priority-override.css?v=<?php echo time(); ?>">
    
    <!-- Script per mobile menu -->
    <script>
        function toggleMobileMenu() {
            document.querySelector('.sidebar').classList.toggle('active');
        }
    </script>
</head>
<body>
    <?php
    // Get current page name for active menu
    $current_page = basename($_SERVER['PHP_SELF']);
    
    // Check if user is authenticated
    $auth = Auth::getInstance();
    $user = $auth->getUser();
    $isSuperAdmin = $auth->isSuperAdmin();
    $isUtenteSpeciale = $auth->isUtenteSpeciale();
    $currentAzienda = $auth->getCurrentAzienda();
    
    // Gestione notifiche/messaggi
    $success_message = $_SESSION['success'] ?? null;
    $error_message = $_SESSION['error'] ?? null;
    unset($_SESSION['success']);
    unset($_SESSION['error']);
    ?>
    
    <div class="app-container">
        <!-- Mobile menu toggle -->
        <button class="mobile-menu-toggle" onclick="toggleMobileMenu()" style="display: none;">
            <i class="fas fa-bars"></i>
        </button>
        
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>Nexio</h2>
                <p>Semplifica, Connetti, Cresci</p>
            </div>
            
            <nav class="sidebar-menu">
                <!-- Dashboard -->
                <div class="menu-item">
                    <a href="<?php echo APP_PATH; ?>/dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </div>
                
                <!-- AREA OPERATIVA -->
                <div class="menu-section-title">AREA OPERATIVA</div>
                
                <div class="menu-item">
                    <a href="<?php echo APP_PATH; ?>/filesystem.php" class="<?php echo $current_page == 'filesystem.php' ? 'active' : ''; ?>">
                        <i class="fas fa-folder-open"></i>
                        <span>File Manager</span>
                    </a>
                </div>
                
                <div class="menu-item">
                    <a href="<?php echo APP_PATH; ?>/calendario-eventi.php" class="<?php echo $current_page == 'calendario-eventi.php' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Calendario Eventi</span>
                    </a>
                </div>
                
                <div class="menu-item">
                    <a href="<?php echo APP_PATH; ?>/tickets.php" class="<?php echo $current_page == 'tickets.php' ? 'active' : ''; ?>">
                        <i class="fas fa-ticket-alt"></i>
                        <span>Ticket Supporto</span>
                    </a>
                </div>
                
                <?php if ($isSuperAdmin): ?>
                <!-- GESTIONE (solo admin) -->
                <div class="menu-section-title">GESTIONE</div>
                
                <div class="menu-item">
                    <a href="<?php echo APP_PATH; ?>/aziende.php" class="<?php echo $current_page == 'aziende.php' ? 'active' : ''; ?>">
                        <i class="fas fa-building"></i>
                        <span>Aziende</span>
                    </a>
                </div>
                
                <!-- AMMINISTRAZIONE (solo super admin) -->
                <div class="menu-section-title">AMMINISTRAZIONE</div>
                
                <div class="menu-item">
                    <a href="<?php echo APP_PATH; ?>/gestione-utenti.php" class="<?php echo $current_page == 'gestione-utenti.php' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>
                        <span>Utenti</span>
                    </a>
                </div>
                
                <div class="menu-item">
                    <a href="<?php echo APP_PATH; ?>/log-attivita.php" class="<?php echo $current_page == 'log-attivita.php' ? 'active' : ''; ?>">
                        <i class="fas fa-history"></i>
                        <span>Audit Log</span>
                    </a>
                </div>
                
                <div class="menu-item">
                    <a href="<?php echo APP_PATH; ?>/configurazione-email.php" class="<?php echo $current_page == 'configurazione-email.php' ? 'active' : ''; ?>">
                        <i class="fas fa-envelope"></i>
                        <span>Configurazioni</span>
                    </a>
                </div>
                <?php endif; ?>
                
                <!-- ACCOUNT -->
                <div class="menu-section-title">ACCOUNT</div>
                
                <div class="menu-item">
                    <a href="<?php echo APP_PATH; ?>/cambio-password.php" class="<?php echo $current_page == 'cambio-password.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user-circle"></i>
                        <span>Il Mio Profilo</span>
                    </a>
                </div>
                
                <div class="menu-item">
                    <a href="<?php echo APP_PATH; ?>/logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Esci</span>
                    </a>
                </div>
            </nav>
            
            <!-- User info -->
            <div class="user-info" style="margin-top: auto; padding: 20px; border-top: 1px solid rgba(255,255,255,0.1);">
                <div class="user-name"><?php echo htmlspecialchars($user['nome'] . ' ' . $user['cognome']); ?></div>
                <div class="user-role text-small text-muted"><?php echo htmlspecialchars(ucfirst($user['ruolo'])); ?></div>
                <?php if ($currentAzienda): ?>
                <div class="user-company text-small text-muted mt-1">
                    <i class="fas fa-building fa-sm"></i> <?php echo htmlspecialchars($currentAzienda['nome']); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Main content -->
        <div class="main-content">
            <?php if ($success_message): ?>
            <div class="alert alert-success fade-in">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
            <div class="alert alert-danger fade-in">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php endif; ?>
            
            <!-- Page content will be inserted here -->