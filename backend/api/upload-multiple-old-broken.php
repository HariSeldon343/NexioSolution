<?php
/**
 * Upload Multiple API
 * 
 * API per il caricamento multiplo di documenti con validazione ISO e progress tracking
 * 
 * @package Nexio\API
 * @version 1.0.0
 */

// Error handler per garantire sempre output JSON
function handleFatalError() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
        }
        echo json_encode([
            'success' => false, 
            'error' => 'Errore interno del server',
            'error_code' => 'FATAL_ERROR',
            'debug' => ['message' => $error['message'], 'file' => basename($error['file']), 'line' => $error['line']]
        ]);
    }
}
register_shutdown_function('handleFatalError');

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../middleware/Auth.php';
    require_once __DIR__ . '/../utils/MultiFileManager.php';
    require_once __DIR__ . '/../utils/ActivityLogger.php';
    require_once __DIR__ . '/../utils/ModulesHelper.php';
    require_once __DIR__ . '/../utils/UserRoleHelper.php';
    require_once __DIR__ . '/../utils/CSRFTokenManager.php';
    require_once __DIR__ . '/../utils/Mailer.php'; // Aggiunto per notifyUploadCompletion

    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore caricamento dipendenze: ' . $e->getMessage()]);
    exit;
}

// Non usare 'use' se le classi non sono in namespace
// MultiFileManager e ActivityLogger sono già inclusi

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Autenticazione
$auth = Auth::getInstance();
if (!$auth->checkSession()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non autenticato']);
    exit;
}

// Verifica modulo gestione documentale (temporaneamente disabilitato per debug)
// ModulesHelper::requireModule('gestione_documentale');

$user = $auth->getUser();

