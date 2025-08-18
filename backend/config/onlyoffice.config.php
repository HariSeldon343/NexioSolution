<?php
/**
 * OnlyOffice Document Server Configuration
 * Production-ready configuration with JWT authentication and security hardening
 */

// ================================================================
// ENVIRONMENT DETECTION
// ================================================================

$isProduction = (getenv('APP_ENV') === 'production') || 
                (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] !== 'localhost');
$isDocker = file_exists('/.dockerenv');
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') 
            ? 'https' : 'http';

// ================================================================
// ONLYOFFICE SERVER CONFIGURATION
// ================================================================

// Server URLs - configurable via environment variables
// CONFIGURAZIONE CORRETTA PER DOCKER LOCALE
$ONLYOFFICE_DS_PUBLIC_URL = getenv('ONLYOFFICE_DS_PUBLIC_URL') ?: 'http://localhost:8082';

$ONLYOFFICE_DS_INTERNAL_URL = getenv('ONLYOFFICE_DS_INTERNAL_URL') ?: 
    ($isDocker ? 'http://onlyoffice-documentserver' : $ONLYOFFICE_DS_PUBLIC_URL);

// Legacy variable for backward compatibility
$ONLYOFFICE_SERVER = $ONLYOFFICE_DS_PUBLIC_URL;

// Request timeout (seconds)
$ONLYOFFICE_TIMEOUT = intval(getenv('ONLYOFFICE_TIMEOUT') ?: 30);

// Maximum file size (bytes) - 100MB default
$ONLYOFFICE_MAX_FILE_SIZE = intval(getenv('ONLYOFFICE_MAX_FILE_SIZE') ?: 100 * 1024 * 1024);

// Supported formats
$ONLYOFFICE_SUPPORTED_FORMATS = [
    'docx', 'doc', 'odt', 'rtf', 'txt', 'html', 'htm', 'mht', 'pdf', 'djvu', 'fb2', 'epub', 'xps',
    'xlsx', 'xls', 'ods', 'csv', 'tsv',
    'pptx', 'ppt', 'odp', 'ppsx', 'pps'
];

// Documents directory
$ONLYOFFICE_DOCUMENTS_DIR = getenv('ONLYOFFICE_DOCUMENTS_DIR') ?: 
    realpath(__DIR__ . '/../../documents/onlyoffice');

// ================================================================
// JWT SECURITY CONFIGURATION
// ================================================================

// Enable JWT authentication (MUST be true in production)
// ABILITATO COME RICHIESTO
$ONLYOFFICE_JWT_ENABLED = filter_var(
    getenv('ONLYOFFICE_JWT_ENABLED') ?: 'true', 
    FILTER_VALIDATE_BOOLEAN
);  // Forzato a TRUE per sicurezza

// JWT Secret Key - CRITICAL: Set via environment variable in production
// IMPORTANTE: Usare la STESSA chiave configurata nel Docker di OnlyOffice
// Per generare una nuova chiave: openssl rand -hex 32
$ONLYOFFICE_JWT_SECRET = getenv('ONLYOFFICE_JWT_SECRET') ?: 
    'nexio-secret-key-2025-onlyoffice-jwt-secure-token';  // CAMBIARE con la chiave usata in Docker!

// JWT Algorithm
$ONLYOFFICE_JWT_ALGORITHM = getenv('ONLYOFFICE_JWT_ALGORITHM') ?: 'HS256';

// JWT Header name - CONFIGURATO COME RICHIESTO
$ONLYOFFICE_JWT_HEADER = getenv('ONLYOFFICE_JWT_HEADER') ?: 'Authorization';

// ================================================================
// CALLBACK URL CONFIGURATION
// ================================================================

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = rtrim(dirname(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))), '/');

// Handle Docker environment callback URL
// IMPORTANTE: Usa host.docker.internal per permettere al container di raggiungere l'host
if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
    // In ambiente locale con Docker, usa host.docker.internal
    $callbackHost = 'host.docker.internal';
} else {
    // In produzione usa l'host normale
    $callbackHost = $host;
}

