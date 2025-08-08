<?php
require_once __DIR__ . '/../../backend/config/config.php';
require_once __DIR__ . '/../../backend/middleware/Auth.php';
require_once __DIR__ . '/../../backend/models/DocumentSpace.php';
require_once __DIR__ . '/../../backend/models/AdvancedFolder.php';
require_once __DIR__ . '/../../backend/models/DocumentVersion.php';

use Nexio\Models\DocumentSpace;
use Nexio\Models\AdvancedFolder;
use Nexio\Models\DocumentVersion;

// Autenticazione
$auth = new Auth();
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autenticato']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$userId = $auth->getUser()['id'];
$isSuperAdmin = $auth->isSuperAdmin();

header('Content-Type: application/json');

try {
    $documentSpace = DocumentSpace::getInstance();
    $advancedFolder = AdvancedFolder::getInstance();
    $documentVersion = DocumentVersion::getInstance();
    
    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'folder-contents':
                    $folderId = intval($_GET['folder_id'] ?? 0);
                    $includeTrash = filter_var($_GET['include_trash'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    
                    // Verifica permessi
                    $folder = $advancedFolder->getFolder($folderId);
                    if (!$folder) {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'message' => 'Cartella non trovata']);
                        exit;
                    }
                    
                    if (!$documentSpace->canAccessSpace($userId, $folder['id_spazio'], $isSuperAdmin)) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'message' => 'Accesso negato']);
                        exit;
                    }
                    
                    $contents = $advancedFolder->getFolderContents($folderId, $includeTrash);
                    $breadcrumb = $advancedFolder->getBreadcrumb($folderId);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => [
                            'folder' => $folder,
                            'contents' => $contents,
                            'breadcrumb' => $breadcrumb
                        ]
                    ]);
                    break;
                    
                case 'version-history':
                    $documentId = intval($_GET['document_id'] ?? 0);
                    
                    $history = $documentVersion->getVersionHistory($documentId);
                    echo json_encode(['success' => true, 'data' => $history]);
                    break;
                    
                case 'documents-near-revision':
                    $days = intval($_GET['days'] ?? 30);
                    $spaceId = $_GET['space_id'] ?? null;
                    
                    if ($spaceId && !$documentSpace->canAccessSpace($userId, $spaceId, $isSuperAdmin)) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'message' => 'Accesso negato']);
                        exit;
                    }
                    
                    $documents = $documentVersion->getDocumentsNearRevision($days, $spaceId);
                    echo json_encode(['success' => true, 'data' => $documents]);
                    break;
                    
                case 'trash-contents':
                    $spaceId = intval($_GET['space_id'] ?? 0);
                    
                    if (!$documentSpace->canAccessSpace($userId, $spaceId, $isSuperAdmin)) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'message' => 'Accesso negato']);
                        exit;
                    }
                    
                    $stmt = db_query("
                        SELECT c.*, u.nome AS nome_eliminatore
                        FROM cestino_documenti c
                        LEFT JOIN utenti u ON c.eliminato_da = u.id
                        WHERE c.id_spazio = ?
                        ORDER BY c.eliminato_il DESC
                    ", [$spaceId]);
                    
                    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($items as &$item) {
                        $item['dati_oggetto'] = json_decode($item['dati_oggetto'], true);
                    }
                    
                    echo json_encode(['success' => true, 'data' => $items]);
                    break;
                    
                default:
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Azione non valida']);
            }
            break;
            
        case 'POST':
            switch ($action) {
                case 'create-folder':
                    $data = json_decode(file_get_contents('php://input'), true);
                    $data['creata_da'] = $userId;
                    
                    // Verifica accesso spazio
                    if (!$documentSpace->canAccessSpace($userId, $data['id_spazio'], $isSuperAdmin)) {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'message' => 'Accesso negato']);
                        exit;
                    }
                    
                    $folderId = $advancedFolder->createFolder($data);
                    echo json_encode([
                        'success' => true,
                        'message' => 'Cartella creata con successo',
                        'data' => ['id' => $folderId]
                    ]);
                    break;
                    
                case 'upload-document':
                    if (empty($_FILES['file'])) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Nessun file caricato']);
                        exit;
                    }
                    
                    $data = [
                        'nome' => $_POST['nome'] ?? $_FILES['file']['name'],
                        'id_cartella' => intval($_POST['id_cartella']),
                        'descrizione' => $_POST['descrizione'] ?? null,
                        'tipo_documento' => $_POST['tipo_documento'] ?? 'documento',
                        'creato_da' => $userId,
                        'stato_workflow' => $_POST['stato_workflow'] ?? 'bozza'
                    ];
                    
                    // Metadati ISO se forniti
                    if (!empty($_POST['metadati_iso'])) {
                        $data['metadati_iso'] = json_decode($_POST['metadati_iso'], true);
                    }
                    
                    $documentId = $documentVersion->createDocument($data, $_FILES['file']);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Documento caricato con successo',
                        'data' => ['id' => $documentId]
                    ]);
                    break;
                    
                case 'add-version':
                    if (empty($_FILES['file'])) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Nessun file caricato']);
                        exit;
                    }
                    
                    $data = [
                        'id_documento' => intval($_POST['id_documento']),
                        'numero_versione' => $_POST['numero_versione'],
                        'file_data' => $_FILES['file'],
                        'note_versione' => $_POST['note_versione'] ?? null,
                        'stato_workflow' => $_POST['stato_workflow'] ?? 'bozza',
                        'caricato_da' => $userId
                    ];
                    
                    // Campi opzionali
                    foreach (['responsabile_revisione', 'data_revisione', 'prossima_revisione'] as $field) {
                        if (!empty($_POST[$field])) {
                            $data[$field] = $_POST[$field];
                        }
                    }
                    
                    $versionId = $documentVersion->addVersion($data);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Nuova versione aggiunta',
                        'data' => ['id' => $versionId]
                    ]);
                    break;
                    
                case 'move-to-trash':
                    $data = json_decode(file_get_contents('php://input'), true);
                    $type = $data['type'] ?? '';
                    $id = intval($data['id'] ?? 0);
                    
                    if ($type === 'folder') {
                        $advancedFolder->moveToTrash($id, $userId);
                        echo json_encode([
                            'success' => true,
                            'message' => 'Cartella spostata nel cestino'
                        ]);
                    } else {
                        // Sposta documento nel cestino
                        $stmt = db_query("SELECT * FROM documenti WHERE id = ?", [$id]);
                        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$doc) {
                            http_response_code(404);
                            echo json_encode(['success' => false, 'message' => 'Documento non trovato']);
                            exit;
                        }
                        
                        // Ottieni spazio dalla cartella
                        $stmt = db_query("SELECT id_spazio FROM cartelle WHERE id = ?", [$doc['id_cartella']]);
                        $spaceId = $stmt->fetchColumn();
                        
                        // Marca come eliminato
                        db_query("UPDATE documenti SET eliminato = 1 WHERE id = ?", [$id]);
                        
                        // Aggiungi al cestino
                        db_query("
                            INSERT INTO cestino_documenti (
                                tipo_oggetto, id_oggetto, id_spazio, dati_oggetto,
                                percorso_originale, eliminato_da, scadenza_cestino
                            ) VALUES (
                                'documento', ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY)
                            )
                        ", [$id, $spaceId, json_encode($doc), $doc['nome'], $userId]);
                        
                        echo json_encode([
                            'success' => true,
                            'message' => 'Documento spostato nel cestino'
                        ]);
                    }
                    break;
                    
                case 'restore-from-trash':
                    $data = json_decode(file_get_contents('php://input'), true);
                    $trashId = intval($data['id'] ?? 0);
                    
                    $advancedFolder->restoreFromTrash($trashId, $userId);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Elemento ripristinato con successo'
                    ]);
                    break;
                    
                case 'approve-version':
                    $data = json_decode(file_get_contents('php://input'), true);
                    $versionId = intval($data['version_id'] ?? 0);
                    
                    $documentVersion->approveVersion($versionId, $userId);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Versione approvata con successo'
                    ]);
                    break;
                    
                default:
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Azione non valida']);
            }
            break;
            
        case 'PUT':
            // Aggiornamenti vari
            break;
            
        case 'DELETE':
            if ($action === 'permanent-delete' && isset($_GET['id'])) {
                $trashId = intval($_GET['id']);
                
                // Solo super admin può eliminare definitivamente
                if (!$isSuperAdmin) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Solo i super admin possono eliminare definitivamente']);
                    exit;
                }
                
                // Elimina definitivamente dal cestino
                db_query("DELETE FROM cestino_documenti WHERE id = ?", [$trashId]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Elemento eliminato definitivamente'
                ]);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Azione non valida']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Metodo non permesso']);
    }
    
} catch (Exception $e) {
    error_log("Errore API documenti avanzati: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore server: ' . $e->getMessage()]);
}