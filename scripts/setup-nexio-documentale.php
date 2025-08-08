<?php
/**
 * Script di Setup Completo per Sistema Documentale Nexio
 * 
 * Questo script configura l'intero sistema documentale:
 * - Esegue tutti gli script SQL nell'ordine corretto
 * - Crea le directory necessarie
 * - Verifica i permessi
 * - Configura le tabelle iniziali
 * - Crea strutture di test
 * 
 * @package Nexio
 * @version 1.0.0
 */

// Configurazione
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300);

// Percorsi
define('ROOT_PATH', dirname(__DIR__));
define('DATABASE_PATH', ROOT_PATH . '/database');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('DOCUMENTS_PATH', ROOT_PATH . '/documents');
define('LOGS_PATH', ROOT_PATH . '/logs');

// Colori per output
class Console {
    public static function info($msg) {
        echo "\033[36m[INFO]\033[0m $msg\n";
    }
    
    public static function success($msg) {
        echo "\033[32m[SUCCESS]\033[0m $msg\n";
    }
    
    public static function error($msg) {
        echo "\033[31m[ERROR]\033[0m $msg\n";
    }
    
    public static function warning($msg) {
        echo "\033[33m[WARNING]\033[0m $msg\n";
    }
}

// Classe principale di setup
class NexioSetup {
    private $pdo;
    private $config;
    private $errors = [];
    private $warnings = [];
    
    public function __construct() {
        $this->loadConfig();
    }
    
    /**
     * Carica la configurazione
     */
    private function loadConfig() {
        $configFile = ROOT_PATH . '/backend/config/config.php';
        if (!file_exists($configFile)) {
            die("File di configurazione non trovato: $configFile\n");
        }
        
        require_once $configFile;
        
        $this->config = [
            'db_host' => DB_HOST ?? 'localhost',
            'db_name' => DB_NAME ?? 'NexioSol',
            'db_user' => DB_USER ?? 'root',
            'db_pass' => DB_PASS ?? ''
        ];
    }
    
    /**
     * Esegue il setup completo
     */
    public function run() {
        Console::info("=== NEXIO DOCUMENTALE - SETUP COMPLETO ===\n");
        
        // 1. Verifica requisiti
        Console::info("1. Verifica requisiti di sistema...");
        if (!$this->checkRequirements()) {
            $this->showErrors();
            return false;
        }
        Console::success("Requisiti verificati");
        
        // 2. Connessione database
        Console::info("\n2. Connessione al database...");
        if (!$this->connectDatabase()) {
            $this->showErrors();
            return false;
        }
        Console::success("Connesso al database");
        
        // 3. Esecuzione script SQL
        Console::info("\n3. Esecuzione script SQL...");
        if (!$this->executeSQLScripts()) {
            $this->showErrors();
            return false;
        }
        Console::success("Script SQL eseguiti");
        
        // 4. Creazione directory
        Console::info("\n4. Creazione directory...");
        if (!$this->createDirectories()) {
            $this->showErrors();
            return false;
        }
        Console::success("Directory create");
        
        // 5. Verifica permessi
        Console::info("\n5. Verifica permessi...");
        if (!$this->checkPermissions()) {
            $this->showErrors();
            return false;
        }
        Console::success("Permessi verificati");
        
        // 6. Inserimento dati iniziali
        Console::info("\n6. Inserimento dati iniziali...");
        if (!$this->insertInitialData()) {
            $this->showErrors();
            return false;
        }
        Console::success("Dati iniziali inseriti");
        
        // 7. Creazione strutture di test
        Console::info("\n7. Creazione strutture di test...");
        if (!$this->createTestStructures()) {
            $this->showErrors();
            return false;
        }
        Console::success("Strutture di test create");
        
        // 8. Generazione report
        Console::info("\n8. Generazione report di setup...");
        $this->generateSetupReport();
        
        Console::success("\n=== SETUP COMPLETATO CON SUCCESSO ===");
        
        if (!empty($this->warnings)) {
            Console::warning("\nAvvertimenti:");
            foreach ($this->warnings as $warning) {
                Console::warning("- $warning");
            }
        }
        
        return true;
    }
    
