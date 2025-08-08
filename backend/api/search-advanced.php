<?php
/**
 * Advanced Search API
 * 
 * API per ricerca full-text avanzata con filtri ISO e ranking intelligente
 * 
 * @package Nexio\API
 * @version 1.0.0
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../middleware/Auth.php';
require_once __DIR__ . '/../utils/AdvancedSearchEngine.php';
require_once __DIR__ . '/../utils/ActivityLogger.php';
require_once __DIR__ . '/../utils/ModulesHelper.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

use Nexio\Utils\ActivityLogger;
// ModulesHelper already included above

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Autenticazione
$auth = Auth::getInstance();
if (!$auth->checkSession()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non autenticato']);
    exit;
}

// Verifica modulo gestione documentale
ModulesHelper::requireModule('gestione_documentale');

$user = $auth->getUser();
$currentAzienda = $auth->getCurrentAzienda();
$aziendaId = $currentAzienda['azienda_id'];

if (!$aziendaId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Azienda non selezionata']);
    exit;
}

$searchEngine = AdvancedSearchEngine::getInstance();
$logger = ActivityLogger::getInstance();

try {
    // Parsing parametri ricerca
    $searchMode = $_REQUEST['mode'] ?? 'standard'; // standard, semantic, suggestions
    
    switch ($searchMode) {
        case 'suggestions':
            handleSuggestions();
            break;
            
        case 'semantic':
            handleSemanticSearch();
            break;
            
        case 'export':
            handleExportSearch();
            break;
            
        default:
            handleStandardSearch();
            break;
    }
    
} catch (Exception $e) {
    error_log("Advanced Search Error: " . $e->getMessage());
    
    $logger->logError('Errore ricerca avanzata', [
        'error' => $e->getMessage(),
        'azienda_id' => $aziendaId,
        'user_id' => $user['id'],
        'search_mode' => $searchMode ?? 'unknown'
    ]);
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'error_code' => 'SEARCH_ERROR'
    ]);
}

/**
 * Gestisce ricerca standard avanzata
 */
function handleStandardSearch(): void
{
    global $searchEngine, $aziendaId, $logger, $user;
    
    // Costruisci criteri di ricerca
    $criteria = buildSearchCriteria();
    $criteria['azienda_id'] = $aziendaId;
    
    // Log ricerca
    $logger->log('ricerca_avanzata_eseguita', 'search_log', null, [
        'azienda_id' => $aziendaId,
        'user_id' => $user['id'],
        'query' => $criteria['query'] ?? '',
        'filters_count' => count(array_filter($criteria))
    ]);
    
    // Esegui ricerca
    $results = $searchEngine->search($criteria);
    
    // Aggiungi statistiche aggiuntive
    $results['search_stats'] = [
        'query_complexity' => calculateQueryComplexity($criteria),
        'filters_applied' => count($results['filters_applied']),
        'user_id' => $user['id'],
        'timestamp' => date('c')
    ];
    
    // Aggiungi azioni suggerite
    $results['suggested_actions'] = generateSuggestedActions($results, $criteria);
    
    echo json_encode([
        'success' => true,
        'data' => $results
    ]);
}

/**
 * Gestisce ricerca semantica
 */
function handleSemanticSearch(): void
{
    global $searchEngine, $aziendaId, $logger, $user;
    
    $query = $_REQUEST['query'] ?? '';
    if (empty($query)) {
        throw new Exception('Query di ricerca richiesta per la ricerca semantica');
    }
    
    $options = [
        'expand_synonyms' => ($_REQUEST['expand_synonyms'] ?? 'true') === 'true',
        'include_related' => ($_REQUEST['include_related'] ?? 'true') === 'true',
        'semantic_threshold' => floatval($_REQUEST['semantic_threshold'] ?? 0.6),
        'limit' => intval($_REQUEST['limit'] ?? 20),
        'page' => intval($_REQUEST['page'] ?? 1)
    ];
    
    // Log ricerca semantica
    $logger->log('ricerca_semantica_eseguita', 'search_log', null, [
        'azienda_id' => $aziendaId,
        'user_id' => $user['id'],
        'query' => $query,
        'options' => $options
    ]);
    
    // Esegui ricerca semantica
    $results = $searchEngine->semanticSearch($query, $aziendaId, $options);
    
    // Aggiungi analisi semantica
    $results['semantic_analysis'] = [
        'query_intent' => analyzeQueryIntent($query),
        'extracted_entities' => extractQueryEntities($query),
        'related_concepts' => findRelatedConcepts($query, $aziendaId),
        'confidence_score' => calculateSemanticConfidence($results['documents'])
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $results
    ]);
}

