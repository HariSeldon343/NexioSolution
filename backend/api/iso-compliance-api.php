<?php
/**
 * ISO Compliance Document Management API
 * RESTful API endpoints for ISO document management
 * 
 * @package Nexio\API
 * @version 1.0.0
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

require_once '../middleware/Auth.php';
require_once '../services/ISOComplianceService.php';
require_once '../utils/RateLimiter.php';

use Nexio\Services\ISOComplianceService;

// Initialize authentication
$auth = Auth::getInstance();
if (!$auth->checkSession()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autenticato']);
    exit;
}

// Rate limiting
$rateLimiter = new RateLimiter();
if (!$rateLimiter->check($_SERVER['REMOTE_ADDR'], 'api_iso', 100, 60)) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many requests']);
    exit;
}

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = '/backend/api/iso-compliance-api.php';
$path = str_replace($basePath, '', parse_url($requestUri, PHP_URL_PATH));
$pathParts = array_filter(explode('/', trim($path, '/')));

// Initialize service
$service = ISOComplianceService::getInstance();
$service->initialize($auth->getCurrentCompany(), $auth->getUser()['id']);

try {
    // Route request
    switch ($method) {
        case 'GET':
            handleGetRequest($pathParts, $service);
            break;
            
        case 'POST':
            handlePostRequest($pathParts, $service);
            break;
            
        case 'PUT':
            handlePutRequest($pathParts, $service);
            break;
            
        case 'DELETE':
            handleDeleteRequest($pathParts, $service);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
}

/**
 * Handle GET requests
 */
