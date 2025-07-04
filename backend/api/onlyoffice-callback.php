<?php
/**
 * API Callback OnlyOffice Document Server Docker
 * Gestisce i callback di salvataggio e modifica documenti
 */

// Log immediato per debug
error_log("OnlyOffice Callback - Called at " . date('Y-m-d H:i:s'));
error_log("OnlyOffice Callback - Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("OnlyOffice Callback - Request URI: " . $_SERVER['REQUEST_URI']);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/onlyoffice.config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Gestisci preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Log della richiesta per debug
if ($ONLYOFFICE_DEBUG) {
    $input = file_get_contents('php://input');
    error_log("OnlyOffice Callback - Method: " . $_SERVER['REQUEST_METHOD']);
    error_log("OnlyOffice Callback - Input: " . $input);
    error_log("OnlyOffice Callback - Headers: " . json_encode(getallheaders()));
}

try {
    // Leggi il payload dal corpo della richiesta
    $input = file_get_contents('php://input');
    
    if (empty($input)) {
        // Richiesta vuota - normale per alcuni tipi di callback
        echo json_encode(['error' => 0]);
        exit;
    }
    
    // Decodifica JSON
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Payload JSON non valido');
    }
    
    // Estrai informazioni dal callback
    $status = $data['status'] ?? 0;
    $url = $data['url'] ?? '';
    $changesurl = $data['changesurl'] ?? '';
    $history = $data['history'] ?? null;
    $users = $data['users'] ?? [];
    $key = $data['key'] ?? '';
    $userdata = $data['userdata'] ?? '';
    
    // Log del callback ricevuto
    if ($ONLYOFFICE_DEBUG) {
        error_log("OnlyOffice Callback - Status: $status, Key: $key, URL: $url");
    }
    
    // Gestisci i diversi stati del callback
    switch ($status) {
        case 0:
            // Nessun documento, nessuna azione necessaria
            handleNoDocument($key);
            break;
            
        case 1:
            // Documento in modifica - nessuna azione necessaria
            handleEditingDocument($key, $users);
            break;
            
        case 2:
        case 6:
            // Documento pronto per il salvataggio
            if ($url) {
                saveDocumentFromCallback($key, $url);
            }
            break;
            
        case 3:
        case 7:
            // Errore di salvataggio documento
            handleSaveError($key);
            break;
            
        case 4:
            // Documento chiuso senza modifiche
            handleDocumentClosed($key);
            break;
            
        default:
            throw new Exception("Stato callback sconosciuto: $status");
    }
    
    // Risposta di successo
    echo json_encode(['error' => 0]);
    
} catch (Exception $e) {
    // Log dell'errore
    error_log("OnlyOffice Callback Error: " . $e->getMessage());
    
    // Risposta di errore
    http_response_code(500);
    echo json_encode([
        'error' => 1,
        'message' => $e->getMessage()
    ]);
}

/**
 * Gestisce il caso di nessun documento
 */
function handleNoDocument($key) {
    if ($GLOBALS['ONLYOFFICE_DEBUG']) {
        error_log("OnlyOffice Callback - No document for key: $key");
    }
}

/**
 * Gestisce il documento in modifica
 */
function handleEditingDocument($key, $users) {
    if ($GLOBALS['ONLYOFFICE_DEBUG']) {
        error_log("OnlyOffice Callback - Document editing, key: $key, users: " . json_encode($users));
    }
    
    // Potresti aggiornare lo stato "in modifica" nel database
    try {
        // Esempio: aggiorna timestamp ultimo accesso
        updateDocumentAccess($key, $users);
    } catch (Exception $e) {
        error_log("Error updating document access: " . $e->getMessage());
    }
}

/**
 * Salva il documento dal callback OnlyOffice
 */
