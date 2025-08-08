<?php
/**
 * Files API - Modern File Manager Backend
 * Handles all file and folder operations
 */

// Prevent any output before headers
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/files-api-errors.log');

// Clean all output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Start a fresh output buffer
ob_start();

// Global error handler to ensure JSON output
set_error_handler(function($severity, $message, $file, $line) {
    // Log the error
    error_log("PHP Error [{$severity}]: {$message} in {$file} on line {$line}");
    
    // For fatal errors, output JSON response
    if ($severity & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR)) {
        // Clean any output
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Si è verificato un errore interno del server'
        ]);
        exit;
    }
    
    // Don't execute PHP internal error handler
    return true;
});

// Exception handler for uncaught exceptions
set_exception_handler(function($exception) {
    error_log("Uncaught Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
    
    // Clean any output
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Si è verificato un errore imprevisto'
    ]);
    exit;
});

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../middleware/Auth.php';
require_once '../utils/ActivityLogger.php';
require_once '../utils/PermissionManager.php';

// Clean any output that might have been generated during requires
ob_clean();

// Set JSON header early
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Authentication
$auth = Auth::getInstance();
if (!$auth->checkSession()) {
    // Ensure clean output even for auth errors
    ob_clean();
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Non autenticato']);
    exit;
}

$user = $auth->getUser();
$userId = $user ? $user['id'] : null;
$companyId = $auth->getCurrentCompany(); // This returns integer or null, not array
$permissionManager = PermissionManager::getInstance();

// Validate auth data
if (!$user || !$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Sessione non valida. Effettua nuovamente il login.']);
    exit;
}

// For super admin and special users, companyId can be 0 or null - they can access all companies
// Convert 0 to null for database operations
if ($companyId === 0 && $auth->hasElevatedPrivileges()) {
    // 0 means global access for super_admin and utente_speciale
    $companyId = null; // Use null for global queries
} else if (!$companyId && !$auth->hasElevatedPrivileges()) {
    http_response_code(401);
    echo json_encode(['error' => 'Nessuna azienda selezionata. Seleziona un\'azienda dal menu principale.']);
    exit;
}

// Get request data
$method = $_SERVER['REQUEST_METHOD'];
$rawData = file_get_contents('php://input');
$data = null;

if (!empty($rawData)) {
    $data = json_decode($rawData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON: ' . json_last_error_msg()]);
        exit;
    }
}

// Check for method override (for servers that don't support DELETE)
if ($method === 'POST' && isset($data['_method'])) {
    $method = strtoupper($data['_method']);
    unset($data['_method']);
}

// Also check X-HTTP-Method-Override header
$methodOverride = isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']) ? strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']) : null;
if ($methodOverride && in_array($methodOverride, ['PUT', 'DELETE'])) {
    $method = $methodOverride;
}

