<?php
require_once '../config/config.php';

header('Content-Type: application/json');

$auth = Auth::getInstance();
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autenticato']);
    exit;
}

// Solo super admin e utenti speciali possono cambiare azienda liberamente
if (!$auth->hasElevatedPrivileges()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Non autorizzato']);
    exit;
}

// Leggi JSON dal body della richiesta
$json = file_get_contents('php://input');
$data = json_decode($json, true);

$azienda_id = $data['azienda_id'] ?? null;

try {
    if ($azienda_id) {
        // Verifica che l'azienda esista e sia attiva
        $stmt = db_query("SELECT id, nome FROM aziende WHERE id = ? AND stato = 'attiva'", [$azienda_id]);
        $azienda = $stmt->fetch();
        
        if (!$azienda) {
            throw new Exception('Azienda non trovata o non attiva');
        }
        
        // Imposta l'azienda nella sessione
        $_SESSION['azienda_id'] = $azienda_id;
        $_SESSION['azienda_nome'] = $azienda['nome'];
        
        // Log attività
        $logger = ActivityLogger::getInstance();
        $logger->log('sistema', 'switch_azienda', $azienda_id, 
                    ['azienda_nome' => $azienda['nome']]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Azienda cambiata con successo',
            'azienda' => [
                'id' => $azienda['id'],
                'nome' => $azienda['nome']
            ]
        ]);
    } else {
        // Rimuovi azienda dalla sessione
        unset($_SESSION['azienda_id']);
        unset($_SESSION['azienda_nome']);
        
        // Log attività
        $logger = ActivityLogger::getInstance();
        $logger->log('sistema', 'switch_azienda', null, 
                    ['azione' => 'nessuna_azienda']);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Azienda deselezionata'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>