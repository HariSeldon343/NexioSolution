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
    $onclick = isset($item['onclick']) ? "onclick=\"{$item['onclick']}\"" : '';
    
    echo "<a href=\"" . APP_PATH . "/{$item['url']}\" class=\"menu-item {$activeClass}\" {$onclick}>";
    echo "<i class=\"{$item['icon']}\" style=\"font-family: 'Font Awesome 6 Free', 'Font Awesome 5 Free'; font-weight: 900; display: inline-block; width: 20px; margin-right: 10px; text-align: center;\"></i>";
    echo "<span>{$item['title']}</span>";
    echo '</a>';
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

<aside class="sidebar" style="background: #162d4f !important;">
    <!-- Header Sidebar -->
    <div class="sidebar-header">
        <a href="<?php echo APP_PATH; ?>/dashboard.php" class="sidebar-logo">
            <div class="logo-wrapper">
                <img src="<?php echo APP_PATH; ?>/assets/images/nexio-icon.svg" alt="Nexio">
            </div>
            <div class="sidebar-logo-text">
                <span class="logo-title">NEXIO</span>
                <span class="logo-motto">Semplifica, Connetti, Cresci Insieme</span>
            </div>
        </a>
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
            
            // Nessun separatore tra sezioni per un design più pulito
            
            // Renderizza titolo sezione
            renderMenuSection($sectionTitles[$sectionKey]);
            
            // Renderizza menu items della sezione
            foreach ($groupedMenu[$sectionKey] as $key => $item) {
                renderMenuItem($item, $currentPage, $key);
            }
        }
        ?>
    </nav>
    
    <!-- Footer Sidebar con Info Utente -->
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <?php 
                $fullName = trim(($user['nome'] ?? '') . ' ' . ($user['cognome'] ?? ''));
                $initials = '';
                if ($fullName) {
                    $parts = explode(' ', $fullName);
                    $initials = strtoupper(substr($parts[0], 0, 1));
                    if (isset($parts[1])) {
                        $initials .= strtoupper(substr($parts[1], 0, 1));
                    }
                }
                echo $initials ?: 'U';
                ?>
            </div>
            <div class="user-details">
                <div class="user-name" title="<?php echo htmlspecialchars($fullName ?: 'Utente'); ?>" style="white-space: normal; overflow-wrap: break-word; line-height: 1.2; -webkit-line-clamp: 2; -webkit-box-orient: vertical; display: -webkit-box;">
                    <?php echo htmlspecialchars($fullName ?: 'Utente'); ?>
                </div>
                <div class="user-role" style="display: flex; align-items: center;">
                    <?php 
                    $isSuperAdmin = $auth->isSuperAdmin();
                    if ($isSuperAdmin) {
                        echo '<i class="fas fa-shield-alt" style="color: rgba(255,255,255,0.7); margin-right: 4px; font-size: 0.75rem; font-family: \'Font Awesome 6 Free\', \'Font Awesome 5 Free\'; font-weight: 900;"></i>';
                        echo '<span style="background-color: #0d6efd; color: white; padding: 0.25rem 0.5rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; display: inline-block;">Super Admin</span>';
                    } else {
                        $role = $user['ruolo'] ?? 'Utente';
                        echo '<i class="fas fa-user" style="color: rgba(255,255,255,0.7); margin-right: 4px; font-size: 0.75rem; font-family: \'Font Awesome 6 Free\', \'Font Awesome 5 Free\'; font-weight: 900;"></i>';
                        echo '<span style="background-color: #6c757d; color: white; padding: 0.25rem 0.5rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; display: inline-block;">' . htmlspecialchars(ucfirst($role)) . '</span>';
                    }
                    ?>
                </div>
            </div>
            
            <!-- Menu dropdown utente -->
            <div class="dropdown">
                <button class="btn-icon" data-bs-toggle="dropdown">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?php echo APP_PATH; ?>/profilo.php"><i class="fas fa-user-circle"></i> Profilo</a></li>
                    <?php 
                    // Verifica se l'utente ha più aziende
                    try {
                        $userAziende = [];
                        if (isset($user['id'])) {
                            $stmt = db_query("SELECT COUNT(*) as count FROM utenti_aziende WHERE utente_id = ?", [$user['id']]);
                            $count = $stmt ? $stmt->fetch()['count'] : 0;
                            if ($count > 1): ?>
                                <li><a class="dropdown-item" href="<?php echo APP_PATH; ?>/seleziona-azienda.php"><i class="fas fa-exchange-alt"></i> Cambia Azienda</a></li>
                            <?php endif;
                        }
                    } catch (Exception $e) {
                        // Ignora errori
                    }
                    ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?php echo APP_PATH; ?>/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</aside>

