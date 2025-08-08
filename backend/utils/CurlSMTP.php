<?php
/**
 * CurlSMTP - Invio email usando cURL e servizi SMTP esterni
 * Alternativa per quando le connessioni socket dirette non funzionano
 */

class CurlSMTP {
    private $config;
    private $lastError = '';
    private $debug = false;
    
    public function __construct($config) {
        $this->config = $config;
    }
    
    public function enableDebug($debug = true) {
        $this->debug = $debug;
    }
    
    private function log($message) {
        if ($this->debug) {
            error_log("[CurlSMTP] " . $message);
        }
    }
    
    public function getLastError() {
        return $this->lastError;
    }
    
    /**
     * Invia email usando il metodo mail() di PHP con headers corretti
     * Questo metodo spesso funziona quando SMTP diretto fallisce
     */
    public function sendViaMailFunction($to, $subject, $body, $from, $fromName) {
        $this->log("Invio via mail() function");
        
        // Headers completi per massima compatibilità
        $headers = array();
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-type: text/html; charset=UTF-8";
        $headers[] = "From: $fromName <$from>";
        $headers[] = "Reply-To: $from";
        $headers[] = "X-Mailer: Nexio Platform";
        $headers[] = "X-Priority: 3";
        $headers[] = "Return-Path: $from";
        
        // Parametri aggiuntivi per alcuni server
        $additionalParams = "-f$from";
        
        // Codifica il subject per caratteri speciali
        $subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        
        $this->log("Invio email a: $to");
        $this->log("Da: $fromName <$from>");
        
        // Tentativo di invio
        $result = @mail($to, $subject, $body, implode("\r\n", $headers), $additionalParams);
        
        if ($result) {
            $this->log("Email inviata con successo via mail()");
            return true;
        } else {
            $this->lastError = "mail() function failed";
            $this->log("Errore invio via mail()");
            return false;
        }
    }
    
    /**
     * Invia email tramite un servizio SMTP2GO o simile usando cURL
     * Utile quando le connessioni dirette SMTP sono bloccate
     */
    public function sendViaAPI($to, $subject, $body, $from, $fromName) {
        $this->log("Invio via API non implementato - usa mail() come fallback");
        return $this->sendViaMailFunction($to, $subject, $body, $from, $fromName);
    }
    
    /**
     * Metodo principale che prova diversi approcci
     */
    public function send($to, $subject, $body, $from, $fromName) {
        // Prima prova con mail() che spesso funziona su hosting condivisi
        if ($this->sendViaMailFunction($to, $subject, $body, $from, $fromName)) {
            return true;
        }
        
        // Se fallisce, prova con API (se implementata)
        return $this->sendViaAPI($to, $subject, $body, $from, $fromName);
    }
}

/**
 * Funzione helper per test rapido
 */
function testEmailSystem($to = 'test@example.com') {
    echo "<pre>";
    echo "Test Sistema Email\n";
    echo "==================\n\n";
    
    // Test 1: Verifica configurazione PHP
    echo "1. Configurazione PHP:\n";
    echo "   sendmail_path: " . ini_get('sendmail_path') . "\n";
    echo "   SMTP: " . ini_get('SMTP') . "\n";
    echo "   smtp_port: " . ini_get('smtp_port') . "\n";
    echo "   mail.add_x_header: " . ini_get('mail.add_x_header') . "\n\n";
    
    // Test 2: Funzione mail() disponibile
    echo "2. Funzione mail():\n";
    if (function_exists('mail')) {
        echo "   ✓ Disponibile\n";
        
        // Test invio
        if (isset($_GET['test_mail'])) {
            $subject = 'Test Email - ' . date('Y-m-d H:i:s');
            $message = '<html><body><h3>Test Email</h3><p>Se ricevi questa email, il sistema funziona!</p></body></html>';
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $headers .= "From: Nexio Test <info@nexiosolution.it>\r\n";
            
            if (mail($to, $subject, $message, $headers)) {
                echo "   ✓ Email inviata a: $to\n";
            } else {
                echo "   ✗ Errore invio\n";
            }
        } else {
            echo "   <a href='?test_mail=1&to=$to'>Clicca per testare</a>\n";
        }
    } else {
        echo "   ✗ Non disponibile\n";
    }
    
    echo "</pre>";
}

// Se eseguito direttamente, mostra test
if (basename($_SERVER['PHP_SELF']) == 'CurlSMTP.php') {
    $testEmail = $_GET['to'] ?? 'test@example.com';
    testEmailSystem($testEmail);
    ?>
    <hr>
    <form method="get">
        <label>Test email a: <input type="email" name="to" value="<?php echo htmlspecialchars($testEmail); ?>"></label>
        <button type="submit" name="test_mail" value="1">Invia Test</button>
    </form>
    <?php
}
?>