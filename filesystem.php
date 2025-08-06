<?php
require_once 'backend/config/config.php';
require_once 'backend/middleware/Auth.php';
require_once 'backend/utils/PermissionManager.php';

// Verifica autenticazione
$auth = Auth::getInstance();
$auth->requireAuth();

$user = $auth->getUser();
$currentAzienda = $auth->getCurrentAzienda();
$aziendaId = $currentAzienda['azienda_id'] ?? $currentAzienda['id'] ?? null;
$isSuperAdmin = $auth->isSuperAdmin();
$isUtenteSpeciale = $user['ruolo'] === 'utente_speciale';

// Gestione accesso filesystem
if (!$aziendaId && !($isSuperAdmin || $isUtenteSpeciale)) {
    // Solo utenti normali devono avere un'azienda
    header('Location: dashboard.php?error=no_company');
    exit;
}

// Per super admin e utenti speciali senza azienda, gestisci file globali
if (!$aziendaId && ($isSuperAdmin || $isUtenteSpeciale)) {
    $aziendaId = 0; // ID speciale per file globali
}

// Permission Manager non necessario - i permessi sono gestiti tramite Auth
// $permManager = PermissionManager::getInstance();

$pageTitle = 'Gestione Documenti';
include 'components/header.php';
require_once 'components/page-header.php';
?>

<!-- CSS specifici per il file explorer -->
<link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/dashboard-clean.css">
<link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/file-explorer.css">

<style>
/* File Explorer Layout */
.file-explorer-container {
    display: flex;
    gap: 1.5rem;
    min-height: calc(100vh - 200px);
}

/* Sidebar Albero Cartelle */
.folder-tree-sidebar {
    width: 280px;
    background: white;
    border-radius: 8px;
    padding: 0;
    border: 1px solid #e5e7eb;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.folder-tree-header {
    padding: 1rem;
    border-bottom: 1px solid #e5e7eb;
    background: #f9fafb;
}

.folder-tree-header h3 {
    font-size: 16px;
    font-weight: 600;
    color: #111827;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.folder-tree-content {
    flex: 1;
    overflow-y: auto;
    padding: 0.5rem;
}

/* Area Principale */
.file-explorer-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

/* Breadcrumb */
.breadcrumb-container {
    background: white;
    border-radius: 8px;
    padding: 1rem;
    border: 1px solid #e5e7eb;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.breadcrumb {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 14px;
}

.breadcrumb-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.breadcrumb-item a {
    color: #6b7280;
    text-decoration: none;
    transition: color 0.2s;
}

.breadcrumb-item a:hover {
    color: #2d5a9f;
}

.breadcrumb-separator {
    color: #9ca3af;
}

/* Toolbar */
.file-toolbar {
    background: white;
    border-radius: 8px;
    padding: 1rem;
    border: 1px solid #e5e7eb;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.toolbar-actions {
    display: flex;
    gap: 0.5rem;
}

.toolbar-views {
    display: flex;
    gap: 0.5rem;
}

.view-btn {
    padding: 8px 12px;
    border: 1px solid #e5e7eb;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    color: #6b7280;
}

.view-btn:hover {
    border-color: #2d5a9f;
    color: #2d5a9f;
}

.view-btn.active {
    background: #2d5a9f;
    color: white;
    border-color: #2d5a9f;
}

/* Files Container */
.files-container {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    border: 1px solid #e5e7eb;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    flex: 1;
    overflow: auto;
    position: relative;
}

/* Vista Griglia */
.files-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 1rem;
}

.file-item {
    padding: 1rem;
    border-radius: 8px;
    border: 1px solid transparent;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
    position: relative;
}

.file-item:hover {
    background: #f9fafb;
    border-color: #e5e7eb;
}

.file-item.selected {
    background: #dbeafe;
    border-color: #2d5a9f;
}

.file-icon {
    font-size: 48px;
    color: #6b7280;
    margin-bottom: 0.5rem;
}

.folder-icon {
    color: #fbbf24;
}

.file-name {
    font-size: 13px;
    color: #374151;
    word-break: break-word;
    line-height: 1.3;
}

.file-meta {
    font-size: 11px;
    color: #9ca3af;
    margin-top: 4px;
}

/* Vista Lista */
.files-list {
    width: 100%;
}

.files-table {
    width: 100%;
    border-collapse: collapse;
}

.files-table th {
    text-align: left;
    padding: 0.75rem;
    font-size: 12px;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1px solid #e5e7eb;
    background: #f9fafb;
    cursor: pointer;
    user-select: none;
}

.files-table th:hover {
    color: #374151;
}

.files-table td {
    padding: 0.75rem;
    border-bottom: 1px solid #f3f4f6;
    font-size: 14px;
}

.files-table tr:hover {
    background: #f9fafb;
}

