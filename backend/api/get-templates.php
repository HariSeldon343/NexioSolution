<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../middleware/Auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$auth = Auth::getInstance();
$auth->requireAuth();
$user = $auth->getUser();

try {
    // Configurazione database
    $host = 'localhost';
    $dbname = 'piattaforma_collaborativa';
    $username = 'root';
    $password = '';
    
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $aziendaId = $_GET['azienda_id'] ?? null;
    
    // Verifica se le tabelle esistono
    $tablesExist = true;
    $missingTables = [];
    
    try {
        $pdo->query("SELECT 1 FROM moduli_documento LIMIT 1");
    } catch (PDOException $e) {
        $tablesExist = false;
        $missingTables[] = 'moduli_documento';
    }
    
    try {
        $pdo->query("SELECT 1 FROM moduli_template LIMIT 1");
    } catch (PDOException $e) {
        $tablesExist = false;
        $missingTables[] = 'moduli_template';
    }
    
    try {
        $pdo->query("SELECT 1 FROM azienda_moduli LIMIT 1");
    } catch (PDOException $e) {
        $tablesExist = false;
        $missingTables[] = 'azienda_moduli';
    }
    
    if (!$tablesExist) {
        error_log("get-templates: Tabelle mancanti: " . implode(', ', $missingTables));
    }
    
    // Query per ottenere i template accessibili all'utente
    if ($tablesExist) {
        $query = "
            SELECT mt.id, mt.nome, mt.descrizione, mt.header_content, mt.footer_content, 
                   md.nome as modulo_nome, md.icona, md.id as modulo_id
            FROM moduli_template mt
            INNER JOIN moduli_documento md ON mt.modulo_id = md.id
            WHERE mt.attivo = 1 AND md.attivo = 1";
        
        $params = [];
        
        // Se è specificata un'azienda, filtra per i moduli abilitati per quell'azienda
        // Se azienda_id è vuoto e l'utente è super admin, mostra tutti i template
        if ($aziendaId) {
            $query .= " AND EXISTS (
                SELECT 1 FROM azienda_moduli am 
                WHERE am.azienda_id = ? AND am.modulo_id = md.id AND am.attivo = 1
            )";
            $params[] = $aziendaId;
        } else {
            // Per Super Admin senza azienda selezionata: mostra tutti i template
            // Verifica se l'utente è super admin
            if ($user && isset($user['ruolo']) && $user['ruolo'] === 'super_admin') {
                // Nessun filtro aggiuntivo - mostra tutti i template
            } else {
                // Per utenti normali senza azienda: nessun template
                $query .= " AND 1 = 0"; // Forza risultato vuoto
            }
        }
        
        $query .= " ORDER BY md.ordine ASC, mt.nome ASC";
    } else {
        // Fallback se le tabelle non esistono - restituisci template di base
        error_log("get-templates: Usando fallback per tabelle mancanti");
        
        $formattedTemplates = [
            [
                'id' => 1,
                'nome' => 'Template Base',
                'modulo_nome' => 'Template Base',
                'modulo_id' => 1,
                'descrizione' => 'Template di base per documenti',
                'icona' => 'fa-file-alt',
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'id' => 2,
                'nome' => 'Template Formale',
                'modulo_nome' => 'Template Formale',
                'modulo_id' => 2,
                'descrizione' => 'Template per documenti formali',
                'icona' => 'fa-file-word',
                'created_at' => date('Y-m-d H:i:s')
            ]
        ];
        
        echo json_encode($formattedTemplates);
        exit;
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $templates = $stmt->fetchAll();

    // Formatta i dati per l'output
    $formattedTemplates = array_map(function($template) {
        return [
            'id' => $template['id'],
            'nome' => $template['nome'],
            'modulo_nome' => $template['modulo_nome'] ?? $template['nome'],
            'modulo_id' => $template['modulo_id'] ?? null,
            'descrizione' => $template['descrizione'] ?? '',
            'icona' => $template['icona'] ?? 'fa-file-alt'
        ];
    }, $templates);

    echo json_encode($formattedTemplates);

} catch (PDOException $e) {
    error_log("Database error in get-templates: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error', 
        'message' => $e->getMessage(),
        'debug' => true
    ]);
} catch (Exception $e) {
    error_log("Error in get-templates: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error', 
        'message' => $e->getMessage(),
        'debug' => true
    ]);
} 