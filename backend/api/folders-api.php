<?php
/**
 * API per la gestione cartelle semplice
 * 
 * @package Nexio
 * @version 1.0.0
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../middleware/Auth.php';
require_once '../utils/ActivityLogger.php';

header('Content-Type: application/json');

// Autenticazione
$auth = Auth::getInstance();
if (!$auth->checkSession()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autenticato']);
    exit;
}

$user = $auth->getUser();
$userId = $user['id'];
$isSuperAdmin = $auth->isSuperAdmin();

// Parse JSON input
$input = json_decode(file_get_contents('php://input'), true);
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'POST':
            handleCreateFolder($input, $userId, $isSuperAdmin);
            break;
            
        case 'DELETE':
            handleDeleteFolder($input, $userId, $isSuperAdmin);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Metodo non consentito']);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Crea una nuova cartella
 */
function handleCreateFolder($input, $userId, $isSuperAdmin) {
    // Validazione input
    if (empty($input['nome'])) {
        throw new Exception('Nome cartella obbligatorio');
    }
    
    $nome = trim($input['nome']);
    $parentId = isset($input['parent_id']) ? intval($input['parent_id']) : null;
    $aziendaId = isset($input['azienda_id']) ? intval($input['azienda_id']) : 0;
    $descrizione = isset($input['descrizione']) ? trim($input['descrizione']) : '';
    
    // Verifica permessi per utenti globali (super_admin e utente_speciale)
    $user = $auth->getUser();
    $isUtenteSpeciale = ($user['ruolo'] === 'utente_speciale');
    
    if ($aziendaId === 0) {
        // azienda_id = 0 significa cartella globale - solo per super_admin e utente_speciale
        if (!$isSuperAdmin && !$isUtenteSpeciale) {
            throw new Exception('Solo gli amministratori e utenti speciali possono creare cartelle globali');
        }
        // Per utenti globali, salva nel database con azienda_id = NULL
        $dbAziendaId = null;
    } else if ($aziendaId > 0) {
        // Utente normale con azienda valida
        $dbAziendaId = $aziendaId;
    } else {
        // azienda_id negativo o non valido
        throw new Exception('ID azienda non valido');
    }
    
    try {
        db_begin_transaction();
        
        // Calcola percorso completo e livello
        $percorsoCompleto = $nome;
        $livello = 0;
        
        if ($parentId) {
            $stmt = db_query("SELECT percorso_completo, livello FROM cartelle WHERE id = ?", [$parentId]);
            $parent = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$parent) {
                throw new Exception('Cartella padre non trovata');
            }
            
            $percorsoCompleto = $parent['percorso_completo'] . '/' . $nome;
            $livello = $parent['livello'] + 1;
        }
        
        // Verifica duplicati - usa $dbAziendaId per il controllo database
        if ($dbAziendaId === null) {
            $query = "SELECT id FROM cartelle WHERE nome = ? AND azienda_id IS NULL";
            $params = [$nome];
        } else {
            $query = "SELECT id FROM cartelle WHERE nome = ? AND azienda_id = ?";
            $params = [$nome, $dbAziendaId];
        }
        
        if ($parentId) {
            $query .= " AND parent_id = ?";
            $params[] = $parentId;
        } else {
            $query .= " AND parent_id IS NULL";
        }
        
        $stmt = db_query($query, $params);
        if ($stmt->fetch()) {
            throw new Exception('Esiste già una cartella con questo nome in questa posizione');
        }
        
        // Crea cartella - usa $dbAziendaId per il salvataggio database
        $folderId = db_insert('cartelle', [
            'nome' => $nome,
            'parent_id' => $parentId,
            'percorso_completo' => $percorsoCompleto,
            'livello' => $livello,
            'azienda_id' => $dbAziendaId,
            'creato_da' => $userId,
            'colore' => '#fbbf24'
        ]);
        
        // Log attività - usa l'azienda_id originale per il log
        ActivityLogger::getInstance()->log(
            'cartella_creata',
            'cartelle',
            $folderId,
            [
                'nome' => $nome,
                'percorso' => $percorsoCompleto,
                'azienda_id' => $aziendaId,
                'is_global' => ($dbAziendaId === null)
            ]
        );
        
        db_commit();
        
        echo json_encode([
            'success' => true,
            'folder_id' => $folderId,
            'message' => 'Cartella creata con successo'
        ]);
        
    } catch (Exception $e) {
        db_rollback();
        throw $e;
    }
}

/**
 * Elimina una cartella
 */
function handleDeleteFolder($input, $userId, $isSuperAdmin) {
    if (empty($input['folder_id'])) {
        throw new Exception('ID cartella obbligatorio');
    }
    
    $folderId = intval($input['folder_id']);
    
    // Verifica che la cartella esista
    $stmt = db_query("SELECT * FROM cartelle WHERE id = ?", [$folderId]);
    $folder = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$folder) {
        throw new Exception('Cartella non trovata');
    }
    
    // Verifica permessi
    if (!$isSuperAdmin && $folder['creato_da'] != $userId) {
        throw new Exception('Non hai i permessi per eliminare questa cartella');
    }
    
    // Verifica se ci sono sottocartelle
    $stmt = db_query("SELECT COUNT(*) as count FROM cartelle WHERE parent_id = ?", [$folderId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        throw new Exception('Impossibile eliminare: la cartella contiene sottocartelle');
    }
    
    // Verifica se ci sono documenti
    $stmt = db_query("SELECT COUNT(*) as count FROM documenti WHERE cartella_id = ?", [$folderId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        throw new Exception('Impossibile eliminare: la cartella contiene documenti');
    }
    
    try {
        db_begin_transaction();
        
        // Elimina cartella
        db_delete('cartelle', 'id = ?', [$folderId]);
        
        // Log attività
        ActivityLogger::getInstance()->log(
            'cartella_eliminata',
            'cartelle',
            $folderId,
            [
                'nome' => $folder['nome'],
                'percorso' => $folder['percorso_completo']
            ]
        );
        
        db_commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Cartella eliminata con successo'
        ]);
        
    } catch (Exception $e) {
        db_rollback();
        throw $e;
    }
}