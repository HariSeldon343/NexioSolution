<?php
require_once 'backend/config/config.php';

$auth = Auth::getInstance();
$auth->requireAuth();

// Ottieni l'ID azienda dalla richiesta
$aziendaId = $_POST['azienda_id'] ?? $_GET['azienda_id'] ?? null;

// Ottieni l'URL di redirect se fornito
$redirectUrl = $_GET['redirect'] ?? $_POST['redirect'] ?? null;
if ($redirectUrl) {
    // Valida che il redirect sia un path relativo interno
    $redirectUrl = urldecode($redirectUrl);
    if (strpos($redirectUrl, '://') !== false || strpos($redirectUrl, '//') === 0) {
        // Non permettere URL esterni
        $redirectUrl = null;
    }
}

// Default redirect al dashboard se non specificato
// Se il redirectUrl inizia con APP_PATH, non aggiungerlo di nuovo
if ($redirectUrl && strpos($redirectUrl, APP_PATH) === 0) {
    $defaultRedirect = $redirectUrl;
} else {
    $defaultRedirect = $redirectUrl ? APP_PATH . $redirectUrl : APP_PATH . '/dashboard.php';
}

if ($aziendaId !== null) {
    // Se è vuoto, rimuovi l'azienda corrente (vista globale per super admin)
    if ($aziendaId === '') {
        if ($auth->isSuperAdmin()) {
            unset($_SESSION['azienda_corrente']);
            redirect($defaultRedirect);
        }
    } else {
        // Prova a impostare l'azienda
        try {
            $auth->switchCompany($aziendaId);
            redirect($defaultRedirect);
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            redirect(APP_PATH . '/seleziona-azienda.php');
        }
    }
}

// Se arriviamo qui, reindirizza alla selezione
redirect(APP_PATH . '/seleziona-azienda.php'); 