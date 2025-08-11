<?php
/**
 * JWT Manager - Gestione token JWT per autenticazione mobile
 */

namespace Backend\Utils;

use Exception;
use PDO;

class JWTManager {
    private $pdo;
    private $config;
    private $secretKey;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->config = require __DIR__ . '/../config/jwt-config.php';
        $this->secretKey = $this->config['secret_key'];
    }
    
    /**
     * Genera coppia di token (access + refresh)
     */
    public function generateTokenPair($userId, $deviceId = null, $deviceInfo = null) {
        try {
            // Genera access token
            $accessToken = $this->generateAccessToken($userId);
            
            // Genera refresh token
            $refreshToken = $this->generateRefreshToken($userId, $deviceId, $deviceInfo);
            
            return [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken['token'],
                'expires_in' => $this->config['access_token_expiry'],
                'refresh_expires_in' => $this->config['refresh_token_expiry'],
                'token_type' => 'Bearer'
            ];
        } catch (Exception $e) {
            throw new Exception('Errore generazione token: ' . $e->getMessage());
        }
    }
    
    /**
     * Genera access token JWT
     */
    private function generateAccessToken($userId) {
        $header = json_encode([
            'typ' => 'JWT',
            'alg' => $this->config['algorithm']
        ]);
        
        $now = time();
        $payload = json_encode([
            'iss' => $this->config['issuer'],
            'aud' => $this->config['audience'],
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $this->config['access_token_expiry'],
            'sub' => $userId,
            'jti' => bin2hex(random_bytes(16))
        ]);
        
        $base64Header = $this->base64UrlEncode($header);
        $base64Payload = $this->base64UrlEncode($payload);
        
        $signature = hash_hmac(
            'sha256',
            $base64Header . '.' . $base64Payload,
            $this->secretKey,
            true
        );
        $base64Signature = $this->base64UrlEncode($signature);
        
        return $base64Header . '.' . $base64Payload . '.' . $base64Signature;
    }
    
    /**
     * Genera e salva refresh token
     */
    private function generateRefreshToken($userId, $deviceId = null, $deviceInfo = null) {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + $this->config['refresh_token_expiry']);
        
        // Revoca token precedenti per lo stesso device
        if ($deviceId) {
            $stmt = $this->pdo->prepare("
                UPDATE jwt_refresh_tokens 
                SET is_revoked = 1 
                WHERE user_id = ? AND device_id = ? AND is_revoked = 0
            ");
            $stmt->execute([$userId, $deviceId]);
        }
        
        // Salva nuovo refresh token
        $stmt = $this->pdo->prepare("
            INSERT INTO jwt_refresh_tokens 
            (user_id, token_hash, device_id, device_info, expires_at, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            $tokenHash,
            $deviceId,
            $deviceInfo ? json_encode($deviceInfo) : null,
            $expiresAt,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        return [
            'token' => $token,
            'expires_at' => $expiresAt
        ];
    }
    
    /**
     * Valida access token JWT
     */
    public function validateAccessToken($token) {
        try {
            $tokenParts = explode('.', $token);
            
            if (count($tokenParts) !== 3) {
                throw new Exception('Token formato non valido');
            }
            
            list($header, $payload, $signatureProvided) = $tokenParts;
            
            // Verifica firma
            $signature = hash_hmac(
                'sha256',
                $header . '.' . $payload,
                $this->secretKey,
                true
            );
            $signatureValid = $this->base64UrlEncode($signature);
            
            if ($signatureProvided !== $signatureValid) {
                throw new Exception('Firma token non valida');
            }
            
            // Decodifica payload
            $payloadData = json_decode($this->base64UrlDecode($payload), true);
            
            // Verifica scadenza
            if ($payloadData['exp'] < time()) {
                throw new Exception('Token scaduto');
            }
            
            // Verifica issuer e audience
            if ($payloadData['iss'] !== $this->config['issuer'] ||
                $payloadData['aud'] !== $this->config['audience']) {
                throw new Exception('Token non valido per questa applicazione');
            }
            
            return [
                'valid' => true,
                'user_id' => $payloadData['sub'],
                'expires_at' => $payloadData['exp']
            ];
            
        } catch (Exception $e) {
            return [
                'valid' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Refresh access token usando refresh token
     */
    public function refreshAccessToken($refreshToken, $deviceId = null) {
        try {
            $tokenHash = hash('sha256', $refreshToken);
            
            // Verifica refresh token
            $stmt = $this->pdo->prepare("
                SELECT * FROM jwt_refresh_tokens 
                WHERE token_hash = ? 
                AND is_revoked = 0 
                AND expires_at > NOW()
            ");
            $stmt->execute([$tokenHash]);
            $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$tokenData) {
                throw new Exception('Refresh token non valido o scaduto');
            }
            
            // Aggiorna last_used_at
            $stmt = $this->pdo->prepare("
                UPDATE jwt_refresh_tokens 
                SET last_used_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$tokenData['id']]);
            
            // Genera nuovo access token
            $accessToken = $this->generateAccessToken($tokenData['user_id']);
            
            // Rotation del refresh token (opzionale, per maggiore sicurezza)
            $rotateRefresh = false; // Configurabile
            if ($rotateRefresh) {
                // Revoca vecchio token
                $stmt = $this->pdo->prepare("
                    UPDATE jwt_refresh_tokens SET is_revoked = 1 WHERE id = ?
                ");
                $stmt->execute([$tokenData['id']]);
                
                // Genera nuovo refresh token
                $newRefresh = $this->generateRefreshToken(
                    $tokenData['user_id'],
                    $deviceId ?? $tokenData['device_id'],
                    json_decode($tokenData['device_info'], true)
                );
                
                return [
                    'access_token' => $accessToken,
                    'refresh_token' => $newRefresh['token'],
                    'expires_in' => $this->config['access_token_expiry'],
                    'token_type' => 'Bearer'
                ];
            }
            
            return [
                'access_token' => $accessToken,
                'expires_in' => $this->config['access_token_expiry'],
                'token_type' => 'Bearer'
            ];
            
        } catch (Exception $e) {
            throw new Exception('Errore refresh token: ' . $e->getMessage());
        }
    }
    
    /**
     * Revoca tutti i token di un utente
     */
    public function revokeAllTokens($userId) {
        $stmt = $this->pdo->prepare("
            UPDATE jwt_refresh_tokens 
            SET is_revoked = 1 
            WHERE user_id = ? AND is_revoked = 0
        ");
        return $stmt->execute([$userId]);
    }
    
    /**
     * Revoca token specifico per device
     */
    public function revokeDeviceTokens($userId, $deviceId) {
        $stmt = $this->pdo->prepare("
            UPDATE jwt_refresh_tokens 
            SET is_revoked = 1 
            WHERE user_id = ? AND device_id = ? AND is_revoked = 0
        ");
        return $stmt->execute([$userId, $deviceId]);
    }
    
    /**
     * Ottieni lista dispositivi con token attivi
     */
    public function getActiveDevices($userId) {
        $stmt = $this->pdo->prepare("
            SELECT 
                device_id,
                device_info,
                last_used_at,
                ip_address,
                user_agent,
                created_at
            FROM jwt_refresh_tokens
            WHERE user_id = ? 
            AND is_revoked = 0 
            AND expires_at > NOW()
            ORDER BY last_used_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Helper per base64 URL-safe encoding
     */
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Helper per base64 URL-safe decoding
     */
    private function base64UrlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }
    
    /**
     * Log accesso API
     */
    public function logApiAccess($userId, $endpoint, $method, $responseCode, $responseTime, $error = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO mobile_api_logs 
                (user_id, endpoint, method, response_code, response_time_ms, 
                 ip_address, user_agent, error_message)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $userId,
                $endpoint,
                $method,
                $responseCode,
                $responseTime,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $error
            ]);
        } catch (Exception $e) {
            // Log silenzioso, non bloccare richiesta
            error_log('Errore log API: ' . $e->getMessage());
        }
    }
}