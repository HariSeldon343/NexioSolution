/**
 * ISO WIZARD JAVASCRIPT MODULE
 * Nexio Document Management System
 * 
 * Handles ISO configuration wizard functionality:
 * - Multi-step wizard navigation
 * - Standard selection
 * - Implementation mode selection
 * - Structure preview generation
 * - System creation
 */

class ISOWizard {
    constructor() {
        this.currentStep = 1;
        this.totalSteps = 4;
        this.selectedStandards = new Set();
        this.implementationMode = null;
        this.structurePreview = null;
        this.isCreating = false;
    }

    static init() {
        if (!window.isoWizard) {
            window.isoWizard = new ISOWizard();
            window.isoWizard.initializeEventListeners();
        }
        return window.isoWizard;
    }

    initializeEventListeners() {
        // Wizard navigation functions
        window.nextWizardStep = this.nextStep.bind(this);
        window.previousWizardStep = this.previousStep.bind(this);
        window.finishWizard = this.finishWizard.bind(this);
        
        // Standard selection handlers
        document.addEventListener('change', (e) => {
            if (e.target.matches('input[type="checkbox"][value^="iso"], input[type="checkbox"][value="gdpr"]')) {
                this.handleStandardSelection(e);
            }
        });
        
        // Implementation mode handlers
        document.addEventListener('change', (e) => {
            if (e.target.matches('input[name="implementationMode"]')) {
                this.handleModeSelection(e);
            }
        });
        
        // Standard card click handlers
        document.addEventListener('click', (e) => {
            const standardCard = e.target.closest('.standard-card');
            if (standardCard) {
                this.toggleStandardCard(standardCard);
            }
            
            const modeOption = e.target.closest('.mode-option');
            if (modeOption) {
                this.toggleModeOption(modeOption);
            }
        });
    }

    nextStep() {
        if (this.currentStep >= this.totalSteps) return;
        
        // Validate current step
        if (!this.validateCurrentStep()) {
            return;
        }
        
        // Hide current step
        this.hideStep(this.currentStep);
        
        // Move to next step
        this.currentStep++;
        
        // Show next step
        this.showStep(this.currentStep);
        
        // Update step indicators
        this.updateStepIndicators();
        
        // Update buttons
        this.updateButtons();
        
        // Generate content for specific steps
        if (this.currentStep === 3) {
            this.generateStructurePreview();
        } else if (this.currentStep === 4) {
            this.generateConfirmationSummary();
        }
    }

    previousStep() {
        if (this.currentStep <= 1) return;
        
        // Hide current step
        this.hideStep(this.currentStep);
        
        // Move to previous step
        this.currentStep--;
        
        // Show previous step
        this.showStep(this.currentStep);
        
        // Update step indicators
        this.updateStepIndicators();
        
        // Update buttons
        this.updateButtons();
    }

    hideStep(stepNumber) {
        const stepElement = document.getElementById(`wizardStep${stepNumber}`);
        if (stepElement) {
            stepElement.classList.remove('active');
        }
    }

    showStep(stepNumber) {
        const stepElement = document.getElementById(`wizardStep${stepNumber}`);
        if (stepElement) {
            stepElement.classList.add('active');
        }
    }

    updateStepIndicators() {
        document.querySelectorAll('.wizard-step').forEach((step, index) => {
            const stepNumber = index + 1;
            step.classList.remove('active', 'completed');
            
            if (stepNumber === this.currentStep) {
                step.classList.add('active');
            } else if (stepNumber < this.currentStep) {
                step.classList.add('completed');
            }
        });
    }

    updateButtons() {
        const prevBtn = document.getElementById('wizardPrevBtn');
        const nextBtn = document.getElementById('wizardNextBtn');
        const finishBtn = document.getElementById('wizardFinishBtn');
        
        // Previous button
        if (this.currentStep === 1) {
            prevBtn.classList.add('d-none');
        } else {
            prevBtn.classList.remove('d-none');
        }
        
        // Next/Finish buttons
        if (this.currentStep === this.totalSteps) {
            nextBtn.classList.add('d-none');
            finishBtn.classList.remove('d-none');
        } else {
            nextBtn.classList.remove('d-none');
            finishBtn.classList.add('d-none');
        }
    }

    validateCurrentStep() {
        switch (this.currentStep) {
            case 1:
                if (this.selectedStandards.size === 0) {
                    this.showAlert('Seleziona almeno uno standard ISO', 'warning');
                    return false;
                }
                break;
                
            case 2:
                if (!this.implementationMode) {
                    this.showAlert('Seleziona una modalità di implementazione', 'warning');
                    return false;
                }
                break;
                
            case 3:
                // Structure preview - no validation needed
                break;
                
            case 4:
                // Confirmation - no validation needed
                break;
        }
        
        return true;
    }

