<?php
/**
 * Script di Monitoraggio Performance per Sistema Documentale Nexio
 * 
 * Monitora:
 * - Performance database e query lente
 * - Utilizzo memoria e CPU
 * - Stato del sistema
 * - Metriche applicative
 * 
 * @package Nexio
 * @version 1.0.0
 */

// Configurazione
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);

define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/backend/config/config.php';
require_once ROOT_PATH . '/backend/config/database.php';

// Classe per output colorato
class MonitorConsole {
    public static function header($msg) {
        echo "\n\033[36m=== $msg ===\033[0m\n";
    }
    
    public static function ok($msg) {
        echo "\033[32m[OK]\033[0m $msg\n";
    }
    
    public static function warning($msg) {
        echo "\033[33m[WARNING]\033[0m $msg\n";
    }
    
    public static function error($msg) {
        echo "\033[31m[ERROR]\033[0m $msg\n";
    }
    
    public static function info($msg) {
        echo "\033[36m[INFO]\033[0m $msg\n";
    }
    
    public static function metric($label, $value, $unit = '') {
        echo "  \033[90m$label:\033[0m $value $unit\n";
    }
}

// Classe principale di monitoraggio
class NexioPerformanceMonitor {
    private $pdo;
    private $metrics = [];
    private $alerts = [];
    private $startTime;
    
    // Soglie di allarme
    private $thresholds = [
        'query_time' => 1.0,          // secondi
        'memory_usage' => 80,         // percentuale
        'cpu_load' => 2.0,           // load average
        'disk_usage' => 90,          // percentuale
        'connection_count' => 100,    // connessioni DB
        'slow_query_count' => 10,     // query lente
        'table_size' => 1000000000,   // 1GB
        'response_time' => 3.0        // secondi
    ];
    
    public function __construct() {
        $this->startTime = microtime(true);
        $this->connectDatabase();
    }
    
