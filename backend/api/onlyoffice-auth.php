<?php
/**
 * OnlyOffice Authentication API
 * Fornisce configurazione e token per l'editor OnlyOffice
 */

require_once '../config/onlyoffice.config.php';
require_once '../config/config.php';
require_once '../middleware/Auth.php';

$auth = Auth::getInstance();
$auth->requireAuth();

header('Content-Type: application/json');

$docId = $_GET['doc'] ?? null;
if (!$docId) {
    http_response_code(400);
    echo json_encode(['error' => 'Document ID required']);
    exit;
}

// Verifica che il documento esista
$stmt = db_query("SELECT * FROM documenti WHERE id = ?", [$docId]);
$document = $stmt->fetch();

if (!$document) {
    http_response_code(404);
    echo json_encode(['error' => 'Document not found']);
    exit;
}

// Genera token e configurazione
$documentUrl = OnlyOfficeConfig::getDocumentUrl($docId);
$callbackUrl = OnlyOfficeConfig::getCallbackUrl($docId);
$publicDocumentUrl = OnlyOfficeConfig::getPublicDocumentUrl($docId);

// Informazioni debug utili
$response = [
    'success' => true,
    'documentId' => $docId,
    'documentName' => $document['nome'] ?? $document['filename'] ?? 'Documento',
    'documentUrl' => $documentUrl,
    'callbackUrl' => $callbackUrl,
    'publicDocumentUrl' => $publicDocumentUrl,
    'apiUrl' => OnlyOfficeConfig::getDocumentServerPublicUrl(),
    'environment' => OnlyOfficeConfig::isLocal() ? 'LOCAL' : 'PRODUCTION',
    'config' => [
        'fileServerPublic' => OnlyOfficeConfig::getFileServerPublicBase(),
        'fileServerInternal' => OnlyOfficeConfig::FILESERVER_INTERNAL_BASE,
        'documentServerUrl' => OnlyOfficeConfig::getDocumentServerPublicUrl(),
        'jwtEnabled' => OnlyOfficeConfig::JWT_ENABLED
    ],
    'debug' => [
        'host' => $_SERVER['HTTP_HOST'] ?? 'unknown',
        'isLocal' => OnlyOfficeConfig::isLocal(),
        'documentPath' => $document['percorso_file'] ?? null,
        'documentFilename' => $document['filename'] ?? null
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);