<!-- CSS e JS Sidebar - Include solo se non già presente -->
<?php if (!defined('SIDEBAR_ASSETS_INCLUDED')): ?>
<?php define('SIDEBAR_ASSETS_INCLUDED', true); ?>
<link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/sidebar-responsive.css?v=<?php echo time(); ?>">
<?php endif; ?>
<style>
    /* Sidebar - Design Minimale */
    .sidebar {
        width: 260px !important;
        background: #162d4f !important;
        color: white !important;
        padding: 0 !important;
        position: fixed !important;
        height: 100vh !important;
        overflow-y: auto !important;
        z-index: 1000 !important;
        left: 0 !important;
        top: 0 !important;
        display: flex !important;
        flex-direction: column !important;
        border-right: 1px solid rgba(255,255,255,0.1) !important;
    }
    
    .sidebar-header {
        padding: 1.5rem !important;
        border-bottom: 1px solid rgba(255,255,255,0.1) !important;
    }
    
    .sidebar-logo {
        display: flex !important;
        align-items: center !important;
        gap: 0.75rem !important;
        color: white !important;
        text-decoration: none !important;
        transition: opacity 0.15s ease !important;
    }
    
    .sidebar-logo:hover {
        opacity: 0.9;
    }
    
    .sidebar-logo img {
        width: 56px !important;
        height: 56px !important;
        border-radius: 4px !important;
        background: transparent !important;
        padding: 0 !important;
        filter: brightness(0) invert(1) !important;
    }
    
    .sidebar-logo span {
        font-size: 1.25rem !important;
        font-weight: 500 !important;
        letter-spacing: 0.025em !important;
        color: white !important;
        text-transform: uppercase !important;
    }
    
    .sidebar-menu {
        flex: 1 !important;
        padding: 1rem 0 !important;
        overflow-y: auto !important;
    }
    
    .menu-item {
        display: block !important;
        padding: 0.75rem 1.5rem !important;
        color: rgba(255,255,255,0.7) !important;
        text-decoration: none !important;
        transition: opacity 0.15s ease !important;
        position: relative !important;
        font-size: 0.875rem !important;
        font-weight: 400 !important;
    }
    
    .menu-item:hover {
        background: rgba(255,255,255,0.05) !important;
        color: white !important;
    }
    
    .menu-item.active {
        background: rgba(255,255,255,0.08) !important;
        color: white !important;
        border-left: 2px solid white !important;
    }
    
    .menu-item.active::before {
        content: '' !important;
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        bottom: 0 !important;
        width: 2px !important;
        background: white !important;
    }
    
    .menu-item i {
        width: 20px !important;
        margin-right: 10px !important;
        font-size: 0.875rem !important;
        vertical-align: middle !important;
        opacity: 0.8 !important;
    }
    
    .menu-separator {
        height: 1px !important;
        background: rgba(255,255,255,0.08) !important;
        margin: 0.5rem 1.5rem !important;
    }
    
    .menu-section-title {
        font-size: 0.625rem !important;
        color: rgba(255,255,255,0.4) !important;
        margin: 1rem 1.5rem 0.5rem !important;
        font-weight: 400 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.1em !important;
    }
    
    .sidebar-footer {
        padding: 1rem 1.5rem !important;
        border-top: 1px solid rgba(255,255,255,0.1) !important;
    }
    
    .user-info {
        display: flex !important;
        align-items: center !important;
        gap: 0.75rem !important;
        color: white !important;
        padding: 0.5rem 0 !important;
    }
    
    .user-avatar {
        width: 36px !important;
        height: 36px !important;
        border-radius: 4px !important;
        background: rgba(255,255,255,0.1) !important;
        border: 1px solid rgba(255,255,255,0.2) !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-weight: 400 !important;
        font-size: 0.875rem !important;
        color: rgba(255,255,255,0.9) !important;
        text-transform: uppercase !important;
    }
    
    .user-details {
        flex: 1 !important;
    }
    
    .user-name {
        font-weight: 400 !important;
        font-size: 0.875rem !important;
        color: rgba(255,255,255,0.9) !important;
        margin-bottom: 0.125rem !important;
    }
    
    .user-role {
        font-size: 0.75rem !important;
        color: rgba(255,255,255,0.5) !important;
        text-transform: uppercase !important;
        letter-spacing: 0.025em !important;
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
        margin-left: 260px !important;
        padding: 2rem !important;
        background: #ffffff !important;
        min-height: 100vh !important;
        width: calc(100vw - 260px) !important;
        flex: 1 !important;
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