/**
 * Gestisce suggerimenti di ricerca
 */
function handleSuggestions(): void
{
    global $searchEngine, $aziendaId;
    
    $query = $_REQUEST['query'] ?? '';
    $limit = intval($_REQUEST['limit'] ?? 10);
    $type = $_REQUEST['type'] ?? 'all'; // all, terms, documents, categories
    
    if (strlen($query) < 2) {
        echo json_encode([
            'success' => true,
            'data' => [
                'terms' => [],
                'documents' => [],
                'categories' => [],
                'recent_searches' => getRecentSearches($aziendaId)
            ]
        ]);
        return;
    }
    
    $suggestions = $searchEngine->getSuggestions($query, $aziendaId, $limit);
    
    // Aggiungi suggerimenti contestuali
    if ($type === 'all' || $type === 'contextual') {
        $suggestions['contextual'] = getContextualSuggestions($query, $aziendaId);
    }
    
    // Aggiungi ricerche popolari
    if ($type === 'all' || $type === 'popular') {
        $suggestions['popular'] = getPopularSearches($aziendaId, $limit);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $suggestions
    ]);
}

/**
 * Gestisce export risultati ricerca
 */
function handleExportSearch(): void
{
    global $searchEngine, $aziendaId, $logger, $user;
    
    $format = $_REQUEST['format'] ?? 'json'; // json, csv, excel
    $criteria = buildSearchCriteria();
    $criteria['azienda_id'] = $aziendaId;
    $criteria['limit'] = 1000; // Limite per export
    
    // Esegui ricerca per export
    $results = $searchEngine->search($criteria);
    
    $logger->log('export_ricerca_eseguito', 'search_log', null, [
        'azienda_id' => $aziendaId,
        'user_id' => $user['id'],
        'format' => $format,
        'results_count' => count($results['documents'])
    ]);
    
    switch ($format) {
        case 'csv':
            exportToCSV($results['documents']);
            break;
            
        case 'excel':
            exportToExcel($results['documents']);
            break;
            
        default:
            exportToJSON($results);
            break;
    }
}

/**
 * Costruisce criteri di ricerca dai parametri
 */
function buildSearchCriteria(): array
{
    $criteria = [];
    
    // Query testuale
    if (!empty($_REQUEST['query'])) {
        $criteria['query'] = trim($_REQUEST['query']);
    }
    
    // Filtri base
    $filters = [
        'tipo_documento', 'norma_iso', 'stato', 'cartella_id', 
        'classificazione_id', 'creato_da', 'data_da', 'data_a',
        'modificato_da', 'modificato_a', 'file_size_min', 'file_size_max',
        'mime_type', 'contiene_dati_personali'
    ];
    
    foreach ($filters as $filter) {
        if (!empty($_REQUEST[$filter])) {
            $value = $_REQUEST[$filter];
            
            // Gestione array per filtri multipli
            if (in_array($filter, ['tipo_documento', 'norma_iso', 'stato']) && strpos($value, ',') !== false) {
                $criteria[$filter] = explode(',', $value);
            } else {
                $criteria[$filter] = $value;
            }
        }
    }
    
    // Tags
    if (!empty($_REQUEST['tags'])) {
        $criteria['tags'] = explode(',', $_REQUEST['tags']);
    }
    
    // Ordinamento
    $criteria['order_by'] = $_REQUEST['order_by'] ?? 'data_creazione';
    $criteria['order_dir'] = $_REQUEST['order_dir'] ?? 'DESC';
    
    // Paginazione
    $criteria['page'] = intval($_REQUEST['page'] ?? 1);
    $criteria['limit'] = intval($_REQUEST['limit'] ?? 20);
    
    // Opzioni avanzate
    $criteria['include_content'] = ($_REQUEST['include_content'] ?? 'false') === 'true';
    $criteria['highlight_terms'] = ($_REQUEST['highlight_terms'] ?? 'true') === 'true';
    $criteria['fuzzy_search'] = ($_REQUEST['fuzzy_search'] ?? 'false') === 'true';
    
    return $criteria;
}

/**
 * Calcola complessità della query
 */
