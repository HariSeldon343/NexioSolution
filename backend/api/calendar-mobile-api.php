<?php
/**
 * API Mobile Calendar - Endpoint dedicato per l'app PWA
 * Gestisce autenticazione, sicurezza CSRF e operazioni specifiche mobile
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/config.php';
require_once '../utils/CSRFTokenManager.php';
require_once '../utils/EventInvite.php';
require_once '../utils/NotificationCenter.php';

try {
    $auth = Auth::getInstance();
    
    // Verifica autenticazione per tutti i endpoints
    if (!$auth->isAuthenticated()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Autenticazione richiesta',
            'code' => 'UNAUTHORIZED'
        ]);
        exit;
    }
    
    $user = $auth->getUser();
    $currentAzienda = $auth->getCurrentAzienda();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? $_POST['action'] ?? 'default';
    
    // CSRF Protection per operazioni di modifica
    if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
        $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null;
        $csrfManager = CSRFTokenManager::getInstance();
        
        if (!$csrfManager->validateToken($csrfToken)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'Token CSRF non valido',
                'code' => 'CSRF_INVALID'
            ]);
            exit;
        }
    }
    
    // Route delle azioni
    switch ($action) {
        case 'auth_check':
            handleAuthCheck($auth, $user, $currentAzienda);
            break;
            
        case 'events':
            handleEventsRequest($method, $auth, $user, $currentAzienda);
            break;
            
        case 'event_details':
            handleEventDetails($auth, $user, $currentAzienda);
            break;
            
        case 'sync':
            handleMobileSync($auth, $user, $currentAzienda);
            break;
            
        case 'push_subscription':
            handlePushSubscription($method, $auth, $user);
            break;
            
        case 'preferences':
            handleMobilePreferences($method, $auth, $user);
            break;
            
        case 'offline_cache':
            handleOfflineCache($auth, $user, $currentAzienda);
            break;
            
        case 'statistics':
            handleMobileStatistics($auth, $user, $currentAzienda);
            break;
            
        case 'export':
            handleMobileExport($auth, $user, $currentAzienda);
            break;
            
        case 'csrf_token':
            handleCSRFToken();
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Azione non supportata',
                'code' => 'INVALID_ACTION'
            ]);
            break;
    }
    
} catch (Exception $e) {
    error_log("Calendar Mobile API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Errore interno del server',
        'code' => 'INTERNAL_ERROR',
        'message' => APP_ENV === 'development' ? $e->getMessage() : null
    ]);
}

function handleAuthCheck($auth, $user, $currentAzienda) {
    // Verifica stato autenticazione e permessi
    $permissions = [
        'canManageEvents' => $auth->canManageEvents(),
        'canViewAllEvents' => $auth->canViewAllEvents(),
        'isSuperAdmin' => $auth->isSuperAdmin(),
        'isUtenteSpeciale' => $auth->isUtenteSpeciale()
    ];
    
    // Statistiche rapide
    $stats = [];
    try {
        $whereClause = "WHERE 1=1";
        $params = [];
        
        // Filtro per azienda se necessario
        if (!$auth->canViewAllEvents() && $currentAzienda) {
            $aziendaId = $currentAzienda['id'] ?? $currentAzienda['azienda_id'] ?? null;
            if ($aziendaId) {
                $whereClause .= " AND (azienda_id = ? OR creata_da = ?)";
                $params[] = $aziendaId;
                $params[] = $user['id'];
            }
        }
        
        // Eventi oggi
        $todayQuery = "SELECT COUNT(*) as count FROM eventi $whereClause AND DATE(data_inizio) = CURDATE()";
        $stmt = db_query($todayQuery, $params);
        $stats['todayEvents'] = intval($stmt->fetch()['count']);
        
        // Eventi questo mese
        $monthQuery = "SELECT COUNT(*) as count FROM eventi $whereClause AND YEAR(data_inizio) = YEAR(NOW()) AND MONTH(data_inizio) = MONTH(NOW())";
        $stmt = db_query($monthQuery, $params);
        $stats['monthEvents'] = intval($stmt->fetch()['count']);
        
        // Prossimo evento
        $nextEventQuery = "SELECT id, titolo, data_inizio FROM eventi $whereClause AND data_inizio > NOW() ORDER BY data_inizio ASC LIMIT 1";
        $stmt = db_query($nextEventQuery, $params);
        $nextEvent = $stmt->fetch();
        $stats['nextEvent'] = $nextEvent ?: null;
        
    } catch (Exception $e) {
        error_log("Stats error: " . $e->getMessage());
        $stats = ['todayEvents' => 0, 'monthEvents' => 0, 'nextEvent' => null];
    }
    
    echo json_encode([
        'success' => true,
        'auth' => [
            'authenticated' => true,
            'user' => [
                'id' => $user['id'],
                'nome' => $user['nome'] ?? '',
                'cognome' => $user['cognome'] ?? '',
                'email' => $user['email'] ?? '',
                'ruolo' => $user['ruolo'] ?? ''
            ],
            'azienda' => $currentAzienda ? [
                'id' => $currentAzienda['id'] ?? $currentAzienda['azienda_id'] ?? null,
                'nome' => $currentAzienda['nome'] ?? ''
            ] : null,
            'permissions' => $permissions,
            'statistics' => $stats,
            'timestamp' => date('c'),
            'csrf_token' => CSRFTokenManager::getInstance()->generateToken()
        ]
    ]);
}

function handleEventsRequest($method, $auth, $user, $currentAzienda) {
    switch ($method) {
        case 'GET':
            getMobileEvents($auth, $user, $currentAzienda);
            break;
        case 'POST':
            createMobileEvent($auth, $user, $currentAzienda);
            break;
        case 'PUT':
            updateMobileEvent($auth, $user, $currentAzienda);
            break;
        case 'DELETE':
            deleteMobileEvent($auth, $user, $currentAzienda);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Metodo non supportato']);
            break;
    }
}

function getMobileEvents($auth, $user, $currentAzienda) {
    $start = $_GET['start'] ?? null;
    $end = $_GET['end'] ?? null;
    $limit = intval($_GET['limit'] ?? 100);
    $offset = intval($_GET['offset'] ?? 0);
    
    if ($limit > 500) $limit = 500; // Max 500 events per request
    
    try {
        $whereClause = "WHERE 1=1";
        $params = [];
        
        // Filtro per azienda se non super admin
        if (!$auth->canViewAllEvents()) {
            if ($currentAzienda) {
                $aziendaId = $currentAzienda['id'] ?? $currentAzienda['azienda_id'] ?? null;
                if ($aziendaId) {
                    $whereClause .= " AND (e.azienda_id = ? OR e.creata_da = ?)";
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
                       a.nome as azienda_nome,
                       COUNT(ep.id) as num_partecipanti
                FROM eventi e 
                LEFT JOIN utenti u ON e.creata_da = u.id 
                LEFT JOIN aziende a ON e.azienda_id = a.id
                LEFT JOIN evento_partecipanti ep ON e.id = ep.evento_id
                $whereClause
                GROUP BY e.id
                ORDER BY e.data_inizio ASC
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = db_query($sql, $params);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Count total events for pagination
        $countSql = "SELECT COUNT(DISTINCT e.id) as total FROM eventi e $whereClause";
        $countParams = array_slice($params, 0, -2); // Remove limit and offset
        $stmt = db_query($countSql, $countParams);
        $total = intval($stmt->fetch()['total']);
        
        echo json_encode([
            'success' => true,
            'events' => $events,
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'hasMore' => ($offset + $limit) < $total
            ],
            'filters' => [
                'start' => $start,
                'end' => $end
            ],
            'timestamp' => date('c')
        ]);
        
    } catch (Exception $e) {
        throw new Exception("Errore nel recupero eventi: " . $e->getMessage());
    }
}

function createMobileEvent($auth, $user, $currentAzienda) {
    if (!$auth->canManageEvents()) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Non hai i permessi per creare eventi',
            'code' => 'PERMISSION_DENIED'
        ]);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Dati non validi',
            'code' => 'INVALID_DATA'
        ]);
        return;
    }
    
    try {
        db_connection()->beginTransaction();
        
        // Validazione dati
        $titolo = sanitize_input($input['titolo'] ?? '');
        $descrizione = sanitize_input($input['descrizione'] ?? '');
        $data_inizio = $input['data_inizio'] ?? '';
        $data_fine = $input['data_fine'] ?? $data_inizio;
        $luogo = sanitize_input($input['luogo'] ?? '');
        $tipo = sanitize_input($input['tipo'] ?? 'riunione');
        
        if (empty($titolo) || empty($data_inizio)) {
            throw new Exception('Titolo e data di inizio sono obbligatori');
        }
        
        // Validazione formato date
        if (!validateDateTimeFormat($data_inizio)) {
            throw new Exception('Formato data di inizio non valido');
        }
        
        if ($data_fine !== $data_inizio && !validateDateTimeFormat($data_fine)) {
            throw new Exception('Formato data di fine non valido');
        }
        
        $aziendaId = $currentAzienda['id'] ?? null;
        
        // Inserimento evento
        $stmt = db_query(
            "INSERT INTO eventi (titolo, descrizione, data_inizio, data_fine, luogo, tipo, azienda_id, creata_da, creato_il) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [$titolo, $descrizione, $data_inizio, $data_fine, $luogo, $tipo, $aziendaId, $user['id']]
        );
        
        $evento_id = db_connection()->lastInsertId();
        
        // Gestisci partecipanti se forniti
        if (!empty($input['partecipanti'])) {
            foreach ($input['partecipanti'] as $utente_id) {
                if (is_numeric($utente_id)) {
                    db_query(
                        "INSERT INTO evento_partecipanti (evento_id, utente_id, stato_partecipazione, creato_il) 
                         VALUES (?, ?, 'invitato', NOW())",
                        [$evento_id, $utente_id]
                    );
                }
            }
        }
        
        db_connection()->commit();
        
        // Recupera l'evento creato
        $newEvent = getSingleMobileEvent($evento_id, $auth, $user, $currentAzienda);
        
        // Invia notifiche email ai partecipanti se richiesto
        if (!empty($input['partecipanti']) && !empty($input['invia_notifiche'])) {
            try {
                $notificationCenter = NotificationCenter::getInstance();
                $partecipanti = db_query("
                    SELECT u.* FROM utenti u
                    JOIN evento_partecipanti ep ON u.id = ep.utente_id
                    WHERE ep.evento_id = ?
                ", [$evento_id])->fetchAll();
                
                $notificationCenter->notifyEventInvitation($newEvent, $partecipanti);
            } catch (Exception $e) {
                error_log("Errore invio notifiche evento mobile: " . $e->getMessage());
            }
        }
        
        // Log attività
        logActivity($user['id'], 'event_create', "Creato evento: {$titolo}", [
            'evento_id' => $evento_id,
            'from_mobile' => true
        ]);
        
        echo json_encode([
            'success' => true,
            'event' => $newEvent,
            'message' => 'Evento creato con successo',
            'id' => $evento_id
        ]);
        
    } catch (Exception $e) {
        if (db_connection()->inTransaction()) {
            db_connection()->rollback();
        }
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'code' => 'CREATE_ERROR'
        ]);
    }
}

function updateMobileEvent($auth, $user, $currentAzienda) {
    if (!$auth->canManageEvents()) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Non hai i permessi per modificare eventi',
            'code' => 'PERMISSION_DENIED'
        ]);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $eventId = $input['id'] ?? null;
    
    if (!$eventId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'ID evento mancante',
            'code' => 'MISSING_ID'
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
        
        if (!$auth->canViewAllEvents() && $evento['creata_da'] != $user['id']) {
            throw new Exception('Non hai i permessi per modificare questo evento');
        }
        
        db_connection()->beginTransaction();
        
        // Aggiorna campi evento
        $titolo = sanitize_input($input['titolo'] ?? $evento['titolo']);
        $descrizione = sanitize_input($input['descrizione'] ?? $evento['descrizione']);
        $data_inizio = $input['data_inizio'] ?? $evento['data_inizio'];
        $data_fine = $input['data_fine'] ?? $evento['data_fine'];
        $luogo = sanitize_input($input['luogo'] ?? $evento['luogo']);
        $tipo = sanitize_input($input['tipo'] ?? $evento['tipo']);
        
        // Validazione
        if (!validateDateTimeFormat($data_inizio)) {
            throw new Exception('Formato data di inizio non valido');
        }
        
        if ($data_fine && !validateDateTimeFormat($data_fine)) {
            throw new Exception('Formato data di fine non valido');
        }
        
        db_query(
            "UPDATE eventi SET titolo = ?, descrizione = ?, data_inizio = ?, data_fine = ?, 
             luogo = ?, tipo = ?, aggiornato_il = NOW() WHERE id = ?",
            [$titolo, $descrizione, $data_inizio, $data_fine, $luogo, $tipo, $eventId]
        );
        
        // Aggiorna partecipanti se forniti
        if (isset($input['partecipanti'])) {
            db_query("DELETE FROM evento_partecipanti WHERE evento_id = ?", [$eventId]);
            
            foreach ($input['partecipanti'] as $utente_id) {
                if (is_numeric($utente_id)) {
                    db_query(
                        "INSERT INTO evento_partecipanti (evento_id, utente_id, stato_partecipazione, creato_il) 
                         VALUES (?, ?, 'invitato', NOW())",
                        [$eventId, $utente_id]
                    );
                }
            }
        }
        
        db_connection()->commit();
        
        $updatedEvent = getSingleMobileEvent($eventId, $auth, $user, $currentAzienda);
        
        // Invia notifiche di modifica se richiesto
        if (!empty($input['invia_notifiche'])) {
            try {
                $notificationCenter = NotificationCenter::getInstance();
                $partecipanti = db_query("
                    SELECT u.* FROM utenti u
                    JOIN evento_partecipanti ep ON u.id = ep.utente_id
                    WHERE ep.evento_id = ?
                ", [$eventId])->fetchAll();
                
                $modifiche = [];
                if ($evento['titolo'] != $titolo) $modifiche['titolo'] = $titolo;
                if ($evento['data_inizio'] != $data_inizio) $modifiche['data_inizio'] = $data_inizio;
                if ($evento['data_fine'] != $data_fine) $modifiche['data_fine'] = $data_fine;
                if ($evento['luogo'] != $luogo) $modifiche['luogo'] = $luogo;
                
                if (!empty($modifiche)) {
                    $notificationCenter->notifyEventModified($updatedEvent, $partecipanti, $modifiche);
                }
            } catch (Exception $e) {
                error_log("Errore invio notifiche modifica evento mobile: " . $e->getMessage());
            }
        }
        
        // Log attività
        logActivity($user['id'], 'event_update', "Modificato evento: {$titolo}", [
            'evento_id' => $eventId,
            'from_mobile' => true
        ]);
        
        echo json_encode([
            'success' => true,
            'event' => $updatedEvent,
            'message' => 'Evento aggiornato con successo'
        ]);
        
    } catch (Exception $e) {
        if (db_connection()->inTransaction()) {
            db_connection()->rollback();
        }
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'code' => 'UPDATE_ERROR'
        ]);
    }
}

function deleteMobileEvent($auth, $user, $currentAzienda) {
    if (!$auth->canManageEvents()) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Non hai i permessi per eliminare eventi',
            'code' => 'PERMISSION_DENIED'
        ]);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $eventId = $input['id'] ?? $_GET['id'] ?? null;
    
    if (!$eventId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'ID evento mancante',
            'code' => 'MISSING_ID'
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
        
        if (!$auth->canViewAllEvents() && $evento['creata_da'] != $user['id']) {
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
                $notificationCenter = NotificationCenter::getInstance();
                $notificationCenter->notifyEventCancelled($evento, $partecipanti);
            } catch (Exception $e) {
                error_log("Errore invio notifiche cancellazione evento mobile: " . $e->getMessage());
            }
        }
        
        // Log attività
        logActivity($user['id'], 'event_delete', "Eliminato evento: {$evento['titolo']}", [
            'evento_id' => $eventId,
            'from_mobile' => true
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Evento eliminato con successo'
        ]);
        
    } catch (Exception $e) {
        if (db_connection()->inTransaction()) {
            db_connection()->rollback();
        }
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'code' => 'DELETE_ERROR'
        ]);
    }
}

function handleEventDetails($auth, $user, $currentAzienda) {
    $eventId = $_GET['id'] ?? null;
    
    if (!$eventId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'ID evento mancante',
            'code' => 'MISSING_ID'
        ]);
        return;
    }
    
    try {
        $event = getSingleMobileEvent($eventId, $auth, $user, $currentAzienda);
        
        // Carica anche i partecipanti dettagliati
        $partecipanti = db_query("
            SELECT u.id, u.nome, u.cognome, u.email, ep.stato_partecipazione
            FROM utenti u
            JOIN evento_partecipanti ep ON u.id = ep.utente_id
            WHERE ep.evento_id = ?
            ORDER BY u.nome, u.cognome
        ", [$eventId])->fetchAll(PDO::FETCH_ASSOC);
        
        $event['partecipanti_dettagli'] = $partecipanti;
        
        echo json_encode([
            'success' => true,
            'event' => $event
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'code' => 'EVENT_NOT_FOUND'
        ]);
    }
}

function handleMobileSync($auth, $user, $currentAzienda) {
    $lastSync = $_GET['lastSync'] ?? null;
    $force = $_GET['force'] === 'true';
    
    try {
        $whereClause = "WHERE 1=1";
        $params = [];
        
        // Filtro per azienda se non super admin
        if (!$auth->canViewAllEvents()) {
            if ($currentAzienda) {
                $aziendaId = $currentAzienda['id'] ?? $currentAzienda['azienda_id'] ?? null;
                if ($aziendaId) {
                    $whereClause .= " AND (e.azienda_id = ? OR e.creata_da = ?)";
                    $params[] = $aziendaId;
                    $params[] = $user['id'];
                }
            }
        }
        
        // Solo eventi modificati dopo l'ultimo sync (se non force)
        if ($lastSync && !$force) {
            $whereClause .= " AND (e.creato_il > ? OR e.aggiornato_il > ?)";
            $params[] = $lastSync;
            $params[] = $lastSync;
        }
        
        $sql = "SELECT e.*, 
                       u.nome as creatore_nome, u.cognome as creatore_cognome,
                       a.nome as azienda_nome,
                       COUNT(ep.id) as num_partecipanti
                FROM eventi e 
                LEFT JOIN utenti u ON e.creata_da = u.id 
                LEFT JOIN aziende a ON e.azienda_id = a.id
                LEFT JOIN evento_partecipanti ep ON e.id = ep.evento_id
                $whereClause
                GROUP BY e.id
                ORDER BY e.data_inizio ASC";
        
        $stmt = db_query($sql, $params);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $syncTime = date('c');
        
        echo json_encode([
            'success' => true,
            'sync' => [
                'events' => $events,
                'count' => count($events),
                'lastSync' => $lastSync,
                'newSync' => $syncTime,
                'force' => $force
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception("Errore nella sincronizzazione mobile: " . $e->getMessage());
    }
}

function handlePushSubscription($method, $auth, $user) {
    if ($method === 'POST') {
        // Salva subscription push
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['subscription'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Subscription mancante']);
            return;
        }
        
        try {
            $subscription = json_encode($input['subscription']);
            
            db_query(
                "INSERT INTO push_subscriptions (utente_id, subscription_data, created_at) 
                 VALUES (?, ?, NOW()) 
                 ON DUPLICATE KEY UPDATE subscription_data = ?, updated_at = NOW()",
                [$user['id'], $subscription, $subscription]
            );
            
            echo json_encode(['success' => true, 'message' => 'Subscription salvata']);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Errore salvataggio subscription']);
        }
        
    } elseif ($method === 'DELETE') {
        // Rimuovi subscription push
        try {
            db_query("DELETE FROM push_subscriptions WHERE utente_id = ?", [$user['id']]);
            echo json_encode(['success' => true, 'message' => 'Subscription rimossa']);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Errore rimozione subscription']);
        }
    }
}

function handleMobilePreferences($method, $auth, $user) {
    if ($method === 'POST') {
        // Salva preferenze mobile
        $input = json_decode(file_get_contents('php://input'), true);
        
        try {
            $preferences = json_encode($input['preferences'] ?? []);
            
            db_query(
                "INSERT INTO user_preferences (utente_id, tipo, valore, aggiornato_il) 
                 VALUES (?, 'mobile_calendar', ?, NOW()) 
                 ON DUPLICATE KEY UPDATE valore = ?, aggiornato_il = NOW()",
                [$user['id'], $preferences, $preferences]
            );
            
            echo json_encode(['success' => true, 'message' => 'Preferenze salvate']);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Errore salvataggio preferenze']);
        }
        
    } else {
        // Recupera preferenze mobile
        try {
            $stmt = db_query(
                "SELECT valore FROM user_preferences 
                 WHERE utente_id = ? AND tipo = 'mobile_calendar'",
                [$user['id']]
            );
            
            $preferences = $stmt->fetch();
            $preferencesData = $preferences ? json_decode($preferences['valore'], true) : [];
            
            // Preferenze default mobile
            $defaultPreferences = [
                'defaultView' => 'month',
                'startWeek' => 'monday',
                'timeFormat' => '24h',
                'notifications' => true,
                'theme' => 'auto',
                'syncInterval' => 300, // 5 minutes
                'offlineMode' => true,
                'touchGestures' => true
            ];
            
            $finalPreferences = array_merge($defaultPreferences, $preferencesData);
            
            echo json_encode([
                'success' => true,
                'preferences' => $finalPreferences
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Errore recupero preferenze']);
        }
    }
}

function handleOfflineCache($auth, $user, $currentAzienda) {
    // Fornisce dati per cache offline
    try {
        $cacheData = [
            'events' => [],
            'users' => [],
            'eventTypes' => [
                'riunione' => 'Riunione',
                'appuntamento' => 'Appuntamento',
                'scadenza' => 'Scadenza',
                'evento' => 'Evento',
                'altro' => 'Altro'
            ]
        ];
        
        // Eventi recenti e futuri
        $whereClause = "WHERE e.data_inizio >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $params = [];
        
        if (!$auth->canViewAllEvents() && $currentAzienda) {
            $aziendaId = $currentAzienda['id'] ?? $currentAzienda['azienda_id'] ?? null;
            if ($aziendaId) {
                $whereClause .= " AND (e.azienda_id = ? OR e.creata_da = ?)";
                $params[] = $aziendaId;
                $params[] = $user['id'];
            }
        }
        
        $sql = "SELECT e.*, u.nome as creatore_nome, u.cognome as creatore_cognome
                FROM eventi e 
                LEFT JOIN utenti u ON e.creata_da = u.id 
                $whereClause
                ORDER BY e.data_inizio ASC
                LIMIT 200";
        
        $stmt = db_query($sql, $params);
        $cacheData['events'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Utenti per partecipanti (se permessi)
        if ($auth->canManageEvents()) {
            $usersQuery = "SELECT id, nome, cognome, email FROM utenti WHERE attivo = 1";
            if ($currentAzienda && !$auth->isSuperAdmin()) {
                $usersQuery .= " AND (azienda_id = ? OR id = ?)";
                $userParams = [$currentAzienda['id'] ?? 0, $user['id']];
            } else {
                $userParams = [];
            }
            
            $stmt = db_query($usersQuery, $userParams);
            $cacheData['users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        echo json_encode([
            'success' => true,
            'cache' => $cacheData,
            'timestamp' => date('c')
        ]);
        
    } catch (Exception $e) {
        throw new Exception("Errore generazione cache offline: " . $e->getMessage());
    }
}

function handleMobileStatistics($auth, $user, $currentAzienda) {
    $period = $_GET['period'] ?? 'month';
    
    try {
        $stats = [];
        $now = new DateTime();
        
        // Definisci periodo
        switch ($period) {
            case 'week':
                $start = $now->modify('monday this week')->format('Y-m-d 00:00:00');
                $end = $now->modify('sunday this week')->format('Y-m-d 23:59:59');
                break;
            case 'year':
                $start = $now->format('Y-01-01 00:00:00');
                $end = $now->format('Y-12-31 23:59:59');
                break;
            case 'month':
            default:
                $start = $now->format('Y-m-01 00:00:00');
                $end = $now->format('Y-m-t 23:59:59');
                break;
        }
        
        $whereClause = "WHERE data_inizio BETWEEN ? AND ?";
        $params = [$start, $end];
        
        // Filtro per azienda
        if (!$auth->canViewAllEvents() && $currentAzienda) {
            $aziendaId = $currentAzienda['id'] ?? $currentAzienda['azienda_id'] ?? null;
            if ($aziendaId) {
                $whereClause .= " AND (azienda_id = ? OR creata_da = ?)";
                $params[] = $aziendaId;
                $params[] = $user['id'];
            }
        }
        
        // Totale eventi
        $stmt = db_query("SELECT COUNT(*) as total FROM eventi $whereClause", $params);
        $stats['totalEvents'] = intval($stmt->fetch()['total']);
        
        // Eventi per tipo
        $stmt = db_query(
            "SELECT tipo, COUNT(*) as count FROM eventi $whereClause GROUP BY tipo ORDER BY count DESC",
            $params
        );
        $stats['eventsByType'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Eventi per settimana/giorno
        $groupBy = $period === 'year' ? 'YEARWEEK(data_inizio)' : 'DATE(data_inizio)';
        $stmt = db_query(
            "SELECT $groupBy as period, COUNT(*) as count 
             FROM eventi $whereClause 
             GROUP BY $groupBy 
             ORDER BY period ASC",
            $params
        );
        $stats['eventsOverTime'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'statistics' => $stats,
            'period' => $period,
            'range' => ['start' => $start, 'end' => $end]
        ]);
        
    } catch (Exception $e) {
        throw new Exception("Errore calcolo statistiche mobile: " . $e->getMessage());
    }
}

function handleMobileExport($auth, $user, $currentAzienda) {
    $format = $_GET['format'] ?? 'ics';
    $period = $_GET['period'] ?? 'month';
    
    if ($format !== 'ics') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Formato non supportato']);
        return;
    }
    
    try {
        // Stesso logic dell'export normale ma ottimizzato per mobile
        $events = getEventsForExport($period, $auth, $user, $currentAzienda);
        $icsContent = generateMobileICS($events);
        
        echo json_encode([
            'success' => true,
            'export' => [
                'format' => $format,
                'period' => $period,
                'count' => count($events),
                'content' => base64_encode($icsContent),
                'filename' => "nexio-mobile-calendar-{$period}-" . date('Y-m-d') . ".ics"
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception("Errore esportazione mobile: " . $e->getMessage());
    }
}

function handleCSRFToken() {
    $csrfManager = CSRFTokenManager::getInstance();
    
    echo json_encode([
        'success' => true,
        'token' => $csrfManager->generateToken()
    ]);
}

// Utility Functions

function getSingleMobileEvent($eventId, $auth, $user, $currentAzienda) {
    $sql = "SELECT e.*, 
                   u.nome as creatore_nome, u.cognome as creatore_cognome,
                   a.nome as azienda_nome,
                   GROUP_CONCAT(ep.utente_id) as partecipanti_ids,
                   GROUP_CONCAT(CONCAT(up.nome, ' ', up.cognome) SEPARATOR ', ') as partecipanti_nomi
            FROM eventi e 
            LEFT JOIN utenti u ON e.creata_da = u.id 
            LEFT JOIN aziende a ON e.azienda_id = a.id
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
    if (!$auth->canViewAllEvents() && $event['creata_da'] != $user['id']) {
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
        $event['partecipanti'] = array_map('intval', explode(',', $event['partecipanti_ids']));
    } else {
        $event['partecipanti'] = [];
    }
    
    return $event;
}

function getEventsForExport($period, $auth, $user, $currentAzienda) {
    $now = new DateTime();
    
    switch ($period) {
        case 'week':
            $start = $now->modify('monday this week')->format('Y-m-d');
            $end = $now->modify('sunday this week')->format('Y-m-d');
            break;
        case 'year':
            $start = $now->format('Y-01-01');
            $end = $now->format('Y-12-31');
            break;
        case 'all':
            $start = null;
            $end = null;
            break;
        case 'month':
        default:
            $start = $now->format('Y-m-01');
            $end = $now->format('Y-m-t');
            break;
    }
    
    $whereClause = "WHERE 1=1";
    $params = [];
    
    if ($start && $end) {
        $whereClause .= " AND DATE(data_inizio) BETWEEN ? AND ?";
        $params[] = $start;
        $params[] = $end;
    }
    
    // Filtro per azienda
    if (!$auth->canViewAllEvents() && $currentAzienda) {
        $aziendaId = $currentAzienda['id'] ?? $currentAzienda['azienda_id'] ?? null;
        if ($aziendaId) {
            $whereClause .= " AND (azienda_id = ? OR creata_da = ?)";
            $params[] = $aziendaId;
            $params[] = $user['id'];
        }
    }
    
    $stmt = db_query(
        "SELECT * FROM eventi $whereClause ORDER BY data_inizio ASC LIMIT 1000",
        $params
    );
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function generateMobileICS($events) {
    $ics = "BEGIN:VCALENDAR\r\n";
    $ics .= "VERSION:2.0\r\n";
    $ics .= "PRODID:-//Nexio Mobile//Calendar//IT\r\n";
    $ics .= "CALSCALE:GREGORIAN\r\n";
    $ics .= "METHOD:PUBLISH\r\n";
    
    foreach ($events as $event) {
        $ics .= "BEGIN:VEVENT\r\n";
        $ics .= "UID:mobile-" . $event['id'] . "@nexio.calendar\r\n";
        $ics .= "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
        
        $start = new DateTime($event['data_inizio']);
        $ics .= "DTSTART:" . $start->format('Ymd\THis\Z') . "\r\n";
        
        if ($event['data_fine'] && $event['data_fine'] !== $event['data_inizio']) {
            $end = new DateTime($event['data_fine']);
            $ics .= "DTEND:" . $end->format('Ymd\THis\Z') . "\r\n";
        }
        
        $ics .= "SUMMARY:" . str_replace(["\r", "\n", ","], ["", "\\n", "\\,"], $event['titolo']) . "\r\n";
        
        if ($event['descrizione']) {
            $ics .= "DESCRIPTION:" . str_replace(["\r", "\n", ","], ["", "\\n", "\\,"], $event['descrizione']) . "\r\n";
        }
        
        if ($event['luogo']) {
            $ics .= "LOCATION:" . str_replace(["\r", "\n", ","], ["", "\\n", "\\,"], $event['luogo']) . "\r\n";
        }
        
        $ics .= "CATEGORIES:" . strtoupper($event['tipo']) . "\r\n";
        $ics .= "STATUS:CONFIRMED\r\n";
        $ics .= "TRANSP:OPAQUE\r\n";
        
        $ics .= "END:VEVENT\r\n";
    }
    
    $ics .= "END:VCALENDAR\r\n";
    
    return $ics;
}

function validateDateTimeFormat($datetime) {
    $formats = [
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'Y-m-d\TH:i:s',
        'Y-m-d\TH:i'
    ];
    
    foreach ($formats as $format) {
        $d = DateTime::createFromFormat($format, $datetime);
        if ($d && $d->format($format) === $datetime) {
            return true;
        }
    }
    
    return false;
}

function logActivity($userId, $action, $description, $metadata = []) {
    try {
        require_once '../utils/ActivityLogger.php';
        $logger = ActivityLogger::getInstance();
        $logger->log($userId, $action, $description, $metadata);
    } catch (Exception $e) {
        error_log("Activity log error: " . $e->getMessage());
    }
}

?>