<?php
/**
 * Gestione Template - Interfaccia con stile dashboard
 */

require_once __DIR__ . '/backend/config/config.php';
require_once __DIR__ . '/backend/models/Template.php';

$auth = Auth::getInstance();
$auth->requireAuth();
$user = $auth->getUser();

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
$templates = $template->getAll();

$currentTemplate = null;
if ($template_id && $action === 'edit') {
    $currentTemplate = $template->getById($template_id);
}

$pageTitle = 'Gestione Template';
include dirname(__FILE__) . '/components/header.php';
?>

<div class="content-header">
    <h1><i class="fas fa-file-contract"></i> Gestione Template Documenti</h1>
    <p>Crea e gestisci modelli di documento con intestazioni e piè di pagina personalizzabili</p>
</div>

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
                        <button class="btn btn-sm btn-danger" onclick="deleteTemplate(<?= $tmpl['id'] ?>)">
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
                <div id="headerBuilderEdit" class="builder-content"></div>
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
                <div id="footerBuilderEdit" class="builder-content"></div>
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
            </div>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Modal per aggiunta elementi -->
<div id="addElementModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Aggiungi Elemento</h3>
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
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Annulla</button>
            <button type="button" class="btn btn-primary" onclick="addElement()">Aggiungi</button>
        </div>
    </div>
</div>

<style>
/* Stili aggiuntivi specifici per la gestione template */
.tabs-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
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
    padding: 18px 24px;
    background: #f8f9fa;
    border: none;
    cursor: pointer;
    font-size: 15px;
    font-weight: 600;
    color: #4a5568;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    position: relative;
}

.tab.active {
    background: #4299e1;
    color: white;
    box-shadow: inset 0 -3px 0 #2b6cb0;
}

.tab:hover:not(.active) {
    background: #e2e8f0;
    color: #2d3748;
}

.tab-content {
    display: none;
    padding: 30px;
    background: #f7fafc;
    min-height: 400px;
}

.tab-content.active {
    display: block;
}

.empty-state {
    text-align: center;
    padding: 80px 40px;
    background: white;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.empty-state i {
    font-size: 72px;
    margin-bottom: 24px;
    color: #cbd5e0;
    display: block;
}

.empty-state h3 {
    margin-bottom: 12px;
    color: #2d3748;
    font-size: 20px;
    font-weight: 600;
}

.empty-state p {
    color: #718096;
    font-size: 16px;
    margin-bottom: 24px;
    line-height: 1.5;
}

.templates-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 24px;
    padding: 0;
}

.template-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 28px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    position: relative;
    overflow: hidden;
}

.template-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #4299e1, #63b3ed);
    transform: scaleX(0);
    transition: transform 0.3s ease;
}

.template-card:hover::before {
    transform: scaleX(1);
}

.template-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
    border-color: #4299e1;
}

.template-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid #f1f5f9;
}

.template-header h3 {
    color: #2d3748;
    margin: 0;
    font-size: 19px;
    font-weight: 600;
    line-height: 1.3;
    flex: 1;
    margin-right: 12px;
}

.template-status {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 4px;
}

.template-status.active {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #10b981;
}

.template-status.inactive {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #ef4444;
}

.template-status i {
    font-size: 8px;
}

.template-body {
    margin-bottom: 20px;
}

.template-body p {
    color: #64748b;
    margin-bottom: 18px;
    font-size: 14px;
    line-height: 1.6;
    min-height: 42px;
}

.template-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 24px;
    padding: 12px 0;
    border-top: 1px solid #f1f5f9;
    border-bottom: 1px solid #f1f5f9;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: #64748b;
    font-weight: 500;
}

.meta-item i {
    color: #94a3b8;
    width: 14px;
}

.template-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-start;
}

.template-actions .btn {
    flex: 1;
    min-width: 90px;
    text-align: center;
    justify-content: center;
    font-size: 13px;
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 500;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 32px;
    margin-bottom: 32px;
}

.form-section {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 32px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    transition: box-shadow 0.3s ease;
}

.form-section:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.form-section h2 {
    color: #2d3748;
    margin-bottom: 24px;
    font-size: 19px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 12px;
    padding-bottom: 12px;
    border-bottom: 2px solid #f1f5f9;
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
    border-radius: 12px;
    padding: 32px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    margin-top: 32px;
}