.file-list-icon {
    font-size: 20px;
    margin-right: 0.5rem;
    vertical-align: middle;
}

/* Upload Area */
.upload-dropzone {
    border: 2px dashed #e5e7eb;
    border-radius: 8px;
    padding: 3rem;
    text-align: center;
    background: #f9fafb;
    transition: all 0.3s;
}

.upload-dropzone.drag-over {
    border-color: #2d5a9f;
    background: #dbeafe;
}

.upload-icon {
    font-size: 48px;
    color: #9ca3af;
    margin-bottom: 1rem;
}

/* Dynamic Drop Zone */
.drop-zone {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(45, 90, 159, 0.1);
    border: 3px dashed #2d5a9f;
    border-radius: 8px;
    z-index: 100;
    display: flex;
    align-items: center;
    justify-content: center;
}

.drop-zone-content {
    text-align: center;
    color: #2d5a9f;
    font-weight: 600;
}

.drop-zone-icon {
    font-size: 48px;
    margin-bottom: 1rem;
}

.drop-zone-text {
    font-size: 16px;
}

/* Context Menu */
.context-menu {
    position: fixed;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    padding: 0.5rem 0;
    z-index: 1000;
    display: none;
    min-width: 180px;
}

.context-menu-item {
    padding: 0.5rem 1rem;
    cursor: pointer;
    font-size: 14px;
    color: #374151;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.context-menu-item:hover {
    background: #f3f4f6;
}

.context-menu-item.danger {
    color: #ef4444;
}

.context-menu-divider {
    height: 1px;
    background: #e5e7eb;
    margin: 0.5rem 0;
}

/* Loader */
.files-loader {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 4rem;
}

