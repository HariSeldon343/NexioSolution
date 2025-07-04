<?php
// Gestione errori PHP per evitare output HTML
ini_set('display_errors', 0);
error_reporting(0);

require_once '../config/config.php';

header('Content-Type: application/json');

$auth = Auth::getInstance();
$auth->requireAuth();

$user = $auth->getUser();

// Solo super admin può eliminare documenti
if (!$auth->isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Non hai i permessi per eliminare documenti']);
    exit;
}

// Ottieni ID documento
$data = json_decode(file_get_contents('php://input'), true);
$documentId = intval($data['id'] ?? 0);

if (!$documentId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID documento non valido']);
    exit;
}

try {
    db_connection()->beginTransaction();
    
    // Carica info documento per il log
    $stmt = db_query("SELECT titolo, codice FROM documenti WHERE id = ?", [$documentId]);
    $documento = $stmt->fetch();
    
    if (!$documento) {
        throw new Exception('Documento non trovato');
    }
    
    // Elimina destinatari
    db_query("DELETE FROM documenti_destinatari WHERE documento_id = ?", [$documentId]);
    
    // Elimina versioni
    db_query("DELETE FROM documenti_versioni WHERE documento_id = ?", [$documentId]);
    
    // Elimina documento
    db_query("DELETE FROM documenti WHERE id = ?", [$documentId]);
    
    // Log attività (opzionale, senza bloccare se fallisce)
    try {
        error_log("Documento eliminato: {$documento['titolo']} (Codice: {$documento['codice']}) da utente ID: {$user['id']}");
    } catch (Exception $logError) {
        // Ignora errori di log
    }
    
    db_connection()->commit();
    
    echo json_encode(['success' => true, 'message' => 'Documento eliminato con successo']);
    
} catch (Exception $e) {
    db_connection()->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 