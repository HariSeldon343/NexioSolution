<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../middleware/Auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$auth = Auth::getInstance();
$auth->requireAuth();
$user = $auth->getUser();

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $docId = $input['id'] ?? null;
    $titolo = $input['titolo'] ?? 'Documento senza titolo';
    $contenuto = $input['contenuto'] ?? '';
    $contenutoHtml = $input['contenuto_html'] ?? '';
    $templateId = $input['template_id'] ?? null;
    $frontespizio = $input['frontespizio'] ?? null;
    $now = date('Y-m-d H:i:s');
    
    if ($docId) {
        // Aggiorna documento esistente
        $stmt = $pdo->prepare("
            UPDATE documenti 
            SET titolo = ?, 
                contenuto = ?, 
                contenuto_html = ?,
                template_id = ?,
                frontespizio = ?,
                updated_at = ?
            WHERE id = ? AND user_id = ?
        ");
        
        $result = $stmt->execute([
            $titolo, 
            $contenuto, 
            $contenutoHtml,
            $templateId,
            $frontespizio,
            $now, 
            $docId, 
            $user['id']
        ]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Documento non trovato o accesso negato');
        }
        
        $response = [
            'success' => true,
            'docId' => $docId,
            'message' => 'Documento aggiornato con successo'
        ];
        
    } else {
        // Crea nuovo documento
        $stmt = $pdo->prepare("
            INSERT INTO documenti (
                user_id, 
                titolo, 
                contenuto, 
                contenuto_html,
                template_id,
                frontespizio,
                created_at, 
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $user['id'], 
            $titolo, 
            $contenuto, 
            $contenutoHtml,
            $templateId,
            $frontespizio,
            $now, 
            $now
        ]);
        
        $newDocId = $pdo->lastInsertId();
        
        $response = [
            'success' => true,
            'docId' => $newDocId,
            'message' => 'Nuovo documento creato con successo'
        ];
    }

    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Database error in save-document: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Errore del database: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error in save-document: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
} 