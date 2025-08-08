/**
 * Calendar Mobile JavaScript
 * Funzionalità avanzate per il calendario mobile PWA
 */

class CalendarMobile {
    constructor() {
        this.currentDate = new Date();
        this.events = [];
        this.tasks = [];
        this.view = 'month';
        this.isOnline = navigator.onLine;
        this.eventQueue = []; // Per azioni offline
        this.taskQueue = []; // Per task offline
        this.showTasks = true;
        this.taskFilter = 'all'; // all, assigned, created
        
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        this.setupSwipeGestures();
        this.setupOfflineHandling();
        this.loadEvents();
        this.loadTasks();
        
        // Setup PWA install prompt
        this.setupInstallPrompt();
        
        // Setup task-specific features
        this.setupTaskEventListeners();
    }
    
    setupEventListeners() {
        // Online/offline status
        window.addEventListener('online', () => {
            this.isOnline = true;
            this.syncOfflineActions();
            this.showNotification('Connesso', 'success');
        });
        
        window.addEventListener('offline', () => {
            this.isOnline = false;
            this.showNotification('Modalità offline', 'info');
        });
        
        // Back button handling
        window.addEventListener('popstate', (e) => {
            if (e.state) {
                this.changeView(e.state.view, e.state.date);
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') this.navigateMonth(-1);
            if (e.key === 'ArrowRight') this.navigateMonth(1);
            if (e.key === 'Escape') this.closeModal();
        });
    }
    
    setupSwipeGestures() {
        let startX = 0;
        let startY = 0;
        let endX = 0;
        let endY = 0;
        
        const calendar = document.getElementById('calendarBody');
        if (!calendar) return;
        
        calendar.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
        }, { passive: true });
        
