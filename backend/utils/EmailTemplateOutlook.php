<?php
/**
 * Template email ottimizzato per Outlook e tutti i client email
 * Usa layout basato su tabelle HTML per massima compatibilità
 */

class EmailTemplateOutlook {
    
    /**
     * Genera il template HTML compatibile con Outlook
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
        $logoUrl = $appUrl . '/assets/images/nexio-logo.svg';
        
        $html = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>' . htmlspecialchars($title) . '</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:AllowPNG/>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style type="text/css">
        @media screen and (max-width: 600px) {
            .responsive-table {
                width: 100% !important;
            }
            .responsive-td {
                display: block !important;
                width: 100% !important;
                padding: 10px 0 !important;
            }
            .button-td {
                padding: 20px 0 !important;
            }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; min-width: 100%; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; font-size: 16px; line-height: 1.5; background-color: #f7fafc; color: #2d3748;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f7fafc;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <!-- Container principale -->
                <table class="responsive-table" border="0" cellpadding="0" cellspacing="0" width="600" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden;">
                    
                    <!-- Header con gradiente -->
                    <tr>
                        <td align="center" style="background: linear-gradient(135deg, #2d5a9f 0%, #1e3a6f 100%); padding: 40px 20px;">
                            <table border="0" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding-bottom: 20px;">
                                        <!-- Logo Nexio con design migliorato -->
                                        <table border="0" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td align="center">
                                                    <!-- Usa immagine logo se disponibile, altrimenti testo stilizzato -->
                                                    <div style="background-color: #ffffff; border-radius: 12px; padding: 15px 25px; display: inline-block;">
                                                        <span style="font-size: 32px; color: #2d5a9f; font-weight: bold; letter-spacing: 1px;">
                                                            NEXIO
                                                        </span>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="color: #e2e8f0; font-size: 14px; padding-bottom: 10px; letter-spacing: 0.5px;">
                                        Semplifica, Connetti, Cresci Insieme
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding-top: 10px;">
                                        <table border="0" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="background-color: rgba(255,255,255,0.2); border-radius: 6px; padding: 10px 20px;">
                                                    <span style="color: #ffffff; font-size: 20px; font-weight: 600;">
                                                        ' . htmlspecialchars($title) . '
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Contenuto principale -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td style="color: #2d3748; font-size: 16px; line-height: 26px; padding-bottom: 25px;">
                                        ' . nl2br(htmlspecialchars($message)) . '
                                    </td>
                                </tr>';
        
        // Aggiungi dettagli se presenti
        if (!empty($details)) {
            $html .= '
                                <tr>
                                    <td style="padding: 20px 0;">
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f7fafc; border-radius: 6px; border: 1px solid #e2e8f0;">
                                            <tr>
                                                <td style="padding: 25px;">';
            
            foreach ($details as $label => $value) {
                // Se il valore contiene HTML (per priorità colorate, etc.), non fare htmlspecialchars
                $displayValue = (strpos($value, '<') !== false) ? $value : htmlspecialchars($value);
                
                $html .= '
                                                    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 12px;">
                                                        <tr>
                                                            <td width="35%" style="color: #718096; font-weight: 600; font-size: 14px; padding-right: 15px; vertical-align: top;">
                                                                ' . htmlspecialchars($label) . ':
                                                            </td>
                                                            <td width="65%" style="color: #2d3748; font-size: 14px; line-height: 20px;">
                                                                ' . $displayValue . '
                                                            </td>
                                                        </tr>
                                                    </table>';
            }
            
            $html .= '
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>';
        }
        
        // Aggiungi pulsante CTA se presente
        if ($buttonText && $buttonUrl) {
            $html .= '
                                <tr>
                                    <td align="center" class="button-td" style="padding: 35px 0;">
                                        <table border="0" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td align="center" style="border-radius: 6px; background: linear-gradient(135deg, #2d5a9f 0%, #1e3a6f 100%);">
                                                    <a href="' . htmlspecialchars($buttonUrl) . '" target="_blank" style="font-size: 16px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; color: #ffffff; text-decoration: none; padding: 14px 35px; border-radius: 6px; display: inline-block; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase;">
                                                        ' . htmlspecialchars($buttonText) . '
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                        <!-- Fallback per Outlook -->
                                        <!--[if mso]>
                                        <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="' . htmlspecialchars($buttonUrl) . '" style="height:48px;v-text-anchor:middle;width:240px;" arcsize="10%" stroke="f" fillcolor="#2d5a9f">
                                            <w:anchorlock/>
                                            <center style="color:#ffffff;font-family:Arial,sans-serif;font-size:16px;font-weight:bold;text-transform:uppercase;">
                                                ' . htmlspecialchars($buttonText) . '
                                            </center>
                                        </v:roundrect>
                                        <![endif]-->
                                    </td>
                                </tr>';
        }
        
        $html .= '
                                <tr>
                                    <td style="color: #2d3748; font-size: 16px; line-height: 26px; padding-top: 25px; border-top: 1px solid #e2e8f0;">
                                        <p style="margin: 0 0 10px 0;">Cordiali saluti,</p>
                                        <p style="margin: 0; font-weight: 600; color: #2d5a9f;">Il team di ' . htmlspecialchars($appName) . '</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td align="center" style="background-color: #f7fafc; padding: 30px 20px; border-top: 2px solid #e2e8f0;">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="center" style="padding-bottom: 15px;">
                                        <table border="0" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="font-size: 18px; color: #2d5a9f; font-weight: bold; letter-spacing: 1px;">
                                                    NEXIO
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="color: #718096; font-size: 13px; line-height: 20px; padding: 0 40px;">
                                        Questa email è stata inviata automaticamente dalla piattaforma <strong style="color: #2d3748;">' . htmlspecialchars($appName) . '</strong>.<br>
                                        Per assistenza, contatta il supporto tecnico o accedi al tuo <a href="' . htmlspecialchars($appUrl) . '/profilo.php" style="color: #2d5a9f; text-decoration: none; font-weight: 600;">profilo</a> per gestire le notifiche.
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding-top: 20px;">
                                        <table border="0" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="color: #a0aec0; font-size: 11px;">
                                                    &copy; ' . date('Y') . ' ' . htmlspecialchars($appName) . '. Tutti i diritti riservati.
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Template specifici per diversi tipi di notifica
     * (Manteniamo gli stessi metodi ma usando il nuovo template)
     */
    
