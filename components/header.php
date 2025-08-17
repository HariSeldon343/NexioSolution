<?php
// Gestione componente header responsive
$isLightVersion = isset($lightVersion) && $lightVersion === true;
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? "Nexio Platform"); ?> - Nexio</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?php echo APP_PATH; ?>/assets/images/favicon.svg">
    
    <!-- Inter Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- FontAwesome 6 - Official CDN Only (NO KITS - they cause CORS issues) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <!-- FontAwesome 5 Fallback (for compatibility) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" integrity="sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    
    <!-- CSRF Token -->
    <?php 
    // Genera CSRF token se non esiste
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
    ?>
    <meta name="csrf-token" content="<?php echo $_SESSION["csrf_token"]; ?>">
    
    <!-- ===================================================== -->
    <!-- NEXIO MASTER CLEAN CSS - COMPLETE REPLACEMENT        -->
    <!-- This single CSS file REPLACES ALL other CSS files    -->
    <!-- Based on the original clean dashboard design         -->
    <!-- Provides consistent, clean styling across all pages  -->
    <!-- ===================================================== -->
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/nexio-master-clean.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/page-header-standard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/sidebar-gutter.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/sidebar-viewport-fix.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/sidebar-structure-fix.css?v=<?php echo time(); ?>">
    
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/nexio-no-animations.css?v=<?php echo time(); ?>">
    
    <!-- Nexio UI Fixes 2025 - Risolve problemi sidebar, badge e colori -->
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/nexio-ui-fixes-2025.css?v=<?php echo time(); ?>">
    
    <!-- Altri CSS se necessari -->
    <?php if (isset($additionalCSS) && is_array($additionalCSS)): ?>
        <?php foreach ($additionalCSS as $css): ?>
            <link rel="stylesheet" href="<?php echo $css; ?>?v=<?php echo time(); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Nexio Priority Override - DEVE essere caricato per ULTIMO -->
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/nexio-priority-override.css?v=<?php echo time(); ?>">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    

    
    <!-- Bootstrap Bundle (include Popper per i dropdown) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Minimal JavaScript - Only essential functionality -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Mobile menu toggle
        const mobileToggle = document.querySelector('.mobile-menu-toggle');
        const sidebar = document.querySelector('.sidebar');
        
        if (mobileToggle && sidebar) {
            mobileToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });
        }
    });
    </script>
    
</head>
<body class="<?php echo isset($bodyClass) ? htmlspecialchars($bodyClass) : ''; ?> <?php echo $auth->isSuperAdmin() ? 'super-admin' : ''; ?>">
    <?php if (!$isLightVersion): ?>
        <!-- Mobile Menu Toggle -->
        <button class="mobile-menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('active')">
            <i class="fas fa-bars"></i>
        </button>
        <?php include __DIR__ . "/sidebar.php"; ?>
        <div class="main-content">
    <?php else: ?>
        <div class="light-container">
    <?php endif; ?>
