<?php
/**
 * Versione Mobile Completa - Replica 1:1 del Desktop
 * Responsive e ottimizzata per dispositivi mobili
 */

session_start();
require_once 'backend/config/config.php';
require_once 'backend/middleware/Auth.php';

$auth = Auth::getInstance();

// Se non autenticato, mostra login mobile
if (!$auth->isAuthenticated() && !isset($_POST['login'])) {
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <meta name="theme-color" content="#2563eb">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <title>Nexio Mobile - Login</title>
        <link rel="icon" type="image/svg+xml" href="assets/images/nexio-icon.svg">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            
            .login-container { 
                width: 100%; 
                max-width: 400px; 
                animation: fadeIn 0.5s ease-out;
            }
            
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            .logo-container { 
                text-align: center; 
                margin-bottom: 32px; 
            }
            
            .logo {
                width: 100px; 
                height: 100px; 
                background: white; 
                border-radius: 24px;
                display: inline-flex; 
                align-items: center; 
                justify-content: center;
                margin-bottom: 20px; 
                box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            }
            
            .logo img {
                width: 60px;
                height: 60px;
            }
            
            .app-name { 
                color: white; 
                font-size: 32px; 
                font-weight: 700; 
                margin-bottom: 8px;
                text-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .app-tagline { 
                color: rgba(255,255,255,0.9); 
                font-size: 16px;
                font-weight: 300;
            }
            
            .login-card {
                background: white; 
                border-radius: 20px; 
                padding: 40px 32px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            }
            
            .form-title { 
                font-size: 24px; 
                font-weight: 600; 
                color: #1a202c; 
                margin-bottom: 32px; 
                text-align: center; 
            }
            
            .form-group { 
                margin-bottom: 24px; 
            }
            
            .form-label { 
                display: block; 
                font-size: 14px; 
                font-weight: 600; 
                color: #4a5568; 
                margin-bottom: 8px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .form-input {
                width: 100%; 
                padding: 14px 16px; 
                border: 2px solid #e2e8f0;
                border-radius: 12px; 
                font-size: 16px; 
                transition: all 0.3s;
                background: #f7fafc;
            }
            
            .form-input:focus { 
                outline: none; 
                border-color: #667eea; 
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
                background: white;
            }
            
            .btn-login {
                width: 100%; 
                padding: 16px; 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none; 
                border-radius: 12px; 
                font-size: 16px; 
                font-weight: 600;
                cursor: pointer; 
                transition: transform 0.2s, box-shadow 0.2s;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            
            .btn-login:hover { 
                transform: translateY(-2px);
                box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
            }
            
            .btn-login:active {
                transform: translateY(0);
            }
            
            .error { 
                background: linear-gradient(135deg, #ff6b6b, #ff8e53);
                color: white; 
                padding: 14px; 
                border-radius: 12px; 
                margin-bottom: 24px; 
                text-align: center;
                font-weight: 500;
            }
            
            .forgot-link {
                text-align: center;
                margin-top: 24px;
            }
            
            .forgot-link a {
                color: #667eea;
                text-decoration: none;
                font-size: 14px;
                font-weight: 500;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="logo-container">
                <div class="logo">
                    <img src="assets/images/nexio-icon.svg?v=<?php echo @filemtime(__DIR__ . '/assets/images/nexio-icon.svg'); ?>" alt="Nexio" onerror="this.style.display='none'; this.parentElement.innerHTML='<span style=\"font-size: 48px; font-weight: bold; color: #667eea;\">N</span>';">
                </div>
                <h1 class="app-name">Nexio Platform</h1>
                <p class="app-tagline">Sistema Gestionale Integrato</p>
            </div>
            
            <div class="login-card">
                <h2 class="form-title">Accesso Mobile</h2>
                
                <?php if (isset($_GET['error'])): ?>
                <div class="error">⚠️ Credenziali non valide</div>
                <?php endif; ?>
                
                <form method="POST" action="m.php">
                    <input type="hidden" name="login" value="1">
                    <div class="form-group">
                        <label class="form-label">Username o Email</label>
                        <input type="text" name="username" class="form-input" required autocomplete="username" placeholder="Inserisci username o email">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-input" required autocomplete="current-password" placeholder="Inserisci password">
                    </div>
                    <button type="submit" class="btn-login">Accedi</button>
                </form>
                
                <div class="forgot-link">
                    <a href="recupera-password.php">Password dimenticata?</a>
                </div>
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
    
    $result = $auth->login($username, $password);
    if ($result['success']) {
        header('Location: m.php');
    } else {
        header('Location: m.php?error=1');
    }
    exit();
}

// Gestione logout
if (isset($_GET['logout'])) {
    $auth->logout();
    header('Location: m.php');
    exit();
}

// --- UTENTE AUTENTICATO ---
$user = $auth->getUser();
$currentAzienda = $auth->getCurrentAzienda();
$isSuperAdmin = $auth->isSuperAdmin();
$hasElevatedPrivileges = $auth->hasElevatedPrivileges();

// Pagina corrente
$page = $_GET['page'] ?? 'dashboard';

// Gestione azioni
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'update_task_progress':
            $taskId = intval($_POST['task_id']);
            $progress = intval($_POST['progress']);
            $stmt = $pdo->prepare("UPDATE tasks SET progresso = ? WHERE id = ?");
            $stmt->execute([$progress, $taskId]);
            echo json_encode(['success' => true]);
            exit();
            
        case 'assign_task':
            $taskId = intval($_POST['task_id']);
            $userId = intval($_POST['user_id']);
            $stmt = $pdo->prepare("UPDATE tasks SET assegnato_a = ? WHERE id = ?");
            $stmt->execute([$userId, $taskId]);
            echo json_encode(['success' => true]);
            exit();
            
        case 'update_task_status':
            $taskId = intval($_POST['task_id']);
            $status = $_POST['status'];
            $stmt = $pdo->prepare("UPDATE tasks SET stato = ? WHERE id = ?");
            $stmt->execute([$status, $taskId]);
            echo json_encode(['success' => true]);
            exit();
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#2563eb">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>Nexio Mobile - <?php echo ucfirst($page); ?></title>
    
    <link rel="icon" type="image/svg+xml" href="assets/images/nexio-icon.svg">
    <link rel="apple-touch-icon" href="assets/images/nexio-icon.svg">
    
    <!-- Font Awesome per icone -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            -webkit-tap-highlight-color: transparent;
        }
        
        :root {
            --primary: #667eea;
            --primary-dark: #5a67d8;
            --secondary: #48bb78;
            --info: #4299e1;
            --warning: #ed8936;
            --danger: #f56565;
            --dark: #2d3748;
            --gray: #718096;
            --light: #f7fafc;
            --white: #ffffff;
            --sidebar-width: 280px;
            --header-height: 56px;
            --bottom-nav-height: 60px;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--light);
            color: var(--dark);
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            padding-bottom: var(--bottom-nav-height);
        }
        
        /* Header Mobile */
        .mobile-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 0 16px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--header-height);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .header-left, .header-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .header-title {
            font-size: 18px;
            font-weight: 600;
            flex: 1;
            text-align: center;
        }
        
        .header-btn {
            background: none;
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .header-btn:active {
            background: rgba(255,255,255,0.1);
        }
        
        /* Content Area */
        .mobile-content {
            padding-top: var(--header-height);
            min-height: calc(100vh - var(--bottom-nav-height));
            background: var(--light);
        }
        
        /* Card Components */
        .card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .card-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--dark);
        }
        
        /* Dashboard Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            padding: 16px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid rgba(0,0,0,0.05);
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .stat-card:active {
            transform: scale(0.98);
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            font-size: 24px;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        
        .stat-label {
            font-size: 12px;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        
        .stat-change {
            font-size: 12px;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .stat-change.positive { color: var(--secondary); }
        .stat-change.negative { color: var(--danger); }
        
        /* Lists */
        .list-section {
            padding: 16px;
        }
        
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .section-action {
            color: var(--primary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        
        .list-item {
            background: white;
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
            border: 1px solid rgba(0,0,0,0.05);
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .list-item:active {
            background: var(--light);
        }
        
        .list-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
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
            color: var(--gray);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .list-meta {
            text-align: right;
            flex-shrink: 0;
        }
        
        .list-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-success { background: #c6f6d5; color: #22543d; }
        .badge-warning { background: #fed7aa; color: #7c2d12; }
        .badge-danger { background: #fed7d7; color: #742a2a; }
        .badge-info { background: #bee3f8; color: #2c5282; }
        .badge-primary { background: #e9d8fd; color: #44337a; }
        
        /* Calendar View */
        .calendar-container {
            padding: 16px;
        }
        
        .calendar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding: 12px;
            background: white;
            border-radius: 12px;
        }
        
        .calendar-nav {
            display: flex;
            gap: 8px;
        }
        
        .calendar-btn {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            background: white;
            border-radius: 8px;
            color: var(--dark);
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .calendar-btn:active {
            background: var(--light);
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
            background: white;
            border-radius: 12px;
            padding: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        
        .calendar-day-header {
            text-align: center;
            font-size: 11px;
            font-weight: 600;
            color: var(--gray);
            padding: 8px 0;
            text-transform: uppercase;
        }
        
        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.2s;
            position: relative;
        }
        
        .calendar-day:active {
            background: var(--light);
        }
        
        .calendar-day.today {
            background: var(--primary);
            color: white;
            font-weight: 600;
        }
        
        .calendar-day.has-events::after {
            content: '';
            position: absolute;
            bottom: 4px;
            width: 4px;
            height: 4px;
            background: var(--danger);
            border-radius: 50%;
        }
        
        .calendar-day.other-month {
            color: #cbd5e0;
        }
        
        /* Task Management */
        .task-item {
            background: white;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .task-header {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 12px;
        }
        
        .task-checkbox {
            width: 24px;
            height: 24px;
            border-radius: 6px;
            border: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            flex-shrink: 0;
            margin-top: 2px;
        }
        
        .task-checkbox.checked {
            background: var(--primary);
            border-color: var(--primary);
        }
        
        .task-checkbox.checked::after {
            content: '✓';
            color: white;
            font-size: 14px;
            font-weight: bold;
        }
        
        .task-content {
            flex: 1;
        }
        
        .task-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 4px;
        }
        
        .task-title.completed {
            text-decoration: line-through;
            color: var(--gray);
        }
        
        .task-description {
            font-size: 13px;
            color: var(--gray);
            margin-bottom: 8px;
        }
        
        .task-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 12px;
            color: var(--gray);
        }
        
        .task-meta-item {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .task-priority {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .priority-alta { background: #fed7d7; color: #742a2a; }
        .priority-media { background: #fed7aa; color: #7c2d12; }
        .priority-bassa { background: #c6f6d5; color: #22543d; }
        
        .task-progress {
            margin-top: 12px;
        }
        
        .progress-label {
            font-size: 12px;
            color: var(--gray);
            margin-bottom: 4px;
            display: flex;
            justify-content: space-between;
        }
        
        .progress-bar {
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--primary-dark));
            border-radius: 4px;
            transition: width 0.3s;
        }
        
        .task-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
        }
        
        .task-action-btn {
            flex: 1;
            padding: 8px;
            border: 1px solid #e2e8f0;
            background: white;
            border-radius: 8px;
            font-size: 12px;
            color: var(--dark);
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }
        
        .task-action-btn:active {
            background: var(--light);
        }
        
        /* Ticket System */
        .ticket-item {
            background: white;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border-left: 4px solid var(--primary);
        }
        
        .ticket-item.high-priority {
            border-left-color: var(--danger);
        }
        
        .ticket-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }
        
        .ticket-number {
            font-size: 12px;
            color: var(--gray);
            font-weight: 600;
        }
        
        .ticket-status {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-aperto { background: #fed7aa; color: #7c2d12; }
        .status-in_lavorazione { background: #bee3f8; color: #2c5282; }
        .status-risolto { background: #c6f6d5; color: #22543d; }
        .status-chiuso { background: #e2e8f0; color: #4a5568; }
        
        .ticket-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 8px;
        }
        
        .ticket-description {
            font-size: 13px;
            color: var(--gray);
            margin-bottom: 12px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .ticket-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: var(--gray);
        }
        
        .ticket-assignee {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .assignee-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 600;
        }
        
        /* Bottom Navigation */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: var(--bottom-nav-height);
            background: white;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-around;
            align-items: center;
            z-index: 1000;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
        }
        
        .nav-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            color: var(--gray);
            text-decoration: none;
            cursor: pointer;
            transition: color 0.2s;
            position: relative;
            padding: 8px;
        }
        
        .nav-item.active {
            color: var(--primary);
        }
        
        .nav-item.active::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 3px;
            background: var(--primary);
            border-radius: 0 0 3px 3px;
        }
        
        .nav-icon {
            font-size: 20px;
        }
        
        .nav-label {
            font-size: 11px;
            font-weight: 500;
        }
        
        /* Side Menu */
        .side-menu {
            position: fixed;
            top: 0;
            left: -100%;
            width: var(--sidebar-width);
            height: 100vh;
            background: white;
            box-shadow: 2px 0 15px rgba(0,0,0,0.1);
            transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 2000;
            overflow-y: auto;
        }
        
        .side-menu.open {
            left: 0;
        }
        
        .menu-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 24px 20px;
        }
        
        .menu-user {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .user-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            border: 2px solid rgba(255,255,255,0.3);
        }
        
        .user-info {
            flex: 1;
        }
        
        .user-name {
            font-weight: 600;
            font-size: 18px;
            margin-bottom: 4px;
        }
        
        .user-role {
            font-size: 13px;
            opacity: 0.9;
            text-transform: capitalize;
        }
        
        .company-info {
            padding: 12px;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            font-size: 13px;
        }
        
        .menu-items {
            padding: 16px 0;
        }
        
        .menu-section {
            margin-bottom: 24px;
        }
        
        .menu-section-title {
            font-size: 11px;
            font-weight: 600;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 0 20px;
            margin-bottom: 8px;
        }
        
        .menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: var(--dark);
            text-decoration: none;
            transition: background 0.2s;
            cursor: pointer;
            font-size: 15px;
        }
        
        .menu-item:active {
            background: var(--light);
        }
        
        .menu-item.active {
            background: rgba(102, 126, 234, 0.1);
            color: var(--primary);
            border-right: 3px solid var(--primary);
        }
        
        .menu-icon {
            width: 24px;
            font-size: 18px;
            text-align: center;
        }
        
        .menu-divider {
            height: 1px;
            background: #e2e8f0;
            margin: 16px 20px;
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
        
        /* FAB Button */
        .fab {
            position: fixed;
            bottom: calc(var(--bottom-nav-height) + 16px);
            right: 16px;
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            cursor: pointer;
            z-index: 100;
            font-size: 24px;
            transition: transform 0.2s;
        }
        
        .fab:active {
            transform: scale(0.95);
        }
        
        /* Modals */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 3000;
            padding: 20px;
        }
        
        .modal.open {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 16px;
            max-width: 400px;
            width: 100%;
            max-height: 80vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .modal-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .modal-close {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: none;
            background: var(--light);
            color: var(--gray);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 6px;
        }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .modal-footer {
            padding: 16px 20px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
        }
        
        .btn-secondary {
            background: var(--light);
            color: var(--dark);
            border: 1px solid #e2e8f0;
        }
        
        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }
        
        .empty-icon {
            font-size: 64px;
            margin-bottom: 16px;
            opacity: 0.3;
        }
        
        .empty-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 8px;
        }
        
        .empty-text {
            font-size: 14px;
            color: var(--gray);
        }
        
        /* Loading */
        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--light);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Responsive adjustments */
        @media (min-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
            }
            
            .mobile-content {
                max-width: 768px;
                margin: 0 auto;
            }
        }
        
        /* iOS specific */
        @supports (-webkit-touch-callout: none) {
            .bottom-nav {
                padding-bottom: env(safe-area-inset-bottom);
                height: calc(var(--bottom-nav-height) + env(safe-area-inset-bottom));
            }
            
            .mobile-header {
                padding-top: env(safe-area-inset-top);
                height: calc(var(--header-height) + env(safe-area-inset-top));
            }
            
            .mobile-content {
                padding-top: calc(var(--header-height) + env(safe-area-inset-top));
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="mobile-header">
        <div class="header-left">
            <button class="header-btn" onclick="toggleMenu()">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        
        <div class="header-title">
            <?php 
            $titles = [
                'dashboard' => 'Dashboard',
                'documenti' => 'Documenti',
                'filesystem' => 'File System',
                'calendario' => 'Calendario',
                'tasks' => 'Attività',
                'tickets' => 'Tickets',
                'aziende' => 'Aziende',
                'utenti' => 'Utenti',
                'referenti' => 'Referenti',
                'profilo' => 'Profilo'
            ];
            echo $titles[$page] ?? 'Nexio';
            ?>
        </div>
        
        <div class="header-right">
            <?php if ($page == 'calendario'): ?>
            <button class="header-btn" onclick="showCalendarOptions()">
                <i class="fas fa-ellipsis-v"></i>
            </button>
            <?php elseif ($page == 'tasks'): ?>
            <button class="header-btn" onclick="showTaskFilters()">
                <i class="fas fa-filter"></i>
            </button>
            <?php else: ?>
            <button class="header-btn" onclick="showNotifications()">
                <i class="fas fa-bell"></i>
            </button>
            <?php endif; ?>
        </div>
    </header>
    
    <!-- Side Menu -->
    <div class="side-menu" id="sideMenu">
        <div class="menu-header">
            <div class="menu-user">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['nome'] ?? 'U', 0, 1)); ?>
                </div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($user['nome'] . ' ' . $user['cognome']); ?></div>
                    <div class="user-role"><?php echo htmlspecialchars($user['ruolo'] ?? 'utente'); ?></div>
                </div>
            </div>
            <?php if ($currentAzienda): ?>
            <div class="company-info">
                <i class="fas fa-building"></i> <?php echo htmlspecialchars($currentAzienda['nome']); ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="menu-items">
            <div class="menu-section">
                <div class="menu-section-title">Principale</div>
                <a href="m.php" class="menu-item <?php echo $page == 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt menu-icon"></i>
                    <span>Dashboard</span>
                </a>
                <a href="m.php?page=documenti" class="menu-item <?php echo $page == 'documenti' ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt menu-icon"></i>
                    <span>Documenti</span>
                </a>
                <a href="m.php?page=filesystem" class="menu-item <?php echo $page == 'filesystem' ? 'active' : ''; ?>">
                    <i class="fas fa-folder menu-icon"></i>
                    <span>File System</span>
                </a>
                <a href="m.php?page=calendario" class="menu-item <?php echo $page == 'calendario' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar menu-icon"></i>
                    <span>Calendario</span>
                </a>
            </div>
            
            <div class="menu-section">
                <div class="menu-section-title">Gestione</div>
                <a href="m.php?page=tasks" class="menu-item <?php echo $page == 'tasks' ? 'active' : ''; ?>">
                    <i class="fas fa-tasks menu-icon"></i>
                    <span>Attività</span>
                </a>
                <a href="m.php?page=tickets" class="menu-item <?php echo $page == 'tickets' ? 'active' : ''; ?>">
                    <i class="fas fa-ticket-alt menu-icon"></i>
                    <span>Tickets</span>
                </a>
                <a href="m.php?page=referenti" class="menu-item <?php echo $page == 'referenti' ? 'active' : ''; ?>">
                    <i class="fas fa-address-book menu-icon"></i>
                    <span>Referenti</span>
                </a>
            </div>
            
            <?php if ($hasElevatedPrivileges): ?>
            <div class="menu-section">
                <div class="menu-section-title">Amministrazione</div>
                <a href="m.php?page=aziende" class="menu-item <?php echo $page == 'aziende' ? 'active' : ''; ?>">
                    <i class="fas fa-building menu-icon"></i>
                    <span>Aziende</span>
                </a>
                <a href="m.php?page=utenti" class="menu-item <?php echo $page == 'utenti' ? 'active' : ''; ?>">
                    <i class="fas fa-users menu-icon"></i>
                    <span>Utenti</span>
                </a>
            </div>
            <?php endif; ?>
            
            <div class="menu-divider"></div>
            
            <a href="m.php?page=profilo" class="menu-item <?php echo $page == 'profilo' ? 'active' : ''; ?>">
                <i class="fas fa-user-cog menu-icon"></i>
                <span>Profilo</span>
            </a>
            <a href="m.php?logout=1" class="menu-item">
                <i class="fas fa-sign-out-alt menu-icon"></i>
                <span>Esci</span>
            </a>
        </div>
    </div>
    <div class="menu-overlay" id="menuOverlay" onclick="toggleMenu()"></div>
    
    <!-- Main Content -->
    <main class="mobile-content">
        <?php
        // Include content based on current page
        switch ($page) {
            case 'dashboard':
                include 'mobile/dashboard-content.php';
                break;
            case 'documenti':
                include 'mobile/documenti-content.php';
                break;
            case 'filesystem':
                include 'mobile/filesystem-content.php';
                break;
            case 'calendario':
                include 'mobile/calendario-content.php';
                break;
            case 'tasks':
                include 'mobile/tasks-content.php';
                break;
            case 'tickets':
                include 'mobile/tickets-content.php';
                break;
            case 'aziende':
                include 'mobile/aziende-content.php';
                break;
            case 'utenti':
                include 'mobile/utenti-content.php';
                break;
            case 'referenti':
                include 'mobile/referenti-content.php';
                break;
            case 'profilo':
                include 'mobile/profilo-content.php';
                break;
            default:
                echo '<div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="empty-title">Pagina non trovata</div>
                    <div class="empty-text">La pagina richiesta non esiste</div>
                </div>';
        }
        ?>
    </main>
    
    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="m.php" class="nav-item <?php echo $page == 'dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-home nav-icon"></i>
            <span class="nav-label">Home</span>
        </a>
        <a href="m.php?page=documenti" class="nav-item <?php echo $page == 'documenti' ? 'active' : ''; ?>">
            <i class="fas fa-file-alt nav-icon"></i>
            <span class="nav-label">Documenti</span>
        </a>
        <a href="m.php?page=calendario" class="nav-item <?php echo $page == 'calendario' ? 'active' : ''; ?>">
            <i class="fas fa-calendar nav-icon"></i>
            <span class="nav-label">Calendario</span>
        </a>
        <a href="m.php?page=tasks" class="nav-item <?php echo $page == 'tasks' ? 'active' : ''; ?>">
            <i class="fas fa-tasks nav-icon"></i>
            <span class="nav-label">Tasks</span>
        </a>
        <a href="m.php?page=tickets" class="nav-item <?php echo $page == 'tickets' ? 'active' : ''; ?>">
            <i class="fas fa-ticket-alt nav-icon"></i>
            <span class="nav-label">Tickets</span>
        </a>
    </nav>
    
    <!-- FAB for quick actions -->
    <?php if (in_array($page, ['tasks', 'tickets', 'documenti', 'calendario'])): ?>
    <button class="fab" onclick="showQuickAdd()">
        <i class="fas fa-plus"></i>
    </button>
    <?php endif; ?>
    
    <script>
        // Toggle side menu
        function toggleMenu() {
            const menu = document.getElementById('sideMenu');
            const overlay = document.getElementById('menuOverlay');
            menu.classList.toggle('open');
            overlay.classList.toggle('open');
        }
        
        // Show quick add modal based on current page
        function showQuickAdd() {
            const page = '<?php echo $page; ?>';
            switch(page) {
                case 'tasks':
                    showAddTaskModal();
                    break;
                case 'tickets':
                    showAddTicketModal();
                    break;
                case 'documenti':
                    showUploadModal();
                    break;
                case 'calendario':
                    showAddEventModal();
                    break;
            }
        }
        
        // Task management functions
        function updateTaskProgress(taskId, progress) {
            fetch('m.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=update_task_progress&task_id=${taskId}&progress=${progress}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }
        
        function assignTask(taskId, userId) {
            fetch('m.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=assign_task&task_id=${taskId}&user_id=${userId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }
        
        function updateTaskStatus(taskId, status) {
            fetch('m.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=update_task_status&task_id=${taskId}&status=${status}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }
        
        // Modal functions (placeholders - implement as needed)
        function showAddTaskModal() {
            alert('Aggiungi nuova attività');
        }
        
        function showAddTicketModal() {
            alert('Crea nuovo ticket');
        }
        
        function showUploadModal() {
            alert('Carica documento');
        }
        
        function showAddEventModal() {
            alert('Aggiungi evento');
        }
        
        function showNotifications() {
            alert('Notifiche');
        }
        
        function showCalendarOptions() {
            alert('Opzioni calendario');
        }
        
        function showTaskFilters() {
            alert('Filtri attività');
        }
    </script>
</body>
</html>