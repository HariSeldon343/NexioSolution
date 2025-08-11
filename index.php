<?php
/**
 * Punto di ingresso principale della piattaforma
 * Reindirizza alla dashboard se autenticato, altrimenti al login
 */

require_once 'backend/config/config.php';
require_once 'backend/middleware/Auth.php';

// Rileva se è un dispositivo mobile
function isMobile() {
    return preg_match('/(android|webos|iphone|ipad|ipod|blackberry|windows phone)/i', $_SERVER['HTTP_USER_AGENT'] ?? '');
}

// Se mobile, redirect a mobile.php
if (isMobile() && !isset($_GET['desktop'])) {
    header('Location: mobile.php');
    exit();
}

$auth = Auth::getInstance();

if ($auth->isAuthenticated()) {
    // Utente autenticato - vai alla dashboard
    redirect(APP_PATH . '/dashboard.php');
} else {
    // Non autenticato - vai al login
    redirect(APP_PATH . '/login.php');
} 