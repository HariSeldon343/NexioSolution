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
     * CONFIGURAZIONE HTTP PER SVILUPPO LOCALE
     * OnlyOffice Document Server con HTTP per evitare problemi SSL in sviluppo
     * NOTA: Per produzione, configurare HTTPS con certificati validi
     */
    
    // URL pubblici (accessibili dal browser) - HTTP per sviluppo
    const ONLYOFFICE_DS_PUBLIC_URL = 'http://localhost:8082/';   // HTTP su porta 8082 (come da docker-compose)
    const FILESERVER_PUBLIC_URL = 'http://localhost:8083/';      // HTTP su porta 8083 (nginx fileserver)
    
    // URL interni Docker (comunicazione container-to-container)
    const ONLYOFFICE_DS_INTERNAL_URL = 'http://nexio-onlyoffice/';   // HTTP per comunicazione interna
    const FILESERVER_INTERNAL_URL = 'http://nexio-fileserver/';      // Nome host del container
    
    // URL applicazione
    const APP_PUBLIC_URL = 'http://localhost/piattaforma-collaborativa';
    const APP_INTERNAL_URL = 'http://host.docker.internal/piattaforma-collaborativa'; // Per Docker su Windows
    
    // Configurazione produzione (Cloudflare)
    const PRODUCTION_URL = 'https://app.nexiosolution.it/piattaforma-collaborativa';
    const PRODUCTION_DS_URL = 'https://app.nexiosolution.it/onlyoffice/';
    
    // Legacy aliases per retrocompatibilità
    const DOCUMENT_SERVER_URL = self::ONLYOFFICE_DS_PUBLIC_URL;
    const FILE_SERVER_URL = self::FILESERVER_PUBLIC_URL;
    
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
     * Verifica se siamo in ambiente di produzione
     */
    public static function isProduction() {
        return isset($_SERVER['HTTP_HOST']) && 
               strpos($_SERVER['HTTP_HOST'], 'nexiosolution.it') !== false;
    }
    
    /**
     * Ottieni URL del Document Server per il browser (pubblico)
     */
    public static function getDocumentServerUrl() {
        if (self::isProduction()) {
            return self::PRODUCTION_DS_URL;
        }
        return self::ONLYOFFICE_DS_PUBLIC_URL;
    }
    
    /**
     * Ottieni URL del Document Server per comunicazione interna
     */
    public static function getDocumentServerInternalUrl() {
        // I container Docker comunicano sempre via hostname interno
        return self::ONLYOFFICE_DS_INTERNAL_URL;
    }
    
    /**
     * Ottieni URL del File Server per il browser (pubblico)
     */
    public static function getFileServerUrl() {
        if (self::isProduction()) {
            return self::PRODUCTION_URL . '/';
        }
        return self::FILESERVER_PUBLIC_URL;
    }
    
    /**
     * Ottieni URL documento per OnlyOffice Document Server (interno)
     * Questo URL verrà usato da OnlyOffice per scaricare il documento
     */
    public static function getDocumentUrlForDS($filename) {
        // OnlyOffice deve usare l'URL interno del fileserver
        return self::FILESERVER_INTERNAL_URL . 'piattaforma-collaborativa/backend/api/onlyoffice-document-public.php?doc=' . urlencode($filename);
    }
    
    /**
     * Ottieni URL documento per il browser (debug/preview)
     * Questo URL può essere usato per verificare che il documento sia accessibile
     */
    public static function getDocumentUrlForBrowser($filename) {
        if (self::isProduction()) {
            return self::PRODUCTION_URL . '/backend/api/onlyoffice-document-public.php?doc=' . urlencode($filename);
        }
        return self::FILESERVER_PUBLIC_URL . 'piattaforma-collaborativa/backend/api/onlyoffice-document-public.php?doc=' . urlencode($filename);
    }
    
    /**
     * Ottieni URL per un documento specifico (legacy - usa URL per DS)
     */
    public static function getDocumentUrl($filename) {
        return self::getDocumentUrlForDS($filename);
    }
    
    /**
     * Ottieni URL di callback per OnlyOffice
     * Il callback deve essere raggiungibile da OnlyOffice container
     */
    public static function getCallbackUrl($documentId) {
        // OnlyOffice container deve poter raggiungere l'app
        if (self::isProduction()) {
            return self::PRODUCTION_URL . '/backend/api/onlyoffice-callback.php?id=' . $documentId;
        }
        // In sviluppo, usa host.docker.internal per Windows Docker
        return self::APP_INTERNAL_URL . '/backend/api/onlyoffice-callback.php?id=' . $documentId;
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
        $url = self::getDocumentServerUrl() . 'healthcheck';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        // Per HTTPS su localhost, ignora certificati SSL (solo per sviluppo!)
        if (strpos($url, 'https://localhost') === 0) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        return [
            'success' => $httpCode == 200,
            'http_code' => $httpCode,
            'response' => $response,
            'error' => $error,
            'url' => $url,
            'is_https' => strpos($url, 'https://') === 0
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