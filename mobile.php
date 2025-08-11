<?php
// Versione Mobile - Single Page Application
session_start();
require_once 'backend/config/config.php';
require_once 'backend/middleware/Auth.php';

$auth = Auth::getInstance();

// Se non autenticato, mostra login
if (!$auth->isAuthenticated() && !isset($_POST['login'])) {
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <meta name="theme-color" content="#2563eb">
        <title>Nexio Mobile - Login</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .login-container { width: 100%; max-width: 400px; }
            .logo-container { text-align: center; margin-bottom: 32px; }
            .logo {
                width: 80px; height: 80px; background: white; border-radius: 20px;
                display: inline-flex; align-items: center; justify-content: center;
                margin-bottom: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                font-size: 36px; font-weight: bold; color: #2563eb;
            }
            .app-name { color: white; font-size: 28px; font-weight: 700; margin-bottom: 8px; }
            .app-tagline { color: rgba(255,255,255,0.8); font-size: 14px; }
            .login-card {
                background: white; border-radius: 16px; padding: 32px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            }
            .form-title { font-size: 20px; font-weight: 600; color: #1e293b; margin-bottom: 24px; text-align: center; }
            .form-group { margin-bottom: 20px; }
            .form-label { display: block; font-size: 14px; font-weight: 500; color: #1e293b; margin-bottom: 8px; }
            .form-input {
                width: 100%; padding: 12px 16px; border: 1px solid #e5e7eb;
                border-radius: 8px; font-size: 16px; transition: all 0.2s;
            }
            .form-input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
            .btn-login {
                width: 100%; padding: 14px; background: #2563eb; color: white;
                border: none; border-radius: 8px; font-size: 16px; font-weight: 600;
                cursor: pointer; transition: background 0.2s;
            }
            .btn-login:hover { background: #1d4ed8; }
            .error { background: #fee; color: #c00; padding: 12px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="logo-container">
                <div class="logo">N</div>
                <h1 class="app-name">Nexio</h1>
                <p class="app-tagline">Piattaforma Collaborativa Mobile</p>
            </div>
            <div class="login-card">
                <h2 class="form-title">Accedi al tuo account</h2>
                <?php if (isset($_GET['error'])): ?>
                <div class="error">Credenziali non valide</div>
                <?php endif; ?>
                <form method="POST" action="mobile.php">
                    <input type="hidden" name="login" value="1">
                    <div class="form-group">
                        <label class="form-label">Username o Email</label>
                        <input type="text" name="username" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-input" required>
                    </div>
                    <button type="submit" class="btn-login">Accedi</button>
                </form>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Gestione login
if (isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($auth->login($username, $password)) {
        header('Location: mobile.php');
        exit();
    } else {
        header('Location: mobile.php?error=1');
        exit();
    }
}

// Gestione logout
if (isset($_GET['logout'])) {
    $auth->logout();
    header('Location: mobile.php');
    exit();
}

// Utente autenticato - mostra dashboard
$user = $auth->getUser();
$currentAzienda = $auth->getCurrentAzienda();
$isSuperAdmin = $auth->isSuperAdmin();

// Carica dati dashboard
$stats = ['documenti' => 0, 'eventi' => 0, 'tasks' => 0, 'utenti' => 0];
$activities = [];
$events = [];

try {
    $aziendaId = $currentAzienda['id'] ?? null;
    
    // Conta documenti
    $query = "SELECT COUNT(*) as total FROM documenti WHERE stato != 'cestino'";
    if (!$isSuperAdmin && $aziendaId) {
        $query .= " AND (azienda_id = ? OR azienda_id IS NULL)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$aziendaId]);
    } else {
        $stmt = $pdo->query($query);
    }
    $stats['documenti'] = $stmt->fetchColumn();
    
    // Conta eventi
    $query = "SELECT COUNT(*) as total FROM eventi WHERE data_inizio >= NOW()";
    if (!$isSuperAdmin && $aziendaId) {
        $query .= " AND (azienda_id = ? OR azienda_id IS NULL)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$aziendaId]);
    } else {
        $stmt = $pdo->query($query);
    }
    $stats['eventi'] = $stmt->fetchColumn();
    
    // Conta tasks
    $query = "SELECT COUNT(*) as total FROM tasks WHERE stato IN ('pending', 'in_progress')";
    if (!$isSuperAdmin) {
        $query .= " AND (assegnato_a = ? OR creato_da = ?)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user['id'], $user['id']]);
    } else {
        $stmt = $pdo->query($query);
    }
    $stats['tasks'] = $stmt->fetchColumn();
    
    // Conta utenti (solo admin)
    if ($isSuperAdmin) {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM utenti WHERE attivo = 1");
        $stats['utenti'] = $stmt->fetchColumn();
    }
    
    // Attività recenti
    $query = "SELECT 
        'documento' as tipo,
        CONCAT('Documento: ', titolo) as descrizione,
        data_creazione as data
        FROM documenti 
        WHERE stato != 'cestino'";
    
    if (!$isSuperAdmin && $aziendaId) {
        $query .= " AND (azienda_id = ? OR azienda_id IS NULL)";
    }
    $query .= " ORDER BY data_creazione DESC LIMIT 5";
    
    if (!$isSuperAdmin && $aziendaId) {
        $stmt = $pdo->prepare($query);
        $stmt->execute([$aziendaId]);
    } else {
        $stmt = $pdo->query($query);
    }
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $activities[] = [
            'tipo' => $row['tipo'],
            'descrizione' => $row['descrizione'],
            'data' => date('d/m H:i', strtotime($row['data']))
        ];
    }
    
    // Eventi prossimi
    $query = "SELECT titolo, data_inizio FROM eventi WHERE data_inizio >= NOW()";
    if (!$isSuperAdmin && $aziendaId) {
        $query .= " AND (azienda_id = ? OR azienda_id IS NULL)";
    }
    $query .= " ORDER BY data_inizio ASC LIMIT 5";
    
    if (!$isSuperAdmin && $aziendaId) {
        $stmt = $pdo->prepare($query);
        $stmt->execute([$aziendaId]);
    } else {
        $stmt = $pdo->query($query);
    }
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $events[] = [
            'titolo' => $row['titolo'],
            'data' => date('d/m H:i', strtotime($row['data_inizio']))
        ];
    }
} catch (Exception $e) {
    // Log error
}

// Pagina da mostrare
$page = $_GET['page'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#2563eb">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Nexio Mobile</title>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1e293b;
            --light: #f8fafc;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--light);
            color: var(--dark);
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }
        .mobile-header {
            background: var(--primary);
            color: white;
            padding: 12px 16px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header-title {
            font-size: 18px;
            font-weight: 600;
            flex: 1;
            text-align: center;
        }
        .menu-btn {
            background: none;
            border: none;
            color: white;
            padding: 8px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .mobile-content {
            padding-top: 56px;
            padding-bottom: 60px;
            min-height: 100vh;
        }
        .dashboard-grid {
            padding: 16px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-icon {
            width: 40px;
            height: 40px;
            margin: 0 auto 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            border-radius: 8px;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 4px;
        }
        .stat-label {
            font-size: 12px;
            color: var(--secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .section {
            padding: 0 16px 16px;
        }
        .section-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--dark);
        }
        .list-card {
            background: white;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .list-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(37, 99, 235, 0.1);
            font-size: 20px;
        }
        .list-content {
            flex: 1;
            min-width: 0;
        }
        .list-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .list-subtitle {
            font-size: 12px;
            color: var(--secondary);
        }
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-around;
            padding: 8px 0;
            z-index: 1000;
        }
        .nav-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 8px;
            color: var(--secondary);
            text-decoration: none;
        }
        .nav-item.active {
            color: var(--primary);
        }
        .nav-icon {
            font-size: 20px;
            margin-bottom: 4px;
        }
        .nav-label {
            font-size: 10px;
            font-weight: 500;
        }
        .side-menu {
            position: fixed;
            top: 0;
            left: -280px;
            width: 280px;
            height: 100vh;
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            transition: left 0.3s;
            z-index: 2000;
        }
        .side-menu.open {
            left: 0;
        }
        .menu-header {
            background: var(--primary);
            color: white;
            padding: 20px;
        }
        .menu-user {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }
        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: bold;
        }
        .menu-items {
            padding: 16px 0;
        }
        .menu-item {
            display: block;
            padding: 12px 20px;
            color: var(--dark);
            text-decoration: none;
            transition: background 0.2s;
        }
        .menu-item:hover {
            background: var(--light);
        }
        .menu-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s, visibility 0.3s;
            z-index: 1999;
        }
        .menu-overlay.open {
            opacity: 1;
            visibility: visible;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--secondary);
        }
        .documents-list {
            padding: 16px;
        }
        .document-item {
            background: white;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 12px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="mobile-header">
        <button class="menu-btn" onclick="toggleMenu()">☰</button>
        <div class="header-title">
            <?php 
            echo $page == 'dashboard' ? 'Dashboard' : 
                 ($page == 'documenti' ? 'Documenti' : 
                 ($page == 'calendario' ? 'Calendario' : 
                 ($page == 'filesystem' ? 'File System' : 'Nexio')));
            ?>
        </div>
        <button class="menu-btn" onclick="toggleMenu()">👤</button>
    </header>
    
    <!-- Side Menu -->
    <div class="side-menu" id="sideMenu">
        <div class="menu-header">
            <div class="menu-user">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['nome'] ?? 'U', 0, 1)); ?>
                </div>
                <div>
                    <div style="font-weight: 600;"><?php echo htmlspecialchars($user['nome'] . ' ' . $user['cognome']); ?></div>
                    <div style="font-size: 12px; opacity: 0.9;"><?php echo htmlspecialchars($user['ruolo'] ?? 'utente'); ?></div>
                </div>
            </div>
            <?php if ($currentAzienda): ?>
            <div style="padding: 8px; background: rgba(255,255,255,0.1); border-radius: 4px; font-size: 12px;">
                <?php echo htmlspecialchars($currentAzienda['nome']); ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="menu-items">
            <a href="mobile.php" class="menu-item">🏠 Dashboard</a>
            <a href="mobile.php?page=documenti" class="menu-item">📁 Documenti</a>
            <a href="mobile.php?page=calendario" class="menu-item">📅 Calendario</a>
            <a href="mobile.php?page=filesystem" class="menu-item">💾 File System</a>
            <?php if ($isSuperAdmin): ?>
            <a href="aziende.php" class="menu-item">🏢 Aziende</a>
            <a href="utenti.php" class="menu-item">👥 Utenti</a>
            <?php endif; ?>
            <hr style="margin: 16px 20px; border: none; border-top: 1px solid #e5e7eb;">
            <a href="mobile.php?logout=1" class="menu-item">🚪 Esci</a>
        </div>
    </div>
    <div class="menu-overlay" id="menuOverlay" onclick="toggleMenu()"></div>
    
    <!-- Main Content -->
    <main class="mobile-content">
        <?php if ($page == 'dashboard'): ?>
            <!-- Dashboard -->
            <div class="dashboard-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(37, 99, 235, 0.1);">📄</div>
                    <div class="stat-value"><?php echo $stats['documenti']; ?></div>
                    <div class="stat-label">Documenti</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1);">📅</div>
                    <div class="stat-value"><?php echo $stats['eventi']; ?></div>
                    <div class="stat-label">Eventi</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1);">✅</div>
                    <div class="stat-value"><?php echo $stats['tasks']; ?></div>
                    <div class="stat-label">Tasks</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(139, 92, 246, 0.1);">👥</div>
                    <div class="stat-value"><?php echo $stats['utenti']; ?></div>
                    <div class="stat-label">Utenti</div>
                </div>
            </div>
            
            <div class="section">
                <h2 class="section-title">Attività Recenti</h2>
                <?php if (empty($activities)): ?>
                    <div class="empty-state">Nessuna attività recente</div>
                <?php else: ?>
                    <?php foreach ($activities as $activity): ?>
                    <div class="list-card">
                        <div class="list-icon">📄</div>
                        <div class="list-content">
                            <div class="list-title"><?php echo htmlspecialchars($activity['descrizione']); ?></div>
                            <div class="list-subtitle"><?php echo $activity['data']; ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="section">
                <h2 class="section-title">Prossimi Eventi</h2>
                <?php if (empty($events)): ?>
                    <div class="empty-state">Nessun evento programmato</div>
                <?php else: ?>
                    <?php foreach ($events as $event): ?>
                    <div class="list-card">
                        <div class="list-icon" style="background: rgba(16, 185, 129, 0.1);">📅</div>
                        <div class="list-content">
                            <div class="list-title"><?php echo htmlspecialchars($event['titolo']); ?></div>
                            <div class="list-subtitle"><?php echo $event['data']; ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
        <?php elseif ($page == 'documenti'): ?>
            <!-- Documenti -->
            <div class="documents-list">
                <?php
                // Carica documenti
                $documenti = [];
                try {
                    $query = "SELECT d.*, c.nome as cartella_nome 
                              FROM documenti d 
                              LEFT JOIN cartelle c ON d.cartella_id = c.id 
                              WHERE d.stato != 'cestino'";
                    
                    if (!$isSuperAdmin && $currentAzienda) {
                        $query .= " AND (d.azienda_id = ? OR d.azienda_id IS NULL)";
                        $stmt = $pdo->prepare($query);
                        $stmt->execute([$currentAzienda['id']]);
                    } else {
                        $stmt = $pdo->query($query);
                    }
                    
                    $documenti = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {}
                
                if (empty($documenti)): ?>
                    <div class="empty-state">
                        <div style="font-size: 64px; margin-bottom: 16px;">📁</div>
                        <div style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">Nessun documento</div>
                        <div>Non ci sono documenti da visualizzare</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($documenti as $doc): ?>
                    <div class="document-item">
                        <div class="list-icon">
                            <?php 
                            if (strpos($doc['mime_type'] ?? '', 'pdf') !== false) echo '📕';
                            elseif (strpos($doc['mime_type'] ?? '', 'word') !== false) echo '📘';
                            elseif (strpos($doc['mime_type'] ?? '', 'sheet') !== false) echo '📗';
                            else echo '📄';
                            ?>
                        </div>
                        <div class="list-content">
                            <div class="list-title"><?php echo htmlspecialchars($doc['titolo'] ?? 'Senza titolo'); ?></div>
                            <div class="list-subtitle">
                                <?php echo $doc['cartella_nome'] ?? 'Root'; ?> • 
                                <?php 
                                $size = $doc['file_size'] ?? 0;
                                if ($size < 1024) echo $size . ' B';
                                elseif ($size < 1024*1024) echo round($size/1024, 1) . ' KB';
                                else echo round($size/(1024*1024), 1) . ' MB';
                                ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
        <?php elseif ($page == 'calendario'): ?>
            <!-- Calendario -->
            <div class="section">
                <h2 class="section-title">Eventi del Mese</h2>
                <?php
                // Carica eventi del mese
                $eventi = [];
                try {
                    $query = "SELECT * FROM eventi 
                              WHERE MONTH(data_inizio) = MONTH(CURRENT_DATE()) 
                              AND YEAR(data_inizio) = YEAR(CURRENT_DATE())";
                    
                    if (!$isSuperAdmin && $currentAzienda) {
                        $query .= " AND (azienda_id = ? OR azienda_id IS NULL)";
                        $stmt = $pdo->prepare($query);
                        $stmt->execute([$currentAzienda['id']]);
                    } else {
                        $stmt = $pdo->query($query);
                    }
                    
                    $eventi = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {}
                
                if (empty($eventi)): ?>
                    <div class="empty-state">
                        <div style="font-size: 64px; margin-bottom: 16px;">📅</div>
                        <div style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">Nessun evento</div>
                        <div>Non ci sono eventi questo mese</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($eventi as $evento): ?>
                    <div class="list-card">
                        <div class="list-icon" style="background: rgba(16, 185, 129, 0.1);">📅</div>
                        <div class="list-content">
                            <div class="list-title"><?php echo htmlspecialchars($evento['titolo']); ?></div>
                            <div class="list-subtitle">
                                <?php echo date('d/m/Y H:i', strtotime($evento['data_inizio'])); ?>
                                <?php if ($evento['data_fine']): ?>
                                    - <?php echo date('d/m/Y H:i', strtotime($evento['data_fine'])); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
        <?php elseif ($page == 'filesystem'): ?>
            <!-- File System -->
            <div class="section">
                <h2 class="section-title">File System</h2>
                <div class="empty-state">
                    <div style="font-size: 64px; margin-bottom: 16px;">💾</div>
                    <div style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">File System</div>
                    <div>Accedi alla versione desktop per gestire i file</div>
                    <a href="filesystem.php" style="display: inline-block; margin-top: 16px; padding: 12px 24px; background: var(--primary); color: white; text-decoration: none; border-radius: 8px;">
                        Apri Versione Desktop
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </main>
    
    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="mobile.php" class="nav-item <?php echo $page == 'dashboard' ? 'active' : ''; ?>">
            <span class="nav-icon">🏠</span>
            <span class="nav-label">Home</span>
        </a>
        <a href="mobile.php?page=documenti" class="nav-item <?php echo $page == 'documenti' ? 'active' : ''; ?>">
            <span class="nav-icon">📁</span>
            <span class="nav-label">Documenti</span>
        </a>
        <a href="mobile.php?page=calendario" class="nav-item <?php echo $page == 'calendario' ? 'active' : ''; ?>">
            <span class="nav-icon">📅</span>
            <span class="nav-label">Calendario</span>
        </a>
        <a href="mobile.php?page=filesystem" class="nav-item <?php echo $page == 'filesystem' ? 'active' : ''; ?>">
            <span class="nav-icon">💾</span>
            <span class="nav-label">Files</span>
        </a>
        <a href="#" onclick="toggleMenu(); return false;" class="nav-item">
            <span class="nav-icon">⋯</span>
            <span class="nav-label">Altro</span>
        </a>
    </nav>
    
    <script>
        function toggleMenu() {
            const menu = document.getElementById('sideMenu');
            const overlay = document.getElementById('menuOverlay');
            menu.classList.toggle('open');
            overlay.classList.toggle('open');
        }
    </script>
</body>
</html>