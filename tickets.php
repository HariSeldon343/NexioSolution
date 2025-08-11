<?php
require_once 'backend/config/config.php';
require_once 'backend/utils/ActivityLogger.php';
require_once 'backend/utils/Mailer.php';
require_once 'backend/utils/ModulesHelper.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$user = $auth->getUser();
$isSuperAdmin = $auth->isSuperAdmin();
// Database instance handled by functions
$logger = ActivityLogger::getInstance();
$mailer = Mailer::getInstance();

// Verifica azienda selezionata
$aziendaId = $_SESSION['azienda_id'] ?? null;

// Verifica accesso al modulo tickets
if (!$isSuperAdmin) {
    ModulesHelper::requireModule('tickets');
}

// Per super admin, gestisci filtro azienda
$filter_azienda_id = null;
$aziende_list = [];

if ($isSuperAdmin) {
    // Carica lista aziende per il filtro
    $aziende_list = db_query("SELECT id, nome FROM aziende WHERE stato = 'attiva' ORDER BY nome")->fetchAll();
    
    // Se c'è un filtro azienda specifico nei GET
    if (isset($_GET['azienda_filter'])) {
        $filter_azienda_id = intval($_GET['azienda_filter']);
    }
} else {
    // Utente normale deve avere un'azienda selezionata
    if (!$aziendaId) {
        redirect('seleziona-azienda.php');
    }
}

