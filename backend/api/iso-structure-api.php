<?php
/**
 * API per inizializzazione e gestione struttura ISO
 * Crea automaticamente la struttura di cartelle per gli standard selezionati
 * 
 * @package Nexio
 * @version 1.0.0
 */

require_once '../config/config.php';
require_once '../middleware/Auth.php';

// Headers API
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Autenticazione
$auth = Auth::getInstance();
if (!$auth->checkSession()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non autenticato']);
    exit;
}

$user = $auth->getUser();
$userId = $user['id'];
$isSuperAdmin = $auth->isSuperAdmin();
$isUtenteSpeciale = $auth->isUtenteSpeciale();

// Solo super admin e utenti speciali possono configurare ISO
if (!$isSuperAdmin && !$isUtenteSpeciale) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permessi insufficienti']);
    exit;
}

// Gestione azioni
$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

try {
    switch ($action) {
        case 'initialize':
            initializeISOStructure($input);
            break;
        case 'get_config':
            getCompanyConfiguration($input);
            break;
        case 'update_config':
            updateCompanyConfiguration($input);
            break;
        case 'check_compliance':
            checkCompliance($input);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Azione non valida']);
    }
} catch (Exception $e) {
    error_log("Errore API struttura ISO: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore interno del server']);
}

/**
 * Inizializza la struttura ISO per un'azienda
 */
function initializeISOStructure($input) {
    global $userId, $isSuperAdmin, $auth;
    
    // Validazione input  
    $schema = $input['schema'] ?? '';
    $companyId = $input['company_id'] ?? $auth->getCurrentCompany();
    $csrfToken = $input['csrf_token'] ?? '';
    
    // Verifica CSRF token
    if (!isset($_SESSION['csrf_token']) || $csrfToken !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Token CSRF non valido']);
        return;
    }
    
    if (!in_array($schema, ['iso9001', 'iso14001', 'iso45001', 'integrated', 'custom'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Schema non valido']);
        return;
    }
    
    try {
        db_begin_transaction();
        
        // Verifica se esiste già una configurazione
        $stmt = db_query("SELECT id FROM iso_company_configurations WHERE azienda_id = ?", [$companyId]);
        $existingConfig = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingConfig) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Struttura ISO già configurata per questa azienda']);
            db_rollback();
            return;
        }
        
        // Determina structure_type e standards basati sullo schema
        $structure_type = ($schema === 'integrated') ? 'integrated' : 
                         ($schema === 'custom') ? 'custom' : 'separate';
        
        $enabled_standards = [];
        switch ($schema) {
            case 'iso9001':
                $enabled_standards = ['ISO9001'];
                break;
            case 'iso14001':
                $enabled_standards = ['ISO14001'];
                break;
            case 'iso45001':
                $enabled_standards = ['ISO45001'];
                break;
            case 'integrated':
                $enabled_standards = ['ISO9001', 'ISO14001', 'ISO45001'];
                break;
        }
        
        // Crea configurazione in iso_company_configurations
        db_insert('iso_company_configurations', [
            'azienda_id' => $companyId,
            'structure_type' => $structure_type,
            'enabled_standards' => json_encode($enabled_standards),
            'enable_versioning' => 1,
            'enable_approval_workflow' => 1,
            'enable_fulltext_search' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        // Crea anche in company_document_schemas se la tabella esiste
        try {
            db_insert('company_document_schemas', [
                'azienda_id' => $companyId,
                'schema_type' => $schema,
                'schema_config' => json_encode([
                    'name' => getSchemaName($schema),
                    'enabled_standards' => $enabled_standards,
                    'initialized_at' => date('Y-m-d H:i:s')
                ]),
                'created_by' => $userId,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            // La tabella potrebbe non esistere ancora
            error_log("company_document_schemas table might not exist: " . $e->getMessage());
        }
        
        // Crea struttura cartelle basata sullo schema selezionato
        if ($schema !== 'custom') {
            createFolderStructure($companyId, $enabled_standards, $userId);
        }
        
        // Log attività
        if (class_exists('ActivityLogger')) {
            ActivityLogger::getInstance()->log('iso_structure_initialized', 'iso_configuration', $companyId, [
                'schema' => $schema,
                'standards' => $enabled_standards
            ]);
        }
        
        db_commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Struttura ISO inizializzata con successo',
            'schema' => $schema
        ]);
        
    } catch (Exception $e) {
        db_rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Crea la struttura delle cartelle per gli standard selezionati
 */
function createFolderStructure($companyId, $standards, $userId) {
    foreach ($standards as $standard) {
        // Ottieni l'ID dello standard dalla tabella iso_standards
        $stmt = db_query("SELECT id FROM iso_standards WHERE codice = ?", [$standard]);
        $standardData = $stmt->fetch();
        
        if (!$standardData) {
            continue;
        }
        
        $standardId = $standardData['id'];
        
        // Ottieni tutti i template delle cartelle per questo standard
        $stmt = db_query(
            "SELECT * FROM iso_folder_templates 
             WHERE standard_id = ? 
             ORDER BY parent_template_id IS NULL DESC, livello, ordine_visualizzazione",
            [$standardId]
        );
        
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $folderMapping = [];
        
        foreach ($templates as $template) {
            $parentId = null;
            if ($template['parent_template_id'] && isset($folderMapping[$template['parent_template_id']])) {
                $parentId = $folderMapping[$template['parent_template_id']];
            }
            
            // Calcola percorso completo
            $percorsoCompleto = '/';
            if ($parentId) {
                $parentStmt = db_query("SELECT percorso_completo FROM cartelle WHERE id = ?", [$parentId]);
                $parentData = $parentStmt->fetch();
                $percorsoCompleto = $parentData['percorso_completo'] . '/';
            }
            $percorsoCompleto .= $template['nome'];
            
            // Crea cartella nella tabella cartelle
            $folderId = db_insert('cartelle', [
                'nome' => $template['nome'],
                'parent_id' => $parentId,
                'percorso_completo' => $percorsoCompleto,
                'azienda_id' => $companyId,
                'iso_template_id' => $template['id'],
                'iso_standard_codice' => $standard,
                'iso_compliance_level' => $template['obbligatoria'] ? 'obbligatoria' : 'opzionale',
                'iso_metadata' => json_encode([
                    'descrizione' => $template['descrizione'],
                    'icona' => $template['icona'],
                    'colore' => $template['colore']
                ]),
                'data_creazione' => date('Y-m-d H:i:s'),
                'creato_da' => $userId
            ]);
            
            // Mappa template ID a folder ID
            $folderMapping[$template['id']] = $folderId;
        }
    }
}

/**
 * Ottiene il nome descrittivo dello schema
 */
function getSchemaName($schema) {
    $names = [
        'iso9001' => 'ISO 9001:2015 - Sistema di Gestione Qualità',
        'iso14001' => 'ISO 14001:2015 - Sistema di Gestione Ambientale',
        'iso45001' => 'ISO 45001:2018 - Sistema di Gestione SSL',
        'integrated' => 'Sistema Integrato ISO 9001/14001/45001',
        'custom' => 'Struttura Personalizzata'
    ];
    
    return $names[$schema] ?? 'Sistema Documentale';
}

/**
 * Crea cartella root per uno standard
 */
function createStandardRootFolder($companyId, $standard) {
    global $userId;
    
    // Recupera info standard
    $stmt = db_query("SELECT nome FROM iso_standards WHERE codice = ?", [$standard]);
    $standardInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $nomeCartella = $standard . ' - ' . ($standardInfo['nome'] ?? 'Standard');
    
    // Verifica se esiste già
    $stmt = db_query(
        "SELECT id FROM cartelle WHERE nome = ? AND azienda_id = ? AND parent_id IS NULL",
        [$nomeCartella, $companyId]
    );
    
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        return $existing['id'];
    }
    
    // Crea cartella
    return db_insert('cartelle', [
        'nome' => $nomeCartella,
        'parent_id' => null,
        'percorso_completo' => $nomeCartella,
        'azienda_id' => $companyId,
        'iso_standard_codice' => $standard,
        'iso_compliance_level' => 'standard',
        'creato_da' => $userId,
        'data_creazione' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Crea sottocartelle standard per uno standard ISO
 */
function createStandardSubfolders($companyId, $standard, $parentId) {
    global $userId;
    
    $foldersCreated = 0;
    
    // Recupera template cartelle per lo standard
    $stmt = db_query(
        "SELECT * FROM iso_template_cartelle WHERE iso_standard_codice = ? ORDER BY ordine",
        [$standard]
    );
    
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($templates as $template) {
        // Costruisci percorso
        $stmt = db_query("SELECT percorso_completo FROM cartelle WHERE id = ?", [$parentId]);
        $parent = $stmt->fetch(PDO::FETCH_ASSOC);
        $percorsoCompleto = $parent['percorso_completo'] . '/' . $template['nome_cartella'];
        
        // Verifica se esiste già
        $stmt = db_query(
            "SELECT id FROM cartelle WHERE percorso_completo = ? AND azienda_id = ?",
            [$percorsoCompleto, $companyId]
        );
        
        if (!$stmt->fetch()) {
            // Crea cartella
            db_insert('cartelle', [
                'nome' => $template['nome_cartella'],
                'parent_id' => $parentId,
                'percorso_completo' => $percorsoCompleto,
                'azienda_id' => $companyId,
                'iso_standard_codice' => $standard,
                'iso_compliance_level' => 'standard',
                'metadata_iso' => $template['metadata'],
                'creato_da' => $userId,
                'data_creazione' => date('Y-m-d H:i:s')
            ]);
            
            $foldersCreated++;
        }
    }
    
    return $foldersCreated;
}

/**
 * Crea cartella root per sistema integrato
 */
function createIntegratedRootFolder($companyId, $standards) {
    global $userId;
    
    $nomeCartella = 'SGI - Sistema di Gestione Integrato';
    
    // Verifica se esiste già
    $stmt = db_query(
        "SELECT id FROM cartelle WHERE nome = ? AND azienda_id = ? AND parent_id IS NULL",
        [$nomeCartella, $companyId]
    );
    
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        return $existing['id'];
    }
    
    // Crea cartella
    return db_insert('cartelle', [
        'nome' => $nomeCartella,
        'parent_id' => null,
        'percorso_completo' => $nomeCartella,
        'azienda_id' => $companyId,
        'iso_standard_codice' => 'SGI',
        'iso_compliance_level' => 'avanzata',
        'metadata_iso' => json_encode(['standards' => $standards]),
        'creato_da' => $userId,
        'data_creazione' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Crea struttura integrata di cartelle
 */
function createIntegratedSubfolders($companyId, $parentId, $standards) {
    global $userId;
    
    $foldersCreated = 0;
    
    // Definisci struttura integrata comune
    $integratedFolders = [
        '01_Politiche_e_Obiettivi' => 'Politiche aziendali e obiettivi strategici',
        '02_Contesto_e_Leadership' => 'Analisi del contesto e leadership',
        '03_Pianificazione' => 'Pianificazione del sistema integrato',
        '04_Rischi_e_Opportunità' => 'Gestione rischi e opportunità',
        '05_Risorse_e_Competenze' => 'Gestione risorse e competenze',
        '06_Processi_Operativi' => 'Processi operativi principali',
        '07_Controlli_e_Monitoraggio' => 'Controlli operativi e monitoraggio',
        '08_Audit_Interni' => 'Audit interni integrati',
        '09_Non_Conformità' => 'Gestione non conformità',
        '10_Miglioramento_Continuo' => 'Azioni di miglioramento',
        '11_Riesame_Direzione' => 'Riesame della direzione',
        '12_Documentazione_Sistema' => 'Documentazione del sistema'
    ];
    
    foreach ($integratedFolders as $nome => $descrizione) {
        // Costruisci percorso
        $stmt = db_query("SELECT percorso_completo FROM cartelle WHERE id = ?", [$parentId]);
        $parent = $stmt->fetch(PDO::FETCH_ASSOC);
        $percorsoCompleto = $parent['percorso_completo'] . '/' . $nome;
        
        // Verifica se esiste già
        $stmt = db_query(
            "SELECT id FROM cartelle WHERE percorso_completo = ? AND azienda_id = ?",
            [$percorsoCompleto, $companyId]
        );
        
        if (!$stmt->fetch()) {
            // Crea cartella
            $subfolderId = db_insert('cartelle', [
                'nome' => $nome,
                'parent_id' => $parentId,
                'percorso_completo' => $percorsoCompleto,
                'azienda_id' => $companyId,
                'iso_standard_codice' => 'SGI',
                'iso_compliance_level' => 'avanzata',
                'metadata_iso' => json_encode([
                    'descrizione' => $descrizione,
                    'standards' => $standards
                ]),
                'creato_da' => $userId,
                'data_creazione' => date('Y-m-d H:i:s')
            ]);
            
            $foldersCreated++;
            
            // Crea sottocartelle specifiche per standard se necessario
            if (in_array($nome, ['06_Processi_Operativi', '07_Controlli_e_Monitoraggio'])) {
                foreach ($standards as $standard) {
                    $subfolderName = $standard;
                    $subfolderPath = $percorsoCompleto . '/' . $subfolderName;
                    
                    $stmt = db_query(
                        "SELECT id FROM cartelle WHERE percorso_completo = ? AND azienda_id = ?",
                        [$subfolderPath, $companyId]
                    );
                    
                    if (!$stmt->fetch()) {
                        db_insert('cartelle', [
                            'nome' => $subfolderName,
                            'parent_id' => $subfolderId,
                            'percorso_completo' => $subfolderPath,
                            'azienda_id' => $companyId,
                            'iso_standard_codice' => $standard,
                            'iso_compliance_level' => 'avanzata',
                            'creato_da' => $userId,
                            'data_creazione' => date('Y-m-d H:i:s')
                        ]);
                        
                        $foldersCreated++;
                    }
                }
            }
        }
    }
    
    return $foldersCreated;
}

/**
 * Recupera configurazione aziendale
 */
function getCompanyConfiguration($input) {
    global $isSuperAdmin;
    
    $companyId = $input['company_id'] ?? $_SESSION['azienda_id'] ?? null;
    
    if (!$companyId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID azienda mancante']);
        return;
    }
    
    // Verifica permessi
    if (!$isSuperAdmin && $companyId != $_SESSION['azienda_id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Accesso negato']);
        return;
    }
    
    $stmt = db_query("
        SELECT c.*, u.nome as configuratore_nome, u.cognome as configuratore_cognome
        FROM iso_configurazione_azienda c
        LEFT JOIN utenti u ON c.configurato_da = u.id
        WHERE c.azienda_id = ?
    ", [$companyId]);
    
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($config) {
        // Decodifica JSON
        $config['standards_attivi'] = json_decode($config['standards_attivi'], true);
        $config['configurazione_avanzata'] = json_decode($config['configurazione_avanzata'], true);
    }
    
    echo json_encode(['success' => true, 'data' => $config]);
}

/**
 * Aggiorna configurazione aziendale
 */
function updateCompanyConfiguration($input) {
    global $userId, $isSuperAdmin;
    
    $companyId = $input['company_id'] ?? $_SESSION['azienda_id'] ?? null;
    
    if (!$companyId || empty($input['updates'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Parametri mancanti']);
        return;
    }
    
    // Verifica permessi
    if (!$isSuperAdmin && $companyId != $_SESSION['azienda_id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Accesso negato']);
        return;
    }
    
    try {
        $updates = [];
        
        if (isset($input['updates']['stato'])) {
            $updates['stato'] = $input['updates']['stato'];
        }
        
        if (isset($input['updates']['configurazione_avanzata'])) {
            $updates['configurazione_avanzata'] = json_encode($input['updates']['configurazione_avanzata']);
        }
        
        if (!empty($updates)) {
            $updates['ultima_modifica'] = date('Y-m-d H:i:s');
            
            db_update('iso_configurazione_azienda', $updates, 'azienda_id = ?', [$companyId]);
            
            // Log attività
            if (class_exists('ActivityLogger')) {
                ActivityLogger::getInstance()->log('iso_config_updated', 'iso_configurazione', $companyId, $updates);
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'Configurazione aggiornata']);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Verifica conformità struttura
 */
function checkCompliance($input) {
    global $isSuperAdmin;
    
    $companyId = $input['company_id'] ?? $_SESSION['azienda_id'] ?? null;
    $standard = $input['standard'] ?? null;
    
    if (!$companyId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID azienda mancante']);
        return;
    }
    
    // Verifica permessi
    if (!$isSuperAdmin && $companyId != $_SESSION['azienda_id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Accesso negato']);
        return;
    }
    
    $compliance = [
        'total_required' => 0,
        'total_present' => 0,
        'missing_folders' => [],
        'compliance_percentage' => 0
    ];
    
    // Query per verificare cartelle richieste vs presenti
    $query = "
        SELECT 
            t.nome_cartella as required_folder,
            t.iso_standard_codice,
            c.id as folder_id
        FROM iso_template_cartelle t
        LEFT JOIN cartelle c ON c.nome LIKE CONCAT('%', t.nome_cartella, '%')
            AND c.azienda_id = ?
            AND c.iso_standard_codice = t.iso_standard_codice
        WHERE t.obbligatoria = TRUE
    ";
    
    $params = [$companyId];
    
    if ($standard) {
        $query .= " AND t.iso_standard_codice = ?";
        $params[] = $standard;
    }
    
    $stmt = db_query($query, $params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as $row) {
        $compliance['total_required']++;
        
        if ($row['folder_id']) {
            $compliance['total_present']++;
        } else {
            $compliance['missing_folders'][] = [
                'standard' => $row['iso_standard_codice'],
                'folder' => $row['required_folder']
            ];
        }
    }
    
    if ($compliance['total_required'] > 0) {
        $compliance['compliance_percentage'] = round(
            ($compliance['total_present'] / $compliance['total_required']) * 100, 2
        );
    }
    
    echo json_encode(['success' => true, 'data' => $compliance]);
}