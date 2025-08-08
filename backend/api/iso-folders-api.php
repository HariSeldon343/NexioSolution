<?php
/**
 * ISO Folders API - Gestione cartelle del filesystem
 * 
 * Endpoint per operazioni su cartelle: list, tree, contents, create, update, delete
 * Multi-tenant con controllo permessi
 */

require_once '../config/config.php';
require_once '../middleware/Auth.php';
require_once '../utils/PermissionManager.php';
require_once '../utils/ActivityLogger.php';

// Gestione errori
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Autenticazione richiesta
$auth = Auth::getInstance();
if (!$auth->checkSession()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autenticato']);
    exit;
}

$user = $auth->getUser();
$userId = $user['id'];
$userRole = $user['ruolo'];

// Get company_id from GET or POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $companyId = $input['company_id'] ?? $_GET['company_id'] ?? $auth->getCurrentCompany() ?? null;
} else {
    $companyId = $_GET['company_id'] ?? $auth->getCurrentCompany() ?? null;
}

if (!$companyId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Azienda non specificata']);
    exit;
}

// Verifica che l'utente appartenga all'azienda (se non super_admin)
if (!$auth->isSuperAdmin()) {
    $stmt = db_query(
        "SELECT COUNT(*) FROM utenti_aziende WHERE utente_id = ? AND azienda_id = ?",
        [$userId, $companyId]
    );
    if ($stmt->fetchColumn() == 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Accesso negato']);
        exit;
    }
}

// Get action
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$permissionManager = PermissionManager::getInstance();

try {
    switch ($action) {
        case 'get_tree':
            handleGetTreeAction();
            break;
            
        case 'tree':
            handleTreeAction();
            break;
            
        case 'contents':
            handleContentsAction();
            break;
            
        case 'list':
            handleListAction();
            break;
            
        case 'create':
            handleCreateAction();
            break;
            
        case 'rename':
            handleRenameAction();
            break;
            
        case 'update':
            handleUpdateAction();
            break;
            
        case 'delete':
            handleDeleteAction();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Azione non valida']);
            break;
    }
} catch (Exception $e) {
    ActivityLogger::getInstance()->logError('iso_folders_api_error', [
        'action' => $action,
        'error' => $e->getMessage(),
        'user_id' => $userId,
        'company_id' => $companyId
    ]);
    
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore server: ' . $e->getMessage()]);
}

/**
 * Ottieni struttura ad albero per jsTree (compatibile con filesystem.php)
 */
function handleGetTreeAction() {
    global $companyId, $userId, $auth;
    
    $parentId = $_GET['id'] ?? '#';
    
    try {
        if ($parentId === '#') {
            // Root level - ottieni cartelle di primo livello
            $stmt = db_query(
                "SELECT 
                    c.id,
                    c.nome AS text,
                    c.parent_id,
                    CASE WHEN EXISTS(SELECT 1 FROM cartelle sc WHERE sc.parent_id = c.id) THEN true ELSE false END as children,
                    c.iso_metadata
                FROM cartelle c
                WHERE c.parent_id IS NULL AND c.azienda_id = ?
                ORDER BY c.nome",
                [$companyId]
            );
        } else {
            // Ottieni sottocartelle
            $stmt = db_query(
                "SELECT 
                    c.id,
                    c.nome AS text,
                    c.parent_id,
                    CASE WHEN EXISTS(SELECT 1 FROM cartelle sc WHERE sc.parent_id = c.id) THEN true ELSE false END as children,
                    c.iso_metadata
                FROM cartelle c
                WHERE c.parent_id = ? AND c.azienda_id = ?
                ORDER BY c.nome",
                [$parentId, $companyId]
            );
        }
        
        $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formatta per jsTree
        $nodes = [];
        foreach ($folders as $folder) {
            $metadata = json_decode($folder['iso_metadata'], true);
            $icon = $metadata['icona'] ?? 'fas fa-folder';
            
            $nodes[] = [
                'id' => $folder['id'],
                'text' => $folder['text'],
                'children' => $folder['children'],
                'icon' => $icon,
                'data' => [
                    'parent_id' => $folder['parent_id']
                ]
            ];
        }
        
        echo json_encode($nodes);
        
    } catch (Exception $e) {
        throw new Exception("Errore caricamento albero: " . $e->getMessage());
    }
}

