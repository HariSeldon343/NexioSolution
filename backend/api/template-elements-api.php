<?php
/**
 * API per gestione elementi template con supporto multi-azienda
 */

require_once '../config/config.php';
require_once '../models/Template.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

$auth = Auth::getInstance();
$auth->requireAuth();
$user = $auth->getUser();

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore connessione database']);
    exit;
}

$template = new Template($pdo);
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        handleGet($template, $action, $auth);
        break;
    case 'POST':
        handlePost($template, $action, $auth, $user);
        break;
    case 'PUT':
        handlePut($template, $action, $auth);
        break;
    case 'DELETE':
        handleDelete($template, $action, $auth);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Metodo non supportato']);
}

function handleGet($template, $action, $auth) {
    switch ($action) {
        case 'available-templates':
            // Ottiene template disponibili per l'utente corrente
            $user = $auth->getUser();
            $aziende_ids = [];
            
            if ($auth->isSuperAdmin()) {
                // Super admin vede tutti i template
                $stmt = $template->db->query("SELECT id FROM aziende WHERE stato = 'attiva'");
                $aziende_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } else {
                // Altri utenti vedono solo i template della loro azienda
                $aziende_ids = [$user['azienda_id']];
            }
            
            $templates = $template->getAvailableForAziende($aziende_ids);
            echo json_encode(['templates' => $templates]);
            break;
            
        case 'template-details':
            $template_id = $_GET['id'] ?? null;
            if (!$template_id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID template richiesto']);
                return;
            }
            
            $tmpl = $template->getById($template_id);
            if (!$tmpl) {
                http_response_code(404);
                echo json_encode(['error' => 'Template non trovato']);
                return;
            }
            
            // Verifica permessi
            if (!canAccessTemplate($auth, $tmpl)) {
                http_response_code(403);
                echo json_encode(['error' => 'Accesso negato']);
                return;
            }
            
            echo json_encode(['template' => $tmpl]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Azione non valida']);
    }
}

function handlePost($template, $action, $auth, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'update-element':
            $template_id = $input['template_id'] ?? null;
            $section = $input['section'] ?? null; // 'intestazione' o 'pie_pagina'
            $column_index = $input['column_index'] ?? null;
            $row_index = $input['row_index'] ?? null;
            $element_data = $input['element_data'] ?? null;
            
            if (!$template_id || !$section || $column_index === null || $row_index === null || !$element_data) {
                http_response_code(400);
                echo json_encode(['error' => 'Parametri mancanti']);
                return;
            }
            
            $tmpl = $template->getById($template_id);
            if (!$tmpl || !canEditTemplate($auth, $tmpl)) {
                http_response_code(403);
                echo json_encode(['error' => 'Accesso negato']);
                return;
            }
            
            $result = $template->updateElement($template_id, $section, $column_index, $row_index, $element_data);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Elemento aggiornato con successo']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Errore aggiornamento elemento']);
            }
            break;
            
        case 'add-element':
            $template_id = $input['template_id'] ?? null;
            $section = $input['section'] ?? null;
            $column_index = $input['column_index'] ?? null;
            $element_data = $input['element_data'] ?? null;
            
            if (!$template_id || !$section || $column_index === null || !$element_data) {
                http_response_code(400);
                echo json_encode(['error' => 'Parametri mancanti']);
                return;
            }
            
            $tmpl = $template->getById($template_id);
            if (!$tmpl || !canEditTemplate($auth, $tmpl)) {
                http_response_code(403);
                echo json_encode(['error' => 'Accesso negato']);
                return;
            }
            
            // Trova il prossimo indice disponibile
            $config = json_decode($tmpl[$section . '_config'], true) ?: ['columns' => []];
            if (!isset($config['columns'][$column_index])) {
                $config['columns'][$column_index] = ['rows' => []];
            }
            $row_index = count($config['columns'][$column_index]['rows']);
            
            $result = $template->updateElement($template_id, $section, $column_index, $row_index, $element_data);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Elemento aggiunto con successo', 'row_index' => $row_index]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Errore aggiunta elemento']);
            }
            break;
            
        case 'clone-template':
            $template_id = $input['template_id'] ?? null;
            $target_azienda_id = $input['target_azienda_id'] ?? null;
            
            if (!$template_id || !$target_azienda_id) {
                http_response_code(400);
                echo json_encode(['error' => 'Parametri mancanti']);
                return;
            }
            
            if (!$auth->isSuperAdmin()) {
                http_response_code(403);
                echo json_encode(['error' => 'Solo i super admin possono clonare template']);
                return;
            }
            
            $result = $template->cloneForAzienda($template_id, $target_azienda_id, $user['id']);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Template clonato con successo']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Errore clonazione template']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Azione non valida']);
    }
}

function handleDelete($template, $action, $auth) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'remove-element':
            $template_id = $input['template_id'] ?? null;
            $section = $input['section'] ?? null;
            $column_index = $input['column_index'] ?? null;
            $row_index = $input['row_index'] ?? null;
            
            if (!$template_id || !$section || $column_index === null || $row_index === null) {
                http_response_code(400);
                echo json_encode(['error' => 'Parametri mancanti']);
                return;
            }
            
            $tmpl = $template->getById($template_id);
            if (!$tmpl || !canEditTemplate($auth, $tmpl)) {
                http_response_code(403);
                echo json_encode(['error' => 'Accesso negato']);
                return;
            }
            
            $result = $template->removeElement($template_id, $section, $column_index, $row_index);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Elemento rimosso con successo']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Errore rimozione elemento']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Azione non valida']);
    }
}

function canAccessTemplate($auth, $template) {
    if ($auth->isSuperAdmin()) {
        return true;
    }
    
    $user = $auth->getUser();
    return $template['azienda_id'] === null || $template['azienda_id'] == $user['azienda_id'];
}

function canEditTemplate($auth, $template) {
    if ($auth->isSuperAdmin()) {
        return true;
    }
    
    $user = $auth->getUser();
    
    // Può modificare solo se è della sua azienda o se è un template globale e ha permessi admin
    if ($template['azienda_id'] === null) {
        return $auth->hasRoleInAzienda('admin') || $auth->hasRoleInAzienda('proprietario');
    }
    
    return $template['azienda_id'] == $user['azienda_id'] && 
           ($auth->hasRoleInAzienda('admin') || $auth->hasRoleInAzienda('proprietario'));
}