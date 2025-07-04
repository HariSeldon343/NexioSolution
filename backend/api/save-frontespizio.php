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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
    exit;
}

try {
    $documento_id = filter_input(INPUT_POST, 'documento_id', FILTER_VALIDATE_INT);
    $contenuto_json = filter_input(INPUT_POST, 'contenuto_json', FILTER_DEFAULT);
    
    if (!$documento_id || !$contenuto_json) {
        throw new Exception('Dati mancanti');
    }
    
    // Valida JSON
    $parsed = json_decode($contenuto_json);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON non valido');
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
    
    // Inizia transazione
    $conn->beginTransaction();
    
    // Verifica se esiste già un frontespizio
    $existsQuery = "SELECT id FROM documenti_frontespizio WHERE documento_id = ?";
    $existsStmt = $conn->prepare($existsQuery);
    $existsStmt->execute([$documento_id]);
    $exists = $existsStmt->fetch();
    
    if ($exists) {
        // Aggiorna frontespizio esistente
        $updateQuery = "UPDATE documenti_frontespizio SET contenuto_json = ? WHERE documento_id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->execute([$contenuto_json, $documento_id]);
    } else {
        // Inserisci nuovo frontespizio
        $insertQuery = "INSERT INTO documenti_frontespizio (documento_id, contenuto_json) VALUES (?, ?)";
        $insertStmt = $conn->prepare($insertQuery);
        $insertStmt->execute([$documento_id, $contenuto_json]);
    }
    
    // Aggiorna flag in documenti
    $updateDocQuery = "UPDATE documenti SET ha_frontespizio = 1 WHERE id = ?";
    $updateDocStmt = $conn->prepare($updateDocQuery);
    $updateDocStmt->execute([$documento_id]);
    
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Frontespizio salvato con successo']);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 