    handleStandardSelection(event) {
        const standard = event.target.value;
        const card = event.target.closest('.standard-card');
        
        if (event.target.checked) {
            this.selectedStandards.add(standard);
            card.classList.add('selected');
        } else {
            this.selectedStandards.delete(standard);
            card.classList.remove('selected');
        }
    }

    handleModeSelection(event) {
        this.implementationMode = event.target.value;
        
        // Update visual selection
        document.querySelectorAll('.mode-option').forEach(option => {
            option.classList.remove('selected');
        });
        
        const selectedOption = event.target.closest('.mode-option');
        if (selectedOption) {
            selectedOption.classList.add('selected');
        }
    }

    toggleStandardCard(card) {
        const checkbox = card.querySelector('input[type="checkbox"]');
        if (checkbox) {
            checkbox.checked = !checkbox.checked;
            checkbox.dispatchEvent(new Event('change'));
        }
    }

    toggleModeOption(option) {
        const radio = option.querySelector('input[type="radio"]');
        if (radio) {
            radio.checked = true;
            radio.dispatchEvent(new Event('change'));
        }
    }

    generateStructurePreview() {
        const previewContainer = document.getElementById('structurePreview');
        if (!previewContainer) return;
        
        let structure = this.generateStructure();
        let html = '<div class="structure-tree">';
        
        if (this.implementationMode === 'integrated') {
            html += this.renderIntegratedStructure(structure);
        } else if (this.implementationMode === 'separated') {
            html += this.renderSeparatedStructure(structure);
        } else {
            html += this.renderCustomStructure(structure);
        }
        
        html += '</div>';
        previewContainer.innerHTML = html;
        
        this.structurePreview = structure;
    }

    generateStructure() {
        const structure = {
            mode: this.implementationMode,
            standards: Array.from(this.selectedStandards),
            folders: []
        };
        
        if (this.implementationMode === 'integrated') {
            structure.folders = this.getIntegratedFolders();
        } else if (this.implementationMode === 'separated') {
            structure.folders = this.getSeparatedFolders();
        } else {
            structure.folders = this.getCustomFolders();
        }
        
        return structure;
    }

    getIntegratedFolders() {
        return [
            {
                name: 'Sistema di Gestione Integrato',
                type: 'folder',
                children: [
                    {
                        name: '01. Politiche e Obiettivi',
                        type: 'folder',
                        children: [
                            { name: 'Politica Integrata', type: 'file' },
                            { name: 'Obiettivi e Traguardi', type: 'file' },
                            { name: 'Indicatori di Performance', type: 'file' }
                        ]
                    },
                    {
                        name: '02. Manuale del Sistema',
                        type: 'folder',
                        children: [
                            { name: 'Manuale Sistema Integrato', type: 'file' },
                            { name: 'Organigramma', type: 'file' },
                            { name: 'Matrice Responsabilità', type: 'file' }
                        ]
                    },
                    {
                        name: '03. Procedure Operative',
                        type: 'folder',
                        children: [
                            { name: 'Controllo Documenti', type: 'file' },
                            { name: 'Gestione Non Conformità', type: 'file' },
                            { name: 'Azioni Correttive e Preventive', type: 'file' },
                            { name: 'Audit Interni', type: 'file' }
                        ]
                    },
                    {
                        name: '04. Registrazioni',
                        type: 'folder',
                        children: [
                            { name: 'Registrazioni Qualità', type: 'folder' },
                            { name: 'Registrazioni Ambiente', type: 'folder' },
                            { name: 'Registrazioni Sicurezza', type: 'folder' }
                        ]
                    },
                    {
                        name: '05. Monitoraggio e Misurazione',
                        type: 'folder',
                        children: [
                            { name: 'Indicatori di Processo', type: 'file' },
                            { name: 'Soddisfazione Cliente', type: 'file' },
                            { name: 'Monitoraggio Ambientale', type: 'file' },
                            { name: 'Indicatori Sicurezza', type: 'file' }
                        ]
                    },
                    {
                        name: '06. Riesame della Direzione',
                        type: 'folder',
                        children: [
                            { name: 'Verbali Riesame', type: 'folder' },
                            { name: 'Piani di Miglioramento', type: 'folder' }
                        ]
                    }
                ]
            }
        ];
    }

