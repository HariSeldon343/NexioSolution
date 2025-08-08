<?php
/**
 * Filesystem Simple API
 * API semplificata per gestione file e cartelle
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../middleware/Auth.php';

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

// Authentication
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
$currentCompany = $auth->getCurrentCompany();
$defaultCompanyId = is_array($currentCompany) ? ($currentCompany['id'] ?? null) : $currentCompany;

// Handle request
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            handleGet($action);
            break;
        case 'POST':
            handlePost($action);
            break;
        default:
            throw new Exception('Metodo non supportato');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Handle GET requests
 */
function handleGet($action) {
    global $userId, $isSuperAdmin, $isUtenteSpeciale, $defaultCompanyId;
    
    switch ($action) {
        case 'list':
            listFiles();
            break;
            
        case 'download':
            downloadFile();
            break;
            
        case 'search':
            searchFiles();
            break;
            
        case 'tree':
            getFolderTree();
            break;
            
        default:
            throw new Exception('Azione non valida');
    }
}

/**
 * Handle POST requests
 */
function handlePost($action) {
    // Get JSON data if present
    $rawData = file_get_contents('php://input');
    $data = null;
    if ($rawData) {
        $data = json_decode($rawData, true);
        if (!$data) {
            // If not JSON, try form data
            $data = $_POST;
        }
    } else {
        $data = $_POST;
    }
    
    $action = $action ?: ($data['action'] ?? '');
    
    switch ($action) {
        case 'upload':
            uploadFiles();
            break;
            
        case 'create_folder':
            createFolder($data);
            break;
            
        case 'delete':
            deleteItem($data);
            break;
            
        case 'rename':
            renameItem($data);
            break;
            
        default:
            throw new Exception('Azione non valida');
    }
}

/**
 * List files and folders
 */
