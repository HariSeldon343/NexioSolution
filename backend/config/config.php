<?php
/**
 * Configurazione principale della piattaforma
 */

// Imposta timezone
date_default_timezone_set('Europe/Rome');

// Percorsi
define('ROOT_PATH', dirname(dirname(__DIR__)));
define('BASE_PATH', dirname(dirname(__DIR__)));
define('APP_PATH', '/piattaforma-collaborativa');
define('APP_NAME', 'Nexio');
define('APP_VERSION', '1.0.0');
define('APP_MOTTO', 'Piattaforma Collaborativa Aziendale');

// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'piattaforma_collaborativa');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Sessioni - configura solo se la sessione non è ancora attiva
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
}

// Errori (sviluppo)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', BASE_PATH . '/logs/error.log');

// Carica configurazione database
require_once __DIR__ . '/database.php';

// Autoloader
spl_autoload_register(function ($class) {
    $paths = [
        BASE_PATH . '/backend/models/' . $class . '.php',
        BASE_PATH . '/backend/controllers/' . $class . '.php',
        BASE_PATH . '/backend/middleware/' . $class . '.php',
        BASE_PATH . '/backend/utils/' . $class . '.php'
    ];
    
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

// Funzione wrapper per compatibilità
function db_connection() {
    global $pdo;
    return $pdo;
}

// Funzioni helper
function redirect($path) {
    header('Location: ' . $path);
    exit;
}

function get_client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

function format_datetime($datetime) {
    if (empty($datetime)) {
        return '-';
    }
    
    try {
        $date = new DateTime($datetime);
        return $date->format('d/m/Y H:i');
    } catch (Exception $e) {
        return '-';
    }
}

function format_date($date) {
    if (empty($date)) {
        return '-';
    }
    
    try {
        $dateObj = new DateTime($date);
        return $dateObj->format('d/m/Y');
    } catch (Exception $e) {
        return '-';
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Sanitizza input utente
 */
function sanitize_input($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Genera password casuale
 */
function generateRandomPassword($length = 8) {
    // Assicura almeno 8 caratteri
    if ($length < 8) $length = 8;
    
    // Gruppi di caratteri
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $numbers = '0123456789';
    $special = '!@#$%^&*(),.?":{}|<>';
    
    // Garantisce almeno un carattere di ogni tipo richiesto
    $password = '';
    $password .= $uppercase[random_int(0, strlen($uppercase) - 1)]; // Almeno 1 maiuscola
    $password .= $special[random_int(0, strlen($special) - 1)];    // Almeno 1 speciale
    $password .= $lowercase[random_int(0, strlen($lowercase) - 1)]; // Almeno 1 minuscola
    $password .= $numbers[random_int(0, strlen($numbers) - 1)];    // Almeno 1 numero
    
    // Riempi il resto con caratteri casuali
    $allChars = $lowercase . $uppercase . $numbers . $special;
    for ($i = 4; $i < $length; $i++) {
        $password .= $allChars[random_int(0, strlen($allChars) - 1)];
    }
    
    // Mescola la password per evitare pattern prevedibili
    return str_shuffle($password);
}