// Route request
try {
    switch ($method) {
        case 'POST':
            handlePost($data);
            break;
        case 'PUT':
            handlePut($data);
            break;
        case 'DELETE':
            handleDelete($data);
            break;
        case 'GET':
            handleGet();
            break;
        default:
            throw new Exception('Metodo non supportato: ' . $method);
    }
} catch (Exception $e) {
    // Log the error
    error_log("files-api.php error: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
    
    // Ensure clean JSON output
    ob_clean();
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    $errorResponse = json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    echo $errorResponse;
    exit;
}

/**
 * Handle POST requests
 */
function handlePost($data) {
    global $userId, $companyId;
    
    $action = $data['action'] ?? '';
    
    switch ($action) {
        case 'create_folder':
            createFolder($data);
            break;
        case 'rename':
            renameItem($data);
            break;
        case 'move':
            moveItems($data);
            break;
        case 'copy':
            copyItems($data);
            break;
        default:
            throw new Exception('Azione non valida');
    }
}

/**
 * Create new folder
 */
function createFolder($data) {
    global $userId, $companyId, $auth, $permissionManager;
    
    $nome = trim($data['nome'] ?? '');
    $parentId = $data['parent_id'] ?? null;
    
    // Handle company context for folder creation
    $targetCompanyId = $companyId;
    
    // For super admin and utente_speciale, allow creating global folders (azienda_id = NULL)
    if ($auth->hasElevatedPrivileges()) {
        // If they explicitly specified an azienda_id, use it
        if (isset($data['azienda_id']) && $data['azienda_id'] > 0) {
            $targetCompanyId = $data['azienda_id'];
            // Verify the company exists
            $company = db_query("SELECT id FROM aziende WHERE id = ?", [$targetCompanyId])->fetch();
            if (!$company) {
                throw new Exception('Azienda non trovata');
            }
        } else {
            // No azienda specified OR azienda_id = 0 = create global folder (NULL in database)
            $targetCompanyId = null;
        }
    } else if (!$targetCompanyId) {
        // Normal users must have a company
        throw new Exception('Azienda non valida');
    }
    
    if (empty($nome)) {
        throw new Exception('Nome cartella richiesto');
    }
    
    // Enhanced validation for folder names
    // Check for reserved names
    $reservedNames = ['CON', 'PRN', 'AUX', 'NUL', 'COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9', 'LPT1', 'LPT2', 'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9'];
    if (in_array(strtoupper($nome), $reservedNames)) {
        throw new Exception('Nome cartella riservato dal sistema');
    }
    
    // Check for special characters
    if (preg_match('/[<>:"|?*]/', $nome)) {
        throw new Exception('Il nome della cartella contiene caratteri non validi: < > : " | ? *');
    }
    
    // Check for dots at beginning or end
    if (substr($nome, 0, 1) === '.' || substr($nome, -1) === '.') {
        throw new Exception('Il nome della cartella non può iniziare o terminare con un punto');
    }
    
    // Sanitize folder name
    $nome = preg_replace('/[\/\\\\]/', '_', $nome); // Replace slashes
    $nome = trim($nome);
    
    // Check length
    if (strlen($nome) > 255) {
        throw new Exception('Nome cartella troppo lungo (max 255 caratteri)');
    }
    
    if (strlen($nome) < 1) {
        throw new Exception('Nome cartella troppo corto');
    }
    
    // Convert empty string to null for parent_id
    if ($parentId === '' || $parentId === '0') {
        $parentId = null;
    }
    
    // Check for duplicate folder with proper NULL handling
    $query = "SELECT id FROM cartelle WHERE nome = ?";
    $params = [$nome];
    
    // Handle azienda_id comparison (NULL for global folders)
    if ($targetCompanyId === null) {
        $query .= " AND azienda_id IS NULL";
    } else {
        $query .= " AND azienda_id = ?";
        $params[] = $targetCompanyId;
    }
    
    if ($parentId !== null) {
        // Verify parent folder exists
        if ($targetCompanyId === null) {
            // For global folders, parent can be from any company or global
            $parentCheck = db_query("SELECT id FROM cartelle WHERE id = ?", [$parentId])->fetch();
        } else {
            // For company folders, parent must be from same company
            $parentCheck = db_query("SELECT id FROM cartelle WHERE id = ? AND azienda_id = ?", [$parentId, $targetCompanyId])->fetch();
        }
        
        if (!$parentCheck) {
            throw new Exception('Cartella padre non trovata');
        }
        
        // Check if user has write permission on parent folder
        if (!$auth->hasElevatedPrivileges() && !$permissionManager->checkFolderAccess($parentId, 'write', $userId, $targetCompanyId)) {
            throw new Exception('Non hai i permessi per creare cartelle in questa posizione');
        }
        
        $query .= " AND parent_id = ?";
        $params[] = $parentId;
    } else {
        // Explicitly check for NULL parent_id
        $query .= " AND parent_id IS NULL";
    }
    
    $stmt = db_query($query, $params);
    if ($stmt->fetch()) {
        throw new Exception('Una cartella con questo nome esiste già in questa posizione');
    }
    
    // Create folder
    try {
        db_begin_transaction();
        
        // Build full path
        $percorsoCompleto = $nome;
        if ($parentId) {
            $parent = db_query("SELECT percorso_completo FROM cartelle WHERE id = ?", [$parentId])->fetch();
            if ($parent) {
                $percorsoCompleto = $parent['percorso_completo'] . '/' . $nome;
            }
        } else {
            $percorsoCompleto = '/' . $nome;
        }
        
        $folderId = db_insert('cartelle', [
            'nome' => $nome,
            'parent_id' => $parentId,
            'percorso_completo' => $percorsoCompleto,
            'azienda_id' => $targetCompanyId,
            'creato_da' => $userId,
            'data_creazione' => date('Y-m-d H:i:s')
        ]);
        
        // Log activity
        ActivityLogger::getInstance()->log(
            'cartella_creata',
            'cartelle',
            $folderId,
            ['nome' => $nome, 'parent_id' => $parentId]
        );
        
        db_commit();
        
        // Clean output and send JSON response
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        echo json_encode([
            'success' => true,
            'folder_id' => $folderId,
            'message' => 'Cartella creata con successo'
        ]);
        exit;
    } catch (Exception $e) {
        db_rollback();
        throw $e;
    }
}

/**
 * Rename item (folder or file)
 */
function renameItem($data) {
    global $userId, $companyId, $auth, $permissionManager;
    
    $type = $data['type'] ?? '';
    $id = intval($data['id'] ?? 0);
    $newName = trim($data['new_name'] ?? '');
    
    if (!in_array($type, ['folder', 'file']) || !$id || empty($newName)) {
        throw new Exception('Parametri non validi');
    }
    
    try {
        db_begin_transaction();
        
        if ($type === 'folder') {
            // Check ownership - super admin can access all companies
            if ($companyId) {
                $folder = db_query("SELECT * FROM cartelle WHERE id = ? AND azienda_id = ?", [$id, $companyId])->fetch();
            } else if ($auth->hasElevatedPrivileges()) {
                $folder = db_query("SELECT * FROM cartelle WHERE id = ?", [$id])->fetch();
            } else {
                throw new Exception('Cartella non trovata');
            }
            
            if (!$folder) {
                throw new Exception('Cartella non trovata');
            }
            
            // Check write permission
            if (!$auth->hasElevatedPrivileges() && !$permissionManager->checkFolderAccess($id, 'write', $userId, $folder['azienda_id'])) {
                throw new Exception('Non hai i permessi per rinominare questa cartella');
            }
            
            // Update folder
            db_update('cartelle', 
                ['nome' => $newName],
                'id = ?',
                [$id]
            );
            
            // Update full path for all children
            updateChildrenPaths($id, $folder['parent_id'], $newName);
            
            ActivityLogger::getInstance()->log('cartella_rinominata', 'cartelle', $id, [
                'old_name' => $folder['nome'],
                'new_name' => $newName
            ]);
        } else {
            // Check ownership - super admin can access all companies
            if ($companyId) {
                $doc = db_query("SELECT * FROM documenti WHERE id = ? AND azienda_id = ?", [$id, $companyId])->fetch();
            } else if ($auth->hasElevatedPrivileges()) {
                $doc = db_query("SELECT * FROM documenti WHERE id = ?", [$id])->fetch();
            } else {
                throw new Exception('Documento non trovato');
            }
            
            if (!$doc) {
                throw new Exception('Documento non trovato');
            }
            
            // Update document
            db_update('documenti',
                ['titolo' => $newName],
                'id = ?',
                [$id]
            );
            
            ActivityLogger::getInstance()->log('documento_rinominato', 'documenti', $id, [
                'old_name' => $doc['titolo'],
                'new_name' => $newName
            ]);
        }
        
        db_commit();
        
        // Clean output and send JSON response
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Elemento rinominato con successo'
        ]);
        exit;
    } catch (Exception $e) {
        db_rollback();
        throw $e;
    }
}

