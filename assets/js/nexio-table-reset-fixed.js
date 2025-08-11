/**
 * Nexio Table Reset JavaScript - FIXED VERSION
 * Completely rebuilds table structure to fix overlapping issues
 * Created: 2025-08-11
 */

(function() {
    'use strict';
    
    function resetAndFixTable() {
        console.log('Starting complete table reset...');
        
        // Find the log table - try multiple selectors
        let logTableContainer = document.querySelector('.log-table');
        
        // If not found, try alternative selectors
        if (!logTableContainer) {
            // Try to find table directly
            const tables = document.querySelectorAll('table');
            for (let table of tables) {
                // Check if this looks like a log table
                const firstHeader = table.querySelector('thead th');
                if (firstHeader && (firstHeader.textContent.includes('Data') || 
                                   firstHeader.textContent.includes('Ora'))) {
                    // Create wrapper if needed
                    if (!table.closest('.log-table')) {
                        const wrapper = document.createElement('div');
                        wrapper.className = 'log-table';
                        table.parentNode.insertBefore(wrapper, table);
                        wrapper.appendChild(table);
                        logTableContainer = wrapper;
                    } else {
                        logTableContainer = table.closest('.log-table');
                    }
                    break;
                }
            }
        }
        
        if (!logTableContainer) {
            console.log('No log table container found - will retry');
            return false;
        }
        
        const table = logTableContainer.querySelector('table');
        if (!table) {
            console.log('No table found in log-table container');
            return false;
        }
        
        console.log('Found table, resetting all styles...');
        
        // Remove ALL inline styles from table and its children
        const allElements = table.querySelectorAll('*');
        allElements.forEach(el => {
            if (el.hasAttribute('style')) {
                // Save some essential styles
                const style = el.getAttribute('style');
                el.removeAttribute('style');
                
                // Restore only essential styles for specific elements
                if (el.classList.contains('details-cell')) {
                    el.style.whiteSpace = 'normal';
                    el.style.wordWrap = 'break-word';
                }
            }
        });
        table.removeAttribute('style');
        
        // Remove problematic classes
        table.classList.remove('table', 'table-striped', 'table-bordered', 'table-hover');
        
        // Set basic table styles
        table.style.width = '100%';
        table.style.borderCollapse = 'collapse';
        table.style.borderSpacing = '0';
        table.style.tableLayout = 'fixed';
        table.style.backgroundColor = 'white';
        
        // Add or update colgroup for proper column widths
        let colgroup = table.querySelector('colgroup');
        if (colgroup) {
            colgroup.remove();
        }
        
        colgroup = document.createElement('colgroup');
        
        // Count actual columns from header
        const headerRow = table.querySelector('thead tr');
        if (!headerRow) {
            console.log('No header row found');
            return false;
        }
        
        const headers = headerRow.querySelectorAll('th');
        const columnCount = headers.length;
        console.log(`Found ${columnCount} columns`);
        
        // Define column widths based on header text
        headers.forEach((header, index) => {
            const col = document.createElement('col');
            const headerText = header.textContent.toLowerCase();
            
            if (headerText.includes('data') || headerText.includes('ora')) {
                col.style.width = '140px';
            } else if (headerText.includes('utente') || headerText.includes('user')) {
                col.style.width = '150px';
            } else if (headerText.includes('tipo') || headerText.includes('type')) {
                col.style.width = '90px';
            } else if (headerText.includes('azione') || headerText.includes('action')) {
                col.style.width = '110px';
            } else if (headerText.includes('dettagli') || headerText.includes('detail')) {
                // Dettagli gets remaining space
                col.style.width = 'auto';
            } else if (headerText.includes('azienda') || headerText.includes('company')) {
                col.style.width = '130px';
            } else {
                col.style.width = 'auto';
            }
            
            colgroup.appendChild(col);
        });
        
        // Insert colgroup as first child of table
        table.insertBefore(colgroup, table.firstChild);
        
        // Style headers
        headers.forEach((th, index) => {
            th.style.padding = '10px 8px';
            th.style.backgroundColor = '#f5f5f5';
            th.style.border = '1px solid #ddd';
            th.style.fontWeight = 'bold';
            th.style.textAlign = 'left';
            th.style.fontSize = '13px';
            th.style.color = '#333';
            th.style.whiteSpace = 'nowrap';
            th.style.overflow = 'hidden';
            th.style.textOverflow = 'ellipsis';
        });
        
        // Style all rows and cells
        const tbody = table.querySelector('tbody');
        if (tbody) {
            const rows = tbody.querySelectorAll('tr');
            rows.forEach((row, rowIndex) => {
                // Remove row styles
                row.removeAttribute('style');
                
                // Add zebra striping
                if (rowIndex % 2 === 0) {
                    row.style.backgroundColor = '#ffffff';
                } else {
                    row.style.backgroundColor = '#f9f9f9';
                }
                
                // Style cells in this row
                const cells = row.querySelectorAll('td');
                cells.forEach((td, cellIndex) => {
                    td.style.padding = '8px';
                    td.style.border = '1px solid #ddd';
                    td.style.fontSize = '13px';
                    td.style.color = '#333';
                    td.style.textAlign = 'left';
                    td.style.verticalAlign = 'middle';
                    
                    // Special handling based on column
                    if (cellIndex === 0) {
                        // Date column - no wrap
                        td.style.whiteSpace = 'nowrap';
                    } else if (td.classList.contains('details-cell') || cellIndex === 4) {
                        // Details column - allow wrap
                        td.style.whiteSpace = 'normal';
                        td.style.wordWrap = 'break-word';
                        td.style.wordBreak = 'break-word';
                    }
                    
                    // Fix badges inside cells
                    const badges = td.querySelectorAll('.action-badge, .entity-type');
                    badges.forEach(badge => {
                        badge.style.display = 'inline-block';
                        badge.style.padding = '2px 6px';
                        badge.style.borderRadius = '3px';
                        badge.style.fontSize = '11px';
                        badge.style.fontWeight = 'normal';
                    });
                });
                
                // Add hover effect
                row.onmouseenter = function() {
                    this.style.backgroundColor = '#e8f4f8';
                };
                
                row.onmouseleave = function() {
                    if (rowIndex % 2 === 0) {
                        this.style.backgroundColor = '#ffffff';
                    } else {
                        this.style.backgroundColor = '#f9f9f9';
                    }
                };
            });
        }
        
        // Fix container
        logTableContainer.style.width = '100%';
        logTableContainer.style.overflowX = 'auto';
        logTableContainer.style.overflowY = 'visible';
        
        console.log('Table reset complete');
        return true;
    }
    
    // Safe initialization function
    function initialize() {
        // Ensure DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                resetAndFixTable();
                setupObserver();
            });
        } else {
            resetAndFixTable();
            setupObserver();
        }
    }
    
    // Setup mutation observer
    function setupObserver() {
        // Only setup observer if body exists
        if (!document.body) {
            setTimeout(setupObserver, 100);
            return;
        }
        
        const observer = new MutationObserver(function(mutations) {
            let hasTableChanges = false;
            
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach(node => {
                        if (node.nodeType === 1) {
                            if (node.tagName === 'TABLE' || 
                                (node.classList && node.classList.contains('log-table')) ||
                                (node.querySelector && node.querySelector('table'))) {
                                hasTableChanges = true;
                            }
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
    }
    
    // Initialize
    initialize();
    
    // Run at various times to catch dynamic content
    setTimeout(function() { resetAndFixTable(); }, 100);
    setTimeout(function() { resetAndFixTable(); }, 500);
    setTimeout(function() { resetAndFixTable(); }, 1000);
    setTimeout(function() { resetAndFixTable(); }, 2000);
    
    // Fix after AJAX calls
    if (window.jQuery) {
        jQuery(document).ajaxComplete(function() {
            setTimeout(resetAndFixTable, 100);
        });
    }
    
    // Expose function globally for debugging
    window.resetAndFixTable = resetAndFixTable;
    
})();