<?php
/**
 * OnlyOffice Configuration - Definitiva per Docker Desktop e Cloudflare
 */

class OnlyOfficeConfig {
    
    /**
     * Determina se siamo in ambiente locale o produzione
     */
    public static function isLocal() {
        return strpos($_SERVER['HTTP_HOST'] ?? 'localhost', 'localhost') !== false;
    }
    
    /**
     * URL interno per il Document Server (sempre host.docker.internal)
     * Questo è l'URL che OnlyOffice usa internamente per raggiungere i file
     */
    const FILESERVER_INTERNAL_BASE = 'http://host.docker.internal/piattaforma-collaborativa/';
    
    /**
     * URL pubblico per il browser
     */
    public static function getFileServerPublicBase() {
        if (self::isLocal()) {
            // In locale usa localhost standard
            return 'http://localhost/piattaforma-collaborativa/';
        } else {
            // In produzione usa Cloudflare
            return 'https://app.nexiosolution.it/piattaforma-collaborativa/';
        }
    }
    
    /**
     * URL del Document Server OnlyOffice
     */
    public static function getDocumentServerPublicUrl() {
        if (self::isLocal()) {
            // In locale usa HTTP porta 8082 (container Docker)
            return 'http://localhost:8082/';
        } else {
            // In produzione tramite Cloudflare
            return 'https://app.nexiosolution.it/onlyoffice/';
        }
    }
    
    /**
     * Genera URL documento per OnlyOffice (usa sempre host.docker.internal)
     */
    public static function getDocumentUrl($docId, $filename = null) {
        $url = self::FILESERVER_INTERNAL_BASE . 'backend/api/onlyoffice-document-public.php?doc=' . $docId;
        if ($filename) {
            $url .= '&filename=' . urlencode($filename);
        }
        return $url;
    }
    
    /**
     * Genera callback URL (usa sempre host.docker.internal)
     */
    public static function getCallbackUrl($docId) {
        return self::FILESERVER_INTERNAL_BASE . 'backend/api/onlyoffice-callback.php?doc=' . $docId;
    }
    
    /**
     * Genera URL pubblico per download manuale (per il browser)
     */
    public static function getPublicDocumentUrl($docId) {
        return self::getFileServerPublicBase() . 'backend/api/onlyoffice-document-public.php?doc=' . $docId;
    }
    
    /**
     * JWT Configuration
     */
    const JWT_ENABLED = false; // Per testing, abilitare in produzione
    const JWT_SECRET = 'your-secret-key-here'; // DEVE essere uguale nel container Docker
    
    // Timeout per le richieste (in secondi)
    const CALLBACK_TIMEOUT = 30;
    
    // Formati supportati
    const SUPPORTED_FORMATS = [
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'odt', 'ods', 'odp', 'txt', 'rtf', 'csv'
    ];
    
    /**
     * Verifica se un formato è supportato
     */
    public static function isFormatSupported($extension) {
        return in_array(strtolower($extension), self::SUPPORTED_FORMATS);
    }
    
    /**
     * Ottieni tipo di documento dall'estensione
     */
    public static function getDocumentType($extension) {
        $ext = strtolower($extension);
        if (in_array($ext, ['doc', 'docx', 'odt', 'txt', 'rtf'])) {
            return 'word';
        }
        if (in_array($ext, ['xls', 'xlsx', 'ods', 'csv'])) {
            return 'cell';
        }
        if (in_array($ext, ['ppt', 'pptx', 'odp'])) {
            return 'slide';
        }
        return 'word'; // default
    }
    
    /**
     * Genera la chiave unica per il documento
     */
    public static function generateDocumentKey($documentId, $version = null) {
        $key = 'doc_' . $documentId;
        if ($version) {
            $key .= '_v' . $version;
        }
        return md5($key . '_' . time());
    }
    
    /**
     * Genera la configurazione completa per l'editor
     */
    public static function getEditorConfig($document, $user) {
        $documentKey = self::generateDocumentKey($document['id']);
        $filename = $document['filename'] ?? 'document.docx';
        
        $config = [
            'document' => [
                'fileType' => pathinfo($filename, PATHINFO_EXTENSION),
                'key' => $documentKey,
                'title' => $document['nome'] ?? 'Documento',
                'url' => self::getDocumentUrl($document['id'], $filename),
                'permissions' => [
                    'download' => true,
                    'edit' => true,
                    'print' => true,
                    'review' => true,
                    'chat' => false // NON in customization!
                ]
            ],
            'documentType' => self::getDocumentType(pathinfo($filename, PATHINFO_EXTENSION)),
            'editorConfig' => [
                'callbackUrl' => self::getCallbackUrl($document['id']),
                'mode' => 'edit',
                'lang' => 'it',
                'user' => [
                    'id' => (string)$user['id'],
                    'name' => $user['nome'] ?? 'Utente'
                ],
                'customization' => [
                    'autosave' => true,
                    'compactHeader' => false,
                    'feedback' => false,
                    'forcesave' => false
                ]
            ],
            'type' => 'desktop'
        ];
        
        // Aggiungi JWT se abilitato
        if (self::JWT_ENABLED && self::JWT_SECRET) {
            $config['token'] = self::generateJWT($config);
        }
        
        return $config;
    }
    
    /**
     * Genera il JWT token
     */
    public static function generateJWT($payload) {
        if (!self::JWT_ENABLED || !self::JWT_SECRET) {
            return null;
        }
        
        require_once __DIR__ . '/../utils/SimpleJWT.php';
        return SimpleJWT::encode($payload, self::JWT_SECRET);
    }
    
    /**
     * Verifica il JWT token
     */
    public static function verifyJWT($token) {
        if (!self::JWT_ENABLED || !self::JWT_SECRET) {
            return true; // JWT disabilitato
        }
        
        require_once __DIR__ . '/../utils/SimpleJWT.php';
        try {
            SimpleJWT::decode($token, self::JWT_SECRET);
            return true;
        } catch (Exception $e) {
            error_log("JWT verification failed: " . $e->getMessage());
            return false;
        }
    }
}

// Mantieni compatibilità con vecchio codice
class OnlyOfficeHelper extends OnlyOfficeConfig {}