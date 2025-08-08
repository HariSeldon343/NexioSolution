<?php
/**
 * Rate Limiter - Protezione contro brute force e abusi
 * Limita il numero di richieste per IP/utente in un determinato periodo
 */

class RateLimiter {
    private static $instance = null;
    private $db;
    private $cache = [];
    
    // Configurazione limiti
    private $limits = [
        'login' => ['attempts' => 5, 'window' => 900], // 5 tentativi in 15 minuti
        'api' => ['attempts' => 60, 'window' => 60], // 60 richieste al minuto
        'upload' => ['attempts' => 10, 'window' => 3600], // 10 upload all'ora
        'password_reset' => ['attempts' => 3, 'window' => 3600], // 3 reset password all'ora
        'registration' => ['attempts' => 3, 'window' => 86400], // 3 registrazioni al giorno
        'export' => ['attempts' => 20, 'window' => 3600], // 20 export all'ora
    ];
    
    private function __construct() {
        $this->db = db_connection();
        $this->createTableIfNotExists();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Controlla se l'azione è permessa
     */
    public function isAllowed($action, $identifier = null) {
        $identifier = $identifier ?? $this->getIdentifier();
        $limit = $this->limits[$action] ?? ['attempts' => 10, 'window' => 60];
        
        // Pulisci vecchi tentativi
        $this->cleanOldAttempts($action, $limit['window']);
        
        // Conta tentativi recenti
        $attempts = $this->countAttempts($action, $identifier, $limit['window']);
        
        return $attempts < $limit['attempts'];
    }
    
    /**
     * Registra un tentativo
     */
    public function recordAttempt($action, $identifier = null, $success = false) {
        $identifier = $identifier ?? $this->getIdentifier();
        
        try {
            db_insert('rate_limit_attempts', [
                'action' => $action,
                'identifier' => $identifier,
                'ip_address' => get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'success' => $success ? 1 : 0,
                'attempted_at' => date('Y-m-d H:i:s')
            ]);
            
            // Se fallito, incrementa il contatore
            if (!$success) {
                $this->incrementFailureCount($action, $identifier);
            } else {
                // Se successo, resetta il contatore
                $this->resetFailureCount($action, $identifier);
            }
            
        } catch (Exception $e) {
            error_log("RateLimiter error: " . $e->getMessage());
        }
    }
    
    /**
     * Ottieni tempo rimanente del blocco
     */
    public function getBlockedUntil($action, $identifier = null) {
        $identifier = $identifier ?? $this->getIdentifier();
        $limit = $this->limits[$action] ?? ['attempts' => 10, 'window' => 60];
        
        $sql = "SELECT attempted_at 
                FROM rate_limit_attempts 
                WHERE action = :action 
                AND identifier = :identifier 
                AND attempted_at > DATE_SUB(NOW(), INTERVAL :window SECOND)
                ORDER BY attempted_at DESC 
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':action', $action);
        $stmt->bindValue(':identifier', $identifier);
        $stmt->bindValue(':window', $limit['window'], PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit['attempts'], PDO::PARAM_INT);
        $stmt->execute();
        
        $attempts = $stmt->fetchAll();
        
        if (count($attempts) >= $limit['attempts']) {
            // Calcola quando scadrà il blocco
            $oldestAttempt = end($attempts);
            $blockedUntil = strtotime($oldestAttempt['attempted_at']) + $limit['window'];
            return $blockedUntil;
        }
        
        return 0;
    }
    
    /**
     * Ottieni messaggio di errore formattato
     */
    public function getErrorMessage($action, $identifier = null) {
        $blockedUntil = $this->getBlockedUntil($action, $identifier);
        
        if ($blockedUntil > time()) {
            $minutes = ceil(($blockedUntil - time()) / 60);
            
            switch ($action) {
                case 'login':
                    return "Troppi tentativi di accesso. Riprova tra {$minutes} minuti.";
                case 'password_reset':
                    return "Troppi tentativi di reset password. Riprova tra {$minutes} minuti.";
                case 'registration':
                    return "Troppe registrazioni da questo IP. Riprova tra {$minutes} minuti.";
                default:
                    return "Troppe richieste. Riprova tra {$minutes} minuti.";
            }
        }
        
        return null;
    }
    
    /**
     * Conta tentativi recenti
     */
    private function countAttempts($action, $identifier, $window) {
        // Prima controlla cache in memoria
        $cacheKey = "{$action}:{$identifier}";
        if (isset($this->cache[$cacheKey]) && 
            $this->cache[$cacheKey]['expires'] > time()) {
            return $this->cache[$cacheKey]['count'];
        }
        
        $sql = "SELECT COUNT(*) as count 
                FROM rate_limit_attempts 
                WHERE action = :action 
                AND identifier = :identifier 
                AND attempted_at > DATE_SUB(NOW(), INTERVAL :window SECOND)
                AND success = 0";
        
        $stmt = db_query($sql, [
            'action' => $action,
            'identifier' => $identifier,
            'window' => $window
        ]);
        
        $count = $stmt->fetch()['count'];
        
        // Salva in cache per 10 secondi
        $this->cache[$cacheKey] = [
            'count' => $count,
            'expires' => time() + 10
        ];
        
        return $count;
    }
    
