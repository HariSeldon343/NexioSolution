/**
 * Force Scroll - Garantisce che la pagina sia sempre scrollabile
 * Questo script previene che altri script disabilitino lo scroll
 */

(function() {
    'use strict';
    
    // Funzione per forzare lo scroll
    function forceScroll() {
        // Controlla che gli elementi esistano prima di modificarli
        if (document.documentElement) {
            document.documentElement.style.overflowY = 'auto';
            document.documentElement.style.height = 'auto';
            document.documentElement.style.minHeight = '100%';
        }
        
        if (document.body) {
            document.body.style.overflowY = 'auto';
            document.body.style.overflowX = 'hidden';
            document.body.style.height = 'auto';
            document.body.style.minHeight = '100vh';
            document.body.style.maxHeight = 'none';
        }
        
        // Trova e correggi il main-content
        const mainContent = document.querySelector('.main-content');
        if (mainContent) {
            mainContent.style.overflow = 'visible';
            mainContent.style.overflowY = 'visible';
            mainContent.style.overflowX = 'visible';
            mainContent.style.height = 'auto';
            mainContent.style.minHeight = '100vh';
            mainContent.style.maxHeight = 'none';
        }
        
        // Rimuovi qualsiasi classe che potrebbe bloccare lo scroll
        if (document.body && document.body.classList) {
            document.body.classList.remove('no-scroll', 'overflow-hidden', 'fixed');
        }
        if (document.documentElement && document.documentElement.classList) {
            document.documentElement.classList.remove('no-scroll', 'overflow-hidden', 'fixed');
        }
    }
    
    // Applica immediatamente
    forceScroll();
    
    // Riapplica quando il DOM è pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', forceScroll);
    }
    
    // Riapplica dopo che tutti gli script sono caricati
    window.addEventListener('load', function() {
        setTimeout(forceScroll, 100);
        setTimeout(forceScroll, 500);
        setTimeout(forceScroll, 1000);
    });
    
    // Previeni modifiche future con MutationObserver
    const observer = new MutationObserver(function(mutations) {
        let needsForce = false;
        
        mutations.forEach(function(mutation) {
            if (mutation.type === 'attributes' && 
                (mutation.attributeName === 'style' || mutation.attributeName === 'class')) {
                const target = mutation.target;
                
                if (target === document.body || target === document.documentElement) {
                    needsForce = true;
                }
                
                // Controlla se qualcuno sta cercando di bloccare lo scroll
                if (target.style && (
                    target.style.overflow === 'hidden' ||
                    target.style.overflowY === 'hidden' ||
                    target.style.height === '100vh'
                )) {
                    if (target !== document.querySelector('.sidebar')) {
                        needsForce = true;
                    }
                }
            }
        });
        
        if (needsForce) {
            requestAnimationFrame(forceScroll);
        }
    });
    
    // Osserva modifiche su html e body
    if (document.documentElement) {
        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['style', 'class']
        });
    }
    
    if (document.body) {
        observer.observe(document.body, {
            attributes: true,
            attributeFilter: ['style', 'class'],
            childList: true,
            subtree: true
        });
    }
    
    // Debug: log quando lo scroll è abilitato
    console.log('Force Scroll: Script attivo - Lo scroll è garantito');
    
})();