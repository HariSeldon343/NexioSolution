<?php

/**
 * Advanced Search Engine
 * Motore di ricerca avanzato con indicizzazione full-text e suggerimenti
 */
class AdvancedSearchEngine
{
    private static $instance = null;
    private $db;
    private $logger;

    private function __construct()
    {
        $this->db = db_connection();
        $this->logger = ActivityLogger::getInstance();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Ricerca avanzata con filtri multipli
     */
    public function search(array $criteria): array
    {
        $startTime = microtime(true);
        
        try {
            // Preparazione query base
            $baseQuery = $this->buildBaseQuery($criteria);
            
            // Applicazione filtri
            $filteredQuery = $this->applyFilters($baseQuery, $criteria);
            
            // Ordinamento e paginazione
            $finalQuery = $this->applyOrderingAndPagination($filteredQuery, $criteria);
            
            // Esecuzione query
            $results = $this->executeSearch($finalQuery);
            
            // Post-processing risultati
            $processedResults = $this->postProcessResults($results, $criteria);
            
            // Suggerimenti di ricerca
            $suggestions = $this->generateSuggestions($criteria, count($results['documents']));
            
            // Metriche di ricerca
            $searchTime = microtime(true) - $startTime;
            
            // Log ricerca
            $this->logSearch($criteria, count($results['documents']), $searchTime);
            
            return [
                'documents' => $processedResults['documents'],
                'pagination' => $results['pagination'],
                'suggestions' => $suggestions,
                'filters_applied' => $this->getAppliedFilters($criteria),
                'search_time' => round($searchTime, 3),
                'total_results' => $results['pagination']['total']
            ];
            
        } catch (Exception $e) {
            $this->logger->logError('Errore ricerca avanzata: ' . $e->getMessage(), $criteria);
            throw $e;
        }
    }

    /**
     * Ricerca suggerita con autocomplete
     */
    public function getSuggestions(string $query, int $aziendaId, int $limit = 10): array
    {
        $suggestions = [
            'terms' => [],
            'documents' => [],
            'categories' => []
        ];

        if (strlen($query) < 2) {
            return $suggestions;
        }

        // Suggerimenti dai termini più cercati
        $suggestions['terms'] = $this->getTermSuggestions($query, $aziendaId, $limit);
        
        // Suggerimenti dai titoli documenti
        $suggestions['documents'] = $this->getDocumentSuggestions($query, $aziendaId, $limit);
        
        // Suggerimenti dalle categorie
        $suggestions['categories'] = $this->getCategorySuggestions($query, $aziendaId, $limit);

        return $suggestions;
    }

    /**
     * Indicizzazione documento per ricerca full-text
     */
    public function indexDocument(int $documentId, array $documentData): void
    {
        try {
            // Estrazione testo da contenuto HTML
            $plainText = $this->extractTextFromHtml($documentData['contenuto_html'] ?? '');
            
            // Estrazione keywords
            $keywords = $this->extractKeywords($plainText);
            
            // Analisi semantica
            $semanticData = $this->performSemanticAnalysis($plainText);
            
            // Preparazione dati indice
            $indexData = [
                'documento_id' => $documentId,
                'testo_completo' => $plainText,
                'keywords' => json_encode($keywords),
                'semantic_data' => json_encode($semanticData),
                'word_count' => str_word_count($plainText),
                'language' => $this->detectLanguage($plainText),
                'readability_score' => $this->calculateReadabilityScore($plainText),
                'data_indicizzazione' => date('Y-m-d H:i:s'),
                'hash_contenuto' => md5($plainText)
            ];

            // Inserimento/aggiornamento indice
            $existing = db_query(
                "SELECT id FROM search_index WHERE documento_id = ?",
                [$documentId]
            )->fetchColumn();

            if ($existing) {
                db_update('search_index', $indexData, 'documento_id = ?', [$documentId]);
            } else {
                db_insert('search_index', $indexData);
            }

            // Aggiornamento statistics
            $this->updateSearchStatistics($documentData['azienda_id']);

            $this->logger->log('documento_indicizzato', 'search_index', $documentId, [
                'word_count' => $indexData['word_count'],
                'keywords_count' => count($keywords)
            ]);

        } catch (Exception $e) {
            $this->logger->logError('Errore indicizzazione documento: ' . $e->getMessage(), [
                'documento_id' => $documentId
            ]);
            throw $e;
        }
    }

    /**
     * Reindirizzazione completa per un'azienda
     */
    public function reindexCompany(int $aziendaId): array
    {
        $stats = ['processed' => 0, 'errors' => 0, 'start_time' => microtime(true)];

        try {
            // Ottieni tutti i documenti dell'azienda
            $documents = db_query(
                "SELECT id, contenuto_html, azienda_id FROM documenti_avanzati WHERE azienda_id = ?",
                [$aziendaId]
            )->fetchAll();

            foreach ($documents as $document) {
                try {
                    $this->indexDocument($document['id'], $document);
                    $stats['processed']++;
                } catch (Exception $e) {
                    $stats['errors']++;
                    $this->logger->logError('Errore reindirizzazione documento: ' . $e->getMessage(), [
                        'documento_id' => $document['id']
                    ]);
                }
            }

            $stats['execution_time'] = microtime(true) - $stats['start_time'];
            
            $this->logger->log('reindirizzazione_completata', 'search_index', null, [
                'azienda_id' => $aziendaId,
                'stats' => $stats
            ]);

            return $stats;

        } catch (Exception $e) {
            $this->logger->logError('Errore reindirizzazione azienda: ' . $e->getMessage(), [
                'azienda_id' => $aziendaId
            ]);
            throw $e;
        }
    }

    /**
     * Ricerca semantica avanzata
     */
    public function semanticSearch(string $query, int $aziendaId, array $options = []): array
    {
        // Espansione query con sinonimi
        $expandedQuery = $this->expandQueryWithSynonyms($query);
        
        // Ricerca con termini espansi
        $criteria = array_merge([
            'query' => $expandedQuery,
            'azienda_id' => $aziendaId,
            'semantic_mode' => true
        ], $options);

        $results = $this->search($criteria);
        
        // Ranking semantico
        $results['documents'] = $this->applySemanticRanking($results['documents'], $query);
        
        return $results;
    }

    // Metodi privati di supporto

    private function buildBaseQuery(array $criteria): array
    {
        $select = [
            'd.*',
            'u.nome AS creatore_nome',
            'u.cognome AS creatore_cognome',
            'c.nome AS cartella_nome',
            'cl.descrizione AS classificazione_desc',
            'si.keywords',
            'si.word_count',
            'si.readability_score'
        ];

        $from = [
            'documenti_avanzati d',
            'LEFT JOIN utenti u ON d.creato_da = u.id',
            'LEFT JOIN cartelle c ON d.cartella_id = c.id',
            'LEFT JOIN classificazioni cl ON d.classificazione_id = cl.id',
            'LEFT JOIN search_index si ON d.id = si.documento_id'
        ];

        $where = ['d.azienda_id = ?'];
        $params = [$criteria['azienda_id']];

        return [
            'select' => $select,
            'from' => $from,
            'where' => $where,
            'params' => $params
        ];
    }

    private function applyFilters(array $query, array $criteria): array
    {
        // Filtro ricerca testuale
        if (!empty($criteria['query'])) {
            if ($criteria['semantic_mode'] ?? false) {
                $query['where'][] = "MATCH(si.testo_completo) AGAINST(? IN NATURAL LANGUAGE MODE)";
                $query['select'][] = "MATCH(si.testo_completo) AGAINST(? IN NATURAL LANGUAGE MODE) AS relevance_score";
                $query['params'][] = $criteria['query'];
                $query['params'][] = $criteria['query'];
            } else {
                $query['where'][] = "MATCH(d.titolo, d.descrizione, d.contenuto_html) AGAINST(? IN BOOLEAN MODE)";
                $query['select'][] = "MATCH(d.titolo, d.descrizione, d.contenuto_html) AGAINST(? IN BOOLEAN MODE) AS relevance_score";
                $booleanQuery = $this->prepareBooleanQuery($criteria['query']);
                $query['params'][] = $booleanQuery;
                $query['params'][] = $booleanQuery;
            }
        }

        // Filtro per tipo documento
        if (!empty($criteria['tipo_documento'])) {
            if (is_array($criteria['tipo_documento'])) {
                $placeholders = str_repeat('?,', count($criteria['tipo_documento']) - 1) . '?';
                $query['where'][] = "d.tipo_documento IN ($placeholders)";
                $query['params'] = array_merge($query['params'], $criteria['tipo_documento']);
            } else {
                $query['where'][] = "d.tipo_documento = ?";
                $query['params'][] = $criteria['tipo_documento'];
            }
        }

        // Filtro per norma ISO
        if (!empty($criteria['norma_iso'])) {
            $query['where'][] = "d.norma_iso = ?";
            $query['params'][] = $criteria['norma_iso'];
        }

        // Filtro per stato
        if (!empty($criteria['stato'])) {
            if (is_array($criteria['stato'])) {
                $placeholders = str_repeat('?,', count($criteria['stato']) - 1) . '?';
                $query['where'][] = "d.stato IN ($placeholders)";
                $query['params'] = array_merge($query['params'], $criteria['stato']);
            } else {
                $query['where'][] = "d.stato = ?";
                $query['params'][] = $criteria['stato'];
            }
        }

        // Filtro per cartella
        if (!empty($criteria['cartella_id'])) {
            $query['where'][] = "d.cartella_id = ?";
            $query['params'][] = $criteria['cartella_id'];
        }

        // Filtro per data creazione
        if (!empty($criteria['data_da'])) {
            $query['where'][] = "d.data_creazione >= ?";
            $query['params'][] = $criteria['data_da'];
        }
        if (!empty($criteria['data_a'])) {
            $query['where'][] = "d.data_creazione <= ?";
            $query['params'][] = $criteria['data_a'];
        }

        // Filtro per data modifica
        if (!empty($criteria['modificato_da'])) {
            $query['where'][] = "d.modificato_da >= ?";
            $query['params'][] = $criteria['modificato_da'];
        }
        if (!empty($criteria['modificato_a'])) {
            $query['where'][] = "d.modificato_da <= ?";
            $query['params'][] = $criteria['modificato_a'];
        }

        // Filtro per creatore
        if (!empty($criteria['creato_da'])) {
            $query['where'][] = "d.creato_da = ?";
            $query['params'][] = $criteria['creato_da'];
        }

        // Filtro per tag
        if (!empty($criteria['tags'])) {
            $tagConditions = [];
            foreach ($criteria['tags'] as $tag) {
                $tagConditions[] = "JSON_CONTAINS(d.tags, ?)";
                $query['params'][] = json_encode($tag);
            }
            $query['where'][] = '(' . implode(' OR ', $tagConditions) . ')';
        }

        // Filtro GDPR
        if (isset($criteria['contiene_dati_personali'])) {
            $query['where'][] = "d.contiene_dati_personali = ?";
            $query['params'][] = $criteria['contiene_dati_personali'];
        }

        // Filtro per dimensione file
        if (!empty($criteria['file_size_min'])) {
            $query['where'][] = "d.file_size >= ?";
            $query['params'][] = $criteria['file_size_min'];
        }
        if (!empty($criteria['file_size_max'])) {
            $query['where'][] = "d.file_size <= ?";
            $query['params'][] = $criteria['file_size_max'];
        }

        // Filtro per tipo MIME
        if (!empty($criteria['mime_type'])) {
            $query['where'][] = "d.mime_type = ?";
            $query['params'][] = $criteria['mime_type'];
        }

        return $query;
    }

    private function applyOrderingAndPagination(array $query, array $criteria): array
    {
        // Ordinamento
        $orderBy = [];
        
        if (!empty($criteria['query'])) {
            $orderBy[] = 'relevance_score DESC';
        }
        
        switch ($criteria['order_by'] ?? 'data_creazione') {
            case 'titolo':
                $orderBy[] = 'd.titolo ' . ($criteria['order_dir'] ?? 'ASC');
                break;
            case 'data_modifica':
                $orderBy[] = 'd.ultima_modifica ' . ($criteria['order_dir'] ?? 'DESC');
                break;
            case 'dimensione':
                $orderBy[] = 'd.file_size ' . ($criteria['order_dir'] ?? 'DESC');
                break;
            default:
                $orderBy[] = 'd.data_creazione ' . ($criteria['order_dir'] ?? 'DESC');
        }

        $query['order_by'] = $orderBy;

        // Paginazione
        $query['limit'] = $criteria['limit'] ?? 20;
        $query['offset'] = (($criteria['page'] ?? 1) - 1) * $query['limit'];

        return $query;
    }

    private function executeSearch(array $query): array
    {
        // Query principale
        $sql = "SELECT " . implode(', ', $query['select']) . 
               " FROM " . implode(' ', $query['from']) . 
               " WHERE " . implode(' AND ', $query['where']);
        
        if (!empty($query['order_by'])) {
            $sql .= " ORDER BY " . implode(', ', $query['order_by']);
        }
        
        $sql .= " LIMIT ? OFFSET ?";
        
        $params = array_merge($query['params'], [$query['limit'], $query['offset']]);
        $stmt = db_query($sql, $params);
        $documents = $stmt->fetchAll();

        // Count totale
        $countSql = "SELECT COUNT(*) FROM " . implode(' ', $query['from']) . 
                   " WHERE " . implode(' AND ', $query['where']);
        $totalCount = db_query($countSql, $query['params'])->fetchColumn();

        return [
            'documents' => $documents,
            'pagination' => [
                'page' => (int)(($query['offset'] / $query['limit']) + 1),
                'limit' => $query['limit'],
                'total' => (int)$totalCount,
                'pages' => ceil($totalCount / $query['limit'])
            ]
        ];
    }

    private function postProcessResults(array $results, array $criteria): array
    {
        foreach ($results['documents'] as &$document) {
            // Highlight termini di ricerca
            if (!empty($criteria['query'])) {
                $document['highlighted_title'] = $this->highlightTerms($document['titolo'], $criteria['query']);
                $document['highlighted_description'] = $this->highlightTerms($document['descrizione'], $criteria['query']);
            }

            // Parsing tags e metadati
            $document['tags'] = json_decode($document['tags'] ?? '[]', true);
            $document['metadati'] = json_decode($document['metadati'] ?? '[]', true);
            $document['keywords'] = json_decode($document['keywords'] ?? '[]', true);

            // Formattazione dimensione file
            if ($document['file_size']) {
                $document['file_size_formatted'] = $this->formatFileSize($document['file_size']);
            }

            // Score di rilevanza normalizzato
            if (isset($document['relevance_score'])) {
                $document['relevance_score'] = round($document['relevance_score'], 2);
            }
        }

        return ['documents' => $results['documents']];
    }

    private function generateSuggestions(array $criteria, int $resultCount): array
    {
        $suggestions = [];

        // Suggerimenti se pochi risultati
        if ($resultCount < 5 && !empty($criteria['query'])) {
            $suggestions[] = [
                'type' => 'spelling',
                'message' => 'Prova a controllare l\'ortografia o utilizzare termini più generali'
            ];
            
            $suggestions[] = [
                'type' => 'filters',
                'message' => 'Prova a rimuovere alcuni filtri per ampliare la ricerca'
            ];
        }

        // Suggerimenti filtri popolari
        if (empty($criteria['tipo_documento'])) {
            $popularTypes = $this->getPopularDocumentTypes($criteria['azienda_id']);
            if (!empty($popularTypes)) {
                $suggestions[] = [
                    'type' => 'filter_suggestion',
                    'field' => 'tipo_documento',
                    'values' => $popularTypes,
                    'message' => 'Filtra per tipo documento'
                ];
            }
        }

        return $suggestions;
    }

    private function getAppliedFilters(array $criteria): array
    {
        $applied = [];
        
        $filterMap = [
            'tipo_documento' => 'Tipo Documento',
            'norma_iso' => 'Norma ISO',
            'stato' => 'Stato',
            'cartella_id' => 'Cartella',
            'data_da' => 'Data Da',
            'data_a' => 'Data A',
            'contiene_dati_personali' => 'Dati Personali'
        ];

        foreach ($filterMap as $key => $label) {
            if (!empty($criteria[$key])) {
                $applied[$key] = [
                    'label' => $label,
                    'value' => $criteria[$key]
                ];
            }
        }

        return $applied;
    }

    private function logSearch(array $criteria, int $resultCount, float $searchTime): void
    {
        $searchData = [
            'query' => $criteria['query'] ?? '',
            'filters' => array_intersect_key($criteria, array_flip([
                'tipo_documento', 'norma_iso', 'stato', 'cartella_id'
            ])),
            'result_count' => $resultCount,
            'search_time' => $searchTime,
            'azienda_id' => $criteria['azienda_id']
        ];

        // Salva nella tabella search_log per analytics
        db_insert('search_log', [
            'query_text' => $criteria['query'] ?? '',
            'filters_applied' => json_encode($searchData['filters']),
            'result_count' => $resultCount,
            'search_time' => $searchTime,
            'user_id' => Auth::getInstance()->getUser()['id'],
            'azienda_id' => $criteria['azienda_id'],
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        $this->logger->log('ricerca_eseguita', 'search_log', null, $searchData);
    }

    private function prepareBooleanQuery(string $query): string
    {
        // Converte query user-friendly in boolean MySQL
        $terms = explode(' ', trim($query));
        $booleanTerms = [];

        foreach ($terms as $term) {
            $term = trim($term);
            if (strlen($term) > 1) {
                // Aggiunge wildcard per ricerca parziale
                $booleanTerms[] = '+' . $term . '*';
            }
        }

        return implode(' ', $booleanTerms);
    }

    private function extractTextFromHtml(string $html): string
    {
        // Rimuove tag HTML e decode entities
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Normalizza spazi
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }

    private function extractKeywords(string $text): array
    {
        $stopWords = ['il', 'la', 'le', 'lo', 'gli', 'di', 'da', 'in', 'con', 'su', 'per', 'tra', 'fra', 'e', 'o', 'ma', 'se', 'che', 'chi', 'come', 'quando', 'dove', 'perché', 'della', 'delle', 'del', 'dei', 'dall', 'dalla', 'dalle', 'dallo', 'dagli'];
        
        // Estrae parole (min 3 caratteri)
        $words = str_word_count(strtolower($text), 1, 'àáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿ');
        
        // Filtra stop words e parole troppo corte
        $keywords = array_filter($words, function($word) use ($stopWords) {
            return strlen($word) >= 3 && !in_array($word, $stopWords);
        });

        // Conta frequenza e prende i più comuni
        $wordCounts = array_count_values($keywords);
        arsort($wordCounts);
        
        return array_slice(array_keys($wordCounts), 0, 50);
    }

    private function performSemanticAnalysis(string $text): array
    {
        // Analisi semantica base
        return [
            'sentiment' => $this->analyzeSentiment($text),
            'entities' => $this->extractEntities($text),
            'topics' => $this->extractTopics($text),
            'complexity' => $this->analyzeComplexity($text)
        ];
    }

    private function analyzeSentiment(string $text): string
    {
        // Analisi sentiment semplificata
        $positiveWords = ['buono', 'ottimo', 'eccellente', 'positivo', 'miglioramento', 'successo'];
        $negativeWords = ['cattivo', 'pessimo', 'errore', 'problema', 'fallimento', 'negativo'];
        
        $text = strtolower($text);
        $positiveCount = 0;
        $negativeCount = 0;
        
        foreach ($positiveWords as $word) {
            $positiveCount += substr_count($text, $word);
        }
        
        foreach ($negativeWords as $word) {
            $negativeCount += substr_count($text, $word);
        }
        
        if ($positiveCount > $negativeCount) return 'positive';
        if ($negativeCount > $positiveCount) return 'negative';
        return 'neutral';
    }

    private function extractEntities(string $text): array
    {
        // Estrazione entità semplificata
        $entities = [];
        
        // Date
        if (preg_match_all('/\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4}/', $text, $matches)) {
            $entities['dates'] = array_unique($matches[0]);
        }
        
        // Email
        if (preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $matches)) {
            $entities['emails'] = array_unique($matches[0]);
        }
        
        // Numeri
        if (preg_match_all('/\b\d{3,}\b/', $text, $matches)) {
            $entities['numbers'] = array_unique($matches[0]);
        }
        
        return $entities;
    }

    private function extractTopics(string $text): array
    {
        // Estrazione topic semplificata basata su keywords
        $keywords = $this->extractKeywords($text);
        
        // Raggruppa per topic
        $topics = [];
        $topicWords = [
            'qualita' => ['qualità', 'controllo', 'standard', 'norma', 'certificazione'],
            'sicurezza' => ['sicurezza', 'rischio', 'protezione', 'prevenzione', 'emergenza'],
            'ambiente' => ['ambiente', 'ambientale', 'inquinamento', 'sostenibilità', 'rifiuti'],
            'processo' => ['processo', 'procedura', 'workflow', 'operativo', 'attività']
        ];
        
        foreach ($topicWords as $topic => $words) {
            $score = 0;
            foreach ($words as $word) {
                if (in_array($word, $keywords)) {
                    $score++;
                }
            }
            if ($score > 0) {
                $topics[$topic] = $score;
            }
        }
        
        arsort($topics);
        return array_slice($topics, 0, 3, true);
    }

    private function analyzeComplexity(string $text): float
    {
        // Analisi complessità base (Flesch Reading Ease adattato)
        $sentences = substr_count($text, '.') + substr_count($text, '!') + substr_count($text, '?');
        $words = str_word_count($text);
        $syllables = $this->countSyllables($text);
        
        if ($sentences == 0 || $words == 0) return 0;
        
        $avgWordsPerSentence = $words / $sentences;
        $avgSyllablesPerWord = $syllables / $words;
        
        $score = 206.835 - (1.015 * $avgWordsPerSentence) - (84.6 * $avgSyllablesPerWord);
        
        return max(0, min(100, $score)) / 100; // Normalizza 0-1
    }

    private function countSyllables(string $text): int
    {
        // Conta sillabe approssimativo per italiano
        $text = strtolower($text);
        $vowels = 'aeiouàèéìíîòóù';
        $syllables = 0;
        $prevWasVowel = false;
        
        for ($i = 0; $i < strlen($text); $i++) {
            $isVowel = strpos($vowels, $text[$i]) !== false;
            if ($isVowel && !$prevWasVowel) {
                $syllables++;
            }
            $prevWasVowel = $isVowel;
        }
        
        return max(1, $syllables);
    }

    private function detectLanguage(string $text): string
    {
        // Semplice detection italiana
        $italianWords = ['il', 'la', 'di', 'che', 'e', 'per', 'con', 'del', 'della'];
        $text = strtolower($text);
        
        $italianCount = 0;
        foreach ($italianWords as $word) {
            $italianCount += substr_count($text, ' ' . $word . ' ');
        }
        
        return $italianCount > 2 ? 'it' : 'unknown';
    }

    private function calculateReadabilityScore(string $text): float
    {
        return $this->analyzeComplexity($text);
    }

    private function updateSearchStatistics(int $aziendaId): void
    {
        // Aggiorna statistiche di indicizzazione
        $stats = [
            'last_index_update' => date('Y-m-d H:i:s'),
            'total_documents' => db_query(
                "SELECT COUNT(*) FROM documenti_avanzati WHERE azienda_id = ?",
                [$aziendaId]
            )->fetchColumn()
        ];

        db_query(
            "INSERT INTO search_statistics (azienda_id, stats_data, updated_at) 
             VALUES (?, ?, ?) 
             ON DUPLICATE KEY UPDATE stats_data = ?, updated_at = ?",
            [$aziendaId, json_encode($stats), date('Y-m-d H:i:s'), json_encode($stats), date('Y-m-d H:i:s')]
        );
    }

    private function getTermSuggestions(string $query, int $aziendaId, int $limit): array
    {
        // Suggerimenti da termini cercati in precedenza
        $stmt = db_query(
            "SELECT query_text, COUNT(*) as frequency 
             FROM search_log 
             WHERE azienda_id = ? AND query_text LIKE ? AND query_text != ?
             GROUP BY query_text 
             ORDER BY frequency DESC 
             LIMIT ?",
            [$aziendaId, $query . '%', $query, $limit]
        );

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function getDocumentSuggestions(string $query, int $aziendaId, int $limit): array
    {
        $stmt = db_query(
            "SELECT titolo 
             FROM documenti_avanzati 
             WHERE azienda_id = ? AND titolo LIKE ? 
             ORDER BY data_creazione DESC 
             LIMIT ?",
            [$aziendaId, '%' . $query . '%', $limit]
        );

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function getCategorySuggestions(string $query, int $aziendaId, int $limit): array
    {
        $stmt = db_query(
            "SELECT DISTINCT tipo_documento 
             FROM documenti_avanzati 
             WHERE azienda_id = ? AND tipo_documento LIKE ? 
             LIMIT ?",
            [$aziendaId, '%' . $query . '%', $limit]
        );

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function expandQueryWithSynonyms(string $query): string
    {
        // Dizionario sinonimi semplificato
        $synonyms = [
            'qualità' => ['qualità', 'qualita', 'controllo', 'standard'],
            'documento' => ['documento', 'file', 'allegato', 'pratica'],
            'procedura' => ['procedura', 'processo', 'workflow', 'operazione'],
            'sicurezza' => ['sicurezza', 'protezione', 'prevenzione']
        ];

        $expandedTerms = explode(' ', $query);
        
        foreach ($expandedTerms as &$term) {
            foreach ($synonyms as $key => $syns) {
                if (in_array(strtolower($term), $syns)) {
                    $term = '(' . implode(' OR ', $syns) . ')';
                    break;
                }
            }
        }

        return implode(' ', $expandedTerms);
    }

    private function applySemanticRanking(array $documents, string $originalQuery): array
    {
        // Ranking semantico avanzato
        foreach ($documents as &$doc) {
            $semanticScore = 0;
            
            // Boost per corrispondenza esatta nel titolo
            if (stripos($doc['titolo'], $originalQuery) !== false) {
                $semanticScore += 10;
            }
            
            // Boost per keywords correlate
            $keywords = json_decode($doc['keywords'] ?? '[]', true);
            $queryTerms = explode(' ', strtolower($originalQuery));
            
            foreach ($queryTerms as $term) {
                if (in_array($term, $keywords)) {
                    $semanticScore += 5;
                }
            }
            
            // Boost per documenti recenti
            $daysSinceCreation = (time() - strtotime($doc['data_creazione'])) / 86400;
            if ($daysSinceCreation < 30) {
                $semanticScore += 2;
            }
            
            $doc['semantic_score'] = $semanticScore;
        }

        // Ordina per score semantico
        usort($documents, function($a, $b) {
            return ($b['semantic_score'] ?? 0) <=> ($a['semantic_score'] ?? 0);
        });

        return $documents;
    }

    private function highlightTerms(string $text, string $query): string
    {
        $terms = explode(' ', $query);
        
        foreach ($terms as $term) {
            $term = trim($term);
            if (strlen($term) > 2) {
                $text = preg_replace(
                    '/(' . preg_quote($term, '/') . ')/i',
                    '<mark>$1</mark>',
                    $text
                );
            }
        }
        
        return $text;
    }

    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    private function getPopularDocumentTypes(int $aziendaId): array
    {
        $stmt = db_query(
            "SELECT tipo_documento, COUNT(*) as count 
             FROM documenti_avanzati 
             WHERE azienda_id = ? 
             GROUP BY tipo_documento 
             ORDER BY count DESC 
             LIMIT 5",
            [$aziendaId]
        );

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}