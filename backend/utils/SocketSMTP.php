<?php
/**
 * SocketSMTP - Implementazione SMTP robusta con socket diretti
 * Ottimizzata per mail.nexiosolution.it porta 465
 */

class SocketSMTP {
    private $host;
    private $port;
    private $username;
    private $password;
    private $from;
    private $fromName;
    private $timeout = 10; // Timeout ridotto per evitare hanging
    private $readTimeout = 5; // Timeout per singole letture
    private $debug = false;
    private $lastError = '';
    private $socket = null;
    
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
    
    public function getLastError() {
        return $this->lastError;
    }
    
    private function log($message) {
        if ($this->debug) {
            $timestamp = date('Y-m-d H:i:s');
            error_log("[SocketSMTP $timestamp] $message");
            if (php_sapi_name() === 'cli' || isset($_GET['debug'])) {
                echo "[SocketSMTP] $message\n";
            }
        }
    }
    
    /**
     * Test rapido della connessione SMTP
     */
    public function testConnection() {
        $this->log("=== TEST CONNESSIONE SMTP ===");
        $this->log("Host: {$this->host}:{$this->port}");
        $this->log("Username: {$this->username}");
        
        try {
            // Test 1: Risoluzione DNS
            $this->log("\n1. Test risoluzione DNS...");
            $ip = gethostbyname($this->host);
            if ($ip === $this->host) {
                throw new Exception("Impossibile risolvere l'host {$this->host}");
            }
            $this->log("   ✓ Host risolto: $ip");
            
            // Test 2: Connessione socket
            $this->log("\n2. Test connessione socket...");
            if (!$this->connect()) {
                throw new Exception("Connessione fallita: " . $this->lastError);
            }
            $this->log("   ✓ Connesso con successo");
            
            // Test 3: EHLO
            $this->log("\n3. Test comando EHLO...");
            $hostname = $this->getHostname();
            $response = $this->command("EHLO $hostname", 250);
            if (!$response) {
                throw new Exception("EHLO fallito");
            }
            $this->log("   ✓ EHLO accettato");
            
            // Test 4: AUTH
            $this->log("\n4. Test autenticazione...");
            if (!$this->authenticate()) {
                throw new Exception("Autenticazione fallita: " . $this->lastError);
            }
            $this->log("   ✓ Autenticazione riuscita");
            
            // Chiudi connessione
            $this->quit();
            
            $this->log("\n=== TEST COMPLETATO CON SUCCESSO ===");
            return true;
            
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            $this->log("\n✗ ERRORE: " . $e->getMessage());
            $this->disconnect();
            return false;
        }
    }
    
    /**
     * Invia email
     */
    public function send($to, $subject, $body, $isHtml = true) {
        $this->log("=== INVIO EMAIL ===");
        $this->log("Destinatario: $to");
        $this->log("Oggetto: $subject");
        
        try {
            // Connetti
            if (!$this->connect()) {
                throw new Exception("Connessione fallita: " . $this->lastError);
            }
            
            // EHLO
            $hostname = $this->getHostname();
            if (!$this->command("EHLO $hostname", 250)) {
                throw new Exception("EHLO fallito");
            }
            
            // Autentica
            if (!$this->authenticate()) {
                throw new Exception("Autenticazione fallita: " . $this->lastError);
            }
            
            // MAIL FROM
            $from = $this->from ?: $this->username;
            if (!$this->command("MAIL FROM: <$from>", 250)) {
                throw new Exception("MAIL FROM fallito");
            }
            
            // RCPT TO
            if (!$this->command("RCPT TO: <$to>", 250)) {
                throw new Exception("RCPT TO fallito");
            }
            
            // DATA
            if (!$this->command("DATA", 354)) {
                throw new Exception("DATA fallito");
            }
            
            // Invia messaggio
            $message = $this->buildMessage($to, $subject, $body, $isHtml);
            if (!$this->sendData($message)) {
                throw new Exception("Invio messaggio fallito");
            }
            
            // QUIT
            $this->quit();
            
            $this->log("=== EMAIL INVIATA CON SUCCESSO ===");
            return true;
            
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            $this->log("ERRORE: " . $e->getMessage());
            $this->disconnect();
            return false;
        }
    }
    
