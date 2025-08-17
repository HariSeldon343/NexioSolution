/**
 * Sidebar Dropdown Fix
 * Garantisce che il dropdown del footer della sidebar funzioni correttamente
 */

document.addEventListener('DOMContentLoaded', function() {
    // Trova il trigger del dropdown nella sidebar
    const dropdownTrigger = document.querySelector('.sidebar-footer .user-menu-trigger');
    const dropdown = dropdownTrigger ? dropdownTrigger.closest('.dropdown') : null;
    
    if (dropdownTrigger && dropdown) {
        // Rimuovi handler esistenti per evitare duplicati
        const newTrigger = dropdownTrigger.cloneNode(true);
        dropdownTrigger.parentNode.replaceChild(newTrigger, dropdownTrigger);
        
        // Aggiungi nuovo handler
        newTrigger.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const dropdownMenu = dropdown.querySelector('.dropdown-menu');
            if (dropdownMenu) {
                // Toggle visibility
                const isShown = dropdownMenu.classList.contains('show');
                
                // Chiudi tutti gli altri dropdown
                document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                    menu.classList.remove('show');
                });
                
                if (!isShown) {
                    dropdownMenu.classList.add('show');
                    
                    // Posiziona il menu sopra il trigger
                    const triggerRect = newTrigger.getBoundingClientRect();
                    const menuHeight = dropdownMenu.offsetHeight;
                    
                    // Verifica se c'è spazio sopra
                    if (triggerRect.top > menuHeight + 10) {
                        dropdownMenu.style.bottom = '100%';
                        dropdownMenu.style.top = 'auto';
                        dropdownMenu.style.left = 'auto';
                        dropdownMenu.style.marginLeft = '';
                    } else {
                        // Se non c'è spazio sopra, apri a lato
                        dropdownMenu.style.bottom = 'auto';
                        dropdownMenu.style.top = '0';
                        dropdownMenu.style.left = '100%';
                        dropdownMenu.style.marginLeft = '10px';
                    }
                }
            }
        });
        
        // Chiudi il dropdown quando si clicca fuori
        document.addEventListener('click', function(e) {
            if (!dropdown.contains(e.target)) {
                const dropdownMenu = dropdown.querySelector('.dropdown-menu');
                if (dropdownMenu) {
                    dropdownMenu.classList.remove('show');
                }
            }
        });
        
        // Gestisci i click sui menu items
        const menuItems = dropdown.querySelectorAll('.dropdown-item');
        menuItems.forEach(item => {
            item.addEventListener('click', function(e) {
                // Se è un link, lascia che segua il suo corso
                if (this.href && !this.href.includes('#')) {
                    return true;
                }
                
                // Altrimenti chiudi il dropdown
                const dropdownMenu = dropdown.querySelector('.dropdown-menu');
                if (dropdownMenu) {
                    dropdownMenu.classList.remove('show');
                }
            });
        });
    }
    
    // Fix per il calcolo dell'altezza della sidebar (solo mobile)
    function adjustSidebarHeight() {
        const sidebar = document.querySelector('.sidebar');
        if (!sidebar) return;
        const menu = sidebar.querySelector('.sidebar-menu');
        const header = sidebar.querySelector('.sidebar-header');
        const footer = sidebar.querySelector('.sidebar-footer');
        
        if (window.innerWidth <= 768) {
            const windowHeight = window.innerHeight;
            sidebar.style.height = windowHeight + 'px';
            if (menu && header && footer) {
                const availableHeight = windowHeight - header.offsetHeight - footer.offsetHeight;
                menu.style.maxHeight = availableHeight + 'px';
            }
        } else {
            // Desktop: nessuna altezza fissa, lascia crescere naturalmente
            sidebar.style.height = '';
            if (menu) menu.style.maxHeight = '';
        }
    }
    
    // Aggiusta all'avvio e al ridimensionamento
    adjustSidebarHeight();
    window.addEventListener('resize', adjustSidebarHeight);
    
    // Monitora cambiamenti nel DOM che potrebbero influire sull'altezza (solo mobile)
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        const observer = new MutationObserver(adjustSidebarHeight);
        observer.observe(sidebar, { childList: true, subtree: true, attributes: true, attributeFilter: ['style', 'class'] });
    }
});