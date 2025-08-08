<?php
/**
 * API Semplice per Eventi Calendario
 * Versione minimalista per test mobile
 */

// Headers per CORS e JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gestisci preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Funzione per inviare risposta JSON
function sendResponse($success, $data = null, $message = '', $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'eventi' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Eventi demo sempre disponibili
$eventiDemo = [
    '2025-01-08' => [
        ['titolo' => 'Riunione Team', 'ora' => '09:00', 'descrizione' => 'Meeting settimanale di coordinamento'],
        ['titolo' => 'Presentazione Progetto', 'ora' => '14:30', 'descrizione' => 'Presentazione al cliente ABC']
    ],
    '2025-01-10' => [
        ['titolo' => 'Formazione ISO', 'ora' => '10:00', 'descrizione' => 'Corso di aggiornamento normativo']
    ],
    '2025-01-15' => [
        ['titolo' => 'Scadenza Documenti', 'ora' => '16:00', 'descrizione' => 'Consegna report mensile'],
        ['titolo' => 'Call con Cliente XYZ', 'ora' => '18:00', 'descrizione' => 'Follow-up progetto in corso']
    ],
    '2025-01-20' => [
        ['titolo' => 'Revisione Codice', 'ora' => '11:00', 'descrizione' => 'Code review sprint corrente']
    ],
    '2025-01-25' => [
        ['titolo' => 'Pianificazione Mensile', 'ora' => '09:30', 'descrizione' => 'Planning meeting febbraio']
    ]
];

try {
    // Prova a connettersi al database
    $configPaths = [
        __DIR__ . '/../config/database.php',
        dirname(__DIR__, 2) . '/backend/config/database.php',
        dirname(__DIR__, 3) . '/backend/config/database.php'
    ];
    
    $pdo = null;
    foreach ($configPaths as $configPath) {
        if (file_exists($configPath)) {
            try {
                require_once $configPath;
                if (isset($pdo) && $pdo instanceof PDO) {
                    break;
                }
            } catch (Exception $e) {
                continue;
            }
        }
    }
    
    // Se abbiamo una connessione database, prova a caricare eventi reali
    if ($pdo instanceof PDO) {
        try {
            // Query semplice per eventi
            $query = "SELECT 
                DATE(data_inizio) as data_evento,
                titolo,
                TIME_FORMAT(data_inizio, '%H:%i') as ora,
                descrizione
            FROM eventi 
            WHERE data_inizio >= CURDATE() 
                AND data_inizio <= DATE_ADD(CURDATE(), INTERVAL 3 MONTH)
            ORDER BY data_inizio";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $risultati = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Organizza eventi per data
            $eventiReali = [];
            foreach ($risultati as $evento) {
                $data = $evento['data_evento'];
                if (!isset($eventiReali[$data])) {
                    $eventiReali[$data] = [];
                }
                $eventiReali[$data][] = [
                    'titolo' => $evento['titolo'] ?: 'Evento senza titolo',
                    'ora' => $evento['ora'] ?: '00:00',
                    'descrizione' => $evento['descrizione'] ?: ''
                ];
            }
            
            // Se abbiamo eventi reali, usali, altrimenti usa demo
            if (!empty($eventiReali)) {
                sendResponse(true, $eventiReali, 'Eventi caricati dal database (' . count($risultati) . ' eventi trovati)');
            } else {
                sendResponse(true, $eventiDemo, 'Nessun evento nel database. Mostro eventi demo.');
            }
            
        } catch (PDOException $e) {
            // Errore database, usa eventi demo
            sendResponse(true, $eventiDemo, 'Errore database: ' . $e->getMessage() . '. Uso eventi demo.');
        }
    } else {
        // Nessuna connessione database, usa eventi demo
        sendResponse(true, $eventiDemo, 'Database non disponibile. Mostro eventi demo.');
    }
    
} catch (Exception $e) {
    // Qualsiasi altro errore, usa eventi demo
    sendResponse(true, $eventiDemo, 'Errore generale: ' . $e->getMessage() . '. Uso eventi demo.');
}
?>