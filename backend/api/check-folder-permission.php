<?php
/**
 * API - Check Folder Permission
 * 
 * Verifica se l'utente ha un permesso specifico su una cartella
 */

header('Content-Type: application/json');
require_once '../middleware/Auth.php';
require_once '../utils/PermissionManager.php';
require_once '../middleware/PermissionMiddleware.php';

try {
    $auth = Auth::getInstance();
    
    if (!$auth->isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Non autenticato']);
        exit;
    }
    
    // Leggi input JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['folder_id']) || !isset($input['action'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Parametri mancanti']);
        exit;
    }
    
    $folderId = (int)$input['folder_id'];
    $action = $input['action'];
    
    $user = $auth->getUser();
    $company = $auth->getCurrentAzienda();
    $permissionManager = PermissionManager::getInstance();
    
    // Verifica permesso
    $hasPermission = $permissionManager->checkFolderAccess(
        $folderId, 
        $action, 
        $user['id'], 
        $company['id'] ?? null
    );
    
    echo json_encode([
        'success' => true,
        'has_permission' => $hasPermission,
        'folder_id' => $folderId,
        'action' => $action
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Errore interno del server'
    ]);
}