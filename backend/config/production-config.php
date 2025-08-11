<?php
/**
 * Production Configuration for Cloudflare Tunnel
 * URL: https://app.nexiosolution.it/piattaforma-collaborativa
 */

// Base URL Configuration
define('PRODUCTION_URL', 'https://app.nexiosolution.it');
define('APP_BASE_PATH', '/piattaforma-collaborativa');
define('APP_URL', PRODUCTION_URL . APP_BASE_PATH);
define('API_URL', APP_URL . '/backend/api');
define('PWA_URL', APP_URL . '/mobile-calendar-app');

// JWT Production Keys (generare nuove per sicurezza)
define('JWT_SECRET_PRODUCTION', hash('sha256', 'NexioSol_JWT_' . getenv('SERVER_NAME') . '_2025_SecureKey'));
define('JWT_REFRESH_SECRET', hash('sha256', 'NexioSol_Refresh_' . getenv('SERVER_NAME') . '_2025_Token'));

// CORS Configuration for Cloudflare
define('CORS_ALLOWED_ORIGINS', [
    'https://app.nexiosolution.it',
    'https://nexiosolution.it',
    'http://localhost:3000' // Per sviluppo locale
]);

// Security Headers for Production
define('SECURITY_HEADERS', [
    'X-Frame-Options' => 'SAMEORIGIN',
    'X-Content-Type-Options' => 'nosniff',
    'X-XSS-Protection' => '1; mode=block',
    'Referrer-Policy' => 'strict-origin-when-cross-origin',
    'Permissions-Policy' => 'geolocation=(self), microphone=(), camera=()',
    'Content-Security-Policy' => "default-src 'self' https://app.nexiosolution.it; " .
                                  "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://app.nexiosolution.it https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
                                  "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
                                  "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com data:; " .
                                  "img-src 'self' data: blob: https:; " .
                                  "connect-src 'self' https://app.nexiosolution.it wss://app.nexiosolution.it https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
                                  "frame-ancestors 'self';"
]);

// PWA Configuration
define('PWA_CONFIG', [
    'name' => 'Nexio Calendar',
    'short_name' => 'Nexio Cal',
    'start_url' => APP_BASE_PATH . '/mobile-calendar-app/',
    'scope' => APP_BASE_PATH . '/mobile-calendar-app/',
    'display' => 'standalone',
    'theme_color' => '#1976d2',
    'background_color' => '#ffffff'
]);

// WebPush VAPID Keys (generare con: https://web-push-codelab.glitch.me/)
define('VAPID_PUBLIC_KEY', 'BKd0ZY7ngm3VwJdjRPfhV6_qGaLVq8Zz0TpBDPMZR4VQww8lgLz5gv3mTPaKlXkFMVxqPybKVfIaFqLQdQw5zCk');
define('VAPID_PRIVATE_KEY', getenv('VAPID_PRIVATE_KEY') ?: 'GENERATE_NEW_PRIVATE_KEY');
define('VAPID_SUBJECT', 'mailto:admin@nexiosolution.it');

// Rate Limiting
define('RATE_LIMIT_ENABLED', true);
define('RATE_LIMIT_PER_MINUTE', 60);
define('RATE_LIMIT_PER_HOUR', 600);

// Session Configuration
define('SESSION_SECURE', true); // Forza HTTPS per cookies
define('SESSION_HTTPONLY', true);
define('SESSION_SAMESITE', 'Strict');

// Database SSL (se necessario per Cloudflare)
define('DB_SSL_ENABLED', false);
define('DB_SSL_CA', '');

// Error Reporting (disabilita in produzione)
if (strpos($_SERVER['HTTP_HOST'] ?? '', 'app.nexiosolution.it') !== false) {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../../logs/php_errors.log');
}

// Funzione helper per applicare security headers
function applySecurityHeaders() {
    foreach (SECURITY_HEADERS as $header => $value) {
        header("$header: $value");
    }
}

// Funzione helper per CORS
function applyCORSHeaders() {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    if (in_array($origin, CORS_ALLOWED_ORIGINS)) {
        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Credentials: true");
    }
    
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token, X-Requested-With");
    header("Access-Control-Max-Age: 86400");
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

// Auto-include in config.php se in produzione
if (strpos($_SERVER['HTTP_HOST'] ?? '', 'app.nexiosolution.it') !== false) {
    applyCORSHeaders();
    applySecurityHeaders();
}
?>