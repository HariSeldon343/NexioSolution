<?php
/**
 * API per gestione permessi documenti e cartelle
 * Consente di assegnare, revocare e visualizzare permessi
 * 
 * @package Nexio
 * @version 1.0.0
 */

require_once '../config/config.php';
require_once '../middleware/Auth.php';
require_once '../utils/PermissionManager.php';

// Headers API
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Autenticazione
$auth = Auth::getInstance();
if (!$auth->checkSession()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non autenticato']);
    exit;
}

$user = $auth->getUser();
$userId = $user['id'];
$userRole = $user['ruolo'];
$companyId = $_SESSION['azienda_id'] ?? null;

// Solo admin e super_admin possono gestire permessi
if (!in_array($userRole, ['super_admin', 'utente_speciale', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Privilegi insufficienti']);
    exit;
}

$permissionManager = PermissionManager::getInstance();

// Gestione richieste
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

try {
    switch ($method) {
        case 'GET':
            handleGet($action);
            break;
        case 'POST':
            handlePost($action, $input);
            break;
        case 'DELETE':
            handleDelete($input);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Metodo non supportato']);
    }
} catch (Exception $e) {
    error_log("Errore API permessi: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * GET - Recupera permessi
 */
function handleGet($action) {
    global $permissionManager, $userId, $companyId, $userRole;
    
    switch ($action) {
        case 'user_permissions':
            // Recupera permessi di un utente
            $targetUserId = $_GET['user_id'] ?? $userId;
            
            // Verifica che possa vedere i permessi dell'utente
            if ($targetUserId != $userId && $userRole != 'super_admin') {
                // Verifica che l'utente appartenga alla stessa azienda
                $stmt = db_query("
                    SELECT COUNT(*) FROM utenti_aziende 
                    WHERE utente_id = ? AND azienda_id = ?
                ", [$targetUserId, $companyId]);
                
                if ($stmt->fetchColumn() == 0) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Accesso negato']);
                    return;
                }
            }
            
            $permissions = $permissionManager->getUserPermissions($targetUserId, $companyId);
            echo json_encode(['success' => true, 'data' => $permissions]);
            break;
            
        case 'document_permissions':
            // Recupera permessi per un documento
            $documentId = $_GET['document_id'] ?? null;
            if (!$documentId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID documento mancante']);
                return;
            }
            
            // Verifica accesso al documento
            if (!$permissionManager->checkDocumentAccess($documentId, 'view', $userId, $companyId)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Accesso negato']);
                return;
            }
            
            $stmt = db_query("
                SELECT dp.*, u.nome, u.cognome, u.username, g.nome as granted_by_name
                FROM document_permissions dp
                JOIN utenti u ON dp.user_id = u.id
                JOIN utenti g ON dp.granted_by = g.id
                WHERE dp.document_id = ? AND dp.azienda_id = ?
                ORDER BY dp.granted_at DESC
            ", [$documentId, $companyId]);
            
            $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $permissions]);
            break;
            
        case 'folder_permissions':
            // Recupera permessi per una cartella
            $folderId = $_GET['folder_id'] ?? null;
            if (!$folderId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID cartella mancante']);
                return;
            }
            
            // Verifica accesso alla cartella
            if (!$permissionManager->checkFolderAccess($folderId, 'view', $userId, $companyId)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Accesso negato']);
                return;
            }
            
            $stmt = db_query("
                SELECT fp.*, u.nome, u.cognome, u.username, g.nome as granted_by_name
                FROM folder_permissions fp
                JOIN utenti u ON fp.user_id = u.id
                JOIN utenti g ON fp.granted_by = g.id
                WHERE fp.folder_id = ? AND fp.azienda_id = ?
                ORDER BY fp.granted_at DESC
            ", [$folderId, $companyId]);
            
            $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $permissions]);
            break;
            
        case 'available_permissions':
            // Lista permessi disponibili
            $permissions = $permissionManager->getAvailablePermissions();
            echo json_encode(['success' => true, 'data' => $permissions]);
            break;
            
        case 'role_permissions':
            // Permessi per ruolo
            $role = $_GET['role'] ?? null;
            if (!$role) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ruolo mancante']);
                return;
            }
            
            $rolePermissions = PermissionManager::ROLE_PERMISSIONS[$role] ?? [];
            echo json_encode(['success' => true, 'data' => $rolePermissions]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Azione non valida']);
    }
}

/**
 * POST - Assegna permessi
 */
function handlePost($action, $input) {
    global $permissionManager, $userId, $companyId, $userRole;
    
    switch ($action) {
        case 'assign_permissions':
            // Validazione input
            if (empty($input['user_id']) || empty($input['permissions']) || 
                empty($input['resource_type']) || empty($input['resource_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Parametri mancanti']);
                return;
            }
            
            $targetUserId = intval($input['user_id']);
            $permissions = $input['permissions'];
            $resourceType = $input['resource_type'];
            $resourceId = intval($input['resource_id']);
            
            // Verifica permessi per la risorsa
            if ($resourceType === 'document') {
                if (!$permissionManager->checkDocumentAccess($resourceId, 'share', $userId, $companyId)) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Non hai i permessi per condividere questo documento']);
                    return;
                }
            } elseif ($resourceType === 'folder') {
                if (!$permissionManager->checkFolderAccess($resourceId, 'manage_permissions', $userId, $companyId)) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Non hai i permessi per gestire i permessi di questa cartella']);
                    return;
                }
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Tipo risorsa non valido']);
                return;
            }
            
            try {
                $permissionManager->assignPermissions(
                    $targetUserId, 
                    $permissions, 
                    $resourceType, 
                    $resourceId, 
                    $userId
                );
                
                echo json_encode(['success' => true, 'message' => 'Permessi assegnati con successo']);
                
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
            
        case 'assign_bulk_permissions':
            // Assegna permessi a più utenti contemporaneamente
            if (empty($input['user_ids']) || empty($input['permissions']) || 
                empty($input['resource_type']) || empty($input['resource_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Parametri mancanti']);
                return;
            }
            
            $userIds = $input['user_ids'];
            $permissions = $input['permissions'];
            $resourceType = $input['resource_type'];
            $resourceId = intval($input['resource_id']);
            
            $success = [];
            $errors = [];
            
            foreach ($userIds as $targetUserId) {
                try {
                    $permissionManager->assignPermissions(
                        $targetUserId, 
                        $permissions, 
                        $resourceType, 
                        $resourceId, 
                        $userId
                    );
                    $success[] = $targetUserId;
                } catch (Exception $e) {
                    $errors[$targetUserId] = $e->getMessage();
                }
            }
            
            echo json_encode([
                'success' => count($errors) === 0,
                'assigned' => $success,
                'errors' => $errors,
                'message' => 'Permessi assegnati a ' . count($success) . ' utenti'
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Azione non valida']);
    }
}

/**
 * DELETE - Revoca permessi
 */
function handleDelete($input) {
    global $permissionManager, $userId, $companyId;
    
    // Validazione input
    if (empty($input['user_id']) || empty($input['resource_type']) || 
        empty($input['resource_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Parametri mancanti']);
        return;
    }
    
    $targetUserId = intval($input['user_id']);
    $resourceType = $input['resource_type'];
    $resourceId = intval($input['resource_id']);
    $permissionType = $input['permission_type'] ?? null; // Se null, revoca tutti
    
    // Verifica permessi per la risorsa
    if ($resourceType === 'document') {
        if (!$permissionManager->checkDocumentAccess($resourceId, 'share', $userId, $companyId)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Non hai i permessi per gestire questo documento']);
            return;
        }
    } elseif ($resourceType === 'folder') {
        if (!$permissionManager->checkFolderAccess($resourceId, 'manage_permissions', $userId, $companyId)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Non hai i permessi per gestire questa cartella']);
            return;
        }
    }
    
    try {
        db_begin_transaction();
        
        if ($resourceType === 'document') {
            $query = "DELETE FROM document_permissions 
                      WHERE user_id = ? AND document_id = ? AND azienda_id = ?";
            $params = [$targetUserId, $resourceId, $companyId];
            
            if ($permissionType) {
                $query .= " AND permission_type = ?";
                $params[] = $permissionType;
            }
            
            db_query($query, $params);
            
        } elseif ($resourceType === 'folder') {
            $query = "DELETE FROM folder_permissions 
                      WHERE user_id = ? AND folder_id = ? AND azienda_id = ?";
            $params = [$targetUserId, $resourceId, $companyId];
            
            if ($permissionType) {
                $query .= " AND permission_type = ?";
                $params[] = $permissionType;
            }
            
            db_query($query, $params);
        }
        
        // Log attività
        if (class_exists('ActivityLogger')) {
            ActivityLogger::getInstance()->log('permissions_revoked', $resourceType, $resourceId, [
                'target_user_id' => $targetUserId,
                'permission_type' => $permissionType,
                'revoked_by' => $userId
            ]);
        }
        
        // Pulisci cache
        $permissionManager->clearUserCache($targetUserId);
        
        db_commit();
        
        echo json_encode(['success' => true, 'message' => 'Permessi revocati con successo']);
        
    } catch (Exception $e) {
        db_rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}