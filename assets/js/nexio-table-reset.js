/**
 * Nexio Table Reset JavaScript
 * Completely rebuilds table structure to fix overlapping issues
 * Created: 2025-08-11
 */

(function() {
    'use strict';
    
    function resetAndFixTable() {
        console.log('Starting complete table reset...');
        
        // Find the log table
        const logTableContainer = document.querySelector('.log-table');
        if (!logTableContainer) {
            console.log('No log table container found');
            return;
        }
        
        const table = logTableContainer.querySelector('table');
        if (!table) {
            console.log('No table found in log-table container');
            return;
        }
        
        console.log('Found table, resetting all styles...');
        
        // Remove ALL inline styles from table and its children
        const allElements = table.querySelectorAll('*');
        allElements.forEach(el => {
            el.removeAttribute('style');
        });
        table.removeAttribute('style');
        
        // Remove problematic classes
        table.classList.remove('table', 'table-striped', 'table-bordered', 'table-hover');
        
        // Add colgroup for proper column widths
        let colgroup = table.querySelector('colgroup');
        if (!colgroup) {
            colgroup = document.createElement('colgroup');
            
            // Define column widths
            const widths = [
                '130px', // Data/Ora
                '140px', // Utente  
                '80px',  // Tipo
                '100px', // Azione
                'auto',  // Dettagli
                '120px'  // Azienda (if exists)
            ];
            
            // Count actual columns
            const headerRow = table.querySelector('thead tr');
            const columnCount = headerRow ? headerRow.querySelectorAll('th').length : 6;
            
            for (let i = 0; i < columnCount; i++) {
                const col = document.createElement('col');
                if (i < widths.length) {
                    if (widths[i] !== 'auto') {
                        col.style.width = widths[i];
                    }
                }
                colgroup.appendChild(col);
            }
            
            // Insert colgroup as first child of table
            table.insertBefore(colgroup, table.firstChild);
        }
        
        // Fix headers
        const headers = table.querySelectorAll('thead th');
        headers.forEach((th, index) => {
            // Remove all inline styles
            th.removeAttribute('style');
            
            // Add basic styling via JavaScript
            th.style.padding = '12px 8px';
            th.style.background = '#f5f5f5';
            th.style.border = '1px solid #ddd';
            th.style.fontWeight = 'bold';
            th.style.textAlign = 'left';
            th.style.whiteSpace = 'nowrap';
            th.style.fontSize = '13px';
            th.style.color = '#333';
        });
        
        // Fix data cells
        const cells = table.querySelectorAll('tbody td');
        cells.forEach((td, index) => {
            // Remove all inline styles first
            const originalContent = td.innerHTML;
            td.removeAttribute('style');
            
            // Add basic styling
            td.style.padding = '10px 8px';
            td.style.border = '1px solid #ddd';
            td.style.background = 'white';
            td.style.color = '#555';
            td.style.textAlign = 'left';
            td.style.verticalAlign = 'middle';
            
            // Special handling for details cell
            if (td.classList.contains('details-cell')) {
                td.style.whiteSpace = 'normal';
                td.style.wordWrap = 'break-word';
                td.style.minWidth = '200px';
            }
            
            // Fix inner elements
            const innerDivs = td.querySelectorAll('div[style]');
            innerDivs.forEach(div => {
                // Preserve only essential styles
                const currentStyle = div.getAttribute('style');
                if (currentStyle && currentStyle.includes('font-size')) {
                    div.style.fontSize = '12px';
                }
                if (currentStyle && currentStyle.includes('color')) {
                    // Keep color but make it readable
                    const color = window.getComputedStyle(div).color;
                    if (color.includes('rgb(108') || color.includes('#718096')) {
                        div.style.color = '#666';
                    }
                }
            });
        });
        
        // Fix table layout
        table.style.tableLayout = 'auto';
        table.style.width = '100%';
        table.style.borderCollapse = 'collapse';
        table.style.borderSpacing = '0';
        
        // Fix container
        logTableContainer.style.overflowX = 'auto';
        logTableContainer.style.width = '100%';
        
        // Add zebra striping
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach((row, index) => {
            row.removeAttribute('style');
            if (index % 2 === 0) {
                row.style.background = '#fff';
            } else {
                row.style.background = '#f9f9f9';
            }
            
            // Add hover effect
            row.addEventListener('mouseenter', function() {
                this.style.background = '#f0f0f0';
            });
            
            row.addEventListener('mouseleave', function() {
                if (index % 2 === 0) {
                    this.style.background = '#fff';
                } else {
                    this.style.background = '#f9f9f9';
                }
            });
        });
        
        // Fix badges and special elements
        const badges = table.querySelectorAll('.action-badge, .entity-type');
        badges.forEach(badge => {
            badge.style.display = 'inline-block';
            badge.style.padding = '2px 6px';
            badge.style.borderRadius = '3px';
            badge.style.fontSize = '12px';
            
            if (badge.classList.contains('action-badge')) {
                // Simple color scheme for actions
                const text = badge.textContent.toLowerCase();
                if (text.includes('create') || text.includes('crea')) {
                    badge.style.background = '#d4edda';
                    badge.style.color = '#155724';
                } else if (text.includes('update') || text.includes('modif')) {
                    badge.style.background = '#cce5ff';
                    badge.style.color = '#004085';
                } else if (text.includes('delete') || text.includes('elim')) {
                    badge.style.background = '#f8d7da';
                    badge.style.color = '#721c24';
                } else if (text.includes('login')) {
                    badge.style.background = '#d1ecf1';
                    badge.style.color = '#0c5460';
                } else {
                    badge.style.background = '#e2e3e5';
                    badge.style.color = '#383d41';
                }
            }
        });
        
        console.log('Table reset complete');
    }
    
    // Run immediately
    resetAndFixTable();
    
    // Run when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', resetAndFixTable);
    }
    
    // Run after delays
    setTimeout(resetAndFixTable, 100);
    setTimeout(resetAndFixTable, 500);
    setTimeout(resetAndFixTable, 1000);
    
    // Monitor for table changes
    const observer = new MutationObserver(function(mutations) {
        let hasTableChanges = false;
        
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList') {
                mutation.addedNodes.forEach(node => {
                    if (node.nodeType === 1 && 
                        (node.classList?.contains('log-table') || 
                         node.querySelector?.('.log-table'))) {
                        hasTableChanges = true;
                    }
                });
            }
        });
        
        if (hasTableChanges) {
            setTimeout(resetAndFixTable, 100);
        }
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
    
    // Fix after AJAX
    if (window.jQuery) {
        jQuery(document).ajaxComplete(function() {
            setTimeout(resetAndFixTable, 100);
        });
    }
    
    // Expose globally
    window.resetAndFixTable = resetAndFixTable;
    
})();