    getSeparatedFolders() {
        const folders = [];
        
        this.selectedStandards.forEach(standard => {
            switch (standard) {
                case 'iso9001':
                    folders.push({
                        name: 'ISO 9001 - Sistema Qualità',
                        type: 'folder',
                        children: this.getISO9001Structure()
                    });
                    break;
                    
                case 'iso14001':
                    folders.push({
                        name: 'ISO 14001 - Sistema Ambientale',
                        type: 'folder',
                        children: this.getISO14001Structure()
                    });
                    break;
                    
                case 'iso45001':
                    folders.push({
                        name: 'ISO 45001 - Sicurezza e Salute',
                        type: 'folder',
                        children: this.getISO45001Structure()
                    });
                    break;
                    
                case 'gdpr':
                    folders.push({
                        name: 'GDPR - Privacy',
                        type: 'folder',
                        children: this.getGDPRStructure()
                    });
                    break;
            }
        });
        
        return folders;
    }

    getCustomFolders() {
        return [
            {
                name: 'Struttura Personalizzata',
                type: 'folder',
                children: [
                    {
                        name: '01. Documenti di Sistema',
                        type: 'folder',
                        children: []
                    },
                    {
                        name: '02. Procedure',
                        type: 'folder',
                        children: []
                    },
                    {
                        name: '03. Moduli e Registrazioni',
                        type: 'folder',
                        children: []
                    }
                ]
            }
        ];
    }

    getISO9001Structure() {
        return [
            {
                name: '4. Contesto dell\'Organizzazione',
                type: 'folder',
                children: [
                    { name: 'Analisi del Contesto', type: 'file' },
                    { name: 'Parti Interessate', type: 'file' },
                    { name: 'Campo di Applicazione', type: 'file' }
                ]
            },
            {
                name: '5. Leadership',
                type: 'folder',
                children: [
                    { name: 'Politica per la Qualità', type: 'file' },
                    { name: 'Ruoli e Responsabilità', type: 'file' }
                ]
            },
            {
                name: '6. Pianificazione',
                type: 'folder',
                children: [
                    { name: 'Rischi e Opportunità', type: 'file' },
                    { name: 'Obiettivi per la Qualità', type: 'file' }
                ]
            },
            {
                name: '7. Supporto',
                type: 'folder',
                children: [
                    { name: 'Risorse', type: 'file' },
                    { name: 'Competenze', type: 'file' },
                    { name: 'Comunicazione', type: 'file' }
                ]
            },
            {
                name: '8. Attività Operative',
                type: 'folder',
                children: [
                    { name: 'Pianificazione Operativa', type: 'file' },
                    { name: 'Progettazione e Sviluppo', type: 'file' },
                    { name: 'Approvvigionamento', type: 'file' }
                ]
            },
            {
                name: '9. Valutazione delle Prestazioni',
                type: 'folder',
                children: [
                    { name: 'Monitoraggio e Misurazione', type: 'file' },
                    { name: 'Audit Interni', type: 'file' },
                    { name: 'Riesame della Direzione', type: 'file' }
                ]
            },
            {
                name: '10. Miglioramento',
                type: 'folder',
                children: [
                    { name: 'Non Conformità', type: 'file' },
                    { name: 'Azioni Correttive', type: 'file' },
                    { name: 'Miglioramento Continuo', type: 'file' }
                ]
            }
        ];
    }

    getISO14001Structure() {
        return [
            {
                name: '4. Contesto dell\'Organizzazione',
                type: 'folder',
                children: [
                    { name: 'Aspetti Ambientali', type: 'file' },
                    { name: 'Compliance Normativa', type: 'file' }
                ]
            },
            {
                name: '5. Leadership',
                type: 'folder',
                children: [
                    { name: 'Politica Ambientale', type: 'file' },
                    { name: 'Responsabilità Ambientali', type: 'file' }
                ]
            },
            {
                name: '6. Pianificazione',
                type: 'folder',
                children: [
                    { name: 'Obiettivi Ambientali', type: 'file' },
                    { name: 'Programmi Ambientali', type: 'file' }
                ]
            },
            {
                name: '7. Supporto',
                type: 'folder',
                children: [
                    { name: 'Formazione Ambientale', type: 'file' },
                    { name: 'Comunicazione Ambientale', type: 'file' }
                ]
            },
            {
                name: '8. Attività Operative',
                type: 'folder',
                children: [
                    { name: 'Controllo Operativo', type: 'file' },
                    { name: 'Emergenze Ambientali', type: 'file' }
                ]
            },
            {
                name: '9. Valutazione delle Prestazioni',
                type: 'folder',
                children: [
                    { name: 'Monitoraggio Ambientale', type: 'file' },
                    { name: 'Valutazione Compliance', type: 'file' }
                ]
            },
            {
                name: '10. Miglioramento',
                type: 'folder',
                children: [
                    { name: 'Non Conformità Ambientali', type: 'file' },
                    { name: 'Miglioramento Ambientale', type: 'file' }
                ]
            }
        ];
    }

