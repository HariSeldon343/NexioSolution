<?php
/**
 * Configurazione OnlyOffice Document Server Docker
 * Configurazione per server locale Docker
 */

// ================================================================
// CONFIGURAZIONE ONLYOFFICE DOCKER
// ================================================================

// Server OnlyOffice locale (Docker)
$ONLYOFFICE_SERVER = 'http://localhost:8080';

// Timeout per le richieste (secondi)
$ONLYOFFICE_TIMEOUT = 30;

// Formati supportati
$ONLYOFFICE_SUPPORTED_FORMATS = [
    'docx', 'doc', 'odt', 'rtf', 'txt',
    'xlsx', 'xls', 'ods', 'csv',
    'pptx', 'ppt', 'odp'
];

$ONLYOFFICE_DS_PUBLIC_URL  = 'http://localhost:8082'; // URL pubblico (esterno) del Document Server
$ONLYOFFICE_DS_INTERNAL_URL = 'http://onlyoffice-documentserver'; // URL interno se usi docker-compose; altrimenti identico al precedente
$ONLYOFFICE_CALLBACK_URL   = 'http://<tuo_dominio>/backend/api/onlyoffice-callback.php';
$ONLYOFFICE_DOCUMENTS_DIR  = __DIR__ . '/../../uploads/documenti_onlyoffice'; // cartella dove salvare temporaneamente i docx
$ONLYOFFICE_DEBUG = false;


// Dimensione massima file (bytes) - 50MB
$ONLYOFFICE_MAX_FILE_SIZE = 50 * 1024 * 1024;

// Abilita debug
$ONLYOFFICE_DEBUG = true;

// Directory per salvare i documenti
$ONLYOFFICE_DOCUMENTS_DIR = __DIR__ . '/../../documents/onlyoffice';

// JWT per sicurezza (per ora disabilitato per semplicità)
$ONLYOFFICE_JWT_ENABLED = false;
$ONLYOFFICE_JWT_SECRET = '';
$ONLYOFFICE_JWT_HEADER = 'Authorization';

// ================================================================
// AUTO-CONFIGURAZIONE CALLBACK URL
// ================================================================

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = rtrim(dirname(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))), '/');

// Per Docker su Windows/WSL, OnlyOffice deve usare host.docker.internal
// per raggiungere il host dal container
if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
    $callbackHost = 'host.docker.internal';
    // Mantieni la porta di XAMPP se presente
    if (strpos($host, ':') !== false) {
        $callbackHost .= ':' . explode(':', $host)[1];
    } else {
        $callbackHost .= ':80'; // Porta default XAMPP
    }
} else {
    $callbackHost = $host;
}

$ONLYOFFICE_CALLBACK_URL = $protocol . '://' . $callbackHost . $basePath . '/backend/api/onlyoffice-callback.php';

// Document Server Public URL (quello che il browser deve usare)
$ONLYOFFICE_DS_PUBLIC_URL = $ONLYOFFICE_SERVER;

// Document Server Internal URL (quello che il server PHP usa per comunicare)
$ONLYOFFICE_DS_INTERNAL_URL = $ONLYOFFICE_SERVER;

// ================================================================
// FUNZIONI UTILITY
// ================================================================

/**
 * Verifica se OnlyOffice è configurato correttamente
 */
function checkOnlyOfficeConfig() {
    global $ONLYOFFICE_SERVER, $ONLYOFFICE_DOCUMENTS_DIR;
    
    $errors = [];
    
    // Verifica server
    if (empty($ONLYOFFICE_SERVER)) {
        $errors[] = 'ONLYOFFICE_SERVER non configurato';
    }
    
    // Verifica directory documenti
    if (!is_dir($ONLYOFFICE_DOCUMENTS_DIR)) {
        if (!mkdir($ONLYOFFICE_DOCUMENTS_DIR, 0755, true)) {
            $errors[] = 'Impossibile creare directory documenti: ' . $ONLYOFFICE_DOCUMENTS_DIR;
        }
    } elseif (!is_writable($ONLYOFFICE_DOCUMENTS_DIR)) {
        $errors[] = 'Directory documenti non scrivibile: ' . $ONLYOFFICE_DOCUMENTS_DIR;
    }
    
    return $errors;
}

/**
 * Ottiene lo stato del Document Server
 */
function getOnlyOfficeServerStatus() {
    global $ONLYOFFICE_DS_INTERNAL_URL, $ONLYOFFICE_TIMEOUT;
    
    $healthUrl = $ONLYOFFICE_DS_INTERNAL_URL . '/healthcheck';
    
    $context = stream_context_create([
        'http' => [
            'timeout' => $ONLYOFFICE_TIMEOUT,
            'method' => 'GET',
            'ignore_errors' => true,
            'header' => [
                'User-Agent: PHP OnlyOffice Client',
                'Accept: application/json'
            ]
        ]
    ]);
    
    $result = @file_get_contents($healthUrl, false, $context);
    
    return $result !== false && strpos($result, 'true') !== false;
}

// ================================================================
// LOG CONFIGURAZIONI (solo in debug)
// ================================================================

if ($ONLYOFFICE_DEBUG && php_sapi_name() !== 'cli') {
    error_log("OnlyOffice Config - Public Server: $ONLYOFFICE_DS_PUBLIC_URL");
    error_log("OnlyOffice Config - Internal Server: $ONLYOFFICE_DS_INTERNAL_URL");
    error_log("OnlyOffice Config - Callback: $ONLYOFFICE_CALLBACK_URL");
    error_log("OnlyOffice Config - Documents Dir: $ONLYOFFICE_DOCUMENTS_DIR");
}
?> 