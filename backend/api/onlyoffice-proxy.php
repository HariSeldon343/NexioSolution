<?php
/**
 * OnlyOffice Proxy - Risolve problemi CORS
 * Proxy per accedere a OnlyOffice attraverso lo stesso dominio
 */

// SECURITY: Require authentication
require_once __DIR__ . '/../middleware/Auth.php';
$auth = Auth::getInstance();
$auth->requireAuth();

// SECURITY: Remove wildcard CORS, use specific origins
$allowedOrigins = [
    'http://localhost',
    'http://localhost:8082',
    'https://office.yourdomain.com'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    // Default to localhost if no valid origin
    header('Access-Control-Allow-Origin: http://localhost');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

// Gestisci preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// SECURITY: Add CSRF protection for state-changing operations
if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'])) {
    require_once __DIR__ . '/../utils/CSRFTokenManager.php';
    CSRFTokenManager::validateRequest();
}

// Configurazione OnlyOffice
$onlyoffice_server = 'http://localhost:8080';
$path = $_GET['path'] ?? '';

// SECURITY: Multi-tenant check - get current company context
$currentAzienda = $auth->getCurrentAzienda();
$isSuperAdmin = $auth->isSuperAdmin();

// Valida il path per sicurezza
$allowed_paths = [
    'healthcheck',
    'web-apps/apps/api/documents/api.js',
    'welcome'
];

$is_allowed = false;
foreach ($allowed_paths as $allowed_path) {
    if (strpos($path, $allowed_path) !== false) {
        $is_allowed = true;
        break;
    }
}

if (!$is_allowed) {
    http_response_code(403);
    echo json_encode(['error' => 'Path non autorizzato']);
    exit;
}

// SECURITY: Log proxy access for audit
error_log("OnlyOffice proxy access - User: {$auth->getUser()['id']}, Company: {$currentAzienda['id']}, Path: {$path}");

// Costruisci URL completo
$url = $onlyoffice_server . '/' . ltrim($path, '/');

// Inizializza cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HEADER, true);

// Forward metodo HTTP
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']);

// Forward headers rilevanti
$headers = [];
foreach (getallheaders() as $name => $value) {
    if (in_array(strtolower($name), ['content-type', 'authorization', 'user-agent'])) {
        $headers[] = "$name: $value";
    }
}
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Forward body se presente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty(file_get_contents('php://input'))) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
}

// Esegui richiesta
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

if (curl_error($ch)) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore proxy: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}

curl_close($ch);

// Separa headers e body
$responseHeaders = substr($response, 0, $headerSize);
$responseBody = substr($response, $headerSize);

// Forward headers risposta (escludi quelli problematici)
$headerLines = explode("\n", $responseHeaders);
foreach ($headerLines as $header) {
    $header = trim($header);
    if (empty($header) || strpos($header, 'HTTP/') === 0) continue;
    
    $headerLower = strtolower($header);
    if (strpos($headerLower, 'content-encoding') !== false ||
        strpos($headerLower, 'transfer-encoding') !== false ||
        strpos($headerLower, 'connection') !== false) {
        continue;
    }
    
    header($header);
}

// Set status code
http_response_code($httpCode);

// Output body
echo $responseBody;
?> 