/**
 * Gestisce la rinomina delle cartelle
 */
function handleRenameAction() {
    global $companyId, $userId, $permissionManager, $auth;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $folderId = $data['id'] ?? null;
    $newName = trim($data['name'] ?? '');
    $csrfToken = $data['csrf_token'] ?? '';
    
    // Verifica CSRF
    if (!isset($_SESSION['csrf_token']) || $csrfToken !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Token CSRF non valido']);
        return;
    }
    
    if (!$folderId || empty($newName)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Parametri mancanti']);
        return;
    }
    
    try {
        // Verifica permessi
        if (!$auth->isSuperAdmin() && !$auth->hasElevatedPrivileges()) {
            if (!$permissionManager->checkFolderAccess($folderId, 'edit', $userId, $companyId)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Permessi insufficienti']);
                return;
            }
        }
        
        // Sanitizza il nome
        $newName = preg_replace('/[\/\\\\:*?"<>|]/', '', $newName);
        
        // Aggiorna il nome
        db_update('cartelle', ['nome' => $newName], 'id = ? AND azienda_id = ?', [$folderId, $companyId]);
        
        // Aggiorna percorso completo
        updateFolderPaths($folderId);
        
        // Log attività
        ActivityLogger::getInstance()->log('cartella_rinominata', 'cartelle', $folderId, [
            'nuovo_nome' => $newName
        ]);
        
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        throw new Exception("Errore rinomina cartella: " . $e->getMessage());
    }
}

/**
 * Aggiorna i percorsi delle cartelle dopo una rinomina
 */
function updateFolderPaths($folderId) {
    global $companyId;
    
    // Funzione ricorsiva per aggiornare i percorsi
    $updatePath = function($id, $parentPath = '') use (&$updatePath, $companyId) {
        $stmt = db_query("SELECT nome FROM cartelle WHERE id = ?", [$id]);
        $folder = $stmt->fetch();
        
        if ($folder) {
            $newPath = $parentPath . '/' . $folder['nome'];
            db_update('cartelle', ['percorso_completo' => $newPath], 'id = ?', [$id]);
            
            // Aggiorna sottocartelle
            $stmt = db_query("SELECT id FROM cartelle WHERE parent_id = ? AND azienda_id = ?", [$id, $companyId]);
            $children = $stmt->fetchAll();
            
            foreach ($children as $child) {
                $updatePath($child['id'], $newPath);
            }
        }
    };
    
    // Ottieni il percorso del parent
    $stmt = db_query("SELECT parent_id FROM cartelle WHERE id = ?", [$folderId]);
    $folder = $stmt->fetch();
    
    if ($folder && $folder['parent_id']) {
        $stmt = db_query("SELECT percorso_completo FROM cartelle WHERE id = ?", [$folder['parent_id']]);
        $parent = $stmt->fetch();
        $parentPath = $parent ? $parent['percorso_completo'] : '';
    } else {
        $parentPath = '';
    }
    
    $updatePath($folderId, $parentPath);
}

/**
 * Ottieni struttura ad albero delle cartelle
 */
