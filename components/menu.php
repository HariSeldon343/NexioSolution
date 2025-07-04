<?php
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
                    <div class="logo">
                <img src="<?php echo APP_PATH; ?>/assets/images/nexio-logo.svg" alt="Nexio Logo" style="max-width: 200px;">
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
                    <li class="menu-item <?php echo $currentPage === basename($item['url']) ? 'active' : ''; ?>">
                        <a href="<?php echo $item['url']; ?>">
                            <i class="<?php echo $item['icon']; ?>"></i>
                            <span><?php echo $item['title']; ?></span>
                        </a>
                    </li>
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