<?php
/**
 * Calendario Eventi Unificato
 * Gestione completa eventi con viste calendario
 */

require_once 'backend/config/config.php';
require_once 'backend/utils/EventInvite.php';
require_once 'backend/utils/ModulesHelper.php';
require_once 'backend/utils/CSRFTokenManager.php';

$auth = Auth::getInstance();
$auth->requireAuth();

// Initialize CSRF token manager
$csrf = CSRFTokenManager::getInstance();

$user = $auth->getUser();
$currentAzienda = $auth->getCurrentAzienda();
$isSuperAdmin = $auth->isSuperAdmin();
$isUtenteSpeciale = $auth->isUtenteSpeciale();

// Verifica accesso al modulo calendario
if (!$isSuperAdmin) {
    ModulesHelper::requireModule('calendario');
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
    if (!$currentAzienda) {
        redirect(APP_PATH . '/seleziona-azienda.php');
    }
}

$action = $_GET['action'] ?? 'calendar';
$id = intval($_GET['id'] ?? 0);
$view = $_GET['view'] ?? 'month'; // month, week, day, list
$date = $_GET['date'] ?? date('Y-m-d');
$show_tasks = isset($_GET['show_tasks']) ? $_GET['show_tasks'] === '1' : true; // Mostra task di default per utenti autorizzati

$message = '';
$error = '';

