<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/ICSGenerator.php';

class EventInvite {
    private static $instance = null;
    
    public function __construct() {
        // Non è più necessario un oggetto database, usiamo le funzioni globali
    }
    
    /**
     * Ottiene l'istanza singleton di EventInvite
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Genera un file iCal per un evento usando la nuova classe ICSGenerator
     */
    public function generateICalFile($evento, $partecipante = null) {
        try {
            $icsGenerator = new ICSGenerator();
            
            // Carica dati organizer se non già presenti
            $organizer = null;
            if (!empty($evento['creato_da'])) {
                $stmt = db_query("SELECT email, nome, cognome FROM utenti WHERE id = ?", [$evento['creato_da']]);
                $organizer = $stmt->fetch();
            }
            
            // Genera ICS per invito (con metodo REQUEST invece di PUBLISH)
            $ics = $this->generateInviteICS($evento, $organizer, $partecipante);
            
            return $ics;
            
        } catch (Exception $e) {
            error_log('Errore generazione ICS per evento: ' . $e->getMessage());
            // Fallback al metodo vecchio in caso di errore
            return $this->generateLegacyICalFile($evento, $partecipante);
        }
    }
    
    /**
     * Genera ICS specifico per inviti email (con metodo REQUEST)
     */
    private function generateInviteICS($evento, $organizer = null, $partecipante = null) {
        $ics = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//Nexio Solution//Piattaforma Collaborativa//IT\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:REQUEST\r\n"; // Importante per gli inviti
        
        // Timezone
        $ics .= "BEGIN:VTIMEZONE\r\n";
        $ics .= "TZID:Europe/Rome\r\n";
        $ics .= "BEGIN:STANDARD\r\n";
        $ics .= "DTSTART:20071028T030000\r\n";
        $ics .= "RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU\r\n";
        $ics .= "TZNAME:CET\r\n";
        $ics .= "TZOFFSETFROM:+0200\r\n";
        $ics .= "TZOFFSETTO:+0100\r\n";
        $ics .= "END:STANDARD\r\n";
        $ics .= "BEGIN:DAYLIGHT\r\n";
        $ics .= "DTSTART:20070325T020000\r\n";
        $ics .= "RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU\r\n";
        $ics .= "TZNAME:CEST\r\n";
        $ics .= "TZOFFSETFROM:+0100\r\n";
        $ics .= "TZOFFSETTO:+0200\r\n";
        $ics .= "END:DAYLIGHT\r\n";
        $ics .= "END:VTIMEZONE\r\n";
        
        $ics .= "BEGIN:VEVENT\r\n";
        
        // UID univoco
        $uid = "evento-{$evento['id']}-" . date('Ymd-His') . "@nexiosolution.it";
        $ics .= "UID:" . $uid . "\r\n";
        
        // Organizer
        if ($organizer && !empty($organizer['email'])) {
            $organizerName = $organizer['nome'] . ' ' . $organizer['cognome'];
            $ics .= "ORGANIZER;CN=" . $this->escapeText($organizerName) . ":MAILTO:" . $organizer['email'] . "\r\n";
        }
        
        // Partecipante
        if ($partecipante && !empty($partecipante['email'])) {
            $attendeeName = $partecipante['nome'] . ' ' . $partecipante['cognome'];
            $ics .= "ATTENDEE;CUTYPE=INDIVIDUAL;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=" . 
                    $this->escapeText($attendeeName) . ":MAILTO:" . $partecipante['email'] . "\r\n";
        }
        
        // Date con timezone
        $dtstart = $this->formatDateTimeWithTZ($evento['data_inizio']);
        $dtend = $this->formatDateTimeWithTZ($evento['data_fine'] ?? date('Y-m-d H:i:s', strtotime($evento['data_inizio'] . ' +1 hour')));
        $dtstamp = date('Ymd\THis\Z');
        
        $ics .= "DTSTART;TZID=Europe/Rome:" . $dtstart . "\r\n";
        $ics .= "DTEND;TZID=Europe/Rome:" . $dtend . "\r\n";
        $ics .= "DTSTAMP:" . $dtstamp . "\r\n";
        
        // Dati evento
        $ics .= "SUMMARY:" . $this->escapeText($evento['titolo']) . "\r\n";
        
        if (!empty($evento['descrizione'])) {
            $ics .= "DESCRIPTION:" . $this->escapeText($evento['descrizione']) . "\r\n";
        }
        
        if (!empty($evento['luogo'])) {
            $ics .= "LOCATION:" . $this->escapeText($evento['luogo']) . "\r\n";
        }
        
        // Status e sequenza
        $ics .= "STATUS:CONFIRMED\r\n";
        $ics .= "SEQUENCE:0\r\n";
        $ics .= "PRIORITY:5\r\n";
        $ics .= "CLASS:PUBLIC\r\n";
        
        // Creato e modificato
        if (!empty($evento['creato_il'])) {
            $created = date('Ymd\THis\Z', strtotime($evento['creato_il']));
            $ics .= "CREATED:" . $created . "\r\n";
        }
        
        $lastModified = date('Ymd\THis\Z', strtotime($evento['aggiornato_il'] ?? $evento['creato_il'] ?? 'now'));
        $ics .= "LAST-MODIFIED:" . $lastModified . "\r\n";
        
        // Reminder
        $ics .= "BEGIN:VALARM\r\n";
        $ics .= "TRIGGER:-PT15M\r\n";
        $ics .= "ACTION:DISPLAY\r\n";
        $ics .= "DESCRIPTION:Promemoria: " . $this->escapeText($evento['titolo']) . "\r\n";
        $ics .= "END:VALARM\r\n";
        
        $ics .= "END:VEVENT\r\n";
        $ics .= "END:VCALENDAR\r\n";
        
        return $ics;
    }
    
