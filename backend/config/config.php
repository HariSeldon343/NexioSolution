<?php
/**
 * Configurazione principale della piattaforma
 */

// Prevent multiple inclusions
if (defined('NEXIO_CONFIG_LOADED')) {
    return;
}
define('NEXIO_CONFIG_LOADED', true);

// Imposta timezone
date_default_timezone_set('Europe/Rome');

// Include production config if on Cloudflare tunnel
if (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'app.nexiosolution.it') !== false) {
    require_once __DIR__ . '/production-config.php';
}

// Include CSP configuration
require_once __DIR__ . '/csp-config.php';

// Percorsi
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(dirname(__DIR__)));
    define('BASE_PATH', dirname(dirname(__DIR__)));
    define('APP_PATH', '/piattaforma-collaborativa');
    define('APP_NAME', 'Nexio');
    define('APP_VERSION', '1.0.0');
    define('APP_MOTTO', 'Piattaforma Collaborativa Aziendale');
    define('UPLOAD_PATH', dirname(dirname(__DIR__)) . '/uploads');
}

// Database
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'nexiosol');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_CHARSET', 'utf8mb4');
}

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
ini_set('error_log', ROOT_PATH . '/logs/error.log');

// Autoloader
spl_autoload_register(function ($class) {
    $paths = [
        ROOT_PATH . '/backend/models/',
        ROOT_PATH . '/backend/utils/',
        ROOT_PATH . '/backend/middleware/',
        ROOT_PATH . '/backend/services/'
    ];
    
    foreach ($paths as $path) {
        $file = $path . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Database connection function
function db_connect() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Errore di connessione al database");
        }
    }
    
    return $pdo;
}

// Alias for backward compatibility
function db_connection() {
    return db_connect();
}

// Query helper function
function db_query($sql, $params = []) {
    $pdo = db_connect();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

// Insert helper function
function db_insert($table, $data) {
    $columns = array_keys($data);
    $placeholders = array_map(function($col) { return ":$col"; }, $columns);
    
    $sql = "INSERT INTO $table (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $placeholders) . ")";
    
    $stmt = db_query($sql, $data);
    return db_connect()->lastInsertId();
}

// Update helper function
function db_update($table, $data, $where, $whereParams = []) {
    $setParts = [];
    $params = [];
    
    foreach ($data as $key => $value) {
        $setParts[] = "$key = :set_$key";
        $params["set_$key"] = $value;
    }
    
    // Add where parameters with prefixes to avoid conflicts
    $whereIndex = 0;
    foreach ($whereParams as $value) {
        $params["where_$whereIndex"] = $value;
        $whereIndex++;
    }
    
    // Replace ? with named parameters in where clause
    $whereIndex = 0;
    $where = preg_replace_callback('/\?/', function() use (&$whereIndex) {
        return ":where_" . ($whereIndex++);
    }, $where);
    
    $sql = "UPDATE $table SET " . implode(", ", $setParts) . " WHERE $where";
    
    $stmt = db_query($sql, $params);
    return $stmt->rowCount();
}

// Helper JSON response function
function json_response($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

// Redirect helper function
function redirect($url) {
    if (!headers_sent()) {
        header("Location: " . $url);
        exit();
    } else {
        echo '<script>window.location.href="' . $url . '";</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . $url . '"></noscript>';
        exit();
    }
}

// Get client IP address helper function
function get_client_ip() {
    // Check for IP behind proxy
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // Can contain multiple IPs, get the first one
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        return $_SERVER['HTTP_X_REAL_IP'];
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        return $_SERVER['REMOTE_ADDR'];
    }
    return '0.0.0.0';
}

// Include Auth middleware
if (file_exists(ROOT_PATH . '/backend/middleware/Auth.php')) {
    require_once ROOT_PATH . '/backend/middleware/Auth.php';
}

// Include Database compatibility class
if (file_exists(__DIR__ . '/database-compat.php')) {
    require_once __DIR__ . '/database-compat.php';
}

// Funzione per generare password
function generate_secure_password($length = 12) {
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