/**
 * Update paths for all children folders
 */
function updateChildrenPaths($folderId, $parentId, $newName) {
    // Build new parent path
    $newPath = $newName;
    if ($parentId) {
        $parent = db_query("SELECT percorso_completo FROM cartelle WHERE id = ?", [$parentId])->fetch();
        if ($parent) {
            $newPath = $parent['percorso_completo'] . '/' . $newName;
        }
    }
    
    // Update current folder path
    db_update('cartelle',
        ['percorso_completo' => $newPath],
        'id = ?',
        [$folderId]
    );
    
    // Update all children recursively
    $children = db_query("SELECT id, nome FROM cartelle WHERE parent_id = ?", [$folderId])->fetchAll();
    foreach ($children as $child) {
        updateChildrenPaths($child['id'], $folderId, $child['nome']);
    }
}

/**
 * Handle DELETE requests
 */
function handleDelete($data) {
    global $userId, $companyId, $auth, $permissionManager;
    
    // Enhanced logging for debugging
    error_log("=== FILES-API DELETE REQUEST START ===");
    error_log("Timestamp: " . date('Y-m-d H:i:s'));
    error_log("Data received: " . json_encode($data));
    error_log("User ID: $userId, Company ID: $companyId");
    error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
    if (function_exists('getallheaders')) {
        error_log("Headers: " . json_encode(getallheaders()));
    }
    
    $type = $data['type'] ?? '';
    $id = intval($data['id'] ?? 0);
    
    if (!in_array($type, ['folder', 'file']) || !$id) {
        error_log("files-api.php DELETE - Invalid parameters: type=$type, id=$id");
        throw new Exception('Parametri non validi');
    }
    
    try {
        db_begin_transaction();
        
        if ($type === 'folder') {
            // Check ownership - super admin can access all companies
            if ($companyId) {
                $folder = db_query("SELECT * FROM cartelle WHERE id = ? AND azienda_id = ?", [$id, $companyId])->fetch();
            } else if ($auth->hasElevatedPrivileges()) {
                $folder = db_query("SELECT * FROM cartelle WHERE id = ?", [$id])->fetch();
            } else {
                throw new Exception('Cartella non trovata');
            }
            
            if (!$folder) {
                throw new Exception('Cartella non trovata');
            }
            
            // Check delete permission
            if (!$auth->hasElevatedPrivileges() && !$permissionManager->checkFolderAccess($id, 'delete', $userId, $folder['azienda_id'])) {
                throw new Exception('Non hai i permessi per eliminare questa cartella');
            }
            
            // Check for recursive delete option - default to false for safety
            $recursive = isset($data['recursive']) ? ($data['recursive'] === true || $data['recursive'] === 'true' || $data['recursive'] === '1' || $data['recursive'] === 1) : false;
            
            // Check if folder is empty
            $hasChildren = db_query("SELECT COUNT(*) as count FROM cartelle WHERE parent_id = ?", [$id])->fetch()['count'];
            $hasFiles = db_query("SELECT COUNT(*) as count FROM documenti WHERE cartella_id = ?", [$id])->fetch()['count'];
            
            if (($hasChildren > 0 || $hasFiles > 0) && !$recursive) {
                error_log("files-api.php DELETE - Folder not empty: $hasChildren subfolders, $hasFiles files. Recursive disabled.");
                throw new Exception("La cartella non è vuota ($hasChildren sottocartelle, $hasFiles documenti).");
            }
            
            if ($recursive && ($hasChildren > 0 || $hasFiles > 0)) {
                // Use iterative approach to avoid deep recursion
                $foldersToDelete = [];
                $processQueue = [$id];
                
                // Build list of all folders to delete (bottom-up)
                while (!empty($processQueue)) {
                    $currentFolderId = array_shift($processQueue);
                    $foldersToDelete[] = $currentFolderId;
                    
                    // Get all subfolders
                    $subfolders = db_query("SELECT id FROM cartelle WHERE parent_id = ?", [$currentFolderId])->fetchAll();
                    foreach ($subfolders as $subfolder) {
                        $processQueue[] = $subfolder['id'];
                    }
                }
                
                // Reverse the array to delete from bottom to top
                $foldersToDelete = array_reverse($foldersToDelete);
                
                // Delete all documents and folders
                foreach ($foldersToDelete as $folderId) {
                    // Delete all documents in this folder
                    $documents = db_query("SELECT id, file_path FROM documenti WHERE cartella_id = ?", [$folderId])->fetchAll();
                    foreach ($documents as $doc) {
                        // Delete physical file
                        if (!empty($doc['file_path'])) {
                            $filePath = UPLOAD_PATH . '/documenti/' . $doc['file_path'];
                            if (file_exists($filePath)) {
                                unlink($filePath);
                            }
                        }
                        // Delete document record
                        db_delete('documenti', 'id = ?', [$doc['id']]);
                        
                        ActivityLogger::getInstance()->log('documento_eliminato', 'documenti', $doc['id'], [
                            'motivo' => 'eliminazione_cartella_ricorsiva'
                        ]);
                    }
                    
                    // Delete the folder if it's not the main folder (we'll delete that later)
                    if ($folderId != $id) {
                        if ($companyId) {
                            db_delete('cartelle', 'id = ? AND azienda_id = ?', [$folderId, $companyId]);
                        } else {
                            // For super admin, delete without company restriction
                            db_delete('cartelle', 'id = ?', [$folderId]);
                        }
                        
                        ActivityLogger::getInstance()->log('cartella_eliminata', 'cartelle', $folderId, [
                            'motivo' => 'eliminazione_ricorsiva_sottocartella'
                        ]);
                    }
                }
            }
            
            // Delete the folder itself
            if ($companyId) {
                $deleteResult = db_delete('cartelle', 'id = ? AND azienda_id = ?', [$id, $companyId]);
            } else {
                // For super admin, delete without company restriction
                $deleteResult = db_delete('cartelle', 'id = ?', [$id]);
            }
            
            if (!$deleteResult) {
                throw new Exception('Impossibile eliminare la cartella. Verifica i permessi.');
            }
            
            ActivityLogger::getInstance()->log('cartella_eliminata', 'cartelle', $id, [
                'nome' => $folder['nome'],
                'ricorsiva' => $recursive
            ]);
        } else {
            // Check ownership - super admin can access all companies
            if ($companyId) {
                $doc = db_query("SELECT * FROM documenti WHERE id = ? AND azienda_id = ?", [$id, $companyId])->fetch();
            } else if ($auth->hasElevatedPrivileges()) {
                $doc = db_query("SELECT * FROM documenti WHERE id = ?", [$id])->fetch();
            } else {
                throw new Exception('Documento non trovato');
            }
            
            if (!$doc) {
                throw new Exception('Documento non trovato');
            }
            
            // Delete physical file if it exists
            if (!empty($doc['file_path'])) {
                $filePath = UPLOAD_PATH . '/documenti/' . $doc['file_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            
            // Delete from database
            $deleteResult = db_delete('documenti', 'id = ?', [$id]);
            if (!$deleteResult) {
                throw new Exception('Impossibile eliminare il documento.');
            }
            
            ActivityLogger::getInstance()->log('documento_eliminato', 'documenti', $id, [
                'titolo' => $doc['titolo']
            ]);
        }
        
        db_commit();
        
        error_log("Delete operation completed successfully for $type id: $id");
        error_log("=== FILES-API DELETE REQUEST END ===");
        
        // Clean output and send JSON response
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Elemento eliminato con successo'
        ]);
        exit;
    } catch (Exception $e) {
        db_rollback();
        error_log("Delete operation failed: " . $e->getMessage());
        error_log("=== FILES-API DELETE REQUEST END (ERROR) ===");
        throw $e;
    }
}

