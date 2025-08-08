/**
 * Nexio PWA Main App JavaScript
 * Handles PWA functionality, install prompts, and app state
 */

class NexioPWA {
    constructor() {
        this.deferredPrompt = null;
        this.isOnline = navigator.onLine;
        this.installBanner = document.getElementById('installBanner');
        this.installBtn = document.getElementById('installBtn');
        this.dismissBtn = document.getElementById('dismissBtn');
        this.offlineBanner = document.getElementById('offlineBanner');
        this.loadingOverlay = document.getElementById('loadingOverlay');
        this.currentUser = null;
        this.debugMode = localStorage.getItem('nexio-debug') === 'true' || window.location.search.includes('debug=true');
        
        if (this.debugMode) {
            this.initDebugMode();
        }
        
        this.init();
    }

    init() {
        console.log('Nexio PWA: Initializing...');
        
        // Check authentication first
        this.checkAuthentication()
            .then(() => {
                // Setup event listeners
                this.setupPWAEvents();
                this.setupNavigationEvents();
                this.setupOnlineOfflineEvents();
                this.setupUIEvents();
                
                // Check if app is already installed
                this.checkInstallStatus();
                
                // Initialize app state
                this.updateOnlineStatus();
                
                // Hide loading overlay
                this.hideLoadingOverlay();
                
                console.log('Nexio PWA: Initialized successfully');
            })
            .catch((error) => {
                console.error('Nexio PWA: Authentication failed:', error);
                this.handleAuthenticationFailure();
            });
    }

    setupPWAEvents() {
        // Before install prompt event
        window.addEventListener('beforeinstallprompt', (e) => {
            console.log('PWA: Before install prompt triggered');
            e.preventDefault();
            this.deferredPrompt = e;
            this.showInstallBanner();
        });

        // App installed event
        window.addEventListener('appinstalled', () => {
            console.log('PWA: App was installed');
            this.hideInstallBanner();
            this.deferredPrompt = null;
            this.trackInstallation('installed');
            
            // Show success message
            this.showToast('App installata con successo!', 'success');
        });

        // Install button click
        if (this.installBtn) {
            this.installBtn.addEventListener('click', () => {
                this.promptInstall();
            });
        }

        // Dismiss banner
        if (this.dismissBtn) {
            this.dismissBtn.addEventListener('click', () => {
                this.hideInstallBanner();
                this.trackInstallation('dismissed');
            });
        }
    }