function handleTreeAction() {
    global $companyId, $userId, $permissionManager, $auth;
    
    try {
        // Super admin vede tutto
        $whereClause = $auth->isSuperAdmin() ? "" : " AND azienda_id = ?";
        $params = $auth->isSuperAdmin() ? [] : [$companyId];
        
        // Get all folders
        $stmt = db_query(
            "SELECT 
                c.id,
                c.nome AS name,
                c.parent_id,
                c.percorso_completo,
                c.livello,
                c.colore,
                c.azienda_id,
                COUNT(DISTINCT d.id) as document_count,
                COUNT(DISTINCT sc.id) as subfolder_count
            FROM cartelle c
            LEFT JOIN documenti d ON c.id = d.cartella_id AND d.stato != 'archiviato'
            LEFT JOIN cartelle sc ON c.id = sc.parent_id
            WHERE 1=1 $whereClause
            GROUP BY c.id
            ORDER BY c.parent_id, c.nome",
            $params
        );
        
        $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Build tree structure
        $tree = buildTree($folders);
        
        // Add ISO badges if applicable
        $tree = addISOBadges($tree);
        
        echo json_encode([
            'success' => true,
            'data' => $tree
        ]);
        
    } catch (Exception $e) {
        throw new Exception("Errore caricamento struttura: " . $e->getMessage());
    }
}

/**
 * Ottieni contenuti di una cartella (cartelle + documenti)
 */
function handleContentsAction() {
    global $companyId, $userId, $permissionManager, $auth;
    
    $folderId = $_GET['folder_id'] ?? null;
    
    try {
        // Verifica permessi sulla cartella
        if ($folderId && !$auth->isSuperAdmin()) {
            if (!$permissionManager->checkFolderAccess($folderId, 'view', $userId, $companyId)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Accesso negato alla cartella']);
                return;
            }
        }
        
        // Get subfolders
        $folderWhereClause = "parent_id " . ($folderId ? "= ?" : "IS NULL");
        $folderParams = $folderId ? [$folderId] : [];
        
        if (!$auth->isSuperAdmin()) {
            $folderWhereClause .= " AND azienda_id = ?";
            $folderParams[] = $companyId;
        }
        
        $stmt = db_query(
            "SELECT 
                id,
                nome AS name,
                parent_id,
                colore,
                data_creazione AS created,
                data_aggiornamento AS modified,
                'folder' as type,
                (SELECT COUNT(*) FROM cartelle sc WHERE sc.parent_id = cartelle.id) +
                (SELECT COUNT(*) FROM documenti d WHERE d.cartella_id = cartelle.id AND d.stato != 'archiviato') as item_count
            FROM cartelle
            WHERE $folderWhereClause
            ORDER BY nome",
            $folderParams
        );
        
        $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get documents
        $docWhereClause = "cartella_id " . ($folderId ? "= ?" : "IS NULL");
        $docParams = $folderId ? [$folderId] : [];
        
        if (!$auth->isSuperAdmin()) {
            $docWhereClause .= " AND azienda_id = ?";
            $docParams[] = $companyId;
        }
        
        // Aggiungi filtro per stato non archiviato
        $docWhereClause .= " AND stato != 'archiviato'";
        
        $stmt = db_query(
            "SELECT 
                d.id,
                d.titolo AS name,
                d.codice,
                d.descrizione,
                d.file_path,
                d.formato AS extension,
                d.dimensione_file AS size,
                d.stato,
                d.versione,
                d.data_creazione AS created,
                d.data_modifica AS modified,
                'document' as type,
                c.nome AS iso_standard,
                u.nome AS created_by_name,
                u.cognome AS created_by_surname
            FROM documenti d
            LEFT JOIN classificazioni c ON d.classificazione_id = c.id
            LEFT JOIN utenti u ON d.creato_da = u.id
            WHERE $docWhereClause
            ORDER BY d.titolo",
            $docParams
        );
        
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format documents
        foreach ($documents as &$doc) {
            $doc['created_by'] = trim($doc['created_by_name'] . ' ' . $doc['created_by_surname']);
            unset($doc['created_by_name'], $doc['created_by_surname']);
        }
        
        // Build breadcrumb
        $breadcrumb = [];
        if ($folderId) {
            $breadcrumb = getBreadcrumb($folderId);
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'folders' => $folders,
                'documents' => $documents
            ],
            'breadcrumb' => $breadcrumb,
            'current_folder' => $folderId
        ]);
        
    } catch (Exception $e) {
        throw new Exception("Errore caricamento contenuti: " . $e->getMessage());
    }
}

