<?php
require_once 'backend/config/config.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$user = $auth->getUser();
$db = Database::getInstance();

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $_POST['action'] !== 'save') {
        throw new Exception('Richiesta non valida');
    }
    
    $id = intval($_POST['id'] ?? 0);
    $tipo = $_POST['tipo'] ?? 'documento';
    $contenuto = $_POST['contenuto'] ?? '';
    
    if ($id) {
        // Aggiorna documento esistente
        $stmt = $db->prepare("
            UPDATE documenti 
            SET contenuto = ?, 
                aggiornato_da = ?, 
                aggiornato_il = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([$contenuto, $user['id'], $id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Documento aggiornato con successo',
            'id' => $id
        ]);
        
    } else {
        // Crea nuovo documento
        $titolo = $_POST['titolo'] ?? 'Nuovo ' . ucfirst($tipo);
        $codice = 'DOC-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
        
        // Determina il modulo_id in base al tipo
        $modulo_id = 1; // Default
        $tipo_mapping = [
            'documento' => 1,
            'foglio' => 2,
            'modulo' => 3
        ];
        $modulo_id = $tipo_mapping[$tipo] ?? 1;
        
        // Ottieni l'azienda corrente
        $currentAzienda = $auth->getCurrentAzienda();
        $azienda_id = $currentAzienda ? $currentAzienda['azienda_id'] : 1;
        
        $stmt = $db->prepare("
            INSERT INTO documenti (
                titolo, codice, contenuto, stato,
                modulo_id, azienda_id, creato_da, 
                data_creazione, versione_corrente
            ) VALUES (?, ?, ?, 'bozza', ?, ?, ?, NOW(), 1)
        ");
        
        $stmt->execute([
            $titolo,
            $codice,
            $contenuto,
            $modulo_id,
            $azienda_id,
            $user['id']
        ]);
        
        $newId = $db->lastInsertId();
        
        // Salva anche il tipo nella tabella moduli_template se necessario
        $stmt = $db->prepare("
            INSERT INTO moduli_template (modulo_id, tipo_documento)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE tipo_documento = ?
        ");
        $stmt->execute([$modulo_id, $tipo, $tipo]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Documento creato con successo',
            'id' => $newId
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 