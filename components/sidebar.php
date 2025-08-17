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

<aside class="sidebar">
    <!-- Header Sidebar -->
    <div class="sidebar-header">
        <a href="<?php echo APP_PATH; ?>/dashboard.php" class="sidebar-logo">
            <div class="logo-wrapper">
                <img src="<?php echo APP_PATH; ?>/assets/images/nexio-icon.svg?v=<?php echo @filemtime(dirname(__DIR__) . '/assets/images/nexio-icon.svg'); ?>" alt="Nexio">
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
        <div class="user-info w-100 d-flex flex-column">
            <!-- Prima riga: nome utente (con o senza avatar) -->
            <div class="user-name-row d-flex align-items-center gap-2 mb-1">
                <div class="user-avatar-compact">
                    <?php 
                    // Gestione corretta delle iniziali per nomi multipli
                    $nome = trim($user['nome'] ?? '');
                    $cognome = trim($user['cognome'] ?? '');
                    $initials = '';
                    
                    // Prima iniziale dal primo nome
                    if ($nome) {
                        $nomiParts = explode(' ', $nome);
                        $initials = strtoupper(substr($nomiParts[0], 0, 1));
                    }
                    
                    // Seconda iniziale dal cognome (o secondo nome se presente)
                    if ($nome && strpos($nome, ' ') !== false) {
                        // Se il nome contiene spazi (es. "Antonio Silverstro"), prendi la seconda parte
                        $nomiParts = explode(' ', $nome);
                        if (count($nomiParts) > 1) {
                            $initials .= strtoupper(substr($nomiParts[1], 0, 1));
                        }
                    } elseif ($cognome) {
                        // Altrimenti usa il cognome
                        $initials .= strtoupper(substr($cognome, 0, 1));
                    }
                    
                    echo $initials ?: 'U';
                    ?>
                </div>
                <div class="user-name flex-grow-1 text-truncate">
                    <?php 
                    $fullName = trim(($nome ? $nome : '') . ($cognome ? ' ' . $cognome : ''));
                    echo htmlspecialchars($fullName ?: 'Utente'); 
                    ?>
                </div>
            </div>
            <!-- Seconda riga: solo badge centrato -->
            <div class="user-controls-row d-flex align-items-center justify-content-center">
                <?php 
                $isSuperAdmin = $auth->isSuperAdmin();
                if ($isSuperAdmin) {
                    echo '<div class="badge d-inline-flex justify-content-center align-items-center gap-1 px-3 py-2 mb-2" style="width:auto;">';
                    echo '<i class="fas fa-shield-alt"></i><span>SUPER ADMIN</span>';
                    echo '</div>';
                } else {
                    $role = $user['ruolo'] ?? 'Utente';
                    echo '<div class="badge role-' . strtolower($role) . ' d-inline-flex justify-content-center align-items-center gap-1 px-3 py-2 mb-2" style="width:auto;">';
                    echo '<i class="fas fa-user"></i><span>' . htmlspecialchars(strtoupper($role)) . '</span>';
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </div>
</aside>

<!-- Sidebar assets are now included in the master CSS -->