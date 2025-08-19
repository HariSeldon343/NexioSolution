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
     * CONFIGURAZIONE DINAMICA PER AMBIENTI MULTIPLI
     * Rileva automaticamente l'ambiente e configura gli URL appropriati
     */
    
    // Configurazione per ambiente di sviluppo locale
    const DEV_ONLYOFFICE_PUBLIC_URL = 'http://localhost:8082/';
    const DEV_FILESERVER_PUBLIC_URL = 'http://localhost/';
    const DEV_APP_PUBLIC_URL = 'http://localhost/piattaforma-collaborativa';
    
    // Configurazione per produzione
    const PROD_ONLYOFFICE_PUBLIC_URL = 'https://app.nexiosolution.it/onlyoffice/';
    const PROD_FILESERVER_PUBLIC_URL = 'https://app.nexiosolution.it/';
    const PROD_APP_PUBLIC_URL = 'https://app.nexiosolution.it/piattaforma-collaborativa';
    
    // URL interni Docker (comunicazione container-to-container)
    const ONLYOFFICE_DS_INTERNAL_URL = 'http://nexio-documentserver/';
    const FILESERVER_INTERNAL_URL = 'http://nexio-fileserver/';
    
    // Host per Docker Desktop (Windows/Mac) - sempre usa host.docker.internal
    const DOCKER_HOST_INTERNAL = 'http://host.docker.internal';
    
    
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
     * Verifica se stiamo usando Docker Desktop (Windows/Mac)
     * Docker Desktop richiede host.docker.internal per comunicazione container->host
     */
    public static function isDockerDesktop() {
        // Su Docker Desktop, l'ambiente WSL2 o il fatto che stiamo su Windows indica Docker Desktop
        return !self::isProduction() && 
               (PHP_OS_FAMILY === 'Windows' || 
                (isset($_SERVER['WSL_DISTRO_NAME']) || 
                 file_exists('/.dockerenv')));
    }
    
    /**
     * Ottieni URL documento per OnlyOffice container su Docker Desktop
     * CRITICO: Su Docker Desktop DEVE usare host.docker.internal
     */
    public static function getDocumentUrlForContainer($path) {
        // SEMPRE usa host.docker.internal per Docker Desktop Windows
        return self::DOCKER_HOST_INTERNAL . $path;
    }
    
    /**
     * Ottieni URL del Document Server per il browser (pubblico)
     */
    public static function getDocumentServerUrl() {
        if (self::isProduction()) {
            return self::PROD_ONLYOFFICE_PUBLIC_URL;
        }
        return self::DEV_ONLYOFFICE_PUBLIC_URL;
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
            return self::PROD_FILESERVER_PUBLIC_URL;
        }
        return self::DEV_FILESERVER_PUBLIC_URL;
    }
    
    /**
     * Ottieni URL documento per OnlyOffice Document Server (interno)
     * Questo URL verrà usato da OnlyOffice per scaricare il documento
     * CRITICO: Su Docker Desktop deve usare host.docker.internal
     */
    public static function getDocumentUrlForDS($filename) {
        if (self::isProduction()) {
            // In produzione usa URL pubblico HTTPS
            return self::PROD_APP_PUBLIC_URL . '/documents/onlyoffice/' . $filename;
        }
        // In sviluppo Docker Desktop SEMPRE richiede host.docker.internal
        return self::DOCKER_HOST_INTERNAL . '/piattaforma-collaborativa/documents/onlyoffice/' . $filename;
    }
    
    /**
     * Ottieni URL documento per il browser (debug/preview)
     * Questo URL può essere usato per verificare che il documento sia accessibile
     */
    public static function getDocumentUrlForBrowser($filename) {
        if (self::isProduction()) {
            return self::PROD_APP_PUBLIC_URL . '/documents/onlyoffice/' . $filename;
        }
        return self::DEV_APP_PUBLIC_URL . '/documents/onlyoffice/' . $filename;
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
            return self::PROD_APP_PUBLIC_URL . '/backend/api/onlyoffice-callback.php?doc=' . $documentId;
        }
        // In sviluppo, SEMPRE usa host.docker.internal per Windows Docker
        return self::DOCKER_HOST_INTERNAL . '/piattaforma-collaborativa/backend/api/onlyoffice-callback.php?doc=' . $documentId;
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
        
        // Per HTTPS su localhost, SEMPRE ignora certificati SSL (solo per sviluppo!)
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        
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