<?php
/**
 * Mobile Events API
 * Provides calendar events endpoints for Flutter app
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Mobile-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/config.php';
require_once '../config/database.php';

// Verify mobile token
function verifyMobileToken($db) {
    $token = $_SERVER['HTTP_X_MOBILE_TOKEN'] ?? '';
    
    if (empty($token)) {
        throw new Exception('Token mancante');
    }
    
    $stmt = $db->prepare("
        SELECT mt.*, u.* 
        FROM mobile_tokens mt
        JOIN utenti u ON mt.user_id = u.id
        WHERE mt.token = ? AND mt.expires_at > NOW() AND u.stato = 'attivo'
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('Token non valido o scaduto');
    }
    
    // Update last used
    $db->prepare("UPDATE mobile_tokens SET last_used = NOW() WHERE token = ?")
       ->execute([$token]);
    
    return $user;
}

try {
    $db = Database::getInstance()->getConnection();
    $user = verifyMobileToken($db);
    
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    switch ($method) {
        case 'GET':
            // Get events
            $start = $_GET['start'] ?? date('Y-m-01');
            $end = $_GET['end'] ?? date('Y-m-t', strtotime('+1 month'));
            $azienda_id = $_GET['azienda_id'] ?? null;
            
            $sql = "
                SELECT e.*, 
                       u.nome as creator_nome, u.cognome as creator_cognome,
                       a.nome as azienda_nome,
                       GROUP_CONCAT(DISTINCT ep.utente_id) as partecipanti_ids,
                       (SELECT COUNT(*) FROM evento_partecipanti WHERE evento_id = e.id) as num_partecipanti
                FROM eventi e
                LEFT JOIN utenti u ON e.utente_id = u.id
                LEFT JOIN aziende a ON e.azienda_id = a.id
                LEFT JOIN evento_partecipanti ep ON e.id = ep.evento_id
                WHERE 1=1
            ";
            
            $params = [];
            
            // Filter by date range
            $sql .= " AND ((e.data_inizio >= ? AND e.data_inizio <= ?) 
                      OR (e.data_fine >= ? AND e.data_fine <= ?)
                      OR (e.data_inizio <= ? AND e.data_fine >= ?))";
            $params = array_merge($params, [$start, $end, $start, $end, $start, $end]);
            
            // Filter by company if specified
            if ($azienda_id) {
                $sql .= " AND e.azienda_id = ?";
                $params[] = $azienda_id;
            }
            
            // Filter by user participation or creation
            if ($user['role'] !== 'super_admin') {
                $sql .= " AND (e.utente_id = ? OR ep.utente_id = ? OR e.visibilita = 'pubblico')";
                $params[] = $user['user_id'];
                $params[] = $user['user_id'];
            }
            
            $sql .= " GROUP BY e.id ORDER BY e.data_inizio ASC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get participants for each event
            foreach ($events as &$event) {
                if (!empty($event['partecipanti_ids'])) {
                    $participantIds = explode(',', $event['partecipanti_ids']);
                    $placeholders = str_repeat('?,', count($participantIds) - 1) . '?';
                    
                    $stmt = $db->prepare("
                        SELECT id, nome, cognome, email, profile_picture 
                        FROM utenti 
                        WHERE id IN ($placeholders)
                    ");
                    $stmt->execute($participantIds);
                    $event['partecipanti'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => $events
            ]);
            break;
            
        case 'POST':
            // Create event
            $data = json_decode(file_get_contents('php://input'), true);
            
            $titolo = $data['titolo'] ?? '';
            $descrizione = $data['descrizione'] ?? '';
            $data_inizio = $data['data_inizio'] ?? '';
            $data_fine = $data['data_fine'] ?? '';
            $ora_inizio = $data['ora_inizio'] ?? '';
            $ora_fine = $data['ora_fine'] ?? '';
            $luogo = $data['luogo'] ?? '';
            $tipo = $data['tipo'] ?? 'evento';
            $colore = $data['colore'] ?? '#2196F3';
            $azienda_id = $data['azienda_id'] ?? null;
            $visibilita = $data['visibilita'] ?? 'privato';
            $ricorrenza = $data['ricorrenza'] ?? null;
            $promemoria = $data['promemoria'] ?? null;
            $partecipanti = $data['partecipanti'] ?? [];
            
            if (empty($titolo) || empty($data_inizio)) {
                throw new Exception('Titolo e data inizio sono obbligatori');
            }
            
            // Insert event
            $stmt = $db->prepare("
                INSERT INTO eventi (
                    titolo, descrizione, data_inizio, data_fine, 
                    ora_inizio, ora_fine, luogo, tipo, colore,
                    utente_id, azienda_id, visibilita, ricorrenza, promemoria
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $titolo, $descrizione, $data_inizio, $data_fine,
                $ora_inizio, $ora_fine, $luogo, $tipo, $colore,
                $user['user_id'], $azienda_id, $visibilita, $ricorrenza, $promemoria
            ]);
            
            $eventId = $db->lastInsertId();
            
            // Add participants
            if (!empty($partecipanti)) {
                $stmt = $db->prepare("
                    INSERT INTO evento_partecipanti (evento_id, utente_id, stato)
                    VALUES (?, ?, 'invitato')
                ");
                
                foreach ($partecipanti as $participantId) {
                    $stmt->execute([$eventId, $participantId]);
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => ['id' => $eventId]
            ]);
            break;
            
        case 'PUT':
            // Update event
            $eventId = $_GET['id'] ?? 0;
            
            if (!$eventId) {
                throw new Exception('ID evento mancante');
            }
            
            // Check permission
            $stmt = $db->prepare("
                SELECT * FROM eventi 
                WHERE id = ? AND (utente_id = ? OR ? = 'super_admin')
            ");
            $stmt->execute([$eventId, $user['user_id'], $user['role']]);
            
            if (!$stmt->fetch()) {
                throw new Exception('Non hai i permessi per modificare questo evento');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $updates = [];
            $params = [];
            
            foreach (['titolo', 'descrizione', 'data_inizio', 'data_fine', 
                     'ora_inizio', 'ora_fine', 'luogo', 'tipo', 'colore',
                     'visibilita', 'ricorrenza', 'promemoria'] as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            
            if (!empty($updates)) {
                $params[] = $eventId;
                $sql = "UPDATE eventi SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
            }
            
            // Update participants if provided
            if (isset($data['partecipanti'])) {
                // Remove old participants
                $db->prepare("DELETE FROM evento_partecipanti WHERE evento_id = ?")
                   ->execute([$eventId]);
                
                // Add new participants
                if (!empty($data['partecipanti'])) {
                    $stmt = $db->prepare("
                        INSERT INTO evento_partecipanti (evento_id, utente_id, stato)
                        VALUES (?, ?, 'invitato')
                    ");
                    
                    foreach ($data['partecipanti'] as $participantId) {
                        $stmt->execute([$eventId, $participantId]);
                    }
                }
            }
            
            echo json_encode(['success' => true]);
            break;
            
        case 'DELETE':
            // Delete event
            $eventId = $_GET['id'] ?? 0;
            
            if (!$eventId) {
                throw new Exception('ID evento mancante');
            }
            
            // Check permission
            $stmt = $db->prepare("
                SELECT * FROM eventi 
                WHERE id = ? AND (utente_id = ? OR ? = 'super_admin')
            ");
            $stmt->execute([$eventId, $user['user_id'], $user['role']]);
            
            if (!$stmt->fetch()) {
                throw new Exception('Non hai i permessi per eliminare questo evento');
            }
            
            // Delete event (participants will be deleted by cascade)
            $stmt = $db->prepare("DELETE FROM eventi WHERE id = ?");
            $stmt->execute([$eventId]);
            
            echo json_encode(['success' => true]);
            break;
            
        default:
            throw new Exception('Metodo non supportato');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}