.template-builder h2 {
    color: #2d3748;
    margin-bottom: 28px;
    font-size: 20px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 12px;
    padding-bottom: 16px;
    border-bottom: 2px solid #f1f5f9;
}

.section-builder {
    margin-bottom: 32px;
    border: 2px solid #e8f4f8;
    border-radius: 12px;
    padding: 24px;
    background: #fafcfe;
    transition: all 0.3s ease;
}

.section-builder:hover {
    border-color: #bee3f8;
    background: #f7fafc;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 18px;
}

.section-header h3 {
    color: #2b6cb0;
    font-size: 17px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
}

.section-controls {
    display: flex;
    align-items: center;
    gap: 12px;
    background: white;
    padding: 8px 16px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.control-label {
    font-size: 13px;
    color: #64748b;
    font-weight: 500;
}

.section-controls button {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: 1px solid #cbd5e0;
    background: white;
    color: #4a5568;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.section-controls button:hover {
    background: #4299e1;
    color: white;
    border-color: #3182ce;
}

.section-controls span {
    min-width: 20px;
    text-align: center;
    font-weight: 600;
    color: #2d3748;
}

.builder-content {
    min-height: 120px;
}

.form-actions {
    text-align: center;
    margin-top: 40px;
    padding-top: 24px;
    border-top: 2px solid #f1f5f9;
}

.form-actions .btn {
    margin: 0 12px;
    padding: 12px 24px;
    font-size: 15px;
    font-weight: 600;
    border-radius: 10px;
    transition: all 0.3s ease;
}

.form-actions .btn-primary {
    background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
    border: none;
    box-shadow: 0 4px 12px rgba(66, 153, 225, 0.3);
}

.form-actions .btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(66, 153, 225, 0.4);
}

.form-actions .btn-secondary {
    background: #f7fafc;
    color: #4a5568;
    border: 1px solid #e2e8f0;
}

.form-actions .btn-secondary:hover {
    background: #edf2f7;
    border-color: #cbd5e0;
}

.code-editor {
    font-family: 'Courier New', monospace;
    font-size: 13px;
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
    border-radius: 8px;
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}

.modal-header {
    padding: 20px 25px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    color: #2d3748;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    color: #718096;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-body {
    padding: 25px;
}

.modal-footer {
    padding: 20px 25px;
    border-top: 1px solid #e2e8f0;
    text-align: right;
}

.modal-footer .btn {
    margin-left: 10px;
}

/* Stili per i form */
.form-group {
    margin-bottom: 24px;
}

.form-group label {
    display: block;
    margin-bottom: 10px;
    font-weight: 600;
    color: #2d3748;
    font-size: 14px;
}

.form-control {
    width: 100%;
    padding: 14px 18px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 15px;
    transition: all 0.3s ease;
    background: #fafafa;
    font-family: inherit;
}

.form-control:focus {
    outline: none;
    border-color: #4299e1;
    box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
    background: white;
}

.form-control:hover {
    border-color: #cbd5e0;
    background: white;
}

textarea.form-control {
    resize: vertical;
    min-height: 100px;
    line-height: 1.5;
}

select.form-control {
    cursor: pointer;
}

.code-editor {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 13px;
    background: #1a202c;
    color: #e2e8f0;
    border: 2px solid #2d3748;
}

.code-editor:focus {
    border-color: #4299e1;
    box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
}

/* Miglioramenti ai bottoni generali */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-primary {
    background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(66, 153, 225, 0.3);
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(66, 153, 225, 0.4);
}

.btn-secondary {
    background: #f7fafc;
    color: #4a5568;
    border: 1px solid #e2e8f0;
}

