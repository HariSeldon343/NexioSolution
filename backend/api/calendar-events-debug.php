<?php
/**
 * DEBUG VERSION - API Endpoint per Eventi Calendario
 * Versione con logging esteso per debugging mobile app
 */

// Enable all error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Debug logging function
function debug_log($message, $data = null) {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $message,
        'data' => $data
    ];
    
    error_log("CALENDAR_DEBUG: " . json_encode($logEntry));
    return $logEntry;
}

// Start debug logging
$debugLogs = [];
$debugLogs[] = debug_log("API called", [
    'method' => $_SERVER['REQUEST_METHOD'],
    'url' => $_SERVER['REQUEST_URI'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
]);

try {
    // 1. Include required files
    $debugLogs[] = debug_log("Including required files");
    
    if (!file_exists('../config/config.php')) {
        throw new Exception("config.php not found at " . realpath('../config/'));
    }
    require_once '../config/config.php';
    
    if (!file_exists('../config/database.php')) {
        throw new Exception("database.php not found");
    }
    require_once '../config/database.php';
    
    if (!file_exists('../middleware/Auth.php')) {
        throw new Exception("Auth.php not found");
    }
    require_once '../middleware/Auth.php';
    
    $debugLogs[] = debug_log("Files included successfully");
    
    // 2. Test database connection
    $debugLogs[] = debug_log("Testing database connection");
    
    if (!isset($pdo)) {
        throw new Exception("PDO connection not available");
    }
    
    $stmt = $pdo->query("SELECT 1");
    if (!$stmt) {
        throw new Exception("Database test query failed");
    }
    
    $debugLogs[] = debug_log("Database connection OK");
    
    // 3. Initialize Auth
    $debugLogs[] = debug_log("Initializing Auth");
    
    $auth = Auth::getInstance();
    $debugLogs[] = debug_log("Auth instance created");
    
    // 4. Check authentication
    $isAuthenticated = $auth->isAuthenticated();
    $debugLogs[] = debug_log("Authentication check", ['authenticated' => $isAuthenticated]);
    
    if (!$isAuthenticated) {
        $debugLogs[] = debug_log("User not authenticated - returning 401");
        
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Non autenticato',
            'debug_logs' => $debugLogs,
            'session_data' => [
                'session_id' => session_id(),
                'session_status' => session_status(),
                'session_data' => $_SESSION
            ]
        ]);
        exit;
    }
    
    // 5. Get user and company data
    $user = $auth->getUser();
    $currentAzienda = $auth->getCurrentAzienda();
    
    $debugLogs[] = debug_log("User data", [
        'user_id' => $user['id'] ?? 'null',
        'username' => $user['username'] ?? 'null',
        'azienda_id' => $currentAzienda['id'] ?? 'null'
    ]);
    
    // 6. Handle different HTTP methods
    $method = $_SERVER['REQUEST_METHOD'];
    $debugLogs[] = debug_log("Handling HTTP method", ['method' => $method]);
    
    switch ($method) {
        case 'GET':
            handleGetEventsDebug($auth, $user, $currentAzienda, $debugLogs);
            break;
            
        default:
            $debugLogs[] = debug_log("Unsupported method", ['method' => $method]);
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => 'Metodo non supportato',
                'debug_logs' => $debugLogs
            ]);
            break;
    }
    
} catch (Exception $e) {
    $debugLogs[] = debug_log("Exception caught", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    error_log("Calendar API Debug Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Errore interno del server: ' . $e->getMessage(),
        'debug_logs' => $debugLogs
    ]);
}

function handleGetEventsDebug($auth, $user, $currentAzienda, &$debugLogs) {
    global $pdo;
    
    $start = $_GET['start'] ?? null;
    $end = $_GET['end'] ?? null;
    $eventId = $_GET['id'] ?? null;
    
    $debugLogs[] = debug_log("GET request parameters", [
        'start' => $start,
        'end' => $end,
        'id' => $eventId
    ]);
    
    try {
        // 1. Check if eventi table exists
        $debugLogs[] = debug_log("Checking if eventi table exists");
        
        $tableExists = db_table_exists('eventi');
        $debugLogs[] = debug_log("Eventi table check", ['exists' => $tableExists]);
        
        if (!$tableExists) {
            $debugLogs[] = debug_log("Creating sample eventi table structure");
            
            // Try to create the table if it doesn't exist
            $createTableSQL = "
                CREATE TABLE IF NOT EXISTS eventi (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    titolo VARCHAR(255) NOT NULL,
                    descrizione TEXT,
                    data_inizio DATETIME NOT NULL,
                    data_fine DATETIME,
                    luogo VARCHAR(255),
                    tipo VARCHAR(50) DEFAULT 'riunione',
                    azienda_id INT,
                    creato_da INT,
                    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_data_inizio (data_inizio),
                    INDEX idx_azienda_id (azienda_id)
                )
            ";
            
            $pdo->exec($createTableSQL);
            $debugLogs[] = debug_log("Eventi table created");
            
            // Create sample data
            $sampleEvents = [
                [
                    'titolo' => 'Riunione Test Mobile',
                    'descrizione' => 'Test event per mobile app',
                    'data_inizio' => date('Y-m-d H:i:s', strtotime('+1 hour')),
                    'data_fine' => date('Y-m-d H:i:s', strtotime('+2 hours')),
                    'luogo' => 'Sede principale',
                    'tipo' => 'riunione',
                    'azienda_id' => $currentAzienda['id'] ?? 1,
                    'creato_da' => $user['id'] ?? 1
                ],
                [
                    'titolo' => 'Demo App Mobile',
                    'descrizione' => 'Dimostrazione funzionalità mobile',
                    'data_inizio' => date('Y-m-d H:i:s', strtotime('tomorrow 10:00')),
                    'data_fine' => date('Y-m-d H:i:s', strtotime('tomorrow 11:00')),
                    'luogo' => 'Sala conferenze',
                    'tipo' => 'demo',
                    'azienda_id' => $currentAzienda['id'] ?? 1,
                    'creato_da' => $user['id'] ?? 1
                ]
            ];
            
            foreach ($sampleEvents as $event) {
                db_insert('eventi', $event);
            }
            
            $debugLogs[] = debug_log("Sample events created", ['count' => count($sampleEvents)]);
        }
        
        // 2. Get events
        if ($eventId) {
            $debugLogs[] = debug_log("Getting single event", ['id' => $eventId]);
            $event = getSingleEventDebug($eventId, $auth, $user, $currentAzienda, $debugLogs);
            echo json_encode([
                'success' => true,
                'event' => $event,
                'debug_logs' => $debugLogs
            ]);
        } else {
            $debugLogs[] = debug_log("Getting events in range");
            $events = getEventsInRangeDebug($start, $end, $auth, $user, $currentAzienda, $debugLogs);
            echo json_encode([
                'success' => true,
                'events' => $events,
                'count' => count($events),
                'debug_logs' => $debugLogs
            ]);
        }
        
    } catch (Exception $e) {
        $debugLogs[] = debug_log("Error in handleGetEventsDebug", [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'debug_logs' => $debugLogs
        ]);
    }
}

