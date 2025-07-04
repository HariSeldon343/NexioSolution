<?php
require_once '../config/config.php';
require_once '../middleware/Auth.php';

// Verifica autenticazione
$auth = Auth::getInstance();
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autenticato']);
    exit;
}

header('Content-Type: application/json');

try {
    $templateId = $_GET['id'] ?? null;
    
    if (!$templateId) {
        http_response_code(400);
        echo json_encode(['error' => 'ID template richiesto']);
        exit;
    }
    
    $db = Database::getInstance();
    
    $stmt = $db->prepare("
        SELECT 
            id,
            nome,
            header_content,
            footer_content,
            modulo_id,
            created_at
        FROM moduli_template 
        WHERE id = ?
    ");
    
    $stmt->execute([$templateId]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($template) {
        echo json_encode($template);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Template non trovato']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore server: ' . $e->getMessage()]);
}
?> 