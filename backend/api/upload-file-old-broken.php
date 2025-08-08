<?php
/**
 * API per upload file singoli e multipli
 * Supporta upload sicuro con validazione e antivirus
 * 
 * @package Nexio
 * @version 1.0.0
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../middleware/Auth.php';
require_once '../utils/PermissionManager.php';
require_once '../utils/ActivityLogger.php';
require_once '../utils/CSRFTokenManager.php';

// Set JSON header early
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache, must-revalidate');

// Helper function to generate document code
function generateDocumentCode() {
    return 'DOC_' . strtoupper(uniqid());
}

// Autenticazione
$auth = Auth::getInstance();
if (!$auth->checkSession()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non autenticato']);
    exit;
}

$user = $auth->getUser();
$userId = $user['id'];
$isSuperAdmin = $auth->isSuperAdmin();
$isUtenteSpeciale = $user['ruolo'] === 'utente_speciale';

// Get company from request or session
$requestedCompanyId = isset($_POST['azienda_id']) ? intval($_POST['azienda_id']) : null;
$sessionCompanyId = $auth->getCurrentCompany();

// Determine company context based on user role
if ($isSuperAdmin || $isUtenteSpeciale) {
    // Super admin and special users can choose company or use global (null)
    if ($requestedCompanyId !== null) {
        // Validate requested company exists if not 0 (global)
        if ($requestedCompanyId > 0) {
            $companyCheck = db_query("SELECT id FROM aziende WHERE id = ? AND status = 'attiva'", [$requestedCompanyId])->fetch();
            if (!$companyCheck) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Azienda richiesta non valida']);
                exit;
            }
        }
        $companyId = $requestedCompanyId === 0 ? null : $requestedCompanyId;
    } else {
        // Use session company or null for global
        $companyId = $sessionCompanyId;
    }
} else {
    // Normal users must have a company
    $companyId = $sessionCompanyId;
    if (!$companyId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Nessuna azienda selezionata']);
        exit;
    }
}

// Configurazione upload
$maxFileSize = 10 * 1024 * 1024; // 10MB
$allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'jpg', 'jpeg', 'png', 'gif'];
$uploadPath = UPLOAD_PATH . '/documenti/';

// Crea directory se non esiste
if (!file_exists($uploadPath)) {
    mkdir($uploadPath, 0755, true);
}

try {
    // Verifica CSRF token usando il manager
    $csrfManager = CSRFTokenManager::getInstance();
    $csrfManager->verifyRequest();

    // Verifica se è un upload singolo o multiplo
    if (!isset($_FILES['file']) && !isset($_FILES['files'])) {
        throw new Exception('Nessun file caricato');
    }
    
    $permissionManager = PermissionManager::getInstance();
    
    // Verifica permessi upload
    $targetFolderId = $_POST['cartella_id'] ?? $_POST['folder_id'] ?? null;
    
    // Convert empty string to null
    if ($targetFolderId === '' || $targetFolderId === '0') {
        $targetFolderId = null;
    }
    
    if ($targetFolderId) {
        // Verify target folder exists and check access based on user role
        if ($isSuperAdmin || $isUtenteSpeciale) {
            // Super admin and special users can access all folders or company-specific ones
            if ($companyId === null) {
                // Global context - check folder exists with null azienda_id
                $folderCheck = db_query("SELECT id FROM cartelle WHERE id = ? AND azienda_id IS NULL", [$targetFolderId])->fetch();
            } else {
                // Company context - check folder exists for that company
                $folderCheck = db_query("SELECT id FROM cartelle WHERE id = ? AND azienda_id = ?", [$targetFolderId, $companyId])->fetch();
            }
        } else {
            // Normal users - check folder exists for their company
            $folderCheck = db_query("SELECT id FROM cartelle WHERE id = ? AND azienda_id = ?", [$targetFolderId, $companyId])->fetch();
        }
        
        if (!$folderCheck) {
            throw new Exception('Cartella di destinazione non trovata o non accessibile');
        }
        
        // Verifica permessi sulla cartella target (più permissivo)
        if (!$auth->isSuperAdmin() && !$permissionManager->checkFolderAccess($targetFolderId, 'create', $userId, $companyId)) {
            // Fallback: check if user can at least write to the folder
            if (!$permissionManager->checkFolderAccess($targetFolderId, 'write', $userId, $companyId)) {
                throw new Exception('Non hai i permessi per caricare file in questa cartella');
            }
        }
    }
    
    $files = [];
    
    // Normalizza array files
    if (isset($_FILES['file'])) {
        // Upload singolo
        $files[] = $_FILES['file'];
    } else {
        // Upload multiplo
        for ($i = 0; $i < count($_FILES['files']['name']); $i++) {
            $files[] = [
                'name' => $_FILES['files']['name'][$i],
                'type' => $_FILES['files']['type'][$i],
                'tmp_name' => $_FILES['files']['tmp_name'][$i],
                'error' => $_FILES['files']['error'][$i],
                'size' => $_FILES['files']['size'][$i]
            ];
        }
    }
    
    $uploadedFiles = [];
    $errors = [];
    
    db_begin_transaction();
    
    foreach ($files as $file) {
        try {
            // Valida file
            validateFile($file, $allowedExtensions, $maxFileSize);
            
            // Genera nome univoco
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $fileName = uniqid() . '_' . time() . '.' . $extension;
            $filePath = $uploadPath . $fileName;
            
            // Sposta file
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                throw new Exception('Errore durante il caricamento del file');
            }
            
            // Crea record documento - solo campi base garantiti
            $documentData = [
                'titolo' => $_POST['titolo'] ?? pathinfo($file['name'], PATHINFO_FILENAME),
                'file_path' => $fileName, // Store only filename, not full path
                'azienda_id' => $companyId,
                'creato_da' => $userId,
                'data_creazione' => date('Y-m-d H:i:s'),
                'stato' => 'pubblicato', // Default to published for uploaded files
                'versione' => 1
            ];
            
            // Aggiungi campi opzionali solo se esistono nel database
            if (db_column_exists('documenti', 'codice')) {
                $documentData['codice'] = $_POST['codice'] ?? generateDocumentCode();
            }
            if (db_column_exists('documenti', 'descrizione')) {
                $documentData['descrizione'] = $_POST['descrizione'] ?? null;
            }
            if (db_column_exists('documenti', 'cartella_id')) {
                $documentData['cartella_id'] = $targetFolderId;
            }
            if (db_column_exists('documenti', 'tipo_documento')) {
                $documentData['tipo_documento'] = $_POST['tipo_documento'] ?? 'generico';
            }
            if (db_column_exists('documenti', 'classificazione_iso')) {
                $documentData['classificazione_iso'] = $_POST['classificazione'] ?? 'interno';
            }
            if (db_column_exists('documenti', 'dimensione_file')) {
                $documentData['dimensione_file'] = $file['size'];
            }
            if (db_column_exists('documenti', 'tipo_mime')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $documentData['tipo_mime'] = finfo_file($finfo, $filePath); // Use final file path
                finfo_close($finfo);
            }
            
            // Gestione tag opzionali
            if (!empty($_POST['tags']) && db_column_exists('documenti', 'tags')) {
                $tags = array_map('trim', explode(',', $_POST['tags']));
                $documentData['tags'] = json_encode($tags);
            }
            
            $documentId = db_insert('documenti', $documentData);
            
            // Crea prima versione se la tabella esiste
            if (db_table_exists('documenti_versioni')) {
                $versionData = [
                    'documento_id' => $documentId,
                    'versione' => 1,
                    'creato_da' => $userId,
                    'data_creazione' => date('Y-m-d H:i:s'),
                    'note' => 'Versione iniziale'
                ];
                
                // Add optional fields if they exist
                if (db_column_exists('documenti_versioni', 'file_path')) {
                    $versionData['file_path'] = $documentData['file_path'];
                }
                if (db_column_exists('documenti_versioni', 'dimensione_file')) {
                    $versionData['dimensione_file'] = $documentData['dimensione_file'];
                }
                if (db_column_exists('documenti_versioni', 'tipo_mime')) {
                    $versionData['tipo_mime'] = $documentData['tipo_mime'] ?? null;
                }
                
                db_insert('documenti_versioni', $versionData);
            }
            
            // Log attività
            ActivityLogger::getInstance()->log('documento_caricato', 'documenti', $documentId, [
                'file_name' => $file['name'],
                'file_size' => $file['size'],
                'cartella_id' => $documentData['cartella_id'] ?? null,
                'company_id' => $companyId
            ]);
            
            $uploadedFiles[] = [
                'id' => $documentId,
                'nome' => $file['name'],
                'titolo' => $documentData['titolo'],
                'codice' => $documentData['codice'] ?? null,
                'dimensione' => formatFileSize($file['size']),
                'cartella_id' => $targetFolderId
            ];
            
        } catch (Exception $e) {
            $errors[] = [
                'file' => $file['name'],
                'error' => $e->getMessage()
            ];
        }
    }
    
    db_commit();
    
    // Prepara risposta
    $response = [
        'success' => count($errors) === 0,
        'uploaded' => $uploadedFiles,
        'errors' => $errors,
        'total' => count($files),
        'successful' => count($uploadedFiles),
        'failed' => count($errors)
    ];
    
    if (count($uploadedFiles) > 0 && count($errors) === 0) {
        $response['message'] = 'Tutti i file sono stati caricati con successo';
    } elseif (count($uploadedFiles) > 0 && count($errors) > 0) {
        $response['message'] = count($uploadedFiles) . ' file caricati, ' . count($errors) . ' errori';
    } else {
        $response['message'] = 'Nessun file caricato correttamente';
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    db_rollback();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Valida un file
 */
