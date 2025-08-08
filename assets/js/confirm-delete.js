/**
 * Sistema di conferma cancellazione globale per la piattaforma
 * Fornisce un modal di conferma riutilizzabile per tutte le cancellazioni
 */

class ConfirmDelete {
    constructor() {
        this.createModal();
    }

    createModal() {
        // Crea il modal HTML se non esiste già
        if (document.getElementById('confirmDeleteModal')) {
            return;
        }

        const modalHTML = `
            <div id="confirmDeleteModal" class="modal" style="display: none;">
                <div class="modal-content" style="max-width: 500px;">
                    <div class="modal-header">
                        <h2><i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i> Conferma Eliminazione</h2>
                        <button class="close-modal" onclick="confirmDelete.close()">×</button>
                    </div>
                    <div class="modal-body">
                        <p id="confirmDeleteMessage" style="font-size: 16px; margin-bottom: 20px;">
                            Sei sicuro di voler eliminare questo elemento?
                        </p>
                        <div class="alert alert-warning" style="background: #fef3c7; border: 1px solid #fbbf24; color: #92400e;">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Attenzione:</strong> Questa azione non può essere annullata.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="confirmDelete.close()">
                            <i class="fas fa-times"></i> Annulla
                        </button>
                        <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                            <i class="fas fa-trash"></i> Elimina
                        </button>
                    </div>
                </div>
            </div>
        `;

        // Aggiungi il modal al body
        document.body.insertAdjacentHTML('beforeend', modalHTML);

        // Aggiungi stili CSS se non esistono
        if (!document.getElementById('confirmDeleteStyles')) {
            const styles = `
                <style id="confirmDeleteStyles">
                    .modal {
                        position: fixed;
                        z-index: 9999;
                        left: 0;
                        top: 0;
                        width: 100%;
                        height: 100%;
                        background-color: rgba(0,0,0,0.5);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        animation: fadeIn 0.2s ease-out;
                    }

                    @keyframes fadeIn {
                        from { opacity: 0; }
                        to { opacity: 1; }
                    }

                    .modal-content {
                        background: white;
                        border-radius: 12px;
                        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                        width: 90%;
                        max-height: 90vh;
                        display: flex;
                        flex-direction: column;
                        animation: slideIn 0.3s ease-out;
                    }

                    @keyframes slideIn {
                        from { transform: translateY(-30px); opacity: 0; }
                        to { transform: translateY(0); opacity: 1; }
                    }

                    .modal-header {
                        padding: 20px;
                        border-bottom: 1px solid #e2e8f0;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                    }

                    .modal-header h2 {
                        margin: 0;
                        color: #2d3748;
                        font-size: 20px;
                        display: flex;
                        align-items: center;
                        gap: 10px;
                    }

                    .close-modal {
                        background: none;
                        border: none;
                        font-size: 28px;
                        cursor: pointer;
                        color: #718096;
                        padding: 0;
                        width: 32px;
                        height: 32px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        border-radius: 6px;
                        transition: all 0.2s;
                    }

                    .close-modal:hover {
                        background: #f7fafc;
                        color: #2d3748;
                    }

                    .modal-body {
                        padding: 20px;
                        overflow-y: auto;
                        flex: 1;
                    }

                    .modal-footer {
                        padding: 20px;
                        border-top: 1px solid #e2e8f0;
                        display: flex;
                        justify-content: flex-end;
                        gap: 10px;
                    }

                    .btn-danger {
                        background: #ef4444;
                        color: white;
                        border: none;
                        padding: 10px 20px;
                        border-radius: 6px;
                        cursor: pointer;
                        font-size: 14px;
                        display: flex;
                        align-items: center;
                        gap: 8px;
                        transition: all 0.2s;
                    }

                    .btn-danger:hover {
                        background: #dc2626;
                        transform: translateY(-1px);
                        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
                    }

                    .alert-warning {
                        padding: 12px 16px;
                        border-radius: 8px;
                        font-size: 14px;
                        display: flex;
                        align-items: flex-start;
                        gap: 10px;
                    }
                </style>
            `;
            document.head.insertAdjacentHTML('beforeend', styles);
        }
    }

    /**
     * Mostra il modal di conferma
     * @param {Object} options - Opzioni per la conferma
     * @param {string} options.message - Messaggio da mostrare
     * @param {string} options.itemType - Tipo di elemento (es. "azienda", "utente", "documento")
     * @param {string} options.itemName - Nome dell'elemento da cancellare
     * @param {Function} options.onConfirm - Callback da eseguire alla conferma
     */
    show(options) {
        const modal = document.getElementById('confirmDeleteModal');
        const messageEl = document.getElementById('confirmDeleteMessage');
        const confirmBtn = document.getElementById('confirmDeleteBtn');

        // Costruisci il messaggio
        let message = options.message || 'Sei sicuro di voler eliminare questo elemento?';
        
        if (options.itemType && options.itemName) {
            const itemTypeText = this.getItemTypeText(options.itemType);
            message = `Sei sicuro di voler eliminare ${itemTypeText} "<strong>${this.escapeHtml(options.itemName)}</strong>"?`;
        }

        messageEl.innerHTML = message;

        // Rimuovi event listener precedenti
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

        // Aggiungi nuovo event listener
        newConfirmBtn.addEventListener('click', () => {
            if (options.onConfirm && typeof options.onConfirm === 'function') {
                options.onConfirm();
            }
            this.close();
        });

        // Mostra il modal
        modal.style.display = 'flex';

        // Chiudi con ESC
        this.escHandler = (e) => {
            if (e.key === 'Escape') {
                this.close();
            }
        };
        document.addEventListener('keydown', this.escHandler);
    }

    close() {
        const modal = document.getElementById('confirmDeleteModal');
        if (modal) {
            modal.style.display = 'none';
        }
        
        // Rimuovi event listener ESC
        if (this.escHandler) {
            document.removeEventListener('keydown', this.escHandler);
        }
    }

    getItemTypeText(itemType) {
        const types = {
            'azienda': "l'azienda",
            'utente': "l'utente",
            'documento': "il documento",
            'cartella': "la cartella",
            'evento': "l'evento",
            'ticket': "il ticket",
            'referente': "il referente",
            'template': "il template"
        };
        return types[itemType] || "l'elemento";
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Inizializza il sistema di conferma cancellazione (solo se non esiste già)
if (typeof window.confirmDelete === 'undefined') {
    window.confirmDelete = new ConfirmDelete();
    console.log('ConfirmDelete inizializzato correttamente');
} else {
    console.warn('ConfirmDelete già inizializzato, evito duplicazione');
    // Assicurati che sia la versione corrente
    if (typeof window.confirmDelete.show !== 'function') {
        console.warn('ConfirmDelete esistente non valido, reinizializzo');
        window.confirmDelete = new ConfirmDelete();
    }
}

// Per retrocompatibilità, mantieni anche il riferimento globale senza window
if (typeof confirmDelete === 'undefined') {
    const confirmDelete = window.confirmDelete;
}

// Funzione helper globale per retrocompatibilità e facilità d'uso
if (typeof window.confirmDeleteAction === 'undefined') {
    window.confirmDeleteAction = function(options) {
        (window.confirmDelete || confirmDelete).show(options);
    };
}