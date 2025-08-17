/**
 * Nexio Mobile JavaScript
 * Funzionalità ottimizzate per mobile/PWA
 */

class NexioMobile {
    constructor() {
        this.isOnline = navigator.onLine;
        this.deferredPrompt = null;
        this.swRegistration = null;
        this.pendingActions = [];
        
        this.init();
    }
    
    init() {
        this.registerServiceWorker();
        this.setupNetworkListeners();
        this.setupInstallPrompt();
        this.setupTouchGestures();
        this.setupPullToRefresh();
        this.setupBackButton();
        this.loadPendingActions();
    }
    
    // Service Worker Registration
    async registerServiceWorker() {
        if (!('serviceWorker' in navigator)) return;
        
        try {
            const registration = await navigator.serviceWorker.register('/piattaforma-collaborativa/mobile/sw.js', {
                scope: '/piattaforma-collaborativa/'
            });
            
            this.swRegistration = registration;
            console.log('ServiceWorker registered:', registration);
            
            // Check for updates
            registration.addEventListener('updatefound', () => {
                const newWorker = registration.installing;
                newWorker.addEventListener('statechange', () => {
                    if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                        this.showUpdatePrompt();
                    }
                });
            });
            
            // Periodic update check
            setInterval(() => {
                registration.update();
            }, 60000); // Check every minute
            
        } catch (error) {
            console.error('ServiceWorker registration failed:', error);
        }
    }
    
    // Network Status
    setupNetworkListeners() {
        window.addEventListener('online', () => {
            this.isOnline = true;
            this.showToast('Connessione ripristinata', 'success');
            this.syncPendingActions();
        });
        
        window.addEventListener('offline', () => {
            this.isOnline = false;
            this.showToast('Modalità offline', 'warning');
        });
    }
    
    // PWA Install Prompt
    setupInstallPrompt() {
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            this.deferredPrompt = e;
            this.showInstallButton();
        });
        
        window.addEventListener('appinstalled', () => {
            console.log('PWA installed');
            this.hideInstallButton();
        });
    }
    
    showInstallButton() {
        const existingBanner = document.getElementById('installBanner');
        if (existingBanner) return;
        
        const banner = document.createElement('div');
        banner.id = 'installBanner';
        banner.className = 'install-banner';
        banner.innerHTML = `
            <div class="install-banner__content">
                <div class="install-banner__text">
                    <div class="install-banner__title">Installa Nexio Mobile</div>
                    <div class="install-banner__subtitle">Accedi rapidamente dalla home</div>
                </div>
                <div class="install-banner__actions">
                    <button class="btn btn--primary" onclick="nexioMobile.installPWA()">Installa</button>
                    <button class="btn btn--secondary" onclick="nexioMobile.dismissInstall()">Dopo</button>
                </div>
            </div>
        `;
        document.body.appendChild(banner);
        
        setTimeout(() => banner.classList.add('show'), 100);
    }
    
    hideInstallButton() {
        const banner = document.getElementById('installBanner');
        if (banner) {
            banner.classList.remove('show');
            setTimeout(() => banner.remove(), 300);
        }
    }
    
    async installPWA() {
        if (!this.deferredPrompt) return;
        
        this.deferredPrompt.prompt();
        const { outcome } = await this.deferredPrompt.userChoice;
        
        if (outcome === 'accepted') {
            console.log('User accepted the install prompt');
            this.trackEvent('pwa_install', 'accepted');
        } else {
            console.log('User dismissed the install prompt');
            this.trackEvent('pwa_install', 'dismissed');
        }
        
        this.deferredPrompt = null;
        this.hideInstallButton();
    }
    
    dismissInstall() {
        this.hideInstallButton();
        this.trackEvent('pwa_install', 'dismissed_banner');
    }
    
    // Touch Gestures
    setupTouchGestures() {
        let touchStartX = 0;
        let touchStartY = 0;
        let touchEndX = 0;
        let touchEndY = 0;
        
        document.addEventListener('touchstart', (e) => {
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
        }, { passive: true });
        
        document.addEventListener('touchend', (e) => {
            touchEndX = e.changedTouches[0].clientX;
            touchEndY = e.changedTouches[0].clientY;
            this.handleSwipe(touchStartX, touchStartY, touchEndX, touchEndY);
        }, { passive: true });
    }
    
    handleSwipe(startX, startY, endX, endY) {
        const diffX = endX - startX;
        const diffY = endY - startY;
        const threshold = 100;
        
        // Horizontal swipe
        if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > threshold) {
            if (diffX > 0) {
                // Swipe right - open menu
                if (startX < 50) {
                    this.openSideMenu();
                }
            } else {
                // Swipe left - close menu
                this.closeSideMenu();
            }
        }
    }
    
    // Pull to Refresh
    setupPullToRefresh() {
        let touchStartY = 0;
        let touchEndY = 0;
        const threshold = 150;
        
        document.addEventListener('touchstart', (e) => {
            if (window.scrollY === 0) {
                touchStartY = e.touches[0].clientY;
            }
        }, { passive: true });
        
        document.addEventListener('touchmove', (e) => {
            if (window.scrollY === 0 && touchStartY > 0) {
                touchEndY = e.touches[0].clientY;
                const diff = touchEndY - touchStartY;
                
                if (diff > 0 && diff < threshold) {
                    // Show pull indicator
                    this.showPullIndicator(diff / threshold);
                }
            }
        }, { passive: true });
        
        document.addEventListener('touchend', (e) => {
            if (touchStartY > 0 && touchEndY > 0) {
                const diff = touchEndY - touchStartY;
                
                if (diff > threshold) {
                    this.refresh();
                }
                
                this.hidePullIndicator();
                touchStartY = 0;
                touchEndY = 0;
            }
        }, { passive: true });
    }
    
    showPullIndicator(progress) {
        let indicator = document.getElementById('pullIndicator');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'pullIndicator';
            indicator.className = 'pull-indicator';
            indicator.innerHTML = '<div class="spinner"></div>';
            document.body.appendChild(indicator);
        }
        
        indicator.style.opacity = progress;
        indicator.style.transform = `translateY(${progress * 50}px)`;
    }
    
    hidePullIndicator() {
        const indicator = document.getElementById('pullIndicator');
        if (indicator) {
            indicator.style.opacity = 0;
            setTimeout(() => indicator.remove(), 300);
        }
    }
    
    refresh() {
        this.showToast('Aggiornamento...', 'info');
        location.reload();
    }
    
    // Back Button Handling
    setupBackButton() {
        window.addEventListener('popstate', (e) => {
            if (this.isSideMenuOpen()) {
                e.preventDefault();
                this.closeSideMenu();
            }
        });
    }
    
    // Side Menu
    openSideMenu() {
        const menu = document.querySelector('.side-menu');
        const overlay = document.querySelector('.menu-overlay');
        
        if (menu) {
            menu.classList.add('open');
            document.body.style.overflow = 'hidden';
        }
        
        if (overlay) {
            overlay.classList.add('open');
        }
    }
    
    closeSideMenu() {
        const menu = document.querySelector('.side-menu');
        const overlay = document.querySelector('.menu-overlay');
        
        if (menu) {
            menu.classList.remove('open');
            document.body.style.overflow = '';
        }
        
        if (overlay) {
            overlay.classList.remove('open');
        }
    }
    
    isSideMenuOpen() {
        const menu = document.querySelector('.side-menu');
        return menu && menu.classList.contains('open');
    }
    
    toggleSideMenu() {
        if (this.isSideMenuOpen()) {
            this.closeSideMenu();
        } else {
            this.openSideMenu();
        }
    }
    
    // Toast Notifications
    showToast(message, type = 'info', duration = 3000) {
        const existingToast = document.querySelector('.toast.show');
        if (existingToast) {
            existingToast.remove();
        }
        
        const toast = document.createElement('div');
        toast.className = `toast toast--${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => toast.classList.add('show'), 10);
        
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }
    
    // Update Prompt
    showUpdatePrompt() {
        const modal = document.createElement('div');
        modal.className = 'modal-overlay active';
        modal.innerHTML = `
            <div class="modal">
                <h3>Aggiornamento disponibile</h3>
                <p>È disponibile una nuova versione dell'app. Vuoi aggiornare ora?</p>
                <div style="display: flex; gap: 12px; margin-top: 20px;">
                    <button class="btn btn--primary btn--block" onclick="nexioMobile.updateApp()">Aggiorna</button>
                    <button class="btn btn--secondary btn--block" onclick="this.closest('.modal-overlay').remove()">Dopo</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    
    updateApp() {
        if (this.swRegistration && this.swRegistration.waiting) {
            this.swRegistration.waiting.postMessage({ type: 'SKIP_WAITING' });
            window.location.reload();
        }
    }
    
    // Offline Actions Queue
    addPendingAction(action) {
        this.pendingActions.push({
            ...action,
            timestamp: Date.now()
        });
        this.savePendingActions();
    }
    
    savePendingActions() {
        localStorage.setItem('nexio_pending_actions', JSON.stringify(this.pendingActions));
    }
    
    loadPendingActions() {
        const saved = localStorage.getItem('nexio_pending_actions');
        if (saved) {
            this.pendingActions = JSON.parse(saved);
        }
    }
    
    async syncPendingActions() {
        if (!this.isOnline || this.pendingActions.length === 0) return;
        
        console.log('Syncing pending actions:', this.pendingActions.length);
        
        for (const action of this.pendingActions) {
            try {
                await this.executeAction(action);
                this.pendingActions = this.pendingActions.filter(a => a !== action);
            } catch (error) {
                console.error('Failed to sync action:', error);
            }
        }
        
        this.savePendingActions();
        
        if (this.pendingActions.length === 0) {
            this.showToast('Sincronizzazione completata', 'success');
        }
    }
    
    async executeAction(action) {
        const response = await fetch(action.url, {
            method: action.method || 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.getCSRFToken(),
                ...action.headers
            },
            body: JSON.stringify(action.data)
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return await response.json();
    }
    
    // CSRF Token
    getCSRFToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }
    
    // API Helpers
    async apiCall(url, options = {}) {
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.getCSRFToken()
            }
        };
        
        const finalOptions = {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...options.headers
            }
        };
        
        if (!this.isOnline) {
            // Queue for later if offline
            this.addPendingAction({
                url,
                ...finalOptions
            });
            
            throw new Error('Offline - action queued');
        }
        
        try {
            const response = await fetch(url, finalOptions);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            if (!this.isOnline) {
                this.addPendingAction({
                    url,
                    ...finalOptions
                });
            }
            throw error;
        }
    }
    
    // Analytics
    trackEvent(category, action, label = null, value = null) {
        if (typeof gtag !== 'undefined') {
            gtag('event', action, {
                event_category: category,
                event_label: label,
                value: value
            });
        }
        
        console.log('Track event:', { category, action, label, value });
    }
    
    // Vibration API
    vibrate(pattern = 50) {
        if ('vibrate' in navigator) {
            navigator.vibrate(pattern);
        }
    }
    
    // Share API
    async share(data) {
        if (!navigator.share) {
            console.log('Web Share API not supported');
            return false;
        }
        
        try {
            await navigator.share(data);
            this.trackEvent('share', 'success');
            return true;
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error('Share failed:', error);
            }
            return false;
        }
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.nexioMobile = new NexioMobile();
    });
} else {
    window.nexioMobile = new NexioMobile();
}

// Export for modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = NexioMobile;
}