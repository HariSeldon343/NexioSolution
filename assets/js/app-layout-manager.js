/**
 * App Layout Manager
 * Gestisce il layout responsive con sidebar e main content
 * Creato: 2025-08-12
 */

(function() {
    'use strict';
    
    // Inizializza al caricamento del DOM
    document.addEventListener('DOMContentLoaded', function() {
        initLayoutManager();
    });
    
    function initLayoutManager() {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        const mobileToggle = document.querySelector('.mobile-menu-toggle');
        const body = document.body;
        
        if (!sidebar || !mainContent) {
            console.warn('Layout Manager: Sidebar o Main Content non trovati');
            return;
        }
        
        // Gestione toggle mobile esistente (se non già gestito)
        if (mobileToggle && !mobileToggle.hasAttribute('data-layout-managed')) {
            mobileToggle.setAttribute('data-layout-managed', 'true');
            
            // Rimuovi eventuali listener esistenti
            const newToggle = mobileToggle.cloneNode(true);
            mobileToggle.parentNode.replaceChild(newToggle, mobileToggle);
            
            // Aggiungi nuovo listener
            newToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar();
            });
        }
        
        // Funzione per toggle sidebar
        function toggleSidebar() {
            sidebar.classList.toggle('active');
            body.classList.toggle('sidebar-open');
            
            // Salva stato in localStorage
            const isOpen = sidebar.classList.contains('active');
            localStorage.setItem('sidebarState', isOpen ? 'open' : 'closed');
        }
        
        // Chiudi sidebar cliccando sull'overlay (mobile)
        if (window.innerWidth <= 768) {
            body.addEventListener('click', function(e) {
                if (body.classList.contains('sidebar-open')) {
                    // Se clicca fuori dalla sidebar
                    if (!sidebar.contains(e.target) && !e.target.closest('.mobile-menu-toggle')) {
                        toggleSidebar();
                    }
                }
            });
        }
        
        // Gestione resize window
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                handleResize();
            }, 250);
        });
        
        function handleResize() {
            if (window.innerWidth > 768) {
                // Desktop: rimuovi classi mobile
                sidebar.classList.remove('active');
                body.classList.remove('sidebar-open');
            } else {
                // Mobile: applica stato salvato
                const savedState = localStorage.getItem('sidebarState');
                if (savedState === 'open') {
                    sidebar.classList.add('active');
                    body.classList.add('sidebar-open');
                } else {
                    sidebar.classList.remove('active');
                    body.classList.remove('sidebar-open');
                }
            }
        }
        
        // Gestione links nella sidebar
        const sidebarLinks = sidebar.querySelectorAll('a:not(.dropdown-toggle)');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function() {
                // Su mobile, chiudi la sidebar dopo click su link
                if (window.innerWidth <= 768) {
                    setTimeout(() => {
                        sidebar.classList.remove('active');
                        body.classList.remove('sidebar-open');
                    }, 100);
                }
            });
        });
        
        // Fix per dropdown Bootstrap nella sidebar
        const dropdownToggles = sidebar.querySelectorAll('.dropdown-toggle');
        dropdownToggles.forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                if (window.innerWidth <= 768) {
                    e.stopPropagation(); // Previeni chiusura sidebar
                }
            });
        });
        
        // Assicura che il main content abbia sempre il margine corretto
        function adjustMainContent() {
            if (!mainContent) return;
            
            if (window.innerWidth > 768) {
                // Desktop: margine per sidebar
                mainContent.style.marginLeft = '260px';
                mainContent.style.width = 'calc(100% - 260px)';
            } else {
                // Mobile: nessun margine
                mainContent.style.marginLeft = '0';
                mainContent.style.width = '100%';
            }
        }
        
        // Applica correzioni iniziali
        adjustMainContent();
        handleResize();
        
        // Previeni scroll orizzontale indesiderato
        function preventHorizontalScroll() {
            const maxWidth = window.innerWidth;
            const elements = mainContent.querySelectorAll('*');
            
            elements.forEach(el => {
                if (el.offsetWidth > maxWidth - 40) { // 40px di padding
                    el.style.maxWidth = '100%';
                    el.style.overflowX = 'auto';
                }
            });
        }
        
        // Esegui controllo scroll ogni volta che il contenuto cambia
        if (mainContent) {
            const observer = new MutationObserver(preventHorizontalScroll);
            observer.observe(mainContent, {
                childList: true,
                subtree: true
            });
            
            // Controllo iniziale
            preventHorizontalScroll();
        }
        
        // Gestione swipe per mobile (opzionale)
        if ('ontouchstart' in window && window.innerWidth <= 768) {
            let touchStartX = 0;
            let touchEndX = 0;
            
            document.addEventListener('touchstart', function(e) {
                touchStartX = e.changedTouches[0].screenX;
            }, { passive: true });
            
            document.addEventListener('touchend', function(e) {
                touchEndX = e.changedTouches[0].screenX;
                handleSwipe();
            }, { passive: true });
            
            function handleSwipe() {
                const swipeDistance = touchEndX - touchStartX;
                const threshold = 50; // Minima distanza per considerare uno swipe
                
                if (Math.abs(swipeDistance) < threshold) return;
                
                if (swipeDistance > 0 && touchStartX < 20) {
                    // Swipe da sinistra: apri sidebar
                    sidebar.classList.add('active');
                    body.classList.add('sidebar-open');
                } else if (swipeDistance < 0 && sidebar.classList.contains('active')) {
                    // Swipe da destra: chiudi sidebar
                    sidebar.classList.remove('active');
                    body.classList.remove('sidebar-open');
                }
            }
        }
        
        // Log di debug
        console.log('Layout Manager inizializzato correttamente');
    }
    
    // Esporta funzioni globali se necessario
    window.AppLayoutManager = {
        toggleSidebar: function() {
            const sidebar = document.querySelector('.sidebar');
            const body = document.body;
            if (sidebar) {
                sidebar.classList.toggle('active');
                body.classList.toggle('sidebar-open');
            }
        },
        
        closeSidebar: function() {
            const sidebar = document.querySelector('.sidebar');
            const body = document.body;
            if (sidebar) {
                sidebar.classList.remove('active');
                body.classList.remove('sidebar-open');
            }
        },
        
        openSidebar: function() {
            const sidebar = document.querySelector('.sidebar');
            const body = document.body;
            if (sidebar) {
                sidebar.classList.add('active');
                body.classList.add('sidebar-open');
            }
        }
    };
})();