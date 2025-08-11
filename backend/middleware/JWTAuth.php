<?php
/**
 * JWT Authentication Middleware
 * Middleware per autenticazione JWT nelle API mobile
 */

namespace Backend\Middleware;

use Backend\Utils\JWTManager;
use Backend\Utils\RateLimiter;
use PDO;
use Exception;

class JWTAuth {
    private $pdo;
    private $jwtManager;
    private $rateLimiter;
    private $config;
    private $currentUser = null;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->jwtManager = new JWTManager($pdo);
        $this->rateLimiter = new RateLimiter($pdo);
        $this->config = require __DIR__ . '/../config/jwt-config.php';
        
        // Imposta headers CORS
        $this->setCorsHeaders();
    }
    
    /**
     * Verifica autenticazione JWT
     */
    public function authenticate() {
        try {
            // Gestisci preflight OPTIONS
            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                http_response_code(204);
                exit;
            }
            
            // Estrai token dall'header Authorization
            $token = $this->extractToken();
            
            if (!$token) {
                $this->unauthorized('Token mancante');
            }
            
            // Valida token
            $validation = $this->jwtManager->validateAccessToken($token);
            
            if (!$validation['valid']) {
                $this->unauthorized($validation['error'] ?? 'Token non valido');
            }
            
            // Carica dati utente
            $this->loadUser($validation['user_id']);
            
            // Verifica rate limiting
            if (!$this->checkRateLimit()) {
                $this->tooManyRequests();
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->unauthorized($e->getMessage());
        }
    }
    
    /**
     * Estrai token JWT dall'header Authorization
     */
    private function extractToken() {
        $headers = getallheaders();
        
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
        } elseif (isset($headers['authorization'])) {
            $authHeader = $headers['authorization'];
        } else {
            return null;
        }
        
        // Formato: "Bearer <token>"
        if (preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Carica dati utente dal database
     */
    private function loadUser($userId) {
        $stmt = $this->pdo->prepare("
            SELECT u.*, 
                   ua.azienda_id as current_azienda_id,
                   a.nome as azienda_nome
            FROM utenti u
            LEFT JOIN utenti_aziende ua ON u.id = ua.utente_id
            LEFT JOIN aziende a ON ua.azienda_id = a.id
            WHERE u.id = ? AND u.attivo = 1
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            throw new Exception('Utente non trovato o non attivo');
        }
        
        $this->currentUser = $user;
    }
    
    /**
     * Verifica rate limiting
     */
    private function checkRateLimit() {
        $identifier = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $endpoint = $_SERVER['REQUEST_URI'] ?? '/';
        
        return $this->rateLimiter->checkLimit(
            $identifier,
            'ip',
            $endpoint,
            $this->config['rate_limit']['api_requests_per_minute'],
            60 // Finestra di 60 secondi
        );
    }
    
    /**
     * Imposta headers CORS per PWA
     */
    private function setCorsHeaders() {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        // Verifica se l'origine è consentita
        if (in_array($origin, $this->config['cors']['allowed_origins'])) {
            header("Access-Control-Allow-Origin: $origin");
        } else {
            // In sviluppo, permetti localhost
            if (strpos($origin, 'localhost') !== false) {
                header("Access-Control-Allow-Origin: $origin");
            }
        }
        
        header("Access-Control-Allow-Methods: " . implode(', ', $this->config['cors']['allowed_methods']));
        header("Access-Control-Allow-Headers: " . implode(', ', $this->config['cors']['allowed_headers']));
        header("Access-Control-Max-Age: " . $this->config['cors']['max_age']);
        
        if ($this->config['cors']['credentials']) {
            header("Access-Control-Allow-Credentials: true");
        }
    }
    
    /**
     * Risposta 401 Unauthorized
     */
    private function unauthorized($message = 'Non autorizzato') {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $message,
            'code' => 'UNAUTHORIZED'
        ]);
        exit;
    }
    
    /**
     * Risposta 429 Too Many Requests
     */
    private function tooManyRequests() {
        http_response_code(429);
        header('Content-Type: application/json');
        header('Retry-After: 60');
        echo json_encode([
            'success' => false,
            'error' => 'Troppe richieste. Riprova tra 60 secondi.',
            'code' => 'RATE_LIMIT_EXCEEDED'
        ]);
        exit;
    }
    
    /**
     * Ottieni utente corrente
     */
    public function getUser() {
        return $this->currentUser;
    }
    
    /**
     * Ottieni ID utente corrente
     */
    public function getUserId() {
        return $this->currentUser ? $this->currentUser['id'] : null;
    }
    
    /**
     * Ottieni azienda corrente dell'utente
     */
    public function getCurrentAzienda() {
        return $this->currentUser ? $this->currentUser['current_azienda_id'] : null;
    }
    
    /**
     * Verifica se utente è super admin
     */
    public function isSuperAdmin() {
        return $this->currentUser && $this->currentUser['ruolo'] === 'super_admin';
    }
    
    /**
     * Verifica permesso specifico
     */
    public function hasPermission($permission) {
        if (!$this->currentUser) {
            return false;
        }
        
        // Super admin ha tutti i permessi
        if ($this->isSuperAdmin()) {
            return true;
        }
        
        // Implementa logica permessi specifica
        // Per ora verifica solo ruolo base
        switch ($permission) {
            case 'create_event':
            case 'edit_event':
            case 'delete_event':
                return in_array($this->currentUser['ruolo'], ['utente_speciale', 'utente']);
                
            case 'manage_users':
            case 'manage_companies':
                return $this->currentUser['ruolo'] === 'utente_speciale';
                
            default:
                return false;
        }
    }
    
    /**
     * Log attività API
     */
    public function logActivity($action, $details = null) {
        try {
            $startTime = microtime(true);
            
            // Registra nel log API
            register_shutdown_function(function() use ($startTime, $action, $details) {
                $responseTime = round((microtime(true) - $startTime) * 1000);
                $responseCode = http_response_code();
                
                $this->jwtManager->logApiAccess(
                    $this->getUserId(),
                    $_SERVER['REQUEST_URI'] ?? '/',
                    $_SERVER['REQUEST_METHOD'] ?? 'GET',
                    $responseCode,
                    $responseTime,
                    $responseCode >= 400 ? ($details ?? 'Errore') : null
                );
            });
        } catch (Exception $e) {
            // Log silenzioso
            error_log('Errore log attività: ' . $e->getMessage());
        }
    }
}