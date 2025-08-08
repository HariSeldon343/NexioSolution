<?php
/**
 * Download Progress API
 * 
 * API per il tracking real-time del progresso dei download multipli
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
    $zipId = $_GET['zip_id'] ?? null;
    
    if (!$sessionId && !$zipId) {
        throw new Exception('Session ID o ZIP ID richiesto');
    }
    
    // Query progress
    if ($sessionId) {
        $stmt = db_query(
            "SELECT * FROM download_sessions WHERE id = ? AND azienda_id = ?",
            [$sessionId, $aziendaId]
        );
    } else {
        $stmt = db_query(
            "SELECT * FROM download_sessions WHERE zip_id = ? AND azienda_id = ?",
            [$zipId, $aziendaId]
        );
    }
    
    $session = $stmt->fetch();
    
    if (!$session) {
        throw new Exception('Sessione non trovata');
    }
    
    // Ottieni documenti inclusi nel download
    $documentIds = json_decode($session['document_ids'], true);
    $documentsStmt = db_query(
        "SELECT d.id, d.codice, d.titolo, d.file_originale, d.dimensione_file, d.tipo_file
         FROM documenti d 
         WHERE d.id IN (" . str_repeat('?,', count($documentIds) - 1) . "?) AND d.azienda_id = ?
         ORDER BY d.titolo ASC",
        array_merge($documentIds, [$aziendaId])
    );
    $documents = $documentsStmt->fetchAll();
    
    // Calcola statistiche
    $stats = [
        'total_documents' => count($documents),
        'files_processed' => $session['files_processed'] ?? 0,
        'progress_percent' => $session['progress'] ?? 0,
        'total_size' => $session['total_size'],
        'final_size' => $session['final_size'],
        'compression_ratio' => null,
        'stato' => $session['stato'],
        'started_at' => $session['created_at'],
        'completed_at' => $session['completed_at'],
        'estimated_time_remaining' => null
    ];
    
    // Calcola ratio di compressione se completato
    if ($session['stato'] === 'completed' && $session['final_size'] && $session['total_size']) {
        $stats['compression_ratio'] = round((1 - ($session['final_size'] / $session['total_size'])) * 100, 2);
    }
    
    // Calcola tempo rimanente stimato
    if ($session['stato'] === 'processing' && $stats['progress_percent'] > 0) {
        $elapsed = time() - strtotime($session['created_at']);
        $totalEstimated = ($elapsed / ($stats['progress_percent'] / 100));
        $stats['estimated_time_remaining'] = max(0, $totalEstimated - $elapsed);
    }
    
    // Verifica esistenza file ZIP se completato
    $zipAvailable = false;
    $downloadInfo = null;
    
    if ($session['stato'] === 'completed') {
        $zipPath = findZipFile($session['zip_id']);
        $zipAvailable = $zipPath && file_exists($zipPath);
        
        if ($zipAvailable) {
            // Ottieni token download se esiste
            $tokenStmt = db_query(
                "SELECT token, expires_at, used FROM download_tokens 
                 WHERE zip_id = ? AND expires_at > NOW() 
                 ORDER BY created_at DESC LIMIT 1",
                [$session['zip_id']]
            );
            $tokenData = $tokenStmt->fetch();
            
            if ($tokenData && !$tokenData['used']) {
                $downloadInfo = [
                    'available' => true,
                    'download_url' => "/backend/api/download-zip.php?token=" . $tokenData['token'],
                    'expires_at' => $tokenData['expires_at'],
                    'file_size' => filesize($zipPath),
                    'filename' => generateDownloadFilename($session['zip_id'], $aziendaId)
                ];
            } else {
                // Genera nuovo token se necessario
                $newToken = generateNewDownloadToken($session['zip_id'], $aziendaId);
                $downloadInfo = [
                    'available' => true,
                    'download_url' => "/backend/api/download-zip.php?token=" . $newToken,
                    'expires_at' => date('Y-m-d H:i:s', time() + 3600),
                    'file_size' => filesize($zipPath),
                    'filename' => generateDownloadFilename($session['zip_id'], $aziendaId)
                ];
            }
        }
    }
    
    // Analisi tipi file
    $fileTypes = [];
    foreach ($documents as $doc) {
        $type = $doc['tipo_file'];
        if (!isset($fileTypes[$type])) {
            $fileTypes[$type] = ['count' => 0, 'size' => 0];
        }
        $fileTypes[$type]['count']++;
        $fileTypes[$type]['size'] += $doc['dimensione_file'];
    }
    
    // Risposta completa
    $response = [
        'session_id' => $session['id'],
        'zip_id' => $session['zip_id'],
        'status' => $session['stato'],
        'progress' => $stats,
        'download' => $downloadInfo,
        'documents' => array_map(function($doc) {
            return [
                'id' => $doc['id'],
                'codice' => $doc['codice'],
                'titolo' => $doc['titolo'],
                'file_originale' => $doc['file_originale'],
                'size' => $doc['dimensione_file'],
                'type' => $doc['tipo_file'],
                'size_formatted' => formatBytes($doc['dimensione_file'])
            ];
        }, $documents),
        'file_types' => $fileTypes,
        'metadata' => json_decode($session['metadata'] ?? '{}', true)
    ];
    
    // Aggiungi informazioni real-time se la sessione è ancora attiva
    if ($session['stato'] === 'processing') {
        $response['live_updates'] = [
            'refresh_interval' => 3000, // 3 secondi per i download
            'websocket_available' => false
        ];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Download Progress Error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage(),
        'error_code' => 'PROGRESS_ERROR'
    ]);
}

/**
 * Trova il file ZIP basato sull'ID
 */
function findZipFile(string $zipId): ?string
{
    $tempDir = sys_get_temp_dir();
    $pattern = "{$tempDir}/nexio_zip_{$zipId}/download.zip";
    
    if (file_exists($pattern)) {
        return $pattern;
    }
    
    $altPatterns = [
        BASE_PATH . "/temp/downloads/nexio_zip_{$zipId}/download.zip",
        BASE_PATH . "/uploads/temp/nexio_zip_{$zipId}/download.zip"
    ];
    
    foreach ($altPatterns as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    
    return null;
}

/**
 * Genera nome file per download
 */
function generateDownloadFilename(string $zipId, int $aziendaId): string
{
    $timestamp = date('Y-m-d_H-i-s');
    
    $stmt = db_query("SELECT nome FROM aziende WHERE id = ?", [$aziendaId]);
    $aziendaNome = $stmt->fetchColumn();
    
    if ($aziendaNome) {
        $aziendaNome = preg_replace('/[^a-zA-Z0-9_-]/', '_', $aziendaNome);
        return "nexio_export_{$aziendaNome}_{$timestamp}.zip";
    }
    
    return "nexio_export_{$timestamp}.zip";
}

/**
 * Genera nuovo token download
 */
function generateNewDownloadToken(string $zipId, int $aziendaId): string
{
    $token = bin2hex(random_bytes(32));
    
    db_insert('download_tokens', [
        'token' => $token,
        'zip_id' => $zipId,
        'azienda_id' => $aziendaId,
        'created_at' => date('Y-m-d H:i:s'),
        'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        'used' => 0
    ]);
    
    return $token;
}

/**
 * Formatta bytes in formato leggibile
 */
function formatBytes(int $bytes): string
{
    if ($bytes === 0) return '0 B';
    
    $units = ['B', 'KB', 'MB', 'GB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, 2) . ' ' . $units[$i];
}
?>