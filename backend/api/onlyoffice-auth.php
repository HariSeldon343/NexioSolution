<?php
/**
 * API per autenticazione OnlyOffice Cloud
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/onlyoffice.config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $auth = Auth::getInstance();
    $auth->requireAuth();
    
    $user = $auth->getUser();
    $documento_id = $_GET['documento_id'] ?? $_POST['documento_id'] ?? null;
    $file_id = $_GET['file_id'] ?? $_POST['file_id'] ?? 'new_' . time();
    
    // Genera URL per OnlyOffice Cloud con parametri specifici
    $onlyoffice_base_url = $ONLYOFFICE_SERVER;
    
    // Determina il documento da aprire o creare
    $document_info = prepareDocumentForOnlyOffice($documento_id, $file_id, $user);
    
    // Costruisci URL di accesso diretto
    $access_url = buildOnlyOfficeAccessUrl($onlyoffice_base_url, $document_info);
    
    echo json_encode([
        'success' => true,
        'access_url' => $access_url,
        'document_info' => $document_info,
        'server' => $onlyoffice_base_url
    ]);
    
} catch (Exception $e) {
    error_log("OnlyOffice Auth Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function prepareDocumentForOnlyOffice($documento_id, $file_id, $user) {
    $document_info = [
        'id' => $file_id,
        'title' => 'Nuovo Documento',
        'fileType' => 'docx',
        'user_id' => $user['id'],
        'user_name' => $user['nome'] . ' ' . $user['cognome'],
        'permissions' => [
            'edit' => true,
            'download' => true,
            'print' => true,
            'review' => true
        ]
    ];
    
    if ($documento_id) {
        try {
            $stmt = db_query("SELECT * FROM documenti WHERE id = ?", [$documento_id]);
            $documento = $stmt->fetch();
            
            if ($documento) {
                $document_info['id'] = $documento_id;
                $document_info['title'] = $documento['titolo'] ?? 'Documento';
                $document_info['existing'] = true;
            }
        } catch (Exception $e) {
            // Usa valori di default se errore
        }
    }
    
    return $document_info;
}

function buildOnlyOfficeAccessUrl($base_url, $document_info) {
    // Costruisci URL per accesso diretto al tuo spazio OnlyOffice
    $params = [
        'action' => 'edit',
        'type' => 'desktop',
        'lang' => 'it'
    ];
    
    // URL del tuo spazio OnlyOffice Cloud
    $access_url = $base_url . '/products/files/';
    
    // Se hai un documento specifico, aggiungi parametri
    if (isset($document_info['existing']) && $document_info['existing']) {
        $access_url .= '?' . http_build_query($params);
    }
    
    return $access_url;
}
?>