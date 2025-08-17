<?php
/**
 * Mobile Configuration File
 * Gestisce gli URL dinamicamente per supportare sia localhost che produzione
 */

// Rileva automaticamente il base URL
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$scriptPath = dirname($_SERVER['SCRIPT_NAME']);

// Rimuovi /mobile dalla path se presente per ottenere il root del progetto
$basePath = str_replace('/mobile', '', $scriptPath);
$basePath = rtrim($basePath, '/');

// Define delle costanti per l'uso nel codice
define('BASE_PATH', $basePath);
define('BASE_URL', $basePath);
define('FULL_URL', $protocol . '://' . $host . $basePath);
define('MOBILE_URL', $basePath . '/mobile');
define('API_URL', $basePath . '/backend/api');
define('ASSETS_URL', $basePath . '/assets');

// Percorsi relativi per file system
define('ROOT_PATH', dirname(__DIR__));
define('BACKEND_PATH', ROOT_PATH . '/backend');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');

// Funzione helper per generare URL completi
function url($path = '') {
    if (strpos($path, 'http') === 0) {
        return $path; // Already a full URL
    }
    $path = ltrim($path, '/');
    return BASE_URL . '/' . $path;
}

// Funzione helper per generare URL API
function api_url($endpoint = '') {
    $endpoint = ltrim($endpoint, '/');
    return API_URL . '/' . $endpoint;
}

// Funzione helper per asset URL
function asset_url($path = '') {
    $path = ltrim($path, '/');
    return ASSETS_URL . '/' . $path;
}

// Funzione helper per mobile URL
function mobile_url($path = '') {
    $path = ltrim($path, '/');
    return MOBILE_URL . '/' . $path;
}

// Generazione dinamica del manifest per PWA
function generate_manifest_url() {
    return mobile_url('manifest.php');
}

// Meta tag per base URL (da includere nell'HTML head)
function base_url_meta() {
    return '<meta name="base-url" content="' . BASE_URL . '">';
}

// JavaScript config per client-side
function js_config() {
    return '<script>
        window.NexioConfig = {
            BASE_URL: "' . BASE_URL . '",
            API_URL: "' . API_URL . '",
            MOBILE_URL: "' . MOBILE_URL . '",
            ASSETS_URL: "' . ASSETS_URL . '",
            FULL_URL: "' . FULL_URL . '"
        };
    </script>';
}