// Usa la nuova logica semplificata per determinare l'azienda
try {
    // Supporta sia 'azienda_id' che 'azienda_override' per compatibilità
    $requestedCompanyId = null;
    if (isset($_POST['azienda_id'])) {
        $requestedCompanyId = (int)$_POST['azienda_id'];
    } elseif (isset($_POST['azienda_override'])) {
        $requestedCompanyId = (int)$_POST['azienda_override'];
    }
    
    $aziendaId = UserRoleHelper::getUploadContext($user, $requestedCompanyId);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

// Istanzia le classi correttamente
try {
    // MultiFileManager ha namespace Nexio\Utils
    $multiFileManager = \Nexio\Utils\MultiFileManager::getInstance();
    
    // ActivityLogger NON ha namespace - usa direttamente
    $logger = ActivityLogger::getInstance(); 
    
    error_log('DEBUG: Classi istanziate correttamente - MultiFileManager e ActivityLogger');
    
} catch (Exception $e) {
    error_log('DEBUG: Errore istanziazione classi: ' . $e->getMessage());
    error_log('DEBUG: Stack trace: ' . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Errore inizializzazione sistema: ' . $e->getMessage(),
        'debug_info' => [
            'error_type' => get_class($e),
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ]);
    exit;
}

try {
    // Controllo metodo
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Metodo non supportato');
    }
    
    // Verifica CSRF token usando il manager
    $csrfManager = CSRFTokenManager::getInstance();
    $csrfManager->verifyRequest();
    
    // Controllo presenza file - supporta sia 'files' che 'files[]'
    $fileData = null;
    if (!empty($_FILES['files'])) {
        $fileData = $_FILES['files'];
    } elseif (!empty($_FILES['files[]'])) {
        $fileData = $_FILES['files[]'];
    }
    
    if (empty($fileData)) {
        throw new Exception('Nessun file caricato');
    }
    
    // Normalizza array file per upload multiplo
    $files = [];
    
    if (is_array($fileData['name'])) {
        // Upload multiplo
        for ($i = 0; $i < count($fileData['name']); $i++) {
            if ($fileData['error'][$i] === UPLOAD_ERR_OK) {
                $files[] = [
                    'name' => $fileData['name'][$i],
                    'type' => $fileData['type'][$i],
                    'tmp_name' => $fileData['tmp_name'][$i],
                    'error' => $fileData['error'][$i],
                    'size' => $fileData['size'][$i]
                ];
            }
        }
    } else {
        // Upload singolo
        $files[] = $fileData;
    }
    
    if (empty($files)) {
        throw new Exception('Nessun file valido trovato');
    }
    
    // Parsing metadata con validazione
    $metadata = [
        'cartella_id' => !empty($_POST['cartella_id']) && is_numeric($_POST['cartella_id']) ? (int)$_POST['cartella_id'] : null,
        'tipo_documento_default' => isset($_POST['tipo_documento_default']) ? trim($_POST['tipo_documento_default']) : 'documento_generico',
        'classificazione_id_default' => !empty($_POST['classificazione_id_default']) && is_numeric($_POST['classificazione_id_default']) ? (int)$_POST['classificazione_id_default'] : null,
        'tags_default' => isset($_POST['tags_default']) ? trim($_POST['tags_default']) : '',
        'auto_classify' => (($_POST['auto_classify'] ?? 'true') === 'true'),
        'extract_metadata' => (($_POST['extract_metadata'] ?? 'true') === 'true'),
        'notify_completion' => (($_POST['notify_completion'] ?? 'false') === 'true'),
        'files' => []
    ];
    
    // Metadata specifici per ogni file (se forniti)
    foreach ($files as $index => $file) {
        $fileMetadata = [
            'titolo' => isset($_POST["file_{$index}_titolo"]) ? trim($_POST["file_{$index}_titolo"]) : pathinfo($file['name'], PATHINFO_FILENAME),
            'descrizione' => isset($_POST["file_{$index}_descrizione"]) ? trim($_POST["file_{$index}_descrizione"]) : '',
            'tipo_documento' => isset($_POST["file_{$index}_tipo_documento"]) ? trim($_POST["file_{$index}_tipo_documento"]) : $metadata['tipo_documento_default'],
            'classificazione_id' => !empty($_POST["file_{$index}_classificazione_id"]) && is_numeric($_POST["file_{$index}_classificazione_id"]) ? 
                (int)$_POST["file_{$index}_classificazione_id"] : $metadata['classificazione_id_default'],
            'tags' => isset($_POST["file_{$index}_tags"]) ? trim($_POST["file_{$index}_tags"]) : $metadata['tags_default'],
            'cartella_id' => !empty($_POST["file_{$index}_cartella_id"]) && is_numeric($_POST["file_{$index}_cartella_id"]) ? 
                (int)$_POST["file_{$index}_cartella_id"] : $metadata['cartella_id']
        ];
        
        // Converti tags da stringa a array
        if (!empty($fileMetadata['tags'])) {
            $fileMetadata['tags'] = array_filter(array_map('trim', explode(',', $fileMetadata['tags'])));
        } else {
            $fileMetadata['tags'] = [];
        }
        
        $metadata['files'][] = $fileMetadata;
    }
    
    // Log avvio upload multiplo
    $logger->log('upload_multiplo_avviato', 'upload_sessions', null, [
        'azienda_id' => $aziendaId,
        'user_id' => $user['id'],
        'files_count' => count($files),
        'total_size' => array_sum(array_column($files, 'size')),
        'cartella_id' => $metadata['cartella_id'],
        'is_super_admin_upload' => ($aziendaId === 0 && $auth->hasRole('super_admin'))
    ]);
    
    // Esegui upload multiplo
    $result = $multiFileManager->handleMultipleUpload($files, $metadata, $aziendaId);
    
    // Prepara risposta
    $response = [
        'success' => true,
        'message' => 'Upload completato',
        'data' => [
            'batch_id' => $result['batch_id'],
            'session_id' => $result['summary']['session_id'],
            'summary' => $result['summary'],
            'files' => $result['files']
        ]
    ];
    
    // Aggiungi dettagli progress se richiesto
    if ($_POST['include_progress'] ?? false) {
        $response['data']['progress_url'] = "/backend/api/upload-progress.php?session_id=" . $result['summary']['session_id'];
    }
    
    // Notifica completamento se richiesta
    if ($metadata['notify_completion'] && $result['summary']['successful'] > 0) {
        notifyUploadCompletion($user, $result['summary'], $aziendaId);
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Upload Multiple Error: " . $e->getMessage());
    
    if (isset($logger)) {
        $logger->logError('Errore upload multiplo', [
            'error' => $e->getMessage(),
            'azienda_id' => $aziendaId ?? null,
            'user_id' => isset($user) ? $user['id'] : null,
            'files_count' => count($files ?? []),
            'is_super_admin_upload' => (($aziendaId === 0) && $auth->hasRole('super_admin'))
        ]);
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'error_code' => 'UPLOAD_FAILED'
    ]);
}

/**
 * Notifica completamento upload via email
 */
function notifyUploadCompletion(array $user, array $summary, int $aziendaId): void
{
    try {
        $mailer = Mailer::getInstance();
        
        $subject = "Upload multiplo completato - {$summary['successful']} file caricati";
        
        $body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px;'>
            <h2>Upload multiplo completato</h2>
            
            <div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;'>
                <h3>Riepilogo:</h3>
                <ul>
                    <li><strong>File totali:</strong> {$summary['total_files']}</li>
                    <li><strong>Caricati con successo:</strong> {$summary['successful']}</li>
                    <li><strong>Errori:</strong> {$summary['errors']}</li>
                    <li><strong>Dimensione totale:</strong> " . formatBytes($summary['total_size']) . "</li>
                </ul>
            </div>
            
            <p>I documenti sono ora disponibili nel sistema documentale.</p>
            
            <p>
                <a href='" . BASE_URL . "/filesystem.php' 
                   style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>
                   Visualizza Documenti
                </a>
            </p>
        </div>";
        
        $mailer->send($user['email'], $subject, $body);
        
    } catch (Exception $e) {
        error_log("Errore notifica upload: " . $e->getMessage());
    }
}

/**
 * Formatta bytes in formato leggibile
 */
function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, 2) . ' ' . $units[$i];
}
?>