// Gestisci le diverse azioni
switch ($action) {
    case 'nuovo_task':
        // Solo super admin può creare task
        if (!$isSuperAdmin) {
            $_SESSION['error'] = "Non hai i permessi per assegnare task";
            redirect(APP_PATH . '/calendario-eventi.php');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                // Gestione prodotto/servizio
                $prodotto_tipo = 'predefinito';
                $prodotto_predefinito = null;
                $prodotto_personalizzato = null;
                
                if (!empty($_POST['prodotto_servizio'])) {
                    if (in_array($_POST['prodotto_servizio'], ['9001', '14001', '27001', '45001', 'Autorizzazione', 'Accreditamento'])) {
                        $prodotto_predefinito = $_POST['prodotto_servizio'];
                    } else {
                        $prodotto_tipo = 'personalizzato';
                        $prodotto_personalizzato = $_POST['prodotto_servizio'];
                    }
                }
                
                // Verifica che ci siano utenti assegnati
                $utenti_assegnati = $_POST['utenti_assegnati'] ?? [];
                if (empty($utenti_assegnati)) {
                    throw new Exception("Seleziona almeno un utente a cui assegnare il task");
                }
                
                db_connection()->beginTransaction();
                
                // Inserisci task con usa_giorni_specifici
                $usa_giorni_specifici = isset($_POST['usa_giorni_specifici']) ? 1 : 0;
                
                $sql = "INSERT INTO task_calendario (
                            utente_assegnato_id, attivita, giornate_previste, costo_giornata,
                            azienda_id, citta, prodotto_servizio_tipo, prodotto_servizio_predefinito,
                            prodotto_servizio_personalizzato, data_inizio, data_fine,
                            descrizione, note, assegnato_da, usa_giorni_specifici
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                // Usa il primo utente come principale per mantenere compatibilità
                $params = [
                    $utenti_assegnati[0],
                    $_POST['attivita'],
                    $_POST['giornate_previste'],
                    $_POST['costo_giornata'],
                    $_POST['azienda_id'],
                    $_POST['citta'],
                    $prodotto_tipo,
                    $prodotto_predefinito,
                    $prodotto_personalizzato,
                    $_POST['data_inizio'],
                    $_POST['data_fine'],
                    $_POST['descrizione'] ?? '',
                    $_POST['note'] ?? '',
                    $user['id'],
                    $usa_giorni_specifici
                ];
                
                db_query($sql, $params);
                $task_id = db_connection()->lastInsertId();
                
                // Inserisci assegnazioni multiple
                foreach ($utenti_assegnati as $utente_id) {
                    $sql_assegnazione = "INSERT INTO task_assegnazioni (task_id, utente_id, percentuale_completamento) 
                                         VALUES (?, ?, 0)";
                    db_query($sql_assegnazione, [$task_id, $utente_id]);
                }
                
                // Se sono stati selezionati giorni specifici, salvali
                if ($usa_giorni_specifici && !empty($_POST['giorni_specifici'])) {
                    $giorni = explode(',', $_POST['giorni_specifici']);
                    foreach ($giorni as $giorno) {
                        $sql_giorno = "INSERT INTO task_giorni (task_id, data_giorno) VALUES (?, ?)";
                        db_query($sql_giorno, [$task_id, $giorno]);
                    }
                }
                
                // Invia notifiche email a tutti gli utenti assegnati
                foreach ($utenti_assegnati as $utente_id) {
                    $utente = db_query("SELECT * FROM utenti WHERE id = ?", [$utente_id])->fetch();
                    if ($utente) {
                        sendTaskAssignmentNotification([
                            'id' => $task_id,
                            'attivita' => $_POST['attivita'],
                            'giornate_previste' => $_POST['giornate_previste'],
                            'data_inizio' => $_POST['data_inizio'],
                            'data_fine' => $_POST['data_fine'],
                            'citta' => $_POST['citta'],
                            'prodotto_servizio_personalizzato' => $prodotto_personalizzato,
                            'prodotto_servizio_predefinito' => $prodotto_predefinito
                        ], $utente, $user);
                    }
                }
                
                db_connection()->commit();
                
                $_SESSION['success'] = "Task assegnato con successo a " . count($utenti_assegnati) . " utenti";
                redirect(APP_PATH . '/calendario-eventi.php');
                
            } catch (Exception $e) {
                if (db_connection()->inTransaction()) {
                    db_connection()->rollback();
                }
                $error = "Errore durante la creazione del task: " . $e->getMessage();
            }
        }
        
        // Carica lista utenti assegnabili (solo utenti speciali e super admin)
        $utenti_assegnabili = db_query("
            SELECT id, nome, cognome, email, ruolo 
            FROM utenti 
            WHERE ruolo IN ('super_admin', 'utente_speciale', 'admin') 
            AND attivo = 1
            ORDER BY nome, cognome
        ")->fetchAll();
        
        // Carica lista aziende
        $aziende_disponibili = db_query("
            SELECT id, nome 
            FROM aziende 
            WHERE stato = 'attiva' 
            ORDER BY nome
        ")->fetchAll();
        break;
        
    case 'nuovo':
        if (!$auth->canManageEvents()) {
            $_SESSION['error'] = "Non hai i permessi per creare eventi";
            redirect(APP_PATH . '/calendario-eventi.php');
        }
        
        $eventInvite = new EventInvite();
        
        // Ottieni l'ID dell'azienda corrente
        $aziendaId = null;
        if ($currentAzienda) {
            $aziendaId = $currentAzienda['id'] ?? $currentAzienda['azienda_id'] ?? null;
        }
        
        // Carica lista utenti invitabili
        $utenti_disponibili = $eventInvite->getInvitableUsers($user['id'], $aziendaId);
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                error_log("[DEBUG EVENTO] Inizio creazione evento");
                
                // Verifica se c'è già una transazione attiva
                $transactionActive = db_connection()->inTransaction();
                error_log("[DEBUG EVENTO] Transazione già attiva: " . ($transactionActive ? 'SI' : 'NO'));
                
                if (!$transactionActive) {
                    db_connection()->beginTransaction();
                    error_log("[DEBUG EVENTO] Transazione iniziata");
                }
                
                // Valida i dati
                $titolo = sanitize_input($_POST['titolo'] ?? '');
                $descrizione = sanitize_input($_POST['descrizione'] ?? '');
                $data_inizio = $_POST['data_inizio'] ?? '';
                $ora_inizio = $_POST['ora_inizio'] ?? '';
                $data_fine = $_POST['data_fine'] ?? '';
                $ora_fine = $_POST['ora_fine'] ?? '';
                $luogo = sanitize_input($_POST['luogo'] ?? '');
                $tipo = sanitize_input($_POST['tipo'] ?? 'riunione');
                
                if (empty($titolo) || empty($data_inizio) || empty($ora_inizio)) {
                    throw new Exception("Titolo, data e ora di inizio sono obbligatori");
                }
                
                $datetime_inizio = $data_inizio . ' ' . $ora_inizio;
                $datetime_fine = empty($data_fine) || empty($ora_fine) ? 
                    $datetime_inizio : $data_fine . ' ' . $ora_fine;
                
                error_log("[DEBUG EVENTO] Prima di INSERT evento");
                
                // Inserisci evento
                $stmt = db_query(
                    "INSERT INTO eventi (titolo, descrizione, data_inizio, data_fine, luogo, tipo, azienda_id, creata_da) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    [$titolo, $descrizione, $datetime_inizio, $datetime_fine, $luogo, $tipo, $aziendaId, $user['id']]
                );
                
                $evento_id = db_connection()->lastInsertId();
                error_log("[DEBUG EVENTO] Evento creato con ID: $evento_id");
                
                // Gestisci partecipanti invitati
                $partecipanti = $_POST['partecipanti'] ?? [];
                if (!empty($partecipanti)) {
                    error_log("[DEBUG EVENTO] Aggiunta " . count($partecipanti) . " partecipanti");
                    
                    foreach ($partecipanti as $utente_id) {
                        db_query(
                            "INSERT INTO evento_partecipanti (evento_id, utente_id, stato) 
                             VALUES (?, ?, 'invitato')",
                            [$evento_id, $utente_id]
                        );
                    }
                    
                    // Invia notifiche email
                    if (!empty($_POST['invia_notifiche'])) {
                        error_log("[DEBUG EVENTO] Invio notifiche email");
                        $eventInvite->sendInvitations($evento_id, $partecipanti);
                        error_log("[DEBUG EVENTO] Notifiche inviate");
                    }
                }
                
                error_log("[DEBUG EVENTO] Prima del commit, transazione attiva: " . (db_connection()->inTransaction() ? 'SI' : 'NO'));
                
                if (!$transactionActive && db_connection()->inTransaction()) {
                    db_connection()->commit();
                    error_log("[DEBUG EVENTO] Transazione committata");
                }
                
                // Debug dettagliato per capire cosa sta succedendo
                $debug_msg = "Evento creato con successo";
                $debug_msg .= " [DEBUG: partecipanti=" . json_encode($partecipanti ?? []) . "]";
                $debug_msg .= " [DEBUG: invia_notifiche=" . (isset($_POST['invia_notifiche']) ? $_POST['invia_notifiche'] : 'NON_IMPOSTATO') . "]";
                
                if (!empty($partecipanti)) {
                    $debug_msg .= " - " . count($partecipanti) . " partecipanti aggiunti";
                    if (!empty($_POST['invia_notifiche'])) {
                        $debug_msg .= " - Notifiche INVIATE";
                    } else {
                        $debug_msg .= " - Notifiche NON inviate (checkbox non spuntato)";
                    }
                } else {
                    $debug_msg .= " - NESSUN partecipante selezionato";
                }
                
                $_SESSION['success'] = $debug_msg;
                redirect(APP_PATH . '/calendario-eventi.php');
                
            } catch (Exception $e) {
                error_log("[DEBUG EVENTO] ERRORE: " . $e->getMessage());
                error_log("[DEBUG EVENTO] Stack trace: " . $e->getTraceAsString());
                error_log("[DEBUG EVENTO] Transazione attiva prima del rollback: " . (db_connection()->inTransaction() ? 'SI' : 'NO'));
                
                if (db_connection()->inTransaction()) {
                    db_connection()->rollback();
                    error_log("[DEBUG EVENTO] Rollback eseguito");
                }
                $error = "Errore durante la creazione dell'evento: " . $e->getMessage();
            }
        }
        break;
        
    case 'modifica':
        if (!$auth->canManageEvents()) {
            $_SESSION['error'] = "Non hai i permessi per modificare eventi";
            redirect(APP_PATH . '/calendario-eventi.php');
        }
        
        // Carica evento esistente
        $stmt = db_query("SELECT * FROM eventi WHERE id = ?", [$id]);
        $evento = $stmt->fetch();
        
        if (!$evento) {
            $_SESSION['error'] = "Evento non trovato";
            redirect(APP_PATH . '/calendario-eventi.php');
        }
        
        // Verifica permessi
        if (!$auth->canViewAllEvents() && $evento['creata_da'] != $user['id']) {
            $_SESSION['error'] = "Non hai i permessi per modificare questo evento";
            redirect(APP_PATH . '/calendario-eventi.php');
        }
        
        $eventInvite = new EventInvite();
        $aziendaId = $currentAzienda['id'] ?? $currentAzienda['azienda_id'] ?? null;
        $utenti_disponibili = $eventInvite->getInvitableUsers($user['id'], $aziendaId);
        
        // Carica partecipanti attuali
        $stmt = db_query("SELECT utente_id FROM evento_partecipanti WHERE evento_id = ?", [$id]);
        $partecipanti_attuali = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                db_connection()->beginTransaction();
                
                $titolo = sanitize_input($_POST['titolo'] ?? '');
                $descrizione = sanitize_input($_POST['descrizione'] ?? '');
                $data_inizio = $_POST['data_inizio'] ?? '';
                $ora_inizio = $_POST['ora_inizio'] ?? '';
                $data_fine = $_POST['data_fine'] ?? '';
                $ora_fine = $_POST['ora_fine'] ?? '';
                $luogo = sanitize_input($_POST['luogo'] ?? '');
                $tipo = sanitize_input($_POST['tipo'] ?? 'riunione');
                
                if (empty($titolo) || empty($data_inizio) || empty($ora_inizio)) {
                    throw new Exception("Titolo, data e ora di inizio sono obbligatori");
                }
                
                $datetime_inizio = $data_inizio . ' ' . $ora_inizio;
                $datetime_fine = empty($data_fine) || empty($ora_fine) ? 
                    $datetime_inizio : $data_fine . ' ' . $ora_fine;
                
                // Aggiorna evento
                db_query(
                    "UPDATE eventi SET titolo = ?, descrizione = ?, data_inizio = ?, data_fine = ?, 
                     luogo = ?, tipo = ? WHERE id = ?",
                    [$titolo, $descrizione, $datetime_inizio, $datetime_fine, $luogo, $tipo, $id]
                );
                
                // Aggiorna partecipanti
                db_query("DELETE FROM evento_partecipanti WHERE evento_id = ?", [$id]);
                
                $partecipanti = $_POST['partecipanti'] ?? [];
                if (!empty($partecipanti)) {
                    foreach ($partecipanti as $utente_id) {
                        db_query(
                            "INSERT INTO evento_partecipanti (evento_id, utente_id, stato) 
                             VALUES (?, ?, 'invitato')",
                            [$id, $utente_id]
                        );
                    }
                }
                
                // Recupera i partecipanti per le notifiche
                $stmt = db_query("
                    SELECT u.email, u.nome, u.cognome 
                    FROM evento_partecipanti ep
                    JOIN utenti u ON ep.utente_id = u.id
                    WHERE ep.evento_id = ?
                ", [$id]);
                $partecipanti_notificare = $stmt->fetchAll();
                
                db_connection()->commit();
                
                // Invia notifiche email ai partecipanti
                if (!empty($partecipanti_notificare) && isset($_POST['invia_notifiche']) && $_POST['invia_notifiche'] == '1') {
                    $eventInvite = EventInvite::getInstance();
                    $evento_aggiornato = db_query("SELECT * FROM eventi WHERE id = ?", [$id])->fetch();
                    
                    foreach ($partecipanti_notificare as $partecipante) {
                        try {
                            $eventInvite->sendEventUpdate($evento_aggiornato, $partecipante);
                        } catch (Exception $e) {
                            error_log("Errore invio notifica aggiornamento evento: " . $e->getMessage());
                        }
                    }
                }
                
                $_SESSION['success'] = "Evento aggiornato con successo";
                redirect(APP_PATH . '/calendario-eventi.php');
                
            } catch (Exception $e) {
                if (db_connection()->inTransaction()) {
                    db_connection()->rollback();
                }
                $error = "Errore durante l'aggiornamento dell'evento: " . $e->getMessage();
            }
        }
        break;
        
    case 'elimina':
        if (!$auth->canManageEvents()) {
            $_SESSION['error'] = "Non hai i permessi per eliminare eventi";
            redirect(APP_PATH . '/calendario-eventi.php');
        }
        
        $stmt = db_query("SELECT * FROM eventi WHERE id = ?", [$id]);
        $evento = $stmt->fetch();
        
        if (!$evento) {
            $_SESSION['error'] = "Evento non trovato";
            redirect(APP_PATH . '/calendario-eventi.php');
        }
        
        // Verifica permessi
        if (!$auth->canViewAllEvents() && $evento['creata_da'] != $user['id']) {
            $_SESSION['error'] = "Non hai i permessi per eliminare questo evento";
            redirect(APP_PATH . '/calendario-eventi.php');
        }
        
        // Verifica CSRF token se richiesta POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $csrf->verifyRequest();
            } catch (Exception $e) {
                $_SESSION['error'] = "Token di sicurezza non valido. Riprova.";
                redirect(APP_PATH . '/calendario-eventi.php');
            }
        }
        
        try {
            db_connection()->beginTransaction();
            
            // Prima recupera i partecipanti per notificare
            $stmt = db_query("
                SELECT u.email, u.nome, u.cognome 
                FROM evento_partecipanti ep
                JOIN utenti u ON ep.utente_id = u.id
                WHERE ep.evento_id = ?
            ", [$id]);
            $partecipanti = $stmt->fetchAll();
            
            // Elimina partecipanti
            db_query("DELETE FROM evento_partecipanti WHERE evento_id = ?", [$id]);
            
            // Elimina evento
            db_query("DELETE FROM eventi WHERE id = ?", [$id]);
            
            db_connection()->commit();
            
            // Invia notifiche email ai partecipanti
            if (!empty($partecipanti)) {
                $eventInvite = EventInvite::getInstance();
                foreach ($partecipanti as $partecipante) {
                    try {
                        $eventInvite->sendEventCancellation($evento, $partecipante);
                    } catch (Exception $e) {
                        error_log("Errore invio notifica cancellazione evento: " . $e->getMessage());
                    }
                }
            }
            
            $_SESSION['success'] = "Evento eliminato con successo";
            
        } catch (Exception $e) {
            if (db_connection()->inTransaction()) {
                db_connection()->rollback();
            }
            $_SESSION['error'] = "Errore durante l'eliminazione dell'evento: " . $e->getMessage();
        }
        
        redirect(APP_PATH . '/calendario-eventi.php?view=' . ($_GET['view'] ?? 'month') . '&date=' . ($_GET['date'] ?? date('Y-m-d')));
        break;
        
    case 'modifica_task':
        // Solo super admin può modificare task
        if (!$isSuperAdmin) {
            $_SESSION['error'] = "Non hai i permessi per modificare task";
            redirect(APP_PATH . '/calendario-eventi.php');
        }
        
        $stmt = db_query("SELECT * FROM task_calendario WHERE id = ?", [$id]);
        $task = $stmt->fetch();
        
        if (!$task) {
            $_SESSION['error'] = "Task non trovato";
            redirect(APP_PATH . '/calendario-eventi.php');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                // Gestione prodotto/servizio
                $prodotto_tipo = 'predefinito';
                $prodotto_predefinito = null;
                $prodotto_personalizzato = null;
                
                if (!empty($_POST['prodotto_servizio'])) {
                    if (in_array($_POST['prodotto_servizio'], ['9001', '14001', '27001', '45001', 'Autorizzazione', 'Accreditamento'])) {
                        $prodotto_predefinito = $_POST['prodotto_servizio'];
                    } else {
                        $prodotto_tipo = 'personalizzato';
                        $prodotto_personalizzato = $_POST['prodotto_servizio'];
                    }
                }
                
                // Verifica che ci siano utenti assegnati
                $utenti_assegnati = $_POST['utenti_assegnati'] ?? [];
                if (empty($utenti_assegnati)) {
                    throw new Exception("Seleziona almeno un utente a cui assegnare il task");
                }
                
                db_connection()->beginTransaction();
                
                // Recupera gli utenti precedentemente assegnati
                $stmt = db_query("SELECT utente_id FROM task_assegnazioni WHERE task_id = ?", [$id]);
                $utenti_precedenti = array_column($stmt->fetchAll(), 'utente_id');
                
                // Aggiorna task con usa_giorni_specifici
                $usa_giorni_specifici = isset($_POST['usa_giorni_specifici']) ? 1 : 0;
                
                $sql = "UPDATE task_calendario SET
                            utente_assegnato_id = ?, attivita = ?, giornate_previste = ?, costo_giornata = ?,
                            azienda_id = ?, citta = ?, prodotto_servizio_tipo = ?, prodotto_servizio_predefinito = ?,
                            prodotto_servizio_personalizzato = ?, data_inizio = ?, data_fine = ?,
                            descrizione = ?, note = ?, usa_giorni_specifici = ?, ultima_modifica = NOW()
                        WHERE id = ?";
                
                // Usa il primo utente come principale per mantenere compatibilità
                $params = [
                    $utenti_assegnati[0],
                    $_POST['attivita'],
                    $_POST['giornate_previste'],
                    $_POST['costo_giornata'],
                    $_POST['azienda_id'],
                    $_POST['citta'],
                    $prodotto_tipo,
                    $prodotto_predefinito,
                    $prodotto_personalizzato,
                    $_POST['data_inizio'],
                    $_POST['data_fine'],
                    $_POST['descrizione'] ?? '',
                    $_POST['note'] ?? '',
                    $usa_giorni_specifici,
                    $id
                ];
                
                db_query($sql, $params);
                
                // Aggiorna assegnazioni
                db_query("DELETE FROM task_assegnazioni WHERE task_id = ?", [$id]);
                
                foreach ($utenti_assegnati as $utente_id) {
                    $sql_assegnazione = "INSERT INTO task_assegnazioni (task_id, utente_id, percentuale_completamento) 
                                         VALUES (?, ?, COALESCE((SELECT percentuale_completamento FROM 
                                                 (SELECT * FROM task_assegnazioni) AS old 
                                                 WHERE old.task_id = ? AND old.utente_id = ? LIMIT 1), 0))";
                    db_query($sql_assegnazione, [$id, $utente_id, $id, $utente_id]);
                }
                
                // Aggiorna giorni specifici
                db_query("DELETE FROM task_giorni WHERE task_id = ?", [$id]);
                
                if ($usa_giorni_specifici && !empty($_POST['giorni_specifici'])) {
                    $giorni = explode(',', $_POST['giorni_specifici']);
                    foreach ($giorni as $giorno) {
                        $sql_giorno = "INSERT INTO task_giorni (task_id, data_giorno) VALUES (?, ?)";
                        db_query($sql_giorno, [$id, $giorno]);
                    }
                }
                
                // Invia notifiche email se richiesto
                if (isset($_POST['invia_notifiche']) && $_POST['invia_notifiche'] == '1') {
                    // Notifica agli utenti nuovi
                    $utenti_nuovi = array_diff($utenti_assegnati, $utenti_precedenti);
                    foreach ($utenti_nuovi as $utente_id) {
                        $utente = db_query("SELECT * FROM utenti WHERE id = ?", [$utente_id])->fetch();
                        if ($utente) {
                            sendTaskAssignmentNotification($task, $utente, $user);
                        }
                    }
                    
                    // Notifica agli utenti esistenti
                    $utenti_esistenti = array_intersect($utenti_assegnati, $utenti_precedenti);
                    foreach ($utenti_esistenti as $utente_id) {
                        $utente = db_query("SELECT * FROM utenti WHERE id = ?", [$utente_id])->fetch();
                        if ($utente) {
                            sendTaskUpdateNotification($task, $utente, $user);
                        }
                    }
                    
                    // Notifica agli utenti rimossi
                    $utenti_rimossi = array_diff($utenti_precedenti, $utenti_assegnati);
                    foreach ($utenti_rimossi as $utente_id) {
                        $utente = db_query("SELECT * FROM utenti WHERE id = ?", [$utente_id])->fetch();
                        if ($utente) {
                            sendTaskReassignNotification($task, $utente, $user);
                        }
                    }
                }
                
                db_connection()->commit();
                
                $_SESSION['success'] = "Task aggiornato con successo";
                redirect(APP_PATH . '/calendario-eventi.php');
                
            } catch (Exception $e) {
                if (db_connection()->inTransaction()) {
                    db_connection()->rollback();
                }
                $error = "Errore durante l'aggiornamento del task: " . $e->getMessage();
            }
        }
        
        // Carica liste per il form
        $utenti_assegnabili = db_query("
            SELECT id, nome, cognome, email, ruolo 
            FROM utenti 
            WHERE ruolo IN ('super_admin', 'utente_speciale', 'admin') 
            AND attivo = 1
            ORDER BY nome, cognome
        ")->fetchAll();
        
        $aziende_disponibili = db_query("
            SELECT id, nome 
            FROM aziende 
            WHERE stato = 'attiva' 
            ORDER BY nome
        ")->fetchAll();
        break;
        
    case 'elimina_task':
        // Solo super admin può eliminare task
        if (!$isSuperAdmin) {
            $_SESSION['error'] = "Non hai i permessi per eliminare task";
            redirect(APP_PATH . '/calendario-eventi.php');
        }
        
        $stmt = db_query("SELECT * FROM task_calendario WHERE id = ?", [$id]);
        $task = $stmt->fetch();
        
        if (!$task) {
            $_SESSION['error'] = "Task non trovato";
            redirect(APP_PATH . '/calendario-eventi.php');
        }
        
        try {
            // Prima recupera l'utente assegnato per notificare
            $utente = db_query("SELECT * FROM utenti WHERE id = ?", [$task['utente_assegnato_id']])->fetch();
            
            // Elimina il task
            db_query("DELETE FROM task_calendario WHERE id = ?", [$id]);
            
            // Invia notifica di cancellazione
            if ($utente) {
                sendTaskCancellationNotification($task, $utente, $user);
            }
            
            $_SESSION['success'] = "Task eliminato con successo";
            
        } catch (Exception $e) {
            $_SESSION['error'] = "Errore durante l'eliminazione del task: " . $e->getMessage();
        }
        
        redirect(APP_PATH . '/calendario-eventi.php');
        break;
}

// Carica eventi per la vista calendario
function getEventsForView($view, $date, $user, $auth, $filter_azienda_id = null, $include_tasks = false) {
    $whereClause = "WHERE 1=1";
    $params = [];
    
    // Filtro per azienda
    if ($auth->isSuperAdmin()) {
        // Super admin con filtro azienda specifico
        if ($filter_azienda_id) {
            $whereClause .= " AND e.azienda_id = ?";
            $params[] = $filter_azienda_id;
        }
        // Altrimenti vede tutto
    } else {
        // Utente normale - vede solo eventi della sua azienda
        $currentAzienda = $auth->getCurrentAzienda();
        if ($currentAzienda) {
            $aziendaId = $currentAzienda['id'] ?? $currentAzienda['azienda_id'] ?? null;
            if ($aziendaId) {
                $whereClause .= " AND (e.azienda_id = ? OR e.creata_da = ?)";
                $params[] = $aziendaId;
                $params[] = $user['id'];
            }
        }
    }
    
    // Filtro per data basato sulla vista
    switch ($view) {
        case 'day':
            $whereClause .= " AND DATE(e.data_inizio) = ?";
            $params[] = $date;
            break;
            
        case 'week':
            $startWeek = date('Y-m-d', strtotime('monday this week', strtotime($date)));
            $endWeek = date('Y-m-d', strtotime('sunday this week', strtotime($date)));
            $whereClause .= " AND DATE(e.data_inizio) BETWEEN ? AND ?";
            $params[] = $startWeek;
            $params[] = $endWeek;
            break;
            
        case 'month':
            $year = date('Y', strtotime($date));
            $month = date('m', strtotime($date));
            $whereClause .= " AND YEAR(e.data_inizio) = ? AND MONTH(e.data_inizio) = ?";
            $params[] = $year;
            $params[] = $month;
            break;
            
        case 'list':
            // For list view, show events from the current month or future
            $year = date('Y', strtotime($date));
            $month = date('m', strtotime($date));
            $firstDayOfMonth = "$year-$month-01";
            $whereClause .= " AND DATE(e.data_inizio) >= ?";
            $params[] = $firstDayOfMonth;
            break;
    }
    
    $sql = "SELECT e.*, 
                   u.nome as creatore_nome, u.cognome as creatore_cognome,
                   a.nome as nome_azienda,
                   COUNT(ep.id) as num_partecipanti
            FROM eventi e 
            LEFT JOIN utenti u ON e.creata_da = u.id 
            LEFT JOIN aziende a ON e.azienda_id = a.id
            LEFT JOIN evento_partecipanti ep ON e.id = ep.evento_id
            $whereClause
            GROUP BY e.id
            ORDER BY e.data_inizio ASC";
    
    try {
        $stmt = db_query($sql, $params);
        $result = $stmt->fetchAll();
        return $result ?: [];
    } catch (Exception $e) {
        error_log("Error fetching events: " . $e->getMessage());
        return [];
    }
}

// Carica eventi e task se l'utente ha i permessi
$include_tasks = $show_tasks && ($isSuperAdmin || $isUtenteSpeciale);
$eventi = getEventsForView($view, $date, $user, $auth, $filter_azienda_id, $include_tasks);

// Se l'utente può vedere i task, carica anche i contatori
$task_counters = null;
$user_tasks = [];
$filter_user_id = null;

// Gestione filtro utente per super admin
if ($isSuperAdmin && isset($_GET['filter_user'])) {
    $filter_user_id = intval($_GET['filter_user']);
}

if ($isSuperAdmin || $isUtenteSpeciale) {
    // Carica i task per la visualizzazione nel calendario
    $task_params = [];
    $task_sql = "SELECT t.*, 
                 u.nome as utente_nome, u.cognome as utente_cognome,
                 a.nome as azienda_nome
                 FROM task_calendario t
                 JOIN utenti u ON t.utente_assegnato_id = u.id
                 JOIN aziende a ON t.azienda_id = a.id
                 WHERE t.stato != 'annullato'";
    
    // Applica filtri in base al ruolo
    if (!$isSuperAdmin) {
        $task_sql .= " AND t.utente_assegnato_id = ?";
        $task_params[] = $user['id'];
    } elseif ($filter_user_id) {
        // Se super admin con filtro utente
        $task_sql .= " AND t.utente_assegnato_id = ?";
        $task_params[] = $filter_user_id;
    }
    
    // Applica filtro data in base alla vista
    switch ($view) {
        case 'day':
            $task_sql .= " AND (DATE(t.data_inizio) <= ? AND DATE(t.data_fine) >= ?)";
            $task_params[] = $date;
            $task_params[] = $date;
            break;
            
        case 'week':
            $startWeek = date('Y-m-d', strtotime('monday this week', strtotime($date)));
            $endWeek = date('Y-m-d', strtotime('sunday this week', strtotime($date)));
            $task_sql .= " AND ((t.data_inizio BETWEEN ? AND ?) OR (t.data_fine BETWEEN ? AND ?) OR (t.data_inizio <= ? AND t.data_fine >= ?))";
            $task_params[] = $startWeek;
            $task_params[] = $endWeek;
            $task_params[] = $startWeek;
            $task_params[] = $endWeek;
            $task_params[] = $startWeek;
            $task_params[] = $endWeek;
            break;
            
        case 'month':
            $year = date('Y', strtotime($date));
            $month = date('m', strtotime($date));
            $start_month = "$year-$month-01";
            $end_month = date('Y-m-t', strtotime($start_month));
            $task_sql .= " AND ((t.data_inizio BETWEEN ? AND ?) OR (t.data_fine BETWEEN ? AND ?) OR (t.data_inizio <= ? AND t.data_fine >= ?))";
            $task_params[] = $start_month;
            $task_params[] = $end_month;
            $task_params[] = $start_month;
            $task_params[] = $end_month;
            $task_params[] = $start_month;
            $task_params[] = $end_month;
            break;
            
        case 'list':
            // For list view, show tasks from the current month or future
            $year = date('Y', strtotime($date));
            $month = date('m', strtotime($date));
            $firstDayOfMonth = "$year-$month-01";
            $task_sql .= " AND t.data_fine >= ?";
            $task_params[] = $firstDayOfMonth;
            break;
    }
    
    $task_sql .= " ORDER BY t.data_inizio ASC";
    
    $stmt = db_query($task_sql, $task_params);
    $user_tasks = $stmt->fetchAll();
    
    // Carica i contatori
    $counter_sql = "SELECT * FROM vista_conteggio_giornate_task WHERE utente_assegnato_id = ?";
    $counter_params = [$filter_user_id ?: ($isSuperAdmin && isset($_GET['view_user']) ? intval($_GET['view_user']) : $user['id'])];
    $stmt = db_query($counter_sql, $counter_params);
    $task_counters = $stmt->fetchAll();
}

$pageTitle = 'Calendario Eventi';
$bodyClass = 'calendario-eventi-page';
include dirname(__FILE__) . '/components/header.php';
require_once 'components/page-header.php';
?>

<!-- Clean Dashboard Styles -->
<link rel="stylesheet" href="assets/css/dashboard-clean.css">
<!-- Calendar Fix CSS -->
<link rel="stylesheet" href="assets/css/calendar-fix.css">

<?php
// Header unificato per calendario
$calendarActions = [
    [
        'text' => 'Nuovo Evento',
        'icon' => 'fas fa-plus',
        'href' => '?action=new',
        'class' => 'unified-btn unified-btn-primary'
    ],
    [
        'text' => 'Importa ICS',
        'icon' => 'fas fa-file-import',
        'href' => '#',
        'class' => 'unified-btn unified-btn-success',
        'onclick' => 'openICSImportModal(); return false;'
    ],
    [
        'text' => 'Esporta Calendario',
        'icon' => 'fas fa-download',
        'href' => '#',
        'class' => 'unified-btn unified-btn-secondary',
        'onclick' => 'toggleExportDropdown()'
    ]
];

if ($isSuperAdmin || $isUtenteSpeciale) {
    $calendarActions[] = [
        'text' => 'Task Progress',
        'icon' => 'fas fa-tasks',
        'href' => 'task-progress.php',
        'class' => 'unified-btn unified-btn-info'
    ];
}

renderPageHeader('Calendario Eventi', 'Visualizza e gestisci gli eventi', 'calendar-alt');
?>

<div class="action-bar">
    <?php if ($isSuperAdmin && count($aziende_list) > 1): ?>
    <select class="form-control"  onchange="filterByAzienda(this.value)">
        <option value="">Tutte le aziende</option>
        <?php foreach ($aziende_list as $azienda): ?>
            <option value="<?php echo $azienda['id']; ?>" 
                    <?php echo $filter_azienda_id == $azienda['id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($azienda['nome']); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php endif; ?>
    
    <button class="btn btn-success" onclick="openICSImportModal(); return false;" style="display:inline-flex;align-items:center;justify-content:center;gap:6px;">
        <i class="fas fa-file-import"></i> Importa ICS
    </button>
    
    <div class="export-dropdown" style="position: relative;">
        <button class="btn btn-secondary dropdown-toggle" style="display:inline-flex;align-items:center;justify-content:center;gap:6px;" onclick="toggleExportDropdown()">
            <i class="fas fa-download"></i> Esporta ICS
        </button>
        <div class="dropdown-menu" id="exportDropdown" >
            <a href="esporta-calendario.php?tipo=calendario&periodo=mese" class="dropdown-item"  onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='transparent'">
                <i class="fas fa-calendar"></i> Questo mese
            </a>
            <a href="esporta-calendario.php?tipo=calendario&periodo=trimestre" class="dropdown-item"  onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='transparent'">
                <i class="fas fa-calendar-alt"></i> Questo trimestre
            </a>
            <a href="esporta-calendario.php?tipo=calendario&periodo=anno" class="dropdown-item"  onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='transparent'">
                <i class="fas fa-calendar-check"></i> Questo anno
            </a>
            <a href="esporta-calendario.php?tipo=calendario&periodo=tutto" class="dropdown-item"  onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='transparent'">
                <i class="fas fa-download"></i> Tutti gli eventi
            </a>
        </div>
    </div>
    <?php if ($auth->canManageEvents()): ?>
    <a href="?action=nuovo" class="btn btn-primary">
        <i class="fas fa-plus"></i> Nuovo Evento
    </a>
    <?php endif; ?>
    
    <?php if ($isSuperAdmin): ?>
    <a href="?action=nuovo_task" class="btn btn-primary" style="background: #0fb37a;">
        <i class="fas fa-tasks"></i> Assegna Task
    </a>
    <?php endif; ?>
</div>

<?php if (($isSuperAdmin || $isUtenteSpeciale) && !empty($task_counters)): ?>
<!-- Contatori giornate task -->
<div class="content-card">
    <div class="panel-header">
        <h2><i class="fas fa-chart-bar"></i> Riepilogo Giornate Task</h2>
    </div>
    <div class="stats-grid">
        <?php 
        $totale_generale = 0;
        $totale_completate = 0;
        $totale_pianificate = 0;
        
        foreach ($task_counters as $counter): 
            $totale_generale += $counter['totale_giornate'];
            $totale_completate += $counter['giornate_completate'];
            $totale_pianificate += $counter['giornate_pianificate'];
        ?>
        <div class="stat-card">
            <div class="stat-icon" style="background: #dbeafe;">
                <i class="fas fa-tasks" ></i>
            </div>
            <div class="stat-label"><?= htmlspecialchars($counter['attivita']) ?></div>
            <div class="stat-value"><?= number_format($counter['totale_giornate'], 1, ',', '.') ?> gg</div>
            <div >
                <div style="margin-bottom: 4px;"><span class="badge badge-success">Completate: <?= number_format($counter['giornate_completate'], 1, ',', '.') ?></span></div>
                <div><span class="badge badge-info">Pianificate: <?= number_format($counter['giornate_pianificate'], 1, ',', '.') ?></span></div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <div class="stat-card" style="background: #2d5a9f; color: white;">
            <div class="stat-icon" style="background: rgba(255,255,255,0.2);">
                <i class="fas fa-chart-line" style="color: white;"></i>
            </div>
            <div class="stat-label" style="color: rgba(255,255,255,0.9);">TOTALE</div>
            <div class="stat-value" style="color: white;"><?= number_format($totale_generale, 1, ',', '.') ?> gg</div>
            <div style="font-size: 12px; margin-top: 8px;">
                <div style="margin-bottom: 4px;"><span class="badge" style="background: rgba(255,255,255,0.2); color: white;">Completate: <?= number_format($totale_completate, 1, ',', '.') ?></span></div>
                <div><span class="badge" style="background: rgba(255,255,255,0.2); color: white;">Pianificate: <?= number_format($totale_pianificate, 1, ',', '.') ?></span></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($isSuperAdmin): ?>
<!-- Riepilogo giornate per tutti gli utenti (solo Super Admin) -->
<div class="content-card">
    <div class="panel-header">
        <h2><i class="fas fa-users"></i> Riepilogo Giornate Assegnate per Utente</h2>
    </div>
    <?php
    // Carica riepilogo giornate per tutti gli utenti
    $workday_sql = "SELECT 
                    u.id, u.nome, u.cognome, u.ruolo,
                    COUNT(DISTINCT t.id) as num_task,
                    SUM(t.giornate_previste) as totale_giornate,
                    SUM(CASE WHEN t.stato = 'completato' THEN t.giornate_previste ELSE 0 END) as giornate_completate,
                    SUM(CASE WHEN t.stato IN ('assegnato', 'in_corso') THEN t.giornate_previste ELSE 0 END) as giornate_in_corso,
                    GROUP_CONCAT(DISTINCT t.attivita ORDER BY t.attivita) as attivita_types
                FROM utenti u
                LEFT JOIN task_calendario t ON u.id = t.utente_assegnato_id AND t.stato != 'annullato'
                WHERE u.attivo = 1 AND u.ruolo IN ('super_admin', 'utente_speciale', 'admin')
                GROUP BY u.id
                HAVING num_task > 0 OR u.ruolo = 'super_admin'
                ORDER BY totale_giornate DESC, u.nome, u.cognome";
    
    $stmt = db_query($workday_sql);
    $user_workdays = $stmt->fetchAll();
    ?>
    
    <div style="overflow-x: auto;">
        <table class="table-clean">
            <thead>
                <tr>
                    <th>Utente</th>
                    <th>Ruolo</th>
                    <th>Task Assegnati</th>
                    <th>Totale Giornate</th>
                    <th>Completate</th>
                    <th>In Corso</th>
                    <th>Attività</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $totale_task_generale = 0;
                $totale_gg_generale = 0;
                $totale_gg_completate = 0;
                $totale_gg_in_corso = 0;
                
                foreach ($user_workdays as $user_data): 
                    $totale_task_generale += $user_data['num_task'];
                    $totale_gg_generale += $user_data['totale_giornate'] ?? 0;
                    $totale_gg_completate += $user_data['giornate_completate'] ?? 0;
                    $totale_gg_in_corso += $user_data['giornate_in_corso'] ?? 0;
                ?>
                <tr>
                    <td class="user-name">
                        <i class="fas fa-user"></i>
                        <?= htmlspecialchars($user_data['nome'] . ' ' . $user_data['cognome']) ?>
                    </td>
                    <td>
                        <span class="badge <?= match($user_data['ruolo']) {
                            'super_admin' => 'badge-info',
                            'utente_speciale' => 'badge-success',
                            'admin' => 'badge-warning',
                            default => 'badge-secondary'
                        } ?>">
                            <?= match($user_data['ruolo']) {
                                'super_admin' => 'Super Admin',
                                'utente_speciale' => 'Utente Speciale',
                                'admin' => 'Admin',
                                default => ucfirst($user_data['ruolo'])
                            } ?>
                        </span>
                    </td>
                    <td class="text-center"><?= $user_data['num_task'] ?></td>
                    <td class="text-center">
                        <strong><?= number_format($user_data['totale_giornate'] ?? 0, 1, ',', '.') ?></strong> gg
                    </td>
                    <td class="text-center text-success">
                        <?= number_format($user_data['giornate_completate'] ?? 0, 1, ',', '.') ?> gg
                    </td>
                    <td class="text-center text-warning">
                        <?= number_format($user_data['giornate_in_corso'] ?? 0, 1, ',', '.') ?> gg
                    </td>
                    <td class="activity-types">
                        <?php if ($user_data['attivita_types']): ?>
                            <?php foreach (explode(',', $user_data['attivita_types']) as $attivita): ?>
                                <span class="badge badge-secondary" style="margin-right: 4px;"><?= htmlspecialchars($attivita) ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="text-muted">Nessuna</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($user_data['num_task'] > 0): ?>
                        <a href="?view=list&filter_user=<?= $user_data['id'] ?>" class="btn btn-sm btn-secondary">
                            <i class="fas fa-eye"></i> Visualizza
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="summary-row">
                    <td colspan="2"><strong>TOTALE</strong></td>
                    <td class="text-center"><strong><?= $totale_task_generale ?></strong></td>
                    <td class="text-center"><strong><?= number_format($totale_gg_generale, 1, ',', '.') ?></strong> gg</td>
                    <td class="text-center text-success"><strong><?= number_format($totale_gg_completate, 1, ',', '.') ?></strong> gg</td>
                    <td class="text-center text-warning"><strong><?= number_format($totale_gg_in_corso, 1, ',', '.') ?></strong> gg</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Controlli vista calendario -->
<div class="calendar-controls">
    <div class="view-controls">
        <a href="?view=day&date=<?= $date ?>" class="btn btn-sm <?= $view === 'day' ? 'btn-primary' : 'btn-outline' ?>">
            <i class="fas fa-calendar-day"></i> Giorno
        </a>
        <a href="?view=week&date=<?= $date ?>" class="btn btn-sm <?= $view === 'week' ? 'btn-primary' : 'btn-outline' ?>">
            <i class="fas fa-calendar-week"></i> Settimana
        </a>
        <a href="?view=month&date=<?= $date ?>" class="btn btn-sm <?= $view === 'month' ? 'btn-primary' : 'btn-outline' ?>">
            <i class="fas fa-calendar"></i> Mese
        </a>
        <a href="?view=list&date=<?= $date ?>" class="btn btn-sm <?= $view === 'list' ? 'btn-primary' : 'btn-outline' ?>">
            <i class="fas fa-list"></i> Lista
        </a>
    </div>
    
    <div class="date-navigation">
        <?php
        $prevDate = date('Y-m-d', strtotime($date . ' -1 ' . ($view === 'month' ? 'month' : ($view === 'week' ? 'week' : 'day'))));
        $nextDate = date('Y-m-d', strtotime($date . ' +1 ' . ($view === 'month' ? 'month' : ($view === 'week' ? 'week' : 'day'))));
        ?>
        <a href="?view=<?= $view ?>&date=<?= $prevDate ?>" class="btn btn-sm btn-outline">
            <i class="fas fa-chevron-left"></i>
        </a>
        <span class="current-date">
            <?php
            switch ($view) {
                case 'day':
                    echo date('d/m/Y', strtotime($date));
                    break;
                case 'week':
                    $startWeek = date('d/m', strtotime('monday this week', strtotime($date)));
                    $endWeek = date('d/m/Y', strtotime('sunday this week', strtotime($date)));
                    echo "$startWeek - $endWeek";
                    break;
                case 'month':
                    echo date('F Y', strtotime($date));
                    break;
                case 'list':
                    echo 'Tutti gli eventi';
                    break;
            }
            ?>
        </span>
        <a href="?view=<?= $view ?>&date=<?= $nextDate ?>" class="btn btn-sm btn-outline">
            <i class="fas fa-chevron-right"></i>
        </a>
        <a href="?view=<?= $view ?>&date=<?= date('Y-m-d') ?>" class="btn btn-sm btn-secondary">
            <i class="fas fa-home"></i> Oggi
        </a>
    </div>
</div>

<!-- Visualizzazione eventi -->
<div class="calendar-container">
    <?php if ($action === 'nuovo' || $action === 'modifica'): ?>
        <!-- Form per nuovo/modifica evento -->
        <?php include 'components/evento-form.php'; ?>
    <?php elseif ($action === 'nuovo_task'): ?>
        <!-- Form per nuovo task -->
        <?php include 'components/task-form.php'; ?>
    <?php else: ?>
        <!-- Vista calendario -->
        <?php
        switch ($view) {
            case 'day':
                include 'components/calendar-day-view.php';
                break;
            case 'week':
                include 'components/calendar-week-view.php';
                break;
            case 'month':
                include 'components/calendar-month-view.php';
                break;
            case 'list':
            default:
                include 'components/calendar-list-view.php';
                break;
        }
        ?>
    <?php endif; ?>
</div>

<style>
/* Stili specifici per calendario eventi */
.content-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e2e8f0;
}

