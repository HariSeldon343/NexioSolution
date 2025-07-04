/**
 * Template Builder Drag and Drop JavaScript
 * Sistema completo per la gestione del template builder con drag-and-drop
 */

// Configurazione globale
let templateConfig = {
    header: {
        columns: 1,
        data: {}
    },
    footer: {
        columns: 1,
        data: {}
    }
};

// Element types configuration
const elementTypes = {
    'titolo_documento': {
        icon: 'fas fa-heading',
        label: 'Nome Documento',
        preview: '[Nome Documento]',
        placeholder: '{{documento.titolo}}'
    },
    'codice_documento': {
        icon: 'fas fa-barcode',
        label: 'Codice Documento',
        preview: '[COD-001]',
        placeholder: '{{documento.codice}}'
    },
    'numero_versione': {
        icon: 'fas fa-code-branch',
        label: 'Versione',
        preview: 'v1.0',
        placeholder: '{{documento.versione}}'
    },
    'data_creazione': {
        icon: 'fas fa-calendar-plus',
        label: 'Data Creazione',
        preview: '01/01/2024',
        placeholder: '{{documento.data_creazione}}'
    },
    'data_revisione': {
        icon: 'fas fa-calendar-edit',
        label: 'Data Revisione',
        preview: '15/01/2024',
        placeholder: '{{documento.data_revisione}}'
    },
    'logo': {
        icon: 'fas fa-image',
        label: 'Logo Aziendale',
        preview: '[LOGO]',
        placeholder: '{{azienda.logo}}'
    },
    'azienda_nome': {
        icon: 'fas fa-building',
        label: 'Nome Azienda',
        preview: '[Nome Azienda]',
        placeholder: '{{azienda.nome}}'
    },
    'copyright': {
        icon: 'fas fa-copyright',
        label: 'Copyright',
        preview: '© 2024 Azienda',
        placeholder: '{{azienda.copyright}}'
    },
    'numero_pagine': {
        icon: 'fas fa-file-alt',
        label: 'Numero Pagina',
        preview: 'Pag. 1',
        placeholder: '{{sistema.numero_pagina}}'
    },
    'testo_libero': {
        icon: 'fas fa-font',
        label: 'Testo Libero',
        preview: '[Testo Personalizzato]',
        placeholder: '',
        customizable: true
    },
    'separatore': {
        icon: 'fas fa-minus',
        label: 'Separatore',
        preview: '---',
        placeholder: '<hr>'
    }
};

// Inizializzazione
document.addEventListener('DOMContentLoaded', function() {
    initializeDragAndDrop();
    initializeColumnControls();
    initializeFormSubmission();
    updateGridColumns('header', 1);
    updateGridColumns('footer', 1);
});

/**
 * Inizializza il sistema drag-and-drop
 */
function initializeDragAndDrop() {
    // Inizializza elementi trascinabili dalla palette
    const paletteElements = document.querySelectorAll('.element-item');
    paletteElements.forEach(element => {
        element.addEventListener('dragstart', handleDragStart);
        element.setAttribute('draggable', 'true');
    });

    // Inizializza drop zones
    initializeDropZones();
}

/**
 * Inizializza le drop zones
 */
function initializeDropZones() {
    const dropZones = document.querySelectorAll('.drop-zone');
    dropZones.forEach(zone => {
        zone.addEventListener('dragover', handleDragOver);
        zone.addEventListener('drop', handleDrop);
        zone.addEventListener('dragenter', handleDragEnter);
        zone.addEventListener('dragleave', handleDragLeave);
        
        // Inizializza Sortable per riordinamento elementi
        new Sortable(zone, {
            group: 'template-elements',
            animation: 150,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            onStart: function(evt) {
                evt.item.classList.add('dragging');
            },
            onEnd: function(evt) {
                evt.item.classList.remove('dragging');
                updateTemplateConfig();
                updateLivePreview();
            }
        });
    });
}

/**
 * Gestisce l'inizio del trascinamento
 */
function handleDragStart(e) {
    const elementType = e.target.getAttribute('data-type');
    e.dataTransfer.setData('text/plain', elementType);
    e.dataTransfer.effectAllowed = 'copy';
    e.target.classList.add('dragging');
}

/**
 * Gestisce il trascinamento sopra una drop zone
 */
function handleDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'copy';
}

/**
 * Gestisce l'entrata in una drop zone
 */
