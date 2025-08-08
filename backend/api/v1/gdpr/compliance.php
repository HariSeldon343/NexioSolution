<?php
/**
 * API Endpoint: GDPR Compliance Management
 * GET/POST /api/v1/gdpr/compliance
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../../middleware/Auth.php';
require_once '../../../models/AdvancedDocument.php';
require_once '../../../utils/ActivityLogger.php';

try {
    // Verifica autenticazione
    $auth = Auth::getInstance();
    $auth->requireAuth();

    // Parametri di base
    $aziendaId = $_GET['azienda_id'] ?? $_POST['azienda_id'] ?? $auth->getCurrentCompany();
    if (!$aziendaId) {
        throw new Exception('ID azienda richiesto', 400);
    }

    // Verifica accesso all'azienda
    if (!$auth->isSuperAdmin() && $auth->getCurrentCompany() !== (int)$aziendaId) {
        throw new Exception('Accesso negato all\'azienda specificata', 403);
    }

    $response = [];

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            $response = handleGetCompliance($aziendaId, $_GET);
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            $response = handlePostCompliance($aziendaId, $input, $auth);
            break;
            
        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true);
            $response = handlePutCompliance($aziendaId, $input, $auth);
            break;
            
        case 'DELETE':
            $input = json_decode(file_get_contents('php://input'), true);
            $response = handleDeleteCompliance($aziendaId, $input, $auth);
            break;
            
        default:
            throw new Exception('Metodo non consentito', 405);
    }

    // Log dell'operazione
    ActivityLogger::getInstance()->log('gdpr_compliance_api', 'api_gdpr', null, [
        'method' => $_SERVER['REQUEST_METHOD'],
        'azienda_id' => $aziendaId,
        'action' => $response['action'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
    ]);

    http_response_code($response['status_code'] ?? 200);
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Log errore
    if (isset($auth)) {
        ActivityLogger::getInstance()->logError('Errore API gdpr/compliance: ' . $e->getMessage(), [
            'method' => $_SERVER['REQUEST_METHOD'],
            'azienda_id' => $aziendaId ?? null,
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

/**
 * Gestisce richieste GET - Visualizzazione stato conformità
 */
function handleGetCompliance($aziendaId, $params) {
    $action = $params['action'] ?? 'dashboard';
    
    switch ($action) {
        case 'dashboard':
            return getGDPRDashboard($aziendaId);
            
        case 'data_inventory':
            return getDataInventory($aziendaId, $params);
            
        case 'risk_assessment':
            return getRiskAssessment($aziendaId);
            
        case 'rights_requests':
            return getRightsRequests($aziendaId, $params);
            
        case 'data_breaches':
            return getDataBreaches($aziendaId, $params);
            
        case 'compliance_report':
            return getComplianceReport($aziendaId, $params);
            
        default:
            throw new Exception('Azione non riconosciuta', 400);
    }
}

/**
 * Gestisce richieste POST - Creazione nuovi elementi
 */
function handlePostCompliance($aziendaId, $input, $auth) {
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'rights_request':
            return createRightsRequest($aziendaId, $input, $auth);
            
        case 'data_breach':
            return reportDataBreach($aziendaId, $input, $auth);
            
        case 'dpia':
            return createDPIA($aziendaId, $input, $auth);
            
        case 'consent_record':
            return recordConsent($aziendaId, $input, $auth);
            
        default:
            throw new Exception('Azione non riconosciuta', 400);
    }
}

/**
 * Gestisce richieste PUT - Aggiornamento elementi
 */
function handlePutCompliance($aziendaId, $input, $auth) {
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'update_rights_request':
            return updateRightsRequest($aziendaId, $input, $auth);
            
        case 'update_data_breach':
            return updateDataBreach($aziendaId, $input, $auth);
            
        case 'update_retention_policy':
            return updateRetentionPolicy($aziendaId, $input, $auth);
            
        default:
            throw new Exception('Azione non riconosciuta', 400);
    }
}

