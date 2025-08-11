/**
 * Nexio Button Size Fix JavaScript
 * Dynamically normalizes oversized buttons and icons
 * Created: 2025-08-11
 */

(function() {
    'use strict';
    
    // Function to fix button sizes
    function fixButtonSizes() {
        // Find all buttons
        const buttons = document.querySelectorAll('.btn, button, a.btn, input[type="button"], input[type="submit"]');
        
        buttons.forEach(button => {
            // Check if button has excessive inline styles
            const style = button.getAttribute('style');
            if (style) {
                // Remove oversized height values
                if (style.includes('height:') || style.includes('height :')) {
                    const currentHeight = parseInt(button.style.height);
                    if (currentHeight > 40) {
                        button.style.setProperty('height', '36px', 'important');
                    }
                }
                
                // Fix excessive padding
                if (style.includes('padding:') || style.includes('padding :')) {
                    const paddingMatch = style.match(/padding:\s*(\d+)px\s+(\d+)px/);
                    if (paddingMatch) {
                        const vPadding = parseInt(paddingMatch[1]);
                        const hPadding = parseInt(paddingMatch[2]);
                        
                        if (vPadding > 10 || hPadding > 20) {
                            button.style.setProperty('padding', '6px 12px', 'important');
                        }
                    }
                }
                
                // Fix font size if too large
                const fontSize = window.getComputedStyle(button).fontSize;
                const fontSizePx = parseInt(fontSize);
                if (fontSizePx > 16) {
                    button.style.setProperty('font-size', '14px', 'important');
                }
                
                // Fix letter spacing if too wide
                if (style.includes('letter-spacing')) {
                    const letterSpacing = window.getComputedStyle(button).letterSpacing;
                    const letterSpacingValue = parseFloat(letterSpacing);
                    if (letterSpacingValue > 1) {
                        button.style.setProperty('letter-spacing', '0.025em', 'important');
                    }
                }
            }
            
            // Fix icons inside buttons
            const icons = button.querySelectorAll('i, .fa, .fas, .far, .fab');
            icons.forEach(icon => {
                const iconSize = window.getComputedStyle(icon).fontSize;
                const iconSizePx = parseInt(iconSize);
                
                // If icon is larger than 16px, normalize it
                if (iconSizePx > 16) {
                    icon.style.setProperty('font-size', '14px', 'important');
                }
                
                // Ensure proper spacing
                if (icon.nextSibling && icon.nextSibling.nodeType === 3) { // Text node
                    icon.style.setProperty('margin-right', '4px', 'important');
                }
            });
            
            // Special handling for ticket creation button
            if (button.href && button.href.includes('tickets.php?action=nuovo')) {
                // Fix button to fit content
                button.style.setProperty('display', 'inline-flex', 'important');
                button.style.setProperty('align-items', 'center', 'important');
                button.style.setProperty('padding', '8px 16px', 'important');
                button.style.setProperty('font-size', '14px', 'important');
                button.style.setProperty('height', '38px', 'important');
                button.style.setProperty('width', 'auto', 'important');
                button.style.setProperty('white-space', 'nowrap', 'important');
                button.style.setProperty('overflow', 'visible', 'important');
                button.style.removeProperty('max-height'); // Remove max-height constraint
                
                const icon = button.querySelector('i');
                if (icon) {
                    icon.style.setProperty('font-size', '14px', 'important');
                    icon.style.setProperty('display', 'inline-flex', 'important');
                    icon.style.setProperty('align-items', 'center', 'important');
                    icon.style.setProperty('vertical-align', 'middle', 'important');
                    icon.style.setProperty('margin-right', '6px', 'important');
                    icon.style.setProperty('line-height', '1', 'important');
                }
            }
        });
        
        // Fix standalone icons that might be oversized
        const standaloneIcons = document.querySelectorAll('i[class*="fa-"]:not(.btn i)');
        standaloneIcons.forEach(icon => {
            const iconSize = window.getComputedStyle(icon).fontSize;
            const iconSizePx = parseInt(iconSize);
            
            // If icon is larger than 24px outside buttons, cap it
            if (iconSizePx > 24) {
                icon.style.setProperty('font-size', '20px', 'important');
            }
        });
    }
    
    // Function to observe DOM changes
    function observeButtons() {
        const observer = new MutationObserver(function(mutations) {
            let shouldFix = false;
            
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach(node => {
                        if (node.nodeType === 1) { // Element node
                            if (node.classList && node.classList.contains('btn')) {
                                shouldFix = true;
                            } else if (node.querySelector && node.querySelector('.btn')) {
                                shouldFix = true;
                            }
                        }
                    });
                } else if (mutation.type === 'attributes' && 
                          (mutation.attributeName === 'style' || mutation.attributeName === 'class')) {
                    if (mutation.target.classList && mutation.target.classList.contains('btn')) {
                        shouldFix = true;
                    }
                }
            });
            
            if (shouldFix) {
                setTimeout(fixButtonSizes, 100);
            }
        });
        
        // Start observing
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['style', 'class']
        });
    }
    
    // Run fixes when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            fixButtonSizes();
            observeButtons();
        });
    } else {
        fixButtonSizes();
        observeButtons();
    }
    
    // Also run after delays to catch dynamically loaded content
    setTimeout(fixButtonSizes, 500);
    setTimeout(fixButtonSizes, 1000);
    setTimeout(fixButtonSizes, 2000);
    
    // Fix after AJAX calls
    if (window.jQuery) {
        jQuery(document).ajaxComplete(function() {
            setTimeout(fixButtonSizes, 100);
        });
    }
    
    // Expose function globally for debugging
    window.fixButtonSizes = fixButtonSizes;
    
})();