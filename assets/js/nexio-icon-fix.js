/**
 * Nexio Icon Fix - Emergency fallback for FontAwesome icons
 * This script ensures icons display even if FontAwesome fails to load
 */

(function() {
    'use strict';
    
    // Wait for DOM to be fully loaded
    document.addEventListener('DOMContentLoaded', function() {
        
        // Check if FontAwesome loaded successfully
        function checkFontAwesome() {
            const testIcon = document.createElement('i');
            testIcon.className = 'fas fa-check';
            testIcon.style.position = 'absolute';
            testIcon.style.visibility = 'hidden';
            document.body.appendChild(testIcon);
            
            const computed = window.getComputedStyle(testIcon, ':before');
            const content = computed.getPropertyValue('content');
            document.body.removeChild(testIcon);
            
            // If content is not the FontAwesome check icon, icons didn't load
            if (!content || content === 'none' || content === '""') {
                console.warn('FontAwesome not loaded properly, applying fallbacks');
                applyIconFallbacks();
            }
        }
        
        // Apply Unicode fallbacks for common icons
        function applyIconFallbacks() {
            const iconMap = {
                'fa-bars': '☰',
                'fa-home': '🏠',
                'fa-users': '👥',
                'fa-user': '👤',
                'fa-file': '📄',
                'fa-folder': '📁',
                'fa-folder-open': '📂',
                'fa-calendar': '📅',
                'fa-calendar-alt': '📆',
                'fa-bell': '🔔',
                'fa-cog': '⚙',
                'fa-cogs': '⚙',
                'fa-sign-out-alt': '⇤',
                'fa-sign-in-alt': '⇥',
                'fa-plus': '➕',
                'fa-plus-circle': '⊕',
                'fa-minus': '➖',
                'fa-edit': '✏',
                'fa-pen': '✏',
                'fa-trash': '🗑',
                'fa-trash-alt': '🗑',
                'fa-download': '⬇',
                'fa-upload': '⬆',
                'fa-search': '🔍',
                'fa-times': '✖',
                'fa-check': '✓',
                'fa-check-circle': '✅',
                'fa-exclamation-triangle': '⚠',
                'fa-info-circle': 'ℹ',
                'fa-question-circle': '❓',
                'fa-envelope': '✉',
                'fa-phone': '☎',
                'fa-building': '🏢',
                'fa-chart-bar': '📊',
                'fa-chart-line': '📈',
                'fa-dashboard': '📊',
                'fa-tachometer-alt': '⏱',
                'fa-tasks': '📋',
                'fa-clipboard': '📋',
                'fa-save': '💾',
                'fa-print': '🖨',
                'fa-eye': '👁',
                'fa-eye-slash': '🚫',
                'fa-lock': '🔒',
                'fa-unlock': '🔓',
                'fa-key': '🔑',
                'fa-star': '⭐',
                'fa-heart': '❤',
                'fa-comment': '💬',
                'fa-comments': '💬',
                'fa-share': '↗',
                'fa-arrow-left': '←',
                'fa-arrow-right': '→',
                'fa-arrow-up': '↑',
                'fa-arrow-down': '↓',
                'fa-chevron-left': '‹',
                'fa-chevron-right': '›',
                'fa-chevron-up': '︿',
                'fa-chevron-down': '﹀',
                'fa-angle-left': '‹',
                'fa-angle-right': '›',
                'fa-angle-up': '︿',
                'fa-angle-down': '﹀',
                'fa-filter': '⊕',
                'fa-sort': '⇅',
                'fa-list': '☰',
                'fa-th': '⚏',
                'fa-th-large': '⬚',
                'fa-file-pdf': '📑',
                'fa-file-word': '📝',
                'fa-file-excel': '📊',
                'fa-file-image': '🖼',
                'fa-file-archive': '🗜',
                'fa-database': '🗄',
                'fa-server': '🖥',
                'fa-cloud': '☁',
                'fa-cloud-upload-alt': '☁⬆',
                'fa-cloud-download-alt': '☁⬇'
            };
            
            // Find all icon elements
            const icons = document.querySelectorAll('[class*="fa-"]');
            
            icons.forEach(icon => {
                // Get all classes
                const classes = icon.className.split(' ');
                
                // Find the icon class
                for (let cls of classes) {
                    if (iconMap[cls]) {
                        // Create a span with the Unicode character
                        const span = document.createElement('span');
                        span.textContent = iconMap[cls];
                        span.style.fontFamily = 'inherit';
                        span.style.fontSize = 'inherit';
                        span.style.lineHeight = 'inherit';
                        span.className = icon.className;
                        
                        // Replace the icon element
                        if (icon.parentNode) {
                            icon.parentNode.replaceChild(span, icon);
                        }
                        break;
                    }
                }
            });
        }
        
        // Force button styling
        function forceButtonStyles() {
            const buttons = document.querySelectorAll('.btn, button, [type="button"], [type="submit"]');
            
            buttons.forEach(btn => {
                // Ensure button is visible
                if (btn.style.display === 'none') {
                    btn.style.display = '';
                }
                
                // Apply minimum styling if no class
                if (!btn.className.includes('btn')) {
                    btn.classList.add('btn', 'btn-secondary');
                }
            });
        }
        
        // Fix modal issues
        function fixModals() {
            // Ensure all modals are hidden by default
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (!modal.classList.contains('show')) {
                    modal.style.display = 'none';
                }
            });
            
            // Remove orphaned backdrops
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(backdrop => {
                if (!document.querySelector('.modal.show')) {
                    backdrop.remove();
                }
            });
        }
        
        // Check and apply fixes after a delay to ensure everything is loaded
        setTimeout(function() {
            checkFontAwesome();
            forceButtonStyles();
            fixModals();
        }, 1000);
        
        // Also check after fonts might have loaded
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(function() {
                checkFontAwesome();
            });
        }
    });
    
    // Fallback: Add a style element with forced icon display
    const style = document.createElement('style');
    style.textContent = `
        /* Force icon visibility */
        [class*="fa-"]:before {
            display: inline-block !important;
            font-style: normal !important;
            font-variant: normal !important;
            text-rendering: auto !important;
            -webkit-font-smoothing: antialiased !important;
        }
        
        /* Ensure buttons are visible */
        .btn {
            opacity: 1 !important;
            visibility: visible !important;
        }
        
        /* Prevent modal auto-show */
        .modal:not(.show) {
            display: none !important;
        }
    `;
    document.head.appendChild(style);
    
})();