    /**
     * Formatta data/ora con timezone
     */
    private function formatDateTimeWithTZ($datetime) {
        try {
            $dt = new DateTime($datetime, new DateTimeZone('Europe/Rome'));
            return $dt->format('Ymd\THis');
        } catch (Exception $e) {
            return date('Ymd\THis', strtotime($datetime));
        }
    }
    
    /**
     * Escape del testo per formato ICS
     */
    private function escapeText($text) {
        if (empty($text)) {
            return '';
        }
        
        $text = strip_tags($text);
        $text = str_replace(['\\', ',', ';', "\n", "\r"], ['\\\\', '\\,', '\\;', '\\n', ''], $text);
        
        return $text;
    }
    
    /**
     * Metodo legacy di backup per la generazione ICS
     */
    private function generateLegacyICalFile($evento, $partecipante = null) {
        $ical = "BEGIN:VCALENDAR\r\n";
        $ical .= "VERSION:2.0\r\n";
        $ical .= "PRODID:-//Nexio Platform//Event Calendar//IT\r\n";
        $ical .= "CALSCALE:GREGORIAN\r\n";
        $ical .= "METHOD:REQUEST\r\n";
        $ical .= "BEGIN:VEVENT\r\n";
        
        $uid = md5($evento['id'] . '@nexio.platform');
        $ical .= "UID:" . $uid . "\r\n";
        
        $stmt = db_query("SELECT email, nome, cognome FROM utenti WHERE id = ?", [$evento['creato_da']]);
        $organizer = $stmt->fetch();
        if ($organizer) {
            $ical .= "ORGANIZER;CN=" . $organizer['nome'] . " " . $organizer['cognome'] . ":mailto:" . $organizer['email'] . "\r\n";
        }
        
        if ($partecipante) {
            $ical .= "ATTENDEE;CUTYPE=INDIVIDUAL;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=" . 
                     $partecipante['nome'] . " " . $partecipante['cognome'] . ":mailto:" . $partecipante['email'] . "\r\n";
        }
        
        $dtstart = date('Ymd\THis', strtotime($evento['data_inizio']));
        $dtend = date('Ymd\THis', strtotime($evento['data_fine']));
        $dtstamp = date('Ymd\THis');
        
        $ical .= "DTSTART:" . $dtstart . "\r\n";
        $ical .= "DTEND:" . $dtend . "\r\n";
        $ical .= "DTSTAMP:" . $dtstamp . "\r\n";
        $ical .= "SUMMARY:" . $this->escapeString($evento['titolo']) . "\r\n";
        
        if (!empty($evento['descrizione'])) {
            $ical .= "DESCRIPTION:" . $this->escapeString($evento['descrizione']) . "\r\n";
        }
        
        if (!empty($evento['luogo'])) {
            $ical .= "LOCATION:" . $this->escapeString($evento['luogo']) . "\r\n";
        }
        
        $ical .= "STATUS:CONFIRMED\r\n";
        $ical .= "PRIORITY:5\r\n";
        $ical .= "TRANSP:OPAQUE\r\n";
        
        $ical .= "BEGIN:VALARM\r\n";
        $ical .= "TRIGGER:-PT15M\r\n";
        $ical .= "ACTION:DISPLAY\r\n";
        $ical .= "DESCRIPTION:Reminder: " . $this->escapeString($evento['titolo']) . "\r\n";
        $ical .= "END:VALARM\r\n";
        
        $ical .= "END:VEVENT\r\n";
        $ical .= "END:VCALENDAR\r\n";
        
        return $ical;
    }
    
