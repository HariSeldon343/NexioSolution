<?php
/**
 * Script di test per visualizzare il nuovo template email dei ticket
 * Questo file può essere rimosso dopo il test
 */

// Includi i file necessari
require_once 'backend/config/config.php';
require_once 'backend/utils/EmailTemplateOutlook.php';

// Dati di esempio per il ticket
$ticket = [
    'id' => 123,
    'codice' => 'TICKET-2025-0001',
    'oggetto' => 'Problema accesso sistema documentale',
    'descrizione' => "Non riesco ad accedere al sistema documentale.\nIl sistema mostra un errore 500 quando provo ad entrare.\nHo già provato a svuotare la cache del browser ma il problema persiste.",
    'categoria' => 'tecnico',
    'priorita' => 'alta',
    'stato' => 'aperto',
    'azienda_id' => 1,
    'creato_il' => date('Y-m-d H:i:s')
];

$creator = [
    'id' => 1,
    'nome' => 'Mario',
    'cognome' => 'Rossi',
    'email' => 'mario.rossi@example.com'
];

$author = [
    'id' => 2,
    'nome' => 'Luca',
    'cognome' => 'Bianchi',
    'email' => 'luca.bianchi@example.com'
];

// Scegli quale template visualizzare
$templateType = $_GET['type'] ?? 'new';

switch($templateType) {
    case 'new':
        // Template per nuovo ticket
        $emailHtml = EmailTemplateOutlook::newTicket($ticket, $creator);
        $title = "Preview: Nuovo Ticket";
        break;
        
    case 'reply':
        // Template per risposta al ticket
        $reply = "Ho verificato il problema e sembra essere dovuto a un'interruzione temporanea del servizio.\nHo riavviato il server e ora dovrebbe funzionare correttamente.\n\nProva ad accedere nuovamente e fammi sapere se il problema persiste.";
        $emailHtml = EmailTemplateOutlook::ticketReply($ticket, $reply, $author);
        $title = "Preview: Risposta Ticket";
        break;
        
    case 'status':
        // Template per cambio stato
        $emailHtml = EmailTemplateOutlook::ticketStatusChanged($ticket, 'aperto', 'in-lavorazione', $author);
        $title = "Preview: Cambio Stato Ticket";
        break;
        
    case 'closed':
        // Template per ticket chiuso
        $emailHtml = EmailTemplateOutlook::ticketStatusChanged($ticket, 'in-lavorazione', 'chiuso', $author);
        $title = "Preview: Ticket Chiuso";
        break;
        
    default:
        $emailHtml = EmailTemplateOutlook::newTicket($ticket, $creator);
        $title = "Preview: Email Template";
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <style>
        body {
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        .controls {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .controls h1 {
            margin: 0 0 20px 0;
            color: #2d5a9f;
            font-size: 24px;
        }
        .controls p {
            color: #666;
            margin: 10px 0;
        }
        .template-selector {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .template-selector a {
            padding: 10px 20px;
            background: #2d5a9f;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            transition: background 0.3s;
        }
        .template-selector a:hover {
            background: #1e3a6f;
        }
        .template-selector a.active {
            background: #1e3a6f;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .email-preview {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .email-preview iframe {
            width: 100%;
            height: 800px;
            border: none;
        }
        .info {
            background: #f0f9ff;
            border-left: 4px solid #2d5a9f;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info strong {
            color: #2d5a9f;
        }
    </style>
</head>
<body>
    <div class="controls">
        <h1><?php echo $title; ?></h1>
        
        <div class="info">
            <strong>Template Email Migliorato</strong><br>
            Questo è il nuovo design professionale per le email di notifica dei ticket con:
            <ul style="margin: 10px 0;">
                <li>Logo aziendale nel header</li>
                <li>Design responsive per dispositivi mobili</li>
                <li>Colori coerenti con il tema della piattaforma</li>
                <li>Pulsante call-to-action prominente</li>
                <li>Compatibilità con Outlook e tutti i client email</li>
                <li>Indicatori colorati per priorità e stati</li>
            </ul>
        </div>
        
        <p><strong>Seleziona il tipo di template da visualizzare:</strong></p>
        <div class="template-selector">
            <a href="?type=new" <?php echo $templateType === 'new' ? 'class="active"' : ''; ?>>Nuovo Ticket</a>
            <a href="?type=reply" <?php echo $templateType === 'reply' ? 'class="active"' : ''; ?>>Risposta al Ticket</a>
            <a href="?type=status" <?php echo $templateType === 'status' ? 'class="active"' : ''; ?>>Cambio Stato</a>
            <a href="?type=closed" <?php echo $templateType === 'closed' ? 'class="active"' : ''; ?>>Ticket Chiuso</a>
        </div>
    </div>
    
    <div class="email-preview">
        <iframe srcdoc="<?php echo htmlspecialchars($emailHtml); ?>"></iframe>
    </div>
    
    <div class="controls" style="margin-top: 20px;">
        <p><strong>Nota:</strong> Questa è solo un'anteprima. Le email reali saranno inviate con i dati effettivi del ticket.</p>
        <p>Per tornare alla gestione ticket: <a href="tickets.php">Vai ai Ticket</a></p>
    </div>
</body>
</html>