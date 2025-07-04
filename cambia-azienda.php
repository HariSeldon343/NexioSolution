<?php
require_once 'backend/config/config.php';

$auth = Auth::getInstance();
$auth->requireAuth();

// Ottieni l'ID azienda dalla richiesta
$aziendaId = $_POST['azienda_id'] ?? $_GET['azienda_id'] ?? null;

if ($aziendaId !== null) {
    // Se è vuoto, rimuovi l'azienda corrente (vista globale per super admin)
    if ($aziendaId === '') {
        if ($auth->isSuperAdmin()) {
            unset($_SESSION['azienda_corrente']);
            redirect(APP_PATH . '/dashboard.php');
        }
    } else {
        // Prova a impostare l'azienda
        if ($auth->setCurrentAzienda($aziendaId)) {
            redirect(APP_PATH . '/dashboard.php');
        } else {
            $_SESSION['error'] = "Non hai accesso a questa azienda.";
            redirect(APP_PATH . '/seleziona-azienda.php');
        }
    }
}

// Se arriviamo qui, reindirizza alla selezione
redirect(APP_PATH . '/seleziona-azienda.php'); 