/**
 * Lista semplice delle cartelle
 */
function handleListAction() {
    global $companyId, $userId, $auth;
    
    try {
        $whereClause = $auth->isSuperAdmin() ? "" : " WHERE azienda_id = ?";
        $params = $auth->isSuperAdmin() ? [] : [$companyId];
        
        $stmt = db_query(
            "SELECT 
                id,
                nome,
                parent_id,
                percorso_completo,
                livello,
                colore
            FROM cartelle
            $whereClause
            ORDER BY percorso_completo",
            $params
        );
        
        $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $folders
        ]);
        
    } catch (Exception $e) {
        throw new Exception("Errore lista cartelle: " . $e->getMessage());
    }
}

/**
 * Crea nuova cartella
 */
function handleCreateAction() {
    global $companyId, $userId, $permissionManager, $auth;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validazione input
    $nome = trim($data['nome'] ?? '');
    $parentId = $data['parent_id'] ?? null;
    $colore = $data['colore'] ?? '#fbbf24';
    
    if (empty($nome)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nome cartella obbligatorio']);
        return;
    }
    
    // Sanitizza il nome
    $nome = preg_replace('/[\/\\\\:*?"<>|]/', '', $nome);
    
    try {
        // Verifica permessi sulla cartella parent
        if ($parentId && !$auth->isSuperAdmin()) {
            if (!$permissionManager->checkFolderAccess($parentId, 'create', $userId, $companyId)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Permessi insufficienti per creare cartelle qui']);
                return;
            }
        }
        
        // Verifica unicità nome nella stessa cartella
        $checkParams = [$nome, $companyId];
        $checkWhere = "nome = ? AND azienda_id = ? AND parent_id ";
        
        if ($parentId) {
            $checkWhere .= "= ?";
            $checkParams[] = $parentId;
        } else {
            $checkWhere .= "IS NULL";
        }
        
        $stmt = db_query("SELECT COUNT(*) FROM cartelle WHERE $checkWhere", $checkParams);
        
        if ($stmt->fetchColumn() > 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Esiste già una cartella con questo nome']);
            return;
        }
        
        // Calcola percorso e livello
        $percorso = '';
        $livello = 0;
        
        if ($parentId) {
            $stmt = db_query(
                "SELECT percorso_completo, livello FROM cartelle WHERE id = ? AND azienda_id = ?",
                [$parentId, $companyId]
            );
            $parent = $stmt->fetch();
            
            if (!$parent) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Cartella padre non trovata']);
                return;
            }
            
            $percorso = $parent['percorso_completo'] . '/' . $nome;
            $livello = $parent['livello'] + 1;
        } else {
            $percorso = '/' . $nome;
        }
        
        // Inserisci cartella
        db_begin_transaction();
        
        try {
            $cartellaId = db_insert('cartelle', [
                'nome' => $nome,
                'parent_id' => $parentId,
                'percorso_completo' => $percorso,
                'livello' => $livello,
                'colore' => $colore,
                'azienda_id' => $companyId,
                'creato_da' => $userId,
                'aggiornato_da' => $userId
            ]);
            
            // Log attività
            ActivityLogger::getInstance()->log('cartella_creata', 'cartelle', $cartellaId, [
                'nome' => $nome,
                'percorso' => $percorso,
                'parent_id' => $parentId
            ]);
            
            db_commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Cartella creata con successo',
                'data' => [
                    'id' => $cartellaId,
                    'nome' => $nome,
                    'percorso_completo' => $percorso
                ]
            ]);
            
        } catch (Exception $e) {
            db_rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        throw new Exception("Errore creazione cartella: " . $e->getMessage());
    }
}

/**
 * Aggiorna cartella esistente
 */
