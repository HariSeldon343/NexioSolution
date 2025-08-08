/**
 * Nexio PWA Calendar Manager
 * Handles calendar functionality for the mobile app
 */

class CalendarManager {
    constructor() {
        this.currentDate = new Date();
        this.currentView = 'month';
        this.events = [];
        this.isLoading = false;
        
        this.initializeCalendar();
    }

    initializeCalendar() {
        console.log('Calendar: Initializing...');
        
        this.setupEventListeners();
        this.renderCurrentView();
        this.loadTodayEvents();
        
        console.log('Calendar: Initialized');
    }

    setupEventListeners() {
        // Month navigation
        const prevBtn = document.getElementById('prevMonth');
        const nextBtn = document.getElementById('nextMonth');
        
        if (prevBtn) {
            prevBtn.addEventListener('click', () => this.previousMonth());
        }
        
        if (nextBtn) {
            nextBtn.addEventListener('click', () => this.nextMonth());
        }

        // View toggle
        const monthViewBtn = document.getElementById('monthView');
        const weekViewBtn = document.getElementById('weekView');
        const dayViewBtn = document.getElementById('dayView');

        if (monthViewBtn) {
            monthViewBtn.addEventListener('click', () => this.switchView('month'));
        }
        
        if (weekViewBtn) {
            weekViewBtn.addEventListener('click', () => this.switchView('week'));
        }
        
        if (dayViewBtn) {
            dayViewBtn.addEventListener('click', () => this.switchView('day'));
        }
    }

    // Navigation Methods
    previousMonth() {
        this.currentDate.setMonth(this.currentDate.getMonth() - 1);
        this.renderCurrentView();
        this.updateMonthHeader();
    }

    nextMonth() {
        this.currentDate.setMonth(this.currentDate.getMonth() + 1);
        this.renderCurrentView();
        this.updateMonthHeader();
    }

    switchView(viewType) {
        this.currentView = viewType;
        this.updateViewButtons();
        this.renderCurrentView();
    }

    updateViewButtons() {
        document.querySelectorAll('#monthView, #weekView, #dayView').forEach(btn => {
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-outline-secondary');
        });

        const activeBtn = document.getElementById(this.currentView + 'View');
        if (activeBtn) {
            activeBtn.classList.remove('btn-outline-secondary');
            activeBtn.classList.add('btn-primary');
        }
    }

    updateMonthHeader() {
        const monthNames = [
            'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno',
            'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'
        ];

        const monthHeader = document.getElementById('currentMonth');
        if (monthHeader) {
            monthHeader.textContent = `${monthNames[this.currentDate.getMonth()]} ${this.currentDate.getFullYear()}`;
        }
    }

