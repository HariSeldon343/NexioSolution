<?php
/**
 * Mobile Tickets API
 * Provides ticket management endpoints for Flutter app
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
    
    return $user;
}

try {
    $db = Database::getInstance()->getConnection();
    $user = verifyMobileToken($db);
    
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    switch ($method) {
        case 'GET':
            // Get tickets
            $status = $_GET['status'] ?? null;
            $priority = $_GET['priority'] ?? null;
            $azienda_id = $_GET['azienda_id'] ?? null;
            $search = $_GET['search'] ?? '';
            
            $sql = "
                SELECT t.*, 
                       u.nome as creator_nome, u.cognome as creator_cognome,
                       a.nome as azienda_nome,
                       (SELECT COUNT(*) FROM ticket_risposte WHERE ticket_id = t.id) as num_risposte,
                       (SELECT MAX(created_at) FROM ticket_risposte WHERE ticket_id = t.id) as ultima_risposta
                FROM tickets t
                LEFT JOIN utenti u ON t.utente_id = u.id
                LEFT JOIN aziende a ON t.azienda_id = a.id
                WHERE 1=1
            ";
            
            $params = [];
            
            // Filter by user (unless super admin)
            if ($user['role'] !== 'super_admin') {
                $sql .= " AND (t.utente_id = ? OR t.assegnato_a = ?)";
                $params[] = $user['user_id'];
                $params[] = $user['user_id'];
            }
            
            // Filter by status
            if ($status) {
                $sql .= " AND t.stato = ?";
                $params[] = $status;
            }
            
            // Filter by priority
            if ($priority) {
                $sql .= " AND t.priorita = ?";
                $params[] = $priority;
            }
            
            // Filter by company
            if ($azienda_id) {
                $sql .= " AND t.azienda_id = ?";
                $params[] = $azienda_id;
            }
            
            // Search
            if (!empty($search)) {
                $sql .= " AND (t.oggetto LIKE ? OR t.descrizione LIKE ?)";
                $searchTerm = "%$search%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            $sql .= " ORDER BY 
                     CASE t.priorita 
                        WHEN 'critica' THEN 1 
                        WHEN 'alta' THEN 2 
                        WHEN 'media' THEN 3 
                        WHEN 'bassa' THEN 4 
                     END,
                     t.created_at DESC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get ticket details if specific ID requested
            if (isset($_GET['id'])) {
                $ticketId = $_GET['id'];
                
                // Get ticket with responses
                $stmt = $db->prepare("
                    SELECT t.*, 
                           u.nome as creator_nome, u.cognome as creator_cognome,
                           a.nome as azienda_nome
                    FROM tickets t
                    LEFT JOIN utenti u ON t.utente_id = u.id
                    LEFT JOIN aziende a ON t.azienda_id = a.id
                    WHERE t.id = ?
                ");
                $stmt->execute([$ticketId]);
                $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($ticket) {
                    // Get responses
                    $stmt = $db->prepare("
                        SELECT tr.*, u.nome, u.cognome, u.profile_picture
                        FROM ticket_risposte tr
                        LEFT JOIN utenti u ON tr.utente_id = u.id
                        WHERE tr.ticket_id = ?
                        ORDER BY tr.created_at ASC
                    ");
                    $stmt->execute([$ticketId]);
                    $ticket['risposte'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Get attachments
                    $stmt = $db->prepare("
                        SELECT * FROM ticket_allegati
                        WHERE ticket_id = ?
                    ");
                    $stmt->execute([$ticketId]);
                    $ticket['allegati'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
                
                echo json_encode([
                    'success' => true,
                    'data' => $ticket
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'data' => $tickets
                ]);
            }
            break;
            
        case 'POST':
            // Create ticket or add response
            $data = json_decode(file_get_contents('php://input'), true);
            
            if ($action === 'reply') {
                // Add response to ticket
                $ticket_id = $data['ticket_id'] ?? 0;
                $messaggio = $data['messaggio'] ?? '';
                
                if (!$ticket_id || empty($messaggio)) {
                    throw new Exception('ID ticket e messaggio sono obbligatori');
                }
                
                // Check if user can reply to this ticket
                $stmt = $db->prepare("
                    SELECT * FROM tickets 
                    WHERE id = ? AND (utente_id = ? OR assegnato_a = ? OR ? = 'super_admin')
                ");
                $stmt->execute([$ticket_id, $user['user_id'], $user['user_id'], $user['role']]);
                
                if (!$stmt->fetch()) {
                    throw new Exception('Non hai i permessi per rispondere a questo ticket');
                }
                
                // Create response table if not exists
                $db->exec("
                    CREATE TABLE IF NOT EXISTS ticket_risposte (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        ticket_id INT NOT NULL,
                        utente_id INT NOT NULL,
                        messaggio TEXT NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
                        FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE
                    )
                ");
                
                // Insert response
                $stmt = $db->prepare("
                    INSERT INTO ticket_risposte (ticket_id, utente_id, messaggio)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$ticket_id, $user['user_id'], $messaggio]);
                
                // Update ticket last activity
                $db->prepare("UPDATE tickets SET updated_at = NOW() WHERE id = ?")
                   ->execute([$ticket_id]);
                
                echo json_encode([
                    'success' => true,
                    'data' => ['id' => $db->lastInsertId()]
                ]);
                
            } else {
                // Create new ticket
                $oggetto = $data['oggetto'] ?? '';
                $descrizione = $data['descrizione'] ?? '';
                $categoria = $data['categoria'] ?? 'generale';
                $priorita = $data['priorita'] ?? 'media';
                $azienda_id = $data['azienda_id'] ?? null;
                
                if (empty($oggetto) || empty($descrizione)) {
                    throw new Exception('Oggetto e descrizione sono obbligatori');
                }
                
                // Insert ticket
                $stmt = $db->prepare("
                    INSERT INTO tickets (
                        oggetto, descrizione, categoria, priorita, stato,
                        utente_id, azienda_id, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, 'aperto', ?, ?, NOW(), NOW())
                ");
                
                $stmt->execute([
                    $oggetto, $descrizione, $categoria, $priorita,
                    $user['user_id'], $azienda_id
                ]);
                
                $ticketId = $db->lastInsertId();
                
                echo json_encode([
                    'success' => true,
                    'data' => ['id' => $ticketId]
                ]);
            }
            break;
            
        case 'PUT':
            // Update ticket
            $ticketId = $_GET['id'] ?? 0;
            
            if (!$ticketId) {
                throw new Exception('ID ticket mancante');
            }
            
            // Check permission
            $stmt = $db->prepare("
                SELECT * FROM tickets 
                WHERE id = ? AND (utente_id = ? OR assegnato_a = ? OR ? = 'super_admin')
            ");
            $stmt->execute([$ticketId, $user['user_id'], $user['user_id'], $user['role']]);
            
            if (!$stmt->fetch()) {
                throw new Exception('Non hai i permessi per modificare questo ticket');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $updates = [];
            $params = [];
            
            foreach (['oggetto', 'descrizione', 'categoria', 'priorita', 'stato', 'assegnato_a'] as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            
            if (!empty($updates)) {
                $updates[] = "updated_at = NOW()";
                $params[] = $ticketId;
                $sql = "UPDATE tickets SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
            }
            
            echo json_encode(['success' => true]);
            break;
            
        case 'DELETE':
            // Delete ticket (super admin only)
            if ($user['role'] !== 'super_admin') {
                throw new Exception('Solo i super admin possono eliminare i ticket');
            }
            
            $ticketId = $_GET['id'] ?? 0;
            
            if (!$ticketId) {
                throw new Exception('ID ticket mancante');
            }
            
            $stmt = $db->prepare("DELETE FROM tickets WHERE id = ?");
            $stmt->execute([$ticketId]);
            
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