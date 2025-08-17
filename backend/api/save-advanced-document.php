<?php
/**
 * API per salvare documenti dall'editor avanzato
 * Versione integrata con il database Nexio
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../middleware/Auth.php';

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

    $docId = $input['docId'] ?? null;
    $title = $input['title'] ?? 'Documento senza titolo';
    $content = $input['content']; // HTML content
    $plainText = $input['plainText'] ?? strip_tags($content);
    $stats = $input['stats'] ?? [];
    $settings = $input['settings'] ?? [];
    
    // Extract header/footer settings
    $headerText = $input['header_text'] ?? '';
    $footerText = $input['footer_text'] ?? '';
    $pageNumbering = $input['page_numbering'] ?? false;
    $pageNumberFormat = $input['page_number_format'] ?? 'page_x_of_y';
    
    $userId = $user['id'];
    $aziendaId = $currentAzienda ? $currentAzienda['azienda_id'] : null;
    $now = date('Y-m-d H:i:s');
    
    // Prepare metadata JSON with header/footer settings
    $metadata = json_encode([
        'stats' => $stats,
        'settings' => $settings,
        'header_text' => $headerText,
        'footer_text' => $footerText,
        'page_numbering' => $pageNumbering,
        'page_number_format' => $pageNumberFormat,
        'editor_version' => 'advanced_v1.0',
        'last_modified' => $now
    ], JSON_UNESCAPED_UNICODE);
    
    if ($docId) {
        // Update existing document
        $stmt = db_query(
            "UPDATE documenti 
             SET titolo = ?, 
                 contenuto_html = ?, 
                 contenuto = ?, 
                 metadata = ?,
                 data_modifica = NOW(),
                 modificato_da = ?
             WHERE id = ? 
             AND (azienda_id = ? OR azienda_id IS NULL OR ?)",
            [
                $title,
                $content,
                $plainText,
                $metadata,
                $userId,
                $docId,
                $aziendaId,
                $auth->isSuperAdmin() ? 1 : 0
            ]
        );
        
        if ($stmt->rowCount() === 0) {
            // Check if document exists but user doesn't have permission
            $checkStmt = db_query("SELECT id, azienda_id FROM documenti WHERE id = ?", [$docId]);
            $existing = $checkStmt->fetch();
            
            if ($existing) {
                throw new Exception('Access denied to this document');
            } else {
                throw new Exception('Document not found');
            }
        }
        
        // Create version if DocumentVersion class exists
        if (class_exists('DocumentVersion')) {
            try {
                $versionModel = new DocumentVersion();
                $versionModel->addVersion(
                    $docId,
                    $content,
                    null,
                    $userId,
                    $user['nome'] . ' ' . $user['cognome'],
                    false,
                    'Salvataggio automatico dall\'editor'
                );
            } catch (Exception $e) {
                // Log but don't fail if versioning fails
                error_log("Warning: Could not create version: " . $e->getMessage());
            }
        }
        
        // Log activity
        if (class_exists('ActivityLogger')) {
            try {
                $logger = ActivityLogger::getInstance();
                $logger->log('documento', 'modificato', $docId, "Documento '$title' aggiornato");
            } catch (Exception $e) {
                error_log("Warning: Could not log activity: " . $e->getMessage());
            }
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
        // Generate unique code for new document
        $codice = 'DOC-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        
        // Create new document
        $stmt = db_query(
            "INSERT INTO documenti (
                codice, titolo, contenuto_html, contenuto, 
                tipo_documento, stato, metadata,
                azienda_id, cartella_id, creato_da, 
                data_creazione, data_modifica
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [
                $codice,
                $title,
                $content,
                $plainText,
                'documento',
                'bozza',
                $metadata,
                $aziendaId,
                null, // cartella_id - can be set later
                $userId
            ]
        );
        
        $newDocId = db_connection()->lastInsertId();
        
        // Create initial version if DocumentVersion class exists
        if (class_exists('DocumentVersion')) {
            try {
                $versionModel = new DocumentVersion();
                $versionModel->addVersion(
                    $newDocId,
                    $content,
                    null,
                    $userId,
                    $user['nome'] . ' ' . $user['cognome'],
                    true, // Major version for new document
                    'Versione iniziale'
                );
            } catch (Exception $e) {
                // Log but don't fail if versioning fails
                error_log("Warning: Could not create initial version: " . $e->getMessage());
            }
        }
        
        // Log activity
        if (class_exists('ActivityLogger')) {
            try {
                $logger = ActivityLogger::getInstance();
                $logger->log('documento', 'creato', $newDocId, "Nuovo documento '$title' creato");
            } catch (Exception $e) {
                error_log("Warning: Could not log activity: " . $e->getMessage());
            }
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