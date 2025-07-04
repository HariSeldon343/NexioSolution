<?php
require_once 'backend/config/config.php';

// Inizializza autenticazione
$auth = Auth::getInstance();
$auth->requireAuth();

// Verifica permessi - solo super admin, proprietario e admin possono accedere
if (!$auth->isSuperAdmin() && !$auth->hasRoleInAzienda('proprietario') && !$auth->hasRoleInAzienda('admin')) {
    $_SESSION['error'] = "Accesso non autorizzato alla gestione template";
    redirect(APP_PATH . '/dashboard.php');
}

$db = Database::getInstance();
$user = $auth->getUser();
$currentAzienda = $auth->getCurrentAzienda();

// Gestione salvataggio template
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Disabilita la visualizzazione degli errori per evitare output HTML
    ini_set('display_errors', 0);
    error_reporting(0);
    
    // Buffer di output per catturare eventuali messaggi indesiderati
    ob_start();
    
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'save_template') {
        try {
            // Pulisci il buffer se c'è output indesiderato
            ob_clean();
            
            // Debug: log dei dati ricevuti
            
            $modulo_id = $_POST['modulo_id'] ?? null;
            $template_content = $_POST['template_content'] ?? '';
            $header_content = $_POST['header_content'] ?? '';
            $footer_content = $_POST['footer_content'] ?? '';
            $tipo = $_POST['tipo'] ?? 'word';
            
            
            if (!$modulo_id) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Modulo ID mancante']);
                exit;
            }
            
            // Se template_content è vuoto, usa uno spazio per evitare problemi con il database
            if (empty($template_content)) {
                $template_content = ' ';
            }
            
            // Gestione upload loghi
            $header_logo_path = null;
            $footer_logo_path = null;
            
            if (isset($_FILES['header_logo']) && $_FILES['header_logo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/uploads/logos/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $header_logo_path = 'uploads/logos/header_' . $modulo_id . '_' . time() . '.' . pathinfo($_FILES['header_logo']['name'], PATHINFO_EXTENSION);
                move_uploaded_file($_FILES['header_logo']['tmp_name'], __DIR__ . '/' . $header_logo_path);
            }
            
            if (isset($_FILES['footer_logo']) && $_FILES['footer_logo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/uploads/logos/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $footer_logo_path = 'uploads/logos/footer_' . $modulo_id . '_' . time() . '.' . pathinfo($_FILES['footer_logo']['name'], PATHINFO_EXTENSION);
                move_uploaded_file($_FILES['footer_logo']['tmp_name'], __DIR__ . '/' . $footer_logo_path);
            }
            
            // Verifica se esiste già un template
            $check_query = "SELECT id FROM moduli_template WHERE modulo_id = ?";
            $exists = $db->query($check_query, [$modulo_id])->fetch();
            
            if ($exists) {
                // Aggiorna template esistente
                $update_query = "UPDATE moduli_template SET 
                    contenuto = ?, 
                    tipo = ?, 
                    header_content = ?, 
                    footer_content = ?,
                    logo_header = COALESCE(?, logo_header),
                    logo_footer = COALESCE(?, logo_footer),
                    aggiornato_il = NOW() 
                    WHERE modulo_id = ?";
                $db->query($update_query, [
                    $template_content, 
                    $tipo, 
                    $header_content, 
                    $footer_content,
                    $header_logo_path,
                    $footer_logo_path,
                    $modulo_id
                ]);
            } else {
                // Inserisci nuovo template
                $insert_query = "INSERT INTO moduli_template 
                    (modulo_id, contenuto, tipo, header_content, footer_content, logo_header, logo_footer) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
                $db->query($insert_query, [
                    $modulo_id, 
                    $template_content, 
                    $tipo, 
                    $header_content, 
                    $footer_content,
                    $header_logo_path,
                    $footer_logo_path
                ]);
            }
            
            // Log attività
            if (class_exists('ActivityLogger')) {
                ActivityLogger::log('template_modulo_aggiornato', [
                    'modulo_id' => $modulo_id,
                    'tipo' => $tipo
                ]);
            }
            
            // Pulisci qualsiasi output residuo prima di inviare JSON
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Template salvato con successo']);
            exit;
        } catch (Exception $e) {
            // Pulisci buffer se c'è qualcosa
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Errore nel salvataggio: ' . $e->getMessage()]);
            exit;
        }
    } elseif ($_POST['action'] === 'create_module') {
        // Gestione creazione nuovo modulo
        $nome = trim($_POST['nome'] ?? '');
        $codice = trim($_POST['codice'] ?? '');
        $descrizione = trim($_POST['descrizione'] ?? '');
        $tipo = trim($_POST['tipo'] ?? '');
        $icona = trim($_POST['icona'] ?? 'fas fa-file-alt');
        $attivo = isset($_POST['attivo']) ? 1 : 0;
        $ordine = intval($_POST['ordine'] ?? 0);
        
        // Validazione
        $errors = [];
        if (empty($nome)) {
            $errors[] = "Il nome del modulo è obbligatorio";
        }
        if (empty($codice)) {
            $errors[] = "Il codice del modulo è obbligatorio";
        }
        if (empty($tipo)) {
            $errors[] = "Il tipo del modulo è obbligatorio";
        }
        
        // Se non è stata specificata un'icona, usa quella di default per il tipo
        if ($icona === 'fas fa-file-alt' || empty($icona)) {
            switch ($tipo) {
                case 'word':
                    $icona = 'fas fa-file-word';
                    break;
                case 'excel':
                    $icona = 'fas fa-file-excel';
                    break;
                case 'form':
                    $icona = 'fas fa-wpforms';
                    break;
                default:
                    $icona = 'fas fa-file-alt';
            }
        }
        
        // Verifica codice univoco
        if (!empty($codice)) {
            $stmt = $db->query("SELECT id FROM moduli_documento WHERE codice = ?", [$codice]);
            if ($stmt->fetch()) {
                $errors[] = "Il codice del modulo deve essere univoco";
            }
        }
        
        if (empty($errors)) {
            try {
                $db->insert('moduli_documento', [
                    'nome' => $nome,
                    'codice' => strtoupper(preg_replace('/[^A-Za-z0-9_]/', '_', $codice)),
                    'descrizione' => $descrizione,
                    'tipo' => $tipo,
                    'icona' => $icona,
                    'attivo' => $attivo,
                    'ordine' => $ordine
                ]);
                
                $_SESSION['success'] = "Modulo documento creato con successo!";
                redirect(APP_PATH . '/gestione-moduli-template.php');
            } catch (Exception $e) {
                $_SESSION['error'] = "Errore nella creazione del modulo: " . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = implode('<br>', $errors);
        }
    } elseif ($_POST['action'] === 'delete_module' && isset($_POST['modulo_id'])) {
        // Gestione eliminazione modulo
        $modulo_id = intval($_POST['modulo_id']);
        
        try {
            // Verifica se ci sono documenti associati
            $stmt = $db->query("SELECT COUNT(*) as count FROM documenti WHERE modulo_id = ?", [$modulo_id]);
            $count = $stmt->fetch()['count'];
            
            if ($count > 0) {
                $_SESSION['error'] = "Impossibile eliminare il modulo: ci sono $count documenti associati";
            } else {
                // Elimina prima il template se esiste
                $db->delete('moduli_template', 'modulo_id = ?', [$modulo_id]);
                // Poi elimina il modulo
                $db->delete('moduli_documento', 'id = ?', [$modulo_id]);
                $_SESSION['success'] = "Modulo eliminato con successo!";
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Errore nell'eliminazione del modulo: " . $e->getMessage();
        }
        redirect(APP_PATH . '/gestione-moduli-template.php');
    }
}

// Carica tutti i moduli
$stmt = $db->query("SELECT * FROM moduli_documento WHERE attivo = 1 ORDER BY ordine, nome");
$moduli = $stmt->fetchAll();

// Carica i template esistenti
$templates = [];
$stmt = $db->query("SELECT * FROM moduli_template");
while ($template = $stmt->fetch()) {
    $templates[$template['modulo_id']] = $template;
}

$pageTitle = 'Gestione Template Documenti';
require_once 'components/header.php';
?>

<!-- CKEditor 5 -->
<link rel="stylesheet" href="https://cdn.ckeditor.com/ckeditor5/43.3.1/ckeditor5.css">
<script src="https://cdn.ckeditor.com/ckeditor5/43.3.1/ckeditor5.umd.js"></script>

<style>
    /* Override per dimensioni caratteri più piccole */
    body {
        font-size: 0.875rem; /* 14px invece di 16px */
    }
    
    h1 {
        font-size: 1.5rem !important; /* 24px invece di più grande */
    }
    
    h2 {
        font-size: 1.25rem !important; /* 20px */
    }
    
    h3 {
        font-size: 1.125rem !important; /* 18px */
    }
    
    .moduli-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .modulo-card {
        background: var(--bg-secondary);
        border: 2px solid var(--border-color);
        border-radius: 8px;
        padding: 1rem;
        cursor: pointer;
        transition: all 0.3s ease;
        text-align: center;
        position: relative;
    }
    
    .modulo-card:hover {
        border-color: var(--primary-color);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    
    .modulo-card.active {
        background: #e8f0fe;
        border-color: var(--primary-color);
    }
    
    .modulo-icon {
        font-size: 2rem; /* Ridotto da 3rem */
        margin-bottom: 0.5rem;
        display: block;
    }
    
    .modulo-name {
        font-size: 0.9375rem; /* 15px invece di 18px */
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }
    
    .modulo-code {
        font-size: 0.75rem; /* 12px invece di 14px */
        color: var(--text-secondary);
        font-family: monospace;
        background: rgba(212, 165, 116, 0.1);
        padding: 0.125rem 0.375rem;
        border-radius: 4px;
        display: inline-block;
    }
    
    /* Template editor */
    .template-container {
        background: var(--bg-secondary);
        border-radius: 8px;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        border: 1px solid var(--border-color);
    }
    
    .template-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid var(--border-color);
    }
    
    .template-title {
        font-size: 1rem; /* 16px invece di 20px */
        font-weight: 600;
        color: var(--text-primary);
    }
    
    /* Template type selector */
    .template-type-selector {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1rem;
        padding: 0.75rem;
        background: var(--bg-primary);
        border-radius: 6px;
    }
    
    .type-btn {
        flex: 1;
        padding: 0.5rem 1rem;
        border: 2px solid var(--border-color);
        background: white;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s;
        text-align: center;
        font-size: 0.875rem;
    }
    
    .type-btn:hover {
        border-color: var(--primary-color);
        background: var(--bg-primary);
    }
    
    .type-btn.active {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
    
    .type-btn i {
        display: block;
        font-size: 1.5rem;
        margin-bottom: 0.25rem;
    }
    
    /* Editor sections */
    .editor-section {
        margin-bottom: 1rem;
    }
    
    .editor-section h4 {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .template-editor {
        width: 100%;
        min-height: 300px;
        padding: 0.75rem;
        border: 2px solid var(--border-color);
        border-radius: 6px;
        font-family: monospace;
        font-size: 0.8125rem; /* 13px */
        resize: vertical;
        background: #f9f9f9;
    }
    
    .header-footer-editor {
        width: 100%;
        min-height: 100px;
        padding: 0.75rem;
        border: 2px solid var(--border-color);
        border-radius: 6px;
        font-family: monospace;
        font-size: 0.8125rem;
        resize: vertical;
        background: #f9f9f9;
    }
    
    .template-editor:focus,
    .header-footer-editor:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(212, 165, 116, 0.1);
    }
    
    /* Placeholders info */
    .placeholders-info {
        background: rgba(212, 165, 116, 0.1);
        border: 1px solid var(--primary-light);
        border-radius: 6px;
        padding: 0.75rem;
        margin-top: 0.75rem;
        font-size: 0.8125rem;
    }
    
    .placeholders-info h4 {
        color: var(--primary-dark);
        margin-bottom: 0.375rem;
        font-size: 0.875rem;
    }
    
    .placeholder-list {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 0.375rem;
        font-size: 0.75rem;
    }
    
    .placeholder-item {
        font-family: monospace;
        background: white;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
    }
    
    /* Template preview */
    .template-preview {
        border: 1px solid var(--border-color);
        border-radius: 6px;
        padding: 1rem;
        background: white;
        margin-top: 1rem;
        max-height: 400px;
        overflow-y: auto;
    }
    
    /* Logo upload */
    .logo-upload {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 0.5rem;
    }
    
    .logo-preview {
        width: 80px;
        height: 80px;
        border: 2px dashed var(--border-color);
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    
    .logo-preview img {
        max-width: 100%;
        max-height: 100%;
    }
    
    /* Tabs per template types */
    .template-tabs {
        display: none;
        margin-top: 1rem;
    }
    
    .template-tabs.active {
        display: block;
    }
    
    /* Buttons sizing */
    .btn {
        font-size: 0.875rem;
        padding: 0.5rem 1rem;
    }
    
    .btn-small {
        font-size: 0.75rem;
        padding: 0.375rem 0.75rem;
    }
    
    /* Aggiungi alla sezione style esistente */
    .template-status {
        display: inline-block;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 500;
    }
    
    .template-status.configured {
        background: #d1fae5;
        color: #065f46;
    }
    
    .template-status.pending {
        background: #fee2e2;
        color: #991b1b;
    }
    
    /* Stili per il pulsante elimina modulo */
    .delete-module-btn {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        background: #fee2e2;
        color: #dc2626;
        border: none;
        border-radius: 6px;
        padding: 0.375rem 0.625rem;
        font-size: 0.75rem;
        cursor: pointer;
        opacity: 0;
        transition: all 0.2s;
        z-index: 10;
    }
    
    .modulo-card:hover .delete-module-btn {
        opacity: 1;
    }
    
    .delete-module-btn:hover {
        background: #fecaca;
        color: #b91c1c;
    }
    
    /* Form styles */
    .form-container h2 {
        color: var(--text-primary);
        font-size: 1.25rem;
        margin-bottom: 1.5rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid var(--primary-light);
    }
    
    .form-row {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
        margin-bottom: 1rem;
    }
    
    .form-group {
        margin-bottom: 1rem;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 0.375rem;
        color: var(--text-primary);
        font-weight: 600;
        font-size: 0.875rem;
    }
    
    .form-control {
        width: 100%;
        padding: 0.625rem 0.875rem;
        border: 2px solid var(--border-color);
        border-radius: 6px;
        font-size: 0.875rem;
        transition: all 0.2s;
        background: var(--bg-secondary);
    }
    
    .form-control:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(43, 87, 154, 0.1);
    }
    
    .form-text {
        display: block;
        margin-top: 0.25rem;
        font-size: 0.75rem;
        color: var(--text-secondary);
    }
    
    .form-actions {
        display: flex;
        gap: 1rem;
        margin-top: 1.5rem;
    }
    
    .required {
        color: #dc2626;
    }
    
    /* Editor tabs */
    .editor-tabs {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1rem;
        border-bottom: 2px solid #e5e7eb;
    }
    
    .editor-tab {
        background: none;
        border: none;
        padding: 0.75rem 1.5rem;
        cursor: pointer;
        color: #6b7280;
        font-size: 0.875rem;
        font-weight: 500;
        position: relative;
        transition: all 0.3s;
    }
    
    .editor-tab:hover {
        color: var(--primary-color);
    }
    
    .editor-tab.active {
        color: var(--primary-color);
    }
    
    .editor-tab.active::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        right: 0;
        height: 2px;
        background: var(--primary-color);
    }
    
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
    }
    
    .editor-toolbar {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 0.75rem;
        padding: 0.5rem;
        background: #f9fafb;
        border-radius: 6px;
        flex-wrap: wrap;
    }
    
    .btn-tool {
        background: white;
        border: 1px solid #e5e7eb;
        padding: 0.375rem 0.75rem;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.813rem;
        color: #374151;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }
    
    .btn-tool:hover {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
    
    .template-preview {
        margin-top: 1rem;
        padding: 1rem;
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        min-height: 100px;
    }
    
    .placeholder-item {
        padding: 0.375rem;
        background: #f3f4f6;
        border-radius: 4px;
        font-size: 0.813rem;
        margin-bottom: 0.375rem;
    }
    
    .placeholder-item code {
        background: #1b3f76;
        color: white;
        padding: 0.125rem 0.375rem;
        border-radius: 3px;
        font-size: 0.75rem;
    }
    
    .template-editor-section {
        background: white;
        padding: 1.5rem;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    /* Stili per Editor Migliorato */
    .header-footer-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
        margin-top: 1rem;
    }
    
    .editor-column {
        background: #f9fafb;
        border-radius: 8px;
        padding: 1rem;
        border: 1px solid #e5e7eb;
    }
    
    .editor-column h5 {
        margin: 0 0 1rem 0;
        color: var(--text-primary);
        font-size: 0.9375rem;
        font-weight: 600;
    }
    
    /* Template predefiniti */
    .template-presets {
        display: grid;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }
    
    .preset-card {
        background: white;
        border: 2px solid transparent;
        border-radius: 8px;
        padding: 1rem;
        cursor: pointer;
        transition: all 0.2s;
        position: relative;
    }
    
    .preset-card:hover {
        border-color: var(--primary-color);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .preset-card.selected {
        border-color: var(--primary-color);
        background: rgba(212, 165, 116, 0.05);
    }
    
    .preset-preview {
        background: #f3f4f6;
        border-radius: 4px;
        padding: 0.75rem;
        margin-bottom: 0.5rem;
        font-size: 0.75rem;
        line-height: 1.4;
        height: 80px;
        overflow: hidden;
        position: relative;
    }
    
    .preset-preview::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 20px;
        background: linear-gradient(to bottom, rgba(243,244,246,0), rgba(243,244,246,1));
    }
    
    .preset-name {
        font-weight: 600;
        color: var(--text-primary);
        font-size: 0.875rem;
    }
    
    /* Editor semplificato */
    .simple-editor {
        background: white;
        border: 2px solid var(--border-color);
        border-radius: 6px;
        overflow: hidden;
    }
    
    .simple-toolbar {
        background: #f3f4f6;
        border-bottom: 1px solid var(--border-color);
        padding: 0.5rem;
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    
    .toolbar-btn {
        background: white;
        border: 1px solid #e5e7eb;
        padding: 0.5rem;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.875rem;
        color: #374151;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }
    
    .toolbar-btn:hover {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
    
    .editor-content {
        min-height: 120px;
        padding: 1rem;
        font-family: Arial, sans-serif;
        font-size: 14px;
        line-height: 1.6;
        color: #333;
        outline: none;
    }
    
    .editor-content:focus {
        background: #fafafa;
    }
    
    /* Quick insert buttons */
    .quick-insert {
        display: flex;
        gap: 0.5rem;
        margin-top: 0.5rem;
        flex-wrap: wrap;
    }
    
    .quick-insert-btn {
        background: #e0f2fe;
        color: #0284c7;
        border: 1px solid #7dd3fc;
        padding: 0.375rem 0.75rem;
        border-radius: 6px;
        font-size: 0.75rem;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .quick-insert-btn:hover {
        background: #0284c7;
        color: white;
        border-color: #0284c7;
    }
    
    /* Preview migliorata */
    .live-preview {
        background: white;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        padding: 1.5rem;
        margin-top: 1rem;
        min-height: 150px;
        box-shadow: inset 0 0 10px rgba(0,0,0,0.03);
    }
    
    .preview-label {
        font-size: 0.75rem;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.5rem;
    }
    
    /* Logo section migliorata */
    .logo-section {
        background: #f9fafb;
        border-radius: 6px;
        padding: 1rem;
        margin-bottom: 1rem;
    }
    
    .logo-upload-area {
        border: 2px dashed #cbd5e0;
        border-radius: 6px;
        padding: 1.5rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .logo-upload-area:hover {
        border-color: var(--primary-color);
        background: rgba(212, 165, 116, 0.05);
    }
    
    .logo-upload-area.has-logo {
        padding: 0.5rem;
    }
    
    .logo-upload-area img {
        max-height: 60px;
        max-width: 100%;
    }
    
    /* CKEditor 5 styles */
    .ck-editor {
        border-radius: 8px !important;
        overflow: hidden;
    }
    
    .ck-editor__main {
        min-height: 400px;
    }
    
    .ck-editor__main .ck-content {
        min-height: 400px;
        font-family: Arial, sans-serif;
        font-size: 14px;
        line-height: 1.6;
    }
    
    /* Header/Footer editors */
    .header-footer-editor .ck-editor__main,
    .header-footer-editor .ck-editor__main .ck-content {
        min-height: 200px !important;
    }
    
    /* Template builder tools */
    .template-builder {
        background: #f8fafc;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1rem;
    }
    
    .builder-tools {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 0.75rem;
        margin-bottom: 1rem;
    }
    
    .builder-tool {
        background: white;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        padding: 1rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .builder-tool:hover {
        border-color: var(--primary-color);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .builder-tool i {
        font-size: 2rem;
        color: var(--primary-color);
        margin-bottom: 0.5rem;
        display: block;
    }
    
    .builder-tool span {
        font-size: 0.875rem;
        color: var(--text-primary);
        font-weight: 500;
    }
    
    /* Variable inserter */
    .variable-inserter {
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 1rem;
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        margin-top: 0.5rem;
        z-index: 1000;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        display: none;
    }
    
    .variable-inserter.active {
        display: block;
    }
    
    .variable-category {
        margin-bottom: 1rem;
    }
    
    .variable-category h5 {
        font-size: 0.875rem;
        color: var(--text-secondary);
        margin-bottom: 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .variable-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 0.5rem;
    }
    
    .variable-item {
        padding: 0.5rem 0.75rem;
        background: #f3f4f6;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 0.813rem;
    }
    
    .variable-item:hover {
        background: rgba(43, 87, 154, 0.05);
        border-color: var(--primary-color);
    }
    
    .variable-item code {
        font-size: 0.75rem;
        opacity: 0.8;
    }
    
    /* Header/Footer Visual Editor Styles */
    .visual-editor-container {
        background: white;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1rem;
    }
    
    .a4-preview {
        width: 100%;
        max-width: 794px; /* A4 width at 96 DPI */
        margin: 0 auto;
        background: white;
        box-shadow: 0 0 20px rgba(0,0,0,0.1);
        border: 1px solid #ddd;
    }
    
    .header-footer-area {
        min-height: 120px;
        background: #fafafa;
        border: 2px dashed #cbd5e0;
        position: relative;
        padding: 1rem;
    }
    
    .header-area {
        border-bottom: 2px solid #e5e7eb;
    }
    
    .footer-area {
        border-top: 2px solid #e5e7eb;
    }
    
    /* Grid Layout System */
    .layout-grid {
        display: grid;
        gap: 0.5rem;
        height: 100%;
        min-height: 100px;
    }
    
    .grid-1-col { grid-template-columns: 1fr; }
    .grid-2-col { grid-template-columns: 1fr 1fr; }
    .grid-3-col { grid-template-columns: 1fr 1fr 1fr; }
    .grid-2-1-col { grid-template-columns: 2fr 1fr; }
    .grid-1-2-col { grid-template-columns: 1fr 2fr; }
    
    .grid-cell {
        border: 1px dashed #e5e7eb;
        padding: 0.5rem;
        min-height: 50px;
        position: relative;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    /* Multi-row cells */
    .cell-rows {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        height: 100%;
    }
    
    .cell-row {
        flex: 1;
        border: 1px dashed #e5e7eb;
        padding: 0.375rem;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
        min-height: 40px;
    }
    
    .cell-row:hover {
        background: #f0f9ff;
        border-color: #3b82f6;
    }
    
    .cell-row.active {
        background: #eff6ff;
        border: 2px solid #3b82f6;
    }
    
    .cell-row.has-content {
        cursor: default;
    }
    
    .grid-cell:hover {
        background: #f0f9ff;
        border-color: #3b82f6;
    }
    
    .grid-cell.active {
        background: #eff6ff;
        border: 2px solid #3b82f6;
    }
    
    .grid-cell.has-content {
        cursor: default;
    }
    
    /* Row controls */
    .row-controls {
        position: absolute;
        top: 5px;
        right: 5px;
        display: none;
        gap: 0.25rem;
        z-index: 10;
    }
    
    .grid-cell:hover .row-controls {
        display: flex;
    }
    
    .row-btn {
        width: 24px;
        height: 24px;
        border: 1px solid #e5e7eb;
        background: white;
        border-radius: 4px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        color: #6b7280;
    }
    
    .row-btn:hover {
        background: #3b82f6;
        color: white;
        border-color: #3b82f6;
    }
    
    /* Document title and code elements */
    .doc-title-element,
    .doc-code-element {
        background: #fef3c7;
        border: 1px solid #fbbf24;
        padding: 0.5rem;
        border-radius: 4px;
        font-weight: 600;
    }
    
    .doc-title-element::before {
        content: "📝 ";
    }
    
    .doc-code-element::before {
        content: "🔢 ";
    }
    
    /* Layout Templates Styles */
    .layout-section {
        margin-bottom: 1.5rem;
    }
    
    .layout-templates {
        display: flex;
        gap: 0.75rem;
        margin-top: 0.5rem;
    }
    
    .layout-template {
        flex: 1;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        padding: 0.75rem;
        cursor: pointer;
        background: white;
        transition: all 0.2s;
        min-height: 60px;
        display: flex;
        gap: 0.25rem;
        align-items: center;
        justify-content: center;
    }
    
    .layout-template > div {
        background: #e5e7eb;
        height: 40px;
        border-radius: 4px;
    }
    
    .layout-template:hover {
        border-color: var(--primary-color);
        transform: translateY(-2px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .layout-template.active {
        border-color: var(--primary-color);
        background: rgba(212, 165, 116, 0.1);
    }
    
    /* Element Toolbox Styles */
    .element-toolbox {
        margin-bottom: 1.5rem;
    }
    
    .element-buttons {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 0.75rem;
        margin-top: 0.5rem;
    }
    
    .element-btn {
        background: white;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        padding: 0.75rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.375rem;
    }
    
    .element-btn:hover {
        border-color: var(--primary-color);
        background: rgba(212, 165, 116, 0.05);
        transform: translateY(-2px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .element-btn i {
        font-size: 1.5rem;
        color: var(--primary-color);
    }
    
    .element-btn span {
        font-size: 0.75rem;
        color: var(--text-primary);
        font-weight: 500;
    }
    
    /* Content Element Styles */
    .content-element {
        position: relative;
        padding: 0.5rem;
    }
    
    .content-element:hover .element-controls {
        opacity: 1;
    }
    
    .element-controls {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        display: flex;
        gap: 0.25rem;
        opacity: 0;
        transition: opacity 0.2s;
        background: rgba(43, 87, 154, 0.05);
        padding: 0.25rem;
        border-radius: 0.5rem;
    }
    
    .control-btn {
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 4px;
        padding: 0.25rem 0.5rem;
        cursor: pointer;
        font-size: 0.75rem;
        color: #6b7280;
        transition: all 0.2s;
    }
    
    .control-btn:hover {
        background: #f3f4f6;
        border-color: #9ca3af;
    }
    
    .control-btn.delete:hover {
        background: #fee2e2;
        color: #dc2626;
        border-color: #fecaca;
    }
    
    /* Text Editor Popup Styles */
    .popup-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9998;
        display: none;
    }
    
    .popup-overlay.active {
        display: block;
    }
    
    .text-editor-popup {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        width: 90%;
        max-width: 600px;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
        z-index: 9999;
        display: none;
    }
    
    .text-editor-popup.active {
        display: block;
    }
    
    .alignment-buttons {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }
    
    .align-btn {
        background: white;
        border: 2px solid #e5e7eb;
        border-radius: 6px;
        padding: 0.5rem 0.75rem;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .align-btn:hover {
        background: #f3f4f6;
        border-color: #9ca3af;
    }
    
    .align-btn.active {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
    
    /* Image Upload Zone */
    .image-upload-zone {
        border: 2px dashed #cbd5e0;
        border-radius: 8px;
        padding: 2rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
        background: #f9fafb;
    }
    
    .image-upload-zone:hover {
        border-color: var(--primary-color);
        background: rgba(212, 165, 116, 0.05);
    }
    
    .image-upload-zone i {
        font-size: 3rem;
        color: #9ca3af;
        margin-bottom: 0.5rem;
        display: block;
    }
    
    .image-upload-zone p {
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }
    
    .image-upload-zone small {
        color: var(--text-secondary);
        font-size: 0.75rem;
    }
    
    /* Preview Document Styles */
    .preview-document {
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 2rem;
        margin-top: 2rem;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    }
    
    .preview-header,
    .preview-footer {
        background: #f9fafb;
        padding: 1rem;
        border-radius: 6px;
        margin-bottom: 1rem;
        min-height: 60px;
    }
    
    .preview-body {
        padding: 2rem 1rem;
        min-height: 200px;
        border: 1px dashed #e5e7eb;
        border-radius: 6px;
        margin-bottom: 1rem;
    }
    
    .preview-page-info {
        text-align: center;
        color: #6b7280;
        font-size: 0.875rem;
        padding-top: 1rem;
        border-top: 1px solid #e5e7eb;
    }
</style>

<div class="content-header">
    <h1><i class="fas fa-file-code" style="font-size: 1.25rem;"></i> Gestione Template Documenti</h1>
    <div>
        <?php if ($auth->isSuperAdmin()): ?>
        <button type="button" class="btn btn-primary" onclick="showCreateModuleForm()">
            <i class="fas fa-plus"></i> Nuovo Modulo
        </button>
        <?php endif; ?>
        <a href="<?php echo APP_PATH; ?>/dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Torna alla Dashboard
        </a>
    </div>
</div>

<?php if (isset($_SESSION['success'])): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
<div class="alert alert-error">
    <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
</div>
<?php endif; ?>

<!-- Form per creare nuovo modulo (nascosto di default) -->
<div id="create-module-form" style="display: none;">
    <div class="form-container" style="margin-bottom: 2rem;">
        <h2><i class="fas fa-plus-circle"></i> Crea Nuovo Modulo Documento</h2>
        <form method="post" action="">
            <input type="hidden" name="action" value="create_module">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="nome">Nome del Modulo <span class="required">*</span></label>
                    <input type="text" id="nome" name="nome" class="form-control" required 
                           placeholder="es. Contratto di Servizio">
                </div>
                
                <div class="form-group">
                    <label for="codice">Codice Univoco <span class="required">*</span></label>
                    <input type="text" id="codice" name="codice" class="form-control" required 
                           placeholder="es. CONTR_SERV" pattern="[A-Za-z0-9_]+"
                           title="Solo lettere, numeri e underscore">
                    <small class="form-text">Sarà convertito in maiuscolo automaticamente</small>
                </div>
            </div>
            
            <div class="form-group">
                <label for="descrizione">Descrizione</label>
                <textarea id="descrizione" name="descrizione" class="form-control" rows="2"
                          placeholder="Breve descrizione del modulo"></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="tipo">Tipo di Modulo <span class="required">*</span></label>
                    <select id="tipo" name="tipo" class="form-control" required>
                        <option value="">-- Seleziona Tipo --</option>
                        <option value="word">📄 Documento Word</option>
                        <option value="excel">📊 Foglio Excel</option>
                        <option value="form">📋 Modulo Form</option>
                    </select>
                    <small class="form-text">Il tipo determina come verrà visualizzato e gestito il template</small>
                </div>
                
                <div class="form-group">
                    <label for="icona">Icona</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <select id="icona_type" class="form-control" style="width: auto;" onchange="toggleIconInput()">
                            <option value="fa">Font Awesome</option>
                            <option value="emoji">Emoji</option>
                        </select>
                        <input type="text" id="icona" name="icona" class="form-control" 
                               value="fas fa-file-alt" placeholder="es. fas fa-file-contract">
                    </div>
                    <small class="form-text">
                        <span id="icon-help-fa">Classe Font Awesome (es. fas fa-file-contract)</span>
                        <span id="icon-help-emoji" style="display: none;">Inserisci un emoji</span>
                    </small>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="ordine">Ordine di visualizzazione</label>
                    <input type="number" id="ordine" name="ordine" class="form-control" 
                           value="0" min="0">
                    <small class="form-text">0 = ordine alfabetico</small>
                </div>
                
                <div class="form-group">
                    <label style="margin-top: 2rem;">
                        <input type="checkbox" name="attivo" value="1" checked>
                        Modulo attivo
                    </label>
                </div>
            </div>
            
            <div class="form-actions" style="margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Crea Modulo
                </button>
                <button type="button" class="btn btn-secondary" onclick="hideCreateModuleForm()">
                    Annulla
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (empty($moduli)): ?>
    <div class="empty-state">
        <i class="fas fa-folder-open" style="font-size: 3rem;"></i>
        <h2 style="font-size: 1.25rem;">Nessun modulo documento disponibile</h2>
        <p style="font-size: 0.875rem;">I moduli documento devono essere creati prima di poter gestire i template.</p>
    </div>
<?php else: ?>
    <div class="moduli-grid">
        <?php foreach ($moduli as $modulo): ?>
        <div class="modulo-card" onclick="selectModulo(<?php echo $modulo['id']; ?>)" 
             id="modulo-<?php echo $modulo['id']; ?>"
             data-nome="<?php echo htmlspecialchars($modulo['nome']); ?>"
             data-tipo="<?php echo htmlspecialchars($modulo['tipo'] ?? 'word'); ?>">
            <?php if ($auth->isSuperAdmin()): ?>
            <button class="delete-module-btn" onclick="event.stopPropagation(); deleteModule(<?php echo $modulo['id']; ?>, '<?php echo htmlspecialchars($modulo['nome']); ?>')" 
                    title="Elimina modulo">
                <i class="fas fa-trash"></i>
            </button>
            <?php endif; ?>
            <div class="modulo-icon">
                <?php if (!empty($modulo['icona']) && strpos($modulo['icona'], 'fa-') !== false): ?>
                    <i class="fas <?php echo $modulo['icona']; ?>"></i>
                <?php else: ?>
                    <?php echo $modulo['icona'] ?? ''; ?>
                <?php endif; ?>
            </div>
            <div class="modulo-name"><?php echo htmlspecialchars($modulo['nome']); ?></div>
            <div class="modulo-code"><?php echo htmlspecialchars($modulo['codice']); ?></div>
            
            <!-- Mostra il tipo di modulo -->
            <div style="margin-top: 0.5rem;">
                <?php 
                $tipoLabels = [
                    'word' => ['label' => 'Documento Word', 'icon' => 'fas fa-file-word', 'color' => '#1b3f76'],
                    'excel' => ['label' => 'Foglio Excel', 'icon' => 'fas fa-file-excel', 'color' => '#217346'],
                    'form' => ['label' => 'Modulo Form', 'icon' => 'fas fa-wpforms', 'color' => '#9c27b0']
                ];
                $tipo = $modulo['tipo'] ?? 'word';
                $tipoInfo = $tipoLabels[$tipo] ?? $tipoLabels['word'];
                ?>
                <span style="background: <?php echo $tipoInfo['color']; ?>; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 0.25rem;">
                    <i class="<?php echo $tipoInfo['icon']; ?>"></i>
                    <?php echo $tipoInfo['label']; ?>
                </span>
            </div>
            
            <?php if (isset($templates[$modulo['id']])): ?>
                <?php 
                $template = $templates[$modulo['id']];
                $hasHeader = !empty($template['header_content']);
                $hasFooter = !empty($template['footer_content']);
                ?>
                <div style="margin-top: 0.375rem; font-size: 0.75rem;">
                    <span style="color: var(--primary-color);">
                        <i class="fas fa-check-circle"></i> Template configurato
                    </span>
                    <?php if ($hasHeader || $hasFooter): ?>
                        <div style="margin-top: 0.25rem;">
                            <?php if ($hasHeader): ?>
                                <span style="color: #059669; margin-right: 0.5rem;">
                                    <i class="fas fa-heading"></i> Header
                                </span>
                            <?php endif; ?>
                            <?php if ($hasFooter): ?>
                                <span style="color: #059669;">
                                    <i class="fas fa-align-center"></i> Footer
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    
    <div id="template-editor-container" style="display: none;">
        <div class="template-container">
            <div class="template-header">
                <h3 class="template-title">
                    <span id="selected-modulo-name"></span>
                </h3>
                <div>
                    <button type="button" class="btn btn-secondary" onclick="cancelEdit()">
                        <i class="fas fa-times"></i> Annulla
                    </button>
                    <button type="button" class="btn btn-primary" onclick="saveTemplate()">
                        <i class="fas fa-save"></i> Salva Template
                    </button>
                </div>
            </div>
            
            <form id="template-form" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_template">
                <input type="hidden" name="modulo_id" id="modulo_id">
                <input type="hidden" name="template_content" id="template_content" value=" ">
                
                <!-- Template Type Selector -->
                <div class="template-type-selector">
                    <button type="button" class="type-btn active" onclick="switchTemplateType('word')" data-type="word">
                        <i class="fas fa-file-word"></i>
                        <span>Documento Word</span>
                    </button>
                    <button type="button" class="type-btn" onclick="switchTemplateType('excel')" data-type="excel">
                        <i class="fas fa-file-excel"></i>
                        <span>Foglio Excel</span>
                    </button>
                    <button type="button" class="type-btn" onclick="switchTemplateType('form')" data-type="form">
                        <i class="fas fa-wpforms"></i>
                        <span>Modulo Form</span>
                    </button>
                </div>
                
                <input type="hidden" name="template_type" id="template_type" value="word">
                
                <!-- Editor del Template -->
                <div class="template-editor-section">
                    <div class="editor-tabs">
                        <button type="button" class="editor-tab active" onclick="switchTab('header')">
                            <i class="fas fa-heading"></i> Intestazione
                        </button>
                        <button type="button" class="editor-tab" onclick="switchTab('footer')">
                            <i class="fas fa-align-center"></i> Piè di pagina
                        </button>
                    </div>
                    
                    <!-- Header -->
                    <div id="header-tab" class="tab-content active">
                        <div class="visual-editor-container">
                            <h4 style="margin-bottom: 1rem;"><i class="fas fa-heading"></i> Editor Visuale Intestazione</h4>
                            
                            <!-- Layout Templates -->
                            <div class="layout-section">
                                <label style="font-weight: 600; margin-bottom: 0.5rem; display: block;">
                                    <i class="fas fa-th"></i> Scegli Layout
                                </label>
                                <div class="layout-templates">
                                    <div class="layout-template active" onclick="setHeaderLayout('1-col')" data-layout="1-col">
                                        <div style="flex: 1;"></div>
                                    </div>
                                    <div class="layout-template" onclick="setHeaderLayout('2-col')" data-layout="2-col">
                                        <div style="flex: 1;"></div>
                                        <div style="flex: 1;"></div>
                                    </div>
                                    <div class="layout-template" onclick="setHeaderLayout('3-col')" data-layout="3-col">
                                        <div style="flex: 1;"></div>
                                        <div style="flex: 1;"></div>
                                        <div style="flex: 1;"></div>
                                    </div>
                                    <div class="layout-template" onclick="setHeaderLayout('2-1-col')" data-layout="2-1-col">
                                        <div style="flex: 2;"></div>
                                        <div style="flex: 1;"></div>
                                    </div>
                                    <div class="layout-template" onclick="setHeaderLayout('1-2-col')" data-layout="1-2-col">
                                        <div style="flex: 1;"></div>
                                        <div style="flex: 2;"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Element Toolbox -->
                            <div class="element-toolbox">
                                <label style="font-weight: 600; margin-bottom: 0.5rem; display: block;">
                                    <i class="fas fa-toolbox"></i> Elementi Disponibili
                                </label>
                                <div class="element-buttons">
                                    <div class="element-btn" onclick="addHeaderElement('text')">
                                        <i class="fas fa-font"></i>
                                        <span>Testo</span>
                                    </div>
                                    <div class="element-btn" onclick="addHeaderElement('logo')">
                                        <i class="fas fa-building"></i>
                                        <span>Logo Azienda</span>
                                    </div>
                                    <div class="element-btn" onclick="addHeaderElement('company-info')">
                                        <i class="fas fa-building"></i>
                                        <span>Info Azienda</span>
                                    </div>
                                    <div class="element-btn" onclick="addHeaderElement('date')">
                                        <i class="fas fa-calendar"></i>
                                        <span>Data</span>
                                    </div>
                                    <div class="element-btn" onclick="addHeaderElement('doc-title')">
                                        <i class="fas fa-heading"></i>
                                        <span>Titolo Documento</span>
                                    </div>
                                    <div class="element-btn" onclick="addHeaderElement('doc-code')">
                                        <i class="fas fa-barcode"></i>
                                        <span>Codice Documento</span>
                                    </div>
                                    <div class="element-btn" onclick="addHeaderElement('doc-version')">
                                        <i class="fas fa-code-branch"></i>
                                        <span>Versione Documento</span>
                                    </div>
                                    <div class="element-btn" onclick="addHeaderElement('page-number')">
                                        <i class="fas fa-hashtag"></i>
                                        <span>Numero Pagina</span>
                                    </div>
                                    <div class="element-btn" onclick="addHeaderElement('line')">
                                        <i class="fas fa-minus"></i>
                                        <span>Linea</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- A4 Preview Area -->
                            <div class="a4-preview">
                                <div class="header-area header-footer-area" id="header-design-area">
                                    <div class="layout-grid grid-1-col" id="header-grid">
                                        <div class="grid-cell">
                                            <div class="row-controls">
                                                <button type="button" class="row-btn" onclick="addRowToCell(this)" title="Aggiungi riga">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                                <button type="button" class="row-btn" onclick="removeRowFromCell(this)" title="Rimuovi riga">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                            </div>
                                            <div class="cell-rows">
                                                <div class="cell-row" onclick="selectRow(this, 'header')">
                                                    <span style="color: #9ca3af;">Clicca per aggiungere contenuto</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Hidden textarea to store HTML -->
                            <textarea id="header-editor" style="display: none;"></textarea>
                            <input type="hidden" id="header-content" name="header_content">
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div id="footer-tab" class="tab-content" style="display: none;">
                        <div class="visual-editor-container">
                            <h4 style="margin-bottom: 1rem;"><i class="fas fa-align-center"></i> Editor Visuale Piè di Pagina</h4>
                            
                            <!-- Layout Templates -->
                            <div class="layout-section">
                                <label style="font-weight: 600; margin-bottom: 0.5rem; display: block;">
                                    <i class="fas fa-th"></i> Scegli Layout
                                </label>
                                <div class="layout-templates">
                                    <div class="layout-template active" onclick="setFooterLayout('1-col')" data-layout="1-col">
                                        <div style="flex: 1;"></div>
                                    </div>
                                    <div class="layout-template" onclick="setFooterLayout('2-col')" data-layout="2-col">
                                        <div style="flex: 1;"></div>
                                        <div style="flex: 1;"></div>
                                    </div>
                                    <div class="layout-template" onclick="setFooterLayout('3-col')" data-layout="3-col">
                                        <div style="flex: 1;"></div>
                                        <div style="flex: 1;"></div>
                                        <div style="flex: 1;"></div>
                                    </div>
                                    <div class="layout-template" onclick="setFooterLayout('2-1-col')" data-layout="2-1-col">
                                        <div style="flex: 2;"></div>
                                        <div style="flex: 1;"></div>
                                    </div>
                                    <div class="layout-template" onclick="setFooterLayout('1-2-col')" data-layout="1-2-col">
                                        <div style="flex: 1;"></div>
                                        <div style="flex: 2;"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Element Toolbox -->
                            <div class="element-toolbox">
                                <label style="font-weight: 600; margin-bottom: 0.5rem; display: block;">
                                    <i class="fas fa-toolbox"></i> Elementi Disponibili
                                </label>
                                <div class="element-buttons">
                                    <div class="element-btn" onclick="addFooterElement('text')">
                                        <i class="fas fa-font"></i>
                                        <span>Testo</span>
                                    </div>
                                    <div class="element-btn" onclick="addFooterElement('logo')">
                                        <i class="fas fa-building"></i>
                                        <span>Logo Azienda</span>
                                    </div>
                                    <div class="element-btn" onclick="addFooterElement('page-number')">
                                        <i class="fas fa-hashtag"></i>
                                        <span>Numero Pagina</span>
                                    </div>
                                    <div class="element-btn" onclick="addFooterElement('doc-title')">
                                        <i class="fas fa-heading"></i>
                                        <span>Titolo Documento</span>
                                    </div>
                                    <div class="element-btn" onclick="addFooterElement('doc-code')">
                                        <i class="fas fa-barcode"></i>
                                        <span>Codice Documento</span>
                                    </div>
                                    <div class="element-btn" onclick="addFooterElement('doc-version')">
                                        <i class="fas fa-code-branch"></i>
                                        <span>Versione Documento</span>
                                    </div>
                                    <div class="element-btn" onclick="addFooterElement('copyright')">
                                        <i class="fas fa-copyright"></i>
                                        <span>Copyright</span>
                                    </div>
                                    <div class="element-btn" onclick="addFooterElement('date')">
                                        <i class="fas fa-calendar"></i>
                                        <span>Data Stampa</span>
                                    </div>
                                    <div class="element-btn" onclick="addFooterElement('contact')">
                                        <i class="fas fa-phone"></i>
                                        <span>Contatti</span>
                                    </div>
                                    <div class="element-btn" onclick="addFooterElement('line')">
                                        <i class="fas fa-minus"></i>
                                        <span>Linea</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- A4 Preview Area -->
                            <div class="a4-preview">
                                <div class="footer-area header-footer-area" id="footer-design-area">
                                    <div class="layout-grid grid-1-col" id="footer-grid">
                                        <div class="grid-cell">
                                            <div class="row-controls">
                                                <button type="button" class="row-btn" onclick="addRowToCell(this)" title="Aggiungi riga">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                                <button type="button" class="row-btn" onclick="removeRowFromCell(this)" title="Rimuovi riga">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                            </div>
                                            <div class="cell-rows">
                                                <div class="cell-row" onclick="selectRow(this, 'footer')">
                                                    <span style="color: #9ca3af;">Clicca per aggiungere contenuto</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Hidden textarea to store HTML -->
                            <textarea id="footer-editor" style="display: none;"></textarea>
                            <input type="hidden" id="footer-content" name="footer_content">
                        </div>
                    </div>
                    
                    <div class="placeholders-info">
                        <h4><i class="fas fa-info-circle"></i> Elementi disponibili come placeholder</h4>
                        <div class="placeholder-list">
                            <div class="placeholder-item" onclick="copyPlaceholder('{logo_azienda}')">
                                <code>{logo_azienda}</code> - Logo dell'azienda
                            </div>
                            <div class="placeholder-item" onclick="copyPlaceholder('{info_azienda}')">
                                <code>{info_azienda}</code> - Informazioni complete azienda
                            </div>
                            <div class="placeholder-item" onclick="copyPlaceholder('{titolo_documento}')">
                                <code>{titolo_documento}</code> - Titolo del documento
                            </div>
                            <div class="placeholder-item" onclick="copyPlaceholder('{codice_documento}')">
                                <code>{codice_documento}</code> - Codice del documento
                            </div>
                            <div class="placeholder-item" onclick="copyPlaceholder('{versione_documento}')">
                                <code>{versione_documento}</code> - Versione del documento
                            </div>
                            <div class="placeholder-item" onclick="copyPlaceholder('{numero_pagina}')">
                                <code>{numero_pagina}</code> - Numero di pagina
                            </div>
                            <div class="placeholder-item" onclick="copyPlaceholder('{totale_pagine}')">
                                <code>{totale_pagine}</code> - Totale pagine
                            </div>
                            <div class="placeholder-item" onclick="copyPlaceholder('{data_stampa}')">
                                <code>{data_stampa}</code> - Data e ora di stampa
                        </div>
                            <div class="placeholder-item" onclick="copyPlaceholder('{copyright}')">
                                <code>{copyright}</code> - Copyright aziendale
                            </div>
                            <div class="placeholder-item" onclick="copyPlaceholder('{contatti}')">
                                <code>{contatti}</code> - Contatti azienda
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            
            <!-- Preview Button -->
            <div style="margin-top: 1rem;">
                <button type="button" class="btn btn-secondary" onclick="previewTemplate()">
                    <i class="fas fa-eye"></i> Anteprima Template
                </button>
            </div>
            
            <!-- Preview Container -->
            <div id="template-preview" class="preview-document" style="display: none;">
                <h4 style="margin-bottom: 1rem; color: var(--primary-color);">
                    <i class="fas fa-eye"></i> Anteprima Documento
                </h4>
                
                <div class="preview-header" id="preview-header">
                    <!-- Header content will be inserted here -->
                </div>
                
                <div class="preview-body" id="preview-body">
                    <!-- Main content will be inserted here -->
                </div>
                
                <div class="preview-footer" id="preview-footer">
                    <!-- Footer content will be inserted here -->
                </div>
                
                <div class="preview-page-info">
                    Pagina 1 di 1
                </div>
            </div>
            
            <!-- Text Editor Popup -->
            <div class="popup-overlay" onclick="closeTextEditor()"></div>
            <div class="text-editor-popup" id="text-editor-popup">
                <h4 style="margin-bottom: 1rem;">
                    <i class="fas fa-edit"></i> Editor Testo
                    <button class="control-btn" style="float: right;" onclick="closeTextEditor()">
                        <i class="fas fa-times"></i>
                    </button>
                </h4>
                
                <div class="alignment-buttons">
                    <button class="align-btn" onclick="setTextAlign('left')">
                        <i class="fas fa-align-left"></i>
                    </button>
                    <button class="align-btn" onclick="setTextAlign('center')">
                        <i class="fas fa-align-center"></i>
                    </button>
                    <button class="align-btn" onclick="setTextAlign('right')">
                        <i class="fas fa-align-right"></i>
                    </button>
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <label>Contenuto:</label>
                    <textarea id="text-editor-content" class="form-control" rows="6" 
                              placeholder="Inserisci il testo qui..."></textarea>
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <label>Dimensione Font:</label>
                    <select id="text-font-size" class="form-control" style="width: 150px;">
                        <option value="10px">10px</option>
                        <option value="12px" selected>12px</option>
                        <option value="14px">14px</option>
                        <option value="16px">16px</option>
                        <option value="18px">18px</option>
                        <option value="20px">20px</option>
                        <option value="24px">24px</option>
                    </select>
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <label>Stile:</label>
                    <label style="margin-left: 1rem;">
                        <input type="checkbox" id="text-bold"> Grassetto
                    </label>
                    <label style="margin-left: 1rem;">
                        <input type="checkbox" id="text-italic"> Corsivo
                    </label>
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <label>Variabili disponibili:</label>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem;">
                        <button class="quick-insert-btn" onclick="insertTextVariable('{titolo_documento}')">
                            {titolo_documento}
                        </button>
                        <button class="quick-insert-btn" onclick="insertTextVariable('{codice_documento}')">
                            {codice_documento}
                        </button>
                        <button class="quick-insert-btn" onclick="insertTextVariable('{versione_documento}')">
                            {versione_documento}
                        </button>
                        <button class="quick-insert-btn" onclick="insertTextVariable('{data_stampa}')">
                            {data_stampa}
                        </button>
                        <button class="quick-insert-btn" onclick="insertTextVariable('{numero_pagina}')">
                            {numero_pagina}
                        </button>
                        <button class="quick-insert-btn" onclick="insertTextVariable('{totale_pagine}')">
                            {totale_pagine}
                        </button>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button class="btn btn-primary" onclick="saveTextContent()">
                        <i class="fas fa-check"></i> Applica
                    </button>
                    <button class="btn btn-secondary" onclick="closeTextEditor()">
                        Annulla
                    </button>
                </div>
            </div>
            
            <!-- Image Upload Popup -->
            <div class="popup-overlay" id="image-overlay" onclick="closeImageUpload()"></div>
            <div class="text-editor-popup" id="image-upload-popup">
                <h4 style="margin-bottom: 1rem;">
                    <i class="fas fa-image"></i> Carica Immagine/Logo
                    <button class="control-btn" style="float: right;" onclick="closeImageUpload()">
                        <i class="fas fa-times"></i>
                    </button>
                </h4>
                
                <div class="image-upload-zone" onclick="document.getElementById('upload-image-input').click()">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p>Clicca per selezionare un'immagine</p>
                    <small>PNG, JPG, GIF - Max 2MB</small>
                </div>
                
                <input type="file" id="upload-image-input" accept="image/*" style="display: none;" 
                       onchange="handleImageUpload(event)">
                
                <div id="image-preview-container" style="display: none; margin-top: 1rem;">
                    <img id="image-preview" style="max-width: 100%; max-height: 200px;">
                    
                    <div style="margin-top: 1rem;">
                        <label>Larghezza massima:</label>
                        <input type="range" id="image-width" min="50" max="300" value="150" 
                               oninput="updateImageSize()">
                        <span id="image-width-value">150px</span>
                    </div>
                </div>
                
                <div class="form-actions" style="margin-top: 1rem;">
                    <button class="btn btn-primary" onclick="saveImageContent()" id="save-image-btn" disabled>
                        <i class="fas fa-check"></i> Inserisci
                    </button>
                    <button class="btn btn-secondary" onclick="closeImageUpload()">
                        Annulla
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
    // Templates esistenti
    const existingTemplates = <?php echo json_encode($templates); ?>;
    let selectedModulo = null;
    let currentTemplateType = 'word';
    let currentEditingCell = null;
    let currentEditingSection = null;
    
    // Layout Management
    function setHeaderLayout(layout) {
        setLayout('header', layout);
    }
    
    function setFooterLayout(layout) {
        setLayout('footer', layout);
    }
    
    function setLayout(section, layout) {
        const grid = document.getElementById(section + '-grid');
        
        // Update active state
        document.querySelectorAll(`#${section}-tab .layout-template`).forEach(t => {
            t.classList.remove('active');
            if (t.dataset.layout === layout) t.classList.add('active');
        });
        
        // Clear existing grid
        grid.className = 'layout-grid grid-' + layout;
        grid.innerHTML = '';
        
        // Create new cells based on layout
        let cellCount = 1;
        switch(layout) {
            case '2-col':
            case '2-1-col':
            case '1-2-col':
                cellCount = 2;
                break;
            case '3-col':
                cellCount = 3;
                break;
        }
        
        for (let i = 0; i < cellCount; i++) {
            const cell = document.createElement('div');
            cell.className = 'grid-cell';
            
            // Add row controls and cell-rows structure
            cell.innerHTML = `
                <div class="row-controls">
                    <button type="button" class="row-btn" onclick="addRowToCell(this)" title="Aggiungi riga">
                        <i class="fas fa-plus"></i>
                    </button>
                    <button type="button" class="row-btn" onclick="removeRowFromCell(this)" title="Rimuovi riga">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
                <div class="cell-rows">
                    <div class="cell-row" onclick="selectRow(this, '${section}')">
                        <span style="color: #9ca3af;">Clicca per aggiungere contenuto</span>
                    </div>
                </div>
            `;
            
            grid.appendChild(cell);
        }
        
        saveToHidden(section);
    }
    
    // Cell Selection
    function selectCell(cell, section) {
        // This function is kept for backward compatibility but now works with rows
        const firstRow = cell.querySelector('.cell-row');
        if (firstRow) {
            selectRow(firstRow, section);
        }
    }
    
    // Row Selection (new function for row-based selection)
    function selectRow(row, section) {
        if (row.classList.contains('has-content')) return;
        
        // Update active state
        document.querySelectorAll(`#${section}-grid .cell-row`).forEach(r => {
            r.classList.remove('active');
        });
        row.classList.add('active');
        
        currentEditingCell = row;
        currentEditingSection = section;
    }
    
    // Row management functions
    function addRowToCell(btn) {
        const cell = btn.closest('.grid-cell');
        const cellRows = cell.querySelector('.cell-rows');
        const currentRows = cellRows.querySelectorAll('.cell-row').length;
        
        if (currentRows >= 3) {
            alert('Massimo 3 righe per cella');
            return;
        }
        
        const newRow = document.createElement('div');
        newRow.className = 'cell-row';
        const section = cell.closest('.header-area') ? 'header' : 'footer';
        newRow.onclick = function() { selectRow(this, section); };
        newRow.innerHTML = '<span style="color: #9ca3af;">Clicca per aggiungere contenuto</span>';
        
        cellRows.appendChild(newRow);
        saveToHidden(section);
    }
    
    function removeRowFromCell(btn) {
        const cell = btn.closest('.grid-cell');
        const cellRows = cell.querySelector('.cell-rows');
        const rows = cellRows.querySelectorAll('.cell-row');
        
        if (rows.length <= 1) {
            alert('Deve esserci almeno una riga per cella');
            return;
        }
        
        // Remove the last empty row, or the last row if all have content
        let removed = false;
        for (let i = rows.length - 1; i >= 0; i--) {
            if (!rows[i].classList.contains('has-content')) {
                rows[i].remove();
                removed = true;
                break;
            }
        }
        
        if (!removed) {
            rows[rows.length - 1].remove();
        }
        
        const section = cell.closest('.header-area') ? 'header' : 'footer';
        saveToHidden(section);
    }
    
    // Add Elements
    function addHeaderElement(type) {
        if (!currentEditingCell || currentEditingSection !== 'header') {
            alert('Seleziona prima una cella nell\'intestazione');
            return;
        }
        addElement(type, 'header');
    }
    
    function addFooterElement(type) {
        if (!currentEditingCell || currentEditingSection !== 'footer') {
            alert('Seleziona prima una cella nel piè di pagina');
            return;
        }
        addElement(type, 'footer');
    }
    
    function addElement(type, section) {
        switch(type) {
            case 'text':
                openTextEditor();
                break;
            case 'logo':
                insertCompanyLogo();
                break;
            case 'company-info':
                insertCompanyInfo();
                break;
            case 'date':
                insertDate();
                break;
            case 'doc-title':
                insertDocTitle();
                break;
            case 'doc-code':
                insertDocCode();
                break;
            case 'doc-version':
                insertDocVersion();
                break;
            case 'page-number':
                insertPageNumber();
                break;
            case 'copyright':
                insertCopyright();
                break;
            case 'contact':
                insertContact();
                break;
            case 'line':
                insertLine();
                break;
        }
    }
    
    // Text Editor
    function openTextEditor() {
        document.getElementById('text-editor-popup').classList.add('active');
        document.querySelector('.popup-overlay').classList.add('active');
        document.getElementById('text-editor-content').value = '';
        document.getElementById('text-editor-content').focus();
    }
    
    function closeTextEditor() {
        document.getElementById('text-editor-popup').classList.remove('active');
        document.querySelector('.popup-overlay').classList.remove('active');
    }
    
    function setTextAlign(align) {
        document.querySelectorAll('.align-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        event.target.closest('.align-btn').classList.add('active');
    }
    
    function insertTextVariable(variable) {
        const textarea = document.getElementById('text-editor-content');
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = textarea.value;
        textarea.value = text.substring(0, start) + variable + text.substring(end);
        textarea.focus();
        textarea.selectionStart = textarea.selectionEnd = start + variable.length;
    }
    
    function saveTextContent() {
        const content = document.getElementById('text-editor-content').value;
        const fontSize = document.getElementById('text-font-size').value;
        const isBold = document.getElementById('text-bold').checked;
        const isItalic = document.getElementById('text-italic').checked;
        const align = document.querySelector('.align-btn.active') ? 
                      document.querySelector('.align-btn.active i').className.split('-').pop() : 'left';
        
        if (!content.trim()) {
            alert('Inserisci del testo');
            return;
        }
        
        let style = `font-size: ${fontSize};`;
        if (isBold) style += ' font-weight: bold;';
        if (isItalic) style += ' font-style: italic;';
        style += ` text-align: ${align};`;
        
        const html = `
            <div class="content-element text-element" style="${style}">
                ${content.replace(/\n/g, '<br>')}
                <div class="element-controls">
                    <button class="control-btn" onclick="editElement(this)">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="control-btn delete" onclick="removeElement(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
        
        currentEditingCell.innerHTML = html;
        currentEditingCell.classList.add('has-content');
        currentEditingCell.classList.remove('active');
        
        closeTextEditor();
        saveToHidden(currentEditingSection);
    }
    
    // Image Upload
    function openImageUpload() {
        document.getElementById('image-upload-popup').classList.add('active');
        document.getElementById('image-overlay').classList.add('active');
    }
    
    function closeImageUpload() {
        document.getElementById('image-upload-popup').classList.remove('active');
        document.getElementById('image-overlay').classList.remove('active');
        document.getElementById('image-preview-container').style.display = 'none';
        document.getElementById('save-image-btn').disabled = true;
    }
    
    function handleImageUpload(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('image-preview').src = e.target.result;
                document.getElementById('image-preview-container').style.display = 'block';
                document.getElementById('save-image-btn').disabled = false;
            };
            reader.readAsDataURL(file);
        }
    }
    
    function updateImageSize() {
        const width = document.getElementById('image-width').value;
        document.getElementById('image-width-value').textContent = width + 'px';
    }
    
    function saveImageContent() {
        const imgSrc = document.getElementById('image-preview').src;
        const width = document.getElementById('image-width').value;
        
        // Per ora usa un placeholder invece del data URL completo
        // In produzione, bisognerebbe caricare l'immagine sul server
        const placeholderSrc = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTUwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTUwIiBoZWlnaHQ9IjEwMCIgZmlsbD0iI2RkZCIvPjx0ZXh0IHRleHQtYW5jaG9yPSJtaWRkbGUiIHg9Ijc1IiB5PSI1MCIgc3R5bGU9ImZpbGw6IzYwNjA2MDtmb250LXdlaWdodDpib2xkO2ZvbnQtc2l6ZToxNHB4O2ZvbnQtZmFtaWx5OkFyaWFsLEhlbHZldGljYSxzYW5zLXNlcmlmO2RvbWluYW50LWJhc2VsaW5lOmNlbnRyYWwiPkxPR088L3RleHQ+PC9zdmc+';
        
        const html = `
            <div class="content-element image-element">
                <img src="${placeholderSrc}" alt="Logo" style="max-width: ${width}px; height: auto;">
                <div class="element-controls">
                    <button class="control-btn delete" onclick="removeElement(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
        
        currentEditingCell.innerHTML = html;
        currentEditingCell.classList.add('has-content');
        currentEditingCell.classList.remove('active');
        
        closeImageUpload();
        saveToHidden(currentEditingSection);
    }
    
    // Preset Elements
    function insertCompanyInfo() {
        const html = `
            <div class="content-element company-info">
                {info_azienda}
                <div class="element-controls">
                    <button class="control-btn delete" onclick="removeElement(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
        
        currentEditingCell.innerHTML = html;
        currentEditingCell.classList.add('has-content');
        currentEditingCell.classList.remove('active');
        saveToHidden(currentEditingSection);
    }
    
    function insertDate() {
        const html = `
            <div class="content-element date-element">
                {data_stampa}
                <div class="element-controls">
                    <button class="control-btn delete" onclick="removeElement(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
        
        currentEditingCell.innerHTML = html;
        currentEditingCell.classList.add('has-content');
        currentEditingCell.classList.remove('active');
        saveToHidden(currentEditingSection);
    }
    
    function insertDocTitle() {
        const html = `
            <div class="content-element doc-title-element">
                {titolo_documento}
                <div class="element-controls">
                    <button class="control-btn delete" onclick="removeElement(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
        
        currentEditingCell.innerHTML = html;
        currentEditingCell.classList.add('has-content');
        currentEditingCell.classList.remove('active');
        saveToHidden(currentEditingSection);
    }
    
    function insertDocCode() {
        const html = `
            <div class="content-element doc-code-element">
                {codice_documento}
                <div class="element-controls">
                    <button class="control-btn delete" onclick="removeElement(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
        
        currentEditingCell.innerHTML = html;
        currentEditingCell.classList.add('has-content');
        currentEditingCell.classList.remove('active');
        saveToHidden(currentEditingSection);
    }
    
    function insertDocVersion() {
        const html = `
            <div class="content-element doc-version-element">
                {versione_documento}
                <div class="element-controls">
                    <button class="control-btn delete" onclick="removeElement(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
        
        currentEditingCell.innerHTML = html;
        currentEditingCell.classList.add('has-content');
        currentEditingCell.classList.remove('active');
        saveToHidden(currentEditingSection);
    }
    
    function insertPageNumber() {
        const html = `
            <div class="content-element page-number">
                Pagina {numero_pagina} di {totale_pagine}
                <div class="element-controls">
                    <button class="control-btn delete" onclick="removeElement(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
        
        currentEditingCell.innerHTML = html;
        currentEditingCell.classList.add('has-content');
        currentEditingCell.classList.remove('active');
        saveToHidden(currentEditingSection);
    }
    
    function insertCopyright() {
        const html = `
            <div class="content-element copyright">
                {copyright}
                <div class="element-controls">
                    <button class="control-btn delete" onclick="removeElement(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
        
        currentEditingCell.innerHTML = html;
        currentEditingCell.classList.add('has-content');
        currentEditingCell.classList.remove('active');
        saveToHidden(currentEditingSection);
    }
    
    function insertContact() {
        const html = `
            <div class="content-element contact">
                {contatti}
                <div class="element-controls">
                    <button class="control-btn delete" onclick="removeElement(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
        
        currentEditingCell.innerHTML = html;
        currentEditingCell.classList.add('has-content');
        currentEditingCell.classList.remove('active');
        saveToHidden(currentEditingSection);
    }
    
    function insertLine() {
        const html = `
            <div class="content-element line-element">
                <hr style="border-top: 2px solid #1b3f76; margin: 10px 0;">
                <div class="element-controls">
                    <button class="control-btn delete" onclick="removeElement(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
        
        currentEditingCell.innerHTML = html;
        currentEditingCell.classList.add('has-content');
        currentEditingCell.classList.remove('active');
        saveToHidden(currentEditingSection);
    }
    
    function insertCompanyLogo() {
        const html = `
            <div class="content-element logo-element">
                <div style="border: 2px dashed #cbd5e0; padding: 1rem; text-align: center; background: #f9fafb; border-radius: 6px;">
                    <i class="fas fa-building" style="font-size: 2rem; color: #9ca3af;"></i><br>
                    <span style="color: #6b7280; font-size: 0.875rem;">{logo_azienda}</span>
                </div>
                <div class="element-controls">
                    <button class="control-btn delete" onclick="removeElement(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
        
        currentEditingCell.innerHTML = html;
        currentEditingCell.classList.add('has-content');
        currentEditingCell.classList.remove('active');
        saveToHidden(currentEditingSection);
    }
    
    // Element Controls
    function removeElement(btn) {
        const row = btn.closest('.cell-row');
        row.innerHTML = '<span style="color: #9ca3af;">Clicca per aggiungere contenuto</span>';
        row.classList.remove('has-content');
        
        const section = row.closest('.header-area') ? 'header' : 'footer';
        saveToHidden(section);
    }
    
    // Save to hidden fields
    function saveToHidden(section) {
        const grid = document.getElementById(section + '-grid');
        
        // Build clean HTML for saving
        let html = '<div style="width: 100%;">';
        const layout = grid.className.replace('layout-grid grid-', '');
        
        if (layout.includes('col')) {
            html += '<table style="width: 100%; border-collapse: collapse;"><tr>';
            
            grid.querySelectorAll('.grid-cell').forEach((cell, index) => {
                let width = '50%';
                if (layout === '3-col') width = '33.33%';
                if (layout === '2-1-col') width = index === 0 ? '66.66%' : '33.33%';
                if (layout === '1-2-col') width = index === 0 ? '33.33%' : '66.66%';
                
                html += `<td style="width: ${width}; padding: 5px; vertical-align: top;">`;
                
                // Check if cell has multiple rows
                const rows = cell.querySelectorAll('.cell-row');
                if (rows.length > 1) {
                    html += '<table style="width: 100%; border-collapse: collapse;">';
                    rows.forEach(row => {
                        html += '<tr><td style="padding: 2px;">';
                        const element = row.querySelector('.content-element');
                        if (element) {
                            // Clone and clean the element
                            const clone = element.cloneNode(true);
                            const controls = clone.querySelector('.element-controls');
                            if (controls) controls.remove();
                            html += clone.innerHTML;
                        }
                        html += '</td></tr>';
                    });
                    html += '</table>';
                } else {
                    // Single row
                    const element = rows[0]?.querySelector('.content-element');
                    if (element) {
                        // Clone and clean the element
                        const clone = element.cloneNode(true);
                        const controls = clone.querySelector('.element-controls');
                        if (controls) controls.remove();
                        
                        // Se è un elemento logo, salva solo il placeholder
                        if (element.classList.contains('logo-element')) {
                            html += '{logo_azienda}';
                        } else {
                            html += clone.innerHTML;
                        }
                    }
                }
                
                html += '</td>';
            });
            
            html += '</tr></table>';
        }
        
        html += '</div>';
        
        // Save to hidden fields
        document.getElementById(section + '-content').value = html;
        
        // Also save to textarea for CKEditor compatibility
        const textarea = document.getElementById(section + '-editor');
        if (textarea) textarea.value = html;
    }
    
    // Load existing template data
    function loadTemplateData(section, htmlContent) {
        if (!htmlContent) return;
        
        // For now, show a message that existing content is loaded
        const grid = document.getElementById(section + '-grid');
        if (grid && grid.querySelector('.grid-cell')) {
            const firstRow = grid.querySelector('.cell-row');
            if (firstRow) {
                firstRow.innerHTML = `
                    <div class="content-element">
                        <div style="color: #059669; text-align: center;">
                            <i class="fas fa-check-circle"></i> Template esistente caricato
                        </div>
                    </div>
                `;
                firstRow.classList.add('has-content');
            }
        }
    }
    
    // Module selection and core functions
    function selectModulo(moduloId) {
        // Rimuovi active da tutti i moduli
        document.querySelectorAll('.modulo-card').forEach(card => {
            card.classList.remove('active');
        });
        
        // Aggiungi active al modulo selezionato
        const moduloCard = document.getElementById('modulo-' + moduloId);
        moduloCard.classList.add('active');
        
        // Mostra l'editor
        document.getElementById('template-editor-container').style.display = 'block';
        
        // Imposta il nome del modulo
        document.getElementById('selected-modulo-name').textContent = moduloCard.dataset.nome;
        
        // Memorizza il modulo selezionato
        selectedModulo = moduloId;
        document.getElementById('modulo_id').value = moduloId;
        
        // Carica il template esistente se disponibile
        const templateData = existingTemplates[moduloId];
        if (templateData) {
            // Load header/footer
            if (templateData.header_content) {
                document.getElementById('header-content').value = templateData.header_content;
                loadTemplateData('header', templateData.header_content);
            }
            
            if (templateData.footer_content) {
                document.getElementById('footer-content').value = templateData.footer_content;
                loadTemplateData('footer', templateData.footer_content);
            }
            
            // Aggiorna il tipo di template
            const tipo = moduloCard.dataset.tipo || 'word';
            document.getElementById('template_type').value = tipo;
            updateTemplateType(tipo);
        } else {
            // Pulisci i campi
            document.getElementById('header-content').value = '';
            document.getElementById('footer-content').value = '';
            
            // Reset grids
            setLayout('header', '1-col');
            setLayout('footer', '1-col');
        }
        
        // Vai direttamente all'header tab
        switchTabDirect('header');
        
        // Scroll to editor
        setTimeout(() => {
            document.getElementById('template-editor-container').scrollIntoView({ 
                behavior: 'smooth', 
                block: 'start' 
            });
        }, 200);
    }
    
    function switchTabDirect(tabName) {
        // Rimuovi active da tutti i tab
        document.querySelectorAll('.editor-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
            content.style.display = 'none';
        });
        
        // Attiva il tab selezionato
        const tabIndex = tabName === 'header' ? 0 : 1;
        document.querySelectorAll('.editor-tab')[tabIndex].classList.add('active');
        document.getElementById(tabName + '-tab').classList.add('active');
        document.getElementById(tabName + '-tab').style.display = 'block';
    }
    
    function switchTab(tabName) {
        switchTabDirect(tabName);
    }
    
    function switchTemplateType(type) {
        currentTemplateType = type;
        document.getElementById('template_type').value = type;
        
        // Update buttons
        document.querySelectorAll('.type-btn').forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.type === type) {
                btn.classList.add('active');
            }
        });
    }
    
    function updateTemplateType(type) {
        document.querySelectorAll('.type-btn').forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.type === type) {
                btn.classList.add('active');
            }
        });
        currentTemplateType = type;
    }
    
    function cancelEdit() {
        document.getElementById('template-editor-container').style.display = 'none';
        document.querySelectorAll('.modulo-card').forEach(card => {
            card.classList.remove('active');
        });
        selectedModulo = null;
    }
    
    function saveTemplate() {
        if (!selectedModulo) {
            alert('Seleziona un modulo prima di salvare');
            return;
        }
        
        // Header e footer vengono salvati dal visual editor
        const headerContent = document.getElementById('header-content').value;
        const footerContent = document.getElementById('footer-content').value;
        const templateType = document.getElementById('template_type').value;
        
        // Prepara i dati del form
        const formData = new FormData(document.getElementById('template-form'));
        // Aggiungi un contenuto template vuoto per compatibilità con il backend
        formData.set('template_content', ' ');
        // Aggiungi il tipo (il PHP si aspetta 'tipo' non 'template_type')
        formData.set('tipo', templateType);
        
        // Mostra loading
        const saveBtn = event.target || document.querySelector('[onclick="saveTemplate()"]');
        const originalText = saveBtn.innerHTML;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvataggio...';
        saveBtn.disabled = true;
        
        // Debug: log formData
        console.log('FormData being sent:');
        for (let [key, value] of formData.entries()) {
            console.log(key + ': ' + value);
        }
        
        // Usa il nuovo endpoint API
        fetch('api-save-template.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            
            // Controlla se la risposta è JSON
            const contentType = response.headers.get("content-type");
            if (contentType && contentType.indexOf("application/json") !== -1) {
                return response.json();
            } else {
                // Se non è JSON, leggi come testo per debug
                return response.text().then(text => {
                    console.error('Non-JSON response:', text);
                    throw new Error('La risposta non è JSON valido');
                });
            }
        })
        .then(data => {
            console.log('Response data:', data);
            if (data.success) {
                showToast('Template salvato con successo!', 'success');
                // Aggiorna i dati locali
                existingTemplates[selectedModulo] = {
                    header_content: headerContent,
                    footer_content: footerContent,
                    tipo: templateType
                };
                // Aggiorna UI
                setTimeout(() => location.reload(), 1000);
            } else {
                alert('Errore: ' + (data.message || 'Errore nel salvataggio'));
            }
        })
        .catch(error => {
            console.error('Errore completo:', error);
            alert('Si è verificato un errore durante il salvataggio: ' + error.message);
        })
        .finally(() => {
            saveBtn.innerHTML = originalText;
            saveBtn.disabled = false;
        });
    }
    
    function previewTemplate() {
        const header = document.getElementById('header-content').value;
        const footer = document.getElementById('footer-content').value;
        
        // Prepara i dati di esempio
        const sampleData = {
            '{titolo_documento}': 'Documento di Esempio',
            '{codice_documento}': 'DOC-2024-001',
            '{versione_documento}': '1.0',
            '{logo_azienda}': '<img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTUwIiBoZWlnaHQ9IjUwIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjxyZWN0IHdpZHRoPSIxNTAiIGhlaWdodD0iNTAiIGZpbGw9IiNkZGQiLz48dGV4dCB0ZXh0LWFuY2hvcj0ibWlkZGxlIiB4PSI3NSIgeT0iMzAiIHN0eWxlPSJmaWxsOiM2MDYwNjA7Zm9udC13ZWlnaHQ6Ym9sZDtmb250LXNpemU6MTRweDtmb250LWZhbWlseTpBcmlhbCxIZWx2ZXRpY2Esc2Fucy1zZXJpZjtkb21pbmFudC1iYXNlbGluZTpjZW50cmFsIj5MT0dPIEFaSUVOREE8L3RleHQ+PC9zdmc+" alt="Logo Azienda" style="max-height: 50px;">',
            '{info_azienda}': '<div style="font-size: 12px; line-height: 1.5;"><strong>Azienda Demo S.r.l.</strong><br>Via Roma 123, 00100 Roma<br>Tel: +39 06 123456 - Email: info@aziendademo.it<br>P.IVA: 12345678901</div>',
            '{data_stampa}': new Date().toLocaleString('it-IT'),
            '{copyright}': '© ' + new Date().getFullYear() + ' Azienda Demo S.r.l. - Tutti i diritti riservati',
            '{contatti}': '<i class="fas fa-phone"></i> +39 06 123456 | <i class="fas fa-envelope"></i> info@aziendademo.it',
            '{numero_pagina}': '1',
            '{totale_pagine}': '1'
        };
        
        // Sostituisci i placeholder
        let previewHeader = header;
        let previewFooter = footer;
        
        Object.keys(sampleData).forEach(placeholder => {
            const value = sampleData[placeholder];
            const regex = new RegExp(placeholder.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g');
            previewHeader = previewHeader.replace(regex, value);
            previewFooter = previewFooter.replace(regex, value);
        });
        
        // Mostra l'anteprima
        document.getElementById('preview-header').innerHTML = previewHeader || '<p style="color: #999; text-align: center;">Nessuna intestazione</p>';
        document.getElementById('preview-body').innerHTML = '<p style="text-align: center; color: #999; padding: 2rem;">Il contenuto del documento verrà inserito qui</p>';
        document.getElementById('preview-footer').innerHTML = previewFooter || '<p style="color: #999; text-align: center;">Nessun piè di pagina</p>';
        
        document.getElementById('template-preview').style.display = 'block';
        
        // Scroll to preview
        document.getElementById('template-preview').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    
    // Altre funzioni helper
    function copyPlaceholder(placeholder) {
        navigator.clipboard.writeText(placeholder).then(() => {
            showToast('Placeholder copiato negli appunti!', 'success');
        });
    }
    
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type === 'success' ? 'success' : 'error'}`;
        toast.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }
    
    // Gestione moduli
    function showCreateModuleForm() {
        document.getElementById('create-module-form').style.display = 'block';
        document.getElementById('nome').focus();
        document.getElementById('create-module-form').scrollIntoView({ 
            behavior: 'smooth', 
            block: 'start' 
        });
    }
    
    function hideCreateModuleForm() {
        document.getElementById('create-module-form').style.display = 'none';
        document.querySelector('#create-module-form form').reset();
    }
    
    function deleteModule(moduleId, moduleName) {
        if (confirm(`Sei sicuro di voler eliminare il modulo "${moduleName}"?\nQuesta azione non può essere annullata.`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete_module';
            form.appendChild(actionInput);
            
            const moduleInput = document.createElement('input');
            moduleInput.type = 'hidden';
            moduleInput.name = 'modulo_id';
            moduleInput.value = moduleId;
            form.appendChild(moduleInput);
            
            document.body.appendChild(form);
            form.submit();
        }
    }
    
    function toggleIconInput() {
        const iconType = document.getElementById('icona_type').value;
        const iconInput = document.getElementById('icona');
        const helpFa = document.getElementById('icon-help-fa');
        const helpEmoji = document.getElementById('icon-help-emoji');
        
        if (iconType === 'emoji') {
            iconInput.value = '📄';
            iconInput.placeholder = 'Inserisci un emoji';
            helpFa.style.display = 'none';
            helpEmoji.style.display = 'inline';
        } else {
            iconInput.value = 'fas fa-file-alt';
            iconInput.placeholder = 'es. fas fa-file-contract';
            helpFa.style.display = 'inline';
            helpEmoji.style.display = 'none';
        }
    }
    
    // Genera codice dal nome
    document.getElementById('nome')?.addEventListener('input', function() {
        const nome = this.value;
        const codiceInput = document.getElementById('codice');
        if (!codiceInput.dataset.manuallyEdited) {
            const codice = nome
                .toUpperCase()
                .replace(/[àáäâ]/g, 'A')
                .replace(/[èéëê]/g, 'E')
                .replace(/[ìíïî]/g, 'I')
                .replace(/[òóöô]/g, 'O')
                .replace(/[ùúüû]/g, 'U')
                .replace(/[^A-Z0-9]/g, '_')
                .replace(/_+/g, '_')
                .replace(/^_|_$/g, '');
            codiceInput.value = codice;
        }
    });
    
    document.getElementById('codice')?.addEventListener('input', function() {
        this.dataset.manuallyEdited = 'true';
    });
</script>

<?php require_once 'components/footer.php'; ?>