function handleUpdateAction() {
    global $companyId, $userId, $permissionManager, $auth;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $cartellaId = $data['id'] ?? null;
    
    if (!$cartellaId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID cartella mancante']);
        return;
    }
    
    try {
        // Verifica permessi
        if (!$auth->isSuperAdmin()) {
            if (!$permissionManager->checkFolderAccess($cartellaId, 'edit', $userId, $companyId)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Permessi insufficienti']);
                return;
            }
        }
        
        // Verifica esistenza cartella
        $stmt = db_query(
            "SELECT * FROM cartelle WHERE id = ? AND azienda_id = ?",
            [$cartellaId, $companyId]
        );
        $cartella = $stmt->fetch();
        
        if (!$cartella) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Cartella non trovata']);
            return;
        }
        
        $updateData = [];
        $oldValues = [];
        
        // Nome
        if (isset($data['nome']) && $data['nome'] !== $cartella['nome']) {
            $newNome = trim($data['nome']);
            $newNome = preg_replace('/[\/\\\\:*?"<>|]/', '', $newNome);
            
            if (empty($newNome)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Nome cartella non valido']);
                return;
            }
            
            // Verifica unicità
            $checkParams = [$newNome, $companyId, $cartellaId];
            $checkWhere = "nome = ? AND azienda_id = ? AND id != ? AND parent_id ";
            
            if ($cartella['parent_id']) {
                $checkWhere .= "= ?";
                $checkParams[] = $cartella['parent_id'];
            } else {
                $checkWhere .= "IS NULL";
            }
            
            $stmt = db_query("SELECT COUNT(*) FROM cartelle WHERE $checkWhere", $checkParams);
            
            if ($stmt->fetchColumn() > 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Nome già utilizzato']);
                return;
            }
            
            $updateData['nome'] = $newNome;
            $oldValues['nome'] = $cartella['nome'];
            
            // Aggiorna anche il percorso
            $newPercorso = dirname($cartella['percorso_completo']) . '/' . $newNome;
            if (dirname($cartella['percorso_completo']) === '.') {
                $newPercorso = '/' . $newNome;
            }
            $updateData['percorso_completo'] = $newPercorso;
        }
        
        // Colore
        if (isset($data['colore'])) {
            $updateData['colore'] = $data['colore'];
            $oldValues['colore'] = $cartella['colore'];
        }
        
        if (empty($updateData)) {
            echo json_encode(['success' => true, 'message' => 'Nessuna modifica richiesta']);
            return;
        }
        
        // Aggiorna
        db_begin_transaction();
        
        try {
            $updateData['aggiornato_da'] = $userId;
            
            db_update('cartelle', $updateData, 'id = ?', [$cartellaId]);
            
            // Se il nome è cambiato, aggiorna i percorsi delle sottocartelle
            if (isset($updateData['nome'])) {
                updateSubfolderPaths($cartellaId, $cartella['percorso_completo'], $updateData['percorso_completo']);
            }
            
            // Log attività
            ActivityLogger::getInstance()->log('cartella_aggiornata', 'cartelle', $cartellaId, [
                'modifiche' => array_keys($updateData),
                'valori_precedenti' => $oldValues
            ]);
            
            db_commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Cartella aggiornata con successo'
            ]);
            
        } catch (Exception $e) {
            db_rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        throw new Exception("Errore aggiornamento cartella: " . $e->getMessage());
    }
}

/**
 * Elimina cartella
 */
