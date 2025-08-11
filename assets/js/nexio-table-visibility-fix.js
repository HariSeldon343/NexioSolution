/**
 * Nexio Table Visibility Fix JavaScript
 * Dynamically improves table readability and visibility
 * Created: 2025-08-11
 */

(function() {
    'use strict';
    
    // Function to fix table visibility issues
    function fixTableVisibility() {
        // Find all tables
        const tables = document.querySelectorAll('table, .table');
        
        tables.forEach(table => {
            // Add table class if missing
            if (!table.classList.contains('table')) {
                table.classList.add('table');
            }
            
            // Fix all table cells
            const cells = table.querySelectorAll('td');
            cells.forEach(cell => {
                // Remove problematic inline styles
                const style = cell.getAttribute('style');
                if (style) {
                    // Fix max-width constraints
                    if (style.includes('max-width')) {
                        cell.style.removeProperty('max-width');
                        cell.style.setProperty('word-wrap', 'break-word', 'important');
                        cell.style.setProperty('white-space', 'normal', 'important');
                    }
                    
                    // Fix poor contrast colors
                    const colorMatch = style.match(/color:\s*([^;]+)/);
                    if (colorMatch) {
                        const color = colorMatch[1].trim();
                        
                        // Map poor contrast colors to better ones
                        const colorMap = {
                            'rgb(108, 117, 125)': '#495057', // Better gray
                            '#6c757d': '#495057',
                            'gray': '#495057',
                            'lightgray': '#495057',
                            'rgb(220, 53, 69)': '#721c24', // Better red
                            'red': '#721c24',
                            'rgb(40, 167, 69)': '#155724', // Better green
                            'green': '#155724',
                            'rgb(255, 193, 7)': '#856404', // Better yellow
                            'orange': '#856404',
                            'rgb(0, 123, 255)': '#004085', // Better blue
                            'blue': '#004085',
                            'rgb(23, 162, 184)': '#0c5460' // Better cyan
                        };
                        
                        // Check if color needs improvement
                        for (const [badColor, goodColor] of Object.entries(colorMap)) {
                            if (color.includes(badColor)) {
                                cell.style.setProperty('color', goodColor, 'important');
                                
                                // Add background for better visibility
                                if (goodColor === '#721c24') {
                                    cell.style.setProperty('background-color', '#f8d7da', 'important');
                                } else if (goodColor === '#155724') {
                                    cell.style.setProperty('background-color', '#d4edda', 'important');
                                } else if (goodColor === '#856404') {
                                    cell.style.setProperty('background-color', '#fff3cd', 'important');
                                } else if (goodColor === '#004085') {
                                    cell.style.setProperty('background-color', '#d1ecf1', 'important');
                                }
                                break;
                            }
                        }
                    }
                    
                    // Fix font size if too small
                    if (style.includes('font-size: 0.8rem') || style.includes('font-size: 0.75rem')) {
                        cell.style.setProperty('font-size', '0.875rem', 'important');
                    }
                }
                
                // Ensure text is visible
                if (cell.textContent && cell.textContent.trim()) {
                    cell.style.setProperty('visibility', 'visible', 'important');
                    cell.style.setProperty('opacity', '1', 'important');
                }
                
                // Fix details cells specifically
                if (cell.classList.contains('details-cell') || 
                    cell.className.includes('detail')) {
                    cell.style.setProperty('max-width', 'none', 'important');
                    cell.style.setProperty('white-space', 'normal', 'important');
                    cell.style.setProperty('word-wrap', 'break-word', 'important');
                    cell.style.setProperty('overflow', 'visible', 'important');
                }
            });
            
            // Add zebra striping if not present
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach((row, index) => {
                if (index % 2 === 0) {
                    row.style.setProperty('background-color', '#ffffff', 'important');
                } else {
                    row.style.setProperty('background-color', '#f8f9fa', 'important');
                }
                
                // Add hover effect
                row.addEventListener('mouseenter', function() {
                    this.style.setProperty('background-color', '#e9ecef', 'important');
                });
                
                row.addEventListener('mouseleave', function() {
                    if (index % 2 === 0) {
                        this.style.setProperty('background-color', '#ffffff', 'important');
                    } else {
                        this.style.setProperty('background-color', '#f8f9fa', 'important');
                    }
                });
            });
            
            // Fix table headers
            const headers = table.querySelectorAll('th');
            headers.forEach(header => {
                header.style.setProperty('background-color', '#f8f9fa', 'important');
                header.style.setProperty('color', '#212529', 'important');
                header.style.setProperty('font-weight', '600', 'important');
                header.style.setProperty('border-bottom', '2px solid #dee2e6', 'important');
            });
            
            // Ensure table uses fixed layout for better column control
            table.style.setProperty('table-layout', 'fixed', 'important');
            table.style.setProperty('width', '100%', 'important');
            
            // Fix specific columns by index (common log table structure)
            const headerRow = table.querySelector('thead tr');
            if (headerRow) {
                const headers = headerRow.querySelectorAll('th');
                if (headers.length >= 5) {
                    // Set optimal widths
                    headers[0].style.setProperty('width', '15%', 'important'); // Date/Time
                    headers[1].style.setProperty('width', '15%', 'important'); // User
                    headers[2].style.setProperty('width', '12%', 'important'); // Type
                    headers[3].style.setProperty('width', '20%', 'important'); // Action
                    headers[4].style.setProperty('width', '28%', 'important'); // Details
                    if (headers[5]) {
                        headers[5].style.setProperty('width', '10%', 'important'); // Company
                    }
                }
            }
        });
        
        // Fix badges in tables
        const badges = document.querySelectorAll('.table .badge, table .badge');
        badges.forEach(badge => {
            // Ensure badges are visible
            badge.style.setProperty('display', 'inline-block', 'important');
            badge.style.setProperty('padding', '0.25rem 0.5rem', 'important');
            badge.style.setProperty('font-size', '0.75rem', 'important');
            badge.style.setProperty('font-weight', '500', 'important');
            
            // Fix badge colors
            if (badge.classList.contains('badge-success') || 
                badge.textContent.toLowerCase().includes('success')) {
                badge.style.setProperty('background-color', '#28a745', 'important');
                badge.style.setProperty('color', '#ffffff', 'important');
            } else if (badge.classList.contains('badge-danger') || 
                       badge.textContent.toLowerCase().includes('error')) {
                badge.style.setProperty('background-color', '#dc3545', 'important');
                badge.style.setProperty('color', '#ffffff', 'important');
            } else if (badge.classList.contains('badge-warning') || 
                       badge.textContent.toLowerCase().includes('warning')) {
                badge.style.setProperty('background-color', '#ffc107', 'important');
                badge.style.setProperty('color', '#212529', 'important');
            } else if (badge.classList.contains('badge-info')) {
                badge.style.setProperty('background-color', '#17a2b8', 'important');
                badge.style.setProperty('color', '#ffffff', 'important');
            }
        });
    }
    
    // Function to observe table changes
    function observeTables() {
        const observer = new MutationObserver(function(mutations) {
            let shouldFix = false;
            
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach(node => {
                        if (node.nodeType === 1) { // Element node
                            if (node.tagName === 'TABLE' || node.classList?.contains('table')) {
                                shouldFix = true;
                            } else if (node.querySelector && node.querySelector('table, .table')) {
                                shouldFix = true;
                            }
                        }
                    });
                }
            });
            
            if (shouldFix) {
                setTimeout(fixTableVisibility, 100);
            }
        });
        
        // Start observing
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
    
    // Run fixes when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            fixTableVisibility();
            observeTables();
        });
    } else {
        fixTableVisibility();
        observeTables();
    }
    
    // Also run after delays to catch dynamically loaded content
    setTimeout(fixTableVisibility, 500);
    setTimeout(fixTableVisibility, 1000);
    setTimeout(fixTableVisibility, 2000);
    
    // Fix after AJAX calls
    if (window.jQuery) {
        jQuery(document).ajaxComplete(function() {
            setTimeout(fixTableVisibility, 100);
        });
    }
    
    // Expose function globally for debugging
    window.fixTableVisibility = fixTableVisibility;
    
})();