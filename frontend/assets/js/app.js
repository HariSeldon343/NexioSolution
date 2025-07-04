/**
 * Piattaforma Collaborativa - JavaScript principale
 * Gestisce interazioni UI, menu mobile, notifiche e funzionalità responsive
 */

// Inizializzazione quando il DOM è pronto
document.addEventListener('DOMContentLoaded', function() {
    initMobileMenu();
    initNotifications();
    initDataTables();
    initModals();
    initFormValidation();
    initTooltips();
    initCharts();
    initDatePickers();
    initFileUploads();
    initAutoSave();
});

// Menu Mobile
function initMobileMenu() {
    const menuBtn = document.querySelector('.mobile-menu-btn');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (menuBtn) {
        menuBtn.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            document.body.classList.toggle('menu-open');
        });
    }
    
    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            document.body.classList.remove('menu-open');
        });
    }
    
    // Chiudi menu su resize a desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 768) {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            document.body.classList.remove('menu-open');
        }
    });
}

// Sistema Notifiche in tempo reale
function initNotifications() {
    // Controlla nuove notifiche ogni 30 secondi
    if (typeof CHECK_NOTIFICATIONS !== 'undefined' && CHECK_NOTIFICATIONS) {
        setInterval(checkNewNotifications, 30000);
    }
    
    // Gestisci click su notifiche
    document.addEventListener('click', function(e) {
        if (e.target.matches('.notification-item')) {
            markNotificationAsRead(e.target.dataset.id);
        }
    });
}

function checkNewNotifications() {
    fetch('/piattaforma-collaborativa/backend/api/notifiche.php?action=check')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.count > 0) {
                updateNotificationBadge(data.count);
                showNotificationToast(data.latest);
            }
        })
        .catch(console.error);
}

// Tabelle Responsive
function initDataTables() {
    const tables = document.querySelectorAll('.data-table');
    
    tables.forEach(table => {
        // Aggiungi wrapper per scroll orizzontale
        if (!table.parentElement.classList.contains('table-responsive')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'table-responsive';
            table.parentNode.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        }
        
        // Aggiungi data-label per vista mobile
        const headers = table.querySelectorAll('thead th');
        const rows = table.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            cells.forEach((cell, index) => {
                if (headers[index]) {
                    cell.setAttribute('data-label', headers[index].textContent);
                }
            });
        });
        
        // Ricerca nella tabella
        addTableSearch(table);
    });
}

// Sistema Modal
function initModals() {
    // Apri modal
    document.addEventListener('click', function(e) {
        if (e.target.matches('[data-modal]')) {
            e.preventDefault();
            const modalId = e.target.dataset.modal;
            openModal(modalId);
        }
        
        // Chiudi modal
        if (e.target.matches('.modal-close, .modal-overlay')) {
            closeModal(e.target.closest('.modal'));
        }
    });
    
    // Chiudi con ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const openModal = document.querySelector('.modal.active');
            if (openModal) closeModal(openModal);
        }
    });
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.classList.add('modal-open');
        
        // Focus primo elemento
        const firstInput = modal.querySelector('input, textarea, select');
        if (firstInput) firstInput.focus();
    }
}

function closeModal(modal) {
    if (modal) {
        modal.classList.remove('active');
        document.body.classList.remove('modal-open');
    }
}

// Validazione Form
function initFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
                
                // Mostra errori
                const firstInvalid = form.querySelector(':invalid');
                if (firstInvalid) {
                    firstInvalid.focus();
                    showFieldError(firstInvalid);
                }
            }
            
            form.classList.add('was-validated');
        });
        
        // Validazione in tempo reale
        const inputs = form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });
        });
    });
}

function validateField(field) {
    const isValid = field.checkValidity();
    const errorElement = field.parentElement.querySelector('.invalid-feedback');
    
    if (!isValid && errorElement) {
        errorElement.style.display = 'block';
    } else if (errorElement) {
        errorElement.style.display = 'none';
    }
    
    // Validazioni custom
    if (field.type === 'email') {
        validateEmail(field);
    } else if (field.type === 'tel') {
        validatePhone(field);
    } else if (field.dataset.match) {
        validateMatch(field);
    }
}

// Tooltips
function initTooltips() {
    const tooltips = document.querySelectorAll('[data-tooltip]');
    
    tooltips.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
        element.addEventListener('focus', showTooltip);
        element.addEventListener('blur', hideTooltip);
    });
}

function showTooltip(e) {
    const text = e.target.dataset.tooltip;
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = text;
    
    document.body.appendChild(tooltip);
    
    const rect = e.target.getBoundingClientRect();
    tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
    tooltip.style.left = rect.left + (rect.width - tooltip.offsetWidth) / 2 + 'px';
}

