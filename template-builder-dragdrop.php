<?php
/**
 * Template Builder con Drag and Drop
 * Sistema avanzato per creare template di documenti con interfaccia visuale
 */

require_once __DIR__ . '/backend/config/config.php';
require_once __DIR__ . '/backend/models/Template.php';

$auth = Auth::getInstance();
$auth->requireAuth();
$user = $auth->getUser();

// Solo super admin possono gestire i template
if (!$auth->isSuperAdmin()) {
    redirect(APP_PATH . '/dashboard.php');
}

// Connessione database
$pdo = db_connection();
$template = new Template($pdo);

// Gestione azioni
$action = $_GET['action'] ?? 'list';
$template_id = $_GET['id'] ?? null;

// Gestione POST per azioni rapide
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_action'])) {
    $quick_action = $_POST['quick_action'];
    $id = $_POST['template_id'] ?? null;
    
    if (!$id) {
        $_SESSION['error'] = "ID template non specificato";
        redirect(APP_PATH . '/template-builder-dragdrop.php');
    }
    
    try {
        switch ($quick_action) {
            case 'activate':
                $template->activate($id);
                $_SESSION['success'] = "Template attivato con successo";
                break;
                
            case 'deactivate':
                $template->deactivate($id);
                $_SESSION['success'] = "Template disattivato con successo";
                break;
                
            case 'toggle':
                $template->toggleStatus($id);
                $_SESSION['success'] = "Stato del template modificato con successo";
                break;
                
            case 'delete':
                $template->delete($id);
                $_SESSION['success'] = "Template eliminato con successo";
                break;
                
            case 'activate_all':
                // Attiva tutti i template inattivi
                $stmt = db_query("UPDATE templates SET attivo = 1 WHERE attivo = 0");
                $_SESSION['success'] = "Tutti i template sono stati attivati";
                break;
                
            default:
                $_SESSION['error'] = "Azione non riconosciuta";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Errore: " . $e->getMessage();
    }
    
    redirect(APP_PATH . '/template-builder-dragdrop.php');
}

// Template globali - non servono aziende specifiche

// Carica templates esistenti (TUTTI per debug)
$show_inactive = $_GET['show_inactive'] ?? false;
// TEMPORANEO: mostra sempre tutti i template per debug
$filters = []; // $show_inactive ? [] : ['attivo' => 1];
$templates = $template->getAll($filters);

// Debug: Log dei template caricati
error_log("Template Builder - Template caricati (" . count($templates) . "):");
foreach ($templates as $tpl) {
    error_log("- ID: {$tpl['id']}, Nome: {$tpl['nome']}, Attivo: {$tpl['attivo']}");
}

$currentTemplate = null;
if ($template_id && $action === 'edit') {
    $currentTemplate = $template->getById($template_id);
}

