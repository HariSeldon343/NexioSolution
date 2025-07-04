<?php
/**
 * API per Template Builder Drag and Drop
 * Gestisce operazioni CRUD per template con interfaccia drag-and-drop
 */

// Pulisci qualsiasi output precedente
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/Template.php';

try {
    $auth = Auth::getInstance();
    $auth->requireAuth();
    $user = $auth->getUser();
    
    // Solo super admin possono gestire i template
    if (!$auth->isSuperAdmin()) {
        throw new Exception('Accesso negato: sono richiesti privilegi di super amministratore');
    }
    
    $pdo = db_connection();
    $template = new Template($pdo);
    
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    switch ($method) {
        case 'GET':
            handleGetRequest($template, $action);
            break;
            
        case 'POST':
            handlePostRequest($template, $action, $user);
            break;
            
        case 'PUT':
            handlePutRequest($template, $action, $user);
            break;
            
        case 'DELETE':
            handleDeleteRequest($template, $action);
            break;
            
        default:
            throw new Exception('Metodo HTTP non supportato');
    }
    
} catch (Exception $e) {
    // Pulisci qualsiasi output precedente prima di inviare l'errore
    ob_clean();
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Gestisce le richieste GET
 */
function handleGetRequest($template, $action) {
    switch ($action) {
        case 'list':
            $templates = $template->getAll();
            echo json_encode([
                'success' => true,
                'data' => $templates
            ]);
            break;
            
        case 'get':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('ID template richiesto');
            }
            
            $templateData = $template->getById($id);
            if (!$templateData) {
                throw new Exception('Template non trovato');
            }
            
            echo json_encode([
                'success' => true,
                'data' => $templateData
            ]);
            break;
            
        case 'preview':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('ID template richiesto');
            }
            
            $previewData = generateTemplatePreview($template, $id);
            echo json_encode([
                'success' => true,
                'data' => $previewData
            ]);
            break;
            
        default:
            throw new Exception('Azione GET non riconosciuta');
    }
}

/**
 * Gestisce le richieste POST
 */
function handlePostRequest($template, $action, $user) {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    
    switch ($action) {
        case 'create':
            $templateData = createTemplateFromInput($input, $user);
            $templateId = $template->create($templateData);
            
            echo json_encode([
                'success' => true,
                'message' => 'Template creato con successo',
                'data' => ['id' => $templateId]
            ]);
            break;
            
        case 'duplicate':
            $sourceId = $input['source_id'] ?? null;
            if (!$sourceId) {
                throw new Exception('ID template sorgente richiesto');
            }
            
            $newTemplateId = duplicateTemplate($template, $sourceId, $user);
            echo json_encode([
                'success' => true,
                'message' => 'Template duplicato con successo',
                'data' => ['id' => $newTemplateId]
            ]);
            break;
            
        case 'validate':
            $validation = validateTemplateConfiguration($input);
            echo json_encode([
                'success' => true,
                'data' => $validation
            ]);
            break;
            
        default:
            throw new Exception('Azione POST non riconosciuta');
    }
}

/**
 * Gestisce le richieste PUT
 */
function handlePutRequest($template, $action, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'update':
            $id = $input['id'] ?? $input['template_id'] ?? null;
            if (!$id) {
                throw new Exception('ID template richiesto');
            }
            
            $templateData = createTemplateFromInput($input, $user);
            $template->update($id, $templateData);
            
            echo json_encode([
                'success' => true,
                'message' => 'Template aggiornato con successo'
            ]);
            break;
            
        case 'toggle-status':
            $id = $input['id'] ?? null;
            if (!$id) {
                throw new Exception('ID template richiesto');
            }
            
            $template->toggleStatus($id);
            echo json_encode([
                'success' => true,
                'message' => 'Stato template aggiornato'
            ]);
            break;
            
        default:
            throw new Exception('Azione PUT non riconosciuta');
    }
}

/**
 * Gestisce le richieste DELETE
 */
function handleDeleteRequest($template, $action) {
    switch ($action) {
        case 'delete':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('ID template richiesto');
            }
            
            $template->delete($id);
            echo json_encode([
                'success' => true,
                'message' => 'Template eliminato con successo'
            ]);
            break;
            
        default:
            throw new Exception('Azione DELETE non riconosciuta');
    }
}