function handleDragEnter(e) {
    e.preventDefault();
    e.target.closest('.drop-zone').classList.add('drag-over');
}

/**
 * Gestisce l'uscita da una drop zone
 */
function handleDragLeave(e) {
    if (!e.target.closest('.drop-zone').contains(e.relatedTarget)) {
        e.target.closest('.drop-zone').classList.remove('drag-over');
    }
}

/**
 * Gestisce il drop di un elemento
 */
function handleDrop(e) {
    e.preventDefault();
    const dropZone = e.target.closest('.drop-zone');
    dropZone.classList.remove('drag-over');
    
    const elementType = e.dataTransfer.getData('text/plain');
    if (elementType && elementTypes[elementType]) {
        addElementToZone(dropZone, elementType);
    }
    
    // Rimuovi classe dragging da tutti gli elementi
    document.querySelectorAll('.dragging').forEach(el => {
        el.classList.remove('dragging');
    });
}

/**
 * Aggiunge un elemento a una drop zone
 */
function addElementToZone(dropZone, elementType) {
    const elementConfig = elementTypes[elementType];
    const elementId = 'element_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    
    // Crea l'elemento HTML
    const elementDiv = document.createElement('div');
    elementDiv.className = 'dropped-element';
    elementDiv.setAttribute('data-type', elementType);
    elementDiv.setAttribute('data-id', elementId);
    
    let customContent = '';
    if (elementConfig.customizable) {
        const customText = prompt('Inserisci il testo personalizzato:', elementConfig.preview);
        if (customText === null) return; // Annullato
        customContent = customText || elementConfig.preview;
    }
    
    elementDiv.innerHTML = `
        <div class="element-content">
            <i class="${elementConfig.icon}"></i>
            <span>${customContent || elementConfig.label}</span>
        </div>
        <div class="element-actions">
            <button type="button" class="element-btn edit" onclick="editElement('${elementId}')" title="Modifica">
                <i class="fas fa-edit"></i>
            </button>
            <button type="button" class="element-btn delete" onclick="removeElement('${elementId}')" title="Rimuovi">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    // Nascondi placeholder e aggiungi elemento
    const placeholder = dropZone.querySelector('.drop-placeholder');
    if (placeholder) {
        placeholder.style.display = 'none';
    }
    
    dropZone.classList.add('has-elements');
    dropZone.appendChild(elementDiv);
    
    // Salva dati elemento
    const section = dropZone.getAttribute('data-section');
    const column = parseInt(dropZone.getAttribute('data-column'));
    const row = parseInt(dropZone.getAttribute('data-row'));
    
    if (!templateConfig[section].data[column]) {
        templateConfig[section].data[column] = {};
    }
    if (!templateConfig[section].data[column][row]) {
        templateConfig[section].data[column][row] = [];
    }
    
    const elementData = {
        id: elementId,
        type: elementType,
        content: customContent || elementConfig.placeholder,
        style: '',
        align: 'left'
    };
    
    templateConfig[section].data[column][row].push(elementData);
    
    // Aggiorna preview e configurazione
    updateTemplateConfig();
    updateLivePreview();
}

/**
 * Rimuove un elemento
 */
function removeElement(elementId) {
    const element = document.querySelector(`[data-id="${elementId}"]`);
    if (!element) return;
    
    if (confirm('Sei sicuro di voler rimuovere questo elemento?')) {
        const dropZone = element.closest('.drop-zone');
        element.remove();
        
        // Se non ci sono più elementi, mostra il placeholder
        if (dropZone.children.length === 0 || (dropZone.children.length === 1 && dropZone.querySelector('.drop-placeholder'))) {
            const placeholder = dropZone.querySelector('.drop-placeholder');
            if (placeholder) {
                placeholder.style.display = 'flex';
            }
            dropZone.classList.remove('has-elements');
        }
        
        // Rimuovi dai dati
        removeElementFromConfig(elementId);
        updateTemplateConfig();
        updateLivePreview();
    }
}

/**
 * Rimuove un elemento dalla configurazione
 */
function removeElementFromConfig(elementId) {
    ['header', 'footer'].forEach(section => {
        Object.keys(templateConfig[section].data).forEach(columnKey => {
            Object.keys(templateConfig[section].data[columnKey]).forEach(rowKey => {
                templateConfig[section].data[columnKey][rowKey] = 
                    templateConfig[section].data[columnKey][rowKey].filter(el => el.id !== elementId);
            });
        });
    });
}

/**
 * Modifica un elemento
 */
function editElement(elementId) {
    const element = document.querySelector(`[data-id="${elementId}"]`);
    if (!element) return;
    
    const elementType = element.getAttribute('data-type');
    const elementConfig = elementTypes[elementType];
    
    if (elementConfig.customizable) {
        const currentText = element.querySelector('.element-content span').textContent;
        const newText = prompt('Modifica il testo:', currentText);
        
        if (newText !== null) {
            element.querySelector('.element-content span').textContent = newText;
            
            // Aggiorna nella configurazione
            updateElementInConfig(elementId, { content: newText });
            updateTemplateConfig();
            updateLivePreview();
        }
    } else {
        alert('Questo elemento non è modificabile');
    }
}

/**
 * Aggiorna un elemento nella configurazione
 */
function updateElementInConfig(elementId, updates) {
    ['header', 'footer'].forEach(section => {
        Object.keys(templateConfig[section].data).forEach(columnKey => {
            Object.keys(templateConfig[section].data[columnKey]).forEach(rowKey => {
                templateConfig[section].data[columnKey][rowKey].forEach(el => {
                    if (el.id === elementId) {
                        Object.assign(el, updates);
                    }
                });
            });
        });
    });
}

/**
 * Controlli per cambiare il numero di colonne
 */
function initializeColumnControls() {
    // I controlli sono gestiti tramite onclick negli elementi HTML
}

/**
 * Cambia il numero di colonne
 */
function changeColumns(section, delta) {
    const currentCount = templateConfig[section].columns;
    const newCount = Math.max(1, Math.min(3, currentCount + delta));
    
    if (newCount !== currentCount) {
        templateConfig[section].columns = newCount;
        updateGridColumns(section, newCount);
        updateColumnCounter(section, newCount);
        updateTemplateConfig();
        updateLivePreview();
    }
}

/**
 * Aggiorna la griglia delle colonne
 */
function updateGridColumns(section, columnCount) {
    const grid = document.getElementById(`${section}Grid`);
    if (!grid) return;
    
    // Pulisci griglia esistente
    grid.innerHTML = '';
    
    // Imposta CSS grid
    grid.style.gridTemplateColumns = `repeat(${columnCount}, 1fr)`;
    
    // Crea colonne
    for (let col = 0; col < columnCount; col++) {
        const column = document.createElement('div');
        column.className = 'grid-column';
        column.setAttribute('data-column', col);
        
        // Crea 3 righe per colonna
        for (let row = 0; row < 3; row++) {
            const dropZone = document.createElement('div');
            dropZone.className = 'drop-zone';
            dropZone.setAttribute('data-section', section);
            dropZone.setAttribute('data-column', col);
            dropZone.setAttribute('data-row', row);
            
            dropZone.innerHTML = `
                <div class="drop-placeholder">
                    <i class="fas fa-plus"></i>
                    <span>Trascina elementi qui</span>
                </div>
            `;
            
            // Aggiungi event listeners
            dropZone.addEventListener('dragover', handleDragOver);
            dropZone.addEventListener('drop', handleDrop);
            dropZone.addEventListener('dragenter', handleDragEnter);
            dropZone.addEventListener('dragleave', handleDragLeave);
            
            // Inizializza Sortable
            new Sortable(dropZone, {
                group: 'template-elements',
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                onEnd: function(evt) {
                    updateTemplateConfig();
                    updateLivePreview();
                }
            });
            
            column.appendChild(dropZone);
        }
        
        grid.appendChild(column);
    }
    
    // Ricarica elementi esistenti se presenti
    loadExistingElements(section);
}

/**
 * Aggiorna il contatore delle colonne
 */
function updateColumnCounter(section, count) {
    const counter = document.getElementById(`${section}ColumnCount`);
    if (counter) {
        counter.textContent = count;
    }
}

/**
 * Carica elementi esistenti nella griglia
 */
function loadExistingElements(section) {
    if (!templateConfig[section].data) return;
    
    Object.keys(templateConfig[section].data).forEach(columnKey => {
        const column = parseInt(columnKey);
        if (column >= templateConfig[section].columns) return;
        
        Object.keys(templateConfig[section].data[columnKey]).forEach(rowKey => {
            const row = parseInt(rowKey);
            if (row >= 3) return;
            
            const elements = templateConfig[section].data[columnKey][rowKey];
            const dropZone = document.querySelector(`[data-section="${section}"][data-column="${column}"][data-row="${row}"]`);
            
            if (dropZone && elements.length > 0) {
                const placeholder = dropZone.querySelector('.drop-placeholder');
                if (placeholder) {
                    placeholder.style.display = 'none';
                }
                dropZone.classList.add('has-elements');
                
                elements.forEach(elementData => {
                    const elementDiv = document.createElement('div');
                    elementDiv.className = 'dropped-element';
                    elementDiv.setAttribute('data-type', elementData.type);
                    elementDiv.setAttribute('data-id', elementData.id);
                    
                    const elementConfig = elementTypes[elementData.type];
                    const displayText = elementData.content || elementConfig.label;
                    
                    elementDiv.innerHTML = `
                        <div class="element-content">
                            <i class="${elementConfig.icon}"></i>
                            <span>${displayText}</span>
                        </div>
                        <div class="element-actions">
                            <button type="button" class="element-btn edit" onclick="editElement('${elementData.id}')" title="Modifica">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="element-btn delete" onclick="removeElement('${elementData.id}')" title="Rimuovi">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    `;
                    
                    dropZone.appendChild(elementDiv);
                });
            }
        });
    });
}

/**
 * Aggiorna la configurazione del template
 */
function updateTemplateConfig() {
    // Raccogli dati da tutte le drop zones
    ['header', 'footer'].forEach(section => {
        templateConfig[section].data = {};
        
        const dropZones = document.querySelectorAll(`[data-section="${section}"]`);
        dropZones.forEach(zone => {
            const column = parseInt(zone.getAttribute('data-column'));
            const row = parseInt(zone.getAttribute('data-row'));
            
            if (!templateConfig[section].data[column]) {
                templateConfig[section].data[column] = {};
            }
            if (!templateConfig[section].data[column][row]) {
                templateConfig[section].data[column][row] = [];
            }
            
            const elements = zone.querySelectorAll('.dropped-element');
            templateConfig[section].data[column][row] = [];
            
            elements.forEach(element => {
                const elementData = {
                    id: element.getAttribute('data-id'),
                    type: element.getAttribute('data-type'),
                    content: element.querySelector('.element-content span').textContent,
                    style: '',
                    align: 'left'
                };
                templateConfig[section].data[column][row].push(elementData);
            });
        });
    });
    
    // Aggiorna i campi hidden del form
    updateHiddenFormFields();
}

/**
 * Aggiorna i campi nascosti del form
 */
function updateHiddenFormFields() {
    const headerConfig = {
        columns: Array.from({length: templateConfig.header.columns}, (_, i) => ({
            rows: Array.from({length: 3}, (_, j) => ({
                elements: templateConfig.header.data[i] && templateConfig.header.data[i][j] ? 
                         templateConfig.header.data[i][j] : []
            }))
        }))
    };
    
    const footerConfig = {
        columns: Array.from({length: templateConfig.footer.columns}, (_, i) => ({
            rows: Array.from({length: 3}, (_, j) => ({
                elements: templateConfig.footer.data[i] && templateConfig.footer.data[i][j] ? 
                         templateConfig.footer.data[i][j] : []
            }))
        }))
    };
    
    document.getElementById('headerConfigData').value = JSON.stringify(headerConfig);
    document.getElementById('footerConfigData').value = JSON.stringify(footerConfig);
}

/**
 * Aggiorna l'anteprima live
 */
function updateLivePreview() {
    updateSectionPreview('header', 'livePreviewHeader');
    updateSectionPreview('footer', 'livePreviewFooter');
}

/**
 * Aggiorna l'anteprima di una sezione
 */
function updateSectionPreview(section, previewId) {
    const preview = document.getElementById(previewId);
    if (!preview) return;
    
    const sectionData = templateConfig[section];
    let html = '';
    
    if (Object.keys(sectionData.data).length === 0) {
        html = '<div class="preview-empty">' + (section === 'header' ? 'Intestazione vuota' : 'Piè di pagina vuoto') + '</div>';
    } else {
        html = '<div style="display: grid; grid-template-columns: repeat(' + sectionData.columns + ', 1fr); gap: 12px; width: 100%;">';
        
        for (let col = 0; col < sectionData.columns; col++) {
            html += '<div style="display: flex; flex-direction: column; gap: 4px;">';
            
            for (let row = 0; row < 3; row++) {
                if (sectionData.data[col] && sectionData.data[col][row] && sectionData.data[col][row].length > 0) {
                    sectionData.data[col][row].forEach(element => {
                        const elementConfig = elementTypes[element.type];
                        const displayContent = element.content || elementConfig.preview;
                        html += `<div style="padding: 4px 8px; background: #f7fafc; border-radius: 4px; font-size: 11px; color: #4a5568;">${displayContent}</div>`;
                    });
                }
            }
            
            html += '</div>';
        }
        
        html += '</div>';
    }
    
    preview.innerHTML = html;
}

/**
 * Carica configurazione template esistente
 */
function loadTemplateConfiguration(section, config) {
    if (!config || !config.columns) return;
    
    templateConfig[section].columns = config.columns.length;
    templateConfig[section].data = {};
    
    config.columns.forEach((column, colIndex) => {
        if (column.rows) {
            templateConfig[section].data[colIndex] = {};
            column.rows.forEach((row, rowIndex) => {
                if (row.elements) {
                    templateConfig[section].data[colIndex][rowIndex] = row.elements.map(element => ({
                        ...element,
                        id: element.id || 'element_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9)
                    }));
                }
            });
        }
    });
    
    updateGridColumns(section, templateConfig[section].columns);
    updateColumnCounter(section, templateConfig[section].columns);
    updateTemplateConfig();
    updateLivePreview();
}

/**
 * Inizializza la gestione del form
 */
function initializeFormSubmission() {
    const form = document.getElementById('templateForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            updateTemplateConfig();
            saveTemplate();
        });
    }
}

/**
 * Salva il template via AJAX
 */
async function saveTemplate() {
    const form = document.getElementById('templateForm');
    const formData = new FormData(form);
    
    // Converti FormData in oggetto
    const templateData = {};
    formData.forEach((value, key) => {
        templateData[key] = value;
    });
    
    // Aggiungi configurazioni come oggetti invece che stringhe
    const headerConfig = document.getElementById('headerConfigData').value;
    const footerConfig = document.getElementById('footerConfigData').value;
    
    templateData.intestazione_config = headerConfig ? JSON.parse(headerConfig) : null;
    templateData.pie_pagina_config = footerConfig ? JSON.parse(footerConfig) : null;
    
    const isUpdate = templateData.action === 'update';
    const url = `/piattaforma-collaborativa/backend/api/template-dragdrop-api.php?action=${isUpdate ? 'update' : 'create'}`;
    
    // Debug: Log dei dati che vengono inviati
    console.log('Template data being sent:', templateData);
    console.log('Is update:', isUpdate);
    console.log('Template ID:', templateData.id);
    
    try {
        showLoadingState(true);
        
        const response = await fetch(url, {
            method: isUpdate ? 'PUT' : 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(templateData)
        });
        
        // Verifica che la risposta sia valida
        const text = await response.text();
        if (!text.trim()) {
            throw new Error('Risposta vuota dal server');
        }
        
        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            console.error('Risposta non valida dal server:', text);
            throw new Error('Risposta non valida dal server. Controlla i log per maggiori dettagli.');
        }
        
        if (result.success) {
            showNotification(result.message, 'success');
            
            if (!isUpdate) {
                // Nuovo template creato, reindirizza alla lista
                setTimeout(() => {
                    window.location.href = 'template-builder-dragdrop.php';
                }, 1500);
            } else {
                // Template aggiornato
                setTimeout(() => {
                    showTab('list');
                }, 1000);
            }
        } else {
            throw new Error(result.message || 'Errore durante il salvataggio');
        }
        
    } catch (error) {
        console.error('Errore salvataggio template:', error);
        showNotification('Errore durante il salvataggio: ' + error.message, 'error');
    } finally {
        showLoadingState(false);
    }
}

/**
 * Mostra/nasconde stato di caricamento
 */
function showLoadingState(loading) {
    const submitBtn = document.querySelector('button[type="submit"]');
    if (submitBtn) {
        if (loading) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
        } else {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Salva Template';
        }
    }
}

/**
 * Mostra notifiche all'utente
 */
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        </div>
        <button class="notification-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Aggiungi stili per la notifica se non esistono
    if (!document.getElementById('notification-styles')) {
        const style = document.createElement('style');
        style.id = 'notification-styles';
        style.textContent = `
            .notification {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 10000;
                min-width: 300px;
                padding: 16px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                display: flex;
                align-items: center;
                justify-content: space-between;
                animation: slideInRight 0.3s ease;
            }
            
            .notification-success {
                background: #c6f6d5;
                color: #22543d;
                border: 1px solid #9ae6b4;
            }
            
            .notification-error {
                background: #fed7d7;
                color: #742a2a;
                border: 1px solid #feb2b2;
            }
            
            .notification-info {
                background: #bee3f8;
                color: #2a4365;
                border: 1px solid #90cdf4;
            }
            
            .notification-content {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .notification-close {
                background: none;
                border: none;
                cursor: pointer;
                color: inherit;
                opacity: 0.7;
                padding: 4px;
            }
            
            .notification-close:hover {
                opacity: 1;
            }
            
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        `;
        document.head.appendChild(style);
    }
    
    document.body.appendChild(notification);
    
    // Auto-remove dopo 5 secondi
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

/**
 * Gestione tab
 */
function showTab(tabName) {
    // Nascondi tutti i contenuti tab
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Rimuovi classe active da tutti i bottoni tab
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Mostra il tab selezionato
    const targetTab = document.getElementById(tabName + 'Tab');
    if (targetTab) {
        targetTab.classList.add('active');
    }
    
    // Attiva il bottone tab
    const targetBtn = document.querySelector(`[data-tab="${tabName}"]`);
    if (targetBtn) {
        targetBtn.classList.add('active');
    }
}

/**
 * Anteprima completa del template
 */
function previewTemplate() {
    updateTemplateConfig();
    
    const previewWindow = window.open('', '_blank', 'width=1000,height=800,scrollbars=yes');
    const headerConfig = templateConfig.header;
    const footerConfig = templateConfig.footer;
    
    // Genera HTML per l'intestazione
    let headerHTML = generateSectionHTML(headerConfig);
    
    // Genera HTML per il piè di pagina
    let footerHTML = generateSectionHTML(footerConfig);
    
    previewWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Anteprima Template - ${document.getElementById('nome').value || 'Nuovo Template'}</title>
            <style>
                body { 
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                    margin: 0; 
                    padding: 20px; 
                    background: #f5f5f5;
                }
                .template-preview { 
                    max-width: 800px; 
                    margin: 0 auto; 
                    background: white; 
                    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                    border-radius: 8px;
                    overflow: hidden;
                }
                .preview-header, .preview-footer { 
                    padding: 20px; 
                    background: #f8f9fa;
                    border-bottom: 2px solid #e9ecef;
                    display: grid;
                    gap: 10px;
                }
                .preview-footer {
                    border-bottom: none;
                    border-top: 2px solid #e9ecef;
                }
                .preview-content { 
                    padding: 60px 40px; 
                    min-height: 400px; 
                    text-align: center;
                    color: #666;
                    font-size: 16px;
                    line-height: 1.6;
                }
                .element-preview {
                    margin: 5px 0;
                    padding: 8px;
                    background: white;
                    border: 1px solid #dee2e6;
                    border-radius: 4px;
                    font-size: 14px;
                }
                .column-container {
                    display: grid;
                    gap: 15px;
                    margin-bottom: 10px;
                }
                .empty-section {
                    color: #999;
                    font-style: italic;
                    text-align: center;
                    padding: 20px;
                }
                h1 { color: #2c3e50; margin-bottom: 10px; }
                .template-info {
                    background: #e9ecef;
                    padding: 15px;
                    margin-bottom: 20px;
                    border-radius: 5px;
                    text-align: center;
                }
            </style>
        </head>
        <body>
            <div class="template-info">
                <h1>Anteprima Template: ${document.getElementById('nome').value || 'Nuovo Template'}</h1>
                <p><strong>Tipo:</strong> ${document.getElementById('tipo_template').options[document.getElementById('tipo_template').selectedIndex].text}</p>
                <p><strong>Descrizione:</strong> ${document.getElementById('descrizione').value || 'Nessuna descrizione'}</p>
            </div>
            
            <div class="template-preview">
                <div class="preview-header">
                    ${headerHTML || '<div class="empty-section">Intestazione vuota</div>'}
                </div>
                
                <div class="preview-content">
                    <h2>📄 Contenuto del Documento</h2>
                    <p>Questo è il contenuto principale del documento.</p>
                    <p>Il template verrà applicato automaticamente quando si genera un documento utilizzando questo modello.</p>
                    <hr style="margin: 30px 0; border: 1px solid #eee;">
                    <p><em>Gli elementi dell'intestazione e del piè di pagina appariranno su ogni pagina del documento.</em></p>
                </div>
                
                <div class="preview-footer">
                    ${footerHTML || '<div class="empty-section">Piè di pagina vuoto</div>'}
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 20px; color: #666;">
                <p><small>Anteprima generata il ${new Date().toLocaleString('it-IT')}</small></p>
            </div>
        </body>
        </html>
    `);
    
    previewWindow.document.close();
}

