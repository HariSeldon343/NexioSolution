<?php
/**
 * API per la gestione delle versioni dei documenti
 * Fornisce cronologia versioni e ripristino
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/config.php';
require_once '../middleware/Auth.php';

try {
    $auth = Auth::getInstance();
    
    if (!$auth->isAuthenticated()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Non autenticato'
        ]);
        exit;
    }
    
    $action = $_GET['action'] ?? 'list';
    $user = $auth->getUser();
    $currentAzienda = $auth->getCurrentAzienda();
    $aziendaId = $currentAzienda['id'] ?? null;
    $isSuperAdmin = $auth->isSuperAdmin();
    
    switch ($action) {
        case 'list':
            $documentId = $_GET['document_id'] ?? null;
            
            if (!$documentId) {
                throw new Exception('ID documento mancante');
            }
            
            // Verifica permessi sul documento
            $stmt = db_query(
                "SELECT * FROM documenti 
                 WHERE id = ? AND (azienda_id = ? OR azienda_id IS NULL OR ?)",
                [$documentId, $aziendaId, $isSuperAdmin ? 1 : 0]
            );
            
            if ($stmt->rowCount() === 0) {
                throw new Exception('Documento non trovato o accesso negato');
            }
            
            // Controlla se esiste la tabella document_versions
            $stmt = db_query("SHOW TABLES LIKE 'document_versions'");
            if ($stmt->rowCount() === 0) {
                // Se la tabella non esiste, ritorna array vuoto
                echo json_encode([
                    'success' => true,
                    'versions' => []
                ]);
                exit;
            }
            
            // Recupera cronologia versioni
            $stmt = db_query(
                "SELECT dv.*, u.nome, u.cognome,
                        CONCAT(u.nome, ' ', u.cognome) as created_by_name
                 FROM document_versions dv
                 LEFT JOIN utenti u ON dv.created_by = u.id
                 WHERE dv.document_id = ?
                 ORDER BY dv.version_number DESC
                 LIMIT 20",
                [$documentId]
            );
            
            $versions = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'versions' => $versions
            ]);
            break;
            
        case 'get':
            $versionId = $_GET['version_id'] ?? null;
            
            if (!$versionId) {
                throw new Exception('ID versione mancante');
            }
            
            // Controlla se esiste la tabella document_versions
            $stmt = db_query("SHOW TABLES LIKE 'document_versions'");
            if ($stmt->rowCount() === 0) {
                throw new Exception('Sistema versioni non disponibile');
            }
            
            // Recupera contenuto versione specifica
            $stmt = db_query(
                "SELECT dv.*, d.azienda_id
                 FROM document_versions dv
                 JOIN documenti d ON dv.document_id = d.id
                 WHERE dv.id = ? 
                 AND (d.azienda_id = ? OR d.azienda_id IS NULL OR ?)",
                [$versionId, $aziendaId, $isSuperAdmin ? 1 : 0]
            );
            
            $version = $stmt->fetch();
            
            if (!$version) {
                throw new Exception('Versione non trovata o accesso negato');
            }
            
            echo json_encode([
                'success' => true,
                'content' => $version['contenuto_html'],
                'version_number' => $version['version_number'],
                'created_at' => $version['created_at']
            ]);
            break;
            
        default:
            throw new Exception('Azione non supportata');
    }
    
} catch (Exception $e) {
    error_log("Document Versions API Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
