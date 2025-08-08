/**
 * Nexio Calendario PWA
 * App principale con tutte le funzionalità del calendario
 */

class NexioCalendar {
    constructor() {
        // Stato dell'applicazione
        this.currentDate = new Date();
        this.currentView = 'month';
        this.events = [];
        this.users = [];
        this.isOnline = navigator.onLine;
        this.isAuthenticated = false;
        this.user = null;
        this.company = null;
        
        // Configurazione API
        this.apiBase = window.location.origin + '/piattaforma-collaborativa/backend/api';
        
        // Event listeners
        this.eventListeners = new Map();
        
        // PWA install prompt
        this.deferredPrompt = null;
        
        // Inizializza l'app
        this.init();
    }
    
    /**
     * Inizializzazione dell'applicazione
     */
    async init() {
        try {
            console.log('Initializing Nexio Calendar PWA...');
            
            // Setup IndexedDB first
            await this.setupIndexedDB();
            
            // Setup Service Worker listeners
            this.setupServiceWorker();
            
            // Setup PWA install
            this.setupPWAInstall();
            
            // Setup offline detection
            this.setupOfflineDetection();
            
            // Verifica autenticazione
            await this.checkAuthentication();
            
            if (this.isAuthenticated) {
                // Setup UI
                this.setupUI();
                
                // Load user preferences
                await this.loadUserPreferences();
                
                // Carica dati iniziali
                await this.loadInitialData();
                
                // Render vista iniziale
                this.renderCurrentView();
                
                // Setup auto-refresh
                this.setupAutoRefresh();
                
                // Process any pending sync actions
                if (this.isOnline) {
                    this.processPendingSyncActions();
                }
                
                console.log('Calendar initialized successfully');
            } else {
                this.showLoginRequired();
            }
            
        } catch (error) {
            console.error('Initialization failed:', error);
            this.showError('Errore durante l\'inizializzazione dell\'app');
        } finally {
            this.hideLoading();
        }
    }
    
