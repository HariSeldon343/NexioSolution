<?php
/**
 * Database Integrity Verification Script
 * Checks all required tables and columns for the Nexio platform
 * 
 * Usage: /mnt/c/xampp/php/php.exe scripts/verify-database-integrity.php
 */

// Prevent web access
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

// Color codes for terminal output
$colors = [
    'red'    => "\033[31m",
    'green'  => "\033[32m",
    'yellow' => "\033[33m",
    'blue'   => "\033[34m",
    'reset'  => "\033[0m"
];

function colorize($text, $color) {
    global $colors;
    return $colors[$color] . $text . $colors['reset'];
}

// Load configuration
require_once __DIR__ . '/../backend/config/config.php';

// Ensure database connection is available
if (!isset($db) || $db === null) {
    try {
        $db = db_connection();
    } catch (Exception $e) {
        die(colorize("✗", 'red') . " Failed to establish database connection: " . $e->getMessage() . "\n");
    }
}

function printHeader($text) {
    echo "\n" . colorize("=" . str_repeat("=", strlen($text) + 2) . "=", 'blue') . "\n";
    echo colorize("| " . $text . " |", 'blue') . "\n";
    echo colorize("=" . str_repeat("=", strlen($text) + 2) . "=", 'blue') . "\n\n";
}

function checkTable($tableName, $requiredColumns = []) {
    global $db;
    
    try {
        // Check if table exists
        $stmt = $db->query("SHOW TABLES LIKE '$tableName'");
        
        if ($stmt->rowCount() === 0) {
            echo colorize("✗", 'red') . " Table '$tableName' does not exist\n";
            return false;
        }
        
        echo colorize("✓", 'green') . " Table '$tableName' exists\n";
        
        // Check columns if specified
        if (!empty($requiredColumns)) {
            $stmt = $db->prepare("DESCRIBE $tableName");
            $stmt->execute();
            $existingColumns = [];
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $existingColumns[$row['Field']] = [
                    'type' => $row['Type'],
                    'null' => $row['Null'],
                    'key' => $row['Key'],
                    'default' => $row['Default'],
                    'extra' => $row['Extra']
                ];
            }
            
            $missingColumns = [];
            $incorrectTypes = [];
            
            foreach ($requiredColumns as $column => $expectedType) {
                if (!isset($existingColumns[$column])) {
                    $missingColumns[] = $column;
                } elseif ($expectedType !== null && !stripos($existingColumns[$column]['type'], $expectedType)) {
                    $incorrectTypes[$column] = [
                        'expected' => $expectedType,
                        'actual' => $existingColumns[$column]['type']
                    ];
                }
            }
            
            if (!empty($missingColumns)) {
                echo colorize("  ✗", 'red') . " Missing columns: " . implode(', ', $missingColumns) . "\n";
            }
            
            if (!empty($incorrectTypes)) {
                foreach ($incorrectTypes as $column => $types) {
                    echo colorize("  ⚠", 'yellow') . " Column '$column' type mismatch: expected {$types['expected']}, got {$types['actual']}\n";
                }
            }
            
            if (empty($missingColumns) && empty($incorrectTypes)) {
                echo colorize("  ✓", 'green') . " All required columns present with correct types\n";
            }
        }
        
        return true;
        
    } catch (PDOException $e) {
        echo colorize("✗", 'red') . " Error checking table '$tableName': " . $e->getMessage() . "\n";
        return false;
    }
}

function checkDatabaseConnection() {
    global $db;
    
    try {
        $db->query("SELECT 1");
        echo colorize("✓", 'green') . " Database connection successful\n";
        
        // Get database name
        $stmt = $db->query("SELECT DATABASE()");
        $dbName = $stmt->fetchColumn();
        echo colorize("✓", 'green') . " Connected to database: $dbName\n";
        
        return true;
    } catch (PDOException $e) {
        echo colorize("✗", 'red') . " Database connection failed: " . $e->getMessage() . "\n";
        return false;
    }
}

function countTables() {
    global $db;
    
    try {
        $stmt = $db->query("SHOW TABLES");
        $count = $stmt->rowCount();
        echo colorize("ℹ", 'blue') . " Total tables in database: $count\n";
        return $count;
    } catch (PDOException $e) {
        echo colorize("✗", 'red') . " Error counting tables: " . $e->getMessage() . "\n";
        return 0;
    }
}

