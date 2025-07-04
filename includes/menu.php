<?php
// File menu condiviso per tutti i link di navigazione
$base_path = '/piattaforma-collaborativa';
?>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <div class="logo-star">✦</div>
            <h2>Nexio</h2>
            <p>Semplifica, Connetti, Cresci Insieme</p>
        </div>
    </div>
    
    <nav class="sidebar-menu">
        <a href="<?php echo $base_path; ?>/dashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <i>📊</i> Dashboard
        </a>
        <a href="<?php echo $base_path; ?>/documenti.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'documenti.php' ? 'active' : ''; ?>">
            <i>📄</i> Documenti
        </a>
        <a href="<?php echo $base_path; ?>/calendario.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'calendario.php' ? 'active' : ''; ?>">
            <i>📅</i> Calendario
        </a>
        <?php if ($auth->isAdmin() || $auth->isStaff()): ?>
        <a href="<?php echo $base_path; ?>/utenti.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'utenti.php' ? 'active' : ''; ?>">
            <i>👥</i> Utenti
        </a>
        <?php endif; ?>
        <?php if ($auth->isAdmin()): ?>
        <?php endif; ?>
        <a href="<?php echo $base_path; ?>/profilo.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'profilo.php' ? 'active' : ''; ?>">
            <i>👤</i> Profilo
        </a>
        <a href="<?php echo $base_path; ?>/logout.php" class="nav-item">
            <i>🚪</i> Esci
        </a>
    </nav>
    
    <div class="user-info">
        <div class="user-avatar">
            <i class="fas fa-user-circle"></i>
        </div>
        <div class="user-details">
            <div class="user-name"><?php echo htmlspecialchars($user['nome'] . ' ' . $user['cognome']); ?></div>
            <div class="user-role"><?php echo htmlspecialchars($user['ruolo']); ?></div>
        </div>
    </div>
</aside> 