.calendar-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding: 20px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.view-controls {
    display: flex;
    gap: 10px;
}

.date-navigation {
    display: flex;
    align-items: center;
    gap: 15px;
}

.current-date {
    font-weight: 600;
    color: #2d3748;
    min-width: 150px;
    text-align: center;
}

.calendar-container {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    padding: 20px;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    text-decoration: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-primary {
    background: #4299e1;
    color: white;
}

.btn-primary:hover {
    background: #3182ce;
}

.btn-secondary {
    background: #e2e8f0;
    color: #2d3748;
}

.btn-secondary:hover {
    background: #cbd5e0;
}

.btn-outline {
    background: white;
    color: #4299e1;
    border: 1px solid #4299e1;
}

.btn-outline:hover {
    background: #4299e1;
    color: white;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

/* Dropdown per esportazione */
.export-dropdown {
    position: relative;
    display: inline-block;
}

.dropdown-toggle::after {
    content: '▼';
    margin-left: 6px;
    font-size: 10px;
}

.dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    min-width: 200px;
    z-index: 1000;
    display: none;
}

.dropdown-menu.show {
    display: block;
}

.dropdown-item {
    display: block;
    padding: 10px 15px;
    color: #2d3748;
    text-decoration: none;
    font-size: 14px;
    border-bottom: 1px solid #f7fafc;
    transition: background-color 0.2s ease;
}

.dropdown-item:hover {
    background-color: #f7fafc;
    color: #2d3748;
}

.dropdown-item:last-child {
    border-bottom: none;
}

.dropdown-item i {
    margin-right: 8px;
    width: 16px;
}

/* Stili per i task */
.task-counters {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    padding: 20px;
    margin-bottom: 20px;
}

.task-counters h3 {
    margin: 0 0 20px 0;
    color: #2d3748;
    font-size: 18px;
}

.counters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.counter-card {
    background: #f7fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 15px;
    text-align: center;
}

.counter-card.total {
    background: #edf2f7;
    border-color: #cbd5e0;
}

.counter-type {
    font-weight: 600;
    color: #4a5568;
    margin-bottom: 10px;
    font-size: 14px;
}

.counter-value {
    font-size: 24px;
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 10px;
}

.counter-details {
    font-size: 12px;
    color: #718096;
    display: flex;
    justify-content: space-around;
}

.counter-details .completed {
    color: #48bb78;
}

.counter-details .planned {
    color: #4299e1;
}

/* Stili per task nel calendario */
.event-task {
    background: #f0fff4 !important;
    border-left: 4px solid #48bb78;
}

.event-task .event-type {
    background: #48bb78;
    color: white;
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 3px;
    display: inline-block;
    margin-bottom: 4px;
}

.btn-success {
    background: #48bb78;
    color: white;
}

.btn-success:hover {
    background: #38a169;
}

.header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.filter-select {
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    background: white;
    font-size: 14px;
    cursor: pointer;
}

.filter-select:focus {
    outline: none;
    border-color: #4299e1;
    box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
}

@media (max-width: 768px) {
    .calendar-controls {
        flex-direction: column;
        gap: 15px;
    }
    
    .content-header {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
    
    .header-actions {
        flex-wrap: wrap;
        width: 100%;
    }
    
    .view-controls {
        overflow-x: auto;
        width: 100%;
    }
    
    .date-navigation {
        justify-content: center;
    }
    
    .dropdown-menu {
        right: auto;
        left: 0;
    }
}

/* Workday Summary Table */
.workday-summary {
    margin: 30px 0;
    background: white;
    border-radius: 8px;
    padding: 25px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.workday-summary h3 {
    color: #2d3748;
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.workday-table-container {
    overflow-x: auto;
}

.workday-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.workday-table th {
    background: #f7fafc;
    color: #4a5568;
    font-weight: 600;
    text-align: left;
    padding: 12px 15px;
    border-bottom: 2px solid #e2e8f0;
}

.workday-table td {
    padding: 12px 15px;
    border-bottom: 1px solid #e2e8f0;
    vertical-align: middle;
}

.workday-table tbody tr:hover {
    background: #f7fafc;
}

.user-name {
    font-weight: 500;
    color: #2d3748;
}

.role-badge {
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 500;
}

.role-super_admin {
    background: #e6fffa;
    color: #065f46;
}

.role-utente_speciale {
    background: #fef3c7;
    color: #92400e;
}

.role-admin {
    background: #ede9fe;
    color: #5b21b6;
}

.text-center {
    text-align: center;
}

.text-success {
    color: #48bb78;
}

.text-warning {
    color: #ed8936;
}

.text-muted {
    color: #a0aec0;
}

.activity-types {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}

.activity-badge {
    background: #e2e8f0;
    color: #4a5568;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
}

.summary-row {
    background: #f7fafc;
    font-weight: 600;
}

.summary-row td {
    border-top: 2px solid #e2e8f0;
    border-bottom: none;
}

/* Fix per garantire che i pulsanti siano sempre cliccabili */
.action-bar {
    position: relative;
    z-index: 10;
    display: flex;
    gap: 10px;
    align-items: center;
    margin-bottom: 20px;
}

.action-bar .btn,
.calendar-controls .btn {
    position: relative;
    z-index: 10;
    pointer-events: auto !important;
}

/* Modal Styles - Fixed to not block page when hidden */
.modal:not(.show) {
    display: none !important;
    z-index: -1 !important;
    pointer-events: none !important;
}

.modal.show {
    display: block !important;
    z-index: 1050 !important;
    pointer-events: auto !important;
}

/* Specific modal fixes */
#icsImportModal:not(.show),
#eventDetailsModal:not(.show) {
    display: none !important;
    z-index: -1 !important;
    pointer-events: none !important;
}

#icsImportModal.show,
#eventDetailsModal.show {
    display: block !important;
    z-index: 1050 !important;
    pointer-events: auto !important;
}

.modal-backdrop {
    z-index: 1040;
}

/* Ensure modal content doesn't block clicks when hidden */
.modal[aria-hidden="true"] {
    display: none !important;
    pointer-events: none !important;
}

#icsImportModal .modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-bottom: none;
}

#icsImportModal .modal-header .btn-close {
    filter: brightness(0) invert(1);
    opacity: 0.8;
}