    /**
     * Incrementa contatore fallimenti
     */
    private function incrementFailureCount($action, $identifier) {
        $cacheKey = "{$action}:{$identifier}";
        if (isset($this->cache[$cacheKey])) {
            $this->cache[$cacheKey]['count']++;
        }
    }
    
    /**
     * Resetta contatore fallimenti
     */
    private function resetFailureCount($action, $identifier) {
        $cacheKey = "{$action}:{$identifier}";
        unset($this->cache[$cacheKey]);
        
        // Marca tutti i tentativi precedenti come "success" per resettare il blocco
        $sql = "UPDATE rate_limit_attempts 
                SET success = 1 
                WHERE action = :action 
                AND identifier = :identifier 
                AND success = 0";
        
        db_query($sql, [
            'action' => $action,
            'identifier' => $identifier
        ]);
    }
    
    /**
     * Pulisci vecchi tentativi
     */
    private function cleanOldAttempts($action, $window) {
        // Esegui pulizia solo occasionalmente (1% delle volte)
        if (rand(1, 100) > 1) return;
        
        $sql = "DELETE FROM rate_limit_attempts 
                WHERE action = :action 
                AND attempted_at < DATE_SUB(NOW(), INTERVAL :window SECOND)";
        
        db_query($sql, [
            'action' => $action,
            'window' => $window * 2 // Mantieni per il doppio del tempo per analisi
        ]);
    }
    
    /**
     * Ottieni identificatore univoco
     */
    private function getIdentifier() {
        // Usa combinazione di IP e User Agent per identificare meglio
        return md5(get_client_ip() . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    }
    
    /**
     * Verifica se IP è in whitelist
     */
    public function isWhitelisted($ip = null) {
        $ip = $ip ?? get_client_ip();
        
        // IP locali sempre in whitelist
        if (in_array($ip, ['127.0.0.1', '::1'])) {
            return true;
        }
        
        // Controlla whitelist personalizzata
        $sql = "SELECT 1 FROM ip_whitelist WHERE ip_address = :ip AND attivo = 1";
        $stmt = db_query($sql, ['ip' => $ip]);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Aggiungi IP a blacklist
     */
    public function blacklistIP($ip, $reason = '', $duration = 86400) {
        try {
            db_insert('ip_blacklist', [
                'ip_address' => $ip,
                'reason' => $reason,
                'blocked_until' => date('Y-m-d H:i:s', time() + $duration),
                'created_by' => Auth::getInstance()->getUser()['id'] ?? 0
            ]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Verifica se IP è in blacklist
     */
    public function isBlacklisted($ip = null) {
        $ip = $ip ?? get_client_ip();
        
        $sql = "SELECT reason, blocked_until 
                FROM ip_blacklist 
                WHERE ip_address = :ip 
                AND (blocked_until IS NULL OR blocked_until > NOW())
                AND attivo = 1";
        
        $stmt = db_query($sql, ['ip' => $ip]);
        return $stmt->fetch();
    }
    
    /**
     * Ottieni statistiche tentativi
     */
    public function getStatistics($action = null, $hours = 24) {
        $sql = "SELECT 
                    action,
                    COUNT(*) as total_attempts,
                    SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed_attempts,
                    COUNT(DISTINCT identifier) as unique_identifiers,
                    COUNT(DISTINCT ip_address) as unique_ips
                FROM rate_limit_attempts 
                WHERE attempted_at > DATE_SUB(NOW(), INTERVAL :hours HOUR)";
        
        $params = ['hours' => $hours];
        
        if ($action) {
            $sql .= " AND action = :action";
            $params['action'] = $action;
        }
        
        $sql .= " GROUP BY action";
        
        $stmt = db_query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Crea tabella se non esiste
     */
    private function createTableIfNotExists() {
        $sql = "CREATE TABLE IF NOT EXISTS rate_limit_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            action VARCHAR(50) NOT NULL,
            identifier VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            success BOOLEAN DEFAULT FALSE,
            attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_action_identifier (action, identifier),
            INDEX idx_attempted_at (attempted_at),
            INDEX idx_ip (ip_address)
        )";
        
        try {
            db_query($sql);
        } catch (Exception $e) {
            // Tabella probabilmente già esiste
        }
        
        // Crea anche tabelle whitelist/blacklist
        $sql = "CREATE TABLE IF NOT EXISTS ip_whitelist (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) UNIQUE NOT NULL,
            description VARCHAR(255),
            attivo BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        try {
            db_query($sql);
        } catch (Exception $e) {
            // Ignora se esiste
        }
        
        $sql = "CREATE TABLE IF NOT EXISTS ip_blacklist (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            reason TEXT,
            blocked_until TIMESTAMP NULL,
            attivo BOOLEAN DEFAULT TRUE,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip (ip_address),
            INDEX idx_blocked_until (blocked_until)
        )";
        
        try {
            db_query($sql);
        } catch (Exception $e) {
            // Ignora se esiste
        }
    }
} 