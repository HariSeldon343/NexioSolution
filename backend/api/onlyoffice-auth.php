<?php
/**
 * OnlyOffice Authentication and Configuration API
 * Provides JWT token generation and secure document access
 */

require_once __DIR__ . '/../middleware/Auth.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/onlyoffice.config.php';
require_once __DIR__ . '/../utils/CSRFTokenManager.php';
require_once __DIR__ . '/../utils/ActivityLogger.php';

// Initialize authentication
$auth = Auth::getInstance();
$auth->requireAuth();

// Set JSON response header
header('Content-Type: application/json; charset=utf-8');

// Get request data
$input = file_get_contents('php://input');
$data = json_decode($input, true) ?: [];

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRFTokenManager::validateRequest();
}

// Get action
$action = $data['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'generate_token':
            // Generate access token for document
            $documentId = intval($data['document_id'] ?? $_GET['document_id'] ?? 0);
            if (!$documentId) {
                throw new Exception('Document ID required');
            }
            
            $token = generateDocumentAccessToken($documentId);
            echo json_encode([
                'success' => true,
                'token' => $token
            ]);
            break;
            
        case 'get_config':
            // Get OnlyOffice configuration for document
            $documentId = intval($data['document_id'] ?? $_GET['document_id'] ?? 0);
            if (!$documentId) {
                throw new Exception('Document ID required');
            }
            
            $config = getDocumentConfiguration($documentId);
            echo json_encode([
                'success' => true,
                'config' => $config
            ]);
            break;
            
        case 'verify_access':
            // Verify user has access to document
            $documentId = intval($data['document_id'] ?? $_GET['document_id'] ?? 0);
            if (!$documentId) {
                throw new Exception('Document ID required');
            }
            
            $hasAccess = verifyDocumentAccess($documentId);
            echo json_encode([
                'success' => true,
                'has_access' => $hasAccess
            ]);
            break;
            
        case 'server_status':
            // Check OnlyOffice server status
            $status = getOnlyOfficeServerStatus();
            echo json_encode([
                'success' => true,
                'server_available' => $status,
                'server_url' => $ONLYOFFICE_DS_PUBLIC_URL
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Generate secure access token for document
 */
function generateDocumentAccessToken($documentId) {
    global $auth;
    
    // Verify document exists and user has access
    if (!verifyDocumentAccess($documentId)) {
        throw new Exception('Access denied to document');
    }
    
    // Generate token payload
    $user = $auth->getUser();
    $payload = [
        'document_id' => $documentId,
        'user_id' => $user['id'],
        'azienda_id' => $auth->getCurrentAzienda(),
        'timestamp' => time(),
        'expires' => time() + 3600, // 1 hour expiration
        'nonce' => bin2hex(random_bytes(16))
    ];
    
    // Sign token
    $token = base64_encode(json_encode($payload));
    $signature = hash_hmac('sha256', $token, getSecretKey());
    
    return $token . '.' . $signature;
}

/**
 * Verify document access for current user
 */
function verifyDocumentAccess($documentId) {
    global $auth;
    
    $user = $auth->getUser();
    $isSuperAdmin = $auth->isSuperAdmin();
    $aziendaId = $auth->getCurrentAzienda();
    
    // Build query
    $query = "SELECT id, azienda_id, creato_da FROM documenti WHERE id = ?";
    $params = [$documentId];
    
    // Add company filter for non-super admins
    if (!$isSuperAdmin && $aziendaId) {
        $query .= " AND (azienda_id = ? OR azienda_id IS NULL)";
        $params[] = $aziendaId;
    }
    
    $stmt = db_query($query, $params);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        return false;
    }
    
    // Additional permission checks
    if (!$isSuperAdmin) {
        // Check if user owns the document
        if ($document['creato_da'] == $user['id']) {
            return true;
        }
        
        // Check if user has specific permissions
        if ($user['role'] === 'utente_speciale') {
            return true;
        }
        
        // Check document permissions table if exists
        try {
            $stmt = db_query(
                "SELECT permission FROM document_permissions 
                 WHERE document_id = ? AND user_id = ? AND permission IN ('view', 'edit')",
                [$documentId, $user['id']]
            );
            if ($stmt->rowCount() > 0) {
                return true;
            }
        } catch (Exception $e) {
            // Permissions table might not exist
        }
        
        // Default: allow if in same company
        return $document['azienda_id'] == $aziendaId;
    }
    
    return true;
}

/**
 * Get full OnlyOffice configuration for document
 */
function getDocumentConfiguration($documentId) {
    global $auth, $ONLYOFFICE_DS_PUBLIC_URL, $ONLYOFFICE_JWT_ENABLED, $ONLYOFFICE_CALLBACK_URL;
    
    // Verify access
    if (!verifyDocumentAccess($documentId)) {
        throw new Exception('Access denied to document');
    }
    
    // Get document details
    $stmt = db_query(
        "SELECT d.*, a.nome_azienda 
         FROM documenti d 
         LEFT JOIN aziende a ON d.azienda_id = a.id 
         WHERE d.id = ?",
        [$documentId]
    );
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        throw new Exception('Document not found');
    }
    
    // Get user information
    $user = $auth->getUser();
    $isSuperAdmin = $auth->isSuperAdmin();
    
    // Determine document type
    $extension = strtolower(pathinfo($document['nome_file'], PATHINFO_EXTENSION));
    $documentType = getDocumentType($extension);
    
    // Check edit permissions
    $canEdit = false;
    if (in_array($extension, ['docx', 'xlsx', 'pptx', 'txt', 'csv'])) {
        $canEdit = $isSuperAdmin || 
                   $user['role'] === 'utente_speciale' || 
                   $document['creato_da'] == $user['id'];
    }
    
    // Generate document key
    $documentKey = md5($document['id'] . '_' . $document['data_modifica'] . '_v' . ($document['versione'] ?? 1));
    
    // Build configuration
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
    
    $config = [
        'documentType' => $documentType,
        'document' => [
            'title' => $document['nome_file'],
            'url' => $protocol . '://' . $host . $basePath . '/backend/api/onlyoffice-document.php?id=' . $documentId,
            'fileType' => $extension,
            'key' => $documentKey,
            'info' => [
                'owner' => $document['nome_azienda'] ?? 'Sistema',
                'uploaded' => date('c', strtotime($document['data_caricamento']))
            ],
            'permissions' => [
                'comment' => $canEdit,
                'download' => true,
                'edit' => $canEdit,
                'fillForms' => true,
                'modifyContentControl' => $canEdit,
                'modifyFilter' => $canEdit,
                'print' => true,
                'review' => $canEdit
            ]
        ],
        'editorConfig' => [
            'mode' => $canEdit ? 'edit' : 'view',
            'lang' => 'it',
            'callbackUrl' => $canEdit ? $ONLYOFFICE_CALLBACK_URL . '?id=' . $documentId : null,
            'user' => [
                'id' => (string)$user['id'],
                'name' => $user['nome'] . ' ' . $user['cognome'],
                'group' => $user['role']
            ],
            'customization' => [
                'customer' => [
                    'name' => 'Nexio Platform',
                    'logo' => $protocol . '://' . $host . $basePath . '/assets/images/nexio-logo.svg'
                ],
                'goback' => [
                    'text' => 'Torna ai documenti',
                    'url' => $protocol . '://' . $host . $basePath . '/filesystem.php'
                ]
            ]
        ]
    ];
    
    // Add JWT token if enabled
    if ($ONLYOFFICE_JWT_ENABLED) {
        $config['token'] = generateOnlyOfficeJWT($config);
    }
    
    // Log activity
    ActivityLogger::log(
        'documento',
        'configurazione',
        "Generata configurazione OnlyOffice per: {$document['nome_file']}",
        $user['id'],
        $auth->getCurrentAzienda(),
        $documentId
    );
    
    return $config;
}

/**
 * Get document type for OnlyOffice
 */
function getDocumentType($extension) {
    global $ONLYOFFICE_DOCUMENT_TYPES;
    
    if (isset($ONLYOFFICE_DOCUMENT_TYPES[$extension])) {
        return $ONLYOFFICE_DOCUMENT_TYPES[$extension];
    }
    
    // Default mappings
    if (in_array($extension, ['docx', 'doc', 'odt', 'rtf', 'txt'])) {
        return 'word';
    } elseif (in_array($extension, ['xlsx', 'xls', 'ods', 'csv'])) {
        return 'cell';
    } elseif (in_array($extension, ['pptx', 'ppt', 'odp'])) {
        return 'slide';
    } elseif ($extension === 'pdf') {
        return 'pdf';
    }
    
    return 'word';
}

/**
 * Get secret key for token signing
 */
function getSecretKey() {
    global $ONLYOFFICE_JWT_SECRET;
    
    // Use OnlyOffice JWT secret combined with app secret
    $appSecret = defined('APP_SECRET') ? APP_SECRET : 'nexio-platform-2024';
    return hash('sha256', $ONLYOFFICE_JWT_SECRET . $appSecret);
}
?>