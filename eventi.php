<?php
require_once 'backend/config/config.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$user = $auth->getUser();
// Database instance handled by functions
$currentAzienda = $auth->getCurrentAzienda();

// Se non c'è un'azienda selezionata e non è super admin, reindirizza
if (!$currentAzienda && !$auth->isSuperAdmin()) {
    redirect(APP_PATH . '/seleziona-azienda.php');
}

$action = $_GET['action'] ?? 'list';
$id = intval($_GET['id'] ?? 0);

$message = '';
$error = '';

// Gestisci le diverse azioni
switch ($action) {
    case 'nuovo':
        // Verifica permessi con nuovo sistema
        if (!$auth->canManageEvents()) {
            redirect(APP_PATH . '/calendario.php');
        }
        
        // Carica EventInvite per gestire gli inviti
        require_once 'backend/utils/EventInvite.php';
        $eventInvite = new EventInvite();
        
        // Ottieni l'ID dell'azienda corrente
        $aziendaId = null;
        if ($currentAzienda) {
            $aziendaId = isset($currentAzienda['azienda_id']) ? $currentAzienda['azienda_id'] : 
                         (isset($currentAzienda['id']) ? $currentAzienda['id'] : null);
        }
        
        // Carica lista utenti invitabili in base ai permessi
        $utenti_disponibili = $eventInvite->getInvitableUsers($user['id'], $aziendaId);
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $db->getConnection()->beginTransaction();
                
                // Valida i dati
                $titolo = sanitize_input($_POST['titolo'] ?? '');
                $descrizione = sanitize_input($_POST['descrizione'] ?? '');
                $data_inizio = $_POST['data_inizio'] ?? '';
                $data_fine = $_POST['data_fine'] ?? '';
                $luogo = sanitize_input($_POST['luogo'] ?? '');
                $tipo = sanitize_input($_POST['tipo'] ?? 'riunione');
                
                if (empty($titolo) || empty($data_inizio) || empty($data_fine)) {
                    throw new Exception('Compila tutti i campi obbligatori');
                }
                
                // Verifica che la data fine sia dopo la data inizio
                if (strtotime($data_fine) < strtotime($data_inizio)) {
                    throw new Exception('La data di fine deve essere dopo la data di inizio');
                }
                
                // Inserisci evento con azienda_id
                $stmt = $db->getConnection()->prepare("
                    INSERT INTO eventi (azienda_id, titolo, descrizione, data_inizio, data_fine, luogo, tipo, creato_da, data_creazione)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $aziendaId,
                    $titolo, 
                    $descrizione, 
                    $data_inizio, 
                    $data_fine, 
                    $luogo, 
                    $tipo, 
                    $user['id']
                ]);
                
                $evento_id = $db->getConnection()->lastInsertId();
                
                // Aggiungi il creatore come partecipante
                $stmt = $db->getConnection()->prepare("
                    INSERT INTO evento_partecipanti (evento_id, utente_id, stato_partecipazione, creato_il)
                    VALUES (?, ?, 'accettato', NOW())
                ");
                $stmt->execute([$evento_id, $user['id']]);
                
                // Aggiungi altri partecipanti se selezionati
                $partecipanti_ids = $_POST['partecipanti'] ?? [];
                $partecipanti_validi = [];
                
                if (!empty($partecipanti_ids)) {
                    $stmt = $db->getConnection()->prepare("
                        INSERT INTO evento_partecipanti (evento_id, utente_id, stato_partecipazione, creato_il)
                        VALUES (?, ?, 'invitato', NOW())
                    ");
                    
                    foreach ($partecipanti_ids as $partecipante_id) {
                        if ($partecipante_id != $user['id']) { // Evita duplicati con il creatore
                            // Verifica che l'utente possa invitare questo partecipante
                            if ($eventInvite->canInviteUser($user['id'], $partecipante_id, $aziendaId)) {
                                $stmt->execute([$evento_id, $partecipante_id]);
                                $partecipanti_validi[] = $partecipante_id;
                            }
                        }
                    }
                }
                
                $db->getConnection()->commit();
                
                // Invia inviti con iCal
                if (!empty($partecipanti_validi)) {
                    try {
                        $eventInvite->sendInvitations($evento_id, $partecipanti_validi);
                    } catch (Exception $e) {
                        // Log errore ma non bloccare il processo
                        error_log('Errore invio inviti evento: ' . $e->getMessage());
                    }
                }
                
                // Notifica super admin
                $notificationManager = NotificationManager::getInstance();
                $notificationManager->notificaSuperAdmin(
                    'evento_creato',
                    "Nuovo evento: $titolo",
                    "
                    <h3>Nuovo evento creato</h3>
                    <p><strong>Titolo:</strong> $titolo</p>
                    <p><strong>Data inizio:</strong> $data_inizio</p>
                    <p><strong>Data fine:</strong> $data_fine</p>
                    <p><strong>Descrizione:</strong> $descrizione</p>
                    <p><strong>Creato da:</strong> {$user['nome']} {$user['cognome']}</p>
                    "
                );
                
                redirect(APP_PATH . '/calendario.php?msg=evento_creato');
                
            } catch (Exception $e) {
                $db->getConnection()->rollback();
                $error = $e->getMessage();
            }
        }
        break;
        
    case 'edit':
        // Carica evento esistente
        $sql = "SELECT * FROM eventi WHERE id = ?";
        $params = [$id];
        
        // Se ha un'azienda corrente, filtra per azienda
        if ($currentAzienda && !$auth->isSuperAdmin()) {
            $sql .= " AND azienda_id = ?";
            $aziendaId = isset($currentAzienda['azienda_id']) ? $currentAzienda['azienda_id'] : 
                         (isset($currentAzienda['id']) ? $currentAzienda['id'] : null);
            $params[] = $aziendaId;
        }
        
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->execute($params);
        $evento = $stmt->fetch();
        
        if (!$evento) {
            redirect(APP_PATH . '/calendario.php');
        }
        
        // Verifica permessi
        if (!$auth->canManageEvents() && $evento['creato_da'] != $user['id']) {
            redirect(APP_PATH . '/calendario.php');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                // Valida i dati
                $titolo = sanitize_input($_POST['titolo'] ?? '');
                $descrizione = sanitize_input($_POST['descrizione'] ?? '');
                $data_inizio = $_POST['data_inizio'] ?? '';
                $data_fine = $_POST['data_fine'] ?? '';
                $luogo = sanitize_input($_POST['luogo'] ?? '');
                $tipo = sanitize_input($_POST['tipo'] ?? 'riunione');
                
                if (empty($titolo) || empty($data_inizio) || empty($data_fine)) {
                    throw new Exception('Compila tutti i campi obbligatori');
                }
                
                // Verifica che la data fine sia dopo la data inizio
                if (strtotime($data_fine) < strtotime($data_inizio)) {
                    throw new Exception('La data di fine deve essere dopo la data di inizio');
                }
                
                // Aggiorna evento
                $stmt = $db->getConnection()->prepare("
                    UPDATE eventi 
                    SET titolo = ?, descrizione = ?, data_inizio = ?, data_fine = ?, luogo = ?, tipo = ?
                    WHERE id = ?
                ");
                $stmt->execute([$titolo, $descrizione, $data_inizio, $data_fine, $luogo, $tipo, $id]);
                
                // Invia notifiche email se abilitato
                try {
                    require_once 'backend/utils/Mailer.php';
                    $mailer = Mailer::getInstance();
                    
                    if ($mailer->isNotificationEnabled('event_modified')) {
                        // Recupera i partecipanti con email
                        $stmt = $db->getConnection()->prepare("
                            SELECT u.id, u.email, u.nome, u.cognome 
                            FROM evento_partecipanti p
                            JOIN utenti u ON p.utente_id = u.id
                            WHERE p.evento_id = ? AND u.id != ?
                        ");
                        $stmt->execute([$id, $user['id']]);
                        $partecipanti_email = $stmt->fetchAll();
                        
                        // Recupera evento aggiornato
                        $stmt = $db->getConnection()->prepare("SELECT * FROM eventi WHERE id = ?");
                        $stmt->execute([$id]);
                        $evento_aggiornato = $stmt->fetch();
                        
                        // Invia email ai partecipanti
                        $mailer->sendEventModifiedNotification($evento_aggiornato, $partecipanti_email);
                    }
                } catch (Exception $e) {
                    // Log errore ma non bloccare il processo
                    error_log('Errore invio notifiche evento modificato: ' . $e->getMessage());
                }
                
                // Notifica super admin
                $notificationManager = NotificationManager::getInstance();
                $notificationManager->notificaSuperAdmin(
                    'evento_modificato',
                    "Evento modificato: $titolo",
                    "
                    <h3>Evento modificato</h3>
                    <p><strong>Titolo:</strong> $titolo</p>
                    <p><strong>Data inizio:</strong> $data_inizio</p>
                    <p><strong>Data fine:</strong> $data_fine</p>
                    <p><strong>Descrizione:</strong> $descrizione</p>
                    <p><strong>Modificato da:</strong> {$user['nome']} {$user['cognome']}</p>
                    "
                );
                
                redirect(APP_PATH . '/calendario.php?msg=evento_aggiornato');
                
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
        break;
        
    case 'partecipa':
        try {
            // Verifica che l'evento appartenga all'azienda corrente
            $sql = "SELECT * FROM eventi WHERE id = ?";
            $params = [$id];
            
            if ($currentAzienda && !$auth->isSuperAdmin()) {
                $sql .= " AND azienda_id = ?";
                $aziendaId = isset($currentAzienda['azienda_id']) ? $currentAzienda['azienda_id'] : 
                             (isset($currentAzienda['id']) ? $currentAzienda['id'] : null);
                $params[] = $aziendaId;
            }
            
            $stmt = $db->getConnection()->prepare($sql);
            $stmt->execute($params);
            
            if (!$stmt->fetch()) {
                throw new Exception('Evento non trovato');
            }
            
            // Verifica se l'utente è già iscritto
            $stmt = $db->getConnection()->prepare("SELECT * FROM evento_partecipanti WHERE evento_id = ? AND utente_id = ?");
            $stmt->execute([$id, $user['id']]);
            
            if ($stmt->fetch()) {
                throw new Exception('Sei già iscritto a questo evento');
            }
            
            // Aggiungi partecipante
            $stmt = $db->getConnection()->prepare("
                INSERT INTO evento_partecipanti (evento_id, utente_id, stato_partecipazione, creato_il)
                VALUES (?, ?, 'accettato', NOW())
            ");
            $stmt->execute([$id, $user['id']]);
            
            redirect(APP_PATH . '/calendario.php?msg=iscrizione_confermata');
            
        } catch (Exception $e) {
            redirect(APP_PATH . '/calendario.php?error=' . urlencode($e->getMessage()));
        }
        break;
        
    case 'view':
        // Carica dettagli evento
        $sql = "
            SELECT e.*, u.nome as nome_creatore, u.cognome as cognome_creatore
            FROM eventi e
            LEFT JOIN utenti u ON e.creato_da = u.id
            WHERE e.id = ?
        ";
        $params = [$id];
        
        // Se ha un'azienda corrente, filtra per azienda
        if ($currentAzienda && !$auth->isSuperAdmin()) {
            $sql .= " AND e.azienda_id = ?";
            $aziendaId = isset($currentAzienda['azienda_id']) ? $currentAzienda['azienda_id'] : 
                         (isset($currentAzienda['id']) ? $currentAzienda['id'] : null);
            $params[] = $aziendaId;
        }
        
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->execute($params);
        $evento = $stmt->fetch();
        
        if (!$evento) {
            redirect(APP_PATH . '/calendario.php');
        }
        
        // Carica partecipanti
        $stmt = $db->getConnection()->prepare("
            SELECT u.nome, u.cognome, u.email, p.stato_partecipazione as stato, p.creato_il as data_invito
            FROM evento_partecipanti p
            JOIN utenti u ON p.utente_id = u.id
            WHERE p.evento_id = ?
            ORDER BY p.creato_il
        ");
        $stmt->execute([$id]);
        $partecipanti = $stmt->fetchAll();
        break;
        
    case 'delete':
        // Solo i super admin possono eliminare eventi
        if (!$auth->isSuperAdmin()) {
            redirect(APP_PATH . '/calendario.php?error=' . urlencode('Solo i super admin possono eliminare eventi'));
        }
        
        // Verifica che sia una richiesta POST per sicurezza
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            // Se non è POST, mostra una pagina di conferma
            $stmt = $db->getConnection()->prepare("SELECT * FROM eventi WHERE id = ?");
            $stmt->execute([$id]);
            $evento = $stmt->fetch();
            
            if (!$evento) {
                redirect(APP_PATH . '/calendario.php?error=' . urlencode('Evento non trovato'));
            }
            
            $pageTitle = 'Conferma Eliminazione Evento';
            require_once 'components/header.php';
            ?>
            <div class="content-header">
                <h1><i class="fas fa-trash"></i> Conferma Eliminazione Evento</h1>
            </div>
            
            <div class="content-body">
                <div class="alert alert-warning">
                    <h3>Sei sicuro di voler eliminare questo evento?</h3>
                    <p><strong>Titolo:</strong> <?php echo htmlspecialchars($evento['titolo']); ?></p>
                    <p><strong>Data:</strong> <?php echo format_datetime($evento['data_inizio']); ?></p>
                    <p>Questa azione non può essere annullata e tutti i partecipanti riceveranno una notifica di cancellazione.</p>
                </div>
                
                <form method="POST" action="<?php echo APP_PATH; ?>/eventi.php?action=delete&id=<?php echo $id; ?>" style="display: inline;">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Sì, elimina l'evento
                    </button>
                </form>
                
                <a href="<?php echo APP_PATH; ?>/calendario.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Annulla
                </a>
            </div>
            <?php
            require_once 'components/footer.php';
            exit;
        }
        
        // Procedi con l'eliminazione
        try {
            $db->getConnection()->beginTransaction();
            
            // Carica evento esistente con i partecipanti
            $stmt = $db->getConnection()->prepare("
                SELECT e.*, u.nome as nome_creatore, u.cognome as cognome_creatore
                FROM eventi e
                LEFT JOIN utenti u ON e.creato_da = u.id
                WHERE e.id = ?
            ");
            $stmt->execute([$id]);
            $evento = $stmt->fetch();
            
            if (!$evento) {
                throw new Exception('Evento non trovato');
            }
            
            // Recupera partecipanti per invio notifiche
            $stmt = $db->getConnection()->prepare("
                SELECT u.id, u.nome, u.cognome, u.email, p.stato_partecipazione as stato
                FROM evento_partecipanti p
                JOIN utenti u ON p.utente_id = u.id
                WHERE p.evento_id = ?
            ");
            $stmt->execute([$id]);
            $partecipanti_notifica = $stmt->fetchAll();
            
            // Elimina prima i partecipanti
            $stmt = $db->getConnection()->prepare("DELETE FROM evento_partecipanti WHERE evento_id = ?");
            $stmt->execute([$id]);
            
            // Poi elimina l'evento
            $stmt = $db->getConnection()->prepare("DELETE FROM eventi WHERE id = ?");
            $stmt->execute([$id]);
            
            $db->getConnection()->commit();
            
            // Invia notifiche email ai partecipanti
            if (!empty($partecipanti_notifica)) {
                try {
                    require_once 'backend/utils/Mailer.php';
                    $mailer = Mailer::getInstance();
                    
                    $subject = "Evento Cancellato: " . $evento['titolo'];
                    $messageBody = "
                        <h2>Evento Cancellato</h2>
                        <p>L'evento <strong>" . htmlspecialchars($evento['titolo']) . "</strong> è stato cancellato.</p>
                        
                        <div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                            <h3>Dettagli evento cancellato:</h3>
                            <p><strong>Titolo:</strong> " . htmlspecialchars($evento['titolo']) . "</p>
                            <p><strong>Data inizio:</strong> " . format_datetime($evento['data_inizio']) . "</p>
                            <p><strong>Data fine:</strong> " . format_datetime($evento['data_fine']) . "</p>
                            " . ($evento['luogo'] ? "<p><strong>Luogo:</strong> " . htmlspecialchars($evento['luogo']) . "</p>" : "") . "
                            " . ($evento['descrizione'] ? "<p><strong>Descrizione:</strong> " . nl2br(htmlspecialchars($evento['descrizione'])) . "</p>" : "") . "
                        </div>
                        
                        <p>Ci scusiamo per l'inconveniente.</p>
                        <p><em>Cancellato da: " . htmlspecialchars($user['nome'] . ' ' . $user['cognome']) . "</em></p>
                    ";
                    
                    foreach ($partecipanti_notifica as $partecipante) {
                        $personalizedMessage = "
                            <p>Ciao " . htmlspecialchars($partecipante['nome']) . ",</p>
                            " . $messageBody . "
                        ";
                        
                        $mailer->sendEmail(
                            $partecipante['email'],
                            $partecipante['nome'] . ' ' . $partecipante['cognome'],
                            $subject,
                            $personalizedMessage
                        );
                    }
                    
                    $message = 'Evento eliminato con successo. Notifiche inviate a ' . count($partecipanti_notifica) . ' partecipanti.';
                    
                } catch (Exception $e) {
                    error_log('Errore invio notifiche cancellazione evento: ' . $e->getMessage());
                    $message = 'Evento eliminato ma si è verificato un errore nell\'invio delle notifiche.';
                }
            } else {
                $message = 'Evento eliminato con successo.';
            }
            
            redirect(APP_PATH . '/calendario.php?msg=' . urlencode($message));
            
        } catch (Exception $e) {
            $db->getConnection()->rollback();
            redirect(APP_PATH . '/calendario.php?error=' . urlencode('Errore durante l\'eliminazione: ' . $e->getMessage()));
        }
        break;
}

$pageTitle = $action == 'nuovo' ? 'Nuovo Evento' : 
            ($action == 'edit' ? 'Modifica Evento' : 
            ($action == 'delete' ? 'Elimina Evento' : 'Dettagli Evento'));
require_once 'components/header.php';
?>

<style>
    .form-container {
        background: white;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        max-width: 800px;
        margin: 0 auto;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        color: #333;
    }
    
    .form-group input,
    .form-group textarea,
    .form-group select {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 14px;
    }
    
    .form-group textarea {
        min-height: 100px;
        resize: vertical;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    
    .required {
        color: #e74c3c;
    }
    
    .event-details {
        background: white;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    
    .info-item {
        padding: 15px;
        background: #f8f9fa;
        border-radius: 5px;
    }
    
    .info-label {
        font-size: 12px;
        color: #666;
        text-transform: uppercase;
        margin-bottom: 5px;
    }
    
    .info-value {
        font-size: 16px;
        color: #333;
        font-weight: 500;
    }
    
    .participants-list {
        margin-top: 30px;
    }
    
    .participant-item {
        display: flex;
        align-items: center;
        padding: 10px;
        border-bottom: 1px solid #eee;
    }
    
    .participant-item:last-child {
        border-bottom: none;
    }
    
    .participant-status {
        margin-left: auto;
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 12px;
    }
    
    .status-confermato {
        background: #d4edda;
        color: #155724;
    }
    
    .status-invitato {
        background: #fff3cd;
        color: #856404;
    }
    
    .btn-danger {
        background-color: #dc3545;
        color: white;
        border: 1px solid #dc3545;
        padding: 8px 16px;
        border-radius: 4px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        transition: background-color 0.2s;
    }
    
    .btn-danger:hover {
        background-color: #c82333;
        border-color: #bd2130;
        color: white;
        text-decoration: none;
    }
    
    .header-actions {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
        }
        
        .header-actions {
            flex-direction: column;
            gap: 8px;
        }
        
        .header-actions .btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<?php if ($action == 'nuovo' || $action == 'edit'): ?>
    <div class="content-header">
        <h1><i class="fas fa-calendar-plus"></i> <?php echo $pageTitle; ?></h1>
    </div>
    
    <?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>
    
    <div class="form-container">
        <form method="POST" action="">
            <div class="form-group">
                <label for="titolo">Titolo <span class="required">*</span></label>
                <input type="text" id="titolo" name="titolo" 
                       value="<?php echo htmlspecialchars($action == 'edit' ? $evento['titolo'] : ($_POST['titolo'] ?? '')); ?>" 
                       required>
            </div>
            
            <div class="form-group">
                <label for="descrizione">Descrizione</label>
                <textarea id="descrizione" name="descrizione"><?php echo htmlspecialchars($action == 'edit' ? $evento['descrizione'] : ($_POST['descrizione'] ?? '')); ?></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="data_inizio">Data e ora inizio <span class="required">*</span></label>
                    <input type="datetime-local" id="data_inizio" name="data_inizio" 
                           value="<?php echo $action == 'edit' ? date('Y-m-d\TH:i', strtotime($evento['data_inizio'])) : ($_POST['data_inizio'] ?? ''); ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label for="data_fine">Data e ora fine <span class="required">*</span></label>
                    <input type="datetime-local" id="data_fine" name="data_fine" 
                           value="<?php echo $action == 'edit' ? date('Y-m-d\TH:i', strtotime($evento['data_fine'])) : ($_POST['data_fine'] ?? ''); ?>" 
                           required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="luogo">Luogo</label>
                    <input type="text" id="luogo" name="luogo" 
                           value="<?php echo htmlspecialchars($action == 'edit' ? $evento['luogo'] : ($_POST['luogo'] ?? '')); ?>" 
                           placeholder="Es: Sala riunioni, Online, ecc.">
                </div>
                
                <div class="form-group">
                    <label for="tipo">Tipo evento</label>
                    <select id="tipo" name="tipo">
                        <?php
                        $tipi = ['riunione' => 'Riunione', 'formazione' => 'Formazione', 'evento' => 'Evento', 'altro' => 'Altro'];
                        $tipo_selezionato = $action == 'edit' ? $evento['tipo'] : ($_POST['tipo'] ?? 'riunione');
                        foreach ($tipi as $value => $label):
                        ?>
                        <option value="<?php echo $value; ?>" <?php echo $tipo_selezionato == $value ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <?php if ($action == 'nuovo' && !empty($utenti_disponibili)): ?>
            <div class="form-group">
                <label for="partecipanti">Invita partecipanti</label>
                <select id="partecipanti" name="partecipanti[]" multiple style="height: 200px;" class="form-control">
                    <?php 
                    $grouped = [];
                    foreach ($utenti_disponibili as $utente) {
                        if ($utente['id'] == $user['id']) continue; // Skip current user
                        
                        if ($utente['ruolo'] === 'super_admin') {
                            $grouped['Super Admin'][] = $utente;
                        } elseif (isset($utente['azienda_nome'])) {
                            $grouped[$utente['azienda_nome']][] = $utente;
                        } else {
                            $grouped['Utenti Azienda'][] = $utente;
                        }
                    }
                    
                    foreach ($grouped as $group => $users): ?>
                        <optgroup label="<?php echo htmlspecialchars($group); ?>">
                            <?php foreach ($users as $utente): ?>
                                <option value="<?php echo $utente['id']; ?>">
                                    <?php echo htmlspecialchars($utente['nome'] . ' ' . $utente['cognome'] . ' (' . $utente['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
                <small class="form-text text-muted">
                    <i class="fas fa-info-circle"></i> 
                    <?php if ($auth->isSuperAdmin()): ?>
                        Puoi invitare tutti gli utenti del sistema
                    <?php else: ?>
                        Puoi invitare solo i membri della tua azienda e i super admin
                    <?php endif; ?>
                    - Tieni premuto Ctrl per selezionare più partecipanti
                </small>
            </div>
            <?php endif; ?>
            
            <div style="display: flex; gap: 10px; margin-top: 30px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> <?php echo $action == 'edit' ? 'Salva Modifiche' : 'Crea Evento'; ?>
                </button>
                <a href="<?php echo APP_PATH; ?>/calendario.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Annulla
                </a>
            </div>
        </form>
    </div>
    
<?php elseif ($action == 'view'): ?>
    <div class="content-header">
        <h1><i class="fas fa-calendar-alt"></i> Dettagli Evento</h1>
        <div class="header-actions">
            <?php if ($auth->canManageEvents() || $evento['creato_da'] == $user['id']): ?>
            <a href="<?php echo APP_PATH; ?>/eventi.php?action=edit&id=<?php echo $evento['id']; ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Modifica
            </a>
            <?php endif; ?>
            
            <?php if ($auth->isSuperAdmin()): ?>
            <form method="POST" action="<?php echo APP_PATH; ?>/eventi.php?action=delete&id=<?php echo $evento['id']; ?>" style="display: inline;">
                <button type="submit" class="btn btn-danger" 
                        onclick="return confirm('Sei sicuro di voler eliminare questo evento? Tutti i partecipanti riceveranno una notifica di cancellazione.');">
                    <i class="fas fa-trash"></i> Elimina Evento
                </button>
            </form>
            <?php endif; ?>
            
            <a href="<?php echo APP_PATH; ?>/calendario.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Torna al calendario
            </a>
        </div>
    </div>
    
    <div class="event-details">
        <div class="event-info">
            <h2><?php echo htmlspecialchars($evento['titolo']); ?></h2>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Data inizio</div>
                    <div class="info-value"><?php echo format_datetime($evento['data_inizio']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Data fine</div>
                    <div class="info-value"><?php echo format_datetime($evento['data_fine']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Luogo</div>
                    <div class="info-value"><?php echo htmlspecialchars($evento['luogo'] ?: 'Non specificato'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Tipo</div>
                    <div class="info-value"><?php echo ucfirst($evento['tipo']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Creato da</div>
                    <div class="info-value"><?php echo htmlspecialchars($evento['nome_creatore'] . ' ' . $evento['cognome_creatore']); ?></div>
                </div>
            </div>
            
            <?php if ($evento['descrizione']): ?>
            <div style="margin-top: 20px;">
                <h3>Descrizione</h3>
                <p><?php echo nl2br(htmlspecialchars($evento['descrizione'])); ?></p>
            </div>
            <?php endif; ?>
            
            <div class="participants-list">
                <h3>Partecipanti (<?php echo count($partecipanti); ?>)</h3>
                <?php foreach ($partecipanti as $partecipante): ?>
                <div class="participant-item">
                    <div>
                        <strong><?php echo htmlspecialchars($partecipante['nome'] . ' ' . $partecipante['cognome']); ?></strong>
                        <br>
                        <small><?php echo htmlspecialchars($partecipante['email']); ?></small>
                    </div>
                    <span class="participant-status status-<?php echo $partecipante['stato']; ?>">
                        <?php echo ucfirst($partecipante['stato']); ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once 'components/footer.php'; ?> 