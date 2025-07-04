<?php
require_once 'backend/config/config.php';
require_once 'backend/utils/RateLimiter.php';

$auth = Auth::getInstance();
$rateLimiter = RateLimiter::getInstance();

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
            
            // Se remember me è selezionato, estendi la durata della sessione
            if ($remember) {
                // Qui potresti implementare un cookie remember me sicuro
                // Per ora estendiamo solo la durata della sessione
                $_SESSION['remember_me'] = true;
            }
            
            redirect(APP_PATH . '/dashboard.php');
        } else {
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
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --bg-primary: #faf8f5;
            --bg-secondary: #ffffff;
            --text-primary: #2c2c2c;
            --text-secondary: #6b6b6b;
            --border-color: #e8e8e8;
            --accent-color: #1b3f76;
            --accent-hover: #0f2847;
            --error-bg: #fef2f2;
            --error-text: #991b1b;
            --error-border: #fecaca;
            --success-bg: #f0fdf4;
            --success-text: #166534;
            --success-border: #bbf7d0;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: var(--bg-secondary);
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 400px;
            overflow: hidden;
        }
        
        .login-header {
            padding: 2.5rem 2rem 2rem;
            text-align: center;
            border-bottom: 1px solid var(--border-color);
        }
        
        .logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
        }
        
        .logo img {
            width: 100%;
            height: 100%;
        }
        
        .login-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .login-header p {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .login-body {
            padding: 2rem;
        }
        
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
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
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .form-input {
            width: 100%;
            padding: 0.625rem 0.875rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.875rem;
            transition: all 0.2s;
            background-color: var(--bg-secondary);
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(43, 87, 154, 0.1);
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
        }
        
        .link {
            color: var(--accent-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        .link:hover {
            color: var(--accent-hover);
            text-decoration: underline;
        }
        
        .btn-submit {
            width: 100%;
            padding: 0.75rem 1rem;
            background-color: var(--accent-color);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-submit:hover:not(:disabled) {
            background-color: var(--accent-hover);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-submit:active:not(:disabled) {
            transform: translateY(0);
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
            padding: 1.5rem 2rem;
            background-color: #fafafa;
            border-top: 1px solid var(--border-color);
            text-align: center;
            font-size: 0.75rem;
            color: var(--text-secondary);
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