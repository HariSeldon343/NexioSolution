<?php
/**
 * PermissionMiddleware - Middleware per validazione permessi API
 * 
 * Middleware che intercetta tutte le chiamate API e verifica i permessi
 * prima di permettere l'esecuzione delle operazioni richieste
 * 
 * @author Nexio Platform
 * @version 1.0.0
 */

require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/../utils/PermissionManager.php';
require_once __DIR__ . '/../utils/ActivityLogger.php';
require_once __DIR__ . '/../utils/RateLimiter.php';

class PermissionMiddleware {
    private static $instance = null;
    private $permissionManager;
    private $auth;
    private $activityLogger;
    
    // Mapping endpoint -> permessi richiesti
    const ENDPOINT_PERMISSIONS = [
        // API Documenti
        'documents/list.php' => ['document_view'],
        'documents/create.php' => ['document_upload'],
        'documents/update.php' => ['document_edit'],
        'documents/delete.php' => ['document_delete'],
        'documents/download.php' => ['document_download'],
        'documents/approve.php' => ['document_approve'],
        'documents/share.php' => ['document_share'],
        
        // API Cartelle
        'folders/list.php' => ['folder_view'],
        'folders/create.php' => ['folder_create'],
        'folders/update.php' => ['folder_edit'],
        'folders/delete.php' => ['folder_delete'],
        'folders/permissions.php' => ['folder_manage_permissions'],
        
        // API ISO
        'iso-compliance-api.php' => ['iso_manage_compliance'],
        'iso-structure-api.php' => ['iso_configure'],
        'iso-setup-api.php' => ['iso_structure_admin'],
        'iso-documents-api.php' => ['iso_audit_access'],
        
        // API Aziende
        'switch-azienda.php' => ['company_switch'],
        'company/manage.php' => ['company_manage'],
        'company/users.php' => ['company_users'],
        
        // API Sistema
        'system/logs.php' => ['system_logs'],
        'system/config.php' => ['system_config'],
        
        // API Calendar
        'calendar-api.php' => ['document_view'], // Eventi legati a documenti
        'calendar-events.php' => ['document_view'],
        
        // API Template
        'template-api.php' => ['document_edit'],
        'template-elements-api.php' => ['document_edit'],
        
        // API Upload/Download
        'upload-file.php' => ['document_upload'],
        'download-file.php' => ['document_download'],
        'download-export.php' => ['document_view']
    ];
    
    // Rate limits per endpoint sensibili (richieste per ora)
    const RATE_LIMITS = [
        'documents/delete.php' => 20,
        'folders/delete.php' => 10,
        'iso-structure-api.php' => 50,
        'system/config.php' => 10,
        'upload-file.php' => 100,
        'documents/create.php' => 50
    ];
    
