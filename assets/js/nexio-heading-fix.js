/**
 * Nexio Heading Fix - Forces white text on calendar and date headings
 * Created: 2025-08-11
 */

(function() {
    'use strict';
    
    // Month names in multiple languages
    const monthNames = [
        // English
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December',
        // Italian
        'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno',
        'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre',
        // Short forms
        'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
        'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
        'Gen', 'Feb', 'Mar', 'Apr', 'Mag', 'Giu',
        'Lug', 'Ago', 'Set', 'Ott', 'Nov', 'Dic'
    ];
    
    // Function to check if text looks like a date/calendar heading
    function isDateHeading(text) {
        if (!text) return false;
        
        // Check for month names
        for (let month of monthNames) {
            if (text.includes(month)) return true;
        }
        
        // Check for year patterns (4 digits)
        if (/\b20\d{2}\b/.test(text)) return true;
        
        // Check for date patterns
        if (/\d{1,2}\/\d{1,2}\/\d{2,4}/.test(text)) return true;
        if (/\d{1,2}-\d{1,2}-\d{2,4}/.test(text)) return true;
        
        return false;
    }
    
    // Function to fix heading colors
    function fixHeadingColors() {
        // Find all h2 elements
        const headings = document.querySelectorAll('h2');
        
        headings.forEach(heading => {
            const text = heading.textContent || heading.innerText || '';
            
            // Check if it's a calendar/date heading
            if (isDateHeading(text)) {
                heading.style.setProperty('color', '#ffffff', 'important');
                
                // Also fix any child elements
                const children = heading.querySelectorAll('*');
                children.forEach(child => {
                    child.style.setProperty('color', '#ffffff', 'important');
                });
            }
            
            // Check parent containers for calendar context
            const parent = heading.closest('.calendar, .calendario, [class*="calendar"], [id*="calendar"]');
            if (parent) {
                heading.style.setProperty('color', '#ffffff', 'important');
            }
            
            // Check for specific classes or IDs
            if (heading.className.includes('month') || 
                heading.className.includes('year') || 
                heading.className.includes('date') ||
                heading.className.includes('calendar') ||
                heading.id.includes('month') ||
                heading.id.includes('year') ||
                heading.id.includes('calendar')) {
                heading.style.setProperty('color', '#ffffff', 'important');
            }
        });
        
        // Also check for h1, h3, h4 that might be calendar titles
        const otherHeadings = document.querySelectorAll('h1, h3, h4');
        otherHeadings.forEach(heading => {
            const text = heading.textContent || heading.innerText || '';
            if (isDateHeading(text)) {
                heading.style.setProperty('color', '#ffffff', 'important');
            }
        });
        
        // Fix FullCalendar toolbar title specifically
        const fcTitle = document.querySelector('.fc-toolbar-title');
        if (fcTitle) {
            fcTitle.style.setProperty('color', '#ffffff', 'important');
        }
    }
    
    // Function to observe DOM changes
    function observeHeadings() {
        const observer = new MutationObserver(function(mutations) {
            let shouldFix = false;
            
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach(node => {
                        if (node.nodeType === 1) { // Element node
                            if (node.tagName === 'H2' || node.tagName === 'H1' || node.tagName === 'H3') {
                                shouldFix = true;
                            } else if (node.querySelector && node.querySelector('h1, h2, h3, h4')) {
                                shouldFix = true;
                            }
                        }
                    });
                } else if (mutation.type === 'characterData' || 
                          (mutation.type === 'attributes' && mutation.attributeName === 'class')) {
                    // Text content changed or class changed
                    if (mutation.target.tagName === 'H2' || 
                        mutation.target.closest('h2')) {
                        shouldFix = true;
                    }
                }
            });
            
            if (shouldFix) {
                setTimeout(fixHeadingColors, 100);
            }
        });
        
        // Start observing
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            characterData: true,
            attributes: true,
            attributeFilter: ['class', 'id']
        });
    }
    
    // Run fixes when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            fixHeadingColors();
            observeHeadings();
        });
    } else {
        fixHeadingColors();
        observeHeadings();
    }
    
    // Also run after delays to catch dynamically loaded content
    setTimeout(fixHeadingColors, 500);
    setTimeout(fixHeadingColors, 1000);
    setTimeout(fixHeadingColors, 2000);
    
    // Fix after AJAX calls
    if (window.jQuery) {
        jQuery(document).ajaxComplete(function() {
            setTimeout(fixHeadingColors, 100);
        });
    }
    
    // Listen for calendar-specific events
    document.addEventListener('calendar:loaded', fixHeadingColors);
    document.addEventListener('calendar:monthChanged', fixHeadingColors);
    document.addEventListener('fullcalendar:loaded', fixHeadingColors);
    
    // Expose function globally for debugging
    window.fixHeadingColors = fixHeadingColors;
    
})();