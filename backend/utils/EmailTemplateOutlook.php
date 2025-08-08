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
</head>
<body style="margin: 0; padding: 0; min-width: 100%; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5; background-color: #f4f4f4; color: #333333;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f4f4f4;">
        <tr>
            <td align="center" style="padding: 40px 0;">
                <!-- Container principale -->
                <table border="0" cellpadding="0" cellspacing="0" width="600" style="background-color: #ffffff; border: 1px solid #dddddd;">
                    
                    <!-- Header -->
                    <tr>
                        <td align="center" bgcolor="#2d5a9f" style="padding: 40px 20px;">
                            <table border="0" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding-bottom: 20px;">
                                        <!-- Logo Nexio in formato testo -->
                                        <table border="0" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="font-size: 36px; color: #ffffff; font-weight: bold; letter-spacing: 2px;">
                                                    NEXIO
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="color: #ffffff; font-size: 14px; padding-bottom: 10px;">
                                        Semplifica, Connetti, Cresci Insieme
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="color: #ffffff; font-size: 24px; font-weight: bold; padding-top: 10px;">
                                        ' . htmlspecialchars($title) . '
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
                                    <td style="color: #333333; font-size: 16px; line-height: 24px; padding-bottom: 20px;">
                                        ' . nl2br(htmlspecialchars($message)) . '
                                    </td>
                                </tr>';
        
        // Aggiungi dettagli se presenti
        if (!empty($details)) {
            $html .= '
                                <tr>
                                    <td style="padding: 20px 0;">
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8f8f8; border: 1px solid #e0e0e0;">
                                            <tr>
                                                <td style="padding: 20px;">';
            
            foreach ($details as $label => $value) {
                $html .= '
                                                    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 10px;">
                                                        <tr>
                                                            <td width="30%" style="color: #666666; font-weight: bold; font-size: 14px; padding-right: 10px; vertical-align: top;">
                                                                ' . htmlspecialchars($label) . ':
                                                            </td>
                                                            <td width="70%" style="color: #333333; font-size: 14px;">
                                                                ' . htmlspecialchars($value) . '
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
                                    <td align="center" style="padding: 30px 0;">
                                        <table border="0" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td align="center" style="border-radius: 4px;" bgcolor="#4299e1">
                                                    <a href="' . htmlspecialchars($buttonUrl) . '" target="_blank" style="font-size: 16px; font-family: Arial, sans-serif; color: #ffffff; text-decoration: none; padding: 12px 30px; border: 1px solid #4299e1; display: inline-block; font-weight: bold;">
                                                        ' . htmlspecialchars($buttonText) . '
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                        <!-- Fallback per Outlook -->
                                        <!--[if mso]>
                                        <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="' . htmlspecialchars($buttonUrl) . '" style="height:40px;v-text-anchor:middle;width:200px;" arcsize="10%" stroke="f" fillcolor="#4299e1">
                                            <w:anchorlock/>
                                            <center style="color:#ffffff;font-family:Arial,sans-serif;font-size:16px;font-weight:bold;">
                                                ' . htmlspecialchars($buttonText) . '
                                            </center>
                                        </v:roundrect>
                                        <![endif]-->
                                    </td>
                                </tr>';
        }
        
        $html .= '
                                <tr>
                                    <td style="color: #333333; font-size: 16px; line-height: 24px; padding-top: 20px;">
                                        Cordiali saluti,<br>
                                        <strong>Il team di ' . htmlspecialchars($appName) . '</strong>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td align="center" bgcolor="#f8f8f8" style="padding: 30px 20px; border-top: 1px solid #e0e0e0;">
                            <table border="0" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="color: #666666; font-size: 12px; line-height: 18px;">
                                        Questa email è stata inviata automaticamente dalla piattaforma <strong>' . htmlspecialchars($appName) . '</strong>.<br>
                                        Per modificare le preferenze di notifica, accedi al tuo profilo.
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