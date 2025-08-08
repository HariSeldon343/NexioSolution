/**
 * Nexio Calendar Mobile PWA - Main Application
 * Gestisce tutte le funzionalità del calendario mobile
 */

class NexioCalendarApp {
    constructor() {
        this.currentDate = new Date();
        this.currentView = 'month';
        this.events = [];
        this.user = null;
        this.azienda = null;
        this.isOnline = navigator.onLine;
        this.syncInProgress = false;
        this.lastSyncTime = null;
        
        // DOM elements
        this.elements = {};
        
        // Event listeners
        this.eventListeners = [];
        
        // Initialize app
        this.init();
    }
    
    async init() {
        try {
            // Cache DOM elements
            this.cacheElements();
            
            // Setup event listeners
            this.setupEventListeners();
            
            // Check authentication
            await this.checkAuthentication();
            
            // Load user preferences
            await this.loadPreferences();
            
            // Initial data load
            await this.loadInitialData();
            
            // Setup periodic sync
            this.setupPeriodicSync();
            
            // Hide loading screen
            this.hideLoadingScreen();
            
            // Show install banner if appropriate
            this.checkInstallBanner();
            
            console.log('[App] Nexio Calendar initialized successfully');
            
        } catch (error) {
            console.error('[App] Initialization error:', error);
            this.showToast('Errore durante l\'inizializzazione dell\'app', 'error');
        }
    }
    
    cacheElements() {
        this.elements = {
            // Navigation
            navbar: document.getElementById('navbar'),
            navTitle: document.getElementById('navTitle'),
            syncBtn: document.getElementById('syncBtn'),
            addEventBtn: document.getElementById('addEventBtn'),
            menuBtn: document.getElementById('menuBtn'),
            
            // View controls
            viewControls: document.getElementById('viewControls'),
            monthViewBtn: document.getElementById('monthViewBtn'),
            weekViewBtn: document.getElementById('weekViewBtn'),
            dayViewBtn: document.getElementById('dayViewBtn'),
            listViewBtn: document.getElementById('listViewBtn'),
            prevBtn: document.getElementById('prevBtn'),
            nextBtn: document.getElementById('nextBtn'),
            todayBtn: document.getElementById('todayBtn'),
            currentPeriod: document.getElementById('currentPeriod'),
            
            // Calendar views
            calendarContainer: document.getElementById('calendarContainer'),
            loadingEvents: document.getElementById('loadingEvents'),
            monthView: document.getElementById('monthView'),
            weekView: document.getElementById('weekView'),
            dayView: document.getElementById('dayView'),
            listView: document.getElementById('listView'),
            monthGrid: document.getElementById('monthGrid'),
            weekGrid: document.getElementById('weekGrid'),
            dayGrid: document.getElementById('dayGrid'),
            eventsList: document.getElementById('eventsList'),
            
            // Side menu
            sideMenu: document.getElementById('sideMenu'),
            closeSideMenu: document.getElementById('closeSideMenu'),
            menuOverlay: null, // Will be created dynamically
            refreshBtn: document.getElementById('refreshBtn'),
            exportBtn: document.getElementById('exportBtn'),
            notificationsBtn: document.getElementById('notificationsBtn'),
            notificationToggle: document.getElementById('notificationToggle'),
            themeBtn: document.getElementById('themeBtn'),
            themeToggle: document.getElementById('themeToggle'),
            userInfo: document.getElementById('userInfo'),
            logoutBtn: document.getElementById('logoutBtn'),
            connectionStatus: document.getElementById('connectionStatus'),
            
            // Event modal
            eventModal: document.getElementById('eventModal'),
            eventModalTitle: document.getElementById('eventModalTitle'),
            eventForm: document.getElementById('eventForm'),
            eventId: document.getElementById('eventId'),
            eventTitle: document.getElementById('eventTitle'),
            eventDescription: document.getElementById('eventDescription'),
            eventStartDate: document.getElementById('eventStartDate'),
            eventStartTime: document.getElementById('eventStartTime'),
            eventEndDate: document.getElementById('eventEndDate'),
            eventEndTime: document.getElementById('eventEndTime'),
            eventLocation: document.getElementById('eventLocation'),
            eventType: document.getElementById('eventType'),
            sendNotifications: document.getElementById('sendNotifications'),
            saveEventBtn: document.getElementById('saveEventBtn'),
            deleteEventBtn: document.getElementById('deleteEventBtn'),
            
            // Banners
            offlineBanner: document.getElementById('offlineBanner'),
            installBanner: document.getElementById('installBanner'),
            installBtn: document.getElementById('installBtn'),
            dismissInstallBtn: document.getElementById('dismissInstallBtn'),
            
            // Toast container
            toastContainer: document.getElementById('toastContainer')
        };
        
        // Create menu overlay
        this.elements.menuOverlay = document.createElement('div');
        this.elements.menuOverlay.className = 'menu-overlay';
        this.elements.menuOverlay.id = 'menuOverlay';
        document.body.appendChild(this.elements.menuOverlay);
    }
    
