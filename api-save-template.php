<?php
// Previeni qualsiasi output accidentale
ob_start();

// Rimuovi tutti gli errori di display
ini_set('display_errors', 0);
error_reporting(0);

require_once 'backend/config/config.php';

// Funzione per risposta JSON pulita
function sendJsonResponse($success, $message, $data = []) {
    // Pulisci tutto l'output buffer
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Imposta header JSON
    header('Content-Type: application/json; charset=utf-8');
    
    // Prepara risposta
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if (!empty($data)) {
        $response = array_merge($response, $data);
    }
    
    // Invia risposta e termina
    echo json_encode($response);
    exit;
}

try {
    // Verifica metodo POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse(false, 'Metodo non consentito');
    }
    
    // Verifica autenticazione
    $auth = Auth::getInstance();
    if (!$auth->isAuthenticated()) {
        sendJsonResponse(false, 'Non autenticato');
    }
    
    // Verifica permessi
    if (!$auth->isSuperAdmin() && !$auth->hasRoleInAzienda('proprietario') && !$auth->hasRoleInAzienda('admin')) {
        sendJsonResponse(false, 'Non autorizzato');
    }
    
    // Ottieni dati POST
    $modulo_id = $_POST['modulo_id'] ?? null;
    $template_content = $_POST['template_content'] ?? '';
    $header_content = $_POST['header_content'] ?? '';
    $footer_content = $_POST['footer_content'] ?? '';
    $tipo = $_POST['tipo'] ?? 'word';
    
    // Validazione
    if (!$modulo_id) {
        sendJsonResponse(false, 'ID modulo mancante');
    }
    
    // Se template_content è vuoto, usa uno spazio
    if (empty($template_content)) {
        $template_content = ' ';
    }
    
    // Database
    $db = Database::getInstance();
    
    // Verifica se esiste già un template
    $check_query = "SELECT id FROM moduli_template WHERE modulo_id = ?";
    $exists = $db->query($check_query, [$modulo_id])->fetch();
    
    if ($exists) {
        // Aggiorna template esistente
        $update_query = "UPDATE moduli_template SET 
            contenuto = ?, 
            tipo = ?, 
            header_content = ?, 
            footer_content = ?,
            aggiornato_il = NOW() 
            WHERE modulo_id = ?";
        
        $db->query($update_query, [
            $template_content, 
            $tipo, 
            $header_content, 
            $footer_content,
            $modulo_id
        ]);
        
        sendJsonResponse(true, 'Template aggiornato con successo');
    } else {
        // Inserisci nuovo template
        $insert_query = "INSERT INTO moduli_template 
            (modulo_id, contenuto, tipo, header_content, footer_content) 
            VALUES (?, ?, ?, ?, ?)";
        
        $db->query($insert_query, [
            $modulo_id, 
            $template_content, 
            $tipo, 
            $header_content, 
            $footer_content
        ]);
        
        sendJsonResponse(true, 'Template creato con successo');
    }
    
} catch (Exception $e) {
    sendJsonResponse(false, 'Errore: ' . $e->getMessage());
}
?> 