function listFiles() {
    global $userId, $isSuperAdmin, $isUtenteSpeciale, $defaultCompanyId;
    
    $folderId = $_GET['folder'] ?? null;
    if ($folderId === '') $folderId = null;
    
    $folders = [];
    $files = [];
    $path = [];
    
    try {
        // Build WHERE clause based on user role
        if ($isSuperAdmin || $isUtenteSpeciale) {
            // Can see all files
            if ($folderId) {
                $folders = db_query("
                    SELECT c.*, 
                           (SELECT COUNT(*) FROM cartelle c2 WHERE c2.parent_id = c.id) +
                           (SELECT COUNT(*) FROM documenti d WHERE d.cartella_id = c.id) as count
                    FROM cartelle c 
                    WHERE c.parent_id = ?
                    ORDER BY c.nome", [$folderId])->fetchAll();
                
                $files = db_query("
                    SELECT id, titolo as nome, mime_type, tipo_documento, 
                           COALESCE(dimensione_file, file_size, 0) as dimensione_file
                    FROM documenti 
                    WHERE cartella_id = ?
                    ORDER BY titolo", [$folderId])->fetchAll();
            } else {
                // Root folders
                $folders = db_query("
                    SELECT c.*, 
                           (SELECT COUNT(*) FROM cartelle c2 WHERE c2.parent_id = c.id) +
                           (SELECT COUNT(*) FROM documenti d WHERE d.cartella_id = c.id) as count
                    FROM cartelle c 
                    WHERE c.parent_id IS NULL
                    ORDER BY c.nome")->fetchAll();
                
                $files = db_query("
                    SELECT id, titolo as nome, mime_type, tipo_documento,
                           COALESCE(dimensione_file, file_size, 0) as dimensione_file
                    FROM documenti 
                    WHERE cartella_id IS NULL
                    ORDER BY titolo")->fetchAll();
            }
        } else {
            // Normal users - see only their company files
            if (!$defaultCompanyId) {
                throw new Exception('Nessuna azienda selezionata');
            }
            
            if ($folderId) {
                $folders = db_query("
                    SELECT c.*,
                           (SELECT COUNT(*) FROM cartelle c2 WHERE c2.parent_id = c.id AND c2.azienda_id = ?) +
                           (SELECT COUNT(*) FROM documenti d WHERE d.cartella_id = c.id AND d.azienda_id = ?) as count
                    FROM cartelle c 
                    WHERE c.parent_id = ? AND c.azienda_id = ?
                    ORDER BY c.nome", [$defaultCompanyId, $defaultCompanyId, $folderId, $defaultCompanyId])->fetchAll();
                
                $files = db_query("
                    SELECT id, titolo as nome, mime_type, tipo_documento,
                           COALESCE(dimensione_file, file_size, 0) as dimensione_file
                    FROM documenti 
                    WHERE cartella_id = ? AND azienda_id = ?
                    ORDER BY titolo", [$folderId, $defaultCompanyId])->fetchAll();
            } else {
                // Root folders for company
                $folders = db_query("
                    SELECT c.*,
                           (SELECT COUNT(*) FROM cartelle c2 WHERE c2.parent_id = c.id AND c2.azienda_id = ?) +
                           (SELECT COUNT(*) FROM documenti d WHERE d.cartella_id = c.id AND d.azienda_id = ?) as count
                    FROM cartelle c 
                    WHERE c.parent_id IS NULL AND c.azienda_id = ?
                    ORDER BY c.nome", [$defaultCompanyId, $defaultCompanyId, $defaultCompanyId])->fetchAll();
                
                $files = db_query("
                    SELECT id, titolo as nome, mime_type, tipo_documento,
                           COALESCE(dimensione_file, file_size, 0) as dimensione_file
                    FROM documenti 
                    WHERE cartella_id IS NULL AND azienda_id = ?
                    ORDER BY titolo", [$defaultCompanyId])->fetchAll();
            }
        }
        
        // Build path
        if ($folderId) {
            $currentFolder = $folderId;
            while ($currentFolder) {
                $folder = db_query("SELECT id, nome, parent_id FROM cartelle WHERE id = ?", [$currentFolder])->fetch();
                if ($folder) {
                    array_unshift($path, ['id' => $folder['id'], 'nome' => $folder['nome']]);
                    $currentFolder = $folder['parent_id'];
                } else {
                    break;
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'folders' => $folders,
                'files' => $files
            ],
            'path' => $path
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Errore nel caricamento: ' . $e->getMessage());
    }
}

/**
 * Upload files
 */
function uploadFiles() {
    global $userId, $isSuperAdmin, $isUtenteSpeciale, $defaultCompanyId;
    
    if (!isset($_FILES['files'])) {
        throw new Exception('Nessun file caricato');
    }
    
    $folderId = $_POST['folder_id'] ?? null;
    if ($folderId === '' || $folderId === '0') $folderId = null;
    
    // Determine company ID
    $companyId = null;
    if ($isSuperAdmin || $isUtenteSpeciale) {
        // Can choose company or leave null for personal
        $companyId = isset($_POST['azienda_id']) && $_POST['azienda_id'] !== '' 
            ? intval($_POST['azienda_id']) 
            : null;
    } else {
        // Normal users must use their company
        $companyId = $defaultCompanyId;
        if (!$companyId) {
            throw new Exception('Nessuna azienda selezionata');
        }
    }
    
    // Validate folder if specified
    if ($folderId) {
        if ($companyId) {
            $folder = db_query("SELECT id FROM cartelle WHERE id = ? AND azienda_id = ?", 
                [$folderId, $companyId])->fetch();
        } else {
            $folder = db_query("SELECT id FROM cartelle WHERE id = ? AND azienda_id IS NULL", 
                [$folderId])->fetch();
        }
        
        if (!$folder && !($isSuperAdmin || $isUtenteSpeciale)) {
            throw new Exception('Cartella non trovata o non accessibile');
        }
    }
    
    $uploadPath = UPLOAD_PATH . '/documenti/';
    if (!is_dir($uploadPath)) {
        mkdir($uploadPath, 0755, true);
    }
    
    $uploaded = 0;
    $errors = [];
    
    // Process each file
    $files = $_FILES['files'];
    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            $errors[] = $files['name'][$i] . ': Errore upload';
            continue;
        }
        
        $fileName = $files['name'][$i];
        $fileTmp = $files['tmp_name'][$i];
        $fileSize = $files['size'][$i];
        $fileType = $files['type'][$i];
        
        // Validate extension
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'doc', 'docx'])) {
            $errors[] = $fileName . ': Tipo file non consentito';
            continue;
        }
        
        // Validate size (10MB)
        if ($fileSize > 10 * 1024 * 1024) {
            $errors[] = $fileName . ': File troppo grande (max 10MB)';
            continue;
        }
        
        // Generate unique filename
        $uniqueName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
        $targetPath = $uploadPath . $uniqueName;
        
        // Move file
        if (move_uploaded_file($fileTmp, $targetPath)) {
            // Insert into database
            try {
                db_insert('documenti', [
                    'titolo' => pathinfo($fileName, PATHINFO_FILENAME),
                    'file_path' => $uniqueName,
                    'mime_type' => $fileType,
                    'dimensione_file' => $fileSize,
                    'cartella_id' => $folderId,
                    'azienda_id' => $companyId,
                    'creato_da' => $userId,
                    'data_creazione' => date('Y-m-d H:i:s'),
                    'data_modifica' => date('Y-m-d H:i:s')
                ]);
                $uploaded++;
            } catch (Exception $e) {
                unlink($targetPath); // Remove file if DB insert fails
                $errors[] = $fileName . ': Errore database';
            }
        } else {
            $errors[] = $fileName . ': Errore spostamento file';
        }
    }
    
    echo json_encode([
        'success' => $uploaded > 0,
        'uploaded' => $uploaded,
        'errors' => $errors
    ]);
}

/**
 * Create folder
 */
function createFolder($data) {
    global $userId, $isSuperAdmin, $isUtenteSpeciale, $defaultCompanyId;
    
    $name = trim($data['name'] ?? '');
    $parentId = $data['parent_id'] ?? null;
    
    if (empty($name)) {
        throw new Exception('Nome cartella richiesto');
    }
    
    // Determine company ID
    $companyId = null;
    if ($isSuperAdmin || $isUtenteSpeciale) {
        $companyId = isset($data['azienda_id']) && $data['azienda_id'] !== '' 
            ? intval($data['azienda_id']) 
            : null;
    } else {
        $companyId = $defaultCompanyId;
        if (!$companyId) {
            throw new Exception('Nessuna azienda selezionata');
        }
    }
    
    // Check parent folder if specified
    if ($parentId) {
        if ($companyId) {
            $parent = db_query("SELECT id FROM cartelle WHERE id = ? AND azienda_id = ?", 
                [$parentId, $companyId])->fetch();
        } else {
            $parent = db_query("SELECT id FROM cartelle WHERE id = ? AND azienda_id IS NULL", 
                [$parentId])->fetch();
        }
        
        if (!$parent && !($isSuperAdmin || $isUtenteSpeciale)) {
            throw new Exception('Cartella padre non trovata');
        }
    }
    
    // Create folder
    db_insert('cartelle', [
        'nome' => $name,
        'parent_id' => $parentId,
        'azienda_id' => $companyId,
        'creata_da' => $userId,
        'data_creazione' => date('Y-m-d H:i:s'),
        'data_modifica' => date('Y-m-d H:i:s')
    ]);
    
    echo json_encode(['success' => true]);
}

/**
 * Delete item
 */
function deleteItem($data) {
    global $userId, $isSuperAdmin, $isUtenteSpeciale, $defaultCompanyId;
    
    error_log('deleteItem called with data: ' . json_encode($data));
    
    $type = $data['type'] ?? '';
    $id = intval($data['id'] ?? 0);
    
    error_log('Delete request - Type: ' . $type . ', ID: ' . $id);
    
    if (!$id) {
        throw new Exception('ID non valido');
    }
    
    if ($type === 'folder') {
        // Check if folder exists and user has access
        if ($isSuperAdmin || $isUtenteSpeciale) {
            $folder = db_query("SELECT * FROM cartelle WHERE id = ?", [$id])->fetch();
        } else {
            $folder = db_query("SELECT * FROM cartelle WHERE id = ? AND azienda_id = ?", 
                [$id, $defaultCompanyId])->fetch();
        }
        
        if (!$folder) {
            throw new Exception('Cartella non trovata');
        }
        
        // Check if empty
        $hasSubfolders = db_query("SELECT COUNT(*) as cnt FROM cartelle WHERE parent_id = ?", 
            [$id])->fetch()['cnt'] > 0;
        $hasFiles = db_query("SELECT COUNT(*) as cnt FROM documenti WHERE cartella_id = ?", 
            [$id])->fetch()['cnt'] > 0;
        
        if ($hasSubfolders || $hasFiles) {
            throw new Exception('La cartella non è vuota');
        }
        
        // Delete folder
        db_delete('cartelle', 'id = ?', [$id]);
        
    } else if ($type === 'file') {
        // Check if file exists and user has access
        if ($isSuperAdmin || $isUtenteSpeciale) {
            $file = db_query("SELECT * FROM documenti WHERE id = ?", [$id])->fetch();
        } else {
            $file = db_query("SELECT * FROM documenti WHERE id = ? AND azienda_id = ?", 
                [$id, $defaultCompanyId])->fetch();
        }
        
        if (!$file) {
            throw new Exception('File non trovato');
        }
        
        // Delete physical file
        if ($file['file_path']) {
            $filePath = UPLOAD_PATH . '/documenti/' . $file['file_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        
        // Delete from database
        db_delete('documenti', 'id = ?', [$id]);
        
    } else {
        throw new Exception('Tipo non valido');
    }
    
    echo json_encode(['success' => true]);
}

/**
 * Rename item
 */
function renameItem($data) {
    global $userId, $isSuperAdmin, $isUtenteSpeciale, $defaultCompanyId;
    
    $type = $data['type'] ?? '';
    $id = intval($data['id'] ?? 0);
    $newName = trim($data['name'] ?? '');
    
    if (!$id || empty($newName)) {
        throw new Exception('Parametri non validi');
    }
    
    if ($type === 'folder') {
        // Check if folder exists and user has access
        if ($isSuperAdmin || $isUtenteSpeciale) {
            $folder = db_query("SELECT * FROM cartelle WHERE id = ?", [$id])->fetch();
        } else {
            $folder = db_query("SELECT * FROM cartelle WHERE id = ? AND azienda_id = ?", 
                [$id, $defaultCompanyId])->fetch();
        }
        
        if (!$folder) {
            throw new Exception('Cartella non trovata');
        }
        
        // Check if name already exists in the same parent
        $existingFolder = db_query("SELECT id FROM cartelle WHERE nome = ? AND parent_id " . 
            ($folder['parent_id'] ? "= ?" : "IS NULL") . " AND id != ?" . 
            ($folder['azienda_id'] ? " AND azienda_id = ?" : " AND azienda_id IS NULL"), 
            array_filter([
                $newName, 
                $folder['parent_id'], 
                $id, 
                $folder['azienda_id']
            ], function($v) { return $v !== null; }))->fetch();
            
        if ($existingFolder) {
            throw new Exception('Esiste già una cartella con questo nome');
        }
        
        // Update folder name
        db_update('cartelle', ['nome' => $newName, 'data_modifica' => date('Y-m-d H:i:s')], 
            'id = ?', [$id]);
        
    } else if ($type === 'file') {
        // Check if file exists and user has access
        if ($isSuperAdmin || $isUtenteSpeciale) {
            $file = db_query("SELECT * FROM documenti WHERE id = ?", [$id])->fetch();
        } else {
            $file = db_query("SELECT * FROM documenti WHERE id = ? AND azienda_id = ?", 
                [$id, $defaultCompanyId])->fetch();
        }
        
        if (!$file) {
            throw new Exception('File non trovato');
        }
        
        // Check if name already exists in the same folder
        $existingFile = db_query("SELECT id FROM documenti WHERE titolo = ? AND cartella_id " . 
            ($file['cartella_id'] ? "= ?" : "IS NULL") . " AND id != ?" . 
            ($file['azienda_id'] ? " AND azienda_id = ?" : " AND azienda_id IS NULL"), 
            array_filter([
                $newName, 
                $file['cartella_id'], 
                $id, 
                $file['azienda_id']
            ], function($v) { return $v !== null; }))->fetch();
            
        if ($existingFile) {
            throw new Exception('Esiste già un file con questo nome');
        }
        
        // Update file name
        db_update('documenti', ['titolo' => $newName, 'data_modifica' => date('Y-m-d H:i:s')], 
            'id = ?', [$id]);
        
    } else {
        throw new Exception('Tipo non valido');
    }
    
    echo json_encode(['success' => true]);
}

/**
 * Download file
 */
function downloadFile() {
    global $isSuperAdmin, $isUtenteSpeciale, $defaultCompanyId;
    
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        http_response_code(404);
        exit('File non trovato');
    }
    
    // Get file info
    if ($isSuperAdmin || $isUtenteSpeciale) {
        $file = db_query("SELECT * FROM documenti WHERE id = ?", [$id])->fetch();
    } else {
        $file = db_query("SELECT * FROM documenti WHERE id = ? AND azienda_id = ?", 
            [$id, $defaultCompanyId])->fetch();
    }
    
    if (!$file) {
        http_response_code(404);
        exit('File non trovato');
    }
    
    $filePath = UPLOAD_PATH . '/documenti/' . $file['file_path'];
    if (!file_exists($filePath)) {
        http_response_code(404);
        exit('File fisico non trovato');
    }
    
    // Send file
    header('Content-Type: ' . ($file['mime_type'] ?: 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . $file['titolo'] . '"');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
}

/**
 * Search files
 */
function searchFiles() {
    global $isSuperAdmin, $isUtenteSpeciale, $defaultCompanyId;
    
    $query = trim($_GET['q'] ?? '');
    if (strlen($query) < 2) {
        echo json_encode(['success' => true, 'data' => ['folders' => [], 'files' => []]]);
        return;
    }
    
    $searchPattern = '%' . $query . '%';
    
    try {
        if ($isSuperAdmin || $isUtenteSpeciale) {
            // Search all
            $folders = db_query("
                SELECT c.*,
                       (SELECT COUNT(*) FROM cartelle c2 WHERE c2.parent_id = c.id) +
                       (SELECT COUNT(*) FROM documenti d WHERE d.cartella_id = c.id) as count
                FROM cartelle c 
                WHERE c.nome LIKE ?
                ORDER BY c.nome
                LIMIT 20", [$searchPattern])->fetchAll();
            
            $files = db_query("
                SELECT id, titolo as nome, mime_type, tipo_documento,
                       COALESCE(dimensione_file, file_size, 0) as dimensione_file
                FROM documenti 
                WHERE titolo LIKE ?
                ORDER BY titolo
                LIMIT 20", [$searchPattern])->fetchAll();
        } else {
            // Search only company files
            $folders = db_query("
                SELECT c.*,
                       (SELECT COUNT(*) FROM cartelle c2 WHERE c2.parent_id = c.id AND c2.azienda_id = ?) +
                       (SELECT COUNT(*) FROM documenti d WHERE d.cartella_id = c.id AND d.azienda_id = ?) as count
                FROM cartelle c 
                WHERE c.nome LIKE ? AND c.azienda_id = ?
                ORDER BY c.nome
                LIMIT 20", [$defaultCompanyId, $defaultCompanyId, $searchPattern, $defaultCompanyId])->fetchAll();
            
            $files = db_query("
                SELECT id, titolo as nome, mime_type, tipo_documento,
                       COALESCE(dimensione_file, file_size, 0) as dimensione_file
                FROM documenti 
                WHERE titolo LIKE ? AND azienda_id = ?
                ORDER BY titolo
                LIMIT 20", [$searchPattern, $defaultCompanyId])->fetchAll();
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'folders' => $folders,
                'files' => $files
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Errore nella ricerca: ' . $e->getMessage());
    }
}

/**
 * Get folder tree structure
 */
function getFolderTree() {
    global $isSuperAdmin, $isUtenteSpeciale, $defaultCompanyId;
    
    try {
        // Build folder tree
        if ($isSuperAdmin || $isUtenteSpeciale) {
            // Get all folders for super admin
            $folders = db_query("SELECT id, nome, parent_id, azienda_id FROM cartelle ORDER BY nome")->fetchAll();
        } else {
            // Get only company folders
            if (!$defaultCompanyId) {
                echo json_encode(['success' => true, 'tree' => []]);
                return;
            }
            $folders = db_query("SELECT id, nome, parent_id, azienda_id FROM cartelle WHERE azienda_id = ? ORDER BY nome", [$defaultCompanyId])->fetchAll();
        }
        
        // Build tree structure
        $tree = buildFolderTree($folders, null);
        
        echo json_encode([
            'success' => true,
            'tree' => $tree
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Errore nel caricamento albero cartelle: ' . $e->getMessage());
    }
}

/**
 * Build hierarchical folder tree
 */
function buildFolderTree($folders, $parentId) {
    $tree = [];
    
    foreach ($folders as $folder) {
        if ($folder['parent_id'] == $parentId) {
            $node = [
                'id' => $folder['id'],
                'nome' => $folder['nome'],
                'azienda_id' => $folder['azienda_id'],
                'children' => buildFolderTree($folders, $folder['id'])
            ];
            $tree[] = $node;
        }
    }
    
    return $tree;
}