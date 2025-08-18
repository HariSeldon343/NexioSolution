<?php
/**
 * OnlyOffice Document Server Callback Handler
 * Secure callback handler with JWT authentication and versioning support
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/onlyoffice.config.php';
require_once __DIR__ . '/../middleware/Auth.php';
require_once __DIR__ . '/../models/DocumentVersion.php';
require_once __DIR__ . '/../utils/ActivityLogger.php';
require_once __DIR__ . '/../utils/CSRFTokenManager.php';

// Apply security headers
applyOnlyOfficeSecurityHeaders();

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Set JSON response headers
header('Content-Type: application/json; charset=utf-8');

// Check rate limiting
if (!checkOnlyOfficeRateLimit()) {
    logOnlyOfficeEvent('warning', 'Rate limit exceeded', [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    http_response_code(429);
    echo json_encode(['error' => 1, 'message' => 'Rate limit exceeded']);
    exit;
}

// Validate callback IP
if (!validateOnlyOfficeCallbackIP()) {
    logOnlyOfficeEvent('security', 'Invalid callback IP', [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    http_response_code(403);
    echo json_encode(['error' => 1, 'message' => 'Access denied']);
    exit;
}

try {
    // Read request payload
    $input = file_get_contents('php://input');
    
    logOnlyOfficeEvent('debug', 'Callback received', [
        'method' => $_SERVER['REQUEST_METHOD'],
        'headers' => getallheaders()
    ]);
    
    if (empty($input)) {
        // Empty request - normal for some callback types
        echo json_encode(['error' => 0]);
        exit;
    }
    
    // Decode JSON payload
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Invalid JSON payload');
    }
    
    // Verify JWT token if enabled
    if ($ONLYOFFICE_JWT_ENABLED) {
        $token = extractJWTFromRequest();
        
        if (empty($token) && isset($data['token'])) {
            $token = $data['token'];
        }
        
        if (empty($token)) {
            throw new Exception('JWT token required but not provided');
        }
        
        $jwtResult = verifyOnlyOfficeJWT($token);
        
        if (!$jwtResult['valid']) {
            logOnlyOfficeEvent('security', 'Invalid JWT token', [
                'error' => $jwtResult['error']
            ]);
            throw new Exception('Invalid JWT token: ' . $jwtResult['error']);
        }
        
        // Merge JWT payload with callback data
        if (!empty($jwtResult['payload'])) {
            $data = array_merge($data, $jwtResult['payload']);
        }
    }
    
    // Extract and validate callback information
    $status = isset($data['status']) ? intval($data['status']) : 0;
    $url = isset($data['url']) ? filter_var($data['url'], FILTER_SANITIZE_URL) : '';
    $changesurl = isset($data['changesurl']) ? filter_var($data['changesurl'], FILTER_SANITIZE_URL) : '';
    $history = $data['history'] ?? null;
    $users = isset($data['users']) && is_array($data['users']) ? $data['users'] : [];
    $actions = isset($data['actions']) && is_array($data['actions']) ? $data['actions'] : [];
    $key = isset($data['key']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $data['key']) : '';
    $userdata = $data['userdata'] ?? '';
    $forcesavetype = isset($data['forcesavetype']) ? intval($data['forcesavetype']) : 0;
    
    // Parse userdata if it's a JSON string
    if (is_string($userdata) && !empty($userdata)) {
        $userdata = json_decode($userdata, true) ?: [];
    }
    
    logOnlyOfficeEvent('info', 'Callback processed', [
        'status' => $status,
        'key' => $key,
        'users' => $users,
        'forcesavetype' => $forcesavetype
    ]);
    
    // Handle different callback statuses
    switch ($status) {
        case 0:
            // No document - no action needed
            handleNoDocument($key);
            break;
            
        case 1:
            // Document being edited
            handleEditingDocument($key, $users, $actions);
            break;
            
        case 2:
            // Document ready for saving
            if ($url) {
                saveDocumentFromCallback($key, $url, $users, $userdata, $history, $changesurl, $forcesavetype);
            }
            break;
            
        case 3:
            // Document save error
            handleSaveError($key, 'Document save error occurred');
            break;
            
        case 4:
            // Document closed without changes
            handleDocumentClosed($key, $users);
            break;
            
        case 6:
            // Document being edited, but force save initiated
            if ($url) {
                saveDocumentFromCallback($key, $url, $users, $userdata, $history, $changesurl, $forcesavetype);
            }
            break;
            
        case 7:
            // Force save error
            handleSaveError($key, 'Force save error occurred');
            break;
            
        default:
            throw new Exception("Unknown callback status: $status");
    }
    
    // Success response
    echo json_encode(['error' => 0]);
    
} catch (Exception $e) {
    logOnlyOfficeEvent('error', 'Callback error', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    // Error response
    http_response_code(500);
    echo json_encode([
        'error' => 1,
        'message' => $e->getMessage()
    ]);
}

/**
 * Handle no document status
 */
