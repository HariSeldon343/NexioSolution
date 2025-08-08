<?php
/**
 * BrevoMailer - Gestione invio email tramite API Brevo (ex SendinBlue)
 * 
 * Questa classe fornisce un'interfaccia semplice per inviare email
 * utilizzando l'API di Brevo con cURL nativo di PHP.
 * 
 * @author Nexio Platform
 * @version 1.0
 */

class BrevoMailer {
    /**
     * API Key di Brevo
     * @var string
     */
    private $apiKey = 'xsmtpsib-63dbb8e04720fb90ecfa0008096ad8a29b88c40207ea340c8b82c5d97c8d2d70-HXbs1KMQcALY59N8';
    
    /**
     * URL endpoint API di Brevo
     * @var string
     */
    private $apiEndpoint = 'https://api.brevo.com/v3/smtp/email';
    
    /**
     * Email del mittente predefinito
     * @var string
     */
    private $defaultFromEmail = 'info@nexiosolution.it';
    
    /**
     * Nome del mittente predefinito
     * @var string
     */
    private $defaultFromName = 'Nexio Platform';
    
    /**
     * Singleton instance
     * @var BrevoMailer|null
     */
    private static $instance = null;
    
    /**
     * Private constructor for singleton pattern
     */
    private function __construct() {
        // Verifica che cURL sia disponibile
        if (!function_exists('curl_init')) {
            throw new Exception('cURL non è installato o abilitato');
        }
    }
    
