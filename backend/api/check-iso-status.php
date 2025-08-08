<?php
header('Content-Type: application/json');
require_once '../config/config.php';

try {
    $pdo = db_connection();
    
    // Lista delle tabelle ISO che dovrebbero esistere  
    $expectedTables = [
        'iso_standards',
        'iso_folder_templates',
        'iso_company_configurations', 
        'iso_folders',
        'iso_documents'
    ];
    
    // Tabelle che ISOStructureManager cerca
    $managerTables = [
        'aziende_iso_config',
        'aziende_iso_folders', 
        'iso_deployment_log',
        'iso_compliance_check'
    ];
    
    $tableStatus = [];
    
    // Verifica tabelle standard
    foreach ($expectedTables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        $exists = $stmt->fetch();
        $tableStatus['expected'][$table] = (bool)$exists;
    }
    
    // Verifica tabelle manager
    foreach ($managerTables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        $exists = $stmt->fetch();
        $tableStatus['manager'][$table] = (bool)$exists;
    }
    
    // Verifica struttura iso_standards se esiste
    if ($tableStatus['expected']['iso_standards']) {
        $stmt = $pdo->query("DESCRIBE iso_standards");
        $tableStatus['structure']['iso_standards'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    // Verifica struttura iso_folder_templates se esiste  
    if ($tableStatus['expected']['iso_folder_templates']) {
        $stmt = $pdo->query("DESCRIBE iso_folder_templates");
        $tableStatus['structure']['iso_folder_templates'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $tableStatus
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>