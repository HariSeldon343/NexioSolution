<?php
/**
 * OnlyOffice Configuration
 * Configurazione definitiva per integrazione con Docker
 */

// Previeni accesso diretto
if (!defined('APP_PATH')) {
    define('APP_PATH', '/piattaforma-collaborativa');
}

class OnlyOfficeConfig {
    /**
     * URL del Document Server OnlyOffice (Docker)
     */
    const DOCUMENT_SERVER_URL = 'http://localhost:8080';
    
    /**
     * URL del File Server Nginx (per servire i documenti)
     */
    const FILE_SERVER_URL = 'http://localhost:8081';
    
    /**
     * URL pubblico dell'applicazione (per callback)
     */
    const APP_PUBLIC_URL = 'http://localhost/piattaforma-collaborativa';
    
    /**
     * Percorso locale documenti
     */
    const DOCUMENTS_PATH = '/mnt/c/xampp/htdocs/piattaforma-collaborativa/documents/onlyoffice';
    
    /**
     * Percorso relativo documenti (dal root del progetto)
     */
    const DOCUMENTS_RELATIVE_PATH = 'documents/onlyoffice';
    
    /**
     * JWT Configuration (DISABILITATO per testing)
     */
    const JWT_ENABLED = false;
    const JWT_SECRET = 'disabled'; // Cambiare in produzione!
    const JWT_HEADER = 'Authorization';
    const JWT_PREFIX = 'Bearer';
    
    /**
     * Timeout e limiti
     */
    const CONVERSION_TIMEOUT = 120000; // 2 minuti in ms
    const MAX_FILE_SIZE = 104857600; // 100MB
    const DOWNLOAD_TIMEOUT = 30; // secondi
    
    /**
     * Formati supportati
     */
    const SUPPORTED_FORMATS = [
        'text' => ['docx', 'doc', 'odt', 'rtf', 'txt', 'html', 'htm', 'mht', 'pdf', 'djvu', 'fb2', 'epub', 'xps'],
        'spreadsheet' => ['xlsx', 'xls', 'ods', 'csv'],
        'presentation' => ['pptx', 'ppt', 'odp']
    ];
    
    /**
     * Tipi di documento e relative estensioni di default
     */
    const DOCUMENT_TYPES = [
        'word' => 'docx',
        'cell' => 'xlsx',
        'slide' => 'pptx'
    ];
    
    /**
     * Permessi di default per nuovi documenti
     */
    const DEFAULT_PERMISSIONS = [
        'comment' => true,
        'download' => true,
        'edit' => true,
        'fillForms' => true,
        'modifyFilter' => true,
        'modifyContentControl' => true,
        'review' => true,
        'chat' => true,
        'commentGroups' => [],
        'userInfoGroups' => []
    ];
    
    /**
     * Configurazione editor
     */
    const EDITOR_CONFIG = [
        'callbackUrl' => null, // Impostato dinamicamente
        'lang' => 'it',
        'region' => 'it-IT',
        'mode' => 'edit', // edit, view, embedded
        'user' => null, // Impostato dinamicamente
        'customization' => [
            'autosave' => true,
            'chat' => true,
            'commentAuthorOnly' => false,
            'comments' => true,
            'compactHeader' => false,
            'compactToolbar' => false,
            'compatibleFeatures' => false,
            'forcesave' => false,
            'help' => true,
            'hideRightMenu' => false,
            'hideRulers' => false,
            'integrationMode' => 'embed',
            'macros' => true,
            'macrosMode' => 'warn',
            'mentionShare' => true,
            'mobileForceView' => true,
            'plugins' => true,
            'spellcheck' => true,
            'submitForm' => true,
            'toolbarHideFileName' => false,
            'toolbarNoTabs' => false,
            'trackChanges' => true,
            'unit' => 'cm',
            'zoom' => 100,
            'logo' => [
                'image' => APP_PATH . '/assets/images/nexio-logo.svg',
                'imageEmbedded' => APP_PATH . '/assets/images/nexio-logo.svg',
                'url' => APP_PATH
            ],
            'customer' => [
                'address' => '',
                'info' => 'Nexio Platform',
                'logo' => APP_PATH . '/assets/images/nexio-logo.svg',
                'logoDark' => APP_PATH . '/assets/images/nexio-logo.svg',
                'mail' => 'info@nexio.local',
                'name' => 'Nexio',
                'phone' => '',
                'www' => APP_PATH
            ],
            'feedback' => [
                'visible' => false
            ],
            'goback' => [
                'blank' => false,
                'requestClose' => false,
                'text' => 'Torna a Nexio',
                'url' => APP_PATH . '/filesystem.php'
            ]
        ]
    ];
    
    /**
     * Ottieni URL completo del Document Server
     */
    public static function getDocumentServerUrl() {
        return self::DOCUMENT_SERVER_URL;
    }
    
    /**
     * Ottieni URL completo del File Server
     */
    public static function getFileServerUrl() {
        return self::FILE_SERVER_URL;
    }
    
    /**
     * Ottieni URL per un documento specifico
     */
    public static function getDocumentUrl($filename) {
        // Se usiamo il file server Nginx
        if (self::FILE_SERVER_URL) {
            return self::FILE_SERVER_URL . '/documents/onlyoffice/' . $filename;
        }
        // Altrimenti usa l'URL dell'app
        return self::APP_PUBLIC_URL . '/' . self::DOCUMENTS_RELATIVE_PATH . '/' . $filename;
    }
    
