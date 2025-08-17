<?php
/**
 * API per aggiornare l'azienda associata a un evento
 * Permette di cambiare o rimuovere l'azienda associata
 */

require_once '../config/config.php';
require_once '../middleware/Auth.php';
require_once '../utils/CSRFTokenManager.php';
require_once '../utils/ActivityLogger.php';

// Verifica autenticazione
$auth = Auth::getInstance();
$auth->requireAuth();

// Verifica CSRF token
CSRFTokenManager::validateRequest();

// Header JSON
header('Content-Type: application/json');

try {
    // Verifica permessi - solo chi può gestire eventi
    if (!$auth->canManageEvents()) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Non hai i permessi per modificare eventi'
        ]);
        exit;
    }
    
    // Verifica metodo
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error' => 'Metodo non consentito'
        ]);
        exit;
    }
    
    // Ottieni i dati dal POST
    $eventId = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    $aziendaId = isset($_POST['azienda_id']) && $_POST['azienda_id'] !== '' ? intval($_POST['azienda_id']) : null;
    
    if (!$eventId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'ID evento non valido'
        ]);
        exit;
    }
    
    // Verifica che l'evento esista
    $stmt = db_query("SELECT * FROM eventi WHERE id = ?", [$eventId]);
    $evento = $stmt->fetch();
    
    if (!$evento) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Evento non trovato'
        ]);
        exit;
    }
    
    $user = $auth->getUser();
    $isSuperAdmin = $auth->isSuperAdmin();
    
    // Verifica permessi sull'evento specifico
    if (!$isSuperAdmin && $evento['creato_da'] != $user['id']) {
        // Verifica se l'utente appartiene all'azienda dell'evento
        $currentAzienda = $auth->getCurrentAzienda();
        $aziendaId_corrente = isset($currentAzienda['azienda_id']) ? $currentAzienda['azienda_id'] : 
                              (isset($currentAzienda['id']) ? $currentAzienda['id'] : null);
        
        if ($evento['azienda_id'] != $aziendaId_corrente) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'Non hai i permessi per modificare questo evento'
            ]);
            exit;
        }
    }
    
    // Se non è super admin, verifica che l'azienda sia valida
    if (!$isSuperAdmin && $aziendaId !== null) {
        // Verifica che l'utente possa assegnare eventi a questa azienda
        $currentAzienda = $auth->getCurrentAzienda();
        $aziendaId_corrente = isset($currentAzienda['azienda_id']) ? $currentAzienda['azienda_id'] : 
                              (isset($currentAzienda['id']) ? $currentAzienda['id'] : null);
        
        if ($aziendaId != $aziendaId_corrente) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'Non puoi assegnare eventi ad altre aziende'
            ]);
            exit;
        }
    }
    
    // Se viene specificata un'azienda, verifica che esista
    if ($aziendaId !== null) {
        $stmt = db_query("SELECT id, nome FROM aziende WHERE id = ? AND stato = 'attiva'", [$aziendaId]);
        $azienda = $stmt->fetch();
        
        if (!$azienda) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Azienda non valida o non attiva'
            ]);
            exit;
        }
    }
    
    // Inizia transazione
    db_connection()->beginTransaction();
    
    try {
        // Aggiorna l'azienda dell'evento
        if ($aziendaId === null) {
            // Rimuovi associazione azienda (calendario personale)
            $stmt = db_query(
                "UPDATE eventi SET azienda_id = NULL WHERE id = ?",
                [$eventId]
            );
        } else {
            // Associa all'azienda specificata
            $stmt = db_query(
                "UPDATE eventi SET azienda_id = ? WHERE id = ?",
                [$aziendaId, $eventId]
            );
        }
        
        // Log dell'attività
        $logger = ActivityLogger::getInstance();
        $oldAzienda = $evento['azienda_id'] ? "ID: {$evento['azienda_id']}" : "Nessuna";
        $newAzienda = $aziendaId ? "ID: {$aziendaId}" : "Nessuna (calendario personale)";
        
        $logger->log(
            'evento_update',
            "Aggiornata azienda per evento ID {$eventId}: da {$oldAzienda} a {$newAzienda}",
            [
                'evento_id' => $eventId,
                'titolo_evento' => $evento['titolo'],
                'azienda_precedente' => $evento['azienda_id'],
                'azienda_nuova' => $aziendaId,
                'utente_id' => $user['id']
            ]
        );
        
        // Commit transazione
        db_connection()->commit();
        
        // Prepara risposta con info aggiornate
        $response = [
            'success' => true,
            'message' => $aziendaId === null ? 
                'Evento spostato nel calendario personale' : 
                'Azienda aggiornata con successo',
            'evento_id' => $eventId,
            'azienda_id' => $aziendaId
        ];
        
        // Se c'è un'azienda, aggiungi il nome
        if ($aziendaId !== null && isset($azienda)) {
            $response['azienda_nome'] = $azienda['nome'];
        }
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        // Rollback in caso di errore
        db_connection()->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    // Rollback se la transazione è attiva
    if (db_connection()->inTransaction()) {
        db_connection()->rollback();
    }
    
    error_log("Errore in update-event-company.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Errore durante l\'aggiornamento: ' . $e->getMessage()
    ]);
}
?>