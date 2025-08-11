<?php
/**
 * Sync API - Gestione sincronizzazione bidirezionale per PWA
 * Supporta sincronizzazione eventi, task e documenti con gestione conflitti
 */

require_once __DIR__ . '/../../backend/config/database.php';
require_once __DIR__ . '/../middleware/JWTAuth.php';
require_once __DIR__ . '/../utils/JWTManager.php';

use Backend\Middleware\JWTAuth;
use Backend\Utils\JWTManager;

// CORS Headers
$jwtConfig = require __DIR__ . '/../config/jwt-config.php';
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $jwtConfig['cors']['allowed_origins']) || 
    strpos($origin, 'localhost') !== false) {
    header("Access-Control-Allow-Origin: $origin");
}

header('Content-Type: application/json');
header("Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Sync-Token");
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
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    // JWT Authentication
    $jwtAuth = new JWTAuth($pdo);
    $jwtAuth->authenticate();
    $user = $jwtAuth->getUser();
    
    // Get action
    $action = $_GET['action'] ?? 'sync';
    
    switch ($action) {
        case 'sync':
            handleFullSync($pdo, $user);
            break;
            
        case 'push':
            handlePushSync($pdo, $user);
            break;
            
        case 'pull':
            handlePullSync($pdo, $user);
            break;
            
        case 'status':
            handleSyncStatus($pdo, $user);
            break;
            
        case 'resolve':
            handleConflictResolve($pdo, $user);
            break;
            
        case 'batch':
            handleBatchSync($pdo, $user);
            break;
            
        default:
            throw new Exception('Azione sync non valida');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Sincronizzazione completa bidirezionale
 */
function handleFullSync($pdo, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $deviceId = $input['device_id'] ?? null;
    $lastSync = $input['last_sync'] ?? null;
    $clientData = $input['data'] ?? [];
    
    try {
        $pdo->beginTransaction();
        
        $syncResult = [
            'pull' => [],
            'conflicts' => [],
            'acknowledged' => [],
            'timestamp' => date('c')
        ];
        
        // 1. Processa dati dal client (push)
        foreach ($clientData as $entity) {
            $result = processSyncEntity($pdo, $user, $entity, $deviceId);
            
            if ($result['status'] === 'conflict') {
                $syncResult['conflicts'][] = $result;
            } else {
                $syncResult['acknowledged'][] = [
                    'client_id' => $entity['client_id'] ?? null,
                    'server_id' => $result['server_id'],
                    'version' => $result['version']
                ];
            }
        }
        
        // 2. Recupera modifiche dal server (pull)
        $syncResult['pull']['eventi'] = getModifiedEvents($pdo, $user, $lastSync);
        $syncResult['pull']['tasks'] = getModifiedTasks($pdo, $user, $lastSync);
        
        // 3. Aggiorna sync log
        logSyncOperation($pdo, $user['id'], $deviceId, 'full_sync', $syncResult);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'sync' => $syncResult
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Push - Invia modifiche dal client al server
 */
function handlePushSync($pdo, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $deviceId = $input['device_id'] ?? null;
    $entities = $input['entities'] ?? [];
    
    try {
        $pdo->beginTransaction();
        
        $results = [];
        $conflicts = [];
        
        foreach ($entities as $entity) {
            $result = processSyncEntity($pdo, $user, $entity, $deviceId);
            
            if ($result['status'] === 'conflict') {
                $conflicts[] = $result;
            } else {
                $results[] = $result;
            }
        }
        
        // Log sync operation
        logSyncOperation($pdo, $user['id'], $deviceId, 'push', [
            'entities_count' => count($entities),
            'success_count' => count($results),
            'conflict_count' => count($conflicts)
        ]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'results' => $results,
            'conflicts' => $conflicts,
            'timestamp' => date('c')
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Pull - Recupera modifiche dal server
 */
function handlePullSync($pdo, $user) {
    $lastSync = $_GET['last_sync'] ?? null;
    $entityTypes = $_GET['types'] ?? 'eventi,tasks';
    $limit = min(intval($_GET['limit'] ?? 100), 500);
    
    try {
        $types = explode(',', $entityTypes);
        $data = [];
        
        if (in_array('eventi', $types)) {
            $data['eventi'] = getModifiedEvents($pdo, $user, $lastSync, $limit);
        }
        
        if (in_array('tasks', $types)) {
            $data['tasks'] = getModifiedTasks($pdo, $user, $lastSync, $limit);
        }
        
        if (in_array('documenti', $types)) {
            $data['documenti'] = getModifiedDocuments($pdo, $user, $lastSync, $limit);
        }
        
        // Log sync operation
        logSyncOperation($pdo, $user['id'], $_GET['device_id'] ?? null, 'pull', [
            'types' => $types,
            'count' => array_sum(array_map('count', $data))
        ]);
        
        echo json_encode([
            'success' => true,
            'data' => $data,
            'timestamp' => date('c'),
            'has_more' => checkHasMore($pdo, $user, $lastSync, $types, $limit)
        ]);
        
    } catch (Exception $e) {
        throw $e;
    }
}

/**
 * Stato sincronizzazione
 */
function handleSyncStatus($pdo, $user) {
    $deviceId = $_GET['device_id'] ?? null;
    
    try {
        // Ottieni ultimo sync
        $stmt = $pdo->prepare("
            SELECT * FROM sync_queue 
            WHERE user_id = ? 
            AND ($deviceId IS NULL OR device_id = ?)
            AND status IN ('completed', 'failed')
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$user['id'], $deviceId]);
        $lastSync = $stmt->fetch();
        
        // Conta entità pendenti
        $stmt = $pdo->prepare("
            SELECT 
                entity_type,
                COUNT(*) as count,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'conflict' THEN 1 ELSE 0 END) as conflicts
            FROM sync_queue
            WHERE user_id = ?
            AND ($deviceId IS NULL OR device_id = ?)
            AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY entity_type
        ");
        $stmt->execute([$user['id'], $deviceId]);
        $stats = $stmt->fetchAll();
        
        // Verifica connettività
        $isOnline = checkServerConnectivity();
        
        echo json_encode([
            'success' => true,
            'status' => [
                'last_sync' => $lastSync ? $lastSync['synced_at'] : null,
                'pending_items' => array_sum(array_column($stats, 'pending')),
                'conflict_items' => array_sum(array_column($stats, 'conflicts')),
                'stats_by_type' => $stats,
                'is_online' => $isOnline,
                'sync_enabled' => true,
                'device_id' => $deviceId
            ]
        ]);
        
    } catch (Exception $e) {
        throw $e;
    }
}

/**
 * Risolvi conflitti
 */
function handleConflictResolve($pdo, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $conflictId = $input['conflict_id'] ?? null;
    $resolution = $input['resolution'] ?? 'server_wins';
    $mergedData = $input['merged_data'] ?? null;
    
    if (!$conflictId) {
        throw new Exception('ID conflitto mancante');
    }
    
    try {
        $pdo->beginTransaction();
        
        // Recupera conflitto
        $stmt = $pdo->prepare("
            SELECT * FROM sync_queue 
            WHERE id = ? AND user_id = ? AND status = 'conflict'
        ");
        $stmt->execute([$conflictId, $user['id']]);
        $conflict = $stmt->fetch();
        
        if (!$conflict) {
            throw new Exception('Conflitto non trovato');
        }
        
        $conflictData = json_decode($conflict['data'], true);
        $resolved = false;
        $finalData = null;
        
        switch ($resolution) {
            case 'client_wins':
                $finalData = $conflictData['client_data'];
                $resolved = applyEntityData($pdo, $conflict['entity_type'], 
                                          $conflict['entity_id'], $finalData);
                break;
                
            case 'server_wins':
                $finalData = $conflictData['server_data'];
                $resolved = true; // Server data already in place
                break;
                
            case 'merge':
                if (!$mergedData) {
                    throw new Exception('Dati merged mancanti');
                }
                $finalData = $mergedData;
                $resolved = applyEntityData($pdo, $conflict['entity_type'], 
                                          $conflict['entity_id'], $finalData);
                break;
        }
        
        // Aggiorna stato conflitto
        $stmt = $pdo->prepare("
            UPDATE sync_queue 
            SET status = 'resolved', 
                conflict_resolution = ?,
                data = ?,
                synced_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $resolution,
            json_encode(['resolution' => $resolution, 'final_data' => $finalData]),
            $conflictId
        ]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'resolved' => $resolved,
            'final_data' => $finalData
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Sincronizzazione batch per grandi quantità di dati
 */
function handleBatchSync($pdo, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $batchId = $input['batch_id'] ?? uniqid('batch_');
    $batchNumber = intval($input['batch_number'] ?? 1);
    $totalBatches = intval($input['total_batches'] ?? 1);
    $entities = $input['entities'] ?? [];
    
    try {
        $pdo->beginTransaction();
        
        // Processa batch
        $results = [];
        foreach ($entities as $entity) {
            $results[] = processSyncEntity($pdo, $user, $entity, $input['device_id'] ?? null);
        }
        
        // Salva stato batch
        $stmt = $pdo->prepare("
            INSERT INTO sync_batches 
            (batch_id, user_id, batch_number, total_batches, 
             entities_count, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
            status = VALUES(status),
            entities_count = entities_count + VALUES(entities_count)
        ");
        
        $stmt->execute([
            $batchId,
            $user['id'],
            $batchNumber,
            $totalBatches,
            count($entities),
            $batchNumber === $totalBatches ? 'completed' : 'processing'
        ]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'batch_id' => $batchId,
            'batch_number' => $batchNumber,
            'total_batches' => $totalBatches,
            'processed' => count($results),
            'is_complete' => $batchNumber === $totalBatches
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// Helper Functions

function processSyncEntity($pdo, $user, $entity, $deviceId) {
    $type = $entity['type'] ?? '';
    $operation = $entity['operation'] ?? '';
    $data = $entity['data'] ?? [];
    $version = $entity['version'] ?? 1;
    $clientId = $entity['client_id'] ?? null;
    
    // Verifica conflitti di versione
    if ($entity['server_id'] ?? null) {
        $serverVersion = getEntityVersion($pdo, $type, $entity['server_id']);
        
        if ($serverVersion && $serverVersion > $version) {
            // Conflitto rilevato
            return [
                'status' => 'conflict',
                'client_id' => $clientId,
                'server_id' => $entity['server_id'],
                'client_version' => $version,
                'server_version' => $serverVersion,
                'type' => $type
            ];
        }
    }
    
    // Applica operazione
    $serverId = null;
    $newVersion = $version + 1;
    
    switch ($operation) {
        case 'create':
            $serverId = createEntity($pdo, $type, $data, $user['id']);
            break;
            
        case 'update':
            $serverId = $entity['server_id'];
            updateEntity($pdo, $type, $serverId, $data, $newVersion);
            break;
            
        case 'delete':
            $serverId = $entity['server_id'];
            deleteEntity($pdo, $type, $serverId);
            break;
    }
    
    // Registra in sync queue
    $stmt = $pdo->prepare("
        INSERT INTO sync_queue 
        (user_id, device_id, entity_type, entity_id, operation, 
         data, version, status, created_at, synced_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', NOW(), NOW())
    ");
    
    $stmt->execute([
        $user['id'],
        $deviceId,
        $type,
        $serverId,
        $operation,
        json_encode($data),
        $newVersion
    ]);
    
    return [
        'status' => 'success',
        'client_id' => $clientId,
        'server_id' => $serverId,
        'version' => $newVersion,
        'type' => $type,
        'operation' => $operation
    ];
}

function getModifiedEvents($pdo, $user, $lastSync, $limit = 100) {
    $sql = "
        SELECT e.*, 
               GREATEST(e.creato_il, IFNULL(e.aggiornato_il, e.creato_il)) as modified_at
        FROM eventi e
        WHERE (e.creata_da = ? OR e.azienda_id IN (
            SELECT azienda_id FROM utenti_aziende WHERE utente_id = ?
        ))
    ";
    
    $params = [$user['id'], $user['id']];
    
    if ($lastSync) {
        $sql .= " AND GREATEST(e.creato_il, IFNULL(e.aggiornato_il, e.creato_il)) > ?";
        $params[] = $lastSync;
    }
    
    $sql .= " ORDER BY modified_at ASC LIMIT ?";
    $params[] = $limit;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

function getModifiedTasks($pdo, $user, $lastSync, $limit = 100) {
    $sql = "
        SELECT t.*, 
               GREATEST(t.created_at, IFNULL(t.updated_at, t.created_at)) as modified_at
        FROM tasks t
        WHERE (t.assegnato_a = ? OR t.creato_da = ?)
    ";
    
    $params = [$user['id'], $user['id']];
    
    if ($lastSync) {
        $sql .= " AND GREATEST(t.created_at, IFNULL(t.updated_at, t.created_at)) > ?";
        $params[] = $lastSync;
    }
    
    $sql .= " ORDER BY modified_at ASC LIMIT ?";
    $params[] = $limit;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

function getModifiedDocuments($pdo, $user, $lastSync, $limit = 100) {
    $sql = "
        SELECT d.*
        FROM documenti d
        WHERE d.creato_da = ?
    ";
    
    $params = [$user['id']];
    
    if ($lastSync) {
        $sql .= " AND d.data_creazione > ?";
        $params[] = $lastSync;
    }
    
    $sql .= " ORDER BY d.data_creazione ASC LIMIT ?";
    $params[] = $limit;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

function getEntityVersion($pdo, $type, $id) {
    $table = '';
    switch ($type) {
        case 'evento':
            $table = 'eventi';
            break;
        case 'task':
            $table = 'tasks';
            break;
        case 'documento':
            $table = 'documenti';
            break;
        default:
            return null;
    }
    
    $stmt = $pdo->prepare("SELECT version FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    
    return $stmt->fetchColumn();
}

function createEntity($pdo, $type, $data, $userId) {
    switch ($type) {
        case 'evento':
            $stmt = $pdo->prepare("
                INSERT INTO eventi (titolo, descrizione, data_inizio, data_fine, 
                                  luogo, tipo, creata_da, version, creato_il)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute([
                $data['titolo'],
                $data['descrizione'] ?? '',
                $data['data_inizio'],
                $data['data_fine'] ?? $data['data_inizio'],
                $data['luogo'] ?? '',
                $data['tipo'] ?? 'evento',
                $userId
            ]);
            return $pdo->lastInsertId();
            
        case 'task':
            $stmt = $pdo->prepare("
                INSERT INTO tasks (titolo, descrizione, data_scadenza, 
                                 priorita, stato, assegnato_a, creato_da, version, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute([
                $data['titolo'],
                $data['descrizione'] ?? '',
                $data['data_scadenza'],
                $data['priorita'] ?? 'media',
                $data['stato'] ?? 'pending',
                $data['assegnato_a'] ?? $userId,
                $userId
            ]);
            return $pdo->lastInsertId();
    }
    
    return null;
}

function updateEntity($pdo, $type, $id, $data, $version) {
    switch ($type) {
        case 'evento':
            $stmt = $pdo->prepare("
                UPDATE eventi 
                SET titolo = ?, descrizione = ?, data_inizio = ?, 
                    data_fine = ?, luogo = ?, tipo = ?,
                    version = ?, aggiornato_il = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $data['titolo'],
                $data['descrizione'],
                $data['data_inizio'],
                $data['data_fine'],
                $data['luogo'],
                $data['tipo'],
                $version,
                $id
            ]);
            break;
            
        case 'task':
            $stmt = $pdo->prepare("
                UPDATE tasks 
                SET titolo = ?, descrizione = ?, data_scadenza = ?,
                    priorita = ?, stato = ?, version = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $data['titolo'],
                $data['descrizione'],
                $data['data_scadenza'],
                $data['priorita'],
                $data['stato'],
                $version,
                $id
            ]);
            break;
    }
}

function deleteEntity($pdo, $type, $id) {
    switch ($type) {
        case 'evento':
            $stmt = $pdo->prepare("DELETE FROM eventi WHERE id = ?");
            $stmt->execute([$id]);
            break;
            
        case 'task':
            $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
            $stmt->execute([$id]);
            break;
    }
}

function applyEntityData($pdo, $type, $id, $data) {
    try {
        updateEntity($pdo, $type, $id, $data, ($data['version'] ?? 1) + 1);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function checkHasMore($pdo, $user, $lastSync, $types, $limit) {
    // Verifica se ci sono altri record da sincronizzare
    foreach ($types as $type) {
        $count = 0;
        
        switch ($type) {
            case 'eventi':
                $sql = "SELECT COUNT(*) FROM eventi WHERE creata_da = ?";
                $params = [$user['id']];
                if ($lastSync) {
                    $sql .= " AND GREATEST(creato_il, IFNULL(aggiornato_il, creato_il)) > ?";
                    $params[] = $lastSync;
                }
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $count = $stmt->fetchColumn();
                break;
        }
        
        if ($count > $limit) {
            return true;
        }
    }
    
    return false;
}

function checkServerConnectivity() {
    // Verifica connettività con il server
    return true; // Simplified for now
}

function logSyncOperation($pdo, $userId, $deviceId, $operation, $details) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO mobile_api_logs 
            (user_id, device_id, endpoint, method, request_body, 
             response_code, created_at)
            VALUES (?, ?, ?, ?, ?, 200, NOW())
        ");
        
        $stmt->execute([
            $userId,
            $deviceId,
            "/sync/$operation",
            $_SERVER['REQUEST_METHOD'],
            json_encode($details)
        ]);
    } catch (Exception $e) {
        // Log silently
        error_log("Sync log error: " . $e->getMessage());
    }
}