.btn-secondary:hover {
    background: #edf2f7;
    border-color: #cbd5e0;
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

.btn-danger {
    background: #e53e3e;
    color: white;
}

.btn-danger:hover {
    background: #c53030;
    transform: translateY(-1px);
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

/* Miglioramenti ai column builder */
.column-builder {
    border: 2px solid #f1f5f9 !important;
    border-radius: 12px !important;
    padding: 20px !important;
    background: white !important;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05) !important;
    transition: all 0.3s ease !important;
}

.column-builder:hover {
    border-color: #bee3f8 !important;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1) !important;
}

.add-row {
    border: 2px dashed #cbd5e0 !important;
    border-radius: 8px !important;
    padding: 20px !important;
    text-align: center !important;
    cursor: pointer !important;
    color: #64748b !important;
    transition: all 0.3s ease !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 10px !important;
    font-size: 14px !important;
    font-weight: 500 !important;
    background: #fafcfe !important;
}

.add-row:hover {
    border-color: #4299e1 !important;
    background: #f0f9ff !important;
    color: #2b6cb0 !important;
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .templates-grid {
        grid-template-columns: 1fr;
    }
    
    .template-actions {
        justify-content: center;
    }
    
    .section-header {
        flex-direction: column;
        gap: 10px;
        align-items: flex-start;
    }
    
    .tab {
        padding: 14px 16px;
        font-size: 14px;
    }
    
    .template-card {
        padding: 20px;
    }
    
    .tab-content {
        padding: 20px;
    }
}
</style>

<script>
let currentSection = '';
let currentColumn = '';
let templateData = {
    header: { columns: [{ rows: [] }] },
    footer: { columns: [{ rows: [] }] }
};

// Dati template per la modifica
let templateDataEdit = {
    header: { columns: [{ rows: [] }] },
    footer: { columns: [{ rows: [] }] }
};

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    initializeBuilder();
    
    <?php if ($currentTemplate): ?>
    // Inizializza i dati del template per la modifica
    templateDataEdit = {
        header: <?= json_encode($currentTemplate['intestazione_config'] ?? ['columns' => [['rows' => []]]]) ?>,
        footer: <?= json_encode($currentTemplate['pie_pagina_config'] ?? ['columns' => [['rows' => []]]]) ?>
    };
    
    // Mostra automaticamente il tab di modifica se siamo in modalità edit
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('action') === 'edit') {
        showTab('edit');
        initializeEditBuilder();
    }
    <?php endif; ?>
});

function showTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById('tab-' + tabName).classList.add('active');
    event.target.classList.add('active');
}

function initializeBuilder() {
    rebuildSection('header');
    rebuildSection('footer');
    updatePreview();
}

function changeColumns(section, delta) {
    const currentCount = templateData[section].columns.length;
    const newCount = Math.min(3, Math.max(1, currentCount + delta));
    
    if (newCount !== currentCount) {
        if (newCount > currentCount) {
            // Aggiungi colonne
            for (let i = currentCount; i < newCount; i++) {
                templateData[section].columns.push({ rows: [] });
            }
        } else {
            // Rimuovi colonne
            templateData[section].columns.splice(newCount);
        }
        
        document.getElementById(section + 'ColumnCount').textContent = newCount;
        rebuildSection(section);
        updatePreview();
    }
}