    // Rendering Methods
    renderCurrentView() {
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
        }
        this.updateMonthHeader();
    }

    renderMonthView() {
        const calendarGrid = document.getElementById('calendarGrid');
        if (!calendarGrid) {
            console.error('Calendar: calendarGrid element not found');
            this.showError('Errore nel rendering del calendario');
            return;
        }

        const year = this.currentDate.getFullYear();
        const month = this.currentDate.getMonth();
        
        // Get first day of month and number of days
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const daysInMonth = lastDay.getDate();
        const startingDay = firstDay.getDay();

        // Create calendar HTML
        let calendarHTML = `
            <div class="calendar-weekdays">
                <div class="calendar-weekday">Dom</div>
                <div class="calendar-weekday">Lun</div>
                <div class="calendar-weekday">Mar</div>
                <div class="calendar-weekday">Mer</div>
                <div class="calendar-weekday">Gio</div>
                <div class="calendar-weekday">Ven</div>
                <div class="calendar-weekday">Sab</div>
            </div>
            <div class="calendar-days">
        `;

        // Previous month's trailing days
        const prevMonth = new Date(year, month - 1, 0);
        const prevMonthDays = prevMonth.getDate();
        
        for (let i = startingDay - 1; i >= 0; i--) {
            const day = prevMonthDays - i;
            calendarHTML += `<div class="calendar-day other-month" data-date="${year}-${month}-${day}">${day}</div>`;
        }

        // Current month days
        const today = new Date();
        for (let day = 1; day <= daysInMonth; day++) {
            const date = new Date(year, month, day);
            const isToday = date.toDateString() === today.toDateString();
            const hasEvents = this.hasEventsOnDate(date);
            
            let classes = 'calendar-day';
            if (isToday) classes += ' today';
            if (hasEvents) classes += ' has-events';
            
            calendarHTML += `
                <div class="${classes}" data-date="${year}-${month + 1}-${day}" onclick="window.CalendarManager.selectDate('${year}-${month + 1}-${day}')">
                    ${day}
                </div>
            `;
        }

        // Next month's leading days
        const totalCells = Math.ceil((startingDay + daysInMonth) / 7) * 7;
        const remainingCells = totalCells - (startingDay + daysInMonth);
        
        for (let day = 1; day <= remainingCells; day++) {
            calendarHTML += `<div class="calendar-day other-month" data-date="${year}-${month + 2}-${day}">${day}</div>`;
        }

        calendarHTML += '</div>';
        calendarGrid.innerHTML = calendarHTML;
    }

    renderWeekView() {
        const calendarGrid = document.getElementById('calendarGrid');
        if (!calendarGrid) {
            console.error('Calendar: calendarGrid element not found');
            this.showError('Errore nel rendering del calendario');
            return;
        }

        // Get current week
        const startOfWeek = this.getStartOfWeek(this.currentDate);
        const weekDays = [];
        
        for (let i = 0; i < 7; i++) {
            const day = new Date(startOfWeek);
            day.setDate(startOfWeek.getDate() + i);
            weekDays.push(day);
        }

        let weekHTML = '<div class="week-view">';
        
        weekDays.forEach(day => {
            const isToday = day.toDateString() === new Date().toDateString();
            const dayEvents = this.getEventsForDate(day);
            
            weekHTML += `
                <div class="week-day ${isToday ? 'today' : ''}">
                    <div class="week-day-header">
                        <div class="week-day-name">${this.getDayName(day.getDay())}</div>
                        <div class="week-day-number">${day.getDate()}</div>
                    </div>
                    <div class="week-day-events">
            `;
            
            dayEvents.forEach(event => {
                weekHTML += `
                    <div class="week-event" onclick="window.CalendarManager.showEventDetails(${event.id})">
                        <div class="week-event-time">${this.formatTime(event.start_time)}</div>
                        <div class="week-event-title">${event.title}</div>
                    </div>
                `;
            });
            
            weekHTML += '</div></div>';
        });
        
        weekHTML += '</div>';
        calendarGrid.innerHTML = weekHTML;
    }

    renderDayView() {
        const calendarGrid = document.getElementById('calendarGrid');
        if (!calendarGrid) {
            console.error('Calendar: calendarGrid element not found');
            this.showError('Errore nel rendering del calendario');
            return;
        }

        const dayEvents = this.getEventsForDate(this.currentDate);
        const hours = [];
        
        // Generate 24-hour view
        for (let hour = 0; hour < 24; hour++) {
            hours.push({
                hour: hour,
                events: dayEvents.filter(event => {
                    const eventHour = new Date(event.start_time).getHours();
                    return eventHour === hour;
                })
            });
        }

        let dayHTML = '<div class="day-view">';
        
        hours.forEach(({ hour, events }) => {
            dayHTML += `
                <div class="day-hour">
                    <div class="day-hour-label">${this.formatHour(hour)}</div>
                    <div class="day-hour-events">
            `;
            
            events.forEach(event => {
                dayHTML += `
                    <div class="day-event" onclick="window.CalendarManager.showEventDetails(${event.id})">
                        <div class="day-event-title">${event.title}</div>
                        <div class="day-event-time">${this.formatTime(event.start_time)} - ${this.formatTime(event.end_time)}</div>
                    </div>
                `;
            });
            
            dayHTML += '</div></div>';
        });
        
        dayHTML += '</div>';
        calendarGrid.innerHTML = dayHTML;
    }

    // Event Management
    async loadTodayEvents() {
        try {
            this.showLoading();
            const today = new Date().toISOString().split('T')[0];
            const events = await this.fetchEvents(today, today);
            this.renderTodayEvents(events);
        } catch (error) {
            console.error('Calendar: Failed to load today events:', error);
            
            // Show specific error messages based on the error type
            let errorMessage = 'Errore nel caricamento degli eventi';
            if (error.message.includes('401') || error.message.includes('Non autenticato')) {
                errorMessage = 'Sessione scaduta. Ricarica la pagina.';
            } else if (error.message.includes('403') || error.message.includes('permessi')) {
                errorMessage = 'Non hai i permessi per visualizzare gli eventi.';
            } else if (error.message.includes('500') || error.message.includes('server')) {
                errorMessage = 'Errore del server. Riprova più tardi.';
            } else if (error.message.includes('Network') || error.message.includes('fetch')) {
                errorMessage = 'Problema di connessione. Verifica la tua connessione internet.';
            }
            
            this.showError(errorMessage);
        } finally {
            this.hideLoading();
        }
    }

    async fetchEvents(startDate, endDate) {
        try {
            console.log('Calendar: Fetching events from', startDate, 'to', endDate);
            
            const response = await fetch(`../backend/api/calendar-events.php?start=${startDate}&end=${endDate}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                console.error('Calendar: HTTP error', response.status, response.statusText);
                if (response.status === 401) {
                    throw new Error('Sessione scaduta. Ricarica la pagina e accedi nuovamente.');
                } else if (response.status === 403) {
                    throw new Error('Non hai i permessi per visualizzare gli eventi.');
                } else if (response.status >= 500) {
                    throw new Error('Errore del server. Riprova più tardi.');
                } else {
                    throw new Error(`Errore nella richiesta: ${response.status}`);
                }
            }

            const data = await response.json();
            console.log('Calendar: API response:', data);
            
            if (data.success) {
                this.events = data.events || [];
                console.log('Calendar: Loaded', this.events.length, 'events');
                return this.events;
            } else {
                console.error('Calendar: API returned error:', data.message);
                throw new Error(data.message || 'Errore nel recupero degli eventi');
            }
        } catch (error) {
            console.error('Calendar: Fetch events failed:', error);
            
            // Fallback to empty array if network/API fails
            this.events = [];
            
            // Still throw error to let calling function handle it
            throw error;
        }
    }

    renderTodayEvents(events) {
        // Try to find both possible event containers
        let eventsList = document.getElementById('todayEventsList') || document.getElementById('eventsList');
        if (!eventsList) {
            console.warn('Calendar: No events list container found');
            return;
        }

        if (!events || events.length === 0) {
            eventsList.innerHTML = '<p class="text-muted text-center py-3">Nessun evento trovato</p>';
            return;
        }

        let eventsHTML = '';
        events.forEach(event => {
            const eventDate = new Date(event.start_time);
            const isToday = eventDate.toDateString() === new Date().toDateString();
            
            eventsHTML += `
                <div class="event-item" onclick="window.CalendarManager.showEventDetails(${event.id})" style="cursor: pointer;">
                    <div class="event-title">${event.title}</div>
                    <div class="event-time">
                        <i class="fas fa-clock me-1"></i>
                        ${this.formatTime(event.start_time)} - ${this.formatTime(event.end_time)}
                    </div>
                    ${event.location ? `<div class="event-location"><i class="fas fa-map-marker-alt me-1"></i>${event.location}</div>` : ''}
                    ${event.description ? `<div class="event-description">${event.description}</div>` : ''}
                    ${!isToday ? `<div class="event-date"><small class="text-muted">${eventDate.toLocaleDateString('it-IT')}</small></div>` : ''}
                </div>
            `;
        });

        eventsList.innerHTML = eventsHTML;
        console.log('Calendar: Rendered', events.length, 'events to', eventsList.id);
    }

    // Helper Methods
    hasEventsOnDate(date) {
        const dateString = date.toISOString().split('T')[0];
        return this.events.some(event => {
            const eventDate = new Date(event.start_time).toISOString().split('T')[0];
            return eventDate === dateString;
        });
    }

    getEventsForDate(date) {
        const dateString = date.toISOString().split('T')[0];
        return this.events.filter(event => {
            const eventDate = new Date(event.start_time).toISOString().split('T')[0];
            return eventDate === dateString;
        });
    }

    getStartOfWeek(date) {
        const startOfWeek = new Date(date);
        const day = startOfWeek.getDay();
        const diff = startOfWeek.getDate() - day;
        startOfWeek.setDate(diff);
        return startOfWeek;
    }

    getDayName(dayIndex) {
        const dayNames = ['Dom', 'Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab'];
        return dayNames[dayIndex];
    }

    formatTime(timeString) {
        if (!timeString) return '';
        const date = new Date(timeString);
        return date.toLocaleTimeString('it-IT', { 
            hour: '2-digit', 
            minute: '2-digit',
            hour12: false 
        });
    }

    formatHour(hour) {
        return hour.toString().padStart(2, '0') + ':00';
    }

    // User Interactions
    selectDate(dateString) {
        console.log('Calendar: Date selected:', dateString);
        const [year, month, day] = dateString.split('-');
        this.currentDate = new Date(year, month - 1, day);
        this.switchView('day');
    }

    showEventDetails(eventId) {
        const event = this.events.find(e => e.id === eventId);
        if (!event) return;

        // Create modal or navigate to event details
        console.log('Calendar: Show event details for:', event);
        
        if (window.nexioPWA) {
            window.nexioPWA.showToast(`Evento: ${event.title}`, 'info');
        }
    }

    // Loading States
    showLoading() {
        this.isLoading = true;
        
        // Show the loading overlay
        const loadingOverlay = document.getElementById('loadingOverlay');
        if (loadingOverlay) {
            loadingOverlay.classList.remove('d-none');
        }
        
        if (window.nexioPWA) {
            window.nexioPWA.showLoadingOverlay();
        }
    }

    hideLoading() {
        this.isLoading = false;
        
        // Hide the loading overlay
        const loadingOverlay = document.getElementById('loadingOverlay');
        if (loadingOverlay) {
            loadingOverlay.classList.add('d-none');
        }
        
        if (window.nexioPWA) {
            window.nexioPWA.hideLoadingOverlay();
        }
    }

    showError(message) {
        console.error('Calendar Error:', message);
        
        // Show in UI as well
        const eventsList = document.getElementById('eventsList') || document.getElementById('todayEventsList');
        if (eventsList) {
            eventsList.innerHTML = `<div class="alert alert-danger p-3 mb-3" style="background-color: #fee2e2; color: #dc2626; border-radius: 0.5rem; border: 1px solid #fecaca;">${message}</div>`;
        }
        
        if (window.nexioPWA) {
            window.nexioPWA.showToast(message, 'danger');
        }
    }

    // PWA Methods
    async refresh() {
        console.log('Calendar: Refreshing data...');
        await this.loadTodayEvents();
        this.renderCurrentView();
    }

    async syncOfflineChanges() {
        console.log('Calendar: Syncing offline changes...');
        // Implementation for syncing offline changes
        return Promise.resolve();
    }
}

// Initialize Calendar Manager when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    console.log('Calendar: DOM loaded, initializing...');
    window.CalendarManager = new CalendarManager();
});

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CalendarManager;
}