    // Nuovo ticket
    public static function newTicket($ticket, $creator) {
        $appUrl = defined('APP_URL') ? APP_URL : 'https://app.nexiosolution.it/piattaforma-collaborativa';
        
        // Mappa colori priorità
        $priorityColors = [
            'alta' => '#dc2626',    // Rosso
            'media' => '#f59e0b',   // Arancione
            'bassa' => '#10b981'    // Verde
        ];
        
        $priorityColor = $priorityColors[$ticket['priorita']] ?? '#6b7280';
        
        $details = [
            'Codice Ticket' => $ticket['codice'],
            'Oggetto' => $ticket['oggetto'] ?? $ticket['titolo'],
            'Categoria' => ucfirst($ticket['categoria']),
            'Priorità' => '<span style="color: ' . $priorityColor . '; font-weight: bold;">' . ucfirst($ticket['priorita']) . '</span>',
            'Creato da' => $creator['nome'] . ' ' . $creator['cognome'],
            'Data Creazione' => date('d/m/Y H:i', strtotime($ticket['creato_il']))
        ];
        
        if (!empty($ticket['descrizione'])) {
            $details['Descrizione'] = nl2br(htmlspecialchars($ticket['descrizione']));
        }
        
        $message = "È stato creato un nuovo ticket che richiede la tua attenzione.\n\n";
        $message .= "Il ticket #{$ticket['codice']} è stato aperto da {$creator['nome']} {$creator['cognome']} ";
        $message .= "con priorità " . strtoupper($ticket['priorita']) . ".";
        
        return self::generate(
            'Nuovo Ticket: ' . $ticket['codice'],
            $message,
            'Visualizza e Rispondi al Ticket',
            "{$appUrl}/tickets.php?action=view&id={$ticket['id']}",
            $details
        );
    }
    
