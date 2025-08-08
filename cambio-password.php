<?php
require_once 'backend/config/config.php';
require_once 'backend/utils/ActivityLogger.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$user = $auth->getUser();
$logger = ActivityLogger::getInstance();

// Verifica se l'utente deve cambiare password
$stmt = db_query("
    SELECT primo_accesso, password_scadenza 
    FROM utenti 
    WHERE id = ?
", [$user['id']]);
$userData = $stmt->fetch();

$isPrimoAccesso = $userData['primo_accesso'];
$isPasswordScaduta = $userData['password_scadenza'] && strtotime($userData['password_scadenza']) < time();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validazioni
    if (strlen($newPassword) < 8) {
        $message = "La nuova password deve essere di almeno 8 caratteri!";
        $messageType = 'error';
    } elseif (!preg_match('/[A-Z]/', $newPassword)) {
        $message = "La password deve contenere almeno una lettera maiuscola!";
        $messageType = 'error';
    } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $newPassword)) {
        $message = "La password deve contenere almeno un carattere speciale!";
        $messageType = 'error';
    } elseif ($newPassword !== $confirmPassword) {
        $message = "Le password non coincidono!";
        $messageType = 'error';
    } elseif ($newPassword === $currentPassword) {
        $message = "La nuova password deve essere diversa dalla password attuale!";
        $messageType = 'error';
    } else {
        // Verifica password attuale
        $stmt = db_query("SELECT password FROM utenti WHERE id = ?", [$user['id']]);
        $userPassword = $stmt->fetchColumn();
        
        if (!password_verify($currentPassword, $userPassword)) {
            $message = "Password attuale non corretta!";
            $messageType = 'error';
        } else {
            // Verifica che la password non sia stata usata nelle ultime 3 volte
            $stmt = db_query("
                SELECT password_hash 
                FROM password_history 
                WHERE utente_id = ? 
                ORDER BY data_cambio DESC 
                LIMIT 3
            ", [$user['id']]);
            
            $passwordUsedBefore = false;
            while ($row = $stmt->fetch()) {
                if (password_verify($newPassword, $row['password_hash'])) {
                    $passwordUsedBefore = true;
                    break;
                }
            }
            
            if ($passwordUsedBefore) {
                $message = "La password non può essere uguale a una delle ultime 3 password utilizzate!";
                $messageType = 'error';
            } else {
                // Aggiorna password
                $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $newPasswordScadenza = date('Y-m-d', strtotime('+90 days')); // Validità password 90 giorni
                
                db_connection()->beginTransaction();
                
                try {
                    // Aggiorna password utente
                    $stmt = db_query("
                        UPDATE utenti 
                        SET password = ?, 
                            primo_accesso = 0, 
                            password_scadenza = ?,
                            last_password_change = NOW()
                        WHERE id = ?
                    ", [$newPasswordHash, $newPasswordScadenza, $user['id']]);
                    
                    if ($stmt && $stmt->rowCount() > 0) {
                        // Salva password nella cronologia
                        db_query("
                            INSERT INTO password_history (utente_id, password_hash) 
                            VALUES (?, ?)
                        ", [$user['id'], $newPasswordHash]);
                        
                        // Mantieni solo le ultime 10 password nella cronologia
                        db_query("
                            DELETE FROM password_history 
                            WHERE utente_id = ? 
                            AND id NOT IN (
                                SELECT id FROM (
                                    SELECT id 
                                    FROM password_history 
                                    WHERE utente_id = ? 
                                    ORDER BY data_cambio DESC 
                                    LIMIT 10
                                ) AS t
                            )
                        ", [$user['id'], $user['id']]);
                        
                        db_connection()->commit();
                        
                        // Log attività
                        $logger->log('password_cambiata', "Password cambiata dall'utente", ['user_id' => $user['id']]);
                        
                        // Invia notifica email
                        try {
                            require_once 'backend/utils/NotificationCenter.php';
                            $notificationCenter = NotificationCenter::getInstance();
                            $notificationCenter->notifyPasswordChanged($user);
                        } catch (Exception $e) {
                            error_log("Errore invio notifica password cambiata: " . $e->getMessage());
                        }
                        
                        // Redirect al dashboard
                        header('Location: dashboard.php');
                        exit;
                    } else {
                        throw new Exception("Errore durante l'aggiornamento della password");
                    }
                } catch (Exception $e) {
                    db_connection()->rollBack();
                    $message = "Errore durante l'aggiornamento della password!";
                    $messageType = 'error';
                    error_log("Errore cambio password: " . $e->getMessage());
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="Cambio password - <?php echo APP_NAME; ?>">
    <meta name="robots" content="noindex, nofollow">
    
    <title>Cambio Password - <?php echo APP_NAME; ?></title>
    
    <link rel="icon" type="image/svg+xml" href="<?php echo APP_PATH; ?>/assets/images/favicon.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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
            --accent-color: #2d5a9f;
            --accent-hover: #0f2847;
            --error-bg: #fef2f2;
            --error-text: #991b1b;
            --error-border: #fecaca;
            --warning-bg: #fef3c7;
            --warning-text: #92400e;
            --warning-border: #fcd34d;
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
        
        .password-container {
            background: var(--bg-secondary);
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 440px;
            overflow: hidden;
        }
        
        .password-header {
            padding: 2.5rem 2rem 2rem;
            text-align: center;
            border-bottom: 1px solid var(--border-color);
        }
        
        .icon-wrapper {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: #fee2e2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .icon-wrapper i {
            font-size: 2.5rem;
            color: #dc2626;
        }
        
        .password-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .password-header p {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .password-body {
            padding: 2rem;
        }
        
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .alert-error {
            background-color: var(--error-bg);
            color: var(--error-text);
            border: 1px solid var(--error-border);
        }
        
        .alert-warning {
            background-color: var(--warning-bg);
            color: var(--warning-text);
            border: 1px solid var(--warning-border);
        }
        
        .alert-success {
            background-color: var(--success-bg);
            color: var(--success-text);
            border: 1px solid var(--success-border);
        }
        
        .form-group {
            margin-bottom: 1.25rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .form-control {
            width: 100%;
            padding: 0.625rem 0.875rem;
            font-size: 0.875rem;
            line-height: 1.5;
            color: var(--text-primary);
            background-color: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            transition: all 0.2s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(45, 90, 159, 0.1);
        }
        
        .form-control:disabled {
            background-color: var(--bg-primary);
            cursor: not-allowed;
        }
        
        .text-muted {
            color: var(--text-secondary);
            font-size: 0.75rem;
        }
        
        .password-requirements {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            border: 1px solid #e2e8f0;
        }
        
        .password-requirements p {
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .password-requirements ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .password-requirements li {
            padding: 0.25rem 0;
            padding-left: 1.5rem;
            position: relative;
            color: var(--text-secondary);
        }
        
        .password-requirements li:before {
            content: '✓';
            position: absolute;
            left: 0;
            color: #10b981;
            font-weight: bold;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            line-height: 1;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        
        .btn-primary {
            background-color: var(--accent-color);
            color: white;
        }
        
        .btn-primary:hover:not(:disabled) {
            background-color: var(--accent-hover);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .btn-block {
            width: 100%;
        }
        
        .text-center {
            text-align: center;
        }
        
        .mt-3 {
            margin-top: 1rem;
        }
        
        .text-muted a {
            color: var(--accent-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        .text-muted a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 480px) {
            .password-container {
                margin: 0 -20px;
                border-radius: 0;
                min-height: 100vh;
                display: flex;
                flex-direction: column;
            }
            
            .password-body {
                flex: 1;
            }
        }
    </style>
</head>
<body>
    <div class="password-container">
        <div class="password-header">
            <div class="icon-wrapper">
                <i class="fas fa-key"></i>
            </div>
            <h1>Cambio Password <?php echo $isPrimoAccesso ? 'Obbligatorio' : 'Richiesto'; ?></h1>
            <?php if ($isPrimoAccesso): ?>
                <p>È il tuo primo accesso. Per motivi di sicurezza, devi impostare una nuova password.</p>
            <?php elseif ($isPasswordScaduta): ?>
                <p>La tua password è scaduta. Per continuare, devi impostarne una nuova.</p>
            <?php else: ?>
                <p>È necessario aggiornare la tua password per continuare.</p>
            <?php endif; ?>
        </div>
        
        <div class="password-body">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'warning' ? 'exclamation-triangle' : 'exclamation-circle'); ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" onsubmit="return validateForm()">
                <div class="form-group">
                    <label for="current_password">Password Attuale</label>
                    <input type="password" name="current_password" id="current_password" 
                           class="form-control" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="new_password">Nuova Password</label>
                    <input type="password" name="new_password" id="new_password" 
                           class="form-control" required minlength="8"
                           placeholder="Minimo 8 caratteri" onkeyup="checkPasswordStrength()">
                    <small class="text-muted">La password deve essere di almeno 8 caratteri</small>
                    <div id="password-strength" style="margin-top: 0.5rem;"></div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Conferma Nuova Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" 
                           class="form-control" required onkeyup="checkPasswordMatch()">
                    <div id="password-match" style="margin-top: 0.5rem;"></div>
                </div>
                
                <div class="password-requirements">
                    <p><strong>Requisiti password:</strong></p>
                    <ul>
                        <li>Almeno 8 caratteri</li>
                        <li>Almeno 1 lettera maiuscola</li>
                        <li>Almeno 1 carattere speciale (!@#$%^&*...)</li>
                        <li>Diversa dalle ultime 3 password</li>
                        <li>Scadrà dopo 60 giorni</li>
                    </ul>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block" id="submit-btn">
                    <i class="fas fa-lock"></i> Cambia Password
                </button>
            </form>
            
            <div class="text-center mt-3">
                <a href="logout.php" class="text-muted">
                    <i class="fas fa-sign-out-alt"></i> Esci
                </a>
            </div>
        </div>
    </div>
    
    <script>
        function checkPasswordStrength() {
            const password = document.getElementById('new_password').value;
            const strengthDiv = document.getElementById('password-strength');
            
            if (password.length === 0) {
                strengthDiv.innerHTML = '';
                return;
            }
            
            let strength = 0;
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]+/)) strength++;
            if (password.match(/[A-Z]+/)) strength++;
            if (password.match(/[0-9]+/)) strength++;
            if (password.match(/[!@#$%^&*(),.?":{}|<>]+/)) strength++;
            
            let strengthText = '';
            let strengthColor = '';
            
            switch(strength) {
                case 0:
                case 1:
                    strengthText = 'Molto debole';
                    strengthColor = '#ef4444';
                    break;
                case 2:
                    strengthText = 'Debole';
                    strengthColor = '#f59e0b';
                    break;
                case 3:
                    strengthText = 'Media';
                    strengthColor = '#eab308';
                    break;
                case 4:
                    strengthText = 'Forte';
                    strengthColor = '#22c55e';
                    break;
                case 5:
                    strengthText = 'Molto forte';
                    strengthColor = '#16a34a';
                    break;
            }
            
            strengthDiv.innerHTML = `<small style="color: ${strengthColor};">Forza password: ${strengthText}</small>`;
        }
        
        function checkPasswordMatch() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchDiv = document.getElementById('password-match');
            
            if (confirmPassword.length === 0) {
                matchDiv.innerHTML = '';
                return;
            }
            
            if (password === confirmPassword) {
                matchDiv.innerHTML = '<small style="color: #22c55e;"><i class="fas fa-check"></i> Le password coincidono</small>';
            } else {
                matchDiv.innerHTML = '<small style="color: #ef4444;"><i class="fas fa-times"></i> Le password non coincidono</small>';
            }
        }
        
        function validateForm() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                alert('Le password non coincidono!');
                return false;
            }
            
            if (password.length < 8) {
                alert('La password deve essere di almeno 8 caratteri!');
                return false;
            }
            
            return true;
        }
    </script>
</body>
</html>