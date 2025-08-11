<?php
/**
 * BrevoSMTP - Invio email tramite SMTP di Brevo
 * 
 * Utilizza le credenziali SMTP di Brevo per l'invio email
 */

class BrevoSMTP {
    private static $instance = null;
    private $config;
    
    private function __construct() {
        $this->config = [
            'host' => 'smtp-relay.brevo.com',
            'port' => 587,
            'username' => '92cc1e002@smtp-brevo.com',
            'password' => 'xsmtpsib-63dbb8e04720fb90ecfa0008096ad8a29b88c40207ea340c8b82c5d97c8d2d70-aX0LNnV92pwYfKsW',
            'from_email' => 'info@nexiosolution.it',
            'from_name' => 'Nexio Platform',
            'encryption' => 'tls'
        ];
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Invia email tramite SMTP di Brevo
     */
    public function send($to, $subject, $body, $isHtml = true) {
        try {
            error_log('BrevoSMTP::send() chiamato');
            error_log('To: ' . (is_array($to) ? implode(',', $to) : $to));
            error_log('Subject: ' . $subject);
            
            // Connetti al server SMTP
            $socket = $this->connectSMTP();
            
            // Autenticazione
            $this->authenticateSMTP($socket);
            
            // Invia email
            $this->sendSMTPMessage($socket, $to, $subject, $body, $isHtml);
            
            // Chiudi connessione
            fwrite($socket, "QUIT\r\n");
            fgets($socket, 515);
            fclose($socket);
            
            // Log successo
            error_log('BrevoSMTP: Email inviata con successo a ' . (is_array($to) ? implode(',', $to) : $to));
            $this->saveToDatabase($to, $subject, $body, 'sent');
            
            return true;
            
        } catch (Exception $e) {
            error_log('BrevoSMTP Error: ' . $e->getMessage());
            $this->saveToDatabase($to, $subject, $body, 'failed', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Connetti al server SMTP
     */
    private function connectSMTP() {
        error_log('BrevoSMTP: Tentativo connessione a ' . $this->config['host'] . ':' . $this->config['port']);
        
        $socket = @fsockopen($this->config['host'], $this->config['port'], $errno, $errstr, 30);
        
        if (!$socket) {
            error_log('BrevoSMTP: Connessione fallita - ' . $errstr . ' (' . $errno . ')');
            throw new Exception("Connessione fallita: $errstr ($errno)");
        }
        
        error_log('BrevoSMTP: Connessione stabilita');
        
        // Leggi risposta di benvenuto
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '220') {
            throw new Exception('Server response error: ' . $response);
        }
        
        // EHLO
        fwrite($socket, "EHLO localhost\r\n");
        while ($line = fgets($socket, 515)) {
            if (substr($line, 3, 1) == ' ') break;
        }
        
        // STARTTLS
        if ($this->config['encryption'] === 'tls') {
            error_log('BrevoSMTP: Invio comando STARTTLS');
            fwrite($socket, "STARTTLS\r\n");
            $response = fgets($socket, 515);
            
            if (substr($response, 0, 3) !== '220') {
                throw new Exception('STARTTLS failed: ' . $response);
            }
            
            error_log('BrevoSMTP: Abilitazione crittografia TLS');
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception('Impossibile abilitare TLS');
            }
            
            error_log('BrevoSMTP: TLS abilitato, invio EHLO dopo STARTTLS');
            // EHLO again after STARTTLS
            fwrite($socket, "EHLO localhost\r\n");
            while ($line = fgets($socket, 515)) {
                if (substr($line, 3, 1) == ' ') break;
            }
        }
        
        return $socket;
    }
    
    /**
     * Autenticazione SMTP
     */
    private function authenticateSMTP($socket) {
        // AUTH LOGIN
        fwrite($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket, 515);
        
        if (substr($response, 0, 3) !== '334') {
            throw new Exception('AUTH LOGIN failed: ' . $response);
        }
        
        // Username
        fwrite($socket, base64_encode($this->config['username']) . "\r\n");
        $response = fgets($socket, 515);
        
        if (substr($response, 0, 3) !== '334') {
            throw new Exception('Username failed: ' . $response);
        }
        
        // Password
        fwrite($socket, base64_encode($this->config['password']) . "\r\n");
        $response = fgets($socket, 515);
        
        if (substr($response, 0, 3) !== '235') {
            throw new Exception('Authentication failed: ' . $response);
        }
        
        error_log('BrevoSMTP: Autenticazione completata con successo');
    }
    
    /**
     * Invia messaggio SMTP
     */
    private function sendSMTPMessage($socket, $to, $subject, $body, $isHtml) {
        // MAIL FROM
        fwrite($socket, "MAIL FROM: <{$this->config['from_email']}>\r\n");
        $response = fgets($socket, 515);
        
        if (substr($response, 0, 3) !== '250') {
            throw new Exception('MAIL FROM failed: ' . $response);
        }
        
        // RCPT TO (supporta array di destinatari)
        $recipients = is_array($to) ? $to : [$to];
        foreach ($recipients as $recipient) {
            fwrite($socket, "RCPT TO: <$recipient>\r\n");
            $response = fgets($socket, 515);
            
            if (substr($response, 0, 3) !== '250') {
                throw new Exception('RCPT TO failed for ' . $recipient . ': ' . $response);
            }
        }
        
        // DATA
        fwrite($socket, "DATA\r\n");
        $response = fgets($socket, 515);
        
        if (substr($response, 0, 3) !== '354') {
            throw new Exception('DATA failed: ' . $response);
        }
        
        // Costruisci il messaggio con gli header corretti
        $message = $this->buildMessage($to, $subject, $body, $isHtml);
        
        // Invia il messaggio
        fwrite($socket, $message . "\r\n.\r\n");
        $response = fgets($socket, 515);
        
        if (substr($response, 0, 3) !== '250') {
            throw new Exception('Message sending failed: ' . $response);
        }
    }
    
    /**
     * Costruisci il messaggio email con gli header corretti
     */
    private function buildMessage($to, $subject, $body, $isHtml) {
        // Headers principali
        $message = "Date: " . date('r') . "\r\n";
        $message .= "From: {$this->config['from_name']} <{$this->config['from_email']}>\r\n";
        $message .= "To: " . (is_array($to) ? implode(', ', $to) : $to) . "\r\n";
        $message .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $message .= "Message-ID: <" . uniqid() . "@{$this->config['host']}>\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        
        // Content-Type
        if ($isHtml) {
            $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        } else {
            $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        }
        
        $message .= "Content-Transfer-Encoding: 8bit\r\n";
        $message .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        $message .= "Reply-To: {$this->config['from_email']}\r\n";
        $message .= "Return-Path: {$this->config['from_email']}\r\n";
        
        // Headers per disabilitare il tracking di Brevo
        $message .= "X-Mailin-track-links: 0\r\n";
        $message .= "X-Mailin-track-opens: 0\r\n";
        
        // Linea vuota tra headers e body
        $message .= "\r\n";
        
        // Body
        $message .= $body;
        
        return $message;
    }
    
    /**
     * Salva nel database (opzionale)
     */
    private function saveToDatabase($to, $subject, $body, $status, $error = null) {
        try {
            // Controlla se siamo in una transazione
            if (!db_connection()->inTransaction()) {
                // Verifica se la tabella notifiche_email esiste
                $tableExists = db_query("
                    SELECT COUNT(*) as count 
                    FROM information_schema.tables 
                    WHERE table_schema = DATABASE() 
                    AND table_name = 'notifiche_email'
                ")->fetch();
                
                if ($tableExists['count'] > 0) {
                    // Inserisci il record
                    db_insert('notifiche_email', [
                        'destinatario_email' => is_array($to) ? implode(',', $to) : $to,
                        'oggetto' => $subject,
                        'contenuto' => $body,
                        'stato' => $status === 'sent' ? 'sent' : 'failed',
                        'inviato_il' => $status === 'sent' ? date('Y-m-d H:i:s') : null,
                        'ultimo_errore' => $error
                    ]);
                }
            }
        } catch (Exception $e) {
            // Non bloccare l'invio email per errori di database
            error_log('BrevoSMTP: Errore salvataggio database - ' . $e->getMessage());
        }
    }
}
?>