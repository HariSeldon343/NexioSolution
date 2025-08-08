<?php
/**
 * Gestione Template - Reindirizzamento al nuovo Template Builder
 */

require_once __DIR__ . '/backend/config/config.php';

// Reindirizza direttamente al nuovo template builder drag-and-drop
header('Location: ' . APP_PATH . '/template-builder-dragdrop.php');
exit;

// Il codice seguente non verrà mai eseguito, ma lo manteniamo per compatibilità

// Connessione database
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('Errore connessione database: ' . $e->getMessage());
}

$template = new Template($pdo);

// Gestione azioni
$action = $_GET['action'] ?? 'list';
$template_id = $_GET['id'] ?? null;

// Carica lista aziende per il dropdown
$stmt = $pdo->query("SELECT id, nome FROM aziende ORDER BY nome");
$aziende = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Carica templates esistenti
try {
    // Verifica se la tabella esiste prima di caricare i template
    $stmt = $pdo->query("SHOW TABLES LIKE 'templates'");
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        $templates = $template->getAll();
    } else {
        $templates = []; // Se la tabella non esiste, lista vuota (verrà creata più tardi se necessario)
    }
} catch (Exception $e) {
    error_log("Errore caricamento templates: " . $e->getMessage());
    $templates = [];
}

// Debug: Aggiungi informazioni dettagliate
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px; border: 1px solid #ccc;'>";
    echo "<h4>Debug Info:</h4>";
    echo "Action: " . htmlspecialchars($action) . "<br>";
    echo "Template ID: " . htmlspecialchars($template_id) . "<br>";
    
    // Verifica se la tabella templates esiste
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'templates'");
        $tableExists = $stmt->fetch();
        echo "Tabella templates esiste: " . ($tableExists ? 'SÌ' : 'NO') . "<br>";
        
        if ($tableExists) {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM templates");
            $count = $stmt->fetch()['count'];
            echo "Numero templates nel database: " . $count . "<br>";
        }
    } catch (Exception $e) {
        echo "Errore verifica tabella: " . $e->getMessage() . "<br>";
    }
    echo "</div>";
}

$currentTemplate = null;
if ($template_id && $action === 'edit') {
    try {
        // Prima verifica se la tabella esiste
        $stmt = $pdo->query("SHOW TABLES LIKE 'templates'");
        $tableExists = $stmt->fetch();
        
        if (!$tableExists) {
            // La tabella non esiste, creiamola
            $createTableSQL = "
            CREATE TABLE IF NOT EXISTS templates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(255) NOT NULL,
                descrizione TEXT,
                azienda_id INT NULL,
                intestazione_config JSON,
                pie_pagina_config JSON,
                stili_css TEXT,
                attivo TINYINT(1) DEFAULT 1,
                creato_da INT,
                data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                ultima_modifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (azienda_id) REFERENCES aziende(id),
                FOREIGN KEY (creato_da) REFERENCES utenti(id)
            )";
            $pdo->exec($createTableSQL);
            
            // Inserisci alcuni template di esempio
            $sampleTemplates = [
                [
                    'nome' => 'Template Standard Aziendale',
                    'descrizione' => 'Template base con logo aziendale, titolo documento e informazioni standard',
                    'azienda_id' => null,
                    'intestazione_config' => '{"columns": [{"rows": [{"type": "logo"}, {"type": "azienda_nome"}]}, {"rows": [{"type": "titolo_documento"}, {"type": "codice_documento"}]}]}',
                    'pie_pagina_config' => '{"columns": [{"rows": [{"type": "copyright"}]}, {"rows": [{"type": "numero_pagine"}]}]}',
                    'stili_css' => '.template-header { font-size: 14px; }',
                    'attivo' => 1,
                    'creato_da' => $user['id']
                ],
                [
                    'nome' => 'Template Semplice',
                    'descrizione' => 'Template minimalista con solo le informazioni essenziali',
                    'azienda_id' => null,
                    'intestazione_config' => '{"columns": [{"rows": [{"type": "titolo_documento"}]}]}',
                    'pie_pagina_config' => '{"columns": [{"rows": [{"type": "numero_pagine"}]}]}',
                    'stili_css' => '',
                    'attivo' => 1,
                    'creato_da' => $user['id']
                ]
            ];
            
            $insertSQL = "INSERT INTO templates (nome, descrizione, azienda_id, intestazione_config, pie_pagina_config, stili_css, attivo, creato_da) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($insertSQL);
            
            foreach ($sampleTemplates as $template) {
                $stmt->execute([
                    $template['nome'],
                    $template['descrizione'],
                    $template['azienda_id'],
                    $template['intestazione_config'],
                    $template['pie_pagina_config'],
                    $template['stili_css'],
                    $template['attivo'],
                    $template['creato_da']
                ]);
            }
        }
        
        // Ora prova a caricare il template
        $currentTemplate = $template->getById($template_id);
        if (!$currentTemplate) {
            // Fallback: carica direttamente dal database
            $stmt = $pdo->prepare("SELECT * FROM templates WHERE id = ?");
            $stmt->execute([$template_id]);
            $currentTemplate = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($currentTemplate) {
                // Decodifica JSON
                $currentTemplate['intestazione_config'] = json_decode($currentTemplate['intestazione_config'], true);
                $currentTemplate['pie_pagina_config'] = json_decode($currentTemplate['pie_pagina_config'], true);
            }
        }
    } catch (Exception $e) {
        // Log dell'errore e crea template di test
        error_log("Errore gestione template: " . $e->getMessage());
        $currentTemplate = [
            'id' => $template_id,
            'nome' => 'Template di Test',
            'descrizione' => 'Template di test per la modifica',
            'azienda_id' => null,
            'stili_css' => '',
            'attivo' => 1,
            'intestazione_config' => ['columns' => [['rows' => []]]],
            'pie_pagina_config' => ['columns' => [['rows' => []]]]
        ];
    }
}

