<?php
/**
 * Logout - Termina la sessione utente
 */

require_once 'backend/config/config.php';

$auth = Auth::getInstance();
$auth->logout();

// Redirect al login con messaggio di successo
redirect(APP_PATH . '/login.php?logout=1'); 