#icsImportModal .modal-header .btn-close:hover {
    opacity: 1;
}

#icsImportModal .form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

#icsImportModal .btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
}

#icsImportModal .btn-primary:hover {
    background: linear-gradient(135deg, #5a6fd7 0%, #693f8f 100%);
}

.btn-success {
    background: #48bb78;
    border: none;
    color: white;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-success:hover {
    background: #38a169;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(72, 187, 120, 0.3);
}

/* File upload styling */
.form-control[type="file"] {
    padding: 0.75rem;
    border: 2px dashed #cbd5e0;
    background: #f7fafc;
    cursor: pointer;
    transition: all 0.3s ease;
}

.form-control[type="file"]:hover {
    border-color: #667eea;
    background: #edf2f7;
}

.form-control[type="file"]:focus {
    border-style: solid;
}

@media (max-width: 768px) {
    .workday-table {
        font-size: 12px;
    }
    
    .workday-table th,
    .workday-table td {
        padding: 8px 10px;
    }
    
    .activity-badge {
        font-size: 10px;
    }
    
    #icsImportModal .modal-dialog {
        margin: 1rem;
    }
}
</style>

<script>
function toggleExportDropdown() {
    const dropdown = document.getElementById('exportDropdown');
    dropdown.classList.toggle('show');
}