/**
 * Move items to another folder
 */
function moveItems($data) {
    global $userId, $companyId;
    
    $items = $data['items'] ?? [];
    $targetFolderId = $data['target_folder_id'] ?? null;
    
    if (empty($items)) {
        throw new Exception('Nessun elemento selezionato');
    }
    
    try {
        db_begin_transaction();
        
        // Verify target folder if specified
        if ($targetFolderId) {
            if ($companyId) {
                $targetFolder = db_query("SELECT * FROM cartelle WHERE id = ? AND azienda_id = ?", 
                    [$targetFolderId, $companyId])->fetch();
            } else {
                // For super admin, allow access to any folder
                $targetFolder = db_query("SELECT * FROM cartelle WHERE id = ?", 
                    [$targetFolderId])->fetch();
            }
            if (!$targetFolder) {
                throw new Exception('Cartella di destinazione non trovata');
            }
        }
        
        foreach ($items as $item) {
            $type = $item['type'] ?? '';
            $id = intval($item['id'] ?? 0);
            
            if ($type === 'folder') {
                // Check ownership and not moving to self or child
                $folder = db_query("SELECT * FROM cartelle WHERE id = ? AND azienda_id = ?", 
                    [$id, $companyId])->fetch();
                if (!$folder) continue;
                
                // Prevent moving folder into itself or its children
                if ($targetFolderId && isChildFolder($targetFolderId, $id)) {
                    throw new Exception('Non puoi spostare una cartella dentro se stessa');
                }
                
                // Update folder
                db_update('cartelle',
                    ['parent_id' => $targetFolderId],
                    'id = ?',
                    [$id]
                );
                
                // Update paths
                updateChildrenPaths($id, $targetFolderId, $folder['nome']);
                
            } else if ($type === 'file') {
                // Check ownership
                $doc = db_query("SELECT * FROM documenti WHERE id = ? AND azienda_id = ?", 
                    [$id, $companyId])->fetch();
                if (!$doc) continue;
                
                // Update document
                db_update('documenti',
                    ['cartella_id' => $targetFolderId],
                    'id = ?',
                    [$id]
                );
            }
        }
        
        ActivityLogger::getInstance()->log('elementi_spostati', null, null, [
            'items_count' => count($items),
            'target_folder_id' => $targetFolderId
        ]);
        
        db_commit();
        
        // Clean output and send JSON response
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Elementi spostati con successo'
        ]);
        exit;
    } catch (Exception $e) {
        db_rollback();
        throw $e;
    }
}

