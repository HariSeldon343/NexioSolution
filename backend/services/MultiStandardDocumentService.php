<?php
/**
 * Servizio per gestione documenti multi-norma
 * Implementa pattern Singleton per consistenza con architettura Nexio
 */

namespace Nexio\Services;

use Exception;
use PDO;

class MultiStandardDocumentService {
    private static $instance = null;
    private $db;
    
    private function __construct() {
        $this->db = db_connection();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Configura un'azienda per il sistema multi-norma
     */
    public function configuraAzienda($azienda_id, $tipo_configurazione, $standards = [], $utente_id = null) {
        try {
            db_begin_transaction();
            
            // Inserisci configurazione base
            db_insert('configurazioni_normative_azienda', [
                'azienda_id' => $azienda_id,
                'tipo_configurazione' => $tipo_configurazione,
                'configurato_da' => $utente_id
            ]);
            
            // Attiva standards richiesti
            foreach ($standards as $standard_codice) {
                $standard = db_query(
                    "SELECT id FROM standard_normativi WHERE codice = ?",
                    [$standard_codice]
                )->fetch();
                
                if ($standard) {
                    db_insert('aziende_standard', [
                        'azienda_id' => $azienda_id,
                        'standard_id' => $standard['id'],
                        'attivo' => true,
                        'data_attivazione' => date('Y-m-d'),
                        'responsabile_id' => $utente_id
                    ]);
                    
                    // Crea struttura cartelle per sistema separato
                    if ($tipo_configurazione === 'separata') {
                        $this->creaStrutturaStandard($azienda_id, $standard['id'], $utente_id);
                    }
                }
            }
            
            // Per sistema integrato, crea struttura unificata
            if ($tipo_configurazione === 'integrata') {
                $this->creaStrutturaIntegrata($azienda_id, $standards, $utente_id);
            }
            
            db_commit();
            
            // Log attività
            \ActivityLogger::getInstance()->log(
                'configurazione_normativa',
                'azienda',
                $azienda_id,
                [
                    'tipo' => $tipo_configurazione,
                    'standards' => $standards
                ]
            );
            
            return true;
            
        } catch (Exception $e) {
            db_rollback();
            throw new Exception("Errore configurazione azienda: " . $e->getMessage());
        }
    }
    
    /**
     * Crea struttura cartelle per uno standard specifico
     */
    private function creaStrutturaStandard($azienda_id, $standard_id, $utente_id) {
        // Chiama stored procedure
        $stmt = $this->db->prepare("CALL sp_crea_struttura_standard(?, ?, ?)");
        $stmt->execute([$azienda_id, $standard_id, $utente_id]);
    }
    
    /**
     * Crea struttura integrata per più standard
     */
    private function creaStrutturaIntegrata($azienda_id, $standards, $utente_id) {
        // Crea cartella radice
        $root_id = db_insert('cartelle', [
            'nome' => 'Sistema Integrato',
            'percorso_completo' => '/Sistema_Integrato',
            'livello' => 0,
            'azienda_id' => $azienda_id,
            'tipo_cartella' => 'mista',
            'creato_da' => $utente_id
        ]);
        
        // Ottieni categorie comuni
        $categorie = db_query("
            SELECT DISTINCT nome, codice, ordine 
            FROM categorie_standard 
            WHERE standard_id IN (
                SELECT id FROM standard_normativi WHERE codice IN (" . 
                str_repeat('?,', count($standards) - 1) . "?)
            )
            GROUP BY nome
            ORDER BY MIN(ordine)
        ", $standards)->fetchAll();
        
        // Crea sottocartelle
        foreach ($categorie as $categoria) {
            db_insert('cartelle', [
                'nome' => $categoria['nome'],
                'parent_id' => $root_id,
                'percorso_completo' => '/Sistema_Integrato/' . str_replace(' ', '_', $categoria['nome']),
                'livello' => 1,
                'azienda_id' => $azienda_id,
                'tipo_cartella' => 'mista',
                'creato_da' => $utente_id
            ]);
        }
    }
    
    /**
     * Upload multiplo di documenti
     */
    public function uploadMultiplo($files, $cartella_id, $standard_id = null, $metadata = []) {
        $auth = \Auth::getInstance();
        $utente_id = $auth->getUserId();
        $azienda_id = $auth->getCurrentCompany();
        
        // Crea batch
        $batch_id = db_insert('upload_batch', [
            'codice_batch' => 'BATCH_' . time() . '_' . uniqid(),
            'utente_id' => $utente_id,
            'azienda_id' => $azienda_id,
            'cartella_destinazione_id' => $cartella_id,
            'numero_file_totali' => count($files['name']),
            'metadata' => json_encode($metadata)
        ]);
        
        $uploaded = [];
        $errors = [];
        
        foreach ($files['name'] as $key => $filename) {
            try {
                // Registra file nel batch
                $file_id = db_insert('upload_batch_files', [
                    'batch_id' => $batch_id,
                    'nome_file' => $filename,
                    'dimensione_bytes' => $files['size'][$key],
                    'mime_type' => $files['type'][$key],
                    'stato' => 'in_upload'
                ]);
                
                // Upload fisico del file
                $upload_result = $this->uploadSingoloFile(
                    $files['tmp_name'][$key],
                    $filename,
                    $files['type'][$key],
                    $files['size'][$key],
                    $cartella_id,
                    $standard_id
                );
                
                if ($upload_result['success']) {
                    // Aggiorna stato file
                    db_update('upload_batch_files', 
                        [
                            'stato' => 'completato',
                            'documento_id' => $upload_result['documento_id'],
                            'progresso_percentuale' => 100,
                            'data_fine_upload' => date('Y-m-d H:i:s')
                        ],
                        'id = ?',
                        [$file_id]
                    );
                    
                    $uploaded[] = $upload_result;
                } else {
                    throw new Exception($upload_result['error']);
                }
                
            } catch (Exception $e) {
                // Registra errore
                db_update('upload_batch_files',
                    [
                        'stato' => 'fallito',
                        'errore' => $e->getMessage()
                    ],
                    'id = ?',
                    [$file_id]
                );
                
                $errors[] = [
                    'file' => $filename,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        // Aggiorna stato batch
        $stato_finale = empty($errors) ? 'completato' : (empty($uploaded) ? 'fallito' : 'completato');
        db_update('upload_batch',
            [
                'stato' => $stato_finale,
                'numero_file_completati' => count($uploaded),
                'data_fine' => date('Y-m-d H:i:s')
            ],
            'id = ?',
            [$batch_id]
        );
        
        return [
            'batch_id' => $batch_id,
            'uploaded' => $uploaded,
            'errors' => $errors,
            'totale' => count($files['name']),
            'successo' => count($uploaded),
            'falliti' => count($errors)
        ];
    }
    
    /**
     * Upload singolo file
     */
    private function uploadSingoloFile($tmp_path, $filename, $mime_type, $size, $cartella_id, $standard_id = null) {
        $auth = \Auth::getInstance();
        $azienda_id = $auth->getCurrentCompany();
        
        // Genera percorso sicuro
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $safe_name = uniqid() . '_' . time() . '.' . $ext;
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/documenti/' . $azienda_id . '/';
        
        // Crea directory se non esiste
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_path = $upload_dir . $safe_name;
        $relative_path = '/uploads/documenti/' . $azienda_id . '/' . $safe_name;
        
        // Sposta file
        if (!move_uploaded_file($tmp_path, $file_path)) {
            throw new Exception("Errore nel caricamento del file");
        }
        
        // Calcola hash per deduplicazione
        $hash = hash_file('sha256', $file_path);
        
        // Verifica se esiste già
        $existing = db_query(
            "SELECT id FROM documenti WHERE hash_contenuto = ? AND azienda_id = ?",
            [$hash, $azienda_id]
        )->fetch();
        
        if ($existing) {
            unlink($file_path); // Rimuovi duplicato
            return [
                'success' => true,
                'documento_id' => $existing['id'],
                'duplicato' => true
            ];
        }
        
        // Inserisci documento
        $documento_id = db_insert('documenti', [
            'codice' => 'DOC_' . date('Y') . '_' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
            'titolo' => pathinfo($filename, PATHINFO_FILENAME),
            'file_path' => $relative_path,
            'dimensione_file' => $size,
            'formato' => $ext,
            'tipo_documento' => $this->detectTipoDocumento($filename, $mime_type),
            'cartella_id' => $cartella_id,
            'standard_id' => $standard_id,
            'hash_contenuto' => $hash,
            'stato' => 'bozza',
            'azienda_id' => $azienda_id,
            'creato_da' => $auth->getUserId()
        ]);
        
        // Indicizza per ricerca full-text
        $this->indicizzaDocumento($documento_id, $filename);
        
        return [
            'success' => true,
            'documento_id' => $documento_id,
            'file_path' => $relative_path
        ];
    }
    
    /**
     * Rileva tipo documento dal nome file
     */
    private function detectTipoDocumento($filename, $mime_type) {
        $filename_lower = strtolower($filename);
        
        // Pattern comuni
        $patterns = [
            'procedura' => ['proc', 'procedura', 'prc'],
            'modulo' => ['mod', 'modulo', 'form'],
            'manuale' => ['manuale', 'manual'],
            'politica' => ['politica', 'policy', 'pol'],
            'piano' => ['piano', 'plan'],
            'report' => ['report', 'rapporto'],
            'verbale' => ['verbale', 'meeting'],
            'checklist' => ['checklist', 'check']
        ];
        
        foreach ($patterns as $tipo => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($filename_lower, $keyword) !== false) {
                    return $tipo;
                }
            }
        }
        
        return 'documento';
    }
    
    /**
     * Indicizza documento per ricerca
     */
    private function indicizzaDocumento($documento_id, $titolo) {
        // Indicizza titolo con peso alto
        db_insert('documenti_indice_ricerca', [
            'documento_id' => $documento_id,
            'tipo_contenuto' => 'titolo',
            'contenuto_indicizzato' => $titolo,
            'peso' => 3
        ]);
    }
    
    /**
     * Ricerca documenti multi-norma
     */
    public function ricercaAvanzata($query, $filtri = []) {
        $auth = \Auth::getInstance();
        $azienda_id = $auth->getCurrentCompany();
        
        // Log ricerca
        $start_time = microtime(true);
        
        // Prepara parametri
        $params = [
            $query,
            $azienda_id,
            $filtri['standard_id'] ?? null,
            $filtri['limite'] ?? 20,
            $filtri['offset'] ?? 0
        ];
        
        // Esegui ricerca
        $stmt = $this->db->prepare("CALL sp_ricerca_documenti(?, ?, ?, ?, ?)");
        $stmt->execute($params);
        $risultati = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Log performance
        $tempo_ms = (microtime(true) - $start_time) * 1000;
        
        db_insert('log_ricerche', [
            'utente_id' => $auth->getUserId(),
            'azienda_id' => $azienda_id,
            'query_ricerca' => $query,
            'numero_risultati' => count($risultati),
            'tempo_esecuzione_ms' => $tempo_ms,
            'filtri_applicati' => json_encode($filtri)
        ]);
        
        return [
            'risultati' => $risultati,
            'totale' => count($risultati),
            'tempo_ms' => $tempo_ms
        ];
    }
    
    /**
     * Prepara download multiplo
     */
    public function preparaDownloadMultiplo($documento_ids, $formato = 'zip') {
        $auth = \Auth::getInstance();
        
        // Verifica permessi su tutti i documenti
        $docs_verificati = [];
        foreach ($documento_ids as $doc_id) {
            if ($this->verificaPermessoDocumento($doc_id, 'download')) {
                $docs_verificati[] = $doc_id;
            }
        }
        
        if (empty($docs_verificati)) {
            throw new Exception("Nessun documento accessibile per il download");
        }
        
        // Crea batch download
        $batch_code = 'DL_' . time() . '_' . uniqid();
        $batch_id = db_insert('download_batch', [
            'codice_batch' => $batch_code,
            'utente_id' => $auth->getUserId(),
            'azienda_id' => $auth->getCurrentCompany(),
            'tipo_export' => $formato,
            'documenti_ids' => json_encode($docs_verificati),
            'stato' => 'in_coda'
        ]);
        
        // Processa in background (qui andrebbe una coda di job)
        // Per ora simuliamo elaborazione immediata
        $this->processaDownloadBatch($batch_id);
        
        return [
            'batch_id' => $batch_id,
            'batch_code' => $batch_code,
            'documenti' => count($docs_verificati),
            'stato' => 'in_elaborazione'
        ];
    }
    
    /**
     * Verifica permesso su documento
     */
    private function verificaPermessoDocumento($documento_id, $tipo_permesso = 'lettura') {
        $auth = \Auth::getInstance();
        
        // Super admin ha sempre accesso
        if ($auth->isSuperAdmin()) {
            return true;
        }
        
        // Verifica appartenenza azienda
        $doc = db_query(
            "SELECT azienda_id FROM documenti WHERE id = ?",
            [$documento_id]
        )->fetch();
        
        if (!$doc || $doc['azienda_id'] != $auth->getCurrentCompany()) {
            return false;
        }
        
        // Verifica permessi specifici
        $campo_permesso = 'permesso_' . $tipo_permesso;
        $permesso = db_query("
            SELECT $campo_permesso
            FROM permessi_documenti_avanzati
            WHERE documento_id = ?
            AND soggetto_tipo = 'utente'
            AND soggetto_id = ?
            AND (data_fine IS NULL OR data_fine > NOW())
        ", [$documento_id, $auth->getUserId()])->fetch();
        
        return $permesso && $permesso[$campo_permesso];
    }
    
    /**
     * Processa batch download (simulato)
     */
    private function processaDownloadBatch($batch_id) {
        // In produzione questo dovrebbe essere asincrono
        db_update('download_batch',
            [
                'stato' => 'in_elaborazione',
                'data_pronto' => null
            ],
            'id = ?',
            [$batch_id]
        );
        
        // Qui andrebbe la logica di creazione ZIP
        // ...
        
        // Simula completamento
        db_update('download_batch',
            [
                'stato' => 'pronto',
                'data_pronto' => date('Y-m-d H:i:s'),
                'data_scadenza' => date('Y-m-d H:i:s', strtotime('+7 days')),
                'file_generato_path' => '/downloads/temp/batch_' . $batch_id . '.zip'
            ],
            'id = ?',
            [$batch_id]
        );
    }
    
    /**
     * Dashboard compliance per azienda
     */
    public function getDashboardCompliance($azienda_id = null) {
        if (!$azienda_id) {
            $azienda_id = \Auth::getInstance()->getCurrentCompany();
        }
        
        return db_query("
            SELECT * FROM v_dashboard_compliance 
            WHERE azienda_id = ?
            ORDER BY standard_nome
        ", [$azienda_id])->fetchAll();
    }
    
    /**
     * Report audit documenti
     */
    public function getAuditReport($documento_id, $giorni = 30) {
        return db_query("
            SELECT 
                ad.*,
                u.nome as utente_nome,
                u.cognome as utente_cognome
            FROM audit_documenti ad
            JOIN utenti u ON ad.utente_id = u.id
            WHERE ad.documento_id = ?
            AND ad.timestamp_azione >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ORDER BY ad.timestamp_azione DESC
        ", [$documento_id, $giorni])->fetchAll();
    }
}