<?php
/**
 * Push Notifications API
 * Gestione notifiche push per PWA usando Web Push Protocol
 */

require_once __DIR__ . '/../../backend/config/database.php';
require_once __DIR__ . '/../middleware/JWTAuth.php';

use Backend\Middleware\JWTAuth;

// Load configuration
$jwtConfig = require __DIR__ . '/../config/jwt-config.php';

// CORS Headers
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $jwtConfig['cors']['allowed_origins']) || 
    strpos($origin, 'localhost') !== false) {
    header("Access-Control-Allow-Origin: $origin");
}

header('Content-Type: application/json');
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    // Database connection
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // JWT Authentication
    $jwtAuth = new JWTAuth($pdo);
    $jwtAuth->authenticate();
    $user = $jwtAuth->getUser();
    
    $action = $_GET['action'] ?? 'send';
    
    switch ($action) {
        case 'send':
            sendPushNotification($pdo, $user);
            break;
            
        case 'send_to_user':
            sendToUser($pdo, $user);
            break;
            
        case 'broadcast':
            broadcastNotification($pdo, $user);
            break;
            
        case 'schedule':
            scheduleNotification($pdo, $user);
            break;
            
        case 'test':
            testNotification($pdo, $user);
            break;
            
        default:
            throw new Exception('Azione non valida');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Invia notifica push singola
 */
function sendPushNotification($pdo, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $targetUserId = $input['user_id'] ?? $user['id'];
    $title = $input['title'] ?? 'Nexio Calendar';
    $body = $input['body'] ?? '';
    $data = $input['data'] ?? [];
    $icon = $input['icon'] ?? '/icons/icon-192x192.png';
    $badge = $input['badge'] ?? '/icons/badge-72x72.png';
    
    try {
        // Recupera subscription attive per l'utente
        $stmt = $pdo->prepare("
            SELECT * FROM push_subscriptions 
            WHERE user_id = ? AND is_active = 1
        ");
        $stmt->execute([$targetUserId]);
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($subscriptions)) {
            throw new Exception('Nessuna subscription attiva per questo utente');
        }
        
        $results = [];
        $config = require __DIR__ . '/../config/jwt-config.php';
        
        foreach ($subscriptions as $subscription) {
            $result = sendWebPush(
                $subscription['endpoint'],
                $subscription['public_key'],
                $subscription['auth_token'],
                [
                    'title' => $title,
                    'body' => $body,
                    'icon' => $icon,
                    'badge' => $badge,
                    'data' => $data,
                    'timestamp' => time(),
                    'requireInteraction' => $input['require_interaction'] ?? false,
                    'actions' => $input['actions'] ?? []
                ],
                $config['push']
            );
            
            $results[] = [
                'device_id' => $subscription['device_id'],
                'success' => $result['success'],
                'error' => $result['error'] ?? null
            ];
            
            // Aggiorna last_used_at
            if ($result['success']) {
                $stmt = $pdo->prepare("
                    UPDATE push_subscriptions 
                    SET last_used_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$subscription['id']]);
            }
        }
        
        // Log notifica
        logNotification($pdo, $user['id'], $targetUserId, $title, $body, $results);
        
        echo json_encode([
            'success' => true,
            'sent_to' => count($subscriptions),
            'results' => $results
        ]);
        
    } catch (Exception $e) {
        throw $e;
    }
}

/**
 * Invia notifica a utente specifico
 */
function sendToUser($pdo, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $targetUserId = $input['target_user_id'] ?? null;
    $notification = $input['notification'] ?? [];
    
    if (!$targetUserId) {
        throw new Exception('ID utente target mancante');
    }
    
    // Verifica permessi (solo admin o stesso utente)
    if (!$user['ruolo'] === 'super_admin' && $user['id'] !== $targetUserId) {
        throw new Exception('Non autorizzato');
    }
    
    $input['user_id'] = $targetUserId;
    sendPushNotification($pdo, $user);
}

/**
 * Broadcast notifica a tutti gli utenti
 */
