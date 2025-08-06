<?php
/**
 * Logout - Termina la sessione utente
 */

require_once 'backend/config/config.php';
require_once 'backend/utils/ActivityLogger.php';

$auth = Auth::getInstance();
$logger = ActivityLogger::getInstance();

// Log logout prima di distruggere la sessione
if ($auth->isAuthenticated()) {
    $user = $auth->getUser();
    if ($user) {
        $logger->logLogout($user['id']);
    }
}

$auth->logout();

// Redirect al login con messaggio di successo
redirect(APP_PATH . '/login.php?logout=1'); 