<?php

/**
 * Advanced Document Model
 * Gestisce documenti con funzionalità avanzate ISO e GDPR
 */
class AdvancedDocument
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
     * Crea nuovo documento avanzato
     */
    public function create(array $data): int
    {
        try {
            db_begin_transaction();

            // Validazione dati
            $this->validateDocumentData($data);

            // Preparazione dati documento
            $documentData = [
                'codice' => $this->generateDocumentCode($data['norma_iso'] ?? null),
                'titolo' => $data['titolo'],
                'descrizione' => $data['descrizione'] ?? '',
                'tipo_documento' => $data['tipo_documento'],
                'contenuto_html' => $data['contenuto_html'] ?? '',
                'file_path' => $data['file_path'] ?? null,
                'file_size' => $data['file_size'] ?? null,
                'mime_type' => $data['mime_type'] ?? null,
                'hash_file' => $data['hash_file'] ?? null,
                'cartella_id' => $data['cartella_id'],
                'template_id' => $data['template_id'] ?? null,
                'classificazione_id' => $data['classificazione_id'] ?? null,
                'norma_iso' => $data['norma_iso'] ?? null,
                'versione' => 1,
                'stato' => $data['stato'] ?? 'bozza',
                'tags' => json_encode($data['tags'] ?? []),
                'metadati' => json_encode($data['metadati'] ?? []),
                'contiene_dati_personali' => $data['contiene_dati_personali'] ?? false,
                'tipo_dati_gdpr' => $data['tipo_dati_gdpr'] ?? null,
                'periodo_conservazione' => $data['periodo_conservazione'] ?? null,
                'azienda_id' => $data['azienda_id'],
                'creato_da' => $data['creato_da'],
                'responsabile_id' => $data['responsabile_id'] ?? null
            ];

            // Inserimento documento
            $documentId = db_insert('documenti_avanzati', $documentData);

            // Creazione prima versione
            $this->createVersion($documentId, $documentData);

            // Indicizzazione per ricerca full-text
            $this->indexDocument($documentId, $documentData);

            // GDPR tracking se contiene dati personali
            if ($documentData['contiene_dati_personali']) {
                $this->trackGDPRData($documentId, $documentData);
            }

            // Log attività
            $this->logger->log('documento_avanzato_creato', 'documenti_avanzati', $documentId, [
                'norma_iso' => $data['norma_iso'] ?? null,
                'contiene_dati_personali' => $documentData['contiene_dati_personali']
            ]);

            db_commit();
            return $documentId;

        } catch (Exception $e) {
            db_rollback();
            $this->logger->logError('Errore creazione documento avanzato: ' . $e->getMessage(), $data);
            throw $e;
        }
    }

    /**
     * Ricerca full-text avanzata
     */
    public function search(array $criteria): array
    {
        $whereConditions = ['d.azienda_id = ?'];
        $params = [$criteria['azienda_id']];

        // Ricerca testuale
        if (!empty($criteria['query'])) {
            $whereConditions[] = "MATCH(d.titolo, d.descrizione, d.contenuto_html) AGAINST(? IN BOOLEAN MODE)";
            $params[] = $this->prepareSearchQuery($criteria['query']);
        }

        // Filtro per norma ISO
        if (!empty($criteria['norma_iso'])) {
            $whereConditions[] = "d.norma_iso = ?";
            $params[] = $criteria['norma_iso'];
        }

        // Filtro per tipo documento
        if (!empty($criteria['tipo_documento'])) {
            $whereConditions[] = "d.tipo_documento = ?";
            $params[] = $criteria['tipo_documento'];
        }

        // Filtro per stato
        if (!empty($criteria['stato'])) {
            $whereConditions[] = "d.stato = ?";
            $params[] = $criteria['stato'];
        }

        // Filtro per data
        if (!empty($criteria['data_da'])) {
            $whereConditions[] = "d.data_creazione >= ?";
            $params[] = $criteria['data_da'];
        }
        if (!empty($criteria['data_a'])) {
            $whereConditions[] = "d.data_creazione <= ?";
            $params[] = $criteria['data_a'];
        }

        // Filtro GDPR
        if (isset($criteria['contiene_dati_personali'])) {
            $whereConditions[] = "d.contiene_dati_personali = ?";
            $params[] = $criteria['contiene_dati_personali'];
        }

        // Paginazione
        $page = $criteria['page'] ?? 1;
        $limit = $criteria['limit'] ?? 20;
        $offset = ($page - 1) * $limit;

        // Query principale
        $sql = "
            SELECT 
                d.*,
                u.nome AS creatore_nome,
                u.cognome AS creatore_cognome,
                c.nome AS cartella_nome,
                cl.descrizione AS classificazione_desc,
                MATCH(d.titolo, d.descrizione, d.contenuto_html) AGAINST(? IN BOOLEAN MODE) AS relevance_score
            FROM documenti_avanzati d
            LEFT JOIN utenti u ON d.creato_da = u.id
            LEFT JOIN cartelle c ON d.cartella_id = c.id
            LEFT JOIN classificazioni cl ON d.classificazione_id = cl.id
            WHERE " . implode(' AND ', $whereConditions) . "
            ORDER BY " . (!empty($criteria['query']) ? 'relevance_score DESC,' : '') . " d.data_creazione DESC
            LIMIT ? OFFSET ?
        ";

        $searchParams = !empty($criteria['query']) ? [$criteria['query']] : [''];
        $allParams = array_merge($searchParams, $params, [$limit, $offset]);

        $stmt = db_query($sql, $allParams);
        $results = $stmt->fetchAll();

        // Count totale
        $countSql = "
            SELECT COUNT(*) 
            FROM documenti_avanzati d
            WHERE " . implode(' AND ', $whereConditions);
        $totalCount = db_query($countSql, $params)->fetchColumn();

        return [
            'documents' => $results,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $totalCount,
                'pages' => ceil($totalCount / $limit)
            ]
        ];
    }

    /**
     * Backup documentale
     */
    public function createBackup(int $aziendaId, array $options = []): array
    {
        $backupId = uniqid('backup_', true);
        $backupPath = BASE_PATH . '/backups/' . $backupId;
        
        if (!is_dir($backupPath)) {
            mkdir($backupPath, 0755, true);
        }

        try {
            // Export documenti
            $documents = $this->exportDocuments($aziendaId, $options);
            file_put_contents($backupPath . '/documents.json', json_encode($documents, JSON_PRETTY_PRINT));

            // Export strutture cartelle
            $folders = $this->exportFolders($aziendaId);
            file_put_contents($backupPath . '/folders.json', json_encode($folders, JSON_PRETTY_PRINT));

            // Export classificazioni
            $classifications = $this->exportClassifications($aziendaId);
            file_put_contents($backupPath . '/classifications.json', json_encode($classifications, JSON_PRETTY_PRINT));

            // Export template
            $templates = $this->exportTemplates($aziendaId);
            file_put_contents($backupPath . '/templates.json', json_encode($templates, JSON_PRETTY_PRINT));

            // Copia file fisici
            if ($options['include_files'] ?? true) {
                $this->copyDocumentFiles($documents, $backupPath . '/files');
            }

            // Creazione ZIP
            $zipPath = $backupPath . '.zip';
            $this->createZipBackup($backupPath, $zipPath);

            // Cleanup directory temporanea
            $this->removeDirectory($backupPath);

            // Log backup
            $this->logger->log('backup_creato', 'backup', null, [
                'azienda_id' => $aziendaId,
                'backup_id' => $backupId,
                'documenti_count' => count($documents),
                'include_files' => $options['include_files'] ?? true
            ]);

            return [
                'backup_id' => $backupId,
                'file_path' => $zipPath,
                'size' => filesize($zipPath),
                'documents_count' => count($documents)
            ];

        } catch (Exception $e) {
            // Cleanup in caso di errore
            if (is_dir($backupPath)) {
                $this->removeDirectory($backupPath);
            }
            throw $e;
        }
    }

    /**
     * GDPR Compliance tracking
     */
    public function trackGDPRData(int $documentId, array $documentData): void
    {
        $gdprData = [
            'documento_id' => $documentId,
            'tipo_dati' => $documentData['tipo_dati_gdpr'],
            'periodo_conservazione' => $documentData['periodo_conservazione'],
            'data_raccolta' => date('Y-m-d H:i:s'),
            'data_scadenza' => $this->calculateExpiryDate($documentData['periodo_conservazione']),
            'azienda_id' => $documentData['azienda_id']
        ];

        db_insert('gdpr_data_tracking', $gdprData);
    }

    /**
     * Right to be forgotten implementation
     */
    public function forgetData(int $documentId, string $reason): bool
    {
        try {
            db_begin_transaction();

            // Anonimizzazione contenuto
            db_update('documenti_avanzati', [
                'contenuto_html' => '[DATI RIMOSSI PER GDPR]',
                'metadati' => json_encode(['gdpr_forgotten' => true, 'reason' => $reason, 'date' => date('Y-m-d H:i:s')])
            ], 'id = ?', [$documentId]);

            // Rimozione file fisico se presente
            $document = $this->findById($documentId);
            if ($document && $document['file_path'] && file_exists($document['file_path'])) {
                unlink($document['file_path']);
            }

            // Log GDPR action
            $this->logger->log('gdpr_data_forgotten', 'documenti_avanzati', $documentId, [
                'reason' => $reason
            ]);

            db_commit();
            return true;

        } catch (Exception $e) {
            db_rollback();
            throw $e;
        }
    }

    /**
     * Trova documento per ID
     */
    public function findById(int $id): ?array
    {
        $stmt = db_query("SELECT * FROM documenti_avanzati WHERE id = ?", [$id]);
        return $stmt->fetch() ?: null;
    }

    // Metodi privati di supporto

    private function validateDocumentData(array $data): void
    {
        if (empty($data['titolo'])) {
            throw new InvalidArgumentException('Titolo documento richiesto');
        }

        if (empty($data['tipo_documento'])) {
            throw new InvalidArgumentException('Tipo documento richiesto');
        }

        if (empty($data['azienda_id'])) {
            throw new InvalidArgumentException('Azienda ID richiesto');
        }

        if (empty($data['creato_da'])) {
            throw new InvalidArgumentException('Creatore richiesto');
        }
    }

    private function generateDocumentCode(?string $normaIso = null): string
    {
        $prefix = $normaIso ? strtoupper($normaIso) : 'DOC';
        $year = date('Y');
        $random = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        return "{$prefix}-{$year}-{$random}";
    }

    private function createVersion(int $documentId, array $documentData): void
    {
        $versionData = [
            'documento_id' => $documentId,
            'versione' => 1,
            'contenuto_html' => $documentData['contenuto_html'],
            'file_path' => $documentData['file_path'],
            'note_versione' => 'Versione iniziale',
            'creato_da' => $documentData['creato_da']
        ];

        db_insert('documenti_versioni', $versionData);
    }

    private function indexDocument(int $documentId, array $documentData): void
    {
        // Estrazione testo per indicizzazione
        $searchableText = strip_tags($documentData['contenuto_html']);
        $keywords = $this->extractKeywords($searchableText);

        $indexData = [
            'documento_id' => $documentId,
            'testo_indicizzato' => $searchableText,
            'keywords' => json_encode($keywords),
            'data_indicizzazione' => date('Y-m-d H:i:s')
        ];

        db_insert('documenti_search_index', $indexData);
    }

    private function prepareSearchQuery(string $query): string
    {
        // Preparazione query per MATCH AGAINST
        $terms = explode(' ', $query);
        $booleanTerms = array_map(function($term) {
            return '+' . trim($term) . '*';
        }, $terms);

        return implode(' ', $booleanTerms);
    }

    private function extractKeywords(string $text): array
    {
        $stopWords = ['il', 'la', 'le', 'lo', 'gli', 'di', 'da', 'in', 'con', 'su', 'per', 'tra', 'fra', 'e', 'o', 'ma', 'se', 'che', 'chi', 'come', 'quando', 'dove', 'perché'];
        
        $words = str_word_count(strtolower($text), 1, 'àáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿ');
        $keywords = array_filter($words, function($word) use ($stopWords) {
            return strlen($word) > 3 && !in_array($word, $stopWords);
        });

        return array_slice(array_keys(array_count_values($keywords)), 0, 20);
    }

    private function exportDocuments(int $aziendaId, array $options): array
    {
        $whereConditions = ['azienda_id = ?'];
        $params = [$aziendaId];

        if (!empty($options['from_date'])) {
            $whereConditions[] = 'data_creazione >= ?';
            $params[] = $options['from_date'];
        }

        if (!empty($options['to_date'])) {
            $whereConditions[] = 'data_creazione <= ?';
            $params[] = $options['to_date'];
        }

        $sql = "SELECT * FROM documenti_avanzati WHERE " . implode(' AND ', $whereConditions);
        $stmt = db_query($sql, $params);
        return $stmt->fetchAll();
    }

    private function exportFolders(int $aziendaId): array
    {
        $stmt = db_query("SELECT * FROM cartelle WHERE azienda_id = ?", [$aziendaId]);
        return $stmt->fetchAll();
    }

    private function exportClassifications(int $aziendaId): array
    {
        $stmt = db_query("SELECT * FROM classificazioni WHERE azienda_id = ?", [$aziendaId]);
        return $stmt->fetchAll();
    }

    private function exportTemplates(int $aziendaId): array
    {
        $stmt = db_query("SELECT * FROM template_documenti WHERE azienda_id = ? OR globale = 1", [$aziendaId]);
        return $stmt->fetchAll();
    }

    private function copyDocumentFiles(array $documents, string $destPath): void
    {
        if (!is_dir($destPath)) {
            mkdir($destPath, 0755, true);
        }

        foreach ($documents as $doc) {
            if ($doc['file_path'] && file_exists($doc['file_path'])) {
                $filename = basename($doc['file_path']);
                copy($doc['file_path'], $destPath . '/' . $filename);
            }
        }
    }

    private function createZipBackup(string $sourcePath, string $zipPath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
            throw new Exception('Cannot create ZIP backup');
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourcePath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = substr($file->getPathname(), strlen($sourcePath) + 1);
                $zip->addFile($file->getPathname(), $relativePath);
            }
        }

        $zip->close();
    }

    private function removeDirectory(string $dir): void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object) && !is_link($dir . "/" . $object)) {
                        $this->removeDirectory($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }

    private function calculateExpiryDate(?int $months): ?string
    {
        if (!$months) return null;
        return date('Y-m-d', strtotime("+{$months} months"));
    }
}