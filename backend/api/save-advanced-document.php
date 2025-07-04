<?php
/**
 * API per salvare documenti dall'editor avanzato
 * Versione integrata con il database Nexio
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    json_response(['error' => 'Method not allowed']);
    exit;
}

try {
    $auth = Auth::getInstance();
    $auth->requireAuth();
    
    $user = $auth->getUser();
    $currentAzienda = $auth->getCurrentAzienda();
    
    // Get JSON data from request
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['content'])) {
        http_response_code(400);
        json_response(['error' => 'Invalid input data']);
        exit;
    }

    // Establish database connection
    $db = Database::getInstance();

    $docId = $input['docId'] ?? null;
    $title = $input['title'] ?? 'Documento senza titolo';
    $content = $input['content']; // HTML content
    $plainText = $input['plainText'] ?? '';
    $stats = $input['stats'] ?? [];
    $settings = $input['settings'] ?? [];
    $userId = $user['id'];
    $aziendaId = $currentAzienda ? $currentAzienda['azienda_id'] : null;
    $now = date('Y-m-d H:i:s');
    
    // Prepare metadata JSON
    $metadata = json_encode([
        'stats' => $stats,
        'settings' => $settings,
        'editor_version' => 'advanced_v1.0',
        'last_modified' => $now
    ], JSON_UNESCAPED_UNICODE);
    
    if ($docId) {
        // Update existing document
        $updateData = [
            'titolo' => $title,
            'contenuto_html' => $content,
            'contenuto' => $plainText,
            'metadata' => $metadata,
            'updated_at' => $now
        ];
        
        $rowsAffected = $db->update('documenti', $updateData, 'id = ? AND user_id = ?', [$docId, $userId]);
        
        if ($rowsAffected === 0) {
            // Check if document exists but belongs to another user
            $stmt = $db->query("SELECT id, user_id FROM documenti WHERE id = ?", [$docId]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                throw new Exception('Access denied to this document');
            } else {
                throw new Exception('Document not found');
            }
        }
        
        // Log activity using ActivityLogger if available
        if (class_exists('ActivityLogger')) {
            $logger = ActivityLogger::getInstance();
            $logger->log('documento', 'modificato', $docId, "Documento '$title' aggiornato");
        }
        
        $response = [
            'success' => true,
            'status' => 'updated',
            'docId' => $docId,
            'title' => $title,
            'timestamp' => $now,
            'stats' => $stats
        ];
    } else {
        // Create new document
        $insertData = [
            'user_id' => $userId,
            'azienda_id' => $aziendaId,
            'titolo' => $title,
            'contenuto_html' => $content,
            'contenuto' => $plainText,
            'tipo' => 'documento',
            'stato' => 'bozza',
            'metadata' => $metadata,
            'created_at' => $now,
            'updated_at' => $now
        ];
        
        $newDocId = $db->insert('documenti', $insertData);
        
        // Log activity using ActivityLogger if available
        if (class_exists('ActivityLogger')) {
            $logger = ActivityLogger::getInstance();
            $logger->log('documento', 'creato', $newDocId, "Nuovo documento '$title' creato");
        }
        
        $response = [
            'success' => true,
            'status' => 'created',
            'docId' => $newDocId,
            'title' => $title,
            'timestamp' => $now,
            'stats' => $stats
        ];
    }

    // Add auto-save timestamp to response
    $response['autoSaveTime'] = date('H:i:s');
    
    json_response($response);

} catch (Exception $e) {
    error_log("Error in save-advanced-document: " . $e->getMessage());
    http_response_code(500);
    json_response([
        'success' => false,
        'error' => 'Errore durante il salvataggio del documento',
        'details' => $e->getMessage()
    ]);
}
?>