    getISO45001Structure() {
        return [
            {
                name: '4. Contesto dell\'Organizzazione',
                type: 'folder',
                children: [
                    { name: 'Pericoli e Rischi SSL', type: 'file' },
                    { name: 'Requisiti Legali SSL', type: 'file' }
                ]
            },
            {
                name: '5. Leadership e Partecipazione',
                type: 'folder',
                children: [
                    { name: 'Politica SSL', type: 'file' },
                    { name: 'Consultazione Lavoratori', type: 'file' }
                ]
            },
            {
                name: '6. Pianificazione',
                type: 'folder',
                children: [
                    { name: 'Valutazione Rischi', type: 'file' },
                    { name: 'Obiettivi SSL', type: 'file' }
                ]
            },
            {
                name: '7. Supporto',
                type: 'folder',
                children: [
                    { name: 'Formazione SSL', type: 'file' },
                    { name: 'Informazione SSL', type: 'file' }
                ]
            },
            {
                name: '8. Attività Operative',
                type: 'folder',
                children: [
                    { name: 'Controlli SSL', type: 'file' },
                    { name: 'Emergenze SSL', type: 'file' },
                    { name: 'Approvvigionamento SSL', type: 'file' }
                ]
            },
            {
                name: '9. Valutazione delle Prestazioni',
                type: 'folder',
                children: [
                    { name: 'Monitoraggio SSL', type: 'file' },
                    { name: 'Investigazione Incidenti', type: 'file' }
                ]
            },
            {
                name: '10. Miglioramento',
                type: 'folder',
                children: [
                    { name: 'Incidenti e Non Conformità', type: 'file' },
                    { name: 'Miglioramento SSL', type: 'file' }
                ]
            }
        ];
    }

    getGDPRStructure() {
        return [
            {
                name: 'Documentazione GDPR',
                type: 'folder',
                children: [
                    { name: 'Registro Trattamenti', type: 'file' },
                    { name: 'Privacy Policy', type: 'file' },
                    { name: 'Cookie Policy', type: 'file' }
                ]
            },
            {
                name: 'Procedure Privacy',
                type: 'folder',
                children: [
                    { name: 'Gestione Data Breach', type: 'file' },
                    { name: 'Diritti Interessati', type: 'file' },
                    { name: 'DPIA Procedure', type: 'file' }
                ]
            },
            {
                name: 'Consensi e Informative',
                type: 'folder',
                children: [
                    { name: 'Moduli Consenso', type: 'folder' },
                    { name: 'Informative Privacy', type: 'folder' }
                ]
            },
            {
                name: 'Sicurezza Dati',
                type: 'folder',
                children: [
                    { name: 'Misure Sicurezza', type: 'file' },
                    { name: 'Pseudonimizzazione', type: 'file' }
                ]
            }
        ];
    }

    renderIntegratedStructure(structure) {
        return this.renderTreeStructure(structure.folders, 0);
    }

    renderSeparatedStructure(structure) {
        return this.renderTreeStructure(structure.folders, 0);
    }

    renderCustomStructure(structure) {
        return this.renderTreeStructure(structure.folders, 0) + 
               '<p class="text-muted mt-3"><em>La struttura personalizzata potrà essere modificata dopo la creazione del sistema.</em></p>';
    }

    renderTreeStructure(items, level) {
        let html = '';
        const indent = '│  '.repeat(level);
        
        items.forEach((item, index) => {
            const isLast = index === items.length - 1;
            const prefix = isLast ? '└── ' : '├── ';
            const iconClass = item.type === 'folder' ? 'folder' : 'file';
            
            html += `<div class="${iconClass}">${indent}${prefix}📁 ${item.name}</div>\n`;
            
            if (item.children && item.children.length > 0) {
                html += this.renderTreeStructure(item.children, level + 1);
            }
        });
        
        return html;
    }

