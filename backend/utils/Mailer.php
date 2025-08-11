<?php
/**
 * Classe per la gestione dell'invio email
 * Utilizza le configurazioni SMTP salvate nel database
 */

require_once dirname(__DIR__) . '/config/config.php';

// Database gestito dalle funzioni del progetto

class Mailer {
    private static $instance = null;
    private $config = [];
    private $debug = true; // Abilita debug per troubleshooting
    
    private function __construct() {
        try {
            // Database gestito dalle funzioni del progetto
            $this->loadConfig();
        } catch (Exception $e) {
            error_log('Mailer initialization error: ' . $e->getMessage());
            throw new Exception('Impossibile inizializzare il servizio email: ' . $e->getMessage());
        }
    }
    
    /**
     * Ottiene l'istanza singleton del Mailer
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Carica la configurazione SMTP dal database
     */
    private function loadConfig() {
        try {
            // NON creare tabelle se siamo in una transazione
            // perché causerebbe un commit implicito
            if (!db_connection()->inTransaction()) {
                // Verifica che la tabella configurazioni esista
                if (!$this->tableExists('configurazioni')) {
                    $this->createConfigTable();
                }
            }
            
            $stmt = db_query("
                SELECT chiave, valore 
                FROM configurazioni 
                WHERE chiave LIKE 'smtp_%' OR chiave LIKE 'notify_%' OR chiave LIKE 'smtp_from_%' OR chiave LIKE 'email_%'
            ");
            
            if ($stmt) {
                while ($row = $stmt->fetch()) {
                    $this->config[$row['chiave']] = $row['valore'];
                }
            }
            
            // Configurazioni di default se non presenti
            $defaults = [
                'smtp_enabled' => '0',
                'smtp_host' => '',
                'smtp_port' => '587',
                'smtp_encryption' => 'tls',
                'smtp_from_email' => 'noreply@localhost',
                'smtp_from_name' => 'Piattaforma Collaborativa',
                'email_fallback_enabled' => '1',
                'email_fallback_method' => 'mail',
                'email_queue_enabled' => '1'
            ];
            
            foreach ($defaults as $key => $value) {
                if (!isset($this->config[$key])) {
                    $this->config[$key] = $value;
                }
            }
            
            // Log configurazioni caricate per debug (senza password)
            $configDebug = $this->config;
            if (isset($configDebug['smtp_password'])) {
                $configDebug['smtp_password'] = '***HIDDEN***';
            }
            error_log('Mailer config loaded: ' . json_encode($configDebug));
            
        } catch (Exception $e) {
            error_log('Error loading email config: ' . $e->getMessage());
            // Usa configurazioni di default in caso di errore
            $this->config = [
                'smtp_enabled' => '0',
                'email_fallback_enabled' => '1',
                'email_fallback_method' => 'mail',
                'email_queue_enabled' => '1'
            ];
        }
    }
    
    /**
     * Verifica se una tabella esiste
     */
    private function tableExists($tableName) {
        try {
            $stmt = db_query("SHOW TABLES LIKE '$tableName'");
            return $stmt && $stmt->fetch() !== false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Crea la tabella configurazioni se non esiste
     */
    private function createConfigTable() {
        try {
            db_query("
                CREATE TABLE IF NOT EXISTS configurazioni (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    chiave VARCHAR(255) UNIQUE NOT NULL,
                    valore TEXT,
                    descrizione TEXT,
                    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_chiave (chiave)
                )
            ");
        } catch (Exception $e) {
            error_log('Error creating config table: ' . $e->getMessage());
        }
    }
    
    /**
     * Verifica se il servizio email è configurato
     */
    public function isConfigured() {
        // Se SMTP è abilitato, verifica che sia configurato
        if ($this->config['smtp_enabled'] === '1') {
            return !empty($this->config['smtp_host']) && 
                   !empty($this->config['smtp_port']) &&
                   !empty($this->config['smtp_from_email']);
        }
        
        // Se fallback è abilitato, considera il sistema come configurato
        return $this->config['email_fallback_enabled'] === '1';
    }
    
    /**
     * Ricarica la configurazione (utile dopo un aggiornamento)
     */
    public function reloadConfig() {
        $this->loadConfig();
    }
    
    /**
     * Invia una email
     * 
     * @param string|array $to Destinatario o array di destinatari
     * @param string $subject Oggetto dell'email
     * @param string $body Corpo dell'email (HTML)
     * @param array $options Opzioni aggiuntive (cc, bcc, attachments, disable_tracking, etc.)
     * @return bool True se l'invio è riuscito, false altrimenti
     */
    public function send($to, $subject, $body, $options = []) {
        // Log completo configurazione per debug
        error_log('[MAILER DEBUG] Inizio invio email');
        error_log('[MAILER DEBUG] Destinatario: ' . (is_array($to) ? implode(',', $to) : $to));
        error_log('[MAILER DEBUG] Oggetto: ' . $subject);
        
        // Controlla se dobbiamo disabilitare il tracking (per email di benvenuto, reset password, etc.)
        $disableTracking = isset($options['disable_tracking']) && $options['disable_tracking'] === true;
        
        // Per email critiche come benvenuto e reset password, disabilita sempre il tracking
        $criticalSubjects = [
            'Benvenuto su Nexio',
            'Password Reimpostata',
            'Le tue credenziali di accesso',
            'Reset Password'
        ];
        
        foreach ($criticalSubjects as $criticalSubject) {
            if (stripos($subject, $criticalSubject) !== false) {
                $disableTracking = true;
                error_log('[MAILER DEBUG] Email critica rilevata, disabilito tracking');
                break;
            }
        }
        
        // Se dobbiamo disabilitare il tracking, usa l'API HTTP di Brevo
        if ($disableTracking) {
            try {
                require_once __DIR__ . '/BrevoAPI.php';
                $brevoAPI = BrevoAPI::getInstance();
                
                if ($brevoAPI->send($to, $subject, $body, true)) {
                    error_log('[MAILER DEBUG] ✅ Email inviata con successo tramite BrevoAPI (no tracking)');
                    $this->logEmail($to, $subject, 'success', 'BrevoAPI - No Tracking');
                    return true;
                } else {
                    error_log('[MAILER DEBUG] ❌ BrevoAPI fallito, provo con SMTP');
                }
            } catch (Exception $e) {
                error_log('[MAILER DEBUG] ❌ BrevoAPI eccezione: ' . $e->getMessage());
            }
        }
        
        // Fallback o invio normale con BrevoSMTP
        try {
            require_once __DIR__ . '/BrevoSMTP.php';
            $brevoSMTP = BrevoSMTP::getInstance();
            
            if ($brevoSMTP->send($to, $subject, $body, true)) {
                error_log('[MAILER DEBUG] ✅ Email inviata con successo tramite BrevoSMTP');
                $this->logEmail($to, $subject, 'success', 'BrevoSMTP');
                return true;
            } else {
                error_log('[MAILER DEBUG] ❌ BrevoSMTP fallito senza eccezione');
                $this->logEmail($to, $subject, 'failed', 'BrevoSMTP');
                return false;
            }
        } catch (Exception $e) {
            error_log('[MAILER DEBUG] ❌ BrevoSMTP eccezione: ' . $e->getMessage());
            $this->logEmail($to, $subject, 'failed', 'BrevoSMTP - ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verifica se PHPMailer è disponibile
     */
    private function isPhpMailerAvailable() {
        return class_exists('PHPMailer\\PHPMailer\\PHPMailer');
    }
    
    /**
     * Fallback: invia email usando la funzione mail() nativa di PHP
     */
    private function sendWithNativeMail($to, $subject, $body) {
        // Su Windows, verifica se sendmail è configurato
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $sendmailPath = ini_get('sendmail_path');
            if (empty($sendmailPath)) {
                error_log('Mailer: sendmail_path non configurato su Windows. Configura sendmail in php.ini o installa PHPMailer.');
                return false;
            }
        }
        
        $fromEmail = $this->config['smtp_from_email'] ?? 'noreply@example.com';
        $fromName = $this->config['smtp_from_name'] ?? 'Piattaforma Collaborativa';
        
        // Crea un boundary per il messaggio multipart
        $boundary = md5(time());
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'From: ' . $fromName . ' <' . $fromEmail . '>',
            'Reply-To: ' . $fromEmail,
            'X-Mailer: PHP/' . phpversion()
        ];
        
        // Estrai il testo dal contenuto HTML
        $textBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $body));
        $textBody = html_entity_decode($textBody, ENT_QUOTES, 'UTF-8');
        
        // Costruisci il messaggio multipart
        $message = '';
        $message .= '--' . $boundary . "\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $textBody . "\r\n";
        
        $message .= '--' . $boundary . "\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $body . "\r\n";
        
        $message .= '--' . $boundary . '--';
        
        $to = is_array($to) ? implode(', ', array_values($to)) : $to;
        
        // Tentativo di invio
        error_log('Mailer: Tentativo invio con mail() nativa a ' . $to);
        $result = @mail($to, $subject, $message, implode("\r\n", $headers));
        
        if (!$result) {
            $lastError = error_get_last();
            error_log('Mailer: Errore mail() - ' . ($lastError['message'] ?? 'Unknown error'));
        }
        
        $this->logEmail($to, $subject, $result ? 'success' : 'failed', 'Native mail function');
        
        return $result;
    }
    
    /**
     * Log dell'invio email
     */
    private function logEmail($to, $subject, $status, $error = null) {
        try {
            $dettagli = [
                'to' => is_array($to) ? implode(', ', array_values($to)) : $to,
                'subject' => $subject,
                'status' => $status,
                'error' => $error
            ];
            
            db_query("
                INSERT INTO log_attivita (entita_tipo, azione, dettagli, data_azione)
                VALUES (?, ?, ?, NOW())
            ", ['email', 'email_sent', json_encode($dettagli)]);
        } catch (Exception $e) {
            error_log('Failed to log email: ' . $e->getMessage());
        }
    }
    
    /**
     * Invia email di test
     */
    public function sendTestEmail($to) {
        // Verifica configurazione prima di tentare l'invio
        if (empty($this->config['smtp_host'])) {
            error_log('Mailer: Impossibile inviare email test - Server SMTP non configurato');
            return false;
        }
        
        if (empty($this->config['smtp_username']) || empty($this->config['smtp_password'])) {
            error_log('Mailer: Impossibile inviare email test - Credenziali SMTP mancanti');
            return false;
        }
        
        if (empty($this->config['smtp_from_email'])) {
            error_log('Mailer: Impossibile inviare email test - Email mittente non configurata');
            return false;
        }
        
        $subject = 'Test Email - Piattaforma Collaborativa';
        $body = '
            <html>
            <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                <h2>Test Email</h2>
                <p>Questa è una email di test dalla Piattaforma Collaborativa.</p>
                <p>Se ricevi questa email, la configurazione SMTP è corretta!</p>
                <hr>
                <p style="font-size: 12px; color: #666;">
                    Configurazione utilizzata:<br>
                    Server: ' . htmlspecialchars($this->config['smtp_host'] ?? 'N/A') . '<br>
                    Porta: ' . htmlspecialchars($this->config['smtp_port'] ?? 'N/A') . '<br>
                    Crittografia: ' . htmlspecialchars($this->config['smtp_encryption'] ?? 'N/A') . '
                </p>
            </body>
            </html>
        ';
        
        return $this->send($to, $subject, $body);
    }
    
    /**
     * Verifica se le notifiche sono abilitate per un determinato tipo
     */
    public function isNotificationEnabled($type) {
        $key = 'notify_' . $type;
        return isset($this->config[$key]) && $this->config[$key] === '1';
    }
    
    /**
     * Invia notifica per evento creato
     */
    public function sendEventCreatedNotification($evento, $partecipanti) {
        if (!$this->isNotificationEnabled('event_created')) {
            return;
        }
        
        $subject = 'Nuovo Evento: ' . $evento['titolo'];
        $body = $this->getEventEmailTemplate($evento, 'created');
        
        foreach ($partecipanti as $partecipante) {
            if (!empty($partecipante['email'])) {
                $this->send($partecipante['email'], $subject, $body);
            }
        }
    }
    
    /**
     * Invia notifica per ticket creato
     */
    public function sendTicketCreatedNotification($ticket, $assegnato_a) {
        if (!$this->isNotificationEnabled('ticket_created')) {
            return;
        }
        
        $subject = 'Nuovo Ticket #' . $ticket['id'] . ': ' . $ticket['oggetto'];
        $body = $this->getTicketEmailTemplate($ticket, 'created');
        
        if (!empty($assegnato_a['email'])) {
            $this->send($assegnato_a['email'], $subject, $body);
        }
    }
    
    /**
     * Invia notifica per cambio stato ticket
     */
    public function sendTicketStatusChangedNotification($ticket, $old_status, $new_status, $interessati) {
        if (!$this->isNotificationEnabled('ticket_status_changed')) {
            return;
        }
        
        $subject = 'Ticket #' . $ticket['id'] . ' - Cambio stato: ' . $new_status;
        $body = $this->getTicketEmailTemplate($ticket, 'status_changed', [
            'old_status' => $old_status,
            'new_status' => $new_status
        ]);
        
        foreach ($interessati as $utente) {
            if (!empty($utente['email'])) {
                $this->send($utente['email'], $subject, $body);
            }
        }
    }
    
    /**
     * Template email per eventi
     */
    private function getEventEmailTemplate($evento, $action, $extra = []) {
        $baseUrl = defined('APP_URL') ? APP_URL : 'http://localhost';
        
        $html = '
        <html>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto;">
            <div style="background-color: #f8f9fa; padding: 20px; border-radius: 5px;">
                <h2 style="color: #2d3748; margin-bottom: 20px;">' . htmlspecialchars($evento['titolo']) . '</h2>
                
                <div style="background-color: white; padding: 20px; border-radius: 5px; margin-bottom: 20px;">';
        
        if ($action === 'modified') {
            $html .= '<div style="background-color: #fef3c7; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                        <p style="margin: 0; color: #92400e;">
                            <strong>⚠️ Attenzione:</strong> Questo evento è stato modificato.
                        </p>
                      </div>';
        }
        
        $html .= '
                    <p><strong>Data inizio:</strong> ' . date('d/m/Y H:i', strtotime($evento['data_inizio'])) . '</p>
                    <p><strong>Data fine:</strong> ' . date('d/m/Y H:i', strtotime($evento['data_fine'])) . '</p>
                    <p><strong>Luogo:</strong> ' . htmlspecialchars($evento['luogo'] ?? 'Da definire') . '</p>
                    <p><strong>Tipo:</strong> ' . ucfirst($evento['tipo'] ?? 'evento') . '</p>';
        
        if (!empty($evento['descrizione'])) {
            $html .= '<p><strong>Descrizione:</strong><br>' . nl2br(htmlspecialchars($evento['descrizione'])) . '</p>';
        }
        
        $html .= '
                </div>
                
                <a href="' . $baseUrl . '/eventi.php?action=view&id=' . $evento['id'] . '" 
                   style="display: inline-block; padding: 10px 20px; background-color: #6366f1; color: white; text-decoration: none; border-radius: 5px;">
                    Visualizza Dettagli Evento
                </a>
            </div>
        </body>
        </html>
        ';
        
        return $html;
    }
    
    /**
     * Template email per ticket
     */
    private function getTicketEmailTemplate($ticket, $action, $extra = []) {
        $baseUrl = defined('APP_URL') ? APP_URL : 'http://localhost';
        
        $html = '
        <html>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto;">
            <div style="background-color: #f8f9fa; padding: 20px; border-radius: 5px;">
                <h2 style="color: #2d3748; margin-bottom: 20px;">Ticket #' . $ticket['id'] . '</h2>
                
                <div style="background-color: white; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
                    <p><strong>Oggetto:</strong> ' . htmlspecialchars($ticket['oggetto']) . '</p>
                    <p><strong>Priorità:</strong> ' . htmlspecialchars($ticket['priorita']) . '</p>
                    <p><strong>Stato:</strong> ' . htmlspecialchars($ticket['stato']) . '</p>';
        
        if ($action === 'status_changed' && !empty($extra['old_status'])) {
            $html .= '<p><strong>Cambio stato:</strong> ' . 
                     htmlspecialchars($extra['old_status']) . ' → ' . 
                     htmlspecialchars($extra['new_status']) . '</p>';
        }
        
        $html .= '
                    <p><strong>Descrizione:</strong><br>' . nl2br(htmlspecialchars($ticket['descrizione'] ?? '')) . '</p>
                </div>
                
                <a href="' . $baseUrl . '/tickets.php?id=' . $ticket['id'] . '" 
                   style="display: inline-block; padding: 10px 20px; background-color: #6366f1; color: white; text-decoration: none; border-radius: 5px;">
                    Visualizza Ticket
                </a>
            </div>
        </body>
        </html>
        ';
        
        return $html;
    }
    
    /**
     * Invia notifica per evento modificato
     */
    public function sendEventModifiedNotification($evento, $partecipanti, $modifiche = '') {
        if (!$this->isNotificationEnabled('event_modified')) {
            return;
        }
        
        $subject = 'Evento Modificato: ' . $evento['titolo'];
        $body = $this->getEventEmailTemplate($evento, 'modified', ['modifiche' => $modifiche]);
        
        foreach ($partecipanti as $partecipante) {
            if (!empty($partecipante['email'])) {
                $this->send($partecipante['email'], $subject, $body);
            }
        }
    }
    
    /**
     * Invia notifica per documento creato
     */
    public function sendDocumentCreatedNotification($documento, $destinatari) {
        if (!$this->isNotificationEnabled('document_created')) {
            return;
        }
        
        $subject = 'Nuovo Documento: ' . $documento['titolo'];
        $body = $this->getDocumentEmailTemplate($documento, 'created');
        
        foreach ($destinatari as $destinatario) {
            if (!empty($destinatario['email'])) {
                $this->send($destinatario['email'], $subject, $body);
            }
        }
    }
    
    /**
     * Invia notifica per documento modificato
     */
    public function sendDocumentModifiedNotification($documento, $destinatari) {
        if (!$this->isNotificationEnabled('document_modified')) {
            return;
        }
        
        $subject = 'Documento Modificato: ' . $documento['titolo'];
        $body = $this->getDocumentEmailTemplate($documento, 'modified');
        
        foreach ($destinatari as $destinatario) {
            if (!empty($destinatario['email'])) {
                $this->send($destinatario['email'], $subject, $body);
            }
        }
    }
    
    /**
     * Invia notifica per documento condiviso
     */
    public function sendDocumentSharedNotification($documento, $destinatario, $condiviso_da) {
        if (!$this->isNotificationEnabled('document_shared')) {
            return;
        }
        
        $subject = 'Documento Condiviso: ' . $documento['titolo'];
        $body = $this->getDocumentEmailTemplate($documento, 'shared', ['condiviso_da' => $condiviso_da]);
        
        if (!empty($destinatario['email'])) {
            $this->send($destinatario['email'], $subject, $body);
        }
    }
    
    /**
     * Invia email di benvenuto per nuovo utente
     */
    public function sendUserCreatedNotification($utente, $password_temporanea = null) {
        if (!$this->isNotificationEnabled('user_created')) {
            return;
        }
        
        $subject = 'Benvenuto su ' . (defined('APP_NAME') ? APP_NAME : 'Piattaforma Collaborativa');
        $body = $this->getUserEmailTemplate($utente, 'created', ['password' => $password_temporanea]);
        
        if (!empty($utente['email'])) {
            $this->send($utente['email'], $subject, $body);
        }
    }
    
    /**
     * Invia notifica di reset password
     */
    public function sendPasswordResetNotification($utente, $nuova_password) {
        if (!$this->isNotificationEnabled('password_reset')) {
            return;
        }
        
        $subject = 'Password Reimpostata - ' . (defined('APP_NAME') ? APP_NAME : 'Piattaforma Collaborativa');
        $body = $this->getUserEmailTemplate($utente, 'password_reset', ['password' => $nuova_password]);
        
        if (!empty($utente['email'])) {
            $this->send($utente['email'], $subject, $body);
        }
    }
    
    /**
     * Template email per documenti
     */
    private function getDocumentEmailTemplate($documento, $action, $extra = []) {
        $baseUrl = defined('APP_URL') ? APP_URL : 'http://localhost';
        
        $html = '
        <html>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto;">
            <div style="background-color: #f8f9fa; padding: 20px; border-radius: 5px;">
                <h2 style="color: #2d3748; margin-bottom: 20px;">' . htmlspecialchars($documento['titolo']) . '</h2>
                
                <div style="background-color: white; padding: 20px; border-radius: 5px; margin-bottom: 20px;">';
        
        if ($action === 'created') {
            $html .= '<p>È stato creato un nuovo documento.</p>';
        } elseif ($action === 'modified') {
            $html .= '<p>Il documento è stato modificato.</p>';
        } elseif ($action === 'shared' && !empty($extra['condiviso_da'])) {
            $html .= '<p><strong>' . htmlspecialchars($extra['condiviso_da']['nome'] . ' ' . $extra['condiviso_da']['cognome']) . 
                     '</strong> ha condiviso questo documento con te.</p>';
        }
        
        $html .= '
                    <p><strong>Codice:</strong> ' . htmlspecialchars($documento['codice'] ?? '') . '</p>
                    <p><strong>Categoria:</strong> ' . htmlspecialchars($documento['categoria'] ?? 'Non specificata') . '</p>';
        
        if (!empty($documento['descrizione'])) {
            $html .= '<p><strong>Descrizione:</strong><br>' . nl2br(htmlspecialchars($documento['descrizione'])) . '</p>';
        }
        
        $html .= '
                </div>
                
                <a href="' . $baseUrl . '/documenti.php?id=' . $documento['id'] . '" 
                   style="display: inline-block; padding: 10px 20px; background-color: #6366f1; color: white; text-decoration: none; border-radius: 5px;">
                    Visualizza Documento
                </a>
            </div>
        </body>
        </html>
        ';
        
        return $html;
    }
    
    /**
     * Template email per utenti
     */
    private function getUserEmailTemplate($utente, $action, $extra = []) {
        $baseUrl = defined('APP_URL') ? APP_URL : 'http://localhost';
        $appName = defined('APP_NAME') ? APP_NAME : 'Piattaforma Collaborativa';
        
        if ($action === 'password_reset') {
            $html = '
            <html>
            <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto;">
                <div style="background-color: #f8f9fa; padding: 20px; border-radius: 5px;">
                    <h2 style="color: #2d3748; margin-bottom: 20px;">Password Reimpostata</h2>
                    
                    <div style="background-color: white; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
                        <p>Ciao <strong>' . htmlspecialchars($utente['nome']) . '</strong>,</p>
                        <p>La tua password è stata reimpostata da un amministratore. Di seguito trovi i nuovi dettagli per accedere:</p>
                        
                        <div style="background-color: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #ffc107;">
                            <p><strong>Username:</strong> ' . htmlspecialchars($utente['username']) . '</p>
                            <p><strong>Email:</strong> ' . htmlspecialchars($utente['email']) . '</p>';
            
            if (!empty($extra['password'])) {
                $html .= '<p><strong>Nuova password:</strong> ' . htmlspecialchars($extra['password']) . '</p>';
            }
            
            $html .= '
                            <p style="color: #dc3545; font-size: 14px; font-weight: bold;">🔐 IMPORTANTE: Dovrai cambiare questa password al primo accesso per motivi di sicurezza</p>
                        </div>
                        
                        <p>Per accedere alla piattaforma, clicca sul pulsante qui sotto:</p>
                    </div>
                    
                    <a href="' . $baseUrl . '/login.php" 
                       style="display: inline-block; padding: 10px 20px; background-color: #dc3545; color: white; text-decoration: none; border-radius: 5px;">
                        Accedi alla Piattaforma
                    </a>
                    
                    <p style="margin-top: 20px; font-size: 14px; color: #718096;">
                        Se non hai richiesto questo reset, contatta immediatamente l\'amministratore del sistema.
                    </p>
                </div>
            </body>
            </html>
            ';
        } else {
            // Template per creazione utente (esistente)
            $html = '
            <html>
            <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto;">
                <div style="background-color: #f8f9fa; padding: 20px; border-radius: 5px;">
                    <h2 style="color: #2d3748; margin-bottom: 20px;">Benvenuto su ' . htmlspecialchars($appName) . '!</h2>
                    
                    <div style="background-color: white; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
                        <p>Ciao <strong>' . htmlspecialchars($utente['nome']) . '</strong>,</p>
                        <p>Il tuo account è stato creato con successo. Di seguito trovi i dettagli per accedere:</p>
                        
                        <div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
                            <p><strong>Username:</strong> ' . htmlspecialchars($utente['username']) . '</p>
                            <p><strong>Email:</strong> ' . htmlspecialchars($utente['email']) . '</p>';
            
            if (!empty($extra['password'])) {
                $html .= '<p><strong>Password temporanea:</strong> ' . htmlspecialchars($extra['password']) . '</p>
                          <p style="color: #ef4444; font-size: 14px;">⚠️ Ti consigliamo di cambiare la password al primo accesso</p>';
            }
            
            $html .= '
                        </div>
                        
                        <p>Per accedere alla piattaforma, clicca sul pulsante qui sotto:</p>
                    </div>
                    
                    <a href="' . $baseUrl . '/login.php" 
                       style="display: inline-block; padding: 10px 20px; background-color: #6366f1; color: white; text-decoration: none; border-radius: 5px;">
                        Accedi alla Piattaforma
                    </a>
                    
                    <p style="margin-top: 20px; font-size: 14px; color: #718096;">
                        Se hai bisogno di assistenza, contatta l\'amministratore del sistema.
                    </p>
                </div>
            </body>
            </html>
            ';
        }
        
        return $html;
    }
    
    /**
     * Invia una email con allegato
     * 
     * @param string|array $to Destinatario o array di destinatari
     * @param string $subject Oggetto dell'email
     * @param string $body Corpo dell'email (HTML)
     * @param array $attachment Array con 'filename', 'content' e 'type'
     * @param array $options Opzioni aggiuntive
     * @return bool True se l'invio è riuscito, false altrimenti
     */
    public function sendWithAttachment($to, $subject, $body, $attachment, $options = []) {
        // Verifica che le configurazioni SMTP siano complete
        if (empty($this->config['smtp_host']) || empty($this->config['smtp_port'])) {
            error_log('Mailer: Configurazione SMTP incompleta per invio con allegato');
            return false;
        }
        
        try {
            // Prepara i dati dell'email
            $fromEmail = $this->config['smtp_from_email'] ?? 'noreply@example.com';
            $fromName = $this->config['smtp_from_name'] ?? 'Piattaforma Collaborativa';
            $to_string = is_array($to) ? implode(', ', array_values($to)) : $to;
            
            // Genera boundary per multipart
            $boundary = md5(time());
            
            // Headers
            $headers = [
                'From: ' . $fromName . ' <' . $fromEmail . '>',
                'Reply-To: ' . $fromEmail,
                'MIME-Version: 1.0',
                'Content-Type: multipart/mixed; boundary="' . $boundary . '"'
            ];
            
            // Corpo del messaggio multipart
            $message = '';
            
            // Parte HTML
            $message .= '--' . $boundary . "\r\n";
            $message .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
            $message .= 'Content-Transfer-Encoding: 7bit' . "\r\n\r\n";
            $message .= $body . "\r\n\r\n";
            
            // Allegato
            if (!empty($attachment['content']) && !empty($attachment['filename'])) {
                $message .= '--' . $boundary . "\r\n";
                
                // Tipo di contenuto dell'allegato
                $contentType = $attachment['type'] ?? 'application/octet-stream';
                $message .= 'Content-Type: ' . $contentType . '; name="' . $attachment['filename'] . '"' . "\r\n";
                $message .= 'Content-Transfer-Encoding: base64' . "\r\n";
                $message .= 'Content-Disposition: attachment; filename="' . $attachment['filename'] . '"' . "\r\n\r\n";
                
                // Contenuto dell'allegato (base64)
                $message .= chunk_split(base64_encode($attachment['content'])) . "\r\n";
            }
            
            // Chiusura multipart
            $message .= '--' . $boundary . '--';
            
            // Invia email
            $result = @mail($to_string, $subject, $message, implode("\r\n", $headers));
            
            if ($result) {
                $this->logEmail($to, $subject, 'success', 'Email con allegato inviata');
                return true;
            } else {
                throw new Exception('Invio email con allegato fallito');
            }
            
        } catch (Exception $e) {
            error_log('Mailer sendWithAttachment error: ' . $e->getMessage());
            $this->logEmail($to, $subject, 'failed', $e->getMessage());
            return false;
        }
    }
}
?> 