    /**
     * Connessione al database
     */
    private function connectDatabase() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Errore connessione database: " . $e->getMessage() . "\n");
        }
    }
    
    /**
     * Esegue il monitoraggio completo
     */
    public function monitor($continuous = false) {
        do {
            system('clear'); // Pulisce lo schermo (Linux/Mac)
            
            MonitorConsole::header("NEXIO PERFORMANCE MONITOR");
            MonitorConsole::info("Timestamp: " . date('Y-m-d H:i:s'));
            
            // 1. Stato sistema
            $this->checkSystemStatus();
            
            // 2. Performance database
            $this->checkDatabasePerformance();
            
            // 3. Metriche applicative
            $this->checkApplicationMetrics();
            
            // 4. Utilizzo risorse
            $this->checkResourceUsage();
            
            // 5. Query lente
            $this->checkSlowQueries();
            
            // 6. Dimensioni tabelle
            $this->checkTableSizes();
            
            // 7. Report alerts
            $this->reportAlerts();
            
            // 8. Salva metriche
            $this->saveMetrics();
            
            if ($continuous) {
                sleep(30); // Aggiorna ogni 30 secondi
            }
            
        } while ($continuous);
    }
    
    /**
     * Verifica stato sistema
     */
    private function checkSystemStatus() {
        MonitorConsole::header("STATO SISTEMA");
        
        // Uptime
        if (PHP_OS_FAMILY === 'Linux') {
            $uptime = shell_exec('uptime -p');
            MonitorConsole::metric("Uptime", trim($uptime));
        }
        
        // PHP Version
        MonitorConsole::metric("PHP Version", PHP_VERSION);
        
        // MySQL Version
        try {
            $version = $this->pdo->query("SELECT VERSION()")->fetchColumn();
            MonitorConsole::metric("MySQL Version", $version);
            $this->metrics['mysql_version'] = $version;
        } catch (PDOException $e) {
            MonitorConsole::error("Impossibile ottenere versione MySQL");
        }
        
        // Server Load
        if (function_exists('sys_getloadavg') && PHP_OS_FAMILY !== 'Windows') {
            $load = sys_getloadavg();
            MonitorConsole::metric("Load Average", implode(', ', $load));
            
            if ($load[0] > $this->thresholds['cpu_load']) {
                $this->addAlert('HIGH_LOAD', "Load average elevato: {$load[0]}");
            }
            $this->metrics['load_average'] = $load[0];
        }
    }
    
    /**
     * Verifica performance database
     */
    private function checkDatabasePerformance() {
        MonitorConsole::header("PERFORMANCE DATABASE");
        
        // 1. Connessioni attive
        try {
            $stmt = $this->pdo->query("SHOW STATUS LIKE 'Threads_connected'");
            $connections = $stmt->fetch()['Value'] ?? 0;
            MonitorConsole::metric("Connessioni attive", $connections);
            
            if ($connections > $this->thresholds['connection_count']) {
                $this->addAlert('HIGH_CONNECTIONS', "Troppe connessioni: $connections");
            }
            $this->metrics['db_connections'] = $connections;
            
        } catch (PDOException $e) {
            MonitorConsole::error("Errore lettura connessioni");
        }
        
        // 2. Query al secondo
        try {
            $stmt = $this->pdo->query("SHOW STATUS LIKE 'Questions'");
            $questions = $stmt->fetch()['Value'] ?? 0;
            
            $stmt = $this->pdo->query("SHOW STATUS LIKE 'Uptime'");
            $uptime = $stmt->fetch()['Value'] ?? 1;
            
            $qps = round($questions / $uptime, 2);
            MonitorConsole::metric("Query per secondo", $qps);
            $this->metrics['queries_per_second'] = $qps;
            
        } catch (PDOException $e) {
            MonitorConsole::error("Errore calcolo QPS");
        }
        
        // 3. Cache hit ratio
        try {
            $stmt = $this->pdo->query("SHOW STATUS LIKE 'Qcache_hits'");
            $hits = $stmt->fetch()['Value'] ?? 0;
            
            $stmt = $this->pdo->query("SHOW STATUS LIKE 'Qcache_inserts'");
            $inserts = $stmt->fetch()['Value'] ?? 0;
            
            if ($hits + $inserts > 0) {
                $hitRatio = round(($hits / ($hits + $inserts)) * 100, 2);
                MonitorConsole::metric("Cache hit ratio", $hitRatio, '%');
                $this->metrics['cache_hit_ratio'] = $hitRatio;
            }
            
        } catch (PDOException $e) {
            MonitorConsole::info("Query cache non disponibile");
        }
        
        // 4. InnoDB Buffer Pool
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    (SELECT VARIABLE_VALUE FROM information_schema.GLOBAL_STATUS 
                     WHERE VARIABLE_NAME = 'Innodb_buffer_pool_pages_data') as data_pages,
                    (SELECT VARIABLE_VALUE FROM information_schema.GLOBAL_STATUS 
                     WHERE VARIABLE_NAME = 'Innodb_buffer_pool_pages_total') as total_pages
            ");
            $buffer = $stmt->fetch();
            
            if ($buffer['total_pages'] > 0) {
                $bufferUsage = round(($buffer['data_pages'] / $buffer['total_pages']) * 100, 2);
                MonitorConsole::metric("InnoDB Buffer Pool Usage", $bufferUsage, '%');
                $this->metrics['innodb_buffer_usage'] = $bufferUsage;
            }
            
        } catch (PDOException $e) {
            MonitorConsole::info("InnoDB metrics non disponibili");
        }
    }
    
    /**
     * Verifica metriche applicative
     */
    private function checkApplicationMetrics() {
        MonitorConsole::header("METRICHE APPLICATIVE");
        
        try {
            // 1. Totale documenti
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM documenti");
            $docCount = $stmt->fetchColumn();
            MonitorConsole::metric("Totale documenti", number_format($docCount));
            $this->metrics['total_documents'] = $docCount;
            
            // 2. Documenti oggi
            $stmt = $this->pdo->query("
                SELECT COUNT(*) FROM documenti 
                WHERE DATE(data_creazione) = CURDATE()
            ");
            $todayDocs = $stmt->fetchColumn();
            MonitorConsole::metric("Documenti oggi", $todayDocs);
            $this->metrics['documents_today'] = $todayDocs;
            
            // 3. Utenti attivi (ultimi 30 min)
            $stmt = $this->pdo->query("
                SELECT COUNT(DISTINCT utente_id) FROM log_attivita 
                WHERE data_azione > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            ");
            $activeUsers = $stmt->fetchColumn();
            MonitorConsole::metric("Utenti attivi (30min)", $activeUsers);
            $this->metrics['active_users'] = $activeUsers;
            
            // 4. Spazio utilizzato uploads
            $uploadSize = $this->getDirectorySize(ROOT_PATH . '/uploads');
            MonitorConsole::metric("Spazio uploads", $this->formatBytes($uploadSize));
            $this->metrics['upload_size'] = $uploadSize;
            
            // 5. Email in coda
            $stmt = $this->pdo->query("
                SELECT COUNT(*) FROM notifiche_email 
                WHERE stato IN ('in_coda', 'inviando')
            ");
            $emailQueue = $stmt->fetchColumn();
            MonitorConsole::metric("Email in coda", $emailQueue);
            
            if ($emailQueue > 100) {
                $this->addAlert('EMAIL_QUEUE', "Troppe email in coda: $emailQueue");
            }
            $this->metrics['email_queue'] = $emailQueue;
            
        } catch (PDOException $e) {
            MonitorConsole::error("Errore lettura metriche: " . $e->getMessage());
        }
    }
    
    /**
     * Verifica utilizzo risorse
     */
    private function checkResourceUsage() {
        MonitorConsole::header("UTILIZZO RISORSE");
        
        // 1. Memoria PHP
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->getMemoryLimit();
        $memoryPercent = round(($memoryUsage / $memoryLimit) * 100, 2);
        
        MonitorConsole::metric("Memoria PHP", 
            $this->formatBytes($memoryUsage) . " / " . $this->formatBytes($memoryLimit) . 
            " ({$memoryPercent}%)"
        );
        
        if ($memoryPercent > $this->thresholds['memory_usage']) {
            $this->addAlert('HIGH_MEMORY', "Utilizzo memoria elevato: {$memoryPercent}%");
        }
        $this->metrics['memory_usage_percent'] = $memoryPercent;
        
        // 2. Disco
        $diskFree = disk_free_space(ROOT_PATH);
        $diskTotal = disk_total_space(ROOT_PATH);
        $diskUsed = $diskTotal - $diskFree;
        $diskPercent = round(($diskUsed / $diskTotal) * 100, 2);
        
        MonitorConsole::metric("Spazio disco", 
            $this->formatBytes($diskUsed) . " / " . $this->formatBytes($diskTotal) . 
            " ({$diskPercent}% utilizzato)"
        );
        
        if ($diskPercent > $this->thresholds['disk_usage']) {
            $this->addAlert('HIGH_DISK', "Spazio disco insufficiente: {$diskPercent}%");
        }
        $this->metrics['disk_usage_percent'] = $diskPercent;
        
        // 3. CPU (se disponibile)
        if (PHP_OS_FAMILY === 'Linux') {
            $cpuUsage = $this->getCPUUsage();
            if ($cpuUsage !== null) {
                MonitorConsole::metric("Utilizzo CPU", $cpuUsage, '%');
                $this->metrics['cpu_usage'] = $cpuUsage;
            }
        }
    }
    
    /**
     * Verifica query lente
     */
    private function checkSlowQueries() {
        MonitorConsole::header("QUERY LENTE");
        
        try {
            // Simula analisi query lente basata sui log
            $slowQueries = [];
            
            // Query esempio per trovare operazioni lente
            $testQueries = [
                [
                    'query' => "SELECT COUNT(*) FROM documenti d JOIN cartelle c ON d.cartella_id = c.id",
                    'description' => 'Count documenti con join'
                ],
                [
                    'query' => "SELECT * FROM log_attivita ORDER BY data_azione DESC LIMIT 1000",
                    'description' => 'Log attività recenti'
                ],
                [
                    'query' => "SELECT d.*, GROUP_CONCAT(dv.versione) as versioni FROM documenti d LEFT JOIN documenti_versioni dv ON d.id = dv.documento_id GROUP BY d.id",
                    'description' => 'Documenti con versioni'
                ]
            ];
            
            foreach ($testQueries as $test) {
                $startTime = microtime(true);
                
                try {
                    $stmt = $this->pdo->query($test['query']);
                    $stmt->fetchAll();
                    $execTime = microtime(true) - $startTime;
                    
                    if ($execTime > $this->thresholds['query_time']) {
                        $slowQueries[] = [
                            'query' => $test['description'],
                            'time' => $execTime
                        ];
                    }
                    
                } catch (PDOException $e) {
                    // Ignora errori query test
                }
            }
            
            if (empty($slowQueries)) {
                MonitorConsole::ok("Nessuna query lenta rilevata");
            } else {
                foreach ($slowQueries as $slow) {
                    MonitorConsole::warning(sprintf(
                        "%s: %.3f sec",
                        $slow['query'],
                        $slow['time']
                    ));
                }
                
                if (count($slowQueries) > $this->thresholds['slow_query_count']) {
                    $this->addAlert('SLOW_QUERIES', "Troppe query lente: " . count($slowQueries));
                }
            }
            
            $this->metrics['slow_query_count'] = count($slowQueries);
            
        } catch (Exception $e) {
            MonitorConsole::error("Errore analisi query: " . $e->getMessage());
        }
    }
    
    /**
     * Verifica dimensioni tabelle
     */
    private function checkTableSizes() {
        MonitorConsole::header("DIMENSIONI TABELLE");
        
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    table_name,
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb,
                    table_rows
                FROM information_schema.tables
                WHERE table_schema = '" . DB_NAME . "'
                    AND table_type = 'BASE TABLE'
                ORDER BY data_length + index_length DESC
                LIMIT 10
            ");
            
            $tables = $stmt->fetchAll();
            $totalSize = 0;
            
            foreach ($tables as $table) {
                MonitorConsole::metric(
                    $table['table_name'],
                    sprintf("%s MB (%s righe)", $table['size_mb'], number_format($table['table_rows']))
                );
                
                $totalSize += $table['size_mb'];
                
                // Alert per tabelle troppo grandi
                if ($table['size_mb'] * 1024 * 1024 > $this->thresholds['table_size'] / 1024) {
                    $this->addAlert('LARGE_TABLE', "Tabella grande: {$table['table_name']} ({$table['size_mb']} MB)");
                }
            }
            
            MonitorConsole::metric("Dimensione totale DB", $totalSize, 'MB');
            $this->metrics['database_size_mb'] = $totalSize;
            
        } catch (PDOException $e) {
            MonitorConsole::error("Errore lettura dimensioni: " . $e->getMessage());
        }
    }
    
    /**
     * Report alerts
     */
    private function reportAlerts() {
        if (empty($this->alerts)) {
            return;
        }
        
        MonitorConsole::header("ALERTS");
        
        foreach ($this->alerts as $alert) {
            MonitorConsole::error("[{$alert['type']}] {$alert['message']}");
        }
        
        // Salva alerts nel database
        $this->saveAlerts();
    }
    
    /**
     * Salva metriche nel database
     */
    private function saveMetrics() {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO performance_metrics 
                (timestamp, metrics_json, alerts_json) 
                VALUES (NOW(), ?, ?)
            ");
            
            $stmt->execute([
                json_encode($this->metrics),
                json_encode($this->alerts)
            ]);
            
        } catch (PDOException $e) {
            // Tabella potrebbe non esistere, crea se necessario
            $this->createMetricsTable();
        }
        
        // Salva anche su file
        $logFile = ROOT_PATH . '/logs/performance-' . date('Y-m-d') . '.json';
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'metrics' => $this->metrics,
            'alerts' => $this->alerts
        ];
        
        $existingData = [];
        if (file_exists($logFile)) {
            $existingData = json_decode(file_get_contents($logFile), true) ?: [];
        }
        
        $existingData[] = $logEntry;
        
        // Mantieni solo le ultime 24 ore
        $cutoff = strtotime('-24 hours');
        $existingData = array_filter($existingData, function($entry) use ($cutoff) {
            return strtotime($entry['timestamp']) > $cutoff;
        });
        
        file_put_contents($logFile, json_encode($existingData, JSON_PRETTY_PRINT));
    }
    
    /**
     * Crea tabella metriche se non esiste
     */
    private function createMetricsTable() {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS performance_metrics (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    metrics_json JSON,
                    alerts_json JSON,
                    INDEX idx_timestamp (timestamp)
                )
            ");
        } catch (PDOException $e) {
            // Ignora errore se tabella esiste già
        }
    }
    
    /**
     * Salva alerts critici
     */
    private function saveAlerts() {
        foreach ($this->alerts as $alert) {
            if (in_array($alert['type'], ['HIGH_LOAD', 'HIGH_MEMORY', 'HIGH_DISK'])) {
                // Log alert critico
                error_log("NEXIO ALERT: [{$alert['type']}] {$alert['message']}");
                
                // Potrebbe inviare notifica email/SMS per alerts critici
            }
        }
    }
    
    /**
     * Aggiungi alert
     */
    private function addAlert($type, $message) {
        $this->alerts[] = [
            'type' => $type,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Ottieni dimensione directory
     */
    private function getDirectorySize($dir) {
        $size = 0;
        
        if (!is_dir($dir)) {
            return 0;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }
    
    /**
     * Ottieni limite memoria PHP
     */
    private function getMemoryLimit() {
        $limit = ini_get('memory_limit');
        
        if ($limit == -1) {
            return PHP_INT_MAX;
        }
        
        $unit = strtolower(substr($limit, -1));
        $value = (int) $limit;
        
        switch ($unit) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
    
    /**
     * Ottieni utilizzo CPU (Linux)
     */
    private function getCPUUsage() {
        if (PHP_OS_FAMILY !== 'Linux') {
            return null;
        }
        
        $stat1 = file('/proc/stat');
        sleep(1);
        $stat2 = file('/proc/stat');
        
        $info1 = explode(" ", preg_replace("!cpu +!", "", $stat1[0]));
        $info2 = explode(" ", preg_replace("!cpu +!", "", $stat2[0]));
        
        $dif = [];
        $dif['user'] = $info2[0] - $info1[0];
        $dif['nice'] = $info2[1] - $info1[1];
        $dif['sys'] = $info2[2] - $info1[2];
        $dif['idle'] = $info2[3] - $info1[3];
        
        $total = array_sum($dif);
        
        if ($total > 0) {
            $cpu = round(100 - ($dif['idle'] * 100 / $total), 2);
            return $cpu;
        }
        
        return null;
    }
    
    /**
     * Formatta bytes
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Genera report HTML
     */
    public function generateHTMLReport() {
        $this->monitor(false); // Esegui monitoraggio singolo
        
        $html = '<!DOCTYPE html>
<html>
<head>
    <title>Nexio Performance Report</title>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; }
        .metric { display: inline-block; background: #f8f9fa; padding: 15px; margin: 10px; border-radius: 4px; min-width: 200px; }
        .metric-label { font-size: 12px; color: #666; }
        .metric-value { font-size: 24px; font-weight: bold; color: #333; }
        .alert { background: #f8d7da; color: #721c24; padding: 10px; margin: 10px 0; border-radius: 4px; }
        .ok { color: #28a745; }
        .warning { color: #ffc107; }
        .error { color: #dc3545; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Nexio Performance Report</h1>
        <p>Generated: ' . date('Y-m-d H:i:s') . '</p>';
        
        // Metriche principali
        $html .= '<h2>Metriche Principali</h2><div>';
        
        foreach ($this->metrics as $key => $value) {
            $label = ucwords(str_replace('_', ' ', $key));
            $html .= "<div class='metric'><div class='metric-label'>$label</div><div class='metric-value'>$value</div></div>";
        }
        
        $html .= '</div>';
        
        // Alerts
        if (!empty($this->alerts)) {
            $html .= '<h2>Alerts</h2>';
            foreach ($this->alerts as $alert) {
                $html .= "<div class='alert'>[{$alert['type']}] {$alert['message']}</div>";
            }
        }
        
        $html .= '</div></body></html>';
        
        $reportFile = ROOT_PATH . '/logs/performance-report-' . date('Y-m-d-His') . '.html';
        file_put_contents($reportFile, $html);
        
        MonitorConsole::info("\nReport HTML salvato in: $reportFile");
    }
}

// Esecuzione
if (php_sapi_name() === 'cli') {
    $monitor = new NexioPerformanceMonitor();
    
    // Verifica argomenti
    $continuous = in_array('--continuous', $argv) || in_array('-c', $argv);
    $html = in_array('--html', $argv) || in_array('-h', $argv);
    
    if ($html) {
        $monitor->generateHTMLReport();
    } else {
        $monitor->monitor($continuous);
    }
    
    echo "\n";
} else {
    die("Questo script deve essere eseguito da linea di comando.\n");
}