    generateConfirmationSummary() {
        const summaryContainer = document.getElementById('confirmationSummary');
        if (!summaryContainer) return;
        
        const standardNames = {
            'iso9001': 'ISO 9001:2015 - Sistema di Gestione per la Qualità',
            'iso14001': 'ISO 14001:2015 - Sistema di Gestione Ambientale',
            'iso45001': 'ISO 45001:2018 - Sistema di Gestione SSL',
            'gdpr': 'GDPR - Regolamento Generale sulla Protezione dei Dati'
        };
        
        const modeNames = {
            'separated': 'Cartelle Separate',
            'integrated': 'Sistema Integrato',
            'custom': 'Struttura Personalizzata'
        };
        
        let html = `
            <div class="row">
                <div class="col-md-6">
                    <h6><i class="fas fa-certificate text-primary"></i> Standard Selezionati:</h6>
                    <ul class="list-unstyled mb-4">
        `;
        
        this.selectedStandards.forEach(standard => {
            html += `<li class="mb-2">
                <span class="badge bg-primary me-2">✓</span>
                ${standardNames[standard] || standard}
            </li>`;
        });
        
        html += `
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6><i class="fas fa-cogs text-primary"></i> Modalità Implementazione:</h6>
                    <div class="alert alert-light">
                        <strong>${modeNames[this.implementationMode] || this.implementationMode}</strong>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-12">
                    <h6><i class="fas fa-sitemap text-primary"></i> Struttura da Creare:</h6>
                    <div class="bg-light p-3 rounded">
                        <small class="text-muted">
                            Verranno create <strong>${this.countFolders(this.structurePreview.folders)}</strong> cartelle 
                            e <strong>${this.countFiles(this.structurePreview.folders)}</strong> template di documenti.
                        </small>
                    </div>
                </div>
            </div>
        `;
        
        summaryContainer.innerHTML = html;
    }

    countFolders(folders) {
        let count = folders.length;
        folders.forEach(folder => {
            if (folder.children) {
                count += this.countFolders(folder.children.filter(child => child.type === 'folder'));
            }
        });
        return count;
    }

    countFiles(folders) {
        let count = 0;
        folders.forEach(folder => {
            if (folder.children) {
                count += folder.children.filter(child => child.type === 'file').length;
                count += this.countFiles(folder.children.filter(child => child.type === 'folder'));
            }
        });
        return count;
    }

    async finishWizard() {
        if (this.isCreating) return;
        
        this.isCreating = true;
        const finishBtn = document.getElementById('wizardFinishBtn');
        const originalText = finishBtn.innerHTML;
        
        finishBtn.disabled = true;
        finishBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creazione in corso...';
        
        try {
            const response = await fetch('backend/api/iso-setup-api.php?action=create-system', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    company_id: currentCompanyId,
                    standards: Array.from(this.selectedStandards),
                    implementation_mode: this.implementationMode,
                    structure: this.structurePreview
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showAlert('Sistema ISO creato con successo!', 'success');
                
                // Close modal after delay
                setTimeout(() => {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('configModal'));
                    modal.hide();
                    
                    // Reload the page or refresh the file explorer
                    window.location.reload();
                }, 2000);
            } else {
                this.showAlert('Errore nella creazione del sistema: ' + result.message, 'error');
                finishBtn.disabled = false;
                finishBtn.innerHTML = originalText;
            }
        } catch (error) {
            console.error('Error creating ISO system:', error);
            this.showAlert('Errore di connessione durante la creazione del sistema', 'error');
            finishBtn.disabled = false;
            finishBtn.innerHTML = originalText;
        }
        
        this.isCreating = false;
    }

    showAlert(message, type = 'info') {
        // Create alert element
        const alertClass = {
            'success': 'alert-success',
            'error': 'alert-danger',
            'warning': 'alert-warning',
            'info': 'alert-info'
        }[type] || 'alert-info';
        
        const alertHtml = `
            <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        // Find a container to show the alert
        const currentStep = document.querySelector('.wizard-content.active');
        if (currentStep) {
            // Remove existing alerts
            currentStep.querySelectorAll('.alert').forEach(alert => alert.remove());
            
            // Add new alert at the top
            currentStep.insertAdjacentHTML('afterbegin', alertHtml);
        }
    }

    reset() {
        this.currentStep = 1;
        this.selectedStandards.clear();
        this.implementationMode = null;
        this.structurePreview = null;
        this.isCreating = false;
        
        // Reset UI
        this.hideStep(2);
        this.hideStep(3);
        this.hideStep(4);
        this.showStep(1);
        this.updateStepIndicators();
        this.updateButtons();
        
        // Clear form selections
        document.querySelectorAll('input[type="checkbox"]').forEach(cb => {
            cb.checked = false;
        });
        document.querySelectorAll('input[type="radio"]').forEach(radio => {
            radio.checked = false;
        });
        document.querySelectorAll('.standard-card, .mode-option').forEach(card => {
            card.classList.remove('selected');
        });
    }
}

// Initialize wizard when modal is shown - ONLY when explicitly triggered by user action
// Removed automatic modal initialization to prevent unwanted popups

// Export the ISOWizard class
window.ISOWizard = ISOWizard;