    /**
     * Verifica i requisiti di sistema
     */
    private function checkRequirements() {
        $requirements = [
            'PHP Version >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
            'PDO Extension' => extension_loaded('pdo'),
            'PDO MySQL Extension' => extension_loaded('pdo_mysql'),
            'JSON Extension' => extension_loaded('json'),
            'MBString Extension' => extension_loaded('mbstring'),
            'OpenSSL Extension' => extension_loaded('openssl'),
            'GD Extension' => extension_loaded('gd'),
            'ZIP Extension' => extension_loaded('zip')
        ];
        
        $allPassed = true;
        
        foreach ($requirements as $req => $passed) {
            if ($passed) {
                Console::success("✓ $req");
            } else {
                Console::error("✗ $req");
                $this->errors[] = "Requisito mancante: $req";
                $allPassed = false;
            }
        }
        
        return $allPassed;
    }
    
    /**
     * Connessione al database
     */
    private function connectDatabase() {
        try {
            $dsn = "mysql:host={$this->config['db_host']};charset=utf8mb4";
            $this->pdo = new PDO($dsn, $this->config['db_user'], $this->config['db_pass']);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Crea database se non esiste
            $dbName = $this->config['db_name'];
            $this->pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $this->pdo->exec("USE `$dbName`");
            
            return true;
        } catch (PDOException $e) {
            $this->errors[] = "Errore connessione database: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Esegue gli script SQL nell'ordine corretto
     */
    private function executeSQLScripts() {
        // Ordine di esecuzione degli script SQL
        $scripts = [
            // 1. Tabelle base
            'create_basic_tables.sql',
            'create_aziende_table.sql',
            'create_documenti_table.sql',
            'update_users_table.sql',
            
            // 2. Sistema moduli
            'create_module_system.sql',
            'create_moduli_azienda.sql',
            
            // 3. Sistema permessi
            'create_user_permissions.sql',
            'add_utente_speciale_role.sql',
            
            // 4. Sistema documentale avanzato
            'create_cartelle_table.sql',
            'create_enhanced_filesystem_tables.sql',
            'create_advanced_document_system.sql',
            'create_documenti_versioni.sql',
            
            // 5. Sistema ISO
            'create_iso_compliance_system.sql',
            'create_iso_document_system.sql',
            'create_iso_security_tables.sql',
            'insert_iso_data.sql',
            
            // 6. Sistema multi-standard
            'complete_multi_standard_document_system.sql',
            
            // 7. Altri sistemi
            'create_email_tables.sql',
            'create_notifiche_email_table.sql',
            'create_rate_limit_tables.sql',
            'create_task_calendar_table.sql',
            
            // 8. Viste e procedure
            'create_task_views.sql',
            'stored_procedures_document_system.sql',
            'reporting_views_document_system.sql',
            
            // 9. Ottimizzazioni
            'optimize_performance.sql',
            
            // 10. Dati iniziali
            'insert_classificazioni_complete.sql',
            'add_nexio_ai_module.sql'
        ];
        
        $executedCount = 0;
        $skippedCount = 0;
        
        foreach ($scripts as $script) {
            $filepath = DATABASE_PATH . '/' . $script;
            
            if (!file_exists($filepath)) {
                $this->warnings[] = "Script non trovato: $script";
                $skippedCount++;
                continue;
            }
            
            Console::info("  Esecuzione: $script");
            
            try {
                $sql = file_get_contents($filepath);
                
                // Rimuovi commenti e dividi per statement
                $sql = preg_replace('/--.*$/m', '', $sql);
                $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
                
                // Esegui ogni statement separatamente
                $statements = array_filter(
                    array_map('trim', preg_split('/;\s*$/m', $sql)),
                    function($stmt) { return !empty($stmt); }
                );
                
                foreach ($statements as $statement) {
                    if (!empty(trim($statement))) {
                        $this->pdo->exec($statement);
                    }
                }
                
                $executedCount++;
                Console::success("  ✓ $script eseguito");
                
            } catch (PDOException $e) {
                // Ignora errori per tabelle già esistenti
                if (strpos($e->getMessage(), 'already exists') !== false) {
                    Console::warning("  ! $script: tabella già esistente (ignorato)");
                    $skippedCount++;
                } else {
                    Console::error("  ✗ $script: " . $e->getMessage());
                    $this->warnings[] = "Errore in $script: " . $e->getMessage();
                }
            }
        }
        
        Console::info("  Script eseguiti: $executedCount, Ignorati: $skippedCount");
        return true;
    }
    
    /**
     * Crea le directory necessarie
     */
    private function createDirectories() {
        $directories = [
            UPLOADS_PATH,
            UPLOADS_PATH . '/documenti',
            UPLOADS_PATH . '/documenti/iso',
            UPLOADS_PATH . '/documenti/temp',
            UPLOADS_PATH . '/templates',
            UPLOADS_PATH . '/loghi',
            UPLOADS_PATH . '/attachments',
            DOCUMENTS_PATH,
            DOCUMENTS_PATH . '/onlyoffice',
            DOCUMENTS_PATH . '/exports',
            LOGS_PATH,
            ROOT_PATH . '/temp',
            ROOT_PATH . '/cache'
        ];
        
        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    $this->errors[] = "Impossibile creare directory: $dir";
                    return false;
                }
                Console::success("  Creata: $dir");
            } else {
                Console::info("  Esistente: $dir");
            }
            
            // Crea .htaccess per sicurezza
            $htaccess = $dir . '/.htaccess';
            if (!file_exists($htaccess) && strpos($dir, 'uploads') !== false) {
                file_put_contents($htaccess, "Options -Indexes\nOptions -ExecCGI\nAddHandler cgi-script .php .pl .py .jsp .asp .sh");
            }
        }
        
