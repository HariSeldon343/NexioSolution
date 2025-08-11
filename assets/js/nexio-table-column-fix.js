/**
 * Nexio Table Column Alignment Fix
 * Fixes column misalignment in log activity tables
 * Created: 2025-08-11
 */

(function() {
    'use strict';
    
    function fixTableColumnAlignment() {
        console.log('Fixing table column alignment...');
        
        // Find all tables
        const tables = document.querySelectorAll('table, .table');
        
        tables.forEach(table => {
            // Check if this is a log/activity table
            const isLogTable = table.classList.contains('log-table') || 
                              table.classList.contains('activity-table') ||
                              document.body.classList.contains('log-attivita') ||
                              window.location.pathname.includes('log-attivita');
            
            if (!isLogTable) {
                return;
            }
            
            console.log('Found log table, checking alignment...');
            
            // Get header row
            const headerRow = table.querySelector('thead tr');
            if (!headerRow) {
                console.log('No header row found');
                return;
            }
            
            const headers = headerRow.querySelectorAll('th');
            const headerCount = headers.length;
            console.log(`Header count: ${headerCount}`);
            
            // Get first data row to check column count
            const firstDataRow = table.querySelector('tbody tr');
            if (!firstDataRow) {
                console.log('No data rows found');
                return;
            }
            
            const dataCells = firstDataRow.querySelectorAll('td');
            const dataCount = dataCells.length;
            console.log(`Data cell count: ${dataCount}`);
            
            // Check if there's a mismatch (missing first column)
            if (dataCount === headerCount - 1) {
                console.log('Column mismatch detected! Missing first column in data rows.');
                
                // Add empty first cell to all data rows
                const allDataRows = table.querySelectorAll('tbody tr');
                allDataRows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    if (cells.length === headerCount - 1) {
                        // Create empty cell for first column
                        const emptyCell = document.createElement('td');
                        emptyCell.style.width = '40px';
                        emptyCell.style.minWidth = '40px';
                        emptyCell.style.maxWidth = '40px';
                        emptyCell.innerHTML = '&nbsp;';
                        
                        // Insert at beginning
                        row.insertBefore(emptyCell, row.firstChild);
                    }
                });
            } else if (dataCount > headerCount) {
                console.log('More data cells than headers! Possible extra column.');
                
                // Check if first column is a checkbox or row number
                const firstCell = dataCells[0];
                const hasCheckbox = firstCell.querySelector('input[type="checkbox"]');
                const isRowNumber = /^\d+$/.test(firstCell.textContent.trim());
                
                if (hasCheckbox || isRowNumber || firstCell.textContent.trim() === '') {
                    console.log('First column appears to be checkbox/row number, adding header');
                    
                    // Add empty header for first column
                    const emptyHeader = document.createElement('th');
                    emptyHeader.style.width = '40px';
                    emptyHeader.style.minWidth = '40px';
                    emptyHeader.style.maxWidth = '40px';
                    emptyHeader.innerHTML = '#';
                    
                    headerRow.insertBefore(emptyHeader, headerRow.firstChild);
                }
            }
            
            // Now fix column widths based on content
            const updatedHeaders = table.querySelectorAll('thead th');
            const expectedColumns = [
                { name: '#', width: '40px' },
                { name: 'Data/Ora', width: '150px' },
                { name: 'Utente', width: '120px' },
                { name: 'Tipo', width: '100px' },
                { name: 'Azione', width: '180px' },
                { name: 'Dettagli', width: 'auto' },
                { name: 'Azienda', width: '120px' }
            ];
            
            // Try to identify columns by header text
            updatedHeaders.forEach((header, index) => {
                const headerText = header.textContent.trim().toLowerCase();
                
                // Match column based on header text
                if (headerText.includes('data') || headerText.includes('ora') || headerText.includes('timestamp')) {
                    header.style.width = '150px';
                    header.style.minWidth = '150px';
                } else if (headerText.includes('utente') || headerText.includes('user')) {
                    header.style.width = '120px';
                    header.style.minWidth = '120px';
                } else if (headerText.includes('tipo') || headerText.includes('type')) {
                    header.style.width = '100px';
                    header.style.minWidth = '100px';
                } else if (headerText.includes('azione') || headerText.includes('action')) {
                    header.style.width = '180px';
                    header.style.minWidth = '180px';
                } else if (headerText.includes('dettagli') || headerText.includes('detail') || headerText.includes('descrizione')) {
                    header.style.width = 'auto';
                    header.style.minWidth = '300px';
                } else if (headerText.includes('azienda') || headerText.includes('company')) {
                    header.style.width = '120px';
                    header.style.minWidth = '120px';
                } else if (headerText === '#' || headerText === '' || index === 0) {
                    header.style.width = '40px';
                    header.style.minWidth = '40px';
                    header.style.maxWidth = '40px';
                }
            });
            
            // Apply same widths to data cells
            const allRows = table.querySelectorAll('tbody tr');
            allRows.forEach(row => {
                const cells = row.querySelectorAll('td');
                cells.forEach((cell, index) => {
                    if (index < updatedHeaders.length) {
                        const header = updatedHeaders[index];
                        if (header.style.width && header.style.width !== 'auto') {
                            cell.style.width = header.style.width;
                            cell.style.minWidth = header.style.minWidth || header.style.width;
                            if (header.style.maxWidth) {
                                cell.style.maxWidth = header.style.maxWidth;
                            }
                        } else if (header.style.minWidth) {
                            cell.style.minWidth = header.style.minWidth;
                        }
                    }
                });
            });
            
            // Ensure table layout
            table.style.tableLayout = 'fixed';
            table.style.width = '100%';
            
            console.log('Column alignment fixed');
        });
    }
    
    // Run immediately
    fixTableColumnAlignment();
    
    // Run when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fixTableColumnAlignment);
    }
    
    // Run after delays to catch dynamic content
    setTimeout(fixTableColumnAlignment, 100);
    setTimeout(fixTableColumnAlignment, 500);
    setTimeout(fixTableColumnAlignment, 1000);
    setTimeout(fixTableColumnAlignment, 2000);
    
    // Monitor for changes
    const observer = new MutationObserver(function(mutations) {
        let hasTableChanges = false;
        
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList') {
                mutation.addedNodes.forEach(node => {
                    if (node.nodeType === 1) {
                        if (node.tagName === 'TABLE' || 
                            node.tagName === 'TBODY' ||
                            (node.querySelector && node.querySelector('table'))) {
                            hasTableChanges = true;
                        }
                    }
                });
            }
        });
        
        if (hasTableChanges) {
            setTimeout(fixTableColumnAlignment, 100);
        }
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
    
    // Fix after AJAX
    if (window.jQuery) {
        jQuery(document).ajaxComplete(function() {
            setTimeout(fixTableColumnAlignment, 100);
        });
    }
    
    // Expose globally
    window.fixTableColumnAlignment = fixTableColumnAlignment;
    
})();