<?php
/**
 * Configurazione per ambiente di produzione
 * Rinominare in config.php e inserire i dati corretti
 */

// Configurazione Database (sostituire con i dati Infomaniak)
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');

// Configurazione applicazione
define('APP_NAME', 'Piattaforma Collaborativa');
define('APP_URL', 'https://yourdomain.com');
define('APP_PATH', ''); // Lasciare vuoto se nella root, altrimenti '/subfolder'
define('APP_ENV', 'production');

// Timezone
date_default_timezone_set('Europe/Rome');

// Sessioni
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // Solo HTTPS
ini_set('session.cookie_samesite', 'Strict');

// Error reporting per produzione
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/../../logs/php_errors.log');

// Upload
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png']);

// Email SMTP (Infomaniak)
define('SMTP_HOST', 'mail.infomaniak.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USER', 'noreply@yourdomain.com');
define('SMTP_PASS', 'your_email_password');
define('SMTP_FROM', 'noreply@yourdomain.com');
define('SMTP_FROM_NAME', 'Piattaforma Collaborativa');

// Sicurezza
define('HASH_COST', 12);
define('SESSION_LIFETIME', 3600); // 1 ora
define('REMEMBER_ME_LIFETIME', 2592000); // 30 giorni

// API Keys (se necessarie)
define('RECAPTCHA_SITE_KEY', '');
define('RECAPTCHA_SECRET_KEY', '');

// Manutenzione
define('MAINTENANCE_MODE', false);
define('MAINTENANCE_MESSAGE', 'Il sito è in manutenzione. Torneremo presto online.');

// Debug (disabilitato in produzione)
define('DEBUG_MODE', false);

// Cache
define('CACHE_ENABLED', true);
define('CACHE_LIFETIME', 3600);

// Limiti
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minuti

// Autoload
require_once dirname(__DIR__) . '/database.php';

// Funzioni helper
function redirect($path = '') {
    $url = APP_URL . APP_PATH . '/' . ltrim($path, '/');
    header("Location: $url");
    exit;
}

function asset($path) {
    return APP_URL . APP_PATH . '/assets/' . ltrim($path, '/');
}

function url($path = '') {
    return APP_URL . APP_PATH . '/' . ltrim($path, '/');
}

// Gestione errori personalizzata
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }
    
    $error = date('Y-m-d H:i:s') . " [$severity] $message in $file:$line\n";
    error_log($error, 3, dirname(__DIR__) . '/../../logs/app_errors.log');
    
    if (DEBUG_MODE) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
});

// Gestione eccezioni
set_exception_handler(function($exception) {
    $error = date('Y-m-d H:i:s') . " [EXCEPTION] " . $exception->getMessage() . 
             " in " . $exception->getFile() . ":" . $exception->getLine() . 
             "\n" . $exception->getTraceAsString() . "\n";
    error_log($error, 3, dirname(__DIR__) . '/../../logs/app_errors.log');
    
    if (!DEBUG_MODE) {
        http_response_code(500);
        include dirname(__DIR__) . '/../../pages/500.php';
        exit;
    }
});
?> 