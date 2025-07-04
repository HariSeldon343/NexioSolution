<?php
/**
 * Gestione cifratura dati sensibili
 * Implementa AES-256 per cifrare dati sensibili nel database
 */

class DataEncryption {
    private static $instance = null;
    private $encryptionKey;
    private $cipher = 'AES-256-CBC';
    
    private function __construct() {
        $this->encryptionKey = $this->getOrCreateKey();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Cifra dati sensibili
     */
    public function encrypt($data) {
        if (empty($data)) {
            return null;
        }
        
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->cipher));
        $encrypted = openssl_encrypt($data, $this->cipher, $this->encryptionKey, 0, $iv);
        
        if ($encrypted === false) {
            throw new Exception("Errore durante la cifratura");
        }
        
        // Combina IV e dati cifrati
        return base64_encode($iv . '::' . $encrypted);
    }
    
    /**
     * Decifra dati
     */
    public function decrypt($encryptedData) {
        if (empty($encryptedData)) {
            return null;
        }
        
        try {
            $data = base64_decode($encryptedData);
            list($iv, $encrypted) = explode('::', $data, 2);
            
            $decrypted = openssl_decrypt($encrypted, $this->cipher, $this->encryptionKey, 0, $iv);
            
            if ($decrypted === false) {
                throw new Exception("Errore durante la decifratura");
            }
            
            return $decrypted;
        } catch (Exception $e) {
            error_log("Errore decifratura: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Cifra array di dati
     */
    public function encryptArray($data) {
        return $this->encrypt(json_encode($data));
    }
    
    /**
     * Decifra array di dati
     */
    public function decryptArray($encryptedData) {
        $decrypted = $this->decrypt($encryptedData);
        return $decrypted ? json_decode($decrypted, true) : null;
    }
    
    /**
     * Cifra file
     */
    public function encryptFile($inputFile, $outputFile) {
        if (!file_exists($inputFile)) {
            throw new Exception("File non trovato: $inputFile");
        }
        
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->cipher));
        $inputHandle = fopen($inputFile, 'rb');
        $outputHandle = fopen($outputFile, 'wb');
        
        // Scrivi IV all'inizio del file
        fwrite($outputHandle, $iv);
        
        // Cifra file a blocchi per gestire file grandi
        while (!feof($inputHandle)) {
            $chunk = fread($inputHandle, 8192);
            $encrypted = openssl_encrypt($chunk, $this->cipher, $this->encryptionKey, OPENSSL_RAW_DATA, $iv);
            fwrite($outputHandle, $encrypted);
        }
        
        fclose($inputHandle);
        fclose($outputHandle);
        
        return true;
    }
    
    /**
     * Decifra file
     */
    public function decryptFile($inputFile, $outputFile) {
        if (!file_exists($inputFile)) {
            throw new Exception("File non trovato: $inputFile");
        }
        
        $inputHandle = fopen($inputFile, 'rb');
        $outputHandle = fopen($outputFile, 'wb');
        
        // Leggi IV dall'inizio del file
        $ivLength = openssl_cipher_iv_length($this->cipher);
        $iv = fread($inputHandle, $ivLength);
        
        // Decifra file a blocchi
        while (!feof($inputHandle)) {
            $chunk = fread($inputHandle, 8192 + 16); // +16 per padding
            $decrypted = openssl_decrypt($chunk, $this->cipher, $this->encryptionKey, OPENSSL_RAW_DATA, $iv);
            fwrite($outputHandle, $decrypted);
        }
        
        fclose($inputHandle);
        fclose($outputHandle);
        
        return true;
    }
    
    /**
     * Hash password con salt aggiuntivo
     */
    public function hashPassword($password, $salt = null) {
        if ($salt === null) {
            $salt = bin2hex(random_bytes(32));
        }
        
        // Usa Argon2id se disponibile, altrimenti bcrypt
        if (defined('PASSWORD_ARGON2ID')) {
            $hash = password_hash($password . $salt, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536,
                'time_cost' => 4,
                'threads' => 3
            ]);
        } else {
            $hash = password_hash($password . $salt, PASSWORD_BCRYPT, ['cost' => 12]);
        }
        
        return ['hash' => $hash, 'salt' => $salt];
    }
    
    /**
     * Verifica password con salt
     */
    public function verifyPassword($password, $hash, $salt) {
        return password_verify($password . $salt, $hash);
    }
    
    /**
     * Genera token sicuro
     */
    public function generateSecureToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Genera chiave di cifratura o la recupera
     */
    private function getOrCreateKey() {
        $keyFile = dirname(__DIR__, 2) . '/config/.encryption_key';
        
        if (file_exists($keyFile)) {
            $key = file_get_contents($keyFile);
        } else {
            // Genera nuova chiave
            $key = openssl_random_pseudo_bytes(32);
            
            // Crea directory se non esiste
            $dir = dirname($keyFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0700, true);
            }
            
            // Salva chiave con permessi restrittivi
            file_put_contents($keyFile, $key);
            chmod($keyFile, 0600);
        }
        
        return $key;
    }
    
    /**
     * Ruota chiave di cifratura (per manutenzione)
     */
    public function rotateEncryptionKey($newKey = null) {
        if ($newKey === null) {
            $newKey = openssl_random_pseudo_bytes(32);
        }
        
        $oldKey = $this->encryptionKey;
        $this->encryptionKey = $newKey;
        
        // Qui andrebbe implementata la logica per ri-cifrare tutti i dati
        // con la nuova chiave
        
        return true;
    }
    
    /**
     * Sanitizza dati per prevenire XSS
     */
    public function sanitizeInput($data, $allowHtml = false) {
        if (is_array($data)) {
            return array_map(function($item) use ($allowHtml) {
                return $this->sanitizeInput($item, $allowHtml);
            }, $data);
        }
        
        if ($allowHtml) {
            // Permetti solo tag HTML sicuri
            $allowed = '<p><br><strong><em><u><a><ul><ol><li><blockquote>';
            return strip_tags($data, $allowed);
        } else {
            return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }
    
    /**
     * Genera hash per integrità dati
     */
    public function generateIntegrityHash($data) {
        return hash_hmac('sha256', serialize($data), $this->encryptionKey);
    }
    
    /**
     * Verifica integrità dati
     */
    public function verifyIntegrity($data, $hash) {
        $calculatedHash = $this->generateIntegrityHash($data);
        return hash_equals($calculatedHash, $hash);
    }
} 