$pageTitle = 'Gestione Template';
include dirname(__FILE__) . '/components/header.php';
require_once 'components/page-header.php';
?>

<?php renderPageHeader('Gestione Template', 'Crea e modifica i template documentali', 'file-alt'); ?>

<div class="tabs-container">
    <div class="tabs">
        <button class="tab active" onclick="showTab('list')">
            <i class="fas fa-list"></i>
            Lista Template
        </button>
        <button class="tab" onclick="showTab('create')">
            <i class="fas fa-plus"></i>
            Nuovo Template
        </button>
        <?php if ($currentTemplate): ?>
        <button class="tab" onclick="showTab('edit')">
            <i class="fas fa-edit"></i>
            Modifica Template
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Tab Lista Template -->
<div id="tab-list" class="tab-content active">
    <div class="template-list">
        <?php if (empty($templates)): ?>
            <div class="empty-state">
                <i class="fas fa-file-contract"></i>
                <h3>Nessun template trovato</h3>
                <p>Crea il tuo primo template per iniziare a generare documenti professionali</p>
                <button class="btn btn-primary" onclick="showTab('create')">
                    <i class="fas fa-plus"></i>
                    Crea Primo Template
                </button>
            </div>
        <?php else: ?>
            <div class="templates-grid">
                <?php foreach ($templates as $tmpl): ?>
                <div class="template-card">
                    <div class="template-header">
                        <h3><?= htmlspecialchars($tmpl['nome']) ?></h3>
                        <div class="template-status <?= $tmpl['attivo'] ? 'active' : 'inactive' ?>">
                            <i class="fas fa-circle"></i> <?= $tmpl['attivo'] ? 'Attivo' : 'Disattivo' ?>
                        </div>
                    </div>
                    
                    <div class="template-body">
                        <p><?= htmlspecialchars($tmpl['descrizione'] ?? 'Nessuna descrizione') ?></p>
                        
                        <div class="template-meta">
                            <div class="meta-item">
                                <i class="fas fa-building"></i>
                                <span><?= htmlspecialchars($tmpl['azienda_nome'] ?? 'Tutte le aziende') ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-calendar"></i>
                                <span><?= date('d/m/Y', strtotime($tmpl['data_creazione'])) ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="template-actions">
                        <a href="?action=edit&id=<?= $tmpl['id'] ?>" class="btn btn-sm btn-outline">
                            <i class="fas fa-edit"></i>
                            Modifica
                        </a>
                        <button class="btn btn-sm btn-secondary" onclick="previewTemplate(<?= $tmpl['id'] ?>)">
                            <i class="fas fa-eye"></i>
                            Anteprima
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="deleteTemplate(<?= $tmpl['id'] ?>, '<?= htmlspecialchars($tmpl['nome'], ENT_QUOTES) ?>')">
                            <i class="fas fa-trash"></i>
                            Elimina
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Tab Nuovo Template -->
<div id="tab-create" class="tab-content">
    <form id="templateForm" onsubmit="saveTemplate(event)">
        <div class="form-grid">
            <div class="form-section">
                <h2><i class="fas fa-info-circle"></i> Informazioni Template</h2>
                
                <div class="form-group">
                    <label for="nome">Nome Template *</label>
                    <input type="text" id="nome" name="nome" class="form-control" required 
                           placeholder="Es. Template Standard Aziendale">
                </div>
                
                <div class="form-group">
                    <label for="descrizione">Descrizione</label>
                    <textarea id="descrizione" name="descrizione" class="form-control" rows="3"
                              placeholder="Descrivi l'uso e le caratteristiche del template"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="azienda_id">Azienda</label>
                    <select id="azienda_id" name="azienda_id" class="form-control">
                        <option value="">Tutte le aziende</option>
                        <?php foreach ($aziende as $azienda): ?>
                        <option value="<?= $azienda['id'] ?>"><?= htmlspecialchars($azienda['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="stili_css">CSS Personalizzato</label>
                    <textarea id="stili_css" name="stili_css" class="form-control code-editor" rows="4"
                              placeholder=".template-header { font-size: 14px; }"></textarea>
                </div>
            </div>
            
            <div class="form-section">
                <h2><i class="fas fa-eye"></i> Anteprima Template</h2>
                <div class="preview-container">
                    <div class="preview-header" id="previewHeader">
                        <em>Intestazione sarà visualizzata qui</em>
                    </div>
                    <div class="preview-content">
                        <i class="fas fa-file-alt"></i>
                        <p>Contenuto del documento</p>
                    </div>
                    <div class="preview-footer" id="previewFooter">
                        <em>Piè di pagina sarà visualizzato qui</em>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="template-builder">
            <h2><i class="fas fa-layer-group"></i> Costruttore Template</h2>
            
            <!-- Intestazione -->
            <div class="section-builder">
                <div class="section-header">
                    <h3><i class="fas fa-arrow-up"></i> Intestazione Documento</h3>
                    <div class="section-controls">
                        <span class="control-label">Colonne:</span>
                        <button type="button" class="btn btn-sm btn-outline" onclick="changeColumns('header', -1)">-</button>
                        <span id="headerColumnCount">1</span>
                        <button type="button" class="btn btn-sm btn-outline" onclick="changeColumns('header', 1)">+</button>
                    </div>
                </div>
                <div id="headerBuilder" class="builder-content"></div>
            </div>
            
            <!-- Piè di pagina -->
            <div class="section-builder">
                <div class="section-header">
                    <h3><i class="fas fa-arrow-down"></i> Piè di Pagina</h3>
                    <div class="section-controls">
                        <span class="control-label">Colonne:</span>
                        <button type="button" class="btn btn-sm btn-outline" onclick="changeColumns('footer', -1)">-</button>
                        <span id="footerColumnCount">1</span>
                        <button type="button" class="btn btn-sm btn-outline" onclick="changeColumns('footer', 1)">+</button>
                    </div>
                </div>
                <div id="footerBuilder" class="builder-content"></div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Salva Template
                </button>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Dashboard
                </a>
            </div>
        </div>
    </form>
</div>

<!-- Tab Modifica Template -->
<?php if ($currentTemplate): ?>
<div id="tab-edit" class="tab-content">
    <form id="templateEditForm" onsubmit="updateTemplate(event, <?= $currentTemplate['id'] ?>)">
        <div class="form-grid">
            <div class="form-section">
                <h2><i class="fas fa-info-circle"></i> Informazioni Template</h2>
                
                <div class="form-group">
                    <label for="nome_edit">Nome Template *</label>
                    <input type="text" id="nome_edit" name="nome" class="form-control" required 
                           value="<?= htmlspecialchars($currentTemplate['nome']) ?>"
                           placeholder="Es. Template Standard Aziendale">
                </div>
                
                <div class="form-group">
                    <label for="descrizione_edit">Descrizione</label>
                    <textarea id="descrizione_edit" name="descrizione" class="form-control" rows="3"
                              placeholder="Descrivi l'uso e le caratteristiche del template"><?= htmlspecialchars($currentTemplate['descrizione'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="azienda_id_edit">Azienda</label>
                    <select id="azienda_id_edit" name="azienda_id" class="form-control">
                        <option value="">Tutte le aziende</option>
                        <?php foreach ($aziende as $azienda): ?>
                        <option value="<?= $azienda['id'] ?>" <?= $currentTemplate['azienda_id'] == $azienda['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($azienda['nome']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="stili_css_edit">CSS Personalizzato</label>
                    <textarea id="stili_css_edit" name="stili_css" class="form-control code-editor" rows="4"
                              placeholder=".template-header { font-size: 14px; }"><?= htmlspecialchars($currentTemplate['stili_css'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="attivo_edit" name="attivo" <?= $currentTemplate['attivo'] ? 'checked' : '' ?>>
                        Template attivo
                    </label>
                </div>
            </div>
            
            <div class="form-section">
                <h2><i class="fas fa-eye"></i> Anteprima Template</h2>
                <div class="preview-container">
                    <div class="preview-header" id="previewHeaderEdit">
                        <em>Intestazione sarà visualizzata qui</em>
                    </div>
                    <div class="preview-content">
                        <i class="fas fa-file-alt"></i>
                        <p>Contenuto del documento</p>
                    </div>
                    <div class="preview-footer" id="previewFooterEdit">
                        <em>Piè di pagina sarà visualizzato qui</em>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="template-builder">
            <h2><i class="fas fa-layer-group"></i> Costruttore Template</h2>
            
            <!-- Intestazione -->
            <div class="section-builder">
                <div class="section-header">
                    <h3><i class="fas fa-arrow-up"></i> Intestazione Documento</h3>
                    <div class="section-controls">
                        <span class="control-label">Colonne:</span>
                        <button type="button" class="btn btn-sm btn-outline" onclick="changeColumnsEdit('header', -1)">-</button>
                        <span id="headerColumnCountEdit"><?= count($currentTemplate['intestazione_config']['columns'] ?? [1]) ?></span>
                        <button type="button" class="btn btn-sm btn-outline" onclick="changeColumnsEdit('header', 1)">+</button>
                    </div>
                </div>
                <div id="headerBuilderEdit" class="builder-content">
                    <div class="template-elements-editor">
                        <div class="elements-grid" id="headerElementsEdit">
                            <!-- Elementi esistenti verranno caricati qui -->
                        </div>
                        <button type="button" class="btn btn-sm btn-outline" onclick="showAddElementModal('intestazione')">
                            <i class="fas fa-plus"></i> Aggiungi Elemento
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Piè di pagina -->
            <div class="section-builder">
                <div class="section-header">
                    <h3><i class="fas fa-arrow-down"></i> Piè di Pagina</h3>
                    <div class="section-controls">
                        <span class="control-label">Colonne:</span>
                        <button type="button" class="btn btn-sm btn-outline" onclick="changeColumnsEdit('footer', -1)">-</button>
                        <span id="footerColumnCountEdit"><?= count($currentTemplate['pie_pagina_config']['columns'] ?? [1]) ?></span>
                        <button type="button" class="btn btn-sm btn-outline" onclick="changeColumnsEdit('footer', 1)">+</button>
                    </div>
                </div>
                <div id="footerBuilderEdit" class="builder-content">
                    <div class="template-elements-editor">
                        <div class="elements-grid" id="footerElementsEdit">
                            <!-- Elementi esistenti verranno caricati qui -->
                        </div>
                        <button type="button" class="btn btn-sm btn-outline" onclick="showAddElementModal('pie_pagina')">
                            <i class="fas fa-plus"></i> Aggiungi Elemento
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Aggiorna Template
                </button>
                <button type="button" class="btn btn-secondary" onclick="showTab('list')">
                    <i class="fas fa-arrow-left"></i>
                    Torna alla Lista
                </button>
                <?php if ($auth->isSuperAdmin()): ?>
                <button type="button" class="multi-azienda-btn" onclick="showMultiAziendaModal()">
                    <i class="fas fa-building"></i>
                    Associa a Più Aziende
                </button>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Modal per aggiunta/modifica elementi -->
<div id="addElementModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Aggiungi Elemento</h3>
            <button type="button" class="modal-close" onclick="closeModal()">×</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Tipo Elemento</label>
                <select id="elementType" class="form-control">
                    <optgroup label="Dati Documento">
                        <option value="titolo_documento">Titolo Documento</option>
                        <option value="codice_documento">Codice Documento</option>
                        <option value="autore_documento">Autore Documento</option>
                        <option value="stato_documento">Stato Documento</option>
                        <option value="data_creazione">Data Creazione</option>
                        <option value="ultima_modifica">Ultima Modifica</option>
                        <option value="data_revisione">Data Revisione</option>
                        <option value="numero_versione">Numero Versione</option>
                    </optgroup>
                    <optgroup label="Dati Azienda">
                        <option value="logo">Logo Aziendale</option>
                        <option value="azienda_nome">Nome Azienda</option>
                        <option value="azienda_indirizzo">Indirizzo Azienda</option>
                        <option value="azienda_contatti">Contatti Azienda</option>
                        <option value="copyright">Copyright</option>
                    </optgroup>
                    <optgroup label="Sistema">
                        <option value="numero_pagine">Numero Pagine</option>
                        <option value="data_corrente">Data Corrente</option>
                        <option value="testo_libero">Testo Libero</option>
                    </optgroup>
                </select>
            </div>
            <div class="form-group" id="elementContent" style="display: none;">
                <label>Contenuto</label>
                <input type="text" id="elementContentValue" class="form-control" placeholder="Inserisci il contenuto">
            </div>
            <div class="form-group" id="logoUrl" style="display: none;">
                <label>URL Logo</label>
                <input type="text" id="logoUrlValue" class="form-control" placeholder="es. /assets/logo.png">
            </div>
            <div class="form-group">
                <label>Stile CSS</label>
                <input type="text" id="elementStyle" class="form-control" placeholder="es. font-size: 14px; color: #333;">
            </div>
            <div class="form-group">
                <label>Allineamento</label>
                <select id="elementAlign" class="form-control">
                    <option value="left">Sinistra</option>
                    <option value="center">Centro</option>
                    <option value="right">Destra</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Annulla</button>
            <button type="button" class="btn btn-primary" id="saveElementBtn" onclick="saveElement()">Aggiungi</button>
        </div>
    </div>
</div>

<!-- Modal per associazione template multi-azienda -->
<div id="multiAziendaModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Associa Template a Più Aziende</h3>
            <button type="button" class="modal-close" onclick="closeMultiAziendaModal()">×</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Seleziona Aziende</label>
                <div class="aziende-checkboxes" id="aziendeCheckboxes">
                    <?php foreach ($aziende as $azienda): ?>
                    <label class="checkbox-item">
                        <input type="checkbox" name="aziende[]" value="<?= $azienda['id'] ?>">
                        <?= htmlspecialchars($azienda['nome']) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeMultiAziendaModal()">Annulla</button>
            <button type="button" class="btn btn-primary" onclick="cloneToAziende()">Associa Template</button>
        </div>
    </div>
</div>

<style>
/* Stili aggiuntivi specifici per la gestione template */
.content-header {
    margin-bottom: 30px;
}

.content-header h1 {
    color: #2d3748;
    margin-bottom: 10px;
    font-size: 28px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 15px;
}

.content-header p {
    color: #718096;
    font-size: 16px;
}

.tabs-container {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 30px;
    overflow: hidden;
    border: 1px solid #e2e8f0;
}

.tabs {
    display: flex;
    border-bottom: 1px solid #e2e8f0;
}

.tab {
    flex: 1;
    padding: 15px 20px;
    background: #f8f9fa;
    border: none;
    cursor: pointer;
    font-size: 16px;
    font-weight: 500;
    color: #666;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.tab.active {
    background: #4299e1;
    color: white;
}

.tab:hover:not(.active) {
    background: #e9ecef;
    color: #495057;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #718096;
    background: white;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.empty-state i {
    font-size: 64px;
    margin-bottom: 20px;
    color: #e2e8f0;
}

.empty-state h3 {
    margin-bottom: 10px;
    color: #2d3748;
}

.templates-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.template-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 20px;
    transition: all 0.2s ease;
}

.template-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border-color: #4299e1;
}

.template-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.template-header h3 {
    color: #2d3748;
    margin: 0;
    font-size: 18px;
}

.template-status {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.template-status.active {
    background: #c6f6d5;
    color: #22543d;
}

.template-status.inactive {
    background: #fed7d7;
    color: #742a2a;
}

.template-body p {
    color: #718096;
    margin-bottom: 15px;
}

.template-meta {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    color: #718096;
}

.template-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-bottom: 30px;
}

.form-section {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 25px;
}

.form-section h2 {
    color: #2d3748;
    margin-bottom: 20px;
    font-size: 18px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #495057;
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: #4299e1;
    box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
}

.preview-container {
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    overflow: hidden;
}

.preview-header,
.preview-footer {
    padding: 15px;
    background: #f8f9fa;
    border-bottom: 1px solid #e2e8f0;
    min-height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.preview-footer {
    border-bottom: none;
    border-top: 1px solid #e2e8f0;
}

.preview-content {
    padding: 40px 20px;
    text-align: center;
    color: #718096;
    background: white;
}

.preview-content i {
    font-size: 32px;
    margin-bottom: 10px;
    color: #e2e8f0;
}

.template-builder {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 25px;
}

.template-builder h2 {
    color: #2d3748;
    margin-bottom: 25px;
    font-size: 20px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-builder {
    margin-bottom: 30px;
    padding: 20px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.section-header h3 {
    color: #2d3748;
    font-size: 18px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-controls {
    display: flex;
    align-items: center;
    gap: 10px;
}

.control-label {
    font-size: 14px;
    font-weight: 500;
    color: #495057;
}

.form-actions {
    display: flex;
    gap: 15px;
    justify-content: flex-start;
    margin-top: 30px;
}

.btn-outline {
    background: white;
    color: #4299e1;
    border: 1px solid #4299e1;
}

.btn-outline:hover {
    background: #4299e1;
    color: white;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

.btn-danger {
    background: #e53e3e;
    color: white;
}

.btn-danger:hover {
    background: #c53030;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 12px;
    padding: 30px;
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.modal-header h3 {
    font-size: 20px;
    font-weight: 600;
    color: #2d3748;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    color: #6c757d;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-footer {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 20px;
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .tabs {
        flex-direction: column;
    }
    
    .template-actions {
        justify-content: center;
    }
}

/* Stili per l'editor di elementi */
.template-elements-editor {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 15px;
    background: #f8f9fa;
}

.elements-grid {
    margin-bottom: 15px;
}

.element-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 15px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    margin-bottom: 8px;
    transition: all 0.2s ease;
}

.element-item:hover {
    border-color: #4299e1;
    box-shadow: 0 2px 4px rgba(66, 153, 225, 0.1);
}

.element-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.element-type {
    background: #4299e1;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
}

.element-content {
    color: #718096;
    font-size: 14px;
}

.element-actions {
    display: flex;
    gap: 5px;
}

.element-actions button {
    padding: 4px 8px;
    font-size: 12px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-edit-element {
    background: #38a169;
    color: white;
}

.btn-edit-element:hover {
    background: #2f855a;
}

.btn-delete-element {
    background: #e53e3e;
    color: white;
}

.btn-delete-element:hover {
    background: #c53030;
}

/* Stili per checkbox aziende */
.aziende-checkboxes {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 10px;
}

.checkbox-item {
    display: block;
    padding: 8px 0;
    cursor: pointer;
    border-bottom: 1px solid #f1f5f9;
}

.checkbox-item:last-child {
    border-bottom: none;
}

.checkbox-item input[type="checkbox"] {
    margin-right: 8px;
}

/* Stili per multi-azienda */
.template-multi-actions {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #e2e8f0;
}

.multi-azienda-btn {
    background: #805ad5;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s ease;
}

.multi-azienda-btn:hover {
    background: #6b46c1;
}
</style>

</main>
</div>

<script>
// JavaScript per la gestione dei tab e delle funzioni del template
function showTab(tabName) {
    // Nasconde tutti i tab content
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Rimuove active da tutti i tab button
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Mostra il tab selezionato
    const targetTab = document.getElementById('tab-' + tabName);
    if (targetTab) {
        targetTab.classList.add('active');
    }
    
    // Attiva il button corrispondente (se l'evento esiste)
    if (event && event.target) {
        event.target.classList.add('active');
    } else {
        // Fallback: trova il button per il tab specificato
        const tabButton = document.querySelector(`.tab[onclick="showTab('${tabName}')"]`);
        if (tabButton) {
            tabButton.classList.add('active');
        }
    }
}

function saveTemplate(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    formData.append('action', 'create');
    
    // Aggiungi configurazioni JSON
    const intestazione_config = {
        columns: [{
            width: 100,
            rows: []
        }]
    };
    const pie_pagina_config = {
        columns: [{
            width: 100,
            rows: []
        }]
    };
    
    formData.append('intestazione_config', JSON.stringify(intestazione_config));
    formData.append('pie_pagina_config', JSON.stringify(pie_pagina_config));
    
    fetch('/piattaforma-collaborativa/backend/api/template-api.php?action=create', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message || 'Template creato con successo');
            window.location.href = 'gestione-template.php';
        } else {
            alert('Errore: ' + (data.error || 'Impossibile creare il template'));
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        alert('Errore di connessione al server');
    });
}

function previewTemplate(templateId) {
    // Apri una nuova finestra per l'anteprima
    const width = 800;
    const height = 600;
    const left = (screen.width - width) / 2;
    const top = (screen.height - height) / 2;
    
    const previewWindow = window.open(
        'about:blank',
        'templatePreview',
        `width=${width},height=${height},left=${left},top=${top},toolbar=no,menubar=no,scrollbars=yes,resizable=yes`
    );
    
    // Carica l'anteprima via API
    fetch(`/piattaforma-collaborativa/backend/api/template-api.php?action=preview&id=${templateId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const previewHtml = `
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>Anteprima Template</title>
                        <style>
                            body { margin: 0; padding: 20px; font-family: Arial, sans-serif; }
                            .page { 
                                width: 210mm; 
                                min-height: 297mm; 
                                margin: 0 auto; 
                                background: white; 
                                box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
                                padding: 20mm;
                                box-sizing: border-box;
                            }
                            .header { border-bottom: 2px solid #ddd; padding-bottom: 20px; margin-bottom: 20px; }
                            .footer { border-top: 2px solid #ddd; padding-top: 20px; margin-top: 20px; }
                            .content { min-height: 200mm; }
                            ${data.css || ''}
                        </style>
                    </head>
                    <body>
                        <div class="page">
                            <div class="header">${data.header || '<p>Header vuoto</p>'}</div>
                            <div class="content">
                                <h1>Contenuto del Documento</h1>
                                <p>Questo è un esempio di come apparirà il documento con questo template.</p>
                                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit...</p>
                            </div>
                            <div class="footer">${data.footer || '<p>Footer vuoto</p>'}</div>
                        </div>
                    </body>
                    </html>
                `;
                previewWindow.document.write(previewHtml);
                previewWindow.document.close();
            } else {
                previewWindow.close();
                alert('Errore nel caricamento dell\'anteprima: ' + (data.error || 'Errore sconosciuto'));
            }
        })
        .catch(error => {
            previewWindow.close();
            console.error('Errore:', error);
            alert('Errore di connessione al server');
        });
}

function deleteTemplate(templateId, templateName) {
    if (!confirm(`Sei sicuro di voler eliminare il template "${templateName || 'Template #' + templateId}"?`)) {
        return;
    }
    
    fetch('/piattaforma-collaborativa/backend/api/template-api.php', {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            id: templateId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message || 'Template eliminato con successo');
            // Forza un refresh completo della pagina per aggirare la cache
            window.location.href = 'gestione-template.php?refresh=' + Date.now();
        } else {
            alert('Errore: ' + (data.error || 'Impossibile eliminare il template'));
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        alert('Errore di connessione al server');
    });
}

function changeColumns(section, delta) {
    const countElement = document.getElementById(section + 'ColumnCount');
    let currentCount = parseInt(countElement.textContent);
    let newCount = currentCount + delta;
    
    if (newCount >= 1 && newCount <= 4) {
        countElement.textContent = newCount;
        console.log(`${section} columns changed to:`, newCount);
    }
}

function changeColumnsEdit(section, delta) {
    const countElement = document.getElementById(section + 'ColumnCountEdit');
    let currentCount = parseInt(countElement.textContent);
    let newCount = currentCount + delta;
    
    if (newCount >= 1 && newCount <= 4) {
        countElement.textContent = newCount;
        console.log(`${section} edit columns changed to:`, newCount);
    }
}

function updateTemplate(event, templateId) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    formData.append('action', 'update');
    formData.append('id', templateId);
    
    // Aggiungi configurazioni JSON (per ora vuote, verranno aggiornate dal builder)
    const intestazione_config = {
        columns: [{
            width: 100,
            rows: []
        }]
    };
    const pie_pagina_config = {
        columns: [{
            width: 100,
            rows: []
        }]
    };
    
    // Se ci sono dati esistenti, usali
    if (typeof templateDataEdit !== 'undefined') {
        if (templateDataEdit.intestazione_config) {
            formData.append('intestazione_config', JSON.stringify(templateDataEdit.intestazione_config));
        } else {
            formData.append('intestazione_config', JSON.stringify(intestazione_config));
        }
        
        if (templateDataEdit.pie_pagina_config) {
            formData.append('pie_pagina_config', JSON.stringify(templateDataEdit.pie_pagina_config));
        } else {
            formData.append('pie_pagina_config', JSON.stringify(pie_pagina_config));
        }
    } else {
        formData.append('intestazione_config', JSON.stringify(intestazione_config));
        formData.append('pie_pagina_config', JSON.stringify(pie_pagina_config));
    }
    
    // Gestione checkbox attivo
    const attivoCheckbox = document.getElementById('attivo_edit');
    formData.append('attivo', attivoCheckbox && attivoCheckbox.checked ? '1' : '0');
    
    fetch('/piattaforma-collaborativa/backend/api/template-api.php?action=update', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message || 'Template aggiornato con successo');
            // Ricarica la pagina per mostrare i dati aggiornati
            window.location.href = 'gestione-template.php?action=edit&id=' + templateId;
        } else {
            alert('Errore: ' + (data.error || 'Impossibile aggiornare il template'));
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        alert('Errore di connessione al server');
    });
}

function closeModal() {
    document.getElementById('addElementModal').classList.remove('active');
}

// Variabili globali per l'editor di elementi
let currentSection = null;
let currentEditElement = null;

function showAddElementModal(section) {
    currentSection = section;
    currentEditElement = null;
    
    document.getElementById('modalTitle').textContent = 'Aggiungi Elemento';
    document.getElementById('saveElementBtn').textContent = 'Aggiungi';
    document.getElementById('saveElementBtn').onclick = saveElement;
    
    // Reset form
    document.getElementById('elementType').value = '';
    document.getElementById('elementContentValue').value = '';
    document.getElementById('logoUrlValue').value = '';
    document.getElementById('elementStyle').value = '';
    document.getElementById('elementAlign').value = 'left';
    
    document.getElementById('addElementModal').classList.add('active');
}

function editElement(section, columnIndex, rowIndex, elementData) {
    currentSection = section;
    currentEditElement = { columnIndex, rowIndex };
    
    document.getElementById('modalTitle').textContent = 'Modifica Elemento';
    document.getElementById('saveElementBtn').textContent = 'Aggiorna';
    document.getElementById('saveElementBtn').onclick = saveElement;
    
    // Popola form con dati esistenti
    document.getElementById('elementType').value = elementData.type || '';
    document.getElementById('elementContentValue').value = elementData.content || '';
    document.getElementById('logoUrlValue').value = elementData.url || '';
    document.getElementById('elementStyle').value = elementData.style || '';
    document.getElementById('elementAlign').value = elementData.align || 'left';
    
    // Mostra/nascondi campi appropriati
    toggleElementFields();
    
    document.getElementById('addElementModal').classList.add('active');
}

function saveElement() {
    const elementType = document.getElementById('elementType').value;
    if (!elementType) {
        alert('Seleziona un tipo di elemento');
        return;
    }
    
    const elementData = {
        type: elementType,
        content: document.getElementById('elementContentValue').value,
        url: document.getElementById('logoUrlValue').value,
        style: document.getElementById('elementStyle').value,
        align: document.getElementById('elementAlign').value
    };
    
    if (currentEditElement) {
        // Modalità modifica
        updateTemplateElement(currentEditElement.columnIndex, currentEditElement.rowIndex, elementData);
    } else {
        // Modalità aggiunta
        addTemplateElement(0, elementData); // Default colonna 0
    }
    
    closeModal();
}

function updateTemplateElement(columnIndex, rowIndex, elementData) {
    const templateId = <?= $currentTemplate['id'] ?? 'null' ?>;
    if (!templateId) {
        alert('Template ID non trovato');
        return;
    }
    
    fetch('/piattaforma-collaborativa/backend/api/template-elements-api.php?action=update-element', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            template_id: templateId,
            section: currentSection,
            column_index: columnIndex,
            row_index: rowIndex,
            element_data: elementData
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Elemento aggiornato con successo');
            loadTemplateElements();
        } else {
            alert('Errore: ' + (data.error || 'Errore sconosciuto'));
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        alert('Errore di connessione');
    });
}

function addTemplateElement(columnIndex, elementData) {
    const templateId = <?= $currentTemplate['id'] ?? 'null' ?>;
    if (!templateId) {
        alert('Template ID non trovato');
        return;
    }
    
    fetch('/piattaforma-collaborativa/backend/api/template-elements-api.php?action=add-element', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            template_id: templateId,
            section: currentSection,
            column_index: columnIndex,
            element_data: elementData
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Elemento aggiunto con successo');
            loadTemplateElements();
        } else {
            alert('Errore: ' + (data.error || 'Errore sconosciuto'));
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        alert('Errore di connessione');
    });
}

function deleteTemplateElement(columnIndex, rowIndex) {
    if (!confirm('Sei sicuro di voler eliminare questo elemento?')) {
        return;
    }
    
    const templateId = <?= $currentTemplate['id'] ?? 'null' ?>;
    if (!templateId) {
        alert('Template ID non trovato');
        return;
    }
    
    fetch('/piattaforma-collaborativa/backend/api/template-elements-api.php?action=remove-element', {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            template_id: templateId,
            section: currentSection,
            column_index: columnIndex,
            row_index: rowIndex
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Elemento eliminato con successo');
            loadTemplateElements();
        } else {
            alert('Errore: ' + (data.error || 'Errore sconosciuto'));
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        alert('Errore di connessione');
    });
}

function loadTemplateElements() {
    if (!templateDataEdit) return;
    
    // Carica elementi header
    loadSectionElements('intestazione', 'headerElementsEdit', templateDataEdit.intestazione_config);
    
    // Carica elementi footer  
    loadSectionElements('pie_pagina', 'footerElementsEdit', templateDataEdit.pie_pagina_config);
}

function loadSectionElements(section, containerId, config) {
    const container = document.getElementById(containerId);
    if (!container || !config || !config.columns) return;
    
    container.innerHTML = '';
    
    config.columns.forEach((column, columnIndex) => {
        if (column.rows) {
            column.rows.forEach((element, rowIndex) => {
                const elementDiv = createElementDiv(section, columnIndex, rowIndex, element);
                container.appendChild(elementDiv);
            });
        }
    });
}

function createElementDiv(section, columnIndex, rowIndex, element) {
    const div = document.createElement('div');
    div.className = 'element-item';
    
    const elementTypeLabel = getElementTypeLabel(element.type);
    const elementContent = element.content || element.url || 'Nessun contenuto';
    
    div.innerHTML = `
        <div class="element-info">
            <span class="element-type">${elementTypeLabel}</span>
            <span class="element-content">${elementContent}</span>
        </div>
        <div class="element-actions">
            <button class="btn-edit-element" onclick="editElement('${section}', ${columnIndex}, ${rowIndex}, ${JSON.stringify(element).replace(/"/g, '&quot;')})">
                <i class="fas fa-edit"></i>
            </button>
            <button class="btn-delete-element" onclick="currentSection='${section}'; deleteTemplateElement(${columnIndex}, ${rowIndex})">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;
    
    return div;
}

function getElementTypeLabel(type) {
    const labels = {
        'titolo_documento': 'Titolo',
        'codice_documento': 'Codice',
        'autore_documento': 'Autore',
        'stato_documento': 'Stato',
        'data_creazione': 'Data Creazione',
        'ultima_modifica': 'Ultima Modifica',
        'data_revisione': 'Data Revisione',
        'numero_versione': 'Versione',
        'logo': 'Logo',
        'azienda_nome': 'Nome Azienda',
        'azienda_indirizzo': 'Indirizzo',
        'azienda_contatti': 'Contatti',
        'copyright': 'Copyright',
        'numero_pagine': 'N. Pagine',
        'data_corrente': 'Data Corrente',
        'testo_libero': 'Testo Libero'
    };
    return labels[type] || type;
}

function toggleElementFields() {
    const elementType = document.getElementById('elementType').value;
    const contentField = document.getElementById('elementContent');
    const logoField = document.getElementById('logoUrl');
    
    if (elementType === 'testo_libero') {
        contentField.style.display = 'block';
        logoField.style.display = 'none';
    } else if (elementType === 'logo') {
        contentField.style.display = 'none';
        logoField.style.display = 'block';
    } else {
        contentField.style.display = 'none';
        logoField.style.display = 'none';
    }
}

// Event listener per cambio tipo elemento
document.addEventListener('DOMContentLoaded', function() {
    const elementTypeSelect = document.getElementById('elementType');
    if (elementTypeSelect) {
        elementTypeSelect.addEventListener('change', toggleElementFields);
    }
});

// Funzioni per associazione multi-azienda
function showMultiAziendaModal() {
    document.getElementById('multiAziendaModal').classList.add('active');
}

function closeMultiAziendaModal() {
    document.getElementById('multiAziendaModal').classList.remove('active');
}

function cloneToAziende() {
    const templateId = <?= $currentTemplate['id'] ?? 'null' ?>;
    if (!templateId) {
        alert('Template ID non trovato');
        return;
    }
    
    const checkboxes = document.querySelectorAll('#aziendeCheckboxes input[type="checkbox"]:checked');
    const selectedAziende = Array.from(checkboxes).map(cb => cb.value);
    
    if (selectedAziende.length === 0) {
        alert('Seleziona almeno un\'azienda');
        return;
    }
    
    Promise.all(selectedAziende.map(aziendeId => {
        return fetch('/piattaforma-collaborativa/backend/api/template-elements-api.php?action=clone-template', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                template_id: templateId,
                target_azienda_id: aziendeId
            })
        });
    }))
    .then(responses => Promise.all(responses.map(r => r.json())))
    .then(results => {
        const successful = results.filter(r => r.success).length;
        const failed = results.length - successful;
        
        alert(`Template associato a ${successful} aziende` + (failed > 0 ? ` (${failed} errori)` : ''));
        closeMultiAziendaModal();
    })
    .catch(error => {
        console.error('Errore:', error);
        alert('Errore durante l\'associazione');
    });
}

function addElement() {
    // Funzione legacy - ora usa saveElement()
    saveElement();
}

<?php if ($currentTemplate): ?>
// Template data per edit mode
const templateDataEdit = <?= json_encode([
    'intestazione_config' => json_decode($currentTemplate['intestazione_config'] ?? '{"columns": []}', true),
    'pie_pagina_config' => json_decode($currentTemplate['pie_pagina_config'] ?? '{"columns": []}', true)
]) ?>;

function initializeEditBuilder() {
    // Inizializza header edit builder
    if (templateDataEdit.intestazione_config && templateDataEdit.intestazione_config.columns) {
        const headerBuilder = document.getElementById('headerBuilderEdit');
        if (headerBuilder) {
            headerBuilder.innerHTML = '';
            // Aggiungi logica per costruire l'interfaccia basata sui dati esistenti
            console.log('Header edit builder initialized with:', templateDataEdit.intestazione_config);
        }
    }
    
    // Inizializza footer edit builder  
    if (templateDataEdit.pie_pagina_config && templateDataEdit.pie_pagina_config.columns) {
        const footerBuilder = document.getElementById('footerBuilderEdit');
        if (footerBuilder) {
            footerBuilder.innerHTML = '';
            // Aggiungi logica per costruire l'interfaccia basata sui dati esistenti
            console.log('Footer edit builder initialized with:', templateDataEdit.pie_pagina_config);
        }
    }
}
<?php endif; ?>

// Inizializzazione
document.addEventListener('DOMContentLoaded', function() {
    console.log('Gestione Template inizializzata');
    
    // Controlla URL parameters per mostrare il tab appropriato
    const urlParams = new URLSearchParams(window.location.search);
    const action = urlParams.get('action');
    
    if (action === 'edit') {
        <?php if ($currentTemplate): ?>
        console.log('Modalità edit attivata per template ID:', <?= $currentTemplate['id'] ?>);
        
        // Mostra il tab edit e nascondi gli altri
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        document.querySelectorAll('.tab').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Attiva tab edit
        const editTab = document.getElementById('tab-edit');
        const editButton = document.querySelector('.tab[onclick="showTab(\'edit\')"]');
        
        if (editTab) {
            editTab.classList.add('active');
            console.log('Tab edit attivato');
        } else {
            console.error('Tab edit non trovato nel DOM');
        }
        
        if (editButton) {
            editButton.classList.add('active');
            console.log('Button edit attivato');
        } else {
            console.error('Button edit non trovato nel DOM');
        }
        
        // Inizializza builder per edit mode
        if (typeof initializeEditBuilder === 'function') {
            initializeEditBuilder();
        } else {
            console.log('initializeEditBuilder non disponibile, template caricato con dati di base');
        }
        <?php else: ?>
        console.error('Template non trovato per ID:', urlParams.get('id'));
        alert('Template non trovato! Tornando alla lista...');
        showTab('list');
        <?php endif; ?>
    } else {
        // Default: mostra lista
        const listTab = document.getElementById('tab-list');
        const listButton = document.querySelector('.tab[onclick="showTab(\'list\')"]');
        
        if (listTab) {
            listTab.classList.add('active');
        }
        if (listButton) {
            listButton.classList.add('active');
        }
    }
});
</script>

</body>
</html>