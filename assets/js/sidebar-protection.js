/**
 * SIDEBAR LAYOUT PROTECTION
 * Protegge il layout della sidebar da modifiche JavaScript non autorizzate
 * Creato: 2025-08-11
 */

(function() {
    'use strict';
    
    // Proteggi il layout della sidebar appena il DOM è pronto
    document.addEventListener('DOMContentLoaded', function() {
        protectSidebarLayout();
    });
    
    function protectSidebarLayout() {
        // Seleziona gli elementi critici della sidebar
        const sidebarFooter = document.querySelector('.sidebar-footer');
        const userInfo = document.querySelector('.sidebar-footer .user-info');
        const userAvatar = document.querySelector('.sidebar-footer .user-avatar');
        const userDetails = document.querySelector('.sidebar-footer .user-details');
        const userRole = document.querySelector('.sidebar-footer .user-role');
        
        if (!sidebarFooter || !userInfo) {
            console.warn('Sidebar footer elements not found');
            return;
        }
        
        // Salva la struttura HTML originale
        const originalHTML = {
            footer: sidebarFooter.innerHTML,
            userInfo: userInfo.innerHTML,
            userDetails: userDetails ? userDetails.innerHTML : null,
            userRole: userRole ? userRole.innerHTML : null
        };
        
        // Funzione per ripristinare il layout originale
        function restoreOriginalLayout() {
            if (sidebarFooter && sidebarFooter.innerHTML !== originalHTML.footer) {
                console.log('Sidebar layout modified - restoring original');
                sidebarFooter.innerHTML = originalHTML.footer;
            }
        }
        
        // Proteggi contro modifiche inline style
        function protectFromInlineStyles() {
            // Rimuovi tutti gli stili inline dagli elementi protetti
            const protectedElements = [
                '.sidebar-footer',
                '.sidebar-footer .user-info',
                '.sidebar-footer .user-avatar',
                '.sidebar-footer .user-details',
                '.sidebar-footer .user-name',
                '.sidebar-footer .user-role',
                '.sidebar-footer .badge'
            ];
            
            protectedElements.forEach(selector => {
                const elements = document.querySelectorAll(selector);
                elements.forEach(el => {
                    // Rimuovi attributo style solo se è stato aggiunto dopo il caricamento
                    if (el.hasAttribute('style') && el.getAttribute('data-original-style') === null) {
                        el.setAttribute('data-original-style', el.getAttribute('style') || '');
                        el.removeAttribute('style');
                    }
                });
            });
        }
        
        // Monitora le modifiche al DOM con MutationObserver
        const observer = new MutationObserver(function(mutations) {
            let sidebarModified = false;
            
            mutations.forEach(function(mutation) {
                // Controlla se la modifica riguarda la sidebar footer
                if (mutation.target === sidebarFooter || 
                    mutation.target.closest('.sidebar-footer')) {
                    sidebarModified = true;
                }
            });
            
            if (sidebarModified) {
                // Protezione attiva: rimuovi stili inline aggiunti
                protectFromInlineStyles();
            }
        });
        
        // Configura e avvia l'observer
        const config = {
            attributes: true,
            attributeOldValue: true,
            childList: true,
            subtree: true,
            attributeFilter: ['style', 'class']
        };
        
        observer.observe(sidebarFooter, config);
        
        // Applica protezione iniziale
        protectFromInlineStyles();
        
        // Verifica periodica per assicurarsi che il layout rimanga intatto
        setInterval(function() {
            protectFromInlineStyles();
        }, 2000);
        
        // Log di conferma
        console.log('Sidebar layout protection: ACTIVE');
    }
    
    // Esponi funzioni per debugging se necessario
    window.SidebarProtection = {
        isActive: true,
        disable: function() {
            console.warn('Sidebar protection disabled - layout may be modified');
            this.isActive = false;
        },
        enable: function() {
            console.log('Sidebar protection enabled');
            this.isActive = true;
            protectSidebarLayout();
        }
    };
})();