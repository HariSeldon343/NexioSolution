/**
 * Nexio Button Fix - Forces white text on primary and secondary buttons
 * This script ensures all primary and secondary buttons have white text regardless of inline styles
 * Updated: 2025-08-11 - Added secondary button support
 */

(function() {
    'use strict';
    
    // Function to fix button colors
    function fixPrimaryButtons() {
        // Find all primary, secondary, and danger buttons
        const primaryButtons = document.querySelectorAll('.btn-primary, button.btn-primary, input.btn-primary, a.btn-primary, [class*="btn-primary"], .btn-secondary, button.btn-secondary, input.btn-secondary, a.btn-secondary, [class*="btn-secondary"], .btn-danger, button.btn-danger, input.btn-danger, a.btn-danger, [class*="btn-danger"]');
        
        primaryButtons.forEach(button => {
            // Remove inline color styles that conflict
            if (button.style.color) {
                // Store original color for debugging if needed
                button.dataset.originalColor = button.style.color;
            }
            
            // Force white text color
            button.style.setProperty('color', '#ffffff', 'important');
            
            // Fix background if it's white
            if (button.style.background && (
                button.style.background.includes('white') || 
                button.style.background.includes('255, 255, 255') ||
                button.style.background.includes('#fff')
            )) {
                button.style.setProperty('background-color', '#0d6efd', 'important');
                button.style.setProperty('background', '#0d6efd', 'important');
            }
            
            // Fix border if needed
            if (button.classList.contains('btn-primary')) {
                if (!button.style.borderColor || button.style.borderColor.includes('rgb(45, 90, 159)')) {
                    button.style.setProperty('border-color', '#0d6efd', 'important');
                }
            } else if (button.classList.contains('btn-secondary')) {
                button.style.setProperty('border-color', '#6c757d', 'important');
            } else if (button.classList.contains('btn-danger')) {
                button.style.setProperty('border-color', '#dc3545', 'important');
                button.style.setProperty('background-color', '#dc3545', 'important');
            }
            
            // Fix all child elements (icons, spans, etc.)
            const children = button.querySelectorAll('*');
            children.forEach(child => {
                child.style.setProperty('color', '#ffffff', 'important');
            });
        });
    }
    
    // Function to observe DOM changes and fix new buttons
    function observeButtons() {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    // Check if any new buttons were added
                    mutation.addedNodes.forEach(node => {
                        if (node.nodeType === 1) { // Element node
                            if (node.classList && (node.classList.contains('btn-primary') || node.classList.contains('btn-secondary'))) {
                                fixPrimaryButtons();
                            } else if (node.querySelector && (node.querySelector('.btn-primary') || node.querySelector('.btn-secondary'))) {
                                fixPrimaryButtons();
                            }
                        }
                    });
                } else if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                    // Check if element became a primary or secondary button
                    if (mutation.target.classList && (mutation.target.classList.contains('btn-primary') || mutation.target.classList.contains('btn-secondary'))) {
                        fixPrimaryButtons();
                    }
                }
            });
        });
        
        // Start observing
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['class', 'style']
        });
    }
    
    // Run fixes when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            fixPrimaryButtons();
            observeButtons();
        });
    } else {
        // DOM is already loaded
        fixPrimaryButtons();
        observeButtons();
    }
    
    // Also run after a delay to catch any late-loading elements
    setTimeout(fixPrimaryButtons, 500);
    setTimeout(fixPrimaryButtons, 1000);
    setTimeout(fixPrimaryButtons, 2000);
    
    // Fix buttons after AJAX calls
    if (window.jQuery) {
        jQuery(document).ajaxComplete(function() {
            setTimeout(fixPrimaryButtons, 100);
        });
    }
    
    // Expose function globally for debugging
    window.fixPrimaryButtons = fixPrimaryButtons;
    
})();