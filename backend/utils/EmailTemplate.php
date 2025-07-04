<?php
/**
 * Template email unificato per tutte le notifiche di sistema
 */

class EmailTemplate {
    
    /**
     * Genera il template HTML per le email
     * 
     * @param string $title Titolo dell'email
     * @param string $message Messaggio principale
     * @param string $buttonText Testo del pulsante CTA
     * @param string $buttonUrl URL del pulsante
     * @param array $details Array di dettagli aggiuntivi (opzionale)
     * @return string HTML dell'email
     */
    public static function generate($title, $message, $buttonText = null, $buttonUrl = null, $details = []) {
        $appUrl = defined('APP_URL') ? APP_URL : 'https://app.nexiosolution.it/piattaforma-collaborativa';
        $appName = defined('APP_NAME') ? APP_NAME : 'Nexio Solution';
        
        $html = "
        <!DOCTYPE html>
        <html lang='it'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$title}</title>
        </head>
        <body style='margin: 0; padding: 0; background-color: #f8f9fa; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif;'>
            <div style='max-width: 600px; margin: 20px auto; background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); overflow: hidden;'>
                <!-- Header con logo -->
                <div style='background: linear-gradient(135deg, #2d5a9f 0%, #1b3f76 100%); padding: 30px; text-align: center;'>
                    <!-- Logo inline compatibile con email -->
                    <div style='display: inline-block; width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 12px; margin-bottom: 15px; line-height: 60px; font-size: 28px; color: white; border: 2px solid rgba(255,255,255,0.3);'>✦</div>
                    <div style='color: white; font-size: 18px; font-weight: 600; margin-bottom: 5px;'>Nexio Solution</div>
                    <h1 style='color: white; margin: 0; font-size: 24px; font-weight: 600;'>{$title}</h1>
                </div>
                
                <!-- Contenuto principale -->
                <div style='padding: 30px;'>
                    <p style='color: #4a5568; line-height: 1.6; margin-bottom: 25px; font-size: 16px;'>{$message}</p>";
        
        // Aggiungi dettagli se presenti
        if (!empty($details)) {
            $html .= "
                    <div style='background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin: 25px 0;'>";
            
            foreach ($details as $label => $value) {
                $icon = self::getIconForDetail($label);
                $html .= "
                        <div style='display: flex; align-items: center; margin-bottom: 12px;'>
                            <span style='display: inline-block; width: 20px; color: #4299e1; font-size: 16px; margin-right: 10px;'>{$icon}</span>
                            <span style='color: #2d3748; font-weight: 600; margin-right: 8px;'>{$label}:</span>
                            <span style='color: #4a5568;'>{$value}</span>
                        </div>";
            }
            
            $html .= "
                    </div>";
        }
        
        // Aggiungi pulsante CTA se presente
        if ($buttonText && $buttonUrl) {
            $html .= "
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$buttonUrl}' style='display: inline-block; background: linear-gradient(135deg, #4299e1 0%, #2d5a9f 100%); color: white; text-decoration: none; padding: 12px 30px; border-radius: 8px; font-weight: 600; font-size: 14px;'>{$buttonText}</a>
                    </div>";
        }
        
        $html .= "
                    <p style='color: #4a5568; line-height: 1.6; margin-bottom: 0;'>Cordiali saluti,<br><strong>Il team di {$appName}</strong></p>
                </div>
                
                <!-- Footer -->
                <div style='background: #f8f9fa; border-top: 1px solid #e2e8f0; padding: 20px; text-align: center;'>
                    <p style='color: #718096; font-size: 12px; line-height: 1.5; margin: 0;'>
                        Questa email è stata inviata automaticamente dalla piattaforma <strong>{$appName}</strong>.<br>
                        <small>Per modificare le preferenze di notifica, accedi al tuo profilo.</small>
                    </p>
                </div>
            </div>
        </body>
        </html>";
        