    private function __construct() {
        $this->permissionManager = PermissionManager::getInstance();
        $this->auth = Auth::getInstance();
        $this->activityLogger = ActivityLogger::getInstance();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Valida permessi per endpoint corrente
     */
    public function validateRequest($endpoint = null, $resourceId = null, $additionalContext = []) {
        try {
            // Determina endpoint se non fornito
            if (!$endpoint) {
                $endpoint = $this->getCurrentEndpoint();
            }
            
            // Verifica autenticazione base
            if (!$this->auth->isAuthenticated()) {
                $this->respondWithError(401, 'Non autenticato');
                return false;
            }
            
            $user = $this->auth->getUser();
            $userId = $user['id'];
            $userRole = $user['ruolo'];
            
            // Super admin bypass (con logging)
            if ($userRole === 'super_admin') {
                $this->logAccess($endpoint, $userId, 'super_admin_access', $resourceId);
                return true;
            }
            
            // Verifica rate limiting
            if (!$this->checkRateLimit($endpoint, $userId)) {
                $this->respondWithError(429, 'Troppi tentativi, riprova più tardi');
                return false;
            }
            
            // Verifica IP whitelisting per operazioni sensibili
            if (!$this->checkIPRestrictions($endpoint)) {
                $this->activityLogger->logSecurity('ip_restriction_violation', [
                    'endpoint' => $endpoint,
                    'user_id' => $userId,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                $this->respondWithError(403, 'Accesso negato da questo indirizzo IP');
                return false;
            }
            
            // Ottieni permessi richiesti per l'endpoint
            $requiredPermissions = $this->getRequiredPermissions($endpoint);
            
            if (empty($requiredPermissions)) {
                // Endpoint senza restrizioni specifiche
                $this->logAccess($endpoint, $userId, 'unrestricted_access', $resourceId);
                return true;
            }
            
            // Verifica permessi specifici
            $hasAccess = false;
            
            foreach ($requiredPermissions as $permission) {
                if ($this->hasPermissionForEndpoint($permission, $endpoint, $resourceId, $additionalContext)) {
                    $hasAccess = true;
                    break;
                }
            }
            
            if (!$hasAccess) {
                $this->activityLogger->logSecurity('permission_denied', [
                    'endpoint' => $endpoint,
                    'user_id' => $userId,
                    'required_permissions' => $requiredPermissions,
                    'resource_id' => $resourceId
                ]);
                $this->respondWithError(403, 'Permessi insufficienti');
                return false;
            }
            
            // Log accesso autorizzato
            $this->logAccess($endpoint, $userId, 'authorized_access', $resourceId);
            return true;
            
        } catch (Exception $e) {
            $this->activityLogger->logError('permission_middleware_error', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->respondWithError(500, 'Errore interno del server');
            return false;
        }
    }
    
    /**
     * Middleware rapido per documenti
     */
    public function validateDocumentAccess($documentId, $action = 'view') {
        if (!$this->auth->isAuthenticated()) {
            $this->respondWithError(401, 'Non autenticato');
            return false;
        }
        
        $user = $this->auth->getUser();
        
        if (!$this->permissionManager->checkDocumentAccess($documentId, $action, $user['id'])) {
            $this->respondWithError(403, 'Accesso al documento negato');
            return false;
        }
        
        return true;
    }
    
    /**
     * Middleware rapido per cartelle
     */
    public function validateFolderAccess($folderId, $action = 'view') {
        if (!$this->auth->isAuthenticated()) {
            $this->respondWithError(401, 'Non autenticato');
            return false;
        }
        
        $user = $this->auth->getUser();
        
        if (!$this->permissionManager->checkFolderAccess($folderId, $action, $user['id'])) {
            $this->respondWithError(403, 'Accesso alla cartella negato');
            return false;
        }
        
        return true;
    }
    
    /**
     * Middleware per azioni ISO
     */
    public function validateISOAction($action, $context = []) {
        if (!$this->auth->isAuthenticated()) {
            $this->respondWithError(401, 'Non autenticato');
            return false;
        }
        
        $user = $this->auth->getUser();
        
        if (!$this->permissionManager->canPerformAction($action, $context, $user['id'])) {
            $this->respondWithError(403, 'Privilegi ISO insufficienti');
            return false;
        }
        
        return true;
    }
    
    /**
     * Determina endpoint corrente
     */
    private function getCurrentEndpoint() {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        
        // Estrai il nome del file dall'URL
        $endpoint = basename($script);
        
        // Se siamo in una cartella API, includi il path relativo
        if (strpos($script, '/api/') !== false) {
            $parts = explode('/api/', $script);
            if (count($parts) > 1) {
                $endpoint = $parts[1];
            }
        }
        
        return $endpoint;
    }
    
    /**
     * Ottieni permessi richiesti per endpoint
     */
    private function getRequiredPermissions($endpoint) {
        return self::ENDPOINT_PERMISSIONS[$endpoint] ?? [];
    }
    
    /**
     * Verifica se ha permesso per endpoint specifico
     */
    private function hasPermissionForEndpoint($permission, $endpoint, $resourceId, $context) {
        $user = $this->auth->getUser();
        $userId = $user['id'];
        
        // Per permessi di documento/cartella, usa i metodi specifici
        if (strpos($permission, 'document_') === 0 && $resourceId) {
            $action = str_replace('document_', '', $permission);
            return $this->permissionManager->checkDocumentAccess($resourceId, $action, $userId);
        }
        
        if (strpos($permission, 'folder_') === 0 && $resourceId) {
            $action = str_replace('folder_', '', $permission);
            return $this->permissionManager->checkFolderAccess($resourceId, $action, $userId);
        }
        
        // Per altri permessi, usa canPerformAction
        return $this->permissionManager->canPerformAction($permission, $context, $userId);
    }
    
    /**
     * Verifica rate limiting
     */
    private function checkRateLimit($endpoint, $userId) {
        if (!isset(self::RATE_LIMITS[$endpoint])) {
            return true; // Nessun limite per questo endpoint
        }
        
        $limit = self::RATE_LIMITS[$endpoint];
        $identifier = "api_endpoint_{$endpoint}_{$userId}";
        
        return RateLimiter::check($identifier, 'api_call', $limit, 3600); // Limite per ora
    }
    
    /**
     * Verifica restrizioni IP
     */
    private function checkIPRestrictions($endpoint) {
        $sensitiveEndpoints = [
            'system/config.php',
            'iso-structure-api.php',
            'folders/delete.php',
            'documents/delete.php'
        ];
        
        if (!in_array($endpoint, $sensitiveEndpoints)) {
            return true;
        }
        
        return $this->permissionManager->checkIPWhitelist('system_admin');
    }
    
    /**
     * Log accesso
     */
    private function logAccess($endpoint, $userId, $accessType, $resourceId = null) {
        $this->activityLogger->log('api_access', 'endpoint', null, [
            'endpoint' => $endpoint,
            'access_type' => $accessType,
            'user_id' => $userId,
            'resource_id' => $resourceId,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    }
    
    /**
     * Risposta di errore standardizzata
     */
    private function respondWithError($statusCode, $message) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code($statusCode);
        }
        
        echo json_encode([
            'success' => false,
            'error' => $message,
            'error_code' => $statusCode,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    
    /**
     * Funzione helper per validazione veloce
     */
    public static function requirePermission($permission, $resourceId = null, $context = []) {
        $middleware = self::getInstance();
        $user = Auth::getInstance()->getUser();
        
        if (!$user) {
            $middleware->respondWithError(401, 'Non autenticato');
            return false;
        }
        
        $permissionManager = PermissionManager::getInstance();
        
        if (strpos($permission, 'document_') === 0 && $resourceId) {
            $action = str_replace('document_', '', $permission);
            if (!$permissionManager->checkDocumentAccess($resourceId, $action, $user['id'])) {
                $middleware->respondWithError(403, 'Accesso al documento negato');
                return false;
            }
        } elseif (strpos($permission, 'folder_') === 0 && $resourceId) {
            $action = str_replace('folder_', '', $permission);
            if (!$permissionManager->checkFolderAccess($resourceId, $action, $user['id'])) {
                $middleware->respondWithError(403, 'Accesso alla cartella negato');
                return false;
            }
        } else {
            if (!$permissionManager->canPerformAction($permission, $context, $user['id'])) {
                $middleware->respondWithError(403, 'Permessi insufficienti');
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Middleware per validazione CSRF token
     */
    public function validateCSRF() {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        
        if (!$token || !isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
            $this->activityLogger->logSecurity('csrf_token_invalid', [
                'user_id' => $this->auth->getUser()['id'] ?? null,
                'provided_token' => $token ? substr($token, 0, 8) . '...' : 'none'
            ]);
            $this->respondWithError(403, 'Token CSRF non valido');
            return false;
        }
        
        return true;
    }
    
    /**
     * Genera e restituisce CSRF token
     */
    public static function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Header CSRF per response JSON
     */
    public static function addCSRFHeader() {
        if (!headers_sent()) {
            header('X-CSRF-Token: ' . self::generateCSRFToken());
        }
    }
}

/**
 * Funzioni helper globali per uso rapido
 */

/**
 * Valida accesso API con permessi
 */
function validateAPIAccess($permission = null, $resourceId = null, $context = []) {
    $middleware = PermissionMiddleware::getInstance();
    
    if ($permission) {
        return PermissionMiddleware::requirePermission($permission, $resourceId, $context);
    }
    
    return $middleware->validateRequest();
}

/**
 * Valida accesso documento
 */
function validateDocumentAPI($documentId, $action = 'view') {
    $middleware = PermissionMiddleware::getInstance();
    return $middleware->validateDocumentAccess($documentId, $action);
}

/**
 * Valida accesso cartella
 */
function validateFolderAPI($folderId, $action = 'view') {
    $middleware = PermissionMiddleware::getInstance();
    return $middleware->validateFolderAccess($folderId, $action);
}

/**
 * Valida azione ISO
 */
function validateISOAPI($action, $context = []) {
    $middleware = PermissionMiddleware::getInstance();
    return $middleware->validateISOAction($action, $context);
}

/**
 * Genera token CSRF
 */
function csrf_token() {
    return PermissionMiddleware::generateCSRFToken();
}

/**
 * Campo hidden CSRF per form
 */
function csrf_field() {
    $token = csrf_token();
    return "<input type=\"hidden\" name=\"csrf_token\" value=\"{$token}\">";
}