    setupEventListeners() {
        // Navigation events
        this.addListener(this.elements.syncBtn, 'click', () => this.syncData());
        this.addListener(this.elements.addEventBtn, 'click', () => this.showEventModal());
        this.addListener(this.elements.menuBtn, 'click', () => this.toggleSideMenu());
        
        // View control events
        this.addListener(this.elements.monthViewBtn, 'click', () => this.switchView('month'));
        this.addListener(this.elements.weekViewBtn, 'click', () => this.switchView('week'));
        this.addListener(this.elements.dayViewBtn, 'click', () => this.switchView('day'));
        this.addListener(this.elements.listViewBtn, 'click', () => this.switchView('list'));
        this.addListener(this.elements.prevBtn, 'click', () => this.navigatePrev());
        this.addListener(this.elements.nextBtn, 'click', () => this.navigateNext());
        this.addListener(this.elements.todayBtn, 'click', () => this.navigateToday());
        
        // Side menu events
        this.addListener(this.elements.closeSideMenu, 'click', () => this.closeSideMenu());
        this.addListener(this.elements.menuOverlay, 'click', () => this.closeSideMenu());
        this.addListener(this.elements.refreshBtn, 'click', () => this.refreshData());
        this.addListener(this.elements.exportBtn, 'click', () => this.exportCalendar());
        this.addListener(this.elements.notificationsBtn, 'click', () => this.toggleNotifications());
        this.addListener(this.elements.themeBtn, 'click', () => this.toggleTheme());
        this.addListener(this.elements.logoutBtn, 'click', () => this.logout());
        
        // Event modal events
        this.addListener(this.elements.saveEventBtn, 'click', () => this.saveEvent());
        this.addListener(this.elements.deleteEventBtn, 'click', () => this.deleteEvent());
        
        // Install banner events
        if (this.elements.dismissInstallBtn) {
            this.addListener(this.elements.dismissInstallBtn, 'click', () => {
                this.elements.installBanner.style.display = 'none';
                localStorage.setItem('installBannerDismissed', 'true');
            });
        }
        
        // Online/offline events
        this.addListener(window, 'online', () => this.handleOnlineStatus(true));
        this.addListener(window, 'offline', () => this.handleOnlineStatus(false));
        
        // Service worker messages
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.addEventListener('message', (event) => {
                this.handleServiceWorkerMessage(event.data);
            });
        }
        
        // Touch events for mobile gestures
        this.setupTouchEvents();
        