// Chiudi dropdown quando si clicca fuori
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('exportDropdown');
    const button = event.target.closest('.dropdown-toggle');
    
    if (!button && dropdown) {
        dropdown.classList.remove('show');
    }
});

// Chiudi dropdown quando si preme ESC
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const dropdown = document.getElementById('exportDropdown');
        if (dropdown) {
            dropdown.classList.remove('show');
        }
    }
});

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

<?php
// Funzioni per notifiche task
function sendTaskAssignmentNotification($task, $utente, $assegnatore) {
    require_once 'backend/utils/Mailer.php';
    require_once 'backend/utils/EmailTemplate.php';
    $mailer = Mailer::getInstance();
    
    $subject = "Nuovo Task Assegnato: {$task['attivita']} - {$task['citta']}";
    
    $dettagli = [
        'Attività' => $task['attivita'],
        'Giornate previste' => $task['giornate_previste'],
        'Periodo' => date('d/m/Y', strtotime($task['data_inizio'])) . ' - ' . date('d/m/Y', strtotime($task['data_fine'])),
        'Città' => $task['citta'],
        'Prodotto/Servizio' => $task['prodotto_servizio_personalizzato'] ?? $task['prodotto_servizio_predefinito'] ?? 'Non specificato',
        'Assegnato da' => $assegnatore['nome'] . ' ' . $assegnatore['cognome']
    ];
    
    $body = EmailTemplate::generate(
        'Nuovo Task Assegnato',
        "Ti è stato assegnato un nuovo task.",
        'Visualizza Calendario',
        'http://localhost' . APP_PATH . '/calendario-eventi.php?view=month&date=' . date('Y-m-d', strtotime($task['data_inizio'])),
        $dettagli
    );
    
    return $mailer->send($utente['email'], $subject, $body);
}

