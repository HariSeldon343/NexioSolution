<?php
/**
 * API - Get Resource Permissions
 * 
 * Ottiene i permessi correnti per una risorsa (documento o cartella)
 */

header('Content-Type: application/json');
require_once '../middleware/Auth.php';
require_once '../utils/PermissionManager.php';
require_once '../middleware/PermissionMiddleware.php';
require_once '../config/permission-helpers.php';

try {
    $auth = Auth::getInstance();
    
    if (!$auth->isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Non autenticato']);
        exit;
    }
    
    $user = $auth->getUser();
    $company = $auth->getCurrentAzienda();
    
    // Verifica permessi admin
    if (!in_array($user['ruolo'], ['super_admin', 'admin', 'utente_speciale'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permessi insufficienti']);
        exit;
    }
    
    // Leggi input JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['resource_type']) || !isset($input['resource_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Parametri mancanti']);
        exit;
    }
    
    $resourceType = $input['resource_type'];
    $resourceId = (int)$input['resource_id'];
    $companyId = $company['id'] ?? null;
    
    if (!$companyId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Azienda non selezionata']);
        exit;
    }
    
    // Ottieni utenti azienda
    $stmt = db_query("
        SELECT DISTINCT u.id, u.username, u.nome, u.cognome, u.email, u.ruolo
        FROM utenti u
        JOIN utenti_aziende ua ON u.id = ua.utente_id
        WHERE ua.azienda_id = ? AND u.attivo = 1 AND ua.attivo = 1
        ORDER BY u.nome, u.cognome
    ", [$companyId]);
    
    $users = $stmt->fetchAll();
    
    // Ottieni permessi esistenti per la risorsa
    $permissions = [];
    
    if ($resourceType === 'document') {
        $permissions = getDocumentUsers($resourceId, $companyId);
    } elseif ($resourceType === 'folder') {
        $permissions = getFolderUsers($resourceId, $companyId);
    }
    
    echo json_encode([
        'success' => true,
        'users' => $users,
        'permissions' => $permissions,
        'resource_type' => $resourceType,
        'resource_id' => $resourceId
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Errore interno del server'
    ]);
}