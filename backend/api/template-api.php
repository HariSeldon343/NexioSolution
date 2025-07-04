<?php
/**
 * API per gestione template
 */

require_once '../config/config.php';
require_once '../models/Template.php';

// Verifica autenticazione
$auth = Auth::getInstance();
$auth->requireAuth();
$user = $auth->getUser();

// Solo super admin può gestire template
if (!$auth->isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accesso negato']);
    exit;
}

// Connessione database
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore connessione database']);
    exit;
}

$template = new Template($pdo);

// Gestione richieste
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

header('Content-Type: application/json');

switch ($method) {
    case 'POST':
        if ($action === 'create') {
            // Crea nuovo template
            $data = [
                'nome' => $_POST['nome'] ?? '',
                'descrizione' => $_POST['descrizione'] ?? '',
                'azienda_id' => $_POST['azienda_id'] ?: null,
                'intestazione_config' => json_decode($_POST['intestazione_config'] ?? '{"columns":[]}', true),
                'pie_pagina_config' => json_decode($_POST['pie_pagina_config'] ?? '{"columns":[]}', true),
                'stili_css' => $_POST['stili_css'] ?? '',
                'attivo' => $_POST['attivo'] ?? 1,
                'creato_da' => $user['id']
            ];
            
            try {
                $result = $template->create($data);
                echo json_encode(['success' => true, 'message' => 'Template creato con successo']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            
        } elseif ($action === 'update') {
            // Aggiorna template
            $id = $_POST['id'] ?? null;
            if (!$id) {
                echo json_encode(['success' => false, 'error' => 'ID template mancante']);
                exit;
            }
            
            $data = [
                'nome' => $_POST['nome'] ?? '',
                'descrizione' => $_POST['descrizione'] ?? '',
                'azienda_id' => $_POST['azienda_id'] ?: null,
                'intestazione_config' => json_decode($_POST['intestazione_config'] ?? '{"columns":[]}', true),
                'pie_pagina_config' => json_decode($_POST['pie_pagina_config'] ?? '{"columns":[]}', true),
                'stili_css' => $_POST['stili_css'] ?? '',
                'attivo' => $_POST['attivo'] ?? 1
            ];
            
            try {
                $result = $template->update($id, $data);
                echo json_encode(['success' => true, 'message' => 'Template aggiornato con successo']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }
        break;
        
    case 'DELETE':
        // Elimina template
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;
        
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID template mancante']);
            exit;
        }
        
        try {
            // Verifica che il template non sia in uso
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM documenti WHERE template_id = ?");
            $stmt->execute([$id]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                echo json_encode(['success' => false, 'error' => "Impossibile eliminare: ci sono $count documenti che usano questo template"]);
                exit;
            }
            
            $result = $template->delete($id);
            echo json_encode(['success' => true, 'message' => 'Template eliminato con successo']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;
        
    case 'GET':
        if ($action === 'preview') {
            // Anteprima template
            $id = $_GET['id'] ?? null;
            if (!$id) {
                echo json_encode(['success' => false, 'error' => 'ID template mancante']);
                exit;
            }
            
            try {
                $templateData = $template->getById($id);
                if (!$templateData) {
                    echo json_encode(['success' => false, 'error' => 'Template non trovato']);
                    exit;
                }
                
                // Genera HTML di anteprima
                $sampleData = [
                    'titolo' => 'Documento di Esempio',
                    'codice' => 'DOC-2024-001',
                    'autore_nome' => 'Mario Rossi',
                    'azienda_nome' => 'Azienda Esempio S.r.l.',
                    'data_creazione' => date('d/m/Y'),
                    'stato' => 'Bozza'
                ];
                
                $headerHtml = $template->generateHeader($id, $sampleData);
                $footerHtml = $template->generateFooter($id, $sampleData);
                
                echo json_encode([
                    'success' => true,
                    'header' => $headerHtml,
                    'footer' => $footerHtml,
                    'css' => $template->generateCSS($id)
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Metodo non supportato']);
}
?>