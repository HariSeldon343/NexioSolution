<?php
/**
 * Mobile Tasks API
 * Provides task management endpoints for Flutter app
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
            // Get tasks
            $filter = $_GET['filter'] ?? 'all'; // all, assigned_to_me, created_by_me, today, week
            $status = $_GET['status'] ?? null;
            $azienda_id = $_GET['azienda_id'] ?? null;
            
            $sql = "
                SELECT t.*, 
                       u.nome as assegnato_nome, u.cognome as assegnato_cognome,
                       c.nome as creatore_nome, c.cognome as creatore_cognome,
                       a.nome as azienda_nome
                FROM task_calendario t
                LEFT JOIN utenti u ON t.utente_assegnato_id = u.id
                LEFT JOIN utenti c ON t.assegnato_da = c.id
                LEFT JOIN aziende a ON t.azienda_id = a.id
                WHERE 1=1
            ";
            
            $params = [];
            
            // Apply filters based on user role
            if ($user['role'] === 'super_admin') {
                // Super admin can see all tasks
            } elseif ($user['role'] === 'utente_speciale') {
                // Utente speciale can see their own tasks
                $sql .= " AND t.utente_assegnato_id = ?";
                $params[] = $user['user_id'];
            } else {
                // Regular users can only see tasks assigned to them
                $sql .= " AND t.utente_assegnato_id = ?";
                $params[] = $user['user_id'];
            }
            
            // Apply additional filters
            switch ($filter) {
                case 'assigned_to_me':
                    $sql .= " AND t.utente_assegnato_id = ?";
                    $params[] = $user['user_id'];
                    break;
                    
                case 'created_by_me':
                    $sql .= " AND t.assegnato_da = ?";
                    $params[] = $user['user_id'];
                    break;
                    
                case 'today':
                    $sql .= " AND DATE(t.data_inizio) = CURDATE()";
                    break;
                    
                case 'week':
                    $sql .= " AND t.data_inizio >= CURDATE() 
                             AND t.data_inizio <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
                    break;
            }
            
            // Filter by status
            if ($status) {
                $sql .= " AND t.stato = ?";
                $params[] = $status;
            }
            
            // Filter by company
            if ($azienda_id) {
                $sql .= " AND t.azienda_id = ?";
                $params[] = $azienda_id;
            }
            
            $sql .= " ORDER BY 
                     CASE t.priorita 
                        WHEN 'alta' THEN 1 
                        WHEN 'media' THEN 2 
                        WHEN 'bassa' THEN 3 
                     END,
                     t.data_inizio ASC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get task details if specific ID requested
            if (isset($_GET['id'])) {
                $taskId = $_GET['id'];
                
                $stmt = $db->prepare("
                    SELECT t.*, 
                           u.nome as assegnato_nome, u.cognome as assegnato_cognome,
                           c.nome as creatore_nome, c.cognome as creatore_cognome,
                           a.nome as azienda_nome
                    FROM task_calendario t
                    LEFT JOIN utenti u ON t.utente_assegnato_id = u.id
                    LEFT JOIN utenti c ON t.assegnato_da = c.id
                    LEFT JOIN aziende a ON t.azienda_id = a.id
                    WHERE t.id = ?
                ");
                $stmt->execute([$taskId]);
                $task = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'data' => $task
                ]);
            } else {
                // Calculate statistics
                $stats = [
                    'total' => count($tasks),
                    'pending' => 0,
                    'in_progress' => 0,
                    'completed' => 0,
                    'overdue' => 0
                ];
                
                foreach ($tasks as $task) {
                    $stats[$task['stato']]++;
                    if ($task['stato'] !== 'completato' && $task['data_fine'] < date('Y-m-d')) {
                        $stats['overdue']++;
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'data' => $tasks,
                    'stats' => $stats
                ]);
            }
            break;
            
        case 'POST':
            // Create task (only super admin)
            if ($user['role'] !== 'super_admin') {
                throw new Exception('Solo i super admin possono creare task');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $titolo = $data['titolo'] ?? '';
            $descrizione = $data['descrizione'] ?? '';
            $utente_assegnato_id = $data['utente_assegnato_id'] ?? 0;
            $azienda_id = $data['azienda_id'] ?? null;
            $data_inizio = $data['data_inizio'] ?? date('Y-m-d');
            $data_fine = $data['data_fine'] ?? '';
            $priorita = $data['priorita'] ?? 'media';
            $tipo = $data['tipo'] ?? 'task';
            
            if (empty($titolo) || !$utente_assegnato_id) {
                throw new Exception('Titolo e utente assegnato sono obbligatori');
            }
            
            // Insert task
            $stmt = $db->prepare("
                INSERT INTO task_calendario (
                    titolo, descrizione, utente_assegnato_id, azienda_id,
                    data_inizio, data_fine, priorita, tipo, stato,
                    assegnato_da, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
            ");
            
            $stmt->execute([
                $titolo, $descrizione, $utente_assegnato_id, $azienda_id,
                $data_inizio, $data_fine, $priorita, $tipo,
                $user['user_id']
            ]);
            
            $taskId = $db->lastInsertId();
            
            // Send notification to assigned user
            $stmt = $db->prepare("
                SELECT firebase_token FROM mobile_tokens 
                WHERE user_id = ? AND firebase_token IS NOT NULL
            ");
            $stmt->execute([$utente_assegnato_id]);
            $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($tokens)) {
                // Send push notification
                // This would require Firebase Admin SDK integration
            }
            
            echo json_encode([
                'success' => true,
                'data' => ['id' => $taskId]
            ]);
            break;
            
        case 'PUT':
            // Update task
            $taskId = $_GET['id'] ?? 0;
            
            if (!$taskId) {
                throw new Exception('ID task mancante');
            }
            
            // Check permission
            $stmt = $db->prepare("
                SELECT * FROM task_calendario 
                WHERE id = ?
            ");
            $stmt->execute([$taskId]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$task) {
                throw new Exception('Task non trovato');
            }
            
            // Check permissions
            $canEdit = false;
            if ($user['role'] === 'super_admin') {
                $canEdit = true;
            } elseif ($task['utente_assegnato_id'] == $user['user_id']) {
                // Assigned user can only update status and notes
                $canEdit = true;
            }
            
            if (!$canEdit) {
                throw new Exception('Non hai i permessi per modificare questo task');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $updates = [];
            $params = [];
            
            if ($user['role'] === 'super_admin') {
                // Super admin can update everything
                foreach (['titolo', 'descrizione', 'utente_assegnato_id', 
                         'data_inizio', 'data_fine', 'priorita', 'stato'] as $field) {
                    if (isset($data[$field])) {
                        $updates[] = "$field = ?";
                        $params[] = $data[$field];
                    }
                }
            } else {
                // Regular users can only update status and add notes
                if (isset($data['stato'])) {
                    $updates[] = "stato = ?";
                    $params[] = $data['stato'];
                }
                if (isset($data['note'])) {
                    $updates[] = "note = ?";
                    $params[] = $data['note'];
                }
            }
            
            if (!empty($updates)) {
                $updates[] = "updated_at = NOW()";
                $params[] = $taskId;
                $sql = "UPDATE task_calendario SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                
                // Log completion if status changed to completed
                if (isset($data['stato']) && $data['stato'] === 'completato') {
                    $stmt = $db->prepare("
                        UPDATE task_calendario 
                        SET data_completamento = NOW() 
                        WHERE id = ? AND data_completamento IS NULL
                    ");
                    $stmt->execute([$taskId]);
                }
            }
            
            echo json_encode(['success' => true]);
            break;
            
        case 'DELETE':
            // Delete task (only super admin)
            if ($user['role'] !== 'super_admin') {
                throw new Exception('Solo i super admin possono eliminare task');
            }
            
            $taskId = $_GET['id'] ?? 0;
            
            if (!$taskId) {
                throw new Exception('ID task mancante');
            }
            
            $stmt = $db->prepare("DELETE FROM task_calendario WHERE id = ?");
            $stmt->execute([$taskId]);
            
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