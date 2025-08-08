<?php
/**
 * Cron job per controllare le password in scadenza
 * Eseguire quotidianamente per inviare notifiche
 */

require_once dirname(__DIR__) . '/backend/config/config.php';
require_once dirname(__DIR__) . '/backend/utils/Mailer.php';
require_once dirname(__DIR__) . '/backend/utils/EmailTemplate.php';

echo "=== Controllo Password in Scadenza ===\n";
echo "Data: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Trova utenti con password in scadenza nei prossimi 7 giorni o scadute
    $query = "
        SELECT u.*, 
               DATEDIFF(password_scadenza, CURDATE()) as giorni_mancanti
        FROM utenti u
        WHERE u.attivo = 1
        AND (
            -- Password che scadranno nei prossimi 7 giorni
            (password_scadenza BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY))
            OR
            -- Password già scadute (ma utente ancora attivo)
            (password_scadenza < CURDATE())
        )
        ORDER BY password_scadenza ASC
    ";
    
    $stmt = db_query($query);
    $users = $stmt->fetchAll();
    
    echo "Utenti trovati: " . count($users) . "\n\n";
    
    $mailer = Mailer::getInstance();
    $sent = 0;
    $errors = 0;
    
    foreach ($users as $user) {
        echo "Elaborazione utente: {$user['email']} - ";
        
        if ($user['giorni_mancanti'] < 0) {
            echo "Password SCADUTA da " . abs($user['giorni_mancanti']) . " giorni\n";
            
            // Per password già scadute, invia reminder solo una volta a settimana (ogni lunedì)
            if (date('N') != 1) { // 1 = lunedì
                echo "  -> Saltato (reminder già scadute solo il lunedì)\n";
                continue;
            }
            
            $subject = "Password Scaduta - Accesso Limitato";
            $message = "La tua password è scaduta da " . abs($user['giorni_mancanti']) . " giorni. " .
                      "Per continuare ad utilizzare la piattaforma, è necessario cambiarla immediatamente.";
        } else {
            echo "Password in scadenza tra {$user['giorni_mancanti']} giorni\n";
            
            // Invia notifiche a 7, 3 e 1 giorno dalla scadenza
            if (!in_array($user['giorni_mancanti'], [7, 3, 1])) {
                echo "  -> Saltato (notifica non prevista per questo intervallo)\n";
                continue;
            }
            
            $subject = "Avviso: Password in Scadenza";
            $message = $user['giorni_mancanti'] == 1 
                ? "La tua password scadrà domani. Ti consigliamo di cambiarla oggi per evitare interruzioni."
                : "La tua password scadrà tra {$user['giorni_mancanti']} giorni. Ti consigliamo di cambiarla il prima possibile.";
        }
        
        // Genera email con template
        $emailHtml = EmailTemplate::passwordExpiring($user, max(0, $user['giorni_mancanti']));
        
        // Invia email
        $result = $mailer->send(
            $user['email'],
            $subject,
            $emailHtml
        );
        
        if ($result) {
            echo "  -> Email inviata con successo\n";
            $sent++;
            
            // Log attività
            if (class_exists('ActivityLogger')) {
                ActivityLogger::getInstance()->log(
                    'sistema',
                    'notifica_scadenza_password',
                    $user['id'],
                    "Notifica scadenza password inviata (giorni rimanenti: {$user['giorni_mancanti']})"
                );
            }
        } else {
            echo "  -> ERRORE invio email\n";
            $errors++;
        }
    }
    
    echo "\n=== Riepilogo ===\n";
    echo "Email inviate: $sent\n";
    echo "Errori: $errors\n";
    echo "Completato: " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "ERRORE CRITICO: " . $e->getMessage() . "\n";
    error_log("Cron password expiry check error: " . $e->getMessage());
}
?>