function getEventsInRangeDebug($start, $end, $auth, $user, $currentAzienda, &$debugLogs) {
    $debugLogs[] = debug_log("Building events query");
    
    $whereClause = "WHERE 1=1";
    $params = [];
    
    // Filter by company if not super admin
    $debugLogs[] = debug_log("Checking user permissions");
    
    if (method_exists($auth, 'canViewAllEvents') && !$auth->canViewAllEvents()) {
        if ($currentAzienda) {
            $aziendaId = $currentAzienda['id'] ?? $currentAzienda['azienda_id'] ?? null;
            if ($aziendaId) {
                $whereClause .= " AND (e.azienda_id = ? OR e.creato_da = ?)";
                $params[] = $aziendaId;
                $params[] = $user['id'];
                $debugLogs[] = debug_log("Added company filter", ['azienda_id' => $aziendaId]);
            }
        }
    } else {
        $debugLogs[] = debug_log("User can view all events or permission method not available");
    }
    
    // Date range filter
    if ($start && $end) {
        $whereClause .= " AND DATE(e.data_inizio) BETWEEN ? AND ?";
        $params[] = $start;
        $params[] = $end;
        $debugLogs[] = debug_log("Added date range filter", ['start' => $start, 'end' => $end]);
    } elseif ($start) {
        $whereClause .= " AND DATE(e.data_inizio) >= ?";
        $params[] = $start;
        $debugLogs[] = debug_log("Added start date filter", ['start' => $start]);
    } elseif ($end) {
        $whereClause .= " AND DATE(e.data_inizio) <= ?";
        $params[] = $end;
        $debugLogs[] = debug_log("Added end date filter", ['end' => $end]);
    }
    
    // Build final query
    $sql = "SELECT e.*, 
                   u.nome as creatore_nome, u.cognome as creatore_cognome,
                   COUNT(ep.id) as num_partecipanti
            FROM eventi e 
            LEFT JOIN utenti u ON e.creato_da = u.id 
            LEFT JOIN evento_partecipanti ep ON e.id = ep.evento_id
            $whereClause
            GROUP BY e.id
            ORDER BY e.data_inizio ASC";
    
    $debugLogs[] = debug_log("Executing query", [
        'sql' => $sql,
        'params' => $params
    ]);
    
    try {
        $stmt = db_query($sql, $params);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $debugLogs[] = debug_log("Query executed successfully", [
            'row_count' => count($events)
        ]);
        
        // Process events for better mobile compatibility
        foreach ($events as &$event) {
            $event['start_time'] = $event['data_inizio'];
            $event['end_time'] = $event['data_fine'];
            $event['title'] = $event['titolo'];
            $event['description'] = $event['descrizione'];
            $event['location'] = $event['luogo'];
            
            // Add formatted dates
            $event['formatted_start'] = date('d/m/Y H:i', strtotime($event['data_inizio']));
            if ($event['data_fine']) {
                $event['formatted_end'] = date('d/m/Y H:i', strtotime($event['data_fine']));
            }
        }
        
        return $events;
        
    } catch (Exception $e) {
        $debugLogs[] = debug_log("Query execution failed", [
            'error' => $e->getMessage()
        ]);
        throw $e;
    }
}

function getSingleEventDebug($eventId, $auth, $user, $currentAzienda, &$debugLogs) {
    $sql = "SELECT e.*, 
                   u.nome as creatore_nome, u.cognome as creatore_cognome
            FROM eventi e 
            LEFT JOIN utenti u ON e.creato_da = u.id 
            WHERE e.id = ?";
    
    $debugLogs[] = debug_log("Getting single event", ['id' => $eventId, 'sql' => $sql]);
    
    $stmt = db_query($sql, [$eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        $debugLogs[] = debug_log("Event not found", ['id' => $eventId]);
        throw new Exception('Evento non trovato');
    }
    
    // Add mobile-friendly fields
    $event['start_time'] = $event['data_inizio'];
    $event['end_time'] = $event['data_fine'];
    $event['title'] = $event['titolo'];
    $event['description'] = $event['descrizione'];
    $event['location'] = $event['luogo'];
    
    $debugLogs[] = debug_log("Single event retrieved successfully", ['title' => $event['titolo']]);
    
    return $event;
}
?>