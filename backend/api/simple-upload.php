<?php
/**
 * API per upload file semplice
 * 
 * @package Nexio
 * @version 1.0.0
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../middleware/Auth.php';
require_once '../utils/ActivityLogger.php';

// Autenticazione
$auth = Auth::getInstance();
if (!$auth->checkSession()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autenticato']);
    exit;
}

$user = $auth->getUser();
$userId = $user['id'];

try {
    // Validazione parametri
    if (!isset($_FILES['file'])) {
        throw new Exception('Nessun file caricato');
    }
    
    if (!isset($_POST['folder_id'])) {
        throw new Exception('Cartella di destinazione non specificata');
    }
    
    if (!isset($_POST['azienda_id'])) {
        throw new Exception('Azienda non specificata');
    }
    
    $file = $_FILES['file'];
    $folderId = intval($_POST['folder_id']);
    $aziendaId = intval($_POST['azienda_id']);
    $titolo = isset($_POST['titolo']) ? trim($_POST['titolo']) : pathinfo($file['name'], PATHINFO_FILENAME);
    $descrizione = isset($_POST['descrizione']) ? trim($_POST['descrizione']) : '';
    
    // Verifica errori upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Errore durante il caricamento del file');
    }
    
    // Verifica cartella
    $stmt = db_query("SELECT * FROM cartelle WHERE id = ? AND azienda_id = ?", [$folderId, $aziendaId]);
    $folder = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$folder) {
        throw new Exception('Cartella non trovata o non accessibile');
    }
    
    // Validazione file
    $allowedTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'gif'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowedTypes)) {
        throw new Exception('Tipo di file non consentito');
    }
    
    // Limite dimensione (10MB)
    $maxSize = 10 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        throw new Exception('Il file supera la dimensione massima consentita (10MB)');
    }
    
    // Genera codice documento univoco
    $codice = 'DOC-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    
    // Percorso di destinazione
    $uploadDir = '../../uploads/documenti/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $fileName = $codice . '.' . $ext;
    $uploadPath = $uploadDir . $fileName;
    $relativePath = 'uploads/documenti/' . $fileName;
    
    // Sposta il file
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception('Errore durante il salvataggio del file');
    }
    
    try {
        db_begin_transaction();
        
        // Inserisci documento nel database
        $docId = db_insert('documenti', [
            'codice' => $codice,
            'titolo' => $titolo,
            'descrizione' => $descrizione,
            'file_path' => $relativePath,
            'formato' => $ext,
            'dimensione_file' => $file['size'],
            'stato' => 'pubblicato',
            'azienda_id' => $aziendaId,
            'cartella_id' => $folderId,
            'creato_da' => $userId,
            'tipo_documento' => 'file'
        ]);
        
        // Log attività
        ActivityLogger::getInstance()->log(
            'documento_caricato',
            'documenti',
            $docId,
            [
                'titolo' => $titolo,
                'file' => $file['name'],
                'dimensione' => $file['size'],
                'cartella' => $folder['nome']
            ]
        );
        
        db_commit();
        
        echo json_encode([
            'success' => true,
            'document_id' => $docId,
            'message' => 'File caricato con successo'
        ]);
        
    } catch (Exception $e) {
        db_rollback();
        // Rimuovi file se errore database
        if (file_exists($uploadPath)) {
            unlink($uploadPath);
        }
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}