<?php
namespace Nexio\Models;

use PDO;
use Exception;
use Nexio\Utils\Database;
use Nexio\Utils\ActivityLogger;

/**
 * Modello per gestione avanzata del versionamento documenti
 * Include workflow, metadati ISO e gestione revisioni
 */
class DocumentVersion {
    private static $instance = null;
    private $db;
    private $logger;
    private $uploadPath;
    
    private function __construct() {
        $this->db = Database::getInstance();
        $this->logger = ActivityLogger::getInstance();
        $this->uploadPath = __DIR__ . '/../../uploads/documenti/';
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Crea nuovo documento con prima versione
     */
    public function createDocument($data, $fileData = null) {
        try {
            $this->db->beginTransaction();
            
            // Validazione base
            if (empty($data['nome']) || empty($data['id_cartella'])) {
                throw new Exception('Dati documento mancanti');
            }
            
            // Verifica unicità nome nella cartella
            $check = $this->db->prepare("
                SELECT COUNT(*) FROM documenti 
                WHERE id_cartella = ? AND nome = ? AND eliminato = 0
            ");
            $check->execute([$data['id_cartella'], $data['nome']]);
            
            if ($check->fetchColumn() > 0) {
                throw new Exception('Esiste già un documento con questo nome nella cartella');
            }
            
            // Crea documento
            $stmt = $this->db->prepare("
                INSERT INTO documenti (
                    id_cartella, nome, descrizione, tipo_documento,
                    dimensione_file, creato_da
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['id_cartella'],
                $data['nome'],
                $data['descrizione'] ?? null,
                $data['tipo_documento'] ?? 'documento',
                $fileData['size'] ?? 0,
                $data['creato_da']
            ]);
            
            $documentoId = $this->db->lastInsertId();
            
            // Crea metadati ISO se necessario
            if (!empty($data['metadati_iso'])) {
                $this->createISOMetadata($documentoId, $data['metadati_iso']);
            }
            
            // Se c'è un file, crea prima versione
            if ($fileData) {
                $versionData = [
                    'id_documento' => $documentoId,
                    'numero_versione' => '1.0',
                    'file_data' => $fileData,
                    'note_versione' => 'Versione iniziale',
                    'stato_workflow' => $data['stato_workflow'] ?? 'bozza',
                    'caricato_da' => $data['creato_da']
                ];
                
                if (!empty($data['responsabile_revisione'])) {
                    $versionData['responsabile_revisione'] = $data['responsabile_revisione'];
                }
                
                $this->addVersion($versionData);
            }
            
            // Log attività
            $this->logger->log('documento_creato', 'documenti', $documentoId, [
                'nome' => $data['nome'],
                'cartella' => $data['id_cartella']
            ]);
            
            $this->db->commit();
            return $documentoId;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Crea metadati ISO per documento
     */
    private function createISOMetadata($documentoId, $metadati) {
        // Genera codice documento se non fornito
        if (empty($metadati['codice_documento'])) {
            $metadati['codice_documento'] = $this->generateDocumentCode($documentoId, $metadati);
        }
        
        // Calcola prossima revisione
        if (!empty($metadati['frequenza_revisione'])) {
            $metadati['prossima_revisione'] = date('Y-m-d', strtotime("+{$metadati['frequenza_revisione']} days"));
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO documenti_metadati_iso (
                id_documento, codice_documento, tipo_documento,
                livello_distribuzione, responsabile_documento,
                frequenza_revisione, ultima_revisione, prossima_revisione,
                riferimenti_normativi, parole_chiave, processo_correlato
            ) VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $documentoId,
            $metadati['codice_documento'],
            $metadati['tipo_documento'],
            $metadati['livello_distribuzione'] ?? 'interno',
            $metadati['responsabile_documento'] ?? null,
            $metadati['frequenza_revisione'] ?? null,
            $metadati['prossima_revisione'] ?? null,
            $metadati['riferimenti_normativi'] ?? null,
            $metadati['parole_chiave'] ?? null,
            $metadati['processo_correlato'] ?? null
        ]);
    }
    
    /**
     * Genera codice documento univoco
     */
    private function generateDocumentCode($documentoId, $metadati) {
        $prefix = '';
        
        // Prefisso basato su tipo documento
        switch ($metadati['tipo_documento']) {
            case 'procedura':
                $prefix = 'PRO';
                break;
            case 'modulo':
                $prefix = 'MOD';
                break;
            case 'manuale':
                $prefix = 'MAN';
                break;
            case 'politica':
                $prefix = 'POL';
                break;
            case 'registrazione':
                $prefix = 'REG';
                break;
            default:
                $prefix = 'DOC';
        }
        
        // Aggiungi anno e numero progressivo
        $year = date('Y');
        $code = sprintf('%s-%s-%04d', $prefix, $year, $documentoId);
        
        return $code;
    }
    
    /**
     * Aggiunge nuova versione a documento esistente
     */
    public function addVersion($data) {
        try {
            $this->db->beginTransaction();
            
            // Gestisci upload file
            $filePath = null;
            $fileHash = null;
            $fileSize = 0;
            
            if (!empty($data['file_data'])) {
                $fileInfo = $this->handleFileUpload($data['file_data'], $data['id_documento']);
                $filePath = $fileInfo['path'];
                $fileHash = $fileInfo['hash'];
                $fileSize = $fileInfo['size'];
            }
            
            // Inserisci versione
            $stmt = $this->db->prepare("
                INSERT INTO documenti_versioni_extended (
                    id_documento, numero_versione, file_path, dimensione_file,
                    hash_file, responsabile_revisione, data_revisione,
                    prossima_revisione, stato_workflow, approvato_da,
                    data_approvazione, note_versione, metadati, caricato_da
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['id_documento'],
                $data['numero_versione'],
                $filePath,
                $fileSize,
                $fileHash,
                $data['responsabile_revisione'] ?? null,
                $data['data_revisione'] ?? date('Y-m-d'),
                $data['prossima_revisione'] ?? null,
                $data['stato_workflow'] ?? 'bozza',
                $data['approvato_da'] ?? null,
                $data['data_approvazione'] ?? null,
                $data['note_versione'] ?? null,
                isset($data['metadati']) ? json_encode($data['metadati']) : null,
                $data['caricato_da']
            ]);
            
            $versioneId = $this->db->lastInsertId();
            
            // Aggiorna documento principale
            $stmt = $this->db->prepare("
                UPDATE documenti 
                SET ultima_modifica = NOW(), dimensione_file = ?
                WHERE id = ?
            ");
            $stmt->execute([$fileSize, $data['id_documento']]);
            
            // Aggiorna metadati ISO se necessario
            if ($data['stato_workflow'] === 'approvato' && !empty($data['prossima_revisione'])) {
                $stmt = $this->db->prepare("
                    UPDATE documenti_metadati_iso 
                    SET ultima_revisione = CURDATE(), prossima_revisione = ?
                    WHERE id_documento = ?
                ");
                $stmt->execute([$data['prossima_revisione'], $data['id_documento']]);
            }
            
            // Log attività
            $this->logger->log('versione_aggiunta', 'documenti_versioni_extended', $versioneId, [
                'documento' => $data['id_documento'],
                'versione' => $data['numero_versione'],
                'stato' => $data['stato_workflow']
            ]);
            
            $this->db->commit();
            return $versioneId;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Gestisce upload file
     */
    private function handleFileUpload($fileData, $documentoId) {
        // Crea directory se non esiste
        $uploadDir = $this->uploadPath . date('Y/m/');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Genera nome file univoco
        $extension = pathinfo($fileData['name'], PATHINFO_EXTENSION);
        $fileName = sprintf('%d_%s.%s', $documentoId, uniqid(), $extension);
        $fullPath = $uploadDir . $fileName;
        $relativePath = date('Y/m/') . $fileName;
        
        // Sposta file
        if (!move_uploaded_file($fileData['tmp_name'], $fullPath)) {
            throw new Exception('Errore durante il caricamento del file');
        }
        
        // Calcola hash
        $hash = hash_file('sha256', $fullPath);
        
        return [
            'path' => $relativePath,
            'hash' => $hash,
            'size' => filesize($fullPath)
        ];
    }
    
    /**
     * Approva versione documento
     */
    public function approveVersion($versioneId, $approvatoDa) {
        try {
            $this->db->beginTransaction();
            
            // Aggiorna stato versione
            $stmt = $this->db->prepare("
                UPDATE documenti_versioni_extended 
                SET stato_workflow = 'approvato',
                    approvato_da = ?,
                    data_approvazione = NOW()
                WHERE id = ? AND stato_workflow != 'approvato'
            ");
            $stmt->execute([$approvatoDa, $versioneId]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception('Versione già approvata o non trovata');
            }
            
            // Ottieni info versione
            $stmt = $this->db->prepare("
                SELECT * FROM documenti_versioni_extended WHERE id = ?
            ");
            $stmt->execute([$versioneId]);
            $versione = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Aggiorna metadati ISO
            if ($versione['prossima_revisione']) {
                $stmt = $this->db->prepare("
                    UPDATE documenti_metadati_iso 
                    SET ultima_revisione = CURDATE(),
                        prossima_revisione = ?
                    WHERE id_documento = ?
                ");
                $stmt->execute([
                    $versione['prossima_revisione'],
                    $versione['id_documento']
                ]);
            }
            
            // Log attività
            $this->logger->log('versione_approvata', 'documenti_versioni_extended', $versioneId, [
                'documento' => $versione['id_documento'],
                'versione' => $versione['numero_versione'],
                'approvato_da' => $approvatoDa
            ]);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Ottieni storico versioni documento
     */
    public function getVersionHistory($documentoId) {
        $stmt = $this->db->prepare("
            SELECT v.*, 
                uc.nome AS nome_caricatore,
                ua.nome AS nome_approvatore,
                ur.nome AS nome_responsabile
            FROM documenti_versioni_extended v
            LEFT JOIN utenti uc ON v.caricato_da = uc.id
            LEFT JOIN utenti ua ON v.approvato_da = ua.id
            LEFT JOIN utenti ur ON v.responsabile_revisione = ur.id
            WHERE v.id_documento = ?
            ORDER BY v.id DESC
        ");
        $stmt->execute([$documentoId]);
        
        $versions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decodifica metadati
        foreach ($versions as &$version) {
            $version['metadati'] = json_decode($version['metadati'], true) ?? [];
            $version['dimensione_formattata'] = $this->formatFileSize($version['dimensione_file']);
        }
        
        return $versions;
    }
    
    /**
     * Confronta due versioni
     */
    public function compareVersions($versioneId1, $versioneId2) {
        $stmt = $this->db->prepare("
            SELECT * FROM documenti_versioni_extended 
            WHERE id IN (?, ?)
        ");
        $stmt->execute([$versioneId1, $versioneId2]);
        
        $versions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $versions[$row['id']] = $row;
        }
        
        if (count($versions) !== 2) {
            throw new Exception('Versioni non trovate');
        }
        
        // Confronta campi principali
        $comparison = [
            'version_1' => $versions[$versioneId1],
            'version_2' => $versions[$versioneId2],
            'differences' => []
        ];
        
        // Campi da confrontare
        $fieldsToCompare = [
            'numero_versione', 'stato_workflow', 'responsabile_revisione',
            'data_revisione', 'prossima_revisione', 'dimensione_file', 'hash_file'
        ];
        
        foreach ($fieldsToCompare as $field) {
            if ($versions[$versioneId1][$field] !== $versions[$versioneId2][$field]) {
                $comparison['differences'][] = [
                    'field' => $field,
                    'old_value' => $versions[$versioneId1][$field],
                    'new_value' => $versions[$versioneId2][$field]
                ];
            }
        }
        
        return $comparison;
    }
    
    /**
     * Ottieni documenti in scadenza revisione
     */
    public function getDocumentsNearRevision($giorni = 30, $spazioId = null) {
        $sql = "
            SELECT d.*, dmi.*, 
                c.nome AS nome_cartella,
                c.percorso_completo,
                u.nome AS nome_responsabile,
                s.nome AS nome_spazio
            FROM documenti d
            INNER JOIN documenti_metadati_iso dmi ON d.id = dmi.id_documento
            INNER JOIN cartelle c ON d.id_cartella = c.id
            INNER JOIN spazi_documentali s ON c.id_spazio = s.id
            LEFT JOIN utenti u ON dmi.responsabile_documento = u.id
            WHERE dmi.prossima_revisione IS NOT NULL
            AND dmi.prossima_revisione <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
            AND d.eliminato = 0
        ";
        
        $params = [$giorni];
        
        if ($spazioId) {
            $sql .= " AND c.id_spazio = ?";
            $params[] = $spazioId;
        }
        
        $sql .= " ORDER BY dmi.prossima_revisione";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Formatta dimensione file
     */
    private function formatFileSize($bytes) {
        if ($bytes == 0) return '0 Bytes';
        
        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));
        
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
}