// Grafici Dashboard
function initCharts() {
    // Grafico documenti per categoria
    const docsChart = document.getElementById('documentsChart');
    if (docsChart && typeof Chart !== 'undefined') {
        const ctx = docsChart.getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Manuali', 'Procedure', 'Moduli', 'Dashboard'],
                datasets: [{
                    data: docsChart.dataset.values?.split(',') || [0,0,0,0],
                    backgroundColor: [
                        '#6366f1',
                        '#8b5cf6',
                        '#ec4899',
                        '#f59e0b'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
}

// Date Picker
function initDatePickers() {
    const dateInputs = document.querySelectorAll('input[type="date"]');
    
    // Aggiungi icona calendario per browser che non supportano nativamente
    dateInputs.forEach(input => {
        if (!isDateInputSupported()) {
            addDatePickerFallback(input);
        }
        
        // Formatta date in formato italiano
        input.addEventListener('change', function() {
            if (this.value) {
                const date = new Date(this.value);
                const formatted = formatDateItalian(date);
                
                // Mostra data formattata vicino all'input
                let display = this.parentElement.querySelector('.date-display');
                if (!display) {
                    display = document.createElement('span');
                    display.className = 'date-display';
                    this.parentElement.appendChild(display);
                }
                display.textContent = formatted;
            }
        });
    });
}

// Upload File con Drag & Drop
function initFileUploads() {
    const uploadZones = document.querySelectorAll('.file-upload-zone');
    
    uploadZones.forEach(zone => {
        // Previeni comportamento default
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            zone.addEventListener(eventName, preventDefaults, false);
        });
        
        // Highlight durante drag
        ['dragenter', 'dragover'].forEach(eventName => {
            zone.addEventListener(eventName, () => zone.classList.add('drag-over'), false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            zone.addEventListener(eventName, () => zone.classList.remove('drag-over'), false);
        });
        
        // Gestisci drop
        zone.addEventListener('drop', handleDrop, false);
        
        // Click per selezionare file
        zone.addEventListener('click', () => {
            const input = zone.querySelector('input[type="file"]');
            if (input) input.click();
        });
    });
}

function handleDrop(e) {
    const files = e.dataTransfer.files;
    const zone = e.currentTarget;
    const input = zone.querySelector('input[type="file"]');
    
    if (input && files.length > 0) {
        // Simula selezione file nell'input
        const dataTransfer = new DataTransfer();
        Array.from(files).forEach(file => dataTransfer.items.add(file));
        input.files = dataTransfer.files;
        
        // Trigger change event
        input.dispatchEvent(new Event('change', { bubbles: true }));
        
        // Mostra preview
        showFilePreview(zone, files);
    }
}

// Auto-salvataggio bozze
function initAutoSave() {
    const forms = document.querySelectorAll('[data-autosave]');
    
    forms.forEach(form => {
        const formId = form.dataset.autosave;
        const inputs = form.querySelectorAll('input, textarea, select');
        
        // Ripristina dati salvati
        restoreFormData(formId, form);
        
        // Salva su cambiamento
        inputs.forEach(input => {
            input.addEventListener('input', debounce(() => {
                saveFormData(formId, form);
                showAutoSaveIndicator();
            }, 1000));
        });
    });
}

// Utility Functions
function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function formatDateItalian(date) {
    const options = { day: 'numeric', month: 'long', year: 'numeric' };
    return date.toLocaleDateString('it-IT', options);
}

function isDateInputSupported() {
    const input = document.createElement('input');
    input.setAttribute('type', 'date');
    return input.type === 'date';
}

function showNotificationToast(message) {
    const toast = document.createElement('div');
    toast.className = 'notification-toast fade-in';
    toast.innerHTML = `
        <div class="toast-content">
            <i class="fas fa-info-circle"></i>
            <span>${message}</span>
            <button class="toast-close">&times;</button>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    // Auto-rimuovi dopo 5 secondi
    setTimeout(() => {
        toast.classList.add('fade-out');
        setTimeout(() => toast.remove(), 300);
    }, 5000);
    
    // Chiudi manualmente
    toast.querySelector('.toast-close').addEventListener('click', () => {
        toast.remove();
    });
}

// Gestione errori globale
window.addEventListener('error', function(e) {
    console.error('Errore JavaScript:', e.error);
    
    // In produzione, invia errori al server
    if (typeof PRODUCTION !== 'undefined' && PRODUCTION) {
        logErrorToServer({
            message: e.error.message,
            stack: e.error.stack,
            url: e.filename,
            line: e.lineno,
            column: e.colno
        });
    }
});

// Conferme azioni pericolose
window.confirmDelete = function(message) {
    return confirm(message || 'Sei sicuro di voler eliminare questo elemento? Questa azione non può essere annullata.');
};

window.confirmAction = function(message) {
    return confirm(message || 'Sei sicuro di voler procedere?');
}; 