<?php
require_once __DIR__ . '/../config/config.php';

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
            
            // Converti dettagli in JSON se non lo è già
            if (!empty($dettagli) && !is_string($dettagli)) {
                $dettagli_json = json_encode($dettagli);
            } elseif (!empty($dettagli) && is_string($dettagli)) {
                // Verifica se è già JSON valido
                json_decode($dettagli);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    // Non è JSON, convertilo
                    $dettagli_json = json_encode(['messaggio' => $dettagli]);
                } else {
                    $dettagli_json = $dettagli;
                }
            } else {
                $dettagli_json = json_encode(['messaggio' => '']);
            }
            
            // Determina se questo log è non eliminabile
            $non_eliminabile = ($azione === 'eliminazione_log') ? 1 : 0;
            
            // Usa i nomi corretti delle colonne
            $sql = "INSERT INTO log_attivita (azienda_id, utente_id, entita_tipo, azione, entita_id, dettagli, ip_address, tipo, descrizione, non_eliminabile) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $azienda_id,
                $user ? $user['id'] : null,
                $tipo_entita,
                $azione,
                $entita_id,
                $dettagli_json,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $azione, // tipo
                ucfirst($azione) . ' ' . $tipo_entita, // descrizione
                $non_eliminabile
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
        $dettagli = [
            'messaggio' => 'Login effettuato con successo',
            'user_id' => $user_id,
            'timestamp' => date('Y-m-d H:i:s'),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ];
        $this->log('accesso', 'login', $user_id, $dettagli);
    }
    
    public function logLogout($user_id) {
        $dettagli = [
            'messaggio' => 'Logout effettuato',
            'user_id' => $user_id,
            'timestamp' => date('Y-m-d H:i:s'),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ];
        $this->log('accesso', 'logout', $user_id, $dettagli);
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
        $dettagli = [
            'messaggio' => "Modificato documento: " . $titolo
        ];
        if (!empty($vecchi_dati) || !empty($nuovi_dati)) {
            $dettagli['modifiche'] = [
                'prima' => $vecchi_dati,
                'dopo' => $nuovi_dati
            ];
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
                    ORDER BY data_azione DESC 
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
            $sql = "SELECT * FROM log_attivita WHERE entita_tipo = ?";
            $params = [$tipo_entita];
            
            if ($azienda_id) {
                $sql .= " AND azienda_id = ?";
                $params[] = $azienda_id;
            }
            
            $sql .= " ORDER BY data_azione DESC LIMIT ?";
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
    
    /**
     * Log errori generici
     */
    public function logError($messaggio, $dettagli = []) {
        $dettagli['messaggio'] = $messaggio;
        $dettagli['error_timestamp'] = date('Y-m-d H:i:s');
        $this->log('sistema', 'errore', null, $dettagli);
    }
    
    /**
     * Log tentativo di login fallito
     */
    public function logFailedLogin($username, $ip = null) {
        $dettagli = [
            'messaggio' => 'Tentativo di login fallito',
            'username' => $username,
            'ip_address' => $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        $this->log('accesso', 'login_fallito', null, $dettagli);
    }
} 