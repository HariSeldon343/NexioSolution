<?php
/**
 * Script CRON per l'invio delle email programmate
 * Da eseguire ogni 5 minuti via CRON
 */

// Previeni accesso diretto via browser
if (php_sapi_name() !== 'cli' && !empty($_SERVER['REMOTE_ADDR'])) {
    die('Accesso non autorizzato');
}

require_once dirname(__DIR__) . '/../backend/config/config.php';
require_once dirname(__DIR__) . '/../backend/config/database.php';

// Configurazione email (da sostituire con i dati Infomaniak)
$smtp_config = [
    'host' => defined('SMTP_HOST') ? SMTP_HOST : 'mail.infomaniak.com',
    'port' => defined('SMTP_PORT') ? SMTP_PORT : 587,
    'secure' => defined('SMTP_SECURE') ? SMTP_SECURE : 'tls',
    'username' => defined('SMTP_USER') ? SMTP_USER : '',
    'password' => defined('SMTP_PASS') ? SMTP_PASS : '',
    'from' => defined('SMTP_FROM') ? SMTP_FROM : 'noreply@example.com',
    'from_name' => defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Piattaforma Collaborativa'
];

// Log function
function logCron($message, $type = 'info') {
    $log_file = dirname(__DIR__) . '/../logs/cron_email.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] [$type] $message" . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

try {
    $db = Database::getInstance();
    
    // Seleziona email da inviare
    $query = "SELECT * FROM notifiche_email 
              WHERE stato = 'in_coda' 
              AND programmata_per <= NOW() 
              AND tentativi < 3 
              ORDER BY priorita DESC, programmata_per ASC 
              LIMIT 10";
    
    $stmt = $db->query($query);
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($emails)) {
        logCron("Nessuna email da inviare");
        exit(0);
    }
    
    logCron("Trovate " . count($emails) . " email da inviare");
    
    foreach ($emails as $email) {
        try {
            // Qui implementare l'invio effettivo dell'email
            // Per ora simuliamo l'invio
            $success = sendEmail(
                $email['destinatario_email'],
                $email['destinatario_nome'],
                $email['oggetto'],
                $email['contenuto']
            );
            
            if ($success) {
                // Aggiorna stato email
                $update = $db->prepare("UPDATE notifiche_email 
                                       SET stato = 'inviata', 
                                           inviata_il = NOW() 
                                       WHERE id = ?");
                $update->execute([$email['id']]);
                
                logCron("Email ID {$email['id']} inviata a {$email['destinatario_email']}");
            } else {
                throw new Exception("Invio fallito");
            }
            
        } catch (Exception $e) {
            // Incrementa tentativi
            $update = $db->prepare("UPDATE notifiche_email 
                                   SET tentativi = tentativi + 1,
                                       errore_messaggio = ?,
                                       stato = CASE 
                                           WHEN tentativi >= 2 THEN 'errore' 
                                           ELSE 'in_coda' 
                                       END
                                   WHERE id = ?");
            $update->execute([$e->getMessage(), $email['id']]);
            
            logCron("Errore invio email ID {$email['id']}: " . $e->getMessage(), 'error');
        }
        
        // Pausa tra invii per evitare rate limiting
        sleep(1);
    }
    
} catch (Exception $e) {
    logCron("Errore critico: " . $e->getMessage(), 'critical');
    exit(1);
}

/**
 * Funzione per l'invio effettivo dell'email
 * Da implementare con PHPMailer o altra libreria
 */
function sendEmail($to, $to_name, $subject, $body) {
    global $smtp_config;
    
    // Per ambiente di test, simula sempre successo
    if (!empty($smtp_config['username']) && !empty($smtp_config['password'])) {
        // Qui implementare invio reale con PHPMailer
        // require_once 'PHPMailer/PHPMailer.php';
        // $mail = new PHPMailer(true);
        // ... configurazione SMTP ...
        return true;
    }
    
    // Simulazione per test
    return (rand(1, 10) > 2); // 80% successo
}

logCron("Script completato");
exit(0);
?> 