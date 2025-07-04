<?php
/**
 * Centro notifiche unificato per gestire tutte le email di sistema
 * Implementa le regole sui permessi per determinare chi riceve le email
 */

require_once __DIR__ . '/EmailTemplate.php';

class NotificationCenter {
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Invia notifica per nuovo ticket
     */
    public function notifyNewTicket($ticket, $creator) {
        $recipients = $this->getTicketRecipients($ticket['azienda_id'], $creator['id']);
        $emailContent = EmailTemplate::newTicket($ticket, $creator);
        
        foreach ($recipients as $recipient) {
            $this->sendEmail(
                $recipient['email'],
                "Nuovo Ticket #{$ticket['codice']}",
                $emailContent,
                'ticket_new'
            );
        }
    }
    
    /**
     * Invia notifica per cambio stato ticket
     */
    public function notifyTicketStatusChange($ticket, $oldStatus, $newStatus, $updatedBy) {
        $recipients = $this->getTicketRecipients($ticket['azienda_id'], $updatedBy['id']);
        $emailContent = EmailTemplate::ticketStatusChanged($ticket, $oldStatus, $newStatus, $updatedBy);
        
        foreach ($recipients as $recipient) {
            $this->sendEmail(
                $recipient['email'],
                "Aggiornamento Ticket #{$ticket['codice']}",
                $emailContent,
                'ticket_update'
            );
        }
    }
    
    /**
     * Invia notifica per nuovo commento ticket
     */
    public function notifyTicketComment($ticket, $comment, $author) {
        $recipients = $this->getTicketRecipients($ticket['azienda_id'], $author['id']);
        $emailContent = EmailTemplate::ticketCommentAdded($ticket, $comment, $author);
        
        foreach ($recipients as $recipient) {
            $this->sendEmail(
                $recipient['email'],
                "Commento su Ticket #{$ticket['codice']}",
                $emailContent,
                'ticket_comment'
            );
        }
    }
    
    /**
     * Invia notifica per nuovo evento
     */
    public function notifyEventInvitation($evento, $partecipanti) {
        foreach ($partecipanti as $partecipante) {
            if ($this->canReceiveEventNotifications($partecipante['id'], $evento['azienda_id'])) {
                $emailContent = EmailTemplate::eventInvitation($evento, $partecipante);
                
                $this->sendEmail(
                    $partecipante['email'],
                    "Invito: {$evento['titolo']}",
                    $emailContent,
                    'event_invitation'
                );
            }
        }
    }
    
    /**
     * Invia notifica per evento modificato
     */
    public function notifyEventModified($evento, $partecipanti, $modifiche) {
        foreach ($partecipanti as $partecipante) {
            if ($this->canReceiveEventNotifications($partecipante['id'], $evento['azienda_id'])) {
                $emailContent = EmailTemplate::eventModified($evento, $partecipante, $modifiche);
                
                $this->sendEmail(
                    $partecipante['email'],
                    "Evento Modificato: {$evento['titolo']}",
                    $emailContent,
                    'event_modified'
                );
            }
        }
    }
    
    /**
     * Invia notifica per evento cancellato
     */
    public function notifyEventCancelled($evento, $partecipanti) {
        foreach ($partecipanti as $partecipante) {
            if ($this->canReceiveEventNotifications($partecipante['id'], $evento['azienda_id'])) {
                $emailContent = EmailTemplate::eventCancelled($evento, $partecipante);
                
                $this->sendEmail(
                    $partecipante['email'],
                    "Evento Cancellato: {$evento['titolo']}",
                    $emailContent,
                    'event_cancelled'
                );
            }
        }
    }
    
    /**
     * Invia notifica per nuovo documento
     */
    public function notifyNewDocument($documento, $creator) {
        $recipients = $this->getDocumentRecipients($documento['azienda_id'], $creator['id']);
        $emailContent = EmailTemplate::newDocument($documento, $creator);
        
        foreach ($recipients as $recipient) {
            $this->sendEmail(
                $recipient['email'],
                "Nuovo Documento: {$documento['nome']}",
                $emailContent,
                'document_new'
            );
        }
    }
    
    /**
     * Invia notifica per documento modificato
     */
    public function notifyDocumentModified($documento, $updatedBy) {
        $recipients = $this->getDocumentRecipients($documento['azienda_id'], $updatedBy['id']);
        $emailContent = EmailTemplate::documentModified($documento, $updatedBy);
        
        foreach ($recipients as $recipient) {
            $this->sendEmail(
                $recipient['email'],
                "Documento Aggiornato: {$documento['nome']}",
                $emailContent,
                'document_modified'
            );
        }
    }
    
