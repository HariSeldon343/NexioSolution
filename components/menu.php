<?php
// Include ModulesHelper
require_once __DIR__ . '/../backend/utils/ModulesHelper.php';

// Verifica che l'utente sia autenticato
$auth = Auth::getInstance();
if (!$auth->isAuthenticated()) {
    return;
}

$currentPage = basename($_SERVER['PHP_SELF']);
$user = $auth->getUser();
$isSuperAdmin = $auth->isSuperAdmin();
$currentAzienda = $auth->getCurrentAzienda();

// Menu items con controllo permessi
$menuItems = [
    [
        'title' => 'Dashboard',
        'icon' => 'fas fa-tachometer-alt',
        'url' => 'dashboard.php',
        'visible' => true
    ],
    [
        'title' => 'Aziende',
        'icon' => 'fas fa-building',
        'url' => 'aziende.php',
        'visible' => $isSuperAdmin
    ],
    [
        'title' => 'Calendario',
        'icon' => 'fas fa-calendar-alt',
        'url' => 'calendario.php',
        'visible' => true
    ],
    [
        'title' => 'Documenti',
        'icon' => 'fas fa-file-alt',
        'url' => 'documenti.php',
        'visible' => $auth->canAccess('documents', 'read')
    ],
    [
        'title' => 'Gestione Documentale',
        'icon' => 'fas fa-folder-tree',
        'url' => 'gestione-documentale.php',
        'visible' => true // Visibile a tutti gli utenti autenticati
    ],
    [
        'title' => 'File Manager',
        'icon' => 'fas fa-folder-open',
        'url' => 'filesystem.php',
        'visible' => ModulesHelper::isModuleEnabled('FILESYSTEM', $auth->getCurrentCompany())
    ],
    [
        'title' => 'Sistema ISO',
        'icon' => 'fas fa-certificate',
        'submenu' => [
            [
                'title' => 'Stato Sistema ISO',
                'icon' => 'fas fa-info-circle',
                'url' => 'iso-system-status.php',
                'visible' => $auth->hasElevatedPrivileges()
            ],
            [
                'title' => 'Setup ISO',
                'icon' => 'fas fa-cog',
                'url' => 'setup-iso-document-system.php',
                'visible' => $isSuperAdmin
            ],
            [
                'title' => 'Struttura Conformità',
                'icon' => 'fas fa-sitemap',
                'url' => 'inizializza-struttura-conformita.php',
                'visible' => $auth->hasElevatedPrivileges()
            ],
            [
                'title' => 'Classificazioni ISO',
                'icon' => 'fas fa-tags',
                'url' => 'gestione-classificazioni.php',
                'visible' => $isSuperAdmin || $auth->hasRoleInAzienda('proprietario') || $auth->hasRoleInAzienda('admin')
            ]
        ],
        'visible' => $auth->canAccess('documents', 'read')
    ],
    [
        'title' => 'Classificazioni',
        'icon' => 'fas fa-tags',
        'url' => 'gestione-classificazioni.php',
        'visible' => $isSuperAdmin || $auth->hasRoleInAzienda('proprietario') || $auth->hasRoleInAzienda('admin')
    ],
    [
        'title' => 'Template Documenti',
        'icon' => 'fas fa-file-code',
        'url' => 'gestione-moduli-template.php',
        'visible' => $isSuperAdmin || $auth->hasRoleInAzienda('proprietario') || $auth->hasRoleInAzienda('admin')
    ],
    [
        'title' => 'Tickets',
        'icon' => 'fas fa-ticket-alt',
        'url' => 'tickets.php',
        'visible' => $auth->canAccess('tickets')
    ],
    [
        'title' => 'Gestione Utenti',
        'icon' => 'fas fa-users',
        'url' => 'gestione-utenti.php',
        'visible' => $isSuperAdmin || $auth->hasRoleInAzienda('proprietario') || $auth->hasRoleInAzienda('admin')
    ],
    [
        'title' => 'Moduli',
        'icon' => 'fas fa-clipboard-list',
        'url' => 'gestione-moduli.php',
        'visible' => $auth->canAccess('moduli')
    ],
    [
        'title' => 'Nexio AI',
        'icon' => 'fas fa-robot',
        'url' => 'nexio-ai.php',
        'visible' => true // Temporaneamente visibile a tutti per test
    ],
    [
        'title' => 'Log',
        'icon' => 'fas fa-history',
        'url' => 'log-attivita.php',
        'visible' => $auth->canAccess('logs')
    ],
    [
        'title' => 'Configurazione Email',
        'icon' => 'fas fa-envelope',
        'url' => 'configurazione-email.php',
        'visible' => $isSuperAdmin // Solo super admin
    ],
    [
        'title' => 'Profilo',
        'icon' => 'fas fa-user',
        'url' => 'profilo.php',
        'visible' => true
    ],
    [
        'title' => 'Logout',
        'icon' => 'fas fa-sign-out-alt',
        'url' => 'logout.php',
        'visible' => true
    ]
];
?>