function sendTaskUpdateNotification($task, $utente, $modificatore) {
    require_once 'backend/utils/Mailer.php';
    require_once 'backend/utils/EmailTemplate.php';
    $mailer = Mailer::getInstance();
    
    $subject = "Task Aggiornato: {$task['attivita']} - {$task['citta']}";
    
    $dettagli = [
        'Attività' => $task['attivita'],
        'Giornate previste' => $task['giornate_previste'],
        'Periodo' => date('d/m/Y', strtotime($task['data_inizio'])) . ' - ' . date('d/m/Y', strtotime($task['data_fine'])),
        'Città' => $task['citta'],
        'Prodotto/Servizio' => $task['prodotto_servizio_personalizzato'] ?? $task['prodotto_servizio_predefinito'] ?? 'Non specificato',
        'Modificato da' => $modificatore['nome'] . ' ' . $modificatore['cognome']
    ];
    
    $body = EmailTemplate::generate(
        'Task Aggiornato',
        "Il tuo task è stato modificato.",
        'Visualizza Calendario',
        'http://localhost' . APP_PATH . '/calendario-eventi.php?view=month&date=' . date('Y-m-d', strtotime($task['data_inizio'])),
        $dettagli
    );
    
    return $mailer->send($utente['email'], $subject, $body);
}