        // Keyboard shortcuts
        this.addListener(document, 'keydown', (e) => this.handleKeyboardShortcuts(e));
    }
    
    addListener(element, event, handler) {
        if (element) {
            element.addEventListener(event, handler);
            this.eventListeners.push({ element, event, handler });
        }
    }
    
    setupTouchEvents() {
        let startX, startY;
        
        this.addListener(this.elements.calendarContainer, 'touchstart', (e) => {
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
        });
        
        this.addListener(this.elements.calendarContainer, 'touchend', (e) => {
            if (!startX || !startY) return;
            
            const endX = e.changedTouches[0].clientX;
            const endY = e.changedTouches[0].clientY;
            
            const diffX = startX - endX;
            const diffY = startY - endY;
            
            if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 50) {
                if (diffX > 0) {
                    this.navigateNext();
                } else {
                    this.navigatePrev();
                }
            }
        });
    }
    
    handleKeyboardShortcuts(e) {
        if (e.ctrlKey || e.metaKey) {
            switch (e.key) {
                case 'n':
                    e.preventDefault();
                    this.showEventModal();
                    break;
                case 'r':
                    e.preventDefault();
                    this.refreshData();
                    break;
            }
        }
        
        switch (e.key) {
            case 'ArrowLeft':
                if (!this.isModalOpen()) {
                    this.navigatePrev();
                }
                break;
            case 'ArrowRight':
                if (!this.isModalOpen()) {
                    this.navigateNext();
                }
                break;
            case 't':
                if (!this.isModalOpen()) {
                    this.navigateToday();
                }
                break;
            case 'm':
                if (!this.isModalOpen()) {
                    this.switchView('month');
                }
                break;
            case 'w':
                if (!this.isModalOpen()) {
                    this.switchView('week');
                }
                break;
            case 'd':
                if (!this.isModalOpen()) {
                    this.switchView('day');
                }
                break;
            case 'l':
                if (!this.isModalOpen()) {
                    this.switchView('list');
                }
                break;
        }
    }
    
    isModalOpen() {
        return document.querySelector('.modal.show') !== null;
    }
    
    async checkAuthentication() {
        try {
            const response = await this.apiCall('/backend/api/calendar-api.php?action=status');
            
            if (response.success) {
                this.user = response.status.user;
                this.azienda = response.status.azienda;
                this.updateUserInterface();
            } else {
                // Redirect to login if not authenticated
                this.redirectToLogin();
            }
        } catch (error) {
            console.error('[Auth] Authentication check failed:', error);
            this.redirectToLogin();
        }
    }
    
    redirectToLogin() {
        window.location.href = '../login.php';
    }
    
    updateUserInterface() {
        if (this.user) {
            const userName = `${this.user.nome} ${this.user.cognome}`;
            const userRole = this.user.ruolo;
            
            // Update user info in side menu
            const userNameEl = this.elements.userInfo.querySelector('.user-name');
            const userRoleEl = this.elements.userInfo.querySelector('.user-role');
            
            if (userNameEl) userNameEl.textContent = userName;
            if (userRoleEl) userRoleEl.textContent = this.formatUserRole(userRole);
        }
        
        if (this.azienda) {
            this.elements.navTitle.textContent = `Nexio - ${this.azienda.nome}`;
        }
    }
    
    formatUserRole(role) {
        const roles = {
            'super_admin': 'Super Admin',
            'utente_speciale': 'Utente Speciale',
            'admin': 'Admin',
            'staff': 'Staff',
            'cliente': 'Cliente'
        };
        return roles[role] || role;
    }
    
    async loadPreferences() {
        try {
            const response = await this.apiCall('/backend/api/calendar-api.php?action=preferences');
            
            if (response.success) {
                const prefs = response.preferences;
                
                // Apply preferences
                this.currentView = prefs.defaultView || 'month';
                
                if (prefs.theme === 'dark') {
                    document.documentElement.setAttribute('data-theme', 'dark');
                    this.elements.themeToggle.innerHTML = '<i class="fas fa-toggle-on"></i>';
                }
                
                if (prefs.notifications) {
                    this.elements.notificationToggle.innerHTML = '<i class="fas fa-toggle-on"></i>';
                }
                
                // Update view button
                this.updateActiveViewButton();
            }
        } catch (error) {
            console.log('[Preferences] Could not load preferences, using defaults');
        }
    }
    
    async savePreferences() {
        const preferences = {
            defaultView: this.currentView,
            theme: document.documentElement.getAttribute('data-theme') || 'light',
            notifications: this.elements.notificationToggle.innerHTML.includes('toggle-on'),
            timeFormat: '24h',
            startWeek: 'monday'
        };
        
        try {
            await this.apiCall('/backend/api/calendar-api.php?action=preferences', {
                method: 'POST',
                body: JSON.stringify({ preferences })
            });
        } catch (error) {
            console.log('[Preferences] Could not save preferences');
        }
    }
    
    async loadInitialData() {
        this.showLoading(true);
        
        try {
            await this.loadEvents();
            this.renderCurrentView();
        } catch (error) {
            console.error('[Data] Failed to load initial data:', error);
            this.showToast('Errore nel caricamento dei dati', 'error');
        } finally {
            this.showLoading(false);
        }
    }
    
    async loadEvents() {
        try {
            const start = this.getViewStartDate();
            const end = this.getViewEndDate();
            
            const response = await this.apiCall(
                `/backend/api/calendar-events.php?start=${start}&end=${end}`
            );
            
            if (response.success) {
                this.events = response.events || [];
                this.lastSyncTime = new Date().toISOString();
                console.log(`[Events] Loaded ${this.events.length} events`);
            } else {
                throw new Error(response.error || 'Failed to load events');
            }
        } catch (error) {
            if (this.isOnline) {
                throw error;
            } else {
                // Load from cache when offline
                this.events = await this.loadCachedEvents();
                console.log(`[Events] Loaded ${this.events.length} events from cache`);
            }
        }
    }
    
    async loadCachedEvents() {
        // Implementation would use IndexedDB for persistent storage
        const cached = localStorage.getItem('cachedEvents');
        return cached ? JSON.parse(cached) : [];
    }
    
    getViewStartDate() {
        const date = new Date(this.currentDate);
        
        switch (this.currentView) {
            case 'month':
                date.setDate(1);
                const firstDay = date.getDay();
                date.setDate(date.getDate() - firstDay);
                break;
            case 'week':
                const dayOfWeek = date.getDay();
                date.setDate(date.getDate() - dayOfWeek);
                break;
            case 'day':
                // Current date
                break;
            case 'list':
                date.setMonth(date.getMonth() - 1);
                break;
        }
        
        return date.toISOString().split('T')[0];
    }
    
    getViewEndDate() {
        const date = new Date(this.currentDate);
        
        switch (this.currentView) {
            case 'month':
                date.setMonth(date.getMonth() + 1, 0);
                const lastDay = date.getDay();
                date.setDate(date.getDate() + (6 - lastDay));
                break;
            case 'week':
                const dayOfWeek = date.getDay();
                date.setDate(date.getDate() + (6 - dayOfWeek));
                break;
            case 'day':
                // Current date
                break;
            case 'list':
                date.setMonth(date.getMonth() + 2);
                break;
        }
        
        return date.toISOString().split('T')[0];
    }
    
    setupPeriodicSync() {
        // Sync every 5 minutes when online
        setInterval(() => {
            if (this.isOnline && !this.syncInProgress) {
                this.syncData();
            }
        }, 5 * 60 * 1000);
        
        // Immediate sync when coming back online
        this.addListener(window, 'focus', () => {
            if (this.isOnline && !this.syncInProgress) {
                this.syncData();
            }
        });
    }
    
    hideLoadingScreen() {
        const loadingScreen = document.getElementById('loadingScreen');
        const app = document.getElementById('app');
        
        if (loadingScreen && app) {
            setTimeout(() => {
                loadingScreen.classList.add('hidden');
                app.classList.add('loaded');
                
                setTimeout(() => {
                    loadingScreen.remove();
                }, 300);
            }, 500);
        }
    }
    
    checkInstallBanner() {
        const dismissed = localStorage.getItem('installBannerDismissed');
        if (!dismissed && !window.matchMedia('(display-mode: standalone)').matches) {
            // Banner will be shown by beforeinstallprompt event
        }
    }
    
    // View Management
    switchView(view) {
        if (this.currentView === view) return;
        
        this.currentView = view;
        this.updateActiveViewButton();
        this.renderCurrentView();
        this.savePreferences();
        
        // Update URL hash
        history.replaceState(null, null, `#/calendar/${view}`);
    }
    
    updateActiveViewButton() {
        const buttons = [
            this.elements.monthViewBtn,
            this.elements.weekViewBtn,
            this.elements.dayViewBtn,
            this.elements.listViewBtn
        ];
        
        buttons.forEach(btn => {
            if (btn) {
                btn.classList.remove('active');
                if (btn.dataset.view === this.currentView) {
                    btn.classList.add('active');
                }
            }
        });
    }
    
    async renderCurrentView() {
        // Hide all views
        const views = [
            this.elements.monthView,
            this.elements.weekView,
            this.elements.dayView,
            this.elements.listView
        ];
        
        views.forEach(view => {
            if (view) view.style.display = 'none';
        });
        
        // Update period display
        this.updatePeriodDisplay();
        
        // Render current view
        switch (this.currentView) {
            case 'month':
                this.renderMonthView();
                break;
            case 'week':
                this.renderWeekView();
                break;
            case 'day':
                this.renderDayView();
                break;
            case 'list':
                this.renderListView();
                break;
        }
        
        // Show the active view
        const activeView = document.getElementById(`${this.currentView}View`);
        if (activeView) {
            activeView.style.display = 'block';
            activeView.classList.add('fade-in');
        }
    }
    
    updatePeriodDisplay() {
        let text = '';
        
        switch (this.currentView) {
            case 'month':
                text = this.currentDate.toLocaleDateString('it-IT', { 
                    month: 'long', 
                    year: 'numeric' 
                });
                break;
            case 'week':
                const startOfWeek = new Date(this.currentDate);
                startOfWeek.setDate(startOfWeek.getDate() - startOfWeek.getDay());
                const endOfWeek = new Date(startOfWeek);
                endOfWeek.setDate(endOfWeek.getDate() + 6);
                
                text = `${startOfWeek.getDate()}/${startOfWeek.getMonth() + 1} - ${endOfWeek.getDate()}/${endOfWeek.getMonth() + 1}/${endOfWeek.getFullYear()}`;
                break;
            case 'day':
                text = this.currentDate.toLocaleDateString('it-IT', {
                    weekday: 'long',
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric'
                });
                break;
            case 'list':
                text = 'Tutti gli eventi';
                break;
        }
        
        if (this.elements.currentPeriod) {
            this.elements.currentPeriod.textContent = text;
        }
    }
    
    renderMonthView() {
        if (!this.elements.monthGrid) return;
        
        const year = this.currentDate.getFullYear();
        const month = this.currentDate.getMonth();
        
        // Create month header
        const daysOfWeek = ['Dom', 'Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab'];
        let headerHTML = '<div class="month-header">';
        daysOfWeek.forEach(day => {
            headerHTML += `<div class="month-header-cell">${day}</div>`;
        });
        headerHTML += '</div>';
        
        // Get first day of month and last day
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const startDate = new Date(firstDay);
        startDate.setDate(startDate.getDate() - firstDay.getDay());
        
        let calendarHTML = headerHTML;
        let currentDate = new Date(startDate);
        
        for (let week = 0; week < 6; week++) {
            for (let day = 0; day < 7; day++) {
                const dayEvents = this.getEventsForDate(currentDate);
                const isCurrentMonth = currentDate.getMonth() === month;
                const isToday = this.isSameDate(currentDate, new Date());
                
                let dayClass = 'month-day';
                if (!isCurrentMonth) dayClass += ' other-month';
                if (isToday) dayClass += ' today';
                if (dayEvents.length > 0) dayClass += ' has-events';
                
                let eventsHTML = '';
                dayEvents.slice(0, 3).forEach(event => {
                    eventsHTML += `<div class="event-bar type-${event.tipo}" onclick="app.showEventDetails(${event.id})">${event.titolo}</div>`;
                });
                
                if (dayEvents.length > 3) {
                    eventsHTML += `<div class="event-bar" style="background: #6b7280;">+${dayEvents.length - 3} altri</div>`;
                }
                
                calendarHTML += `
                    <div class="${dayClass}" onclick="app.selectDate('${currentDate.toISOString().split('T')[0]}')" data-date="${currentDate.toISOString().split('T')[0]}">
                        <div class="day-number">${currentDate.getDate()}</div>
                        <div class="day-events">${eventsHTML}</div>
                    </div>
                `;
                
                currentDate.setDate(currentDate.getDate() + 1);
            }
        }
        
        this.elements.monthGrid.innerHTML = calendarHTML;
    }
    
    renderWeekView() {
        if (!this.elements.weekGrid) return;
        
        const startOfWeek = new Date(this.currentDate);
        startOfWeek.setDate(startOfWeek.getDate() - startOfWeek.getDay());
        
        const hours = Array.from({ length: 24 }, (_, i) => i);
        const days = Array.from({ length: 7 }, (_, i) => {
            const day = new Date(startOfWeek);
            day.setDate(day.getDate() + i);
            return day;
        });
        
        let headerHTML = '<div class="week-header">Ora</div>';
        days.forEach(day => {
            const isToday = this.isSameDate(day, new Date());
            headerHTML += `<div class="week-header ${isToday ? 'today' : ''}">${day.toLocaleDateString('it-IT', { weekday: 'short', day: 'numeric' })}</div>`;
        });
        
        let gridHTML = headerHTML;
        
        hours.forEach(hour => {
            gridHTML += `<div class="week-hour">${hour.toString().padStart(2, '0')}:00</div>`;
            
            days.forEach(day => {
                const dayEvents = this.getEventsForDate(day);
                const hourEvents = dayEvents.filter(event => {
                    const eventHour = new Date(event.data_inizio).getHours();
                    return eventHour === hour;
                });
                
                let eventsHTML = '';
                hourEvents.forEach((event, index) => {
                    const duration = this.calculateEventDuration(event);
                    eventsHTML += `
                        <div class="week-event type-${event.tipo}" 
                             onclick="app.showEventDetails(${event.id})"
                             style="top: ${index * 25}px; height: ${Math.max(20, duration * 60)}px;">
                            ${event.titolo}
                        </div>
                    `;
                });
                
                gridHTML += `<div class="week-day-column">${eventsHTML}</div>`;
            });
        });
        
        this.elements.weekGrid.innerHTML = gridHTML;
    }
    
    renderDayView() {
        if (!this.elements.dayGrid) return;
        
        const dayEvents = this.getEventsForDate(this.currentDate);
        const hours = Array.from({ length: 24 }, (_, i) => i);
        
        let headerHTML = `
            <div class="day-header">
                <div class="day-date">${this.currentDate.getDate()}</div>
                <div class="day-name">${this.currentDate.toLocaleDateString('it-IT', { weekday: 'long', month: 'long' })}</div>
            </div>
        `;
        
        let hoursHTML = '<div class="day-hours">';
        hours.forEach(hour => {
            const hourEvents = dayEvents.filter(event => {
                const eventHour = new Date(event.data_inizio).getHours();
                return eventHour === hour;
            });
            
            let eventsHTML = '';
            hourEvents.forEach(event => {
                eventsHTML += `
                    <div class="day-event type-${event.tipo}" onclick="app.showEventDetails(${event.id})">
                        <strong>${event.titolo}</strong>
                        ${event.descrizione ? `<div>${event.descrizione}</div>` : ''}
                        ${event.luogo ? `<div><i class="fas fa-map-marker-alt"></i> ${event.luogo}</div>` : ''}
                    </div>
                `;
            });
            
            hoursHTML += `
                <div class="day-hour">
                    <div class="day-hour-label">${hour.toString().padStart(2, '0')}:00</div>
                    <div class="day-hour-content">${eventsHTML}</div>
                </div>
            `;
        });
        hoursHTML += '</div>';
        
        this.elements.dayGrid.innerHTML = headerHTML + hoursHTML;
    }
    
    renderListView() {
        if (!this.elements.eventsList) return;
        
        const groupedEvents = this.groupEventsByDate();
        let listHTML = '';
        
        Object.keys(groupedEvents).sort().forEach(dateKey => {
            const events = groupedEvents[dateKey];
            const date = new Date(dateKey);
            
            listHTML += `
                <div class="list-date-group">
                    <div class="list-date-header">
                        ${date.toLocaleDateString('it-IT', { 
                            weekday: 'long', 
                            day: 'numeric', 
                            month: 'long', 
                            year: 'numeric' 
                        })}
                    </div>
            `;
            
            events.forEach(event => {
                const startTime = new Date(event.data_inizio).toLocaleTimeString('it-IT', { 
                    hour: '2-digit', 
                    minute: '2-digit' 
                });
                
                listHTML += `
                    <div class="list-event" onclick="app.showEventDetails(${event.id})">
                        <div class="event-time">${startTime}</div>
                        <div class="event-content">
                            <div class="event-title">${event.titolo}</div>
                            <div class="event-details">
                                ${event.luogo ? `<div class="event-location"><i class="fas fa-map-marker-alt"></i> ${event.luogo}</div>` : ''}
                                <div class="event-type"><i class="fas fa-tag"></i> ${this.formatEventType(event.tipo)}</div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            listHTML += '</div>';
        });
        
        if (listHTML === '') {
            listHTML = '<div class="text-center py-5 text-muted">Nessun evento trovato</div>';
        }
        
        this.elements.eventsList.innerHTML = listHTML;
    }
    
    groupEventsByDate() {
        const grouped = {};
        
        this.events.forEach(event => {
            const dateKey = event.data_inizio.split(' ')[0];
            if (!grouped[dateKey]) {
                grouped[dateKey] = [];
            }
            grouped[dateKey].push(event);
        });
        
        // Sort events within each date
        Object.keys(grouped).forEach(dateKey => {
            grouped[dateKey].sort((a, b) => new Date(a.data_inizio) - new Date(b.data_inizio));
        });
        
        return grouped;
    }
    
    getEventsForDate(date) {
        const dateStr = date.toISOString().split('T')[0];
        return this.events.filter(event => {
            const eventDate = event.data_inizio.split(' ')[0];
            return eventDate === dateStr;
        });
    }
    
    isSameDate(date1, date2) {
        return date1.toISOString().split('T')[0] === date2.toISOString().split('T')[0];
    }
    
    calculateEventDuration(event) {
        const start = new Date(event.data_inizio);
        const end = new Date(event.data_fine || event.data_inizio);
        return Math.max(0.5, (end - start) / (1000 * 60 * 60)); // At least 30 minutes
    }
    
    formatEventType(type) {
        const types = {
            'riunione': 'Riunione',
            'appuntamento': 'Appuntamento',
            'scadenza': 'Scadenza',
            'evento': 'Evento',
            'altro': 'Altro'
        };
        return types[type] || type;
    }
    
    // Navigation
    navigatePrev() {
        switch (this.currentView) {
            case 'month':
                this.currentDate.setMonth(this.currentDate.getMonth() - 1);
                break;
            case 'week':
                this.currentDate.setDate(this.currentDate.getDate() - 7);
                break;
            case 'day':
                this.currentDate.setDate(this.currentDate.getDate() - 1);
                break;
        }
        
        this.loadEvents().then(() => this.renderCurrentView());
    }
    
    navigateNext() {
        switch (this.currentView) {
            case 'month':
                this.currentDate.setMonth(this.currentDate.getMonth() + 1);
                break;
            case 'week':
                this.currentDate.setDate(this.currentDate.getDate() + 7);
                break;
            case 'day':
                this.currentDate.setDate(this.currentDate.getDate() + 1);
                break;
        }
        
        this.loadEvents().then(() => this.renderCurrentView());
    }
    
    navigateToday() {
        this.currentDate = new Date();
        this.loadEvents().then(() => this.renderCurrentView());
    }
    
    selectDate(dateStr) {
        this.currentDate = new Date(dateStr);
        if (this.currentView === 'month') {
            this.switchView('day');
        } else {
            this.renderCurrentView();
        }
    }
    
    // Event Management
    showEventModal(eventId = null) {
        const modal = new bootstrap.Modal(this.elements.eventModal);
        
        if (eventId) {
            // Edit existing event
            const event = this.events.find(e => e.id == eventId);
            if (event) {
                this.populateEventForm(event);
                this.elements.eventModalTitle.textContent = 'Modifica Evento';
                this.elements.deleteEventBtn.style.display = 'block';
            }
        } else {
            // Create new event
            this.clearEventForm();
            this.elements.eventModalTitle.textContent = 'Nuovo Evento';
            this.elements.deleteEventBtn.style.display = 'none';
            
            // Set default date
            const dateStr = this.currentDate.toISOString().split('T')[0];
            this.elements.eventStartDate.value = dateStr;
            this.elements.eventEndDate.value = dateStr;
            
            // Set default time
            const now = new Date();
            const timeStr = now.toTimeString().slice(0, 5);
            this.elements.eventStartTime.value = timeStr;
            
            const endTime = new Date(now.getTime() + 60 * 60 * 1000);
            this.elements.eventEndTime.value = endTime.toTimeString().slice(0, 5);
        }
        
        modal.show();
    }
    
    populateEventForm(event) {
        this.elements.eventId.value = event.id;
        this.elements.eventTitle.value = event.titolo;
        this.elements.eventDescription.value = event.descrizione || '';
        this.elements.eventLocation.value = event.luogo || '';
        this.elements.eventType.value = event.tipo || 'riunione';
        
        const startDate = new Date(event.data_inizio);
        this.elements.eventStartDate.value = startDate.toISOString().split('T')[0];
        this.elements.eventStartTime.value = startDate.toTimeString().slice(0, 5);
        
        if (event.data_fine && event.data_fine !== event.data_inizio) {
            const endDate = new Date(event.data_fine);
            this.elements.eventEndDate.value = endDate.toISOString().split('T')[0];
            this.elements.eventEndTime.value = endDate.toTimeString().slice(0, 5);
        }
    }
    
    clearEventForm() {
        this.elements.eventForm.reset();
        this.elements.eventId.value = '';
    }
    
    async saveEvent() {
        if (!this.validateEventForm()) return;
        
        const eventData = this.getEventFormData();
        const isEdit = !!eventData.id;
        
        try {
            this.showLoading(true);
            
            const response = await this.apiCall('/backend/api/calendar-events.php', {
                method: isEdit ? 'PUT' : 'POST',
                body: JSON.stringify(eventData)
            });
            
            if (response.success) {
                this.showToast(
                    isEdit ? 'Evento aggiornato con successo' : 'Evento creato con successo',
                    'success'
                );
                
                // Close modal
                const modal = bootstrap.Modal.getInstance(this.elements.eventModal);
                modal.hide();
                
                // Refresh data
                await this.loadEvents();
                this.renderCurrentView();
                
            } else {
                throw new Error(response.error || 'Errore durante il salvataggio');
            }
        } catch (error) {
            console.error('[Event] Save error:', error);
            
            if (!this.isOnline) {
                // Save for offline sync
                await this.savePendingEvent(eventData);
                this.showToast('Evento salvato per sincronizzazione offline', 'info');
                
                const modal = bootstrap.Modal.getInstance(this.elements.eventModal);
                modal.hide();
            } else {
                this.showToast(error.message, 'error');
            }
        } finally {
            this.showLoading(false);
        }
    }
    
    validateEventForm() {
        const title = this.elements.eventTitle.value.trim();
        const startDate = this.elements.eventStartDate.value;
        const startTime = this.elements.eventStartTime.value;
        
        if (!title) {
            this.showToast('Il titolo è obbligatorio', 'error');
            this.elements.eventTitle.focus();
            return false;
        }
        
        if (!startDate || !startTime) {
            this.showToast('Data e ora di inizio sono obbligatori', 'error');
            return false;
        }
        
        return true;
    }
    
    getEventFormData() {
        const startDateTime = `${this.elements.eventStartDate.value} ${this.elements.eventStartTime.value}:00`;
        let endDateTime = startDateTime;
        
        if (this.elements.eventEndDate.value && this.elements.eventEndTime.value) {
            endDateTime = `${this.elements.eventEndDate.value} ${this.elements.eventEndTime.value}:00`;
        }
        
        return {
            id: this.elements.eventId.value || null,
            titolo: this.elements.eventTitle.value.trim(),
            descrizione: this.elements.eventDescription.value.trim(),
            data_inizio: startDateTime,
            data_fine: endDateTime,
            luogo: this.elements.eventLocation.value.trim(),
            tipo: this.elements.eventType.value,
            invia_notifiche: this.elements.sendNotifications.checked
        };
    }
    
    async deleteEvent() {
        const eventId = this.elements.eventId.value;
        if (!eventId) return;
        
        const event = this.events.find(e => e.id == eventId);
        if (!event) return;
        
        if (!confirm(`Sei sicuro di voler eliminare l'evento "${event.titolo}"?`)) {
            return;
        }
        
        try {
            this.showLoading(true);
            
            const response = await this.apiCall('/backend/api/calendar-events.php', {
                method: 'DELETE',
                body: JSON.stringify({ id: eventId, invia_notifiche: true })
            });
            
            if (response.success) {
                this.showToast('Evento eliminato con successo', 'success');
                
                // Close modal
                const modal = bootstrap.Modal.getInstance(this.elements.eventModal);
                modal.hide();
                
                // Refresh data
                await this.loadEvents();
                this.renderCurrentView();
                
            } else {
                throw new Error(response.error || 'Errore durante l\'eliminazione');
            }
        } catch (error) {
            console.error('[Event] Delete error:', error);
            this.showToast(error.message, 'error');
        } finally {
            this.showLoading(false);
        }
    }
    
    showEventDetails(eventId) {
        this.showEventModal(eventId);
    }
    
    // Offline Support
    async savePendingEvent(eventData) {
        const pending = JSON.parse(localStorage.getItem('pendingEvents') || '[]');
        eventData.pendingId = Date.now();
        pending.push(eventData);
        localStorage.setItem('pendingEvents', JSON.stringify(pending));
    }
    
    // Data Management
    async syncData() {
        if (this.syncInProgress || !this.isOnline) return;
        
        this.syncInProgress = true;
        this.elements.syncBtn.innerHTML = '<i class="fas fa-sync fa-spin"></i>';
        
        try {
            const response = await this.apiCall(
                `/backend/api/calendar-api.php?action=sync&lastSync=${this.lastSyncTime || ''}`
            );
            
            if (response.success && response.sync.events.length > 0) {
                this.events = response.sync.events;
                this.lastSyncTime = response.sync.newSync;
                
                // Update cache
                localStorage.setItem('cachedEvents', JSON.stringify(this.events));
                
                this.renderCurrentView();
                this.showToast(`Sincronizzati ${response.sync.count} eventi`, 'success');
            }
            
            // Sync pending events
            await this.syncPendingEvents();
            
        } catch (error) {
            console.error('[Sync] Error:', error);
            this.showToast('Errore durante la sincronizzazione', 'error');
        } finally {
            this.syncInProgress = false;
            this.elements.syncBtn.innerHTML = '<i class="fas fa-sync"></i>';
        }
    }
    
    async syncPendingEvents() {
        const pending = JSON.parse(localStorage.getItem('pendingEvents') || '[]');
        if (pending.length === 0) return;
        
        for (const event of pending) {
            try {
                const response = await this.apiCall('/backend/api/calendar-events.php', {
                    method: 'POST',
                    body: JSON.stringify(event)
                });
                
                if (response.success) {
                    // Remove from pending
                    const remaining = pending.filter(e => e.pendingId !== event.pendingId);
                    localStorage.setItem('pendingEvents', JSON.stringify(remaining));
                }
            } catch (error) {
                console.log('[Sync] Failed to sync pending event:', error);
            }
        }
    }
    
    async refreshData() {
        await this.loadEvents();
        this.renderCurrentView();
        this.showToast('Dati aggiornati', 'success');
    }
    
    // Side Menu
    toggleSideMenu() {
        this.elements.sideMenu.classList.add('open');
        this.elements.menuOverlay.classList.add('show');
    }
    
    closeSideMenu() {
        this.elements.sideMenu.classList.remove('open');
        this.elements.menuOverlay.classList.remove('show');
    }
    
    toggleNotifications() {
        const isEnabled = this.elements.notificationToggle.innerHTML.includes('toggle-on');
        
        if (isEnabled) {
            this.elements.notificationToggle.innerHTML = '<i class="fas fa-toggle-off"></i>';
            this.disableNotifications();
        } else {
            this.elements.notificationToggle.innerHTML = '<i class="fas fa-toggle-on"></i>';
            this.enableNotifications();
        }
        
        this.savePreferences();
    }
    
    async enableNotifications() {
        if ('Notification' in window) {
            const permission = await Notification.requestPermission();
            if (permission === 'granted') {
                this.showToast('Notifiche attivate', 'success');
                
                // Subscribe to push notifications
                if ('serviceWorker' in navigator && 'PushManager' in window) {
                    try {
                        const registration = await navigator.serviceWorker.ready;
                        const subscription = await registration.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: this.urlBase64ToUint8Array('your-vapid-public-key')
                        });
                        
                        // Send subscription to server
                        await this.sendSubscriptionToServer(subscription);
                    } catch (error) {
                        console.log('[Notifications] Push subscription failed:', error);
                    }
                }
            } else {
                this.elements.notificationToggle.innerHTML = '<i class="fas fa-toggle-off"></i>';
                this.showToast('Permesso notifiche negato', 'warning');
            }
        }
    }
    
    disableNotifications() {
        this.showToast('Notifiche disattivate', 'info');
    }
    
    toggleTheme() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        
        if (isDark) {
            document.documentElement.removeAttribute('data-theme');
            this.elements.themeToggle.innerHTML = '<i class="fas fa-toggle-off"></i>';
        } else {
            document.documentElement.setAttribute('data-theme', 'dark');
            this.elements.themeToggle.innerHTML = '<i class="fas fa-toggle-on"></i>';
        }
        
        this.savePreferences();
    }
    
    async exportCalendar() {
        try {
            const response = await this.apiCall('/backend/api/calendar-api.php?action=export&format=ics&period=month');
            
            if (response.success) {
                const blob = new Blob([atob(response.export.content)], { type: 'text/calendar' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = response.export.filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                
                this.showToast('Calendario esportato', 'success');
            } else {
                throw new Error(response.error);
            }
        } catch (error) {
            console.error('[Export] Error:', error);
            this.showToast('Errore durante l\'esportazione', 'error');
        }
        
        this.closeSideMenu();
    }
    
    logout() {
        if (confirm('Sei sicuro di voler uscire?')) {
            // Clear local data
            localStorage.clear();
            
            // Redirect to logout
            window.location.href = '../logout.php';
        }
    }
    
    // Online/Offline Handling
    handleOnlineStatus(online) {
        this.isOnline = online;
        
        if (online) {
            this.elements.offlineBanner.style.display = 'none';
            this.elements.connectionStatus.textContent = 'Online';
            this.elements.connectionStatus.className = 'online';
            
            // Sync when coming back online
            this.syncData();
        } else {
            this.elements.offlineBanner.style.display = 'block';
            this.elements.connectionStatus.textContent = 'Offline';
            this.elements.connectionStatus.className = 'offline';
        }
    }
    
    handleServiceWorkerMessage(data) {
        switch (data.type) {
            case 'SYNC_COMPLETE':
                if (data.data.count > 0) {
                    this.events = data.data.events;
                    this.renderCurrentView();
                    this.showToast(`Background sync: ${data.data.count} eventi aggiornati`, 'info');
                }
                break;
            case 'PUSH_NOTIFICATION':
                // Handle push notification data
                break;
        }
    }
    
    // Utility Methods
    async apiCall(url, options = {}) {
        const defaultOptions = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };
        
        const mergedOptions = { ...defaultOptions, ...options };
        
        const response = await fetch(url, mergedOptions);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        return await response.json();
    }
    
    showLoading(show) {
        if (this.elements.loadingEvents) {
            this.elements.loadingEvents.style.display = show ? 'block' : 'none';
        }
    }
    
    showToast(message, type = 'info') {
        const toastId = 'toast-' + Date.now();
        const iconClass = {
            'success': 'fas fa-check-circle',
            'error': 'fas fa-exclamation-triangle',
            'warning': 'fas fa-exclamation-circle',
            'info': 'fas fa-info-circle'
        }[type];
        
        const toast = document.createElement('div');
        toast.className = `toast show`;
        toast.id = toastId;
        toast.innerHTML = `
            <div class="toast-header bg-${type === 'error' ? 'danger' : type}">
                <i class="${iconClass} me-2"></i>
                <strong class="me-auto">Nexio Calendar</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">${message}</div>
        `;
        
        this.elements.toastContainer.appendChild(toast);
        
        const bsToast = new bootstrap.Toast(toast, { delay: 5000 });
        bsToast.show();
        
        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
        });
    }
    
    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');
        
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }
    
    async sendSubscriptionToServer(subscription) {
        // Send push subscription to server
        // Implementation depends on your server setup
    }
    
    // Cleanup
    destroy() {
        // Remove all event listeners
        this.eventListeners.forEach(({ element, event, handler }) => {
            element.removeEventListener(event, handler);
        });
        
        this.eventListeners = [];
    }
}

// Initialize app when DOM is loaded
let app;

document.addEventListener('DOMContentLoaded', () => {
    app = new NexioCalendarApp();
});

// Expose app globally for event handlers
window.app = app;