// Gestione invio nuovo ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Debug: Log all POST data to identify if 'titolo' is being sent
    if (isset($_POST['titolo'])) {
        error_log("WARNING: 'titolo' field detected in POST data. This should be 'oggetto'. POST data: " . print_r($_POST, true));
        // Auto-fix: if titolo is sent instead of oggetto, use it
        if (!isset($_POST['oggetto']) && isset($_POST['titolo'])) {
            $_POST['oggetto'] = $_POST['titolo'];
            error_log("Auto-fixed: Using 'titolo' value as 'oggetto'");
        }
    }
    
    if ($_POST['action'] === 'nuovo') {
        $oggetto = trim($_POST['oggetto'] ?? '');
        $descrizione = trim($_POST['descrizione'] ?? '');
        $categoria = $_POST['categoria'] ?? 'altro';
        $priorita = $_POST['priorita'] ?? 'media';
        $destinatari = $_POST['destinatari'] ?? [];
        
        // Per super admin, prendi l'azienda dal form
        if ($auth->isSuperAdmin()) {
            $ticketAziendaId = $_POST['azienda_id'] ?? null;
            if (!$ticketAziendaId) {
                $_SESSION['error'] = "Seleziona un'azienda per cui aprire il ticket";
                redirect('tickets.php?action=nuovo');
                exit;
            }
        } else {
            // Per utenti normali, usa l'azienda corrente
            $ticketAziendaId = $aziendaId;
        }
        
        if (empty($oggetto) || empty($descrizione)) {
            $_SESSION['error'] = "Oggetto e descrizione sono obbligatori";
        } else {
            try {
                db_connection()->beginTransaction();
                
                // Genera codice ticket
                $anno = date('Y');
                $stmt = db_query("
                    SELECT COUNT(*) as total 
                    FROM tickets 
                    WHERE YEAR(creato_il) = ?", [$anno]
                );
                $result = $stmt->fetch();
                $numero = $result['total'] + 1;
                $codice = sprintf("TICKET-%s-%04d", $anno, $numero);
                
                // Crea il ticket
                $ticketId = db_insert('tickets', [
                    'codice' => $codice,
                    'azienda_id' => $ticketAziendaId,
                    'utente_id' => $user['id'],
                    'oggetto' => $oggetto,
                    'descrizione' => $descrizione,
                    'categoria' => $categoria,
                    'priorita' => $priorita,
                    'stato' => 'aperto'
                ]);
                
                // Aggiungi destinatari se selezionati
                if (!empty($destinatari)) {
                    foreach ($destinatari as $destinatarioId) {
                        db_insert('ticket_destinatari', [
                            'ticket_id' => $ticketId,
                            'utente_id' => $destinatarioId,
                            'tipo_destinatario' => 'assegnato'
                        ]);
                    }
                                    } else {
                        // Se nessun destinatario selezionato, invia a tutti gli admin
                        $stmt = db_query("
                            SELECT DISTINCT u.id
                            FROM utenti u
                            JOIN utenti_aziende ua ON u.id = ua.utente_id
                            WHERE ua.azienda_id = ? 
                            AND (ua.ruolo_azienda IN ('proprietario', 'admin') OR u.ruolo = 'super_admin')
                            AND u.attivo = 1
                            AND u.id != ?
                        ", [$ticketAziendaId, $user['id']]);
                    
                    $adminIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($adminIds as $adminId) {
                        db_insert('ticket_destinatari', [
                            'ticket_id' => $ticketId,
                            'utente_id' => $adminId,
                            'tipo_destinatario' => 'cc'
                        ]);
                    }
                }
                
                // Prepara i dati del ticket per l'email
                $ticketData = [
                    'id' => $ticketId,
                    'codice' => $codice,
                    'oggetto' => $oggetto,
                    'descrizione' => $descrizione,
                    'categoria' => $categoria,
                    'priorita' => $priorita,
                    'azienda_id' => $ticketAziendaId,
                    'creato_il' => date('Y-m-d H:i:s')
                ];
                
                // Usa il NotificationCenter per inviare email con il template professionale
                require_once 'backend/utils/NotificationCenter.php';
                require_once 'backend/utils/EmailTemplateOutlook.php';
                $notificationCenter = NotificationCenter::getInstance();
                $notificationCenter->notifyNewTicket($ticketData, $user);
                
                // Notifica super admin con il template professionale
                $notificationManager = NotificationManager::getInstance();
                $emailContent = EmailTemplateOutlook::newTicket($ticketData, $user);
                $notificationManager->notificaSuperAdmin(
                    'ticket_creato',
                    "Nuovo Ticket: {$ticketData['codice']}",
                    $emailContent
                );
                
                db_connection()->commit();
                
                $logger->log('ticket_creato', "Creato ticket: $codice");
                $_SESSION['success'] = "Ticket creato con successo: $codice";
                redirect('tickets.php?action=view&id=' . $ticketId);
                
            } catch (Exception $e) {
                db_connection()->rollback();
                $_SESSION['error'] = "Errore nella creazione del ticket: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'risposta' && isset($_POST['ticket_id'])) {
        // Gestione risposta al ticket
        $ticketId = intval($_POST['ticket_id']);
        $messaggio = trim($_POST['messaggio'] ?? '');
        
        if (empty($messaggio)) {
            $_SESSION['error'] = "Il messaggio non può essere vuoto";
        } else {
            try {
                // Verifica che l'utente possa rispondere al ticket
                $stmt = db_query("
                    SELECT t.*, 
                           (SELECT COUNT(*) FROM ticket_destinatari WHERE ticket_id = t.id AND utente_id = ?) as is_destinatario
                    FROM tickets t
                    WHERE t.id = ?
                ", [$user['id'], $ticketId]);
                
                $ticket = $stmt->fetch();
                
                if (!$ticket) {
                    throw new Exception("Ticket non trovato");
                }
                
                // Controlla permessi: creatore, destinatario o super admin
                $canReply = ($ticket['utente_id'] == $user['id'] || 
                            $ticket['is_destinatario'] > 0 || 
                            $auth->isSuperAdmin());
                
                if (!$canReply) {
                    throw new Exception("Non hai i permessi per rispondere a questo ticket");
                }
                
                // Aggiungi la risposta
                db_insert('ticket_risposte', [
                    'ticket_id' => $ticketId,
                    'utente_id' => $user['id'],
                    'messaggio' => $messaggio
                ]);
                
                // Aggiorna stato ticket se chiuso
                if (isset($_POST['chiudi_ticket']) && $_POST['chiudi_ticket'] == '1') {
                    db_query("UPDATE tickets SET stato = 'chiuso' WHERE id = ?", [$ticketId]);
                    $logger->log('ticket_chiuso', "Chiuso ticket: {$ticket['codice']}");
                }
                
                // Usa il template professionale per inviare notifiche di risposta
                require_once 'backend/utils/EmailTemplateOutlook.php';
                
                // Notifica tutti i partecipanti con il template migliorato
                $stmt = db_query("
                    SELECT DISTINCT u.email, u.nome, u.cognome, u.id
                    FROM (
                        SELECT utente_id FROM tickets WHERE id = ?
                        UNION
                        SELECT utente_id FROM ticket_destinatari WHERE ticket_id = ?
                        UNION
                        SELECT utente_id FROM ticket_risposte WHERE ticket_id = ?
                    ) AS partecipanti
                    JOIN utenti u ON partecipanti.utente_id = u.id
                    WHERE u.id != ? AND u.attivo = 1
                ", [$ticketId, $ticketId, $ticketId, $user['id']]);
                
                $partecipanti = $stmt->fetchAll();
                
                // Genera il template email per la risposta
                $emailContent = EmailTemplateOutlook::ticketReply($ticket, $messaggio, $user);
                
                foreach ($partecipanti as $part) {
                    $mailer->send(
                        $part['email'],
                        "Nuova Risposta - Ticket {$ticket['codice']}",
                        $emailContent
                    );
                }
                
                // Notifica super admin con template professionale
                $stato_notifica = isset($_POST['chiudi_ticket']) ? 'ticket_status_changed' : 'ticket_risposta';
                $notificationManager = NotificationManager::getInstance();
                
                // Se il ticket è stato chiuso, usa il template di cambio stato
                if (isset($_POST['chiudi_ticket']) && $_POST['chiudi_ticket'] == '1') {
                    $emailContent = EmailTemplateOutlook::ticketStatusChanged(
                        $ticket,
                        $ticket['stato'],
                        'chiuso',
                        $user
                    );
                    $subject = "Ticket {$ticket['codice']} - Stato: Chiuso";
                } else {
                    // Altrimenti usa il template di risposta
                    $emailContent = EmailTemplateOutlook::ticketReply($ticket, $messaggio, $user);
                    $subject = "Nuova Risposta - Ticket {$ticket['codice']}";
                }
                
                $notificationManager->notificaSuperAdmin(
                    $stato_notifica,
                    $subject,
                    $emailContent
                );
                
                $_SESSION['success'] = "Risposta inviata con successo";
                redirect('tickets.php?action=view&id=' . $ticketId);
                
            } catch (Exception $e) {
                $_SESSION['error'] = "Errore nell'invio della risposta: " . $e->getMessage();
            }
        }
    }
}

// Gestione azioni
$action = $_GET['action'] ?? 'list';

$pageTitle = 'Gestione Ticket';
$bodyClass = 'tickets-page';
include 'components/header.php';
?>

<!-- Clean Dashboard Styles -->
<link rel="stylesheet" href="assets/css/dashboard-clean.css">

<style>
    /* Additional page-specific styles */
    .destinatari-group label {
        display: block !important;
        padding: 8px 10px !important;
        border-bottom: 1px solid #eee;
        margin: 0 !important;
    }
    
    .message-content {
        color: #374151;
        line-height: 1.6;
    }
    
    /* Custom badge colors for ticket states */
    .badge-aperto { background: #d1fae5; color: #047857; }
    .badge-in-lavorazione, .badge-in_lavorazione { background: #fef3c7; color: #92400e; }
    .badge-chiuso { background: #fee2e2; color: #b91c1c; }
    
    .badge-alta { background: #fee2e2; color: #b91c1c; }
    .badge-media { background: #fef3c7; color: #92400e; }
    .badge-bassa { background: #d1fae5; color: #047857; }
</style>

<div class="content-wrapper">
    <?php 
    // Mostra messaggi di sessione
    if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-auto-dismiss">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($_SESSION['success']); ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($_SESSION['error']); ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    
    <?php if ($action === 'list'): ?>
    <!-- Lista Ticket -->
    <div class="page-header">
        <h1><i class="fas fa-ticket-alt"></i> Gestione Ticket</h1>
        <div class="page-subtitle">Gestisci e monitora i ticket di supporto</div>
    </div>
    
    <div class="action-bar">
        <?php if ($isSuperAdmin && count($aziende_list) > 1): ?>
        <select class="form-control" style="max-width: 250px;" onchange="filterByAzienda(this.value)">
            <option value="">Tutte le aziende</option>
            <?php foreach ($aziende_list as $azienda): ?>
                <option value="<?php echo $azienda['id']; ?>" 
                        <?php echo $filter_azienda_id == $azienda['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($azienda['nome']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        
        <a href="tickets.php?action=nuovo" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nuovo Ticket
        </a>
    </div>
            
            <div class="filters-section">
                <div class="action-bar">
                    <select class="form-control" style="max-width: 200px;" onchange="filterTickets(this.value, 'stato')">
                        <option value="">Tutti gli stati</option>
                        <option value="aperto">Aperti</option>
                        <option value="in-lavorazione">In lavorazione</option>
                        <option value="chiuso">Chiusi</option>
                    </select>
                    
                    <select class="form-control" style="max-width: 200px;" onchange="filterTickets(this.value, 'priorita')">
                        <option value="">Tutte le priorità</option>
                        <option value="alta">Alta</option>
                        <option value="media">Media</option>
                        <option value="bassa">Bassa</option>
                    </select>
                </div>
            </div>
            
            <div class="content-card">
                <table class="table-clean">
                    <thead>
                        <tr>
                            <th>Codice</th>
                            <th>Oggetto</th>
                            <?php if ($auth->isSuperAdmin() && !$filter_azienda_id): ?>
                                <th>Azienda</th>
                            <?php endif; ?>
                            <th>Categoria</th>
                            <th>Priorità</th>
                            <th>Stato</th>
                            <th>Creato da</th>
                            <th>Data</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Query per i ticket
                        if ($auth->isSuperAdmin()) {
                            // Super admin vede tutti i ticket o filtrati per azienda
                            $sql = "
                                SELECT t.*, u.nome, u.cognome, a.nome as azienda_nome,
                                       (SELECT COUNT(*) FROM ticket_risposte WHERE ticket_id = t.id) as num_risposte,
                                       (SELECT COUNT(*) FROM ticket_destinatari WHERE ticket_id = t.id AND utente_id = :user_id_nonletti AND letto = 0) as non_letti
                                FROM tickets t
                                JOIN utenti u ON t.utente_id = u.id
                                LEFT JOIN aziende a ON t.azienda_id = a.id
                                WHERE 1=1";
                            
                            // Aggiungi filtro azienda se specificato
                            if ($filter_azienda_id) {
                                $sql .= " AND t.azienda_id = :filter_azienda_id";
                            }
                            
                            $sql .= " ORDER BY 
                                    CASE t.stato 
                                        WHEN 'aperto' THEN 1 
                                        WHEN 'in-lavorazione' THEN 2 
                                        ELSE 3 
                                    END,
                                    CASE t.priorita 
                                        WHEN 'alta' THEN 1 
                                        WHEN 'media' THEN 2 
                                        ELSE 3 
                                    END,
                                    t.creato_il DESC
                            ";
                            
                            $params = [':user_id_nonletti' => $user['id']];
                            if ($filter_azienda_id) {
                                $params[':filter_azienda_id'] = $filter_azienda_id;
                            }
                            
                            $stmt = db_query($sql, $params);
                        } else {
                            // Utenti normali vedono solo i ticket della loro azienda o dove sono destinatari
                            $sql = "
                                SELECT t.*, u.nome, u.cognome,
                                       (SELECT COUNT(*) FROM ticket_risposte WHERE ticket_id = t.id) as num_risposte,
                                       (SELECT COUNT(*) FROM ticket_destinatari WHERE ticket_id = t.id AND utente_id = :user_id_nonletti AND letto = 0) as non_letti
                                FROM tickets t
                                JOIN utenti u ON t.utente_id = u.id
                                WHERE t.azienda_id = :azienda
                                    OR t.utente_id = :user
                                    OR t.id IN (SELECT ticket_id FROM ticket_destinatari WHERE utente_id = :user_dest)
                                ORDER BY 
                                    CASE t.stato 
                                        WHEN 'aperto' THEN 1 
                                        WHEN 'in-lavorazione' THEN 2 
                                        ELSE 3 
                                    END,
                                    CASE t.priorita 
                                        WHEN 'alta' THEN 1 
                                        WHEN 'media' THEN 2 
                                        ELSE 3 
                                    END,
                                    t.creato_il DESC
                            ";
                            
                            $stmt = db_query($sql, [
                                ':azienda' => $aziendaId,
                                ':user' => $user['id'],
                                ':user_dest' => $user['id'],
                                ':user_id_nonletti' => $user['id']
                            ]);
                        }
                        $tickets = $stmt->fetchAll();
                        
                        foreach ($tickets as $ticket):
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($ticket['codice']); ?></strong>
                                <?php if ($ticket['non_letti'] > 0): ?>
                                    <span class="badge badge-alta"><?php echo $ticket['non_letti']; ?> nuovo</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($ticket['oggetto'] ?? ''); ?></td>
                            <?php if ($auth->isSuperAdmin() && !$filter_azienda_id): ?>
                                <td><?php echo htmlspecialchars($ticket['azienda_nome'] ?? 'N/A'); ?></td>
                            <?php endif; ?>
                            <td><?php echo ucfirst($ticket['categoria'] ?? ''); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $ticket['priorita']; ?>">
                                    <?php echo ucfirst($ticket['priorita']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo str_replace('-', '_', $ticket['stato']); ?>">
                                    <?php echo ucfirst(str_replace('-', ' ', $ticket['stato'])); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($ticket['nome'] . ' ' . $ticket['cognome']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($ticket['creato_il'])); ?></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="tickets.php?action=view&id=<?php echo $ticket['id']; ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i> Visualizza
                                        <?php if ($ticket['num_risposte'] > 0): ?>
                                            (<?php echo $ticket['num_risposte']; ?>)
                                        <?php endif; ?>
                                    </a>
                                    <?php if ($auth->isSuperAdmin() && $ticket['stato'] === 'chiuso'): ?>
                                    <button type="button" 
                                            class="btn btn-sm btn-danger delete-ticket-btn"
                                            data-ticket-id="<?php echo $ticket['id']; ?>"
                                            data-ticket-code="<?php echo htmlspecialchars($ticket['codice']); ?>"
                                            data-ticket-status="<?php echo $ticket['stato']; ?>"
                                            title="Elimina ticket">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($tickets)): ?>
                        <tr>
                            <td colspan="<?php echo ($auth->isSuperAdmin() && !$filter_azienda_id) ? '9' : '8'; ?>">
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <h3>Nessun ticket trovato</h3>
                                    <p>Non ci sono ticket da visualizzare al momento.</p>
                                    <a href="tickets.php?action=nuovo" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Crea il primo ticket
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
    <?php elseif ($action === 'nuovo'): ?>
    <!-- Nuovo Ticket -->
    <div class="page-header">
        <h1><i class="fas fa-plus-circle"></i> Nuovo Ticket</h1>
        <div class="page-subtitle">Crea un nuovo ticket di supporto</div>
    </div>
            
            <div class="content-card">
                <form method="post" action="">
                    <input type="hidden" name="action" value="nuovo">
                    
                    <?php if ($auth->isSuperAdmin()): ?>
                    <div class="form-group">
                        <label for="azienda_id">Azienda per cui aprire il ticket <span class="required">*</span></label>
                        <select id="azienda_id" name="azienda_id" class="form-control" required onchange="updateDestinatari(this.value)">
                            <option value="">-- Seleziona un'azienda --</option>
                            <?php
                            $selectedAziendaId = $_GET['azienda_id'] ?? $aziendaId;
                            $stmt = db_query("SELECT id, nome FROM aziende WHERE stato = 'attiva' ORDER BY nome");
                            $aziende = $stmt->fetchAll();
                            foreach ($aziende as $azienda): ?>
                                <option value="<?php echo $azienda['id']; ?>" <?php echo ($selectedAziendaId == $azienda['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($azienda['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="oggetto">Oggetto <span class="required">*</span></label>
                        <input type="text" id="oggetto" name="oggetto" class="form-control" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="categoria">Categoria</label>
                            <select id="categoria" name="categoria" class="form-control">
                                <option value="tecnico">Tecnico</option>
                                <option value="amministrativo">Amministrativo</option>
                                <option value="commerciale">Commerciale</option>
                                <option value="altro">Altro</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="priorita">Priorità</label>
                            <select id="priorita" name="priorita" class="form-control">
                                <option value="bassa">Bassa</option>
                                <option value="media" selected>Media</option>
                                <option value="alta">Alta</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Destinatari (lascia vuoto per inviare a tutti gli amministratori)</label>
                        <div id="destinatari-container" class="destinatari-group">
                            <?php
                            // Determina quale azienda usare per caricare i destinatari
                            $destinatariAziendaId = $aziendaId;
                            
                            // Se siamo super admin e abbiamo un'azienda nei parametri GET (per il refresh)
                            if ($auth->isSuperAdmin() && isset($_GET['azienda_id'])) {
                                $destinatariAziendaId = intval($_GET['azienda_id']);
                            }
                            
                            if ($destinatariAziendaId) {
                                // Trova amministratori dell'azienda E super admin globali
                                $stmt = db_query("
                                    SELECT DISTINCT u.id, u.nome, u.cognome, u.email, u.ruolo,
                                           COALESCE(ua.ruolo_azienda, 'super_admin') as ruolo_azienda,
                                           CASE 
                                               WHEN u.ruolo = 'super_admin' THEN 'Super Admin Globale'
                                               WHEN ua.ruolo_azienda = 'proprietario' THEN 'Proprietario'
                                               WHEN ua.ruolo_azienda = 'admin' THEN 'Amministratore'
                                               ELSE 'Utente'
                                           END as ruolo_display
                                    FROM utenti u
                                    LEFT JOIN utenti_aziende ua ON u.id = ua.utente_id AND ua.azienda_id = ?
                                    WHERE u.attivo = 1
                                    AND u.id != ?
                                    AND (
                                        -- Super admin globali
                                        u.ruolo = 'super_admin'
                                        OR
                                        -- Admin dell'azienda specifica
                                        (ua.azienda_id = ? AND ua.ruolo_azienda IN ('proprietario', 'admin'))
                                    )
                                    ORDER BY 
                                        CASE 
                                            WHEN u.ruolo = 'super_admin' THEN 0
                                            WHEN ua.ruolo_azienda = 'proprietario' THEN 1 
                                            WHEN ua.ruolo_azienda = 'admin' THEN 2 
                                            ELSE 3 
                                        END,
                                        u.nome, u.cognome
                                ", [$destinatariAziendaId, $user['id'], $destinatariAziendaId]);
                                
                                $amministratori = $stmt->fetchAll();
                                
                                foreach ($amministratori as $admin):
                                ?>
                                <label style="display: block; padding: 8px 0; border-bottom: 1px solid #eee;">
                                    <input type="checkbox" name="destinatari[]" value="<?php echo $admin['id']; ?>">
                                    <?php echo htmlspecialchars($admin['nome'] . ' ' . $admin['cognome']); ?>
                                    <small>(<?php echo htmlspecialchars($admin['email']); ?>)</small>
                                    <?php if ($admin['ruolo'] === 'super_admin'): ?>
                                        <span class="badge badge-alta" style="background: #2d5a9f; color: white;">Super Admin Globale</span>
                                    <?php elseif ($admin['ruolo_azienda'] === 'proprietario'): ?>
                                        <span class="badge badge-alta">Proprietario</span>
                                    <?php elseif ($admin['ruolo_azienda'] === 'admin'): ?>
                                        <span class="badge badge-media">Admin</span>
                                    <?php endif; ?>
                                </label>
                                <?php endforeach;
                            } else if ($auth->isSuperAdmin()) {
                                echo '<p class="text-muted">Seleziona prima un\'azienda per vedere i destinatari disponibili.</p>';
                            }
                            ?>
                        </div>
                        <small class="text-muted">
                            Seleziona uno o più destinatari. Se non selezioni nessuno, 
                            il ticket verrà inviato a tutti gli amministratori.
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="descrizione">Descrizione <span class="required">*</span></label>
                        <textarea id="descrizione" name="descrizione" class="form-control" rows="6" required></textarea>
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Invia Ticket
                        </button>
                        <a href="tickets.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Annulla
                        </a>
                    </div>
                </form>
            </div>
            
            <?php elseif ($action === 'view' && isset($_GET['id'])): ?>
            <!-- Visualizza Ticket -->
            <?php
            $ticketId = intval($_GET['id']);
            
            // Marca come letto per l'utente corrente
            db_query("
                UPDATE ticket_destinatari 
                SET letto = 1, data_lettura = NOW() 
                WHERE ticket_id = ? AND utente_id = ?
            ", [$ticketId, $user['id']]);
            
            // Carica ticket con dettagli
            $stmt = db_query("
                SELECT t.*, u.nome as creatore_nome, u.cognome as creatore_cognome,
                       a.nome as azienda_nome,
                       (SELECT COUNT(*) FROM ticket_destinatari WHERE ticket_id = t.id AND utente_id = ?) as is_destinatario
                FROM tickets t
                JOIN utenti u ON t.utente_id = u.id
                LEFT JOIN aziende a ON t.azienda_id = a.id
                WHERE t.id = ?
            ", [$user['id'], $ticketId]);
            
            $ticket = $stmt->fetch();
            
            if (!$ticket) {
                $_SESSION['error'] = "Ticket non trovato";
                redirect('tickets.php');
            }
            
            // Verifica permessi di visualizzazione
            $canView = ($ticket['utente_id'] == $user['id'] || 
                       $ticket['is_destinatario'] > 0 || 
                       $auth->isSuperAdmin() ||
                       ($ticket['azienda_id'] == $aziendaId && $auth->hasRoleInAzienda('admin')));
            
            if (!$canView) {
                $_SESSION['error'] = "Non hai i permessi per visualizzare questo ticket";
                redirect('tickets.php');
            }
            
            // Carica destinatari
            $stmt = db_query("
                SELECT u.nome, u.cognome, td.tipo_destinatario, td.letto, td.data_lettura
                FROM ticket_destinatari td
                JOIN utenti u ON td.utente_id = u.id
                WHERE td.ticket_id = ?
                ORDER BY td.tipo_destinatario, u.nome, u.cognome
            ", [$ticketId]);
            
            $destinatari = $stmt->fetchAll();
            
            // Carica conversazione
            $stmt = db_query("
                SELECT tr.*, u.nome, u.cognome
                FROM ticket_risposte tr
                JOIN utenti u ON tr.utente_id = u.id
                WHERE tr.ticket_id = ?
                ORDER BY tr.data_creazione ASC
            ", [$ticketId]);
            
            $risposte = $stmt->fetchAll();
            ?>
            
    <div class="page-header">
        <h1>
            <i class="fas fa-ticket-alt"></i> 
            <?php echo htmlspecialchars($ticket['codice']); ?> - 
            <?php echo htmlspecialchars($ticket['oggetto'] ?? ''); ?>
        </h1>
        <div class="page-subtitle">Dettagli e conversazione del ticket</div>
    </div>
    
    <div class="action-bar">
        <a href="tickets.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Torna alla lista
        </a>
    </div>
            
            <div class="content-card">
                <div class="ticket-info">
                    <div>
                        <strong>Stato:</strong><br>
                        <span class="badge badge-<?php echo str_replace('-', '_', $ticket['stato']); ?>">
                            <?php echo ucfirst(str_replace('-', ' ', $ticket['stato'])); ?>
                        </span>
                    </div>
                    <div>
                        <strong>Priorità:</strong><br>
                        <span class="badge badge-<?php echo $ticket['priorita']; ?>">
                            <?php echo ucfirst($ticket['priorita']); ?>
                        </span>
                    </div>
                    <div>
                        <strong>Categoria:</strong><br>
                        <?php echo ucfirst($ticket['categoria']); ?>
                    </div>
                    <div>
                        <strong>Azienda:</strong><br>
                        <?php echo htmlspecialchars($ticket['azienda_nome']); ?>
                    </div>
                    <div>
                        <strong>Creato da:</strong><br>
                        <?php echo htmlspecialchars($ticket['creatore_nome'] . ' ' . $ticket['creatore_cognome']); ?>
                    </div>
                    <div>
                        <strong>Data creazione:</strong><br>
                        <?php echo date('d/m/Y H:i', strtotime($ticket['creato_il'])); ?>
                    </div>
                </div>
                
                <?php if (!empty($destinatari)): ?>
                <div style="margin-bottom: 20px;">
                    <strong>Destinatari:</strong>
                    <?php foreach ($destinatari as $dest): ?>
                        <span style="margin-left: 10px;">
                            <?php echo htmlspecialchars($dest['nome'] . ' ' . $dest['cognome']); ?>
                            <?php if (isset($dest['tipo_destinatario']) && $dest['tipo_destinatario'] === 'assegnato'): ?>
                                <span class="badge badge-alta">Assegnato</span>
                            <?php elseif (isset($dest['tipo_destinatario']) && $dest['tipo_destinatario'] === 'cc'): ?>
                                <span class="badge badge-media">CC</span>
                            <?php endif; ?>
                            <?php if ($dest['letto']): ?>
                                <i class="fas fa-check-circle text-success" 
                                   title="Letto il <?php echo date('d/m/Y H:i', strtotime($dest['data_lettura'])); ?>">
                                </i>
                            <?php endif; ?>
                        </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div class="content-card" style="background: #f9fafb; border-left: 4px solid #2d5a9f;">
                    <div class="message-header">
                        <div class="message-author">
                            <?php echo htmlspecialchars($ticket['creatore_nome'] . ' ' . $ticket['creatore_cognome']); ?>
                        </div>
                        <div>
                            <?php echo date('d/m/Y H:i', strtotime($ticket['creato_il'])); ?>
                        </div>
                    </div>
                    <div class="message-content">
                        <?php echo nl2br(htmlspecialchars($ticket['descrizione'])); ?>
                    </div>
                </div>
                
                <?php if (!empty($risposte)): ?>
                <div class="ticket-messages">
                    <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 1rem;">Conversazione</h3>
                    <?php foreach ($risposte as $risposta): ?>
                    <div class="content-card" style="background: #f9fafb; border-left: 4px solid #2d5a9f;">
                        <div class="message-header">
                            <div class="message-author">
                                <?php echo htmlspecialchars($risposta['nome'] . ' ' . $risposta['cognome']); ?>
                            </div>
                            <div>
                                <?php echo date('d/m/Y H:i', strtotime($risposta['data_creazione'])); ?>
                            </div>
                        </div>
                        <div class="message-content">
                            <?php echo nl2br(htmlspecialchars($risposta['messaggio'])); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <?php 
                // Verifica se l'utente può rispondere
                $canReply = ($ticket['utente_id'] == $user['id'] || 
                            $ticket['is_destinatario'] > 0 || 
                            $auth->isSuperAdmin());
                
                if ($ticket['stato'] !== 'chiuso' && $canReply): 
                ?>
                <div class="content-card" style="background: #f9fafb;">
                    <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 1rem;">Rispondi al ticket</h3>
                    <form method="post" action="">
                        <input type="hidden" name="action" value="risposta">
                        <input type="hidden" name="ticket_id" value="<?php echo $ticketId; ?>">
                        
                        <div class="form-group">
                            <textarea name="messaggio" class="form-control" rows="6" 
                                      placeholder="Scrivi la tua risposta..." required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label style="font-weight: 500; cursor: pointer;">
                                <input type="checkbox" name="chiudi_ticket" value="1" style="margin-right: 8px;">
                                Chiudi il ticket dopo questa risposta
                            </label>
                        </div>
                        
                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-reply"></i> Invia Risposta
                            </button>
                            <?php if ($auth->isSuperAdmin() || $ticket['utente_id'] == $user['id']): ?>
                            <button type="button" class="btn btn-secondary" onclick="cambiaStato('in-lavorazione')">
                                <i class="fas fa-cog"></i> In Lavorazione
                            </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                <?php elseif ($ticket['stato'] === 'chiuso'): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 
                    Questo ticket è stato chiuso. Non è più possibile rispondere.
                    <?php if ($auth->isSuperAdmin()): ?>
                    <div class="mt-3">
                        <button type="button" 
                                class="btn btn-danger delete-ticket-btn"
                                data-ticket-id="<?php echo $ticket['id']; ?>"
                                data-ticket-code="<?php echo htmlspecialchars($ticket['codice']); ?>"
                                data-ticket-status="<?php echo $ticket['stato']; ?>">
                            <i class="fas fa-trash"></i> Elimina Ticket Chiuso
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php elseif (!$canReply): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> 
                    Non hai i permessi per rispondere a questo ticket.
                </div>
                <?php endif; ?>
            </div>
    <?php endif; ?>
</div>

<script>
    function filterTickets(value, type) {
        // Implementare filtri lato client se necessario
        console.log('Filter:', type, value);
    }
    
    // Auto-remove alert dopo animazione
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert-auto-dismiss');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.remove();
            }, 5000);
        });
    });
    
    // Funzione per aggiornare i destinatari quando cambia l'azienda (solo per super admin)
    function updateDestinatari(aziendaId) {
        const container = document.getElementById('destinatari-container');
        
        if (!aziendaId) {
            container.innerHTML = '<p class="text-muted">Seleziona prima un\'azienda per vedere i destinatari disponibili.</p>';
            return;
        }
        
        container.innerHTML = '<p class="text-muted">Caricamento destinatari...</p>';
        
        // Ricarica la pagina con l'azienda selezionata per aggiornare i destinatari
        window.location.href = 'tickets.php?action=nuovo&azienda_id=' + aziendaId;
    }
    
    // Funzione per filtrare per azienda (solo super admin)
    function filterByAzienda(azienda_id) {
        const url = new URL(window.location.href);
        if (azienda_id) {
            url.searchParams.set('azienda_filter', azienda_id);
        } else {
            url.searchParams.delete('azienda_filter');
        }
        window.location.href = url.toString();
    }
</script>

<!-- Include ticket enhancements JavaScript -->
<script src="assets/js/tickets-enhancements.js"></script>

<?php include 'components/footer.php'; ?> 