// Costruisci l'URL di callback completo
$ONLYOFFICE_CALLBACK_URL = getenv('ONLYOFFICE_CALLBACK_URL') ?: 
    'http://' . $callbackHost . '/piattaforma-collaborativa/backend/api/onlyoffice-callback.php';

// ================================================================
// SECURITY CONFIGURATION
// ================================================================

// Rate limiting for callbacks (requests per minute)
$ONLYOFFICE_RATE_LIMIT = intval(getenv('ONLYOFFICE_RATE_LIMIT') ?: 60);

// Allowed callback IPs (empty array = allow all)
$ONLYOFFICE_ALLOWED_IPS = array_filter(
    explode(',', getenv('ONLYOFFICE_ALLOWED_IPS') ?: '')
);

// CORS configuration
$ONLYOFFICE_CORS_ORIGINS = array_filter(
    explode(',', getenv('ONLYOFFICE_CORS_ORIGINS') ?: '*')
);

// Security headers
$ONLYOFFICE_SECURITY_HEADERS = [
    'X-Content-Type-Options' => 'nosniff',
    'X-Frame-Options' => 'SAMEORIGIN',
    'X-XSS-Protection' => '1; mode=block',
    'Referrer-Policy' => 'strict-origin-when-cross-origin'
];

// Session timeout for editing (seconds)
$ONLYOFFICE_SESSION_TIMEOUT = intval(getenv('ONLYOFFICE_SESSION_TIMEOUT') ?: 3600);

// Enable detailed logging
$ONLYOFFICE_DEBUG = filter_var(
    getenv('ONLYOFFICE_DEBUG') ?: (!$isProduction ? 'true' : 'false'),
    FILTER_VALIDATE_BOOLEAN
);

// Log file path
$ONLYOFFICE_LOG_FILE = getenv('ONLYOFFICE_LOG_FILE') ?: 
    realpath(__DIR__ . '/../../logs') . '/onlyoffice.log';

// ================================================================
// JWT FUNCTIONS
// ================================================================

/**
 * Generate JWT token for OnlyOffice
 */
function generateOnlyOfficeJWT($payload) {
    global $ONLYOFFICE_JWT_SECRET, $ONLYOFFICE_JWT_ALGORITHM, $ONLYOFFICE_JWT_ENABLED;
    
    if (!$ONLYOFFICE_JWT_ENABLED) {
        return '';
    }
    
    $header = [
        'alg' => $ONLYOFFICE_JWT_ALGORITHM,
        'typ' => 'JWT'
    ];
    
    $headerEncoded = base64UrlEncode(json_encode($header));
    $payloadEncoded = base64UrlEncode(json_encode($payload));
    
    $signature = hash_hmac(
        $ONLYOFFICE_JWT_ALGORITHM === 'HS256' ? 'sha256' : 'sha512',
        $headerEncoded . '.' . $payloadEncoded,
        $ONLYOFFICE_JWT_SECRET,
        true
    );
    $signatureEncoded = base64UrlEncode($signature);
    
    return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
}

/**
 * Verify JWT token from OnlyOffice callback
 */
function verifyOnlyOfficeJWT($token) {
    global $ONLYOFFICE_JWT_SECRET, $ONLYOFFICE_JWT_ALGORITHM, $ONLYOFFICE_JWT_ENABLED;
    
    if (!$ONLYOFFICE_JWT_ENABLED) {
        return ['valid' => true, 'payload' => []];
    }
    
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return ['valid' => false, 'error' => 'Invalid token format'];
    }
    
    list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;
    
    $signature = base64UrlEncode(hash_hmac(
        $ONLYOFFICE_JWT_ALGORITHM === 'HS256' ? 'sha256' : 'sha512',
        $headerEncoded . '.' . $payloadEncoded,
        $ONLYOFFICE_JWT_SECRET,
        true
    ));
    
    if ($signature !== $signatureEncoded) {
        return ['valid' => false, 'error' => 'Invalid signature'];
    }
    
    $payload = json_decode(base64UrlDecode($payloadEncoded), true);
    
    // Verify expiration if present
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        return ['valid' => false, 'error' => 'Token expired'];
    }
    
    return ['valid' => true, 'payload' => $payload];
}

