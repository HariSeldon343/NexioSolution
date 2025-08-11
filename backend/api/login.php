<?php
/**
 * API Login Endpoint for Mobile/Flutter Application
 * Returns JSON response for authentication
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../middleware/Auth.php';
require_once __DIR__ . '/../utils/RateLimiter.php';
require_once __DIR__ . '/../utils/ActivityLogger.php';

// Set JSON headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, X-Auth-Token');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use POST.'
    ]);
    exit;
}

try {
    // Initialize services
    $auth = Auth::getInstance();
    $rateLimiter = RateLimiter::getInstance();
    $logger = ActivityLogger::getInstance();
    
    // Check if already authenticated
    if ($auth->isAuthenticated()) {
        echo json_encode([
            'success' => true,
            'message' => 'Already authenticated',
            'user' => [
                'id' => $auth->getUserId(),
                'username' => $auth->getUser()['username'],
                'nome' => $auth->getUser()['nome'],
                'cognome' => $auth->getUser()['cognome'],
                'email' => $auth->getUser()['email'],
                'ruolo' => $auth->getUser()['ruolo']
            ],
            'session_id' => session_id()
        ]);
        exit;
    }
    
    // Get input data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Also accept form data
    if (empty($data)) {
        $data = $_POST;
    }
    
    // Validate input
    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Username and password are required'
        ]);
        exit;
    }
    
    // Check rate limiting
    $ip = get_client_ip();
    if (!$rateLimiter->isAllowed('login', $ip)) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => $rateLimiter->getErrorMessage('login', $ip)
        ]);
        exit;
    }
    
    // Record login attempt
    $rateLimiter->recordAttempt('login', $ip, false);
    
    // Attempt login
    $result = $auth->login($username, $password);
    
    if ($result['success']) {
        // Login successful
        $rateLimiter->recordAttempt('login', $ip, true);
        
        // Log successful login
        $user = $auth->getUser();
        if ($user) {
            $logger->logLogin($user['id']);
        }
        
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
        
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
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
        
    } else {
        // Login failed
        $logger->logFailedLogin($username, $ip);
        
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => $result['message'] ?? 'Invalid username or password'
        ]);
    }
    
} catch (Exception $e) {
    error_log('API Login Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}