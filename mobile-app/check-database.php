<?php
/**
 * Database Check Script for Mobile App
 * Verifies database structure for calendar events
 */

// Includi le configurazioni necessarie
require_once '../backend/config/database.php';

header('Content-Type: application/json');

try {
    $results = [];
    
    // 1. Test connessione database
    $results['database'] = [
        'connected' => false,
        'version' => null,
        'name' => DB_NAME
    ];
    
    try {
        $stmt = $pdo->query("SELECT VERSION() as version, DATABASE() as db_name");
        $dbInfo = $stmt->fetch();
        $results['database']['connected'] = true;
        $results['database']['version'] = $dbInfo['version'];
        $results['database']['current_db'] = $dbInfo['db_name'];
    } catch (Exception $e) {
        $results['database']['error'] = $e->getMessage();
    }
    
    // 2. Verifica esistenza tabelle necessarie
    $requiredTables = [
        'eventi',
        'evento_partecipanti', 
        'utenti',
        'aziende',
        'user_sessions'
    ];
    
    $results['tables'] = [];
    
    foreach ($requiredTables as $table) {
        $exists = db_table_exists($table);
        $results['tables'][$table] = [
            'exists' => $exists,
            'count' => 0,
            'structure' => []
        ];
        
        if ($exists) {
            try {
                // Conta righe
                $count = db_count($table);
                $results['tables'][$table]['count'] = $count;
                
                // Struttura tabella
                $structure = db_query("DESCRIBE $table")->fetchAll();
                $results['tables'][$table]['structure'] = $structure;
                
            } catch (Exception $e) {
                $results['tables'][$table]['error'] = $e->getMessage();
            }
        }
    }
    
    // 3. Verifica specificamente tabella eventi
    if (db_table_exists('eventi')) {
        $results['eventi_details'] = [];
        
        try {
            // Conta eventi totali
            $totalEvents = db_count('eventi');
            $results['eventi_details']['total_events'] = $totalEvents;
            
            // Conta eventi per azienda
            $eventsPerCompany = db_query("
                SELECT azienda_id, COUNT(*) as count 
                FROM eventi 
                GROUP BY azienda_id 
                ORDER BY count DESC
            ")->fetchAll();
            $results['eventi_details']['events_per_company'] = $eventsPerCompany;
            
            // Eventi recenti (ultimi 10)
            $recentEvents = db_query("
                SELECT id, titolo, data_inizio, data_fine, azienda_id, creato_da, creato_il 
                FROM eventi 
                ORDER BY creato_il DESC 
                LIMIT 10
            ")->fetchAll();
            $results['eventi_details']['recent_events'] = $recentEvents;
            
            // Range date eventi
            $dateRange = db_query("
                SELECT 
                    MIN(data_inizio) as min_date,
                    MAX(data_inizio) as max_date,
                    COUNT(DISTINCT DATE(data_inizio)) as unique_dates
                FROM eventi
            ")->fetch();
            $results['eventi_details']['date_range'] = $dateRange;
            
        } catch (Exception $e) {
            $results['eventi_details']['error'] = $e->getMessage();
        }
    }
    
    // 4. Verifica utenti e autenticazione
    if (db_table_exists('utenti')) {
        try {
            $userCount = db_count('utenti');
            $results['users'] = [
                'total_users' => $userCount,
                'active_sessions' => 0
            ];
            
            if (db_table_exists('user_sessions')) {
                $activeSessions = db_count('user_sessions', 'expires_at > NOW()');
                $results['users']['active_sessions'] = $activeSessions;
            }
            
        } catch (Exception $e) {
            $results['users']['error'] = $e->getMessage();
        }
    }
    
    // 5. Test query specifica per mobile app
    $results['mobile_app_test'] = [];
    
    try {
        // Simula query che fa l'app mobile
        $today = date('Y-m-d');
        $testQuery = "
            SELECT e.*, 
                   u.nome as creatore_nome, u.cognome as creatore_cognome,
                   COUNT(ep.id) as num_partecipanti,
                   GROUP_CONCAT(CONCAT(up.nome, ' ', up.cognome) SEPARATOR ', ') as partecipanti_nomi
            FROM eventi e 
            LEFT JOIN utenti u ON e.creato_da = u.id 
            LEFT JOIN evento_partecipanti ep ON e.id = ep.evento_id
            LEFT JOIN utenti up ON ep.utente_id = up.id
            WHERE DATE(e.data_inizio) BETWEEN ? AND ?
            GROUP BY e.id
            ORDER BY e.data_inizio ASC
            LIMIT 5
        ";
        
        $testEvents = db_query($testQuery, [$today, $today])->fetchAll();
        $results['mobile_app_test'] = [
            'success' => true,
            'query_executed' => true,
            'events_today' => count($testEvents),
            'sample_events' => $testEvents
        ];
        
    } catch (Exception $e) {
        $results['mobile_app_test'] = [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
    
    // 6. Informazioni sistema
    $results['system_info'] = [
        'php_version' => PHP_VERSION,
        'pdo_available' => class_exists('PDO'),
        'mysql_driver' => in_array('mysql', PDO::getAvailableDrivers()),
        'timezone' => date_default_timezone_get(),
        'current_time' => date('Y-m-d H:i:s'),
        'memory_usage' => memory_get_usage(true),
        'memory_limit' => ini_get('memory_limit')
    ];
    
    // Riepilogo
    $results['summary'] = [
        'database_ok' => $results['database']['connected'],
        'required_tables_exist' => count(array_filter($results['tables'], function($table) { 
            return $table['exists']; 
        })) === count($requiredTables),
        'events_table_ok' => isset($results['eventi_details']) && !isset($results['eventi_details']['error']),
        'mobile_app_query_ok' => $results['mobile_app_test']['success'] ?? false,
        'total_events' => $results['eventi_details']['total_events'] ?? 0
    ];
    
    echo json_encode($results, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
?>