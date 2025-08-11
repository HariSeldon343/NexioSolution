<?php
// API Mobile per Autenticazione
require_once '../config/config.php';
require_once '../middleware/Auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$auth = Auth::getInstance();

try {
    switch ($action) {
        case 'login':
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            
            if (empty($username) || empty($password)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Username e password richiesti']);
                exit();
            }
            
            // Cerca utente per username o email
            $stmt = $pdo->prepare("
                SELECT * FROM utenti 
                WHERE (username = ? OR email = ?) AND stato = 'attivo'
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || !password_verify($password, $user['password'])) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Credenziali non valide']);
                exit();
            }
            
            // Genera token semplice (base64 dell'ID utente)
            // In produzione usare JWT
            $token = base64_encode($user['id']);
            
            // Ottieni aziende dell'utente
            $stmt = $pdo->prepare("
                SELECT a.*, ua.ruolo, ua.is_default 
                FROM aziende a
                JOIN utenti_aziende ua ON a.id = ua.azienda_id
                WHERE ua.utente_id = ? AND a.stato = 'attiva'
                ORDER BY ua.is_default DESC, a.nome ASC
            ");
            $stmt->execute([$user['id']]);
            $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Seleziona azienda di default
            $currentCompany = null;
            foreach ($companies as $company) {
                if ($company['is_default'] || !$currentCompany) {
                    $currentCompany = $company;
                }
            }
            
            // Rimuovi password dall'oggetto utente
            unset($user['password']);
            unset($user['reset_token']);
            unset($user['reset_expiry']);
            
            // Aggiorna ultimo accesso
            $stmt = $pdo->prepare("UPDATE utenti SET ultimo_accesso = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'token' => $token,
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'nome' => $user['nome'],
                        'cognome' => $user['cognome'],
                        'nomeCompleto' => $user['nome'] . ' ' . $user['cognome'],
                        'role' => $user['role'],
                        'isSuperAdmin' => $user['role'] === 'super_admin',
                        'hasElevatedPrivileges' => in_array($user['role'], ['super_admin', 'utente_speciale'])
                    ],
                    'companies' => $companies,
                    'currentCompany' => $currentCompany
                ]
            ]);
            break;
            
        case 'logout':
            // Per mobile non c'è molto da fare lato server
            // Il client deve eliminare il token salvato
            echo json_encode(['success' => true]);
            break;
            
        case 'check':
            // Verifica se il token è ancora valido
            $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            $token = str_replace('Bearer ', '', $token);
            
            if (empty($token)) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Token mancante']);
                exit();
            }
            
            $userId = intval(base64_decode($token));
            
            if ($userId <= 0) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Token non valido']);
                exit();
            }
            
            $stmt = $pdo->prepare("SELECT * FROM utenti WHERE id = ? AND stato = 'attivo'");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Utente non trovato']);
                exit();
            }
            
            // Ottieni aziende
            $stmt = $pdo->prepare("
                SELECT a.*, ua.ruolo, ua.is_default 
                FROM aziende a
                JOIN utenti_aziende ua ON a.id = ua.azienda_id
                WHERE ua.utente_id = ? AND a.stato = 'attiva'
                ORDER BY ua.is_default DESC, a.nome ASC
            ");
            $stmt->execute([$userId]);
            $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $currentCompany = null;
            foreach ($companies as $company) {
                if ($company['is_default'] || !$currentCompany) {
                    $currentCompany = $company;
                }
            }
            
            unset($user['password']);
            unset($user['reset_token']);
            unset($user['reset_expiry']);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'nome' => $user['nome'],
                        'cognome' => $user['cognome'],
                        'nomeCompleto' => $user['nome'] . ' ' . $user['cognome'],
                        'role' => $user['role'],
                        'isSuperAdmin' => $user['role'] === 'super_admin'],
                        'hasElevatedPrivileges' => in_array($user['role'], ['super_admin', 'utente_speciale'])
                    ],
                    'companies' => $companies,
                    'currentCompany' => $currentCompany
                ]
            ]);
            break;
            
        case 'switch-company':
            $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            $token = str_replace('Bearer ', '', $token);
            $companyId = $_POST['company_id'] ?? 0;
            
            if (empty($token)) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Non autorizzato']);
                exit();
            }
            
            $userId = intval(base64_decode($token));
            
            // Verifica che l'utente abbia accesso all'azienda
            $stmt = $pdo->prepare("
                SELECT a.*, ua.ruolo 
                FROM aziende a
                JOIN utenti_aziende ua ON a.id = ua.azienda_id
                WHERE ua.utente_id = ? AND a.id = ? AND a.stato = 'attiva'
            ");
            $stmt->execute([$userId, $companyId]);
            $company = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$company) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Accesso negato all\'azienda']);
                exit();
            }
            
            // Aggiorna azienda di default per l'utente
            $pdo->prepare("UPDATE utenti_aziende SET is_default = 0 WHERE utente_id = ?")->execute([$userId]);
            $pdo->prepare("UPDATE utenti_aziende SET is_default = 1 WHERE utente_id = ? AND azienda_id = ?")->execute([$userId, $companyId]);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'currentCompany' => $company
                ]
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Azione non valida']);
    }
} catch (Exception $e) {
    error_log("Errore mobile-auth-api: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore server']);
}