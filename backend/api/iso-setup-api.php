<?php
/**
 * API per Setup Sistema ISO
 * Gestisce l'esecuzione automatica degli script per risolvere errori ISOStructureManager
 */

header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../middleware/Auth.php';

$auth = Auth::getInstance();
if (!$auth->checkSession()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autenticato']);
    exit;
}

// Solo utenti con privilegi elevati
if (!$auth->isSuperAdmin() && !$auth->isUtenteSpeciale()) {
    http_response_code(403);
    echo json_encode(['error' => 'Accesso negato']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? null;
    
    switch ($action) {
        case 'execute_sql_setup':
            $result = executeSQLSetup();
            break;
            
        case 'verify_tables':
            $result = verifyTables();
            break;
            
        case 'test_manager':
            $result = testISOStructureManager();
            break;
            
        default:
            throw new Exception('Azione non valida');
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Esegue lo script SQL per creare le tabelle ISO
 */
function executeSQLSetup() {
    try {
        $pdo = db_connection();
        
        // Legge il file SQL
        $sqlFile = __DIR__ . '/../../database/create_iso_system_fixed.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception('File SQL non trovato');
        }
        
        $sql = file_get_contents($sqlFile);
        if (!$sql) {
            throw new Exception('Impossibile leggere il file SQL');
        }
        
        // Rimuove i commenti mantenendo le linee
        $lines = explode("\n", $sql);
        $cleanLines = [];
        $inMultilineComment = false;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines
            if (empty($line)) continue;
            
            // Skip single-line comments
            if (strpos($line, '--') === 0) continue;
            
            // Handle multiline comments
            if (strpos($line, '/*') !== false) {
                $inMultilineComment = true;
            }
            if ($inMultilineComment && strpos($line, '*/') !== false) {
                $inMultilineComment = false;
                continue;
            }
            if ($inMultilineComment) continue;
            
            // Skip DELIMITER statements
            if (stripos($line, 'DELIMITER') !== false) continue;
            
            $cleanLines[] = $line;
        }
        
        // Ricostruisce il SQL e divide in statements
        $cleanSql = implode("\n", $cleanLines);
        $statements = explode(';', $cleanSql);
        $executed = 0;
        $errors = [];
        
        $pdo->beginTransaction();
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement)) continue;
            
            try {
                $pdo->exec($statement);
                $executed++;
            } catch (PDOException $e) {
                $errorMsg = $e->getMessage();
                
                // Ignora errori accettabili
                $ignorableErrors = [
                    'already exists',
                    'Duplicate entry',
                    'Duplicate key',
                    'Table already exists',
                    'Column already exists'
                ];
                
                $shouldIgnore = false;
                foreach ($ignorableErrors as $ignorable) {
                    if (stripos($errorMsg, $ignorable) !== false) {
                        $shouldIgnore = true;
                        break;
                    }
                }
                
                if (!$shouldIgnore) {
                    $errors[] = [
                        'statement' => substr($statement, 0, 100) . '...',
                        'error' => $errorMsg
                    ];
                    // Non interrompe l'esecuzione, continua con il prossimo statement
                }
            }
        }
        
        $pdo->commit();
        
        return [
            'success' => true,
            'statements_executed' => $executed,
            'errors_count' => count($errors),
            'errors' => $errors,
            'message' => count($errors) > 0 ? 
                "Script SQL eseguito con {$executed} statement. {count($errors)} errori non critici." :
                'Script SQL eseguito con successo'
        ];
        
    } catch (Exception $e) {
        if (isset($pdo)) {
            $pdo->rollback();
        }
        throw new Exception('Errore SQL: ' . $e->getMessage());
    }
}

/**
 * Verifica che tutte le tabelle siano state create correttamente
 */
function verifyTables() {
    try {
        $pdo = db_connection();
        
        $requiredTables = [
            'iso_standards',
            'iso_folder_templates', 
            'aziende_iso_config',
            'aziende_iso_folders',
            'iso_deployment_log',
            'iso_compliance_check'
        ];
        
        $tablesFound = [];
        $tablesCreated = 0;
        
        foreach ($requiredTables as $table) {
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            $exists = $stmt->fetch();
            
            $tablesFound[$table] = (bool)$exists;
            if ($exists) {
                $tablesCreated++;
            }
        }
        
        // Verifica che ci siano dati di base
        $standardsCount = $pdo->query("SELECT COUNT(*) FROM iso_standards WHERE attivo = 1")->fetchColumn();
        $templatesCount = $pdo->query("SELECT COUNT(*) FROM iso_folder_templates")->fetchColumn();
        
        return [
            'success' => true,
            'tables_found' => $tablesFound,
            'tables_created' => $tablesCreated,
            'total_required' => count($requiredTables),
            'standards_loaded' => (int)$standardsCount,
            'templates_loaded' => (int)$templatesCount,
            'system_ready' => ($tablesCreated === count($requiredTables) && $standardsCount > 0)
        ];
        
    } catch (Exception $e) {
        throw new Exception('Errore verifica tabelle: ' . $e->getMessage());
    }
}

/**
 * Testa se ISOStructureManager può essere inizializzato
 */
function testISOStructureManager() {
    try {
        // Prova a caricare la classe
        require_once '../utils/ISOStructureManager.php';
        
        // Prova a istanziare il manager
        require_once '../utils/ISOStructureManager.php';
        $manager = ISOStructureManager::getInstance();
        
        // Testa metodi base
        $standards = $manager->getAvailableStandards();
        $performance = $manager->getPerformanceMetrics();
        
        return [
            'success' => true,
            'manager_loaded' => true,
            'standards_available' => count($standards),
            'standards_list' => array_keys($standards),
            'performance_metrics' => $performance,
            'message' => 'ISOStructureManager inizializzato correttamente'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'manager_loaded' => false,
            'error' => $e->getMessage(),
            'message' => 'Errore nell\'inizializzazione ISOStructureManager'
        ];
    }
}

/**
 * Ripara eventuali problemi di permessi sulle cartelle
 */
function repairFolderPermissions() {
    try {
        $pdo = db_connection();
        
        // Aggiorna cartelle senza metadati ISO
        $stmt = $pdo->exec("
            UPDATE cartelle 
            SET iso_metadata = JSON_OBJECT('created_manually', true, 'iso_compliant', false)
            WHERE iso_metadata IS NULL AND azienda_id IS NOT NULL
        ");
        
        return [
            'success' => true,
            'folders_updated' => $stmt,
            'message' => 'Permessi cartelle riparati'
        ];
        
    } catch (Exception $e) {
        throw new Exception('Errore riparazione permessi: ' . $e->getMessage());
    }
}
?>