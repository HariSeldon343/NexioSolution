/**
 * Nexio PWA Tasks Manager
 * Handles task functionality for the mobile app
 */

class TasksManager {
    constructor() {
        this.tasks = [];
        this.currentFilter = 'all';
        this.isLoading = false;
        
        this.initializeTasks();
    }

    initializeTasks() {
        console.log('Tasks: Initializing...');
        
        this.setupEventListeners();
        this.loadTasks();
        
        console.log('Tasks: Initialized');
    }

    setupEventListeners() {
        // Filter buttons
        const filterButtons = document.querySelectorAll('[data-filter]');
        filterButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                const filter = e.target.getAttribute('data-filter');
                this.setFilter(filter);
                this.updateFilterButtons();
                this.renderTasks();
            });
        });

        // Task checkbox handlers will be added dynamically
    }

    // Filter Methods
    setFilter(filter) {
        this.currentFilter = filter;
        console.log('Tasks: Filter set to:', filter);
    }

    updateFilterButtons() {
        document.querySelectorAll('[data-filter]').forEach(btn => {
            btn.classList.remove('active', 'btn-primary');
            btn.classList.add('btn-outline-secondary');
            
            if (btn.getAttribute('data-filter') === this.currentFilter) {
                btn.classList.remove('btn-outline-secondary');
                btn.classList.add('active', 'btn-primary');
            }
        });
    }

    getFilteredTasks() {
        switch (this.currentFilter) {
            case 'pending':
                return this.tasks.filter(task => !task.completed);
            case 'completed':
                return this.tasks.filter(task => task.completed);
            case 'overdue':
                return this.tasks.filter(task => this.isOverdue(task) && !task.completed);
            default:
                return this.tasks;
        }
    }

    // Task Management
    async loadTasks() {
        try {
            this.showLoading();
            const tasks = await this.fetchTasks();
            this.tasks = tasks;
            this.renderTasks();
        } catch (error) {
            console.error('Tasks: Failed to load tasks:', error);
            this.showError('Errore nel caricamento delle attività');
        } finally {
            this.hideLoading();
        }
    }

    async fetchTasks() {
        try {
            console.log('Tasks: Fetching tasks...');
            
            const response = await fetch('../backend/api/task-mobile-api.php?action=tasks', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                console.error('Tasks: HTTP error', response.status, response.statusText);
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            console.log('Tasks: API response:', data);
            
            if (data.success) {
                console.log('Tasks: Loaded', (data.tasks || []).length, 'tasks');
                return data.tasks || [];
            } else {
                console.error('Tasks: API returned error:', data.error);
                throw new Error(data.error || 'Errore nel recupero delle attività');
            }
        } catch (error) {
            console.error('Tasks: Fetch tasks failed:', error);
            
            // Fallback to empty array if network/API fails  
            this.tasks = [];
            
            // Still throw error to let calling function handle it
            throw error;
        }
    }

    renderTasks() {
        const tasksList = document.getElementById('tasksList');
        if (!tasksList) return;

        const filteredTasks = this.getFilteredTasks();

        if (filteredTasks.length === 0) {
            let emptyMessage = 'Nessuna attività';
            switch (this.currentFilter) {
                case 'pending':
                    emptyMessage = 'Nessuna attività da completare';
                    break;
                case 'completed':
                    emptyMessage = 'Nessuna attività completata';
                    break;
                case 'overdue':
                    emptyMessage = 'Nessuna attività scaduta';
                    break;
            }
            
            tasksList.innerHTML = `<p class="text-muted text-center py-4">${emptyMessage}</p>`;
            return;
        }

        let tasksHTML = '';
        filteredTasks.forEach(task => {
            tasksHTML += this.renderTaskItem(task);
        });

        tasksList.innerHTML = tasksHTML;
        this.bindTaskEvents();
    }

    renderTaskItem(task) {
        const isOverdue = this.isOverdue(task);
        const priorityClass = this.getPriorityClass(task.priority);
        
        let taskClasses = 'task-item d-flex align-items-start';
        if (task.completed) taskClasses += ' completed';
        if (isOverdue && !task.completed) taskClasses += ' overdue';

        return `
            <div class="${taskClasses}" data-task-id="${task.id}">
                <div class="task-checkbox ${task.completed ? 'checked' : ''}" 
                     onclick="window.TasksManager.toggleTask(${task.id})"></div>
                
                <div class="task-content">
                    <div class="task-title ${task.completed ? 'text-decoration-line-through' : ''}">${task.title}</div>
                    
                    <div class="task-meta d-flex flex-wrap align-items-center gap-2 mt-1">
                        ${task.due_date ? `<span class="text-muted"><i class="fas fa-calendar-alt me-1"></i>${this.formatDate(task.due_date)}</span>` : ''}
                        ${task.priority ? `<span class="task-priority ${priorityClass}">${task.priority}</span>` : ''}
                        ${task.category ? `<span class="badge bg-secondary">${task.category}</span>` : ''}
                    </div>
                    
                    ${task.description ? `<div class="task-description text-muted mt-2">${task.description}</div>` : ''}
                </div>
                
                <div class="task-actions">
                    <button class="btn btn-link btn-sm p-0" onclick="window.TasksManager.showTaskOptions(${task.id})">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                </div>
            </div>
        `;
    }

    bindTaskEvents() {
        // Task checkbox events are handled by onclick attributes in the HTML
        // Additional event binding can be added here if needed
    }

    // Task Actions
    async toggleTask(taskId) {
        try {
            const task = this.tasks.find(t => t.id === taskId);
            if (!task) return;

            const newStatus = !task.completed;
            await this.updateTaskStatus(taskId, newStatus);
            
            // Update local state
            task.completed = newStatus;
            this.renderTasks();
            
            if (window.nexioPWA) {
                window.nexioPWA.showToast(
                    newStatus ? 'Attività completata' : 'Attività riaperta', 
                    'success'
                );
            }
        } catch (error) {
            console.error('Tasks: Failed to toggle task:', error);
            this.showError('Errore nell\'aggiornamento dell\'attività');
        }
    }

    async updateTaskStatus(taskId, completed) {
        // Get CSRF token first
        const csrfResponse = await fetch('../backend/api/task-mobile-api.php?action=csrf_token', {
            method: 'GET',
            credentials: 'same-origin'
        });
        
        if (!csrfResponse.ok) {
            throw new Error('Impossibile ottenere il token CSRF');
        }
        
        const csrfData = await csrfResponse.json();
        const csrfToken = csrfData.token;

        const response = await fetch('../backend/api/task-mobile-api.php?action=move_task', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                task_id: taskId,
                task_type: 'regular',
                from_column: completed ? 'todo' : 'done',
                to_column: completed ? 'done' : 'todo',
                position: 0
            })
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();
        if (!data.success) {
            throw new Error(data.error || 'Errore nell\'aggiornamento dello status');
        }
    }

    showTaskOptions(taskId) {
        const task = this.tasks.find(t => t.id === taskId);
        if (!task) return;

        // Create action sheet or context menu
        const actions = [
            { label: 'Modifica', action: () => this.editTask(taskId) },
            { label: 'Duplica', action: () => this.duplicateTask(taskId) },
            { label: 'Elimina', action: () => this.deleteTask(taskId), danger: true }
        ];

        this.showActionSheet(`Attività: ${task.title}`, actions);
    }

    editTask(taskId) {
        console.log('Tasks: Edit task:', taskId);
        // Implementation for editing task
        if (window.nexioPWA) {
            window.nexioPWA.showToast('Funzione modifica in sviluppo', 'info');
        }
    }

    duplicateTask(taskId) {
        console.log('Tasks: Duplicate task:', taskId);
        // Implementation for duplicating task
        if (window.nexioPWA) {
            window.nexioPWA.showToast('Funzione duplica in sviluppo', 'info');
        }
    }

    async deleteTask(taskId) {
        if (!confirm('Sei sicuro di voler eliminare questa attività?')) {
            return;
        }

        try {
            await this.removeTask(taskId);
            
            // Update local state
            this.tasks = this.tasks.filter(t => t.id !== taskId);
            this.renderTasks();
            
            if (window.nexioPWA) {
                window.nexioPWA.showToast('Attività eliminata', 'success');
            }
        } catch (error) {
            console.error('Tasks: Failed to delete task:', error);
            this.showError('Errore nell\'eliminazione dell\'attività');
        }
    }

    async removeTask(taskId) {
        const response = await fetch('../backend/api/task-mobile-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                action: 'delete',
                task_id: taskId
            })
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();
        if (!data.success) {
            throw new Error(data.message || 'Errore nell\'eliminazione dell\'attività');
        }
    }

    showNewTaskForm() {
        console.log('Tasks: Show new task form');
        // Implementation for new task form
        if (window.nexioPWA) {
            window.nexioPWA.showToast('Funzione nuova attività in sviluppo', 'info');
        }
    }

    // Helper Methods
    isOverdue(task) {
        if (!task.due_date || task.completed) return false;
        const dueDate = new Date(task.due_date);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        return dueDate < today;
    }

    getPriorityClass(priority) {
        switch (priority?.toLowerCase()) {
            case 'high':
            case 'alta':
                return 'high';
            case 'medium':
            case 'media':
                return 'medium';
            case 'low':
            case 'bassa':
                return 'low';
            default:
                return 'medium';
        }
    }

    formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString('it-IT', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
    }

    showActionSheet(title, actions) {
        // Create a simple action sheet using Bootstrap modal or custom implementation
        let actionsHTML = '';
        actions.forEach((action, index) => {
            const btnClass = action.danger ? 'btn-danger' : 'btn-primary';
            actionsHTML += `
                <button type="button" class="btn ${btnClass} w-100 mb-2" onclick="window.TasksManager.handleActionSheet(${index})">
                    ${action.label}
                </button>
            `;
        });

        // Store actions for later use
        this.currentActions = actions;

        // Show action sheet (simplified implementation)
        if (window.nexioPWA) {
            window.nexioPWA.showToast(`${title} - Azioni disponibili`, 'info');
        }
    }

    handleActionSheet(actionIndex) {
        if (this.currentActions && this.currentActions[actionIndex]) {
            this.currentActions[actionIndex].action();
            this.currentActions = null;
        }
    }

    // Loading States
    showLoading() {
        this.isLoading = true;
        if (window.nexioPWA) {
            window.nexioPWA.showLoadingOverlay();
        }
    }

    hideLoading() {
        this.isLoading = false;
        if (window.nexioPWA) {
            window.nexioPWA.hideLoadingOverlay();
        }
    }

    showError(message) {
        if (window.nexioPWA) {
            window.nexioPWA.showToast(message, 'danger');
        }
    }

    // PWA Methods
    async refresh() {
        console.log('Tasks: Refreshing data...');
        await this.loadTasks();
    }

    async syncOfflineChanges() {
        console.log('Tasks: Syncing offline changes...');
        // Implementation for syncing offline changes
        return Promise.resolve();
    }
}

// Initialize Tasks Manager when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    console.log('Tasks: DOM loaded, initializing...');
    window.TasksManager = new TasksManager();
});

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = TasksManager;
}