    /**
     * Ottieni URL di callback per OnlyOffice
     */
    public static function getCallbackUrl($documentId) {
        return self::APP_PUBLIC_URL . '/backend/api/onlyoffice-callback.php?id=' . $documentId;
    }
    
    /**
     * Genera chiave unica per documento
     */
    public static function generateDocumentKey($documentId, $version = 1) {
        return md5($documentId . '_' . $version . '_' . time());
    }
    
    /**
     * Verifica se un formato è supportato
     */
    public static function isFormatSupported($extension) {
        $extension = strtolower($extension);
        foreach (self::SUPPORTED_FORMATS as $type => $formats) {
            if (in_array($extension, $formats)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Ottieni tipo di documento dall'estensione
     */
    public static function getDocumentType($extension) {
        $extension = strtolower($extension);
        foreach (self::SUPPORTED_FORMATS as $type => $formats) {
            if (in_array($extension, $formats)) {
                return $type;
            }
        }
        return 'text'; // default
    }
    
    /**
     * Genera configurazione completa per l'editor
     */
    public static function generateEditorConfig($document, $user, $mode = 'edit') {
        $config = self::EDITOR_CONFIG;
        
        // Imposta URL di callback
        $config['callbackUrl'] = self::getCallbackUrl($document['id']);
        
        // Imposta modalità
        $config['mode'] = $mode;
        
        // Imposta informazioni utente
        $config['user'] = [
            'id' => (string)$user['id'],
            'name' => $user['nome'] . ' ' . $user['cognome'],
            'group' => $user['ruolo'] ?? 'utente'
        ];
        
        // Aggiungi configurazione documento
        $documentConfig = [
            'fileType' => pathinfo($document['filename'], PATHINFO_EXTENSION),
            'key' => self::generateDocumentKey($document['id'], $document['version'] ?? 1),
            'title' => $document['filename'],
            'url' => self::getDocumentUrl($document['filename']),
            'permissions' => self::DEFAULT_PERMISSIONS
        ];
        
        // Se in modalità view, limita i permessi
        if ($mode === 'view') {
            $documentConfig['permissions']['edit'] = false;
            $documentConfig['permissions']['fillForms'] = false;
            $documentConfig['permissions']['modifyFilter'] = false;
            $documentConfig['permissions']['modifyContentControl'] = false;
        }
        
        return [
            'document' => $documentConfig,
            'documentType' => self::getDocumentType($documentConfig['fileType']),
            'editorConfig' => $config,
            'token' => self::JWT_ENABLED ? self::generateJWT($config) : null,
            'type' => 'desktop' // o 'mobile' basato su user agent
        ];
    }
    
    /**
     * Genera JWT token (per quando sarà abilitato)
     */
    public static function generateJWT($payload) {
        if (!self::JWT_ENABLED) {
            return null;
        }
        
        // Implementazione JWT quando necessaria
        require_once __DIR__ . '/../utils/SimpleJWT.php';
        return SimpleJWT::encode($payload, self::JWT_SECRET);
    }
    
    /**
     * Verifica JWT token (per quando sarà abilitato)
     */
    public static function verifyJWT($token) {
        if (!self::JWT_ENABLED) {
            return true; // Sempre valido se JWT è disabilitato
        }
        
        require_once __DIR__ . '/../utils/SimpleJWT.php';
        try {
            SimpleJWT::decode($token, self::JWT_SECRET);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Test connessione al Document Server
     */
    public static function testConnection() {
        $url = self::DOCUMENT_SERVER_URL . '/healthcheck';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        return [
            'success' => $httpCode == 200,
            'http_code' => $httpCode,
            'response' => $response,
            'error' => $error,
            'url' => $url
        ];
    }
    
    /**
     * Crea un nuovo documento vuoto
     */
    public static function createNewDocument($type = 'word', $title = 'Nuovo Documento') {
        // Ottieni estensione di default per il tipo
        $extension = self::DOCUMENT_TYPES[$type] ?? 'docx';
        
        // Genera nome file unico
        $filename = 'new_' . time() . '_' . uniqid() . '.' . $extension;
        $filepath = self::DOCUMENTS_PATH . '/' . $filename;
        
        // Crea file vuoto basato sul tipo
        $templatePath = __DIR__ . '/../../templates/blank.' . $extension;
        
        if (file_exists($templatePath)) {
            // Usa template se disponibile
            copy($templatePath, $filepath);
        } else {
            // Crea file vuoto
            file_put_contents($filepath, '');
        }
        
        return [
            'filename' => $filename,
            'filepath' => $filepath,
            'url' => self::getDocumentUrl($filename),
            'type' => $type,
            'extension' => $extension
        ];
    }
}

// Funzioni helper globali per retrocompatibilità
if (!function_exists('getOnlyOfficeUrl')) {
    function getOnlyOfficeUrl() {
        return OnlyOfficeConfig::getDocumentServerUrl();
    }
}

if (!function_exists('getOnlyOfficeFileUrl')) {
    function getOnlyOfficeFileUrl($filename) {
        return OnlyOfficeConfig::getDocumentUrl($filename);
    }
}

if (!function_exists('testOnlyOfficeConnection')) {
    function testOnlyOfficeConnection() {
        return OnlyOfficeConfig::testConnection();
    }
}