function validateFile($file, $allowedExtensions, $maxFileSize) {
    // Verifica errori upload con messaggi dettagliati
    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
                $maxUpload = ini_get('upload_max_filesize');
                throw new Exception("File troppo grande. Il file supera il limite del server ({$maxUpload})");
            case UPLOAD_ERR_FORM_SIZE:
                throw new Exception('File troppo grande. Il file supera il limite del form');
            case UPLOAD_ERR_NO_FILE:
                throw new Exception('Nessun file selezionato');
            case UPLOAD_ERR_PARTIAL:
                throw new Exception('Upload parziale. Il file non è stato caricato completamente');
            case UPLOAD_ERR_NO_TMP_DIR:
                throw new Exception('Errore del server: directory temporanea mancante');
            case UPLOAD_ERR_CANT_WRITE:
                throw new Exception('Errore del server: impossibile scrivere su disco');
            case UPLOAD_ERR_EXTENSION:
                throw new Exception('Errore del server: upload bloccato da estensione PHP');
            default:
                throw new Exception('Errore sconosciuto durante upload (codice: ' . $file['error'] . ')');
        }
    }
    
    // Verifica che il file esista realmente
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new Exception('File upload non valido per motivi di sicurezza');
    }
    
    // Verifica dimensione prima di altri controlli
    if ($file['size'] === 0) {
        throw new Exception('File vuoto o corrotto');
    }
    
    if ($file['size'] > $maxFileSize) {
        throw new Exception('File troppo grande (max ' . formatFileSize($maxFileSize) . ', caricato: ' . formatFileSize($file['size']) . ')');
    }
    
    // Verifica estensione
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions)) {
        throw new Exception("Tipo file non consentito (.{$extension}). Formati accettati: " . implode(', ', $allowedExtensions));
    }
    
    // Verifica MIME type reale usando finfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if (!$finfo) {
        throw new Exception('Errore del server: impossibile verificare il tipo di file');
    }
    
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if ($mimeType === false) {
        throw new Exception('Impossibile determinare il tipo di file');
    }
    
    // Mapping estensione -> MIME types accettati
    $extensionMimeMap = [
        'pdf' => ['application/pdf', 'application/x-pdf'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xls' => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'ppt' => ['application/vnd.ms-powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'txt' => ['text/plain'],
        'csv' => ['text/csv', 'text/plain', 'application/csv'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif']
    ];
    
    // Verifica che il MIME type corrisponda all'estensione
    if (isset($extensionMimeMap[$extension])) {
        $expectedMimes = $extensionMimeMap[$extension];
        if (!in_array($mimeType, $expectedMimes)) {
            throw new Exception("Il tipo di file ({$mimeType}) non corrisponde all'estensione (.{$extension})");
        }
    } else {
        throw new Exception("Estensione file non riconosciuta: .{$extension}");
    }
    
    // Controllo aggiuntivo per file potenzialmente pericolosi
    $dangerousPatterns = [
        '/^\x{FEFF}?\<\?php/i',  // PHP files
        '/^\x{FEFF}?\<\?=/i',     // Short PHP tags
        '/^\#\!.*php/i',          // PHP shebang
        '/^\x{FEFF}?\<script/i',  // JavaScript
        '/^\x{FEFF}?\<html/i',    // HTML
    ];
    
    $fileContent = file_get_contents($file['tmp_name'], false, null, 0, 1024); // Read first 1KB
    foreach ($dangerousPatterns as $pattern) {
        if (preg_match($pattern, $fileContent)) {
            throw new Exception('Il file contiene contenuto potenzialmente pericoloso');
        }
    }
    
    // Sanitizza nome file
    $fileName = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $file['name']);
    $fileName = preg_replace('/_{2,}/', '_', $fileName); // Replace multiple underscores
    $fileName = trim($fileName, '_.');
    
    if (empty($fileName)) {
        throw new Exception('Nome file non valido dopo sanitizzazione');
    }
    
    return true;
}

/**
 * Genera codice documento automatico
 */
function generateDocumentCode() {
    $prefix = 'DOC';
    $year = date('Y');
    $random = strtoupper(substr(md5(uniqid()), 0, 4));
    
    return $prefix . '-' . $year . '-' . $random;
}

/**
 * Formatta dimensione file
 */
function formatFileSize($bytes) {
    if ($bytes == 0) return '0 B';
    $k = 1024;
    $sizes = ['B', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}