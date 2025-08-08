/**
 * ISO Compliance Document Management - Frontend JavaScript
 * Handles UI interactions and API communications
 * 
 * @version 1.0.0
 */

(function() {
    'use strict';
    
    // Configuration
    const API_BASE = '/backend/api/iso-compliance-api.php';
    
    // State
    let currentFolderId = null;
    let selectedDocuments = new Set();
    let folderTree = {};
    let activeStandards = [];
    
    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', init);
    
    /**
     * Initialize the application
     */
    function init() {
        loadFolderTree();
        loadActiveStandards();
        loadDocuments();
        bindEventHandlers();
        initializeSearch();
    }
    
    /**
     * Bind event handlers
     */
    function bindEventHandlers() {
        // Upload button
        document.getElementById('btnUpload').addEventListener('click', showUploadModal);
        
        // Confirm upload
        document.getElementById('btnConfirmUpload').addEventListener('click', handleUpload);
        
        // New folder button
        document.getElementById('btnNewFolder').addEventListener('click', showNewFolderDialog);
        
        // Bulk download
        document.getElementById('btnBulkDownload').addEventListener('click', handleBulkDownload);
        
        // Document selection
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('doc-checkbox')) {
                handleDocumentSelection(e.target);
            }
        });
    }
    
    /**
     * Load folder tree
     */
    async function loadFolderTree() {
        try {
            const response = await fetch(`${API_BASE}/folders?recursive=true`);
            const data = await response.json();
            
            if (data.data) {
                folderTree = data.data;
                renderFolderTree(data.data);
            }
        } catch (error) {
            console.error('Error loading folder tree:', error);
            showError('Errore nel caricamento delle cartelle');
        }
    }
    
    /**
     * Render folder tree
     */
    function renderFolderTree(folders, parentEl = null) {
        const container = parentEl || document.getElementById('folderTree');
        container.innerHTML = '';
        
        const ul = document.createElement('ul');
        ul.className = 'folder-list';
        
        folders.forEach(folder => {
            const li = document.createElement('li');
            li.className = 'folder-item';
            
            const folderDiv = document.createElement('div');
            folderDiv.className = 'folder-name';
            folderDiv.innerHTML = `
                <i class="fas fa-folder${folder.children?.length ? '' : '-open'} me-2"></i>
                <span>${folder.nome}</span>
                <span class="badge bg-secondary ms-auto">${folder.document_count || 0}</span>
            `;
            
            folderDiv.addEventListener('click', () => selectFolder(folder.id));
            
            li.appendChild(folderDiv);
            
            // Add children recursively
            if (folder.children && folder.children.length > 0) {
                const childUl = document.createElement('ul');
                childUl.className = 'folder-children';
                folder.children.forEach(child => {
                    renderFolderTree([child], childUl);
                });
                li.appendChild(childUl);
            }
            
            ul.appendChild(li);
        });
        
        container.appendChild(ul);
    }
    
    /**
     * Load active standards
     */
    async function loadActiveStandards() {
        try {
            const response = await fetch(`${API_BASE}/standards`);
            const data = await response.json();
            
            if (data.data) {
                activeStandards = data.data;
                renderActiveStandards(data.data);
            }
        } catch (error) {
            console.error('Error loading standards:', error);
        }
    }
    
    /**
     * Render active standards
     */
    function renderActiveStandards(standards) {
        const container = document.getElementById('activeStandards');
        
        if (standards.length === 0) {
            container.innerHTML = '<p class="text-muted">Nessuno standard attivo</p>';
            return;
        }
        
        container.innerHTML = standards.map(standard => `
            <div class="standard-item mb-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${standard.nome}</strong>
                        <small class="text-muted d-block">v${standard.versione}</small>
                    </div>
                    <button class="btn btn-sm btn-outline-primary" onclick="createStandardStructure(${standard.id})">
                        <i class="fas fa-folder-tree"></i>
                    </button>
                </div>
                ${standard.data_scadenza ? `
                    <small class="text-warning">
                        Scade: ${new Date(standard.data_scadenza).toLocaleDateString('it-IT')}
                    </small>
                ` : ''}
            </div>
        `).join('');
    }
    
    /**
     * Load documents
     */
    async function loadDocuments(folderId = null, searchQuery = '') {
        try {
            let url = `${API_BASE}/documents`;
            const params = new URLSearchParams();
            
            if (folderId) {
                params.append('cartella', folderId);
            }
            
            if (searchQuery) {
                params.append('q', searchQuery);
            }
            
            if (params.toString()) {
                url += '?' + params.toString();
            }
            
            const response = await fetch(url);
            const data = await response.json();
            
            renderDocuments(data.documents || []);
            updatePagination(data);
            
        } catch (error) {
            console.error('Error loading documents:', error);
            showError('Errore nel caricamento dei documenti');
        }
    }
    
    /**
     * Render documents
     */
    function renderDocuments(documents) {
        const container = document.getElementById('documentList');
        
        if (documents.length === 0) {
            container.innerHTML = '<p class="text-center text-muted">Nessun documento trovato</p>';
            return;
        }
        
        const table = document.createElement('table');
        table.className = 'table table-hover';
        
        table.innerHTML = `
            <thead>
                <tr>
                    <th width="30">
                        <input type="checkbox" class="form-check-input" id="selectAll">
                    </th>
                    <th>Codice</th>
                    <th>Titolo</th>
                    <th>Tipo</th>
                    <th>Versione</th>
                    <th>Stato</th>
                    <th>Modificato</th>
                    <th width="100">Azioni</th>
                </tr>
            </thead>
            <tbody>
                ${documents.map(doc => renderDocumentRow(doc)).join('')}
            </tbody>
        `;
        
        container.innerHTML = '';
        container.appendChild(table);
        
        // Bind select all
        document.getElementById('selectAll').addEventListener('change', handleSelectAll);
    }
    
    /**
     * Render document row
     */
    function renderDocumentRow(doc) {
        const statusBadge = {
            'bozza': 'secondary',
            'in_revisione': 'warning',
            'approvato': 'info',
            'pubblicato': 'success',
            'obsoleto': 'danger'
        };
        
        return `
            <tr data-document-id="${doc.id}">
                <td>
                    <input type="checkbox" class="form-check-input doc-checkbox" value="${doc.id}">
                </td>
                <td><code>${doc.codice}</code></td>
                <td>
                    <a href="#" onclick="viewDocument(${doc.id}); return false;">
                        ${doc.titolo}
                    </a>
                </td>
                <td>${doc.tipo_documento}</td>
                <td>${doc.versione}</td>
                <td>
                    <span class="badge bg-${statusBadge[doc.stato] || 'secondary'}">
                        ${doc.stato}
                    </span>
                </td>
                <td>${new Date(doc.updated_at).toLocaleDateString('it-IT')}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="downloadDocument(${doc.id})" title="Scarica">
                            <i class="fas fa-download"></i>
                        </button>
                        <button class="btn btn-outline-secondary" onclick="editDocument(${doc.id})" title="Modifica">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }
    
    /**
     * Select folder
     */
    function selectFolder(folderId) {
        currentFolderId = folderId;
        
        // Update UI
        document.querySelectorAll('.folder-name').forEach(el => {
            el.classList.remove('selected');
        });
        
        event.currentTarget.classList.add('selected');
        
        // Load documents for folder
        loadDocuments(folderId);
    }
    
    /**
     * Show upload modal
     */
    function showUploadModal() {
        const modal = new bootstrap.Modal(document.getElementById('uploadModal'));
        modal.show();
    }
    
    /**
     * Handle file upload
     */
    async function handleUpload() {
        const fileInput = document.getElementById('fileInput');
        const files = fileInput.files;
        
        if (files.length === 0) {
            showError('Seleziona almeno un file');
            return;
        }
        
        const formData = new FormData();
        
        // Add files
        for (let i = 0; i < files.length; i++) {
            formData.append('file[]', files[i]);
        }
        
        // Add metadata
        formData.append('folder_id', currentFolderId || 0);
        formData.append('metadata[tipo_documento]', document.getElementById('tipoDocumento').value);
        formData.append('metadata[descrizione]', document.getElementById('descrizione').value);
        
        // Show progress
        showProgress('Caricamento in corso...');
        
        try {
            const response = await fetch(`${API_BASE}/documents/upload`, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                showSuccess(`${data.data.length} file caricati con successo`);
                
                // Close modal
                bootstrap.Modal.getInstance(document.getElementById('uploadModal')).hide();
                
                // Reset form
                document.getElementById('uploadForm').reset();
                
                // Reload documents
                loadDocuments(currentFolderId);
            } else {
                showError(data.error || 'Errore durante il caricamento');
            }
            
        } catch (error) {
            console.error('Upload error:', error);
            showError('Errore durante il caricamento');
        } finally {
            hideProgress();
        }
    }
    
    /**
     * Handle document selection
     */
    function handleDocumentSelection(checkbox) {
        const docId = parseInt(checkbox.value);
        
        if (checkbox.checked) {
            selectedDocuments.add(docId);
        } else {
            selectedDocuments.delete(docId);
        }
        
        // Update bulk download button
        document.getElementById('btnBulkDownload').disabled = selectedDocuments.size === 0;
    }
    
    /**
     * Handle select all
     */
    function handleSelectAll(e) {
        const checkboxes = document.querySelectorAll('.doc-checkbox');
        
        checkboxes.forEach(cb => {
            cb.checked = e.target.checked;
            handleDocumentSelection(cb);
        });
    }
    
    /**
     * Handle bulk download
     */
    async function handleBulkDownload() {
        if (selectedDocuments.size === 0) return;
        
        showProgress('Preparazione download...');
        
        try {
            const response = await fetch(`${API_BASE}/documents/bulk-download`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    document_ids: Array.from(selectedDocuments)
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Download the ZIP file
                window.location.href = data.data.download_url;
                
                // Clear selection
                selectedDocuments.clear();
                document.querySelectorAll('.doc-checkbox').forEach(cb => cb.checked = false);
                document.getElementById('btnBulkDownload').disabled = true;
            } else {
                showError(data.error || 'Errore durante la preparazione del download');
            }
            
        } catch (error) {
            console.error('Download error:', error);
            showError('Errore durante il download');
        } finally {
            hideProgress();
        }
    }
    
    /**
     * Download single document
     */
    function downloadDocument(documentId) {
        window.location.href = `${API_BASE}/documents/${documentId}/download`;
    }
    
    /**
     * Initialize search
     */
    function initializeSearch() {
        let searchTimeout;
        const searchInput = document.getElementById('searchDocuments');
        
        searchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                loadDocuments(currentFolderId, e.target.value);
            }, 300);
        });
    }
    
    /**
     * Create standard structure
     */
    window.createStandardStructure = async function(standardId) {
        if (!confirm('Vuoi creare la struttura delle cartelle per questo standard?')) {
            return;
        }
        
        showProgress('Creazione struttura...');
        
        try {
            const response = await fetch(`${API_BASE}/standards/structure`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    standard_id: standardId,
                    options: {
                        include_optional: true
                    }
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                showSuccess('Struttura creata con successo');
                loadFolderTree();
            } else {
                showError(data.error || 'Errore durante la creazione della struttura');
            }
            
        } catch (error) {
            console.error('Structure creation error:', error);
            showError('Errore durante la creazione della struttura');
        } finally {
            hideProgress();
        }
    };
    
    /**
     * UI Helper functions
     */
    function showError(message) {
        const toast = document.createElement('div');
        toast.className = 'toast align-items-center text-white bg-danger border-0';
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        
        document.body.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }
    
    function showSuccess(message) {
        const toast = document.createElement('div');
        toast.className = 'toast align-items-center text-white bg-success border-0';
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        
        document.body.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }
    
    function showProgress(message) {
        const progress = document.createElement('div');
        progress.id = 'progressOverlay';
        progress.className = 'position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center';
        progress.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
        progress.style.zIndex = '9999';
        progress.innerHTML = `
            <div class="bg-white rounded p-4">
                <div class="spinner-border text-primary me-3" role="status"></div>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(progress);
    }
    
    function hideProgress() {
        const progress = document.getElementById('progressOverlay');
        if (progress) {
            progress.remove();
        }
    }
    
    function updatePagination(data) {
        // Implement pagination UI update
    }
    
})();