function handleGetRequest(array $pathParts, ISOComplianceService $service): void {
    $resource = $pathParts[0] ?? '';
    
    switch ($resource) {
        case 'standards':
            // GET /standards - Get active standards
            $standards = $service->getActiveStandards();
            echo json_encode(['data' => $standards]);
            break;
            
        case 'folders':
            if (isset($pathParts[1])) {
                // GET /folders/{id} - Get folder details
                $folderId = (int)$pathParts[1];
                $folder = $service->getFolder($folderId);
                echo json_encode(['data' => $folder]);
            } else {
                // GET /folders - Get folder tree
                $parentId = $_GET['parent_id'] ?? null;
                $recursive = filter_var($_GET['recursive'] ?? false, FILTER_VALIDATE_BOOLEAN);
                
                $folders = $service->getFolderTree($parentId, ['recursive' => $recursive]);
                echo json_encode(['data' => $folders]);
            }
            break;
            
        case 'documents':
            if (isset($pathParts[1])) {
                // GET /documents/{id} - Get document details
                $documentId = (int)$pathParts[1];
                
                if (isset($pathParts[2]) && $pathParts[2] === 'download') {
                    // GET /documents/{id}/download - Download document
                    handleDocumentDownload($documentId, $service);
                } else {
                    $document = $service->getDocument($documentId);
                    echo json_encode(['data' => $document]);
                }
            } else {
                // GET /documents - Search documents
                $query = $_GET['q'] ?? '';
                $filters = [
                    'tipo_documento' => $_GET['tipo'] ?? null,
                    'standard_id' => $_GET['standard'] ?? null,
                    'stato' => $_GET['stato'] ?? null,
                    'cartella_id' => $_GET['cartella'] ?? null
                ];
                
                $options = [
                    'limit' => (int)($_GET['limit'] ?? 20),
                    'offset' => (int)($_GET['offset'] ?? 0),
                    'order_by' => $_GET['order_by'] ?? 'relevance',
                    'order_dir' => $_GET['order_dir'] ?? 'DESC'
                ];
                
                $results = $service->searchDocuments($query, array_filter($filters), $options);
                echo json_encode($results);
            }
            break;
            
        case 'config':
            // GET /config - Get company ISO configuration
            $config = $service->getCompanyConfig();
            echo json_encode(['data' => $config]);
            break;
            
        case 'operations':
            if (isset($pathParts[1])) {
                // GET /operations/{id} - Get bulk operation status
                $operationId = (int)$pathParts[1];
                $operation = $service->getBulkOperationStatus($operationId);
                echo json_encode(['data' => $operation]);
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Handle POST requests
 */
function handlePostRequest(array $pathParts, ISOComplianceService $service): void {
    $resource = $pathParts[0] ?? '';
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    
    switch ($resource) {
        case 'standards':
            if (isset($pathParts[1]) && $pathParts[1] === 'activate') {
                // POST /standards/activate - Activate standard for company
                $standardId = $data['standard_id'] ?? 0;
                $result = $service->activateStandard($standardId, $data);
                echo json_encode(['data' => $result]);
            } elseif (isset($pathParts[1]) && $pathParts[1] === 'structure') {
                // POST /standards/structure - Create standard folder structure
                $standardId = $data['standard_id'] ?? 0;
                $options = $data['options'] ?? [];
                $result = $service->createStandardStructure($standardId, $options);
                echo json_encode(['success' => $result]);
            }
            break;
            
        case 'folders':
            // POST /folders - Create folder
            $folderId = $service->createFolder($data);
            echo json_encode([
                'success' => true,
                'data' => ['id' => $folderId]
            ]);
            break;
            
        case 'documents':
            if (isset($pathParts[1]) && $pathParts[1] === 'upload') {
                // POST /documents/upload - Handle file upload
                handleDocumentUpload($service);
            } elseif (isset($pathParts[1]) && $pathParts[1] === 'bulk-download') {
                // POST /documents/bulk-download - Download multiple documents
                $documentIds = $data['document_ids'] ?? [];
                $zipPath = $service->bulkDownloadDocuments($documentIds);
                
                // Return download URL
                $downloadUrl = '/backend/api/iso-compliance-api.php/downloads/' . basename($zipPath);
                echo json_encode([
                    'success' => true,
                    'data' => ['download_url' => $downloadUrl]
                ]);
            }
            break;
            
        case 'permissions':
            // POST /permissions - Grant permission
            $result = $service->grantPermission($data);
            echo json_encode(['success' => true]);
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Handle PUT requests
 */
function handlePutRequest(array $pathParts, ISOComplianceService $service): void {
    $resource = $pathParts[0] ?? '';
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    
    switch ($resource) {
        case 'folders':
            if (isset($pathParts[1])) {
                // PUT /folders/{id} - Update folder
                $folderId = (int)$pathParts[1];
                $result = $service->updateFolder($folderId, $data);
                echo json_encode(['success' => true]);
            }
            break;
            
        case 'documents':
            if (isset($pathParts[1])) {
                // PUT /documents/{id} - Update document
                $documentId = (int)$pathParts[1];
                
                if (isset($pathParts[2])) {
                    switch ($pathParts[2]) {
                        case 'approve':
                            // PUT /documents/{id}/approve - Approve document
                            $result = $service->approveDocument($documentId);
                            echo json_encode(['success' => true]);
                            break;
                            
                        case 'version':
                            // PUT /documents/{id}/version - Create new version
                            handleDocumentVersionUpload($documentId, $service);
                            break;
                    }
                } else {
                    $result = $service->updateDocument($documentId, $data);
                    echo json_encode(['success' => true]);
                }
            }
            break;
            
        case 'config':
            // PUT /config - Update company ISO configuration
            $result = $service->updateCompanyConfig($data);
            echo json_encode(['success' => true]);
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Handle DELETE requests
 */
function handleDeleteRequest(array $pathParts, ISOComplianceService $service): void {
    $resource = $pathParts[0] ?? '';
    
    switch ($resource) {
        case 'folders':
            if (isset($pathParts[1])) {
                // DELETE /folders/{id} - Delete folder
                $folderId = (int)$pathParts[1];
                $result = $service->deleteFolder($folderId);
                echo json_encode(['success' => true]);
            }
            break;
            
        case 'documents':
            if (isset($pathParts[1])) {
                // DELETE /documents/{id} - Delete document
                $documentId = (int)$pathParts[1];
                $result = $service->deleteDocument($documentId);
                echo json_encode(['success' => true]);
            }
            break;
            
        case 'permissions':
            if (isset($pathParts[1])) {
                // DELETE /permissions/{id} - Revoke permission
                $permissionId = (int)$pathParts[1];
                $result = $service->revokePermission($permissionId);
                echo json_encode(['success' => true]);
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Resource not found']);
    }
}

/**
 * Handle document upload
 */
function handleDocumentUpload(ISOComplianceService $service): void {
    if (empty($_FILES)) {
        throw new Exception('Nessun file caricato');
    }
    
    $folderId = (int)($_POST['folder_id'] ?? 0);
    $metadata = $_POST['metadata'] ?? [];
    
    // Handle single or multiple file upload
    $files = [];
    if (isset($_FILES['file'])) {
        // Single file
        if (is_array($_FILES['file']['name'])) {
            // Multiple files with same field name
            for ($i = 0; $i < count($_FILES['file']['name']); $i++) {
                $files[] = [
                    'name' => $_FILES['file']['name'][$i],
                    'type' => $_FILES['file']['type'][$i],
                    'tmp_name' => $_FILES['file']['tmp_name'][$i],
                    'error' => $_FILES['file']['error'][$i],
                    'size' => $_FILES['file']['size'][$i]
                ];
            }
        } else {
            $files[] = $_FILES['file'];
        }
    } else {
        // Multiple files with different field names
        foreach ($_FILES as $file) {
            $files[] = $file;
        }
    }
    
    // Validate files
    foreach ($files as $file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Errore upload file: ' . $file['name']);
        }
    }
    
    // Process upload
    $results = $service->bulkUploadDocuments($folderId, $files, $metadata);
    
    echo json_encode([
        'success' => true,
        'data' => $results
    ]);
}

/**
 * Handle document download
 */
function handleDocumentDownload(int $documentId, ISOComplianceService $service): void {
    $document = $service->getDocument($documentId);
    
    if (!file_exists($document['file_path'])) {
        http_response_code(404);
        echo json_encode(['error' => 'File not found']);
        return;
    }
    
    // Log access
    $service->logDocumentAccess($documentId, 'scaricato');
    
    // Set headers
    header('Content-Type: ' . $document['file_type']);
    header('Content-Disposition: attachment; filename="' . $document['codice'] . '_' . $document['titolo'] . '.' . pathinfo($document['file_path'], PATHINFO_EXTENSION) . '"');
    header('Content-Length: ' . $document['file_size']);
    header('Cache-Control: private, max-age=0');
    
    // Output file
    readfile($document['file_path']);
    exit;
}

/**
 * Handle document version upload
 */
function handleDocumentVersionUpload(int $documentId, ISOComplianceService $service): void {
    if (empty($_FILES['file'])) {
        throw new Exception('Nessun file caricato');
    }
    
    $file = $_FILES['file'];
    $changeNotes = $_POST['change_notes'] ?? '';
    $changeType = $_POST['change_type'] ?? 'minore';
    
    $result = $service->createDocumentVersion($documentId, $file, $changeNotes, $changeType);
    
    echo json_encode([
        'success' => true,
        'data' => $result
    ]);
}