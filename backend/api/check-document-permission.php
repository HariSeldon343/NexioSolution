<?php
/**
 * API - Check Document Permission
 * 
 * Verifica se l'utente ha un permesso specifico su un documento
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
    
    if (!isset($input['document_id']) || !isset($input['action'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Parametri mancanti']);
        exit;
    }
    
    $documentId = (int)$input['document_id'];
    $action = $input['action'];
    
    $user = $auth->getUser();
    $company = $auth->getCurrentAzienda();
    $permissionManager = PermissionManager::getInstance();
    
    // Verifica permesso
    $hasPermission = $permissionManager->checkDocumentAccess(
        $documentId, 
        $action, 
        $user['id'], 
        $company['id'] ?? null
    );
    
    echo json_encode([
        'success' => true,
        'has_permission' => $hasPermission,
        'document_id' => $documentId,
        'action' => $action
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Errore interno del server'
    ]);
}