    /**
     * Setup Service Worker communication
     */
    setupServiceWorker() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.addEventListener('message', event => {
                const { type, data } = event.data;
                
                switch (type) {
                    case 'SYNC_STARTED':
                        this.showToast('Sincronizzazione in corso...', 'info');
                        break;
                        
                    case 'SYNC_COMPLETED':
                        this.showToast('Sincronizzazione completata', 'success');
                        if (data.action === 'events') {
                            this.loadEvents();
                        }
                        break;
                        
                    case 'SYNC_FAILED':
                        this.showToast('Errore durante la sincronizzazione', 'error');
                        break;
                        
                    case 'EVENTS_SYNCED':
                        this.loadEvents();
                        this.showToast('Eventi sincronizzati', 'success');
                        break;
                }
            });
        }
    }
    
    /**
     * Setup PWA install functionality
     */
    setupPWAInstall() {
        // Listen for beforeinstallprompt event
        window.addEventListener('beforeinstallprompt', (e) => {
            console.log('beforeinstallprompt fired');
            e.preventDefault();
            this.deferredPrompt = e;
            this.showInstallPrompt();
        });
        
        // Handle install button click
        document.getElementById('install-btn').addEventListener('click', () => {
            this.installPWA();
        });
        
        // Handle dismiss install
        document.getElementById('dismiss-install').addEventListener('click', () => {
            this.hideInstallPrompt();
        });
        
        // Check if already installed
        window.addEventListener('appinstalled', () => {
            console.log('PWA was installed');
            this.hideInstallPrompt();
            this.showToast('App installata con successo!', 'success');
        });
    }
    
    /**
     * Setup offline detection
     */
    setupOfflineDetection() {
        window.addEventListener('online', () => {
            this.isOnline = true;
            this.hideOfflineIndicator();
            this.showToast('Connessione ripristinata', 'success');
            this.syncPendingChanges();
        });
        
        window.addEventListener('offline', () => {
            this.isOnline = false;
            this.showOfflineIndicator();
            this.showToast('Modalità offline attiva', 'warning');
        });
    }
    
    /**
     * Verifica autenticazione utente
     */
    async checkAuthentication() {
        try {
            const response = await this.apiCall('/backend/middleware/Auth.php', 'POST', {
                action: 'check'
            });
            
            if (response.success && response.user) {
                this.isAuthenticated = true;
                this.user = response.user;
                this.company = response.company;
                
                // Update UI with user info
                document.getElementById('user-name').textContent = 
                    `${this.user.nome} ${this.user.cognome}`;
                document.getElementById('company-name').textContent = 
                    this.company ? this.company.nome : 'Nessuna azienda';
                    
            } else {
                this.isAuthenticated = false;
            }
            
        } catch (error) {
            console.error('Authentication check failed:', error);
            this.isAuthenticated = false;
        }
    }
    
    /**
     * Setup UI event listeners
     */
    setupUI() {
        // View selector buttons
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const view = e.target.closest('.view-btn').dataset.view;
                this.changeView(view);
            });
        });
        
        // Navigation buttons
        document.getElementById('prev-period').addEventListener('click', () => {
            this.navigateDate(-1);
        });
        
        document.getElementById('next-period').addEventListener('click', () => {
            this.navigateDate(1);
        });
        
        document.getElementById('today-btn').addEventListener('click', () => {
            this.goToToday();
        });
        
        // FAB button
        document.getElementById('fab').addEventListener('click', () => {
            this.openNewEventModal();
        });
        
        // Menu toggle
        document.getElementById('menu-btn').addEventListener('click', () => {
            this.toggleMenu();
        });
        
        document.getElementById('close-menu').addEventListener('click', () => {
            this.closeMenu();
        });
        
        // Search toggle
        document.getElementById('search-btn').addEventListener('click', () => {
            this.toggleSearch();
        });
        
        document.getElementById('close-search').addEventListener('click', () => {
            this.closeSearch();
        });
        
        // Search input
        document.getElementById('search-input').addEventListener('input', 
            this.debounce((e) => this.performSearch(e.target.value), 300)
        );
        
        // Sync button
        document.getElementById('sync-btn').addEventListener('click', () => {
            this.forceSync();
        });
        
        // Modal handlers
        this.setupModalHandlers();
        
        // Menu actions
        this.setupMenuActions();
        
        // Filter handlers
        this.setupFilterHandlers();
        
        // Touch/swipe gestures
        this.setupGestures();
    }
    
    /**
     * Setup modal event handlers
     */
    setupModalHandlers() {
        // Event modal
        document.getElementById('close-modal').addEventListener('click', () => {
            this.closeEventModal();
        });
        
        document.getElementById('cancel-btn').addEventListener('click', () => {
            this.closeEventModal();
        });
        
        document.getElementById('save-btn').addEventListener('click', () => {
            this.saveEvent();
        });
        
        document.getElementById('delete-btn').addEventListener('click', () => {
            this.deleteEvent();
        });
        
        // Event details modal
        document.getElementById('close-details').addEventListener('click', () => {
            this.closeEventDetailsModal();
        });
        
        document.getElementById('close-details-btn').addEventListener('click', () => {
            this.closeEventDetailsModal();
        });
        
        document.getElementById('edit-event-btn').addEventListener('click', () => {
            this.editCurrentEvent();
        });
        
        // Close modals on backdrop click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.remove('show');
                }
            });
        });
    }
    
    /**
     * Setup menu actions
     */
    setupMenuActions() {
        document.querySelectorAll('[data-action]').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const action = e.target.closest('[data-action]').dataset.action;
                this.handleMenuAction(action);
            });
        });
    }
    
    /**
     * Setup filter handlers
     */
    setupFilterHandlers() {
        document.querySelectorAll('.filter-item input').forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                this.applyFilters();
            });
        });
    }
    
    /**
     * Setup touch gestures for mobile
     */
    setupGestures() {
        let startX = 0;
        let startY = 0;
        
        const calendarContainer = document.getElementById('calendar-container');
        
        calendarContainer.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
        });
        
        calendarContainer.addEventListener('touchend', (e) => {
            if (!startX || !startY) return;
            
            const endX = e.changedTouches[0].clientX;
            const endY = e.changedTouches[0].clientY;
            
            const diffX = startX - endX;
            const diffY = startY - endY;
            
            // Swipe horizontale
            if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 50) {
                if (diffX > 0) {
                    // Swipe left -> next
                    this.navigateDate(1);
                } else {
                    // Swipe right -> previous
                    this.navigateDate(-1);
                }
            }
            
            startX = 0;
            startY = 0;
        });
    }
    
    /**
     * Carica dati iniziali
     */
    async loadInitialData() {
        try {
            await Promise.all([
                this.loadEvents(),
                this.loadUsers()
            ]);
        } catch (error) {
            console.error('Failed to load initial data:', error);
        }
    }
    
    /**
     * Carica eventi dal server
     */
    async loadEvents(start = null, end = null) {
        try {
            // If offline, load from cache immediately
            if (!this.isOnline) {
                this.events = await this.getCachedEvents();
                this.renderCurrentView();
                this.showToast('Modalità offline: eventi da cache locale', 'info');
                return;
            }
            
            const params = new URLSearchParams();
            
            if (start) params.append('start', start);
            if (end) params.append('end', end);
            
            const response = await this.apiCall(`/calendar-events.php?${params}`, 'GET');
            
            if (response.success) {
                this.events = response.events || [];
                
                // Cache events for offline use
                await this.cacheEvents(this.events);
                
                this.renderCurrentView();
            } else {
                throw new Error(response.error || 'Failed to load events');
            }
            
        } catch (error) {
            console.error('Failed to load events:', error);
            
            // Try to load from cache as fallback
            const cachedEvents = await this.getCachedEvents();
            if (cachedEvents.length > 0) {
                this.events = cachedEvents;
                this.renderCurrentView();
                this.showToast('Errore di rete: caricati eventi dalla cache', 'warning');
            } else {
                this.showError('Errore nel caricamento degli eventi e nessuna cache disponibile');
            }
        }
    }
    
    /**
     * Carica utenti disponibili
     */
    async loadUsers() {
        try {
            // If offline, load from cache
            if (!this.isOnline) {
                this.users = await this.getCachedUsers();
                this.populateParticipants();
                return;
            }
            
            const response = await this.apiCall('/get-referenti.php', 'GET');
            
            if (response.success) {
                this.users = response.users || [];
                
                // Cache users for offline use
                await this.cacheUsers(this.users);
                
                this.populateParticipants();
            }
            
        } catch (error) {
            console.error('Failed to load users:', error);
            
            // Try to load from cache as fallback
            const cachedUsers = await this.getCachedUsers();
            if (cachedUsers.length > 0) {
                this.users = cachedUsers;
                this.populateParticipants();
            }
        }
    }
    
    /**
     * Popola lista partecipanti nel modal
     */
    populateParticipants() {
        const container = document.getElementById('participants-container');
        container.innerHTML = '';
        
        this.users.forEach(user => {
            const div = document.createElement('div');
            div.className = 'participant-item';
            div.innerHTML = `
                <label class="checkbox-label">
                    <input type="checkbox" name="partecipanti[]" value="${user.id}">
                    <span class="checkmark"></span>
                    <span class="user-name">${user.nome} ${user.cognome}</span>
                    <small class="user-email">${user.email}</small>
                </label>
            `;
            container.appendChild(div);
        });
    }
    
    /**
     * Cambia vista calendario
     */
    changeView(view) {
        if (view === this.currentView) return;
        
        this.currentView = view;
        
        // Update view buttons
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.view === view);
        });
        
        // Update views
        document.querySelectorAll('.calendar-view').forEach(viewEl => {
            viewEl.classList.toggle('active', viewEl.id === `${view}-view`);
        });
        
        // Render current view
        this.renderCurrentView();
        
        // Save preference
        localStorage.setItem('calendar-view', view);
    }
    
    /**
     * Naviga tra le date
     */
    navigateDate(direction) {
        const currentDate = new Date(this.currentDate);
        
        switch (this.currentView) {
            case 'day':
                currentDate.setDate(currentDate.getDate() + direction);
                break;
            case 'week':
                currentDate.setDate(currentDate.getDate() + (direction * 7));
                break;
            case 'month':
                currentDate.setMonth(currentDate.getMonth() + direction);
                break;
        }
        
        this.currentDate = currentDate;
        this.renderCurrentView();
    }
    
    /**
     * Va alla data odierna
     */
    goToToday() {
        this.currentDate = new Date();
        this.renderCurrentView();
    }
    
    /**
     * Render della vista corrente
     */
    renderCurrentView() {
        this.updateDateTitle();
        
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
    }
    
    /**
     * Aggiorna il titolo della data
     */
    updateDateTitle() {
        const titleEl = document.getElementById('current-date-title');
        let title = '';
        
        switch (this.currentView) {
            case 'day':
                title = this.currentDate.toLocaleDateString('it-IT', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
                break;
            case 'week':
                const startOfWeek = this.getStartOfWeek(this.currentDate);
                const endOfWeek = new Date(startOfWeek);
                endOfWeek.setDate(startOfWeek.getDate() + 6);
                title = `${startOfWeek.getDate()}/${startOfWeek.getMonth() + 1} - ${endOfWeek.getDate()}/${endOfWeek.getMonth() + 1}/${endOfWeek.getFullYear()}`;
                break;
            case 'month':
                title = this.currentDate.toLocaleDateString('it-IT', {
                    year: 'numeric',
                    month: 'long'
                });
                break;
            case 'list':
                title = 'Tutti gli Eventi';
                break;
        }
        
        titleEl.textContent = title;
    }
    
    /**
     * Render vista mensile
     */
    renderMonthView() {
        const monthGrid = document.getElementById('month-grid');
        monthGrid.innerHTML = '';
        
        const firstDay = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth(), 1);
        const lastDay = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() + 1, 0);
        
        // Find first day of calendar grid (Monday of the week containing first day)
        const startDate = this.getStartOfWeek(firstDay);
        
        // Find last day of calendar grid
        const endDate = new Date(lastDay);
        while (endDate.getDay() !== 0) {
            endDate.setDate(endDate.getDate() + 1);
        }
        
        const currentDate = new Date(startDate);
        
        while (currentDate <= endDate) {
            const dayElement = this.createMonthDayElement(currentDate, firstDay, lastDay);
            monthGrid.appendChild(dayElement);
            currentDate.setDate(currentDate.getDate() + 1);
        }
    }
    
    /**
     * Crea elemento per giorno nella vista mensile
     */
    createMonthDayElement(date, monthStart, monthEnd) {
        const dayEl = document.createElement('div');
        const isCurrentMonth = date >= monthStart && date <= monthEnd;
        const isToday = this.isToday(date);
        const dateStr = this.formatDate(date);
        
        dayEl.className = `calendar-day ${!isCurrentMonth ? 'other-month' : ''} ${isToday ? 'today' : ''}`;
        dayEl.dataset.date = dateStr;
        
        // Day number
        const dayNumber = document.createElement('div');
        dayNumber.className = 'day-number';
        dayNumber.textContent = date.getDate();
        dayEl.appendChild(dayNumber);
        
        // Events for this day
        const dayEvents = this.getEventsForDate(date);
        if (dayEvents.length > 0) {
            dayEl.classList.add('has-events');
            
            const eventsContainer = document.createElement('div');
            eventsContainer.className = 'day-events';
            
            // Show max 2 events, then +X more
            const maxVisible = 2;
            dayEvents.slice(0, maxVisible).forEach(event => {
                const eventEl = document.createElement('div');
                eventEl.className = `event-badge event-${event.tipo}`;
                eventEl.textContent = event.titolo.length > 15 ? 
                    event.titolo.substring(0, 15) + '...' : event.titolo;
                eventEl.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.showEventDetails(event);
                });
                eventsContainer.appendChild(eventEl);
            });
            
            if (dayEvents.length > maxVisible) {
                const moreEl = document.createElement('div');
                moreEl.className = 'more-events';
                moreEl.textContent = `+${dayEvents.length - maxVisible}`;
                eventsContainer.appendChild(moreEl);
            }
            
            dayEl.appendChild(eventsContainer);
        }
        
        // Click handler for day
        dayEl.addEventListener('click', () => {
            if (dayEvents.length > 0) {
                this.showDayEvents(date, dayEvents);
            } else {
                this.openNewEventModal(date);
            }
        });
        
        return dayEl;
    }
    
    /**
     * Render vista settimanale
     */
    renderWeekView() {
        const weekGrid = document.getElementById('week-grid');
        weekGrid.innerHTML = '';
        
        const startOfWeek = this.getStartOfWeek(this.currentDate);
        
        // Create time slots
        this.createTimeSlots('week');
        
        // Create day columns
        for (let i = 0; i < 7; i++) {
            const dayDate = new Date(startOfWeek);
            dayDate.setDate(startOfWeek.getDate() + i);
            
            const dayColumn = this.createWeekDayColumn(dayDate);
            weekGrid.appendChild(dayColumn);
        }
    }
    
    /**
     * Crea colonna giorno per vista settimanale
     */
    createWeekDayColumn(date) {
        const dayColumn = document.createElement('div');
        dayColumn.className = 'day-column';
        
        // Day header
        const dayHeader = document.createElement('div');
        dayHeader.className = `day-header ${this.isToday(date) ? 'today' : ''}`;
        dayHeader.innerHTML = `
            <div class="day-name">${this.getDayName(date, true)}</div>
            <div class="day-number">${date.getDate()}</div>
        `;
        dayColumn.appendChild(dayHeader);
        
        // Events container
        const eventsContainer = document.createElement('div');
        eventsContainer.className = 'day-events-container';
        eventsContainer.style.position = 'relative';
        eventsContainer.style.height = '100%';
        
        // Get events for this day
        const dayEvents = this.getEventsForDate(date);
        dayEvents.forEach(event => {
            const eventEl = this.createWeekEventElement(event);
            eventsContainer.appendChild(eventEl);
        });
        
        dayColumn.appendChild(eventsContainer);
        
        // Click handler for empty areas
        dayColumn.addEventListener('click', (e) => {
            if (e.target === eventsContainer || e.target === dayColumn) {
                this.openNewEventModal(date);
            }
        });
        
        return dayColumn;
    }
    
    /**
     * Render vista giornaliera
     */
    renderDayView() {
        const dayGrid = document.getElementById('day-grid');
        dayGrid.innerHTML = '';
        
        // Create time slots
        this.createTimeSlots('day');
        
        // Events container
        const eventsContainer = document.createElement('div');
        eventsContainer.className = 'day-events-container';
        eventsContainer.style.position = 'relative';
        
        // Get events for current date
        const dayEvents = this.getEventsForDate(this.currentDate);
        dayEvents.forEach(event => {
            const eventEl = this.createDayEventElement(event);
            eventsContainer.appendChild(eventEl);
        });
        
        dayGrid.appendChild(eventsContainer);
        
        // Click handler for empty areas
        dayGrid.addEventListener('click', (e) => {
            if (e.target === eventsContainer || e.target === dayGrid) {
                this.openNewEventModal(this.currentDate);
            }
        });
    }
    
    /**
     * Render vista lista
     */
    renderListView() {
        const eventsList = document.getElementById('events-list');
        eventsList.innerHTML = '';
        
        if (this.events.length === 0) {
            eventsList.innerHTML = `
                <div class="no-events">
                    <i class="fas fa-calendar-times"></i>
                    <h3>Nessun evento</h3>
                    <p>Non ci sono eventi da visualizzare</p>
                    <button class="btn btn-primary" onclick="calendar.openNewEventModal()">
                        <i class="fas fa-plus"></i> Crea Nuovo Evento
                    </button>
                </div>
            `;
            return;
        }
        
        // Group events by date
        const eventsByDate = this.groupEventsByDate(this.events);
        
        Object.keys(eventsByDate).sort().forEach(dateStr => {
            const events = eventsByDate[dateStr];
            
            // Date header
            const dateHeader = document.createElement('div');
            dateHeader.className = 'date-group-header';
            const date = new Date(dateStr);
            dateHeader.innerHTML = `
                <h3>${date.toLocaleDateString('it-IT', { 
                    weekday: 'long', 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                })}</h3>
                <span class="event-count">${events.length} eventi</span>
            `;
            eventsList.appendChild(dateHeader);
            
            // Events for this date
            events.forEach(event => {
                const eventEl = this.createListEventElement(event);
                eventsList.appendChild(eventEl);
            });
        });
    }
    
    /**
     * Crea time slots per viste week/day
     */
    createTimeSlots(view) {
        const timeColumn = document.querySelector(`#${view}-view .time-column`);
        timeColumn.innerHTML = '';
        
        // Header spacer for day headers in week view
        if (view === 'week') {
            const spacer = document.createElement('div');
            spacer.className = 'time-header-spacer';
            spacer.style.height = '60px';
            timeColumn.appendChild(spacer);
        }
        
        for (let hour = 0; hour < 24; hour++) {
            const timeSlot = document.createElement('div');
            timeSlot.className = 'time-slot';
            timeSlot.textContent = `${hour.toString().padStart(2, '0')}:00`;
            timeColumn.appendChild(timeSlot);
        }
    }
    
    /**
     * Crea elemento evento per vista settimanale
     */
    createWeekEventElement(event) {
        const eventEl = document.createElement('div');
        eventEl.className = `week-event event-${event.tipo}`;
        
        const startTime = new Date(event.data_inizio);
        const endTime = new Date(event.data_fine || event.data_inizio);
        
        // Calculate position and height
        const startHour = startTime.getHours() + startTime.getMinutes() / 60;
        const duration = (endTime.getTime() - startTime.getTime()) / (1000 * 60 * 60);
        
        eventEl.style.top = `${startHour * 60}px`; // 60px per hour
        eventEl.style.height = `${Math.max(duration * 60, 30)}px`; // Minimum 30px
        
        eventEl.innerHTML = `
            <div class="event-time">${this.formatTime(startTime)}</div>
            <div class="event-title">${event.titolo}</div>
            ${event.luogo ? `<div class="event-location">${event.luogo}</div>` : ''}
        `;
        
        eventEl.addEventListener('click', () => {
            this.showEventDetails(event);
        });
        
        return eventEl;
    }
    
    /**
     * Crea elemento evento per vista giornaliera
     */
    createDayEventElement(event) {
        const eventEl = document.createElement('div');
        eventEl.className = `day-event event-${event.tipo}`;
        
        const startTime = new Date(event.data_inizio);
        const endTime = new Date(event.data_fine || event.data_inizio);
        
        // Calculate position and height
        const startHour = startTime.getHours() + startTime.getMinutes() / 60;
        const duration = (endTime.getTime() - startTime.getTime()) / (1000 * 60 * 60);
        
        eventEl.style.top = `${startHour * 60}px`;
        eventEl.style.height = `${Math.max(duration * 60, 40)}px`;
        
        eventEl.innerHTML = `
            <div class="event-time">${this.formatTime(startTime)} - ${this.formatTime(endTime)}</div>
            <div class="event-title">${event.titolo}</div>
            ${event.descrizione ? `<div class="event-description">${event.descrizione}</div>` : ''}
            ${event.luogo ? `<div class="event-location"><i class="fas fa-map-marker-alt"></i> ${event.luogo}</div>` : ''}
            ${event.partecipanti_nomi ? `<div class="event-participants"><i class="fas fa-users"></i> ${event.partecipanti_nomi}</div>` : ''}
        `;
        
        eventEl.addEventListener('click', () => {
            this.showEventDetails(event);
        });
        
        return eventEl;
    }
    
    /**
     * Crea elemento evento per vista lista
     */
    createListEventElement(event) {
        const eventEl = document.createElement('div');
        eventEl.className = `list-event event-${event.tipo}`;
        
        const startTime = new Date(event.data_inizio);
        const endTime = new Date(event.data_fine || event.data_inizio);
        
        eventEl.innerHTML = `
            <div class="event-indicator"></div>
            <div class="event-content">
                <div class="event-header">
                    <h4 class="event-title">${event.titolo}</h4>
                    <div class="event-time">
                        <i class="fas fa-clock"></i>
                        ${this.formatTime(startTime)} - ${this.formatTime(endTime)}
                    </div>
                </div>
                ${event.descrizione ? `<p class="event-description">${event.descrizione}</p>` : ''}
                <div class="event-meta">
                    ${event.luogo ? `<span class="event-location"><i class="fas fa-map-marker-alt"></i> ${event.luogo}</span>` : ''}
                    ${event.partecipanti_nomi ? `<span class="event-participants"><i class="fas fa-users"></i> ${event.partecipanti_nomi}</span>` : ''}
                    <span class="event-type"><i class="fas fa-tag"></i> ${this.getEventTypeLabel(event.tipo)}</span>
                </div>
            </div>
            <div class="event-actions">
                <button class="btn-icon" title="Visualizza dettagli">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="btn-icon" title="Modifica">
                    <i class="fas fa-edit"></i>
                </button>
            </div>
        `;
        
        // Event listeners
        eventEl.querySelector('.event-content').addEventListener('click', () => {
            this.showEventDetails(event);
        });
        
        eventEl.querySelector('.fa-eye').closest('.btn-icon').addEventListener('click', () => {
            this.showEventDetails(event);
        });
        
        eventEl.querySelector('.fa-edit').closest('.btn-icon').addEventListener('click', () => {
            this.editEvent(event);
        });
        
        return eventEl;
    }
    
    /**
     * Apri modal nuovo evento
     */
    openNewEventModal(date = null) {
        this.currentEditingEvent = null;
        this.resetEventForm();
        
        document.getElementById('modal-title').textContent = 'Nuovo Evento';
        document.getElementById('delete-btn').classList.add('hidden');
        
        if (date) {
            document.getElementById('event-start-date').value = this.formatDate(date);
            document.getElementById('event-end-date').value = this.formatDate(date);
        }
        
        document.getElementById('event-modal').classList.add('show');
    }
    
    /**
     * Modifica evento
     */
    editEvent(event) {
        this.currentEditingEvent = event;
        this.populateEventForm(event);
        
        document.getElementById('modal-title').textContent = 'Modifica Evento';
        document.getElementById('delete-btn').classList.remove('hidden');
        
        document.getElementById('event-modal').classList.add('show');
    }
    
    /**
     * Popola form con dati evento
     */
    populateEventForm(event) {
        document.getElementById('event-id').value = event.id;
        document.getElementById('event-title').value = event.titolo;
        document.getElementById('event-description').value = event.descrizione || '';
        document.getElementById('event-location').value = event.luogo || '';
        document.getElementById('event-type').value = event.tipo;
        
        const startDate = new Date(event.data_inizio);
        const endDate = new Date(event.data_fine || event.data_inizio);
        
        document.getElementById('event-start-date').value = this.formatDate(startDate);
        document.getElementById('event-start-time').value = this.formatTime(startDate, true);
        document.getElementById('event-end-date').value = this.formatDate(endDate);
        document.getElementById('event-end-time').value = this.formatTime(endDate, true);
        
        // Select participants
        if (event.partecipanti) {
            const participantIds = event.partecipanti;
            document.querySelectorAll('input[name="partecipanti[]"]').forEach(checkbox => {
                checkbox.checked = participantIds.includes(parseInt(checkbox.value));
            });
        }
    }
    
    /**
     * Reset form evento
     */
    resetEventForm() {
        document.getElementById('event-form').reset();
        document.getElementById('event-id').value = '';
        document.querySelectorAll('input[name="partecipanti[]"]').forEach(checkbox => {
            checkbox.checked = false;
        });
    }
    
    /**
     * Salva evento
     */
    async saveEvent() {
        try {
            const formData = new FormData(document.getElementById('event-form'));
            const eventData = {};
            
            // Basic fields
            eventData.titolo = formData.get('titolo');
            eventData.descrizione = formData.get('descrizione');
            eventData.luogo = formData.get('luogo');
            eventData.tipo = formData.get('tipo');
            
            // Date and time
            const startDate = formData.get('data_inizio');
            const startTime = formData.get('ora_inizio');
            const endDate = formData.get('data_fine') || startDate;
            const endTime = formData.get('ora_fine') || startTime;
            
            eventData.data_inizio = `${startDate} ${startTime}`;
            eventData.data_fine = `${endDate} ${endTime}`;
            
            // Participants
            eventData.partecipanti = Array.from(formData.getAll('partecipanti[]')).map(id => parseInt(id));
            
            // Notifications
            eventData.invia_notifiche = formData.has('invia_notifiche');
            
            // Validation
            if (!eventData.titolo.trim()) {
                throw new Error('Il titolo è obbligatorio');
            }
            
            // Save loading state
            const saveBtn = document.getElementById('save-btn');
            const originalText = saveBtn.textContent;
            saveBtn.textContent = 'Salvataggio...';
            saveBtn.disabled = true;
            
            // If offline, store for later sync
            if (!this.isOnline) {
                const action = this.currentEditingEvent ? 'updateEvent' : 'createEvent';
                if (this.currentEditingEvent) {
                    eventData.id = this.currentEditingEvent.id;
                }
                
                await this.storePendingSync(action, eventData);
                
                // Update local cache immediately for better UX
                if (this.currentEditingEvent) {
                    // Update existing event in local cache
                    const index = this.events.findIndex(e => e.id === this.currentEditingEvent.id);
                    if (index !== -1) {
                        this.events[index] = { ...this.events[index], ...eventData };
                    }
                } else {
                    // Add new event to local cache with temporary ID
                    const tempEvent = {
                        ...eventData,
                        id: 'temp_' + Date.now(),
                        creatore_nome: this.user.nome,
                        creatore_cognome: this.user.cognome,
                        _pendingSync: true
                    };
                    this.events.push(tempEvent);
                }
                
                await this.cacheEvents(this.events);
                
                this.showToast(
                    `Evento salvato localmente. Sarà sincronizzato quando tornerai online.`,
                    'info'
                );
                this.closeEventModal();
                this.renderCurrentView();
                return;
            }
            
            let response;
            
            if (this.currentEditingEvent) {
                // Update existing event
                eventData.id = this.currentEditingEvent.id;
                response = await this.apiCall('/calendar-events.php', 'PUT', eventData);
            } else {
                // Create new event
                response = await this.apiCall('/calendar-events.php', 'POST', eventData);
            }
            
            if (response.success) {
                this.showToast(this.currentEditingEvent ? 'Evento aggiornato' : 'Evento creato', 'success');
                this.closeEventModal();
                await this.loadEvents();
            } else {
                throw new Error(response.error || 'Errore durante il salvataggio');
            }
            
        } catch (error) {
            console.error('Save event failed:', error);
            this.showToast(error.message, 'error');
        } finally {
            const saveBtn = document.getElementById('save-btn');
            saveBtn.textContent = 'Salva';
            saveBtn.disabled = false;
        }
    }
    
    /**
     * Elimina evento
     */
    async deleteEvent() {
        if (!this.currentEditingEvent) return;
        
        if (!confirm('Sei sicuro di voler eliminare questo evento?')) return;
        
        try {
            const response = await this.apiCall('/calendar-events.php', 'DELETE', {
                id: this.currentEditingEvent.id,
                invia_notifiche: true
            });
            
            if (response.success) {
                this.showToast('Evento eliminato', 'success');
                this.closeEventModal();
                await this.loadEvents();
            } else {
                throw new Error(response.error || 'Errore durante l\'eliminazione');
            }
            
        } catch (error) {
            console.error('Delete event failed:', error);
            this.showToast(error.message, 'error');
        }
    }
    
    /**
     * Chiudi modal evento
     */
    closeEventModal() {
        document.getElementById('event-modal').classList.remove('show');
        this.currentEditingEvent = null;
    }
    
    /**
     * Mostra dettagli evento
     */
    showEventDetails(event) {
        const content = document.getElementById('event-details-content');
        const startDate = new Date(event.data_inizio);
        const endDate = new Date(event.data_fine || event.data_inizio);
        
        content.innerHTML = `
            <div class="event-detail-section">
                <h4><i class="fas fa-calendar"></i> ${event.titolo}</h4>
                ${event.descrizione ? `<p>${event.descrizione}</p>` : ''}
            </div>
            
            <div class="event-detail-section">
                <h5><i class="fas fa-clock"></i> Data e Ora</h5>
                <p>
                    <strong>Inizio:</strong> ${startDate.toLocaleDateString('it-IT')} alle ${this.formatTime(startDate)}<br>
                    <strong>Fine:</strong> ${endDate.toLocaleDateString('it-IT')} alle ${this.formatTime(endDate)}
                </p>
            </div>
            
            ${event.luogo ? `
            <div class="event-detail-section">
                <h5><i class="fas fa-map-marker-alt"></i> Luogo</h5>
                <p>${event.luogo}</p>
            </div>
            ` : ''}
            
            <div class="event-detail-section">
                <h5><i class="fas fa-tag"></i> Tipo</h5>
                <span class="event-type-badge event-${event.tipo}">${this.getEventTypeLabel(event.tipo)}</span>
            </div>
            
            ${event.partecipanti_nomi ? `
            <div class="event-detail-section">
                <h5><i class="fas fa-users"></i> Partecipanti</h5>
                <p>${event.partecipanti_nomi}</p>
            </div>
            ` : ''}
            
            <div class="event-detail-section">
                <h5><i class="fas fa-user"></i> Creato da</h5>
                <p>${event.creatore_nome} ${event.creatore_cognome}</p>
            </div>
        `;
        
        // Store current event for editing
        this.currentViewingEvent = event;
        
        document.getElementById('event-details-modal').classList.add('show');
    }
    
    /**
     * Chiudi modal dettagli evento
     */
    closeEventDetailsModal() {
        document.getElementById('event-details-modal').classList.remove('show');
        this.currentViewingEvent = null;
    }
    
    /**
     * Modifica evento corrente dal modal dettagli
     */
    editCurrentEvent() {
        this.closeEventDetailsModal();
        if (this.currentViewingEvent) {
            this.editEvent(this.currentViewingEvent);
        }
    }
    
    /**
     * Gestisci azioni menu
     */
    handleMenuAction(action) {
        this.closeMenu();
        
        switch (action) {
            case 'new-event':
                this.openNewEventModal();
                break;
            case 'export-calendar':
                this.exportCalendar();
                break;
            case 'refresh':
                this.forceSync();
                break;
            case 'settings':
                this.showSettings();
                break;
            case 'help':
                this.showHelp();
                break;
        }
    }
    
    /**
     * Esporta calendario
     */
    exportCalendar() {
        const params = new URLSearchParams({
            tipo: 'calendario',
            periodo: 'mese',
            format: 'ics'
        });
        
        const exportUrl = `../esporta-calendario.php?${params}`;
        
        // Create temporary link for download
        const link = document.createElement('a');
        link.href = exportUrl;
        link.download = `calendario-${this.formatDate(this.currentDate)}.ics`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        this.showToast('Export calendario avviato', 'info');
    }
    
    /**
     * Forza sincronizzazione
     */
    async forceSync() {
        const syncBtn = document.getElementById('sync-btn');
        const icon = syncBtn.querySelector('i');
        
        icon.classList.add('fa-spin');
        this.showToast('Sincronizzazione in corso...', 'info');
        
        try {
            await this.loadEvents();
            await this.loadUsers();
            this.showToast('Sincronizzazione completata', 'success');
        } catch (error) {
            this.showToast('Errore durante la sincronizzazione', 'error');
        } finally {
            icon.classList.remove('fa-spin');
        }
    }
    
    /**
     * Setup auto refresh
     */
    setupAutoRefresh() {
        // Refresh every 5 minutes when online
        setInterval(() => {
            if (this.isOnline && document.visibilityState === 'visible') {
                this.loadEvents();
            }
        }, 5 * 60 * 1000);
        
        // Refresh when page becomes visible
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible' && this.isOnline) {
                this.loadEvents();
            }
        });
    }
    
    /**
     * Setup IndexedDB per cache offline
     */
    async setupIndexedDB() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open('NexioCalendarDB', 1);
            
            request.onerror = () => {
                console.error('Error opening IndexedDB:', request.error);
                reject(request.error);
            };
            
            request.onsuccess = () => {
                this.db = request.result;
                resolve(this.db);
            };
            
            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                
                // Events store
                if (!db.objectStoreNames.contains('events')) {
                    const eventsStore = db.createObjectStore('events', { keyPath: 'id' });
                    eventsStore.createIndex('data_inizio', 'data_inizio', { unique: false });
                    eventsStore.createIndex('azienda_id', 'azienda_id', { unique: false });
                }
                
                // Users store
                if (!db.objectStoreNames.contains('users')) {
                    const usersStore = db.createObjectStore('users', { keyPath: 'id' });
                    usersStore.createIndex('email', 'email', { unique: false });
                }
                
                // Settings store
                if (!db.objectStoreNames.contains('settings')) {
                    db.createObjectStore('settings', { keyPath: 'key' });
                }
                
                // Pending sync store
                if (!db.objectStoreNames.contains('pendingSync')) {
                    db.createObjectStore('pendingSync', { keyPath: 'id', autoIncrement: true });
                }
            };
        });
    }
    
    /**
     * Cache events in IndexedDB
     */
    async cacheEvents(events) {
        if (!this.db) return;
        
        try {
            const transaction = this.db.transaction(['events'], 'readwrite');
            const store = transaction.objectStore('events');
            
            // Clear old events
            await store.clear();
            
            // Add new events
            for (const event of events) {
                await store.add(event);
            }
            
            // Cache timestamp
            await this.setSetting('events_cache_timestamp', Date.now());
            
        } catch (error) {
            console.error('Error caching events:', error);
        }
    }
    
    /**
     * Get cached events from IndexedDB
     */
    async getCachedEvents() {
        if (!this.db) return [];
        
        try {
            const transaction = this.db.transaction(['events'], 'readonly');
            const store = transaction.objectStore('events');
            const request = store.getAll();
            
            return new Promise((resolve) => {
                request.onsuccess = () => {
                    resolve(request.result || []);
                };
                request.onerror = () => {
                    resolve([]);
                };
            });
        } catch (error) {
            console.error('Error getting cached events:', error);
            return [];
        }
    }
    
    /**
     * Cache users in IndexedDB
     */
    async cacheUsers(users) {
        if (!this.db) return;
        
        try {
            const transaction = this.db.transaction(['users'], 'readwrite');
            const store = transaction.objectStore('users');
            
            // Clear old users
            await store.clear();
            
            // Add new users
            for (const user of users) {
                await store.add(user);
            }
            
        } catch (error) {
            console.error('Error caching users:', error);
        }
    }
    
    /**
     * Get cached users from IndexedDB
     */
    async getCachedUsers() {
        if (!this.db) return [];
        
        try {
            const transaction = this.db.transaction(['users'], 'readonly');
            const store = transaction.objectStore('users');
            const request = store.getAll();
            
            return new Promise((resolve) => {
                request.onsuccess = () => {
                    resolve(request.result || []);
                };
                request.onerror = () => {
                    resolve([]);
                };
            });
        } catch (error) {
            console.error('Error getting cached users:', error);
            return [];
        }
    }
    
    /**
     * Store pending action for sync when online
     */
    async storePendingSync(action, data) {
        if (!this.db) return;
        
        try {
            const transaction = this.db.transaction(['pendingSync'], 'readwrite');
            const store = transaction.objectStore('pendingSync');
            
            await store.add({
                action,
                data,
                timestamp: Date.now()
            });
            
        } catch (error) {
            console.error('Error storing pending sync:', error);
        }
    }
    
    /**
     * Get pending sync actions
     */
    async getPendingSyncActions() {
        if (!this.db) return [];
        
        try {
            const transaction = this.db.transaction(['pendingSync'], 'readonly');
            const store = transaction.objectStore('pendingSync');
            const request = store.getAll();
            
            return new Promise((resolve) => {
                request.onsuccess = () => {
                    resolve(request.result || []);
                };
                request.onerror = () => {
                    resolve([]);
                };
            });
        } catch (error) {
            console.error('Error getting pending sync actions:', error);
            return [];
        }
    }
    
    /**
     * Clear pending sync action
     */
    async clearPendingSyncAction(id) {
        if (!this.db) return;
        
        try {
            const transaction = this.db.transaction(['pendingSync'], 'readwrite');
            const store = transaction.objectStore('pendingSync');
            await store.delete(id);
        } catch (error) {
            console.error('Error clearing pending sync action:', error);
        }
    }
    
    /**
     * Set setting value
     */
    async setSetting(key, value) {
        if (!this.db) return;
        
        try {
            const transaction = this.db.transaction(['settings'], 'readwrite');
            const store = transaction.objectStore('settings');
            await store.put({ key, value });
        } catch (error) {
            console.error('Error setting value:', error);
        }
    }
    
    /**
     * Get setting value
     */
    async getSetting(key, defaultValue = null) {
        if (!this.db) return defaultValue;
        
        try {
            const transaction = this.db.transaction(['settings'], 'readonly');
            const store = transaction.objectStore('settings');
            const request = store.get(key);
            
            return new Promise((resolve) => {
                request.onsuccess = () => {
                    resolve(request.result ? request.result.value : defaultValue);
                };
                request.onerror = () => {
                    resolve(defaultValue);
                };
            });
        } catch (error) {
            console.error('Error getting setting:', error);
            return defaultValue;
        }
    }
    
    /**
     * Toggle menu
     */
    toggleMenu() {
        const menu = document.getElementById('side-menu');
        menu.classList.toggle('show');
    }
    
    closeMenu() {
        document.getElementById('side-menu').classList.remove('show');
    }
    
    /**
     * Toggle search
     */
    toggleSearch() {
        const searchPanel = document.getElementById('search-panel');
        searchPanel.classList.toggle('show');
        if (searchPanel.classList.contains('show')) {
            document.getElementById('search-input').focus();
        }
    }
    
    closeSearch() {
        document.getElementById('search-panel').classList.remove('show');
        document.getElementById('search-input').value = '';
        document.getElementById('search-results').innerHTML = '';
    }
    
    /**
     * Esegui ricerca
     */
    performSearch(query) {
        const resultsContainer = document.getElementById('search-results');
        
        if (!query.trim()) {
            resultsContainer.innerHTML = '';
            return;
        }
        
        const searchResults = this.searchEvents(query);
        
        if (searchResults.length === 0) {
            resultsContainer.innerHTML = `
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    <p>Nessun evento trovato per "${query}"</p>
                </div>
            `;
            return;
        }
        
        resultsContainer.innerHTML = '';
        searchResults.forEach(event => {
            const resultEl = document.createElement('div');
            resultEl.className = 'search-result';
            
            const eventDate = new Date(event.data_inizio);
            resultEl.innerHTML = `
                <div class="result-content">
                    <h4>${this.highlightSearchTerm(event.titolo, query)}</h4>
                    <p class="result-date">
                        <i class="fas fa-calendar"></i>
                        ${eventDate.toLocaleDateString('it-IT')} alle ${this.formatTime(eventDate)}
                    </p>
                    ${event.descrizione ? `<p class="result-description">${this.highlightSearchTerm(event.descrizione, query)}</p>` : ''}
                </div>
                <div class="result-type event-${event.tipo}"></div>
            `;
            
            resultEl.addEventListener('click', () => {
                this.closeSearch();
                this.showEventDetails(event);
            });
            
            resultsContainer.appendChild(resultEl);
        });
    }
    
    /**
     * Cerca eventi
     */
    searchEvents(query) {
        const lowerQuery = query.toLowerCase();
        return this.events.filter(event => 
            event.titolo.toLowerCase().includes(lowerQuery) ||
            (event.descrizione && event.descrizione.toLowerCase().includes(lowerQuery)) ||
            (event.luogo && event.luogo.toLowerCase().includes(lowerQuery))
        );
    }
    
    /**
     * Evidenzia termine ricercato
     */
    highlightSearchTerm(text, term) {
        const regex = new RegExp(`(${term})`, 'gi');
        return text.replace(regex, '<mark>$1</mark>');
    }
    
    /**
     * Applica filtri
     */
    applyFilters() {
        const activeFilters = [];
        document.querySelectorAll('.filter-item input:checked').forEach(checkbox => {
            activeFilters.push(checkbox.id.replace('filter-', '').replace('meetings', 'riunione'));
        });
        
        // This would filter the events display
        this.activeFilters = activeFilters;
        this.renderCurrentView();
    }
    
    
    /**
     * PWA Install functions
     */
    showInstallPrompt() {
        document.getElementById('install-prompt').classList.remove('hidden');
        
        setTimeout(() => {
            document.getElementById('install-prompt').classList.add('hidden');
        }, 10000); // Hide after 10 seconds
    }
    
    hideInstallPrompt() {
        document.getElementById('install-prompt').classList.add('hidden');
    }
    
    async installPWA() {
        if (!this.deferredPrompt) return;
        
        this.deferredPrompt.prompt();
        const { outcome } = await this.deferredPrompt.userChoice;
        
        if (outcome === 'accepted') {
            console.log('User accepted PWA install');
        } else {
            console.log('User dismissed PWA install');
        }
        
        this.deferredPrompt = null;
        this.hideInstallPrompt();
    }
    
    /**
     * Offline functions
     */
    showOfflineIndicator() {
        document.getElementById('offline-indicator').classList.remove('hidden');
    }
    
    hideOfflineIndicator() {
        document.getElementById('offline-indicator').classList.add('hidden');
    }
    
    async syncPendingChanges() {
        if ('serviceWorker' in navigator && 'sync' in window.ServiceWorkerRegistration.prototype) {
            try {
                const registration = await navigator.serviceWorker.ready;
                await registration.sync.register('sync-events');
            } catch (error) {
                console.error('Background sync registration failed:', error);
                // Fallback to manual sync
                this.processPendingSyncActions();
            }
        } else {
            // No background sync support, process immediately
            this.processPendingSyncActions();
        }
    }
    
    /**
     * Process pending sync actions
     */
    async processPendingSyncActions() {
        if (!this.isOnline) return;
        
        const pendingActions = await this.getPendingSyncActions();
        if (pendingActions.length === 0) return;
        
        this.showToast(`Sincronizzazione ${pendingActions.length} azioni in sospeso...`, 'info');
        
        let successCount = 0;
        let errorCount = 0;
        
        for (const pendingAction of pendingActions) {
            try {
                let response;
                
                switch (pendingAction.action) {
                    case 'createEvent':
                        response = await this.apiCall('/calendar-events.php', 'POST', pendingAction.data);
                        break;
                    case 'updateEvent':
                        response = await this.apiCall('/calendar-events.php', 'PUT', pendingAction.data);
                        break;
                    case 'deleteEvent':
                        response = await this.apiCall('/calendar-events.php', 'DELETE', pendingAction.data);
                        break;
                    default:
                        console.warn('Unknown pending action:', pendingAction.action);
                        continue;
                }
                
                if (response.success) {
                    await this.clearPendingSyncAction(pendingAction.id);
                    successCount++;
                } else {
                    throw new Error(response.error || 'Sync failed');
                }
                
            } catch (error) {
                console.error('Failed to sync action:', pendingAction.action, error);
                errorCount++;
                
                // Don't clear failed actions, they will be retried later
            }
        }
        
        if (successCount > 0) {
            this.showToast(`Sincronizzate ${successCount} azioni`, 'success');
            await this.loadEvents(); // Refresh events after sync
        }
        
        if (errorCount > 0) {
            this.showToast(`${errorCount} azioni non sincronizzate`, 'warning');
        }
    }
    
    /**
     * Load user preferences
     */
    async loadUserPreferences() {
        try {
            // Load saved view preference
            const savedView = await this.getSetting('calendar-view', 'month');
            if (savedView && savedView !== this.currentView) {
                this.changeView(savedView);
            }
            
            // Load other preferences
            const autoSync = await this.getSetting('auto-sync', true);
            this.autoSyncEnabled = autoSync;
            
        } catch (error) {
            console.error('Failed to load user preferences:', error);
        }
    }
    
    /**
     * Show day events modal
     */
    showDayEvents(date, events) {
        const modal = document.createElement('div');
        modal.className = 'modal day-events-modal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Eventi per ${date.toLocaleDateString('it-IT', {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    })}</h3>
                    <button class="close-btn" onclick="this.closest('.modal').remove()"><i class="fas fa-times"></i></button>
                </div>
                <div class="modal-body">
                    ${events.map(event => `
                        <div class="day-event-item" data-event-id="${event.id}">
                            <div class="event-time">${this.formatTime(new Date(event.data_inizio))}</div>
                            <div class="event-content">
                                <h4>${event.titolo}</h4>
                                ${event.descrizione ? `<p>${event.descrizione}</p>` : ''}
                                ${event.luogo ? `<div class="event-location"><i class="fas fa-map-marker-alt"></i> ${event.luogo}</div>` : ''}
                            </div>
                            <div class="event-actions">
                                <button class="btn-icon" onclick="calendar.showEventDetails(${JSON.stringify(event).replace(/"/g, '&quot;')})" title="Dettagli">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn-icon" onclick="calendar.editEvent(${JSON.stringify(event).replace(/"/g, '&quot;')})" title="Modifica">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </div>
                    `).join('')}
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary" onclick="calendar.openNewEventModal(new Date('${this.formatDate(date)}'))">
                        <i class="fas fa-plus"></i> Nuovo Evento
                    </button>
                    <button class="btn btn-secondary" onclick="this.closest('.modal').remove()">Chiudi</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        modal.classList.add('show');
        
        // Auto-remove on backdrop click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });
    }
    
    /**
     * Show settings modal
     */
    showSettings() {
        const modal = document.createElement('div');
        modal.className = 'modal settings-modal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-cog"></i> Impostazioni</h3>
                    <button class="close-btn" onclick="this.closest('.modal').remove()"><i class="fas fa-times"></i></button>
                </div>
                <div class="modal-body">
                    <div class="settings-section">
                        <h4>Sincronizzazione</h4>
                        <label class="checkbox-label">
                            <input type="checkbox" id="auto-sync-setting" ${this.autoSyncEnabled ? 'checked' : ''}>
                            <span class="checkmark"></span>
                            Sincronizzazione automatica
                        </label>
                        <small>Sincronizza automaticamente gli eventi quando sei online</small>
                    </div>
                    
                    <div class="settings-section">
                        <h4>Vista Predefinita</h4>
                        <select id="default-view-setting" value="${this.currentView}">
                            <option value="month" ${this.currentView === 'month' ? 'selected' : ''}>Mese</option>
                            <option value="week" ${this.currentView === 'week' ? 'selected' : ''}>Settimana</option>
                            <option value="day" ${this.currentView === 'day' ? 'selected' : ''}>Giorno</option>
                            <option value="list" ${this.currentView === 'list' ? 'selected' : ''}>Lista</option>
                        </select>
                    </div>
                    
                    <div class="settings-section">
                        <h4>Cache</h4>
                        <button class="btn btn-secondary" onclick="calendar.clearCache()">
                            <i class="fas fa-trash"></i> Svuota Cache Locale
                        </button>
                        <small>Rimuove tutti i dati salvati localmente</small>
                    </div>
                    
                    <div class="settings-section">
                        <h4>Informazioni</h4>
                        <p><strong>Versione:</strong> 1.0.0</p>
                        <p><strong>Ultima Sincronizzazione:</strong> <span id="last-sync-time">-</span></p>
                        <p><strong>Eventi in Cache:</strong> ${this.events.length}</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary" onclick="calendar.saveSettings()">Salva</button>
                    <button class="btn btn-secondary" onclick="this.closest('.modal').remove()">Annulla</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        modal.classList.add('show');
        
        // Load last sync time
        this.getSetting('events_cache_timestamp').then(timestamp => {
            if (timestamp) {
                document.getElementById('last-sync-time').textContent = 
                    new Date(timestamp).toLocaleString('it-IT');
            }
        });
    }
    
    /**
     * Save settings
     */
    async saveSettings() {
        try {
            const autoSync = document.getElementById('auto-sync-setting').checked;
            const defaultView = document.getElementById('default-view-setting').value;
            
            await this.setSetting('auto-sync', autoSync);
            await this.setSetting('calendar-view', defaultView);
            
            this.autoSyncEnabled = autoSync;
            
            this.showToast('Impostazioni salvate', 'success');
            document.querySelector('.settings-modal').remove();
            
        } catch (error) {
            console.error('Failed to save settings:', error);
            this.showToast('Errore nel salvataggio delle impostazioni', 'error');
        }
    }
    
    /**
     * Clear cache
     */
    async clearCache() {
        if (!confirm('Sei sicuro di voler svuotare la cache locale? Questo rimuoverà tutti i dati salvati offline.')) {
            return;
        }
        
        try {
            if (this.db) {
                const transaction = this.db.transaction(['events', 'users', 'settings', 'pendingSync'], 'readwrite');
                await transaction.objectStore('events').clear();
                await transaction.objectStore('users').clear();
                await transaction.objectStore('pendingSync').clear();
                // Don't clear settings
            }
            
            this.events = [];
            this.users = [];
            this.renderCurrentView();
            
            this.showToast('Cache svuotata', 'success');
            document.querySelector('.settings-modal').remove();
            
            // Reload data if online
            if (this.isOnline) {
                await this.loadInitialData();
            }
            
        } catch (error) {
            console.error('Failed to clear cache:', error);
            this.showToast('Errore nello svuotamento della cache', 'error');
        }
    }
    
    /**
     * Show help modal
     */
    showHelp() {
        const modal = document.createElement('div');
        modal.className = 'modal help-modal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-question-circle"></i> Guida</h3>
                    <button class="close-btn" onclick="this.closest('.modal').remove()"><i class="fas fa-times"></i></button>
                </div>
                <div class="modal-body">
                    <div class="help-section">
                        <h4><i class="fas fa-calendar"></i> Navigazione</h4>
                        <ul>
                            <li><strong>Swipe orizzontale:</strong> Cambia mese/settimana/giorno</li>
                            <li><strong>Tap su giorno:</strong> Visualizza eventi o crea nuovo evento</li>
                            <li><strong>Pulsante +:</strong> Crea nuovo evento rapidamente</li>
                        </ul>
                    </div>
                    
                    <div class="help-section">
                        <h4><i class="fas fa-wifi"></i> Modalità Offline</h4>
                        <ul>
                            <li>Gli eventi vengono salvati localmente</li>
                            <li>Puoi creare/modificare eventi offline</li>
                            <li>Le modifiche saranno sincronizzate automaticamente</li>
                        </ul>
                    </div>
                    
                    <div class="help-section">
                        <h4><i class="fas fa-mobile-alt"></i> Installazione</h4>
                        <ul>
                            <li>Usa il pulsante "Installa" per aggiungere l'app alla home</li>
                            <li>L'app funziona anche senza connessione</li>
                            <li>Riceverai notifiche per gli aggiornamenti</li>
                        </ul>
                    </div>
                    
                    <div class="help-section">
                        <h4><i class="fas fa-search"></i> Ricerca</h4>
                        <ul>
                            <li>Cerca per titolo, descrizione o luogo</li>
                            <li>I risultati vengono evidenziati</li>
                            <li>Tocca un risultato per vedere i dettagli</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="this.closest('.modal').remove()">Chiudi</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        modal.classList.add('show');
    }
    
    /**
     * Enhanced error handling for API calls
     */
    async apiCall(endpoint, method = 'GET', data = null) {
        const url = this.apiBase + endpoint;
        const options = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        };
        
        if (data && method !== 'GET') {
            options.body = JSON.stringify(data);
        }
        
        try {
            const response = await fetch(url, options);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const result = await response.json();
            return result;
            
        } catch (error) {
            // If network error and we have cached data, inform about offline mode
            if (!this.isOnline && (error.name === 'TypeError' || error.message.includes('fetch'))) {
                throw new Error('Modalità offline attiva');
            }
            
            throw error;
        }
    }
    
    /**
     * UI Helper functions
     */
    showToast(message, type = 'info') {
        const toastContainer = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        const icon = type === 'success' ? 'check-circle' : 
                   type === 'error' ? 'exclamation-circle' :
                   type === 'warning' ? 'exclamation-triangle' : 'info-circle';
        
        toast.innerHTML = `
            <i class="fas fa-${icon}"></i>
            <span>${message}</span>
            <button class="toast-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        toastContainer.appendChild(toast);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, 5000);
    }
    
    showError(message) {
        this.showToast(message, 'error');
    }
    
    showLoading() {
        document.getElementById('loading-screen').classList.remove('hidden');
    }
    
    hideLoading() {
        document.getElementById('loading-screen').classList.add('hidden');
    }
    
    showLoginRequired() {
        document.body.innerHTML = `
            <div class="login-required">
                <div class="login-content">
                    <i class="fas fa-lock"></i>
                    <h2>Accesso Richiesto</h2>
                    <p>È necessario effettuare l'accesso per utilizzare il calendario.</p>
                    <a href="../index.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Accedi
                    </a>
                </div>
            </div>
        `;
    }
    
    /**
     * Date/Time utility functions
     */
    formatDate(date) {
        return date.toISOString().split('T')[0];
    }
    
    formatTime(date, input = false) {
        if (input) {
            return date.toTimeString().slice(0, 5);
        }
        return date.toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' });
    }
    
    isToday(date) {
        const today = new Date();
        return date.toDateString() === today.toDateString();
    }
    
    getStartOfWeek(date) {
        const d = new Date(date);
        const day = d.getDay();
        const diff = d.getDate() - day + (day === 0 ? -6 : 1); // Adjust when day is Sunday
        return new Date(d.setDate(diff));
    }
    
    getDayName(date, short = false) {
        return date.toLocaleDateString('it-IT', { 
            weekday: short ? 'short' : 'long' 
        });
    }
    
    getEventsForDate(date) {
        const dateStr = this.formatDate(date);
        return this.events.filter(event => {
            const eventDate = this.formatDate(new Date(event.data_inizio));
            return eventDate === dateStr;
        });
    }
    
    groupEventsByDate(events) {
        return events.reduce((groups, event) => {
            const date = this.formatDate(new Date(event.data_inizio));
            if (!groups[date]) {
                groups[date] = [];
            }
            groups[date].push(event);
            return groups;
        }, {});
    }
    
    getEventTypeLabel(type) {
        const labels = {
            'riunione': 'Riunione',
            'formazione': 'Formazione', 
            'conferenza': 'Conferenza',
            'altro': 'Altro'
        };
        return labels[type] || type;
    }
    
    /**
     * Debounce utility
     */
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
}

// Initialize the app when DOM is loaded
let calendar;

document.addEventListener('DOMContentLoaded', () => {
    calendar = new NexioCalendar();
});

// Export for global access
window.calendar = calendar;