<?php
require_once 'backend/config/config.php';
require_once 'backend/middleware/Auth.php';
require_once 'backend/utils/RateLimiter.php';
require_once 'backend/utils/ActivityLogger.php';

$auth = Auth::getInstance();
$rateLimiter = RateLimiter::getInstance();
$logger = ActivityLogger::getInstance();

// Rileva se è un dispositivo mobile
function isMobile() {
    return preg_match('/(android|webos|iphone|ipad|ipod|blackberry|windows phone)/i', $_SERVER['HTTP_USER_AGENT'] ?? '');
}

// Se mobile, redirect a mobile.php
if (isMobile() && !isset($_GET['desktop'])) {
    header('Location: mobile.php');
    exit();
}

// Se già loggato, redirect a dashboard
if ($auth->isAuthenticated()) {
    redirect(APP_PATH . '/dashboard.php');
}

$error = '';
$success = '';
$isBlocked = false;

// Controlla rate limiting per IP
$ip = get_client_ip();
if (!$rateLimiter->isAllowed('login', $ip)) {
    $error = $rateLimiter->getErrorMessage('login', $ip);
    $isBlocked = true;
}

// Gestisci form di login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isBlocked) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($username) || empty($password)) {
        $error = 'Inserisci username e password';
    } else {
        // Registra tentativo
        $rateLimiter->recordAttempt('login', $ip, false);
        
        $result = $auth->login($username, $password);
        if ($result['success']) {
            // Login riuscito - resetta rate limiter
            $rateLimiter->recordAttempt('login', $ip, true);
            
            // Log login riuscito
            $user = $auth->getUser();
            if ($user) {
                $logger->logLogin($user['id']);
            }
            
            // Se remember me è selezionato, estendi la durata della sessione
            if ($remember) {
                // Qui potresti implementare un cookie remember me sicuro
                // Per ora estendiamo solo la durata della sessione
                $_SESSION['remember_me'] = true;
            }
            
            redirect(APP_PATH . '/dashboard.php');
        } else {
            // Log tentativo fallito
            $logger->logFailedLogin($username, $ip);
            $error = $result['message'] ?? 'Username o password non validi';
        }
    }
}

// Messaggio se arriva dal logout
if (isset($_GET['logout'])) {
    $success = 'Logout effettuato con successo';
}