function calculateQueryComplexity(array $criteria): string
{
    $complexity = 0;
    
    // Query testuale
    if (!empty($criteria['query'])) {
        $words = str_word_count($criteria['query']);
        $complexity += min($words * 2, 10);
    }
    
    // Filtri applicati
    $filterCount = count(array_filter($criteria, function($key) {
        return !in_array($key, ['azienda_id', 'page', 'limit', 'order_by', 'order_dir']);
    }, ARRAY_FILTER_USE_KEY));
    
    $complexity += $filterCount * 3;
    
    // Classificazione
    if ($complexity < 5) return 'simple';
    if ($complexity < 15) return 'medium';
    return 'complex';
}

/**
 * Genera azioni suggerite basate sui risultati
 */
function generateSuggestedActions(array $results, array $criteria): array
{
    $actions = [];
    
    // Se pochi risultati, suggerisci di allargare la ricerca
    if (count($results['documents']) < 5) {
        $actions[] = [
            'type' => 'broaden_search',
            'title' => 'Allarga la ricerca',
            'description' => 'Prova a rimuovere alcuni filtri o utilizzare termini più generali',
            'icon' => 'expand'
        ];
    }
    
    // Se molti risultati, suggerisci di raffinare
    if (count($results['documents']) > 50) {
        $actions[] = [
            'type' => 'refine_search',
            'title' => 'Raffina la ricerca',
            'description' => 'Aggiungi filtri specifici per ridurre i risultati',
            'icon' => 'filter'
        ];
    }
    
    // Suggerisci export se ci sono risultati
    if (count($results['documents']) > 0) {
        $actions[] = [
            'type' => 'export_results',
            'title' => 'Esporta risultati',
            'description' => 'Scarica i risultati in formato CSV o Excel',
            'icon' => 'download'
        ];
    }
    
    // Suggerisci download multiplo se più documenti
    if (count($results['documents']) > 1) {
        $actions[] = [
            'type' => 'download_multiple',
            'title' => 'Download multiplo',
            'description' => 'Scarica tutti i documenti in un file ZIP',
            'icon' => 'archive'
        ];
    }
    
    // Suggerisci salvataggio ricerca se è complessa
    if (calculateQueryComplexity($criteria) !== 'simple') {
        $actions[] = [
            'type' => 'save_search',
            'title' => 'Salva ricerca',
            'description' => 'Salva questa ricerca per riutilizzarla',
            'icon' => 'bookmark'
        ];
    }
    
    return $actions;
}

/**
 * Analizza l'intento della query
 */
function analyzeQueryIntent(string $query): array
{
    $intent = ['primary' => 'search', 'confidence' => 0.8, 'secondary' => []];
    
    $query = strtolower($query);
    
    // Intent patterns
    $patterns = [
        'find' => ['trova', 'cerca', 'dove', 'localizza'],
        'compare' => ['confronta', 'differenza', 'vs', 'versus'],
        'list' => ['lista', 'elenco', 'tutti', 'mostra'],
        'recent' => ['recente', 'ultimo', 'nuovo', 'aggiornato'],
        'process' => ['procedura', 'processo', 'come', 'step'],
        'audit' => ['audit', 'verifica', 'controllo', 'conformità']
    ];
    
    foreach ($patterns as $intentType => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($query, $keyword) !== false) {
                $intent['primary'] = $intentType;
                $intent['confidence'] = 0.9;
                break 2;
            }
        }
    }
    
    return $intent;
}

/**
 * Estrae entità dalla query
 */
function extractQueryEntities(string $query): array
{
    $entities = [];
    
    // Date
    if (preg_match_all('/\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4}/', $query, $matches)) {
        $entities['dates'] = array_unique($matches[0]);
    }
    
    // Codici documento
    if (preg_match_all('/[A-Z]{2,}-\d{4}-\d{4}/', $query, $matches)) {
        $entities['document_codes'] = array_unique($matches[0]);
    }
    
    // Norme ISO
    if (preg_match_all('/ISO\s*\d+/', $query, $matches)) {
        $entities['iso_standards'] = array_unique($matches[0]);
    }
    
    return $entities;
}

/**
 * Trova concetti correlati
 */