    /**
     * Invia inviti email con allegato iCal
     */
    public function sendInvitations($evento_id, $partecipanti_ids = []) {
        error_log("EventInvite DEBUG: sendInvitations chiamato per evento $evento_id con partecipanti: " . json_encode($partecipanti_ids));
        
        // Carica dettagli evento
        $stmt = db_query("
            SELECT e.*, u.nome as nome_organizzatore, u.cognome as cognome_organizzatore, u.email as email_organizzatore,
                   a.nome as nome_azienda
            FROM eventi e
            JOIN utenti u ON e.creato_da = u.id
            LEFT JOIN aziende a ON e.azienda_id = a.id
            WHERE e.id = ?
        ", [$evento_id]);
        
        $evento = $stmt->fetch();
        if (!$evento) {
            throw new Exception("Evento non trovato");
        }
        
        // Prepara il contenuto email
        $subject = "Invito: " . $evento['titolo'];
        
        // Invia a ciascun partecipante
        foreach ($partecipanti_ids as $partecipante_id) {
            $stmt = db_query("
                SELECT id, email, nome, cognome 
                FROM utenti 
                WHERE id = ? AND attivo = 1
            ", [$partecipante_id]);
            
            $partecipante = $stmt->fetch();
            if (!$partecipante) continue;
            
            // Genera iCal personalizzato per questo partecipante
            $ical_content = $this->generateICalFile($evento, $partecipante);
            
            // Prepara il corpo dell'email
            $body = $this->prepareEmailBody($evento, $partecipante);
            
            // Nome file ICS descrittivo
            $filename = 'evento_' . $evento['id'] . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $evento['titolo']) . '.ics';
            $filename = substr($filename, 0, 100) . '.ics'; // Limita lunghezza
            
            // Invia email con allegato iCal
            try {
                require_once __DIR__ . '/Mailer.php';
                $mailer = Mailer::getInstance();
                
                // Usa il metodo send normale che ha già UniversalMailer come fallback
                if ($mailer->send($partecipante['email'], $subject, $body)) {
                    error_log("EventInvite: ✓ Email inviata con successo a " . $partecipante['email']);
                    
                    // Se vogliamo includere l'ICS nel corpo dell'email (opzionale)
                    $bodyWithICS = $body . '
                    <div style="margin-top: 30px; padding: 20px; background-color: #f8f9fa; border-radius: 5px;">
                        <h4>📅 Aggiungi al calendario</h4>
                        <p>Salva il seguente contenuto in un file con estensione .ics e aprilo con il tuo calendario:</p>
                        <pre style="background: white; padding: 10px; border-radius: 3px; font-size: 12px; overflow-x: auto;">' . htmlspecialchars($ical_content) . '</pre>
                    </div>';
                    
                    // Opzionale: invia una seconda email con l'ICS nel corpo
                    // $mailer->send($partecipante['email'], $subject . ' - File Calendario', $bodyWithICS);
                } else {
                    error_log("EventInvite: Errore invio email a " . $partecipante['email']);
                }
                
            } catch (Exception $e) {
                error_log("EventInvite: Eccezione invio a " . $partecipante['email'] . ": " . $e->getMessage());
            }
        }
    }
    
    /**
     * Prepara il corpo dell'email di invito
     */
    private function prepareEmailBody($evento, $partecipante) {
        require_once __DIR__ . '/EmailTemplate.php';
        return EmailTemplate::eventInvitation($evento, $partecipante);
    }
    
    /**
     * Escape string per formato iCal
     */
    private function escapeString($str) {
        $str = str_replace("\\", "\\\\", $str);
        $str = str_replace(",", "\\,", $str);
        $str = str_replace(";", "\\;", $str);
        $str = str_replace("\n", "\\n", $str);
        $str = str_replace("\r", "", $str);
        return $str;
    }
    
    /**
     * Verifica se un utente può creare eventi per altri utenti
     */
    public function canInviteUser($inviter_id, $invitee_id, $azienda_id = null) {
        // Recupera info sull'invitante
        $stmt = db_query("SELECT ruolo FROM utenti WHERE id = ?", [$inviter_id]);
        $inviter = $stmt->fetch();
        
        // Super admin può invitare chiunque
        if ($inviter && $inviter['ruolo'] === 'super_admin') {
            return true;
        }
        
        // Se c'è un'azienda specificata
        if ($azienda_id) {
            // Verifica che entrambi appartengano alla stessa azienda
            $stmt = db_query("
                SELECT COUNT(*) as count
                FROM utenti_aziende ua1
                JOIN utenti_aziende ua2 ON ua1.azienda_id = ua2.azienda_id
                WHERE ua1.utente_id = ? 
                AND ua2.utente_id = ?
                AND ua1.azienda_id = ?
                AND ua1.attivo = 1
                AND ua2.attivo = 1
            ", [$inviter_id, $invitee_id, $azienda_id]);
            
            $result = $stmt->fetch();
            if ($result && $result['count'] > 0) {
                // Verifica se l'invitante ha i permessi per creare eventi
                $stmt = db_query("
                    SELECT up.puo_creare_eventi
                    FROM utenti_permessi up
                    JOIN utenti_aziende ua ON up.utente_azienda_id = ua.id
                    WHERE ua.utente_id = ? AND ua.azienda_id = ?
                ", [$inviter_id, $azienda_id]);
                
                $permessi = $stmt->fetch();
                return $permessi && $permessi['puo_creare_eventi'];
            }
        }
        
        return false;
    }
    
    /**
     * Invia notifica evento (metodo semplificato per test)
     */
    public function sendEventNotification($evento, $partecipanti) {
        try {
            require_once __DIR__ . '/Mailer.php';
            $mailer = Mailer::getInstance();
            
            $subject = "Invito: " . $evento['titolo'];
            $success = true;
            
            foreach ($partecipanti as $partecipante) {
                if (empty($partecipante['email'])) continue;
                
                // Genera ICS per questo partecipante
                $icsContent = $this->generateICalFile($evento, $partecipante);
                
                // Prepara corpo email
                $body = $this->prepareEmailBody($evento, $partecipante);
                
                // Aggiungi informazioni ICS nel corpo
                $body .= '
                <div style="margin-top: 30px; padding: 20px; background-color: #f0f4f8; border-radius: 8px; border: 1px solid #d1d5db;">
                    <h4 style="color: #374151; margin-top: 0;">📅 Aggiungi al tuo calendario</h4>
                    <p style="color: #6b7280; margin: 10px 0;">Per aggiungere questo evento al tuo calendario:</p>
                    <ol style="color: #6b7280;">
                        <li>Copia il testo qui sotto</li>
                        <li>Salvalo in un file chiamato <strong>evento.ics</strong></li>
                        <li>Apri il file con il tuo calendario preferito</li>
                    </ol>
                    <details style="margin-top: 15px;">
                        <summary style="cursor: pointer; color: #4f46e5; font-weight: 500;">Clicca per vedere il contenuto ICS</summary>
                        <pre style="background: white; padding: 15px; border-radius: 5px; font-size: 11px; overflow-x: auto; margin-top: 10px; border: 1px solid #e5e7eb;">' . htmlspecialchars($icsContent) . '</pre>
                    </details>
                </div>';
                
                if (!$mailer->send($partecipante['email'], $subject, $body)) {
                    error_log("EventInvite: Errore invio a " . $partecipante['email']);
                    $success = false;
                }
            }
            
            return $success;
            
        } catch (Exception $e) {
            error_log("EventInvite: Errore sendEventNotification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Ottieni lista utenti invitabili per un utente
     */
    public function getInvitableUsers($user_id, $azienda_id = null) {
        $stmt = db_query("SELECT ruolo FROM utenti WHERE id = ?", [$user_id]);
        $user = $stmt->fetch();
        
        if ($user && $user['ruolo'] === 'super_admin') {
            // Super admin può invitare tutti
            $stmt = db_query("
                SELECT DISTINCT u.id, u.nome, u.cognome, u.email, u.ruolo,
                       a.nome as azienda_nome
                FROM utenti u
                LEFT JOIN utenti_aziende ua ON u.id = ua.utente_id AND ua.attivo = 1
                LEFT JOIN aziende a ON ua.azienda_id = a.id
                WHERE u.attivo = 1
                ORDER BY u.ruolo DESC, u.nome, u.cognome
            ");
            return $stmt->fetchAll();
        } elseif ($azienda_id) {
            // Utenti azienda possono invitare solo membri della stessa azienda e super admin
            $stmt = db_query("
                SELECT DISTINCT u.id, u.nome, u.cognome, u.email, u.ruolo
                FROM utenti u
                WHERE u.attivo = 1 
                AND (
                    u.ruolo = 'super_admin'
                    OR u.id IN (
                        SELECT utente_id 
                        FROM utenti_aziende 
                        WHERE azienda_id = ? AND attivo = 1
                    )
                )
                ORDER BY u.ruolo DESC, u.nome, u.cognome
            ", [$azienda_id]);
            return $stmt->fetchAll();
        }
        
        return [];
    }
    
    /**
     * Invia notifica di cancellazione evento
     */
    public function sendEventCancellation($evento, $partecipante) {
        require_once __DIR__ . '/Mailer.php';
        $mailer = Mailer::getInstance();
        
        $subject = "Evento Cancellato: " . $evento['titolo'];
        
        $body = EmailTemplate::generate(
            'Evento Cancellato',
            "L'evento '" . htmlspecialchars($evento['titolo']) . "' è stato cancellato.",
            null,
            null,
            [
                'Data originale' => date('d/m/Y', strtotime($evento['data_inizio'])),
                'Ora' => date('H:i', strtotime($evento['data_inizio'])),
                'Luogo' => $evento['luogo'] ?? 'Non specificato',
                'Motivo' => 'L\'evento è stato cancellato dall\'organizzatore'
            ]
        );
        
        return $mailer->send($partecipante['email'], $subject, $body);
    }
    
    /**
     * Invia notifica di aggiornamento evento
     */
    public function sendEventUpdate($evento, $partecipante) {
        require_once __DIR__ . '/Mailer.php';
        $mailer = Mailer::getInstance();
        
        $subject = "Evento Aggiornato: " . $evento['titolo'];
        
        // Genera nuovo ICS per l'evento aggiornato
        $icsContent = $this->generateICalFile($evento, $partecipante);
        
        $body = EmailTemplate::generate(
            'Evento Aggiornato',
            "L'evento '" . htmlspecialchars($evento['titolo']) . "' è stato modificato.",
            'Visualizza Evento',
            APP_URL . '/calendario-eventi.php?date=' . date('Y-m-d', strtotime($evento['data_inizio'])),
            [
                'Nuova data' => date('d/m/Y', strtotime($evento['data_inizio'])),
                'Nuovo orario' => date('H:i', strtotime($evento['data_inizio'])) . ' - ' . date('H:i', strtotime($evento['data_fine'])),
                'Luogo' => $evento['luogo'] ?? 'Non specificato',
                'Descrizione' => $evento['descrizione'] ?? 'Nessuna descrizione'
            ]
        );
        
        // Aggiungi ICS nel corpo
        $body .= '
        <div style="margin-top: 30px; padding: 20px; background-color: #f0f4f8; border-radius: 8px; border: 1px solid #d1d5db;">
            <h4 style="color: #374151; margin-top: 0;">📅 Aggiorna nel tuo calendario</h4>
            <p style="color: #6b7280;">Salva il seguente contenuto in un file .ics per aggiornare l\'evento nel tuo calendario:</p>
            <pre style="background: white; padding: 15px; border-radius: 5px; font-size: 11px; overflow-x: auto; margin-top: 10px; border: 1px solid #e5e7eb;">' . htmlspecialchars($icsContent) . '</pre>
        </div>';
        
        return $mailer->send($partecipante['email'], $subject, $body);
    }
}
