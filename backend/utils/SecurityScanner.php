<?php

/**
 * SecurityScanner
 * 
 * Sistema di sicurezza avanzato per scanning antivirus e validazione file
 * Compatible con il sistema documentale ISO Nexio
 * 
 * Features:
 * - Scanning antivirus euristico
 * - Validazione MIME type profonda
 * - Quarantena file sospetti
 * - Monitoring pattern malevoli
 * - Rate limiting per protezione
 * - Logging eventi sicurezza
 * 
 * @package Nexio\Utils
 * @version 1.0.0
 */

namespace Nexio\Utils;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/ActivityLogger.php';
require_once __DIR__ . '/RateLimiter.php';

class SecurityScanner
{
    private static $instance = null;
    private $logger;
    private $rateLimiter;
    private $config;

    // Configurazione sicurezza
    private $suspiciousPatterns = [
        // Executable signatures
        'MZ',                           // PE/DOS executable
        'PK',                          // ZIP archive (check contents)
        '7z',                          // 7-Zip archive
        '<!DOCTYPE html',              // HTML content in non-HTML files
        '<script',                     // JavaScript in non-web files
        '<?php',                       // PHP code in uploads
        '<%',                          // ASP code
        'eval(',                       // Eval functions
        'exec(',                       // Exec functions
        'system(',                     // System calls
        'shell_exec(',                 // Shell execution
        'base64_decode(',              // Base64 decoding (potential payload)
        'gzinflate(',                  // Compression obfuscation
        'str_rot13(',                  // ROT13 obfuscation
        '\x90\x90\x90\x90',          // NOP sled
        'IEND',                        // PNG end but in wrong context
    ];

