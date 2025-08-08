<?php
/**
 * Process email queue - should be run via cron
 */
require_once dirname(__DIR__) . '/backend/config/config.php';
require_once dirname(__DIR__) . '/backend/utils/Mailer.php';

// Process only via CLI
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

echo "[" . date('Y-m-d H:i:s') . "] Starting email queue processing...\n";

try {
    $pdo = db_connection();
    $mailer = Mailer::getInstance();
    
    if (!$mailer->isConfigured()) {
        echo "Email is not configured. Exiting.\n";
        exit;
    }
    
    // Get pending emails
    $stmt = $pdo->prepare("
        SELECT * FROM notifiche_email 
        WHERE stato = 'in_coda' 
        AND (tentativi < 3 OR tentativi IS NULL)
        ORDER BY data_creazione ASC
        LIMIT 10
    ");
    $stmt->execute();
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($emails)) {
        echo "No emails in queue.\n";
        exit;
    }
    
    echo "Found " . count($emails) . " emails to process.\n";
    
    foreach ($emails as $email) {
        echo "Processing email ID: {$email['id']} to {$email['destinatario']}... ";
        
        try {
            // Send email
            $result = $mailer->send(
                $email['destinatario'],
                $email['oggetto'],
                $email['contenuto']
            );
            
            if ($result) {
                // Mark as sent
                $stmt = $pdo->prepare("
                    UPDATE notifiche_email 
                    SET stato = 'inviata', data_invio = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$email['id']]);
                echo "SENT\n";
            } else {
                // Increment attempts
                $stmt = $pdo->prepare("
                    UPDATE notifiche_email 
                    SET tentativi = COALESCE(tentativi, 0) + 1,
                        errore = 'Failed to send'
                    WHERE id = ?
                ");
                $stmt->execute([$email['id']]);
                echo "FAILED\n";
            }
        } catch (Exception $e) {
            // Mark as failed
            $stmt = $pdo->prepare("
                UPDATE notifiche_email 
                SET tentativi = COALESCE(tentativi, 0) + 1,
                    errore = ?,
                    stato = IF(COALESCE(tentativi, 0) >= 2, 'fallita', stato)
                WHERE id = ?
            ");
            $stmt->execute([$e->getMessage(), $email['id']]);
            echo "ERROR: " . $e->getMessage() . "\n";
        }
        
        // Small delay between emails
        usleep(500000); // 0.5 seconds
    }
    
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    error_log("Email queue processing error: " . $e->getMessage());
}

echo "[" . date('Y-m-d H:i:s') . "] Email queue processing completed.\n";
?>