// Funzione helper per generare HTML delle sezioni
function generateSectionHTML(sectionConfig) {
    if (!sectionConfig || !sectionConfig.data || Object.keys(sectionConfig.data).length === 0) {
        return '';
    }
    
    let html = '';
    const columnCount = sectionConfig.columns || 1;
    
    // Imposta il grid CSS
    html += `<div class="column-container" style="grid-template-columns: repeat(${columnCount}, 1fr);">`;
    
    for (let col = 0; col < columnCount; col++) {
        html += '<div class="column">';
        
        if (sectionConfig.data[col]) {
            const maxRows = Math.max(...Object.keys(sectionConfig.data[col]).map(r => parseInt(r))) + 1;
            
            for (let row = 0; row < maxRows; row++) {
                if (sectionConfig.data[col][row] && sectionConfig.data[col][row].length > 0) {
                    sectionConfig.data[col][row].forEach(element => {
                        html += `<div class="element-preview">
                            <strong>${getElementDisplayName(element.type)}:</strong> ${element.content || getElementPreviewContent(element.type)}
                        </div>`;
                    });
                }
            }
        }
        
        html += '</div>';
    }
    
    html += '</div>';
    return html;
}

// Funzione helper per ottenere il nome visualizzato dell'elemento
function getElementDisplayName(type) {
    const displayNames = {
        'titolo_documento': 'Nome Documento',
        'codice_documento': 'Codice Documento', 
        'numero_versione': 'Versione',
        'data_creazione': 'Data Creazione',
        'data_revisione': 'Data Revisione',
        'logo': 'Logo Aziendale',
        'azienda_nome': 'Nome Azienda',
        'copyright': 'Copyright',
        'numero_pagine': 'Numero Pagina',
        'testo_libero': 'Testo Libero',
        'separatore': 'Separatore'
    };
    return displayNames[type] || type;
}

