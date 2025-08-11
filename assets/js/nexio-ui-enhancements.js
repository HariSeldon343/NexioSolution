/**
 * Nexio UI Enhancements
 * JavaScript enhancements for improved user experience
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // ============================================
    // 1. LOG ATTIVITÀ - EXPANDABLE ROWS
    // ============================================
    
    // Make log rows expandable
    const logRows = document.querySelectorAll('.log-row, .activity-log-row, .log-table tbody tr');
    logRows.forEach(row => {
        // Skip header rows
        if (row.parentElement.tagName === 'THEAD') return;
        
        // Add click handler
        row.style.cursor = 'pointer';
        row.addEventListener('click', function(e) {
            // Don't expand if clicking on a link or button
            if (e.target.tagName === 'A' || e.target.tagName === 'BUTTON' || e.target.closest('a') || e.target.closest('button')) {
                return;
            }
            
            // Check if details row already exists
            let detailsRow = row.nextElementSibling;
            if (detailsRow && detailsRow.classList.contains('log-details-row')) {
                // Toggle visibility
                detailsRow.classList.toggle('expanded');
                row.classList.toggle('expanded');
            } else {
                // Create details row
                const cells = row.cells;
                const colspan = cells.length;
                
                detailsRow = document.createElement('tr');
                detailsRow.classList.add('log-details-row', 'expanded');
                
                const detailsCell = document.createElement('td');
                detailsCell.colSpan = colspan;
                detailsCell.innerHTML = `
                    <div class="log-details expanded">
                        <div class="log-details-content">
                            ${extractLogDetails(row)}
                        </div>
                    </div>
                `;
                
                detailsRow.appendChild(detailsCell);
                row.parentNode.insertBefore(detailsRow, row.nextSibling);
                row.classList.add('expanded');
            }
        });
    });
    
    // Extract detailed information from log row
    function extractLogDetails(row) {
        const cells = row.cells;
        let details = '<div class="log-details-grid">';
        
        // Extract all cell data with labels
        const labels = ['Data/Ora', 'Utente', 'Tipo', 'Azione', 'Dettagli', 'Azienda'];
        for (let i = 0; i < cells.length && i < labels.length; i++) {
            const cellContent = cells[i].innerHTML;
            details += `
                <div class="log-details-item">
                    <span class="log-details-label">${labels[i]}:</span>
                    <span class="log-details-value">${cellContent}</span>
                </div>
            `;
        }
        
        // Add additional metadata if available
        const ipMatch = row.innerHTML.match(/IP:\s*([\d.]+)/);
        if (ipMatch) {
            details += `
                <div class="log-details-item">
                    <span class="log-details-label">IP Address:</span>
                    <span class="log-details-value">${ipMatch[1]}</span>
                </div>
            `;
        }
        
        details += '</div>';
        return details;
    }
    
    // ============================================
    // 2. TICKETS - DELETE CLOSED TICKETS
    // ============================================
    
    // Add delete buttons for closed tickets (super admin only)
    if (document.body.classList.contains('super-admin') || document.querySelector('.user-role')?.textContent?.includes('Super Admin')) {
        const ticketRows = document.querySelectorAll('.tickets-table tbody tr, #ticketsTable tbody tr');
        ticketRows.forEach(row => {
            const statusCell = row.querySelector('.status-chiuso, .ticket-status-chiuso, .badge-chiuso');
            if (statusCell) {
                const actionsCell = row.querySelector('td:last-child');
                if (actionsCell && !actionsCell.querySelector('.ticket-delete-btn')) {
                    const deleteBtn = document.createElement('button');
                    deleteBtn.className = 'btn btn-sm btn-danger ticket-delete-btn';
                    deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
                    deleteBtn.title = 'Elimina ticket chiuso';
                    deleteBtn.onclick = function(e) {
                        e.stopPropagation();
                        const ticketId = row.dataset.ticketId || row.querySelector('[data-ticket-id]')?.dataset.ticketId;
                        if (ticketId && confirm('Sei sicuro di voler eliminare questo ticket chiuso? L\'azione verrà registrata nel log.')) {
                            deleteClosedTicket(ticketId, row);
                        }
                    };
                    actionsCell.appendChild(deleteBtn);
                }
            }
        });
    }
    
    // Delete closed ticket function
    function deleteClosedTicket(ticketId, row) {
        fetch(`${window.APP_PATH || ''}/backend/api/delete-ticket.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify({ ticket_id: ticketId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Fade out and remove row
                row.style.transition = 'opacity 0.3s';
                row.style.opacity = '0';
                setTimeout(() => row.remove(), 300);
                
                // Show success message
                showNotification('Ticket eliminato con successo', 'success');
            } else {
                showNotification(data.message || 'Errore durante l\'eliminazione', 'error');
            }
        })
        .catch(error => {
            console.error('Error deleting ticket:', error);
            showNotification('Errore di connessione', 'error');
        });
    }
    
    // ============================================
    // 3. MOVE DELETE LOGS BUTTON
    // ============================================
    
    const deleteLogsBtn = document.querySelector('#deleteLogsBtn, .btn-delete-logs');
    const filterActions = document.querySelector('.filter-actions, .log-filters form > div:last-child');
    
    if (deleteLogsBtn && filterActions) {
        // Move button to filter actions row
        deleteLogsBtn.style.marginLeft = 'auto';
        filterActions.appendChild(deleteLogsBtn);
    }
    
    // ============================================
    // 4. AZIENDE CARDS OPTIMIZATION
    // ============================================
    
    const aziendeContainer = document.querySelector('.aziende-grid, .companies-grid, .row.g-3');
    if (aziendeContainer && window.location.pathname.includes('aziende')) {
        aziendeContainer.style.display = 'grid';
        aziendeContainer.style.gridTemplateColumns = 'repeat(auto-fill, minmax(300px, 1fr))';
        aziendeContainer.style.gap = '1rem';
        
        // Optimize card sizes
        const cards = aziendeContainer.querySelectorAll('.card');
        cards.forEach(card => {
            card.style.maxWidth = '350px';
            card.style.margin = '0';
            
            const cardBody = card.querySelector('.card-body');
            if (cardBody) {
                cardBody.style.padding = '1rem';
            }
        });
    }
    
    // ============================================
    // 5. NOTIFICATION HELPER
    // ============================================
    
    function showNotification(message, type = 'info') {
        // Remove existing notifications
        const existingNotifications = document.querySelectorAll('.nexio-notification');
        existingNotifications.forEach(n => n.remove());
        
        // Create notification
        const notification = document.createElement('div');
        notification.className = `nexio-notification nexio-notification-${type}`;
        notification.innerHTML = `
            <div class="nexio-notification-content">
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `;
        
        // Style the notification
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 4px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            animation: slideIn 0.3s ease-out;
            max-width: 400px;
        `;
        
        document.body.appendChild(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }
    
    // ============================================
    // 6. ADD ANIMATION STYLES
    // ============================================
    
    if (!document.querySelector('#nexio-ui-animations')) {
        const style = document.createElement('style');
        style.id = 'nexio-ui-animations';
        style.textContent = `
            @keyframes slideIn {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            @keyframes slideOut {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(100%);
                    opacity: 0;
                }
            }
            
            .log-details-row {
                background-color: #f9fafb;
            }
            
            .log-details-row.expanded .log-details {
                display: block !important;
                animation: fadeIn 0.3s ease-out;
            }
            
            .log-details-row:not(.expanded) {
                display: none;
            }
            
            tr.expanded {
                background-color: #f3f4f6 !important;
            }
            
            .log-details-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 1rem;
                padding: 1rem;
            }
            
            .nexio-notification-content {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
            }
            
            .nexio-notification-content button {
                background: none;
                border: none;
                color: white;
                font-size: 1.5rem;
                cursor: pointer;
                padding: 0;
                width: 24px;
                height: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
        `;
        document.head.appendChild(style);
    }
    
    // ============================================
    // 7. USER INFO MULTI-LINE FIX
    // ============================================
    
    const userInfoElements = document.querySelectorAll('.user-info, .header-user-info, #user-info, .user-name');
    userInfoElements.forEach(elem => {
        elem.style.whiteSpace = 'normal';
        elem.style.wordWrap = 'break-word';
        elem.style.lineHeight = '1.3';
        if (elem.classList.contains('user-name')) {
            elem.style.maxWidth = '150px';
        }
    });
    
});

// Export for global use
window.NexioUI = {
    showNotification: function(message, type) {
        // Implementation from above
    }
};