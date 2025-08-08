<?php
/**
 * API - Get User Permissions
 * 
 * Restituisce i permessi dell'utente corrente per il frontend
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
    
    $user = $auth->getUser();
    $company = $auth->getCurrentAzienda();
    $permissionManager = PermissionManager::getInstance();
    
    // Ottieni permessi utente
    $permissions = $permissionManager->getUserPermissions($user['id'], $company['id'] ?? null);
    
    // Aggiungi CSRF token
    $csrfToken = PermissionMiddleware::generateCSRFToken();
    
    echo json_encode([
        'success' => true,
        'permissions' => $permissions,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'nome' => $user['nome'],
            'cognome' => $user['cognome'],
            'ruolo' => $user['ruolo'],
            'email' => $user['email']
        ],
        'company' => $company,
        'csrf_token' => $csrfToken
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Errore interno del server'
    ]);
}