<?php
require_once '../config/config.php';
require_once '../middleware/Auth.php';

// Verifica autenticazione
if (!Auth::getInstance()->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorizzato']);
    exit;
}

// Verifica metodo
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
    exit;
}

try {
    $documento_id = filter_input(INPUT_GET, 'documento_id', FILTER_VALIDATE_INT);
    
    if (!$documento_id) {
        throw new Exception('ID documento mancante');
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Verifica se l'utente ha accesso al documento
    $user = Auth::getInstance()->getUser();
    $checkQuery = "SELECT id FROM documenti WHERE id = ? AND azienda_id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->execute([$documento_id, $user['azienda_id']]);
    
    if (!$checkStmt->fetch()) {
        throw new Exception('Documento non trovato o non autorizzato');
    }
    
    // Recupera frontespizio
    $query = "SELECT * FROM documenti_frontespizio WHERE documento_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$documento_id]);
    $frontespizio = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($frontespizio) {
        echo json_encode([
            'success' => true, 
            'frontespizio' => $frontespizio
        ]);
    } else {
        echo json_encode([
            'success' => true, 
            'frontespizio' => null
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 