function saveDocumentFromCallback($key, $url) {
    global $ONLYOFFICE_DOCUMENTS_DIR;
    
    try {
        // Scarica il documento dall'URL fornito da OnlyOffice
        $document_content = file_get_contents($url);
        
        if ($document_content === false) {
            throw new Exception('Impossibile scaricare il documento da OnlyOffice');
        }
        
        // Determina il percorso del file
        $file_path = $ONLYOFFICE_DOCUMENTS_DIR . '/' . $key . '.docx';
        
        // Salva il file
        $bytes_written = file_put_contents($file_path, $document_content);
        
        if ($bytes_written === false) {
            throw new Exception('Impossibile salvare il documento');
        }
        
        // Aggiorna il database se possibile
        updateDocumentInDatabase($key, $file_path);
        
        if ($GLOBALS['ONLYOFFICE_DEBUG']) {
            error_log("OnlyOffice Callback - Document saved: $file_path ($bytes_written bytes)");
        }
        
    } catch (Exception $e) {
        error_log("OnlyOffice Callback - Save error: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Gestisce errore di salvataggio
 */
function handleSaveError($key) {
    error_log("OnlyOffice Callback - Save error for key: $key");
    
    // Potresti registrare l'errore nel database
    try {
        updateDocumentError($key, 'Errore durante il salvataggio');
    } catch (Exception $e) {
        error_log("Error updating document error status: " . $e->getMessage());
    }
}

/**
 * Gestisce documento chiuso senza modifiche
 */
function handleDocumentClosed($key) {
    if ($GLOBALS['ONLYOFFICE_DEBUG']) {
        error_log("OnlyOffice Callback - Document closed without changes, key: $key");
    }
    
    // Aggiorna stato nel database se necessario
    try {
        updateDocumentClosed($key);
    } catch (Exception $e) {
        error_log("Error updating document closed status: " . $e->getMessage());
    }
}

/**
 * Aggiorna ultimo accesso al documento
 */
function updateDocumentAccess($key, $users) {
    $parts = explode('_', $key);
    $documentId = $parts[0];
    
    if (!is_numeric($documentId)) {
        return;
    }
    
    try {
        $stmt = db_query(
            "UPDATE documenti SET ultimo_accesso = NOW() WHERE id = ?",
            [$documentId]
        );
    } catch (Exception $e) {
        // Silently ignore access update errors
        if ($GLOBALS['ONLYOFFICE_DEBUG']) {
            error_log("OnlyOffice Callback - Access update error: " . $e->getMessage());
        }
    }
}

/**
 * Aggiorna stato di errore del documento
 */
function updateDocumentError($key, $errorMessage) {
    $parts = explode('_', $key);
    $documentId = $parts[0];
    
    if (!is_numeric($documentId)) {
        return;
    }
    
    try {
        // Potresti aggiungere una colonna per gli errori nella tabella documenti
        if ($GLOBALS['ONLYOFFICE_DEBUG']) {
            error_log("OnlyOffice Callback - Document error for ID $documentId: $errorMessage");
        }
    } catch (Exception $e) {
        error_log("OnlyOffice Callback - Error update failed: " . $e->getMessage());
    }
}

/**
 * Aggiorna stato di chiusura del documento
 */
function updateDocumentClosed($key) {
    $parts = explode('_', $key);
    $documentId = $parts[0];
    
    if (!is_numeric($documentId)) {
        return;
    }
    
    try {
        $stmt = db_query(
            "UPDATE documenti SET ultimo_accesso = NOW() WHERE id = ?",
            [$documentId]
        );
    } catch (Exception $e) {
        if ($GLOBALS['ONLYOFFICE_DEBUG']) {
            error_log("OnlyOffice Callback - Close update error: " . $e->getMessage());
        }
    }
}

/**
 * Aggiorna il documento nel database
 */
function updateDocumentInDatabase($key, $file_path) {
    try {
        // Estrai l'ID del documento dalla chiave
        $parts = explode('_', $key);
        $document_id = $parts[0];
        
        if (!is_numeric($document_id)) {
            return; // Non è un ID valido
        }
        
        // Aggiorna il record nel database
        $stmt = db_query(
            "UPDATE documenti SET 
             aggiornato_il = NOW(),
             file_path = ?,
             dimensione_file = ?
             WHERE id = ?",
            [$file_path, filesize($file_path), $document_id]
        );
        
        if ($GLOBALS['ONLYOFFICE_DEBUG']) {
            error_log("OnlyOffice Callback - Database updated for document ID: $document_id");
        }
        
    } catch (Exception $e) {
        error_log("OnlyOffice Callback - Database update error: " . $e->getMessage());
    }
}
?>