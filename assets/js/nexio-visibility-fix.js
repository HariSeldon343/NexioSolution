/**
 * NEXIO VISIBILITY FIX
 * Script di emergenza per correggere problemi di visibilità
 */

document.addEventListener('DOMContentLoaded', function() {
    // 1. Fix tutti i testi bianchi su sfondo bianco nelle tabelle
    const tables = document.querySelectorAll('table, .table');
    tables.forEach(table => {
        // Trova tutte le celle
        const cells = table.querySelectorAll('td');
        cells.forEach(cell => {
            // Se il testo è bianco o quasi bianco
            const computedStyle = window.getComputedStyle(cell);
            const color = computedStyle.color;
            
            // Controlla se il colore è bianco
            if (color === 'rgb(255, 255, 255)' || 
                color === 'white' || 
                color === '#ffffff' || 
                color === '#fff') {
                
                // Cambia in nero
                cell.style.color = '#1e293b !important';
                
                // Applica anche a tutti i figli
                const children = cell.querySelectorAll('*');
                children.forEach(child => {
                    if (!child.closest('.sidebar')) {
                        child.style.color = '#1e293b !important';
                    }
                });
            }
        });
    });
    
    // 2. Fix specifico per user-name
    const userNames = document.querySelectorAll('td.user-name, td .user-name');
    userNames.forEach(element => {
        if (!element.closest('.sidebar')) {
            element.style.color = '#1e293b';
            element.style.fontWeight = '500';
        }
    });
    
    // 3. Fix per badge nelle tabelle
    const badges = document.querySelectorAll('table .badge, table .role-badge, td .badge, td .role-badge');
    badges.forEach(badge => {
        // Mantieni sfondo colorato ma assicura testo bianco
        badge.style.color = 'white';
        
        // Determina colore sfondo basato sulla classe
        if (badge.classList.contains('role-super_admin') || badge.textContent.includes('Super Admin')) {
            badge.style.backgroundColor = '#7c3aed';
        } else if (badge.classList.contains('role-admin') || badge.textContent.includes('Admin')) {
            badge.style.backgroundColor = '#10b981';
        } else if (badge.classList.contains('role-utente_speciale')) {
            badge.style.backgroundColor = '#f59e0b';
        } else if (badge.classList.contains('role-utente')) {
            badge.style.backgroundColor = '#6b7280';
        }
        
        badge.style.padding = '0.25rem 0.625rem';
        badge.style.borderRadius = '9999px';
        badge.style.fontSize = '0.75rem';
        badge.style.fontWeight = '600';
    });
    
    // 4. Fix per email
    const emails = document.querySelectorAll('td .user-email, table .user-email');
    emails.forEach(email => {
        if (!email.closest('.sidebar')) {
            email.style.color = '#6b7280';
            email.style.fontSize = '0.875rem';
        }
    });
    
    // 5. Fix per icone
    const icons = document.querySelectorAll('table i.fas, table i.far, table i.fa, td i.fas, td i.far, td i.fa');
    icons.forEach(icon => {
        if (!icon.closest('.sidebar') && !icon.closest('.btn')) {
            icon.style.color = '#6b7280';
        }
    });
    
    // 6. Fix per bottoni
    const buttons = document.querySelectorAll('table .btn, td .btn');
    buttons.forEach(btn => {
        btn.style.opacity = '1';
        btn.style.visibility = 'visible';
        
        // Assicura che i bottoni abbiano i colori corretti
        if (btn.classList.contains('btn-primary')) {
            btn.style.backgroundColor = '#3b82f6';
            btn.style.color = 'white';
        } else if (btn.classList.contains('btn-danger')) {
            btn.style.backgroundColor = '#ef4444';
            btn.style.color = 'white';
        } else if (btn.classList.contains('btn-secondary')) {
            btn.style.backgroundColor = '#6b7280';
            btn.style.color = 'white';
        } else if (btn.classList.contains('btn-success')) {
            btn.style.backgroundColor = '#10b981';
            btn.style.color = 'white';
        } else if (btn.classList.contains('btn-warning')) {
            btn.style.backgroundColor = '#f59e0b';
            btn.style.color = 'white';
        }
    });
    
    // 7. Fix per link
    const links = document.querySelectorAll('table a:not(.btn), td a:not(.btn)');
    links.forEach(link => {
        if (!link.closest('.sidebar')) {
            link.style.color = '#3b82f6';
        }
    });
    
    // 8. Rimuovi stili inline problematici
    const elementsWithWhiteColor = document.querySelectorAll('[style*="color: white"], [style*="color:#fff"], [style*="color: #ffffff"]');
    elementsWithWhiteColor.forEach(element => {
        // Solo se non è nella sidebar
        if (!element.closest('.sidebar') && !element.closest('.btn') && !element.closest('.badge')) {
            // Rimuovi lo stile color inline
            const currentStyle = element.getAttribute('style');
            if (currentStyle) {
                const newStyle = currentStyle.replace(/color:\s*(white|#fff|#ffffff|rgb\(255,\s*255,\s*255\))\s*(!important)?;?/gi, 'color: #1e293b;');
                element.setAttribute('style', newStyle);
            }
        }
    });
    
    // 9. Debug - Log elementi problematici (decommentare per debug)
    /*
    const problematicElements = document.querySelectorAll('td');
    problematicElements.forEach(el => {
        const color = window.getComputedStyle(el).color;
        const bgColor = window.getComputedStyle(el).backgroundColor;
        if (color === 'rgb(255, 255, 255)' && (bgColor === 'rgb(255, 255, 255)' || bgColor === 'rgba(0, 0, 0, 0)')) {
            console.warn('Elemento con testo bianco su sfondo bianco trovato:', el);
        }
    });
    */
    
    // 10. Observer per elementi aggiunti dinamicamente
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            mutation.addedNodes.forEach(function(node) {
                if (node.nodeType === 1) { // Element node
                    // Riapplica fix per nuovi elementi
                    if (node.tagName === 'TABLE' || node.classList?.contains('table')) {
                        setTimeout(() => {
                            // Richiama le funzioni di fix per la nuova tabella
                            fixTableColors(node);
                        }, 100);
                    }
                }
            });
        });
    });
    
    // Osserva cambiamenti nel DOM
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
});

// Funzione helper per fixare i colori di una tabella specifica
function fixTableColors(table) {
    const cells = table.querySelectorAll('td');
    cells.forEach(cell => {
        const computedStyle = window.getComputedStyle(cell);
        const color = computedStyle.color;
        
        if (color === 'rgb(255, 255, 255)' || color === 'white') {
            cell.style.color = '#1e293b !important';
            
            const children = cell.querySelectorAll('*');
            children.forEach(child => {
                if (!child.closest('.sidebar') && !child.closest('.btn') && !child.closest('.badge')) {
                    child.style.color = '#1e293b !important';
                }
            });
        }
    });
}