    /**
     * Ottiene l'istanza singleton
     * @return BrevoMailer
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Invia un'email tramite l'API di Brevo
     * 
     * @param string $to Email del destinatario
     * @param string $subject Oggetto dell'email
     * @param string $htmlContent Contenuto HTML dell'email
     * @param string|null $toName Nome del destinatario (opzionale)
     * @param array|null $options Opzioni aggiuntive (cc, bcc, attachments, etc.)
     * @return array Array con 'success' (bool) e 'messageId' o 'error'
     */
    public function sendEmail($to, $subject, $htmlContent, $toName = null, $options = []) {
        try {
            // Validazione parametri
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Email destinatario non valida: ' . $to);
            }
            
            if (empty($subject)) {
                throw new Exception('Oggetto email non può essere vuoto');
            }
            
            if (empty($htmlContent)) {
                throw new Exception('Contenuto email non può essere vuoto');
            }
            
            // Costruzione del payload
            $payload = [
                'sender' => [
                    'name' => $options['fromName'] ?? $this->defaultFromName,
                    'email' => $options['fromEmail'] ?? $this->defaultFromEmail
                ],
                'to' => [
                    [
                        'email' => $to,
                        'name' => $toName ?? $to
                    ]
                ],
                'subject' => $subject,
                'htmlContent' => $htmlContent
            ];
            
            // Aggiungi CC se presente
            if (!empty($options['cc'])) {
                $payload['cc'] = $this->formatRecipients($options['cc']);
            }
            
            // Aggiungi BCC se presente
            if (!empty($options['bcc'])) {
                $payload['bcc'] = $this->formatRecipients($options['bcc']);
            }
            
            // Aggiungi reply-to se presente
            if (!empty($options['replyTo'])) {
                $payload['replyTo'] = [
                    'email' => $options['replyTo']['email'] ?? $options['replyTo'],
                    'name' => $options['replyTo']['name'] ?? null
                ];
            }
            
            // Aggiungi testo plain se presente
            if (!empty($options['textContent'])) {
                $payload['textContent'] = $options['textContent'];
            } else {
                // Genera automaticamente versione text dal contenuto HTML
                $payload['textContent'] = strip_tags($htmlContent);
            }
            
            // Headers personalizzati
            if (!empty($options['headers'])) {
                $payload['headers'] = $options['headers'];
            }
            
            // Parametri custom
            if (!empty($options['params'])) {
                $payload['params'] = $options['params'];
            }
            
            // Converti payload in JSON
            $jsonPayload = json_encode($payload);
            
            // Log della richiesta (senza API key)
            $this->log('info', 'Invio email a: ' . $to . ', Oggetto: ' . $subject);
            
            // Inizializza cURL
            $ch = curl_init();
            
            // Configura cURL
            curl_setopt($ch, CURLOPT_URL, $this->apiEndpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disabilita verifica SSL per localhost
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Disabilita verifica SSL per localhost
            
            // Headers
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'api-key: ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            
            // Esegui la richiesta
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            curl_close($ch);
            
            // Gestione errori cURL
            if ($error) {
                throw new Exception('Errore cURL: ' . $error);
            }
            
            // Decodifica risposta
            $responseData = json_decode($response, true);
            
            // Verifica codice HTTP
            if ($httpCode === 201) {
                // Successo
                $this->log('success', 'Email inviata con successo. MessageId: ' . ($responseData['messageId'] ?? 'N/A'));
                
                // Salva nel database locale
                $this->saveToDatabase($to, $subject, $htmlContent, 'sent', $responseData['messageId'] ?? null);
                
                return [
                    'success' => true,
                    'messageId' => $responseData['messageId'] ?? null,
                    'response' => $responseData
                ];
            } else {
                // Errore
                $errorMessage = $this->parseErrorResponse($responseData, $httpCode);
                
                // Log dettagliato per errore 401
                if ($httpCode === 401) {
                    $this->log('error', 'API Key non valida o non autorizzata. Verificare su Brevo: 1) API key attiva, 2) Sender email verificato');
                }
                
                throw new Exception($errorMessage);
            }
            
        } catch (Exception $e) {
            $this->log('error', 'Errore invio email: ' . $e->getMessage());
            
            // Salva nel database locale come fallito
            $this->saveToDatabase($to, $subject, $htmlContent, 'failed', null, $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Wrapper per sostituire la funzione mail() di PHP
     * 
     * @param string $to Destinatario
     * @param string $subject Oggetto
     * @param string $message Messaggio (HTML o testo)
     * @param string $headers Headers aggiuntivi (opzionale)
     * @param string $parameters Parametri aggiuntivi (ignorati)
     * @return bool True se inviato con successo, false altrimenti
     */
    public function mail($to, $subject, $message, $headers = '', $parameters = '') {
        // Parsing headers per estrarre informazioni utili
        $parsedHeaders = $this->parseMailHeaders($headers);
        
        // Determina se il contenuto è HTML
        $isHtml = (stripos($headers, 'content-type: text/html') !== false);
        
        // Prepara opzioni
        $options = [];
        if (!empty($parsedHeaders['From'])) {
            // Estrai email e nome dal formato "Nome <email>"
            if (preg_match('/^(.+?)\s*<(.+?)>$/', $parsedHeaders['From'], $matches)) {
                $options['fromName'] = trim($matches[1]);
                $options['fromEmail'] = trim($matches[2]);
            } else {
                $options['fromEmail'] = trim($parsedHeaders['From']);
            }
        }
        
        if (!empty($parsedHeaders['Reply-To'])) {
            $options['replyTo'] = $parsedHeaders['Reply-To'];
        }
        
        if (!empty($parsedHeaders['CC'])) {
            $options['cc'] = $parsedHeaders['CC'];
        }
        
        if (!empty($parsedHeaders['BCC'])) {
            $options['bcc'] = $parsedHeaders['BCC'];
        }
        
        // Se non è HTML, usa il messaggio come testo plain
        if (!$isHtml) {
            $options['textContent'] = $message;
            $message = nl2br(htmlspecialchars($message)); // Converti in HTML semplice
        }
        
        // Invia email
        $result = $this->sendEmail($to, $subject, $message, null, $options);
        
        return $result['success'];
    }
    
    /**
     * Invia email di benvenuto
     * 
     * @param string $email Email del nuovo utente
     * @param string $nome Nome dell'utente
     * @param string $password Password temporanea
     * @return array Risultato dell'invio
     */
    public function sendWelcomeEmail($email, $nome, $password) {
        $subject = "Benvenuto su Nexio Platform!";
        
        $htmlContent = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #2c3e50; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f4f4f4; }
                .button { display: inline-block; padding: 10px 20px; background-color: #3498db; color: white; text-decoration: none; border-radius: 5px; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Benvenuto su Nexio Platform!</h1>
                </div>
                <div class='content'>
                    <h2>Ciao {$nome},</h2>
                    <p>Il tuo account è stato creato con successo. Ecco i tuoi dati di accesso:</p>
                    <p><strong>Email:</strong> {$email}<br>
                    <strong>Password temporanea:</strong> {$password}</p>
                    <p>Per motivi di sicurezza, ti chiederemo di cambiare la password al primo accesso.</p>
                    <p style='text-align: center; margin: 30px 0;'>
                        <a href='http://localhost/piattaforma-collaborativa/login.php' class='button'>Accedi alla Piattaforma</a>
                    </p>
                    <p>Se hai domande o necessiti di assistenza, non esitare a contattarci.</p>
                    <p>Cordiali saluti,<br>Il Team di Nexio</p>
                </div>
                <div class='footer'>
                    <p>&copy; 2025 Nexio Platform. Tutti i diritti riservati.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->sendEmail($email, $subject, $htmlContent, $nome);
    }
    
    /**
     * Invia notifica evento
     * 
     * @param string $email Email del partecipante
     * @param string $nome Nome del partecipante
     * @param array $evento Dettagli dell'evento
     * @return array Risultato dell'invio
     */
    public function sendEventNotification($email, $nome, $evento) {
        $subject = "Invito all'evento: " . $evento['titolo'];
        
        $dataEvento = date('d/m/Y', strtotime($evento['data_inizio']));
        $oraEvento = date('H:i', strtotime($evento['data_inizio']));
        
        $htmlContent = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #27ae60; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f4f4f4; }
                .event-details { background-color: white; padding: 15px; border-radius: 5px; margin: 20px 0; }
                .button { display: inline-block; padding: 10px 20px; background-color: #27ae60; color: white; text-decoration: none; border-radius: 5px; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Invito all'Evento</h1>
                </div>
                <div class='content'>
                    <h2>Ciao {$nome},</h2>
                    <p>Sei stato invitato a partecipare al seguente evento:</p>
                    <div class='event-details'>
                        <h3>{$evento['titolo']}</h3>
                        <p><strong>Data:</strong> {$dataEvento}<br>
                        <strong>Ora:</strong> {$oraEvento}<br>
                        <strong>Luogo:</strong> {$evento['luogo']}<br>
                        <strong>Descrizione:</strong> {$evento['descrizione']}</p>
                    </div>
                    <p style='text-align: center;'>
                        <a href='http://localhost/piattaforma-collaborativa/calendario-eventi.php' class='button'>Visualizza nel Calendario</a>
                    </p>
                    <p>Ti aspettiamo!</p>
                    <p>Cordiali saluti,<br>Il Team di Nexio</p>
                </div>
                <div class='footer'>
                    <p>&copy; 2025 Nexio Platform. Tutti i diritti riservati.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->sendEmail($email, $subject, $htmlContent, $nome);
    }
    
    /**
     * Invia email di reset password
     * 
     * @param string $email Email dell'utente
     * @param string $nome Nome dell'utente
     * @param string $resetToken Token per il reset
     * @return array Risultato dell'invio
     */
    public function sendPasswordResetEmail($email, $nome, $resetToken) {
        $subject = "Reset Password - Nexio Platform";
        
        $resetLink = "http://localhost/piattaforma-collaborativa/reset-password.php?token=" . $resetToken;
        
        $htmlContent = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #e74c3c; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f4f4f4; }
                .button { display: inline-block; padding: 10px 20px; background-color: #e74c3c; color: white; text-decoration: none; border-radius: 5px; }
                .warning { background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Reset Password</h1>
                </div>
                <div class='content'>
                    <h2>Ciao {$nome},</h2>
                    <p>Abbiamo ricevuto una richiesta di reset password per il tuo account.</p>
                    <p>Se hai effettuato tu questa richiesta, clicca sul pulsante sottostante per reimpostare la tua password:</p>
                    <p style='text-align: center; margin: 30px 0;'>
                        <a href='{$resetLink}' class='button'>Reset Password</a>
                    </p>
                    <div class='warning'>
                        <p><strong>Attenzione:</strong> Questo link è valido per 1 ora. Se non hai richiesto il reset della password, ignora questa email.</p>
                    </div>
                    <p>Per motivi di sicurezza, se non riesci a cliccare il pulsante, copia e incolla questo link nel tuo browser:</p>
                    <p style='word-break: break-all;'>{$resetLink}</p>
                    <p>Cordiali saluti,<br>Il Team di Nexio</p>
                </div>
                <div class='footer'>
                    <p>&copy; 2025 Nexio Platform. Tutti i diritti riservati.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->sendEmail($email, $subject, $htmlContent, $nome);
    }
    
    /**
     * Formatta i destinatari per CC/BCC
     * 
     * @param mixed $recipients String o array di destinatari
     * @return array Array formattato per Brevo
     */
    private function formatRecipients($recipients) {
        if (is_string($recipients)) {
            $recipients = [$recipients];
        }
        
        $formatted = [];
        foreach ($recipients as $recipient) {
            if (is_array($recipient)) {
                $formatted[] = [
                    'email' => $recipient['email'],
                    'name' => $recipient['name'] ?? null
                ];
            } else {
                $formatted[] = [
                    'email' => $recipient,
                    'name' => null
                ];
            }
        }
        
        return $formatted;
    }
    
    /**
     * Parsea gli headers del formato mail() di PHP
     * 
     * @param string $headers Headers in formato stringa
     * @return array Array associativo degli headers
     */
    private function parseMailHeaders($headers) {
        $parsed = [];
        if (empty($headers)) {
            return $parsed;
        }
        
        $lines = explode("\n", $headers);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $parsed[trim($key)] = trim($value);
            }
        }
        
        return $parsed;
    }
    
    /**
     * Parsea la risposta di errore da Brevo
     * 
     * @param array|null $responseData Dati della risposta
     * @param int $httpCode Codice HTTP
     * @return string Messaggio di errore formattato
     */
    private function parseErrorResponse($responseData, $httpCode) {
        if (is_array($responseData)) {
            if (isset($responseData['message'])) {
                return "Errore Brevo ({$httpCode}): " . $responseData['message'];
            }
            if (isset($responseData['code'])) {
                return "Errore Brevo ({$httpCode}): Codice " . $responseData['code'];
            }
        }
        
        // Messaggi di errore standard basati sul codice HTTP
        $httpErrors = [
            400 => 'Richiesta non valida',
            401 => 'Autenticazione fallita - verifica API key',
            403 => 'Accesso negato',
            404 => 'Endpoint non trovato',
            405 => 'Metodo non permesso',
            406 => 'Formato non accettabile',
            429 => 'Troppe richieste - limite rate superato',
            500 => 'Errore interno del server Brevo',
            503 => 'Servizio temporaneamente non disponibile'
        ];
        
        return "Errore Brevo ({$httpCode}): " . ($httpErrors[$httpCode] ?? 'Errore sconosciuto');
    }
    
    /**
     * Log delle operazioni
     * 
     * @param string $level Livello di log (info, success, error)
     * @param string $message Messaggio da loggare
     */
    private function log($level, $message) {
        $logMessage = "[" . date('Y-m-d H:i:s') . "] [BREVO] [{$level}] {$message}\n";
        error_log($logMessage, 3, dirname(__FILE__) . '/../../logs/brevo.log');
        
        // Log anche nel log di sistema
        error_log("BrevoMailer [{$level}]: {$message}");
    }
    
    /**
     * Salva l'email nel database locale
     * 
     * @param string $to Destinatario
     * @param string $subject Oggetto
     * @param string $body Corpo dell'email
     * @param string $status Stato (sent, failed)
     * @param string|null $messageId ID messaggio di Brevo
     * @param string|null $error Messaggio di errore se fallito
     */
    private function saveToDatabase($to, $subject, $body, $status, $messageId = null, $error = null) {
        try {
            // Verifica se la tabella esiste
            db_query("
                CREATE TABLE IF NOT EXISTS email_notifications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    to_email VARCHAR(255) NOT NULL,
                    subject VARCHAR(255) NOT NULL,
                    body TEXT NOT NULL,
                    is_html TINYINT(1) DEFAULT 1,
                    status ENUM('pending', 'viewed', 'sent', 'failed') DEFAULT 'pending',
                    message_id VARCHAR(255),
                    error_message TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    viewed_at TIMESTAMP NULL,
                    sent_at TIMESTAMP NULL,
                    INDEX idx_status (status),
                    INDEX idx_to (to_email),
                    INDEX idx_created (created_at)
                )
            ");
            
            // Inserisci record
            db_query("
                INSERT INTO email_notifications 
                (to_email, subject, body, is_html, status, message_id, error_message, sent_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $to, 
                $subject, 
                $body, 
                1, 
                $status, 
                $messageId, 
                $error,
                $status === 'sent' ? date('Y-m-d H:i:s') : null
            ]);
            
        } catch (Exception $e) {
            $this->log('error', 'Errore salvataggio database: ' . $e->getMessage());
        }
    }
}

// Funzione globale wrapper per compatibilità con mail()
if (!function_exists('brevo_mail')) {
    /**
     * Funzione wrapper globale per sostituire mail()
     * 
     * @param string $to Destinatario
     * @param string $subject Oggetto
     * @param string $message Messaggio
     * @param string $headers Headers aggiuntivi
     * @param string $parameters Parametri (ignorati)
     * @return bool
     */
    function brevo_mail($to, $subject, $message, $headers = '', $parameters = '') {
        try {
            $mailer = BrevoMailer::getInstance();
            return $mailer->mail($to, $subject, $message, $headers, $parameters);
        } catch (Exception $e) {
            error_log('brevo_mail error: ' . $e->getMessage());
            return false;
        }
    }
}

// Esempio di test e utilizzo
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    // Esegui solo se il file viene chiamato direttamente
    
    echo "<h1>Test BrevoMailer</h1>\n";
    
    try {
        $mailer = BrevoMailer::getInstance();
        
        // Test 1: Email semplice
        echo "<h2>Test 1: Email Semplice</h2>\n";
        $result = $mailer->sendEmail(
            'test@example.com',
            'Test Email da Brevo',
            '<h1>Test Email</h1><p>Questa è una email di test inviata tramite Brevo API.</p>',
            'Test User'
        );
        echo "<pre>";
        print_r($result);
        echo "</pre>";
        
        // Test 2: Email di benvenuto
        echo "<h2>Test 2: Email di Benvenuto</h2>\n";
        $result = $mailer->sendWelcomeEmail(
            'nuovo.utente@example.com',
            'Mario Rossi',
            'TempPass123!'
        );
        echo "<pre>";
        print_r($result);
        echo "</pre>";
        
        // Test 3: Notifica evento
        echo "<h2>Test 3: Notifica Evento</h2>\n";
        $evento = [
            'titolo' => 'Riunione Team Development',
            'data_inizio' => '2025-01-25 15:00:00',
            'luogo' => 'Sala Conferenze A',
            'descrizione' => 'Discussione roadmap Q1 2025'
        ];
        $result = $mailer->sendEventNotification(
            'partecipante@example.com',
            'Luigi Bianchi',
            $evento
        );
        echo "<pre>";
        print_r($result);
        echo "</pre>";
        
        // Test 4: Reset password
        echo "<h2>Test 4: Reset Password</h2>\n";
        $result = $mailer->sendPasswordResetEmail(
            'user@example.com',
            'Giulia Verdi',
            'reset_token_' . uniqid()
        );
        echo "<pre>";
        print_r($result);
        echo "</pre>";
        
        // Test 5: Usando wrapper mail()
        echo "<h2>Test 5: Wrapper mail()</h2>\n";
        $headers = "From: Test Sender <test@example.com>\r\n";
        $headers .= "Reply-To: reply@example.com\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        $result = brevo_mail(
            'recipient@example.com',
            'Test tramite wrapper mail()',
            '<p>Questo test usa la funzione wrapper che sostituisce mail()</p>',
            $headers
        );
        echo "Risultato: " . ($result ? 'Successo' : 'Fallito') . "\n";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>Errore: " . $e->getMessage() . "</p>\n";
    }
}
?>