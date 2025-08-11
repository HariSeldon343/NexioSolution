/**
 * Nexio Table Emergency Fix JavaScript
 * Forces proper table layout and removes problematic inline styles
 * Created: 2025-08-11
 */

(function() {
    'use strict';
    
    function emergencyTableFix() {
        console.log('Running emergency table fix...');
        
        // Find all tables
        const tables = document.querySelectorAll('table, .table');
        
        tables.forEach((table, tableIndex) => {
            console.log(`Fixing table ${tableIndex + 1}...`);
            
            // Force table properties
            table.style.setProperty('table-layout', 'auto', 'important');
            table.style.setProperty('width', '100%', 'important');
            table.style.setProperty('border-collapse', 'separate', 'important');
            table.style.setProperty('border-spacing', '0', 'important');
            
            // Add class for styling if missing
            if (!table.classList.contains('table')) {
                table.classList.add('table');
            }
            
            // Fix all cells
            const cells = table.querySelectorAll('td, th');
            cells.forEach((cell, cellIndex) => {
                // Remove ALL inline styles that cause problems
                const style = cell.getAttribute('style') || '';
                
                // List of properties to remove
                const problematicProps = [
                    'max-width',
                    'white-space: nowrap',
                    'overflow: hidden',
                    'text-overflow: ellipsis',
                    'width: 100px',
                    'width: 150px',
                    'width: 200px'
                ];
                
                let newStyle = style;
                problematicProps.forEach(prop => {
                    const regex = new RegExp(prop.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '[^;]*;?', 'gi');
                    newStyle = newStyle.replace(regex, '');
                });
                
                // Apply cleaned style
                if (newStyle !== style) {
                    cell.setAttribute('style', newStyle);
                }
                
                // Force proper text wrapping
                cell.style.setProperty('white-space', 'normal', 'important');
                cell.style.setProperty('word-wrap', 'break-word', 'important');
                cell.style.setProperty('word-break', 'break-word', 'important');
                cell.style.setProperty('overflow', 'visible', 'important');
                cell.style.setProperty('text-overflow', 'clip', 'important');
                cell.style.setProperty('padding', '12px', 'important');
                
                // Remove max-width specifically
                cell.style.removeProperty('max-width');
                
                // Fix details cells
                if (cell.classList.contains('details-cell') || 
                    cell.className.includes('detail') ||
                    cellIndex === 4) { // Usually the 5th column
                    cell.style.setProperty('min-width', '300px', 'important');
                    cell.style.setProperty('width', 'auto', 'important');
                    cell.style.removeProperty('max-width');
                }
                
                // Fix color contrast
                const currentColor = window.getComputedStyle(cell).color;
                const colorMap = {
                    'rgb(40, 167, 69)': { color: '#0d6f29', bg: '#d1f2db' },  // Green
                    'rgb(220, 53, 69)': { color: '#842029', bg: '#f8d7da' },  // Red
                    'rgb(255, 193, 7)': { color: '#664d03', bg: '#fff3cd' },  // Yellow
                    'rgb(0, 123, 255)': { color: '#055160', bg: '#cff4fc' },  // Blue
                    'rgb(108, 117, 125)': { color: '#495057', bg: '#f8f9fa' }, // Gray
                    'green': { color: '#0d6f29', bg: '#d1f2db' },
                    'red': { color: '#842029', bg: '#f8d7da' },
                    'orange': { color: '#664d03', bg: '#fff3cd' },
                    'blue': { color: '#055160', bg: '#cff4fc' }
                };
                
                Object.keys(colorMap).forEach(badColor => {
                    if (currentColor.includes(badColor) || style.includes(badColor)) {
                        const fix = colorMap[badColor];
                        cell.style.setProperty('color', fix.color, 'important');
                        cell.style.setProperty('background-color', fix.bg, 'important');
                        cell.style.setProperty('padding', '8px 12px', 'important');
                        cell.style.setProperty('border-radius', '4px', 'important');
                    }
                });
            });
            
            // Fix table headers
            const headers = table.querySelectorAll('thead th');
            headers.forEach((header, index) => {
                header.style.setProperty('background-color', '#e9ecef', 'important');
                header.style.setProperty('color', '#212529', 'important');
                header.style.setProperty('font-weight', '700', 'important');
                header.style.setProperty('padding', '12px', 'important');
                header.style.setProperty('border', '1px solid #dee2e6', 'important');
                
                // Set column widths for log tables
                if (table.classList.contains('log-table') || 
                    table.classList.contains('activity-table') ||
                    document.body.classList.contains('log-attivita')) {
                    
                    const columnWidths = [
                        '140px', // Date
                        '120px', // User
                        '100px', // Type
                        '180px', // Action
                        'auto',  // Details (flexible)
                        '100px'  // Company
                    ];
                    
                    if (index < columnWidths.length) {
                        header.style.setProperty('width', columnWidths[index], 'important');
                        if (columnWidths[index] !== 'auto') {
                            header.style.setProperty('min-width', columnWidths[index], 'important');
                            header.style.setProperty('max-width', columnWidths[index], 'important');
                        }
                    }
                }
            });
            
            // Apply column widths to body cells
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach((row, rowIndex) => {
                const cells = row.querySelectorAll('td');
                cells.forEach((cell, cellIndex) => {
                    if (table.classList.contains('log-table') || 
                        table.classList.contains('activity-table') ||
                        document.body.classList.contains('log-attivita')) {
                        
                        const columnWidths = [
                            '140px', // Date
                            '120px', // User
                            '100px', // Type
                            '180px', // Action
                            'auto',  // Details (flexible)
                            '100px'  // Company
                        ];
                        
                        if (cellIndex < columnWidths.length) {
                            if (columnWidths[cellIndex] !== 'auto') {
                                cell.style.setProperty('width', columnWidths[cellIndex], 'important');
                                cell.style.setProperty('min-width', columnWidths[cellIndex], 'important');
                                cell.style.setProperty('max-width', columnWidths[cellIndex], 'important');
                            } else {
                                // Details column
                                cell.style.setProperty('width', 'auto', 'important');
                                cell.style.setProperty('min-width', '300px', 'important');
                                cell.style.removeProperty('max-width');
                            }
                        }
                    }
                });
                
                // Zebra striping
                if (rowIndex % 2 === 0) {
                    row.style.setProperty('background-color', '#ffffff', 'important');
                } else {
                    row.style.setProperty('background-color', '#f8f9fa', 'important');
                }
                
                // Add hover effect
                row.addEventListener('mouseenter', function() {
                    this.style.setProperty('background-color', '#e2e6ea', 'important');
                });
                
                row.addEventListener('mouseleave', function() {
                    if (rowIndex % 2 === 0) {
                        this.style.setProperty('background-color', '#ffffff', 'important');
                    } else {
                        this.style.setProperty('background-color', '#f8f9fa', 'important');
                    }
                });
            });
            
            // Wrap table in responsive container if not already wrapped
            if (!table.closest('.table-responsive')) {
                const wrapper = document.createElement('div');
                wrapper.className = 'table-responsive';
                wrapper.style.setProperty('overflow-x', 'auto', 'important');
                wrapper.style.setProperty('overflow-y', 'visible', 'important');
                wrapper.style.setProperty('width', '100%', 'important');
                wrapper.style.setProperty('-webkit-overflow-scrolling', 'touch', 'important');
                
                table.parentNode.insertBefore(wrapper, table);
                wrapper.appendChild(table);
            }
        });
        
        // Fix badges
        const badges = document.querySelectorAll('.badge');
        badges.forEach(badge => {
            badge.style.setProperty('display', 'inline-block', 'important');
            badge.style.setProperty('padding', '4px 8px', 'important');
            badge.style.setProperty('font-size', '12px', 'important');
            badge.style.setProperty('font-weight', '600', 'important');
            badge.style.setProperty('border-radius', '4px', 'important');
            
            // Fix badge colors
            if (badge.classList.contains('badge-success')) {
                badge.style.setProperty('background-color', '#198754', 'important');
                badge.style.setProperty('color', '#ffffff', 'important');
            } else if (badge.classList.contains('badge-danger')) {
                badge.style.setProperty('background-color', '#dc3545', 'important');
                badge.style.setProperty('color', '#ffffff', 'important');
            } else if (badge.classList.contains('badge-warning')) {
                badge.style.setProperty('background-color', '#ffc107', 'important');
                badge.style.setProperty('color', '#000000', 'important');
            } else if (badge.classList.contains('badge-info')) {
                badge.style.setProperty('background-color', '#0dcaf0', 'important');
                badge.style.setProperty('color', '#000000', 'important');
            }
        });
        
        console.log('Emergency table fix completed');
    }
    
    // Run immediately
    emergencyTableFix();
    
    // Run when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', emergencyTableFix);
    }
    
    // Run after delays
    setTimeout(emergencyTableFix, 100);
    setTimeout(emergencyTableFix, 500);
    setTimeout(emergencyTableFix, 1000);
    setTimeout(emergencyTableFix, 2000);
    
    // Monitor for changes
    const observer = new MutationObserver(function(mutations) {
        let hasTableChanges = false;
        
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList') {
                mutation.addedNodes.forEach(node => {
                    if (node.nodeType === 1) {
                        if (node.tagName === 'TABLE' || 
                            node.tagName === 'TD' || 
                            node.tagName === 'TR' ||
                            (node.querySelector && node.querySelector('table'))) {
                            hasTableChanges = true;
                        }
                    }
                });
            }
        });
        
        if (hasTableChanges) {
            setTimeout(emergencyTableFix, 100);
        }
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
    
    // Fix after AJAX
    if (window.jQuery) {
        jQuery(document).ajaxComplete(function() {
            setTimeout(emergencyTableFix, 100);
        });
    }
    
    // Expose globally
    window.emergencyTableFix = emergencyTableFix;
    
})();