    // Cambio stato ticket
    public static function ticketStatusChanged($ticket, $oldStatus, $newStatus, $updatedBy) {
        $appUrl = defined('APP_URL') ? APP_URL : 'https://app.nexiosolution.it/piattaforma-collaborativa';
        
        // Mappa colori stati
        $statusColors = [
            'aperto' => '#10b981',          // Verde
            'in-lavorazione' => '#f59e0b',  // Arancione
            'chiuso' => '#dc2626'            // Rosso
        ];
        
        $oldColor = $statusColors[$oldStatus] ?? '#6b7280';
        $newColor = $statusColors[$newStatus] ?? '#6b7280';
        
        $details = [
            'Codice Ticket' => $ticket['codice'],
            'Oggetto' => $ticket['oggetto'] ?? $ticket['titolo'],
            'Stato precedente' => '<span style="color: ' . $oldColor . ';">' . ucfirst(str_replace('-', ' ', $oldStatus)) . '</span>',
            'Nuovo stato' => '<span style="color: ' . $newColor . '; font-weight: bold;">' . ucfirst(str_replace('-', ' ', $newStatus)) . '</span>',
            'Modificato da' => $updatedBy['nome'] . ' ' . $updatedBy['cognome'],
            'Data modifica' => date('d/m/Y H:i')
        ];
        
        $message = "Lo stato del ticket #{$ticket['codice']} è stato aggiornato.\n\n";
        
        if ($newStatus === 'chiuso') {
            $message .= "Il ticket è stato CHIUSO da {$updatedBy['nome']} {$updatedBy['cognome']}.";
        } elseif ($newStatus === 'in-lavorazione') {
            $message .= "Il ticket è ora IN LAVORAZIONE. {$updatedBy['nome']} {$updatedBy['cognome']} sta gestendo la richiesta.";
        } else {
            $message .= "Il ticket è stato RIAPERTO da {$updatedBy['nome']} {$updatedBy['cognome']}.";
        }
        
        return self::generate(
            'Ticket ' . $ticket['codice'] . ' - Stato: ' . ucfirst(str_replace('-', ' ', $newStatus)),
            $message,
            'Visualizza Ticket',
            "{$appUrl}/tickets.php?action=view&id={$ticket['id']}",
            $details
        );
    }
    
    // Nuova risposta al ticket
    public static function ticketReply($ticket, $reply, $author) {
        $appUrl = defined('APP_URL') ? APP_URL : 'https://app.nexiosolution.it/piattaforma-collaborativa';
        
        // Mappa colori priorità
        $priorityColors = [
            'alta' => '#dc2626',    // Rosso
            'media' => '#f59e0b',   // Arancione
            'bassa' => '#10b981'    // Verde
        ];
        
        $priorityColor = $priorityColors[$ticket['priorita']] ?? '#6b7280';
        
        $details = [
            'Codice Ticket' => $ticket['codice'],
            'Oggetto' => $ticket['oggetto'] ?? $ticket['titolo'],
            'Priorità' => '<span style="color: ' . $priorityColor . ';">' . ucfirst($ticket['priorita']) . '</span>',
            'Risposta da' => $author['nome'] . ' ' . $author['cognome'],
            'Data risposta' => date('d/m/Y H:i')
        ];
        
        // Aggiungi il messaggio di risposta nei dettagli
        if (!empty($reply)) {
            $details['Messaggio'] = '<div style="background-color: #f9fafb; padding: 15px; border-left: 3px solid #2d5a9f; margin-top: 10px;">' . 
                                   nl2br(htmlspecialchars($reply)) . '</div>';
        }
        
        $message = "È stata aggiunta una nuova risposta al ticket #{$ticket['codice']}.\n\n";
        $message .= "{$author['nome']} {$author['cognome']} ha risposto al ticket.";
        
        return self::generate(
            'Nuova Risposta - Ticket ' . $ticket['codice'],
            $message,
            'Visualizza Conversazione Completa',
            "{$appUrl}/tickets.php?action=view&id={$ticket['id']}",
            $details
        );
    }
    
    // Nuovo evento
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
    
    /**
     * Template per notifica assegnazione task
     */
    public static function taskAssigned($task, $assegnato_a, $assegnato_da) {
        $appUrl = defined('APP_URL') ? APP_URL : 'https://app.nexiosolution.it/piattaforma-collaborativa';
        
        $title = "Nuovo Task Assegnato";
        
        $message = "Gentile {$assegnato_a['nome']} {$assegnato_a['cognome']},\n\n";
        $message .= "Ti è stato assegnato un nuovo task da {$assegnato_da['nome']} {$assegnato_da['cognome']}.";
        
        // Gestione prodotto/servizio
        $prodotto = '';
        if ($task['prodotto_servizio_tipo'] == 'predefinito' && $task['prodotto_servizio_predefinito']) {
            $prodotto = $task['prodotto_servizio_predefinito'];
        } elseif ($task['prodotto_servizio_personalizzato']) {
            $prodotto = $task['prodotto_servizio_personalizzato'];
        }
        
        $details = [
            'Attività' => $task['attivita'],
            'Azienda' => $task['azienda_nome'] ?? 'N/D',
            'Città' => $task['citta'],
            'Giornate Previste' => $task['giornate_previste'],
            'Costo Giornata' => '€ ' . number_format($task['costo_giornata'], 2, ',', '.'),
            'Prodotto/Servizio' => $prodotto ?: 'Non specificato',
            'Data Inizio' => date('d/m/Y', strtotime($task['data_inizio'])),
            'Data Fine' => date('d/m/Y', strtotime($task['data_fine'])),
            'Descrizione' => $task['descrizione'] ?: 'Nessuna descrizione'
        ];
        
        if (!empty($task['note'])) {
            $details['Note'] = $task['note'];
        }
        
        return self::generate(
            $title,
            $message,
            'Visualizza Task',
            "{$appUrl}/task-progress.php",
            $details
        );
    }
    
