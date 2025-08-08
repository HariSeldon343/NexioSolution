<?php
/**
 * API per ottenere un documento specifico
 * Utilizzata dall'editor per caricare documenti esistenti
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Verifica autenticazione
    $auth = Auth::getInstance();
    if (!$auth->isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Non autenticato']);
        exit;
    }

    $user = $auth->getUser();
    $currentAzienda = $auth->getCurrentAzienda();
    $documento_id = $_GET['id'] ?? null;

    if (!$documento_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID documento richiesto']);
        exit;
    }

    // Ottieni documento - usa i nomi delle colonne corretti
    $stmt = db_query("SELECT * FROM documenti WHERE id = ?", [$documento_id]);
    
    if (!$stmt) {
        throw new Exception('Errore query database');
    }

    $documento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$documento) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Documento non trovato']);
        exit;
    }

    // Verifica permessi di accesso
    $canAccess = false;
    
    // Creatore del documento
    if ($documento['creato_da'] == $user['id']) {
        $canAccess = true;
    }
    // Super admin o admin
    elseif ($auth->isSuperAdmin() || $user['ruolo'] === 'admin') {
        $canAccess = true;
    }
    // Stesso tenant
    elseif ($currentAzienda) {
        $aziendaId = $currentAzienda['id'] ?? $currentAzienda['azienda_id'] ?? null;
        if ($documento['azienda_id'] == $aziendaId) {
            $canAccess = true;
        }
    }
    
    if (!$canAccess) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Non hai i permessi per accedere a questo documento']);
        exit;
    }

    // Restituisci documento con i campi corretti
    echo json_encode([
        'success' => true,
        'documento' => [
            'id' => $documento['id'],
            'titolo' => $documento['titolo'] ?? 'Documento Senza Titolo',
            'codice' => $documento['codice'] ?? '',
            'contenuto' => $documento['contenuto'] ?? '<p>Documento vuoto...</p>',
            'tipo' => $documento['tipo'] ?? 'documento',
            'stato' => $documento['stato'] ?? 'bozza',
            'versione' => $documento['versione'] ?? 1,
            'creato_da' => $documento['creato_da'],
            'azienda_id' => $documento['azienda_id'],
            'creato_il' => $documento['creato_il'] ?? $documento['created_at'] ?? null,
            'ultimo_aggiornamento' => $documento['ultimo_aggiornamento'] ?? $documento['updated_at'] ?? $documento['creato_il'] ?? null
        ]
    ]);

} catch (Exception $e) {
    error_log("Errore get-document.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore interno del server: ' . $e->getMessage()]);
}
?>