<?php
/**
 * API Session Validation Endpoint
 * Checks if the current session is valid and returns user info
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../middleware/Auth.php';

// Set JSON headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, X-Auth-Token');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Initialize auth
    $auth = Auth::getInstance();
    
    // Check if authenticated
    if (!$auth->isAuthenticated()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'authenticated' => false,
            'error' => 'Not authenticated'
        ]);
        exit;
    }
    
    // Get user info
    $user = $auth->getUser();
    
    // Get current company if applicable
    $currentCompany = null;
    if ($auth->getCurrentCompany()) {
        $companyId = $auth->getCurrentCompany();
        $stmt = db_query("SELECT id, nome FROM aziende WHERE id = ?", [$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($company) {
            $currentCompany = [
                'id' => $company['id'],
                'nome' => $company['nome']
            ];
        }
    }
    
    // Get available companies for the user
    $companies = [];
    if (!$auth->isSuperAdmin()) {
        $stmt = db_query(
            "SELECT a.id, a.nome 
            FROM aziende a 
            JOIN utenti_aziende ua ON a.id = ua.azienda_id 
            WHERE ua.utente_id = ? AND a.stato = 'attiva'
            ORDER BY a.nome",
            [$user['id']]
        );
        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Super admin can see all companies
        $stmt = db_query(
            "SELECT id, nome FROM aziende WHERE stato = 'attiva' ORDER BY nome"
        );
        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Return user info
    echo json_encode([
        'success' => true,
        'authenticated' => true,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'nome' => $user['nome'],
            'cognome' => $user['cognome'],
            'email' => $user['email'],
            'ruolo' => $user['ruolo'],
            'full_name' => trim($user['nome'] . ' ' . $user['cognome'])
        ],
        'session_id' => session_id(),
        'current_company' => $currentCompany,
        'companies' => $companies,
        'permissions' => [
            'is_super_admin' => $auth->isSuperAdmin(),
            'is_utente_speciale' => $auth->isUtenteSpeciale(),
            'has_elevated_privileges' => $auth->hasElevatedPrivileges()
        ]
    ]);
    
} catch (Exception $e) {
    error_log('API Session Validation Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}