    /**
     * Template per notifica cambio stato task
     */
    public static function taskStatusChanged($task, $old_status, $new_status, $changed_by) {
        $appUrl = defined('APP_URL') ? APP_URL : 'https://app.nexiosolution.it/piattaforma-collaborativa';
        
        $title = "Stato Task Aggiornato";
        
        // Mappa degli stati in italiano
        $stati = [
            'assegnato' => 'Assegnato',
            'in_corso' => 'In Corso',
            'completato' => 'Completato',
            'annullato' => 'Annullato'
        ];
        
        $vecchio_stato = $stati[$old_status] ?? $old_status;
        $nuovo_stato = $stati[$new_status] ?? $new_status;
        
        $message = "Lo stato del task è stato aggiornato da {$changed_by['nome']} {$changed_by['cognome']}.\n\n";
        $message .= "Il task è passato da \"{$vecchio_stato}\" a \"{$nuovo_stato}\".";
        
        // Gestione prodotto/servizio
        $prodotto = '';
        if ($task['prodotto_servizio_tipo'] == 'predefinito' && $task['prodotto_servizio_predefinito']) {
            $prodotto = $task['prodotto_servizio_predefinito'];
        } elseif ($task['prodotto_servizio_personalizzato']) {
            $prodotto = $task['prodotto_servizio_personalizzato'];
        }
        
        $details = [
            'Attività' => $task['attivita'],
            'Azienda' => $task['azienda_nome'] ?? 'N/D',
            'Città' => $task['citta'],
            'Vecchio Stato' => $vecchio_stato,
            'Nuovo Stato' => $nuovo_stato,
            'Giornate Previste' => $task['giornate_previste'],
            'Prodotto/Servizio' => $prodotto ?: 'Non specificato',
            'Data Inizio' => date('d/m/Y', strtotime($task['data_inizio'])),
            'Data Fine' => date('d/m/Y', strtotime($task['data_fine']))
        ];
        
        if ($new_status == 'completato' && !empty($task['giornate_effettive'])) {
            $details['Giornate Effettive'] = $task['giornate_effettive'];
        }
        
        if (!empty($task['note'])) {
            $details['Note'] = $task['note'];
        }
        
        return self::generate(
            $title,
            $message,
            'Visualizza Task',
            "{$appUrl}/calendario-eventi.php",
            $details
        );
    }
    
    /**
     * Template per notifica task completato
     */
    public static function taskCompleted($task, $completato_da, $assegnato_da) {
        $appUrl = defined('APP_URL') ? APP_URL : 'https://app.nexiosolution.it/piattaforma-collaborativa';
        
        $title = "Task Completato";
        
        $message = "Il task che hai assegnato è stato completato da {$completato_da['nome']} {$completato_da['cognome']}.";
        
        // Gestione prodotto/servizio
        $prodotto = '';
        if ($task['prodotto_servizio_tipo'] == 'predefinito' && $task['prodotto_servizio_predefinito']) {
            $prodotto = $task['prodotto_servizio_predefinito'];
        } elseif ($task['prodotto_servizio_personalizzato']) {
            $prodotto = $task['prodotto_servizio_personalizzato'];
        }
        
        $details = [
            'Attività' => $task['attivita'],
            'Azienda' => $task['azienda_nome'] ?? 'N/D',
            'Città' => $task['citta'],
            'Giornate Previste' => $task['giornate_previste'],
            'Giornate Effettive' => $task['giornate_effettive'] ?? $task['giornate_previste'],
            'Prodotto/Servizio' => $prodotto ?: 'Non specificato',
            'Data Completamento' => date('d/m/Y H:i')
        ];
        
        if (!empty($task['note'])) {
            $details['Note'] = $task['note'];
        }
        
        return self::generate(
            $title,
            $message,
            'Visualizza Dettagli',
            "{$appUrl}/calendario-eventi.php",
            $details
        );
    }
    