function handleNoDocument($key) {
    logOnlyOfficeEvent('debug', 'No document', ['key' => $key]);
}

/**
 * Handle document being edited
 */
function handleEditingDocument($key, $users, $actions = []) {
    logOnlyOfficeEvent('debug', 'Document being edited', [
        'key' => $key,
        'users' => $users,
        'actions' => $actions
    ]);
    
    try {
        // Update document access and editing status
        updateDocumentAccess($key, $users);
        
        // Track active editors
        if (!empty($users)) {
            updateActiveEditors($key, $users);
        }
        
        // Log collaborative actions if any
        if (!empty($actions)) {
            foreach ($actions as $action) {
                logCollaborativeAction($key, $action);
            }
        }
    } catch (Exception $e) {
        logOnlyOfficeEvent('warning', 'Error updating document status', [
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Save document from OnlyOffice callback with versioning
 */
function saveDocumentFromCallback($key, $url, $users = [], $userdata = [], $history = null, $changesurl = '', $forcesavetype = 0) {
    global $ONLYOFFICE_DOCUMENTS_DIR, $ONLYOFFICE_TIMEOUT;
    
    try {
        // Extract and validate document ID from key
        $parts = explode('_', $key);
        $documentId = isset($parts[0]) ? intval($parts[0]) : 0;
        
        if ($documentId <= 0) {
            throw new Exception('Invalid document key format');
        }
        
        // SECURITY: Verify document exists and get tenant info
        $stmt = db_query(
            "SELECT id, azienda_id FROM documenti WHERE id = ?",
            [$documentId]
        );
        $docInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$docInfo) {
            throw new Exception('Document not found');
        }
        
        // SECURITY: Multi-tenant check - validate company context from userdata
        if (isset($userdata['azienda_id']) && $docInfo['azienda_id'] !== null) {
            if ($userdata['azienda_id'] != $docInfo['azienda_id']) {
                throw new Exception('Multi-tenant security violation: document company mismatch');
            }
        }
        
        // Download document from OnlyOffice
        $context = stream_context_create([
            'http' => [
                'timeout' => $ONLYOFFICE_TIMEOUT,
                'method' => 'GET'
            ]
        ]);
        
        $documentContent = @file_get_contents($url, false, $context);
        
        if ($documentContent === false) {
            throw new Exception('Failed to download document from OnlyOffice');
        }
        
        // Determine file path and extension
        $fileInfo = getDocumentFileInfo($documentId);
        $extension = $fileInfo['extension'] ?? 'docx';
        $timestamp = time();
        $fileName = "{$documentId}_{$timestamp}.{$extension}";
        $filePath = $ONLYOFFICE_DOCUMENTS_DIR . '/' . $fileName;
        
        // Save file
        $bytesWritten = file_put_contents($filePath, $documentContent);
        
        if ($bytesWritten === false) {
            throw new Exception('Failed to save document');
        }
        
        // Download changes history if available
        $changesData = null;
        if (!empty($changesurl)) {
            $changesContent = @file_get_contents($changesurl, false, $context);
            if ($changesContent !== false) {
                $changesData = json_decode($changesContent, true);
            }
        }
        
        // Create document version
        $versionData = createDocumentVersion(
            $documentId,
            $filePath,
            $bytesWritten,
            $users,
            $userdata,
            $history,
            $changesData,
            $forcesavetype
        );
        
        // Update main document record - include azienda_id for multi-tenant context
        updateDocumentInDatabase($documentId, $filePath, $bytesWritten, $versionData, $docInfo['azienda_id']);
        
        // Log activity with tenant info
        logDocumentActivity($documentId, 'document_saved', [
            'version' => $versionData['version_number'] ?? null,
            'size' => $bytesWritten,
            'users' => $users,
            'forcesave' => $forcesavetype,
            'azienda_id' => $docInfo['azienda_id']
        ]);
        
        logOnlyOfficeEvent('info', 'Document saved successfully', [
            'document_id' => $documentId,
            'file' => $fileName,
            'size' => $bytesWritten,
            'version' => $versionData['version_number'] ?? null
        ]);
        
    } catch (Exception $e) {
        logOnlyOfficeEvent('error', 'Save error', [
            'key' => $key,
            'error' => $e->getMessage()
        ]);
        throw $e;
    }
}

/**
 * Handle document save error
 */
function handleSaveError($key, $errorMessage = 'Save error occurred') {
    logOnlyOfficeEvent('error', 'Document save error', [
        'key' => $key,
        'message' => $errorMessage
    ]);
    
    try {
        $parts = explode('_', $key);
        $documentId = $parts[0];
        
        if (is_numeric($documentId)) {
            // Update document error status
            updateDocumentError($documentId, $errorMessage);
            
            // Log activity
            logDocumentActivity($documentId, 'save_error', [
                'error' => $errorMessage
            ]);
        }
    } catch (Exception $e) {
        logOnlyOfficeEvent('warning', 'Failed to update error status', [
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Handle document closed without changes
 */
function handleDocumentClosed($key, $users = []) {
    logOnlyOfficeEvent('debug', 'Document closed without changes', [
        'key' => $key,
        'users' => $users
    ]);
    
    try {
        $parts = explode('_', $key);
        $documentId = $parts[0];
        
        if (is_numeric($documentId)) {
            // Update document closed status
            updateDocumentClosed($documentId);
            
            // Clear active editors
            clearActiveEditors($documentId);
            
            // Log activity
            logDocumentActivity($documentId, 'document_closed', [
                'users' => $users
            ]);
        }
    } catch (Exception $e) {
        logOnlyOfficeEvent('warning', 'Error updating closed status', [
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Update document access timestamp
 */
function updateDocumentAccess($key, $users) {
    $parts = explode('_', $key);
    $documentId = $parts[0];
    
    if (!is_numeric($documentId)) {
        return;
    }
    
    try {
        // Update last access time
        $stmt = db_query(
            "UPDATE documenti SET ultimo_accesso = NOW() WHERE id = ?",
            [$documentId]
        );
        
        // Update editing status
        if (!empty($users)) {
            $editingUsers = json_encode($users);
            $stmt = db_query(
                "UPDATE documenti SET 
                 is_editing = 1,
                 editing_users = ?,
                 editing_started_at = IFNULL(editing_started_at, NOW())
                 WHERE id = ?",
                [$editingUsers, $documentId]
            );
        }
    } catch (Exception $e) {
        logOnlyOfficeEvent('warning', 'Access update error', [
            'document_id' => $documentId,
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Update active editors for a document
 */
function updateActiveEditors($key, $users) {
    $parts = explode('_', $key);
    $documentId = $parts[0];
    
    if (!is_numeric($documentId)) {
        return;
    }
    
    try {
        // Clear existing active editors
        db_query("DELETE FROM document_active_editors WHERE document_id = ?", [$documentId]);
        
        // Add current active editors
        foreach ($users as $user) {
            $userId = $user['id'] ?? null;
            $userName = $user['name'] ?? 'Unknown';
            
            if ($userId) {
                db_query(
                    "INSERT INTO document_active_editors (document_id, user_id, user_name, started_at)
                     VALUES (?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE started_at = VALUES(started_at)",
                    [$documentId, $userId, $userName]
                );
            }
        }
    } catch (Exception $e) {
        // Table might not exist yet, log but don't fail
        logOnlyOfficeEvent('debug', 'Active editors update skipped', [
            'reason' => $e->getMessage()
        ]);
    }
}

/**
 * Clear active editors for a document
 */
function clearActiveEditors($documentId) {
    try {
        db_query("DELETE FROM document_active_editors WHERE document_id = ?", [$documentId]);
        db_query(
            "UPDATE documenti SET 
             is_editing = 0,
             editing_users = NULL,
             editing_started_at = NULL
             WHERE id = ?",
            [$documentId]
        );
    } catch (Exception $e) {
        logOnlyOfficeEvent('debug', 'Clear editors skipped', [
            'reason' => $e->getMessage()
        ]);
    }
}

/**
 * Log collaborative action
 */
function logCollaborativeAction($key, $action) {
    $parts = explode('_', $key);
    $documentId = $parts[0];
    
    if (!is_numeric($documentId)) {
        return;
    }
    
    try {
        $type = $action['type'] ?? 'unknown';
        $userId = $action['userid'] ?? null;
        $data = json_encode($action);
        
        db_query(
            "INSERT INTO document_collaborative_actions 
             (document_id, action_type, user_id, action_data, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$documentId, $type, $userId, $data]
        );
    } catch (Exception $e) {
        // Table might not exist, log but don't fail
        logOnlyOfficeEvent('debug', 'Collaborative action logging skipped', [
            'reason' => $e->getMessage()
        ]);
    }
}

/**
 * Get document file information
 */
function getDocumentFileInfo($documentId) {
    try {
        // SECURITY: Validate document ID is numeric to prevent injection
        if (!is_numeric($documentId) || $documentId <= 0) {
            throw new Exception('Invalid document ID format');
        }
        
        $stmt = db_query(
            "SELECT nome_file, tipo_documento, mime_type, azienda_id FROM documenti WHERE id = ?",
            [$documentId]
        );
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($doc) {
            $fileName = $doc['nome_file'] ?? '';
            $extension = pathinfo($fileName, PATHINFO_EXTENSION) ?: 'docx';
            
            return [
                'filename' => $fileName,
                'extension' => $extension,
                'mime_type' => $doc['mime_type'] ?? '',
                'type' => $doc['tipo_documento'] ?? ''
            ];
        }
    } catch (Exception $e) {
        logOnlyOfficeEvent('warning', 'Failed to get document info', [
            'document_id' => $documentId,
            'error' => $e->getMessage()
        ]);
    }
    
    return ['extension' => 'docx'];
}

/**
 * Create document version with integration to DocumentVersion model
 */
function createDocumentVersion($documentId, $filePath, $fileSize, $users = [], $userdata = [], $history = null, $changesData = null, $forcesavetype = 0) {
    try {
        // Get user information
        $userId = null;
        $userName = 'OnlyOffice User';
        
        if (!empty($users)) {
            $firstUser = reset($users);
            $userId = $firstUser['id'] ?? null;
            $userName = $firstUser['name'] ?? $userName;
        }
        
        // Determine if this is a major version
        $isMajor = ($forcesavetype === 1); // Manual save
        $notes = '';
        
        if ($forcesavetype === 1) {
            $notes = 'Manual save by user';
        } elseif ($forcesavetype === 2) {
            $notes = 'Force save on timer';
        } elseif ($forcesavetype === 3) {
            $notes = 'Force save on exit';
        }
        
        // Use DocumentVersion model if available
        if (class_exists('DocumentVersion')) {
            $versionModel = new DocumentVersion();
            
            // Read file content for HTML documents
            $contentHtml = '';
            if (in_array(pathinfo($filePath, PATHINFO_EXTENSION), ['html', 'htm'])) {
                $contentHtml = file_get_contents($filePath);
            }
            
            $version = $versionModel->addVersion(
                $documentId,
                $contentHtml,
                $filePath,
                $userId,
                $userName,
                $isMajor,
                $notes
            );
            
            return $version;
        } else {
            // Fallback to direct database insert
            $stmt = db_query(
                "SELECT MAX(version_number) as max_version FROM documenti_versioni_extended WHERE documento_id = ?",
                [$documentId]
            );
            $result = $stmt->fetch();
            $versionNumber = ($result['max_version'] ?? 0) + 1;
            
            $stmt = db_query(
                "INSERT INTO documenti_versioni_extended 
                 (documento_id, version_number, file_path, file_size, created_by_id, created_by_name, 
                  created_at, is_major, notes, changes_data, is_current)
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, 1)",
                [
                    $documentId,
                    $versionNumber,
                    $filePath,
                    $fileSize,
                    $userId,
                    $userName,
                    $isMajor ? 1 : 0,
                    $notes,
                    json_encode($changesData)
                ]
            );
            
            // Mark previous versions as not current
            db_query(
                "UPDATE documenti_versioni_extended 
                 SET is_current = 0 
                 WHERE documento_id = ? AND version_number < ?",
                [$documentId, $versionNumber]
            );
            
            return ['version_number' => $versionNumber];
        }
    } catch (Exception $e) {
        logOnlyOfficeEvent('warning', 'Version creation failed', [
            'document_id' => $documentId,
            'error' => $e->getMessage()
        ]);
        return [];
    }
}

/**
 * Update document error status
 */
function updateDocumentError($documentId, $errorMessage) {
    try {
        $stmt = db_query(
            "UPDATE documenti SET 
             last_error = ?,
             last_error_at = NOW()
             WHERE id = ?",
            [$errorMessage, $documentId]
        );
        
        // Log error in activity log
        if (class_exists('ActivityLogger')) {
            ActivityLogger::log('document_error', [
                'document_id' => $documentId,
                'error' => $errorMessage
            ]);
        }
    } catch (Exception $e) {
        logOnlyOfficeEvent('warning', 'Error status update failed', [
            'document_id' => $documentId,
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Update document closed status
 */
function updateDocumentClosed($documentId) {
    try {
        $stmt = db_query(
            "UPDATE documenti SET 
             ultimo_accesso = NOW(),
             is_editing = 0,
             editing_users = NULL,
             editing_started_at = NULL
             WHERE id = ?",
            [$documentId]
        );
    } catch (Exception $e) {
        logOnlyOfficeEvent('warning', 'Close status update failed', [
            'document_id' => $documentId,
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Update document in database with version information
 */
function updateDocumentInDatabase($documentId, $filePath, $fileSize, $versionData = [], $aziendaId = null) {
    try {
        // Update main document record
        $stmt = db_query(
            "UPDATE documenti SET 
             aggiornato_il = NOW(),
             file_path = ?,
             dimensione_file = ?,
             current_version = ?,
             total_versions = (SELECT COUNT(*) FROM documenti_versioni_extended WHERE documento_id = ?)
             WHERE id = ?",
            [
                $filePath,
                $fileSize,
                $versionData['version_number'] ?? null,
                $documentId,
                $documentId
            ]
        );
        
        logOnlyOfficeEvent('info', 'Database updated', [
            'document_id' => $documentId,
            'file_path' => $filePath,
            'version' => $versionData['version_number'] ?? null
        ]);
    } catch (Exception $e) {
        logOnlyOfficeEvent('error', 'Database update failed', [
            'document_id' => $documentId,
            'error' => $e->getMessage()
        ]);
        throw $e;
    }
}

/**
 * Log document activity
 */
function logDocumentActivity($documentId, $action, $details = []) {
    try {
        if (class_exists('ActivityLogger')) {
            ActivityLogger::log($action, array_merge([
                'document_id' => $documentId,
                'source' => 'onlyoffice_callback'
            ], $details));
        }
        
        // Also log to document activity table
        $detailsJson = json_encode($details);
        db_query(
            "INSERT INTO document_activity_log 
             (document_id, action, details, created_at)
             VALUES (?, ?, ?, NOW())",
            [$documentId, $action, $detailsJson]
        );
    } catch (Exception $e) {
        // Activity logging failure should not break the callback
        logOnlyOfficeEvent('debug', 'Activity logging skipped', [
            'reason' => $e->getMessage()
        ]);
    }
}
?>