.files-loader .spinner {
    width: 40px;
    height: 40px;
    border: 3px solid #e5e7eb;
    border-top-color: #2d5a9f;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

/* Empty State */
.files-empty {
    text-align: center;
    padding: 4rem;
    color: #9ca3af;
}

.files-empty i {
    font-size: 64px;
    margin-bottom: 1rem;
    display: block;
    color: #e5e7eb;
}

/* Responsive */
@media (max-width: 768px) {
    .file-explorer-container {
        flex-direction: column;
    }
    
    .folder-tree-sidebar {
        width: 100%;
        max-height: 200px;
    }
    
    .files-grid {
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    }
    
    .file-toolbar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .toolbar-actions,
    .toolbar-views {
        justify-content: center;
    }
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    backdrop-filter: blur(4px);
}

.modal-content {
    background: white;
    margin: 5% auto;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        transform: translateY(-30px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #111827;
}

.modal-body {
    padding: 1.5rem;
}

.close {
    background: none;
    border: none;
    font-size: 24px;
    color: #9ca3af;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s;
}

.close:hover {
    background: #f3f4f6;
    color: #374151;
}

.form-group {
    margin-bottom: 1rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #374151;
}

.form-control {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.2s;
}

.form-control:focus {
    outline: none;
    border-color: #2d5a9f;
    box-shadow: 0 0 0 3px rgba(45, 90, 159, 0.1);
}

.form-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
    margin-top: 1.5rem;
}

.btn {
    padding: 0.75rem 1rem;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
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

.btn-secondary:hover {
    background: #d1d5db;
}

.btn-danger {
    background: #ef4444;
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
}

.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.text-muted {
    color: #6b7280;
    font-size: 14px;
}

/* Upload Progress */
.upload-item {
    margin-bottom: 1rem;
}

.upload-file-name {
    font-size: 14px;
    margin-bottom: 0.5rem;
    color: #374151;
}

.progress {
    background: #f3f4f6;
    border-radius: 4px;
    overflow: hidden;
    height: 24px;
}

.progress-bar {
    background: #2d5a9f;
    height: 100%;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 500;
    transition: width 0.3s ease;
}
</style>

<!-- Header della pagina -->
<div class="page-header">
    <h1><i class="fas fa-folder-open"></i> Gestione Documenti</h1>
    <div class="page-subtitle">
        Gestisci i file e le cartelle della tua azienda
        <?php if ($currentAzienda): ?>
            • <?php echo htmlspecialchars($currentAzienda['nome']); ?>
        <?php endif; ?>
    </div>
</div>

<!-- Container principale -->
<div class="file-explorer-container">
    <!-- Sidebar Albero Cartelle -->
    <aside class="folder-tree-sidebar">
        <div class="folder-tree-header">
            <h3><i class="fas fa-sitemap"></i> Cartelle</h3>
        </div>
        <div class="folder-tree-content" id="folderTree">
            <div class="files-loader">
                <div class="spinner"></div>
            </div>
        </div>
    </aside>
    
    <!-- Area Principale -->
    <main class="file-explorer-main">
        <!-- Breadcrumb -->
        <div class="breadcrumb-container">
            <nav class="breadcrumb" id="breadcrumb">
                <div class="breadcrumb-item">
                    <i class="fas fa-home"></i>
                    <a href="#" data-folder-id="0">Home</a>
                </div>
            </nav>
        </div>
        
        <!-- Toolbar -->
        <div class="file-toolbar">
            <div class="toolbar-actions">
                <button class="btn btn-primary" onclick="safeFileExplorerCall('showUploadModal')">
                    <i class="fas fa-upload"></i> Carica File
                </button>
                <button class="btn btn-secondary" onclick="safeFileExplorerCall('showNewFolderModal')">
                    <i class="fas fa-folder-plus"></i> Nuova Cartella
                </button>
                <?php if ($isSuperAdmin || $isUtenteSpeciale): ?>
                <button class="btn btn-secondary" onclick="safeFileExplorerCall('toggleSelection')">
                    <i class="fas fa-check-square"></i> Seleziona
                </button>
                <?php endif; ?>
            </div>
            
            <div class="toolbar-views">
                <div class="search-box" style="margin-right: 1rem;">
                    <input type="text" class="form-control" placeholder="Cerca file..." 
                           id="searchFiles" onkeyup="safeFileExplorerCall('searchFiles', this.value)">
                </div>
                <button class="view-btn active" data-view="grid" title="Vista griglia">
                    <i class="fas fa-th"></i>
                </button>
                <button class="view-btn" data-view="list" title="Vista lista">
                    <i class="fas fa-list"></i>
                </button>
            </div>
        </div>
        
        <!-- Files Container -->
        <div class="files-container" id="filesContainer">
            <div class="files-loader">
                <div class="spinner"></div>
            </div>
        </div>
    </main>
</div>

<!-- Context Menu -->
<div class="context-menu" id="contextMenu">
    <div class="context-menu-item" onclick="safeFileExplorerCall('openFile')">
        <i class="fas fa-external-link-alt"></i> Apri
    </div>
    <div class="context-menu-item" onclick="safeFileExplorerCall('downloadFile')">
        <i class="fas fa-download"></i> Scarica
    </div>
    <div class="context-menu-divider"></div>
    <div class="context-menu-item" onclick="safeFileExplorerCall('renameItem')">
        <i class="fas fa-edit"></i> Rinomina
    </div>
    <div class="context-menu-item" onclick="safeFileExplorerCall('showProperties')">
        <i class="fas fa-info-circle"></i> Proprietà
    </div>
    <?php if ($isSuperAdmin || !$isUtenteSpeciale): ?>
    <div class="context-menu-divider"></div>
    <div class="context-menu-item danger" onclick="safeFileExplorerCall('deleteItem')">
        <i class="fas fa-trash"></i> Elimina
    </div>
    <?php endif; ?>
</div>

<!-- Modal Upload -->
<div class="modal" id="uploadModal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3>Carica File</h3>
            <button type="button" class="close" onclick="safeFileExplorerCall('closeModal', 'uploadModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="upload-dropzone" id="uploadDropzone">
                <i class="fas fa-cloud-upload-alt upload-icon"></i>
                <h4>Trascina qui i file da caricare</h4>
                <p>oppure</p>
                <button class="btn btn-primary" onclick="document.getElementById('fileInput').click()">
                    Seleziona File
                </button>
                <input type="file" id="fileInput" multiple style="display: none;" onchange="safeFileExplorerCall('handleFileSelect', this.files)">
            </div>
            <div id="uploadProgress" style="display: none; margin-top: 1rem;">
                <!-- Progress bars verranno aggiunti qui -->
            </div>
        </div>
    </div>
</div>

<!-- Modal Nuova Cartella -->
<div class="modal" id="newFolderModal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Crea Nuova Cartella</h3>
            <button type="button" class="close" onclick="safeFileExplorerCall('closeModal', 'newFolderModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form onsubmit="safeFileExplorerCall('createFolder', event)">
                <div class="form-group">
                    <label class="form-label">Nome Cartella</label>
                    <input type="text" class="form-control" id="folderName" required 
                           placeholder="Inserisci il nome della cartella">
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="safeFileExplorerCall('closeModal', 'newFolderModal')">
                        Annulla
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-folder-plus"></i> Crea Cartella
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Rinomina -->
<div class="modal" id="renameModal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Rinomina</h3>
            <button type="button" class="close" onclick="safeFileExplorerCall('closeModal', 'renameModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form onsubmit="safeFileExplorerCall('performRename', event)">
                <div class="form-group">
                    <label class="form-label">Nuovo Nome</label>
                    <input type="text" class="form-control" id="newItemName" required>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="safeFileExplorerCall('closeModal', 'renameModal')">
                        Annulla
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Salva
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Conferma Eliminazione -->
<div class="modal" id="deleteModal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 style="color: #ef4444;"><i class="fas fa-exclamation-triangle"></i> Conferma Eliminazione</h3>
            <button type="button" class="close" onclick="safeFileExplorerCall('closeModal', 'deleteModal')">&times;</button>
        </div>
        <div class="modal-body">
            <p>Sei sicuro di voler eliminare <strong id="deleteItemName"></strong>?</p>
            <p class="text-muted">Questa azione non può essere annullata.</p>
            
            <div class="form-group" style="margin-top: 1rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" id="confirmDelete" onchange="document.getElementById('deleteConfirmBtn').disabled = !this.checked">
                    <span>Sono sicuro di voler eliminare questo elemento</span>
                </label>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="safeFileExplorerCall('closeModal', 'deleteModal')">
                    Annulla
                </button>
                <button type="button" class="btn btn-danger" onclick="safeFileExplorerCall('confirmDelete')" id="deleteConfirmBtn" disabled>
                    <i class="fas fa-trash"></i> Elimina
                </button>
            </div>
        </div>
    </div>
</div>

<!-- External JavaScript -->
<script src="<?php echo APP_PATH; ?>/assets/js/file-explorer.js"></script>
<script>
// Configurazione globale per il file explorer
window.CONFIG = {
    apiUrl: '<?php echo APP_PATH; ?>/backend/api',
    currentUser: <?php echo json_encode([
        'id' => $user['id'],
        'ruolo' => $user['ruolo'],
        'isSuperAdmin' => $isSuperAdmin,
        'isUtenteSpeciale' => $isUtenteSpeciale
    ]); ?>,
    currentAzienda: <?php echo json_encode($currentAzienda); ?>,
    aziendaId: <?php echo json_encode($aziendaId); ?>
};

// Funzione sicura per chiamare metodi di FileExplorer
function safeFileExplorerCall(method, ...args) {
    // Se FileExplorer non esiste ancora, inizializzalo
    if (typeof window.FileExplorer === 'undefined' || window.FileExplorer === null) {
        console.log('FileExplorer not ready, initializing...');
        
        // Controlla se FileExplorerManager è disponibile
        if (typeof FileExplorerManager !== 'undefined') {
            const explorerConfig = {
                companyId: CONFIG.aziendaId,
                apiBaseUrl: CONFIG.apiUrl,
                container: '.file-explorer-container',
                currentFolder: null,
                view: 'grid',
                contextMenu: true,
                dragDrop: true,
                multiSelect: true
            };
            
            window.FileExplorer = new FileExplorerManager(explorerConfig);
            console.log('FileExplorer initialized successfully');
        } else {
            console.error('FileExplorerManager class not available yet');
            
            // Riprova dopo un breve delay
            setTimeout(() => safeFileExplorerCall(method, ...args), 100);
            return;
        }
    }
    
    // Controlla se il metodo esiste
    if (typeof window.FileExplorer[method] === 'function') {
        console.log('Calling FileExplorer.' + method, args);
        return window.FileExplorer[method](...args);
    } else {
        console.error('Method not found:', method);
        console.log('Available methods:', Object.getOwnPropertyNames(Object.getPrototypeOf(window.FileExplorer)));
    }
}

// Inizializza il file explorer
document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM loaded, checking FileExplorerManager...');
    console.log('Available classes:', {
        FileExplorerManager: typeof FileExplorerManager,
        FileExplorer: typeof FileExplorer
    });
    
    if (typeof FileExplorerManager !== 'undefined') {
        try {
            console.log('Creating FileExplorerManager with config:', {
                companyId: CONFIG.aziendaId,
                apiBaseUrl: CONFIG.apiUrl
            });
            
            // Create configuration for the file explorer
            const explorerConfig = {
                companyId: CONFIG.aziendaId,
                apiBaseUrl: CONFIG.apiUrl,
                container: '.file-explorer-container',
                currentFolder: null, // Start with root folder (null, not 0)
                view: 'grid',
                contextMenu: true,
                dragDrop: true,
                multiSelect: true
            };
            
            console.log('Explorer config:', explorerConfig);
            
            // Initialize the FileExplorerManager and assign to global FileExplorer
            window.FileExplorer = new FileExplorerManager(explorerConfig);
            
            // Check if init completed successfully
            if (window.FileExplorer && window.FileExplorer.elements) {
                console.log('FileExplorer initialized successfully');
                console.log('Available elements:', Object.keys(window.FileExplorer.elements));
            } else {
                console.warn('FileExplorer initialization completed but elements might not be ready');
                console.log('FileExplorer object:', window.FileExplorer);
            }
        } catch (error) {
            console.error('Error initializing FileExplorer:', error);
            console.error('Error stack:', error.stack);
        }
    } else {
        console.error('FileExplorerManager class not found');
        console.log('Available on window:', Object.keys(window).filter(key => key.includes('File')));
    }
});
</script>

<?php include 'components/footer.php'; ?>