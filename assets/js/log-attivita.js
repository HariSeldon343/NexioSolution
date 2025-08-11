/**
 * Log Attività Page - Clean Table JavaScript
 * Ensures proper table behavior without expandable rows
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize log attività page functionality
    initLogAttivita();
});

/**
 * Initialize log attività page
 */
function initLogAttivita() {
    // Remove any inline styles that might have been added
    cleanupInlineStyles();
    
    // Initialize filter form enhancements
    initFilterEnhancements();
    
    // Initialize delete functionality (for super admins)
    initDeleteFunctionality();
    
    // Add table enhancements
    enhanceTable();
}

/**
 * Remove problematic inline styles
 */
function cleanupInlineStyles() {
    // Remove inline styles from table rows
    const tableRows = document.querySelectorAll('.log-table tr');
    tableRows.forEach(row => {
        row.style.removeProperty('cursor');
        row.style.removeProperty('user-select');
    });
    
    // Remove any expand indicators that might have been added
    const expandIndicators = document.querySelectorAll('.expand-indicator');
    expandIndicators.forEach(indicator => {
        indicator.remove();
    });
    
    // Remove any log-details elements
    const logDetails = document.querySelectorAll('.log-details');
    logDetails.forEach(detail => {
        detail.remove();
    });
    
    // Remove data attributes that are not needed
    const logItems = document.querySelectorAll('.log-item');
    logItems.forEach(item => {
        // Remove the log-item class to prevent any JS hooks
        item.classList.remove('log-item');
        
        // Remove unnecessary data attributes
        const dataAttrs = ['data-log-id', 'data-datetime', 'data-user', 
                          'data-action', 'data-description', 'data-ip-address', 
                          'data-user-agent', 'data-additional-data'];
        dataAttrs.forEach(attr => {
            item.removeAttribute(attr);
        });
    });
}

/**
 * Enhance filter form functionality
 */
function initFilterEnhancements() {
    const filterForm = document.querySelector('.filters-form form');
    if (!filterForm) return;
    
    // Add loading state when filtering
    filterForm.addEventListener('submit', function() {
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Filtrando...';
            submitBtn.disabled = true;
        }
    });
    
    // Auto-submit on filter change (optional - comment out if not desired)
    const selects = filterForm.querySelectorAll('select');
    selects.forEach(select => {
        select.addEventListener('change', function() {
            // Add a small delay to allow multiple changes
            clearTimeout(window.filterTimeout);
            window.filterTimeout = setTimeout(() => {
                filterForm.submit();
            }, 500);
        });
    });
}

/**
 * Initialize delete functionality
 */
function initDeleteFunctionality() {
    // This is already handled in the PHP file, but we can enhance it
    const deleteModal = document.getElementById('deleteModal');
    if (!deleteModal) return;
    
    // Enhance modal behavior
    const modalContent = deleteModal.querySelector('.modal-content');
    if (modalContent) {
        // Prevent closing when clicking inside modal
        modalContent.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
    
    // Add ESC key to close modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && deleteModal.style.display === 'block') {
            closeDeleteModal();
        }
    });
}

/**
 * Enhance table with additional features
 */
function enhanceTable() {
    const table = document.querySelector('.log-table');
    if (!table) return;
    
    // Add hover effect to rows (CSS handles this, but we can enhance)
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
        // Ensure no inline styles interfere
        row.addEventListener('mouseenter', function() {
            this.style.removeProperty('cursor');
            this.style.removeProperty('user-select');
        });
    });
    
    // Make details column more readable
    enhanceDetailsColumn();
    
    // Add tooltip for long text
    addTooltips();
}

/**
 * Enhance details column formatting
 */
function enhanceDetailsColumn() {
    const detailsCells = document.querySelectorAll('.details-cell');
    
    detailsCells.forEach(cell => {
        // Ensure text wrapping works properly
        cell.style.removeProperty('max-width');
        cell.style.removeProperty('width');
        
        // Format JSON content if present
        const content = cell.textContent.trim();
        if (content.startsWith('{') || content.startsWith('[')) {
            try {
                const json = JSON.parse(content);
                cell.innerHTML = formatJSON(json);
            } catch (e) {
                // Not JSON, leave as is
            }
        }
    });
}

/**
 * Format JSON for display
 */
function formatJSON(obj) {
    let html = '';
    for (const [key, value] of Object.entries(obj)) {
        if (key === 'ip' || key === 'user_agent') continue;
        
        const label = key.replace(/_/g, ' ')
                        .replace(/\b\w/g, l => l.toUpperCase());
        const displayValue = Array.isArray(value) ? value.join(', ') : value;
        
        html += `<div class="detail-row">
                    <strong>${escapeHtml(label)}:</strong> 
                    ${escapeHtml(String(displayValue))}
                 </div>`;
    }
    return html;
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Add tooltips for truncated text
 */
function addTooltips() {
    const cells = document.querySelectorAll('.log-table td');
    
    cells.forEach(cell => {
        // Check if text is truncated
        if (cell.scrollWidth > cell.offsetWidth) {
            cell.title = cell.textContent.trim();
            cell.style.cursor = 'help';
        }
    });
}

/**
 * Override any global functions that might interfere
 */
window.addEventListener('load', function() {
    // Disable expandable logs if loaded from tickets-enhancements.js
    if (window.ticketEnhancements && window.ticketEnhancements.initExpandableLogs) {
        const originalInit = window.ticketEnhancements.initExpandableLogs;
        window.ticketEnhancements.initExpandableLogs = function() {
            // Check if we're on the log-attivita page
            if (document.body.classList.contains('log-attivita-page')) {
                console.log('Expandable logs disabled for log-attivita page');
                return;
            }
            // Otherwise, run the original function
            originalInit.apply(this, arguments);
        };
    }
    
    // Final cleanup after all scripts have loaded
    setTimeout(cleanupInlineStyles, 100);
});

// Export functions for global use if needed
window.logAttivita = {
    cleanupInlineStyles,
    initFilterEnhancements,
    enhanceTable
};