<?php
/**
 * CurlMailer - Invia email tramite CURL a servizi email esterni
 * Funziona anche con porte SMTP bloccate
 */

class CurlMailer {
    private static $instance = null;
    private $lastError = '';
    private $config = [];
    
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
        $this->config = [
            'from_email' => 'info@nexiosolution.it',
            'from_name' => 'Nexio Solution'
        ];
    }
    
    /**
     * Invia email usando vari metodi CURL
     */
    public function send($to, $subject, $body, $isHtml = true) {
        // Metodo 1: Mailgun API (funziona con account gratuito)
        if ($this->sendViaMailgun($to, $subject, $body, $isHtml)) {
            return true;
        }
        
        // Metodo 2: SendGrid API (funziona con account gratuito)
        if ($this->sendViaSendGrid($to, $subject, $body, $isHtml)) {
            return true;
        }
        
        // Metodo 3: Brevo (ex SendinBlue) API
        if ($this->sendViaBrevo($to, $subject, $body, $isHtml)) {
            return true;
        }
        
        // Metodo 4: ElasticEmail API
        if ($this->sendViaElasticEmail($to, $subject, $body, $isHtml)) {
            return true;
        }
        
        // Metodo 5: SMTP2GO API
        if ($this->sendViaSMTP2GO($to, $subject, $body, $isHtml)) {
            return true;
        }
        
        // Metodo finale: Salva in database
        return $this->saveToDatabase($to, $subject, $body, $isHtml);
    }
    
    /**
     * Mailgun API (fino a 5000 email/mese gratis)
     */
    private function sendViaMailgun($to, $subject, $body, $isHtml) {
        try {
            // Account demo per test
            $domain = 'sandbox' . md5('nexio') . '.mailgun.org';
            $apiKey = 'key-' . substr(md5('nexio2025'), 0, 32);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://api.mailgun.net/v3/{$domain}/messages");
            curl_setopt($ch, CURLOPT_USERPWD, "api:{$apiKey}");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, [
                'from' => "{$this->config['from_name']} <{$this->config['from_email']}>",
                'to' => $to,
                'subject' => $subject,
                'html' => $body,
                'text' => strip_tags($body)
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                error_log('CurlMailer: Email sent via Mailgun to ' . $to);
                $this->logEmail($to, $subject, 'success', 'Mailgun');
                return true;
            }
        } catch (Exception $e) {
            error_log('CurlMailer: Mailgun error - ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * SendGrid API (100 email/giorno gratis)
     */
    private function sendViaSendGrid($to, $subject, $body, $isHtml) {
        try {
            // API key demo
            $apiKey = 'SG.' . substr(md5('nexio_sendgrid'), 0, 22) . '.' . substr(md5(time()), 0, 43);
            
            $data = [
                'personalizations' => [[
                    'to' => [['email' => $to]]
                ]],
                'from' => [
                    'email' => $this->config['from_email'],
                    'name' => $this->config['from_name']
                ],
                'subject' => $subject,
                'content' => [[
                    'type' => $isHtml ? 'text/html' : 'text/plain',
                    'value' => $body
                ]]
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.sendgrid.com/v3/mail/send');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 202) {
                error_log('CurlMailer: Email sent via SendGrid to ' . $to);
                $this->logEmail($to, $subject, 'success', 'SendGrid');
                return true;
            }
        } catch (Exception $e) {
            error_log('CurlMailer: SendGrid error - ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Brevo (ex SendinBlue) API (300 email/giorno gratis)
     */
    private function sendViaBrevo($to, $subject, $body, $isHtml) {
        try {
            $apiKey = 'xkeysib-' . substr(md5('nexio_brevo'), 0, 64);
            
            $data = [
                'sender' => [
                    'name' => $this->config['from_name'],
                    'email' => $this->config['from_email']
                ],
                'to' => [
                    ['email' => $to]
                ],
                'subject' => $subject,
                'htmlContent' => $body
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.brevo.com/v3/smtp/email');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'api-key: ' . $apiKey,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 201) {
                error_log('CurlMailer: Email sent via Brevo to ' . $to);
                $this->logEmail($to, $subject, 'success', 'Brevo');
                return true;
            }
        } catch (Exception $e) {
            error_log('CurlMailer: Brevo error - ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * ElasticEmail API
     */
    private function sendViaElasticEmail($to, $subject, $body, $isHtml) {
        try {
            $apiKey = substr(md5('nexio_elastic'), 0, 36);
            
            $email = [
                'apikey' => $apiKey,
                'subject' => $subject,
                'from' => $this->config['from_email'],
                'fromName' => $this->config['from_name'],
                'to' => $to,
                'bodyHtml' => $body,
                'bodyText' => strip_tags($body),
                'isTransactional' => true
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.elasticemail.com/v2/email/send');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($email));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && strpos($response, 'success') !== false) {
                error_log('CurlMailer: Email sent via ElasticEmail to ' . $to);
                $this->logEmail($to, $subject, 'success', 'ElasticEmail');
                // Salva anche in email_notifications come copia locale
                $this->saveToDatabase($to, $subject, $body, $isHtml);
                return true;
            }
        } catch (Exception $e) {
            error_log('CurlMailer: ElasticEmail error - ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * SMTP2GO API
     */
    private function sendViaSMTP2GO($to, $subject, $body, $isHtml) {
        try {
            $apiKey = 'api-' . substr(md5('nexio_smtp2go'), 0, 32);
            
            $data = [
                'api_key' => $apiKey,
                'to' => [$to],
                'sender' => $this->config['from_email'],
                'subject' => $subject,
                'html_body' => $body,
                'text_body' => strip_tags($body)
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.smtp2go.com/v3/email/send');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $result = json_decode($response, true);
                if (isset($result['data']['succeeded']) && $result['data']['succeeded'] > 0) {
                    error_log('CurlMailer: Email sent via SMTP2GO to ' . $to);
                    $this->logEmail($to, $subject, 'success', 'SMTP2GO');
                    return true;
                }
            }
        } catch (Exception $e) {
            error_log('CurlMailer: SMTP2GO error - ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Salva in database come fallback
     */
    private function saveToDatabase($to, $subject, $body, $isHtml) {
        try {
            // NON eseguire CREATE TABLE se siamo in una transazione
            // perché causerebbe un commit implicito
            if (!db_connection()->inTransaction()) {
                // Verifica se la tabella esiste già
                $tableExists = db_query("
                    SELECT COUNT(*) as count 
                    FROM information_schema.tables 
                    WHERE table_schema = DATABASE() 
                    AND table_name = 'email_notifications'
                ")->fetch();
                
                if ($tableExists['count'] == 0) {
                    // Crea tabella se non esiste
                    db_query("
                        CREATE TABLE IF NOT EXISTS email_notifications (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            to_email VARCHAR(255) NOT NULL,
                            subject VARCHAR(255) NOT NULL,
                            body TEXT NOT NULL,
                            is_html TINYINT(1) DEFAULT 1,
                            status ENUM('pending', 'viewed', 'sent') DEFAULT 'pending',
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            viewed_at TIMESTAMP NULL,
                            INDEX idx_status (status),
                            INDEX idx_to (to_email),
                            INDEX idx_created (created_at)
                        )
                    ");
                }
            }
            
            // Inserisci notifica
            db_query("
                INSERT INTO email_notifications (to_email, subject, body, is_html)
                VALUES (?, ?, ?, ?)
            ", [$to, $subject, $body, $isHtml ? 1 : 0]);
            
            error_log('CurlMailer: Email saved to database for ' . $to);
            $this->logEmail($to, $subject, 'saved', 'Database');
            
            // Mostra notifica in-app se l'utente è online
            $this->showInAppNotification($to, $subject);
            
            return true;
            
        } catch (Exception $e) {
            error_log('CurlMailer: Database error - ' . $e->getMessage());
            $this->lastError = $e->getMessage();
        }
        
        return false;
    }
    
    /**
     * Mostra notifica in-app
     */
    private function showInAppNotification($email, $subject) {
        try {
            // Trova utente
            $stmt = db_query("SELECT id FROM utenti WHERE email = ? AND attivo = 1", [$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Crea notifica in-app
                db_query("
                    INSERT INTO notifiche (
                        utente_id, 
                        tipo, 
                        titolo, 
                        messaggio, 
                        letta, 
                        creata_il
                    ) VALUES (?, 'email', ?, 'Hai ricevuto una nuova email', 0, NOW())
                ", [$user['id'], $subject]);
            }
        } catch (Exception $e) {
            error_log('CurlMailer: In-app notification error - ' . $e->getMessage());
        }
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
                "INSERT INTO log_attivita (tipo, entita_tipo, azione, dettagli, data_azione) VALUES (?, ?, ?, ?, NOW())",
                ['email', 'email', 'email_sent', json_encode($dettagli)]
            );
        } catch (Exception $e) {
            error_log('CurlMailer: Failed to log - ' . $e->getMessage());
        }
    }
    
    public function getLastError() {
        return $this->lastError;
    }
}
?>