    /**
     * Template per file caricato
     */
    public static function fileUploaded($file_info, $uploader) {
        $title = "Nuovo File Caricato";
        
        $message = "È stato caricato un nuovo file da {$uploader['nome']} {$uploader['cognome']}.";
        
        $details = [
            'Nome File' => $file_info['nome'],
            'Cartella' => $file_info['cartella'] ?? 'Root',
            'Azienda' => $file_info['azienda_nome'] ?? 'N/D',
            'Data Caricamento' => date('d/m/Y H:i')
        ];
        
        return self::generate($title, $message, null, null, $details);
    }
    
    /**
     * Template per file sostituito
     */
    public static function fileReplaced($file_info, $replacedBy) {
        $title = "File Sostituito";
        
        $message = "Un file è stato sostituito da {$replacedBy['nome']} {$replacedBy['cognome']}.";
        
        $details = [
            'Nome File' => $file_info['nome'],
            'Cartella' => $file_info['cartella'] ?? 'Root',
            'Azienda' => $file_info['azienda_nome'] ?? 'N/D',
            'Data Sostituzione' => date('d/m/Y H:i')
        ];
        
        return self::generate($title, $message, null, null, $details);
    }
    
    /**
     * Template per file eliminato
     */
    public static function fileDeleted($file_info, $deletedBy) {
        $title = "File Eliminato";
        
        $message = "Un file è stato eliminato da {$deletedBy['nome']} {$deletedBy['cognome']}.";
        
        $details = [
            'Nome File' => $file_info['nome'],
            'Cartella' => $file_info['cartella'] ?? 'Root',
            'Azienda' => $file_info['azienda_nome'] ?? 'N/D',
            'Data Eliminazione' => date('d/m/Y H:i')
        ];
        
        return self::generate($title, $message, null, null, $details);
    }
    
    /**
     * Template per cartella creata
     */
    public static function folderCreated($folder_info, $creator) {
        $title = "Nuova Cartella Creata";
        
        $message = "È stata creata una nuova cartella da {$creator['nome']} {$creator['cognome']}.";
        
        $details = [
            'Nome Cartella' => $folder_info['nome'],
            'Percorso' => $folder_info['percorso'] ?? 'Root',
            'Azienda' => $folder_info['azienda_nome'] ?? 'N/D',
            'Data Creazione' => date('d/m/Y H:i')
        ];
        
        return self::generate($title, $message, null, null, $details);
    }
    
    /**
     * Template per nuovo documento
     */
    public static function newDocument($documento, $creator) {
        $title = "Nuovo Documento Creato";
        
        $message = "È stato creato un nuovo documento da {$creator['nome']} {$creator['cognome']}.";
        
        $details = [
            'Titolo' => $documento['nome'],
            'Tipo' => $documento['tipo'] ?? 'Documento',
            'Data Creazione' => date('d/m/Y H:i')
        ];
        
        return self::generate($title, $message, null, null, $details);
    }
    
    /**
     * Template per documento modificato
     */
    public static function documentModified($documento, $updatedBy) {
        $title = "Documento Modificato";
        
        $message = "Un documento è stato modificato da {$updatedBy['nome']} {$updatedBy['cognome']}.";
        
        $details = [
            'Titolo' => $documento['nome'],
            'Data Modifica' => date('d/m/Y H:i')
        ];
        
        return self::generate($title, $message, null, null, $details);
    }
    
    /**
     * Template per recupero password con password temporanea
     */
    public static function passwordRecovery($data) {
        $title = "Password Temporanea";
        
        $message = "Ciao {$data['nome']},\n\nHai richiesto il recupero della password per il tuo account. Ti abbiamo generato una password temporanea che dovrai cambiare al primo accesso.";
        
        $details = [
            'Email' => $data['email'],
            'Password Temporanea' => $data['password_temporanea'],
            'Validità' => $data['scadenza'] ?? '24 ore'
        ];
        
        // Aggiungi nota importante
        $message .= "\n\n⚠️ IMPORTANTE: Per motivi di sicurezza, dovrai cambiare questa password al primo accesso.";
        
        $buttonText = "Accedi alla Piattaforma";
        $buttonUrl = (defined('APP_URL') ? APP_URL : 'https://app.nexiosolution.it/piattaforma-collaborativa') . '/login.php';
        
        return self::generate($title, $message, $buttonText, $buttonUrl, $details);
    }
}
?>