<?php
/**
 * Pagina per il recupero password
 */

require_once 'backend/config/config.php';
require_once 'backend/utils/Mailer.php';

/**
 * Genera una password temporanea sicura
 */
function generateTemporaryPassword($length = 12) {
    $uppercase = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lowercase = 'abcdefghjkmnpqrstuvwxyz';
    $numbers = '23456789';
    $symbols = '!@#$%^&*';
    
    $password = '';
    
    // Garantisci almeno un carattere di ogni tipo
    $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
    $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
    $password .= $numbers[random_int(0, strlen($numbers) - 1)];
    $password .= $symbols[random_int(0, strlen($symbols) - 1)];
    
    // Riempi il resto con caratteri casuali
    $allChars = $uppercase . $lowercase . $numbers . $symbols;
    for ($i = 4; $i < $length; $i++) {
        $password .= $allChars[random_int(0, strlen($allChars) - 1)];
    }
    
    // Mescola la password
    return str_shuffle($password);
}

// Fix temporaneo: aggiungi le colonne mancanti se non esistono
try {
    $db = db_connection();
    
    // Verifica se le colonne esistono
    $checkColumns = $db->query("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'utenti' 
        AND COLUMN_NAME IN ('password_reset_token', 'password_reset_expires', 'last_password_change')
    ");
    
    $existingColumns = [];
    while ($row = $checkColumns->fetch(PDO::FETCH_ASSOC)) {
        $existingColumns[] = $row['COLUMN_NAME'];
    }
    
    // Aggiungi le colonne mancanti
    if (!in_array('password_reset_token', $existingColumns)) {
        $db->exec("ALTER TABLE utenti ADD COLUMN password_reset_token VARCHAR(255) DEFAULT NULL COMMENT 'Token per il reset della password'");
    }
    
    if (!in_array('password_reset_expires', $existingColumns)) {
        $db->exec("ALTER TABLE utenti ADD COLUMN password_reset_expires DATETIME DEFAULT NULL COMMENT 'Scadenza del token di reset password'");
    }
    
    if (!in_array('last_password_change', $existingColumns)) {
        $db->exec("ALTER TABLE utenti ADD COLUMN last_password_change DATETIME DEFAULT NULL COMMENT 'Data ultimo cambio password'");
    }
    
} catch (Exception $e) {
    // Log dell'errore ma continua comunque
    error_log("Errore durante il controllo/aggiunta colonne: " . $e->getMessage());
}

$message = '';
$messageType = '';
$step = 'request'; // request, sent

// Gestione form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 'request') {
        // Richiesta reset password
        $email = trim($_POST['email']);
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Inserisci un indirizzo email valido.';
            $messageType = 'error';
        } else {
            // Verifica se l'utente esiste
            $stmt = db_query("
                SELECT id, nome, cognome, email 
                FROM utenti 
                WHERE email = ? AND attivo = 1
            ", [$email]);
            
            $user = $stmt ? $stmt->fetch() : null;
            
            if ($user) {
                // Genera una password temporanea sicura
                $tempPassword = generateTemporaryPassword();
                $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);
                $passwordScadenza = date('Y-m-d', strtotime('+1 day')); // Scade domani
                
                // Aggiorna utente con password temporanea e forza cambio al primo accesso
                db_update('utenti', 
                    [
                        'password' => $passwordHash,
                        'password_scadenza' => $passwordScadenza,
                        'primo_accesso' => 1, // Forza cambio password al login
                        'password_reset_token' => null,
                        'password_reset_expires' => null,
                        'last_password_change' => date('Y-m-d H:i:s')
                    ],
                    'id = :id',
                    ['id' => $user['id']]
                );
                
                // Log attività
                try {
                    require_once 'backend/utils/ActivityLogger.php';
                    $logger = ActivityLogger::getInstance();
                    $logger->log('password_reset', 'Password temporanea generata per recupero', null, [
                        'user_id' => $user['id'],
                        'email' => $email
                    ]);
                } catch (Exception $e) {
                    // Non bloccare per errori di log
                }
                
                // Invia email con password temporanea
                try {
                    require_once 'backend/utils/EmailTemplateOutlook.php';
                    $mailer = Mailer::getInstance();
                    
                    $subject = APP_NAME . ' - Password Temporanea';
                    
                    // Usa il template Outlook per compatibilità
                    $body = EmailTemplateOutlook::passwordRecovery([
                        'nome' => $user['nome'],
                        'cognome' => $user['cognome'],
                        'email' => $email,
                        'password_temporanea' => $tempPassword,
                        'scadenza' => '24 ore'
                    ]);
                    
                    $emailSent = $mailer->send($email, $subject, $body);
                    
                    if ($emailSent) {
                        $step = 'sent';
                    } else {
                        $message = 'Errore durante l\'invio dell\'email. Riprova più tardi.';
                        $messageType = 'error';
                    }
                    
                } catch (Exception $e) {
                    error_log("Errore invio email reset password: " . $e->getMessage());
                    $message = 'Errore durante l\'invio dell\'email. Riprova più tardi.';
                    $messageType = 'error';
                }
                
            } else {
                // Non rivelare se l'email esiste o no per motivi di sicurezza
                $step = 'sent';
            }
        }
        
    } elseif ($step === 'reset') {
        // Reset effettivo della password
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // Validazioni
        if (strlen($newPassword) < 8) {
            $message = 'La password deve essere di almeno 8 caratteri.';
            $messageType = 'error';
        } elseif (!preg_match('/[A-Z]/', $newPassword)) {
            $message = 'La password deve contenere almeno una lettera maiuscola.';
            $messageType = 'error';
        } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $newPassword)) {
            $message = 'La password deve contenere almeno un carattere speciale.';
            $messageType = 'error';
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'Le password non coincidono.';
            $messageType = 'error';
        } else {
            try {
                db_connection()->beginTransaction();
                
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
                    $message = 'La password non può essere uguale a una delle ultime 3 password utilizzate.';
                    $messageType = 'error';
                } else {
                    // Aggiorna password
                    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $passwordScadenza = date('Y-m-d', strtotime('+90 days'));
                    
                    db_update('utenti',
                        [
                            'password' => $passwordHash,
                            'password_scadenza' => $passwordScadenza,
                            'password_reset_token' => null,
                            'password_reset_expires' => null,
                            'primo_accesso' => 0,
                            'last_password_change' => date('Y-m-d H:i:s')
                        ],
                        'id = :id',
                        ['id' => $user['id']]
                    );
                    
                    // Salva nella cronologia password
                    db_insert('password_history', [
                        'utente_id' => $user['id'],
                        'password_hash' => $passwordHash
                    ]);
                    
                    // Mantieni solo le ultime 10 password
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
                    
                    $message = 'Password reimpostata con successo! Ora puoi accedere con la nuova password.';
                    $messageType = 'success';
                    
                    // Log dell'attività
                    try {
                        require_once 'backend/utils/ActivityLogger.php';
                        $logger = ActivityLogger::getInstance();
                        $logger->log('password_reset', 'Password reimpostata tramite email', null, ['user_id' => $user['id']]);
                    } catch (Exception $e) {
                        // Non bloccare per errori di log
                    }
                    
                    // Redirect al login dopo 3 secondi
                    header("Refresh: 3; url=" . APP_PATH . "/login.php");
                }
                
            } catch (Exception $e) {
                db_connection()->rollBack();
                error_log("Errore reset password: " . $e->getMessage());
                $message = 'Errore durante il reset della password. Riprova.';
                $messageType = 'error';
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
    <meta name="description" content="Recupera Password - <?php echo APP_NAME; ?>">
    <meta name="robots" content="noindex, nofollow">
    
    <title>Recupera Password - <?php echo APP_NAME; ?></title>
    
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
        
        .reset-container {
            background: var(--bg-secondary);
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 440px;
            overflow: hidden;
        }
        
        .reset-header {
            padding: 2.5rem 2rem 2rem;
            text-align: center;
            border-bottom: 1px solid var(--border-color);
        }
        
        .icon-wrapper {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: #dbeafe;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .icon-wrapper i {
            font-size: 2.5rem;
            color: #3b82f6;
        }
        
        .reset-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .reset-header p {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .reset-body {
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
        
        .text-muted {
            color: var(--text-secondary);
            font-size: 0.75rem;
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
        
        .btn-secondary {
            background-color: #f3f4f6;
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        
        .btn-secondary:hover {
            background-color: #e5e7eb;
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
        
        .success-message {
            text-align: center;
            padding: 40px 20px;
        }
        
        .success-message i {
            font-size: 64px;
            color: #10b981;
            margin-bottom: 20px;
        }
        
        .success-message h2 {
            color: var(--text-primary);
            margin-bottom: 15px;
        }
        
        .success-message p {
            color: var(--text-secondary);
        }
        
        @media (max-width: 480px) {
            .reset-container {
                margin: 0 -20px;
                border-radius: 0;
                min-height: 100vh;
                display: flex;
                flex-direction: column;
            }
            
            .reset-body {
                flex: 1;
            }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <?php if ($step === 'sent'): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <h2>Password Temporanea Inviata!</h2>
                <p>Se l'indirizzo email è associato a un account, riceverai una password temporanea via email.</p>
                <p style="margin-top: 15px; color: #dc3545;">
                    <strong>⚠️ Importante:</strong> Dovrai cambiare la password al primo accesso.
                </p>
                <div class="mt-3">
                    <a href="<?php echo APP_PATH; ?>/login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Vai al Login
                    </a>
                </div>
            </div>
            
        <?php else: ?>
            <div class="reset-header">
                <div class="icon-wrapper">
                    <i class="fas fa-key"></i>
                </div>
                <h1>Recupera Password</h1>
                <p>
                    Inserisci il tuo indirizzo email per ricevere una password temporanea.
                </p>
            </div>
            
            <div class="reset-body">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($step === 'request'): ?>
                    <form method="POST">
                        <div class="form-group">
                            <label for="email">Indirizzo Email</label>
                            <input type="email" name="email" id="email" class="form-control" 
                                   placeholder="La tua email registrata" required autofocus>
                            <small class="text-muted">Inserisci l'email associata al tuo account</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-paper-plane"></i> Invia Password Temporanea
                        </button>
                    </form>
                <?php endif; ?>
                
                <div class="text-center mt-3">
                    <a href="<?php echo APP_PATH; ?>/login.php" style="color: var(--accent-color); text-decoration: none; font-size: 0.875rem;">
                        <i class="fas fa-arrow-left"></i> Torna al Login
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>