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
    
    <!-- Icon Fallback CSS - Emergency fallback for icons and UI -->
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/nexio-icon-fallback.css?v=<?php echo time(); ?>">
    
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
    
    <!-- CSS Principale -->
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/style.css?v=<?php echo time(); ?>">
    
    <!-- Miglioramenti CSS incrementali -->
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/nexio-improvements.css?v=<?php echo time(); ?>">
    
    <!-- Fix colori e UI - Risolve problemi di contrasto e bottoni -->
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/nexio-color-fixes.css?v=<?php echo time(); ?>">
    
    <!-- Nexio UI Complete - Sistema completo di stili UI e responsiveness -->
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/nexio-ui-complete.css?v=<?php echo time(); ?>">
    
    <!-- Correzioni Urgenti - Fix critici per visibilità e responsiveness -->
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/nexio-urgent-fixes.css?v=<?php echo time(); ?>">
    
    <!-- Aggiustamenti Finali - Dimensioni testi, badge e padding -->
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/nexio-final-adjustments.css?v=<?php echo time(); ?>">
    
    <!-- Fix Sidebar - Logo, user info e dropdown -->
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/nexio-sidebar-fixes.css?v=<?php echo time(); ?>">
    
    <!-- Critical Fixes - Correzioni critiche finali per leggibilità -->
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/nexio-critical-fixes.css?v=<?php echo time(); ?>">
    
    <!-- EMERGENCY VISIBILITY FIX - Correzione urgente visibilità tabelle -->
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/nexio-visibility-emergency.css?v=<?php echo time(); ?>">
    
    <!-- ULTIMATE UI FIXES - Maximum priority overrides -->
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/nexio-ultimate-fixes.css?v=<?php echo time(); ?>">
    
    <!-- COMPREHENSIVE UI FIXES - Added 2025-08-11 -->
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/nexio-ui-fixes.css?v=<?php echo time(); ?>">
    
    <!-- COMPLETE UI OVERHAUL FIXES - Added 2025-08-11 -->
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/nexio-ui-comprehensive-fix.css?v=<?php echo time(); ?>">
    
    <!-- COMPLETE UI FIXES - Final comprehensive fixes - Added 2025-08-11 -->
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/nexio-complete-ui-fixes.css?v=<?php echo time(); ?>">
    
    <!-- BUTTON WHITE TEXT FIX - Forces white text on all primary buttons - Added 2025-08-11 -->
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/nexio-button-white-text.css?v=<?php echo time(); ?>">
    
    <!-- HEADINGS WHITE TEXT FIX - Forces white text on h2 calendar titles - Added 2025-08-11 -->
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/nexio-headings-white.css?v=<?php echo time(); ?>">
    
    <!-- BUTTON SIZE FIX - Normalizes button and icon sizes - Added 2025-08-11 -->
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/nexio-button-size-fix.css?v=<?php echo time(); ?>">
    
    <!-- BUTTON ALIGNMENT FIX - Fixes icon alignment and text truncation - Added 2025-08-11 -->
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/nexio-button-alignment-fix.css?v=<?php echo time(); ?>">
    
    <!-- CARD SIZE FIX - Reduces company cards to max-width 350px - Added 2025-08-11 -->
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/nexio-card-size-fix.css?v=<?php echo time(); ?>">
    
    <!-- TABLE SIMPLE - Clean simple table styles - Added 2025-08-11 -->
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/nexio-table-simple.css?v=<?php echo time(); ?>">
    
    <!-- LOG DETAILS FIX - Fixes narrow expandable log details panel - Added 2025-08-11 -->
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/log-details-fix.css?v=<?php echo time(); ?>">
    
    <!-- Altri CSS se necessari -->
    <?php if (isset($additionalCSS) && is_array($additionalCSS)): ?>
        <?php foreach ($additionalCSS as $css): ?>
            <link rel="stylesheet" href="<?php echo $css; ?>?v=<?php echo time(); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap Bundle (include Popper per i dropdown) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Fix Dropdown Sidebar -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Fix per il dropdown nella sidebar
        const dropdownBtn = document.querySelector('.sidebar-footer .btn-icon');
        if (dropdownBtn) {
            dropdownBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const dropdown = this.closest('.dropdown');
                const menu = dropdown.querySelector('.dropdown-menu');
                
                // Toggle menu
                dropdown.classList.toggle('show');
                menu.classList.toggle('show');
                
                // Chiudi quando si clicca fuori
                document.addEventListener('click', function closeDropdown(event) {
                    if (!dropdown.contains(event.target)) {
                        dropdown.classList.remove('show');
                        menu.classList.remove('show');
                        document.removeEventListener('click', closeDropdown);
                    }
                });
            });
        }
    });
    </script>
    
    <!-- JavaScript per fix visibilità emergenza -->
    <script src="<?php echo APP_PATH; ?>/assets/js/nexio-visibility-fix.js?v=<?php echo time(); ?>"></script>
    
    <!-- UI ENHANCEMENTS JavaScript - Added 2025-08-11 -->
    <script src="<?php echo APP_PATH; ?>/assets/js/nexio-ui-enhancements.js?v=<?php echo time(); ?>"></script>
    
    <!-- FORCE STYLES JavaScript - Emergency style application -->
    <script src="<?php echo APP_PATH; ?>/assets/js/nexio-force-styles.js?v=<?php echo time(); ?>"></script>
    
    <!-- ICON FIX JavaScript - Emergency fallback for FontAwesome icons -->
    <script src="<?php echo APP_PATH; ?>/assets/js/nexio-icon-fix.js?v=<?php echo time(); ?>"></script>
    
    <!-- BUTTON FIX JavaScript - Forces white text on primary buttons -->
    <script src="<?php echo APP_PATH; ?>/assets/js/nexio-button-fix.js?v=<?php echo time(); ?>"></script>
    
    <!-- HEADING FIX JavaScript - Forces white text on calendar headings -->
    <script src="<?php echo APP_PATH; ?>/assets/js/nexio-heading-fix.js?v=<?php echo time(); ?>"></script>
    
    <!-- BUTTON SIZE FIX JavaScript - Normalizes oversized buttons and icons -->
    <script src="<?php echo APP_PATH; ?>/assets/js/nexio-button-size-fix.js?v=<?php echo time(); ?>"></script>
    
    <!-- TABLE RESET JavaScript FIXED - Complete table rebuild without errors -->
    <script src="<?php echo APP_PATH; ?>/assets/js/nexio-table-reset-fixed.js?v=<?php echo time(); ?>"></script>
    
    <!-- CRITICAL UI FIXES - Emergency inline styles for icons and buttons -->
    <style>
        /* Force FontAwesome icons to display */
        .fa, .fas, .far, .fal, .fad, .fab {
            display: inline-block !important;
            font-style: normal !important;
            font-variant: normal !important;
            text-rendering: auto !important;
            line-height: 1 !important;
            -webkit-font-smoothing: antialiased !important;
            -moz-osx-font-smoothing: grayscale !important;
        }
        
        /* Fallback for missing icons - use Unicode symbols */
        .fa-bars:before { content: "☰" !important; font-family: inherit !important; }
        .fa-home:before { content: "⌂" !important; font-family: inherit !important; }
        .fa-users:before { content: "👥" !important; font-family: inherit !important; }
        .fa-file:before { content: "📄" !important; font-family: inherit !important; }
        .fa-folder:before { content: "📁" !important; font-family: inherit !important; }
        .fa-calendar:before { content: "📅" !important; font-family: inherit !important; }
        .fa-bell:before { content: "🔔" !important; font-family: inherit !important; }
        .fa-cog:before { content: "⚙" !important; font-family: inherit !important; }
        .fa-sign-out-alt:before { content: "⇦" !important; font-family: inherit !important; }
        .fa-plus:before { content: "+" !important; font-family: inherit !important; font-weight: bold !important; }
        .fa-edit:before { content: "✎" !important; font-family: inherit !important; }
        .fa-trash:before { content: "🗑" !important; font-family: inherit !important; }
        .fa-download:before { content: "⇩" !important; font-family: inherit !important; }
        .fa-upload:before { content: "⇧" !important; font-family: inherit !important; }
        .fa-search:before { content: "🔍" !important; font-family: inherit !important; }
        .fa-times:before { content: "✕" !important; font-family: inherit !important; }
        .fa-check:before { content: "✓" !important; font-family: inherit !important; }
        .fa-exclamation-triangle:before { content: "⚠" !important; font-family: inherit !important; }
        .fa-info-circle:before { content: "ⓘ" !important; font-family: inherit !important; }
        
        /* Button styling with maximum specificity */
        body .btn,
        body button.btn,
        body a.btn,
        body input[type="button"].btn,
        body input[type="submit"].btn {
            display: inline-block !important;
            padding: 0.5rem 1rem !important;
            font-size: 0.875rem !important;
            font-weight: 500 !important;
            line-height: 1.5 !important;
            border-radius: 0.375rem !important;
            text-align: center !important;
            text-decoration: none !important;
            vertical-align: middle !important;
            cursor: pointer !important;
            user-select: none !important;
            border: 1px solid transparent !important;
            transition: all 0.15s ease-in-out !important;
        }
        
        /* Primary button - FORCE WHITE TEXT */
        body .btn-primary,
        body button.btn-primary,
        body input.btn-primary,
        body a.btn-primary,
        .btn-primary,
        button.btn-primary,
        input[type="submit"].btn-primary,
        [class*="btn-primary"] {
            color: #ffffff !important;
            background-color: #0d6efd !important;
            border-color: #0d6efd !important;
        }
        
        /* Force white text on all children */
        body .btn-primary *,
        .btn-primary i,
        .btn-primary span {
            color: #ffffff !important;
        }
        
        body .btn-primary:hover {
            color: #ffffff !important;
            background-color: #0b5ed7 !important;
            border-color: #0a58ca !important;
        }
        
        /* Override inline styles that set blue text */
        .btn-primary[style*="color: rgb(45, 90, 159)"],
        .btn-primary[style*="background: white"] {
            color: #ffffff !important;
            background-color: #0d6efd !important;
        }
        
        /* Success button */
        body .btn-success {
            color: #fff !important;
            background-color: #198754 !important;
            border-color: #198754 !important;
        }
        
        body .btn-success:hover {
            background-color: #157347 !important;
            border-color: #146c43 !important;
        }
        
        /* Danger button - FORCE WHITE TEXT */
        body .btn-danger,
        body button.btn-danger,
        body input.btn-danger,
        body a.btn-danger,
        .btn-danger,
        button.btn-danger,
        input[type="submit"].btn-danger,
        [class*="btn-danger"] {
            color: #ffffff !important;
            background-color: #dc3545 !important;
            border-color: #dc3545 !important;
        }
        
        /* Force white text on danger button children */
        body .btn-danger *,
        .btn-danger i,
        .btn-danger span {
            color: #ffffff !important;
        }
        
        body .btn-danger:hover {
            color: #ffffff !important;
            background-color: #bb2d3b !important;
            border-color: #b02a37 !important;
        }
        
        /* Specific for delete buttons */
        button[onclick*="delete"] {
            color: #ffffff !important;
        }
        
        button[onclick*="delete"] * {
            color: #ffffff !important;
        }
        
        /* Secondary button - FORCE WHITE TEXT */
        body .btn-secondary,
        body button.btn-secondary,
        body input.btn-secondary,
        body a.btn-secondary,
        .btn-secondary,
        button.btn-secondary,
        input[type="submit"].btn-secondary,
        [class*="btn-secondary"] {
            color: #ffffff !important;
            background-color: #6c757d !important;
            border-color: #6c757d !important;
        }
        
        /* Force white text on secondary button children */
        body .btn-secondary *,
        .btn-secondary i,
        .btn-secondary span {
            color: #ffffff !important;
        }
        
        body .btn-secondary:hover {
            color: #ffffff !important;
            background-color: #5c636a !important;
            border-color: #565e64 !important;
        }
        
        /* Small buttons */
        body .btn-sm {
            padding: 0.25rem 0.5rem !important;
            font-size: 0.75rem !important;
        }
        
        /* CRITICAL: Fix oversized buttons and icons */
        body .btn {
            max-height: 38px !important;
            padding: 0.375rem 0.75rem !important;
            font-size: 0.875rem !important;
        }
        
        body .btn i,
        body .btn .fa,
        body .btn .fas {
            font-size: 0.875rem !important;
            line-height: 1 !important;
            vertical-align: middle !important;
        }
        
        /* Fix specific ticket button - ENHANCED */
        a[href*="tickets.php?action=nuovo"],
        a[href*="action=nuovo"] {
            display: inline-flex !important;
            align-items: center !important;
            padding: 0.5rem 1rem !important;
            font-size: 0.875rem !important;
            height: 38px !important;
            width: auto !important;
            white-space: nowrap !important;
            overflow: visible !important;
            max-height: none !important;
        }
        
        a[href*="tickets.php?action=nuovo"] i,
        a[href*="action=nuovo"] i {
            display: inline-flex !important;
            align-items: center !important;
            vertical-align: middle !important;
            font-size: 0.875rem !important;
            margin-right: 0.375rem !important;
            line-height: 1 !important;
        }
        
        /* Global button flex alignment */
        .btn {
            display: inline-flex !important;
            align-items: center !important;
            width: auto !important;
            gap: 0.375rem !important;
        }
        
        /* Icon buttons */
        body .btn-icon {
            padding: 0.375rem 0.75rem !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
        }
        
        /* Ensure modals don't auto-open */
        .modal {
            display: none !important;
        }
        
        .modal.show {
            display: block !important;
        }
        
        /* Force white text on calendar headings */
        h2:contains("2025"),
        h2:contains("2024"),
        h2:contains("2026"),
        .calendar h2,
        .calendario h2,
        #calendar h2,
        #calendario h2,
        .fc-toolbar-title,
        h2[class*="month"],
        h2[class*="year"],
        h2[class*="date"] {
            color: #ffffff !important;
        }
        
        /* Specific month names */
        h2 {
            /* Will be handled by JavaScript for dynamic content */
        }
        
        /* Fix modal backdrop */
        .modal-backdrop {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            z-index: 1040 !important;
            width: 100vw !important;
            height: 100vh !important;
            background-color: rgba(0, 0, 0, 0.5) !important;
        }
        
    </style>
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
