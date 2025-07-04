<?php
/**
 * Gestione autenticazione a due fattori (2FA)
 * Implementa TOTP (Time-based One-Time Password) per maggiore sicurezza
 */

class TwoFactorAuth {
    private static $instance = null;
    private $db;
    private $issuer = "Piattaforma Collaborativa";
    private $qrCodeApi = "https://api.qrserver.com/v1/create-qr-code/";
    
    private function __construct() {
        // Database gestito dalle funzioni del progetto
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Genera un nuovo segreto per 2FA
     */
    public function generateSecret($length = 16) {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        return $secret;
    }
    
    /**
     * Abilita 2FA per un utente
     */
    public function enable2FA($userId) {
        $secret = $this->generateSecret();
        $backupCodes = $this->generateBackupCodes();
        
        try {
            $this->db->beginTransaction();
            
            // Salva il segreto e i backup codes
            $stmt = $this->db->prepare("
                INSERT INTO utenti_2fa (utente_id, secret, backup_codes, abilitato, creato_il)
                VALUES (:user_id, :secret, :backup_codes, 0, NOW())
                ON DUPLICATE KEY UPDATE 
                secret = VALUES(secret),
                backup_codes = VALUES(backup_codes),
                abilitato = 0,
                verificato_il = NULL
            ");
            
            $stmt->execute([
                'user_id' => $userId,
                'secret' => $this->encrypt($secret),
                'backup_codes' => json_encode($backupCodes)
            ]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'secret' => $secret,
                'backup_codes' => $backupCodes,
                'qr_code' => $this->generateQRCode($userId, $secret)
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Verifica il codice OTP
     */
    public function verifyOTP($userId, $code) {
        // Ottieni il segreto dell'utente
        $stmt = $this->db->prepare("
            SELECT secret, abilitato FROM utenti_2fa 
            WHERE utente_id = :user_id
        ");
        $stmt->execute(['user_id' => $userId]);
        $data = $stmt->fetch();
        
        if (!$data) {
            return false;
        }
        
        $secret = $this->decrypt($data['secret']);
        
        // Verifica il codice TOTP
        $validCode = $this->generateTOTP($secret);
        
        // Controlla anche i codici precedente e successivo per tolleranza temporale
        $prevCode = $this->generateTOTP($secret, -1);
        $nextCode = $this->generateTOTP($secret, 1);
        
        if ($code === $validCode || $code === $prevCode || $code === $nextCode) {
            // Se è la prima verifica, attiva il 2FA
            if (!$data['abilitato']) {
                $this->db->update('utenti_2fa', 
                    ['abilitato' => 1, 'verificato_il' => date('Y-m-d H:i:s')],
                    'utente_id = :user_id',
                    ['user_id' => $userId]
                );
            }
            
            // Log accesso 2FA riuscito
            $this->log2FAAccess($userId, 'otp', true);
            
            return true;
        }
        
        // Log tentativo fallito
        $this->log2FAAccess($userId, 'otp', false);
        
        return false;
    }
    
    /**
     * Verifica un backup code
     */
    public function verifyBackupCode($userId, $code) {
        $stmt = $this->db->prepare("SELECT backup_codes FROM utenti_2fa WHERE utente_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        $data = $stmt->fetch();
        
        if (!$data || !$data['backup_codes']) {
            return false;
        }
        
        $backupCodes = json_decode($data['backup_codes'], true);
        $hashedCode = hash('sha256', $code);
        
        if (in_array($hashedCode, $backupCodes)) {
            // Rimuovi il codice usato
            $backupCodes = array_diff($backupCodes, [$hashedCode]);
            
            $this->db->update('utenti_2fa',
                ['backup_codes' => json_encode(array_values($backupCodes))],
                'utente_id = :user_id',
                ['user_id' => $userId]
            );
            
            // Log uso backup code
            $this->log2FAAccess($userId, 'backup_code', true);
            
            return true;
        }
        
        $this->log2FAAccess($userId, 'backup_code', false);
        return false;
    }
    
    /**
     * Genera il codice TOTP
     */
    private function generateTOTP($secret, $timeOffset = 0) {
        $time = floor(time() / 30) + $timeOffset;
        $binaryTime = pack('N*', 0) . pack('N*', $time);
        $hash = hash_hmac('sha1', $binaryTime, $this->base32Decode($secret), true);
        $offset = ord($hash[19]) & 0xf;
        $otp = (
            ((ord($hash[$offset+0]) & 0x7f) << 24) |
            ((ord($hash[$offset+1]) & 0xff) << 16) |
            ((ord($hash[$offset+2]) & 0xff) << 8) |
            (ord($hash[$offset+3]) & 0xff)
        ) % 1000000;
        
        return str_pad($otp, 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Genera backup codes
     */
    private function generateBackupCodes($count = 10) {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $code = '';
            for ($j = 0; $j < 8; $j++) {
                $code .= random_int(0, 9);
            }
            // Salva hash del codice
            $codes[] = hash('sha256', $code);
        }
        return $codes;
    }
    
    /**
     * Genera URL per QR Code
     */
    private function generateQRCode($userId, $secret) {
        $user = (new User())->findById($userId);
        $label = urlencode($this->issuer . ':' . $user['email']);
        $otpauthUrl = "otpauth://totp/{$label}?secret={$secret}&issuer=" . urlencode($this->issuer);
        
        return $this->qrCodeApi . "?size=300x300&data=" . urlencode($otpauthUrl);
    }
    
    /**
     * Decodifica base32
     */
    private function base32Decode($input) {
        $map = [
            'A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4, 'F' => 5, 'G' => 6, 'H' => 7,
            'I' => 8, 'J' => 9, 'K' => 10, 'L' => 11, 'M' => 12, 'N' => 13, 'O' => 14, 'P' => 15,
            'Q' => 16, 'R' => 17, 'S' => 18, 'T' => 19, 'U' => 20, 'V' => 21, 'W' => 22, 'X' => 23,
            'Y' => 24, 'Z' => 25, '2' => 26, '3' => 27, '4' => 28, '5' => 29, '6' => 30, '7' => 31
        ];
        
        $input = strtoupper($input);
        $output = '';
        $buffer = 0;
        $bitsLeft = 0;
        
        for ($i = 0; $i < strlen($input); $i++) {
            $val = $map[$input[$i]] ?? 0;
            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;
            
            if ($bitsLeft >= 8) {
                $output .= chr(($buffer >> ($bitsLeft - 8)) & 0xFF);
                $bitsLeft -= 8;
            }
        }
        
        return $output;
    }
    
    /**
     * Cifra dati sensibili
     */
    private function encrypt($data) {
        $key = $this->getEncryptionKey();
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decifra dati sensibili
     */
    private function decrypt($data) {
        $key = $this->getEncryptionKey();
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    /**
     * Ottieni chiave di cifratura
     */
    private function getEncryptionKey() {
        // In produzione, questa chiave dovrebbe essere in una variabile d'ambiente
        return hash('sha256', 'CHANGE_THIS_SECRET_KEY_IN_PRODUCTION', true);
    }
    
    /**
     * Log accessi 2FA
     */
    private function log2FAAccess($userId, $method, $success) {
        $this->db->insert('log_2fa', [
            'utente_id' => $userId,
            'metodo' => $method,
            'successo' => $success ? 1 : 0,
            'ip_address' => get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'creato_il' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Controlla se l'utente ha 2FA abilitato
     */
    public function is2FAEnabled($userId) {
        $stmt = $this->db->prepare("
            SELECT abilitato FROM utenti_2fa 
            WHERE utente_id = :user_id AND abilitato = 1
        ");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchColumn() ? true : false;
    }
    
    /**
     * Disabilita 2FA
     */
    public function disable2FA($userId) {
        return $this->db->delete('utenti_2fa', 'utente_id = :user_id', ['user_id' => $userId]);
    }
} 