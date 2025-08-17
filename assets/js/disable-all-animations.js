/**
 * DISABLE ALL ANIMATIONS JavaScript
 * Forza la rimozione di TUTTE le animazioni e transizioni
 * Created: 2025-08-12
 */

(function() {
    'use strict';
    
    // Funzione per rimuovere tutte le transizioni e animazioni
    function disableAllAnimations() {
        // Ottieni tutti gli elementi del DOM
        const allElements = document.querySelectorAll('*');
        
        // Per ogni elemento, rimuovi animazioni e transizioni
        allElements.forEach(function(element) {
            // Rimuovi stili inline di animazione
            if (element.style) {
                element.style.transition = 'none';
                element.style.animation = 'none';
                element.style.transform = 'none';
                element.style.willChange = 'auto';
                element.style.animationDuration = '0s';
                element.style.animationDelay = '0s';
                element.style.transitionDuration = '0s';
                element.style.transitionDelay = '0s';
                
                // Rimuovi anche prefissi vendor
                element.style.webkitTransition = 'none';
                element.style.mozTransition = 'none';
                element.style.oTransition = 'none';
                element.style.msTransition = 'none';
                
                element.style.webkitAnimation = 'none';
                element.style.mozAnimation = 'none';
                element.style.oAnimation = 'none';
                element.style.msAnimation = 'none';
                
                element.style.webkitTransform = 'none';
                element.style.mozTransform = 'none';
                element.style.oTransform = 'none';
                element.style.msTransform = 'none';
            }
        });
        
        // Rimuovi event listeners che potrebbero aggiungere animazioni
        const buttons = document.querySelectorAll('.btn, button, a, input[type="submit"], input[type="button"]');
        buttons.forEach(function(btn) {
            // Clona il bottone per rimuovere TUTTI gli event listeners
            const newBtn = btn.cloneNode(true);
            if (btn.parentNode) {
                btn.parentNode.replaceChild(newBtn, btn);
            }
        });
    }
    
    // Esegui immediatamente
    disableAllAnimations();
    
    // Esegui quando il DOM è pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', disableAllAnimations);
    } else {
        disableAllAnimations();
    }
    
    // Esegui dopo un breve ritardo per catturare elementi caricati dinamicamente
    setTimeout(disableAllAnimations, 100);
    setTimeout(disableAllAnimations, 500);
    setTimeout(disableAllAnimations, 1000);
    
    // Monitora le modifiche al DOM e disabilita animazioni sui nuovi elementi
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes && mutation.addedNodes.length > 0) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1 && node.style) { // Element node
                        node.style.transition = 'none';
                        node.style.animation = 'none';
                        node.style.transform = 'none';
                    }
                });
            }
        });
    });
    
    // Osserva tutto il body
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
    
    // Previeni l'aggiunta di nuove animazioni tramite classList
    const originalAdd = Element.prototype.classList.add;
    Element.prototype.classList.add = function() {
        const args = Array.prototype.slice.call(arguments);
        // Filtra classi che potrebbero aggiungere animazioni
        const filtered = args.filter(function(className) {
            return !className.includes('animate') && 
                   !className.includes('transition') && 
                   !className.includes('fade') &&
                   !className.includes('slide') &&
                   !className.includes('zoom');
        });
        if (filtered.length > 0) {
            originalAdd.apply(this.classList, filtered);
        }
    };
    
    console.log('All animations and transitions have been disabled');
})();