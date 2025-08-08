<?php
/**
 * API per recuperare i giorni specifici di un task
 */
require_once dirname(__DIR__, 2) . '/backend/config/config.php';
require_once dirname(__DIR__, 2) . '/backend/middleware/Auth.php';

header('Content-Type: application/json');

// Verifica autenticazione
$auth = new Auth();
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non autenticato']);
    exit;
}

// Verifica permessi - solo super admin e utente speciale
if (!$auth->isSuperAdmin() && !in_array($auth->getCurrentUser()['ruolo'], ['utente_speciale'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accesso negato']);
    exit;
}

// Verifica task_id
$task_id = $_GET['task_id'] ?? null;
if (!$task_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Task ID mancante']);
    exit;
}

try {
    // Recupera i giorni del task
    $stmt = db_query("
        SELECT data_giorno 
        FROM task_giorni 
        WHERE task_id = ? 
        ORDER BY data_giorno
    ", [$task_id]);
    
    $giorni = [];
    while ($row = $stmt->fetch()) {
        $giorni[] = $row['data_giorno'];
    }
    
    echo json_encode([
        'success' => true,
        'days' => $giorni
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Errore nel recupero dei giorni: ' . $e->getMessage()
    ]);
}