/**
 * Gestisce richieste DELETE - Right to be forgotten
 */
function handleDeleteCompliance($aziendaId, $input, $auth) {
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'forget_data':
            return forgetPersonalData($aziendaId, $input, $auth);
            
        case 'delete_expired_data':
            return deleteExpiredData($aziendaId, $input, $auth);
            
        default:
            throw new Exception('Azione non riconosciuta', 400);
    }
}

// Implementazioni specifiche

function getGDPRDashboard($aziendaId) {
    // Dashboard overview conformità GDPR
    $dashboard = [
        'compliance_score' => calculateComplianceScore($aziendaId),
        'data_subjects_count' => getDataSubjectsCount($aziendaId),
        'active_consents' => getActiveConsentsCount($aziendaId),
        'pending_requests' => getPendingRequestsCount($aziendaId),
        'documents_with_personal_data' => getDocumentsWithPersonalDataCount($aziendaId),
        'risk_level' => assessOverallRisk($aziendaId),
        'recent_activities' => getRecentGDPRActivities($aziendaId),
        'upcoming_deadlines' => getUpcomingDeadlines($aziendaId),
        'retention_status' => getRetentionStatus($aziendaId)
    ];
    
    return [
        'success' => true,
        'action' => 'dashboard',
        'data' => $dashboard
    ];
}