function rebuildSection(section) {
    const container = document.getElementById(section + 'Builder');
    container.innerHTML = '';
    
    const columnsContainer = document.createElement('div');
    columnsContainer.className = 'columns-container';
    columnsContainer.style.cssText = `
        display: grid;
        grid-template-columns: repeat(${templateData[section].columns.length}, 1fr);
        gap: 15px;
    `;
    
    templateData[section].columns.forEach((column, columnIndex) => {
        const columnDiv = document.createElement('div');
        columnDiv.className = 'column-builder';
        columnDiv.style.cssText = `
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 15px;
            background: #f8f9fa;
        `;
        
        columnDiv.innerHTML = `
            <div style="font-weight: 600; margin-bottom: 10px; color: #2d3748; font-size: 14px;">
                Colonna ${columnIndex + 1}
            </div>
            <div class="column-rows" id="${section}_column_${columnIndex}">
                ${column.rows.map((row, rowIndex) => `
                    <div class="row-item" style="background: white; border: 1px solid #e2e8f0; border-radius: 4px; padding: 10px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 13px;">${getElementTypeName(row.type)}</span>
                        <div>
                            <span style="font-size: 11px; background: #e2e8f0; padding: 2px 6px; border-radius: 12px; color: #666;">${row.type}</span>
                            <button type="button" onclick="removeRow('${section}', ${columnIndex}, ${rowIndex})"
                                    style="background: none; border: none; color: #e53e3e; margin-left: 8px; cursor: pointer;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                `).join('')}
                <div class="add-row" onclick="openAddElementModal('${section}', ${columnIndex})"
                     style="border: 2px dashed #cbd5e0; border-radius: 4px; padding: 15px; text-align: center; cursor: pointer; color: #718096; transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 14px;">
                    <i class="fas fa-plus"></i>
                    Aggiungi Elemento
                </div>
            </div>
        `;
        
        columnsContainer.appendChild(columnDiv);
    });
    
    container.appendChild(columnsContainer);
}

function openAddElementModal(section, columnIndex) {
    currentSection = section;
    currentColumn = columnIndex;
    const modal = document.getElementById('addElementModal');
    modal.classList.add('active');
    modal.removeAttribute('data-mode'); // Modalità creazione
    
    // Reset form
    document.getElementById('elementType').value = 'titolo_documento';
    document.getElementById('elementContentValue').value = '';
    document.getElementById('logoUrlValue').value = '';
    toggleElementFields();
}

function closeModal() {
    document.getElementById('addElementModal').classList.remove('active');
}

function toggleElementFields() {
    const type = document.getElementById('elementType').value;
    const contentField = document.getElementById('elementContent');
    const logoField = document.getElementById('logoUrl');
    
    contentField.style.display = ['testo_libero', 'copyright'].includes(type) ? 'block' : 'none';
    logoField.style.display = type === 'logo' ? 'block' : 'none';
}

document.getElementById('elementType').addEventListener('change', toggleElementFields);

function addElement() {
    const type = document.getElementById('elementType').value;
    const content = document.getElementById('elementContentValue').value;
    const logoUrl = document.getElementById('logoUrlValue').value;
    const modal = document.getElementById('addElementModal');
    const isEditMode = modal.getAttribute('data-mode') === 'edit';
    
    const element = { type };
    
    if (type === 'testo_libero' || type === 'copyright') {
        element.content = content;
    }
    if (type === 'logo') {
        element.logo_url = logoUrl;
        element.max_height = '50px';
    }
    
    if (isEditMode) {
        // Modalità modifica
        if (templateDataEdit[currentSection].columns[currentColumn].rows.length >= 3) {
            alert('Ogni colonna può contenere massimo 3 righe');
            return;
        }
        
        templateDataEdit[currentSection].columns[currentColumn].rows.push(element);
        rebuildSectionEdit(currentSection);
        updatePreviewEdit();
    } else {
        // Modalità creazione
        if (templateData[currentSection].columns[currentColumn].rows.length >= 3) {
            alert('Ogni colonna può contenere massimo 3 righe');
            return;
        }
        
        templateData[currentSection].columns[currentColumn].rows.push(element);
        rebuildSection(currentSection);
        updatePreview();
    }
    
    closeModal();
}

function removeRow(section, columnIndex, rowIndex) {
    templateData[section].columns[columnIndex].rows.splice(rowIndex, 1);
    rebuildSection(section);
    updatePreview();
}

function getElementTypeName(type) {
    const names = {
        'logo': 'Logo Aziendale',
        'titolo_documento': 'Titolo Documento',
        'codice_documento': 'Codice Documento',
        'autore_documento': 'Autore Documento',
        'stato_documento': 'Stato Documento',
        'data_creazione': 'Data Creazione',
        'ultima_modifica': 'Ultima Modifica',
        'copyright': 'Copyright',
        'data_revisione': 'Data Revisione',
        'numero_versione': 'Numero Versione',
        'numero_pagine': 'Numero Pagine',
        'data_corrente': 'Data Corrente',
        'azienda_nome': 'Nome Azienda',
        'azienda_indirizzo': 'Indirizzo Azienda',
        'azienda_contatti': 'Contatti Azienda',
        'testo_libero': 'Testo Libero'
    };
    return names[type] || type;
}

function updatePreview() {
    const headerPreview = document.getElementById('previewHeader');
    const footerPreview = document.getElementById('previewFooter');
    
    headerPreview.innerHTML = generatePreviewHTML(templateData.header);
    footerPreview.innerHTML = generatePreviewHTML(templateData.footer);
}

function generatePreviewHTML(config) {
    if (!config.columns || config.columns.length === 0) {
        return '<em style="color: #718096;">Nessun elemento configurato</em>';
    }
    
    const columnWidth = 100 / config.columns.length;
    let html = '<table style="width: 100%; border-collapse: collapse; font-size: 12px;">';
    
    const maxRows = Math.max(...config.columns.map(col => col.rows.length));
    
    for (let row = 0; row < maxRows; row++) {
        html += '<tr>';
        config.columns.forEach(column => {
            const cellData = column.rows[row] || { type: 'empty' };
            html += `<td style="width: ${columnWidth}%; vertical-align: top; padding: 8px; border: 1px dashed #e2e8f0;">`;
            html += generateCellPreview(cellData);
            html += '</td>';
        });
        html += '</tr>';
    }
    
    html += '</table>';
    return html;
}

function generateCellPreview(cellData) {
    switch (cellData.type) {
        case 'logo':
            return '<div style="text-align: center;"><i class="fas fa-image" style="font-size: 20px; color: #cbd5e0;"></i><br><small style="color: #718096;">Logo</small></div>';
        case 'titolo_documento':
            return '<strong style="color: #2d3748;">Titolo Documento</strong>';
        case 'codice_documento':
            return '<code style="background: #f7fafc; padding: 2px 4px; border-radius: 3px;">DOC-001</code>';
        case 'autore_documento':
            return '<small style="color: #718096;">Autore: Mario Rossi</small>';
        case 'azienda_nome':
            return '<strong style="color: #2d3748;">Nome Azienda S.r.l.</strong>';
        case 'copyright':
            return '<small style="color: #718096;">' + (cellData.content || '© 2024 Azienda') + '</small>';
        case 'data_revisione':
            return '<small style="color: #718096;">Rev: ' + new Date().toLocaleDateString('it-IT') + '</small>';
        case 'numero_versione':
            return '<small style="color: #718096;">v1.0</small>';
        case 'numero_pagine':
            return '<small style="color: #718096;">Pag. 1 di 1</small>';
        case 'data_corrente':
            return '<small style="color: #718096;">' + new Date().toLocaleDateString('it-IT') + '</small>';
        case 'testo_libero':
            return '<span style="color: #2d3748;">' + (cellData.content || 'Testo libero') + '</span>';
        default:
            return '<span style="color: #cbd5e0;">&nbsp;</span>';
    }
}

async function saveTemplate(event) {
    event.preventDefault();
    
    const formData = new FormData();
    formData.append('action', 'create');
    formData.append('nome', document.getElementById('nome').value);
    formData.append('descrizione', document.getElementById('descrizione').value);
    formData.append('azienda_id', document.getElementById('azienda_id').value);
    formData.append('stili_css', document.getElementById('stili_css').value);
    formData.append('intestazione_config', JSON.stringify(templateData.header));
    formData.append('pie_pagina_config', JSON.stringify(templateData.footer));
    
    try {
        const response = await fetch('backend/api/template-api.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Template salvato con successo!');
            window.location.href = '?action=list';
        } else {
            alert('Errore: ' + result.error);
        }
    } catch (error) {
        alert('Errore di comunicazione: ' + error.message);
    }
}

async function deleteTemplate(id) {
    if (!confirm('Sei sicuro di voler eliminare questo template?')) {
        return;
    }
    
    try {
        const response = await fetch('backend/api/template-api.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Template eliminato con successo!');
            location.reload();
        } else {
            alert('Errore: ' + result.error);
        }
    } catch (error) {
        alert('Errore di comunicazione: ' + error.message);
    }
}

function previewTemplate(id) {
    window.open('anteprima-template.php?id=' + id, '_blank', 'width=900,height=700');
}

// Funzioni per la modifica del template
function initializeEditBuilder() {
    rebuildSectionEdit('header');
    rebuildSectionEdit('footer');
    updatePreviewEdit();
}

function changeColumnsEdit(section, delta) {
    const currentCount = templateDataEdit[section].columns.length;
    const newCount = Math.min(3, Math.max(1, currentCount + delta));
    
    if (newCount !== currentCount) {
        if (newCount > currentCount) {
            // Aggiungi colonne
            for (let i = currentCount; i < newCount; i++) {
                templateDataEdit[section].columns.push({ rows: [] });
            }
        } else {
            // Rimuovi colonne
            templateDataEdit[section].columns.splice(newCount);
        }
        
        document.getElementById(section + 'ColumnCountEdit').textContent = newCount;
        rebuildSectionEdit(section);
        updatePreviewEdit();
    }
}

function rebuildSectionEdit(section) {
    const container = document.getElementById(section + 'BuilderEdit');
    container.innerHTML = '';
    
    const columnsContainer = document.createElement('div');
    columnsContainer.className = 'columns-container';
    columnsContainer.style.cssText = `
        display: grid;
        grid-template-columns: repeat(${templateDataEdit[section].columns.length}, 1fr);
        gap: 15px;
    `;
    
    templateDataEdit[section].columns.forEach((column, columnIndex) => {
        const columnDiv = document.createElement('div');
        columnDiv.className = 'column-builder';
        columnDiv.style.cssText = `
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 15px;
            background: #f8f9fa;
        `;
        
        columnDiv.innerHTML = `
            <div style="font-weight: 600; margin-bottom: 10px; color: #2d3748; font-size: 14px;">
                Colonna ${columnIndex + 1}
            </div>
            <div class="column-rows" id="${section}_column_edit_${columnIndex}">
                ${column.rows.map((row, rowIndex) => `
                    <div class="row-item" style="background: white; border: 1px solid #e2e8f0; border-radius: 4px; padding: 10px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 13px;">${getElementTypeName(row.type)}</span>
                        <div>
                            <span style="font-size: 11px; background: #e2e8f0; padding: 2px 6px; border-radius: 12px; color: #666;">${row.type}</span>
                            <button type="button" onclick="removeRowEdit('${section}', ${columnIndex}, ${rowIndex})"
                                    style="background: none; border: none; color: #e53e3e; margin-left: 8px; cursor: pointer;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                `).join('')}
                <div class="add-row" onclick="openAddElementModalEdit('${section}', ${columnIndex})"
                     style="border: 2px dashed #cbd5e0; border-radius: 4px; padding: 15px; text-align: center; cursor: pointer; color: #718096; transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 14px;">
                    <i class="fas fa-plus"></i>
                    Aggiungi Elemento
                </div>
            </div>
        `;
        
        columnsContainer.appendChild(columnDiv);
    });
    
    container.appendChild(columnsContainer);
}

function openAddElementModalEdit(section, columnIndex) {
    currentSection = section;
    currentColumn = columnIndex;
    document.getElementById('addElementModal').classList.add('active');
    document.getElementById('addElementModal').setAttribute('data-mode', 'edit');
    
    // Reset form
    document.getElementById('elementType').value = 'titolo_documento';
    document.getElementById('elementContentValue').value = '';
    document.getElementById('logoUrlValue').value = '';
    toggleElementFields();
}

function addElementEdit() {
    const type = document.getElementById('elementType').value;
    const content = document.getElementById('elementContentValue').value;
    const logoUrl = document.getElementById('logoUrlValue').value;
    
    const element = { type };
    
    if (type === 'testo_libero' || type === 'copyright') {
        element.content = content;
    }
    if (type === 'logo') {
        element.logo_url = logoUrl;
        element.max_height = '50px';
    }
    
    // Verifica limite righe (max 3 per colonna)
    if (templateDataEdit[currentSection].columns[currentColumn].rows.length >= 3) {
        alert('Ogni colonna può contenere massimo 3 righe');
        return;
    }
    
    templateDataEdit[currentSection].columns[currentColumn].rows.push(element);
    rebuildSectionEdit(currentSection);
    updatePreviewEdit();
    closeModal();
}

function removeRowEdit(section, columnIndex, rowIndex) {
    templateDataEdit[section].columns[columnIndex].rows.splice(rowIndex, 1);
    rebuildSectionEdit(section);
    updatePreviewEdit();
}

function updatePreviewEdit() {
    const headerPreview = document.getElementById('previewHeaderEdit');
    const footerPreview = document.getElementById('previewFooterEdit');
    
    headerPreview.innerHTML = generatePreviewHTML(templateDataEdit.header);
    footerPreview.innerHTML = generatePreviewHTML(templateDataEdit.footer);
}

async function updateTemplate(event, templateId) {
    event.preventDefault();
    
    const formData = new FormData();
    formData.append('action', 'update');
    formData.append('id', templateId);
    formData.append('nome', document.getElementById('nome_edit').value);
    formData.append('descrizione', document.getElementById('descrizione_edit').value);
    formData.append('azienda_id', document.getElementById('azienda_id_edit').value);
    formData.append('stili_css', document.getElementById('stili_css_edit').value);
    formData.append('attivo', document.getElementById('attivo_edit').checked ? 1 : 0);
    formData.append('intestazione_config', JSON.stringify(templateDataEdit.header));
    formData.append('pie_pagina_config', JSON.stringify(templateDataEdit.footer));
    
    try {
        const response = await fetch('backend/api/template-api.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Template aggiornato con successo!');
            window.location.href = '?action=list';
        } else {
            alert('Errore: ' + result.error);
        }
    } catch (error) {
        alert('Errore di comunicazione: ' + error.message);
    }
}
</script>

<?php include dirname(__FILE__) . '/components/footer.php'; ?>