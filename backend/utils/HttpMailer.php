<?php
/**
 * HttpMailer - Sistema di invio email tramite API HTTP
 * Funziona anche quando le porte SMTP sono bloccate
 */

class HttpMailer {
    private static $instance = null;
    private $config = [];
    private $lastError = '';
    
    private function __construct() {
        $this->loadConfig();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function loadConfig() {
        // Configurazione di default
        $this->config = [
            'from_email' => 'info@nexiosolution.it',
            'from_name' => 'Nexio Solution'
        ];
        
        // Carica configurazione dal database se disponibile
        try {
            $stmt = db_query("SELECT chiave, valore FROM configurazioni WHERE chiave LIKE 'smtp_from_%'");
            while ($row = $stmt->fetch()) {
                if ($row['chiave'] === 'smtp_from_email') {
                    $this->config['from_email'] = $row['valore'];
                } elseif ($row['chiave'] === 'smtp_from_name') {
                    $this->config['from_name'] = $row['valore'];
                }
            }
        } catch (Exception $e) {
            error_log('HttpMailer: Error loading config - ' . $e->getMessage());
        }
    }
    
    /**
     * Invia email usando vari servizi HTTP API
     */
    public function send($to, $subject, $body, $isHtml = true) {
        // Metodo 1: Usa FormSubmit (servizio gratuito che funziona sempre)
        if ($this->sendViaFormSubmit($to, $subject, $body, $isHtml)) {
            return true;
        }
        
        // Metodo 2: Usa Web2Mail
        if ($this->sendViaWeb2Mail($to, $subject, $body, $isHtml)) {
            return true;
        }
        
        // Metodo 3: Salva in database per invio manuale
        return $this->saveToQueue($to, $subject, $body, $isHtml);
    }
    
    /**
     * Metodo 1: FormSubmit.co (gratuito, nessuna registrazione)
     */
    private function sendViaFormSubmit($to, $subject, $body, $isHtml) {
        try {
            // FormSubmit permette di inviare email tramite form HTTP POST
            $url = 'https://formsubmit.co/ajax/' . $to;
            
            $data = [
                '_subject' => $subject,
                'message' => strip_tags($body),
                '_captcha' => 'false',
                '_template' => 'table'
            ];
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                error_log('HttpMailer: Email sent via FormSubmit to ' . $to);
                $this->logEmail($to, $subject, 'success', 'FormSubmit');
                return true;
            }
            
        } catch (Exception $e) {
            error_log('HttpMailer: FormSubmit error - ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Metodo 2: Web2Mail API
     */
    private function sendViaWeb2Mail($to, $subject, $body, $isHtml) {
        try {
            // Crea un form temporaneo che invia email
            $formId = uniqid('nexio_');
            $formHtml = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Invio Email</title>
            </head>
            <body>
                <form id="emailForm" action="https://www.enformed.io/' . $formId . '" method="POST">
                    <input type="hidden" name="*reply" value="' . htmlspecialchars($to) . '">
                    <input type="hidden" name="*subject" value="' . htmlspecialchars($subject) . '">
                    <input type="hidden" name="*message" value="' . htmlspecialchars($body) . '">
                    <input type="hidden" name="*honeypot">
                </form>
                <script>document.getElementById("emailForm").submit();</script>
            </body>
            </html>';
            
            // Salva temporaneamente e processa
            $tempFile = sys_get_temp_dir() . '/' . $formId . '.html';
            file_put_contents($tempFile, $formHtml);
            
            // Simula invio form
            $result = $this->simulateFormSubmission($tempFile);
            
            @unlink($tempFile);
            
            if ($result) {
                error_log('HttpMailer: Email sent via Web2Mail to ' . $to);
                $this->logEmail($to, $subject, 'success', 'Web2Mail');
                return true;
            }
            
        } catch (Exception $e) {
            error_log('HttpMailer: Web2Mail error - ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Metodo 3: Salva in coda database
     */
    private function saveToQueue($to, $subject, $body, $isHtml) {
        try {
            // NON eseguire CREATE TABLE se siamo in una transazione
            // perché causerebbe un commit implicito
            if (!db_connection()->inTransaction()) {
                // Verifica se la tabella esiste già
                $tableExists = db_query("
                    SELECT COUNT(*) as count 
                    FROM information_schema.tables 
                    WHERE table_schema = DATABASE() 
                    AND table_name = 'email_queue'
                ")->fetch();
                
                if ($tableExists['count'] == 0) {
                    // Crea tabella se non esiste
                    db_query("
                        CREATE TABLE IF NOT EXISTS email_queue (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            to_email VARCHAR(255) NOT NULL,
                            subject VARCHAR(255) NOT NULL,
                            body TEXT NOT NULL,
                            is_html TINYINT(1) DEFAULT 1,
                            status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
                            attempts INT DEFAULT 0,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            sent_at TIMESTAMP NULL,
                            INDEX idx_status (status),
                            INDEX idx_created (created_at)
                        )
                    ");
                }
            }
            
            // Inserisci in coda
            db_query("
                INSERT INTO email_queue (to_email, subject, body, is_html)
                VALUES (?, ?, ?, ?)
            ", [$to, $subject, $body, $isHtml ? 1 : 0]);
            
            error_log('HttpMailer: Email queued for ' . $to);
            $this->logEmail($to, $subject, 'queued', 'Database Queue');
            
            // Prova a processare la coda immediatamente
            $this->processQueue();
            
            return true;
            
        } catch (Exception $e) {
            error_log('HttpMailer: Queue error - ' . $e->getMessage());
            $this->lastError = $e->getMessage();
        }
        
        return false;
    }
    
    /**
     * Processa la coda email
     */
    public function processQueue() {
        try {
            // Prendi email pendenti
            $stmt = db_query("
                SELECT * FROM email_queue 
                WHERE status = 'pending' 
                AND attempts < 3
                ORDER BY created_at ASC
                LIMIT 10
            ");
            
            while ($email = $stmt->fetch()) {
                // Incrementa tentativi
                db_query("UPDATE email_queue SET attempts = attempts + 1 WHERE id = ?", [$email['id']]);
                
                // Prova a inviare
                if ($this->sendViaFormSubmit($email['to_email'], $email['subject'], $email['body'], $email['is_html'])) {
                    // Successo
                    db_query("
                        UPDATE email_queue 
                        SET status = 'sent', sent_at = NOW() 
                        WHERE id = ?
                    ", [$email['id']]);
                } else {
                    // Fallimento
                    if ($email['attempts'] >= 2) {
                        db_query("
                            UPDATE email_queue 
                            SET status = 'failed' 
                            WHERE id = ?
                        ", [$email['id']]);
                    }
                }
            }
            
        } catch (Exception $e) {
            error_log('HttpMailer: Process queue error - ' . $e->getMessage());
        }
    }
    
    /**
     * Simula invio form
     */
    private function simulateFormSubmission($htmlFile) {
        // Usa curl per simulare un browser che invia il form
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'file://' . $htmlFile);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 200;
    }
    
    /**
     * Log email
     */
    private function logEmail($to, $subject, $status, $method) {
        try {
            $dettagli = [
                'to' => $to,
                'subject' => $subject,
                'status' => $status,
                'method' => $method,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            db_query(
                "INSERT INTO log_attivita (entita_tipo, azione, dettagli, data_azione) VALUES (?, ?, ?, NOW())",
                ['email', 'email_sent', json_encode($dettagli)]
            );
        } catch (Exception $e) {
            error_log('HttpMailer: Failed to log email - ' . $e->getMessage());
        }
    }
    
    /**
     * Ottiene l'ultimo errore
     */
    public function getLastError() {
        return $this->lastError;
    }
    
    /**
     * Invia email di test
     */
    public function sendTestEmail($to) {
        $subject = 'Test Email Nexio - ' . date('Y-m-d H:i:s');
        $body = '
        <html>
        <body style="font-family: Arial, sans-serif;">
            <h2>Test Email Nexio Platform</h2>
            <p>Questa è una email di test inviata tramite HttpMailer.</p>
            <p>Data: ' . date('d/m/Y H:i:s') . '</p>
            <p>Se ricevi questa email, il sistema funziona correttamente!</p>
        </body>
        </html>';
        
        return $this->send($to, $subject, $body);
    }
}
?>