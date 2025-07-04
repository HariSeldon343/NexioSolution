<?php
/**
 * SimpleSMTP - Classe semplificata per l'invio email via SMTP
 * Specifica per Windows/XAMPP con mail.nexiosolution.it
 */

class SimpleSMTP {
    private $host;
    private $port;
    private $username;
    private $password;
    private $socket;
    private $debug = false;
    private $timeout = 10;
    
    public function __construct($host, $port, $username, $password) {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
    }
    
    public function enableDebug($debug = true) {
        $this->debug = $debug;
    }
    
    private function log($message) {
        if ($this->debug) {
            error_log("SimpleSMTP: " . $message);
        }
    }
    
    private function connect() {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'ciphers' => 'HIGH:!SSLv2:!SSLv3',
                'crypto_method' => STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
            ]
        ]);
        
        $this->log("Tentativo connessione a {$this->host}:{$this->port}");
        
        // Per porta 465 usa SSL diretto
        if ($this->port == 465) {
            $this->log("Usando SSL diretto per porta 465");
            $this->socket = @stream_socket_client(
                "ssl://{$this->host}:{$this->port}",
                $errno,
                $errstr,
                $this->timeout,
                STREAM_CLIENT_CONNECT,
                $context
            );
        } else {
            // Per altre porte usa connessione normale e poi STARTTLS
            $this->log("Usando connessione TCP normale per porta {$this->port}");
            $this->socket = @stream_socket_client(
                "tcp://{$this->host}:{$this->port}",
                $errno,
                $errstr,
                $this->timeout,
                STREAM_CLIENT_CONNECT,
                $context
            );
        }
        
        if (!$this->socket) {
            $this->log("Errore connessione: $errstr ($errno)");
            throw new Exception("Impossibile connettersi a {$this->host}:{$this->port} - $errstr ($errno)");
        }
        
        $this->log("Connessione stabilita con successo");
        stream_set_timeout($this->socket, $this->timeout);
        
        // Leggi il messaggio di benvenuto
        $response = $this->read();
        $this->log("Messaggio di benvenuto server: " . trim($response));
        
        if (substr($response, 0, 3) != '220') {
            throw new Exception("Risposta inattesa dal server: $response");
        }
        
        return true;
    }
    
    private function read() {
        $data = '';
        while ($line = fgets($this->socket, 515)) {
            $data .= $line;
            if (substr($line, 3, 1) == ' ') {
                break;
            }
        }
        return $data;
    }
    
    private function write($command) {
        $this->log("Client: " . $command);
        fputs($this->socket, $command . "\r\n");
        
        $response = $this->read();
        $this->log("Server: " . $response);
        
        return $response;
    }
    
    private function authenticate() {
        // Invia EHLO
        $hostname = gethostname();
        if (empty($hostname) || $hostname === false) {
            $hostname = 'localhost';
        }
        
        $this->log("Inviando EHLO con hostname: $hostname");
        $response = $this->write("EHLO $hostname");
        if (substr($response, 0, 3) != '250') {
            throw new Exception("EHLO fallito: $response");
        }
        
        // Se non siamo già su SSL (porta 465) e il server supporta STARTTLS
        if ($this->port != 465 && strpos($response, 'STARTTLS') !== false) {
            $this->log("Server supporta STARTTLS, iniziando upgrade...");
            $response = $this->write("STARTTLS");
            if (substr($response, 0, 3) != '220') {
                throw new Exception("STARTTLS fallito: $response");
            }
            
            // Abilita crittografia con metodi multipli per compatibilità
            $crypto_methods = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $crypto_methods |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                $crypto_methods |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }
            
            if (!stream_socket_enable_crypto($this->socket, true, $crypto_methods)) {
                throw new Exception("Impossibile abilitare crittografia TLS");
            }
            $this->log("Crittografia TLS abilitata con successo");
            
            // Reinvia EHLO dopo TLS
            $response = $this->write("EHLO $hostname");
            if (substr($response, 0, 3) != '250') {
                throw new Exception("EHLO dopo TLS fallito: $response");
            }
        } else {
            $this->log("Porta 465 - SSL già attivo, saltando STARTTLS");
        }
        
        // Autenticazione
        $this->log("Iniziando autenticazione AUTH LOGIN");
        $response = $this->write("AUTH LOGIN");
        if (substr($response, 0, 3) != '334') {
            throw new Exception("AUTH LOGIN fallito: $response");
        }
        
        // Invia username in base64
        $this->log("Inviando username...");
        $response = $this->write(base64_encode($this->username));
        if (substr($response, 0, 3) != '334') {
            throw new Exception("Username non accettato: $response");
        }
        
        // Invia password in base64
        $this->log("Inviando password...");
        $response = $this->write(base64_encode($this->password));
        if (substr($response, 0, 3) != '235') {
            throw new Exception("Autenticazione fallita: $response");
        }
        
        $this->log("Autenticazione completata con successo");
        return true;
    }
    
    public function send($to, $subject, $body, $from = null, $fromName = null) {
        try {
            // Usa le credenziali come mittente se non specificato
            if (!$from) {
                $from = $this->username;
            }
            
            $this->log("Tentativo connessione a {$this->host}:{$this->port}");
            
            // Connetti e autentica
            $this->connect();
            $this->log("Connessione stabilita, tentativo autenticazione...");
            $this->authenticate();
            $this->log("Autenticazione completata con successo");
            
            // MAIL FROM
            $response = $this->write("MAIL FROM: <$from>");
            if (substr($response, 0, 3) != '250') {
                throw new Exception("MAIL FROM fallito: $response");
            }
            
            // RCPT TO
            $recipients = is_array($to) ? $to : [$to];
            foreach ($recipients as $recipient) {
                $response = $this->write("RCPT TO: <$recipient>");
                if (substr($response, 0, 3) != '250') {
                    throw new Exception("RCPT TO fallito per $recipient: $response");
                }
            }
            
            // DATA
            $response = $this->write("DATA");
            if (substr($response, 0, 3) != '354') {
                throw new Exception("DATA fallito: $response");
            }
            
            // Costruisci headers con encoding corretto per caratteri speciali
            $headers = [];
            
            // Pulisci e codifica il nome del mittente per evitare problemi di sintassi
            if ($fromName) {
                // Rimuovi caratteri problematici e usa encoding MIME se necessario
                $cleanFromName = str_replace(['"', "'", ",", ";", "\n", "\r"], "", $fromName);
                // Se contiene caratteri non ASCII, usa encoding MIME
                if (preg_match('/[^\x20-\x7E]/', $cleanFromName)) {
                    $encodedFromName = '=?UTF-8?B?' . base64_encode($cleanFromName) . '?=';
                    $headers[] = "From: $encodedFromName <$from>";
                } else {
                    // Metti il nome tra virgolette se contiene spazi o caratteri speciali
                    if (preg_match('/[\s\-\(\)]/', $cleanFromName)) {
                        $headers[] = "From: \"$cleanFromName\" <$from>";
                    } else {
                        $headers[] = "From: $cleanFromName <$from>";
                    }
                }
            } else {
                $headers[] = "From: $from";
            }
            
            $headers[] = "To: " . (is_array($to) ? implode(', ', $to) : $to);
            
            // Codifica subject se contiene caratteri non ASCII
            if (preg_match('/[^\x20-\x7E]/', $subject)) {
                $headers[] = "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=";
            } else {
                $headers[] = "Subject: $subject";
            }
            
            $headers[] = "Date: " . date('r');
            // Genera un Message-ID valido
            $hostname = gethostname();
            if (empty($hostname) || $hostname === false) {
                $hostname = 'localhost';
            }
            $hostname = preg_replace('/[^a-zA-Z0-9.-]/', '', $hostname);
            if (empty($hostname)) {
                $hostname = 'server';
            }
            $headers[] = "Message-ID: <" . uniqid() . "." . time() . "@" . $hostname . ">";
            $headers[] = "MIME-Version: 1.0";
            $headers[] = "Content-Type: text/html; charset=UTF-8";
            $headers[] = "Content-Transfer-Encoding: 8bit";
            $headers[] = "X-Priority: 3";
            $headers[] = "X-Mailer: PHP/" . phpversion();
            $headers[] = "Reply-To: $from";
            $headers[] = "Return-Path: $from";
            
            // Invia headers e body
            $message = implode("\r\n", $headers) . "\r\n\r\n" . $body;
            
            // Normalizza le terminazioni di riga per evitare problemi
            $message = str_replace(["\r\n", "\r", "\n"], "\n", $message);
            
            // Invia il messaggio riga per riga per evitare problemi di buffer
            $lines = explode("\n", $message);
            foreach ($lines as $line) {
                // Limita la lunghezza delle righe a 998 caratteri (RFC 5322)
                if (strlen($line) > 998) {
                    $line = substr($line, 0, 998);
                }
                
                // Se la riga inizia con un punto, aggiungi un altro punto (dot-stuffing)
                if (strlen($line) > 0 && $line[0] === '.') {
                    $line = '.' . $line;
                }
                
                // Invia la riga con terminazione CRLF corretta
                fputs($this->socket, $line . "\r\n");
            }
            
            // Termina il messaggio con un punto su una riga vuota
            fputs($this->socket, ".\r\n");
            
            // IMPORTANTE: Leggi la risposta dopo l'invio del messaggio
            $response = $this->read();
            $this->log("Server dopo invio messaggio: " . $response);
            
            if (substr($response, 0, 3) != '250') {
                throw new Exception("Invio messaggio fallito: $response");
            }
            
            // QUIT
            $this->write("QUIT");
            
            // Chiudi socket
            fclose($this->socket);
            
            return true;
            
        } catch (Exception $e) {
            if ($this->socket) {
                fclose($this->socket);
            }
            throw $e;
        }
    }
}
?> 