function checkIndexes($tableName, $requiredIndexes = []) {
    global $db;
    
    if (empty($requiredIndexes)) {
        return true;
    }
    
    try {
        $stmt = $db->prepare("SHOW INDEX FROM $tableName");
        $stmt->execute();
        $existingIndexes = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $existingIndexes[$row['Key_name']][] = $row['Column_name'];
        }
        
        $missingIndexes = [];
        
        foreach ($requiredIndexes as $indexName => $columns) {
            if (!isset($existingIndexes[$indexName])) {
                $missingIndexes[] = $indexName;
            }
        }
        
        if (!empty($missingIndexes)) {
            echo colorize("  ⚠", 'yellow') . " Missing indexes: " . implode(', ', $missingIndexes) . "\n";
        } else {
            echo colorize("  ✓", 'green') . " All required indexes present\n";
        }
        
        return empty($missingIndexes);
        
    } catch (PDOException $e) {
        echo colorize("  ✗", 'red') . " Error checking indexes: " . $e->getMessage() . "\n";
        return false;
    }
}

// Main execution
printHeader("NEXIO DATABASE INTEGRITY VERIFICATION");

// Check database connection
if (!checkDatabaseConnection()) {
    exit(1);
}

echo "\n";
countTables();

// Define critical tables and their required columns
$criticalTables = [
    'utenti' => [
        'id' => 'int',
        'nome' => 'varchar',
        'cognome' => 'varchar',
        'email' => 'varchar',
        'password' => 'varchar',
        'role' => null, // Don't check type for role
        'stato' => null,
        'azienda_id' => null
    ],
    
    'aziende' => [
        'id' => 'int',
        'nome' => 'varchar', // This is the correct column name
        'ragione_sociale' => 'varchar',
        'codice' => 'varchar',
        'partita_iva' => 'varchar',
        'stato' => null,
        'creata_da' => null
    ],
    
    'documenti' => [
        'id' => 'int',
        'nome_file' => 'varchar',
        'percorso_file' => 'varchar',
        'mime_type' => 'varchar',
        'dimensione' => null,
        'azienda_id' => null,
        'cartella_id' => null,
        'creato_da' => null,
        'data_caricamento' => null
    ],
    
    'cartelle' => [
        'id' => 'int',
        'nome' => 'varchar',
        'parent_id' => null,
        'azienda_id' => null,
        'percorso' => null,
        'creata_da' => null
    ],
    
    'eventi' => [
        'id' => 'int',
        'titolo' => 'varchar',
        'descrizione' => 'text',
        'data_inizio' => null,
        'data_fine' => null,
        'tipo' => null,
        'azienda_id' => null,
        'creato_da' => null
    ],
    
    'tickets' => [
        'id' => 'int',
        'oggetto' => 'varchar',
        'descrizione' => 'text',
        'stato' => null,
        'priorita' => null,
        'azienda_id' => null,
        'creato_da' => null,
        'assegnato_a' => null
    ],
    
    'referenti' => [
        'id' => 'int',
        'nome' => 'varchar',
        'cognome' => 'varchar',
        'azienda_id' => null,
        'ruolo' => null,
        'email' => null,
        'telefono' => null
    ],
    
    'log_attivita' => [
        'id' => 'int',
        'tipo' => 'varchar',
        'azione' => 'varchar',
        'descrizione' => 'text',
        'utente_id' => null,
        'azienda_id' => null,
        'non_eliminabile' => null,
        'data_attivita' => null
    ],
    
    'tasks' => [
        'id' => 'int',
        'titolo' => 'varchar',
        'descrizione' => 'text',
        'stato' => null,
        'priorita' => null,
        'azienda_id' => null,
        'assegnato_a' => null,
        'creato_da' => null
    ],
    
    'moduli' => [
        'id' => 'int',
        'nome' => 'varchar',
        'slug' => 'varchar',
        'descrizione' => 'text',
        'attivo' => null,
        'ordine' => null
    ],
    
    'utenti_aziende' => [
        'id' => 'int',
        'utente_id' => 'int',
        'azienda_id' => 'int',
        'ruolo' => null,
        'attivo' => null
    ]
];

