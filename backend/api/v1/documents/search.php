<?php
/**
 * API Endpoint: Ricerca Documenti Avanzata
 * GET/POST /api/v1/documents/search
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../../middleware/Auth.php';
require_once '../../../utils/AdvancedSearchEngine.php';
require_once '../../../utils/ActivityLogger.php';

try {
    // Verifica autenticazione
    $auth = Auth::getInstance();
    $auth->requireAuth();

    // Verifica metodo HTTP
    if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
        throw new Exception('Metodo non consentito', 405);
    }

    // Lettura parametri di ricerca
    $searchCriteria = [];
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON non valido: ' . json_last_error_msg(), 400);
        }
        $searchCriteria = $input;
    } else {
        $searchCriteria = $_GET;
    }

    // Parametri di base richiesti
    $aziendaId = $searchCriteria['azienda_id'] ?? $auth->getCurrentCompany();
    if (!$aziendaId) {
        throw new Exception('ID azienda richiesto', 400);
    }

    // Verifica accesso all'azienda
    if (!$auth->isSuperAdmin() && $auth->getCurrentCompany() !== (int)$aziendaId) {
        throw new Exception('Accesso negato all\'azienda specificata', 403);
    }

    // Preparazione criteri di ricerca
    $criteria = [
        'azienda_id' => (int)$aziendaId,
        'query' => trim($searchCriteria['query'] ?? ''),
        'page' => max(1, (int)($searchCriteria['page'] ?? 1)),
        'limit' => min(100, max(10, (int)($searchCriteria['limit'] ?? 20)))
    ];

    // Filtri avanzati
    $advancedFilters = [
        'tipo_documento', 'norma_iso', 'stato', 'cartella_id', 
        'data_da', 'data_a', 'modificato_da', 'modificato_a',
        'creato_da', 'tags', 'contiene_dati_personali',
        'file_size_min', 'file_size_max', 'mime_type'
    ];

    foreach ($advancedFilters as $filter) {
        if (isset($searchCriteria[$filter]) && $searchCriteria[$filter] !== '') {
            $criteria[$filter] = $searchCriteria[$filter];
        }
    }

    // Opzioni di ordinamento
    $validOrderBy = ['data_creazione', 'ultima_modifica', 'titolo', 'dimensione', 'relevance'];
    $criteria['order_by'] = in_array($searchCriteria['order_by'] ?? '', $validOrderBy) 
        ? $searchCriteria['order_by'] 
        : 'data_creazione';
    
    $criteria['order_dir'] = strtoupper($searchCriteria['order_dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

    // Modalità di ricerca
    $searchMode = $searchCriteria['search_mode'] ?? 'standard';
    $validModes = ['standard', 'semantic', 'boolean'];
    
    if (!in_array($searchMode, $validModes)) {
        $searchMode = 'standard';
    }

    // Validazione filtri specifici
    if (isset($criteria['data_da']) && !validateDate($criteria['data_da'])) {
        throw new Exception('Formato data_da non valido (YYYY-MM-DD)', 400);
    }
    
    if (isset($criteria['data_a']) && !validateDate($criteria['data_a'])) {
        throw new Exception('Formato data_a non valido (YYYY-MM-DD)', 400);
    }

    if (isset($criteria['tags']) && is_string($criteria['tags'])) {
        $criteria['tags'] = explode(',', $criteria['tags']);
    }

    // Inizializzazione motore di ricerca
    $searchEngine = AdvancedSearchEngine::getInstance();
    
    // Esecuzione ricerca in base alla modalità
    $startTime = microtime(true);
    
    switch ($searchMode) {
        case 'semantic':
            $results = $searchEngine->semanticSearch($criteria['query'], $aziendaId, $criteria);
            break;
        case 'boolean':
            $criteria['boolean_mode'] = true;
            $results = $searchEngine->search($criteria);
            break;
        default:
            $results = $searchEngine->search($criteria);
    }
    
    $searchTime = microtime(true) - $startTime;

    // Arricchimento risultati con informazioni aggiuntive
    foreach ($results['documents'] as &$document) {
        // Informazioni di accesso
        $document['can_edit'] = $this->canEditDocument($document, $auth);
        $document['can_delete'] = $this->canDeleteDocument($document, $auth);
        
        // URL di accesso
        $document['view_url'] = "/documento.php?id=" . $document['id'];
        $document['edit_url'] = "/documento.php?id=" . $document['id'] . "&mode=edit";
        
        // Informazioni di versioning
        $document['version_count'] = $this->getVersionCount($document['id']);
        
        // Preview del contenuto
        if ($searchCriteria['include_preview'] ?? false) {
            $document['content_preview'] = $this->generateContentPreview($document['contenuto_html'], 200);
        }
        
        // Path completo della cartella
        if ($document['cartella_id']) {
            $document['folder_path'] = $this->getFolderPath($document['cartella_id']);
        }
    }

    // Faceted search - aggregazioni per filtri
    $facets = [];
    if ($searchCriteria['include_facets'] ?? false) {
        $facets = $this->generateFacets($aziendaId, $criteria);
    }

    // Suggerimenti di ricerca correlata
    $relatedSearches = [];
    if (!empty($criteria['query']) && ($searchCriteria['include_suggestions'] ?? false)) {
        $relatedSearches = $searchEngine->getSuggestions($criteria['query'], $aziendaId, 5);
    }

    // Salvataggio ricerca (se richiesto)
    if ($searchCriteria['save_search'] ?? false) {
        $searchId = $this->saveSearch($auth->getUser()['id'], $aziendaId, $criteria);
    }

    // Preparazione risposta
    $response = [
        'success' => true,
        'data' => [
            'documents' => $results['documents'],
            'pagination' => $results['pagination'],
            'search_metadata' => [
                'query' => $criteria['query'],
                'search_mode' => $searchMode,
                'execution_time' => round($searchTime, 3),
                'total_results' => $results['pagination']['total'],
                'filters_applied' => $results['filters_applied'] ?? [],
                'suggestions' => $results['suggestions'] ?? []
            ]
        ]
    ];

    // Dati aggiuntivi opzionali
    if (!empty($facets)) {
        $response['data']['facets'] = $facets;
    }
    
    if (!empty($relatedSearches)) {
        $response['data']['related_searches'] = $relatedSearches;
    }
    
    if (isset($searchId)) {
        $response['data']['saved_search_id'] = $searchId;
    }

    // Performance metrics (solo per super admin)
    if ($auth->isSuperAdmin() && ($searchCriteria['include_metrics'] ?? false)) {
        $response['data']['performance_metrics'] = [
            'database_queries' => $this->getQueryCount(),
            'memory_usage' => memory_get_peak_usage(true),
            'cache_hits' => $this->getCacheHits(),
            'index_usage' => $this->getIndexUsage()
        ];
    }

    // Log dell'operazione
    ActivityLogger::getInstance()->log('document_search_api', 'api_search', null, [
        'azienda_id' => $aziendaId,
        'query' => $criteria['query'],
        'search_mode' => $searchMode,
        'results_count' => count($results['documents']),
        'execution_time' => $searchTime,
        'filters' => array_keys($results['filters_applied'] ?? []),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
    ]);

    http_response_code(200);
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Log errore
    if (isset($auth)) {
        ActivityLogger::getInstance()->logError('Errore API documents/search: ' . $e->getMessage(), [
            'search_criteria' => $searchCriteria ?? null,
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

// Funzioni helper

function validateDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function canEditDocument($document, $auth) {
    // Logica di controllo permessi di modifica
    if ($auth->isSuperAdmin()) return true;
    if ($document['creato_da'] == $auth->getUser()['id']) return true;
    if ($document['responsabile_id'] == $auth->getUser()['id']) return true;
    
    // Verifica permessi specifici cartella
    return $this->checkFolderPermissions($document['cartella_id'], $auth->getUser()['id'], 'write');
}

function canDeleteDocument($document, $auth) {
    // Logica di controllo permessi di eliminazione
    if ($auth->isSuperAdmin()) return true;
    if ($document['creato_da'] == $auth->getUser()['id'] && $document['stato'] === 'bozza') return true;
    
    return $this->checkFolderPermissions($document['cartella_id'], $auth->getUser()['id'], 'delete');
}

function getVersionCount($documentId) {
    $stmt = db_query("SELECT COUNT(*) FROM documenti_versioni WHERE documento_id = ?", [$documentId]);
    return (int) $stmt->fetchColumn();
}

function generateContentPreview($html, $maxLength = 200) {
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/\s+/', ' ', trim($text));
    
    if (strlen($text) <= $maxLength) {
        return $text;
    }
    
    return substr($text, 0, $maxLength) . '...';
}

function getFolderPath($folderId) {
    $stmt = db_query("SELECT percorso_completo FROM cartelle WHERE id = ?", [$folderId]);
    return $stmt->fetchColumn() ?: '';
}

function generateFacets($aziendaId, $baseCriteria) {
    $facets = [];
    
    // Facet per tipo documento
    $stmt = db_query(
        "SELECT tipo_documento, COUNT(*) as count 
         FROM documenti_avanzati 
         WHERE azienda_id = ? 
         GROUP BY tipo_documento 
         ORDER BY count DESC",
        [$aziendaId]
    );
    $facets['tipo_documento'] = $stmt->fetchAll();
    
    // Facet per stato
    $stmt = db_query(
        "SELECT stato, COUNT(*) as count 
         FROM documenti_avanzati 
         WHERE azienda_id = ? 
         GROUP BY stato 
         ORDER BY count DESC",
        [$aziendaId]
    );
    $facets['stato'] = $stmt->fetchAll();
    
    // Facet per norma ISO
    $stmt = db_query(
        "SELECT norma_iso, COUNT(*) as count 
         FROM documenti_avanzati 
         WHERE azienda_id = ? AND norma_iso IS NOT NULL 
         GROUP BY norma_iso 
         ORDER BY count DESC",
        [$aziendaId]
    );
    $facets['norma_iso'] = $stmt->fetchAll();
    
    return $facets;
}

function checkFolderPermissions($folderId, $userId, $permission) {
    if (!$folderId) return false;
    
    $stmt = db_query(
        "SELECT permessi FROM permessi_cartelle 
         WHERE cartella_id = ? AND utente_id = ? AND FIND_IN_SET(?, permessi) > 0",
        [$folderId, $userId, $permission]
    );
    
    return $stmt->fetchColumn() !== false;
}

function saveSearch($userId, $aziendaId, $criteria) {
    $searchData = [
        'user_id' => $userId,
        'azienda_id' => $aziendaId,
        'name' => $criteria['search_name'] ?? 'Ricerca ' . date('d/m/Y H:i'),
        'criteria' => json_encode($criteria),
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    return db_insert('saved_searches', $searchData);
}

function getQueryCount() {
    // Implementazione tracking query
    return $_SESSION['query_count'] ?? 0;
}

function getCacheHits() {
    // Implementazione tracking cache
    return $_SESSION['cache_hits'] ?? 0;
}

function getIndexUsage() {
    // Implementazione tracking utilizzo indici
    return ['fulltext' => true, 'btree' => true];
}
?>