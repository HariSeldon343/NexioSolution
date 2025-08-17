/**
 * NEXIO FORCE STYLES JavaScript
 * Emergency JavaScript to force UI styles when CSS fails
 * Created: 2025-08-11
 */

(function() {
    'use strict';
    
    // Wait for DOM to be fully loaded
    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }
    
    ready(function() {
        // Force FontAwesome to load
        function forceFontAwesome() {
            // Check if FontAwesome is loaded
            const testIcon = document.createElement('i');
            testIcon.className = 'fas fa-home';
            testIcon.style.position = 'absolute';
            testIcon.style.visibility = 'hidden';
            document.body.appendChild(testIcon);
            
            const computed = window.getComputedStyle(testIcon, ':before');
            const content = computed.getPropertyValue('content');
            
            document.body.removeChild(testIcon);
            
            // If FontAwesome is not loaded, inject it again
            if (!content || content === 'none' || content === '') {
                console.log('FontAwesome not loaded, injecting backup...');
                
                // Inject FontAwesome 6
                const fa6 = document.createElement('link');
                fa6.rel = 'stylesheet';
                fa6.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css';
                document.head.appendChild(fa6);
                
                // Also inject FontAwesome 5 as fallback
                const fa5 = document.createElement('link');
                fa5.rel = 'stylesheet';
                fa5.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css';
                document.head.appendChild(fa5);
            }
        }
        
        // Force button styles
        function forceButtonStyles() {
            const buttons = document.querySelectorAll('.btn, button.btn, a.btn, input[type="submit"], input[type="button"]');
            
            buttons.forEach(function(btn) {
                // Force uppercase
                btn.style.textTransform = 'uppercase';
                btn.style.letterSpacing = '0.05em';
                btn.style.fontWeight = '500';
                
                // DISABILITATO - Nessun effetto hover con transform
                // btn.addEventListener('mouseenter', function() {
                //     this.style.transform = 'translateY(-1px)';
                // });
                // 
                // btn.addEventListener('mouseleave', function() {
                //     this.style.transform = 'translateY(0)';
                // });
            });
        }
        
        // Force badge styles
        function forceBadgeStyles() {
            const badges = document.querySelectorAll('.badge, [class*="badge-"], .role-badge, [class*="role-"]');
            
            badges.forEach(function(badge) {
                // SALTA COMPLETAMENTE GLI ELEMENTI NEL SIDEBAR-FOOTER
                if (badge.closest('.sidebar-footer')) return;
                if (badge.closest('.user-info')) return;
                if (badge.closest('.user-role')) return;
                
                badge.style.display = 'inline-block';
                badge.style.padding = '0.35rem 0.75rem';
                badge.style.fontSize = '0.8rem';
                badge.style.fontWeight = '600';
                badge.style.borderRadius = '0.375rem';
                
                // Apply color based on class
                if (badge.classList.contains('badge-primary') || 
                    badge.classList.contains('role-super_admin') ||
                    badge.textContent.includes('Super Admin')) {
                    badge.style.backgroundColor = '#0d6efd';
                    badge.style.color = '#ffffff';
                } else if (badge.classList.contains('badge-success') || 
                           badge.classList.contains('role-admin')) {
                    badge.style.backgroundColor = '#198754';
                    badge.style.color = '#ffffff';
                } else if (badge.classList.contains('badge-warning') || 
                           badge.classList.contains('role-utente_speciale')) {
                    badge.style.backgroundColor = '#ffc107';
                    badge.style.color = '#000000';
                } else if (badge.classList.contains('badge-danger')) {
                    badge.style.backgroundColor = '#dc3545';
                    badge.style.color = '#ffffff';
                } else if (badge.classList.contains('badge-info')) {
                    badge.style.backgroundColor = '#0dcaf0';
                    badge.style.color = '#000000';
                } else if (badge.classList.contains('badge-secondary') || 
                           badge.classList.contains('role-utente')) {
                    badge.style.backgroundColor = '#6c757d';
                    badge.style.color = '#ffffff';
                }
            });
        }
        
        // Force sidebar icon visibility
        function forceSidebarIcons() {
            // Modificato per escludere COMPLETAMENTE il footer della sidebar
            // Applica solo agli elementi del menu, NON al footer
            const sidebarIcons = document.querySelectorAll('.sidebar .sidebar-menu .menu-item i');
            
            sidebarIcons.forEach(function(icon) {
                // NON modificare icone nel sidebar-footer
                if (icon.closest('.sidebar-footer')) return;
                
                icon.style.display = 'inline-block';
                icon.style.width = '20px';
                icon.style.marginRight = '10px';
                icon.style.textAlign = 'center';
                icon.style.fontSize = '14px';
                icon.style.color = 'rgba(255,255,255,0.8)';
                icon.style.fontFamily = '"Font Awesome 6 Free", "Font Awesome 5 Free", FontAwesome';
                icon.style.fontWeight = '900';
            });
        }
        
        // Force table styles
        function forceTableStyles() {
            const tables = document.querySelectorAll('.table');
            
            tables.forEach(function(table) {
                table.style.width = '100%';
                table.style.backgroundColor = '#ffffff';
                
                // Style headers
                const headers = table.querySelectorAll('th');
                headers.forEach(function(th) {
                    th.style.backgroundColor = '#f8f9fa';
                    th.style.color = '#495057';
                    th.style.fontWeight = '600';
                    th.style.textTransform = 'uppercase';
                    th.style.fontSize = '0.75rem';
                    th.style.letterSpacing = '0.05em';
                    th.style.padding = '0.75rem';
                });
                
                // Style cells
                const cells = table.querySelectorAll('td');
                cells.forEach(function(td) {
                    td.style.padding = '0.75rem';
                    td.style.color = '#212529';
                    td.style.backgroundColor = '#ffffff';
                });
            });
        }
        
        // Fix Super Admin badge in sidebar specifically
        function fixSuperAdminBadge() {
            // COMPLETAMENTE DISABILITATO - NON MODIFICARE IL LAYOUT DELLA SIDEBAR
            // Il nuovo layout HTML gestisce già correttamente i badge e le icone
            return;
        }
        
        // Apply all fixes
        function applyAllFixes() {
            forceFontAwesome();
            forceButtonStyles();
            forceBadgeStyles();
            forceSidebarIcons();
            forceTableStyles();
            fixSuperAdminBadge();
        }
        
        // Run fixes immediately
        applyAllFixes();
        
        // Run again after a short delay to catch dynamically loaded content
        setTimeout(applyAllFixes, 500);
        setTimeout(applyAllFixes, 1000);
        
        // Also run on any AJAX complete
        if (window.jQuery) {
            jQuery(document).ajaxComplete(function() {
                setTimeout(applyAllFixes, 100);
            });
        }
        
        // Monitor for dynamic content changes
        const observer = new MutationObserver(function(mutations) {
            // Debounce the fixes
            clearTimeout(window.nexioFixTimeout);
            window.nexioFixTimeout = setTimeout(applyAllFixes, 100);
        });
        
        // Start observing
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        
        // Log that fixes are active
        console.log('Nexio Force Styles: Active and monitoring for UI issues');
    });
})();