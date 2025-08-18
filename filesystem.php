<?php
/**
 * Filesystem - Gestione Documenti Semplificata
 * Sistema pulito e funzionale per la gestione file
 */

require_once 'backend/config/config.php';
require_once 'backend/middleware/Auth.php';
require_once 'backend/config/database.php';

// Autenticazione
$auth = Auth::getInstance();
$auth->requireAuth();

$user = $auth->getUser();
$currentAzienda = $auth->getCurrentAzienda();
$aziendaId = $currentAzienda['azienda_id'] ?? $currentAzienda['id'] ?? null;
$isSuperAdmin = $auth->isSuperAdmin();
$isUtenteSpeciale = $user['ruolo'] === 'utente_speciale';

// Gestione accesso
if (!$aziendaId && !($isSuperAdmin || $isUtenteSpeciale)) {
    header('Location: dashboard.php?error=no_company');
    exit;
}

// Get companies for dropdown if needed - ensure no duplicates
$companies = [];
if ($isSuperAdmin || $isUtenteSpeciale) {
    try {
        // Get only active companies (exclude deleted/canceled ones)
        $stmt = db_query("SELECT DISTINCT id, nome FROM aziende WHERE stato = 'attiva' ORDER BY nome");
        $companies_raw = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        
        // Remove any potential duplicates by ID to ensure data consistency
        $companies = [];
        $seen_ids = [];
        foreach ($companies_raw as $company) {
            if (!in_array($company['id'], $seen_ids)) {
                $seen_ids[] = $company['id'];
                $companies[] = $company;
            }
        }
        
    } catch (Exception $e) {
        error_log("Error loading companies dropdown: " . $e->getMessage());
        $companies = [];
    }
}

$pageTitle = 'Gestione Documenti';
include 'components/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <h1><i class="fas fa-folder-open"></i> Gestione Documenti</h1>
    <div class="page-subtitle">Organizza e gestisci i tuoi file e documenti aziendali</div>
</div>

<style>
/* Simple Clean Styles */
.filesystem-container {
    padding: 20px;
    max-width: 1400px;
    margin: 0 auto;
    display: flex;
    gap: 20px;
}

/* Multi-select styles */
.selection-toolbar {
    background: #f3f4f6;
    padding: 12px 20px;
    border-radius: 6px;
    margin-bottom: 15px;
    display: none;
    align-items: center;
    justify-content: space-between;
}

.selection-toolbar.show {
    display: flex;
}

.selection-info {
    color: #374151;
    font-size: 14px;
}

.selection-actions {
    display: flex;
    gap: 10px;
}

.file-checkbox {
    position: absolute;
    top: 10px;
    left: 10px;
    z-index: 5;
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.file-card.selected {
    background: #dbeafe;
    border-color: #3b82f6;
}

.company-info {
    font-size: 11px;
    color: #9ca3af;
    font-style: italic;
    margin-top: 2px;
}

.filesystem-sidebar {
    width: 280px;
    min-width: 280px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    max-height: calc(100vh - 140px);
    overflow-y: auto;
}

.sidebar-header {
    padding: 20px;
    border-bottom: 1px solid #e5e7eb;
    font-weight: 600;
    color: #374151;
}

.folder-tree {
    padding: 10px 0;
}

.tree-node {
    user-select: none;
}

.tree-item {
    display: flex;
    align-items: center;
    padding: 5px 15px;
    cursor: pointer;
    color: #374151;
    font-size: 14px;
    transition: background-color 0.2s;
}

.tree-item:hover {
    background: #f3f4f6;
}

.tree-item.active {
    background: #dbeafe;
    color: #2d5a9f;
}

.tree-toggle {
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 5px;
    cursor: pointer;
}

.tree-toggle i {
    font-size: 12px;
    color: #6b7280;
    transition: transform 0.2s;
}

.tree-toggle.expanded i {
    transform: rotate(90deg);
}

.tree-children {
    display: none;
    padding-left: 20px;
}

.tree-children.show {
    display: block;
}

.invisible {
    visibility: hidden;
}

.filesystem-main {
    flex: 1;
}

.fs-header {
    background: white;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.fs-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}
.fs-toolbar > div:first-child { display: flex; flex-wrap: wrap; gap: 8px; }

.fs-content {
    background: white;
    border-radius: 8px;
    padding: 20px;
    min-height: 400px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.files-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 15px;
    padding: 20px 0;
}

.file-card {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 15px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
}

.file-card:hover {
    background: #f3f4f6;
    transform: translateY(-2px);
}

/* Pulsanti di azione file-card */
.file-card-actions {
    position: absolute;
    top: 5px;
    right: 5px;
    display: none;
    gap: 4px;
    z-index: 10;
    background: rgba(255, 255, 255, 0.95);
    padding: 3px;
    border-radius: 6px;
    backdrop-filter: blur(4px);
}

.file-card:hover .file-card-actions,
.file-card:focus-within .file-card-actions {
    display: flex;
}

/* Pulsanti in basso per migliore visibilità */
.file-card-actions-bottom {
    display: none;
    gap: 4px;
    justify-content: center;
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid #e5e7eb;
}

.file-card:hover .file-card-actions-bottom,
.file-card:focus-within .file-card-actions-bottom {
    display: flex;
}

/* Pulsante OnlyOffice evidenziato - supporta sia button che link */
.action-btn.btn-onlyoffice,
a.action-btn.btn-onlyoffice {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%) !important;
    color: white !important;
    border: 2px solid #28a745 !important;
    font-weight: bold !important;
    box-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);
    transition: all 0.3s ease;
    text-decoration: none !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
}

