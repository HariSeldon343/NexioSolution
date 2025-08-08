<?php
namespace Nexio\Models;

use PDO;
use Exception;
use Nexio\Utils\Database;
use Nexio\Utils\ActivityLogger;
use ZipArchive;

/**
 * Modello avanzato per la gestione delle cartelle
 * Include supporto per cestino, download ZIP, e gestione avanzata
 */
class AdvancedFolder {
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
     * Crea una nuova cartella
     */
    public function createFolder($data) {
        try {
            $this->db->beginTransaction();
            
            // Validazione
            if (empty($data['nome']) || empty($data['id_spazio'])) {
                throw new Exception('Dati mancanti per la creazione della cartella');
            }
            
            // Calcola percorso completo
            $percorsoCompleto = '/';
            if (!empty($data['id_cartella_padre'])) {
                $padre = $this->getFolder($data['id_cartella_padre']);
                if (!$padre) {
                    throw new Exception('Cartella padre non trovata');
                }
                $percorsoCompleto = rtrim($padre['percorso_completo'], '/') . '/' . $data['nome'];
            } else {
                $percorsoCompleto = '/' . $data['nome'];
            }
            
            // Verifica unicità nome nella stessa cartella
            $check = $this->db->prepare("
                SELECT COUNT(*) FROM cartelle 
                WHERE id_spazio = ? AND nome = ? 
                AND (id_cartella_padre = ? OR (id_cartella_padre IS NULL AND ? IS NULL))
                AND eliminata = 0 AND cestinata = 0
            ");
            $check->execute([
                $data['id_spazio'],
                $data['nome'],
                $data['id_cartella_padre'] ?? null,
                $data['id_cartella_padre'] ?? null
            ]);
            
            if ($check->fetchColumn() > 0) {
                throw new Exception('Esiste già una cartella con questo nome');
            }
            
            // Inserimento
            $stmt = $this->db->prepare("
                INSERT INTO cartelle (
                    id_spazio, id_cartella_padre, nome, descrizione,
                    percorso_completo, tipo_cartella, norma_iso, metadati, creata_da
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['id_spazio'],
                $data['id_cartella_padre'] ?? null,
                $data['nome'],
                $data['descrizione'] ?? null,
                $percorsoCompleto,
                $data['tipo_cartella'] ?? 'normale',
                $data['norma_iso'] ?? null,
                isset($data['metadati']) ? json_encode($data['metadati']) : null,
                $data['creata_da']
            ]);
            
            $cartellaId = $this->db->lastInsertId();
            
            // Log attività
            $this->logger->log('cartella_creata', 'cartelle', $cartellaId, [
                'nome' => $data['nome'],
                'percorso' => $percorsoCompleto
            ]);
            
            $this->db->commit();
            return $cartellaId;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Sposta cartella nel cestino (soft delete)
     */
    public function moveToTrash($cartellaId, $userId) {
        try {
            $this->db->beginTransaction();
            
            // Ottieni dettagli cartella
            $cartella = $this->getFolder($cartellaId);
            if (!$cartella) {
                throw new Exception('Cartella non trovata');
            }
            
            // Marca come cestinata
            $stmt = $this->db->prepare("
                UPDATE cartelle 
                SET cestinata = 1, data_cestino = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$cartellaId]);
            
            // Crea record nel cestino
            $stmt = $this->db->prepare("
                INSERT INTO cestino_documenti (
                    tipo_oggetto, id_oggetto, id_spazio, dati_oggetto,
                    percorso_originale, eliminato_da, scadenza_cestino
                ) VALUES (
                    'cartella', ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY)
                )
            ");
            
            $stmt->execute([
                $cartellaId,
                $cartella['id_spazio'],
                json_encode($cartella),
                $cartella['percorso_completo'],
                $userId
            ]);
            
            // Cestina anche sottocartelle e documenti
            $this->trashSubItems($cartellaId, $cartella['id_spazio'], $userId);
            
            // Log attività
            $this->logger->log('cartella_cestinata', 'cartelle', $cartellaId, [
                'nome' => $cartella['nome'],
                'percorso' => $cartella['percorso_completo']
            ]);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Cestina ricorsivamente sottocartelle e documenti
     */
    private function trashSubItems($cartellaId, $spazioId, $userId) {
        // Cestina sottocartelle
        $stmt = $this->db->prepare("
            SELECT id FROM cartelle 
            WHERE id_cartella_padre = ? AND cestinata = 0
        ");
        $stmt->execute([$cartellaId]);
        
        while ($sub = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->moveToTrash($sub['id'], $userId);
        }
        
        // Cestina documenti
        $stmt = $this->db->prepare("
            SELECT * FROM documenti 
            WHERE id_cartella = ? AND eliminato = 0
        ");
        $stmt->execute([$cartellaId]);
        
        $documenti = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($documenti as $doc) {
            // Marca documento come eliminato
            $update = $this->db->prepare("
                UPDATE documenti SET eliminato = 1 WHERE id = ?
            ");
            $update->execute([$doc['id']]);
            
            // Aggiungi al cestino
            $insert = $this->db->prepare("
                INSERT INTO cestino_documenti (
                    tipo_oggetto, id_oggetto, id_spazio, dati_oggetto,
                    percorso_originale, eliminato_da, scadenza_cestino
                ) VALUES (
                    'documento', ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY)
                )
            ");
            
            $insert->execute([
                $doc['id'],
                $spazioId,
                json_encode($doc),
                $doc['nome'],
                $userId
            ]);
        }
    }
    
    /**
     * Ripristina elemento dal cestino
     */
    public function restoreFromTrash($cestinoId, $userId) {
        try {
            $this->db->beginTransaction();
            
            // Ottieni record cestino
            $stmt = $this->db->prepare("
                SELECT * FROM cestino_documenti WHERE id = ?
            ");
            $stmt->execute([$cestinoId]);
            $cestino = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$cestino) {
                throw new Exception('Elemento non trovato nel cestino');
            }
            
            if ($cestino['tipo_oggetto'] === 'cartella') {
                // Ripristina cartella
                $stmt = $this->db->prepare("
                    UPDATE cartelle 
                    SET cestinata = 0, data_cestino = NULL 
                    WHERE id = ?
                ");
                $stmt->execute([$cestino['id_oggetto']]);
                
                // Ripristina anche sottoelementi
                $this->restoreSubItems($cestino['id_oggetto']);
                
            } else {
                // Ripristina documento
                $stmt = $this->db->prepare("
                    UPDATE documenti 
                    SET eliminato = 0 
                    WHERE id = ?
                ");
                $stmt->execute([$cestino['id_oggetto']]);
            }
            
            // Rimuovi dal cestino
            $stmt = $this->db->prepare("
                DELETE FROM cestino_documenti WHERE id = ?
            ");
            $stmt->execute([$cestinoId]);
            
            // Log attività
            $this->logger->log('elemento_ripristinato', 'cestino_documenti', $cestinoId, [
                'tipo' => $cestino['tipo_oggetto'],
                'id_oggetto' => $cestino['id_oggetto']
            ]);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Ripristina ricorsivamente sottoelementi
     */
    private function restoreSubItems($cartellaId) {
        // Ripristina sottocartelle
        $stmt = $this->db->prepare("
            UPDATE cartelle 
            SET cestinata = 0, data_cestino = NULL 
            WHERE id_cartella_padre = ? AND cestinata = 1
        ");
        $stmt->execute([$cartellaId]);
        
        // Ottieni sottocartelle per ripristino ricorsivo
        $stmt = $this->db->prepare("
            SELECT id FROM cartelle WHERE id_cartella_padre = ?
        ");
        $stmt->execute([$cartellaId]);
        
        while ($sub = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->restoreSubItems($sub['id']);
        }
        
        // Ripristina documenti
        $stmt = $this->db->prepare("
            UPDATE documenti d
            INNER JOIN cestino_documenti c ON d.id = c.id_oggetto
            SET d.eliminato = 0
            WHERE d.id_cartella = ? AND c.tipo_oggetto = 'documento'
        ");
        $stmt->execute([$cartellaId]);
        
        // Rimuovi documenti dal cestino
        $stmt = $this->db->prepare("
            DELETE c FROM cestino_documenti c
            INNER JOIN documenti d ON c.id_oggetto = d.id
            WHERE d.id_cartella = ? AND c.tipo_oggetto = 'documento'
        ");
        $stmt->execute([$cartellaId]);
    }
    
    /**
     * Scarica cartella come ZIP
     */
    public function downloadAsZip($cartellaId, $userId) {
        $cartella = $this->getFolder($cartellaId);
        if (!$cartella) {
            throw new Exception('Cartella non trovata');
        }
        
        // Crea file ZIP temporaneo
        $zipPath = sys_get_temp_dir() . '/' . uniqid('folder_') . '.zip';
        $zip = new ZipArchive();
        
        if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
            throw new Exception('Impossibile creare file ZIP');
        }
        
        // Aggiungi cartella e contenuti ricorsivamente
        $this->addFolderToZip($zip, $cartellaId, '');
        
        $zip->close();
        
        // Log attività
        $this->logger->log('cartella_scaricata', 'cartelle', $cartellaId, [
            'nome' => $cartella['nome'],
            'dimensione' => filesize($zipPath)
        ]);
        
        return $zipPath;
    }
    
    /**
     * Aggiunge ricorsivamente cartella e contenuti a ZIP
     */
    private function addFolderToZip($zip, $cartellaId, $basePath) {
        // Ottieni info cartella
        $cartella = $this->getFolder($cartellaId);
        $currentPath = $basePath ? $basePath . '/' . $cartella['nome'] : $cartella['nome'];
        
        // Crea cartella nel ZIP
        $zip->addEmptyDir($currentPath);
        
        // Aggiungi documenti
        $stmt = $this->db->prepare("
            SELECT d.*, dv.file_path 
            FROM documenti d
            LEFT JOIN documenti_versioni dv ON d.id = dv.id_documento AND dv.versione_corrente = 1
            WHERE d.id_cartella = ? AND d.eliminato = 0
        ");
        $stmt->execute([$cartellaId]);
        
        while ($doc = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $filePath = $this->uploadPath . $doc['file_path'];
            if (file_exists($filePath)) {
                $zip->addFile($filePath, $currentPath . '/' . $doc['nome']);
            }
        }
        
        // Aggiungi sottocartelle ricorsivamente
        $stmt = $this->db->prepare("
            SELECT id FROM cartelle 
            WHERE id_cartella_padre = ? AND eliminata = 0 AND cestinata = 0
        ");
        $stmt->execute([$cartellaId]);
        
        while ($sub = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->addFolderToZip($zip, $sub['id'], $currentPath);
        }
    }
    
    /**
     * Ottieni dettagli cartella
     */
    public function getFolder($cartellaId) {
        $stmt = $this->db->prepare("
            SELECT c.*, s.nome AS nome_spazio, s.tipo_spazio,
                COUNT(DISTINCT d.id) AS numero_documenti,
                COUNT(DISTINCT cs.id) AS numero_sottocartelle,
                SUM(d.dimensione_file) AS dimensione_totale
            FROM cartelle c
            LEFT JOIN spazi_documentali s ON c.id_spazio = s.id
            LEFT JOIN documenti d ON c.id = d.id_cartella AND d.eliminato = 0
            LEFT JOIN cartelle cs ON c.id = cs.id_cartella_padre AND cs.eliminata = 0 AND cs.cestinata = 0
            WHERE c.id = ?
            GROUP BY c.id
        ");
        $stmt->execute([$cartellaId]);
        $folder = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($folder) {
            $folder['metadati'] = json_decode($folder['metadati'], true) ?? [];
        }
        
        return $folder;
    }
    
    /**
     * Ottieni contenuto cartella (sottocartelle e documenti)
     */
    public function getFolderContents($cartellaId, $includeTrash = false) {
        $trashCondition = $includeTrash ? '' : 'AND cestinata = 0';
        
        // Sottocartelle
        $stmt = $this->db->prepare("
            SELECT *, 'folder' AS tipo 
            FROM cartelle 
            WHERE id_cartella_padre = ? AND eliminata = 0 {$trashCondition}
            ORDER BY nome
        ");
        $stmt->execute([$cartellaId]);
        $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Documenti
        $stmt = $this->db->prepare("
            SELECT d.*, 'document' AS tipo,
                dv.numero_versione, dv.stato_workflow,
                u.nome AS nome_creatore
            FROM documenti d
            LEFT JOIN documenti_versioni_extended dv ON d.id = dv.id_documento 
                AND dv.id = (SELECT MAX(id) FROM documenti_versioni_extended WHERE id_documento = d.id)
            LEFT JOIN utenti u ON d.creato_da = u.id
            WHERE d.id_cartella = ? AND d.eliminato = 0
            ORDER BY d.nome
        ");
        $stmt->execute([$cartellaId]);
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'folders' => $folders,
            'documents' => $documents
        ];
    }
    
    /**
     * Ottieni percorso breadcrumb
     */
    public function getBreadcrumb($cartellaId) {
        $breadcrumb = [];
        $currentId = $cartellaId;
        
        while ($currentId) {
            $stmt = $this->db->prepare("
                SELECT id, nome, id_cartella_padre 
                FROM cartelle 
                WHERE id = ?
            ");
            $stmt->execute([$currentId]);
            $folder = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($folder) {
                array_unshift($breadcrumb, [
                    'id' => $folder['id'],
                    'nome' => $folder['nome']
                ]);
                $currentId = $folder['id_cartella_padre'];
            } else {
                break;
            }
        }
        
        return $breadcrumb;
    }
}