// Additional tables to check existence (without column verification)
$additionalTables = [
    'documenti_versioni',
    'documenti_destinatari',
    'moduli_azienda',
    'moduli_documento',
    'notifiche_email',
    'email_queue',
    'password_history',
    'rate_limit',
    'task_calendario',
    'ticket_destinatari',
    'user_permissions',
    'template_documenti',
    'template_elementi',
    'iso_compliance_checks',
    'iso_document_types',
    'iso_folders',
    'classificazioni',
    'sottoclassificazioni'
];

printHeader("CHECKING CRITICAL TABLES");

$criticalErrors = 0;
foreach ($criticalTables as $table => $columns) {
    if (!checkTable($table, $columns)) {
        $criticalErrors++;
    }
    echo "\n";
}

printHeader("CHECKING ADDITIONAL TABLES");

$missingTables = 0;
foreach ($additionalTables as $table) {
    if (!checkTable($table)) {
        $missingTables++;
    }
}

// Check for common query issues
printHeader("CHECKING COMMON QUERY PATTERNS");

// Check for incorrect column references
$problematicQueries = [
    'nome_azienda reference' => "SELECT * FROM information_schema.columns WHERE table_schema = DATABASE() AND column_name = 'nome_azienda'",
    'file_type reference' => "SELECT * FROM information_schema.columns WHERE table_schema = DATABASE() AND column_name = 'file_type' AND table_name = 'documenti'"
];

foreach ($problematicQueries as $issue => $query) {
    try {
        $stmt = $db->query($query);
        if ($stmt->rowCount() > 0) {
            echo colorize("⚠", 'yellow') . " Found potentially problematic column: $issue\n";
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "    Table: {$row['TABLE_NAME']}, Column: {$row['COLUMN_NAME']}\n";
            }
        } else {
            echo colorize("✓", 'green') . " No issues found for: $issue\n";
        }
    } catch (PDOException $e) {
        echo colorize("✗", 'red') . " Error checking $issue: " . $e->getMessage() . "\n";
    }
}

// Check important indexes
printHeader("CHECKING PERFORMANCE INDEXES");

$indexesToCheck = [
    'documenti' => [
        'idx_azienda_id' => ['azienda_id'],
        'idx_cartella_id' => ['cartella_id']
    ],
    'cartelle' => [
        'idx_parent_id' => ['parent_id'],
        'idx_azienda_id' => ['azienda_id']
    ],
    'log_attivita' => [
        'idx_utente_id' => ['utente_id'],
        'idx_azienda_id' => ['azienda_id'],
        'idx_data_attivita' => ['data_attivita']
    ]
];

foreach ($indexesToCheck as $table => $indexes) {
    echo "Checking indexes for table '$table':\n";
    checkIndexes($table, $indexes);
    echo "\n";
}

// Summary
printHeader("VERIFICATION SUMMARY");

if ($criticalErrors > 0) {
    echo colorize("✗", 'red') . " Critical errors found: $criticalErrors\n";
    echo colorize("!", 'yellow') . " Please run the database setup scripts to fix these issues.\n";
    echo "  Run: /mnt/c/xampp/php/php.exe scripts/setup-nexio-documentale.php\n";
} else {
    echo colorize("✓", 'green') . " All critical tables are properly configured\n";
}

if ($missingTables > 0) {
    echo colorize("⚠", 'yellow') . " Missing optional tables: $missingTables\n";
    echo "  These may be needed for specific features.\n";
}

// Check for OnlyOffice specific issues
printHeader("ONLYOFFICE INTEGRATION CHECK");

echo "Checking OnlyOffice table requirements:\n";

// Check if documenti table has required columns for OnlyOffice
$onlyofficeColumns = [
    'documenti' => [
        'percorso_file' => 'varchar',
        'mime_type' => 'varchar',
        'versione' => null,
        'data_modifica' => null
    ]
];

foreach ($onlyofficeColumns as $table => $columns) {
    checkTable($table, $columns);
}

// Final recommendations
printHeader("RECOMMENDATIONS");

echo "1. All queries referencing 'aziende' table should use 'nome' column, not 'nome_azienda'\n";
echo "2. Use 'mime_type' column in 'documenti' table, not 'file_type'\n";
echo "3. Always use 'cartella_id = NULL' for root folder, never 0\n";
echo "4. Ensure all foreign key relationships are properly established\n";
echo "5. Regular backups are recommended before any database modifications\n";

echo "\n" . colorize("Database verification completed!", 'green') . "\n\n";

// Return exit code based on critical errors
exit($criticalErrors > 0 ? 1 : 0);