        return true;
    }
    
    /**
     * Verifica i permessi delle directory
     */
    private function checkPermissions() {
        $writableDirs = [
            UPLOADS_PATH,
            DOCUMENTS_PATH,
            LOGS_PATH,
            ROOT_PATH . '/temp',
            ROOT_PATH . '/cache'
        ];
        
        $allWritable = true;
        
        foreach ($writableDirs as $dir) {
            if (is_writable($dir)) {
                Console::success("  ✓ Scrivibile: $dir");
            } else {
                Console::error("  ✗ Non scrivibile: $dir");
                $this->errors[] = "Directory non scrivibile: $dir";
                $allWritable = false;
            }
        }
        
        return $allWritable;
    }
    
    /**
     * Inserisce i dati iniziali
     */
    private function insertInitialData() {
        try {
            // 1. Crea azienda di test
            $stmt = $this->pdo->prepare("
                INSERT IGNORE INTO aziende (nome, codice, stato) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute(['Azienda Demo', 'DEMO', 'attiva']);
            $demoCompanyId = $this->pdo->lastInsertId() ?: 1;
            
            // 2. Crea utente super admin
            $stmt = $this->pdo->prepare("
                INSERT IGNORE INTO utenti (username, password, email, nome, cognome, ruolo, attivo) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                'admin',
                password_hash('admin123', PASSWORD_DEFAULT),
                'admin@nexiosolution.it',
                'Admin',
                'Sistema',
                'super_admin',
                1
            ]);
            $adminId = $this->pdo->lastInsertId() ?: 1;
            
            // 3. Collega admin all'azienda
            $stmt = $this->pdo->prepare("
                INSERT IGNORE INTO utenti_aziende (utente_id, azienda_id, ruolo_azienda) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$adminId, $demoCompanyId, 'admin']);
            
            // 4. Abilita tutti i moduli per l'azienda demo
            $modules = $this->pdo->query("SELECT id FROM moduli_sistema WHERE attivo = 1")->fetchAll();
            foreach ($modules as $module) {
                $stmt = $this->pdo->prepare("
                    INSERT IGNORE INTO moduli_azienda (azienda_id, modulo_id, attivo) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$demoCompanyId, $module['id'], 1]);
            }
            
            // 5. Configura ISO per azienda demo
            $stmt = $this->pdo->prepare("
                INSERT IGNORE INTO iso_configurazione_azienda 
                (azienda_id, tipo_struttura, iso_9001, iso_14001, iso_45001, gdpr) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$demoCompanyId, 'separata', 1, 1, 1, 1]);
            
            Console::success("  Dati iniziali inseriti");
            Console::info("  Username: admin");
            Console::info("  Password: admin123");
            
            return true;
            
        } catch (PDOException $e) {
            $this->errors[] = "Errore inserimento dati: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Crea strutture di test
     */
    private function createTestStructures() {
        try {
            // Ottieni l'ID dell'azienda demo
            $stmt = $this->pdo->query("SELECT id FROM aziende WHERE codice = 'DEMO' LIMIT 1");
            $demoCompanyId = $stmt->fetchColumn();
            
            if (!$demoCompanyId) {
                $this->warnings[] = "Azienda demo non trovata";
                return true;
            }
            
            // Crea struttura cartelle ISO 9001
            $iso9001Structure = [
                'ISO 9001 - Sistema di Gestione Qualità' => [
                    '4. Contesto dell\'organizzazione' => [
                        '4.1 Comprensione dell\'organizzazione',
                        '4.2 Comprensione delle esigenze delle parti interessate',
                        '4.3 Campo di applicazione',
                        '4.4 Sistema di gestione qualità'
                    ],
                    '5. Leadership' => [
                        '5.1 Leadership e impegno',
                        '5.2 Politica',
                        '5.3 Ruoli e responsabilità'
                    ],
                    '6. Pianificazione' => [
                        '6.1 Azioni per affrontare rischi e opportunità',
                        '6.2 Obiettivi per la qualità',
                        '6.3 Pianificazione delle modifiche'
                    ],
                    '7. Supporto' => [
                        '7.1 Risorse',
                        '7.2 Competenza',
                        '7.3 Consapevolezza',
                        '7.4 Comunicazione',
                        '7.5 Informazioni documentate'
                    ]
                ]
            ];
            
            $this->createFolderStructure($iso9001Structure, null, $demoCompanyId, 'ISO_9001');
            
            // Crea alcuni documenti di esempio
            $documents = [
                ['codice' => 'DOC-001', 'titolo' => 'Manuale della Qualità', 'tipo' => 'manuale'],
                ['codice' => 'PRO-001', 'titolo' => 'Procedura Controllo Documenti', 'tipo' => 'procedura'],
                ['codice' => 'MOD-001', 'titolo' => 'Modulo Richiesta Modifica', 'tipo' => 'modulo'],
                ['codice' => 'REG-001', 'titolo' => 'Registro Non Conformità', 'tipo' => 'registro']
            ];
            
            foreach ($documents as $doc) {
                $stmt = $this->pdo->prepare("
                    INSERT IGNORE INTO documenti 
                    (codice, titolo, tipo_documento, stato, azienda_id, creato_da) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $doc['codice'],
                    $doc['titolo'],
                    $doc['tipo'],
                    'pubblicato',
                    $demoCompanyId,
                    1
                ]);
            }
            
            Console::success("  Strutture di test create");
            return true;
            
        } catch (PDOException $e) {
            $this->errors[] = "Errore creazione strutture test: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Crea struttura cartelle ricorsivamente
     */
    private function createFolderStructure($structure, $parentId, $companyId, $standard, $path = '') {
        foreach ($structure as $key => $value) {
            $folderName = is_array($value) ? $key : $value;
            $currentPath = $path ? "$path/$folderName" : $folderName;
            
            // Crea cartella
            $stmt = $this->pdo->prepare("
                INSERT INTO cartelle 
                (nome, parent_id, percorso_completo, standard_riferimento, azienda_id) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$folderName, $parentId, $currentPath, $standard, $companyId]);
            $folderId = $this->pdo->lastInsertId();
            
            // Se ha sottocartelle, creale ricorsivamente
            if (is_array($value)) {
                $this->createFolderStructure($value, $folderId, $companyId, $standard, $currentPath);
            }
        }
    }
    
    /**
     * Genera report di setup
     */
    private function generateSetupReport() {
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0.0',
            'database' => $this->config['db_name'],
            'statistics' => []
        ];
        
        // Raccogli statistiche
        $tables = [
            'utenti' => 'Utenti',
            'aziende' => 'Aziende',
            'cartelle' => 'Cartelle',
            'documenti' => 'Documenti',
            'moduli_sistema' => 'Moduli'
        ];
        
        foreach ($tables as $table => $label) {
            try {
                $count = $this->pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
                $report['statistics'][$label] = $count;
            } catch (PDOException $e) {
                $report['statistics'][$label] = 'N/A';
            }
        }
        
        $reportFile = LOGS_PATH . '/setup-report-' . date('Y-m-d-His') . '.json';
        file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));
        
        Console::info("\nStatistiche Database:");
        foreach ($report['statistics'] as $label => $count) {
            Console::info("  $label: $count");
        }
        
        Console::success("\nReport salvato in: $reportFile");
    }
    
    /**
     * Mostra errori accumulati
     */
    private function showErrors() {
        if (!empty($this->errors)) {
            Console::error("\nErrori riscontrati:");
            foreach ($this->errors as $error) {
                Console::error("- $error");
            }
        }
    }
}

// Esecuzione
if (php_sapi_name() === 'cli') {
    $setup = new NexioSetup();
    $setup->run();
} else {
    die("Questo script deve essere eseguito da linea di comando.\n");
}