function handleDeleteAction() {
    global $companyId, $userId, $permissionManager, $auth;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
        return;
    }
    
    $cartellaId = $_GET['id'] ?? null;
    
    if (!$cartellaId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID cartella mancante']);
        return;
    }
    
    try {
        // Verifica permessi
        if (!$auth->isSuperAdmin()) {
            if (!$permissionManager->checkFolderAccess($cartellaId, 'delete', $userId, $companyId)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Permessi insufficienti']);
                return;
            }
        }
        
        // Verifica esistenza e contenuto
        $stmt = db_query(
            "SELECT 
                c.*,
                (SELECT COUNT(*) FROM cartelle WHERE parent_id = c.id) as subfolder_count,
                (SELECT COUNT(*) FROM documenti WHERE cartella_id = c.id) as document_count
            FROM cartelle c
            WHERE c.id = ? AND c.azienda_id = ?",
            [$cartellaId, $companyId]
        );
        
        $cartella = $stmt->fetch();
        
        if (!$cartella) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Cartella non trovata']);
            return;
        }
        
        // Verifica se è vuota
        if ($cartella['subfolder_count'] > 0 || $cartella['document_count'] > 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'message' => 'La cartella contiene elementi e non può essere eliminata'
            ]);
            return;
        }
        
        // Elimina
        db_begin_transaction();
        
        try {
            db_delete('cartelle', 'id = ?', [$cartellaId]);
            
            // Log attività
            ActivityLogger::getInstance()->log('cartella_eliminata', 'cartelle', $cartellaId, [
                'nome' => $cartella['nome'],
                'percorso' => $cartella['percorso_completo']
            ]);
            
            db_commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Cartella eliminata con successo'
            ]);
            
        } catch (Exception $e) {
            db_rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        throw new Exception("Errore eliminazione cartella: " . $e->getMessage());
    }
}

/**
 * Costruisce struttura ad albero dalle cartelle
 */
function buildTree($folders, $parentId = null) {
    $tree = [];
    
    foreach ($folders as $folder) {
        if ($folder['parent_id'] == $parentId) {
            $node = [
                'id' => $folder['id'],
                'name' => $folder['name'],
                'nome' => $folder['name'], // Compatibilità
                'type' => 'folder',
                'count' => $folder['document_count'],
                'children' => buildTree($folders, $folder['id'])
            ];
            
            // Aggiungi badge se presente
            if (isset($folder['colore'])) {
                $node['color'] = $folder['colore'];
            }
            
            $tree[] = $node;
        }
    }
    
    return $tree;
}

/**
 * Aggiunge badge ISO alle cartelle
 */
function addISOBadges($tree) {
    $isoBadges = [
        'ISO 9001' => 'ISO9001',
        'ISO 14001' => 'ISO14001',
        'ISO 45001' => 'ISO45001',
        'GDPR' => 'GDPR'
    ];
    
    foreach ($tree as &$node) {
        foreach ($isoBadges as $pattern => $badge) {
            if (stripos($node['name'], $pattern) !== false) {
                $node['badge'] = $badge;
                break;
            }
        }
        
        if (!empty($node['children'])) {
            $node['children'] = addISOBadges($node['children']);
        }
    }
    
    return $tree;
}

/**
 * Costruisce breadcrumb per navigazione
 */
function getBreadcrumb($folderId) {
    global $companyId;
    
    $breadcrumb = [];
    $currentId = $folderId;
    
    while ($currentId) {
        $stmt = db_query(
            "SELECT id, nome, parent_id FROM cartelle WHERE id = ? AND azienda_id = ?",
            [$currentId, $companyId]
        );
        
        $folder = $stmt->fetch();
        if (!$folder) break;
        
        array_unshift($breadcrumb, [
            'id' => $folder['id'],
            'name' => $folder['nome']
        ]);
        
        $currentId = $folder['parent_id'];
    }
    
    return $breadcrumb;
}

/**
 * Aggiorna percorsi delle sottocartelle quando una cartella viene rinominata
 */
function updateSubfolderPaths($parentId, $oldPath, $newPath) {
    global $companyId;
    
    // Ottieni tutte le sottocartelle
    $stmt = db_query(
        "SELECT id, percorso_completo FROM cartelle 
         WHERE percorso_completo LIKE ? AND azienda_id = ?",
        [$oldPath . '/%', $companyId]
    );
    
    $subfolders = $stmt->fetchAll();
    
    foreach ($subfolders as $subfolder) {
        $newSubPath = str_replace($oldPath, $newPath, $subfolder['percorso_completo']);
        db_update('cartelle', 
            ['percorso_completo' => $newSubPath], 
            'id = ?', 
            [$subfolder['id']]
        );
    }
}