function getDataInventory($aziendaId, $params) {
    // Inventario dati personali
    $page = max(1, (int)($params['page'] ?? 1));
    $limit = min(100, max(10, (int)($params['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    // Query documenti con dati personali
    $sql = "
        SELECT 
            d.*,
            g.tipo_dati,
            g.periodo_conservazione,
            g.data_scadenza,
            u.nome AS creatore_nome,
            u.cognome AS creatore_cognome
        FROM documenti_avanzati d
        LEFT JOIN gdpr_data_tracking g ON d.id = g.documento_id
        LEFT JOIN utenti u ON d.creato_da = u.id
        WHERE d.azienda_id = ? AND d.contiene_dati_personali = 1
        ORDER BY d.data_creazione DESC
        LIMIT ? OFFSET ?
    ";
    
    $documents = db_query($sql, [$aziendaId, $limit, $offset])->fetchAll();
    
    // Count totale
    $total = db_query(
        "SELECT COUNT(*) FROM documenti_avanzati WHERE azienda_id = ? AND contiene_dati_personali = 1",
        [$aziendaId]
    )->fetchColumn();
    
    // Statistiche per tipo di dato
    $dataTypes = db_query(
        "SELECT tipo_dati, COUNT(*) as count 
         FROM gdpr_data_tracking 
         WHERE azienda_id = ? 
         GROUP BY tipo_dati",
        [$aziendaId]
    )->fetchAll();
    
    return [
        'success' => true,
        'action' => 'data_inventory',
        'data' => [
            'documents' => $documents,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int)$total,
                'pages' => ceil($total / $limit)
            ],
            'statistics' => [
                'total_documents' => (int)$total,
                'data_types' => $dataTypes,
                'expiring_soon' => getExpiringDataCount($aziendaId)
            ]
        ]
    ];
}

function getRiskAssessment($aziendaId) {
    // Valutazione rischi privacy
    $risks = [
        'high_risk_processing' => getHighRiskProcessing($aziendaId),
        'missing_legal_basis' => getMissingLegalBasis($aziendaId),
        'expired_consents' => getExpiredConsents($aziendaId),
        'cross_border_transfers' => getCrossBorderTransfers($aziendaId),
        'data_retention_violations' => getRetentionViolations($aziendaId),
        'security_gaps' => getSecurityGaps($aziendaId)
    ];
    
    $overallRisk = calculateRiskScore($risks);
    
    return [
        'success' => true,
        'action' => 'risk_assessment',
        'data' => [
            'overall_risk_score' => $overallRisk,
            'risk_level' => getRiskLevel($overallRisk),
            'risk_areas' => $risks,
            'recommendations' => generateRiskRecommendations($risks),
            'last_assessment' => getLastAssessmentDate($aziendaId)
        ]
    ];
}

function createRightsRequest($aziendaId, $input, $auth) {
    // Validazione dati richiesta
    $requiredFields = ['type', 'data_subject_name', 'data_subject_email', 'description'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Campo richiesto mancante: {$field}", 400);
        }
    }
    
    $validTypes = ['access', 'rectification', 'erasure', 'portability', 'restriction', 'objection'];
    if (!in_array($input['type'], $validTypes)) {
        throw new Exception('Tipo richiesta non valido', 400);
    }
    
    // Creazione richiesta
    $requestData = [
        'azienda_id' => $aziendaId,
        'type' => $input['type'],
        'data_subject_name' => $input['data_subject_name'],
        'data_subject_email' => $input['data_subject_email'],
        'description' => $input['description'],
        'legal_basis' => $input['legal_basis'] ?? null,
        'priority' => $input['priority'] ?? 'medium',
        'status' => 'pending',
        'received_date' => date('Y-m-d H:i:s'),
        'deadline_date' => date('Y-m-d H:i:s', strtotime('+30 days')),
        'assigned_to' => $auth->getUser()['id'],
        'created_by' => $auth->getUser()['id']
    ];
    
    $requestId = db_insert('gdpr_rights_requests', $requestData);
    
    // Log attività
    ActivityLogger::getInstance()->log('gdpr_rights_request_created', 'gdpr_rights_requests', $requestId, [
        'type' => $input['type'],
        'data_subject' => $input['data_subject_email']
    ]);
    
    return [
        'success' => true,
        'action' => 'rights_request',
        'status_code' => 201,
        'data' => [
            'request_id' => $requestId,
            'message' => 'Richiesta diritti interessato creata con successo',
            'deadline' => $requestData['deadline_date'],
            'next_steps' => [
                'verify_identity' => 'Verificare identità del richiedente',
                'locate_data' => 'Localizzare tutti i dati personali',
                'assess_request' => 'Valutare validità della richiesta',
                'prepare_response' => 'Preparare risposta entro 30 giorni'
            ]
        ]
    ];
}

function forgetPersonalData($aziendaId, $input, $auth) {
    // Validazione richiesta
    if (empty($input['document_ids']) && empty($input['data_subject_email'])) {
        throw new Exception('Specificare document_ids o data_subject_email', 400);
    }
    
    $reason = $input['reason'] ?? 'Right to be forgotten request';
    $documentManager = AdvancedDocument::getInstance();
    
    $processedDocuments = [];
    $errors = [];
    
    try {
        db_begin_transaction();
        
        // Ottieni documenti da processare
        $documents = [];
        if (!empty($input['document_ids'])) {
            $placeholders = str_repeat('?,', count($input['document_ids']) - 1) . '?';
            $documents = db_query(
                "SELECT id FROM documenti_avanzati 
                 WHERE id IN ($placeholders) AND azienda_id = ? AND contiene_dati_personali = 1",
                array_merge($input['document_ids'], [$aziendaId])
            )->fetchAll();
        }
        
        if (!empty($input['data_subject_email'])) {
            // Trova documenti contenenti email del soggetto
            $emailDocs = db_query(
                "SELECT d.id FROM documenti_avanzati d
                 JOIN gdpr_data_tracking g ON d.id = g.documento_id
                 WHERE d.azienda_id = ? AND d.contenuto_html LIKE ?",
                [$aziendaId, '%' . $input['data_subject_email'] . '%']
            )->fetchAll();
            
            $documents = array_merge($documents, $emailDocs);
        }
        
        // Processa ogni documento
        foreach ($documents as $doc) {
            try {
                $success = $documentManager->forgetData($doc['id'], $reason);
                if ($success) {
                    $processedDocuments[] = $doc['id'];
                }
            } catch (Exception $e) {
                $errors[] = [
                    'document_id' => $doc['id'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        // Registra richiesta di cancellazione
        $deletionRecord = [
            'azienda_id' => $aziendaId,
            'data_subject_email' => $input['data_subject_email'] ?? '',
            'documents_processed' => json_encode($processedDocuments),
            'reason' => $reason,
            'processed_by' => $auth->getUser()['id'],
            'processed_at' => date('Y-m-d H:i:s'),
            'verification_method' => $input['verification_method'] ?? 'manual'
        ];
        
        $deletionId = db_insert('gdpr_data_deletions', $deletionRecord);
        
        db_commit();
        
        return [
            'success' => true,
            'action' => 'forget_data',
            'data' => [
                'deletion_id' => $deletionId,
                'documents_processed' => count($processedDocuments),
                'documents_with_errors' => count($errors),
                'processed_document_ids' => $processedDocuments,
                'errors' => $errors,
                'message' => sprintf(
                    'Processati %d documenti, %d errori',
                    count($processedDocuments),
                    count($errors)
                )
            ]
        ];
        
    } catch (Exception $e) {
        db_rollback();
        throw $e;
    }
}

// Funzioni helper

function calculateComplianceScore($aziendaId) {
    $score = 0;
    $maxScore = 100;
    
    // Verifica presenza registro trattamenti
    $hasRegistry = db_query(
        "SELECT COUNT(*) FROM gdpr_processing_registry WHERE azienda_id = ?",
        [$aziendaId]
    )->fetchColumn() > 0;
    if ($hasRegistry) $score += 20;
    
    // Verifica DPIA per trattamenti ad alto rischio
    $highRiskCount = db_query(
        "SELECT COUNT(*) FROM gdpr_processing_registry WHERE azienda_id = ? AND risk_level = 'high'",
        [$aziendaId]
    )->fetchColumn();
    
    $dpiaCount = db_query(
        "SELECT COUNT(*) FROM gdpr_dpia WHERE azienda_id = ?",
        [$aziendaId]
    )->fetchColumn();
    
    if ($highRiskCount == 0 || $dpiaCount >= $highRiskCount) $score += 20;
    
    // Verifica gestione diritti interessati
    $hasRightsProcedures = db_query(
        "SELECT COUNT(*) FROM gdpr_rights_requests WHERE azienda_id = ?",
        [$aziendaId]
    )->fetchColumn() > 0;
    if ($hasRightsProcedures) $score += 15;
    
    // Verifica conformità retention
    $retentionCompliance = checkRetentionCompliance($aziendaId);
    $score += $retentionCompliance * 15 / 100;
    
    // Verifica misure di sicurezza
    $securityMeasures = checkSecurityMeasures($aziendaId);
    $score += $securityMeasures * 15 / 100;
    
    // Verifica formazione personale
    $trainingCompliance = checkTrainingCompliance($aziendaId);
    $score += $trainingCompliance * 15 / 100;
    
    return min($maxScore, round($score, 1));
}

function getDataSubjectsCount($aziendaId) {
    // Conta soggetti interessati unici
    return db_query(
        "SELECT COUNT(DISTINCT data_subject_email) FROM gdpr_rights_requests WHERE azienda_id = ?",
        [$aziendaId]
    )->fetchColumn();
}

function getActiveConsentsCount($aziendaId) {
    return db_query(
        "SELECT COUNT(*) FROM gdpr_consents 
         WHERE azienda_id = ? AND status = 'active' AND (expires_at IS NULL OR expires_at > NOW())",
        [$aziendaId]
    )->fetchColumn();
}

function getPendingRequestsCount($aziendaId) {
    return db_query(
        "SELECT COUNT(*) FROM gdpr_rights_requests WHERE azienda_id = ? AND status = 'pending'",
        [$aziendaId]
    )->fetchColumn();
}

function getDocumentsWithPersonalDataCount($aziendaId) {
    return db_query(
        "SELECT COUNT(*) FROM documenti_avanzati WHERE azienda_id = ? AND contiene_dati_personali = 1",
        [$aziendaId]
    )->fetchColumn();
}

function assessOverallRisk($aziendaId) {
    // Valutazione semplificata del rischio
    $riskFactors = 0;
    
    // Alto volume dati personali
    $personalDataCount = getDocumentsWithPersonalDataCount($aziendaId);
    if ($personalDataCount > 100) $riskFactors++;
    
    // Richieste diritti in sospeso
    $pendingRequests = getPendingRequestsCount($aziendaId);
    if ($pendingRequests > 5) $riskFactors++;
    
    // Dati in scadenza
    $expiringData = getExpiringDataCount($aziendaId);
    if ($expiringData > 10) $riskFactors++;
    
    if ($riskFactors >= 2) return 'alto';
    if ($riskFactors == 1) return 'medio';
    return 'basso';
}

function getExpiringDataCount($aziendaId, $days = 30) {
    return db_query(
        "SELECT COUNT(*) FROM gdpr_data_tracking 
         WHERE azienda_id = ? AND data_scadenza <= DATE_ADD(NOW(), INTERVAL ? DAY)",
        [$aziendaId, $days]
    )->fetchColumn();
}

function checkRetentionCompliance($aziendaId) {
    $total = db_query(
        "SELECT COUNT(*) FROM gdpr_data_tracking WHERE azienda_id = ?",
        [$aziendaId]
    )->fetchColumn();
    
    if ($total == 0) return 100;
    
    $compliant = db_query(
        "SELECT COUNT(*) FROM gdpr_data_tracking 
         WHERE azienda_id = ? AND (data_scadenza IS NULL OR data_scadenza > NOW())",
        [$aziendaId]
    )->fetchColumn();
    
    return round(($compliant / $total) * 100, 1);
}

function checkSecurityMeasures($aziendaId) {
    // Verifica misure di sicurezza implementate
    // Questo è un esempio semplificato
    return 75; // 75% delle misure implementate
}

function checkTrainingCompliance($aziendaId) {
    // Verifica formazione privacy del personale
    // Questo è un esempio semplificato
    return 80; // 80% del personale formato
}

function getRecentGDPRActivities($aziendaId, $limit = 10) {
    return db_query(
        "SELECT 'rights_request' as type, description, created_at as date
         FROM gdpr_rights_requests 
         WHERE azienda_id = ?
         UNION
         SELECT 'data_deletion' as type, reason as description, processed_at as date
         FROM gdpr_data_deletions 
         WHERE azienda_id = ?
         ORDER BY date DESC 
         LIMIT ?",
        [$aziendaId, $aziendaId, $limit]
    )->fetchAll();
}

function getUpcomingDeadlines($aziendaId, $days = 7) {
    return db_query(
        "SELECT 'rights_request' as type, description, deadline_date
         FROM gdpr_rights_requests 
         WHERE azienda_id = ? AND status = 'pending' 
         AND deadline_date <= DATE_ADD(NOW(), INTERVAL ? DAY)
         UNION
         SELECT 'data_retention' as type, CONCAT('Scadenza ritenzione: ', tipo_dati) as description, data_scadenza as deadline_date
         FROM gdpr_data_tracking 
         WHERE azienda_id = ? AND data_scadenza <= DATE_ADD(NOW(), INTERVAL ? DAY)
         ORDER BY deadline_date ASC",
        [$aziendaId, $days, $aziendaId, $days]
    )->fetchAll();
}

function getRetentionStatus($aziendaId) {
    $total = getDocumentsWithPersonalDataCount($aziendaId);
    $expiring = getExpiringDataCount($aziendaId, 30);
    $expired = getExpiringDataCount($aziendaId, 0);
    
    return [
        'total_documents' => $total,
        'expiring_soon' => $expiring,
        'expired' => $expired,
        'compliance_rate' => $total > 0 ? round((($total - $expired) / $total) * 100, 1) : 100
    ];
}
?>