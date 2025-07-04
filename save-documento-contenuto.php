<?php
require_once 'backend/config/config.php';
require_once 'backend/utils/ActivityLogger.php';

header('Content-Type: application/json');

$auth = Auth::getInstance();
$auth->requireAuth();

$user = $auth->getUser();
$db = Database::getInstance();
$logger = ActivityLogger::getInstance();

try {
    // Validazione input
    $id = intval($_POST['id'] ?? 0);
    $contenuto = $_POST['contenuto'] ?? '';
    $action = $_POST['action'] ?? '';
    
    if (!$id) {
        throw new Exception("ID documento mancante");
    }
    
    if ($action !== 'save' && $action !== 'autosave') {
        throw new Exception("Azione non valida");
    }
    
    // Verifica che il documento esista e l'utente abbia i permessi
    $stmt = $db->query("SELECT * FROM documenti WHERE id = ?", [$id]);
    $documento = $stmt->fetch();
    
    if (!$documento) {
        throw new Exception("Documento non trovato");
    }
    
    // Verifica permessi
    if (!$auth->canAccess('documents', 'update')) {
        throw new Exception("Non hai i permessi per modificare questo documento");
    }
    
    // Se è autosave, salva solo il contenuto
    if ($action === 'autosave') {
        $db->query("UPDATE documenti SET contenuto = ? WHERE id = ?", [$contenuto, $id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Contenuto salvato automaticamente'
        ]);
        exit;
    }
    
    // Per salvataggio manuale, aggiorna anche altri campi se forniti
    $updateFields = ['contenuto = ?'];
    $updateParams = [$contenuto];
    
    // Aggiungi campi opzionali se forniti
    if (isset($_POST['titolo'])) {
        $updateFields[] = 'titolo = ?';
        $updateParams[] = $_POST['titolo'];
    }
    
    if (isset($_POST['stato'])) {
        $updateFields[] = 'stato = ?';
        $updateParams[] = $_POST['stato'];
    }
    
    // Aggiungi sempre aggiornamento timestamp e utente
    $updateFields[] = 'aggiornato_da = ?';
    $updateFields[] = 'aggiornato_il = NOW()';
    $updateParams[] = $user['id'];
    $updateParams[] = $id; // ID per WHERE clause
    
    // Esegui update
    $sql = "UPDATE documenti SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $db->query($sql, $updateParams);
    
    // Log attività
    if ($action === 'save') {
        $logger->log('documento_aggiornato', "Aggiornato documento #$id", ['documento_id' => $id]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Documento salvato con successo',
        'id' => $id
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 