        return $html;
    }
    
    /**
     * Ottieni l'icona appropriata per il tipo di dettaglio
     */
    private static function getIconForDetail($label) {
        $icons = [
            'Ticket' => '🎫',
            'Stato' => '📊',
            'Priorità' => '⚡',
            'Data' => '📅',
            'Ora' => '🕐',
            'Luogo' => '📍',
            'Documento' => '📄',
            'Utente' => '👤',
            'Azienda' => '🏢',
            'Categoria' => '📁',
            'Tipo' => '🏷️',
            'Durata' => '⏱️',
            'Organizzatore' => '👤',
            'Partecipanti' => '👥',
            'Descrizione' => '📝'
        ];
        
        foreach ($icons as $key => $icon) {
            if (stripos($label, $key) !== false) {
                return $icon;
            }
        }
        
        return '•';
    }
    
    /**
     * Template specifici per diversi tipi di notifica
     */
    
    // Nuovo ticket
    public static function newTicket($ticket, $creator) {
        $appUrl = defined('APP_URL') ? APP_URL : 'https://app.nexiosolution.it/piattaforma-collaborativa';
        
        $details = [
            'Ticket ID' => "#{$ticket['id']}",
            'Oggetto' => $ticket['oggetto'] ?? $ticket['titolo'],
            'Priorità' => ucfirst($ticket['priorita']),
            'Creato da' => $creator['nome'] . ' ' . $creator['cognome']
        ];
        
        return self::generate(
            'Nuovo Ticket Creato',
            "È stato creato un nuovo ticket che richiede la tua attenzione.",
            'Visualizza Tickets',
            "{$appUrl}/tickets.php",
            $details
        );
    }
    
    // Cambio stato ticket
    public static function ticketStatusChanged($ticket, $oldStatus, $newStatus, $updatedBy) {
        $appUrl = defined('APP_URL') ? APP_URL : 'https://app.nexiosolution.it/piattaforma-collaborativa';
        
        $details = [
            'Ticket ID' => "#{$ticket['id']}",
            'Oggetto' => $ticket['oggetto'] ?? $ticket['titolo'],
            'Stato precedente' => ucfirst($oldStatus),
            'Nuovo stato' => ucfirst($newStatus),
            'Modificato da' => $updatedBy['nome'] . ' ' . $updatedBy['cognome']
        ];
        
        return self::generate(
            'Aggiornamento Stato Ticket',
            "Lo stato del ticket #{$ticket['id']} è stato aggiornato.",
            'Visualizza Tickets',
            "{$appUrl}/tickets.php",
            $details
        );
    }
    
    // Nuovo evento (sostituisce il template in EventInvite)
    public static function eventInvitation($evento, $partecipante) {
        $appUrl = defined('APP_URL') ? APP_URL : 'https://app.nexiosolution.it/piattaforma-collaborativa';
        
        $dataInizio = date('d/m/Y H:i', strtotime($evento['data_inizio']));
        $dataFine = date('d/m/Y H:i', strtotime($evento['data_fine']));
        
        $details = [
            'Evento' => $evento['titolo'],
            'Data inizio' => $dataInizio,
            'Data fine' => $dataFine
        ];
        
        if (!empty($evento['luogo'])) {
            $details['Luogo'] = $evento['luogo'];
        }
        
        if (!empty($evento['descrizione'])) {
            $details['Descrizione'] = $evento['descrizione'];
        }
        
        return self::generate(
            'Invito all\'evento',
            "Gentile {$partecipante['nome']} {$partecipante['cognome']}, sei stato invitato a partecipare al seguente evento.",
            'Visualizza nel Calendario',
            "{$appUrl}/calendario-eventi.php",
            $details
        );
    }
    
    // Evento modificato
    public static function eventModified($evento, $partecipante, $modifiche) {
        $appUrl = defined('APP_URL') ? APP_URL : 'https://app.nexiosolution.it/piattaforma-collaborativa';
        
        $details = [
            'Evento' => $evento['titolo']
        ];
        
        // Aggiungi solo i campi modificati
        foreach ($modifiche as $campo => $valore) {
            $details[ucfirst($campo)] = $valore;
        }
        
        return self::generate(
            'Evento Modificato',
            "L'evento a cui sei stato invitato è stato modificato.",
            'Visualizza Dettagli',
            "{$appUrl}/calendario-eventi.php",
            $details
        );
    }
    
    // Evento cancellato
    public static function eventCancelled($evento, $partecipante) {
        $details = [
            'Evento' => $evento['titolo'],
            'Data prevista' => date('d/m/Y H:i', strtotime($evento['data_inizio']))
        ];
        
        return self::generate(
            'Evento Cancellato',
            "L'evento '{$evento['titolo']}' è stato cancellato.",
            null,
            null,
            $details
        );
    }
    
    // Nuovo documento
    public static function newDocument($documento, $creator) {
        $appUrl = defined('APP_URL') ? APP_URL : 'https://app.nexiosolution.it/piattaforma-collaborativa';
        
        $details = [
            'Documento' => $documento['nome'],
            'Tipo' => $documento['tipo'] ?? 'Documento',
            'Creato da' => $creator['nome'] . ' ' . $creator['cognome'],
            'Data' => date('d/m/Y H:i')
        ];
        
        return self::generate(
            'Nuovo Documento Disponibile',
            "È stato caricato un nuovo documento nella piattaforma.",
            'Visualizza Documenti',
            "{$appUrl}/documenti.php",
            $details
        );
    }
    
    // Documento modificato
    public static function documentModified($documento, $updatedBy) {
        $appUrl = defined('APP_URL') ? APP_URL : 'https://app.nexiosolution.it/piattaforma-collaborativa';
        
        $details = [
            'Documento' => $documento['nome'],
            'Modificato da' => $updatedBy['nome'] . ' ' . $updatedBy['cognome'],
            'Data modifica' => date('d/m/Y H:i')
        ];
        
        return self::generate(
            'Documento Aggiornato',
            "Il documento '{$documento['nome']}' è stato aggiornato.",
            'Visualizza Documenti',
            "{$appUrl}/documenti.php",
            $details
        );
    }
    
    // Reset password
    public static function passwordReset($user, $resetLink) {
        $details = [
            'Utente' => $user['nome'] . ' ' . $user['cognome'],
            'Email' => $user['email'],
            'Validità link' => '24 ore'
        ];
        
        return self::generate(
            'Reset Password',
            "Hai richiesto il reset della password per il tuo account. Clicca sul pulsante qui sotto per impostare una nuova password. Se non hai richiesto tu questo reset, ignora questa email.",
            'Reimposta Password',
            $resetLink,
            $details
        );
    }
    
    // Password cambiata
    public static function passwordChanged($user) {
        $appUrl = defined('APP_URL') ? APP_URL : 'https://app.nexiosolution.it/piattaforma-collaborativa';
        
        $details = [
            'Data cambio' => date('d/m/Y H:i'),
            'Prossima scadenza' => date('d/m/Y', strtotime('+60 days'))
        ];
        
        return self::generate(
            'Password Modificata',
            "La tua password è stata modificata con successo. Se non hai effettuato tu questa operazione, contatta immediatamente l'amministratore.",
            'Accedi al tuo Account',
            "{$appUrl}/login.php",
            $details
        );
    }
    
    // Benvenuto nuovo utente
    public static function welcomeUser($user, $tempPassword = null) {
        $appUrl = defined('APP_URL') ? APP_URL : 'https://app.nexiosolution.it/piattaforma-collaborativa';
        
        $details = [
            'Nome' => $user['nome'] . ' ' . $user['cognome'],
            'Email' => $user['email'],
            'Ruolo' => ucfirst($user['ruolo'])
        ];
        
        if ($tempPassword) {
            $details['Password temporanea'] = $tempPassword;
            $message = "Benvenuto in Nexio Solution! Il tuo account è stato creato con successo. Al primo accesso ti verrà richiesto di cambiare la password.";
        } else {
            $message = "Benvenuto in Nexio Solution! Il tuo account è stato creato con successo.";
        }
        
        return self::generate(
            'Benvenuto in Nexio Solution',
            $message,
            'Accedi alla Piattaforma',
            "{$appUrl}/login.php",
            $details
        );
    }
    
    /**
     * Metodi per notifiche ticket
     */
    
    // Aggiungi commento a ticket
    public static function ticketCommentAdded($ticket, $comment, $author) {
        $appUrl = defined('APP_URL') ? APP_URL : 'https://app.nexiosolution.it/piattaforma-collaborativa';
        
        $details = [
            'Ticket' => "#{$ticket['codice']} - {$ticket['titolo']}",
            'Autore commento' => $author['nome'] . ' ' . $author['cognome'],
            'Data' => date('d/m/Y H:i'),
            'Commento' => substr(strip_tags($comment['contenuto']), 0, 200) . '...'
        ];
        
        return self::generate(
            'Nuovo Commento su Ticket',
            "È stato aggiunto un nuovo commento al ticket #{$ticket['codice']}.",
            'Visualizza Tickets',
            "{$appUrl}/tickets.php",
            $details
        );
    }
    
    // Ticket assegnato
    public static function ticketAssigned($ticket, $assignedTo, $assignedBy) {
        $appUrl = defined('APP_URL') ? APP_URL : 'https://app.nexiosolution.it/piattaforma-collaborativa';
        
        $details = [
            'Ticket' => "#{$ticket['codice']} - {$ticket['titolo']}",
            'Assegnato a' => $assignedTo['nome'] . ' ' . $assignedTo['cognome'],
            'Assegnato da' => $assignedBy['nome'] . ' ' . $assignedBy['cognome'],
            'Priorità' => ucfirst($ticket['priorita'])
        ];
        
        return self::generate(
            'Ticket Assegnato',
            "Ti è stato assegnato un nuovo ticket.",
            'Visualizza Tickets',
            "{$appUrl}/tickets.php",
            $details
        );
    }
    
    // Notifica scadenza password
    public static function passwordExpiring($user, $daysLeft) {
        $appUrl = defined('APP_URL') ? APP_URL : 'https://app.nexiosolution.it/piattaforma-collaborativa';
        
        $details = [
            'Giorni rimanenti' => $daysLeft,
            'Data scadenza' => date('d/m/Y', strtotime("+{$daysLeft} days"))
        ];
        
        $message = "La tua password scadrà tra {$daysLeft} giorni. Ti consigliamo di cambiarla il prima possibile per evitare interruzioni nell'accesso.";
        
        return self::generate(
            'Password in Scadenza',
            $message,
            'Cambia Password',
            "{$appUrl}/cambio-password.php",
            $details
        );
    }
}
?>