function broadcastNotification($pdo, $user) {
    // Solo super admin
    if ($user['ruolo'] !== 'super_admin') {
        http_response_code(403);
        throw new Exception('Solo super admin può inviare broadcast');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    try {
        // Recupera tutti gli utenti con subscription attive
        $stmt = $pdo->prepare("
            SELECT DISTINCT user_id 
            FROM push_subscriptions 
            WHERE is_active = 1
        ");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $totalSent = 0;
        $errors = [];
        
        foreach ($users as $userId) {
            try {
                $input['user_id'] = $userId;
                sendPushNotification($pdo, $user);
                $totalSent++;
            } catch (Exception $e) {
                $errors[] = [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        echo json_encode([
            'success' => true,
            'total_users' => count($users),
            'sent' => $totalSent,
            'errors' => $errors
        ]);
        
    } catch (Exception $e) {
        throw $e;
    }
}

/**
 * Programma notifica futura
 */
function scheduleNotification($pdo, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $scheduledAt = $input['scheduled_at'] ?? null;
    $notification = $input['notification'] ?? [];
    $targetUsers = $input['target_users'] ?? [$user['id']];
    
    if (!$scheduledAt) {
        throw new Exception('Data/ora programmazione mancante');
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO scheduled_notifications 
            (created_by, target_users, notification_data, scheduled_at, status, created_at)
            VALUES (?, ?, ?, ?, 'pending', NOW())
        ");
        
        $stmt->execute([
            $user['id'],
            json_encode($targetUsers),
            json_encode($notification),
            $scheduledAt
        ]);
        
        $notificationId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'notification_id' => $notificationId,
            'scheduled_at' => $scheduledAt
        ]);
        
    } catch (Exception $e) {
        throw $e;
    }
}

/**
 * Test notifica per debug
 */
function testNotification($pdo, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $deviceId = $input['device_id'] ?? null;
    
    try {
        // Trova subscription per test
        $sql = "SELECT * FROM push_subscriptions WHERE user_id = ? AND is_active = 1";
        $params = [$user['id']];
        
        if ($deviceId) {
            $sql .= " AND device_id = ?";
            $params[] = $deviceId;
        }
        
        $sql .= " LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$subscription) {
            throw new Exception('Nessuna subscription trovata per il test');
        }
        
        $config = require __DIR__ . '/../config/jwt-config.php';
        
        $result = sendWebPush(
            $subscription['endpoint'],
            $subscription['public_key'],
            $subscription['auth_token'],
            [
                'title' => 'Test Notifica Nexio',
                'body' => 'Questa è una notifica di test. Timestamp: ' . date('Y-m-d H:i:s'),
                'icon' => '/icons/icon-192x192.png',
                'badge' => '/icons/badge-72x72.png',
                'data' => [
                    'test' => true,
                    'timestamp' => time()
                ],
                'requireInteraction' => false
            ],
            $config['push']
        );
        
        echo json_encode([
            'success' => $result['success'],
            'device_id' => $subscription['device_id'],
            'result' => $result
        ]);
        
    } catch (Exception $e) {
        throw $e;
    }
}

/**
 * Funzione per inviare Web Push usando curl
 */
function sendWebPush($endpoint, $publicKey, $authToken, $payload, $vapidConfig) {
    try {
        // Prepara payload
        $payloadJson = json_encode($payload);
        
        // Encrypt payload (simplified - in produzione usare libreria Web Push)
        $encryptedPayload = encryptPayload($payloadJson, $publicKey, $authToken);
        
        // Genera VAPID headers
        $vapidHeaders = generateVapidHeaders($endpoint, $vapidConfig);
        
        // Invia richiesta
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $encryptedPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'TTL: 86400',
            'Authorization: ' . $vapidHeaders['authorization'],
            'Crypto-Key: ' . $vapidHeaders['crypto_key']
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true];
        } else {
            return [
                'success' => false,
                'error' => "HTTP $httpCode: " . ($error ?: $response)
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Encrypt payload per Web Push (simplified)
 */
function encryptPayload($payload, $publicKey, $authToken) {
    // In produzione, usare una libreria Web Push completa
    // Questo è solo un placeholder
    return $payload;
}

/**
 * Genera VAPID headers (simplified)
 */
function generateVapidHeaders($endpoint, $vapidConfig) {
    // In produzione, implementare VAPID completo
    // Questo è solo un placeholder
    return [
        'authorization' => 'vapid t=' . base64_encode('token') . ', k=' . $vapidConfig['vapid_public_key'],
        'crypto_key' => 'p256ecdsa=' . $vapidConfig['vapid_public_key']
    ];
}

/**
 * Log notifica inviata
 */
function logNotification($pdo, $senderId, $targetUserId, $title, $body, $results) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notification_logs 
            (sender_id, target_user_id, title, body, results, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $senderId,
            $targetUserId,
            $title,
            $body,
            json_encode($results)
        ]);
    } catch (Exception $e) {
        // Log silently
        error_log("Notification log error: " . $e->getMessage());
    }
}