function findRelatedConcepts(string $query, int $aziendaId): array
{
    // Usa il motore di ricerca per trovare documenti simili
    $searchEngine = AdvancedSearchEngine::getInstance();
    
    $relatedSearch = $searchEngine->search([
        'query' => $query,
        'azienda_id' => $aziendaId,
        'limit' => 10,
        'semantic_mode' => true
    ]);
    
    // Estrai concetti dai risultati
    $concepts = [];
    foreach ($relatedSearch['documents'] as $doc) {
        $keywords = json_decode($doc['keywords'] ?? '[]', true);
        $concepts = array_merge($concepts, array_slice($keywords, 0, 3));
    }
    
    return array_unique($concepts);
}

/**
 * Calcola confidence score semantico
 */
function calculateSemanticConfidence(array $documents): float
{
    if (empty($documents)) return 0.0;
    
    $totalScore = 0;
    $count = 0;
    
    foreach ($documents as $doc) {
        if (isset($doc['semantic_score'])) {
            $totalScore += $doc['semantic_score'];
            $count++;
        }
    }
    
    return $count > 0 ? round($totalScore / $count, 2) : 0.5;
}

/**
 * Ottieni ricerche recenti dell'utente
 */
function getRecentSearches(int $aziendaId): array
{
    global $user;
    
    $stmt = db_query(
        "SELECT DISTINCT query_text 
         FROM search_log 
         WHERE user_id = ? AND azienda_id = ? AND query_text != ''
         ORDER BY timestamp DESC 
         LIMIT 5",
        [$user['id'], $aziendaId]
    );
    
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Ottieni suggerimenti contestuali
 */
function getContextualSuggestions(string $query, int $aziendaId): array
{
    // Suggerimenti basati sul contesto della query
    $suggestions = [];
    
    $query = strtolower($query);
    
    // Suggerimenti per ISO
    if (strpos($query, 'iso') !== false || strpos($query, 'qualità') !== false) {
        $suggestions[] = 'manuale qualità';
        $suggestions[] = 'procedura operativa';
        $suggestions[] = 'audit interno';
    }
    
    // Suggerimenti per sicurezza
    if (strpos($query, 'sicurezza') !== false || strpos($query, 'rischio') !== false) {
        $suggestions[] = 'valutazione rischi';
        $suggestions[] = 'piano emergenza';
        $suggestions[] = 'DPI';
    }
    
    // Suggerimenti per ambiente
    if (strpos($query, 'ambiente') !== false || strpos($query, 'rifiuti') !== false) {
        $suggestions[] = 'gestione rifiuti';
        $suggestions[] = 'impatto ambientale';
        $suggestions[] = 'autorizzazioni';
    }
    
    return array_unique($suggestions);
}

/**
 * Ottieni ricerche popolari
 */
function getPopularSearches(int $aziendaId, int $limit): array
{
    $stmt = db_query(
        "SELECT query_text, COUNT(*) as frequency 
         FROM search_log 
         WHERE azienda_id = ? AND query_text != '' AND timestamp > DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP BY query_text 
         ORDER BY frequency DESC 
         LIMIT ?",
        [$aziendaId, $limit]
    );
    
    return $stmt->fetchAll();
}

/**
 * Export risultati in formato CSV
 */
function exportToCSV(array $documents): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="nexio_search_results_' . date('Y-m-d_H-i-s') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Header CSV
    fputcsv($output, [
        'ID', 'Codice', 'Titolo', 'Tipo Documento', 'Stato', 
        'Data Creazione', 'Dimensione', 'Cartella', 'Classificazione'
    ]);
    
    // Dati
    foreach ($documents as $doc) {
        fputcsv($output, [
            $doc['id'],
            $doc['codice'],
            $doc['titolo'],
            $doc['tipo_documento'],
            $doc['stato'],
            $doc['data_creazione'],
            $doc['file_size_formatted'] ?? '',
            $doc['cartella_nome'] ?? '',
            $doc['classificazione_desc'] ?? ''
        ]);
    }
    
    fclose($output);
}

/**
 * Export risultati in formato JSON
 */
function exportToJSON(array $results): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="nexio_search_results_' . date('Y-m-d_H-i-s') . '.json"');
    
    echo json_encode([
        'export_info' => [
            'timestamp' => date('c'),
            'total_results' => count($results['documents']),
            'search_time' => $results['search_time'],
            'filters_applied' => $results['filters_applied']
        ],
        'documents' => $results['documents'],
        'pagination' => $results['pagination']
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

/**
 * Export risultati in formato Excel (placeholder)
 */
function exportToExcel(array $documents): void
{
    // Per ora fallback a CSV - in futuro implementare con PhpSpreadsheet
    exportToCSV($documents);
}
?>