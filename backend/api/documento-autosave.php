<?php
/**
 * API per autosave documenti
 * Salva automaticamente il contenuto del documento mentre l'utente sta modificando
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
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
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get JSON data from request
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['content'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input data']);
    exit;
}

try {
    // Establish database connection
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $docId = $input['docId'] ?? null;
    $content = json_encode($input['content']); // Store JSON Delta
    $html = $input['html'] ?? ''; // Store HTML version too
    $userId = $_SESSION['user_id'] ?? 1; // Default user if no session
    $now = date('Y-m-d H:i:s');
    
    if ($docId) {
        // Update existing document
        $stmt = $pdo->prepare("
            UPDATE documenti 
            SET contenuto = ?, 
                contenuto_html = ?,
                updated_at = ?
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$content, $html, $now, $docId, $userId]);
        
        if ($stmt->rowCount() === 0) {
            // Check if document exists but belongs to another user
            $checkStmt = $pdo->prepare("SELECT id FROM documenti WHERE id = ?");
            $checkStmt->execute([$docId]);
            
            if ($checkStmt->rowCount() > 0) {
                throw new Exception('Access denied to this document');
            } else {
                throw new Exception('Document not found');
            }
        }
        
        $response = [
            'status' => 'updated',
            'docId' => $docId,
            'timestamp' => $now
        ];
    } else {
        // Create new document
        $stmt = $pdo->prepare("
            INSERT INTO documenti (
                user_id, 
                titolo, 
                contenuto, 
                contenuto_html,
                created_at, 
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, 'Nuovo Documento', $content, $html, $now, $now]);
        
        $newDocId = $pdo->lastInsertId();
        $response = [
            'status' => 'created',
            'docId' => $newDocId,
            'timestamp' => $now
        ];
    }

    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
} catch (Exception $e) {
    error_log("Autosave error: " . $e->getMessage());
    http_response_code(403);
    echo json_encode(['error' => $e->getMessage()]);
}
?> 