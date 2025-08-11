<?php
/**
 * API per eliminazione ticket (solo super admin e solo ticket chiusi)
 */

require_once '../config/config.php';
require_once '../middleware/Auth.php';
require_once '../utils/ActivityLogger.php';

header('Content-Type: application/json');

// Autenticazione
$auth = Auth::getInstance();
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non autenticato']);
    exit;
}

// Solo super admin può eliminare ticket
if (!$auth->isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Non autorizzato. Solo i super admin possono eliminare i ticket.']);
    exit;
}

// Ottieni l'ID del ticket
$ticketId = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;

if (!$ticketId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID ticket mancante']);
    exit;
}

try {
    // Verifica che il ticket esista e sia chiuso
    $stmt = db_query("
        SELECT t.*, a.nome as azienda_nome, u.nome as creatore_nome, u.cognome as creatore_cognome
        FROM tickets t
        LEFT JOIN aziende a ON t.azienda_id = a.id
        LEFT JOIN utenti u ON t.utente_id = u.id
        WHERE t.id = ?
    ", [$ticketId]);
    
    $ticket = $stmt->fetch();
    
    if (!$ticket) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Ticket non trovato']);
        exit;
    }
    
    // Verifica che il ticket sia chiuso
    if ($ticket['stato'] !== 'chiuso') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Solo i ticket chiusi possono essere eliminati']);
        exit;
    }
    
    // Inizia transazione
    db_connection()->beginTransaction();
    
    // Salva le informazioni del ticket per il log
    $ticketInfo = [
        'codice' => $ticket['codice'],
        'oggetto' => $ticket['oggetto'],
        'azienda' => $ticket['azienda_nome'],
        'creatore' => $ticket['creatore_nome'] . ' ' . $ticket['creatore_cognome'],
        'data_creazione' => $ticket['creato_il'],
        'data_chiusura' => $ticket['aggiornato_il']
    ];
    
    // Elimina prima le dipendenze
    db_query("DELETE FROM ticket_risposte WHERE ticket_id = ?", [$ticketId]);
    db_query("DELETE FROM ticket_destinatari WHERE ticket_id = ?", [$ticketId]);
    
    // Elimina il ticket
    db_query("DELETE FROM tickets WHERE id = ?", [$ticketId]);
    
    // Commit transazione
    db_connection()->commit();
    
    // Log dell'eliminazione con tutti i dettagli
    $logger = ActivityLogger::getInstance();
    $logger->log(
        'ticket_eliminato',
        "Eliminato ticket chiuso: {$ticketInfo['codice']} - Oggetto: {$ticketInfo['oggetto']} - Azienda: {$ticketInfo['azienda']} - Creatore: {$ticketInfo['creatore']} - Creato: {$ticketInfo['data_creazione']} - Chiuso: {$ticketInfo['data_chiusura']}",
        [
            'ticket_id' => $ticketId,
            'ticket_info' => $ticketInfo,
            'deleted_by' => $auth->getUser()['id']
        ]
    );
    
    echo json_encode([
        'success' => true,
        'message' => "Ticket {$ticket['codice']} eliminato con successo"
    ]);
    
} catch (Exception $e) {
    if (db_connection()->inTransaction()) {
        db_connection()->rollback();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Errore durante l\'eliminazione del ticket: ' . $e->getMessage()
    ]);
}
?>