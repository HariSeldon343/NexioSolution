<?php
/**
 * API per gestione Task Calendario
 * Solo Super User possono creare/modificare/eliminare task
 * Utenti Speciali e Super User possono visualizzare i propri task
 */

require_once dirname(__DIR__) . '/../backend/config/config.php';

header('Content-Type: application/json');

$auth = Auth::getInstance();
$auth->requireAuth();

$user = $auth->getUser();
$isSuperAdmin = $auth->isSuperAdmin();
$isUtenteSpeciale = $auth->isUtenteSpeciale();

// Solo Super User e Utenti Speciali hanno accesso a questa API
if (!$isSuperAdmin && !$isUtenteSpeciale) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accesso non autorizzato']);
    exit;
}

$action = $_GET['action'] ?? '';
$response = ['success' => false];

try {
    switch ($action) {
        case 'list':
            // Ottieni i task
            if ($isSuperAdmin) {
                // Super admin può vedere tutti i task o filtrare per utente
                $utente_id = intval($_GET['utente_id'] ?? 0);
                
                if ($utente_id > 0) {
                    $sql = "SELECT t.*, 
                            u.nome as utente_nome, u.cognome as utente_cognome,
                            a.nome as azienda_nome,
                            su.nome as assegnatore_nome, su.cognome as assegnatore_cognome
                            FROM task_calendario t
                            JOIN utenti u ON t.utente_assegnato_id = u.id
                            JOIN aziende a ON t.azienda_id = a.id
                            LEFT JOIN utenti su ON t.assegnato_da = su.id
                            WHERE t.utente_assegnato_id = ?
                            ORDER BY t.data_inizio ASC";
                    $params = [$utente_id];
                } else {
                    $sql = "SELECT t.*, 
                            u.nome as utente_nome, u.cognome as utente_cognome,
                            a.nome as azienda_nome,
                            su.nome as assegnatore_nome, su.cognome as assegnatore_cognome
                            FROM task_calendario t
                            JOIN utenti u ON t.utente_assegnato_id = u.id
                            JOIN aziende a ON t.azienda_id = a.id
                            LEFT JOIN utenti su ON t.assegnato_da = su.id
                            ORDER BY t.data_inizio ASC";
                    $params = [];
                }
            } else {
                // Utente speciale vede solo i suoi task
                $sql = "SELECT t.*, 
                        u.nome as utente_nome, u.cognome as utente_cognome,
                        a.nome as azienda_nome,
                        su.nome as assegnatore_nome, su.cognome as assegnatore_cognome
                        FROM task_calendario t
                        JOIN utenti u ON t.utente_assegnato_id = u.id
                        JOIN aziende a ON t.azienda_id = a.id
                        LEFT JOIN utenti su ON t.assegnato_da = su.id
                        WHERE t.utente_assegnato_id = ?
                        ORDER BY t.data_inizio ASC";
                $params = [$user['id']];
            }
            
            $stmt = db_query($sql, $params);
            $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response = [
                'success' => true,
                'tasks' => $tasks
            ];
            break;
            
        case 'get_counters':
            // Ottieni i contatori giornate per tipo di attività
            $utente_id = intval($_GET['utente_id'] ?? $user['id']);
            
            // Solo super admin può vedere i contatori di altri utenti
            if (!$isSuperAdmin && $utente_id != $user['id']) {
                throw new Exception('Non autorizzato a visualizzare i contatori di altri utenti');
            }
            
            $sql = "SELECT * FROM vista_conteggio_giornate_task WHERE utente_assegnato_id = ?";
            $stmt = db_query($sql, [$utente_id]);
            $counters = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Aggiungi anche il totale generale
            $totals = [
                'totale_giornate' => 0,
                'giornate_completate' => 0,
                'giornate_pianificate' => 0,
                'numero_task' => 0
            ];
            
            foreach ($counters as $counter) {
                $totals['totale_giornate'] += $counter['totale_giornate'];
                $totals['giornate_completate'] += $counter['giornate_completate'];
                $totals['giornate_pianificate'] += $counter['giornate_pianificate'];
                $totals['numero_task'] += $counter['numero_task'];
            }
            
            $response = [
                'success' => true,
                'counters' => $counters,
                'totals' => $totals
            ];
            break;
            
        case 'create':
            // Solo Super Admin può creare task
            if (!$isSuperAdmin) {
                throw new Exception('Solo i Super User possono assegnare task');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validazione
            $required = ['utente_assegnato_id', 'attivita', 'giornate_previste', 'costo_giornata', 
                         'azienda_id', 'citta', 'data_inizio', 'data_fine'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Il campo $field è obbligatorio");
                }
            }
            
            // Validazione giornate previste
            if ($data['giornate_previste'] < 0 || $data['giornate_previste'] > 15) {
                throw new Exception('Le giornate previste devono essere tra 0 e 15');
            }
            
            // Gestione prodotto/servizio
            $prodotto_tipo = 'predefinito';
            $prodotto_predefinito = null;
            $prodotto_personalizzato = null;
            
            if (isset($data['prodotto_servizio'])) {
                if (in_array($data['prodotto_servizio'], ['9001', '14001', '27001', '45001', 'Autorizzazione', 'Accreditamento'])) {
                    $prodotto_predefinito = $data['prodotto_servizio'];
                } else {
                    $prodotto_tipo = 'personalizzato';
                    $prodotto_personalizzato = $data['prodotto_servizio'];
                }
            }
            
            // Inserisci task
            $sql = "INSERT INTO task_calendario (
                        utente_assegnato_id, attivita, giornate_previste, costo_giornata,
                        azienda_id, citta, prodotto_servizio_tipo, prodotto_servizio_predefinito,
                        prodotto_servizio_personalizzato, data_inizio, data_fine,
                        descrizione, note, assegnato_da
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $data['utente_assegnato_id'],
                $data['attivita'],
                $data['giornate_previste'],
                $data['costo_giornata'],
                $data['azienda_id'],
                $data['citta'],
                $prodotto_tipo,
                $prodotto_predefinito,
                $prodotto_personalizzato,
                $data['data_inizio'],
                $data['data_fine'],
                $data['descrizione'] ?? '',
                $data['note'] ?? '',
                $user['id']
            ];
            
            db_query($sql, $params);
            $task_id = db_connection()->lastInsertId();
            
            // Invia notifica email all'utente assegnato
            try {
                require_once '../utils/NotificationManager.php';
                $notificationManager = NotificationManager::getInstance();
                
                // Ottieni info utente assegnato
                $stmt = db_query("SELECT * FROM utenti WHERE id = ?", [$data['utente_assegnato_id']]);
                $assegnato_a = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Ottieni info azienda
                $stmt = db_query("SELECT nome FROM aziende WHERE id = ?", [$data['azienda_id']]);
                $azienda = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($assegnato_a && $azienda) {
                    // Prepara dati task per email
                    $task_data = array_merge($data, [
                        'id' => $task_id,
                        'azienda_nome' => $azienda['nome']
                    ]);
                    
                    $assegnato_da = [
                        'nome' => $user['nome'],
                        'cognome' => $user['cognome'],
                        'email' => $user['email']
                    ];
                    
                    $notificationManager->notificaTaskAssegnato($task_data, $assegnato_a, $assegnato_da);
                    error_log("Notifica task assegnato inviata per task ID: $task_id");
                }
            } catch (Exception $e) {
                error_log("Errore invio notifica task assegnato: " . $e->getMessage());
            }
            
            $response = [
                'success' => true,
                'message' => 'Task creato con successo',
                'task_id' => $task_id
            ];
            break;
            
        case 'update':
            // Solo Super Admin può modificare task
            if (!$isSuperAdmin) {
                throw new Exception('Solo i Super User possono modificare task');
            }
            
            $task_id = intval($_POST['id'] ?? 0);
            if (!$task_id) {
                throw new Exception('ID task non valido');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Ottieni lo stato precedente del task
            $stmt = db_query("SELECT * FROM task_calendario WHERE id = ?", [$task_id]);
            $task_precedente = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$task_precedente) {
                throw new Exception('Task non trovato');
            }
            
            $old_status = $task_precedente['stato'];
            $new_status = $data['stato'] ?? 'assegnato';
            
            // Gestione prodotto/servizio
            $prodotto_tipo = 'predefinito';
            $prodotto_predefinito = null;
            $prodotto_personalizzato = null;
            
            if (isset($data['prodotto_servizio'])) {
                if (in_array($data['prodotto_servizio'], ['9001', '14001', '27001', '45001', 'Autorizzazione', 'Accreditamento'])) {
                    $prodotto_predefinito = $data['prodotto_servizio'];
                } else {
                    $prodotto_tipo = 'personalizzato';
                    $prodotto_personalizzato = $data['prodotto_servizio'];
                }
            }
            
            $sql = "UPDATE task_calendario SET
                        attivita = ?,
                        giornate_previste = ?,
                        costo_giornata = ?,
                        azienda_id = ?,
                        citta = ?,
                        prodotto_servizio_tipo = ?,
                        prodotto_servizio_predefinito = ?,
                        prodotto_servizio_personalizzato = ?,
                        data_inizio = ?,
                        data_fine = ?,
                        descrizione = ?,
                        note = ?,
                        stato = ?
                    WHERE id = ?";
                    
            $params = [
                $data['attivita'],
                $data['giornate_previste'],
                $data['costo_giornata'],
                $data['azienda_id'],
                $data['citta'],
                $prodotto_tipo,
                $prodotto_predefinito,
                $prodotto_personalizzato,
                $data['data_inizio'],
                $data['data_fine'],
                $data['descrizione'] ?? '',
                $data['note'] ?? '',
                $new_status,
                $task_id
            ];
            
            db_query($sql, $params);
            
            // Se lo stato è cambiato, invia notifica
            if ($old_status != $new_status) {
                try {
                    require_once '../utils/NotificationManager.php';
                    $notificationManager = NotificationManager::getInstance();
                    
                    // Ottieni info chi ha assegnato il task
                    $stmt = db_query("SELECT * FROM utenti WHERE id = ?", [$task_precedente['assegnato_da']]);
                    $assegnato_da = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Ottieni info azienda
                    $stmt = db_query("SELECT nome FROM aziende WHERE id = ?", [$data['azienda_id']]);
                    $azienda = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($assegnato_da && $azienda) {
                        // Prepara dati task per email
                        $task_data = array_merge($data, [
                            'id' => $task_id,
                            'azienda_nome' => $azienda['nome']
                        ]);
                        
                        $changed_by = [
                            'nome' => $user['nome'],
                            'cognome' => $user['cognome'],
                            'email' => $user['email']
                        ];
                        
                        $notificationManager->notificaTaskStatoCambiato($task_data, $old_status, $new_status, $changed_by, $assegnato_da);
                        error_log("Notifica cambio stato task inviata per task ID: $task_id - da $old_status a $new_status");
                    }
                } catch (Exception $e) {
                    error_log("Errore invio notifica cambio stato task: " . $e->getMessage());
                }
            }
            
            $response = [
                'success' => true,
                'message' => 'Task aggiornato con successo'
            ];
            break;
            
        case 'delete':
            // Solo Super Admin può eliminare task
            if (!$isSuperAdmin) {
                throw new Exception('Solo i Super User possono eliminare task');
            }
            
            $task_id = intval($_GET['id'] ?? 0);
            if (!$task_id) {
                throw new Exception('ID task non valido');
            }
            
            // Prima elimina l'evento associato se esiste
            $stmt = db_query("SELECT evento_id FROM task_calendario WHERE id = ?", [$task_id]);
            $task = $stmt->fetch();
            
            if ($task && $task['evento_id']) {
                db_query("DELETE FROM eventi WHERE id = ?", [$task['evento_id']]);
            }
            
            // Poi elimina il task
            db_query("DELETE FROM task_calendario WHERE id = ?", [$task_id]);
            
            $response = [
                'success' => true,
                'message' => 'Task eliminato con successo'
            ];
            break;
            
        case 'get_users':
            // Solo Super Admin può ottenere la lista utenti per assegnare task
            if (!$isSuperAdmin) {
                throw new Exception('Solo i Super User possono vedere la lista utenti');
            }
            
            // Ottieni solo utenti speciali e super admin
            $sql = "SELECT id, nome, cognome, email, ruolo 
                    FROM utenti 
                    WHERE ruolo IN ('super_admin', 'utente_speciale', 'admin') 
                    AND attivo = 1
                    ORDER BY nome, cognome";
            
            $stmt = db_query($sql);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response = [
                'success' => true,
                'users' => $users
            ];
            break;
            
        default:
            throw new Exception('Azione non valida');
    }
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
}

echo json_encode($response);