// Messaggio se sessione scaduta
if (isset($_GET['expired'])) {
    $error = 'La tua sessione è scaduta. Effettua nuovamente l\'accesso.';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="Accedi alla piattaforma collaborativa">
    <meta name="robots" content="noindex, nofollow">
    
    <title>Login - <?php echo APP_NAME; ?></title>
    
    <link rel="icon" type="image/svg+xml" href="<?php echo APP_PATH; ?>/assets/images/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo APP_PATH; ?>/assets/images/favicon.svg">
    
    <!-- Inter Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- NEXIO REDESIGN CSS -->
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/nexio-redesign.css?v=<?php echo time(); ?>">
    
    <!-- Fix colori e UI - Risolve problemi di contrasto e bottoni -->
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/nexio-color-fixes.css?v=<?php echo time(); ?>">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #ffffff;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --border-color: #e5e7eb;
            --accent-color: #2d5a9f;
            --accent-hover: #1e3a5f;
            --error-bg: #ffffff;
            --error-text: #dc2626;
            --error-border: #dc2626;
            --success-bg: #ffffff;
            --success-text: #10b981;
            --success-border: #10b981;
            --shadow-sm: none;
            --shadow-md: none;
            --shadow-lg: none;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: #fafafa;
            color: var(--text-primary);
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 4px;
            border: 1px solid #e5e7eb;
            width: 100%;
            max-width: 380px;
            overflow: hidden;
        }
        
        .login-header {
            padding: 2rem 1.5rem 1.5rem;
            text-align: center;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .logo {
            width: 48px;
            height: 48px;
            margin: 0 auto 1rem;
        }
        
        .logo img {
            width: 100%;
            height: 100%;
        }
        
        .login-header h1 {
            font-size: 1.25rem;
            font-weight: 400;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .login-header p {
            color: var(--text-secondary);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        
        .login-body {
            padding: 1.5rem;
        }
        
        .alert {
            padding: 0.5rem 0.75rem;
            border-radius: 2px;
            margin-bottom: 1rem;
            font-size: 0.75rem;
            border: 1px solid;
        }
        
        .alert-error {
            background-color: var(--error-bg);
            color: var(--error-text);
            border-color: var(--error-border);
        }
        
        .alert-success {
            background-color: var(--success-bg);
            color: var(--success-text);
            border-color: var(--success-border);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.75rem;
            font-weight: 400;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.025em;
            position: static !important;
            opacity: 1 !important;
            visibility: visible !important;
            pointer-events: none;
            background: transparent !important;
            z-index: auto !important;
        }
        
        .form-input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #e5e7eb;
            border-radius: 2px;
            font-size: 0.875rem;
            transition: border-color 0.15s;
            background-color: white !important;
            color: #1e293b !important;
            -webkit-text-fill-color: #1e293b !important;
            position: relative !important;
            z-index: 1;
        }
        
        .form-input::placeholder {
            color: #9ca3af !important;
            opacity: 1 !important;
            -webkit-text-fill-color: #9ca3af !important;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #2d5a9f;
            background-color: white !important;
            color: #1e293b !important;
        }
        
        .form-input:disabled {
            background-color: #f5f5f5;
            cursor: not-allowed;
        }
        
        .form-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }
        
        .checkbox-wrapper {
            display: flex;
            align-items: center;
        }
        
        .checkbox-wrapper input[type="checkbox"] {
            width: 16px;
            height: 16px;
            margin-right: 0.5rem;
            cursor: pointer;
        }
        
        .checkbox-wrapper label {
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 0.75rem;
        }
        
        .link {
            color: #2d5a9f;
            text-decoration: none;
            font-weight: 400;
            font-size: 0.75rem;
        }
        
        .link:hover {
            text-decoration: underline;
        }
        
        .btn-submit {
            width: 100%;
            padding: 0.5rem 1rem;
            background-color: white;
            color: #2d5a9f;
            border: 1px solid #2d5a9f;
            border-radius: 2px;
            font-size: 0.75rem;
            font-weight: 400;
            cursor: pointer;
            transition: all 0.15s;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .btn-submit:hover:not(:disabled) {
            background-color: #2d5a9f;
            color: white;
        }
        
        .btn-submit:active:not(:disabled) {
            transform: none;
        }
        
        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .btn-submit.loading {
            position: relative;
            color: transparent;
        }
        
        .btn-submit.loading::after {
            content: "";
            position: absolute;
            width: 16px;
            height: 16px;
            top: 50%;
            left: 50%;
            margin-left: -8px;
            margin-top: -8px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .login-footer {
            padding: 1rem 1.5rem;
            background-color: #fafafa;
            border-top: 1px solid #f3f4f6;
            text-align: center;
            font-size: 0.625rem;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .login-container {
                margin: 0;
                box-shadow: none;
                border-radius: 0;
                min-height: 100vh;
                display: flex;
                flex-direction: column;
            }
            
            .login-body {
                flex: 1;
                display: flex;
                flex-direction: column;
                justify-content: center;
            }
            
            .login-footer {
                margin-top: auto;
            }
        }
        
        /* Accessibility */
        .form-input:focus-visible,
        .btn-submit:focus-visible,
        .link:focus-visible {
            outline: 2px solid var(--accent-color);
            outline-offset: 2px;
        }
        
        /* Animations */
        .alert {
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">
                <img src="<?php echo APP_PATH; ?>/assets/images/nexio-icon.svg" alt="Nexio Logo">
            </div>
            <h1>Nexio</h1>
            <p>Accedi al tuo account</p>
        </div>
        
        <div class="login-body">
            <form method="POST" action="" id="loginForm">
                <?php if ($error): ?>
                <div class="alert alert-error" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="username" class="form-label">Username o Email</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        class="form-input"
                        placeholder="Inserisci username o email"
                        required 
                        autofocus
                        autocomplete="username"
                        <?php echo $isBlocked ? 'disabled' : ''; ?>
                    >
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-input"
                        placeholder="Inserisci password"
                        required
                        autocomplete="current-password"
                        <?php echo $isBlocked ? 'disabled' : ''; ?>
                    >
                </div>
                
                <div class="form-footer">
                    <div class="checkbox-wrapper">
                        <input type="checkbox" id="remember" name="remember" value="1">
                        <label for="remember">Ricordami</label>
                    </div>
                    <a href="<?php echo APP_PATH; ?>/recupera-password.php" class="link">
                        Password dimenticata?
                    </a>
                </div>
                
                <button 
                    type="submit" 
                    class="btn-submit" 
                    id="loginBtn"
                    <?php echo $isBlocked ? 'disabled' : ''; ?>
                >
                    Accedi
                </button>
            </form>
        </div>
        
        <div class="login-footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. Tutti i diritti riservati.</p>
        </div>
    </div>
    
    <script>
        // Gestione form
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            btn.disabled = true;
            btn.classList.add('loading');
        });
        
        // Auto-hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(function() {
                    alert.remove();
                }, 300);
            });
        }, 5000);
    </script>
</body>
</html> 