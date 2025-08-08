<?php
/**
 * CSRF Token Manager
 * 
 * Gestisce la generazione e validazione dei token CSRF per protezione contro attacchi CSRF
 * 
 * @package Nexio\Utils
 * @version 1.0.0
 */

class CSRFTokenManager 
{
    private static $instance = null;
    private const TOKEN_KEY = 'csrf_token';
    
    /**
     * Singleton instance
     */
    public static function getInstance(): CSRFTokenManager
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Genera un nuovo token CSRF e lo salva in sessione
     */
    public function generateToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION[self::TOKEN_KEY] = $token;
        return $token;
    }
    
    /**
     * Ottiene il token CSRF dalla sessione, generandolo se non esiste
     */
    public function getToken(): string
    {
        if (!isset($_SESSION[self::TOKEN_KEY]) || empty($_SESSION[self::TOKEN_KEY])) {
            return $this->generateToken();
        }
        
        return $_SESSION[self::TOKEN_KEY];
    }
    
    /**
     * Valida il token CSRF fornito contro quello in sessione
     */
    public function validateToken(?string $token): bool
    {
        if (empty($token)) {
            return false;
        }
        
        $sessionToken = $_SESSION[self::TOKEN_KEY] ?? null;
        
        if (empty($sessionToken)) {
            return false;
        }
        
        return hash_equals($sessionToken, $token);
    }
    
    /**
     * Verifica il token CSRF dalla richiesta POST
     * 
     * @throws Exception se il token non è valido
     */
    public function verifyRequest(): void
    {
        // Assicura che ci sia un token in sessione
        $this->getToken();
        
        if (!isset($_POST[self::TOKEN_KEY]) || empty($_POST[self::TOKEN_KEY])) {
            throw new Exception('Token CSRF mancante');
        }
        
        if (!$this->validateToken($_POST[self::TOKEN_KEY])) {
            throw new Exception('Token CSRF non valido');
        }
    }
    
    /**
     * Rinnova il token CSRF (utile dopo operazioni sensibili)
     */
    public function renewToken(): string
    {
        unset($_SESSION[self::TOKEN_KEY]);
        return $this->generateToken();
    }
    
    /**
     * Rimuove il token dalla sessione
     */
    public function clearToken(): void
    {
        unset($_SESSION[self::TOKEN_KEY]);
    }
}
?>