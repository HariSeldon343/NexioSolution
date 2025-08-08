<?php
/**
 * API Endpoint: Creazione Strutture ISO
 * POST /api/v1/structures/create
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../../middleware/Auth.php';
require_once '../../../utils/ISOStructureManager.php';
require_once '../../../utils/ActivityLogger.php';

try {
    // Verifica autenticazione
    $auth = Auth::getInstance();
    $auth->requireAuth();

    // Verifica metodo HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Metodo non consentito', 405);
    }

    // Verifica permessi
    if (!$auth->hasElevatedPrivileges()) {
        throw new Exception('Permessi insufficienti per creare strutture ISO', 403);
    }

    // Lettura dati request
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON non valido: ' . json_last_error_msg(), 400);
    }

    // Validazione parametri richiesti
    $requiredFields = ['template_name', 'azienda_id'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Campo richiesto mancante: {$field}", 400);
        }
    }

    // Validazione azienda_id
    $aziendaId = (int) $input['azienda_id'];
    if ($aziendaId <= 0) {
        throw new Exception('ID azienda non valido', 400);
    }

    // Verifica accesso all'azienda
    $currentCompany = $auth->getCurrentCompany();
    if ($currentCompany !== $aziendaId && !$auth->isSuperAdmin()) {
        throw new Exception('Accesso negato all\'azienda specificata', 403);
    }

    // Validazione template
    $templateName = trim($input['template_name']);
    $validTemplates = ['ISO_9001', 'ISO_14001', 'ISO_45001', 'GDPR'];
    
    if (!in_array($templateName, $validTemplates)) {
        throw new Exception('Template non valido. Valori consentiti: ' . implode(', ', $validTemplates), 400);
    }

    // Opzioni per la creazione
    $options = [
        'mode' => $input['mode'] ?? 'integrata', // separata, integrata, personalizzata
        'create_templates' => $input['create_templates'] ?? false,
        'create_classifications' => $input['create_classifications'] ?? true,
        'parent_folder_id' => $input['parent_folder_id'] ?? null
    ];

    // Validazione modalità
    $validModes = ['separata', 'integrata', 'personalizzata'];
    if (!in_array($options['mode'], $validModes)) {
        throw new Exception('Modalità non valida. Valori consentiti: ' . implode(', ', $validModes), 400);
    }

    // Verifica se struttura già esistente
    $existingCheck = db_query(
        "SELECT COUNT(*) FROM cartelle 
         WHERE azienda_id = ? AND tipo_speciale = 'iso_main' 
         AND descrizione LIKE ?",
        [$aziendaId, '%' . $templateName . '%']
    )->fetchColumn();

    if ($existingCheck > 0 && !($input['force_recreate'] ?? false)) {
        throw new Exception('Struttura ISO già esistente per questa azienda. Utilizzare force_recreate=true per ricreare.', 409);
    }

    // Inizializzazione manager
    $structureManager = ISOStructureManager::getInstance();
    
    // Creazione struttura
    $startTime = microtime(true);
    $result = $structureManager->createStructure($aziendaId, $templateName, $options);
    $executionTime = microtime(true) - $startTime;

    // Verifica conformità iniziale
    $complianceCheck = $structureManager->checkCompliance($aziendaId, $templateName);

    // Preparazione risposta
    $response = [
        'success' => true,
        'message' => 'Struttura ISO creata con successo',
        'data' => [
            'structure_id' => $result['main_folder_id'],
            'template' => $templateName,
            'mode' => $options['mode'],
            'folders_created' => count($result['folders_created']),
            'documents_created' => count($result['documents_created']),
            'classifications_created' => count($result['classifications_created'] ?? []),
            'execution_time' => round($executionTime, 3)
        ],
        'structure_details' => $result,
        'compliance_status' => $complianceCheck,
        'next_steps' => [
            'review_structure' => 'Rivedi la struttura creata e personalizza secondo le esigenze',
            'add_documents' => 'Aggiungi i documenti specifici per ogni sezione',
            'assign_responsibilities' => 'Assegna responsabilità per la gestione dei documenti',
            'configure_workflows' => 'Configura i workflow di approvazione'
        ],
        'recommendations' => $complianceCheck['recommendations'] ?? []
    ];

    // Log dell'operazione
    ActivityLogger::getInstance()->log('iso_structure_api_create', 'api_structures', $result['main_folder_id'], [
        'template' => $templateName,
        'azienda_id' => $aziendaId,
        'mode' => $options['mode'],
        'execution_time' => $executionTime,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
    ]);

    http_response_code(201);
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Log errore
    if (isset($auth)) {
        ActivityLogger::getInstance()->logError('Errore API structures/create: ' . $e->getMessage(), [
            'input' => $input ?? null,
            'user_id' => $auth->getUser()['id'],
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
    }

    // Risposta errore
    $statusCode = $e->getCode() ?: 500;
    if ($statusCode < 400 || $statusCode >= 600) {
        $statusCode = 500;
    }

    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => $statusCode,
            'message' => $e->getMessage(),
            'type' => get_class($e)
        ],
        'timestamp' => date('c')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>