<?php
/**
 * SimpleJWT - Implementazione JWT semplice senza dipendenze esterne
 * Supporta HS256 (HMAC SHA256)
 */

class SimpleJWT {
    private $secretKey;
    private $algorithm = 'HS256';
    
    public function __construct($secretKey) {
        if (empty($secretKey)) {
            throw new Exception('Secret key non può essere vuota');
        }
        $this->secretKey = $secretKey;
    }
    
    /**
     * Codifica un payload in JWT
     * 
     * @param array $payload
     * @param int $expiry Tempo di scadenza in secondi (default: 1 ora)
     * @return string Token JWT
     */
    public function encode($payload, $expiry = 3600) {
        // Aggiungi timestamp standard JWT
        $payload['iat'] = time(); // Issued at
        $payload['exp'] = time() + $expiry; // Expiration time
        $payload['nbf'] = time(); // Not before
        
        // Header
        $header = [
            'typ' => 'JWT',
            'alg' => $this->algorithm
        ];
        
        // Encode header e payload
        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));
        
        // Crea signature
        $signature = $this->sign($headerEncoded . '.' . $payloadEncoded);
        
        // Ritorna il token completo
        return $headerEncoded . '.' . $payloadEncoded . '.' . $signature;
    }
    
    /**
     * Decodifica e valida un JWT
     * 
     * @param string $token
     * @return array|false Payload decodificato o false se non valido
     */
    public function decode($token) {
        try {
            // Split token
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return false;
            }
            
            list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;
            
            // Decodifica header e payload
            $header = json_decode($this->base64UrlDecode($headerEncoded), true);
            $payload = json_decode($this->base64UrlDecode($payloadEncoded), true);
            
            if (!$header || !$payload) {
                return false;
            }
            
            // Verifica algoritmo
            if (!isset($header['alg']) || $header['alg'] !== $this->algorithm) {
                return false;
            }
            
            // Verifica signature
            $signatureValid = $this->verify(
                $headerEncoded . '.' . $payloadEncoded,
                $signatureEncoded
            );
            
            if (!$signatureValid) {
                return false;
            }
            
            // Verifica scadenza
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                return false;
            }
            
            // Verifica nbf (not before)
            if (isset($payload['nbf']) && $payload['nbf'] > time()) {
                return false;
            }
            
            return $payload;
            
        } catch (Exception $e) {
            error_log('JWT decode error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Valida un token JWT
     * 
     * @param string $token
     * @return array ['valid' => bool, 'payload' => array|null, 'error' => string|null]
     */
    public function validate($token) {
        if (empty($token)) {
            return [
                'valid' => false,
                'payload' => null,
                'error' => 'Token vuoto'
            ];
        }
        
        $payload = $this->decode($token);
        
        if ($payload === false) {
            // Determina il tipo di errore
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                $error = 'Formato token non valido';
            } else {
                $testPayload = json_decode($this->base64UrlDecode($parts[1]), true);
                if (isset($testPayload['exp']) && $testPayload['exp'] < time()) {
                    $error = 'Token scaduto';
                } else {
                    $error = 'Signature non valida';
                }
            }
            
            return [
                'valid' => false,
                'payload' => null,
                'error' => $error
            ];
        }
        
        return [
            'valid' => true,
            'payload' => $payload,
            'error' => null
        ];
    }
    
    /**
     * Genera un refresh token sicuro
     * 
     * @return string
     */
    public function generateRefreshToken() {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Firma una stringa con HMAC SHA256
     * 
     * @param string $data
     * @return string
     */
    private function sign($data) {
        $signature = hash_hmac('sha256', $data, $this->secretKey, true);
        return $this->base64UrlEncode($signature);
    }
    
    /**
     * Verifica una signature
     * 
     * @param string $data
     * @param string $signature
     * @return bool
     */
    private function verify($data, $signature) {
        $expectedSignature = $this->sign($data);
        return hash_equals($expectedSignature, $signature);
    }
    
    /**
     * Base64 URL encode
     * 
     * @param string $data
     * @return string
     */
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64 URL decode
     * 
     * @param string $data
     * @return string
     */
    private function base64UrlDecode($data) {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $data .= str_repeat('=', $padlen);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}