/**
 * Extract JWT from request headers
 */
function extractJWTFromRequest() {
    global $ONLYOFFICE_JWT_HEADER;
    
    $headers = getallheaders();
    $authHeader = $headers[$ONLYOFFICE_JWT_HEADER] ?? '';
    
    if (preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
        return $matches[1];
    }
    
    // Check in POST body for callback
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    return $data['token'] ?? '';
}

/**
 * Base64 URL-safe encode
 */
function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Base64 URL-safe decode
 */
function base64UrlDecode($data) {
    return base64_decode(strtr($data, '-_', '+/'));
}

// ================================================================
// SECURITY FUNCTIONS
// ================================================================

/**
 * Apply security headers
 */
function applyOnlyOfficeSecurityHeaders() {
    global $ONLYOFFICE_SECURITY_HEADERS, $ONLYOFFICE_CORS_ORIGINS, $isProduction;
    
    // Apply security headers
    foreach ($ONLYOFFICE_SECURITY_HEADERS as $header => $value) {
        header("$header: $value");
    }
    
    // CORS headers
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array('*', $ONLYOFFICE_CORS_ORIGINS) || in_array($origin, $ONLYOFFICE_CORS_ORIGINS)) {
        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Credentials: true");
    }
    
    // HTTPS enforcement in production
    if ($isProduction && (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on')) {
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
    }
}

/**
 * Check rate limiting
 */
function checkOnlyOfficeRateLimit($identifier = null) {
    global $ONLYOFFICE_RATE_LIMIT;
    
    if (!$ONLYOFFICE_RATE_LIMIT) {
        return true;
    }
    
    $identifier = $identifier ?: $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $cacheKey = 'onlyoffice_rate_' . md5($identifier);
    $cacheFile = sys_get_temp_dir() . '/' . $cacheKey;
    
    $requests = [];
    if (file_exists($cacheFile)) {
        $requests = json_decode(file_get_contents($cacheFile), true) ?: [];
    }
    
    $now = time();
    $requests = array_filter($requests, function($timestamp) use ($now) {
        return $timestamp > ($now - 60);
    });
    
    if (count($requests) >= $ONLYOFFICE_RATE_LIMIT) {
        return false;
    }
    
    $requests[] = $now;
    file_put_contents($cacheFile, json_encode($requests));
    
    return true;
}

/**
 * Validate callback IP
 */
function validateOnlyOfficeCallbackIP() {
    global $ONLYOFFICE_ALLOWED_IPS;
    
    if (empty($ONLYOFFICE_ALLOWED_IPS)) {
        return true;
    }
    
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // Check for proxy headers
    $proxyHeaders = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CF_CONNECTING_IP'];
    foreach ($proxyHeaders as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            $clientIP = trim($ips[0]);
            break;
        }
    }
    
    return in_array($clientIP, $ONLYOFFICE_ALLOWED_IPS);
}

/**
 * Log OnlyOffice events
 */