$pageTitle = 'Template Builder';
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_PATH ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Sortable.js per drag and drop -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
</head>
<body>
    <?php include 'components/header.php'; ?>
    
    <div class="main-content">
        <div class="content-header">
            <div class="header-left">
                <h1><i class="fas fa-layer-group"></i> Template Builder</h1>
                <p class="page-description">Crea e gestisci template di documenti con drag-and-drop</p>
            </div>
            <div class="header-actions">
                <div class="filter-controls">
                    <label class="toggle-switch">
                        <input type="checkbox" <?= $show_inactive ? 'checked' : '' ?> onchange="toggleInactiveView(this.checked)">
                        <span class="slider"></span>
                        <span class="label">Mostra Inattivi</span>
                    </label>
                </div>
                <a href="debug-templates.php" class="btn btn-secondary" target="_blank">
                    <i class="fas fa-bug"></i> Debug DB
                </a>
                <button onclick="activateAllTemplates()" class="btn btn-warning">
                    <i class="fas fa-power-off"></i> Attiva Tutti
                </button>
                <button class="btn btn-primary" onclick="showTab('create')">
                    <i class="fas fa-plus"></i> Nuovo Template
                </button>
            </div>
        </div>

        <!-- Messaggi di Sistema -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($_SESSION['success']) ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($_SESSION['error']) ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Tab Navigation -->
        <div class="tab-navigation">
            <button class="tab-btn active" data-tab="list" onclick="showTab('list')">
                <i class="fas fa-list"></i> Lista Template
            </button>
            <button class="tab-btn" data-tab="create" onclick="showTab('create')">
                <i class="fas fa-plus"></i> Nuovo Template
            </button>
            <?php if ($currentTemplate): ?>
            <button class="tab-btn" data-tab="edit" onclick="showTab('edit')">
                <i class="fas fa-edit"></i> Modifica Template
            </button>
            <?php endif; ?>
        </div>

        <!-- Lista Template -->
        <div id="listTab" class="tab-content active">
            <div class="content-body">
                <?php if (empty($templates)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <h3>Nessun template creato</h3>
                        <p>Crea il primo template per i tuoi documenti</p>
                        <button class="btn btn-primary" onclick="showTab('create')">
                            <i class="fas fa-plus"></i> Crea Template
                        </button>
                    </div>
                <?php else: ?>
                    <div class="templates-grid">
                        <?php foreach ($templates as $tpl): ?>
                        <div class="template-card">
                            <div class="template-preview">
                                <div class="mini-header">
                                    <?php if ($tpl['intestazione_config']): ?>
                                        <div class="preview-elements">
                                            <?php 
                                            $config = json_decode($tpl['intestazione_config'], true);
                                            if ($config && isset($config['columns'])) {
                                                echo '<div class="mini-grid">';
                                                foreach ($config['columns'] as $col) {
                                                    echo '<div class="mini-col">';
                                                    if (isset($col['rows'])) {
                                                        foreach ($col['rows'] as $row) {
                                                            if (isset($row['elements'])) {
                                                                foreach ($row['elements'] as $elem) {
                                                                    echo '<div class="mini-element">' . substr($elem['type'], 0, 3) . '</div>';
                                                                }
                                                            }
                                                        }
                                                    }
                                                    echo '</div>';
                                                }
                                                echo '</div>';
                                            }
                                            ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="empty-preview">Header</div>
                                    <?php endif; ?>
                                </div>
                                <div class="mini-content">
                                    <i class="fas fa-file-alt"></i>
                                    <span>Contenuto</span>
                                </div>
                                <div class="mini-footer">
                                    <?php if ($tpl['pie_pagina_config']): ?>
                                        <div class="preview-elements">Footer</div>
                                    <?php else: ?>
                                        <div class="empty-preview">Footer</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="template-info">
                                <h4><?= htmlspecialchars($tpl['nome']) ?></h4>
                                <p><?= htmlspecialchars($tpl['descrizione'] ?? 'Nessuna descrizione') ?></p>
                                <div class="template-meta">
                                    <span class="status <?= $tpl['attivo'] ? 'active' : 'inactive' ?>">
                                        <i class="fas fa-circle"></i>
                                        <?= $tpl['attivo'] ? 'Attivo' : 'Inattivo' ?>
                                    </span>
                                </div>
                            </div>
                            <div class="template-actions">
                                <a href="?action=edit&id=<?= $tpl['id'] ?>" class="action-btn" title="Modifica">
                                    <i class="fas fa-edit"></i>
                                </a>
                                
                                <?php if ($tpl['attivo']): ?>
                                    <button onclick="toggleTemplateStatus(<?= $tpl['id'] ?>, 'deactivate')" class="action-btn warning-btn" title="Disattiva">
                                        <i class="fas fa-eye-slash"></i>
                                    </button>
                                <?php else: ?>
                                    <button onclick="toggleTemplateStatus(<?= $tpl['id'] ?>, 'activate')" class="action-btn success-btn" title="Attiva">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                <?php endif; ?>
                                
                                <button onclick="duplicateTemplate(<?= $tpl['id'] ?>)" class="action-btn" title="Duplica">
                                    <i class="fas fa-copy"></i>
                                </button>
                                <button onclick="deleteTemplate(<?= $tpl['id'] ?>)" class="action-btn delete-btn" title="Elimina">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Nuovo Template / Modifica Template -->
        <div id="<?= $currentTemplate ? 'editTab' : 'createTab' ?>" class="tab-content">
            <div class="template-builder-container">
                <form id="templateForm" method="POST" class="template-form">
                    <input type="hidden" name="action" value="<?= $currentTemplate ? 'update' : 'create' ?>">
                    <?php if ($currentTemplate): ?>
                    <input type="hidden" name="template_id" value="<?= $currentTemplate['id'] ?>">
                    <?php endif; ?>
                    
                    <!-- Configurazione Base Template -->
                    <div class="form-section">
                        <h3><i class="fas fa-cog"></i> Configurazione Template</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="nome">Nome Template *</label>
                                <input type="text" id="nome" name="nome" class="form-control" required
                                       value="<?= htmlspecialchars($currentTemplate['nome'] ?? '') ?>"
                                       placeholder="es. Template Fattura">
                            </div>
                            <div class="form-group">
                                <label for="tipo_template">Tipo Template</label>
                                <select id="tipo_template" name="tipo_template" class="form-control">
                                    <option value="globale">Template Globale (disponibile per tutte le aziende)</option>
                                    <option value="personalizzato">Template Personalizzabile</option>
                                </select>
                                <small class="form-text text-muted">
                                    I template globali sono disponibili per tutte le aziende. L'associazione alle aziende viene gestita in "Gestione Moduli".
                                </small>
                            </div>
                            <div class="form-group span-2">
                                <label for="descrizione">Descrizione</label>
                                <textarea id="descrizione" name="descrizione" class="form-control" rows="2"
                                          placeholder="Breve descrizione del template"><?= htmlspecialchars($currentTemplate['descrizione'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Drag and Drop Builder -->
                    <div class="dragdrop-builder">
                        <div class="builder-layout">
                            <!-- Palette Elementi -->
                            <div class="elements-palette">
                                <h4><i class="fas fa-puzzle-piece"></i> Elementi Disponibili</h4>
                                <div class="palette-sections">
                                    <div class="palette-section">
                                        <h5>Dati Documento</h5>
                                        <div class="element-item" data-type="titolo_documento">
                                            <i class="fas fa-heading"></i>
                                            <span>Nome Documento</span>
                                        </div>
                                        <div class="element-item" data-type="codice_documento">
                                            <i class="fas fa-barcode"></i>
                                            <span>Codice Documento</span>
                                        </div>
                                        <div class="element-item" data-type="numero_versione">
                                            <i class="fas fa-code-branch"></i>
                                            <span>Versione</span>
                                        </div>
                                        <div class="element-item" data-type="data_creazione">
                                            <i class="fas fa-calendar-plus"></i>
                                            <span>Data Creazione</span>
                                        </div>
                                        <div class="element-item" data-type="data_revisione">
                                            <i class="fas fa-calendar-edit"></i>
                                            <span>Data Revisione</span>
                                        </div>
                                    </div>
                                    
                                    <div class="palette-section">
                                        <h5>Dati Azienda</h5>
                                        <div class="element-item" data-type="logo">
                                            <i class="fas fa-image"></i>
                                            <span>Logo Aziendale</span>
                                        </div>
                                        <div class="element-item" data-type="azienda_nome">
                                            <i class="fas fa-building"></i>
                                            <span>Nome Azienda</span>
                                        </div>
                                        <div class="element-item" data-type="copyright">
                                            <i class="fas fa-copyright"></i>
                                            <span>Copyright</span>
                                        </div>
                                    </div>
                                    
                                    <div class="palette-section">
                                        <h5>Sistema</h5>
                                        <div class="element-item" data-type="numero_pagine">
                                            <i class="fas fa-file-alt"></i>
                                            <span>Numero Pagina</span>
                                        </div>
                                        <div class="element-item" data-type="testo_libero">
                                            <i class="fas fa-font"></i>
                                            <span>Testo Libero</span>
                                        </div>
                                        <div class="element-item" data-type="separatore">
                                            <i class="fas fa-minus"></i>
                                            <span>Separatore</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Template Canvas -->
                            <div class="template-canvas">
                                <!-- Intestazione -->
                                <div class="canvas-section">
                                    <div class="section-header">
                                        <h4><i class="fas fa-arrow-up"></i> Intestazione</h4>
                                        <div class="section-controls">
                                            <label>Colonne:</label>
                                            <button type="button" class="btn-control" onclick="changeColumns('header', -1)">-</button>
                                            <span id="headerColumnCount">1</span>
                                            <button type="button" class="btn-control" onclick="changeColumns('header', 1)">+</button>
                                        </div>
                                    </div>
                                    <div class="drop-zone-container" id="headerContainer">
                                        <div class="column-grid" id="headerGrid">
                                            <div class="grid-column" data-column="0">
                                                <div class="drop-zone" data-section="header" data-column="0" data-row="0">
                                                    <div class="drop-placeholder">
                                                        <i class="fas fa-plus"></i>
                                                        <span>Trascina elementi qui</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Anteprima Contenuto -->
                                <div class="content-preview">
                                    <div class="content-placeholder">
                                        <i class="fas fa-file-alt"></i>
                                        <span>Contenuto del documento</span>
                                    </div>
                                </div>

                                <!-- Piè di Pagina -->
                                <div class="canvas-section">
                                    <div class="section-header">
                                        <h4><i class="fas fa-arrow-down"></i> Piè di Pagina</h4>
                                        <div class="section-controls">
                                            <label>Colonne:</label>
                                            <button type="button" class="btn-control" onclick="changeColumns('footer', -1)">-</button>
                                            <span id="footerColumnCount">1</span>
                                            <button type="button" class="btn-control" onclick="changeColumns('footer', 1)">+</button>
                                        </div>
                                    </div>
                                    <div class="drop-zone-container" id="footerContainer">
                                        <div class="column-grid" id="footerGrid">
                                            <div class="grid-column" data-column="0">
                                                <div class="drop-zone" data-section="footer" data-column="0" data-row="0">
                                                    <div class="drop-placeholder">
                                                        <i class="fas fa-plus"></i>
                                                        <span>Trascina elementi qui</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Anteprima Live -->
                            <div class="live-preview">
                                <h4><i class="fas fa-eye"></i> Anteprima Live</h4>
                                <div class="preview-container">
                                    <div class="preview-header" id="livePreviewHeader">
                                        <div class="preview-empty">Intestazione vuota</div>
                                    </div>
                                    <div class="preview-content">
                                        <i class="fas fa-file-alt"></i>
                                        <p>Contenuto del documento</p>
                                    </div>
                                    <div class="preview-footer" id="livePreviewFooter">
                                        <div class="preview-empty">Piè di pagina vuoto</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            <?= $currentTemplate ? 'Aggiorna Template' : 'Crea Template' ?>
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="showTab('list')">
                            <i class="fas fa-arrow-left"></i>
                            Torna alla Lista
                        </button>
                        <button type="button" class="btn btn-info" onclick="previewTemplate()">
                            <i class="fas fa-eye"></i>
                            Anteprima Completa
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Hidden inputs per i dati del template -->
    <input type="hidden" id="headerConfigData" name="intestazione_config" value="">
    <input type="hidden" id="footerConfigData" name="pie_pagina_config" value="">

    <!-- Includi CSS e JavaScript -->
    <link rel="stylesheet" href="<?= APP_PATH ?>/assets/css/template-builder.css">
    <script src="<?= APP_PATH ?>/assets/js/template-builder-dragdrop.js"></script>
    
    <script>
        // Inizializza il builder se siamo in modalità edit
        <?php if ($currentTemplate): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Carica configurazione esistente
            const headerConfig = <?= json_encode($currentTemplate['intestazione_config'] ?? '{}') ?>;
            const footerConfig = <?= json_encode($currentTemplate['pie_pagina_config'] ?? '{}') ?>;
            
            if (headerConfig && typeof headerConfig === 'object') {
                loadTemplateConfiguration('header', headerConfig);
            }
            if (footerConfig && typeof footerConfig === 'object') {
                loadTemplateConfiguration('footer', footerConfig);
            }
            
            showTab('edit');
        });
        <?php endif; ?>
    </script>
</body>
</html>