/**
 * Crea i dati del template dall'input
 */
function createTemplateFromInput($input, $user) {
    // Validazione campi obbligatori
    if (empty($input['nome'])) {
        throw new Exception('Nome template richiesto');
    }
    
    // Parsing configurazioni JSON
    $intestazioneConfig = null;
    if (!empty($input['intestazione_config'])) {
        if (is_string($input['intestazione_config'])) {
            $intestazioneConfig = json_decode($input['intestazione_config'], true);
        } else {
            $intestazioneConfig = $input['intestazione_config'];
        }
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Configurazione intestazione non valida: ' . json_last_error_msg());
        }
    }
    
    $piePaginaConfig = null;
    if (!empty($input['pie_pagina_config'])) {
        if (is_string($input['pie_pagina_config'])) {
            $piePaginaConfig = json_decode($input['pie_pagina_config'], true);
        } else {
            $piePaginaConfig = $input['pie_pagina_config'];
        }
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Configurazione piè di pagina non valida: ' . json_last_error_msg());
        }
    }
    
    return [
        'nome' => sanitize_input($input['nome']),
        'descrizione' => sanitize_input($input['descrizione'] ?? ''),
        'azienda_id' => null, // Template globali, associazione gestita in azienda_template
        'tipo_template' => sanitize_input($input['tipo_template'] ?? 'globale'),
        'intestazione_config' => $intestazioneConfig ? json_encode($intestazioneConfig) : null,
        'pie_pagina_config' => $piePaginaConfig ? json_encode($piePaginaConfig) : null,
        'stili_css' => sanitize_input($input['stili_css'] ?? ''),
        'attivo' => !empty($input['attivo']) ? 1 : 0,
        'creato_da' => $user['id']
    ];
}

/**
 * Duplica un template esistente
 */
function duplicateTemplate($template, $sourceId, $user) {
    $sourceTemplate = $template->getById($sourceId);
    if (!$sourceTemplate) {
        throw new Exception('Template sorgente non trovato');
    }
    
    $duplicateData = [
        'nome' => $sourceTemplate['nome'] . ' (Copia)',
        'descrizione' => $sourceTemplate['descrizione'],
        'azienda_id' => $sourceTemplate['azienda_id'],
        'intestazione_config' => $sourceTemplate['intestazione_config'],
        'pie_pagina_config' => $sourceTemplate['pie_pagina_config'],
        'stili_css' => $sourceTemplate['stili_css'],
        'attivo' => 0, // Duplicati iniziano come inattivi
        'creato_da' => $user['id']
    ];
    
    return $template->create($duplicateData);
}

/**
 * Valida la configurazione del template
 */
function validateTemplateConfiguration($input) {
    $errors = [];
    $warnings = [];
    
    // Validazione nome
    if (empty($input['nome'])) {
        $errors[] = 'Nome template richiesto';
    } elseif (strlen($input['nome']) > 255) {
        $errors[] = 'Nome template troppo lungo (max 255 caratteri)';
    }
    
    // Validazione configurazione intestazione
    if (!empty($input['intestazione_config'])) {
        $headerConfig = is_string($input['intestazione_config']) ? 
            json_decode($input['intestazione_config'], true) : 
            $input['intestazione_config'];
            
        if (!$headerConfig) {
            $errors[] = 'Configurazione intestazione non valida';
        } else {
            $headerValidation = validateSectionConfig($headerConfig, 'Intestazione');
            $errors = array_merge($errors, $headerValidation['errors']);
            $warnings = array_merge($warnings, $headerValidation['warnings']);
        }
    }
    
    // Validazione configurazione piè di pagina
    if (!empty($input['pie_pagina_config'])) {
        $footerConfig = is_string($input['pie_pagina_config']) ? 
            json_decode($input['pie_pagina_config'], true) : 
            $input['pie_pagina_config'];
            
        if (!$footerConfig) {
            $errors[] = 'Configurazione piè di pagina non valida';
        } else {
            $footerValidation = validateSectionConfig($footerConfig, 'Piè di pagina');
            $errors = array_merge($errors, $footerValidation['errors']);
            $warnings = array_merge($warnings, $footerValidation['warnings']);
        }
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'warnings' => $warnings
    ];
}

