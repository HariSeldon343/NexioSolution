/**
 * MultiDownload JavaScript Module
 * 
 * Sistema JavaScript per download multipli con selezione e progress tracking
 * Compatible con il sistema documentale ISO Nexio
 * 
 * Features:
 * - Selezione multipla documenti/cartelle
 * - Download ZIP con progress tracking
 * - Opzioni configurabili (struttura, metadata, etc.)
 * - Resume capability per download interrotti
 * 
 * @version 1.0.0
 */

class NexioMultiDownload {
    constructor(options = {}) {
        this.options = {
            downloadUrl: '/backend/api/download-multiple.php',
            progressUrl: '/backend/api/download-progress.php',
            downloadZipUrl: '/backend/api/download-zip.php',
            selectionContainerSelector: '.document-grid, .document-list',
            downloadButtonSelector: '#download-selected-btn',
            progressModalSelector: '#download-progress-modal',
            optionsModalSelector: '#download-options-modal',
            csrfToken: null,
            autoDownload: true,
            defaultOptions: {
                preserve_structure: true,
                include_metadata: false,
                add_timestamp: false,
                compression_level: 6,
                include_audit_trail: false
            },
            maxSelectionSize: 1024 * 1024 * 1024, // 1GB
            ...options
        };

        this.selectedItems = new Map(); // Map of item IDs to item data
        this.currentDownload = null;
        this.progressInterval = null;

        this.init();
    }

    init() {
        this.setupEventListeners();
        this.loadCSRFToken();
        this.updateUI();
    }

