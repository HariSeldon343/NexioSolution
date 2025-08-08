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

<style>
/* Simple Clean Styles */
.filesystem-container {
    padding: 20px;
    max-width: 1400px;
    margin: 0 auto;
    display: flex;
    gap: 20px;
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
    gap: 10px;
}

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
}

.file-card:hover {
    background: #f3f4f6;
    transform: translateY(-2px);
}

.file-card-actions {
    position: absolute;
    top: 5px;
    right: 5px;
    display: none;
    gap: 5px;
}

.file-card:hover .file-card-actions {
    display: flex;
}

.action-btn {
    width: 24px;
    height: 24px;
    border: none;
    border-radius: 4px;
    background: rgba(0,0,0,0.1);
    color: #374151;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    transition: all 0.2s;
}

.action-btn:hover {
    background: rgba(0,0,0,0.2);
}

.action-btn.delete:hover {
    background: #ef4444;
    color: white;
}

.file-card {
    position: relative;
}

.file-card i {
    font-size: 48px;
    margin-bottom: 10px;
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
            <div class="loading" style="padding: 20px; text-align: center; color: #6b7280;">
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
                    <button class="btn btn-primary" onclick="showUploadModal()">
                        <i class="fas fa-upload"></i> Carica File
                    </button>
                    <button class="btn btn-secondary" onclick="showNewFolderModal()">
                        <i class="fas fa-folder-plus"></i> Nuova Cartella
                    </button>
                </div>
                
                <div>
                    <input type="text" class="form-control" placeholder="Cerca..." 
                           id="searchInput" onkeyup="searchFiles()" style="width: 200px;">
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
            <button onclick="closeModal('uploadModal')" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
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
                <i class="fas fa-cloud-upload-alt" style="font-size: 48px; color: #9ca3af; margin-bottom: 10px;"></i>
                <p>Trascina qui i file o clicca per selezionare</p>
                <p style="font-size: 12px; color: #6b7280;">Formati: PDF, DOC, DOCX (Max 10MB)</p>
                <input type="file" id="fileInput" multiple accept=".pdf,.doc,.docx" style="display: none;">
            </div>
            
            <div id="uploadList" style="margin-top: 20px;"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('uploadModal')">Annulla</button>
            <button class="btn btn-primary" onclick="uploadFiles()">Carica</button>
        </div>
    </div>
</div>

<!-- New Folder Modal -->
<div class="modal" id="folderModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Nuova Cartella</h3>
            <button onclick="closeModal('folderModal')" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
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
            <button class="btn btn-secondary" onclick="closeModal('folderModal')">Annulla</button>
            <button class="btn btn-primary" onclick="createFolder()">Crea</button>
        </div>
    </div>
</div>

<!-- Rename Modal -->
<div class="modal" id="renameModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Rinomina</h3>
            <button onclick="closeModal('renameModal')" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Nuovo nome:</label>
                <input type="text" class="form-control" id="newItemName" placeholder="Inserisci il nuovo nome">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('renameModal')">Annulla</button>
            <button class="btn btn-primary" onclick="confirmRename()">Rinomina</button>
        </div>
    </div>
</div>

<!-- Delete Confirm Modal -->
<div class="modal" id="deleteModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="color: #ef4444;">Conferma Eliminazione</h3>
            <button onclick="closeModal('deleteModal')" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        <div class="modal-body">
            <p>Sei sicuro di voler eliminare <strong id="deleteItemName"></strong>?</p>
            <p style="color: #6b7280; font-size: 14px;">Questa azione non può essere annullata.</p>
            
            <div style="margin-top: 20px;">
                <label>
                    <input type="checkbox" id="confirmCheck" onchange="document.getElementById('confirmBtn').disabled = !this.checked;">
                    Confermo di voler eliminare questo elemento
                </label>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('deleteModal')">Annulla</button>
            <button class="btn btn-danger" id="confirmBtn" onclick="confirmDeleteFS()" disabled>Elimina</button>
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

// Initial load
document.addEventListener('DOMContentLoaded', function() {
    console.log('Filesystem JS initialized');
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
        html += `
            <div class="file-card" onclick="loadFolder(${folder.id})">
                <div class="file-card-actions">
                    <button class="action-btn" onclick="event.stopPropagation(); handleRename(${folder.id}, 'folder', '${escapeHtml(folder.nome).replace(/'/g, '\\\'')}')" title="Rinomina">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="action-btn delete" onclick="event.stopPropagation(); handleDelete(${folder.id}, 'folder', '${escapeHtml(folder.nome).replace(/'/g, '\\\'')}')" title="Elimina">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <i class="fas fa-folder folder-icon"></i>
                <div class="file-name">${escapeHtml(folder.nome)}</div>
                <div class="file-meta">${folder.count || 0} elementi</div>
            </div>
        `;
    });
    
    // Render files
    data.files.forEach(file => {
        const icon = getFileIcon(file.mime_type || file.tipo_documento);
        html += `
            <div class="file-card" ondblclick="openFile(${file.id})">
                <div class="file-card-actions">
                    <button class="action-btn" onclick="event.stopPropagation(); handleRename(${file.id}, 'file', '${escapeHtml(file.nome).replace(/'/g, '\\\'')}')" title="Rinomina">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="action-btn delete" onclick="event.stopPropagation(); handleDelete(${file.id}, 'file', '${escapeHtml(file.nome).replace(/'/g, '\\\'')}')" title="Elimina">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <i class="fas ${icon.class}" style="color: ${icon.color}"></i>
                <div class="file-name">${escapeHtml(file.nome)}</div>
                <div class="file-meta">${formatFileSize(file.dimensione_file)}</div>
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
    treeContainer.innerHTML = '<div class="loading" style="padding: 20px; text-align: center; color: #6b7280;"><i class="fas fa-spinner fa-spin"></i> Caricamento...</div>';
    
    fetch('backend/api/filesystem-simple-api.php?action=tree')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderFolderTree(data.tree);
            } else {
                treeContainer.innerHTML = '<div style="padding: 20px; text-align: center; color: #ef4444;">Errore nel caricamento</div>';
            }
        })
        .catch(error => {
            console.error('Error loading tree:', error);
            treeContainer.innerHTML = '<div style="padding: 20px; text-align: center; color: #ef4444;">Errore di connessione</div>';
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
                    <i class="fas fa-folder" style="color: #fbbf24; margin-right: 8px;"></i>
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
</script>

<?php include 'components/footer.php'; ?>