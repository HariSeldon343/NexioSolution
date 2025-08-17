/**
 * TRANSITION PRELOAD MANAGER
 * 
 * Previene animazioni indesiderate durante il caricamento della pagina
 * e durante i cambi di pagina, specialmente l'effetto zoom causato da
 * transition su transform.
 */

(function() {
    'use strict';
    
    // Aggiungi immediatamente la classe preload al body
    document.documentElement.classList.add('preload');
    if (document.body) {
        document.body.classList.add('preload');
    } else {
        // Se il body non è ancora disponibile, aspetta il DOMContentLoaded
        document.addEventListener('DOMContentLoaded', function() {
            document.body.classList.add('preload');
        });
    }
    
    // Rimuovi la classe preload dopo che la pagina è completamente caricata
    window.addEventListener('load', function() {
        setTimeout(function() {
            document.documentElement.classList.remove('preload');
            document.body.classList.remove('preload');
            
            // Log per debug (rimuovere in produzione)
            console.log('Transition preload removed - animations enabled');
        }, 100);
    });
    
    // Gestisci i cambi di pagina (navigation)
    let isNavigating = false;
    
    // Intercetta TUTTI i tipi di navigazione
    window.addEventListener('beforeunload', function() {
        document.body.classList.add('page-transitioning');
        document.body.style.pointerEvents = 'none';
    });
    
    // Intercetta modifiche a window.location
    const originalLocation = window.location;
    Object.defineProperty(window, 'location', {
        get: function() { return originalLocation; },
        set: function(url) {
            document.body.classList.add('page-transitioning');
            setTimeout(() => { originalLocation.href = url; }, 10);
        }
    });
    
    // Intercetta i click sui link per prevenire animazioni durante la navigazione
    document.addEventListener('click', function(e) {
        const link = e.target.closest('a');
        if (link && link.href && !link.target && !link.download) {
            // È un link normale che causa un cambio pagina
            if (!isNavigating) {
                isNavigating = true;
                document.body.classList.add('page-transitioning');
            }
        }
    });
    
    // Intercetta la navigazione tramite form submit
    document.addEventListener('submit', function(e) {
        if (!isNavigating) {
            isNavigating = true;
            document.body.classList.add('page-transitioning');
        }
    });
    
    // Intercetta la navigazione tramite history API
    const originalPushState = history.pushState;
    const originalReplaceState = history.replaceState;
    
    history.pushState = function() {
        document.body.classList.add('page-transitioning');
        originalPushState.apply(history, arguments);
        setTimeout(() => {
            document.body.classList.remove('page-transitioning');
        }, 100);
    };
    
    history.replaceState = function() {
        document.body.classList.add('page-transitioning');
        originalReplaceState.apply(history, arguments);
        setTimeout(() => {
            document.body.classList.remove('page-transitioning');
        }, 100);
    };
    
    // Gestisci il back/forward del browser
    window.addEventListener('popstate', function() {
        document.body.classList.add('page-transitioning');
        setTimeout(() => {
            document.body.classList.remove('page-transitioning');
        }, 100);
    });
    
    // Ottimizzazione performance durante scroll
    let scrollTimeout;
    let isScrolling = false;
    
    window.addEventListener('scroll', function() {
        if (!isScrolling) {
            document.body.classList.add('is-scrolling');
            isScrolling = true;
        }
        
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(function() {
            document.body.classList.remove('is-scrolling');
            isScrolling = false;
        }, 150);
    }, { passive: true });
    
    // Ottimizzazione performance durante resize
    let resizeTimeout;
    let isResizing = false;
    
    window.addEventListener('resize', function() {
        if (!isResizing) {
            document.body.classList.add('is-resizing');
            isResizing = true;
        }
        
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            document.body.classList.remove('is-resizing');
            isResizing = false;
        }, 150);
    });
    
    // Funzione utility per forzare il reset delle transition
    window.resetTransitions = function() {
        document.body.classList.add('preload');
        setTimeout(function() {
            document.body.classList.remove('preload');
        }, 10);
    };
    
    // Esponi una funzione per disabilitare temporaneamente le animazioni
    window.disableAnimations = function(duration = 100) {
        document.body.classList.add('preload');
        setTimeout(function() {
            document.body.classList.remove('preload');
        }, duration);
    };
    
})();