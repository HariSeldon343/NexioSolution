/**
 * Ticket Enhancements
 * Funzionalità avanzate per i ticket
 */

document.addEventListener('DOMContentLoaded', function() {
    // Gestione eliminazione ticket (solo per super admin)
    initTicketDeletion();
    
    // Gestione log espandibili
    initExpandableLogs();
});

/**
 * Inizializza la funzionalità di eliminazione ticket
 */
function initTicketDeletion() {
    const deleteButtons = document.querySelectorAll('.delete-ticket-btn');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const ticketId = this.dataset.ticketId;
            const ticketCode = this.dataset.ticketCode;
            const ticketStatus = this.dataset.ticketStatus;
            
            // Verifica che il ticket sia chiuso
            if (ticketStatus !== 'chiuso') {
                alert('Solo i ticket chiusi possono essere eliminati.');
                return;
            }
            
            // Conferma eliminazione
            if (!confirm(`Sei sicuro di voler eliminare il ticket ${ticketCode}?\n\nQuesta azione non può essere annullata.`)) {
                return;
            }
            
            // Seconda conferma per sicurezza
            if (!confirm(`ATTENZIONE: Stai per eliminare permanentemente il ticket ${ticketCode}.\n\nTutte le risposte e i dati associati verranno eliminati.\n\nConfermi di voler procedere?`)) {
                return;
            }
            
            // Mostra spinner
            const originalHtml = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Eliminazione...';
            this.disabled = true;
            
            // Ottieni il CSRF token
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            
            // Invia richiesta di eliminazione
            fetch('/piattaforma-collaborativa/backend/api/delete-ticket.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': csrfToken
                },
                body: `ticket_id=${ticketId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Mostra messaggio di successo
                    alert(data.message);
                    
                    // Rimuovi la riga dalla tabella o reindirizza
                    const row = this.closest('tr');
                    if (row) {
                        row.style.transition = 'opacity 0.5s';
                        row.style.opacity = '0';
                        setTimeout(() => {
                            row.remove();
                            
                            // Se non ci sono più ticket, mostra messaggio vuoto
                            const tbody = document.querySelector('.tickets-table tbody');
                            if (tbody && tbody.querySelectorAll('tr').length === 0) {
                                tbody.innerHTML = `
                                    <tr>
                                        <td colspan="9">
                                            <div class="empty-state">
                                                <i class="fas fa-inbox"></i>
                                                <h3>Nessun ticket trovato</h3>
                                                <p>Non ci sono ticket da visualizzare al momento.</p>
                                                <a href="tickets.php?action=nuovo" class="btn btn-primary">
                                                    <i class="fas fa-plus"></i> Crea un nuovo ticket
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                `;
                            }
                        }, 500);
                    } else {
                        // Se siamo nella vista dettaglio, reindirizza alla lista
                        window.location.href = '/piattaforma-collaborativa/tickets.php?deleted=1';
                    }
                } else {
                    alert('Errore: ' + (data.error || 'Impossibile eliminare il ticket'));
                    // Ripristina il bottone
                    this.innerHTML = originalHtml;
                    this.disabled = false;
                }
            })
            .catch(error => {
                console.error('Errore:', error);
                alert('Errore di rete durante l\'eliminazione del ticket');
                // Ripristina il bottone
                this.innerHTML = originalHtml;
                this.disabled = false;
            });
        });
    });
}

/**
 * Inizializza i log espandibili
 */
function initExpandableLogs() {
    // Skip initialization on log-attivita page
    if (document.body.classList.contains('log-attivita-page')) {
        console.log('Skipping expandable logs for log-attivita page');
        return;
    }
    
    const logItems = document.querySelectorAll('.log-item');
    
    logItems.forEach(item => {
        // Aggiungi indicatore di espansione
        const indicator = document.createElement('span');
        indicator.className = 'expand-indicator';
        indicator.innerHTML = '<i class="fas fa-chevron-right"></i>';
        indicator.style.marginRight = '10px';
        indicator.style.transition = 'transform 0.3s';
        
        // Inserisci l'indicatore all'inizio dell'elemento
        if (item.firstChild) {
            item.insertBefore(indicator, item.firstChild);
        }
        
        // Rendi l'elemento cliccabile
        item.style.cursor = 'pointer';
        item.style.userSelect = 'none';
        
        item.addEventListener('click', function(e) {
            // Previeni click sui link interni
            if (e.target.tagName === 'A' || e.target.tagName === 'BUTTON') {
                return;
            }
            
            e.preventDefault();
            
            // Trova o crea il contenitore dei dettagli
            let details = this.nextElementSibling;
            if (!details || !details.classList.contains('log-details')) {
                // Crea il contenitore dei dettagli
                details = createLogDetails(this);
                this.parentNode.insertBefore(details, this.nextSibling);
            }
            
            // Toggle espansione
            const isExpanded = details.classList.contains('expanded');
            
            if (isExpanded) {
                // Chiudi
                details.classList.remove('expanded');
                details.style.maxHeight = '0';
                details.style.opacity = '0';
                indicator.style.transform = 'rotate(0deg)';
            } else {
                // Apri
                details.classList.add('expanded');
                details.style.maxHeight = details.scrollHeight + 'px';
                details.style.opacity = '1';
                indicator.style.transform = 'rotate(90deg)';
            }
        });
    });
}

/**
 * Crea il contenitore dei dettagli del log
 */
function createLogDetails(logItem) {
    const details = document.createElement('div');
    details.className = 'log-details';
    details.style.overflow = 'hidden';
    details.style.maxHeight = '0';
    details.style.opacity = '0';
    details.style.transition = 'max-height 0.5s ease, opacity 0.3s ease';
    
    // Estrai i dati dal log item
    const logData = extractLogData(logItem);
    
    // Crea il contenuto dettagliato
    details.innerHTML = `
        <div class="log-details-content">
            <div class="log-detail-section">
                <h5>Informazioni Dettagliate</h5>
                <table class="log-info-table">
                    <tr>
                        <td><strong>Data/Ora:</strong></td>
                        <td>${logData.datetime || 'N/D'}</td>
                    </tr>
                    <tr>
                        <td><strong>Utente:</strong></td>
                        <td>${logData.user || 'N/D'}</td>
                    </tr>
                    <tr>
                        <td><strong>Azione:</strong></td>
                        <td>${logData.action || 'N/D'}</td>
                    </tr>
                    <tr>
                        <td><strong>Descrizione:</strong></td>
                        <td>${logData.description || 'N/D'}</td>
                    </tr>
                    ${logData.ipAddress ? `
                    <tr>
                        <td><strong>Indirizzo IP:</strong></td>
                        <td>${logData.ipAddress}</td>
                    </tr>
                    ` : ''}
                    ${logData.userAgent ? `
                    <tr>
                        <td><strong>User Agent:</strong></td>
                        <td style="word-break: break-all;">${logData.userAgent}</td>
                    </tr>
                    ` : ''}
                </table>
            </div>
            
            ${logData.additionalData ? `
            <div class="log-detail-section">
                <h5>Dati Aggiuntivi</h5>
                <pre class="log-json-data">${JSON.stringify(JSON.parse(logData.additionalData), null, 2)}</pre>
            </div>
            ` : ''}
            
            <div class="log-actions">
                <button class="btn btn-sm btn-primary log-download-btn" onclick="downloadLogEntry('${logData.id}')">
                    <i class="fas fa-download"></i> Scarica Dettagli
                </button>
                <button class="btn btn-sm btn-secondary" onclick="copyLogToClipboard('${logData.id}')">
                    <i class="fas fa-copy"></i> Copia
                </button>
            </div>
        </div>
    `;
    
    return details;
}

/**
 * Estrae i dati dal log item
 */
function extractLogData(logItem) {
    // Estrai i dati dagli attributi data-* o dal contenuto HTML
    return {
        id: logItem.dataset.logId || Math.random().toString(36).substr(2, 9),
        datetime: logItem.dataset.datetime || logItem.querySelector('.log-date')?.textContent || '',
        user: logItem.dataset.user || logItem.querySelector('.log-user')?.textContent || '',
        action: logItem.dataset.action || logItem.querySelector('.log-action')?.textContent || '',
        description: logItem.dataset.description || logItem.querySelector('.log-description')?.textContent || '',
        ipAddress: logItem.dataset.ipAddress || '',
        userAgent: logItem.dataset.userAgent || '',
        additionalData: logItem.dataset.additionalData || ''
    };
}

/**
 * Scarica i dettagli del log
 */
function downloadLogEntry(logId) {
    const logItem = document.querySelector(`[data-log-id="${logId}"]`);
    if (!logItem) return;
    
    const logData = extractLogData(logItem);
    
    // Crea il contenuto del file
    const content = `
Log Entry Details
=================
ID: ${logData.id}
Date/Time: ${logData.datetime}
User: ${logData.user}
Action: ${logData.action}
Description: ${logData.description}
IP Address: ${logData.ipAddress || 'N/A'}
User Agent: ${logData.userAgent || 'N/A'}

Additional Data:
${logData.additionalData ? JSON.stringify(JSON.parse(logData.additionalData), null, 2) : 'N/A'}
    `.trim();
    
    // Crea e scarica il file
    const blob = new Blob([content], { type: 'text/plain' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `log_${logData.id}_${new Date().toISOString().split('T')[0]}.txt`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

/**
 * Copia i dettagli del log negli appunti
 */
function copyLogToClipboard(logId) {
    const logItem = document.querySelector(`[data-log-id="${logId}"]`);
    if (!logItem) return;
    
    const logData = extractLogData(logItem);
    
    const content = `
Log Entry: ${logData.id}
Date/Time: ${logData.datetime}
User: ${logData.user}
Action: ${logData.action}
Description: ${logData.description}
    `.trim();
    
    // Copia negli appunti
    if (navigator.clipboard) {
        navigator.clipboard.writeText(content).then(() => {
            alert('Dettagli del log copiati negli appunti');
        }).catch(err => {
            console.error('Errore nella copia:', err);
            fallbackCopyToClipboard(content);
        });
    } else {
        fallbackCopyToClipboard(content);
    }
}

/**
 * Fallback per la copia negli appunti
 */
function fallbackCopyToClipboard(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    try {
        document.execCommand('copy');
        alert('Dettagli del log copiati negli appunti');
    } catch (err) {
        alert('Impossibile copiare negli appunti');
    }
    document.body.removeChild(textarea);
}

// Export per uso globale
window.ticketEnhancements = {
    initTicketDeletion,
    initExpandableLogs,
    downloadLogEntry,
    copyLogToClipboard
};