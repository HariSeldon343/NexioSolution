<?php
require_once '../config/config.php';

header('Content-Type: application/json');

$auth = Auth::getInstance();
$auth->requireAuth();

$db = Database::getInstance();
$azienda_id = intval($_GET['azienda_id'] ?? 0);

if (!$azienda_id) {
    echo json_encode([]);
    exit;
}

// Verifica che l'utente abbia accesso all'azienda
$currentAzienda = $auth->getCurrentAzienda();
if (!$auth->isSuperAdmin() && $currentAzienda['azienda_id'] != $azienda_id) {
    echo json_encode([]);
    exit;
}

// Carica referenti
$stmt = $db->query("
    SELECT id, nome, cognome, email, ruolo_aziendale 
    FROM referenti_aziende 
    WHERE azienda_id = ? AND attivo = 1 
    ORDER BY cognome, nome", [$azienda_id]);

$referenti = $stmt->fetchAll();

echo json_encode($referenti); 