.action-btn.btn-onlyoffice:hover,
a.action-btn.btn-onlyoffice:hover {
    background: linear-gradient(135deg, #20c997 0%, #28a745 100%) !important;
    transform: scale(1.1);
    box-shadow: 0 4px 8px rgba(40, 167, 69, 0.5);
    color: white !important;
    text-decoration: none !important;
}

/* Pulsanti più grandi e visibili - supporta sia button che link */
.action-btn {
    width: 28px;
    height: 28px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.9);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    color: #6b7280;
    cursor: pointer;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

/* Link stilizzati come bottoni */
a.action-btn {
    text-decoration: none !important;
}

/* Icone dentro i pulsanti - più piccole e ben proporzionate */
.action-btn i {
    font-size: 12px !important;
    line-height: 1;
    pointer-events: none;
}

/* Stati hover semplici */
.action-btn:hover {
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
    border-color: #9ca3af;
}

.action-btn:hover:not(.delete) {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
}

.action-btn.delete:hover {
    background: #ef4444;
    color: white;
    border-color: #ef4444;
}

/* Mobile responsive */
@media (max-width: 768px) {
    .file-card-actions,
    .file-card-actions-bottom {
        display: flex !important;
    }
    
    .action-btn {
        width: 26px;
        height: 26px;
    }
    
    .action-btn i {
        font-size: 11px !important;
    }
}

.file-card i {
    font-size: 60px;
    margin-bottom: 12px;
    display: block;
}

.folder-icon { color: #fbbf24; }
.pdf-icon { color: #ef4444; }
.doc-icon { color: #3b82f6; }

.file-name {
    font-size: 14px;
    word-break: break-word;
    margin-bottom: 5px;
}

.file-meta {
    font-size: 11px;
    color: #6b7280;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: #2d5a9f;
    color: white;
}

.btn-primary:hover {
    background: #1e3a8a;
}

.btn-secondary {
    background: #e5e7eb;
    color: #374151;
}

.btn-danger {
    background: #ef4444;
    color: white;
}

.breadcrumb {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
    font-size: 14px;
}

.breadcrumb a {
    color: #2d5a9f;
    text-decoration: none;
}

.breadcrumb a:hover {
    text-decoration: underline;
}

.loading {
    text-align: center;
    padding: 40px;
    color: #6b7280;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #9ca3af;
}

.empty-state i {
    font-size: 64px;
    margin-bottom: 20px;
    opacity: 0.5;
}

/* Modal styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
}

.modal.show {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    border-radius: 8px;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 20px;
    border-top: 1px solid #e5e7eb;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.form-group {
    margin-bottom: 15px;
}

.form-label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
}

.upload-area {
    border: 2px dashed #d1d5db;
    border-radius: 8px;
    padding: 40px;
    text-align: center;
    background: #f9fafb;
}

.upload-area.dragover {
    border-color: #2d5a9f;
    background: #eff6ff;
}
</style>

<div class="filesystem-container">
    <!-- Sidebar with folder tree -->
    <div class="filesystem-sidebar">
        <div class="sidebar-header">
            <i class="fas fa-folder-tree"></i> Struttura Cartelle
        </div>
        <div class="folder-tree" id="folderTree">
            <div class="loading" >
                <i class="fas fa-spinner fa-spin"></i> Caricamento...
            </div>
        </div>
    </div>

    <!-- Main content area -->
    <div class="filesystem-main">
        <div class="fs-header">
            <h1>Gestione Documenti</h1>
            
            <!-- Breadcrumb -->
            <div class="breadcrumb" id="breadcrumb">
                <a href="#" onclick="loadFolder(null); return false;">
                    <i class="fas fa-home"></i> Home
                </a>
            </div>
            
            <!-- Toolbar -->
            <div class="fs-toolbar">
                <div>
                    <button type="button" class="btn btn-primary" onclick="showUploadModal()">
                        <i class="fas fa-upload"></i> Carica File
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="showNewFolderModal()">
                        <i class="fas fa-folder-plus"></i> Nuova Cartella
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="toggleSelectMode()">
                        <i class="fas fa-check-square"></i> Selezione Multipla
                    </button>
                </div>
                
                <div>
                    <input type="text" class="form-control" placeholder="Cerca..." 
                           id="searchInput" onkeyup="searchFiles()" >
                </div>
            </div>
            
            <!-- Selection Toolbar -->
            <div class="selection-toolbar" id="selectionToolbar">
                <div class="selection-info">
                    <span id="selectedCount">0</span> elementi selezionati
                </div>
                <div class="selection-actions">
                    <button type="button" class="btn btn-secondary" onclick="selectAllItems()">
                        <i class="fas fa-check-double"></i> Seleziona Tutto
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="deselectAllItems()">
                        <i class="fas fa-times"></i> Deseleziona Tutto
                    </button>
                    <button type="button" class="btn btn-primary" onclick="downloadSelected()">
                        <i class="fas fa-download"></i> Scarica Selezionati
                    </button>
                    <button type="button" class="btn btn-danger" onclick="deleteSelected()">
                        <i class="fas fa-trash"></i> Elimina Selezionati
                    </button>
                </div>
            </div>
        </div>
        
        <div class="fs-content">
            <div id="filesContainer">
                <div class="loading">
                    <i class="fas fa-spinner fa-spin"></i> Caricamento...
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal" id="uploadModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Carica File</h3>
            <button type="button" onclick="closeModal('uploadModal')" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        <div class="modal-body">
            <?php if ($isSuperAdmin || $isUtenteSpeciale): ?>
            <div class="form-group">
                <label class="form-label">Associa a:</label>
                <select class="form-control" id="uploadCompanyId">
                    <option value="">File Personale (solo per me)</option>
                    <?php foreach ($companies as $company): ?>
                    <option value="<?php echo $company['id']; ?>" <?php echo ($company['id'] == $aziendaId) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($company['nome']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="upload-area" id="uploadArea">
                <i class="fas fa-cloud-upload-alt" ></i>
                <p>Trascina qui i file o clicca per selezionare</p>
                <p >Formati: PDF, DOC, DOCX (Max 10MB)</p>
                <input type="file" id="fileInput" multiple accept=".pdf,.doc,.docx" style="display: none;">
            </div>
            
            <div id="uploadList" style="margin-top: 20px;"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('uploadModal')">Annulla</button>
            <button type="button" class="btn btn-primary" onclick="uploadFiles()">Carica</button>
        </div>
    </div>
</div>

<!-- New Folder Modal -->
<div class="modal" id="folderModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Nuova Cartella</h3>
            <button type="button" onclick="closeModal('folderModal')" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Nome Cartella:</label>
                <input type="text" class="form-control" id="folderName" placeholder="Nome della cartella">
            </div>
            
            <?php if ($isSuperAdmin || $isUtenteSpeciale): ?>
            <div class="form-group">
                <label class="form-label">Associa a:</label>
                <select class="form-control" id="folderCompanyId">
                    <option value="">Cartella Personale (solo per me)</option>
                    <?php foreach ($companies as $company): ?>
                    <option value="<?php echo $company['id']; ?>" <?php echo ($company['id'] == $aziendaId) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($company['nome']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('folderModal')">Annulla</button>
            <button type="button" class="btn btn-primary" onclick="createFolder()">Crea</button>
        </div>
    </div>
</div>

<!-- Rename Modal -->
<div class="modal" id="renameModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Rinomina</h3>
            <button type="button" onclick="closeModal('renameModal')" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Nuovo nome:</label>
                <input type="text" class="form-control" id="newItemName" placeholder="Inserisci il nuovo nome">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('renameModal')">Annulla</button>
            <button type="button" class="btn btn-primary" onclick="confirmRename()">Rinomina</button>
        </div>
    </div>
</div>

<!-- Delete Confirm Modal -->
<div class="modal" id="deleteModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 >Conferma Eliminazione</h3>
            <button type="button" onclick="closeModal('deleteModal')" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        <div class="modal-body">
            <p>Sei sicuro di voler eliminare <strong id="deleteItemName"></strong>?</p>
            <p >Questa azione non può essere annullata.</p>
            
            <div style="margin-top: 20px;">
                <label>
                    <input type="checkbox" id="confirmCheck" onchange="document.getElementById('confirmBtn').disabled = !this.checked;">
                    Confermo di voler eliminare questo elemento
                </label>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">Annulla</button>
            <button type="button" class="btn btn-danger" id="confirmBtn" onclick="confirmDeleteFS()" disabled>Elimina</button>
        </div>
    </div>
</div>

<script>
// Namespace per evitare conflitti
window.FileSystemNexio = window.FileSystemNexio || {};

// Simple filesystem JavaScript
let currentFolder = null;
let currentPath = [];
let selectedFiles = [];
let deleteTarget = null;
let renameTarget = null;
let selectMode = false;
let selectedItems = new Set(); // Per tracciare elementi selezionati

// Initial load
document.addEventListener('DOMContentLoaded', function() {
    console.log('Filesystem JS initialized');
    
    // Non serve più event delegation per OnlyOffice - ora è un link diretto
    
    loadFolder(null);
    loadFolderTree();
    
    // Verify functions are available
    console.log('confirmDeleteFS exists:', typeof confirmDeleteFS === 'function');
});

// Load folder contents
function loadFolder(folderId) {
    currentFolder = folderId;
    const container = document.getElementById('filesContainer');
    container.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Caricamento...</div>';
    
    fetch('backend/api/filesystem-simple-api.php?action=list&folder=' + (folderId || ''))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderFiles(data.data);
                updateBreadcrumb(data.path || []);
            } else {
                container.innerHTML = '<div class="empty-state">Errore nel caricamento</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div class="empty-state">Errore di connessione</div>';
        });
}

// Render files and folders
function renderFiles(data) {
    const container = document.getElementById('filesContainer');
    
    if (!data.folders.length && !data.files.length) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <p>Cartella vuota</p>
            </div>
        `;
        return;
    }
    
    let html = '<div class="files-grid">';
    
    // Render folders
    data.folders.forEach(folder => {
        const isSelected = selectedItems.has(`folder-${folder.id}`);
        const companyName = folder.azienda_nome || 'Personale';
        html += `
            <div class="file-card ${isSelected ? 'selected' : ''}" 
                 data-type="folder" data-id="${folder.id}"
                 onclick="${selectMode ? 'toggleSelection(event, \'folder\', ${folder.id})' : 'loadFolder(' + folder.id + ')'}">
                ${selectMode ? `<input type="checkbox" class="file-checkbox" 
                                       ${isSelected ? 'checked' : ''} 
                                       onclick="event.stopPropagation(); toggleSelection(event, 'folder', ${folder.id})">` : ''}
                <i class="fas fa-folder folder-icon"></i>
                <div class="file-name">${escapeHtml(folder.nome)}</div>
                <div class="file-meta">${folder.count || 0} elementi</div>
                <div class="company-info">(${escapeHtml(companyName)})</div>
                <div class="file-card-actions-bottom">
                    <button type="button" class="action-btn" 
                            onclick="event.stopPropagation(); handleRename(${folder.id}, 'folder', '${escapeHtml(folder.nome).replace(/'/g, '\\\'')}')" 
                            title="Rinomina" 
                            aria-label="Rinomina cartella ${escapeHtml(folder.nome).replace(/'/g, '\\\'')}"
                            tabindex="0">
                        <i class="fas fa-edit" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="action-btn" 
                            onclick="event.stopPropagation(); downloadFolder(${folder.id})" 
                            title="Scarica cartella come ZIP" 
                            aria-label="Scarica cartella ${escapeHtml(folder.nome).replace(/'/g, '\\\'')} come ZIP"
                            tabindex="0">
                        <i class="fas fa-download" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="action-btn delete" 
                            onclick="event.stopPropagation(); handleDelete(${folder.id}, 'folder', '${escapeHtml(folder.nome).replace(/'/g, '\\\'')}')" 
                            title="Elimina" 
                            aria-label="Elimina cartella ${escapeHtml(folder.nome).replace(/'/g, '\\\'')}"
                            tabindex="0">
                        <i class="fas fa-trash" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    // Render files
    data.files.forEach(file => {
        const icon = getFileIcon(file.mime_type || file.tipo_documento);
        const isSelected = selectedItems.has(`file-${file.id}`);
        const companyName = file.azienda_nome || 'Personale';
        html += `
            <div class="file-card ${isSelected ? 'selected' : ''}" 
                 data-type="file" data-id="${file.id}"
                 ondblclick="${selectMode ? '' : 'openFile(' + file.id + ')'}"
                 onclick="${selectMode ? 'toggleSelection(event, \'file\', ' + file.id + ')' : ''}">
                ${selectMode ? `<input type="checkbox" class="file-checkbox" 
                                       ${isSelected ? 'checked' : ''} 
                                       onclick="event.stopPropagation(); toggleSelection(event, 'file', ${file.id})">` : ''}
                <i class="fas ${icon.class}" style="color: ${icon.color}"></i>
                <div class="file-name">${escapeHtml(file.nome)}</div>
                <div class="file-meta">${formatFileSize(file.dimensione_file)}</div>
                <div class="company-info">(${escapeHtml(companyName)})</div>
                <div class="file-card-actions-bottom">
                    ${isDocumentEditable(file) ? `
                    <a href="/piattaforma-collaborativa/onlyoffice-editor.php?id=${file.id}" 
                       target="_blank"
                       class="action-btn btn-onlyoffice"
                       title="Apri con OnlyOffice" 
                       aria-label="Apri con OnlyOffice ${escapeHtml(file.nome)}"
                       onclick="event.stopPropagation(); return true;"
                       tabindex="0"
                       style="display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">
                        <i class="fas fa-file-word" aria-hidden="true"></i>
                    </a>` : ''}
                    <button type="button" class="action-btn" 
                            onclick="event.stopPropagation(); handleRename(${file.id}, 'file', '${escapeHtml(file.nome).replace(/'/g, '\\\'')}')" 
                            title="Rinomina" 
                            aria-label="Rinomina file ${escapeHtml(file.nome).replace(/'/g, '\\\'')}"
                            tabindex="0">
                        <i class="fas fa-edit" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="action-btn" 
                            onclick="event.stopPropagation(); openFile(${file.id})" 
                            title="Scarica file" 
                            aria-label="Scarica file ${escapeHtml(file.nome).replace(/'/g, '\\\'')}"
                            tabindex="0">
                        <i class="fas fa-download" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="action-btn delete" 
                            onclick="event.stopPropagation(); handleDelete(${file.id}, 'file', '${escapeHtml(file.nome).replace(/'/g, '\\\'')}')" 
                            title="Elimina" 
                            aria-label="Elimina file ${escapeHtml(file.nome).replace(/'/g, '\\\'')}"
                            tabindex="0">
                        <i class="fas fa-trash" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

// Update breadcrumb
function updateBreadcrumb(path) {
    const breadcrumb = document.getElementById('breadcrumb');
    let html = '<a href="#" onclick="loadFolder(null); return false;"><i class="fas fa-home"></i> Home</a>';
    
    path.forEach(item => {
        html += ` / <a href="#" onclick="loadFolder(${item.id}); return false;">${escapeHtml(item.nome)}</a>`;
    });
    
    breadcrumb.innerHTML = html;
}

// Show rename modal
function showRenameModal(id, type, currentName) {
    renameTarget = { id, type, name: currentName };
    document.getElementById('newItemName').value = currentName;
    document.getElementById('renameModal').classList.add('show');
}

// Helper functions for handling events
function handleDelete(id, type, name) {
    console.log('handleDelete called:', id, type, name);
    showDeleteModal(name, id, type);
}

function handleRename(id, type, name) {
    console.log('handleRename called:', id, type, name);
    showRenameModal(id, type, name);
}

// Assicuriamoci che le funzioni siano globali e disponibili
window.confirmDeleteFS = confirmDeleteFS;
window.handleDelete = handleDelete;
window.handleRename = handleRename;
window.showDeleteModal = showDeleteModal;
window.showRenameModal = showRenameModal;

// Le funzioni verranno esposte dopo la loro definizione

// Esponi le funzioni per i modal
window.showUploadModal = showUploadModal;
window.showNewFolderModal = showNewFolderModal;
window.closeModal = closeModal;
window.uploadFiles = uploadFiles;
window.createFolder = createFolder;
window.confirmRename = confirmRename;

// Esponi le funzioni per la navigazione
window.loadFolder = loadFolder;
window.selectTreeFolder = selectTreeFolder;
window.toggleTreeNode = toggleTreeNode;

// Esponi le funzioni per la selezione multipla
window.toggleSelectMode = toggleSelectMode;
window.toggleSelection = toggleSelection;
window.selectAllItems = selectAllItems;
window.deselectAllItems = deselectAllItems;
window.downloadSelected = downloadSelected;
window.deleteSelected = deleteSelected;

// Debug: verifica che la funzione sia disponibile
setTimeout(() => {
    console.log('After 100ms - confirmDeleteFS available:', typeof window.confirmDeleteFS === 'function');
}, 100);

// Show delete modal
function showDeleteModal(name, id, type) {
    console.log('showDeleteModal called:', name, id, type);
    deleteTarget = { id: id, type: type, name: name };
    console.log('deleteTarget set to:', deleteTarget);
    
    document.getElementById('deleteItemName').textContent = name;
    document.getElementById('confirmCheck').checked = false;
    document.getElementById('confirmBtn').disabled = true;
    document.getElementById('deleteModal').classList.add('show');
}

// File operations
function showUploadModal() {
    document.getElementById('uploadModal').classList.add('show');
}

function showNewFolderModal() {
    document.getElementById('folderModal').classList.add('show');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
}

// Upload handling
document.getElementById('uploadArea').addEventListener('click', function() {
    document.getElementById('fileInput').click();
});

document.getElementById('fileInput').addEventListener('change', function(e) {
    selectedFiles = Array.from(e.target.files);
    updateUploadList();
});

// Drag and drop
document.getElementById('uploadArea').addEventListener('dragover', function(e) {
    e.preventDefault();
    this.classList.add('dragover');
});

document.getElementById('uploadArea').addEventListener('dragleave', function(e) {
    e.preventDefault();
    this.classList.remove('dragover');
});

document.getElementById('uploadArea').addEventListener('drop', function(e) {
    e.preventDefault();
    this.classList.remove('dragover');
    selectedFiles = Array.from(e.dataTransfer.files);
    updateUploadList();
});

function updateUploadList() {
    const list = document.getElementById('uploadList');
    if (selectedFiles.length === 0) {
        list.innerHTML = '';
        return;
    }
    
    let html = '<p>File selezionati:</p><ul>';
    selectedFiles.forEach(file => {
        html += `<li>${escapeHtml(file.name)} (${formatFileSize(file.size)})</li>`;
    });
    html += '</ul>';
    list.innerHTML = html;
}

function uploadFiles() {
    if (selectedFiles.length === 0) {
        alert('Seleziona almeno un file');
        return;
    }
    
    const formData = new FormData();
    selectedFiles.forEach(file => {
        formData.append('files[]', file);
    });
    formData.append('folder_id', currentFolder || '');
    
    <?php if ($isSuperAdmin || $isUtenteSpeciale): ?>
    const companyId = document.getElementById('uploadCompanyId').value;
    if (companyId) {
        formData.append('azienda_id', companyId);
    }
    <?php else: ?>
    formData.append('azienda_id', '<?php echo $aziendaId; ?>');
    <?php endif; ?>
    
    fetch('backend/api/filesystem-simple-api.php?action=upload', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeModal('uploadModal');
            loadFolder(currentFolder);
            loadFolderTree(); // Refresh sidebar if folders changed
            selectedFiles = [];
            document.getElementById('fileInput').value = '';
            document.getElementById('uploadList').innerHTML = '';
        } else {
            alert('Errore: ' + (data.error || 'Upload fallito'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Errore di connessione');
    });
}

function createFolder() {
    const name = document.getElementById('folderName').value.trim();
    if (!name) {
        alert('Inserisci il nome della cartella');
        return;
    }
    
    const data = {
        action: 'create_folder',
        name: name,
        parent_id: currentFolder || null
    };
    
    <?php if ($isSuperAdmin || $isUtenteSpeciale): ?>
    const companyId = document.getElementById('folderCompanyId').value;
    if (companyId) {
        data.azienda_id = companyId;
    }
    <?php else: ?>
    data.azienda_id = '<?php echo $aziendaId; ?>'; 
    <?php endif; ?>
    
    fetch('backend/api/filesystem-simple-api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            closeModal('folderModal');
            loadFolder(currentFolder);
            loadFolderTree(); // Refresh sidebar
            document.getElementById('folderName').value = '';
        } else {
            alert('Errore: ' + (result.error || 'Creazione fallita'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Errore di connessione');
    });
}

function confirmRename() {
    if (!renameTarget) return;
    
    const newName = document.getElementById('newItemName').value.trim();
    if (!newName) {
        alert('Inserisci un nome valido');
        return;
    }
    
    fetch('backend/api/filesystem-simple-api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'rename',
            type: renameTarget.type,
            id: renameTarget.id,
            name: newName
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeModal('renameModal');
            loadFolder(currentFolder);
            loadFolderTree(); // Refresh sidebar
        } else {
            alert('Errore: ' + (data.error || 'Rinomina fallita'));
        }
        renameTarget = null;
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Errore di connessione');
    });
}

function confirmDeleteFS() {
    console.log('confirmDeleteFS called with deleteTarget:', deleteTarget);
    
    if (!deleteTarget) {
        console.error('deleteTarget is null');
        alert('Errore: nessun elemento selezionato per l\'eliminazione');
        return;
    }
    
    console.log('Sending delete request for:', deleteTarget);
    
    fetch('backend/api/filesystem-simple-api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'delete',
            type: deleteTarget.type,
            id: deleteTarget.id
        })
    })
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            throw new Error('HTTP error ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        console.log('Delete response:', data);
        if (data.success) {
            closeModal('deleteModal');
            loadFolder(currentFolder);
            loadFolderTree(); // Refresh sidebar
            deleteTarget = null;
        } else {
            alert('Errore: ' + (data.error || 'Eliminazione fallita'));
        }
    })
    .catch(error => {
        console.error('Delete error:', error);
        alert('Errore durante l\'eliminazione: ' + error.message);
    });
}

function openFile(fileId) {
    window.open('backend/api/filesystem-simple-api.php?action=download&id=' + fileId, '_blank');
}

// Funzione per verificare se un file è modificabile online
function isDocumentEditable(file) {
    // Debug per capire la struttura del file
    console.log('Checking if editable:', file);
    
    // Prova a ottenere il nome del file da varie sorgenti
    let fileName = file.file_path || file.nome || file.titolo || '';
    
    // Se non troviamo un'estensione nel nome/path, proviamo a dedurla dal mime_type
    if (!fileName.includes('.') && file.mime_type) {
        const mimeToExt = {
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'docx',
            'application/msword': 'doc',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'xlsx',
            'application/vnd.ms-excel': 'xls',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation': 'pptx',
            'application/vnd.ms-powerpoint': 'ppt',
            'application/vnd.oasis.opendocument.text': 'odt',
            'application/vnd.oasis.opendocument.spreadsheet': 'ods',
            'application/vnd.oasis.opendocument.presentation': 'odp',
            'text/plain': 'txt',
            'text/csv': 'csv',
            'application/rtf': 'rtf'
        };
        
        const ext = mimeToExt[file.mime_type];
        if (ext) {
            console.log('File is editable based on mime_type:', file.mime_type, '->', ext);
            return true;
        }
    }
    
    // Estrai l'estensione dal nome o percorso
    const extension = fileName.toLowerCase().split('.').pop();
    
    // Supporta tutti i formati OnlyOffice
    const supportedFormats = [
        // Word
        'docx', 'doc', 'odt', 'rtf', 'txt',
        // Excel
        'xlsx', 'xls', 'ods', 'csv',
        // PowerPoint
        'pptx', 'ppt', 'odp'
    ];
    
    const isEditable = supportedFormats.includes(extension);
    console.log('File editable check:', fileName, 'extension:', extension, 'editable:', isEditable);
    
    return isEditable;
}

// La funzione editDocument non è più necessaria - usiamo link diretti HTML
// Il bottone OnlyOffice è ora un tag <a> che apre direttamente onlyoffice-editor.php

function searchFiles() {
    const query = document.getElementById('searchInput').value.trim();
    if (query.length < 2 && query.length > 0) return;
    
    if (query === '') {
        loadFolder(currentFolder);
        return;
    }
    
    // Simple search implementation
    fetch('backend/api/filesystem-simple-api.php?action=search&q=' + encodeURIComponent(query))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderFiles(data.data);
            }
        })
        .catch(error => console.error('Search error:', error));
}

// Utility functions
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatFileSize(bytes) {
    if (!bytes) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function getFileIcon(type) {
    if (!type) return { class: 'fa-file', color: '#6b7280' };
    
    if (type.includes('pdf')) return { class: 'fa-file-pdf', color: '#ef4444' };
    if (type.includes('word') || type.includes('doc')) return { class: 'fa-file-word', color: '#3b82f6' };
    if (type.includes('excel') || type.includes('sheet')) return { class: 'fa-file-excel', color: '#10b981' };
    
    return { class: 'fa-file', color: '#6b7280' };
}

// Folder tree functionality
function loadFolderTree() {
    const treeContainer = document.getElementById('folderTree');
    treeContainer.innerHTML = '<div class="loading" ><i class="fas fa-spinner fa-spin"></i> Caricamento...</div>';
    
    fetch('backend/api/filesystem-simple-api.php?action=tree')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderFolderTree(data.tree);
            } else {
                treeContainer.innerHTML = '<div >Errore nel caricamento</div>';
            }
        })
        .catch(error => {
            console.error('Error loading tree:', error);
            treeContainer.innerHTML = '<div >Errore di connessione</div>';
        });
}

function renderFolderTree(folders) {
    const treeContainer = document.getElementById('folderTree');
    let html = '';
    
    // Add home link
    html += `
        <div class="tree-node">
            <div class="tree-item ${currentFolder === null ? 'active' : ''}" onclick="selectTreeFolder(null)">
                <div class="tree-toggle">
                    <i class="fas fa-home"></i>
                </div>
                <span>Home</span>
            </div>
        </div>
    `;
    
    // Render folder tree
    html += renderTreeNodes(folders, 0);
    
    treeContainer.innerHTML = html;
}

function renderTreeNodes(nodes, level) {
    let html = '';
    
    nodes.forEach(node => {
        const hasChildren = node.children && node.children.length > 0;
        const isActive = currentFolder == node.id;
        const nodeId = `tree-node-${node.id}`;
        
        html += `
            <div class="tree-node">
                <div class="tree-item ${isActive ? 'active' : ''}" onclick="selectTreeFolder(${node.id})">
                    <div class="tree-toggle ${hasChildren ? '' : 'invisible'}" onclick="event.stopPropagation(); toggleTreeNode('${nodeId}')">
                        <i class="fas fa-chevron-right"></i>
                    </div>
                    <i class="fas fa-folder" ></i>
                    <span>${escapeHtml(node.nome)}</span>
                </div>
        `;
        
        if (hasChildren) {
            html += `
                <div class="tree-children" id="${nodeId}">
                    ${renderTreeNodes(node.children, level + 1)}
                </div>
            `;
        }
        
        html += '</div>';
    });
    
    return html;
}

function selectTreeFolder(folderId) {
    // Update active state
    document.querySelectorAll('.tree-item').forEach(item => item.classList.remove('active'));
    event.target.closest('.tree-item').classList.add('active');
    
    // Load folder
    loadFolder(folderId);
}

function toggleTreeNode(nodeId) {
    const node = document.getElementById(nodeId);
    const toggle = event.target.closest('.tree-toggle');
    
    if (node.classList.contains('show')) {
        node.classList.remove('show');
        toggle.classList.remove('expanded');
    } else {
        node.classList.add('show');
        toggle.classList.add('expanded');
    }
}

// Multi-selection functions
function toggleSelectMode() {
    selectMode = !selectMode;
    selectedItems.clear();
    
    const toolbar = document.getElementById('selectionToolbar');
    if (selectMode) {
        toolbar.classList.add('show');
    } else {
        toolbar.classList.remove('show');
    }
    
    // Re-render current view
    loadFolder(currentFolder);
}

function toggleSelection(event, type, id) {
    const itemKey = `${type}-${id}`;
    
    if (selectedItems.has(itemKey)) {
        selectedItems.delete(itemKey);
    } else {
        selectedItems.add(itemKey);
    }
    
    updateSelectionCount();
    
    // Update visual state
    const card = event.target.closest('.file-card');
    if (card) {
        card.classList.toggle('selected');
    }
}

function selectAllItems() {
    // Select all visible items
    document.querySelectorAll('.file-card').forEach(card => {
        const type = card.dataset.type;
        const id = card.dataset.id;
        if (type && id) {
            selectedItems.add(`${type}-${id}`);
            card.classList.add('selected');
            const checkbox = card.querySelector('.file-checkbox');
            if (checkbox) checkbox.checked = true;
        }
    });
    updateSelectionCount();
}

function deselectAllItems() {
    selectedItems.clear();
    document.querySelectorAll('.file-card').forEach(card => {
        card.classList.remove('selected');
        const checkbox = card.querySelector('.file-checkbox');
        if (checkbox) checkbox.checked = false;
    });
    updateSelectionCount();
}

function updateSelectionCount() {
    document.getElementById('selectedCount').textContent = selectedItems.size;
}

// Download functions
function downloadSelected() {
    if (selectedItems.size === 0) {
        alert('Nessun elemento selezionato');
        return;
    }
    
    // Prepare data for download
    const items = Array.from(selectedItems).map(item => {
        const [type, id] = item.split('-');
        return { type, id };
    });
    
    // Create form and submit
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'backend/api/filesystem-simple-api.php?action=download_multiple';
    form.target = '_blank';
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'items';
    input.value = JSON.stringify(items);
    form.appendChild(input);
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

function downloadFolder(folderId) {
    window.open('backend/api/filesystem-simple-api.php?action=download_folder&id=' + folderId, '_blank');
}

// Delete selected items
function deleteSelected() {
    if (selectedItems.size === 0) {
        alert('Nessun elemento selezionato');
        return;
    }
    
    if (!confirm(`Vuoi eliminare ${selectedItems.size} elementi selezionati?`)) {
        return;
    }
    
    // Prepare items for deletion
    const items = Array.from(selectedItems).map(item => {
        const [type, id] = item.split('-');
        return { type, id };
    });
    
    // Send delete request
    fetch('backend/api/filesystem-simple-api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'delete_multiple',
            items: items
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            selectedItems.clear();
            loadFolder(currentFolder);
            loadFolderTree();
            updateSelectionCount();
        } else {
            alert('Errore: ' + (data.error || 'Eliminazione fallita'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Errore di connessione');
    });
}

// Esponi solo le funzioni necessarie nello scope globale
window.isDocumentEditable = isDocumentEditable;
window.openFile = openFile;

// Debug: verifica che le funzioni siano disponibili
console.log('Functions loaded:', {
    isDocumentEditable: typeof window.isDocumentEditable,
    openFile: typeof window.openFile
});
</script>

<?php include 'components/footer.php'; ?>