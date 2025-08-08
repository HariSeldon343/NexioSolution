<?php
/**
 * Nexio Sidebar Component
 * Sidebar standardizzata per tutte le pagine della piattaforma
 */

// Verifica che l'utente sia autenticato
$auth = Auth::getInstance();
if (!$auth->isAuthenticated()) {
    return;
}

// Include MenuHelper per la gestione del menu
if (!class_exists('MenuHelper')) {
    require_once dirname(__DIR__) . '/backend/utils/MenuHelper.php';
}

// Informazioni utente e pagina corrente
$user = $auth->getUser();
$currentPage = basename($_SERVER['PHP_SELF']);

// Ottieni configurazione menu
$menuConfig = MenuHelper::getMenuConfiguration($auth);
$groupedMenu = MenuHelper::groupMenuBySection($menuConfig);
$sectionTitles = MenuHelper::getSectionTitles();

/**
 * Renderizza un menu item
 */
function renderMenuItem($item, $currentPage, $key = '') {
    $isActive = MenuHelper::isActivePage($item['url'], $currentPage, $item['aliases'] ?? []);
    $activeClass = $isActive ? 'active' : '';
    $style = isset($item['color']) ? "color: {$item['color']};" : '';
    $onclick = isset($item['onclick']) ? "onclick=\"{$item['onclick']}\"" : '';
    
    echo '<div class="menu-item">';
    echo "<a href=\"" . APP_PATH . "/{$item['url']}\" class=\"{$activeClass}\" style=\"{$style}\" {$onclick}>";
    echo "<i class=\"{$item['icon']}\"></i>";
    echo "<span>{$item['title']}</span>";
    echo '</a>';
    echo '</div>';
}

/**
 * Renderizza una sezione del menu
 */
function renderMenuSection($title) {
    if (!empty($title)) {
        echo '<div class="menu-section-title">' . htmlspecialchars($title) . '</div>';
    }
}
?>

<aside class="sidebar">
    <!-- Header Sidebar -->
    <div class="sidebar-header">
        <img src="<?php echo APP_PATH; ?>/assets/images/nexio-icon.svg" alt="Nexio Logo" class="sidebar-logo">
        <h2>✦ Nexio</h2>
        <p>Semplifica, Connetti, Cresci Insieme</p>
    </div>
    
    <!-- Menu Navigazione -->
    <nav class="sidebar-menu">
        <?php
        // Renderizza menu per sezioni
        $sectionsOrder = ['main', 'operativa', 'gestione', 'amministrazione', 'account'];
        
        foreach ($sectionsOrder as $sectionKey) {
            if (empty($groupedMenu[$sectionKey])) {
                continue;
            }
            
            // Renderizza titolo sezione
            renderMenuSection($sectionTitles[$sectionKey]);
            
            // Renderizza menu items della sezione
            foreach ($groupedMenu[$sectionKey] as $key => $item) {
                renderMenuItem($item, $currentPage, $key);
            }
        }
        ?>
    </nav>
    
    <!-- Info Utente -->
    <div class="user-info">
        <div class="user-name">
            <?php 
            $fullName = trim(($user['nome'] ?? '') . ' ' . ($user['cognome'] ?? ''));
            echo htmlspecialchars($fullName ?: 'Utente');
            ?>
        </div>
        <div class="user-role">
            <?php 
            $isSuperAdmin = $auth->isSuperAdmin();
            if ($isSuperAdmin) {
                echo 'Super Admin';
            } else {
                $role = $user['ruolo'] ?? 'Utente';
                echo htmlspecialchars(ucfirst($role));
            }
            ?>
        </div>
        
        <!-- Info Azienda se presente -->
        <?php 
        $currentAzienda = $auth->getCurrentAzienda();
        if ($currentAzienda && !empty($currentAzienda['azienda_nome'])): 
        ?>
        <div class="user-company">
            <i class="fas fa-building" style="margin-right: 5px; color: #a0aec0;"></i>
            <?php echo htmlspecialchars($currentAzienda['azienda_nome']); ?>
            
            <!-- Link per cambiare azienda se ce ne sono più di una -->
            <?php if (count($auth->getUserAziende()) > 1): ?>
            <div style="margin-top: 5px;">
                <a href="<?php echo APP_PATH; ?>/seleziona-azienda.php" 
                   style="color: #4299e1; font-size: 11px; text-decoration: none;">
                    <i class="fas fa-exchange-alt"></i> Cambia Azienda
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</aside>

<!-- CSS e JS Sidebar - Include solo se non già presente -->
<?php if (!defined('SIDEBAR_ASSETS_INCLUDED')): ?>
<?php define('SIDEBAR_ASSETS_INCLUDED', true); ?>
<link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/sidebar-responsive.css?v=<?php echo time(); ?>">
<script src="<?php echo APP_PATH; ?>/assets/js/sidebar-mobile.js?v=<?php echo time(); ?>" defer></script>
<?php endif; ?>
<style>
    /* Sidebar Styles */
    .sidebar {
        width: 250px;
        background: #2d3748;
        color: white;
        padding: 20px;
        position: fixed;
        height: 100vh;
        overflow-y: auto;
        z-index: 1000;
        left: 0;
        top: 0;
    }
    
    .sidebar-header {
        margin-bottom: 30px;
        text-align: center;
        padding: 10px;
    }
    
    .sidebar-logo {
        width: 60px !important;
        height: 60px !important;
        display: block !important;
        object-fit: contain !important;
        margin: 0 auto 15px auto !important;
        filter: none !important;
    }
    
    .sidebar-header h2 {
        color: #4299e1;
        font-size: 24px;
        margin-bottom: 5px;
        display: none; /* Nascondi quando c'è il logo */
    }
    
    .sidebar-header p {
        font-size: 12px;
        color: #a0aec0;
        margin: 0;
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
        font-size: 14px;
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
        font-size: 16px;
    }
    
    .menu-section-title {
        font-size: 12px;
        color: #a0aec0;
        margin-bottom: 15px;
        margin-top: 20px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5px;
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
        font-size: 14px;
    }
    
    .user-role {
        font-size: 12px;
        color: #a0aec0;
        margin-bottom: 8px;
    }
    
    .user-company {
        font-size: 11px;
        color: #cbd5e0;
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px solid #2d3748;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .sidebar {
            width: 100%;
            position: relative;
            height: auto;
        }
    }
    
    /* Layout principale con sidebar */
    .app-container {
        display: flex;
        min-height: 100vh;
        background: #f7fafc;
    }
    
    .main-content {
        margin-left: 250px;
        padding: 20px;
        background: #f7fafc;
        min-height: 100vh;
        width: calc(100vw - 250px);
        flex: 1;
    }
    
    @media (max-width: 768px) {
        .main-content {
            margin-left: 0;
            width: 100%;
        }
    }
    
    /* Scrollbar personalizzata per la sidebar */
    .sidebar::-webkit-scrollbar {
        width: 6px;
    }
    
    .sidebar::-webkit-scrollbar-track {
        background: #1a202c;
    }
    
    .sidebar::-webkit-scrollbar-thumb {
        background: #4a5568;
        border-radius: 3px;
    }
    
    .sidebar::-webkit-scrollbar-thumb:hover {
        background: #718096;
    }
</style>