    /**
     * Invia notifica per password in scadenza
     */
    public function notifyPasswordExpiring($user, $daysLeft) {
        $emailContent = EmailTemplate::passwordExpiring($user, $daysLeft);
        
        $this->sendEmail(
            $user['email'],
            'Password in Scadenza',
            $emailContent,
            'password_expiring'
        );
    }
    
    /**
     * Invia notifica per password cambiata
     */
    public function notifyPasswordChanged($user) {
        $emailContent = EmailTemplate::passwordChanged($user);
        
        $this->sendEmail(
            $user['email'],
            'Password Modificata',
            $emailContent,
            'password_changed'
        );
    }
    
    /**
     * Invia notifica di benvenuto
     */
    public function notifyWelcomeUser($user, $tempPassword = null) {
        $emailContent = EmailTemplate::welcomeUser($user, $tempPassword);
        
        $this->sendEmail(
            $user['email'],
            'Benvenuto in Nexio Solution',
            $emailContent,
            'user_welcome'
        );
    }
    
    /**
     * Ottieni destinatari per notifiche ticket
     */
    private function getTicketRecipients($aziendaId, $excludeUserId = null) {
        // Super admin ricevono sempre tutto
        $superAdmins = db_query("
            SELECT DISTINCT u.id, u.email, u.nome, u.cognome, u.ruolo
            FROM utenti u
            WHERE u.ruolo = 'super_admin' 
            AND u.attivo = 1
            AND u.id != ?
        ", [$excludeUserId])->fetchAll();
        
        $recipients = $superAdmins;
        
        // Se c'è un'azienda, aggiungi utenti con permessi
        if ($aziendaId) {
            $companyUsers = db_query("
                SELECT DISTINCT u.id, u.email, u.nome, u.cognome, u.ruolo
                FROM utenti u
                JOIN utenti_aziende ua ON u.id = ua.utente_id
                LEFT JOIN utenti_permessi up ON ua.id = up.utente_azienda_id
                WHERE ua.azienda_id = ?
                AND u.attivo = 1
                AND ua.attivo = 1
                AND u.id != ?
                AND (up.riceve_notifiche_email = 1 OR u.ruolo = 'super_admin')
            ", [$aziendaId, $excludeUserId])->fetchAll();
            
            // Unisci evitando duplicati
            foreach ($companyUsers as $user) {
                $found = false;
                foreach ($recipients as $recipient) {
                    if ($recipient['id'] == $user['id']) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $recipients[] = $user;
                }
            }
        }
        
        return $recipients;
    }
    
    /**
     * Ottieni destinatari per notifiche documenti
     */
    private function getDocumentRecipients($aziendaId, $excludeUserId = null) {
        return $this->getTicketRecipients($aziendaId, $excludeUserId);
    }
    
    /**
     * Verifica se un utente può ricevere notifiche eventi
     */
    private function canReceiveEventNotifications($userId, $aziendaId) {
        // Super admin ricevono sempre
        $user = db_query("SELECT ruolo FROM utenti WHERE id = ?", [$userId])->fetch();
        if ($user && $user['ruolo'] === 'super_admin') {
            return true;
        }
        
        // Verifica permessi per l'azienda
        $result = db_query("
            SELECT up.riceve_notifiche_eventi
            FROM utenti_aziende ua
            LEFT JOIN utenti_permessi up ON ua.id = up.utente_azienda_id
            WHERE ua.utente_id = ? AND ua.azienda_id = ? AND ua.attivo = 1
        ", [$userId, $aziendaId])->fetch();
        
        return $result && $result['riceve_notifiche_eventi'];
    }
    
    /**
     * Invia email utilizzando il Mailer
     */
    private function sendEmail($to, $subject, $content, $type = 'notification') {
        try {
            require_once __DIR__ . '/Mailer.php';
            $mailer = Mailer::getInstance();
            
            if ($mailer->isConfigured()) {
                $success = $mailer->send($to, $subject, $content);
                
                // Log dell'invio
                if ($success) {
                    error_log("Email inviata: {$type} -> {$to}");
                } else {
                    error_log("Errore invio email: {$type} -> {$to}");
                }
                
                return $success;
            } else {
                error_log("Mailer non configurato per invio: {$type} -> {$to}");
                return false;
            }
        } catch (Exception $e) {
            error_log("Errore invio email {$type}: " . $e->getMessage());
            return false;
        }
    }
}
?>