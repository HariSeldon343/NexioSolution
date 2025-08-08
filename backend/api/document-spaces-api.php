<?php

/**
 * Document Spaces API - Gestione avanzata spazi documentali
 * 
 * API completa per upload/download multipli, ricerca full-text ottimizzata,
 * gestione versioni documenti, metadata GDPR e cache intelligente.
 * 
 * @package Nexio\API
 * @version 2.0.0
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../middleware/Auth.php';
require_once __DIR__ . '/../utils/DocumentSpaceManager.php';
require_once __DIR__ . '/../utils/ActivityLogger.php';
require_once __DIR__ . '/../utils/RateLimiter.php';

use Nexio\Utils\DocumentSpaceManager;
use Nexio\Utils\ActivityLogger;
use Nexio\Utils\RateLimiter;

// Autenticazione
$auth = Auth::getInstance();
if (!$auth->checkSession()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Autenticazione richiesta']);
    exit;
}

$user = $auth->getUser();
$companyId = $auth->getCurrentCompany();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Inizializzazione servizi
$documentManager = DocumentSpaceManager::getInstance();
$logger = ActivityLogger::getInstance();
$rateLimiter = new RateLimiter();

try {
    // Rate limiting globale
    $rateLimiter->check(
        "document_api_{$user['id']}",
        'document_api_calls',
        200, // max 200 chiamate per ora
        3600
    );

    // Router delle azioni
    switch ($method) {
        case 'GET':
            handleGetRequest($action, $companyId, $user, $documentManager, $logger);
            break;
            
        case 'POST':
            handlePostRequest($action, $companyId, $user, $documentManager, $logger, $rateLimiter);
            break;
            
        case 'PUT':
            handlePutRequest($action, $companyId, $user, $documentManager, $logger);
            break;
            
        case 'DELETE':
            handleDeleteRequest($action, $companyId, $user, $documentManager, $logger);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Metodo non supportato']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Gestisce richieste GET
 */
