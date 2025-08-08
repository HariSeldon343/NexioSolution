<?php
/**
 * LocalhostMailer - Invia email tramite localhost
 * Funziona configurando PHP per usare un server SMTP locale
 */

class LocalhostMailer {
    private static $instance = null;
    private $lastError = '';
    
    private function __construct() {
        $this->configurePhp();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Configura PHP per usare SMTP locale
     */
    private function configurePhp() {
        // Configura PHP per usare SMTP locale
        ini_set('SMTP', 'localhost');
        ini_set('smtp_port', '25');
        ini_set('sendmail_from', 'info@nexiosolution.it');
        
        // Per Linux/Unix, prova a usare sendmail
        if (PHP_OS_FAMILY !== 'Windows') {
            // Percorsi possibili per sendmail
            $sendmailPaths = [
                '/usr/sbin/sendmail -t -i',
                '/usr/lib/sendmail -t -i',
                '/usr/bin/msmtp -t',
                '/usr/sbin/ssmtp -t',
                '/usr/sbin/exim4 -t -i',
                '/usr/sbin/postfix -t -i'
            ];
            
            foreach ($sendmailPaths as $path) {
                $binary = explode(' ', $path)[0];
                if (file_exists($binary)) {
                    ini_set('sendmail_path', $path);
                    error_log('LocalhostMailer: Using sendmail path: ' . $path);
                    break;
                }
            }
        }
    }
    
    /**
     * Invia email usando la funzione mail() di PHP con configurazione ottimizzata
     */
    public function send($to, $subject, $body, $isHtml = true) {
        try {
            // Headers ottimizzati
            $headers = [
                'MIME-Version: 1.0',
                'Content-Type: ' . ($isHtml ? 'text/html' : 'text/plain') . '; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
                'From: Nexio Solution <info@nexiosolution.it>',
                'Reply-To: info@nexiosolution.it',
                'Return-Path: info@nexiosolution.it',
                'X-Mailer: PHP/' . phpversion(),
                'X-Priority: 3',
                'X-MSMail-Priority: Normal',
                'Date: ' . date('r'),
                'Message-ID: <' . uniqid() . '@' . gethostname() . '>'
            ];
            
            // Aggiungi headers aggiuntivi per evitare spam
            $headers[] = 'List-Unsubscribe: <mailto:unsubscribe@nexiosolution.it>';
            $headers[] = 'Precedence: bulk';
            
            // NON creare multipart per email HTML semplici
            // Lascia che il client email gestisca il rendering HTML
            
            // Parametri aggiuntivi per sendmail
            $additionalParams = '-f info@nexiosolution.it';
            
            // Prova invio con error suppression
            $result = @mail($to, $subject, $body, implode("\r\n", $headers), $additionalParams);
            
            if ($result) {
                error_log('LocalhostMailer: Email sent successfully to ' . $to);
                $this->logEmail($to, $subject, 'success');
                return true;
            } else {
                $error = error_get_last();
                $this->lastError = $error ? $error['message'] : 'Unknown error';
                error_log('LocalhostMailer: Failed to send email - ' . $this->lastError);
            }
            
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            error_log('LocalhostMailer: Exception - ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Configura un relay SMTP locale temporaneo
     */
    public function setupLocalRelay() {
        // Crea script Python per relay SMTP locale
        $pythonScript = '#!/usr/bin/env python3
import smtplib
import sys
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart

def send_email(to, subject, body, is_html=True):
    try:
        # Configurazione
        smtp_host = "mail.nexiosolution.it"
        smtp_port = 587
        smtp_user = "info@nexiosolution.it"
        smtp_pass = "Ricorda1991"
        
        # Crea messaggio
        msg = MIMEMultipart("alternative")
        msg["Subject"] = subject
        msg["From"] = f"Nexio Solution <{smtp_user}>"
        msg["To"] = to
        
        # Aggiungi corpo
        if is_html:
            part = MIMEText(body, "html")
        else:
            part = MIMEText(body, "plain")
        msg.attach(part)
        
        # Invia
        server = smtplib.SMTP(smtp_host, smtp_port)
        server.starttls()
        server.login(smtp_user, smtp_pass)
        server.send_message(msg)
        server.quit()
        
        return True
    except Exception as e:
        print(f"Error: {e}", file=sys.stderr)
        return False

if __name__ == "__main__":
    if len(sys.argv) >= 4:
        to = sys.argv[1]
        subject = sys.argv[2]
        body = sys.argv[3]
        is_html = len(sys.argv) > 4 and sys.argv[4] == "html"
        
        if send_email(to, subject, body, is_html):
            sys.exit(0)
        else:
            sys.exit(1)
    else:
        print("Usage: python send_email.py <to> <subject> <body> [html]", file=sys.stderr)
        sys.exit(1)
';
        
        $scriptPath = sys_get_temp_dir() . '/nexio_mail_relay.py';
        file_put_contents($scriptPath, $pythonScript);
        chmod($scriptPath, 0755);
        
        return $scriptPath;
    }
    
    /**
     * Invia tramite script Python
     */
    public function sendViaPython($to, $subject, $body, $isHtml = true) {
        $scriptPath = $this->setupLocalRelay();
        
        $cmd = sprintf(
            'python3 %s %s %s %s %s 2>&1',
            escapeshellarg($scriptPath),
            escapeshellarg($to),
            escapeshellarg($subject),
            escapeshellarg($body),
            $isHtml ? 'html' : 'plain'
        );
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode === 0) {
            error_log('LocalhostMailer: Email sent via Python relay to ' . $to);
            $this->logEmail($to, $subject, 'success');
            return true;
        } else {
            $this->lastError = implode("\n", $output);
            error_log('LocalhostMailer: Python relay failed - ' . $this->lastError);
        }
        
        return false;
    }
    
    /**
     * Log email
     */
    private function logEmail($to, $subject, $status) {
        try {
            $dettagli = [
                'to' => $to,
                'subject' => $subject,
                'status' => $status,
                'method' => 'LocalhostMailer',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            db_query(
                "INSERT INTO log_attivita (entita_tipo, azione, dettagli, data_azione) VALUES (?, ?, ?, NOW())",
                ['email', 'email_sent', json_encode($dettagli)]
            );
        } catch (Exception $e) {
            error_log('LocalhostMailer: Failed to log - ' . $e->getMessage());
        }
    }
    
    public function getLastError() {
        return $this->lastError;
    }
}
?>