    setupNavigationEvents() {
        // Tab navigation
        const tabButtons = document.querySelectorAll('[data-bs-toggle="pill"]');
        const bottomNavButtons = document.querySelectorAll('.nav-btn');

        tabButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                const target = e.target.getAttribute('data-bs-target');
                this.updateBottomNavigation(target);
            });
        });

        bottomNavButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                const target = button.getAttribute('data-target');
                this.switchTab(target);
                this.updateBottomNavigation(target);
            });
        });
    }

    setupOnlineOfflineEvents() {
        window.addEventListener('online', () => {
            this.isOnline = true;
            this.updateOnlineStatus();
            this.syncOfflineData();
        });

        window.addEventListener('offline', () => {
            this.isOnline = false;
            this.updateOnlineStatus();
        });
    }

    setupUIEvents() {
        // Setup dropdown menus
        this.setupDropdowns();
        
        // Setup tab functionality
        this.setupTabs();
        
        // Pull to refresh
        let startY = 0;
        let scrollTop = 0;

        document.addEventListener('touchstart', (e) => {
            startY = e.touches[0].pageY;
            scrollTop = window.scrollY;
        });

        document.addEventListener('touchmove', (e) => {
            if (scrollTop === 0 && e.touches[0].pageY > startY + 50) {
                this.showLoadingOverlay();
                setTimeout(() => {
                    this.hideLoadingOverlay();
                    this.refreshData();
                }, 1000);
            }
        });

        // Handle back button
        window.addEventListener('popstate', (e) => {
            // Handle navigation history
            console.log('PWA: Navigation back');
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                    menu.classList.remove('show');
                });
            }
        });
    }

    setupDropdowns() {
        document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(toggle => {
            toggle.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                const menu = toggle.nextElementSibling;
                if (menu && menu.classList.contains('dropdown-menu')) {
                    // Close other dropdowns
                    document.querySelectorAll('.dropdown-menu.show').forEach(otherMenu => {
                        if (otherMenu !== menu) {
                            otherMenu.classList.remove('show');
                        }
                    });
                    
                    // Toggle current dropdown
                    menu.classList.toggle('show');
                }
            });
        });
    }

    setupTabs() {
        document.querySelectorAll('[data-bs-toggle="pill"]').forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                
                const target = tab.getAttribute('data-bs-target');
                if (target) {
                    // Remove active class from all tabs and panes
                    document.querySelectorAll('[data-bs-toggle="pill"]').forEach(t => {
                        t.classList.remove('active');
                        t.setAttribute('aria-selected', 'false');
                    });
                    
                    document.querySelectorAll('.tab-pane').forEach(pane => {
                        pane.classList.remove('show', 'active');
                    });
                    
                    // Add active class to clicked tab
                    tab.classList.add('active');
                    tab.setAttribute('aria-selected', 'true');
                    
                    // Show target pane
                    const targetPane = document.querySelector(target);
                    if (targetPane) {
                        targetPane.classList.add('show', 'active');
                    }
                }
            });
        });
    }

    // Debug Mode
    initDebugMode() {
        console.log('PWA: Debug mode enabled');
        
        // Add debug panel to page
        const debugPanel = document.createElement('div');
        debugPanel.id = 'debug-panel';
        debugPanel.innerHTML = `
            <div style="position: fixed; bottom: 90px; left: 10px; right: 10px; background: rgba(0,0,0,0.8); color: white; padding: 10px; border-radius: 8px; font-family: monospace; font-size: 12px; z-index: 10000; max-height: 200px; overflow-y: auto;">
                <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 10px;">
                    <strong>Debug Console</strong>
                    <button onclick="this.parentElement.parentElement.parentElement.remove()" style="background: red; color: white; border: none; border-radius: 4px; padding: 2px 6px; margin-left: auto;">X</button>
                </div>
                <div id="debug-log" style="white-space: pre-wrap; word-break: break-all;"></div>
            </div>
        `;
        document.body.appendChild(debugPanel);
        
        // Override console.log, console.error for debug panel
        const debugLog = document.getElementById('debug-log');
        const originalLog = console.log;
        const originalError = console.error;
        
        console.log = (...args) => {
            originalLog.apply(console, args);
            if (debugLog) {
                debugLog.innerHTML += new Date().toLocaleTimeString() + ' LOG: ' + args.join(' ') + '\\n';
                debugLog.scrollTop = debugLog.scrollHeight;
            }
        };
        
        console.error = (...args) => {
            originalError.apply(console, args);
            if (debugLog) {
                debugLog.innerHTML += new Date().toLocaleTimeString() + ' ERROR: ' + args.join(' ') + '\\n';
                debugLog.scrollTop = debugLog.scrollHeight;
            }
        };
        
        // Add debug info
        console.log('User Agent:', navigator.userAgent);
        console.log('Online:', navigator.onLine);
        console.log('Service Worker:', 'serviceWorker' in navigator);
        console.log('Current URL:', window.location.href);
    }

    // Authentication Methods
    async checkAuthentication() {
        try {
            console.log('PWA: Checking authentication...');
            
            const response = await fetch('../backend/api/check-auth.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            
            if (data.authenticated) {
                console.log('PWA: User authenticated:', data.user);
                this.currentUser = data.user;
                return true;
            } else {
                throw new Error('Utente non autenticato');
            }
        } catch (error) {
            console.error('PWA: Authentication check failed:', error);
            throw error;
        }
    }

    handleAuthenticationFailure() {
        this.hideLoadingOverlay();
        
        // Show authentication error message
        const authError = `
            <div class="auth-error">
                <div class="text-center py-4">
                    <i class="fas fa-lock fa-3x text-muted mb-3"></i>
                    <h4>Accesso Richiesto</h4>
                    <p class="text-muted mb-4">Devi effettuare l'accesso per utilizzare l'app.</p>
                    <button class="btn btn-primary" onclick="window.location.href='../login.php'">
                        Accedi
                    </button>
                </div>
            </div>
        `;
        
        document.querySelector('.app-main').innerHTML = authError;
        
        // Hide header elements that require authentication
        document.querySelector('#userMenu').style.display = 'none';
        document.querySelector('.nav-pills-container').style.display = 'none';
        document.querySelector('.app-bottom-nav').style.display = 'none';
    }

    // PWA Installation Methods
    async promptInstall() {
        if (!this.deferredPrompt) {
            console.log('PWA: No deferred prompt available');
            this.showToast('Installazione non disponibile', 'warning');
            return;
        }

        console.log('PWA: Showing install prompt');
        this.hideInstallBanner();

        try {
            const result = await this.deferredPrompt.prompt();
            console.log('PWA: Install prompt result:', result.outcome);
            
            this.trackInstallation(result.outcome);
            
            if (result.outcome === 'accepted') {
                this.showToast('App in fase di installazione...', 'info');
            } else {
                this.showToast('Installazione annullata', 'info');
            }
        } catch (error) {
            console.error('PWA: Install prompt failed:', error);
            this.showToast('Errore durante l\'installazione', 'error');
        }

        this.deferredPrompt = null;
    }

    showInstallBanner() {
        if (this.installBanner && !this.isAppInstalled()) {
            this.installBanner.classList.remove('d-none');
            console.log('PWA: Install banner shown');
        }
    }

    hideInstallBanner() {
        if (this.installBanner) {
            this.installBanner.classList.add('d-none');
            console.log('PWA: Install banner hidden');
        }
    }

    checkInstallStatus() {
        // Check if app is running in standalone mode (installed)
        if (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) {
            console.log('PWA: App is running in standalone mode');
            this.hideInstallBanner();
            return true;
        }

        // Check iOS standalone
        if (window.navigator.standalone === true) {
            console.log('PWA: App is running in iOS standalone mode');
            this.hideInstallBanner();
            return true;
        }

        return false;
    }

    isAppInstalled() {
        return this.checkInstallStatus();
    }

    trackInstallation(outcome) {
        console.log('PWA: Installation tracking:', outcome);
        
        // Send analytics if available
        if (typeof gtag !== 'undefined') {
            gtag('event', 'pwa_install_prompt', {
                'outcome': outcome
            });
        }

        // Store in localStorage
        localStorage.setItem('nexio_pwa_install_outcome', outcome);
        localStorage.setItem('nexio_pwa_install_date', new Date().toISOString());
    }

    // Navigation Methods
    switchTab(targetPane) {
        // Hide all tab panes
        document.querySelectorAll('.tab-pane').forEach(pane => {
            pane.classList.remove('show', 'active');
        });

        // Show target pane
        const target = document.getElementById(targetPane);
        if (target) {
            target.classList.add('show', 'active');
        }

        // Update tab buttons
        document.querySelectorAll('[data-bs-toggle="pill"]').forEach(btn => {
            btn.classList.remove('active');
            if (btn.getAttribute('data-bs-target') === '#' + targetPane) {
                btn.classList.add('active');
            }
        });
    }

    updateBottomNavigation(targetPane) {
        document.querySelectorAll('.nav-btn').forEach(btn => {
            btn.classList.remove('active');
            if (btn.getAttribute('data-target') === targetPane.replace('#', '')) {
                btn.classList.add('active');
            }
        });
    }

    // Online/Offline Status
    updateOnlineStatus() {
        if (this.isOnline) {
            this.offlineBanner.classList.add('d-none');
            console.log('PWA: Online mode');
        } else {
            this.offlineBanner.classList.remove('d-none');
            console.log('PWA: Offline mode');
        }
    }

    async syncOfflineData() {
        console.log('PWA: Syncing offline data...');
        
        try {
            // Sync calendar data
            if (window.CalendarManager) {
                await window.CalendarManager.syncOfflineChanges();
            }

            // Sync tasks data
            if (window.TasksManager) {
                await window.TasksManager.syncOfflineChanges();
            }

            this.showToast('Dati sincronizzati', 'success');
        } catch (error) {
            console.error('PWA: Sync failed:', error);
            this.showToast('Errore nella sincronizzazione', 'error');
        }
    }

    // Loading States
    showLoadingOverlay() {
        if (this.loadingOverlay) {
            this.loadingOverlay.classList.remove('d-none');
        }
    }

    hideLoadingOverlay() {
        if (this.loadingOverlay) {
            this.loadingOverlay.classList.add('d-none');
        }
    }

    // Data Refresh
    async refreshData() {
        console.log('PWA: Refreshing data...');
        
        try {
            // Refresh calendar
            if (window.CalendarManager) {
                await window.CalendarManager.refresh();
            }

            // Refresh tasks
            if (window.TasksManager) {
                await window.TasksManager.refresh();
            }

            this.showToast('Dati aggiornati', 'success');
        } catch (error) {
            console.error('PWA: Refresh failed:', error);
            this.showToast('Errore nell\'aggiornamento', 'error');
        }
    }

    // Toast Notifications
    showToast(message, type = 'info') {
        // Create toast element if it doesn't exist
        let toastContainer = document.getElementById('toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toast-container';
            toastContainer.className = 'position-fixed top-0 end-0 p-3';
            toastContainer.style.zIndex = '1200';
            document.body.appendChild(toastContainer);
        }

        const toastId = 'toast-' + Date.now();
        const toastHTML = `
            <div id="${toastId}" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header bg-${type} text-white">
                    <strong class="me-auto">Nexio</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    ${message}
                </div>
            </div>
        `;

        toastContainer.insertAdjacentHTML('beforeend', toastHTML);

        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement);
        toast.show();

        // Remove toast after it's hidden
        toastElement.addEventListener('hidden.bs.toast', () => {
            toastElement.remove();
        });
    }
}

// Global Functions
window.showProfile = function() {
    console.log('Showing profile...');
    // Implementation for profile view
};

window.showSettings = function() {
    console.log('Showing settings...');
    // Implementation for settings view
};

window.logout = function() {
    console.log('Logging out...');
    if (confirm('Sei sicuro di voler effettuare il logout?')) {
        // Clear app data
        localStorage.clear();
        sessionStorage.clear();
        
        // Redirect to login
        window.location.href = '../login.php';
    }
};

window.addNewTask = function() {
    console.log('Adding new task...');
    if (window.TasksManager) {
        window.TasksManager.showNewTaskForm();
    }
};

// Initialize PWA when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM loaded, initializing Nexio PWA...');
    window.nexioPWA = new NexioPWA();
});

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = NexioPWA;
}