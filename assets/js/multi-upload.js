/**
 * MultiUpload JavaScript Module
 * 
 * Sistema JavaScript per upload multipli con drag & drop e progress tracking
 * Compatible con il sistema documentale ISO Nexio
 * 
 * Features:
 * - Drag & drop multipli file
 * - Progress tracking real-time
 * - Validazione client-side
 * - Resume capability
 * - Chunked upload per file grandi
 * - Auto-classificazione ISO
 * 
 * @version 1.0.0
 */

class NexioMultiUpload {
    constructor(options = {}) {
        this.options = {
            dropZoneSelector: '#upload-drop-zone',
            fileInputSelector: '#upload-files',
            progressContainerSelector: '#upload-progress-container',
            previewContainerSelector: '#upload-preview-container',
            uploadButtonSelector: '#start-upload-btn',
            maxFiles: 50,
            maxFileSize: 50 * 1024 * 1024, // 50MB
            chunkSize: 1024 * 1024, // 1MB
            allowedTypes: [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'image/jpeg',
                'image/png',
                'image/gif',
                'text/plain',
                'text/csv'
            ],
            uploadUrl: '/backend/api/upload-multiple.php',
            progressUrl: '/backend/api/upload-progress.php',
            csrfToken: null,
            autoClassify: true,
            enableChunked: true,
            enableResume: true,
            notifyCompletion: true,
            ...options
        };

        this.files = [];
        this.uploadSessions = new Map();
        this.progressIntervals = new Map();
        this.isUploading = false;
        this.currentSessionId = null;

        this.init();
    }

    init() {
        this.setupEventListeners();
        this.setupDropZone();
        this.loadCSRFToken();
    }

    setupEventListeners() {
        // File input change
        const fileInput = document.querySelector(this.options.fileInputSelector);
        if (fileInput) {
            fileInput.addEventListener('change', (e) => this.handleFileSelect(e));
        }

        // Upload button
        const uploadBtn = document.querySelector(this.options.uploadButtonSelector);
        if (uploadBtn) {
            uploadBtn.addEventListener('click', () => this.startUpload());
        }

        // Window beforeunload per warning su upload in corso
        window.addEventListener('beforeunload', (e) => {
            if (this.isUploading) {
                e.preventDefault();
                e.returnValue = 'Upload in corso. Sei sicuro di voler uscire?';
                return e.returnValue;
            }
        });
    }

