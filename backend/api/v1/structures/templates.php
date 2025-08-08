<?php
/**
 * API Endpoint: Lista Template ISO Disponibili
 * GET /api/v1/structures/templates
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Metodo non consentito', 405);
    }

    // Parametri query
    $aziendaId = $_GET['azienda_id'] ?? $auth->getCurrentCompany();
    $includeDetails = filter_var($_GET['include_details'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $checkExisting = filter_var($_GET['check_existing'] ?? false, FILTER_VALIDATE_BOOLEAN);

    // Validazione azienda_id
    if ($aziendaId && !$auth->isSuperAdmin() && $auth->getCurrentCompany() !== (int)$aziendaId) {
        throw new Exception('Accesso negato all\'azienda specificata', 403);
    }

    // Inizializzazione manager
    $structureManager = ISOStructureManager::getInstance();
    
    // Ottieni template disponibili
    $templates = $structureManager->getAvailableTemplates();

    // Arricchimento dati se richiesto
    if ($includeDetails || $checkExisting) {
        foreach ($templates as $templateKey => &$template) {
            
            // Dettagli aggiuntivi
            if ($includeDetails) {
                $template['features'] = $this->getTemplateFeatures($templateKey);
                $template['benefits'] = $this->getTemplateBenefits($templateKey);
                $template['requirements'] = $this->getTemplateRequirements($templateKey);
                $template['implementation_time'] = $this->getImplementationTime($templateKey);
            }

            // Controllo strutture esistenti
            if ($checkExisting && $aziendaId) {
                $existing = db_query(
                    "SELECT id, nome, data_creazione FROM cartelle 
                     WHERE azienda_id = ? AND tipo_speciale = 'iso_main' 
                     AND descrizione LIKE ?",
                    [$aziendaId, '%' . $templateKey . '%']
                )->fetch();

                if ($existing) {
                    $template['existing_structure'] = [
                        'id' => $existing['id'],
                        'name' => $existing['nome'],
                        'created_at' => $existing['data_creazione'],
                        'status' => 'implemented'
                    ];

                    // Verifica conformità
                    $compliance = $structureManager->checkCompliance($aziendaId, $templateKey);
                    $template['compliance_status'] = [
                        'score' => $compliance['overall_score'],
                        'level' => $this->getComplianceLevel($compliance['overall_score']),
                        'missing_items' => count($compliance['missing_documents'])
                    ];
                } else {
                    $template['existing_structure'] = null;
                    $template['compliance_status'] = null;
                }
            }

            // Statistiche utilizzo (se super admin)
            if ($auth->isSuperAdmin()) {
                $usage = db_query(
                    "SELECT COUNT(*) FROM cartelle 
                     WHERE tipo_speciale = 'iso_main' AND descrizione LIKE ?",
                    ['%' . $templateKey . '%']
                )->fetchColumn();
                
                $template['usage_statistics'] = [
                    'total_implementations' => $usage,
                    'popularity_rank' => $this->getPopularityRank($templateKey)
                ];
            }
        }
    }

    // Ordinamento template per popolarità
    uasort($templates, function($a, $b) {
        $rankA = $a['usage_statistics']['popularity_rank'] ?? 999;
        $rankB = $b['usage_statistics']['popularity_rank'] ?? 999;
        return $rankA <=> $rankB;
    });

    // Preparazione risposta
    $response = [
        'success' => true,
        'data' => [
            'templates' => $templates,
            'total_templates' => count($templates),
            'available_modes' => [
                'separata' => [
                    'name' => 'Struttura Separata',
                    'description' => 'Crea una struttura dedicata solo per la norma selezionata',
                    'recommended_for' => 'Aziende che implementano una singola norma'
                ],
                'integrata' => [
                    'name' => 'Sistema Integrato',
                    'description' => 'Integra la norma in un sistema di gestione unificato',
                    'recommended_for' => 'Aziende con certificazioni multiple'
                ],
                'personalizzata' => [
                    'name' => 'Struttura Personalizzata',
                    'description' => 'Adatta la struttura alle specifiche esigenze aziendali',
                    'recommended_for' => 'Aziende con processi specifici'
                ]
            ]
        ],
        'metadata' => [
            'request_time' => date('c'),
            'include_details' => $includeDetails,
            'check_existing' => $checkExisting,
            'azienda_id' => $aziendaId
        ]
    ];

    // Log dell'operazione
    ActivityLogger::getInstance()->log('iso_templates_api_list', 'api_structures', null, [
        'azienda_id' => $aziendaId,
        'include_details' => $includeDetails,
        'check_existing' => $checkExisting,
        'templates_count' => count($templates),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
    ]);

    http_response_code(200);
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Log errore
    if (isset($auth)) {
        ActivityLogger::getInstance()->logError('Errore API structures/templates: ' . $e->getMessage(), [
            'query_params' => $_GET,
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

// Funzioni helper per arricchimento dati

function getTemplateFeatures($templateKey) {
    $features = [
        'ISO_9001' => [
            'Gestione documentale strutturata',
            'Controllo dei processi',
            'Audit interni',
            'Azioni correttive e preventive',
            'Riesame della direzione',
            'Gestione delle non conformità'
        ],
        'ISO_14001' => [
            'Analisi ambientale',
            'Gestione aspetti e impatti',
            'Controllo operativo ambientale',
            'Monitoraggio ambientale',
            'Gestione emergenze ambientali',
            'Conformità normativa'
        ],
        'ISO_45001' => [
            'Valutazione dei rischi SSL',
            'Controllo dei pericoli',
            'Gestione emergenze',
            'Sorveglianza sanitaria',
            'Formazione sicurezza',
            'Investigazione incidenti'
        ],
        'GDPR' => [
            'Registro trattamenti',
            'Valutazione impatto privacy',
            'Gestione diritti interessati',
            'Data breach management',
            'Privacy by design',
            'Trasferimenti internazionali'
        ]
    ];

    return $features[$templateKey] ?? [];
}

function getTemplateBenefits($templateKey) {
    $benefits = [
        'ISO_9001' => [
            'Miglioramento continuo dei processi',
            'Aumento soddisfazione clienti',
            'Riduzione sprechi e inefficienze',
            'Maggiore competitività sul mercato',
            'Accesso a nuovi mercati'
        ],
        'ISO_14001' => [
            'Riduzione impatto ambientale',
            'Risparmio energetico e risorse',
            'Conformità normativa garantita',
            'Miglioramento immagine aziendale',
            'Accesso a incentivi green'
        ],
        'ISO_45001' => [
            'Riduzione infortuni e malattie',
            'Diminuzione assenteismo',
            'Conformità normativa SSL',
            'Riduzione costi assicurativi',
            'Miglioramento clima aziendale'
        ],
        'GDPR' => [
            'Conformità normativa privacy',
            'Riduzione rischi sanzioni',
            'Aumento fiducia clienti',
            'Vantaggio competitivo',
            'Gestione efficace dati personali'
        ]
    ];

    return $benefits[$templateKey] ?? [];
}

function getTemplateRequirements($templateKey) {
    $requirements = [
        'ISO_9001' => [
            'Definizione processi aziendali',
            'Identificazione responsabilità',
            'Sistema di documentazione',
            'Procedure operative',
            'Registrazioni qualità'
        ],
        'ISO_14001' => [
            'Analisi ambientale iniziale',
            'Identificazione aspetti ambientali',
            'Obiettivi e traguardi ambientali',
            'Programmi ambientali',
            'Competenze ambientali'
        ],
        'ISO_45001' => [
            'Valutazione rischi SSL',
            'Consultazione lavoratori',
            'Competenze SSL',
            'Controllo operativo',
            'Preparazione emergenze'
        ],
        'GDPR' => [
            'Mapping flussi dati',
            'Identificazione basi giuridiche',
            'Valutazione rischi privacy',
            'Misure di sicurezza',
            'Procedure diritti interessati'
        ]
    ];

    return $requirements[$templateKey] ?? [];
}

function getImplementationTime($templateKey) {
    $times = [
        'ISO_9001' => '3-6 mesi',
        'ISO_14001' => '4-8 mesi', 
        'ISO_45001' => '3-6 mesi',
        'GDPR' => '2-4 mesi'
    ];

    return $times[$templateKey] ?? '3-6 mesi';
}

function getComplianceLevel($score) {
    if ($score >= 80) return 'Alto';
    if ($score >= 60) return 'Medio';
    if ($score >= 30) return 'Basso';
    return 'Insufficiente';
}

function getPopularityRank($templateKey) {
    // Simulazione ranking basato su utilizzo
    $ranks = [
        'ISO_9001' => 1,
        'GDPR' => 2,
        'ISO_45001' => 3,
        'ISO_14001' => 4
    ];

    return $ranks[$templateKey] ?? 5;
}
?>