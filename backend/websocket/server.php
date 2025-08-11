<?php
/**
 * WebSocket Server for Real-time Synchronization
 * Run with: php backend/websocket/server.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class NexioWebSocketServer implements MessageComponentInterface {
    protected $clients;
    protected $userConnections;
    protected $db;
    
    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->userConnections = [];
        
        try {
            $this->db = Database::getInstance()->getConnection();
        } catch (Exception $e) {
            echo "Database connection failed: " . $e->getMessage() . "\n";
        }
    }
    
    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }
    
    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        if (!$data || !isset($data['type'])) {
            return;
        }
        
        switch ($data['type']) {
            case 'auth':
                $this->handleAuth($from, $data);
                break;
                
            case 'sync':
                $this->handleSync($from, $data);
                break;
                
            case 'notification':
                $this->handleNotification($from, $data);
                break;
                
            case 'presence':
                $this->handlePresence($from, $data);
                break;
                
            case 'document_update':
                $this->handleDocumentUpdate($from, $data);
                break;
                
            case 'ticket_update':
                $this->handleTicketUpdate($from, $data);
                break;
                
            case 'event_update':
                $this->handleEventUpdate($from, $data);
                break;
                
            case 'ping':
                $from->send(json_encode(['type' => 'pong']));
                break;
        }
    }
    
    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        
        // Remove from user connections
        foreach ($this->userConnections as $userId => $connections) {
            $key = array_search($conn, $connections, true);
            if ($key !== false) {
                unset($this->userConnections[$userId][$key]);
                
                // Notify others that user went offline if no more connections
                if (empty($this->userConnections[$userId])) {
                    unset($this->userConnections[$userId]);
                    $this->broadcastPresence($userId, 'offline');
                }
                break;
            }
        }
        
        echo "Connection {$conn->resourceId} has disconnected\n";
    }
    
    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
    
    protected function handleAuth(ConnectionInterface $conn, $data) {
        if (!isset($data['token'])) {
            $conn->send(json_encode(['type' => 'auth_error', 'message' => 'Token required']));
            return;
        }
        
        // Validate token
        try {
            $stmt = $this->db->prepare("
                SELECT mt.user_id, u.nome, u.cognome, u.ruolo
                FROM mobile_tokens mt
                JOIN utenti u ON mt.user_id = u.id
                WHERE mt.token = ? AND mt.expires_at > NOW()
            ");
            $stmt->execute([$data['token']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $conn->send(json_encode(['type' => 'auth_error', 'message' => 'Invalid token']));
                return;
            }
            
            // Store connection
            $conn->userId = $user['user_id'];
            $conn->userData = $user;
            
            if (!isset($this->userConnections[$user['user_id']])) {
                $this->userConnections[$user['user_id']] = [];
            }
            $this->userConnections[$user['user_id']][] = $conn;
            
            // Send success response
            $conn->send(json_encode([
                'type' => 'auth_success',
                'user' => $user
            ]));
            
            // Notify others that user is online
            $this->broadcastPresence($user['user_id'], 'online');
            
            echo "User {$user['user_id']} authenticated on connection {$conn->resourceId}\n";
            
        } catch (Exception $e) {
            $conn->send(json_encode(['type' => 'auth_error', 'message' => 'Authentication failed']));
        }
    }
    
    protected function handleSync(ConnectionInterface $from, $data) {
        if (!isset($from->userId)) {
            $from->send(json_encode(['type' => 'error', 'message' => 'Not authenticated']));
            return;
        }
        
        // Get latest updates for the user
        try {
            $lastSync = $data['last_sync'] ?? '2000-01-01 00:00:00';
            
            // Get updated documents
            $stmt = $this->db->prepare("
                SELECT * FROM documenti 
                WHERE updated_at > ? AND (
                    creato_da = ? OR 
                    azienda_id IN (
                        SELECT azienda_id FROM utenti_aziende WHERE utente_id = ?
                    )
                )
                ORDER BY updated_at DESC LIMIT 100
            ");
            $stmt->execute([$lastSync, $from->userId, $from->userId]);
            $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get updated events
            $stmt = $this->db->prepare("
                SELECT * FROM eventi 
                WHERE updated_at > ? AND (
                    creato_da = ? OR 
                    id IN (
                        SELECT evento_id FROM evento_partecipanti WHERE utente_id = ?
                    )
                )
                ORDER BY updated_at DESC LIMIT 100
            ");
            $stmt->execute([$lastSync, $from->userId, $from->userId]);
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get updated tickets
            $stmt = $this->db->prepare("
                SELECT * FROM tickets 
                WHERE updated_at > ? AND (utente_id = ? OR assegnato_a = ?)
                ORDER BY updated_at DESC LIMIT 100
            ");
            $stmt->execute([$lastSync, $from->userId, $from->userId]);
            $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $from->send(json_encode([
                'type' => 'sync_data',
                'documents' => $documents,
                'events' => $events,
                'tickets' => $tickets,
                'timestamp' => date('Y-m-d H:i:s')
            ]));
            
        } catch (Exception $e) {
            $from->send(json_encode(['type' => 'sync_error', 'message' => $e->getMessage()]));
        }
    }
    
    protected function handleNotification(ConnectionInterface $from, $data) {
        if (!isset($from->userId)) {
            return;
        }
        
        // Send notification to specific users
        if (isset($data['to_users'])) {
            foreach ($data['to_users'] as $userId) {
                if (isset($this->userConnections[$userId])) {
                    foreach ($this->userConnections[$userId] as $conn) {
                        $conn->send(json_encode([
                            'type' => 'notification',
                            'data' => $data['notification']
                        ]));
                    }
                }
            }
        }
        
        // Store notification in database
        try {
            foreach ($data['to_users'] as $userId) {
                $stmt = $this->db->prepare("
                    INSERT INTO notifications (user_id, type, title, message, data, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $userId,
                    $data['notification']['type'] ?? 'general',
                    $data['notification']['title'],
                    $data['notification']['message'],
                    json_encode($data['notification']['data'] ?? [])
                ]);
            }
        } catch (Exception $e) {
            echo "Failed to store notification: " . $e->getMessage() . "\n";
        }
    }
    
    protected function handlePresence(ConnectionInterface $from, $data) {
        if (!isset($from->userId)) {
            return;
        }
        
        $this->broadcastPresence($from->userId, $data['status'] ?? 'online');
    }
    
    protected function handleDocumentUpdate(ConnectionInterface $from, $data) {
        if (!isset($from->userId)) {
            return;
        }
        
        // Broadcast document update to relevant users
        $this->broadcastToCompany($from, 'document_update', $data);
    }
    
    protected function handleTicketUpdate(ConnectionInterface $from, $data) {
        if (!isset($from->userId)) {
            return;
        }
        
        // Notify ticket participants
        if (isset($data['ticket_id'])) {
            try {
                $stmt = $this->db->prepare("
                    SELECT DISTINCT utente_id, assegnato_a 
                    FROM tickets 
                    WHERE id = ?
                ");
                $stmt->execute([$data['ticket_id']]);
                $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $userIds = array_filter([$ticket['utente_id'], $ticket['assegnato_a']]);
                
                foreach ($userIds as $userId) {
                    if (isset($this->userConnections[$userId])) {
                        foreach ($this->userConnections[$userId] as $conn) {
                            $conn->send(json_encode([
                                'type' => 'ticket_update',
                                'data' => $data
                            ]));
                        }
                    }
                }
            } catch (Exception $e) {
                echo "Failed to broadcast ticket update: " . $e->getMessage() . "\n";
            }
        }
    }
    
    protected function handleEventUpdate(ConnectionInterface $from, $data) {
        if (!isset($from->userId)) {
            return;
        }
        
        // Notify event participants
        if (isset($data['event_id'])) {
            try {
                $stmt = $this->db->prepare("
                    SELECT utente_id 
                    FROM evento_partecipanti 
                    WHERE evento_id = ?
                ");
                $stmt->execute([$data['event_id']]);
                $participants = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($participants as $userId) {
                    if (isset($this->userConnections[$userId])) {
                        foreach ($this->userConnections[$userId] as $conn) {
                            $conn->send(json_encode([
                                'type' => 'event_update',
                                'data' => $data
                            ]));
                        }
                    }
                }
            } catch (Exception $e) {
                echo "Failed to broadcast event update: " . $e->getMessage() . "\n";
            }
        }
    }
    
    protected function broadcastPresence($userId, $status) {
        $message = json_encode([
            'type' => 'presence_update',
            'user_id' => $userId,
            'status' => $status,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        // Broadcast to all authenticated clients
        foreach ($this->clients as $client) {
            if (isset($client->userId)) {
                $client->send($message);
            }
        }
    }
    
    protected function broadcastToCompany(ConnectionInterface $from, $type, $data) {
        // Get user's companies
        try {
            $stmt = $this->db->prepare("
                SELECT azienda_id 
                FROM utenti_aziende 
                WHERE utente_id = ?
            ");
            $stmt->execute([$from->userId]);
            $companyIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Get all users in same companies
            $placeholders = str_repeat('?,', count($companyIds) - 1) . '?';
            $stmt = $this->db->prepare("
                SELECT DISTINCT utente_id 
                FROM utenti_aziende 
                WHERE azienda_id IN ($placeholders)
            ");
            $stmt->execute($companyIds);
            $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Broadcast to all users in same companies
            $message = json_encode([
                'type' => $type,
                'data' => $data,
                'from_user' => $from->userId,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            foreach ($userIds as $userId) {
                if (isset($this->userConnections[$userId])) {
                    foreach ($this->userConnections[$userId] as $conn) {
                        $conn->send($message);
                    }
                }
            }
        } catch (Exception $e) {
            echo "Failed to broadcast to company: " . $e->getMessage() . "\n";
        }
    }
}

// Create and run the WebSocket server
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new NexioWebSocketServer()
        )
    ),
    8080,
    '0.0.0.0'
);

echo "WebSocket server started on port 8080\n";
echo "Press Ctrl+C to stop the server\n";

$server->run();