<nav class="sidebar">
    <div class="sidebar-header">
                    <div class="logo" style="display: flex; justify-content: center; align-items: center; width: 100%;">
                <img src="<?php echo APP_PATH; ?>/assets/images/nexio-logo.svg" alt="Nexio Logo" style="max-width: 200px; display: block;">
            </div>
    </div>
    
    <div class="user-info">
        <div class="user-avatar">
            <i class="fas fa-user"></i>
        </div>
        <div class="user-details">
            <div class="user-name"><?php echo htmlspecialchars($user['nome'] . ' ' . $user['cognome']); ?></div>
            <div class="user-role">
                <?php 
                if ($isSuperAdmin) {
                    echo 'Super Admin';
                } elseif ($currentAzienda) {
                    echo ucfirst($currentAzienda['ruolo_azienda']);
                } else {
                    echo ucfirst($user['ruolo']);
                }
                ?>
            </div>
        </div>
    </div>
    
    <div class="menu-section">
        <div class="menu-title">MENU PRINCIPALE</div>
        <ul class="menu-items">
            <?php foreach ($menuItems as $item): ?>
                <?php if ($item['visible']): ?>
                    <?php if (isset($item['submenu'])): ?>
                        <li class="menu-item has-submenu">
                            <a href="javascript:void(0);" onclick="toggleSubmenu(this)">
                                <i class="<?php echo $item['icon']; ?>"></i>
                                <span><?php echo $item['title']; ?></span>
                                <i class="fas fa-chevron-down submenu-arrow"></i>
                            </a>
                            <ul class="submenu">
                                <?php foreach ($item['submenu'] as $subitem): ?>
                                    <?php if (isset($subitem['visible']) && $subitem['visible']): ?>
                                        <li class="submenu-item <?php echo $currentPage === basename($subitem['url']) ? 'active' : ''; ?>">
                                            <a href="<?php echo $subitem['url']; ?>">
                                                <i class="<?php echo $subitem['icon']; ?>"></i>
                                                <span><?php echo $subitem['title']; ?></span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="menu-item <?php echo $currentPage === basename($item['url']) ? 'active' : ''; ?>">
                            <a href="<?php echo $item['url']; ?>">
                                <i class="<?php echo $item['icon']; ?>"></i>
                                <span><?php echo $item['title']; ?></span>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </div>
    
    <?php if ($currentAzienda): ?>
    <div class="company-info">
        <div class="company-label">Azienda Attiva</div>
        <div class="company-name"><?php echo htmlspecialchars($currentAzienda['azienda_nome']); ?></div>
        <?php if (count($auth->getUserAziende()) > 1): ?>
            <a href="seleziona-azienda.php" class="change-company">
                <i class="fas fa-exchange-alt"></i> Cambia
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</nav>

<style>
/* Stili per submenu */
.menu-item.has-submenu {
    position: relative;
}

.submenu-arrow {
    margin-left: auto;
    transition: transform 0.3s;
    font-size: 12px;
}

.menu-item.has-submenu.open .submenu-arrow {
    transform: rotate(180deg);
}

.submenu {
    display: none;
    list-style: none;
    padding: 0;
    margin: 0;
    background: rgba(0, 0, 0, 0.1);
}

.menu-item.has-submenu.open .submenu {
    display: block;
}

.submenu-item {
    padding-left: 20px;
}

.submenu-item a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 20px;
    color: #e0e0e0;
    text-decoration: none;
    font-size: 14px;
    transition: all 0.3s;
}

.submenu-item a:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
}

.submenu-item.active a {
    background: rgba(255, 255, 255, 0.15);
    color: #fff;
    border-left: 3px solid #fbbf24;
}

.menu-item.has-submenu > a {
    display: flex;
    align-items: center;
    gap: 10px;
}
</style>

<script>
function toggleSubmenu(element) {
    const menuItem = element.closest('.menu-item');
    menuItem.classList.toggle('open');
    
    // Chiudi altri submenu aperti
    document.querySelectorAll('.menu-item.has-submenu').forEach(item => {
        if (item !== menuItem && item.classList.contains('open')) {
            item.classList.remove('open');
        }
    });
}

// Mantieni aperto il submenu se una pagina del submenu è attiva
document.addEventListener('DOMContentLoaded', function() {
    const activeSubmenuItem = document.querySelector('.submenu-item.active');
    if (activeSubmenuItem) {
        const parentMenuItem = activeSubmenuItem.closest('.menu-item.has-submenu');
        if (parentMenuItem) {
            parentMenuItem.classList.add('open');
        }
    }
});
</script> 