    // Whitelist MIME types sicuri
    private $safeMimeTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        'text/csv',
        'application/rtf',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/bmp',
        'image/webp'
    ];

    // Estensioni pericolose
    private $dangerousExtensions = [
        'exe', 'com', 'bat', 'cmd', 'scr', 'pif', 'vbs', 'vbe', 'js', 'jse',
        'wsf', 'wsh', 'msc', 'msi', 'msp', 'dll', 'sys', 'ocx', 'cpl',
        'php', 'asp', 'aspx', 'jsp', 'py', 'pl', 'rb', 'sh', 'bash'
    ];

    // File headers conosciuti
    private $fileSignatures = [
        'pdf' => ['25504446'],                                    // %PDF
        'doc' => ['D0CF11E0'],                                    // MS Office OLE
        'docx' => ['504B0304'],                                   // ZIP (Office Open XML)
        'xls' => ['D0CF11E0'],                                    // MS Excel
        'xlsx' => ['504B0304'],                                   // ZIP (Office Open XML)
        'jpg' => ['FFD8FF'],                                      // JPEG
        'png' => ['89504E47'],                                    // PNG
        'gif' => ['474946'],                                      // GIF
        'zip' => ['504B0304', '504B0506', '504B0708'],          // ZIP variants
        'rar' => ['526172211A07'],                               // RAR
        '7z' => ['377ABCAF271C']                                 // 7-Zip
    ];

    private function __construct()
    {
        $this->logger = ActivityLogger::getInstance();
        $this->rateLimiter = RateLimiter::getInstance();
        
        $this->config = [
            'enable_scanning' => true,
            'quarantine_enabled' => true,
            'strict_mime_checking' => true,
            'max_scan_size' => 100 * 1024 * 1024, // 100MB
            'scan_timeout' => 30, // seconds
            'quarantine_retention' => 7 * 24 * 3600, // 7 giorni
            'clamav_enabled' => false, // Se disponibile ClamAV
            'virus_total_api' => null, // API key VirusTotal
            'heuristic_enabled' => true,
            'deep_scan_enabled' => true
        ];
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Scansiona file per minacce
     */
    public function scanFile(string $filePath, array $fileInfo = []): array
    {
        $scanId = uniqid('scan_', true);
        $startTime = microtime(true);
        
        $result = [
            'scan_id' => $scanId,
            'safe' => true,
            'threats' => [],
            'warnings' => [],
            'scan_time' => 0,
            'scan_type' => 'full',
            'quarantined' => false
        ];

        try {
            // Rate limiting per scan
            $this->rateLimiter->check('security_scan', 'file_scan', 10, 60);

            if (!$this->config['enable_scanning']) {
                $result['scan_type'] = 'disabled';
                return $result;
            }

            // Controlli preliminari
            if (!file_exists($filePath)) {
                throw new Exception('File non trovato per la scansione');
            }

            $fileSize = filesize($filePath);
            if ($fileSize > $this->config['max_scan_size']) {
                $result['warnings'][] = 'File troppo grande per scansione completa';
                $result['scan_type'] = 'limited';
            }

            // 1. Validazione MIME type
            $mimeResult = $this->validateMimeType($filePath, $fileInfo);
            if (!$mimeResult['valid']) {
                $result['safe'] = false;
                $result['threats'] = array_merge($result['threats'], $mimeResult['threats']);
            }

            // 2. Validazione estensione
            $extensionResult = $this->validateExtension($filePath, $fileInfo);
            if (!$extensionResult['valid']) {
                $result['safe'] = false;
                $result['threats'] = array_merge($result['threats'], $extensionResult['threats']);
            }

            // 3. Controllo header/signature
            $signatureResult = $this->validateFileSignature($filePath, $fileInfo);
            if (!$signatureResult['valid']) {
                $result['safe'] = false;
                $result['threats'] = array_merge($result['threats'], $signatureResult['threats']);
            }

            // 4. Scansione euristica
            if ($this->config['heuristic_enabled']) {
                $heuristicResult = $this->heuristicScan($filePath);
                if (!$heuristicResult['safe']) {
                    $result['safe'] = false;
                    $result['threats'] = array_merge($result['threats'], $heuristicResult['threats']);
                    $result['warnings'] = array_merge($result['warnings'], $heuristicResult['warnings']);
                }
            }

            // 5. Deep scan per archivi
            if ($this->config['deep_scan_enabled'] && $this->isArchive($filePath)) {
                $deepResult = $this->deepScanArchive($filePath);
                if (!$deepResult['safe']) {
                    $result['safe'] = false;
                    $result['threats'] = array_merge($result['threats'], $deepResult['threats']);
                }
            }

            // 6. ClamAV scan se disponibile
            if ($this->config['clamav_enabled']) {
                $clamResult = $this->clamavScan($filePath);
                if (!$clamResult['safe']) {
                    $result['safe'] = false;
                    $result['threats'] = array_merge($result['threats'], $clamResult['threats']);
                }
            }

            // 7. VirusTotal check se configurato
            if ($this->config['virus_total_api'] && $fileSize < 32 * 1024 * 1024) {
                $vtResult = $this->virusTotalScan($filePath);
                if (!$vtResult['safe']) {
                    $result['safe'] = false;
                    $result['threats'] = array_merge($result['threats'], $vtResult['threats']);
                }
            }

            // Quarantena se necessario
            if (!$result['safe'] && $this->config['quarantine_enabled']) {
                $this->quarantineFile($filePath, $result, $fileInfo);
                $result['quarantined'] = true;
            }

            $result['scan_time'] = microtime(true) - $startTime;

            // Log risultato scan
            $this->logScanResult($scanId, $filePath, $result, $fileInfo);

            return $result;

        } catch (Exception $e) {
            $this->logger->logError('Errore security scan: ' . $e->getMessage(), [
                'scan_id' => $scanId,
                'file_path' => $filePath,
                'file_info' => $fileInfo
            ]);

            $result['safe'] = false;
            $result['threats'][] = 'Errore durante la scansione: ' . $e->getMessage();
            $result['scan_time'] = microtime(true) - $startTime;

            return $result;
        }
    }

    /**
     * Validazione MIME type profonda
     */
    private function validateMimeType(string $filePath, array $fileInfo): array
    {
        $result = ['valid' => true, 'threats' => []];

        try {
            // MIME type dal file
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedMime = finfo_file($finfo, $filePath);
            finfo_close($finfo);

            // MIME type dichiarato
            $declaredMime = $fileInfo['type'] ?? null;

            // Controllo whitelist
            if (!in_array($detectedMime, $this->safeMimeTypes)) {
                $result['valid'] = false;
                $result['threats'][] = "MIME type non sicuro rilevato: $detectedMime";
            }

            // Controllo coerenza dichiarato vs rilevato
            if ($declaredMime && $declaredMime !== $detectedMime) {
                $result['valid'] = false;
                $result['threats'][] = "MIME type inconsistente: dichiarato $declaredMime, rilevato $detectedMime";
            }

            // Controlli specifici per MIME pericolosi
            $dangerousMimes = [
                'application/x-executable',
                'application/x-msdownload',
                'application/x-msdos-program',
                'application/x-javascript',
                'text/javascript',
                'application/javascript',
                'text/html'
            ];

            if (in_array($detectedMime, $dangerousMimes)) {
                $result['valid'] = false;
                $result['threats'][] = "MIME type pericoloso: $detectedMime";
            }

        } catch (Exception $e) {
            $result['valid'] = false;
            $result['threats'][] = 'Errore validazione MIME type: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Validazione estensione file
     */
    private function validateExtension(string $filePath, array $fileInfo): array
    {
        $result = ['valid' => true, 'threats' => []];

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        // Controllo estensioni pericolose
        if (in_array($extension, $this->dangerousExtensions)) {
            $result['valid'] = false;
            $result['threats'][] = "Estensione pericolosa: .$extension";
        }

        // Controllo doppia estensione (es: documento.pdf.exe)
        $filename = pathinfo($filePath, PATHINFO_FILENAME);
        if (preg_match('/\.[a-zA-Z]{2,4}$/', $filename)) {
            $result['valid'] = false;
            $result['threats'][] = 'Possibile doppia estensione rilevata';
        }

        // Controllo caratteri sospetti nel nome
        if (preg_match('/[<>:"|?*\x00-\x1f]/', basename($filePath))) {
            $result['valid'] = false;
            $result['threats'][] = 'Caratteri sospetti nel nome file';
        }

        return $result;
    }

    /**
     * Validazione signature/header file
     */
    private function validateFileSignature(string $filePath, array $fileInfo): array
    {
        $result = ['valid' => true, 'threats' => []];

        try {
            $handle = fopen($filePath, 'rb');
            if (!$handle) {
                throw new Exception('Impossibile aprire file per controllo signature');
            }

            $header = fread($handle, 16);
            fclose($handle);

            $hexHeader = strtoupper(bin2hex($header));
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            // Controlla se signature corrisponde all'estensione
            if (isset($this->fileSignatures[$extension])) {
                $validSignatures = $this->fileSignatures[$extension];
                $signatureMatch = false;

                foreach ($validSignatures as $signature) {
                    if (strpos($hexHeader, strtoupper($signature)) === 0) {
                        $signatureMatch = true;
                        break;
                    }
                }

                if (!$signatureMatch) {
                    $result['valid'] = false;
                    $result['threats'][] = "Signature file non corrisponde all'estensione .$extension";
                }
            }

            // Controlli per signature pericolose
            $dangerousSignatures = [
                '4D5A' => 'Windows Executable (PE)',
                '7F454C46' => 'Linux Executable (ELF)',
                'CAFEBABE' => 'Java Class File',
                'FEEDFACE' => 'Mach-O Binary',
                '504B0304' => 'ZIP Archive (potenziale payload)',
            ];

            foreach ($dangerousSignatures as $signature => $description) {
                if (strpos($hexHeader, $signature) === 0) {
                    $result['valid'] = false;
                    $result['threats'][] = "Signature pericolosa rilevata: $description";
                }
            }

        } catch (Exception $e) {
            $result['valid'] = false;
            $result['threats'][] = 'Errore validazione signature: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Scansione euristica per pattern sospetti
     */
    private function heuristicScan(string $filePath): array
    {
        $result = ['safe' => true, 'threats' => [], 'warnings' => []];

        try {
            $fileSize = filesize($filePath);
            
            // Skip file troppo grandi per euristica
            if ($fileSize > 10 * 1024 * 1024) { // 10MB
                $result['warnings'][] = 'File troppo grande per scansione euristica completa';
                return $result;
            }

            $content = file_get_contents($filePath);
            
            // Controllo pattern sospetti
            foreach ($this->suspiciousPatterns as $pattern) {
                if (strpos($content, $pattern) !== false) {
                    $result['safe'] = false;
                    $result['threats'][] = "Pattern sospetto rilevato: $pattern";
                }
            }

            // Controllo entropy (file compresso/offuscato)
            $entropy = $this->calculateEntropy($content);
            if ($entropy > 7.5) { // Soglia alta entropy
                $result['warnings'][] = 'Alta entropy rilevata (possibile offuscamento)';
            }

            // Controllo URL/domini sospetti
            if (preg_match_all('/https?:\/\/[^\s]+/', $content, $matches)) {
                foreach ($matches[0] as $url) {
                    if ($this->isSuspiciousUrl($url)) {
                        $result['safe'] = false;
                        $result['threats'][] = "URL sospetto rilevato: $url";
                    }
                }
            }

            // Controllo stringhe base64 lunghe (possibili payload)
            if (preg_match('/[A-Za-z0-9+\/]{100,}={0,2}/', $content)) {
                $result['warnings'][] = 'Stringhe base64 lunghe rilevate';
            }

            // Controllo pattern XSS/injection
            $injectionPatterns = [
                '<script',
                'javascript:',
                'vbscript:',
                'data:text/html',
                'eval\(',
                'document\.write',
                'innerHTML\s*='
            ];

            foreach ($injectionPatterns as $pattern) {
                if (preg_match('/' . preg_quote($pattern, '/') . '/i', $content)) {
                    $result['safe'] = false;
                    $result['threats'][] = "Pattern injection rilevato: $pattern";
                }
            }

        } catch (Exception $e) {
            $result['warnings'][] = 'Errore scansione euristica: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Deep scan per archivi (ZIP, RAR, etc.)
     */
    private function deepScanArchive(string $filePath): array
    {
        $result = ['safe' => true, 'threats' => []];

        try {
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            if ($extension === 'zip') {
                $result = $this->scanZipArchive($filePath);
            } elseif ($extension === 'rar') {
                $result = $this->scanRarArchive($filePath);
            }

        } catch (Exception $e) {
            $result['threats'][] = 'Errore deep scan archivio: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Scansione ZIP archive
     */
    private function scanZipArchive(string $filePath): array
    {
        $result = ['safe' => true, 'threats' => []];

        $zip = new ZipArchive();
        if ($zip->open($filePath) === TRUE) {
            
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $filename = $stat['name'];
                
                // Controllo nomi file sospetti
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if (in_array($extension, $this->dangerousExtensions)) {
                    $result['safe'] = false;
                    $result['threats'][] = "File pericoloso in archivio: $filename";
                }

                // Controllo path traversal
                if (strpos($filename, '../') !== false || strpos($filename, '..\\') !== false) {
                    $result['safe'] = false;
                    $result['threats'][] = "Path traversal rilevato: $filename";
                }

                // Controllo compressione anomala (zip bomb)
                if ($stat['comp_size'] > 0 && ($stat['size'] / $stat['comp_size']) > 1000) {
                    $result['safe'] = false;
                    $result['threats'][] = "Possibile zip bomb rilevata: $filename";
                }
            }
            
            $zip->close();
        } else {
            $result['threats'][] = 'Impossibile aprire archivio ZIP per la scansione';
        }

        return $result;
    }

    /**
     * Scansione RAR archive (se disponibile)
     */
    private function scanRarArchive(string $filePath): array
    {
        $result = ['safe' => true, 'threats' => []];

        if (!class_exists('RarArchive')) {
            $result['threats'][] = 'Estensione RAR non disponibile per la scansione';
            return $result;
        }

        // Implementazione scansione RAR se necessaria
        return $result;
    }

    /**
     * Scansione ClamAV se disponibile
     */
    private function clamavScan(string $filePath): array
    {
        $result = ['safe' => true, 'threats' => []];

        try {
            $clamavPath = '/usr/bin/clamdscan'; // Path standard ClamAV
            
            if (!file_exists($clamavPath)) {
                $result['threats'][] = 'ClamAV non disponibile';
                return $result;
            }

            $command = escapeshellcmd($clamavPath) . ' --no-summary ' . escapeshellarg($filePath);
            $output = shell_exec($command);

            if (strpos($output, 'FOUND') !== false) {
                $result['safe'] = false;
                $result['threats'][] = 'Virus rilevato da ClamAV: ' . trim($output);
            }

        } catch (Exception $e) {
            $result['threats'][] = 'Errore ClamAV scan: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Scansione VirusTotal
     */
    private function virusTotalScan(string $filePath): array
    {
        $result = ['safe' => true, 'threats' => []];

        if (!$this->config['virus_total_api']) {
            return $result;
        }

        try {
            $fileHash = hash_file('sha256', $filePath);
            
            // Check hash nel database VT
            $url = "https://www.virustotal.com/vtapi/v2/file/report";
            $data = [
                'apikey' => $this->config['virus_total_api'],
                'resource' => $fileHash
            ];

            $response = $this->makeHttpRequest($url, $data);
            $vtResult = json_decode($response, true);

            if ($vtResult && $vtResult['response_code'] === 1) {
                if ($vtResult['positives'] > 0) {
                    $result['safe'] = false;
                    $result['threats'][] = "VirusTotal detection: {$vtResult['positives']}/{$vtResult['total']} engines";
                }
            }

        } catch (Exception $e) {
            // Non critico se VirusTotal non risponde
            error_log('VirusTotal error: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Quarantena file sospetto
     */
    private function quarantineFile(string $filePath, array $scanResult, array $fileInfo): void
    {
        try {
            $quarantineDir = BASE_PATH . '/quarantine';
            if (!is_dir($quarantineDir)) {
                mkdir($quarantineDir, 0700, true);
            }

            $quarantineName = uniqid('quarantine_') . '.dat';
            $quarantinePath = $quarantineDir . '/' . $quarantineName;

            // Sposta file in quarantena
            if (rename($filePath, $quarantinePath)) {
                
                // Record nel database
                db_insert('file_quarantine', [
                    'original_filename' => basename($filePath),
                    'quarantine_path' => $quarantinePath,
                    'file_hash' => hash_file('sha256', $quarantinePath),
                    'file_size' => filesize($quarantinePath),
                    'mime_type' => $fileInfo['type'] ?? null,
                    'threat_type' => implode(', ', $scanResult['threats']),
                    'threat_details' => json_encode($scanResult),
                    'azienda_id' => Auth::getInstance()->getCurrentAzienda()['azienda_id'] ?? null,
                    'uploaded_by' => Auth::getInstance()->getUser()['id'] ?? null,
                    'quarantined_at' => date('Y-m-d H:i:s'),
                    'status' => 'quarantined'
                ]);

                $this->logger->logSecurity('file_quarantined', [
                    'original_path' => $filePath,
                    'quarantine_path' => $quarantinePath,
                    'threats' => $scanResult['threats'],
                    'scan_id' => $scanResult['scan_id']
                ]);
            }

        } catch (Exception $e) {
            $this->logger->logError('Errore quarantena file: ' . $e->getMessage(), [
                'file_path' => $filePath,
                'scan_result' => $scanResult
            ]);
        }
    }

    /**
     * Log risultato scansione
     */
    private function logScanResult(string $scanId, string $filePath, array $result, array $fileInfo): void
    {
        $logData = [
            'scan_id' => $scanId,
            'file_path' => basename($filePath),
            'file_size' => filesize($filePath),
            'mime_type' => $fileInfo['type'] ?? null,
            'safe' => $result['safe'],
            'threats_count' => count($result['threats']),
            'scan_time' => $result['scan_time'],
            'scan_type' => $result['scan_type'],
            'quarantined' => $result['quarantined']
        ];

        if (!$result['safe']) {
            $this->logger->logSecurity('security_threat_detected', $logData);
        } else {
            $this->logger->log('security_scan_clean', 'security_log', null, $logData);
        }
    }

    // Utility methods

    private function isArchive(string $filePath): bool
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return in_array($extension, ['zip', 'rar', '7z', 'tar', 'gz']);
    }

    private function calculateEntropy(string $data): float
    {
        $entropy = 0;
        $size = strlen($data);
        
        if ($size === 0) return 0;

        $frequencies = array_count_values(str_split($data));
        
        foreach ($frequencies as $frequency) {
            $p = $frequency / $size;
            $entropy -= $p * log($p, 2);
        }

        return $entropy;
    }

    private function isSuspiciousUrl(string $url): bool
    {
        // Lista domini sospetti (esempio)
        $suspiciousDomains = [
            'bit.ly', 'tinyurl.com', 'goo.gl', 't.co',
            'suspicious-domain.com', 'malware-host.net'
        ];

        $domain = parse_url($url, PHP_URL_HOST);
        
        return in_array($domain, $suspiciousDomains) || 
               preg_match('/\d+\.\d+\.\d+\.\d+/', $domain) || // IP address
               strlen($domain) > 50; // Domain troppo lungo
    }

    private function makeHttpRequest(string $url, array $data): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query($data),
                'timeout' => 10
            ]
        ]);

        $result = file_get_contents($url, false, $context);
        
        if ($result === false) {
            throw new Exception('HTTP request failed');
        }

        return $result;
    }

    /**
     * Cleanup file quarantena scaduti
     */
    public function cleanupQuarantine(): array
    {
        $stats = ['cleaned' => 0, 'errors' => 0];

        try {
            $expiredFiles = db_query(
                "SELECT * FROM file_quarantine 
                 WHERE quarantined_at < DATE_SUB(NOW(), INTERVAL ? SECOND)",
                [$this->config['quarantine_retention']]
            )->fetchAll();

            foreach ($expiredFiles as $file) {
                try {
                    if (file_exists($file['quarantine_path'])) {
                        unlink($file['quarantine_path']);
                    }

                    db_update('file_quarantine', [
                        'status' => 'deleted',
                        'reviewed_at' => date('Y-m-d H:i:s')
                    ], 'id = ?', [$file['id']]);

                    $stats['cleaned']++;

                } catch (Exception $e) {
                    $stats['errors']++;
                    $this->logger->logError('Errore cleanup quarantena: ' . $e->getMessage(), [
                        'file_id' => $file['id']
                    ]);
                }
            }

        } catch (Exception $e) {
            $this->logger->logError('Errore cleanup quarantena generale: ' . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Statistiche sicurezza
     */
    public function getSecurityStats(int $aziendaId, int $days = 30): array
    {
        try {
            $stats = [
                'total_scans' => 0,
                'threats_detected' => 0,
                'files_quarantined' => 0,
                'threat_types' => [],
                'scan_performance' => 0
            ];

            // Total scans dalle activity logs
            $totalScans = db_query(
                "SELECT COUNT(*) FROM log_attivita 
                 WHERE azienda_id = ? AND azione IN ('security_scan_clean', 'security_threat_detected') 
                 AND data_azione >= DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$aziendaId, $days]
            )->fetchColumn();

            $stats['total_scans'] = $totalScans;

            // Threats detected
            $threatsDetected = db_query(
                "SELECT COUNT(*) FROM log_attivita 
                 WHERE azienda_id = ? AND azione = 'security_threat_detected' 
                 AND data_azione >= DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$aziendaId, $days]
            )->fetchColumn();

            $stats['threats_detected'] = $threatsDetected;

            // Files in quarantena
            $quarantined = db_query(
                "SELECT COUNT(*) FROM file_quarantine 
                 WHERE azienda_id = ? AND quarantined_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$aziendaId, $days]
            )->fetchColumn();

            $stats['files_quarantined'] = $quarantined;

            // Threat types
            $threatTypes = db_query(
                "SELECT threat_type, COUNT(*) as count 
                 FROM file_quarantine 
                 WHERE azienda_id = ? AND quarantined_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                 GROUP BY threat_type",
                [$aziendaId, $days]
            )->fetchAll();

            $stats['threat_types'] = $threatTypes;

            return $stats;

        } catch (Exception $e) {
            $this->logger->logError('Errore statistiche sicurezza: ' . $e->getMessage());
            return [];
        }
    }
}