function logOnlyOfficeEvent($level, $message, $context = []) {
    global $ONLYOFFICE_DEBUG, $ONLYOFFICE_LOG_FILE;
    
    if (!$ONLYOFFICE_DEBUG && $level === 'debug') {
        return;
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? json_encode($context) : '';
    $logMessage = "[$timestamp] [$level] $message $contextStr\n";
    
    error_log($logMessage, 3, $ONLYOFFICE_LOG_FILE);
    
    if ($level === 'error' || $level === 'critical') {
        error_log("OnlyOffice $level: $message");
    }
}

// ================================================================
// CONFIGURATION VALIDATION
// ================================================================

/**
 * Verify OnlyOffice configuration
 */
function checkOnlyOfficeConfig() {
    global $ONLYOFFICE_DS_PUBLIC_URL, $ONLYOFFICE_DOCUMENTS_DIR, 
           $ONLYOFFICE_JWT_ENABLED, $ONLYOFFICE_JWT_SECRET, $isProduction;
    
    $errors = [];
    
    // Check server URL
    if (empty($ONLYOFFICE_DS_PUBLIC_URL)) {
        $errors[] = 'ONLYOFFICE_DS_PUBLIC_URL not configured';
    }
    
    // Check JWT in production
    if ($isProduction && !$ONLYOFFICE_JWT_ENABLED) {
        $errors[] = 'JWT authentication must be enabled in production';
    }
    
    if ($ONLYOFFICE_JWT_ENABLED && empty($ONLYOFFICE_JWT_SECRET)) {
        $errors[] = 'JWT secret key not configured';
    }
    
    // Check documents directory
    if (!is_dir($ONLYOFFICE_DOCUMENTS_DIR)) {
        if (!@mkdir($ONLYOFFICE_DOCUMENTS_DIR, 0755, true)) {
            $errors[] = 'Cannot create documents directory: ' . $ONLYOFFICE_DOCUMENTS_DIR;
        }
    } elseif (!is_writable($ONLYOFFICE_DOCUMENTS_DIR)) {
        $errors[] = 'Documents directory not writable: ' . $ONLYOFFICE_DOCUMENTS_DIR;
    }
    
    return $errors;
}

/**
 * Get OnlyOffice server status
 */
function getOnlyOfficeServerStatus() {
    global $ONLYOFFICE_DS_INTERNAL_URL, $ONLYOFFICE_TIMEOUT, 
           $ONLYOFFICE_JWT_ENABLED, $ONLYOFFICE_JWT_HEADER;
    
    $healthUrl = $ONLYOFFICE_DS_INTERNAL_URL . '/healthcheck';
    
    $headers = [
        'User-Agent: Nexio OnlyOffice Client/1.0',
        'Accept: application/json'
    ];
    
    // Add JWT token if enabled
    if ($ONLYOFFICE_JWT_ENABLED) {
        $token = generateOnlyOfficeJWT(['iss' => 'nexio-platform']);
        $headers[] = "$ONLYOFFICE_JWT_HEADER: Bearer $token";
    }
    
    $context = stream_context_create([
        'http' => [
            'timeout' => $ONLYOFFICE_TIMEOUT,
            'method' => 'GET',
            'ignore_errors' => true,
            'header' => implode("\r\n", $headers)
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true
        ]
    ]);
    
    $result = @file_get_contents($healthUrl, false, $context);
    
    return $result !== false && strpos($result, 'true') !== false;
}

// ================================================================
// DEFINE CONSTANTS FOR BACKWARD COMPATIBILITY
// ================================================================

// Define all constants if they haven't been defined yet
// This ensures both variable and constant access work throughout the system

if (!defined('ONLYOFFICE_JWT_ENABLED')) {
    define('ONLYOFFICE_JWT_ENABLED', $ONLYOFFICE_JWT_ENABLED);
}

if (!defined('ONLYOFFICE_JWT_SECRET')) {
    define('ONLYOFFICE_JWT_SECRET', $ONLYOFFICE_JWT_SECRET);
}

if (!defined('ONLYOFFICE_JWT_ALGORITHM')) {
    define('ONLYOFFICE_JWT_ALGORITHM', $ONLYOFFICE_JWT_ALGORITHM);
}

if (!defined('ONLYOFFICE_JWT_HEADER')) {
    define('ONLYOFFICE_JWT_HEADER', $ONLYOFFICE_JWT_HEADER);
}

if (!defined('ONLYOFFICE_DS_PUBLIC_URL')) {
    define('ONLYOFFICE_DS_PUBLIC_URL', $ONLYOFFICE_DS_PUBLIC_URL);
}

if (!defined('ONLYOFFICE_DS_INTERNAL_URL')) {
    define('ONLYOFFICE_DS_INTERNAL_URL', $ONLYOFFICE_DS_INTERNAL_URL);
}

if (!defined('ONLYOFFICE_CALLBACK_URL')) {
    define('ONLYOFFICE_CALLBACK_URL', $ONLYOFFICE_CALLBACK_URL);
}

if (!defined('ONLYOFFICE_DEBUG')) {
    define('ONLYOFFICE_DEBUG', $ONLYOFFICE_DEBUG);
}

if (!defined('ONLYOFFICE_FORCE_HTTPS')) {
    // Define FORCE_HTTPS based on production environment
    define('ONLYOFFICE_FORCE_HTTPS', $isProduction);
}

if (!defined('ONLYOFFICE_RATE_LIMIT')) {
    define('ONLYOFFICE_RATE_LIMIT', $ONLYOFFICE_RATE_LIMIT);
}

if (!defined('ONLYOFFICE_CORS_ORIGINS')) {
    // Convert array to string for constant (constants can't be arrays in older PHP versions)
    define('ONLYOFFICE_CORS_ORIGINS', implode(',', $ONLYOFFICE_CORS_ORIGINS));
}

if (!defined('ONLYOFFICE_ALLOWED_IPS')) {
    // Convert array to string for constant
    define('ONLYOFFICE_ALLOWED_IPS', implode(',', $ONLYOFFICE_ALLOWED_IPS));
}

if (!defined('ONLYOFFICE_SERVER')) {
    // Legacy constant for backward compatibility
    define('ONLYOFFICE_SERVER', $ONLYOFFICE_SERVER);
}

if (!defined('ONLYOFFICE_TIMEOUT')) {
    define('ONLYOFFICE_TIMEOUT', $ONLYOFFICE_TIMEOUT);
}

if (!defined('ONLYOFFICE_MAX_FILE_SIZE')) {
    define('ONLYOFFICE_MAX_FILE_SIZE', $ONLYOFFICE_MAX_FILE_SIZE);
}

if (!defined('ONLYOFFICE_DOCUMENTS_DIR')) {
    define('ONLYOFFICE_DOCUMENTS_DIR', $ONLYOFFICE_DOCUMENTS_DIR);
}

if (!defined('ONLYOFFICE_SESSION_TIMEOUT')) {
    define('ONLYOFFICE_SESSION_TIMEOUT', $ONLYOFFICE_SESSION_TIMEOUT);
}

if (!defined('ONLYOFFICE_LOG_FILE')) {
    define('ONLYOFFICE_LOG_FILE', $ONLYOFFICE_LOG_FILE);
}

// Define array constants as JSON strings for retrieval if needed
if (!defined('ONLYOFFICE_SUPPORTED_FORMATS_JSON')) {
    define('ONLYOFFICE_SUPPORTED_FORMATS_JSON', json_encode($ONLYOFFICE_SUPPORTED_FORMATS));
}

if (!defined('ONLYOFFICE_SECURITY_HEADERS_JSON')) {
    define('ONLYOFFICE_SECURITY_HEADERS_JSON', json_encode($ONLYOFFICE_SECURITY_HEADERS));
}

// ================================================================
// INITIALIZATION
// ================================================================

// Create documents directory if needed
if (!file_exists($ONLYOFFICE_DOCUMENTS_DIR)) {
    @mkdir($ONLYOFFICE_DOCUMENTS_DIR, 0755, true);
}

// Create log file if needed
if ($ONLYOFFICE_DEBUG && !file_exists($ONLYOFFICE_LOG_FILE)) {
    @touch($ONLYOFFICE_LOG_FILE);
    @chmod($ONLYOFFICE_LOG_FILE, 0644);
}

// Log configuration in debug mode
if ($ONLYOFFICE_DEBUG && php_sapi_name() !== 'cli') {
    logOnlyOfficeEvent('info', 'Configuration loaded', [
        'public_url' => $ONLYOFFICE_DS_PUBLIC_URL,
        'internal_url' => $ONLYOFFICE_DS_INTERNAL_URL,
        'callback_url' => $ONLYOFFICE_CALLBACK_URL,
        'jwt_enabled' => $ONLYOFFICE_JWT_ENABLED,
        'production' => $isProduction
    ]);
}
?>