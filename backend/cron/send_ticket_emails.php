<?php
/**
 * Cron job per l'invio delle notifiche email dei ticket
 * Eseguire ogni 5 minuti
 */

require_once dirname(__DIR__, 2) . '/backend/config/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Se eseguito da CLI
if (php_sapi_name() !== 'cli') {
    die('Questo script può essere eseguito solo da CLI');
}

$db = Database::getInstance();

try {
    // Seleziona le notifiche in attesa (massimo 10 per volta)
    $stmt = $db->query("
        SELECT * FROM notifiche_email 
        WHERE stato = 'in_attesa' 
        AND tentativi < 3
        ORDER BY priorita ASC, data_creazione ASC
        LIMIT 10
    ");
    
    $notifiche = $stmt->fetchAll();
    
    if (empty($notifiche)) {
        echo "Nessuna notifica da inviare.\n";
        exit;
    }
    
    // Configurazione email
    $mail = new PHPMailer(true);
    
    // Configurazione server
    $mail->isSMTP();
    $mail->Host = EMAIL_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = EMAIL_USERNAME;
    $mail->Password = EMAIL_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = EMAIL_PORT;
    $mail->CharSet = 'UTF-8';
    
    // Mittente
    $mail->setFrom(EMAIL_FROM, APP_NAME);
    
    $inviate = 0;
    $errori = 0;
    
    foreach ($notifiche as $notifica) {
        try {
            // Reset per nuova email
            $mail->clearAddresses();
            $mail->clearAllRecipients();
            
            // Destinatario
            $mail->addAddress($notifica['destinatario_email'], $notifica['destinatario_nome']);
            
            // Contenuto email
            $mail->isHTML(true);
            $mail->Subject = $notifica['oggetto'];
            
            // Template email con stile Nexio
            $htmlBody = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            line-height: 1.6;
            color: #2c2c2c;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .email-header {
            background: #1b3f76;
            color: white;
            padding: 30px;
            text-align: center;
        }
        .email-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        .email-header p {
            margin: 5px 0 0 0;
            opacity: 0.9;
            font-size: 14px;
        }
        .email-body {
            padding: 30px;
        }
        .email-footer {
            background: #f9f9f9;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #eee;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #1b3f76;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin: 10px 0;
        }
        .btn:hover {
            background: #c19660;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        td {
            padding: 8px;
            border: 1px solid #ddd;
        }
        .label {
            background: #f5f5f5;
            font-weight: 600;
        }
        .priority-urgente {
            color: #dc2626;
            font-weight: bold;
        }
        .priority-alta {
            color: #f59e0b;
            font-weight: bold;
        }
        .priority-media {
            color: #3b82f6;
        }
        .priority-bassa {
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1>✦ ' . APP_NAME . '</h1>
            <p>' . APP_MOTTO . '</p>
        </div>
        <div class="email-body">
            ' . $notifica['contenuto'] . '
        </div>
        <div class="email-footer">
            <p>Questa è una notifica automatica da ' . APP_NAME . '</p>
            <p>&copy; ' . date('Y') . ' ' . APP_NAME . '. Tutti i diritti riservati.</p>
        </div>
    </div>
</body>
</html>';
            
            $mail->Body = $htmlBody;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $notifica['contenuto']));
            
            // Invia email
            $mail->send();
            
            // Aggiorna stato notifica
            $db->update('notifiche_email', 
                [
                    'stato' => 'inviata',
                    'data_invio' => date('Y-m-d H:i:s')
                ],
                'id = :id',
                ['id' => $notifica['id']]
            );
            
            $inviate++;
            echo "Email inviata a: {$notifica['destinatario_email']} - Oggetto: {$notifica['oggetto']}\n";
            
        } catch (Exception $e) {
            // Aggiorna tentativi e errore
            $db->update('notifiche_email',
                [
                    'tentativi' => $notifica['tentativi'] + 1,
                    'errore_messaggio' => $mail->ErrorInfo,
                    'stato' => ($notifica['tentativi'] + 1 >= 3) ? 'errore' : 'in_attesa'
                ],
                'id = :id',
                ['id' => $notifica['id']]
            );
            
            $errori++;
            echo "Errore invio email a: {$notifica['destinatario_email']} - Errore: {$mail->ErrorInfo}\n";
        }
        
        // Piccola pausa tra invii
        usleep(500000); // 0.5 secondi
    }
    
    echo "\n=== RIEPILOGO ===\n";
    echo "Email inviate: $inviate\n";
    echo "Errori: $errori\n";
    echo "Completato: " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "Errore critico: " . $e->getMessage() . "\n";
    exit(1);
} 