// Funzione helper per ottenere il contenuto di anteprima dell'elemento
function getElementPreviewContent(type) {
    const previewContent = {
        'titolo_documento': 'Documento di Esempio',
        'codice_documento': 'DOC-2024-001',
        'numero_versione': 'v1.0',
        'data_creazione': new Date().toLocaleDateString('it-IT'),
        'data_revisione': new Date().toLocaleDateString('it-IT'),
        'logo': '[Logo Aziendale]',
        'azienda_nome': 'Nome Azienda S.r.l.',
        'copyright': '© 2024 Tutti i diritti riservati',
        'numero_pagine': 'Pagina 1 di 1',
        'testo_libero': 'Testo personalizzato',
        'separatore': '───────────────'
    };
    return previewContent[type] || '[Contenuto elemento]';
}

/**
 * Duplica un template
 */
async function duplicateTemplate(templateId) {
    if (!confirm('Vuoi duplicare questo template?')) {
        return;
    }
    
    try {
        const response = await fetch('/piattaforma-collaborativa/backend/api/template-dragdrop-api.php?action=duplicate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                source_id: templateId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Template duplicato con successo!', 'success');
            // Ricarica la pagina per mostrare il nuovo template
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            throw new Error(result.message || 'Errore durante la duplicazione');
        }
        
    } catch (error) {
        console.error('Errore duplicazione template:', error);
        showNotification('Errore durante la duplicazione: ' + error.message, 'error');
    }
}

