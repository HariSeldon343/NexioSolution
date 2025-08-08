<?php
/**
 * Upload Progress API
 * 
 * API per il tracking real-time del progresso degli upload multipli
 * 
 * @package Nexio\API
 * @version 1.0.0
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../middleware/Auth.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');

// Autenticazione
$auth = Auth::getInstance();
if (!$auth->checkSession()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autenticato']);
    exit;
}

$user = $auth->getUser();
$currentAzienda = $auth->getCurrentAzienda();
$aziendaId = $currentAzienda['azienda_id'];

try {
    $sessionId = $_GET['session_id'] ?? null;
    $batchId = $_GET['batch_id'] ?? null;
    
    if (!$sessionId && !$batchId) {
        throw new Exception('Session ID o Batch ID richiesto');
    }
    
    // Query progress
    if ($sessionId) {
        $stmt = db_query(
            "SELECT * FROM upload_sessions WHERE id = ? AND azienda_id = ?",
            [$sessionId, $aziendaId]
        );
    } else {
        $stmt = db_query(
            "SELECT * FROM upload_sessions WHERE batch_id = ? AND azienda_id = ?",
            [$batchId, $aziendaId]
        );
    }
    
    $session = $stmt->fetch();
    
    if (!$session) {
        throw new Exception('Sessione non trovata');
    }
    
    // Ottieni dettagli file processati
    $filesStmt = db_query(
        "SELECT d.id, d.codice, d.titolo, d.file_originale, d.dimensione_file, d.stato
         FROM documenti d 
         WHERE d.batch_id = ? AND d.azienda_id = ?
         ORDER BY d.data_creazione ASC",
        [$session['batch_id'], $aziendaId]
    );
    $processedFiles = $filesStmt->fetchAll();
    
    // Calcola statistiche aggiornate
    $stats = [
        'total_files' => $session['total_files'],
        'files_processed' => $session['files_processed'] ?? 0,
        'files_success' => $session['files_success'] ?? 0,
        'files_errors' => $session['files_errors'] ?? 0,
        'progress_percent' => $session['progress'] ?? 0,
        'total_size' => $session['total_size'],
        'processed_size' => array_sum(array_column($processedFiles, 'dimensione_file')),
        'stato' => $session['stato'],
        'started_at' => $session['created_at'],
        'completed_at' => $session['completed_at'],
        'estimated_time_remaining' => null
    ];
    
    // Calcola tempo rimanente stimato
    if ($session['stato'] === 'processing' && $stats['progress_percent'] > 0) {
        $elapsed = time() - strtotime($session['created_at']);
        $totalEstimated = ($elapsed / ($stats['progress_percent'] / 100));
        $stats['estimated_time_remaining'] = max(0, $totalEstimated - $elapsed);
    }
    
    // Dettagli errori se presenti
    $errors = [];
    if ($session['files_errors'] > 0) {
        $errorsStmt = db_query(
            "SELECT file_name, error_message, created_at 
             FROM upload_errors 
             WHERE batch_id = ? 
             ORDER BY created_at DESC",
            [$session['batch_id']]
        );
        $errors = $errorsStmt->fetchAll();
    }
    
    // Risposta completa
    $response = [
        'session_id' => $session['id'],
        'batch_id' => $session['batch_id'],
        'status' => $session['stato'],
        'progress' => $stats,
        'files' => array_map(function($file) {
            return [
                'id' => $file['id'],
                'codice' => $file['codice'],
                'titolo' => $file['titolo'],
                'file_originale' => $file['file_originale'],
                'size' => $file['dimensione_file'],
                'status' => $file['stato']
            ];
        }, $processedFiles),
        'errors' => $errors,
        'metadata' => json_decode($session['metadata'] ?? '{}', true)
    ];
    
    // Aggiungi informazioni real-time se la sessione è ancora attiva
    if ($session['stato'] === 'processing') {
        $response['live_updates'] = [
            'refresh_interval' => 2000, // 2 secondi
            'websocket_available' => false // Per future implementazioni
        ];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Upload Progress Error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage(),
        'error_code' => 'PROGRESS_ERROR'
    ]);
}
?>