<?php
/**
 * API Calendario - Endpoint generale per funzioni di calendario
 * Gestisce operazioni di sincronizzazione e utility per il calendario mobile
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/config.php';

try {
    $auth = Auth::getInstance();
    
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
    $action = $_GET['action'] ?? $_POST['action'] ?? 'status';
    
    switch ($action) {
        case 'status':
            handleStatus($auth, $user, $currentAzienda);
            break;
            
        case 'sync':
            handleSync($auth, $user, $currentAzienda);
            break;
            
        case 'preferences':
            handlePreferences($auth, $user);
            break;
            
        case 'statistics':
            handleStatistics($auth, $user, $currentAzienda);
            break;
            
        case 'export':
            handleExport($auth, $user, $currentAzienda);
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Azione non supportata'
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

function handleStatus($auth, $user, $currentAzienda) {
    $now = new DateTime();
    $today = $now->format('Y-m-d');
    $thisMonth = $now->format('Y-m');
    
    try {
        // Conta eventi oggi
        $todayEventsQuery = "SELECT COUNT(*) as count FROM eventi WHERE DATE(data_inizio) = ?";
        $params = [$today];
        
        if (!$auth->canViewAllEvents() && $currentAzienda) {
            $aziendaId = $currentAzienda['id'] ?? $currentAzienda['azienda_id'] ?? null;
            if ($aziendaId) {
                $todayEventsQuery .= " AND (azienda_id = ? OR creato_da = ?)";
                $params[] = $aziendaId;
                $params[] = $user['id'];
            }
        }
        
        $stmt = db_query($todayEventsQuery, $params);
        $todayEvents = $stmt->fetch()['count'];
        
        // Conta eventi questo mese
        $monthEventsQuery = "SELECT COUNT(*) as count FROM eventi WHERE DATE_FORMAT(data_inizio, '%Y-%m') = ?";
        $params = [$thisMonth];
        
        if (!$auth->canViewAllEvents() && $currentAzienda) {
            $aziendaId = $currentAzienda['id'] ?? $currentAzienda['azienda_id'] ?? null;
            if ($aziendaId) {
                $monthEventsQuery .= " AND (azienda_id = ? OR creato_da = ?)";
                $params[] = $aziendaId;
                $params[] = $user['id'];
            }
        }
        
        $stmt = db_query($monthEventsQuery, $params);
        $monthEvents = $stmt->fetch()['count'];
        
        // Prossimo evento
        $nextEventQuery = "SELECT id, titolo, data_inizio FROM eventi 
                          WHERE data_inizio > NOW() ";
        $params = [];
        
        if (!$auth->canViewAllEvents() && $currentAzienda) {
            $aziendaId = $currentAzienda['id'] ?? $currentAzienda['azienda_id'] ?? null;
            if ($aziendaId) {
                $nextEventQuery .= " AND (azienda_id = ? OR creato_da = ?)";
                $params[] = $aziendaId;
                $params[] = $user['id'];
            }
        }
        
        $nextEventQuery .= " ORDER BY data_inizio ASC LIMIT 1";
        
        $stmt = db_query($nextEventQuery, $params);
        $nextEvent = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'status' => [
                'user' => [
                    'id' => $user['id'],
                    'nome' => $user['nome'] ?? '',
                    'cognome' => $user['cognome'] ?? '',
                    'ruolo' => $user['ruolo'] ?? ''
                ],
                'azienda' => $currentAzienda ? [
                    'id' => $currentAzienda['id'] ?? $currentAzienda['azienda_id'] ?? null,
                    'nome' => $currentAzienda['nome'] ?? ''
                ] : null,
                'permissions' => [
                    'canManageEvents' => $auth->canManageEvents(),
                    'canViewAllEvents' => $auth->canViewAllEvents(),
                    'isSuperAdmin' => $auth->isSuperAdmin()
                ],
                'statistics' => [
                    'todayEvents' => intval($todayEvents),
                    'monthEvents' => intval($monthEvents),
                    'nextEvent' => $nextEvent
                ],
                'timestamp' => $now->format('c')
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception("Errore nel recupero dello status: " . $e->getMessage());
    }
}

function handleSync($auth, $user, $currentAzienda) {
    $lastSync = $_GET['lastSync'] ?? $_POST['lastSync'] ?? null;
    
    try {
        $whereClause = "WHERE 1=1";
        $params = [];
        
        // Filtro per azienda se non super admin
        if (!$auth->canViewAllEvents() && $currentAzienda) {
            $aziendaId = $currentAzienda['id'] ?? $currentAzienda['azienda_id'] ?? null;
            if ($aziendaId) {
                $whereClause .= " AND (azienda_id = ? OR creato_da = ?)";
                $params[] = $aziendaId;
                $params[] = $user['id'];
            }
        }
        
        // Solo eventi modificati dopo l'ultimo sync
        if ($lastSync) {
            $whereClause .= " AND (creato_il > ? OR aggiornato_il > ?)";
            $params[] = $lastSync;
            $params[] = $lastSync;
        }
        
        $sql = "SELECT e.*, 
                       u.nome as creatore_nome, u.cognome as creatore_cognome
                FROM eventi e 
                LEFT JOIN utenti u ON e.creato_da = u.id 
                $whereClause
                ORDER BY e.data_inizio ASC";
        
        $stmt = db_query($sql, $params);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'sync' => [
                'events' => $events,
                'count' => count($events),
                'lastSync' => $lastSync,
                'newSync' => date('c')
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception("Errore nella sincronizzazione: " . $e->getMessage());
    }
}

function handlePreferences($auth, $user) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Salva preferenze
        $input = json_decode(file_get_contents('php://input'), true);
        
        try {
            $preferences = json_encode($input['preferences'] ?? []);
            
            // Salva o aggiorna preferenze utente
            db_query(
                "INSERT INTO user_preferences (utente_id, tipo, valore, aggiornato_il) 
                 VALUES (?, 'calendar', ?, NOW()) 
                 ON DUPLICATE KEY UPDATE valore = ?, aggiornato_il = NOW()",
                [$user['id'], $preferences, $preferences]
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Preferenze salvate con successo'
            ]);
            
        } catch (Exception $e) {
            throw new Exception("Errore nel salvataggio delle preferenze: " . $e->getMessage());
        }
        
    } else {
        // Recupera preferenze
        try {
            $stmt = db_query(
                "SELECT valore FROM user_preferences 
                 WHERE utente_id = ? AND tipo = 'calendar'",
                [$user['id']]
            );
            
            $preferences = $stmt->fetch();
            $preferencesData = $preferences ? json_decode($preferences['valore'], true) : [];
            
            // Preferenze default
            $defaultPreferences = [
                'defaultView' => 'month',
                'startWeek' => 'monday',
                'timeFormat' => '24h',
                'notifications' => true,
                'theme' => 'auto'
            ];
            
            $finalPreferences = array_merge($defaultPreferences, $preferencesData);
            
            echo json_encode([
                'success' => true,
                'preferences' => $finalPreferences
            ]);
            
        } catch (Exception $e) {
            throw new Exception("Errore nel recupero delle preferenze: " . $e->getMessage());
        }
    }
}

function handleStatistics($auth, $user, $currentAzienda) {
    $period = $_GET['period'] ?? 'month'; // day, week, month, year
    
    try {
        $stats = [];
        $now = new DateTime();
        
        // Definisci periodo
        switch ($period) {
            case 'day':
                $start = $now->format('Y-m-d 00:00:00');
                $end = $now->format('Y-m-d 23:59:59');
                break;
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
                $whereClause .= " AND (azienda_id = ? OR creato_da = ?)";
                $params[] = $aziendaId;
                $params[] = $user['id'];
            }
        }
        
        // Conta eventi per tipo
        $stmt = db_query(
            "SELECT tipo, COUNT(*) as count FROM eventi $whereClause GROUP BY tipo",
            $params
        );
        $eventsByType = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Eventi per giorno
        $stmt = db_query(
            "SELECT DATE(data_inizio) as date, COUNT(*) as count 
             FROM eventi $whereClause 
             GROUP BY DATE(data_inizio) 
             ORDER BY date ASC",
            $params
        );
        $eventsByDay = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Totale eventi
        $stmt = db_query("SELECT COUNT(*) as total FROM eventi $whereClause", $params);
        $totalEvents = $stmt->fetch()['total'];
        
        echo json_encode([
            'success' => true,
            'statistics' => [
                'period' => $period,
                'range' => ['start' => $start, 'end' => $end],
                'totalEvents' => intval($totalEvents),
                'eventsByType' => $eventsByType,
                'eventsByDay' => $eventsByDay
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception("Errore nel calcolo delle statistiche: " . $e->getMessage());
    }
}

function handleExport($auth, $user, $currentAzienda) {
    $format = $_GET['format'] ?? 'ics';
    $period = $_GET['period'] ?? 'month';
    
    if ($format !== 'ics') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Formato non supportato'
        ]);
        return;
    }
    
    try {
        // Calcola range date
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
                $whereClause .= " AND (azienda_id = ? OR creato_da = ?)";
                $params[] = $aziendaId;
                $params[] = $user['id'];
            }
        }
        
        $stmt = db_query(
            "SELECT * FROM eventi $whereClause ORDER BY data_inizio ASC",
            $params
        );
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Genera file ICS
        $icsContent = generateICS($events);
        
        echo json_encode([
            'success' => true,
            'export' => [
                'format' => $format,
                'period' => $period,
                'count' => count($events),
                'content' => base64_encode($icsContent),
                'filename' => "nexio-calendar-{$period}-" . date('Y-m-d') . ".ics"
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception("Errore nell'esportazione: " . $e->getMessage());
    }
}

function generateICS($events) {
    $ics = "BEGIN:VCALENDAR\r\n";
    $ics .= "VERSION:2.0\r\n";
    $ics .= "PRODID:-//Nexio//Calendar//IT\r\n";
    $ics .= "CALSCALE:GREGORIAN\r\n";
    
    foreach ($events as $event) {
        $ics .= "BEGIN:VEVENT\r\n";
        $ics .= "UID:" . $event['id'] . "@nexio.calendar\r\n";
        $ics .= "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
        
        $start = new DateTime($event['data_inizio']);
        $ics .= "DTSTART:" . $start->format('Ymd\THis\Z') . "\r\n";
        
        if ($event['data_fine']) {
            $end = new DateTime($event['data_fine']);
            $ics .= "DTEND:" . $end->format('Ymd\THis\Z') . "\r\n";
        }
        
        $ics .= "SUMMARY:" . str_replace(["\r", "\n"], ["", "\\n"], $event['titolo']) . "\r\n";
        
        if ($event['descrizione']) {
            $ics .= "DESCRIPTION:" . str_replace(["\r", "\n"], ["", "\\n"], $event['descrizione']) . "\r\n";
        }
        
        if ($event['luogo']) {
            $ics .= "LOCATION:" . str_replace(["\r", "\n"], ["", "\\n"], $event['luogo']) . "\r\n";
        }
        
        $ics .= "END:VEVENT\r\n";
    }
    
    $ics .= "END:VCALENDAR\r\n";
    
    return $ics;
}
?>