/**
 * Check if folder is child of another
 */
function isChildFolder($childId, $parentId) {
    if ($childId == $parentId) return true;
    
    $current = db_query("SELECT parent_id FROM cartelle WHERE id = ?", [$childId])->fetch();
    if (!$current || !$current['parent_id']) return false;
    
    return isChildFolder($current['parent_id'], $parentId);
}

/**
 * Copy items
 */
function copyItems($data) {
    global $userId, $companyId;
    
    // TODO: Implement file/folder copying
    throw new Exception('Funzione in sviluppo');
}

/**
 * Handle GET requests
 */
function handleGet() {
    global $companyId;
    
    $action = $_GET['action'] ?? '';
    
    error_log("DEBUG: files-api.php GET request - action: $action, companyId: $companyId");
    
    switch ($action) {
        case 'list':
            listFolderContents();
            break;
        case 'folder_tree':
            getFolderTree();
            break;
        case 'search':
            searchItems();
            break;
        case 'check_folder':
            checkFolder();
            break;
        default:
            error_log("DEBUG: files-api.php - Invalid action received: $action");
            throw new Exception('Azione non valida: ' . $action);
    }
}

/**
 * Check if folder has contents
 */
function checkFolder() {
    global $companyId;
    
    $folderId = intval($_GET['id'] ?? 0);
    if (!$folderId) {
        throw new Exception('ID cartella non valido');
    }
    
    // Check if folder exists - super admin can access all companies
    if ($companyId) {
        $folder = db_query("SELECT id FROM cartelle WHERE id = ? AND azienda_id = ?", [$folderId, $companyId])->fetch();
    } else {
        // For super admin, allow access to any folder
        $folder = db_query("SELECT id FROM cartelle WHERE id = ?", [$folderId])->fetch();
    }
    
    if (!$folder) {
        throw new Exception('Cartella non trovata');
    }
    
    // Check for subfolders
    $hasSubfolders = db_query("SELECT COUNT(*) as count FROM cartelle WHERE parent_id = ?", [$folderId])->fetch()['count'] > 0;
    
    // Check for files
    $hasFiles = db_query("SELECT COUNT(*) as count FROM documenti WHERE cartella_id = ?", [$folderId])->fetch()['count'] > 0;
    
    echo json_encode([
        'success' => true,
        'hasContents' => ($hasSubfolders || $hasFiles),
        'hasSubfolders' => $hasSubfolders,
        'hasFiles' => $hasFiles
    ]);
}

