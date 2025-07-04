<?php
require_once __DIR__ . '/../config/database.php';

class ActivityLogger {
    private static $instance = null;
    
    private function __construct() {
        // Non è più necessario un oggetto database, usiamo le funzioni globali
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function log($tipo_entita, $azione, $entita_id = null, $dettagli = '') {
        try {
            $auth = Auth::getInstance();
            $user = $auth->getUser();
            $currentAzienda = $auth->getCurrentAzienda();
            
            $azienda_id = null;
            if ($currentAzienda) {
                $azienda_id = isset($currentAzienda['azienda_id']) ? $currentAzienda['azienda_id'] : 
                             (isset($currentAzienda['id']) ? $currentAzienda['id'] : null);
            }
            
            $sql = "INSERT INTO log_attivita (azienda_id, utente_id, tipo_entita, azione, id_entita, dettagli, ip_address) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $azienda_id,
                $user ? $user['id'] : null,
                $tipo_entita,
                $azione,
                $entita_id,
                $dettagli,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ];
            
            db_query($sql, $params);
            return true;
        } catch (Exception $e) {
            // Ignora errori per non bloccare l'applicazione
            error_log("ActivityLogger error: " . $e->getMessage());
            return false;
        }
    }
    
    // Metodi di convenienza
    public function logLogin($user_id) {
        $this->log('accesso', 'login', null, 'Login effettuato');
    }
    
    public function logLogout($user_id) {
        $this->log('accesso', 'logout', null, 'Logout effettuato');
    }
    
    public function logDocumento($azione, $doc_id, $nome_doc) {
        $dettagli = ucfirst($azione) . " documento: " . $nome_doc;
        $this->log('documento', $azione, $doc_id, $dettagli);
    }
    
    public function logEvento($azione, $evento_id, $nome_evento) {
        $dettagli = ucfirst($azione) . " evento: " . $nome_evento;
        $this->log('evento', $azione, $evento_id, $dettagli);
    }
    
    public function logAzienda($azione, $azienda_id, $nome_azienda) {
        $dettagli = ucfirst($azione) . " azienda: " . $nome_azienda;
        $this->log('azienda', $azione, $azienda_id, $dettagli);
    }
    
    /**
     * Log creazione documento
     */
    public function logDocumentoCreato($documento_id, $titolo) {
        $this->logDocumento('creato', $documento_id, $titolo);
    }
    
    /**
     * Log modifica documento
     */
    public function logDocumentoModificato($documento_id, $titolo, $vecchi_dati = [], $nuovi_dati = []) {
        $dettagli = "Modificato documento: " . $titolo;
        if (!empty($vecchi_dati) || !empty($nuovi_dati)) {
            $dettagli .= " | Modifiche: " . json_encode([
                'prima' => $vecchi_dati,
                'dopo' => $nuovi_dati
            ]);
        }
        $this->log('documento', 'modificato', $documento_id, $dettagli);
    }
    
    /**
     * Ottieni log per azienda
     */
    public function getLogsByAzienda($azienda_id, $limit = 100, $offset = 0) {
        try {
            $sql = "SELECT * FROM log_attivita 
                    WHERE azienda_id = ? 
                    ORDER BY creato_il DESC 
                    LIMIT ? OFFSET ?";
            
            $stmt = db_connection()->prepare($sql);
            $stmt->bindValue(1, $azienda_id, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->bindValue(3, $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("getLogsByAzienda error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Ottieni log per tipo entità
     */
    public function getLogsByTipo($tipo_entita, $azienda_id = null, $limit = 100) {
        try {
            $sql = "SELECT * FROM log_attivita WHERE tipo_entita = ?";
            $params = [$tipo_entita];
            
            if ($azienda_id) {
                $sql .= " AND azienda_id = ?";
                $params[] = $azienda_id;
            }
            
            $sql .= " ORDER BY creato_il DESC LIMIT ?";
            $params[] = $limit;
            
            $stmt = db_connection()->prepare($sql);
            for ($i = 0; $i < count($params); $i++) {
                if ($i === count($params) - 1) { // ultimo parametro è limit
                    $stmt->bindValue($i + 1, $params[$i], PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($i + 1, $params[$i]);
                }
            }
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("getLogsByTipo error: " . $e->getMessage());
            return [];
        }
    }
} 