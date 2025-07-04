<?php
// Menu differenziato per ruolo
$auth = Auth::getInstance();
$userRole = $auth->getUser()['ruolo'];
$currentAzienda = $auth->getCurrentAzienda();
?>

<nav class="nav-menu">
    <?php if ($auth->isSuperAdmin()): ?>
        <!-- Menu Super Admin -->
        <a href="<?php echo APP_PATH; ?>/dashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="<?php echo APP_PATH; ?>/aziende.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'aziende.php' ? 'active' : ''; ?>">
            <i class="fas fa-building"></i> Aziende
        </a>
        <a href="<?php echo APP_PATH; ?>/calendario.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'calendario.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar"></i> Calendario
        </a>
        <a href="<?php echo APP_PATH; ?>/lista-eventi.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'lista-eventi.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-check"></i> Eventi
        </a>
        <a href="<?php echo APP_PATH; ?>/documenti.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'documenti.php' ? 'active' : ''; ?>">
            <i class="fas fa-file-alt"></i> Documenti
        </a>
        <?php if ($currentAzienda): ?>
            <a href="<?php echo APP_PATH; ?>/archivio-documenti.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'archivio-documenti.php' ? 'active' : ''; ?>">
                <i class="fas fa-archive"></i> Archivio Azienda
            </a>
        <?php endif; ?>
        <a href="<?php echo APP_PATH; ?>/gestione-moduli.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'gestione-moduli.php' ? 'active' : ''; ?>">
            <i class="fas fa-puzzle-piece"></i> Gestione Moduli
        </a>
        <a href="<?php echo APP_PATH; ?>/gestione-moduli-template.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'gestione-moduli-template.php' ? 'active' : ''; ?>">
            <i class="fas fa-file-alt"></i> Template Documenti
        </a>
        <a href="<?php echo APP_PATH; ?>/tickets.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'tickets.php' ? 'active' : ''; ?>">
            <i class="fas fa-ticket-alt"></i> Tickets
        </a>
        <a href="<?php echo APP_PATH; ?>/utenti.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'utenti.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i> Utenti
        </a>
        <a href="<?php echo APP_PATH; ?>/log-attivita.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'log-attivita.php' ? 'active' : ''; ?>">
            <i class="fas fa-file-alt"></i> Log Attività
        </a>
    <?php elseif ($auth->hasRoleInAzienda('proprietario') || $auth->hasRoleInAzienda('admin')): ?>
        <!-- Menu Admin Azienda -->
        <a href="<?php echo APP_PATH; ?>/dashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="<?php echo APP_PATH; ?>/documenti.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'documenti.php' ? 'active' : ''; ?>">
            <i class="fas fa-file-alt"></i> Documenti
        </a>
        <a href="<?php echo APP_PATH; ?>/archivio-documenti.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'archivio-documenti.php' ? 'active' : ''; ?>">
            <i class="fas fa-archive"></i> Archivio Azienda
        </a>
        <a href="<?php echo APP_PATH; ?>/calendario.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'calendario.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar"></i> Calendario
        </a>
        <a href="<?php echo APP_PATH; ?>/lista-eventi.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'lista-eventi.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-check"></i> Eventi
        </a>
        <a href="<?php echo APP_PATH; ?>/tickets.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'tickets.php' ? 'active' : ''; ?>">
            <i class="fas fa-ticket-alt"></i> Supporto
        </a>
        <a href="<?php echo APP_PATH; ?>/utenti.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'utenti.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i> Utenti
        </a>
        <a href="<?php echo APP_PATH; ?>/log-attivita.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'log-attivita.php' ? 'active' : ''; ?>">
            <i class="fas fa-file-alt"></i> Log Attività
        </a>
    <?php else: ?>
        <!-- Menu Utente Azienda -->
        <a href="<?php echo APP_PATH; ?>/dashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="<?php echo APP_PATH; ?>/documenti.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'documenti.php' ? 'active' : ''; ?>">
            <i class="fas fa-file-alt"></i> Documenti
        </a>
        <a href="<?php echo APP_PATH; ?>/archivio-documenti.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'archivio-documenti.php' ? 'active' : ''; ?>">
            <i class="fas fa-archive"></i> Archivio Azienda
        </a>
        <a href="<?php echo APP_PATH; ?>/calendario.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'calendario.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar"></i> Calendario
        </a>
        <a href="<?php echo APP_PATH; ?>/tickets.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'tickets.php' ? 'active' : ''; ?>">
            <i class="fas fa-ticket-alt"></i> Supporto
        </a>
    <?php endif; ?>
    
    <a href="<?php echo APP_PATH; ?>/profilo.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'profilo.php' ? 'active' : ''; ?>">
        <i class="fas fa-user"></i> Profilo
    </a>
    <a href="<?php echo APP_PATH; ?>/logout.php" class="nav-item">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</nav> 