/**
 * Elimina un template
 */
async function deleteTemplate(templateId) {
    if (!confirm('Sei sicuro di voler eliminare questo template? Questa azione non può essere annullata.')) {
        return;
    }
    
    // Seconda conferma per operazioni critiche
    if (!confirm('ATTENZIONE: Eliminando questo template, tutti i documenti che lo utilizzano potrebbero non funzionare correttamente. Confermi l\'eliminazione?')) {
        return;
    }
    
    try {
        const response = await fetch(`/piattaforma-collaborativa/backend/api/template-dragdrop-api.php?action=delete&id=${templateId}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Template eliminato con successo!', 'success');
            
            // Rimuovi la card del template dalla UI
            const templateCard = document.querySelector(`[data-template-id="${templateId}"]`);
            if (templateCard) {
                templateCard.style.animation = 'fadeOut 0.3s ease';
                setTimeout(() => {
                    templateCard.remove();
                    
                    // Se non ci sono più template, mostra empty state
                    const templatesGrid = document.querySelector('.templates-grid');
                    if (templatesGrid && templatesGrid.children.length === 0) {
                        window.location.reload();
                    }
                }, 300);
            }
        } else {
            throw new Error(result.message || 'Errore durante l\'eliminazione');
        }
        
    } catch (error) {
        console.error('Errore eliminazione template:', error);
        showNotification('Errore durante l\'eliminazione: ' + error.message, 'error');
    }
}

/**
 * Attiva/Disattiva un template
 */
async function toggleTemplateStatus(templateId, action) {
    const actionText = action === 'activate' ? 'attivare' : 'disattivare';
    
    if (!confirm(`Sei sicuro di voler ${actionText} questo template?`)) {
        return;
    }
    
    try {
        // Crea form e invia via POST
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        // Aggiungi campi
        const actionInput = document.createElement('input');
        actionInput.name = 'quick_action';
        actionInput.value = action;
        form.appendChild(actionInput);
        
        const idInput = document.createElement('input');
        idInput.name = 'template_id';
        idInput.value = templateId;
        form.appendChild(idInput);
        
        // Aggiungi al DOM e invia
        document.body.appendChild(form);
        form.submit();
        
    } catch (error) {
        console.error('Errore toggle template:', error);
        showNotification('Errore durante l\'operazione: ' + error.message, 'error');
    }
}

/**
 * Toggle visualizzazione template inattivi
 */
function toggleInactiveView(showInactive) {
    const url = new URL(window.location);
    if (showInactive) {
        url.searchParams.set('show_inactive', '1');
    } else {
        url.searchParams.delete('show_inactive');
    }
    window.location.href = url.toString();
}

/**
 * Attiva tutti i template inattivi
 */
function activateAllTemplates() {
    if (!confirm('Sei sicuro di voler attivare TUTTI i template inattivi?')) {
        return;
    }
    
    try {
        // Crea form e invia via POST
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        // Aggiungi campo azione
        const actionInput = document.createElement('input');
        actionInput.name = 'quick_action';
        actionInput.value = 'activate_all';
        form.appendChild(actionInput);
        
        // Aggiungi template_id dummy (richiesto dal codice)
        const idInput = document.createElement('input');
        idInput.name = 'template_id';
        idInput.value = '1';
        form.appendChild(idInput);
        
        // Aggiungi al DOM e invia
        document.body.appendChild(form);
        form.submit();
        
    } catch (error) {
        console.error('Errore attivazione template:', error);
        alert('Errore durante l\'attivazione: ' + error.message);
    }
}

/**
 * Valida configurazione template prima del salvataggio
 */
async function validateTemplate() {
    const form = document.getElementById('templateForm');
    const formData = new FormData(form);
    
    const templateData = {};
    formData.forEach((value, key) => {
        templateData[key] = value;
    });
    
    // Aggiungi configurazioni
    const headerConfig = document.getElementById('headerConfigData').value;
    const footerConfig = document.getElementById('footerConfigData').value;
    
    templateData.intestazione_config = headerConfig;
    templateData.pie_pagina_config = footerConfig;
    
    try {
        const response = await fetch('/piattaforma-collaborativa/backend/api/template-dragdrop-api.php?action=validate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(templateData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            const validation = result.data;
            
            if (!validation.valid) {
                let errorMessage = 'Errori di validazione:\n' + validation.errors.join('\n');
                alert(errorMessage);
                return false;
            }
            
            if (validation.warnings.length > 0) {
                let warningMessage = 'Avvisi:\n' + validation.warnings.join('\n') + '\n\nContinuare comunque?';
                return confirm(warningMessage);
            }
            
            return true;
        } else {
            throw new Error(result.message || 'Errore durante la validazione');
        }
        
    } catch (error) {
        console.error('Errore validazione template:', error);
        showNotification('Errore durante la validazione: ' + error.message, 'error');
        return false;
    }
}