function sendTaskReassignNotification($task, $utente_precedente, $modificatore) {
    require_once 'backend/utils/Mailer.php';
    $mailer = Mailer::getInstance();
    
    $subject = "Task Riassegnato: {$task['attivita']} - {$task['citta']}";
    
    $body = EmailTemplate::generate(
        'Task Riassegnato',
        "Il task che ti era stato assegnato è stato riassegnato ad un altro utente.",
        null,
        null,
        [
            'Attività' => $task['attivita'],
            'Periodo' => date('d/m/Y', strtotime($task['data_inizio'])) . ' - ' . date('d/m/Y', strtotime($task['data_fine'])),
            'Città' => $task['citta'],
            'Riassegnato da' => $modificatore['nome'] . ' ' . $modificatore['cognome']
        ]
    );
    
    return $mailer->send($utente_precedente['email'], $subject, $body);
}

function sendTaskCancellationNotification($task, $utente, $cancellatore) {
    require_once 'backend/utils/Mailer.php';
    $mailer = Mailer::getInstance();
    
    $subject = "Task Cancellato: {$task['attivita']} - {$task['citta']}";
    
    $body = EmailTemplate::generate(
        'Task Cancellato',
        "Il task che ti era stato assegnato è stato cancellato.",
        null,
        null,
        [
            'Attività' => $task['attivita'],
            'Periodo' => date('d/m/Y', strtotime($task['data_inizio'])) . ' - ' . date('d/m/Y', strtotime($task['data_fine'])),
            'Città' => $task['citta'],
            'Giornate previste' => $task['giornate_previste'],
            'Cancellato da' => $cancellatore['nome'] . ' ' . $cancellatore['cognome']
        ]
    );
    
    return $mailer->send($utente['email'], $subject, $body);
}
?>

