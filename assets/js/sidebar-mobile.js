/**
 * Nexio Sidebar Mobile Enhancement
 * JavaScript per la gestione responsive della sidebar
 */

(function() {
    'use strict';
    
    let sidebarMobileInit = false;
    
    function initSidebarMobile() {
        if (sidebarMobileInit) return;
        sidebarMobileInit = true;
        
        // Create mobile toggle button
        const toggleButton = document.createElement('button');
        toggleButton.className = 'sidebar-mobile-toggle';
        toggleButton.innerHTML = '<i class="fas fa-bars"></i>';
        toggleButton.setAttribute('aria-label', 'Toggle Navigation Menu');
        toggleButton.setAttribute('aria-expanded', 'false');
        
        // Create overlay for mobile
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        
        // Insert elements into DOM
        document.body.insertBefore(toggleButton, document.body.firstChild);
        document.body.appendChild(overlay);
        
        // Get sidebar element
        const sidebar = document.querySelector('.sidebar');
        if (!sidebar) {
            console.warn('Nexio Sidebar: sidebar element not found');
            return;
        }
        
        // Toggle function
        function toggleSidebar() {
            const isOpen = sidebar.classList.contains('sidebar-open');
            
            if (isOpen) {
                closeSidebar();
            } else {
                openSidebar();
            }
        }
        
        function openSidebar() {
            sidebar.classList.add('sidebar-open');
            overlay.classList.add('show');
            toggleButton.setAttribute('aria-expanded', 'true');
            toggleButton.innerHTML = '<i class="fas fa-times"></i>';
            
            // Prevent body scroll when sidebar is open on mobile
            if (window.innerWidth <= 768) {
                document.body.style.overflow = 'hidden';
            }
        }
        
        function closeSidebar() {
            sidebar.classList.remove('sidebar-open');
            overlay.classList.remove('show');
            toggleButton.setAttribute('aria-expanded', 'false');
            toggleButton.innerHTML = '<i class="fas fa-bars"></i>';
            
            // Restore body scroll
            document.body.style.overflow = '';
        }
        
        // Event listeners
        toggleButton.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', closeSidebar);
        
        // Close sidebar when clicking on menu items on mobile
        const menuItems = sidebar.querySelectorAll('.menu-item a');
        menuItems.forEach(item => {
            item.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    closeSidebar();
                }
            });
        });
        
        // Handle keyboard navigation
        document.addEventListener('keydown', (e) => {
            // Close sidebar with Escape key
            if (e.key === 'Escape' && sidebar.classList.contains('sidebar-open')) {
                closeSidebar();
            }
            
            // Toggle sidebar with Ctrl+M
            if (e.ctrlKey && e.key === 'm') {
                e.preventDefault();
                toggleSidebar();
            }
        });
        
        // Handle window resize
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            // Rimozione timeout per risposta immediata
            // Close sidebar on desktop
            if (window.innerWidth > 768) {
                closeSidebar();
                document.body.style.overflow = '';
            }
            
            // Update toggle button visibility
            updateToggleVisibility();
        });
        
        function updateToggleVisibility() {
            if (window.innerWidth <= 768) {
                toggleButton.style.display = 'block';
            } else {
                toggleButton.style.display = 'none';
            }
        }
        
        // Initial setup
        updateToggleVisibility();
        
        // Focus management
        let lastFocusedElement = null;
        
        function trapFocus(element) {
            const focusableElements = element.querySelectorAll(
                'a[href], button, textarea, input[type="text"], input[type="radio"], input[type="checkbox"], select'
            );
            const firstFocusableElement = focusableElements[0];
            const lastFocusableElement = focusableElements[focusableElements.length - 1];
            
            element.addEventListener('keydown', (e) => {
                if (e.key === 'Tab') {
                    if (e.shiftKey) {
                        if (document.activeElement === firstFocusableElement) {
                            lastFocusableElement.focus();
                            e.preventDefault();
                        }
                    } else {
                        if (document.activeElement === lastFocusableElement) {
                            firstFocusableElement.focus();
                            e.preventDefault();
                        }
                    }
                }
            });
        }
        
        // Apply focus trap when sidebar is open on mobile
        toggleButton.addEventListener('click', () => {
            if (window.innerWidth <= 768 && sidebar.classList.contains('sidebar-open')) {
                lastFocusedElement = document.activeElement;
                trapFocus(sidebar);
                
                // Focus first menu item - timeout rimosso
                const firstMenuItem = sidebar.querySelector('.menu-item a');
                if (firstMenuItem) {
                    firstMenuItem.focus();
                }
            } else if (lastFocusedElement) {
                lastFocusedElement.focus();
                lastFocusedElement = null;
            }
        });
        
        // Smooth scroll for anchor links within sidebar
        sidebar.addEventListener('click', (e) => {
            const link = e.target.closest('a[href^="#"]');
            if (link) {
                e.preventDefault();
                const targetId = link.getAttribute('href').substring(1);
                const targetElement = document.getElementById(targetId);
                
                if (targetElement) {
                    closeSidebar();
                    // Rimozione smooth scroll e timeout
                    targetElement.scrollIntoView({
                        block: 'start'
                    });
                }
            }
        });
        
        console.log('Nexio Sidebar: Mobile enhancement initialized');
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSidebarMobile);
    } else {
        initSidebarMobile();
    }
    
    // Export for manual initialization if needed
    window.NexioSidebar = {
        init: initSidebarMobile,
        initialized: () => sidebarMobileInit
    };
    
})();