/**
 * Valida la configurazione di una sezione (header/footer)
 */
function validateSectionConfig($config, $sectionName) {
    $errors = [];
    $warnings = [];
    
    if (!isset($config['columns']) || !is_array($config['columns'])) {
        $errors[] = "$sectionName: struttura colonne non valida";
        return ['errors' => $errors, 'warnings' => $warnings];
    }
    
    if (count($config['columns']) > 3) {
        $errors[] = "$sectionName: massimo 3 colonne consentite";
    }
    
    $totalElements = 0;
    foreach ($config['columns'] as $colIndex => $column) {
        if (!isset($column['rows']) || !is_array($column['rows'])) {
            $errors[] = "$sectionName: struttura righe non valida per colonna " . ($colIndex + 1);
            continue;
        }
        
        if (count($column['rows']) > 3) {
            $errors[] = "$sectionName: massimo 3 righe per colonna " . ($colIndex + 1);
        }
        
        foreach ($column['rows'] as $rowIndex => $row) {
            if (!isset($row['elements']) || !is_array($row['elements'])) {
                continue;
            }
            
            $totalElements += count($row['elements']);
            
            foreach ($row['elements'] as $element) {
                if (!isset($element['type'])) {
                    $errors[] = "$sectionName: elemento senza tipo in colonna " . ($colIndex + 1) . ", riga " . ($rowIndex + 1);
                }
            }
        }
    }
    
    if ($totalElements === 0) {
        $warnings[] = "$sectionName vuota";
    } elseif ($totalElements > 20) {
        $warnings[] = "$sectionName: troppi elementi ($totalElements), potrebbe influire sulle performance";
    }
    
    return ['errors' => $errors, 'warnings' => $warnings];
}

/**
 * Genera anteprima del template
 */
function generateTemplatePreview($template, $templateId) {
    $templateData = $template->getById($templateId);
    if (!$templateData) {
        throw new Exception('Template non trovato');
    }
    
    $preview = [
        'header' => generateSectionPreview($templateData['intestazione_config']),
        'footer' => generateSectionPreview($templateData['pie_pagina_config'])
    ];
    
    return $preview;
}

/**
 * Genera anteprima di una sezione
 */
function generateSectionPreview($configJson) {
    if (!$configJson) {
        return ['html' => '<div class="empty-section">Sezione vuota</div>'];
    }
    
    $config = json_decode($configJson, true);
    if (!$config || !isset($config['columns'])) {
        return ['html' => '<div class="empty-section">Configurazione non valida</div>'];
    }
    
    $html = '<div style="display: grid; grid-template-columns: repeat(' . count($config['columns']) . ', 1fr); gap: 12px;">';
    
    foreach ($config['columns'] as $column) {
        $html .= '<div style="display: flex; flex-direction: column; gap: 4px;">';
        
        if (isset($column['rows'])) {
            foreach ($column['rows'] as $row) {
                if (isset($row['elements'])) {
                    foreach ($row['elements'] as $element) {
                        $preview = getElementPreview($element);
                        $html .= '<div style="padding: 4px 8px; background: #f0f0f0; border-radius: 4px; font-size: 12px;">';
                        $html .= htmlspecialchars($preview);
                        $html .= '</div>';
                    }
                }
            }
        }
        
        $html .= '</div>';
    }
    
    $html .= '</div>';
    
    return ['html' => $html];
}

/**
 * Ottiene l'anteprima di un elemento
 */
function getElementPreview($element) {
    $type = $element['type'] ?? 'sconosciuto';
    $content = $element['content'] ?? '';
    
    $previews = [
        'titolo_documento' => '[Nome Documento]',
        'codice_documento' => '[COD-001]',
        'numero_versione' => 'v1.0',
        'data_creazione' => date('d/m/Y'),
        'data_revisione' => date('d/m/Y'),
        'logo' => '[LOGO]',
        'azienda_nome' => '[Nome Azienda]',
        'copyright' => '© ' . date('Y') . ' Azienda',
        'numero_pagine' => 'Pag. 1',
        'testo_libero' => $content ?: '[Testo Personalizzato]',
        'separatore' => '---'
    ];
    
    return $previews[$type] ?? "[Elemento: $type]";
}

/**
 * Sanitizza input utente
 */
function sanitize_input($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
?>