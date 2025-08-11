<?php
session_start();
require_once '../backend/config/config.php';
require_once '../backend/middleware/Auth.php';

$auth = Auth::getInstance();
$error = '';

// Se già autenticato, redirect alla home
if ($auth->isAuthenticated()) {
    header('Location: index.php');
    exit();
}

// Gestione login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($auth->login($username, $password)) {
        header('Location: index.php');
        exit();
    } else {
        $error = 'Credenziali non valide';
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#2563eb">
    <title>Nexio - Login</title>
    
    <link rel="icon" type="image/svg+xml" href="../assets/images/nexio-icon.svg">
    <link rel="apple-touch-icon" href="../assets/images/nexio-icon.svg">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --danger: #ef4444;
            --light: #f8fafc;
            --dark: #1e293b;
            --secondary: #64748b;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 400px;
        }
        
        .logo-container {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .logo {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .logo img {
            width: 50px;
            height: 50px;
        }
        
        .app-name {
            color: white;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .app-tagline {
            color: rgba(255,255,255,0.8);
            font-size: 14px;
        }
        
        .login-card {
            background: white;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .form-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 24px;
            text-align: center;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 8px;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.2s, box-shadow 0.2s;
            -webkit-appearance: none;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .btn-login {
            width: 100%;
            padding: 14px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
        }
        
        .btn-login:hover {
            background: var(--primary-dark);
        }
        
        .btn-login:active {
            transform: scale(0.98);
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .remember-me input {
            margin-right: 8px;
        }
        
        .remember-me label {
            font-size: 14px;
            color: var(--secondary);
            cursor: pointer;
        }
        
        .forgot-password {
            text-align: center;
            margin-top: 20px;
        }
        
        .forgot-password a {
            color: var(--primary);
            text-decoration: none;
            font-size: 14px;
        }
        
        .desktop-link {
            text-align: center;
            margin-top: 32px;
            color: rgba(255,255,255,0.8);
            font-size: 14px;
        }
        
        .desktop-link a {
            color: white;
            text-decoration: none;
            font-weight: 600;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        .shake {
            animation: shake 0.5s;
        }
        
        /* Loading spinner */
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-left: 8px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo-container">
            <div class="logo">
                <img src="../assets/images/nexio-icon.svg" alt="Nexio" onerror="this.style.display='none'; this.parentElement.innerHTML='N';">
            </div>
            <h1 class="app-name">Nexio</h1>
            <p class="app-tagline">Piattaforma Collaborativa Mobile</p>
        </div>
        
        <div class="login-card">
            <h2 class="form-title">Accedi al tuo account</h2>
            
            <?php if ($error): ?>
            <div class="error-message shake">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="loginForm">
                <div class="form-group">
                    <label for="username" class="form-label">Username o Email</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        class="form-input" 
                        required 
                        autocomplete="username"
                        placeholder="Inserisci username o email"
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
                        placeholder="Inserisci password"
                    >
                </div>
                
                <div class="remember-me">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Ricordami</label>
                </div>
                
                <button type="submit" class="btn-login" id="loginBtn">
                    Accedi
                </button>
            </form>
            
            <div class="forgot-password">
                <a href="../recupera-password.php">Password dimenticata?</a>
            </div>
        </div>
        
        <div class="desktop-link">
            <a href="../login.php">Versione Desktop →</a>
        </div>
    </div>
    
    <script>
        // Form handling
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            btn.disabled = true;
            btn.innerHTML = 'Accesso in corso<span class="spinner"></span>';
        });
        
        // Auto-focus first input
        document.getElementById('username').focus();
        
        // Register service worker
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('sw.js').catch(console.error);
        }
    </script>
</body>
</html>