    /**
     * Connette al server SMTP
     */
    private function connect() {
        $this->log("Connessione a {$this->host}:{$this->port}...");
        
        // Contesto SSL per porta 465
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'crypto_method' => STREAM_CRYPTO_METHOD_TLS_CLIENT
            ]
        ]);
        
        // Connetti con timeout
        $address = ($this->port == 465) ? "ssl://{$this->host}:{$this->port}" : "tcp://{$this->host}:{$this->port}";
        
        $this->socket = @stream_socket_client(
            $address,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );
        
        if (!$this->socket) {
            $this->lastError = "Connessione fallita: $errstr (Error $errno)";
            $this->log($this->lastError);
            return false;
        }
        
        // Imposta timeout per letture/scritture
        stream_set_timeout($this->socket, $this->readTimeout);
        
        // Leggi banner di benvenuto
        $response = $this->readResponse();
        if (substr($response, 0, 3) != '220') {
            $this->lastError = "Banner inaspettato: $response";
            $this->disconnect();
            return false;
        }
        
        $this->log("Connesso: $response");
        return true;
    }
    
    /**
     * Autentica con il server
     */
    private function authenticate() {
        $this->log("Inizio autenticazione...");
        
        // AUTH LOGIN
        if (!$this->command("AUTH LOGIN", 334)) {
            return false;
        }
        
        // Username
        if (!$this->command(base64_encode($this->username), 334)) {
            $this->lastError = "Username rifiutato";
            return false;
        }
        
        // Password
        if (!$this->command(base64_encode($this->password), 235)) {
            $this->lastError = "Password rifiutata";
            return false;
        }
        
        $this->log("Autenticazione completata");
        return true;
    }
    
    /**
     * Invia comando e verifica risposta
     */
    private function command($cmd, $expectedCode = null) {
        // Non loggare password
        $logCmd = (strpos($cmd, 'AUTH') !== false || strlen($cmd) > 50) 
            ? substr($cmd, 0, 20) . '...' 
            : $cmd;
        
        $this->log(">> $logCmd");
        
        if (!$this->socket) {
            $this->lastError = "Socket non connesso";
            return false;
        }
        
        // Invia comando
        if (@fwrite($this->socket, $cmd . "\r\n") === false) {
            $this->lastError = "Errore scrittura socket";
            return false;
        }
        
        // Leggi risposta
        $response = $this->readResponse();
        if (!$response) {
            return false;
        }
        
        $this->log("<< " . trim($response));
        
        // Verifica codice risposta se specificato
        if ($expectedCode !== null) {
            $code = intval(substr($response, 0, 3));
            if ($code !== $expectedCode) {
                $this->lastError = "Codice inaspettato: $code (atteso: $expectedCode)";
                return false;
            }
        }
        
        return $response;
    }
    
    /**
     * Legge risposta dal server
     */
    private function readResponse() {
        $data = '';
        $endTime = time() + $this->readTimeout;
        
        while (time() < $endTime) {
            $line = @fgets($this->socket, 515);
            if ($line === false) {
                $info = stream_get_meta_data($this->socket);
                if ($info['timed_out']) {
                    $this->lastError = "Timeout lettura";
                } else {
                    $this->lastError = "Errore lettura socket";
                }
                return false;
            }
            
            $data .= $line;
            
            // Controlla se è l'ultima riga della risposta
            if (substr($line, 3, 1) == ' ') {
                break;
            }
        }
        
        return $data;
    }
    
    /**
     * Invia dati del messaggio
     */
    private function sendData($data) {
        $lines = explode("\n", $data);
        
        foreach ($lines as $line) {
            // Dot-stuffing
            if (!empty($line) && $line[0] == '.') {
                $line = '.' . $line;
            }
            
            if (@fwrite($this->socket, $line . "\r\n") === false) {
                $this->lastError = "Errore invio dati";
                return false;
            }
        }
        
        // Termina con .
        return $this->command(".", 250);
    }
    
    /**
     * Costruisce il messaggio email
     */
    private function buildMessage($to, $subject, $body, $isHtml) {
        $from = $this->from ?: $this->username;
        $fromName = $this->fromName ?: 'Nexio Solution';
        
        $headers = [];
        $headers[] = "Date: " . date('r');
        $headers[] = "From: $fromName <$from>";
        $headers[] = "To: $to";
        $headers[] = "Subject: $subject";
        $headers[] = "Message-ID: <" . uniqid() . "@" . $this->host . ">";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: " . ($isHtml ? "text/html" : "text/plain") . "; charset=UTF-8";
        $headers[] = "Content-Transfer-Encoding: 8bit";
        $headers[] = "X-Mailer: Nexio Platform";
        
        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }
    
    /**
     * Ottiene hostname sicuro
     */
    private function getHostname() {
        $hostname = gethostname();
        if (!$hostname || $hostname === false) {
            $hostname = 'localhost';
        }
        return preg_replace('/[^a-zA-Z0-9.-]/', '', $hostname);
    }
    
    /**
     * Invia QUIT e chiude connessione
     */
    private function quit() {
        if ($this->socket) {
            @$this->command("QUIT", 221);
            $this->disconnect();
        }
    }
    
    /**
     * Chiude socket
     */
    private function disconnect() {
        if ($this->socket) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }
}
?>