<!-- ICS Import Modal -->
<div class="modal fade" id="icsImportModal" tabindex="-1" aria-labelledby="icsImportModalLabel" aria-hidden="true" style="display: none;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title" id="icsImportModalLabel">
                    <i class="fas fa-file-import"></i> Importa Eventi da File ICS
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Chiudi"></button>
            </div>
            <form id="icsImportForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-4">
                        <label for="icsFile" class="form-label fw-bold">
                            <i class="fas fa-file-upload"></i> Seleziona file ICS/iCal
                        </label>
                        <input type="file" class="form-control form-control-lg" id="icsFile" name="ics_file" accept=".ics,.ical,.ifb,.icalendar" required>
                        <div class="form-text">
                            <i class="fas fa-info-circle"></i> Formati supportati: .ics, .ical, .ifb, .icalendar (max 5MB)
                        </div>
                    </div>
                    
                    <?php if ($isSuperAdmin && count($aziende_list) > 0): ?>
                    <div class="mb-3">
                        <label for="importAzienda" class="form-label">
                            <i class="fas fa-building"></i> Associa ad azienda (opzionale)
                        </label>
                        <select class="form-select" id="importAzienda" name="azienda_id">
                            <option value="">🗓️ Nessuna azienda / Calendario personale</option>
                            <option value="" disabled>────────────────────────</option>
                            <?php foreach ($aziende_list as $azienda): ?>
                                <option value="<?php echo $azienda['id']; ?>">
                                    <?php echo htmlspecialchars($azienda['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">
                            <i class="fas fa-info-circle"></i> Gli eventi senza azienda appariranno con un colore neutro nel calendario
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info d-flex align-items-start">
                        <i class="fas fa-info-circle me-2 mt-1"></i>
                        <div>
                            <strong>Come funziona l'importazione:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Supporta eventi singoli e ricorrenti</li>
                                <li>Gestisce automaticamente i fusi orari</li>
                                <li>Gli eventi duplicati vengono saltati</li>
                                <li>I partecipanti vengono aggiunti se trovati nel sistema</li>
                                <li>Puoi importare da Google Calendar, Outlook, Apple Calendar, etc.</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div id="importProgress" style="display: none;">
                        <div class="progress mb-2">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%"></div>
                        </div>
                        <p class="text-center text-muted">Importazione in corso...</p>
                    </div>
                    
                    <div id="importResult" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-primary" id="importBtn">
                        <i class="fas fa-upload"></i> Importa Eventi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ICS Import functionality
function openICSImportModal() {
    try {
        // Check if Bootstrap is loaded
        if (typeof bootstrap === 'undefined') {
            alert('Bootstrap non è caricato. Ricarica la pagina.');
            return false;
        }
        
        const modalElement = document.getElementById('icsImportModal');
        if (!modalElement) {
            console.error('Modal element not found');
            return false;
        }
        
        // Ensure modal is properly set up before showing
        modalElement.style.display = '';
        modalElement.removeAttribute('aria-hidden');
        
        // Get or create modal instance
        let modal = bootstrap.Modal.getInstance(modalElement);
        if (!modal) {
            modal = new bootstrap.Modal(modalElement, {
                backdrop: true,
                keyboard: true,
                focus: true
            });
        }
        
        // Reset form when modal opens
        const form = document.getElementById('icsImportForm');
        if (form) {
            form.reset();
            const progressEl = document.getElementById('importProgress');
            const resultEl = document.getElementById('importResult');
            
            if (progressEl) progressEl.style.display = 'none';
            if (resultEl) {
                resultEl.style.display = 'none';
                resultEl.innerHTML = '';
            }
            
            // Reset file input label
            const fileInput = document.getElementById('icsFile');
            if (fileInput) {
                fileInput.value = '';
                const label = fileInput.nextElementSibling;
                if (label && label.classList.contains('form-text')) {
                    label.innerHTML = '<i class="fas fa-info-circle"></i> Formati supportati: .ics, .ical, .ifb, .icalendar (max 5MB)';
                }
            }
        }
        
        // Show modal
        modal.show();
        
    } catch (error) {
        console.error('Error opening modal:', error);
        alert('Errore nell\'apertura del modal. Ricarica la pagina.');
    }
    
    return false;
}

// Update file input label with selected filename
function updateFileLabel(input) {
    const file = input.files[0];
    const label = input.nextElementSibling;
    if (file && label && label.classList.contains('form-text')) {
        const sizeInMB = (file.size / (1024 * 1024)).toFixed(2);
        label.innerHTML = `<i class="fas fa-check-circle text-success"></i> File selezionato: <strong>${file.name}</strong> (${sizeInMB} MB)`;
    }
}

// Add file input change listener and ensure modal works correctly
document.addEventListener('DOMContentLoaded', function() {
    console.log('Calendario eventi DOM loaded');
    
    // Ensure ICS import modal is properly hidden at start
    const icsModalElement = document.getElementById('icsImportModal');
    if (icsModalElement) {
        // Force hide the modal at page load
        icsModalElement.style.display = 'none';
        icsModalElement.classList.remove('show');
        icsModalElement.setAttribute('aria-hidden', 'true');
        
        // Setup event listeners for proper modal behavior
        icsModalElement.addEventListener('hidden.bs.modal', function () {
            icsModalElement.style.display = 'none';
            icsModalElement.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
            
            const backdrop = document.querySelector('.modal-backdrop');
            if (backdrop) {
                backdrop.remove();
            }
        });
        
        icsModalElement.addEventListener('show.bs.modal', function () {
            icsModalElement.style.display = 'block';
            icsModalElement.removeAttribute('aria-hidden');
        });
        
        icsModalElement.addEventListener('shown.bs.modal', function () {
            const firstInput = icsModalElement.querySelector('input:not([type="hidden"])');
            if (firstInput) {
                firstInput.focus();
            }
        });
    }
    
    // Clean up any leftover modal states at page load
    const allBackdrops = document.querySelectorAll('.modal-backdrop');
    allBackdrops.forEach(backdrop => backdrop.remove());
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
    
    // Initialize file input listener
    const fileInput = document.getElementById('icsFile');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            updateFileLabel(this);
        });
    }
    
    console.log('Calendario eventi inizializzato correttamente');
});

// ICS Import form submission
document.addEventListener('DOMContentLoaded', function() {
    const icsForm = document.getElementById('icsImportForm');
    if (icsForm) {
        icsForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const fileInput = document.getElementById('icsFile');
            if (!fileInput) {
                console.error('File input icsFile non trovato');
                return;
            }
            
            const file = fileInput.files[0];
            
            if (!file) {
                alert('Seleziona un file ICS da importare');
                return;
            }
            
            // Validate file size (max 5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('Il file è troppo grande. Dimensione massima: 5MB');
                return;
            }
            
            // Show progress
            const progressEl = document.getElementById('importProgress');
            const resultEl = document.getElementById('importResult');
            const importBtn = document.getElementById('importBtn');
            
            if (progressEl) progressEl.style.display = 'block';
            if (resultEl) resultEl.style.display = 'none';
            if (importBtn) importBtn.disabled = true;
            
            // Prepare form data
            const formData = new FormData();
            formData.append('ics_file', file);
            
            // Add azienda_id if super admin
            <?php if ($isSuperAdmin): ?>
            const aziendaSelect = document.getElementById('importAzienda');
            if (aziendaSelect && aziendaSelect.value) {
                formData.append('azienda_id', aziendaSelect.value);
            }
            <?php endif; ?>
            
            // Get CSRF token
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            
            // Send request
            fetch('<?php echo APP_PATH; ?>/backend/api/import-ics.php', {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': csrfToken
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Use safe element access
                const progressEl = document.getElementById('importProgress');
                const importBtn = document.getElementById('importBtn');
                const resultDiv = document.getElementById('importResult');
                
                if (progressEl) progressEl.style.display = 'none';
                if (importBtn) importBtn.disabled = false;
                
                if (resultDiv) {
                    resultDiv.style.display = 'block';
                    
                    if (data.success) {
                        let resultHtml = `
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i>
                                <strong>Importazione completata!</strong><br>
                                Eventi importati: ${data.imported}<br>
                                Eventi saltati (duplicati): ${data.skipped}<br>
                                Totale eventi nel file: ${data.total}
                            </div>
                        `;
                        
                        if (data.warnings && data.warnings.length > 0) {
                            resultHtml += `
                                <div class="alert alert-warning">
                                    <strong>Avvisi:</strong>
                                    <ul class="mb-0">
                                        ${data.warnings.map(w => `<li>${w}</li>`).join('')}
                                    </ul>
                                </div>
                            `;
                        }
                        
                        resultDiv.innerHTML = resultHtml;
                        
                        // Reload page after 3 seconds to show new events
                        if (data.imported > 0) {
                            setTimeout(() => {
                                window.location.reload();
                            }, 3000);
                        }
                    } else {
                        resultDiv.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Errore durante l'importazione:</strong><br>
                                ${data.error || 'Errore sconosciuto'}
                            </div>
                        `;
                    }
                }
            })
            .catch(error => {
                // Use safe element access for error handling
                const progressEl = document.getElementById('importProgress');
                const importBtn = document.getElementById('importBtn');
                const resultDiv = document.getElementById('importResult');
                
                if (progressEl) progressEl.style.display = 'none';
                if (importBtn) importBtn.disabled = false;
                
                if (resultDiv) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Errore di rete:</strong><br>
                            ${error.message || 'Impossibile completare l\'importazione'}
                        </div>
                    `;
                    resultDiv.style.display = 'block';
                }
            });
        });
    }
});
</script>

<?php include dirname(__FILE__) . '/components/footer.php'; ?>