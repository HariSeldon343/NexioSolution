<?php
/**
 * API Endpoint per Eventi Calendario
 * Gestisce richieste CRUD per eventi del calendario mobile
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../middleware/Auth.php';
require_once '../utils/EventInvite.php';

try {
    $auth = Auth::getInstance();
    
    // Verifica autenticazione
    if (!$auth->isAuthenticated()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Non autenticato'
        ]);
        exit;
    }
    
    $user = $auth->getUser();
    $currentAzienda = $auth->getCurrentAzienda();
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGetEvents($auth, $user, $currentAzienda);
            break;
            
        case 'POST':
            handleCreateEvent($auth, $user, $currentAzienda);
            break;
            
        case 'PUT':
            handleUpdateEvent($auth, $user, $currentAzienda);
            break;
            
        case 'DELETE':
            handleDeleteEvent($auth, $user, $currentAzienda);
            break;
            
        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => 'Metodo non supportato'
            ]);
            break;
    }
    
} catch (Exception $e) {
    error_log("Calendar API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Errore interno del server'
    ]);
}

function handleGetEvents($auth, $user, $currentAzienda) {
    $start = $_GET['start'] ?? null;
    $end = $_GET['end'] ?? null;
    $eventId = $_GET['id'] ?? null;
    
    try {
        if ($eventId) {
            // Recupera evento singolo
            $event = getSingleEvent($eventId, $auth, $user, $currentAzienda);
            echo json_encode([
                'success' => true,
                'event' => $event
            ]);
        } else {
            // Recupera eventi per range di date
            $events = getEventsInRange($start, $end, $auth, $user, $currentAzienda);
            echo json_encode([
                'success' => true,
                'events' => $events,
                'count' => count($events)
            ]);
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function handleCreateEvent($auth, $user, $currentAzienda) {
    if (!$auth->canManageEvents()) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Non hai i permessi per creare eventi'
        ]);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Dati non validi'
        ]);
        return;
    }
    
    try {
        db_connection()->beginTransaction();
        
        $titolo = sanitize_input($input['titolo'] ?? '');
        $descrizione = sanitize_input($input['descrizione'] ?? '');
        $data_inizio = $input['data_inizio'] ?? '';
        $data_fine = $input['data_fine'] ?? $data_inizio;
        $luogo = sanitize_input($input['luogo'] ?? '');
        $tipo = sanitize_input($input['tipo'] ?? 'riunione');
        
        if (empty($titolo) || empty($data_inizio)) {
            throw new Exception('Titolo e data di inizio sono obbligatori');
        }
        
        $aziendaId = $currentAzienda['id'] ?? null;
        
        $stmt = db_query(
            "INSERT INTO eventi (titolo, descrizione, data_inizio, data_fine, luogo, tipo, azienda_id, creato_da, creato_il) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [$titolo, $descrizione, $data_inizio, $data_fine, $luogo, $tipo, $aziendaId, $user['id']]
        );
        
        $evento_id = db_connection()->lastInsertId();
        
        // Gestisci partecipanti se forniti
        if (!empty($input['partecipanti'])) {
            foreach ($input['partecipanti'] as $utente_id) {
                db_query(
                    "INSERT INTO evento_partecipanti (evento_id, utente_id, stato_partecipazione, creato_il) 
                     VALUES (?, ?, 'invitato', NOW())",
                    [$evento_id, $utente_id]
                );
            }
        }
        
        db_connection()->commit();
        
        // Recupera l'evento creato
        $newEvent = getSingleEvent($evento_id, $auth, $user, $currentAzienda);
        
        // Invia notifiche email ai partecipanti
        if (!empty($input['partecipanti']) && !empty($input['invia_notifiche'])) {
            try {
                require_once '../utils/NotificationCenter.php';
                $notificationCenter = NotificationCenter::getInstance();
                
                // Recupera i dati dei partecipanti
                $partecipanti = db_query("
                    SELECT u.* FROM utenti u
                    JOIN evento_partecipanti ep ON u.id = ep.utente_id
                    WHERE ep.evento_id = ?
                ", [$evento_id])->fetchAll();
                
                $notificationCenter->notifyEventInvitation($newEvent, $partecipanti);
            } catch (Exception $e) {
                error_log("Errore invio notifiche evento: " . $e->getMessage());
            }
        }
        
        echo json_encode([
            'success' => true,
            'event' => $newEvent,
            'message' => 'Evento creato con successo'
        ]);
        
    } catch (Exception $e) {
        db_connection()->rollback();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function handleUpdateEvent($auth, $user, $currentAzienda) {
    if (!$auth->canManageEvents()) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Non hai i permessi per modificare eventi'
        ]);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $eventId = $input['id'] ?? null;
    
    if (!$eventId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'ID evento mancante'
        ]);
        return;
    }
    
    try {
        // Verifica esistenza e permessi
        $stmt = db_query("SELECT * FROM eventi WHERE id = ?", [$eventId]);
        $evento = $stmt->fetch();
        
        if (!$evento) {
            throw new Exception('Evento non trovato');
        }
        
        if (!$auth->canViewAllEvents() && $evento['creato_da'] != $user['id']) {
            throw new Exception('Non hai i permessi per modificare questo evento');
        }
        
        db_connection()->beginTransaction();
        
        $titolo = sanitize_input($input['titolo'] ?? $evento['titolo']);
        $descrizione = sanitize_input($input['descrizione'] ?? $evento['descrizione']);
        $data_inizio = $input['data_inizio'] ?? $evento['data_inizio'];
        $data_fine = $input['data_fine'] ?? $evento['data_fine'];
        $luogo = sanitize_input($input['luogo'] ?? $evento['luogo']);
        $tipo = sanitize_input($input['tipo'] ?? $evento['tipo']);
        
        db_query(
            "UPDATE eventi SET titolo = ?, descrizione = ?, data_inizio = ?, data_fine = ?, 
             luogo = ?, tipo = ? WHERE id = ?",
            [$titolo, $descrizione, $data_inizio, $data_fine, $luogo, $tipo, $eventId]
        );
        
        // Aggiorna partecipanti se forniti
        if (isset($input['partecipanti'])) {
            db_query("DELETE FROM evento_partecipanti WHERE evento_id = ?", [$eventId]);
            
            foreach ($input['partecipanti'] as $utente_id) {
                db_query(
                    "INSERT INTO evento_partecipanti (evento_id, utente_id, stato_partecipazione, creato_il) 
                     VALUES (?, ?, 'invitato', NOW())",
                    [$eventId, $utente_id]
                );
            }
        }
        
        db_connection()->commit();
        
        $updatedEvent = getSingleEvent($eventId, $auth, $user, $currentAzienda);
        
        // Invia notifiche di modifica se richiesto
        if (!empty($input['invia_notifiche'])) {
            try {
                require_once '../utils/NotificationCenter.php';
                $notificationCenter = NotificationCenter::getInstance();
                
                // Recupera i partecipanti
                $partecipanti = db_query("
                    SELECT u.* FROM utenti u
                    JOIN evento_partecipanti ep ON u.id = ep.utente_id
                    WHERE ep.evento_id = ?
                ", [$eventId])->fetchAll();
                
                // Determina cosa è cambiato
                $modifiche = [];
                if ($evento['titolo'] != $titolo) $modifiche['titolo'] = $titolo;
                if ($evento['data_inizio'] != $data_inizio) $modifiche['data_inizio'] = $data_inizio;
                if ($evento['data_fine'] != $data_fine) $modifiche['data_fine'] = $data_fine;
                if ($evento['luogo'] != $luogo) $modifiche['luogo'] = $luogo;
                
                if (!empty($modifiche)) {
                    $notificationCenter->notifyEventModified($updatedEvent, $partecipanti, $modifiche);
                }
            } catch (Exception $e) {
                error_log("Errore invio notifiche modifica evento: " . $e->getMessage());
            }
        }
        
        echo json_encode([
            'success' => true,
            'event' => $updatedEvent,
            'message' => 'Evento aggiornato con successo'
        ]);
        
    } catch (Exception $e) {
        db_connection()->rollback();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function handleDeleteEvent($auth, $user, $currentAzienda) {
    if (!$auth->canManageEvents()) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Non hai i permessi per eliminare eventi'
        ]);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $eventId = $input['id'] ?? $_GET['id'] ?? null;
    
    if (!$eventId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'ID evento mancante'
        ]);
        return;
    }
    
    try {
        // Verifica esistenza e permessi
        $stmt = db_query("SELECT * FROM eventi WHERE id = ?", [$eventId]);
        $evento = $stmt->fetch();
        
        if (!$evento) {
            throw new Exception('Evento non trovato');
        }
        
        if (!$auth->canViewAllEvents() && $evento['creato_da'] != $user['id']) {
            throw new Exception('Non hai i permessi per eliminare questo evento');
        }
        
        db_connection()->beginTransaction();
        
        // Prima recupera i partecipanti per le notifiche
        $partecipanti = db_query("
            SELECT u.* FROM utenti u
            JOIN evento_partecipanti ep ON u.id = ep.utente_id
            WHERE ep.evento_id = ?
        ", [$eventId])->fetchAll();
        
        // Elimina partecipanti
        db_query("DELETE FROM evento_partecipanti WHERE evento_id = ?", [$eventId]);
        
        // Elimina evento
        db_query("DELETE FROM eventi WHERE id = ?", [$eventId]);
        
        db_connection()->commit();
        
        // Invia notifiche di cancellazione
        if (!empty($partecipanti) && !empty($input['invia_notifiche'])) {
            try {
                require_once '../utils/NotificationCenter.php';
                $notificationCenter = NotificationCenter::getInstance();
                $notificationCenter->notifyEventCancelled($evento, $partecipanti);
            } catch (Exception $e) {
                error_log("Errore invio notifiche cancellazione evento: " . $e->getMessage());
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Evento eliminato con successo'
        ]);
        
    } catch (Exception $e) {
        db_connection()->rollback();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function getEventsInRange($start, $end, $auth, $user, $currentAzienda) {
    $whereClause = "WHERE 1=1";
    $params = [];
    
    // Filtro per azienda se non super admin
    if (!$auth->canViewAllEvents()) {
        if ($currentAzienda) {
            $aziendaId = $currentAzienda['id'] ?? $currentAzienda['azienda_id'] ?? null;
            if ($aziendaId) {
                $whereClause .= " AND (e.azienda_id = ? OR e.creato_da = ?)";
                $params[] = $aziendaId;
                $params[] = $user['id'];
            }
        }
    }
    
    // Filtro per range di date
    if ($start && $end) {
        $whereClause .= " AND DATE(e.data_inizio) BETWEEN ? AND ?";
        $params[] = $start;
        $params[] = $end;
    } elseif ($start) {
        $whereClause .= " AND DATE(e.data_inizio) >= ?";
        $params[] = $start;
    } elseif ($end) {
        $whereClause .= " AND DATE(e.data_inizio) <= ?";
        $params[] = $end;
    }
    
    $sql = "SELECT e.*, 
                   u.nome as creatore_nome, u.cognome as creatore_cognome,
                   COUNT(ep.id) as num_partecipanti,
                   GROUP_CONCAT(CONCAT(up.nome, ' ', up.cognome) SEPARATOR ', ') as partecipanti_nomi
            FROM eventi e 
            LEFT JOIN utenti u ON e.creato_da = u.id 
            LEFT JOIN evento_partecipanti ep ON e.id = ep.evento_id
            LEFT JOIN utenti up ON ep.utente_id = up.id
            $whereClause
            GROUP BY e.id
            ORDER BY e.data_inizio ASC";
    
    $stmt = db_query($sql, $params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getSingleEvent($eventId, $auth, $user, $currentAzienda) {
    $sql = "SELECT e.*, 
                   u.nome as creatore_nome, u.cognome as creatore_cognome,
                   GROUP_CONCAT(ep.utente_id) as partecipanti_ids,
                   GROUP_CONCAT(CONCAT(up.nome, ' ', up.cognome) SEPARATOR ', ') as partecipanti_nomi
            FROM eventi e 
            LEFT JOIN utenti u ON e.creato_da = u.id 
            LEFT JOIN evento_partecipanti ep ON e.id = ep.evento_id
            LEFT JOIN utenti up ON ep.utente_id = up.id
            WHERE e.id = ?
            GROUP BY e.id";
    
    $stmt = db_query($sql, [$eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        throw new Exception('Evento non trovato');
    }
    
    // Verifica permessi
    if (!$auth->canViewAllEvents() && $event['creato_da'] != $user['id']) {
        if ($currentAzienda) {
            $aziendaId = $currentAzienda['id'] ?? $currentAzienda['azienda_id'] ?? null;
            if ($event['azienda_id'] != $aziendaId) {
                throw new Exception('Non hai i permessi per visualizzare questo evento');
            }
        } else {
            throw new Exception('Non hai i permessi per visualizzare questo evento');
        }
    }
    
    // Converti partecipanti_ids in array
    if ($event['partecipanti_ids']) {
        $event['partecipanti'] = explode(',', $event['partecipanti_ids']);
    } else {
        $event['partecipanti'] = [];
    }
    
    return $event;
}
?>