    setupEventListeners() {
        // Selezione documenti
        this.setupSelectionHandlers();
        
        // Download button
        const downloadBtn = document.querySelector(this.options.downloadButtonSelector);
        if (downloadBtn) {
            downloadBtn.addEventListener('click', () => this.openOptionsModal());
        }

        // Bulk actions
        this.setupBulkActions();

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => this.handleKeyboardShortcuts(e));
    }

    setupSelectionHandlers() {
        const container = document.querySelector(this.options.selectionContainerSelector);
        if (!container) return;

        // Event delegation per elementi dinamici
        container.addEventListener('click', (e) => {
            const item = e.target.closest('.document-item, .folder-item');
            if (!item) return;

            const checkbox = item.querySelector('input[type="checkbox"]');
            if (!checkbox) return;

            // Se click su checkbox, gestisci direttamente
            if (e.target === checkbox) {
                this.handleItemSelection(item, checkbox.checked);
                return;
            }

            // Se click su item (non su link/button), toggle selection
            if (!e.target.closest('a, button, .dropdown')) {
                checkbox.checked = !checkbox.checked;
                this.handleItemSelection(item, checkbox.checked);
                e.preventDefault();
            }
        });

        // Ctrl+Click per selezione multipla
        container.addEventListener('click', (e) => {
            if (e.ctrlKey || e.metaKey) {
                const item = e.target.closest('.document-item, .folder-item');
                if (item) {
                    const checkbox = item.querySelector('input[type="checkbox"]');
                    if (checkbox) {
                        checkbox.checked = !checkbox.checked;
                        this.handleItemSelection(item, checkbox.checked);
                        e.preventDefault();
                    }
                }
            }
        });

        // Shift+Click per selezione range
        let lastSelectedIndex = -1;
        container.addEventListener('click', (e) => {
            if (e.shiftKey) {
                const items = Array.from(container.querySelectorAll('.document-item, .folder-item'));
                const clickedItem = e.target.closest('.document-item, .folder-item');
                
                if (clickedItem) {
                    const currentIndex = items.indexOf(clickedItem);
                    
                    if (lastSelectedIndex !== -1 && currentIndex !== -1) {
                        const start = Math.min(lastSelectedIndex, currentIndex);
                        const end = Math.max(lastSelectedIndex, currentIndex);
                        
                        for (let i = start; i <= end; i++) {
                            const checkbox = items[i].querySelector('input[type="checkbox"]');
                            if (checkbox) {
                                checkbox.checked = true;
                                this.handleItemSelection(items[i], true);
                            }
                        }
                        e.preventDefault();
                    }
                    
                    lastSelectedIndex = currentIndex;
                }
            }
        });
    }

    setupBulkActions() {
        // Select All checkbox
        const selectAllCheckbox = document.querySelector('#select-all-items');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', (e) => {
                this.selectAll(e.target.checked);
            });
        }

        // Quick actions
        const quickActions = document.querySelectorAll('[data-bulk-action]');
        quickActions.forEach(action => {
            action.addEventListener('click', (e) => {
                const actionType = e.target.dataset.bulkAction;
                this.handleBulkAction(actionType);
            });
        });
    }

    handleItemSelection(item, selected) {
        const itemId = item.dataset.itemId;
        const itemType = item.dataset.itemType; // 'document' or 'folder'
        
        if (!itemId) return;

        if (selected) {
            const itemData = this.extractItemData(item);
            this.selectedItems.set(itemId, {
                id: itemId,
                type: itemType,
                element: item,
                ...itemData
            });
            item.classList.add('selected');
        } else {
            this.selectedItems.delete(itemId);
            item.classList.remove('selected');
        }

        this.updateUI();
        this.updateSelectAllState();
    }

    extractItemData(item) {
        return {
            name: item.dataset.itemName || item.querySelector('.item-name')?.textContent || 'Unknown',
            size: parseInt(item.dataset.itemSize) || 0,
            type_file: item.dataset.fileType || null,
            path: item.dataset.itemPath || null
        };
    }

    selectAll(selected) {
        const container = document.querySelector(this.options.selectionContainerSelector);
        if (!container) return;

        const items = container.querySelectorAll('.document-item, .folder-item');
        items.forEach(item => {
            const checkbox = item.querySelector('input[type="checkbox"]');
            if (checkbox) {
                checkbox.checked = selected;
                this.handleItemSelection(item, selected);
            }
        });
    }

    updateSelectAllState() {
        const selectAllCheckbox = document.querySelector('#select-all-items');
        if (!selectAllCheckbox) return;

        const container = document.querySelector(this.options.selectionContainerSelector);
        const totalItems = container.querySelectorAll('.document-item, .folder-item').length;
        const selectedCount = this.selectedItems.size;

        if (selectedCount === 0) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        } else if (selectedCount === totalItems) {
            selectAllCheckbox.checked = true;
            selectAllCheckbox.indeterminate = false;
        } else {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = true;
        }
    }

    openOptionsModal() {
        if (this.selectedItems.size === 0) {
            this.showError('Nessun elemento selezionato');
            return;
        }

        // Calcola dimensione totale stimata
        const totalSize = this.calculateTotalSize();
        if (totalSize > this.options.maxSelectionSize) {
            this.showError(`Selezione troppo grande (max ${this.formatBytes(this.options.maxSelectionSize)})`);
            return;
        }

        const modal = document.querySelector(this.options.optionsModalSelector);
        if (modal) {
            // Aggiorna informazioni selezione
            this.updateSelectionInfo(modal);
            
            // Reset form ai valori default
            this.resetOptionsForm(modal);
            
            // Mostra modal
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        } else {
            // Se non c'è modal, avvia download diretto con opzioni default
            this.startDownload(this.options.defaultOptions);
        }
    }

    updateSelectionInfo(modal) {
        const info = modal.querySelector('.selection-info');
        if (!info) return;

        const documentCount = Array.from(this.selectedItems.values()).filter(item => item.type === 'document').length;
        const folderCount = Array.from(this.selectedItems.values()).filter(item => item.type === 'folder').length;
        const totalSize = this.calculateTotalSize();

        info.innerHTML = `
            <div class="row">
                <div class="col-md-4">
                    <strong>Documenti:</strong> ${documentCount}
                </div>
                <div class="col-md-4">
                    <strong>Cartelle:</strong> ${folderCount}
                </div>
                <div class="col-md-4">
                    <strong>Dimensione stimata:</strong> ${this.formatBytes(totalSize)}
                </div>
            </div>
        `;
    }

    resetOptionsForm(modal) {
        const form = modal.querySelector('form');
        if (!form) return;

        // Reset ai valori default
        Object.entries(this.options.defaultOptions).forEach(([key, value]) => {
            const input = form.querySelector(`[name="${key}"]`);
            if (input) {
                if (input.type === 'checkbox') {
                    input.checked = value;
                } else {
                    input.value = value;
                }
            }
        });
    }

    async startDownload(options = {}) {
        try {
            // Chiudi modal se aperto
            const modal = document.querySelector(this.options.optionsModalSelector);
            if (modal) {
                const bsModal = bootstrap.Modal.getInstance(modal);
                if (bsModal) bsModal.hide();
            }

            // Prepara dati per il download
            const formData = new FormData();
            formData.append('csrf_token', this.options.csrfToken);

            // Separa documenti e cartelle
            const documentIds = [];
            const folderIds = [];

            this.selectedItems.forEach(item => {
                if (item.type === 'document') {
                    documentIds.push(item.id);
                } else if (item.type === 'folder') {
                    folderIds.push(item.id);
                }
            });

            if (documentIds.length > 0) {
                formData.append('document_ids', documentIds.join(','));
            }
            if (folderIds.length > 0) {
                formData.append('folder_ids', folderIds.join(','));
            }

            // Opzioni
            Object.entries(options).forEach(([key, value]) => {
                formData.append(key, value);
            });

            // Mostra progress modal
            this.showProgressModal();

            // Avvia download
            const response = await fetch(this.options.downloadUrl, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.currentDownload = result.data;
                this.startProgressTracking(result.data.session_id);
                this.showSuccess('Preparazione download avviata...');
            } else {
                throw new Error(result.error || 'Errore durante il download');
            }

        } catch (error) {
            this.showError('Errore download: ' + error.message);
            this.hideProgressModal();
        }
    }

    showProgressModal() {
        const modal = document.querySelector(this.options.progressModalSelector);
        if (modal) {
            const bsModal = new bootstrap.Modal(modal, { backdrop: 'static' });
            bsModal.show();
        }
    }

    hideProgressModal() {
        const modal = document.querySelector(this.options.progressModalSelector);
        if (modal) {
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) bsModal.hide();
        }
    }

    startProgressTracking(sessionId) {
        this.progressInterval = setInterval(async () => {
            try {
                const response = await fetch(`${this.options.progressUrl}?session_id=${sessionId}`);
                const data = await response.json();

                if (data.error) {
                    throw new Error(data.error);
                }

                this.updateProgress(data);

                // Stop tracking se completato
                if (['completed', 'failed', 'expired'].includes(data.status)) {
                    clearInterval(this.progressInterval);
                    this.onDownloadComplete(data);
                }

            } catch (error) {
                console.error('Errore tracking progress:', error);
                clearInterval(this.progressInterval);
                this.showError('Errore monitoraggio progress');
            }
        }, 2000);
    }

    updateProgress(data) {
        const modal = document.querySelector(this.options.progressModalSelector);
        if (!modal) return;

        // Update progress bar
        const progressBar = modal.querySelector('.progress-bar');
        if (progressBar) {
            progressBar.style.width = `${data.progress.progress_percent}%`;
            progressBar.textContent = `${Math.round(data.progress.progress_percent)}%`;
        }

        // Update stats
        const stats = modal.querySelector('.download-stats');
        if (stats) {
            stats.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <strong>File processati:</strong> ${data.progress.files_processed}/${data.progress.total_documents}
                    </div>
                    <div class="col-md-6">
                        <strong>Stato:</strong> ${this.getStatusLabel(data.status)}
                    </div>
                    ${data.progress.compression_ratio ? `
                    <div class="col-md-6">
                        <strong>Compressione:</strong> ${data.progress.compression_ratio}%
                    </div>
                    ` : ''}
                    ${data.progress.final_size ? `
                    <div class="col-md-6">
                        <strong>Dimensione finale:</strong> ${this.formatBytes(data.progress.final_size)}
                    </div>
                    ` : ''}
                </div>
            `;
        }

        // Update file list se disponibile
        if (data.documents) {
            this.updateFileList(modal, data.documents);
        }
    }

    updateFileList(modal, documents) {
        const fileList = modal.querySelector('.file-list');
        if (!fileList) return;

        fileList.innerHTML = documents.map(doc => `
            <div class="file-item">
                <i class="fas ${this.getFileIcon(doc.type)}"></i>
                <span class="file-name">${doc.titolo}</span>
                <span class="file-size">${doc.size_formatted}</span>
            </div>
        `).join('');
    }

    onDownloadComplete(data) {
        if (data.status === 'completed' && data.download && data.download.available) {
            // Download automatico se abilitato
            if (this.options.autoDownload) {
                this.triggerDownload(data.download.download_url);
            }
            
            this.showDownloadReady(data.download);
            this.showSuccess(`ZIP creato con successo! ${data.progress.files_processed} file inclusi.`);
        } else {
            this.showError('Download fallito. Controllare i log per dettagli.');
        }

        // Nascondi modal dopo qualche secondo
        setTimeout(() => {
            this.hideProgressModal();
        }, 3000);
    }

    showDownloadReady(downloadInfo) {
        const modal = document.querySelector(this.options.progressModalSelector);
        if (!modal) return;

        const content = modal.querySelector('.modal-body');
        if (content) {
            content.innerHTML += `
                <div class="download-ready mt-3">
                    <div class="alert alert-success">
                        <h5><i class="fas fa-check"></i> Download pronto!</h5>
                        <p>File: <strong>${downloadInfo.filename}</strong></p>
                        <p>Dimensione: <strong>${this.formatBytes(downloadInfo.file_size)}</strong></p>
                        <p>Scade: <strong>${new Date(downloadInfo.expires_at).toLocaleString()}</strong></p>
                        
                        <div class="mt-3">
                            <a href="${downloadInfo.download_url}" class="btn btn-primary" download>
                                <i class="fas fa-download"></i> Scarica ora
                            </a>
                        </div>
                    </div>
                </div>
            `;
        }
    }

    triggerDownload(url) {
        const link = document.createElement('a');
        link.href = url;
        link.download = '';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    calculateTotalSize() {
        let total = 0;
        this.selectedItems.forEach(item => {
            total += item.size || 0;
        });
        return total;
    }

    handleBulkAction(actionType) {
        switch (actionType) {
            case 'download':
                this.openOptionsModal();
                break;
            case 'clear-selection':
                this.clearSelection();
                break;
            case 'invert-selection':
                this.invertSelection();
                break;
            default:
                console.warn('Unknown bulk action:', actionType);
        }
    }

    clearSelection() {
        this.selectedItems.clear();
        
        const checkboxes = document.querySelectorAll('.document-item input[type="checkbox"], .folder-item input[type="checkbox"]');
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
            const item = checkbox.closest('.document-item, .folder-item');
            if (item) {
                item.classList.remove('selected');
            }
        });

        this.updateUI();
        this.updateSelectAllState();
    }

    invertSelection() {
        const container = document.querySelector(this.options.selectionContainerSelector);
        if (!container) return;

        const items = container.querySelectorAll('.document-item, .folder-item');
        items.forEach(item => {
            const checkbox = item.querySelector('input[type="checkbox"]');
            if (checkbox) {
                checkbox.checked = !checkbox.checked;
                this.handleItemSelection(item, checkbox.checked);
            }
        });
    }

    handleKeyboardShortcuts(e) {
        // Ctrl+A - Select All
        if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
            e.preventDefault();
            this.selectAll(true);
        }

        // Ctrl+D - Download selected
        if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
            e.preventDefault();
            this.openOptionsModal();
        }

        // Escape - Clear selection
        if (e.key === 'Escape') {
            this.clearSelection();
        }
    }

    // Utility methods

    getFileIcon(fileType) {
        const type = (fileType || '').toLowerCase();
        
        if (type.includes('pdf')) return 'fa-file-pdf';
        if (type.includes('doc')) return 'fa-file-word';
        if (type.includes('xls')) return 'fa-file-excel';
        if (type.includes('ppt')) return 'fa-file-powerpoint';
        if (type.includes('image') || ['jpg', 'jpeg', 'png', 'gif'].includes(type)) return 'fa-file-image';
        if (type.includes('zip') || type.includes('rar')) return 'fa-file-archive';
        if (type.includes('text') || type === 'txt') return 'fa-file-alt';
        
        return 'fa-file';
    }

    getStatusLabel(status) {
        const labels = {
            'pending': 'In attesa',
            'processing': 'Elaborazione',
            'completed': 'Completato',
            'failed': 'Fallito',
            'expired': 'Scaduto'
        };
        
        return labels[status] || status;
    }

    formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    updateUI() {
        const downloadBtn = document.querySelector(this.options.downloadButtonSelector);
        const selectionCount = document.querySelector('#selection-count');
        const selectionSize = document.querySelector('#selection-size');

        const count = this.selectedItems.size;
        const totalSize = this.calculateTotalSize();

        if (downloadBtn) {
            downloadBtn.disabled = count === 0;
            downloadBtn.innerHTML = count > 0 ? 
                `<i class="fas fa-download"></i> Scarica ${count} elemento${count > 1 ? 'i' : ''}` :
                '<i class="fas fa-download"></i> Scarica selezionati';
        }

        if (selectionCount) {
            selectionCount.textContent = count;
        }

        if (selectionSize) {
            selectionSize.textContent = this.formatBytes(totalSize);
        }

        // Update bulk actions visibility
        const bulkActions = document.querySelector('.bulk-actions');
        if (bulkActions) {
            bulkActions.style.display = count > 0 ? 'block' : 'none';
        }
    }

    loadCSRFToken() {
        const tokenMeta = document.querySelector('meta[name="csrf-token"]');
        if (tokenMeta) {
            this.options.csrfToken = tokenMeta.getAttribute('content');
        }
    }

    // Notification methods
    showSuccess(message) {
        this.showNotification(message, 'success');
    }

    showError(message) {
        this.showNotification(message, 'danger');
    }

    showNotification(message, type) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(alertDiv);
        
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
}

// Initialize quando il DOM è ready
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.nexioDownloader === 'undefined') {
        window.nexioDownloader = new NexioMultiDownload();
    }
});

// Export per uso come modulo
if (typeof module !== 'undefined' && module.exports) {
    module.exports = NexioMultiDownload;
}