        calendar.addEventListener('touchend', (e) => {
            endX = e.changedTouches[0].clientX;
            endY = e.changedTouches[0].clientY;
            
            const deltaX = endX - startX;
            const deltaY = endY - startY;
            
            // Determina direzione swipe
            if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > 50) {
                if (deltaX > 0) {
                    // Swipe right - mese precedente
                    this.navigateMonth(-1);
                } else {
                    // Swipe left - mese successivo
                    this.navigateMonth(1);
                }
                
                // Haptic feedback
                this.vibrate([10]);
            }
        }, { passive: true });
    }
    
    setupOfflineHandling() {
        // Usa IndexedDB per storage offline
        this.openDB().then(db => {
            this.db = db;
            this.loadOfflineEvents();
        });
    }
    
    openDB() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open('CalendarDB', 1);
            
            request.onerror = () => reject(request.error);
            request.onsuccess = () => resolve(request.result);
            
            request.onupgradeneeded = (e) => {
                const db = e.target.result;
                
                // Store per eventi
                if (!db.objectStoreNames.contains('events')) {
                    const eventStore = db.createObjectStore('events', { keyPath: 'id' });
                    eventStore.createIndex('date', 'data_inizio', { unique: false });
                }
                
                // Store per task
                if (!db.objectStoreNames.contains('tasks')) {
                    const taskStore = db.createObjectStore('tasks', { keyPath: 'id' });
                    taskStore.createIndex('status', 'stato', { unique: false });
                    taskStore.createIndex('priority', 'priorita', { unique: false });
                    taskStore.createIndex('due_date', 'data_scadenza', { unique: false });
                }
                
                // Store per azioni offline
                if (!db.objectStoreNames.contains('offline_actions')) {
                    db.createObjectStore('offline_actions', { keyPath: 'id', autoIncrement: true });
                }
                
                // Store per sync mobile task
                if (!db.objectStoreNames.contains('mobile_task_sync')) {
                    const syncStore = db.createObjectStore('mobile_task_sync', { keyPath: 'id', autoIncrement: true });
                    syncStore.createIndex('timestamp', 'timestamp', { unique: false });
                    syncStore.createIndex('type', 'type', { unique: false });
                }
            };
        });
    }
    
    async loadEvents(date = null) {
        if (!date) date = this.currentDate;
        
        const startOfMonth = new Date(date.getFullYear(), date.getMonth(), 1).toISOString().split('T')[0];
        const endOfMonth = new Date(date.getFullYear(), date.getMonth() + 1, 0).toISOString().split('T')[0];
        
        if (this.isOnline) {
            try {
                const response = await fetch(`backend/api/calendar-events.php?start=${startOfMonth}&end=${endOfMonth}`);
                const data = await response.json();
                
                if (data.success) {
                    this.events = data.events;
                    this.saveEventsOffline(data.events);
                    this.renderCalendar();
                }
            } catch (error) {
                console.error('Errore caricamento eventi:', error);
                this.loadOfflineEvents();
            }
        } else {
            this.loadOfflineEvents();
        }
    }
    
    async saveEventsOffline(events) {
        if (!this.db) return;
        
        const transaction = this.db.transaction(['events'], 'readwrite');
        const store = transaction.objectStore('events');
        
        // Pulisci eventi vecchi del mese
        const index = store.index('date');
        const range = IDBKeyRange.bound(
            new Date(this.currentDate.getFullYear(), this.currentDate.getMonth(), 1).toISOString(),
            new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() + 1, 0).toISOString()
        );
        
        const clearRequest = index.openCursor(range);
        clearRequest.onsuccess = (e) => {
            const cursor = e.target.result;
            if (cursor) {
                cursor.delete();
                cursor.continue();
            }
        };
        
        // Salva nuovi eventi
        events.forEach(event => {
            store.put(event);
        });
    }
    
    async loadOfflineEvents() {
        if (!this.db) return;
        
        const transaction = this.db.transaction(['events'], 'readonly');
        const store = transaction.objectStore('events');
        const request = store.getAll();
        
        request.onsuccess = () => {
            this.events = request.result;
            this.renderCalendar();
        };
    }
    
    renderCalendar() {
        const calendar = document.getElementById('calendarBody');
        if (!calendar) return;
        
        // Organizza eventi per data
        const eventsByDate = {};
        this.events.forEach(event => {
            const date = event.data_inizio.split(' ')[0];
            if (!eventsByDate[date]) eventsByDate[date] = [];
            eventsByDate[date].push(event);
        });
        
        // Aggiorna indicatori eventi
        calendar.querySelectorAll('.calendar-day').forEach(day => {
            const date = day.dataset.date;
            const hasEvents = eventsByDate[date] && eventsByDate[date].length > 0;
            
            if (hasEvents) {
                day.classList.add('has-events');
                
                // Rimuovi indicatori esistenti
                day.querySelectorAll('.event-indicator').forEach(indicator => indicator.remove());
                
                // Aggiungi nuovi indicatori
                const eventCount = Math.min(eventsByDate[date].length, 3);
                for (let i = 0; i < eventCount; i++) {
                    const indicator = document.createElement('div');
                    indicator.className = 'event-indicator';
                    day.appendChild(indicator);
                }
            } else {
                day.classList.remove('has-events');
                day.querySelectorAll('.event-indicator').forEach(indicator => indicator.remove());
            }
        });
    }
    
    navigateMonth(direction) {
        this.currentDate.setMonth(this.currentDate.getMonth() + direction);
        const newDate = this.currentDate.toISOString().split('T')[0];
        
        // Aggiorna URL senza ricaricare
        const url = new URL(window.location);
        url.searchParams.set('date', newDate);
        history.pushState({ view: this.view, date: newDate }, '', url);
        
        // Aggiorna header
        document.getElementById('currentMonth').textContent = 
            this.currentDate.toLocaleDateString('it-IT', { month: 'long', year: 'numeric' });
        
        // Ricarica eventi
        this.loadEvents();
    }
    
    showDayEvents(date) {
        const dayEvents = this.events.filter(event => event.data_inizio.startsWith(date));
        
        if (dayEvents.length > 0) {
            this.showEventModal(dayEvents, date);
        } else {
            this.showCreateEventModal(date);
        }
    }
    
    showEventModal(events, date) {
        const modal = this.createModal('Eventi del ' + new Date(date).toLocaleDateString('it-IT'));
        
        const eventsList = document.createElement('div');
        eventsList.className = 'events-modal-list';
        
        events.forEach(event => {
            const eventItem = document.createElement('div');
            eventItem.className = 'event-modal-item';
            eventItem.innerHTML = `
                <div class="event-modal-title">${event.titolo}</div>
                <div class="event-modal-time">
                    ${new Date(event.data_inizio).toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' })}
                    ${event.data_fine ? ' - ' + new Date(event.data_fine).toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' }) : ''}
                </div>
                ${event.descrizione ? `<div class="event-modal-description">${event.descrizione}</div>` : ''}
            `;
            eventItem.onclick = () => this.viewEvent(event.id);
            eventsList.appendChild(eventItem);
        });
        
        modal.appendChild(eventsList);
        this.showModal(modal);
    }
    
    showCreateEventModal(date = null) {
        const modal = this.createModal('Nuovo Evento');
        
        const form = document.createElement('form');
        form.className = 'event-form';
        form.innerHTML = `
            <div class="form-group">
                <label class="form-label">Titolo</label>
                <input type="text" class="form-input" name="titolo" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Data</label>
                    <input type="date" class="form-input" name="data" value="${date || new Date().toISOString().split('T')[0]}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Ora inizio</label>
                    <input type="time" class="form-input" name="ora_inizio" value="09:00" required>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Ora fine (opzionale)</label>
                <input type="time" class="form-input" name="ora_fine">
            </div>
            
            <div class="form-group">
                <label class="form-label">Descrizione</label>
                <textarea class="form-input form-textarea" name="descrizione" rows="3"></textarea>
            </div>
            
            <div class="button-group">
                <button type="button" class="btn-secondary" onclick="calendarMobile.closeModal()">Annulla</button>
                <button type="submit" class="btn-primary">Crea Evento</button>
            </div>
        `;
        
        form.onsubmit = (e) => {
            e.preventDefault();
            this.createEvent(new FormData(form));
        };
        
        modal.appendChild(form);
        this.showModal(modal);
    }
    
    async createEvent(formData) {
        const eventData = {
            titolo: formData.get('titolo'),
            data_inizio: formData.get('data') + ' ' + formData.get('ora_inizio'),
            data_fine: formData.get('ora_fine') ? formData.get('data') + ' ' + formData.get('ora_fine') : null,
            descrizione: formData.get('descrizione')
        };
        
        if (this.isOnline) {
            try {
                const response = await fetch('backend/api/calendar-events.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(eventData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    this.showNotification('Evento creato', 'success');
                    this.closeModal();
                    this.loadEvents();
                    this.vibrate([10, 100, 10]);
                } else {
                    throw new Error(result.error);
                }
            } catch (error) {
                this.showNotification('Errore creazione evento', 'error');
                console.error(error);
            }
        } else {
            // Salva in coda per sync successivo
            this.queueOfflineAction('create', eventData);
            this.showNotification('Evento salvato per sincronizzazione', 'info');
            this.closeModal();
        }
    }
    
    queueOfflineAction(action, data) {
        if (!this.db) return;
        
        const transaction = this.db.transaction(['offline_actions'], 'readwrite');
        const store = transaction.objectStore('offline_actions');
        
        store.add({
            action,
            data,
            timestamp: new Date().toISOString()
        });
    }
    
    async syncOfflineActions() {
        if (!this.db || !this.isOnline) return;
        
        const transaction = this.db.transaction(['offline_actions'], 'readwrite');
        const store = transaction.objectStore('offline_actions');
        const request = store.getAll();
        
        request.onsuccess = async () => {
            const actions = request.result;
            
            for (const action of actions) {
                try {
                    if (action.type === 'event') {
                        if (action.action === 'create') {
                            await fetch('backend/api/calendar-events.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify(action.data)
                            });
                        }
                    } else if (action.type === 'task') {
                        await this.syncSingleTaskAction(action);
                    }
                    
                    // Rimuovi azione dalla coda
                    store.delete(action.id);
                } catch (error) {
                    console.error('Errore sync azione:', error);
                }
            }
            
            this.loadEvents();
            this.loadTasks();
        };
    }
    
    async syncSingleTaskAction(action) {
        let endpoint = '../backend/api/task-mobile-api.php?action=tasks';
        let method = 'POST';
        
        if (action.action === 'update' || action.action === 'complete') {
            method = 'PUT';
        } else if (action.action === 'progress') {
            endpoint = '../backend/api/task-mobile-api.php?action=update_progress';
        }
        
        await fetch(endpoint, {
            method: method,
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.csrfToken
            },
            body: JSON.stringify(action.data)
        });
    }
    
    createModal(title) {
        const modalOverlay = document.createElement('div');
        modalOverlay.className = 'modal-overlay';
        modalOverlay.id = 'eventModal';
        
        const modalContent = document.createElement('div');
        modalContent.className = 'modal-content';
        
        const modalHeader = document.createElement('div');
        modalHeader.className = 'modal-header';
        modalHeader.innerHTML = `
            <div class="modal-title">${title}</div>
            <button class="modal-close" onclick="calendarMobile.closeModal()">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        modalContent.appendChild(modalHeader);
        modalOverlay.appendChild(modalContent);
        
        return modalContent;
    }
    
    showModal(modal) {
        document.body.appendChild(modal.parentNode);
        
        // Animazione di apertura
        setTimeout(() => {
            modal.parentNode.classList.add('active');
        }, 10);
        
        // Chiudi con click esterno
        modal.parentNode.onclick = (e) => {
            if (e.target === modal.parentNode) {
                this.closeModal();
            }
        };
    }
    
    closeModal() {
        const modal = document.getElementById('eventModal');
        if (modal) {
            modal.classList.remove('active');
            setTimeout(() => modal.remove(), 300);
        }
    }
    
    viewEvent(eventId) {
        window.location.href = `calendario-eventi.php?action=visualizza&id=${eventId}`;
    }
    
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <div style="display: flex; align-items: center; gap: 12px;">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => notification.classList.add('show'), 10);
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
    
    vibrate(pattern = [10]) {
        if ('vibrate' in navigator) {
            navigator.vibrate(pattern);
        }
    }
    
    setupInstallPrompt() {
        let deferredPrompt;
        
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            
            // Mostra banner di installazione
            this.showInstallBanner(deferredPrompt);
        });
        
        window.addEventListener('appinstalled', () => {
            this.showNotification('App installata con successo!', 'success');
            deferredPrompt = null;
        });
    }
    
    showInstallBanner(deferredPrompt) {
        const banner = document.createElement('div');
        banner.id = 'installBanner';
        banner.innerHTML = `
            <div style="position: fixed; top: 0; left: 0; right: 0; background: linear-gradient(135deg, #4299e1, #3182ce); color: white; padding: 16px; z-index: 10000; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 12px rgba(0,0,0,0.2);">
                <div>
                    <div style="font-weight: 600; margin-bottom: 4px;">📱 Installa l'App</div>
                    <div style="font-size: 14px; opacity: 0.9;">Accesso rapido al calendario</div>
                </div>
                <div style="display: flex; gap: 8px;">
                    <button onclick="calendarMobile.installApp()" style="background: white; color: #4299e1; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 14px;">Installa</button>
                    <button onclick="document.getElementById('installBanner').remove()" style="background: rgba(255,255,255,0.2); color: white; border: none; padding: 8px 12px; border-radius: 6px;">✕</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(banner);
        
        // Auto-hide dopo 10 secondi
        setTimeout(() => {
            if (document.getElementById('installBanner')) {
                banner.remove();
            }
        }, 10000);
    }
    
    async installApp() {
        const deferredPrompt = window.deferredPrompt;
        if (deferredPrompt) {
            deferredPrompt.prompt();
            const choiceResult = await deferredPrompt.userChoice;
            
            if (choiceResult.outcome === 'accepted') {
                this.showNotification('Installazione in corso...', 'info');
            }
            
            window.deferredPrompt = null;
            document.getElementById('installBanner')?.remove();
        }
    }
    
    // ===================== TASK MANAGEMENT FUNCTIONS =====================
    
    setupTaskEventListeners() {
        // Task filter toggle
        const taskToggle = document.getElementById('taskToggle');
        if (taskToggle) {
            taskToggle.addEventListener('change', (e) => {
                this.showTasks = e.target.checked;
                this.renderCalendar();
            });
        }
        
        // Task filter buttons
        const taskFilterButtons = document.querySelectorAll('.task-filter-btn');
        taskFilterButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.taskFilter = e.target.dataset.filter;
                document.querySelectorAll('.task-filter-btn').forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');
                this.loadTasks();
            });
        });
        
        // Quick task creation
        const quickTaskBtn = document.getElementById('quickTaskBtn');
        if (quickTaskBtn) {
            quickTaskBtn.addEventListener('click', () => {
                this.showQuickTaskModal();
            });
        }
    }
    
    async loadTasks(date = null) {
        if (!date) date = this.currentDate;
        
        const startOfMonth = new Date(date.getFullYear(), date.getMonth(), 1).toISOString().split('T')[0];
        const endOfMonth = new Date(date.getFullYear(), date.getMonth() + 1, 0).toISOString().split('T')[0];
        
        if (this.isOnline) {
            try {
                const params = new URLSearchParams({
                    action: 'tasks',
                    type: this.taskFilter,
                    start: startOfMonth,
                    end: endOfMonth,
                    limit: 100
                });
                
                const response = await fetch(`../backend/api/task-mobile-api.php?${params}`);
                const data = await response.json();
                
                if (data.success) {
                    this.tasks = data.tasks;
                    this.saveTasksOffline(data.tasks);
                    this.renderCalendar();
                    
                    // Update task statistics in UI
                    this.updateTaskStats(data.tasks);
                }
            } catch (error) {
                console.error('Errore caricamento task:', error);
                this.loadTasksOffline();
            }
        } else {
            this.loadTasksOffline();
        }
    }
    
    async saveTasksOffline(tasks) {
        if (!this.db) return;
        
        const transaction = this.db.transaction(['tasks'], 'readwrite');
        const store = transaction.objectStore('tasks');
        
        // Clear old tasks for this month
        const index = store.index('due_date');
        const startOfMonth = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth(), 1).toISOString();
        const endOfMonth = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() + 1, 0).toISOString();
        
        // Save new tasks
        tasks.forEach(task => {
            store.put(task);
        });
    }
    
    async loadTasksOffline() {
        if (!this.db) return;
        
        const transaction = this.db.transaction(['tasks'], 'readonly');
        const store = transaction.objectStore('tasks');
        const request = store.getAll();
        
        request.onsuccess = () => {
            this.tasks = request.result;
            this.renderCalendar();
            this.updateTaskStats(this.tasks);
        };
    }
    
    renderCalendar() {
        // Call original render but include tasks
        this.renderCalendarWithTasks();
    }
    
    renderCalendarWithTasks() {
        const calendar = document.getElementById('calendarBody');
        if (!calendar) return;
        
        // Organize events and tasks by date
        const eventsByDate = {};
        const tasksByDate = {};
        
        this.events.forEach(event => {
            const date = event.data_inizio.split(' ')[0];
            if (!eventsByDate[date]) eventsByDate[date] = [];
            eventsByDate[date].push(event);
        });
        
        if (this.showTasks) {
            this.tasks.forEach(task => {
                const date = task.data_scadenza || task.data_fine || task.created_at?.split(' ')[0];
                if (date) {
                    if (!tasksByDate[date]) tasksByDate[date] = [];
                    tasksByDate[date].push(task);
                }
            });
        }
        
        // Update calendar days with indicators
        calendar.querySelectorAll('.calendar-day').forEach(day => {
            const date = day.dataset.date;
            const hasEvents = eventsByDate[date] && eventsByDate[date].length > 0;
            const hasTasks = tasksByDate[date] && tasksByDate[date].length > 0;
            
            // Clear existing indicators
            day.querySelectorAll('.event-indicator, .task-indicator').forEach(indicator => indicator.remove());
            
            // Add event indicators
            if (hasEvents) {
                day.classList.add('has-events');
                const eventCount = Math.min(eventsByDate[date].length, 2);
                for (let i = 0; i < eventCount; i++) {
                    const indicator = document.createElement('div');
                    indicator.className = 'event-indicator';
                    day.appendChild(indicator);
                }
            } else {
                day.classList.remove('has-events');
            }
            
            // Add task indicators
            if (hasTasks) {
                day.classList.add('has-tasks');
                const taskCount = Math.min(tasksByDate[date].length, 3);
                
                tasksByDate[date].slice(0, taskCount).forEach((task, i) => {
                    const indicator = document.createElement('div');
                    indicator.className = `task-indicator priority-${task.priorita || 'media'}`;
                    indicator.style.left = `${5 + (i * 8)}px`;
                    indicator.style.bottom = '2px';
                    
                    // Add status indication
                    if (task.stato === 'completato') {
                        indicator.classList.add('completed');
                    } else if (task.is_overdue) {
                        indicator.classList.add('overdue');
                    }
                    
                    day.appendChild(indicator);
                });
            } else {
                day.classList.remove('has-tasks');
            }
        });
    }
    
    showDayEvents(date) {
        const dayEvents = this.events.filter(event => event.data_inizio.startsWith(date));
        const dayTasks = this.tasks.filter(task => {
            const taskDate = task.data_scadenza || task.data_fine || task.created_at?.split(' ')[0];
            return taskDate === date;
        });
        
        if (dayEvents.length > 0 || dayTasks.length > 0) {
            this.showDayModal(dayEvents, dayTasks, date);
        } else {
            this.showCreateEventModal(date);
        }
    }
    
    showDayModal(events, tasks, date) {
        const modal = this.createModal('Eventi e Task del ' + new Date(date).toLocaleDateString('it-IT'));
        
        const content = document.createElement('div');
        content.className = 'day-modal-content';
        
        // Events section
        if (events.length > 0) {
            const eventsSection = document.createElement('div');
            eventsSection.className = 'day-section';
            eventsSection.innerHTML = `<h3><i class="fas fa-calendar"></i> Eventi (${events.length})</h3>`;
            
            const eventsList = document.createElement('div');
            eventsList.className = 'events-modal-list';
            
            events.forEach(event => {
                const eventItem = document.createElement('div');
                eventItem.className = 'event-modal-item';
                eventItem.innerHTML = `
                    <div class="event-modal-title">${event.titolo}</div>
                    <div class="event-modal-time">
                        ${new Date(event.data_inizio).toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' })}
                        ${event.data_fine ? ' - ' + new Date(event.data_fine).toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' }) : ''}
                    </div>
                    ${event.descrizione ? `<div class="event-modal-description">${event.descrizione}</div>` : ''}
                `;
                eventItem.onclick = () => this.viewEvent(event.id);
                eventsList.appendChild(eventItem);
            });
            
            eventsSection.appendChild(eventsList);
            content.appendChild(eventsSection);
        }
        
        // Tasks section
        if (tasks.length > 0) {
            const tasksSection = document.createElement('div');
            tasksSection.className = 'day-section';
            tasksSection.innerHTML = `<h3><i class="fas fa-tasks"></i> Task (${tasks.length})</h3>`;
            
            const tasksList = document.createElement('div');
            tasksList.className = 'tasks-modal-list';
            
            tasks.forEach(task => {
                const taskItem = document.createElement('div');
                taskItem.className = `task-modal-item priority-${task.priorita || 'media'} status-${task.stato}`;
                
                const progressBar = task.task_type === 'calendar' ? 
                    `<div class="task-progress-bar">
                        <div class="task-progress-fill" style="width: ${task.progress_percentage || 0}%"></div>
                    </div>` : '';
                
                taskItem.innerHTML = `
                    <div class="task-modal-header">
                        <div class="task-modal-title">${task.titolo || task.attivita}</div>
                        <div class="task-modal-status">${this.getTaskStatusLabel(task.stato)}</div>
                    </div>
                    ${progressBar}
                    <div class="task-modal-meta">
                        <span class="task-priority">${this.getPriorityLabel(task.priorita)}</span>
                        ${task.task_type === 'calendar' ? '<span class="task-type">Calendario</span>' : '<span class="task-type">Regolare</span>'}
                    </div>
                    ${task.descrizione ? `<div class="task-modal-description">${task.descrizione}</div>` : ''}
                    <div class="task-modal-actions">
                        <button onclick="calendarMobile.viewTask(${task.id}, '${task.task_type}')" class="btn-task-view">
                            <i class="fas fa-eye"></i> Visualizza
                        </button>
                        ${task.stato !== 'completato' ? `
                            <button onclick="calendarMobile.quickTaskAction(${task.id}, '${task.task_type}', 'complete')" class="btn-task-complete">
                                <i class="fas fa-check"></i> Completa
                            </button>
                        ` : ''}
                        ${task.task_type === 'calendar' && task.stato !== 'completato' ? `
                            <button onclick="calendarMobile.updateTaskProgress(${task.id})" class="btn-task-progress">
                                <i class="fas fa-percentage"></i> Progresso
                            </button>
                        ` : ''}
                    </div>
                `;
                
                tasksList.appendChild(taskItem);
            });
            
            tasksSection.appendChild(tasksList);
            content.appendChild(tasksSection);
        }
        
        // Quick actions
        const actionsSection = document.createElement('div');
        actionsSection.className = 'day-section day-actions';
        actionsSection.innerHTML = `
            <div class="action-buttons">
                <button onclick="calendarMobile.showCreateEventModal('${date}')" class="btn-action">
                    <i class="fas fa-plus"></i> Nuovo Evento
                </button>
                <button onclick="calendarMobile.showCreateTaskModal('${date}')" class="btn-action">
                    <i class="fas fa-plus-circle"></i> Nuovo Task
                </button>
            </div>
        `;
        content.appendChild(actionsSection);
        
        modal.appendChild(content);
        this.showModal(modal);
    }
    
    showQuickTaskModal() {
        const modal = this.createModal('Nuovo Task Rapido');
        
        const form = document.createElement('form');
        form.className = 'task-form';
        form.innerHTML = `
            <div class="form-group">
                <label class="form-label">Titolo *</label>
                <input type="text" class="form-input" name="titolo" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Tipo</label>
                    <select class="form-input" name="task_type" onchange="calendarMobile.toggleTaskTypeFields(this.value)">
                        <option value="regular">Task Regolare</option>
                        <option value="calendar">Task Calendario</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Priorità</label>
                    <select class="form-input" name="priorita">
                        <option value="bassa">Bassa</option>
                        <option value="media" selected>Media</option>
                        <option value="alta">Alta</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Scadenza</label>
                <input type="date" class="form-input" name="data_scadenza" value="${new Date().toISOString().split('T')[0]}">
            </div>
            
            <div class="calendar-fields" style="display: none;">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Attività</label>
                        <select class="form-input" name="attivita">
                            <option value="Consulenza">Consulenza</option>
                            <option value="Operation">Operation</option>
                            <option value="Verifica">Verifica</option>
                            <option value="Office">Office</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Giornate</label>
                        <input type="number" class="form-input" name="giornate_previste" min="0.5" max="15" step="0.5" value="1">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Città</label>
                    <input type="text" class="form-input" name="citta">
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Descrizione</label>
                <textarea class="form-input form-textarea" name="descrizione" rows="3"></textarea>
            </div>
            
            <div class="button-group">
                <button type="button" class="btn-secondary" onclick="calendarMobile.closeModal()">Annulla</button>
                <button type="submit" class="btn-primary">Crea Task</button>
            </div>
        `;
        
        form.onsubmit = (e) => {
            e.preventDefault();
            this.createTask(new FormData(form));
        };
        
        modal.appendChild(form);
        this.showModal(modal);
    }
    
    showCreateTaskModal(date = null) {
        this.showQuickTaskModal();
        if (date) {
            const dateInput = document.querySelector('input[name="data_scadenza"]');
            if (dateInput) dateInput.value = date;
        }
    }
    
    toggleTaskTypeFields(type) {
        const calendarFields = document.querySelector('.calendar-fields');
        if (calendarFields) {
            calendarFields.style.display = type === 'calendar' ? 'block' : 'none';
        }
    }
    
    async createTask(formData) {
        const taskData = {
            task_type: formData.get('task_type'),
            titolo: formData.get('titolo'),
            descrizione: formData.get('descrizione'),
            priorita: formData.get('priorita'),
            data_scadenza: formData.get('data_scadenza')
        };
        
        if (taskData.task_type === 'calendar') {
            taskData.attivita = formData.get('attivita');
            taskData.giornate_previste = formData.get('giornate_previste');
            taskData.citta = formData.get('citta');
            taskData.data_inizio = taskData.data_scadenza;
            taskData.data_fine = taskData.data_scadenza;
        }
        
        if (this.isOnline) {
            try {
                const response = await fetch('../backend/api/task-mobile-api.php?action=tasks', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': this.csrfToken
                    },
                    body: JSON.stringify(taskData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    this.showNotification('Task creato con successo', 'success');
                    this.closeModal();
                    this.loadTasks();
                    this.vibrate([10, 100, 10]);
                } else {
                    throw new Error(result.error);
                }
            } catch (error) {
                this.showNotification('Errore creazione task: ' + error.message, 'error');
                console.error(error);
            }
        } else {
            // Save in offline queue
            this.queueTaskAction('create', taskData);
            this.showNotification('Task salvato per sincronizzazione', 'info');
            this.closeModal();
        }
    }
    
    async quickTaskAction(taskId, taskType, action) {
        const taskData = { id: taskId, task_type: taskType };
        
        if (action === 'complete') {
            taskData.stato = 'completato';
        }
        
        if (this.isOnline) {
            try {
                const response = await fetch('../backend/api/task-mobile-api.php?action=tasks', {
                    method: 'PUT',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': this.csrfToken
                    },
                    body: JSON.stringify(taskData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    this.showNotification('Task aggiornato', 'success');
                    this.loadTasks();
                    this.vibrate([10]);
                } else {
                    throw new Error(result.error);
                }
            } catch (error) {
                this.showNotification('Errore aggiornamento task', 'error');
                console.error(error);
            }
        } else {
            this.queueTaskAction('update', taskData);
            this.showNotification('Azione salvata per sincronizzazione', 'info');
        }
    }
    
    async updateTaskProgress(taskId) {
        const modal = this.createModal('Aggiorna Progresso Task');
        
        const form = document.createElement('form');
        form.className = 'progress-form';
        form.innerHTML = `
            <div class="progress-section">
                <label class="form-label">Percentuale completamento</label>
                <div class="progress-buttons">
                    <button type="button" class="btn-progress" data-value="0">0%</button>
                    <button type="button" class="btn-progress" data-value="25">25%</button>
                    <button type="button" class="btn-progress" data-value="50">50%</button>
                    <button type="button" class="btn-progress" data-value="75">75%</button>
                    <button type="button" class="btn-progress" data-value="100">100%</button>
                </div>
                <input type="hidden" name="percentage" value="0">
            </div>
            
            <div class="form-group">
                <label class="form-label">Note (opzionale)</label>
                <textarea class="form-input form-textarea" name="note" rows="3" 
                          placeholder="Aggiungi note sull'avanzamento..."></textarea>
            </div>
            
            <div class="button-group">
                <button type="button" class="btn-secondary" onclick="calendarMobile.closeModal()">Annulla</button>
                <button type="submit" class="btn-primary">Aggiorna Progresso</button>
            </div>
        `;
        
        // Progress button handlers
        form.querySelectorAll('.btn-progress').forEach(btn => {
            btn.addEventListener('click', (e) => {
                form.querySelectorAll('.btn-progress').forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');
                form.querySelector('input[name="percentage"]').value = e.target.dataset.value;
            });
        });
        
        form.onsubmit = async (e) => {
            e.preventDefault();
            const progressData = {
                task_id: taskId,
                task_type: 'calendar',
                percentage: parseFloat(form.querySelector('input[name="percentage"]').value),
                note: form.querySelector('textarea[name="note"]').value
            };
            
            if (this.isOnline) {
                try {
                    const response = await fetch('../backend/api/task-mobile-api.php?action=update_progress', {
                        method: 'POST',
                        headers: { 
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': this.csrfToken
                        },
                        body: JSON.stringify(progressData)
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        this.showNotification('Progresso aggiornato', 'success');
                        this.closeModal();
                        this.loadTasks();
                        this.vibrate([10, 100, 10]);
                    } else {
                        throw new Error(result.error);
                    }
                } catch (error) {
                    this.showNotification('Errore aggiornamento progresso', 'error');
                    console.error(error);
                }
            } else {
                this.queueTaskAction('progress', progressData);
                this.showNotification('Progresso salvato per sincronizzazione', 'info');
                this.closeModal();
            }
        };
        
        modal.appendChild(form);
        this.showModal(modal);
    }
    
    viewTask(taskId, taskType) {
        // Redirect to detailed task view or show detailed modal
        if (taskType === 'calendar') {
            window.location.href = `../task-progress.php#task-${taskId}`;
        } else {
            window.location.href = `../mobile/tasks/#task-${taskId}`;
        }
    }
    
    queueTaskAction(action, data) {
        if (!this.db) return;
        
        const transaction = this.db.transaction(['offline_actions'], 'readwrite');
        const store = transaction.objectStore('offline_actions');
        
        store.add({
            type: 'task',
            action,
            data,
            timestamp: new Date().toISOString()
        });
    }
    
    async syncTaskActions() {
        if (!this.db || !this.isOnline) return;
        
        const transaction = this.db.transaction(['offline_actions'], 'readwrite');
        const store = transaction.objectStore('offline_actions');
        const request = store.getAll();
        
        request.onsuccess = async () => {
            const actions = request.result.filter(action => action.type === 'task');
            
            for (const action of actions) {
                try {
                    let endpoint = '../backend/api/task-mobile-api.php?action=tasks';
                    let method = 'POST';
                    
                    if (action.action === 'update' || action.action === 'complete') {
                        method = 'PUT';
                    } else if (action.action === 'progress') {
                        endpoint = '../backend/api/task-mobile-api.php?action=update_progress';
                    }
                    
                    await fetch(endpoint, {
                        method: method,
                        headers: { 
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': this.csrfToken
                        },
                        body: JSON.stringify(action.data)
                    });
                    
                    // Remove action from queue
                    store.delete(action.id);
                } catch (error) {
                    console.error('Errore sync azione task:', error);
                }
            }
            
            this.loadTasks();
        };
    }
    
    updateTaskStats(tasks) {
        const stats = {
            total: tasks.length,
            completed: tasks.filter(t => t.stato === 'completato').length,
            overdue: tasks.filter(t => t.is_overdue).length,
            inProgress: tasks.filter(t => t.stato === 'in_corso').length
        };
        
        // Update UI stats if elements exist
        const statsContainer = document.getElementById('taskStats');
        if (statsContainer) {
            statsContainer.innerHTML = `
                <div class="stat-item">
                    <span class="stat-number">${stats.total}</span>
                    <span class="stat-label">Totali</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">${stats.inProgress}</span>
                    <span class="stat-label">In Corso</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">${stats.completed}</span>
                    <span class="stat-label">Completati</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number text-danger">${stats.overdue}</span>
                    <span class="stat-label">In Ritardo</span>
                </div>
            `;
        }
    }
    
    getTaskStatusLabel(status) {
        const labels = {
            'nuovo': 'Nuovo',
            'assegnato': 'Assegnato',
            'in_corso': 'In Corso',
            'in_attesa': 'In Attesa',
            'completato': 'Completato',
            'annullato': 'Annullato'
        };
        return labels[status] || status;
    }
    
    getPriorityLabel(priority) {
        const labels = {
            'bassa': 'Bassa',
            'media': 'Media',
            'alta': 'Alta'
        };
        return labels[priority] || 'Media';
    }
}

// Inizializza quando il DOM è pronto
document.addEventListener('DOMContentLoaded', () => {
    window.calendarMobile = new CalendarMobile();
});

// Funzioni globali per compatibilità
function changeMonth(direction) {
    if (window.calendarMobile) {
        window.calendarMobile.navigateMonth(direction);
    }
}

function showDayEvents(date) {
    if (window.calendarMobile) {
        window.calendarMobile.showDayEvents(date);
    }
}

function createEvent(date = null) {
    if (window.calendarMobile) {
        window.calendarMobile.showCreateEventModal(date);
    }
}

function viewEvent(eventId) {
    if (window.calendarMobile) {
        window.calendarMobile.viewEvent(eventId);
    }
}