/**
 * Get folder tree structure
 */
function getFolderTree() {
    global $companyId;
    
    error_log("DEBUG: getFolderTree called with companyId: $companyId");
    
    if ($companyId) {
        $folders = db_query("
            SELECT id, nome, parent_id 
            FROM cartelle 
            WHERE azienda_id = ? 
            ORDER BY nome
        ", [$companyId])->fetchAll();
    } else {
        // For super admin, get folders from all companies with company info
        $folders = db_query("
            SELECT c.id, c.nome, c.parent_id, c.azienda_id, a.nome as azienda_nome
            FROM cartelle c
            JOIN aziende a ON c.azienda_id = a.id
            ORDER BY a.nome, c.nome
        ", [])->fetchAll();
    }
    
    error_log("DEBUG: Found " . count($folders) . " folders");
    
    // Build tree structure
    $tree = buildTree($folders);
    
    error_log("DEBUG: Tree structure built with " . count($tree) . " root nodes");
    
    echo json_encode([
        'success' => true,
        'tree' => $tree
    ]);
}

/**
 * Build hierarchical tree from flat array
 */
function buildTree($items, $parentId = null) {
    $tree = [];
    
    foreach ($items as $item) {
        if ($item['parent_id'] == $parentId) {
            $children = buildTree($items, $item['id']);
            if ($children) {
                $item['children'] = $children;
            }
            $tree[] = $item;
        }
    }
    
    return $tree;
}

/**
 * List folder contents (folders and files)
 */
function listFolderContents() {
    global $companyId, $auth, $userId;
    
    $folderId = $_GET['folder_id'] ?? null;
    
    // Convert empty string or '0' to null
    if ($folderId === '' || $folderId === '0') {
        $folderId = null;
    }
    
    $isSuperAdmin = $auth->isSuperAdmin();
    $user = $auth->getUser();
    $isUtenteSpeciale = $user['ruolo'] === 'utente_speciale';
    
    error_log("DEBUG: listFolderContents called - companyId: $companyId, folderId: " . ($folderId ?: 'NULL'));
    
    try {
        // Get folders based on user role
        $folders = [];
        if ($folderId) {
            if ($isSuperAdmin || $isUtenteSpeciale) {
                // Super admin and special users can see all folders
                $folders = db_query("
                    SELECT c1.id, c1.nome, c1.parent_id, c1.data_modifica, c1.data_creazione, c1.azienda_id,
                           COALESCE(a.nome, 'File Personali') as azienda_nome,
                           (SELECT COUNT(*) FROM cartelle c2 WHERE c2.parent_id = c1.id) +
                           (SELECT COUNT(*) FROM documenti d WHERE d.cartella_id = c1.id) as files_count
                    FROM cartelle c1 
                    LEFT JOIN aziende a ON c1.azienda_id = a.id
                    WHERE parent_id = ?
                    ORDER BY CASE WHEN c1.azienda_id IS NULL THEN 0 ELSE 1 END, a.nome, c1.nome
                ", [$folderId])->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // Normal users see only their company folders
                $folders = db_query("
                    SELECT id, nome, parent_id, data_modifica, data_creazione,
                           (SELECT COUNT(*) FROM cartelle c2 WHERE c2.parent_id = c1.id) +
                           (SELECT COUNT(*) FROM documenti d WHERE d.cartella_id = c1.id) as files_count
                    FROM cartelle c1 
                    WHERE parent_id = ? AND azienda_id = ?
                    ORDER BY nome
                ", [$folderId, $companyId])->fetchAll(PDO::FETCH_ASSOC);
            }
        } else {
            // Root level folders
            if ($isSuperAdmin || $isUtenteSpeciale) {
                // Super admin and special users see all root folders
                $folders = db_query("
                    SELECT c1.id, c1.nome, c1.parent_id, c1.data_modifica, c1.data_creazione, c1.azienda_id,
                           COALESCE(a.nome, 'File Personali') as azienda_nome,
                           (SELECT COUNT(*) FROM cartelle c2 WHERE c2.parent_id = c1.id) +
                           (SELECT COUNT(*) FROM documenti d WHERE d.cartella_id = c1.id) as files_count
                    FROM cartelle c1 
                    LEFT JOIN aziende a ON c1.azienda_id = a.id
                    WHERE c1.parent_id IS NULL
                    ORDER BY CASE WHEN c1.azienda_id IS NULL THEN 0 ELSE 1 END, a.nome, c1.nome
                ", [])->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // Normal users see only their company root folders
                $folders = db_query("
                    SELECT id, nome, parent_id, data_modifica, data_creazione,
                           (SELECT COUNT(*) FROM cartelle c2 WHERE c2.parent_id = c1.id) +
                           (SELECT COUNT(*) FROM documenti d WHERE d.cartella_id = c1.id) as files_count
                    FROM cartelle c1 
                    WHERE parent_id IS NULL AND azienda_id = ?
                    ORDER BY nome
                ", [$companyId])->fetchAll(PDO::FETCH_ASSOC);
            }
                $folders = db_query("
                    SELECT c1.id, c1.nome, c1.parent_id, c1.data_modifica, c1.data_creazione, c1.azienda_id,
                           COALESCE(a.nome, 'Globali') as azienda_nome,
                           (SELECT COUNT(*) FROM cartelle c2 WHERE c2.parent_id = c1.id) +
                           (SELECT COUNT(*) FROM documenti d WHERE d.cartella_id = c1.id) as files_count
                    FROM cartelle c1 
                    LEFT JOIN aziende a ON c1.azienda_id = a.id
                    WHERE parent_id IS NULL
                    ORDER BY CASE WHEN c1.azienda_id IS NULL THEN 0 ELSE 1 END, a.nome, c1.nome
                ", [])->fetchAll(PDO::FETCH_ASSOC);
            }
        }
        
        // Get files/documents based on user role
        $files = [];
        if ($folderId) {
            if ($isSuperAdmin || $isUtenteSpeciale) {
                // Super admin and special users see all files
                $files = db_query("
                    SELECT d.id, d.titolo as nome, d.tipo_documento, 
                           COALESCE(d.dimensione_file, d.file_size) as dimensione_file, 
                           d.data_modifica, d.data_creazione, 
                           d.mime_type, 
                           d.file_path, d.azienda_id,
                           COALESCE(a.nome, 'File Personali') as azienda_nome
                    FROM documenti d
                    LEFT JOIN aziende a ON d.azienda_id = a.id
                    WHERE d.cartella_id = ?
                    ORDER BY CASE WHEN d.azienda_id IS NULL THEN 0 ELSE 1 END, a.nome, d.titolo
                ", [$folderId])->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // Normal users see only their company files
                $files = db_query("
                    SELECT id, titolo as nome, tipo_documento, 
                           COALESCE(dimensione_file, file_size) as dimensione_file, 
                           data_modifica, data_creazione, 
                           mime_type, 
                           file_path
                    FROM documenti 
                    WHERE cartella_id = ? AND azienda_id = ?
                    ORDER BY titolo
                ", [$folderId, $companyId])->fetchAll(PDO::FETCH_ASSOC);
            }
        } else {
            // Root level files
            if ($isSuperAdmin || $isUtenteSpeciale) {
                // Super admin and special users see all root files
                $files = db_query("
                    SELECT d.id, d.titolo as nome, d.tipo_documento, 
                           COALESCE(d.dimensione_file, d.file_size) as dimensione_file, 
                           d.data_modifica, d.data_creazione, 
                           d.mime_type, 
                           d.file_path, d.azienda_id,
                           COALESCE(a.nome, 'File Personali') as azienda_nome
                    FROM documenti d
                    LEFT JOIN aziende a ON d.azienda_id = a.id
                    WHERE d.cartella_id IS NULL
                    ORDER BY CASE WHEN d.azienda_id IS NULL THEN 0 ELSE 1 END, a.nome, d.titolo
                ", [])->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // Normal users see only their company root files
                $files = db_query("
                    SELECT id, titolo as nome, tipo_documento, 
                           COALESCE(dimensione_file, file_size) as dimensione_file, 
                           data_modifica, data_creazione, 
                           mime_type, 
                           file_path
                    FROM documenti 
                    WHERE cartella_id IS NULL AND azienda_id = ?
                    ORDER BY titolo
                ", [$companyId])->fetchAll(PDO::FETCH_ASSOC);
            }
                           COALESCE(a.nome, 'Globali') as azienda_nome
                    FROM documenti d
                    LEFT JOIN aziende a ON d.azienda_id = a.id
                    WHERE cartella_id IS NULL
                    ORDER BY CASE WHEN d.azienda_id IS NULL THEN 0 ELSE 1 END, a.nome, d.titolo
                ", [])->fetchAll(PDO::FETCH_ASSOC);
            }
        }
        
        // Build breadcrumb
        $breadcrumb = [];
        if ($folderId) {
            $breadcrumb = buildBreadcrumb($folderId);
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'folders' => $folders,
                'files' => $files
            ],
            'breadcrumb' => $breadcrumb
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Build breadcrumb path for a folder
 */
function buildBreadcrumb($folderId) {
    $breadcrumb = [];
    $currentId = $folderId;
    
    while ($currentId) {
        $folder = db_query("SELECT id, nome, parent_id FROM cartelle WHERE id = ?", [$currentId])->fetch(PDO::FETCH_ASSOC);
        if (!$folder) break;
        
        array_unshift($breadcrumb, [
            'id' => $folder['id'],
            'nome' => $folder['nome']
        ]);
        
        $currentId = $folder['parent_id'];
    }
    
    return $breadcrumb;
}

/**
 * Search files and folders
 */
function searchItems() {
    global $companyId;
    
    $query = $_GET['q'] ?? '';
    if (empty($query)) {
        echo json_encode(['success' => true, 'results' => []]);
        return;
    }
    
    $searchTerm = "%$query%";
    
    // Search folders
    if ($companyId) {
        $folders = db_query("
            SELECT id, nome, 'folder' as type, percorso_completo 
            FROM cartelle 
            WHERE azienda_id = ? AND nome LIKE ?
            LIMIT 20
        ", [$companyId, $searchTerm])->fetchAll();
    } else {
        // For super admin, search folders from all companies
        $folders = db_query("
            SELECT c.id, c.nome, 'folder' as type, c.percorso_completo, c.azienda_id, a.nome as azienda_nome
            FROM cartelle c
            JOIN aziende a ON c.azienda_id = a.id
            WHERE c.nome LIKE ?
            ORDER BY a.nome, c.nome
            LIMIT 20
        ", [$searchTerm])->fetchAll();
    }
    
    // Search documents
    if ($companyId) {
        $documents = db_query("
            SELECT id, titolo as nome, 'file' as type, cartella_id 
            FROM documenti 
            WHERE azienda_id = ? AND titolo LIKE ?
            LIMIT 20
        ", [$companyId, $searchTerm])->fetchAll();
    } else {
        // For super admin, search documents from all companies
        $documents = db_query("
            SELECT d.id, d.titolo as nome, 'file' as type, d.cartella_id, d.azienda_id, a.nome as azienda_nome
            FROM documenti d
            JOIN aziende a ON d.azienda_id = a.id
            WHERE d.titolo LIKE ?
            ORDER BY a.nome, d.titolo
            LIMIT 20
        ", [$searchTerm])->fetchAll();
    }
    
    $results = array_merge($folders, $documents);
    
    echo json_encode([
        'success' => true,
        'results' => $results
    ]);
}
?>