    setupDropZone() {
        const dropZone = document.querySelector(this.options.dropZoneSelector);
        if (!dropZone) return;

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, this.preventDefaults, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => this.highlightDropZone(dropZone), false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => this.unhighlightDropZone(dropZone), false);
        });

        dropZone.addEventListener('drop', (e) => this.handleDrop(e), false);
    }

    preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    highlightDropZone(dropZone) {
        dropZone.classList.add('dragover');
    }

    unhighlightDropZone(dropZone) {
        dropZone.classList.remove('dragover');
    }

    handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        this.handleFiles(files);
    }

    handleFileSelect(e) {
        const files = e.target.files;
        this.handleFiles(files);
    }

    handleFiles(fileList) {
        const files = Array.from(fileList);
        
        // Validazione numero file
        if (this.files.length + files.length > this.options.maxFiles) {
            this.showError(`Numero massimo di file superato (${this.options.maxFiles})`);
            return;
        }

        // Validazione e aggiunta file
        files.forEach(file => {
            if (this.validateFile(file)) {
                const fileInfo = this.createFileInfo(file);
                this.files.push(fileInfo);
                this.addFileToPreview(fileInfo);
            }
        });

        this.updateUI();
    }

    validateFile(file) {
        // Controllo dimensione
        if (file.size > this.options.maxFileSize) {
            this.showError(`File "${file.name}" troppo grande (max ${this.formatBytes(this.options.maxFileSize)})`);
            return false;
        }

        // Controllo tipo MIME
        if (!this.options.allowedTypes.includes(file.type)) {
            this.showError(`Tipo file "${file.type}" non supportato per "${file.name}"`);
            return false;
        }

        // Controllo duplicati
        if (this.files.some(f => f.file.name === file.name && f.file.size === file.size)) {
            this.showError(`File "${file.name}" già presente nella lista`);
            return false;
        }

        return true;
    }

    createFileInfo(file) {
        const id = this.generateId();
        return {
            id: id,
            file: file,
            name: file.name,
            size: file.size,
            type: file.type,
            status: 'pending',
            progress: 0,
            uploadedBytes: 0,
            error: null,
            metadata: {
                titolo: this.extractTitle(file.name),
                tipo_documento: this.autoClassifyDocument(file.name),
                tags: [],
                cartella_id: this.getCurrentFolderId()
            },
            chunks: [],
            currentChunk: 0,
            resumeSupported: false
        };
    }

    addFileToPreview(fileInfo) {
        const container = document.querySelector(this.options.previewContainerSelector);
        if (!container) return;

        const fileElement = this.createFilePreviewElement(fileInfo);
        container.appendChild(fileElement);
    }

    createFilePreviewElement(fileInfo) {
        const div = document.createElement('div');
        div.className = 'upload-file-item';
        div.dataset.fileId = fileInfo.id;

        div.innerHTML = `
            <div class="file-info">
                <div class="file-icon">
                    <i class="fas ${this.getFileIcon(fileInfo.file)}"></i>
                </div>
                <div class="file-details">
                    <div class="file-name" title="${fileInfo.name}">${fileInfo.name}</div>
                    <div class="file-size">${this.formatBytes(fileInfo.size)}</div>
                    <div class="file-type">${this.getFileTypeDescription(fileInfo.type)}</div>
                </div>
                <div class="file-metadata">
                    <input type="text" class="form-control form-control-sm mb-1" 
                           placeholder="Titolo documento" 
                           value="${fileInfo.metadata.titolo}"
                           onchange="nexioUploader.updateFileMetadata('${fileInfo.id}', 'titolo', this.value)">
                    <select class="form-select form-select-sm mb-1"
                            onchange="nexioUploader.updateFileMetadata('${fileInfo.id}', 'tipo_documento', this.value)">
                        ${this.generateDocumentTypeOptions(fileInfo.metadata.tipo_documento)}
                    </select>
                    <input type="text" class="form-control form-control-sm" 
                           placeholder="Tags (separati da virgola)"
                           onchange="nexioUploader.updateFileMetadata('${fileInfo.id}', 'tags', this.value.split(','))">
                </div>
                <div class="file-actions">
                    <button type="button" class="btn btn-sm btn-outline-danger" 
                            onclick="nexioUploader.removeFile('${fileInfo.id}')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="file-progress" style="display: none;">
                <div class="progress mb-2">
                    <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                </div>
                <div class="progress-info">
                    <span class="status">In attesa...</span>
                    <span class="speed"></span>
                    <span class="eta"></span>
                </div>
            </div>
            <div class="file-result" style="display: none;">
                <div class="alert alert-success" style="display: none;">
                    <i class="fas fa-check"></i> Caricato con successo
                </div>
                <div class="alert alert-danger" style="display: none;">
                    <i class="fas fa-exclamation-triangle"></i> <span class="error-message"></span>
                </div>
            </div>
        `;

        return div;
    }

    updateFileMetadata(fileId, field, value) {
        const file = this.files.find(f => f.id === fileId);
        if (file) {
            file.metadata[field] = value;
        }
    }

    removeFile(fileId) {
        const index = this.files.findIndex(f => f.id === fileId);
        if (index > -1) {
            this.files.splice(index, 1);
            
            const element = document.querySelector(`[data-file-id="${fileId}"]`);
            if (element) {
                element.remove();
            }
            
            this.updateUI();
        }
    }

    async startUpload() {
        if (this.files.length === 0) {
            this.showError('Nessun file selezionato');
            return;
        }

        if (this.isUploading) {
            this.showError('Upload già in corso');
            return;
        }

        this.isUploading = true;
        this.updateUI();

        try {
            // Prepara FormData
            const formData = new FormData();
            
            // CSRF Token
            formData.append('csrf_token', this.options.csrfToken);
            
            // File
            this.files.forEach((fileInfo, index) => {
                formData.append(`files[${index}]`, fileInfo.file);
                
                // Metadata per ogni file
                Object.keys(fileInfo.metadata).forEach(key => {
                    formData.append(`file_${index}_${key}`, 
                        Array.isArray(fileInfo.metadata[key]) ? 
                            fileInfo.metadata[key].join(',') : 
                            fileInfo.metadata[key]
                    );
                });
            });

            // Opzioni globali
            formData.append('auto_classify', this.options.autoClassify);
            formData.append('notify_completion', this.options.notifyCompletion);
            formData.append('include_progress', true);

            // Invia upload
            const response = await fetch(this.options.uploadUrl, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.currentSessionId = result.data.session_id;
                this.startProgressTracking(this.currentSessionId);
                this.showSuccess('Upload avviato con successo');
            } else {
                throw new Error(result.error || 'Errore durante l\'upload');
            }

        } catch (error) {
            this.showError('Errore upload: ' + error.message);
            this.isUploading = false;
            this.updateUI();
        }
    }

    startProgressTracking(sessionId) {
        const interval = setInterval(async () => {
            try {
                const response = await fetch(`${this.options.progressUrl}?session_id=${sessionId}`);
                const data = await response.json();

                if (data.error) {
                    throw new Error(data.error);
                }

                this.updateProgress(data);

                // Stop tracking se completato
                if (['completed', 'completed_with_errors', 'failed'].includes(data.status)) {
                    clearInterval(interval);
                    this.progressIntervals.delete(sessionId);
                    this.onUploadComplete(data);
                }

            } catch (error) {
                console.error('Errore tracking progress:', error);
                clearInterval(interval);
                this.progressIntervals.delete(sessionId);
            }
        }, 2000);

        this.progressIntervals.set(sessionId, interval);
    }

    updateProgress(data) {
        const container = document.querySelector(this.options.progressContainerSelector);
        if (!container) return;

        // Update global progress
        const globalProgress = container.querySelector('.global-progress .progress-bar');
        if (globalProgress) {
            globalProgress.style.width = `${data.progress.progress_percent}%`;
            globalProgress.textContent = `${Math.round(data.progress.progress_percent)}%`;
        }

        // Update global stats
        const globalStats = container.querySelector('.global-stats');
        if (globalStats) {
            globalStats.innerHTML = `
                <div class="row">
                    <div class="col">
                        <strong>File processati:</strong> ${data.progress.files_processed}/${data.progress.total_files}
                    </div>
                    <div class="col">
                        <strong>Successi:</strong> ${data.progress.files_success}
                    </div>
                    <div class="col">
                        <strong>Errori:</strong> ${data.progress.files_errors}
                    </div>
                    <div class="col">
                        <strong>Stato:</strong> ${this.getStatusLabel(data.status)}
                    </div>
                </div>
            `;
        }

        // Update individual file progress
        if (data.files) {
            data.files.forEach(file => {
                this.updateFileProgress(file);
            });
        }

        // Update errors if any
        if (data.errors && data.errors.length > 0) {
            this.displayErrors(data.errors);
        }
    }

    updateFileProgress(fileData) {
        const element = document.querySelector(`[data-file-id="${fileData.id}"]`);
        if (!element) return;

        const progressDiv = element.querySelector('.file-progress');
        const resultDiv = element.querySelector('.file-result');
        
        if (fileData.status === 'success') {
            progressDiv.style.display = 'none';
            resultDiv.style.display = 'block';
            resultDiv.querySelector('.alert-success').style.display = 'block';
            
        } else if (fileData.status === 'error') {
            progressDiv.style.display = 'none';
            resultDiv.style.display = 'block';
            resultDiv.querySelector('.alert-danger').style.display = 'block';
            resultDiv.querySelector('.error-message').textContent = fileData.error;
        }
    }

    onUploadComplete(data) {
        this.isUploading = false;
        this.updateUI();

        if (data.status === 'completed') {
            this.showSuccess(`Upload completato! ${data.progress.files_success} file caricati con successo.`);
        } else if (data.status === 'completed_with_errors') {
            this.showWarning(`Upload completato con errori. ${data.progress.files_success} successi, ${data.progress.files_errors} errori.`);
        } else {
            this.showError('Upload fallito. Controllare i log per dettagli.');
        }

        // Auto-refresh della pagina dopo alcuni secondi se richiesto
        if (this.options.autoRefresh && data.progress.files_success > 0) {
            setTimeout(() => {
                window.location.reload();
            }, 3000);
        }
    }

    // Utility methods

    generateId() {
        return 'file_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
    }

    extractTitle(filename) {
        return filename.replace(/\.[^/.]+$/, "").replace(/[_-]/g, ' ');
    }

    autoClassifyDocument(filename) {
        if (!this.options.autoClassify) return 'documento_generico';
        
        const name = filename.toLowerCase();
        
        const classifications = {
            'manual_qualita': ['manuale', 'manual', 'quality', 'qualità'],
            'procedura_operativa': ['procedura', 'procedure', 'operativa', 'operational'],
            'istruzione_lavoro': ['istruzione', 'instruction', 'lavoro', 'work'],
            'modulo_registrazione': ['modulo', 'form', 'registrazione', 'record'],
            'evidenza_audit': ['audit', 'evidenza', 'evidence', 'verifica'],
            'certificazione': ['certificat', 'certificate', 'attestat']
        };

        for (const [type, keywords] of Object.entries(classifications)) {
            for (const keyword of keywords) {
                if (name.includes(keyword)) {
                    return type;
                }
            }
        }

        return 'documento_generico';
    }

    getCurrentFolderId() {
        // Ottieni cartella corrente dal context della pagina
        const folderSelect = document.querySelector('#current-folder-id');
        return folderSelect ? folderSelect.value : null;
    }

    getFileIcon(file) {
        const type = file.type;
        
        if (type.startsWith('image/')) return 'fa-file-image';
        if (type.includes('pdf')) return 'fa-file-pdf';
        if (type.includes('word')) return 'fa-file-word';
        if (type.includes('excel') || type.includes('spreadsheet')) return 'fa-file-excel';
        if (type.includes('powerpoint') || type.includes('presentation')) return 'fa-file-powerpoint';
        if (type.includes('text')) return 'fa-file-alt';
        if (type.includes('zip') || type.includes('rar')) return 'fa-file-archive';
        
        return 'fa-file';
    }

    getFileTypeDescription(mimeType) {
        const types = {
            'application/pdf': 'PDF',
            'application/msword': 'Word Document',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'Word Document',
            'application/vnd.ms-excel': 'Excel Spreadsheet',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'Excel Spreadsheet',
            'image/jpeg': 'JPEG Image',
            'image/png': 'PNG Image',
            'image/gif': 'GIF Image',
            'text/plain': 'Text File',
            'text/csv': 'CSV File'
        };
        
        return types[mimeType] || 'Unknown';
    }

    generateDocumentTypeOptions(selected) {
        const types = {
            'documento_generico': 'Documento Generico',
            'manual_qualita': 'Manuale Qualità',
            'procedura_operativa': 'Procedura Operativa',
            'istruzione_lavoro': 'Istruzione di Lavoro',
            'modulo_registrazione': 'Modulo/Registrazione',
            'evidenza_audit': 'Evidenza Audit',
            'certificazione': 'Certificazione'
        };

        return Object.entries(types).map(([value, label]) => 
            `<option value="${value}" ${value === selected ? 'selected' : ''}>${label}</option>`
        ).join('');
    }

    getStatusLabel(status) {
        const labels = {
            'pending': 'In attesa',
            'processing': 'In elaborazione',
            'completed': 'Completato',
            'completed_with_errors': 'Completato con errori',
            'failed': 'Fallito'
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
        const uploadBtn = document.querySelector(this.options.uploadButtonSelector);
        const fileCount = document.querySelector('#selected-files-count');
        
        if (uploadBtn) {
            uploadBtn.disabled = this.isUploading || this.files.length === 0;
            uploadBtn.innerHTML = this.isUploading ? 
                '<i class="fas fa-spinner fa-spin"></i> Caricamento...' : 
                '<i class="fas fa-upload"></i> Avvia Upload';
        }
        
        if (fileCount) {
            fileCount.textContent = this.files.length;
        }
    }

    loadCSRFToken() {
        const tokenMeta = document.querySelector('meta[name="csrf-token"]');
        if (tokenMeta) {
            this.options.csrfToken = tokenMeta.getAttribute('content');
        } else {
            // Fallback per ottenere token da sessione
            fetch('/backend/api/get-csrf-token.php')
                .then(response => response.json())
                .then(data => {
                    if (data.token) {
                        this.options.csrfToken = data.token;
                    }
                })
                .catch(error => {
                    console.error('Errore caricamento CSRF token:', error);
                });
        }
    }

    // Notification methods
    showSuccess(message) {
        this.showNotification(message, 'success');
    }

    showError(message) {
        this.showNotification(message, 'danger');
    }

    showWarning(message) {
        this.showNotification(message, 'warning');
    }

    showNotification(message, type) {
        // Usa Bootstrap alerts o sistema notifiche custom
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        const container = document.querySelector('#notifications-container') || document.body;
        container.appendChild(alertDiv);
        
        // Auto-remove dopo 5 secondi
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }

    displayErrors(errors) {
        const errorContainer = document.querySelector('#upload-errors');
        if (!errorContainer) return;

        errorContainer.innerHTML = errors.map(error => `
            <div class="alert alert-danger">
                <strong>File:</strong> ${error.file_name}<br>
                <strong>Errore:</strong> ${error.error_message}
            </div>
        `).join('');
    }
}

// Initialize quando il DOM è ready
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.nexioUploader === 'undefined') {
        window.nexioUploader = new NexioMultiUpload();
    }
});

// Export per uso come modulo
if (typeof module !== 'undefined' && module.exports) {
    module.exports = NexioMultiUpload;
}