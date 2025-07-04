<?php
/**
 * Punto di ingresso principale della piattaforma
 * Reindirizza alla dashboard se autenticato, altrimenti al login
 */

require_once 'backend/config/config.php';

$auth = Auth::getInstance();

if ($auth->isAuthenticated()) {
    // Utente autenticato - vai alla dashboard
    redirect(APP_PATH . '/dashboard.php');
} else {
    // Non autenticato - vai al login
    redirect(APP_PATH . '/login.php');
} 