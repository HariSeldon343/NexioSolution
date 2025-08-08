<?php
/**
 * Download ZIP API
 * 
 * API per il download effettivo dei file ZIP generati
 * 
 * @package Nexio\API
 * @version 1.0.0
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../middleware/Auth.php';
require_once __DIR__ . '/../utils/ActivityLogger.php';

use Nexio\Utils\ActivityLogger;

// Autenticazione
$auth = Auth::getInstance();
if (!$auth->checkSession()) {
    header('HTTP/1.1 401 Unauthorized');
    echo 'Non autenticato';
    exit;
}

$user = $auth->getUser();
$currentAzienda = $auth->getCurrentAzienda();
$aziendaId = $currentAzienda['azienda_id'];
$logger = ActivityLogger::getInstance();

try {
    // Verifica token download
    $token = $_GET['token'] ?? null;
    if (!$token) {
        throw new Exception('Token download mancante');
    }
    
    // Valida token
    $stmt = db_query(
        "SELECT dt.*, ds.zip_id, ds.azienda_id, ds.final_size, ds.files_processed
         FROM download_tokens dt
         JOIN download_sessions ds ON dt.zip_id = ds.zip_id
         WHERE dt.token = ? AND dt.expires_at > NOW() AND dt.used = 0",
        [$token]
    );
    
    $tokenData = $stmt->fetch();
    if (!$tokenData) {
        throw new Exception('Token non valido o scaduto');
    }
    
    // Verifica permessi azienda
    if ($tokenData['azienda_id'] != $aziendaId) {
        throw new Exception('Accesso negato al file');
    }
    
    // Trova file ZIP
    $zipPath = findZipFile($tokenData['zip_id']);
    if (!$zipPath || !file_exists($zipPath)) {
        throw new Exception('File ZIP non trovato o non più disponibile');
    }
    
    // Marca token come usato
    db_update('download_tokens', [
        'used' => 1,
        'downloaded_at' => date('Y-m-d H:i:s'),
        'downloaded_by' => $user['id']
    ], 'token = ?', [$token]);
    
    // Log download
    $logger->log('zip_download_started', 'download_sessions', null, [
        'zip_id' => $tokenData['zip_id'],
        'azienda_id' => $aziendaId,
        'user_id' => $user['id'],
        'file_size' => filesize($zipPath),
        'files_count' => $tokenData['files_processed']
    ]);
    
    // Prepara download
    $filename = generateDownloadFilename($tokenData['zip_id'], $aziendaId);
    $fileSize = filesize($zipPath);
    
    // Headers per download
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Support per resume download
    $range = $_SERVER['HTTP_RANGE'] ?? null;
    if ($range) {
        handleRangeDownload($zipPath, $fileSize, $range);
    } else {
        handleStandardDownload($zipPath, $fileSize);
    }
    
    // Log download completato
    $logger->log('zip_download_completed', 'download_sessions', null, [
        'zip_id' => $tokenData['zip_id'],
        'azienda_id' => $aziendaId,
        'user_id' => $user['id'],
        'bytes_transferred' => $fileSize
    ]);
    
} catch (Exception $e) {
    error_log("Download ZIP Error: " . $e->getMessage());
    
    $logger->logError('Errore download ZIP', [
        'error' => $e->getMessage(),
        'token' => $token ?? 'missing',
        'azienda_id' => $aziendaId,
        'user_id' => $user['id']
    ]);
    
    header('HTTP/1.1 400 Bad Request');
    echo 'Errore: ' . $e->getMessage();
}

/**
 * Trova il file ZIP basato sull'ID
 */
function findZipFile(string $zipId): ?string
{
    // Cerca in directory temporanea
    $tempDir = sys_get_temp_dir();
    $pattern = "{$tempDir}/nexio_zip_{$zipId}/download.zip";
    
    if (file_exists($pattern)) {
        return $pattern;
    }
    
    // Cerca in directory alternative
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
    
    // Ottieni nome azienda per filename più descrittivo
    $stmt = db_query("SELECT nome FROM aziende WHERE id = ?", [$aziendaId]);
    $aziendaNome = $stmt->fetchColumn();
    
    if ($aziendaNome) {
        $aziendaNome = preg_replace('/[^a-zA-Z0-9_-]/', '_', $aziendaNome);
        return "nexio_export_{$aziendaNome}_{$timestamp}.zip";
    }
    
    return "nexio_export_{$timestamp}.zip";
}

/**
 * Gestisce download standard
 */
function handleStandardDownload(string $filePath, int $fileSize): void
{
    $chunkSize = 8192; // 8KB chunks
    $handle = fopen($filePath, 'rb');
    
    if (!$handle) {
        throw new Exception('Impossibile aprire file per il download');
    }
    
    // Output del file a chunks per evitare problemi di memoria
    while (!feof($handle)) {
        $chunk = fread($handle, $chunkSize);
        echo $chunk;
        
        // Flush output buffer
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
        
        // Verifica se client ha disconnesso
        if (connection_aborted()) {
            break;
        }
    }
    
    fclose($handle);
}

/**
 * Gestisce download con range per resume capability
 */
function handleRangeDownload(string $filePath, int $fileSize, string $range): void
{
    // Parse range header (es: bytes=0-1023)
    if (!preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
        header('HTTP/1.1 416 Range Not Satisfiable');
        exit;
    }
    
    $start = intval($matches[1]);
    $end = !empty($matches[2]) ? intval($matches[2]) : $fileSize - 1;
    
    // Valida range
    if ($start >= $fileSize || $end >= $fileSize || $start > $end) {
        header('HTTP/1.1 416 Range Not Satisfiable');
        exit;
    }
    
    $contentLength = $end - $start + 1;
    
    // Headers per partial content
    header('HTTP/1.1 206 Partial Content');
    header("Content-Range: bytes {$start}-{$end}/{$fileSize}");
    header("Content-Length: {$contentLength}");
    
    // Output del range richiesto
    $handle = fopen($filePath, 'rb');
    if (!$handle) {
        throw new Exception('Impossibile aprire file per il download');
    }
    
    fseek($handle, $start);
    
    $chunkSize = 8192;
    $bytesRemaining = $contentLength;
    
    while ($bytesRemaining > 0 && !feof($handle)) {
        $bytesToRead = min($chunkSize, $bytesRemaining);
        $chunk = fread($handle, $bytesToRead);
        
        echo $chunk;
        $bytesRemaining -= strlen($chunk);
        
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
        
        if (connection_aborted()) {
            break;
        }
    }
    
    fclose($handle);
}
?>