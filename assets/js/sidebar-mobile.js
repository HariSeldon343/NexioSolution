/**
 * Sidebar Mobile JavaScript
 * Handles mobile-specific sidebar functionality
 */

(function() {
    'use strict';

    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
        initSidebarMobile();
    });

    function initSidebarMobile() {
        const sidebar = document.querySelector('.sidebar');
        const sidebarToggle = document.querySelector('.sidebar-toggle');
        const sidebarOverlay = document.querySelector('.sidebar-overlay');
        const body = document.body;

        // Check if we're on mobile
        const isMobile = window.innerWidth <= 768;

        if (!sidebar) return;

        // Create overlay if it doesn't exist
        if (!sidebarOverlay && isMobile) {
            const overlay = document.createElement('div');
            overlay.className = 'sidebar-overlay';
            document.body.appendChild(overlay);
            
            // Close sidebar when clicking overlay
            overlay.addEventListener('click', closeSidebar);
        }

        // Toggle sidebar on mobile
        // Removed: sidebar-toggle button no longer exists
        // The sidebar can still be toggled via swipe gestures on mobile

        // Handle menu item clicks on mobile
        if (isMobile) {
            const menuItems = sidebar.querySelectorAll('.menu-item a');
            menuItems.forEach(item => {
                item.addEventListener('click', function() {
                    // Close sidebar after clicking a menu item on mobile
                    if (!this.querySelector('.submenu-toggle')) {
                        closeSidebar();
                    }
                });
            });
        }

        // Handle submenu toggles
        const submenuToggles = sidebar.querySelectorAll('.submenu-toggle');
        submenuToggles.forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const parentItem = this.closest('.menu-item');
                const submenu = parentItem.querySelector('.submenu');
                
                if (submenu) {
                    parentItem.classList.toggle('expanded');
                    
                    if (parentItem.classList.contains('expanded')) {
                        submenu.style.maxHeight = submenu.scrollHeight + 'px';
                    } else {
                        submenu.style.maxHeight = '0';
                    }
                }
            });
        });

        // Handle window resize
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                handleResize();
            }, 250);
        });

        // Functions
        function toggleSidebar() {
            body.classList.toggle('sidebar-open');
            
            if (body.classList.contains('sidebar-open')) {
                // Trap focus in sidebar
                trapFocus(sidebar);
            } else {
                // Release focus trap
                releaseFocus();
            }
        }

        function closeSidebar() {
            body.classList.remove('sidebar-open');
            releaseFocus();
        }

        function openSidebar() {
            body.classList.add('sidebar-open');
            trapFocus(sidebar);
        }

        function handleResize() {
            const newIsMobile = window.innerWidth <= 768;
            
            if (!newIsMobile) {
                // Desktop view - remove mobile-specific classes
                body.classList.remove('sidebar-open');
                releaseFocus();
            }
        }

        // Focus trap for accessibility
        let focusableElements = [];
        let firstFocusableElement = null;
        let lastFocusableElement = null;

        function trapFocus(element) {
            const focusableSelectors = 'a[href], button, textarea, input[type="text"], input[type="radio"], input[type="checkbox"], select';
            focusableElements = element.querySelectorAll(focusableSelectors);
            focusableElements = Array.prototype.slice.call(focusableElements);

            firstFocusableElement = focusableElements[0];
            lastFocusableElement = focusableElements[focusableElements.length - 1];

            element.addEventListener('keydown', trapFocusHandler);
            
            // Focus first element
            if (firstFocusableElement) {
                firstFocusableElement.focus();
            }
        }

        function releaseFocus() {
            if (sidebar) {
                sidebar.removeEventListener('keydown', trapFocusHandler);
            }
        }

        function trapFocusHandler(e) {
            if (e.key === 'Tab') {
                if (e.shiftKey) {
                    // Shift + Tab
                    if (document.activeElement === firstFocusableElement) {
                        lastFocusableElement.focus();
                        e.preventDefault();
                    }
                } else {
                    // Tab
                    if (document.activeElement === lastFocusableElement) {
                        firstFocusableElement.focus();
                        e.preventDefault();
                    }
                }
            }

            // Close on Escape
            if (e.key === 'Escape') {
                closeSidebar();
            }
        }

        // Swipe gestures for mobile
        if (isMobile && 'ontouchstart' in window) {
            let touchStartX = 0;
            let touchEndX = 0;

            document.addEventListener('touchstart', function(e) {
                touchStartX = e.changedTouches[0].screenX;
            }, false);

            document.addEventListener('touchend', function(e) {
                touchEndX = e.changedTouches[0].screenX;
                handleSwipe();
            }, false);

            function handleSwipe() {
                const swipeThreshold = 50;
                const swipeDistance = touchEndX - touchStartX;

                if (Math.abs(swipeDistance) > swipeThreshold) {
                    if (swipeDistance > 0 && touchStartX < 20) {
                        // Swipe right from left edge - open sidebar
                        openSidebar();
                    } else if (swipeDistance < 0 && body.classList.contains('sidebar-open')) {
                        // Swipe left - close sidebar
                        closeSidebar();
                    }
                }
            }
        }
    }

    // Export for use in other scripts if needed
    window.SidebarMobile = {
        toggle: function() {
            document.body.classList.toggle('sidebar-open');
        },
        close: function() {
            document.body.classList.remove('sidebar-open');
        },
        open: function() {
            document.body.classList.add('sidebar-open');
        }
    };

})();