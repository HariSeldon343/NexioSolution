<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/ActivityLogger.php';
require_once __DIR__ . '/DataEncryption.php';
require_once __DIR__ . '/RateLimiter.php';

/**
 * DocumentSpaceManager - Singleton per gestione spazi documentali avanzati
 * 
 * Gestisce upload/download multipli, ricerca full-text ottimizzata, versioning,
 * metadata GDPR compliance e cache intelligente per performance.
 * 
 * Features:
 * - Upload multipli con progress tracking
 * - Download batch con ZIP
 * - Ricerca full-text con ranking
 * - Gestione versioni documenti
 * - Cache Redis-compatibile
 * - GDPR compliance metadata
 * - Audit trail completo
 * 
 * @package Nexio\Utils
 * @version 1.0.0
 */
class DocumentSpaceManager
{
    private static $instance = null;
    private $logger;
    private $encryption;
    private $rateLimiter;
    
    // Cache configurations
    private $cache = [];
    private $cacheMaxSize = 1000;
    private $cacheTTL = 3600; // 1 hour
    
    // Upload configurations
    private const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50MB
    private const ALLOWED_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'txt', 'rtf', 'odt', 'ods', 'odp',
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'zip', 'rar', '7z', 'tar', 'gz'
    ];
    
    // Search configurations
    private const SEARCH_RELEVANCE_THRESHOLD = 0.1;
    private const MAX_SEARCH_RESULTS = 100;
    
    // GDPR compliance fields
    private const GDPR_METADATA_FIELDS = [
        'data_retention_period',
        'processing_purpose',
        'legal_basis',
        'data_subject_categories',
        'recipient_categories',
        'international_transfers',
        'automated_processing',
        'sensitive_data_categories'
    ];

    private function __construct()
    {
        $this->logger = ActivityLogger::getInstance();
        $this->encryption = new DataEncryption();
        $this->rateLimiter = new RateLimiter();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Upload multipli con progress tracking
     * 
     * @param int $companyId ID azienda
     * @param int $folderId ID cartella destinazione
     * @param array $files Array di file da $_FILES
     * @param array $metadata Metadata comuni per tutti i file
     * @param int $uploadedBy ID utente che carica
     * @return array Risultato dell'operazione
     */
    public function uploadMultipleFiles($companyId, $folderId, $files, $metadata = [], $uploadedBy = null)
    {
        $startTime = microtime(true);
        
        try {
            // Rate limiting
            $this->rateLimiter->check(
                "upload_multiple_{$companyId}_{$uploadedBy}",
                'file_upload',
                10, // max 10 upload batch per ora
                3600
            );

            // Validazione cartella
            $folder = $this->validateFolder($companyId, $folderId);
            
            // Preparazione risultati
            $results = [
                'uploaded' => [],
                'failed' => [],
                'total_size' => 0,
                'upload_session_id' => uniqid('upload_')
            ];

            db_begin_transaction();

            // Elabora ogni file
            foreach ($files['name'] as $index => $fileName) {
                try {
                    $fileData = [
                        'name' => $files['name'][$index],
                        'type' => $files['type'][$index],
                        'tmp_name' => $files['tmp_name'][$index],
                        'error' => $files['error'][$index],
                        'size' => $files['size'][$index]
                    ];

                    $uploadResult = $this->uploadSingleFile(
                        $companyId, 
                        $folderId, 
                        $fileData, 
                        $metadata, 
                        $uploadedBy,
                        $results['upload_session_id']
                    );

                    $results['uploaded'][] = $uploadResult;
                    $results['total_size'] += $fileData['size'];

                } catch (\Exception $e) {
                    $results['failed'][] = [
                        'file_name' => $fileName,
                        'error' => $e->getMessage()
                    ];

                    $this->logger->logError("Upload fallito per file: {$fileName}", [
                        'error' => $e->getMessage(),
                        'company_id' => $companyId,
                        'folder_id' => $folderId
                    ]);
                }
            }

            // Log dell'operazione
            $this->logger->log(
                'documents_multiple_upload',
                'documenti',
                null,
                [
                    'session_id' => $results['upload_session_id'],
                    'folder_id' => $folderId,
                    'uploaded_count' => count($results['uploaded']),
                    'failed_count' => count($results['failed']),
                    'total_size' => $results['total_size'],
                    'execution_time' => microtime(true) - $startTime
                ]
            );

            db_commit();

            // Invalidate cache
            $this->invalidateCache("folder_{$folderId}");
            $this->invalidateCache("company_documents_{$companyId}");

            return [
                'success' => true,
                'data' => $results,
                'execution_time' => microtime(true) - $startTime
            ];

        } catch (\Exception $e) {
            db_rollback();
            $this->logger->logError("Upload multiplo fallito", [
                'error' => $e->getMessage(),
                'company_id' => $companyId,
                'folder_id' => $folderId
            ]);
            throw $e;
        }
    }

    /**
     * Upload singolo file con validazioni complete
     */
    private function uploadSingleFile($companyId, $folderId, $fileData, $metadata, $uploadedBy, $sessionId)
    {
        // Validazioni base
        $this->validateFileUpload($fileData);
        
        // Generazione percorso sicuro
        $fileName = $this->sanitizeFileName($fileData['name']);
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $uniqueFileName = uniqid() . '_' . $fileName;
        
        // Percorso fisico
        $uploadDir = $this->getUploadDirectory($companyId, $folderId);
        $filePath = $uploadDir . '/' . $uniqueFileName;
        
        // Crea directory se non esistente
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Sposta il file
        if (!move_uploaded_file($fileData['tmp_name'], $filePath)) {
            throw new \Exception("Impossibile salvare il file");
        }

        // Calcola hash per deduplicazione
        $fileHash = hash_file('sha256', $filePath);
        
        // Controlla duplicati
        $duplicate = db_query(
            "SELECT id, titolo FROM documenti 
             WHERE azienda_id = ? AND file_hash = ? AND stato != 'eliminato'",
            [$companyId, $fileHash]
        )->fetch();

        if ($duplicate) {
            unlink($filePath);
            throw new \Exception("File duplicato: già esistente come '{$duplicate['titolo']}'");
        }

        // Prepara metadata GDPR
        $gdprMetadata = $this->prepareGDPRMetadata($metadata);
        
        // Inserimento nel database
        $documentId = db_insert('documenti', [
            'codice' => $this->generateDocumentCode($companyId),
            'titolo' => pathinfo($fileName, PATHINFO_FILENAME),
            'descrizione' => $metadata['description'] ?? '',
            'cartella_id' => $folderId,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_size' => $fileData['size'],
            'file_type' => $fileData['type'],
            'file_extension' => $fileExtension,
            'file_hash' => $fileHash,
            'versione' => 1,
            'stato' => 'pubblicato',
            'azienda_id' => $companyId,
            'upload_session_id' => $sessionId,
            'gdpr_metadata' => json_encode($gdprMetadata),
            'metadata_extended' => json_encode($metadata),
            'creato_da' => $uploadedBy,
            'data_creazione' => date('Y-m-d H:i:s')
        ]);

        // Crea prima versione
        db_insert('documenti_versioni', [
            'documento_id' => $documentId,
            'versione' => 1,
            'file_path' => $filePath,
            'file_size' => $fileData['size'],
            'file_hash' => $fileHash,
            'note_versione' => 'Upload iniziale',
            'creato_da' => $uploadedBy,
            'data_creazione' => date('Y-m-d H:i:s')
        ]);

        // Estrazione testo per ricerca (asincrono)
        $this->scheduleTextExtraction($documentId, $filePath, $fileExtension);

        return [
            'document_id' => $documentId,
            'file_name' => $fileName,
            'file_size' => $fileData['size'],
            'file_path' => $filePath,
            'file_hash' => $fileHash
        ];
    }

    /**
     * Download multipli come file ZIP
     */
    public function downloadMultipleFiles($companyId, $documentIds, $downloadedBy = null)
    {
        try {
            // Rate limiting
            $this->rateLimiter->check(
                "download_multiple_{$companyId}_{$downloadedBy}",
                'file_download',
                20, // max 20 download batch per ora
                3600
            );

            // Validazione documenti
            $documents = $this->validateDocumentsAccess($companyId, $documentIds);
            
            if (empty($documents)) {
                throw new \Exception("Nessun documento valido trovato");
            }

            // Creazione ZIP temporaneo
            $zipFileName = 'documents_' . date('Y-m-d_H-i-s') . '.zip';
            $zipPath = sys_get_temp_dir() . '/' . $zipFileName;
            
            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE) !== TRUE) {
                throw new \Exception("Impossibile creare archivio ZIP");
            }

            $totalSize = 0;
            $addedFiles = [];

            foreach ($documents as $doc) {
                if (file_exists($doc['file_path'])) {
                    // Nome sicuro nel ZIP
                    $safeFileName = $this->sanitizeFileName($doc['file_name']);
                    $counter = 1;
                    $originalSafeName = $safeFileName;
                    
                    // Gestione duplicati di nome
                    while (in_array($safeFileName, $addedFiles)) {
                        $safeFileName = pathinfo($originalSafeName, PATHINFO_FILENAME) . 
                                      "_{$counter}." . 
                                      pathinfo($originalSafeName, PATHINFO_EXTENSION);
                        $counter++;
                    }

                    $zip->addFile($doc['file_path'], $safeFileName);
                    $addedFiles[] = $safeFileName;
                    $totalSize += $doc['file_size'];
                }
            }

            $zip->close();

            // Log download
            $this->logger->log(
                'documents_multiple_download',
                'documenti',
                null,
                [
                    'document_ids' => $documentIds,
                    'files_count' => count($addedFiles),
                    'total_size' => $totalSize,
                    'zip_file' => $zipFileName
                ]
            );

            return [
                'success' => true,
                'zip_path' => $zipPath,
                'zip_filename' => $zipFileName,
                'files_count' => count($addedFiles),
                'total_size' => $totalSize
            ];

        } catch (\Exception $e) {
            $this->logger->logError("Download multiplo fallito", [
                'error' => $e->getMessage(),
                'company_id' => $companyId,
                'document_ids' => $documentIds
            ]);
            throw $e;
        }
    }

    /**
     * Ricerca full-text ottimizzata con ranking
     */
    public function searchDocuments($companyId, $query, $filters = [], $options = [])
    {
        $startTime = microtime(true);
        
        try {
            // Rate limiting
            $this->rateLimiter->check(
                "search_{$companyId}",
                'document_search',
                50, // max 50 ricerche per ora
                3600
            );

            // Cache key
            $cacheKey = "search_" . md5($companyId . $query . serialize($filters) . serialize($options));
            
            if ($cached = $this->getFromCache($cacheKey)) {
                return $cached;
            }

            // Preparazione query
            $searchTerms = $this->prepareSearchTerms($query);
            $sqlConditions = ["d.azienda_id = ?"];
            $sqlParams = [$companyId];

            // Filtri aggiuntivi
            $this->applySearchFilters($filters, $sqlConditions, $sqlParams);

            // Query base con scoring
            $sql = "
                SELECT 
                    d.*,
                    c.nome as cartella_nome,
                    c.percorso_completo as cartella_percorso,
                    COALESCE(dst.contenuto_estratto, '') as contenuto_testo,
                    (
                        -- Score basato su titolo
                        (CASE WHEN d.titolo LIKE ? THEN 10 ELSE 0 END) +
                        (CASE WHEN d.titolo LIKE ? THEN 5 ELSE 0 END) +
                        -- Score basato su descrizione
                        (CASE WHEN d.descrizione LIKE ? THEN 3 ELSE 0 END) +
                        -- Score basato su contenuto
                        (CASE WHEN dst.contenuto_estratto LIKE ? THEN 2 ELSE 0 END) +
                        -- Score basato su tag
                        (CASE WHEN d.tags LIKE ? THEN 4 ELSE 0 END)
                    ) as relevance_score
                FROM documenti d
                LEFT JOIN cartelle c ON d.cartella_id = c.id
                LEFT JOIN documenti_search_text dst ON d.id = dst.documento_id
                WHERE " . implode(' AND ', $sqlConditions) . "
                HAVING relevance_score >= ?
                ORDER BY relevance_score DESC, d.data_aggiornamento DESC
                LIMIT ?
            ";

            // Parametri per scoring
            $searchPattern = "%{$query}%";
            $searchParams = array_merge($sqlParams, [
                $searchPattern, // titolo exact
                "%{$searchTerms[0]}%", // titolo first term
                $searchPattern, // descrizione
                $searchPattern, // contenuto
                $searchPattern, // tags
                self::SEARCH_RELEVANCE_THRESHOLD,
                $options['limit'] ?? self::MAX_SEARCH_RESULTS
            ]);

            $results = db_query($sql, $searchParams)->fetchAll();

            // Post-processing risultati
            $processedResults = $this->processSearchResults($results, $searchTerms);

            $response = [
                'success' => true,
                'query' => $query,
                'results' => $processedResults,
                'total_found' => count($processedResults),
                'execution_time' => microtime(true) - $startTime,
                'filters_applied' => $filters
            ];

            // Cache risultati
            $this->setCache($cacheKey, $response, 300); // 5 minuti per ricerche

            return $response;

        } catch (\Exception $e) {
            $this->logger->logError("Ricerca documenti fallita", [
                'error' => $e->getMessage(),
                'company_id' => $companyId,
                'query' => $query
            ]);
            throw $e;
        }
    }

    /**
     * Gestione versioni documenti
     */
    public function createDocumentVersion($companyId, $documentId, $newFileData, $versionNotes = '', $createdBy = null)
    {
        try {
            db_begin_transaction();

            // Verifica documento
            $document = db_query(
                "SELECT * FROM documenti WHERE id = ? AND azienda_id = ?",
                [$documentId, $companyId]
            )->fetch();

            if (!$document) {
                throw new \Exception("Documento non trovato");
            }

            // Validazione file
            $this->validateFileUpload($newFileData);

            // Ottieni prossimo numero versione
            $nextVersion = db_query(
                "SELECT MAX(versione) + 1 as next_version FROM documenti_versioni WHERE documento_id = ?",
                [$documentId]
            )->fetch()['next_version'] ?? 2;

            // Upload nuovo file
            $fileName = $this->sanitizeFileName($newFileData['name']);
            $uniqueFileName = "v{$nextVersion}_" . uniqid() . '_' . $fileName;
            $uploadDir = $this->getUploadDirectory($companyId, $document['cartella_id']);
            $filePath = $uploadDir . '/' . $uniqueFileName;

            if (!move_uploaded_file($newFileData['tmp_name'], $filePath)) {
                throw new \Exception("Impossibile salvare la nuova versione");
            }

            $fileHash = hash_file('sha256', $filePath);

            // Crea nuova versione
            $versionId = db_insert('documenti_versioni', [
                'documento_id' => $documentId,
                'versione' => $nextVersion,
                'file_path' => $filePath,
                'file_name' => $fileName,
                'file_size' => $newFileData['size'],
                'file_hash' => $fileHash,
                'note_versione' => $versionNotes,
                'creato_da' => $createdBy,
                'data_creazione' => date('Y-m-d H:i:s')
            ]);

            // Aggiorna documento principale
            db_update('documenti', [
                'versione' => $nextVersion,
                'file_path' => $filePath,
                'file_name' => $fileName,
                'file_size' => $newFileData['size'],
                'file_hash' => $fileHash,
                'data_aggiornamento' => date('Y-m-d H:i:s'),
                'aggiornato_da' => $createdBy
            ], 'id = ?', [$documentId]);

            // Log operazione
            $this->logger->log(
                'document_version_created',
                'documenti',
                $documentId,
                [
                    'version' => $nextVersion,
                    'file_size' => $newFileData['size'],
                    'notes' => $versionNotes
                ]
            );

            // Schedulazione estrazione testo
            $this->scheduleTextExtraction($documentId, $filePath, pathinfo($fileName, PATHINFO_EXTENSION));

            db_commit();

            // Invalidate cache
            $this->invalidateCache("document_{$documentId}");

            return [
                'success' => true,
                'version_id' => $versionId,
                'version_number' => $nextVersion,
                'file_path' => $filePath
            ];

        } catch (\Exception $e) {
            db_rollback();
            if (isset($filePath) && file_exists($filePath)) {
                unlink($filePath);
            }
            throw $e;
        }
    }

    /**
     * Ottieni cronologia versioni documento
     */
    public function getDocumentVersions($companyId, $documentId)
    {
        // Verifica accesso
        $document = db_query(
            "SELECT id FROM documenti WHERE id = ? AND azienda_id = ?",
            [$documentId, $companyId]
        )->fetch();

        if (!$document) {
            throw new \Exception("Documento non trovato");
        }

        $versions = db_query(
            "SELECT 
                dv.*,
                u.nome || ' ' || u.cognome as creato_da_nome
             FROM documenti_versioni dv
             LEFT JOIN utenti u ON dv.creato_da = u.id
             WHERE dv.documento_id = ?
             ORDER BY dv.versione DESC",
            [$documentId]
        )->fetchAll();

        return [
            'success' => true,
            'document_id' => $documentId,
            'versions' => $versions,
            'total_versions' => count($versions)
        ];
    }

    /**
     * Validazione file upload
     */
    private function validateFileUpload($fileData)
    {
        // Errori upload
        if ($fileData['error'] !== UPLOAD_ERR_OK) {
            $errors = [
                UPLOAD_ERR_INI_SIZE => 'File troppo grande (limite server)',
                UPLOAD_ERR_FORM_SIZE => 'File troppo grande (limite form)',
                UPLOAD_ERR_PARTIAL => 'Upload parziale',
                UPLOAD_ERR_NO_FILE => 'Nessun file caricato',
                UPLOAD_ERR_NO_TMP_DIR => 'Directory temporanea mancante',
                UPLOAD_ERR_CANT_WRITE => 'Impossibile scrivere su disco',
                UPLOAD_ERR_EXTENSION => 'Upload bloccato da estensione'
            ];
            throw new \Exception($errors[$fileData['error']] ?? 'Errore upload sconosciuto');
        }

        // Dimensione file
        if ($fileData['size'] > self::MAX_FILE_SIZE) {
            throw new \Exception("File troppo grande. Massimo: " . (self::MAX_FILE_SIZE / 1024 / 1024) . "MB");
        }

        // Estensione file
        $extension = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            throw new \Exception("Estensione file non consentita: {$extension}");
        }

        // Verifica MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fileData['tmp_name']);
        finfo_close($finfo);

        if (!$this->isValidMimeType($mimeType, $extension)) {
            throw new \Exception("Tipo file non valido");
        }
    }

    /**
     * Validazione cartella
     */
    private function validateFolder($companyId, $folderId)
    {
        $folder = db_query(
            "SELECT * FROM cartelle WHERE id = ? AND azienda_id = ?",
            [$folderId, $companyId]
        )->fetch();

        if (!$folder) {
            throw new \Exception("Cartella non trovata o accesso negato");
        }

        return $folder;
    }

    /**
     * Validazione accesso documenti
     */
    private function validateDocumentsAccess($companyId, $documentIds)
    {
        if (empty($documentIds)) {
            return [];
        }

        $placeholders = str_repeat('?,', count($documentIds) - 1) . '?';
        $params = array_merge([$companyId], $documentIds);

        return db_query(
            "SELECT * FROM documenti 
             WHERE azienda_id = ? AND id IN ({$placeholders}) AND stato != 'eliminato'",
            $params
        )->fetchAll();
    }

    /**
     * Genera codice documento univoco
     */
    private function generateDocumentCode($companyId)
    {
        $prefix = date('Ym');
        $counter = db_query(
            "SELECT COUNT(*) + 1 as next_num FROM documenti 
             WHERE azienda_id = ? AND codice LIKE ?",
            [$companyId, "{$prefix}%"]
        )->fetch()['next_num'];

        return sprintf("%s%04d", $prefix, $counter);
    }

    /**
     * Ottieni directory upload
     */
    private function getUploadDirectory($companyId, $folderId)
    {
        $baseDir = dirname(__DIR__, 2) . '/uploads/documenti';
        return "{$baseDir}/company_{$companyId}/folder_{$folderId}";
    }

    /**
     * Sanifica nome file
     */
    private function sanitizeFileName($fileName)
    {
        // Rimuovi caratteri pericolosi
        $fileName = preg_replace('/[^a-zA-Z0-9\._-]/', '_', $fileName);
        $fileName = preg_replace('/_{2,}/', '_', $fileName);
        return trim($fileName, '_');
    }

    /**
     * Verifica MIME type valido
     */
    private function isValidMimeType($mimeType, $extension)
    {
        $allowedMimes = [
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xls' => ['application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'txt' => ['text/plain'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif']
        ];

        return isset($allowedMimes[$extension]) && 
               in_array($mimeType, $allowedMimes[$extension]);
    }

    /**
     * Prepara metadata GDPR
     */
    private function prepareGDPRMetadata($metadata)
    {
        $gdprData = [];
        
        foreach (self::GDPR_METADATA_FIELDS as $field) {
            if (isset($metadata[$field])) {
                $gdprData[$field] = $metadata[$field];
            }
        }

        // Default GDPR values
        $gdprData['data_retention_period'] = $gdprData['data_retention_period'] ?? '10 years';
        $gdprData['processing_purpose'] = $gdprData['processing_purpose'] ?? 'Document management';
        $gdprData['legal_basis'] = $gdprData['legal_basis'] ?? 'Legitimate interest';
        
        return $gdprData;
    }

    /**
     * Schedula estrazione testo per ricerca
     */
    private function scheduleTextExtraction($documentId, $filePath, $extension)
    {
        // Inserisce job per estrazione asincrona
        db_insert('document_processing_queue', [
            'documento_id' => $documentId,
            'file_path' => $filePath,
            'processing_type' => 'text_extraction',
            'file_extension' => $extension,
            'stato' => 'pending',
            'data_creazione' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Prepara termini di ricerca
     */
    private function prepareSearchTerms($query)
    {
        $terms = preg_split('/\s+/', trim($query));
        return array_filter($terms, function($term) {
            return strlen($term) >= 2;
        });
    }

    /**
     * Applica filtri di ricerca
     */
    private function applySearchFilters($filters, &$conditions, &$params)
    {
        if (isset($filters['folder_id'])) {
            $conditions[] = "d.cartella_id = ?";
            $params[] = $filters['folder_id'];
        }

        if (isset($filters['file_type'])) {
            $conditions[] = "d.file_extension = ?";
            $params[] = $filters['file_type'];
        }

        if (isset($filters['date_from'])) {
            $conditions[] = "d.data_creazione >= ?";
            $params[] = $filters['date_from'];
        }

        if (isset($filters['date_to'])) {
            $conditions[] = "d.data_creazione <= ?";
            $params[] = $filters['date_to'];
        }

        if (isset($filters['created_by'])) {
            $conditions[] = "d.creato_da = ?";
            $params[] = $filters['created_by'];
        }

        $conditions[] = "d.stato != 'eliminato'";
    }

    /**
     * Processa risultati di ricerca
     */
    private function processSearchResults($results, $searchTerms)
    {
        foreach ($results as &$result) {
            // Highlight termini trovati
            $result['highlighted_title'] = $this->highlightSearchTerms($result['titolo'], $searchTerms);
            $result['highlighted_description'] = $this->highlightSearchTerms($result['descrizione'], $searchTerms);
            
            // Formato file size leggibile
            $result['file_size_formatted'] = $this->formatFileSize($result['file_size']);
            
            // Metadata parsed
            $result['gdpr_metadata_parsed'] = json_decode($result['gdpr_metadata'], true);
            $result['metadata_extended_parsed'] = json_decode($result['metadata_extended'], true);
        }

        return $results;
    }

    /**
     * Evidenzia termini di ricerca
     */
    private function highlightSearchTerms($text, $terms)
    {
        foreach ($terms as $term) {
            $text = preg_replace('/(' . preg_quote($term, '/') . ')/i', '<mark>$1</mark>', $text);
        }
        return $text;
    }

    /**
     * Formatta dimensione file
     */
    private function formatFileSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Cache management
     */
    private function getFromCache($key)
    {
        if (isset($this->cache[$key])) {
            $entry = $this->cache[$key];
            if (time() - $entry['timestamp'] < $this->cacheTTL) {
                return $entry['data'];
            }
            unset($this->cache[$key]);
        }
        return null;
    }

    private function setCache($key, $data, $ttl = null)
    {
        // Cleanup cache se troppo grande
        if (count($this->cache) >= $this->cacheMaxSize) {
            $this->cache = array_slice($this->cache, -($this->cacheMaxSize / 2), null, true);
        }

        $this->cache[$key] = [
            'data' => $data,
            'timestamp' => time(),
            'ttl' => $ttl ?? $this->cacheTTL
        ];
    }

    private function invalidateCache($pattern)
    {
        foreach (array_keys($this->cache) as $key) {
            if (strpos($key, $pattern) !== false) {
                unset($this->cache[$key]);
            }
        }
    }

    /**
     * Ottieni statistiche cache
     */
    public function getCacheStats()
    {
        return [
            'entries' => count($this->cache),
            'max_size' => $this->cacheMaxSize,
            'default_ttl' => $this->cacheTTL,
            'memory_usage' => strlen(serialize($this->cache))
        ];
    }

    /**
     * Pulisce cache completamente
     */
    public function clearCache()
    {
        $this->cache = [];
    }
}