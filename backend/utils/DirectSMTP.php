<?php
/**
 * DirectSMTP - Implementazione diretta e semplice per l'invio email via SMTP
 * Compatibile con mail.nexiosolution.it
 */

class DirectSMTP {
    private $host;
    private $port;
    private $username;
    private $password;
    private $from;
    private $fromName;
    private $timeout = 30;
    private $debug = false;
    private $lastError = '';
    
    public function __construct($host, $port, $username, $password) {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
    }
    
    public function setFrom($email, $name = '') {
        $this->from = $email;
        $this->fromName = $name;
    }
    
    public function enableDebug($debug = true) {
        $this->debug = $debug;
    }
    
    private function log($message) {
        if ($this->debug) {
            error_log("[DirectSMTP] " . $message);
            echo "[" . date('H:i:s') . "] " . $message . "\n";
        }
    }
    
    public function getLastError() {
        return $this->lastError;
    }
    
    /**
     * Invia email usando fsockopen diretto
     */
    public function send($to, $subject, $body, $isHtml = true) {
        $this->log("Inizio invio email a: $to");
        
        // Connessione al server SMTP
        $socket = $this->connect();
        if (!$socket) {
            return false;
        }
        
        try {
            // EHLO
            if (!$this->sendCommand($socket, "EHLO " . gethostname(), 250)) {
                // Prova con HELO se EHLO fallisce
                if (!$this->sendCommand($socket, "HELO " . gethostname(), 250)) {
                    throw new Exception("EHLO/HELO fallito");
                }
            }
            
            // AUTH LOGIN
            if (!$this->sendCommand($socket, "AUTH LOGIN", 334)) {
                throw new Exception("AUTH LOGIN fallito");
            }
            
            // Username (base64)
            if (!$this->sendCommand($socket, base64_encode($this->username), 334)) {
                throw new Exception("Username non accettato");
            }
            
            // Password (base64)
            if (!$this->sendCommand($socket, base64_encode($this->password), 235)) {
                throw new Exception("Password non accettata");
            }
            
            // MAIL FROM
            $from = $this->from ?: $this->username;
            if (!$this->sendCommand($socket, "MAIL FROM: <$from>", 250)) {
                throw new Exception("MAIL FROM fallito");
            }
            
            // RCPT TO
            if (!$this->sendCommand($socket, "RCPT TO: <$to>", 250)) {
                throw new Exception("RCPT TO fallito");
            }
            
            // DATA
            if (!$this->sendCommand($socket, "DATA", 354)) {
                throw new Exception("DATA fallito");
            }
            
            // Headers e corpo email
            $headers = $this->buildHeaders($to, $subject, $isHtml);
            $message = $headers . "\r\n\r\n" . $body . "\r\n.";
            
            if (!$this->sendCommand($socket, $message, 250)) {
                throw new Exception("Invio messaggio fallito");
            }
            
            // QUIT
            $this->sendCommand($socket, "QUIT", 221);
            
            fclose($socket);
            
            $this->log("Email inviata con successo!");
            return true;
            
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            $this->log("ERRORE: " . $e->getMessage());
            if ($socket) {
                fclose($socket);
            }
            return false;
        }
    }
    
    /**
     * Connette al server SMTP
     */
    private function connect() {
        $this->log("Connessione a {$this->host}:{$this->port}");
        
        // Per SSL (porta 465)
        if ($this->port == 465) {
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ]);
            
            $socket = @stream_socket_client(
                "ssl://{$this->host}:{$this->port}",
                $errno,
                $errstr,
                $this->timeout,
                STREAM_CLIENT_CONNECT,
                $context
            );
        } else {
            // Per altre porte (25, 587)
            $socket = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);
        }
        
        if (!$socket) {
            $this->lastError = "Connessione fallita: $errstr ($errno)";
            $this->log($this->lastError);
            return false;
        }
        
        // Leggi risposta di benvenuto
        $response = $this->readResponse($socket);
        $this->log("Risposta server: " . trim($response));
        
        if (substr($response, 0, 3) != '220') {
            $this->lastError = "Risposta server inattesa: $response";
            fclose($socket);
            return false;
        }
        
        return $socket;
    }
    
    /**
     * Invia comando e verifica risposta
     */
    private function sendCommand($socket, $command, $expectedCode) {
        // Non loggare password
        $logCommand = (strpos($command, 'AUTH') !== false || strlen($command) > 100) 
            ? substr($command, 0, 30) . '...' 
            : $command;
        
        $this->log(">> $logCommand");
        
        fputs($socket, $command . "\r\n");
        
        $response = $this->readResponse($socket);
        $this->log("<< " . trim($response));
        
        $code = intval(substr($response, 0, 3));
        return $code === $expectedCode;
    }
    
    /**
     * Legge risposta dal server
     */
    private function readResponse($socket) {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') {
                break;
            }
        }
        return $response;
    }
    
    /**
     * Costruisce gli headers email
     */
    private function buildHeaders($to, $subject, $isHtml) {
        $from = $this->from ?: $this->username;
        $fromName = $this->fromName ?: 'Nexio Solution';
        
        $headers = "Date: " . date('r') . "\r\n";
        $headers .= "From: $fromName <$from>\r\n";
        $headers .= "To: $to\r\n";
        $headers .= "Subject: $subject\r\n";
        $headers .= "Message-ID: <" . uniqid() . "@" . $this->host . ">\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        
        if ($isHtml) {
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        }
        
        $headers .= "X-Mailer: Nexio Platform Mailer\r\n";
        
        return $headers;
    }
}
?>