function handleGetRequest($action, $companyId, $user, $documentManager, $logger)
{
    switch ($action) {
        case 'search':
            // Ricerca documenti avanzata
            $query = $_GET['q'] ?? '';
            $filters = [
                'folder_id' => $_GET['folder_id'] ?? null,
                'file_type' => $_GET['file_type'] ?? null,
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null,
                'created_by' => $_GET['created_by'] ?? null
            ];
            $options = [
                'limit' => (int)($_GET['limit'] ?? 50)
            ];

            $results = $documentManager->searchDocuments($companyId, $query, array_filter($filters), $options);
            echo json_encode($results);
            break;

        case 'versions':
            // Ottieni versioni documento
            $documentId = $_GET['document_id'] ?? '';
            if (empty($documentId)) {
                http_response_code(400);
                echo json_encode(['error' => 'ID documento richiesto']);
                return;
            }

            $versions = $documentManager->getDocumentVersions($companyId, $documentId);
            echo json_encode($versions);
            break;

        case 'cache-stats':
            // Statistiche cache (solo admin)
            if (!Auth::getInstance()->hasElevatedPrivileges()) {
                http_response_code(403);
                echo json_encode(['error' => 'Permessi insufficienti']);
                return;
            }

            $stats = $documentManager->getCacheStats();
            echo json_encode([
                'success' => true,
                'data' => $stats
            ]);
            break;

        case 'folder-documents':
            // Documenti in una cartella specifica
            $folderId = $_GET['folder_id'] ?? '';
            if (empty($folderId)) {
                http_response_code(400);
                echo json_encode(['error' => 'ID cartella richiesto']);
                return;
            }

            $documents = getFolderDocuments($companyId, $folderId);
            echo json_encode([
                'success' => true,
                'data' => $documents
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Azione non riconosciuta']);
    }
}

/**
 * Gestisce richieste POST
 */
function handlePostRequest($action, $companyId, $user, $documentManager, $logger, $rateLimiter)
{
    switch ($action) {
        case 'upload-multiple':
            // Upload multipli con progress tracking
            $rateLimiter->check(
                "upload_multiple_{$companyId}_{$user['id']}",
                'multiple_upload',
                10, // max 10 upload batch per ora
                3600
            );

            $folderId = $_POST['folder_id'] ?? '';
            $metadata = $_POST['metadata'] ?? [];
            
            if (empty($folderId) || empty($_FILES)) {
                http_response_code(400);
                echo json_encode(['error' => 'Folder ID e file richiesti']);
                return;
            }

            // Parse metadata se è stringa JSON
            if (is_string($metadata)) {
                $metadata = json_decode($metadata, true) ?? [];
            }

            $result = $documentManager->uploadMultipleFiles(
                $companyId,
                $folderId,
                $_FILES,
                $metadata,
                $user['id']
            );

            echo json_encode($result);
            break;

        case 'download-multiple':
            // Download multipli come ZIP
            $input = json_decode(file_get_contents('php://input'), true);
            $documentIds = $input['document_ids'] ?? [];

            if (empty($documentIds)) {
                http_response_code(400);
                echo json_encode(['error' => 'IDs documenti richiesti']);
                return;
            }

            $result = $documentManager->downloadMultipleFiles($companyId, $documentIds, $user['id']);
            
            // Avvia download del file ZIP
            if ($result['success']) {
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $result['zip_filename'] . '"');
                header('Content-Length: ' . $result['total_size']);
                readfile($result['zip_path']);
                
                // Cleanup file temporaneo
                unlink($result['zip_path']);
                exit;
            }

            echo json_encode($result);
            break;

        case 'create-version':
            // Crea nuova versione documento
            $documentId = $_POST['document_id'] ?? '';
            $versionNotes = $_POST['version_notes'] ?? '';
            
            if (empty($documentId) || empty($_FILES['file'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Document ID e file richiesti']);
                return;
            }

            $result = $documentManager->createDocumentVersion(
                $companyId,
                $documentId,
                $_FILES['file'],
                $versionNotes,
                $user['id']
            );

            echo json_encode($result);
            break;

        case 'clear-cache':
            // Pulisce cache (solo admin)
            if (!Auth::getInstance()->hasElevatedPrivileges()) {
                http_response_code(403);
                echo json_encode(['error' => 'Permessi insufficienti']);
                return;
            }

            $documentManager->clearCache();
            echo json_encode([
                'success' => true,
                'message' => 'Cache pulita con successo'
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Azione non riconosciuta']);
    }
}

/**
 * Gestisce richieste PUT
 */
function handlePutRequest($action, $companyId, $user, $documentManager, $logger)
{
    $input = json_decode(file_get_contents('php://input'), true);

    switch ($action) {
        case 'update-metadata':
            // Aggiorna metadata documento
            $documentId = $input['document_id'] ?? '';
            $metadata = $input['metadata'] ?? [];

            if (empty($documentId)) {
                http_response_code(400);
                echo json_encode(['error' => 'Document ID richiesto']);
                return;
            }

            $result = updateDocumentMetadata($companyId, $documentId, $metadata, $user['id']);
            echo json_encode($result);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Azione non riconosciuta']);
    }
}

/**
 * Gestisce richieste DELETE
 */
function handleDeleteRequest($action, $companyId, $user, $documentManager, $logger)
{
    $input = json_decode(file_get_contents('php://input'), true);

    switch ($action) {
        case 'delete-document':
            // Elimina documento
            $documentId = $input['document_id'] ?? '';
            
            if (empty($documentId)) {
                http_response_code(400);
                echo json_encode(['error' => 'Document ID richiesto']);
                return;
            }

            $result = deleteDocument($companyId, $documentId, $user['id']);
            echo json_encode($result);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Azione non riconosciuta']);
    }
}

/**
 * Ottieni documenti in una cartella
 */
function getFolderDocuments($companyId, $folderId)
{
    return db_query(
        "SELECT 
            d.*,
            u.nome || ' ' || u.cognome as creatore_nome,
            COUNT(dv.id) as total_versions
         FROM documenti d
         LEFT JOIN utenti u ON d.creato_da = u.id
         LEFT JOIN documenti_versioni dv ON d.id = dv.documento_id
         WHERE d.azienda_id = ? AND d.cartella_id = ? AND d.stato != 'eliminato'
         GROUP BY d.id
         ORDER BY d.data_aggiornamento DESC",
        [$companyId, $folderId]
    )->fetchAll();
}

/**
 * Aggiorna metadata documento
 */
function updateDocumentMetadata($companyId, $documentId, $metadata, $userId)
{
    try {
        db_begin_transaction();

        // Verifica documento
        $document = db_query(
            "SELECT * FROM documenti WHERE id = ? AND azienda_id = ?",
            [$documentId, $companyId]
        )->fetch();

        if (!$document) {
            throw new Exception("Documento non trovato");
        }

        // Aggiorna metadata
        $currentMetadata = json_decode($document['metadata_extended'], true) ?? [];
        $newMetadata = array_merge($currentMetadata, $metadata);

        db_update('documenti', [
            'metadata_extended' => json_encode($newMetadata),
            'data_aggiornamento' => date('Y-m-d H:i:s'),
            'aggiornato_da' => $userId
        ], 'id = ?', [$documentId]);

        ActivityLogger::getInstance()->log(
            'document_metadata_updated',
            'documenti',
            $documentId,
            ['updated_fields' => array_keys($metadata)]
        );

        db_commit();

        return ['success' => true, 'message' => 'Metadata aggiornati'];

    } catch (Exception $e) {
        db_rollback();
        throw $e;
    }
}

/**
 * Elimina documento
 */
function deleteDocument($companyId, $documentId, $userId)
{
    try {
        db_begin_transaction();

        // Verifica documento
        $document = db_query(
            "SELECT * FROM documenti WHERE id = ? AND azienda_id = ?",
            [$documentId, $companyId]
        )->fetch();

        if (!$document) {
            throw new Exception("Documento non trovato");
        }

        // Soft delete
        db_update('documenti', [
            'stato' => 'eliminato',
            'data_eliminazione' => date('Y-m-d H:i:s'),
            'eliminato_da' => $userId
        ], 'id = ?', [$documentId]);

        ActivityLogger::getInstance()->log(
            'document_deleted',
            'documenti',
            $documentId,
            ['titolo' => $document['titolo']]
        );

        db_commit();

        return ['success' => true, 'message' => 'Documento eliminato'];

    } catch (Exception $e) {
        db_rollback();
        throw $e;
    }
}

?>