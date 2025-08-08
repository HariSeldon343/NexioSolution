<?php
namespace Nexio\Models;

use PDO;
use Exception;
use Nexio\Utils\Database;
use Nexio\Utils\ActivityLogger;

/**
 * Modello per la gestione degli spazi documentali isolati
 * Gestisce spazi super admin e spazi azienda con isolamento completo
 */
class DocumentSpace {
    private static $instance = null;
    private $db;
    private $logger;
    
    private function __construct() {
        $this->db = Database::getInstance();
        $this->logger = ActivityLogger::getInstance();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Crea un nuovo spazio documentale
     */
    public function createSpace($data) {
        try {
            $this->db->beginTransaction();
            
            // Validazione tipo spazio
            if (!in_array($data['tipo_spazio'], ['super_admin', 'azienda'])) {
                throw new Exception('Tipo spazio non valido');
            }
            
            // Per spazio super_admin verifica unicità
            if ($data['tipo_spazio'] === 'super_admin') {
                $check = $this->db->prepare("
                    SELECT COUNT(*) FROM spazi_documentali 
                    WHERE tipo_spazio = 'super_admin'
                ");
                $check->execute();
                if ($check->fetchColumn() > 0) {
                    throw new Exception('Spazio super admin già esistente');
                }
                $data['id_azienda'] = null;
            }
            
            // Inserimento spazio
            $stmt = $this->db->prepare("
                INSERT INTO spazi_documentali (
                    tipo_spazio, id_azienda, nome, descrizione, 
                    modalita_gestione, norme_iso, configurazione, creato_da
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['tipo_spazio'],
                $data['id_azienda'] ?? null,
                $data['nome'],
                $data['descrizione'] ?? null,
                $data['modalita_gestione'] ?? 'separata',
                isset($data['norme_iso']) ? json_encode($data['norme_iso']) : null,
                isset($data['configurazione']) ? json_encode($data['configurazione']) : null,
                $data['creato_da']
            ]);
            
            $spazioId = $this->db->lastInsertId();
            
            // Crea cartella root per lo spazio
            $this->createRootFolder($spazioId, $data['creato_da']);
            
            // Se ci sono norme ISO, crea le strutture
            if (!empty($data['norme_iso'])) {
                $this->createISOStructures($spazioId, $data['norme_iso'], $data['modalita_gestione'], $data['creato_da']);
            }
            
            // Log attività
            $this->logger->log('spazio_creato', 'spazi_documentali', $spazioId, [
                'tipo_spazio' => $data['tipo_spazio'],
                'nome' => $data['nome'],
                'norme_iso' => $data['norme_iso'] ?? []
            ]);
            
            $this->db->commit();
            return $spazioId;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Crea la cartella root per uno spazio
     */
    private function createRootFolder($spazioId, $userId) {
        $stmt = $this->db->prepare("
            INSERT INTO cartelle (
                id_spazio, nome, descrizione, id_cartella_padre, 
                percorso_completo, tipo_cartella, creata_da
            ) VALUES (?, 'Root', 'Cartella principale', NULL, '/', 'sistema', ?)
        ");
        $stmt->execute([$spazioId, $userId]);
    }
    
    /**
     * Crea le strutture ISO per uno spazio
     */
    private function createISOStructures($spazioId, $normeISO, $modalita, $userId) {
        // Recupera le strutture ISO dal database
        $stmt = $this->db->prepare("
            SELECT * FROM strutture_iso 
            WHERE codice_norma IN (" . str_repeat('?,', count($normeISO) - 1) . "?)
            AND attivo = 1
        ");
        $stmt->execute($normeISO);
        $strutture = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Ottieni la cartella root dello spazio
        $rootFolder = $this->getRootFolder($spazioId);
        
        if ($modalita === 'integrata') {
            // Crea una struttura unificata
            $this->createIntegratedStructure($spazioId, $rootFolder['id'], $strutture, $userId);
        } else {
            // Crea strutture separate per ogni norma
            foreach ($strutture as $struttura) {
                $this->createSeparateStructure($spazioId, $rootFolder['id'], $struttura, $userId);
            }
        }
    }
    
    /**
     * Crea struttura integrata per più norme ISO
     */
    private function createIntegratedStructure($spazioId, $parentId, $strutture, $userId) {
        // Crea cartella principale per sistema integrato
        $stmt = $this->db->prepare("
            INSERT INTO cartelle (
                id_spazio, id_cartella_padre, nome, descrizione, 
                percorso_completo, tipo_cartella, creata_da
            ) VALUES (?, ?, 'Sistema_Integrato', 'Sistema di gestione integrato', ?, 'iso', ?)
        ");
        $stmt->execute([$spazioId, $parentId, '/Sistema_Integrato', $userId]);
        $sistemaId = $this->db->lastInsertId();
        
        // Unifica le cartelle comuni
        $cartelleUnificate = [];
        foreach ($strutture as $struttura) {
            $cartelle = json_decode($struttura['struttura_cartelle'], true);
            foreach ($cartelle as $cartella) {
                $nome = $cartella['nome'];
                if (!isset($cartelleUnificate[$nome])) {
                    $cartelleUnificate[$nome] = $cartella;
                    $cartelleUnificate[$nome]['norme'] = [];
                }
                $cartelleUnificate[$nome]['norme'][] = $struttura['codice_norma'];
            }
        }
        
        // Crea le cartelle unificate
        foreach ($cartelleUnificate as $nome => $cartella) {
            $stmt = $this->db->prepare("
                INSERT INTO cartelle (
                    id_spazio, id_cartella_padre, nome, descrizione,
                    percorso_completo, tipo_cartella, norma_iso, metadati, creata_da
                ) VALUES (?, ?, ?, ?, ?, 'iso', ?, ?, ?)
            ");
            
            $metadati = json_encode([
                'icona' => $cartella['icona'] ?? 'fas fa-folder',
                'norme_applicabili' => $cartella['norme']
            ]);
            
            $stmt->execute([
                $spazioId,
                $sistemaId,
                $nome,
                $cartella['descrizione'] ?? null,
                "/Sistema_Integrato/{$nome}",
                implode(',', $cartella['norme']),
                $metadati,
                $userId
            ]);
        }
    }
    
    /**
     * Crea struttura separata per una norma ISO
     */
    private function createSeparateStructure($spazioId, $parentId, $struttura, $userId) {
        // Crea cartella principale per la norma
        $nomeCartella = "ISO_{$struttura['codice_norma']}";
        $stmt = $this->db->prepare("
            INSERT INTO cartelle (
                id_spazio, id_cartella_padre, nome, descrizione,
                percorso_completo, tipo_cartella, norma_iso, creata_da
            ) VALUES (?, ?, ?, ?, ?, 'iso', ?, ?)
        ");
        
        $stmt->execute([
            $spazioId,
            $parentId,
            $nomeCartella,
            $struttura['nome_norma'],
            "/{$nomeCartella}",
            'iso',
            $struttura['codice_norma'],
            $userId
        ]);
        
        $normaId = $this->db->lastInsertId();
        
        // Crea sottocartelle dalla struttura
        $cartelle = json_decode($struttura['struttura_cartelle'], true);
        foreach ($cartelle as $cartella) {
            $stmt = $this->db->prepare("
                INSERT INTO cartelle (
                    id_spazio, id_cartella_padre, nome, descrizione,
                    percorso_completo, tipo_cartella, norma_iso, metadati, creata_da
                ) VALUES (?, ?, ?, ?, ?, 'iso', ?, ?, ?)
            ");
            
            $metadati = json_encode(['icona' => $cartella['icona'] ?? 'fas fa-folder']);
            
            $stmt->execute([
                $spazioId,
                $normaId,
                $cartella['nome'],
                $cartella['descrizione'] ?? null,
                "/{$nomeCartella}/{$cartella['nome']}",
                'iso',
                $struttura['codice_norma'],
                $metadati,
                $userId
            ]);
        }
    }
    
    /**
     * Ottiene la cartella root di uno spazio
     */
    private function getRootFolder($spazioId) {
        $stmt = $this->db->prepare("
            SELECT * FROM cartelle 
            WHERE id_spazio = ? AND id_cartella_padre IS NULL
            LIMIT 1
        ");
        $stmt->execute([$spazioId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Ottiene gli spazi accessibili all'utente
     */
    public function getUserSpaces($userId, $isSuperAdmin = false) {
        if ($isSuperAdmin) {
            // Super admin vede tutti gli spazi
            $stmt = $this->db->prepare("
                SELECT s.*, a.nome AS nome_azienda,
                    COUNT(DISTINCT c.id) AS numero_cartelle,
                    COUNT(DISTINCT d.id) AS numero_documenti
                FROM spazi_documentali s
                LEFT JOIN aziende a ON s.id_azienda = a.id
                LEFT JOIN cartelle c ON s.id = c.id_spazio AND c.eliminata = 0 AND c.cestinata = 0
                LEFT JOIN documenti d ON c.id = d.id_cartella AND d.eliminato = 0
                GROUP BY s.id
                ORDER BY s.tipo_spazio DESC, s.nome
            ");
            $stmt->execute();
        } else {
            // Utenti normali vedono solo gli spazi della loro azienda
            $stmt = $this->db->prepare("
                SELECT s.*, a.nome AS nome_azienda,
                    COUNT(DISTINCT c.id) AS numero_cartelle,
                    COUNT(DISTINCT d.id) AS numero_documenti
                FROM spazi_documentali s
                INNER JOIN utenti_aziende ua ON s.id_azienda = ua.id_azienda
                LEFT JOIN aziende a ON s.id_azienda = a.id
                LEFT JOIN cartelle c ON s.id = c.id_spazio AND c.eliminata = 0 AND c.cestinata = 0
                LEFT JOIN documenti d ON c.id = d.id_cartella AND d.eliminato = 0
                WHERE ua.id_utente = ? AND s.tipo_spazio = 'azienda'
                GROUP BY s.id
                ORDER BY s.nome
            ");
            $stmt->execute([$userId]);
        }
        
        $spaces = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decodifica JSON fields
        foreach ($spaces as &$space) {
            $space['norme_iso'] = json_decode($space['norme_iso'], true) ?? [];
            $space['configurazione'] = json_decode($space['configurazione'], true) ?? [];
        }
        
        return $spaces;
    }
    
    /**
     * Verifica se un utente può accedere a uno spazio
     */
    public function canAccessSpace($userId, $spazioId, $isSuperAdmin = false) {
        // Super admin può accedere a tutti gli spazi
        if ($isSuperAdmin) {
            return true;
        }
        
        // Verifica se lo spazio appartiene a un'azienda dell'utente
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM spazi_documentali s
            INNER JOIN utenti_aziende ua ON s.id_azienda = ua.id_azienda
            WHERE s.id = ? AND ua.id_utente = ? AND s.tipo_spazio = 'azienda'
        ");
        $stmt->execute([$spazioId, $userId]);
        
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Aggiorna configurazione spazio
     */
    public function updateSpace($spazioId, $data, $userId) {
        try {
            $this->db->beginTransaction();
            
            $updates = [];
            $params = [];
            
            if (isset($data['nome'])) {
                $updates[] = 'nome = ?';
                $params[] = $data['nome'];
            }
            
            if (isset($data['descrizione'])) {
                $updates[] = 'descrizione = ?';
                $params[] = $data['descrizione'];
            }
            
            if (isset($data['modalita_gestione'])) {
                $updates[] = 'modalita_gestione = ?';
                $params[] = $data['modalita_gestione'];
            }
            
            if (isset($data['norme_iso'])) {
                $updates[] = 'norme_iso = ?';
                $params[] = json_encode($data['norme_iso']);
            }
            
            if (isset($data['configurazione'])) {
                $updates[] = 'configurazione = ?';
                $params[] = json_encode($data['configurazione']);
            }
            
            if (empty($updates)) {
                throw new Exception('Nessun dato da aggiornare');
            }
            
            $params[] = $spazioId;
            
            $sql = "UPDATE spazi_documentali SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            // Log attività
            $this->logger->log('spazio_aggiornato', 'spazi_documentali', $spazioId, $data);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Ottiene dettaglio spazio con statistiche
     */
    public function getSpaceDetails($spazioId) {
        $stmt = $this->db->prepare("
            SELECT s.*, a.nome AS nome_azienda,
                (SELECT COUNT(*) FROM cartelle WHERE id_spazio = s.id AND eliminata = 0 AND cestinata = 0) AS totale_cartelle,
                (SELECT COUNT(*) FROM documenti d 
                 INNER JOIN cartelle c ON d.id_cartella = c.id 
                 WHERE c.id_spazio = s.id AND d.eliminato = 0) AS totale_documenti,
                (SELECT SUM(d.dimensione_file) FROM documenti d 
                 INNER JOIN cartelle c ON d.id_cartella = c.id 
                 WHERE c.id_spazio = s.id AND d.eliminato = 0) AS dimensione_totale,
                (SELECT COUNT(*) FROM cestino_documenti WHERE id_spazio = s.id) AS elementi_cestino
            FROM spazi_documentali s
            LEFT JOIN aziende a ON s.id_azienda = a.id
            WHERE s.id = ?
        ");
        $stmt->execute([$spazioId]);
        $space = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($space) {
            $space['norme_iso'] = json_decode($space['norme_iso'], true) ?? [];
            $space['configurazione'] = json_decode($space['configurazione'], true) ?? [];
            $space['dimensione_formattata'] = $this->formatFileSize($space['dimensione_totale'] ?? 0);
        }
        
        return $space;
    }
    
    /**
     * Formatta dimensione file
     */
    private function formatFileSize($bytes) {
        if ($bytes == 0) return '0 Bytes';
        
        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes) / log($k));
        
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
}