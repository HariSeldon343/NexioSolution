<?php
/**
 * Get CSRF Token API
 * 
 * API per ottenere un nuovo token CSRF valido per la sessione corrente
 * 
 * @package Nexio\API
 * @version 1.0.0
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../middleware/Auth.php';
    require_once __DIR__ . '/../utils/CSRFTokenManager.php';

    // Verifica autenticazione
    $auth = Auth::getInstance();
    if (!$auth->checkSession()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Non autenticato']);
        exit;
    }

    // Ottieni token CSRF usando il manager
    $csrfManager = CSRFTokenManager::getInstance();
    $token = $csrfManager->getToken();

    // Restituisci il token corrente
    echo json_encode([
        'success' => true,
        'token' => $token,
        'expires_in' => 3600 // Token valido per 1 ora (indicativo)
    ]);

} catch (Exception $e) {
    error_log("CSRF Token API Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Errore interno del server',
        'error_code' => 'TOKEN_GENERATION_FAILED'
    ]);
}
?>