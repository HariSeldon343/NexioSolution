<?php
/**
 * UniversalMailer - Sistema di invio email universale con fallback automatici
 * Supporta multiple porte, metodi di autenticazione e fallback
 */

class UniversalMailer {
    private static $instance = null;
    private $config = [];
    private $debug = true;
    private $lastError = '';
    private $lastMethod = '';
    
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
        try {
            // Carica configurazione dal database
            $stmt = db_query("SELECT chiave, valore FROM configurazioni WHERE chiave LIKE 'smtp_%' OR chiave LIKE 'email_%'");
            
            while ($row = $stmt->fetch()) {
                $this->config[$row['chiave']] = $row['valore'];
            }
            
            // Configurazioni di default
            $defaults = [
                'smtp_host' => 'mail.nexiosolution.it',
                'smtp_username' => 'info@nexiosolution.it',
                'smtp_password' => 'Ricorda1991',
                'smtp_from_email' => 'info@nexiosolution.it',
                'smtp_from_name' => 'Nexio Solution'
            ];
            
            foreach ($defaults as $key => $value) {
                if (empty($this->config[$key])) {
                    $this->config[$key] = $value;
                }
            }
        } catch (Exception $e) {
            error_log('UniversalMailer: Error loading config - ' . $e->getMessage());
        }
    }
    
    /**
     * Invia email provando tutti i metodi disponibili
     */
    public function send($to, $subject, $body, $isHtml = true) {
        $this->log("=== UniversalMailer: Invio email ===");
        $this->log("Destinatario: $to");
        $this->log("Oggetto: $subject");
        
        // Array di metodi da provare in ordine
        $methods = [
            ['method' => 'sendWithPhpMailer', 'name' => 'PHPMailer'],
            ['method' => 'sendWithSwiftMailer', 'name' => 'SwiftMailer'],
            ['method' => 'sendWithMailFunction', 'name' => 'PHP mail()'],
            ['method' => 'sendWithSendmail', 'name' => 'Sendmail'],
            ['method' => 'sendWithCustomSMTP', 'name' => 'Custom SMTP']
        ];
        
        foreach ($methods as $methodInfo) {
            $method = $methodInfo['method'];
            $name = $methodInfo['name'];
            
            $this->log("\nTentativo con $name...");
            
            try {
                if ($this->$method($to, $subject, $body, $isHtml)) {
                    $this->log("✅ Email inviata con successo tramite $name!");
                    $this->lastMethod = $name;
                    $this->logEmailSent($to, $subject, 'success', $name);
                    return true;
                }
            } catch (Exception $e) {
                $this->log("❌ $name fallito: " . $e->getMessage());
                $this->lastError = $e->getMessage();
            }
        }
        
        $this->log("\n❌ Tutti i metodi di invio sono falliti!");
        $this->logEmailSent($to, $subject, 'failed', 'all methods failed');
        return false;
    }
    
    /**
     * Metodo 1: PHPMailer (se disponibile)
     */
    private function sendWithPhpMailer($to, $subject, $body, $isHtml) {
        // Verifica se PHPMailer è disponibile
        $autoloadPath = dirname(dirname(__DIR__)) . '/vendor/autoload.php';
        if (!file_exists($autoloadPath)) {
            throw new Exception("PHPMailer non installato - vendor/autoload.php non trovato");
        }
        
        require_once $autoloadPath;
        
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            // Configurazione server
            $mail->isSMTP();
            $mail->Host = $this->config['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['smtp_username'];
            $mail->Password = $this->config['smtp_password'];
            
            // Prova diverse porte e metodi di crittografia
            $ports = [
                ['port' => 465, 'encryption' => 'ssl'],
                ['port' => 587, 'encryption' => 'tls'],
                ['port' => 25, 'encryption' => '']
            ];
            
            foreach ($ports as $portConfig) {
                $mail->Port = $portConfig['port'];
                $mail->SMTPSecure = $portConfig['encryption'];
                
                $this->log("PHPMailer: Provo porta {$portConfig['port']} con {$portConfig['encryption']}");
                
                try {
                    // Configurazione mittente e destinatario
                    $mail->setFrom($this->config['smtp_from_email'], $this->config['smtp_from_name']);
                    $mail->addAddress($to);
                    
                    // Contenuto
                    $mail->isHTML($isHtml);
                    $mail->Subject = $subject;
                    $mail->Body = $body;
                    $mail->AltBody = strip_tags($body);
                    
                    $mail->send();
                    return true;
                } catch (Exception $e) {
                    $this->log("Porta {$portConfig['port']} fallita: " . $e->getMessage());
                    continue;
                }
            }
            
            throw new Exception("Tutte le porte fallite");
        } catch (Exception $e) {
            throw new Exception("PHPMailer error: " . $e->getMessage());
        }
    }
    
    /**
     * Metodo 2: SwiftMailer (se disponibile)
     */
    private function sendWithSwiftMailer($to, $subject, $body, $isHtml) {
        throw new Exception("SwiftMailer non implementato");
    }
    
    /**
     * Metodo 3: PHP mail() function
     */
    private function sendWithMailFunction($to, $subject, $body, $isHtml) {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: ' . ($isHtml ? 'text/html' : 'text/plain') . '; charset=UTF-8',
            'From: ' . $this->config['smtp_from_name'] . ' <' . $this->config['smtp_from_email'] . '>',
            'Reply-To: ' . $this->config['smtp_from_email'],
            'X-Mailer: PHP/' . phpversion()
        ];
        
        // Su Windows, configura sendmail_path se necessario
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $sendmailPath = ini_get('sendmail_path');
            if (empty($sendmailPath)) {
                // Prova a configurare sendmail per Windows
                $possiblePaths = [
                    'C:\\xampp\\sendmail\\sendmail.exe',
                    'C:\\wamp\\sendmail\\sendmail.exe',
                    '/opt/lampp/bin/sendmail'
                ];
                
                foreach ($possiblePaths as $path) {
                    if (file_exists($path)) {
                        ini_set('sendmail_path', "\"$path\" -t");
                        break;
                    }
                }
            }
        }
        
        $result = @mail($to, $subject, $body, implode("\r\n", $headers));
        
        if (!$result) {
            $error = error_get_last();
            throw new Exception("mail() function failed: " . ($error['message'] ?? 'Unknown error'));
        }
        
        return true;
    }
    
    /**
     * Metodo 4: Sendmail diretto
     */
    private function sendWithSendmail($to, $subject, $body, $isHtml) {
        // Cerca sendmail
        $sendmailPaths = [
            '/usr/sbin/sendmail',
            '/usr/lib/sendmail',
            '/opt/lampp/bin/sendmail',
            'C:\\xampp\\sendmail\\sendmail.exe'
        ];
        
        $sendmailPath = null;
        foreach ($sendmailPaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                $sendmailPath = $path;
                break;
            }
        }
        
        if (!$sendmailPath) {
            throw new Exception("Sendmail non trovato");
        }
        
        $from = $this->config['smtp_from_email'];
        $fromName = $this->config['smtp_from_name'];
        
        $headers = "From: $fromName <$from>\r\n";
        $headers .= "To: $to\r\n";
        $headers .= "Subject: $subject\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: " . ($isHtml ? "text/html" : "text/plain") . "; charset=UTF-8\r\n";
        $headers .= "\r\n";
        
        $message = $headers . $body;
        
        $process = popen("$sendmailPath -f $from $to", 'w');
        if (!$process) {
            throw new Exception("Impossibile avviare sendmail");
        }
        
        fwrite($process, $message);
        $result = pclose($process);
        
        if ($result !== 0) {
            throw new Exception("Sendmail ha restituito codice di errore: $result");
        }
        
        return true;
    }
    
    /**
     * Metodo 5: SMTP personalizzato con socket
     */
    private function sendWithCustomSMTP($to, $subject, $body, $isHtml) {
        // Array di porte da provare
        $ports = [465, 587, 25, 2525];
        
        foreach ($ports as $port) {
            try {
                $this->log("Custom SMTP: Provo porta $port");
                
                if ($this->sendViaSMTPSocket($to, $subject, $body, $isHtml, $port)) {
                    return true;
                }
            } catch (Exception $e) {
                $this->log("Porta $port fallita: " . $e->getMessage());
                continue;
            }
        }
        
        throw new Exception("Tutte le porte SMTP fallite");
    }
    
    /**
     * Invia email via socket SMTP
     */
    private function sendViaSMTPSocket($to, $subject, $body, $isHtml, $port) {
        $host = $this->config['smtp_host'];
        $username = $this->config['smtp_username'];
        $password = $this->config['smtp_password'];
        
        // Timeout breve per test veloce
        $timeout = 5;
        
        // Determina se usare SSL
        $useSSL = ($port == 465);
        $address = $useSSL ? "ssl://$host:$port" : "tcp://$host:$port";
        
        // Contesto SSL
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);
        
        // Connetti
        $socket = @stream_socket_client($address, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        
        if (!$socket) {
            throw new Exception("Connessione fallita: $errstr");
        }
        
        // Imposta timeout
        stream_set_timeout($socket, 5);
        
        // Leggi banner
        $response = fgets($socket, 512);
        if (substr($response, 0, 3) != '220') {
            fclose($socket);
            throw new Exception("Banner inaspettato: $response");
        }
        
        // EHLO
        fwrite($socket, "EHLO " . gethostname() . "\r\n");
        $response = $this->readSMTPResponse($socket);
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            throw new Exception("EHLO fallito");
        }
        
        // STARTTLS per porta 587
        if ($port == 587) {
            fwrite($socket, "STARTTLS\r\n");
            $response = fgets($socket, 512);
            if (substr($response, 0, 3) == '220') {
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            }
        }
        
        // AUTH LOGIN
        fwrite($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket, 512);
        if (substr($response, 0, 3) != '334') {
            fclose($socket);
            throw new Exception("AUTH LOGIN non supportato");
        }
        
        // Username
        fwrite($socket, base64_encode($username) . "\r\n");
        $response = fgets($socket, 512);
        
        // Password
        fwrite($socket, base64_encode($password) . "\r\n");
        $response = fgets($socket, 512);
        if (substr($response, 0, 3) != '235') {
            fclose($socket);
            throw new Exception("Autenticazione fallita");
        }
        
        // MAIL FROM
        fwrite($socket, "MAIL FROM: <{$this->config['smtp_from_email']}>\r\n");
        $response = fgets($socket, 512);
        
        // RCPT TO
        fwrite($socket, "RCPT TO: <$to>\r\n");
        $response = fgets($socket, 512);
        
        // DATA
        fwrite($socket, "DATA\r\n");
        $response = fgets($socket, 512);
        
        // Invia messaggio
        $message = $this->buildEmailMessage($to, $subject, $body, $isHtml);
        fwrite($socket, $message . "\r\n.\r\n");
        $response = fgets($socket, 512);
        
        // QUIT
        fwrite($socket, "QUIT\r\n");
        fclose($socket);
        
        return true;
    }
    
    /**
     * Legge risposta SMTP multilinea
     */
    private function readSMTPResponse($socket) {
        $data = '';
        while ($line = fgets($socket, 512)) {
            $data .= $line;
            if (substr($line, 3, 1) == ' ') break;
        }
        return $data;
    }
    
    /**
     * Costruisce il messaggio email
     */
    private function buildEmailMessage($to, $subject, $body, $isHtml) {
        $from = $this->config['smtp_from_email'];
        $fromName = $this->config['smtp_from_name'];
        
        $headers = [
            "Date: " . date('r'),
            "From: $fromName <$from>",
            "To: $to",
            "Subject: $subject",
            "Message-ID: <" . uniqid() . "@nexiosolution.it>",
            "MIME-Version: 1.0",
            "Content-Type: " . ($isHtml ? "text/html" : "text/plain") . "; charset=UTF-8",
            "Content-Transfer-Encoding: 8bit"
        ];
        
        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }
    
    /**
     * Log messaggi
     */
    private function log($message) {
        if ($this->debug) {
            error_log("[UniversalMailer] $message");
            if (php_sapi_name() === 'cli') {
                echo "[UniversalMailer] $message\n";
            }
        }
    }
    
    /**
     * Log email inviata
     */
    private function logEmailSent($to, $subject, $status, $method) {
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
            error_log('Failed to log email: ' . $e->getMessage());
        }
    }
    
    /**
     * Ottiene l'ultimo metodo utilizzato con successo
     */
    public function getLastMethod() {
        return $this->lastMethod;
    }
    
    /**
     * Ottiene l'ultimo errore
     */
    public function getLastError() {
        return $this->lastError;
    }
}
?>