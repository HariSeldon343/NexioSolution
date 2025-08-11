/**
 * Advanced Search JavaScript Module
 * 
 * Sistema JavaScript per ricerca avanzata con autocomplete e filtri dinamici
 * Compatible con il sistema documentale ISO Nexio
 * 
 * Features:
 * - Search-as-you-type con debouncing
 * - Autocomplete suggestions
 * - Filtri avanzati dinamici
 * - Export risultati
 * - Search history
 * - Semantic search
 * 
 * @version 1.0.0
 */

class NexioAdvancedSearch {
    constructor(options = {}) {
        this.options = {
            searchInputSelector: '#advanced-search-input',
            searchFormSelector: '#advanced-search-form',
            resultsContainerSelector: '#search-results-container',
            filtersContainerSelector: '#search-filters-container',
            suggestionsContainerSelector: '#search-suggestions',
            historyContainerSelector: '#search-history',
            searchUrl: '/backend/api/search-advanced.php',
            suggestionsUrl: '/backend/api/search-advanced.php?mode=suggestions',
            exportUrl: '/backend/api/search-advanced.php?mode=export',
            debounceDelay: 300,
            minQueryLength: 2,
            maxSuggestions: 10,
            enableHistory: true,
            enableSemanticSearch: true,
            enableExport: true,
            csrfToken: null,
            ...options
        };

        this.searchInput = null;
        this.searchForm = null;
        this.currentQuery = '';
        this.currentFilters = {};
        this.searchHistory = [];
        this.debounceTimer = null;
        this.suggestionsVisible = false;
        this.currentRequest = null;

        this.init();
    }

    init() {
        this.loadElements();
        this.setupEventListeners();
        this.loadSearchHistory();
        this.loadCSRFToken();
        this.initializeFilters();
    }

    loadElements() {
        this.searchInput = document.querySelector(this.options.searchInputSelector);
        this.searchForm = document.querySelector(this.options.searchFormSelector);
        this.resultsContainer = document.querySelector(this.options.resultsContainerSelector);
        this.filtersContainer = document.querySelector(this.options.filtersContainerSelector);
        this.suggestionsContainer = document.querySelector(this.options.suggestionsContainerSelector);
        this.historyContainer = document.querySelector(this.options.historyContainerSelector);
    }

    setupEventListeners() {
        if (this.searchInput) {
            // Search input events
            this.searchInput.addEventListener('input', (e) => this.handleSearchInput(e));
            this.searchInput.addEventListener('focus', () => this.showSuggestions());
            this.searchInput.addEventListener('blur', () => this.hideSuggestions(200));
            this.searchInput.addEventListener('keydown', (e) => this.handleKeydown(e));
        }

        if (this.searchForm) {
            this.searchForm.addEventListener('submit', (e) => this.handleFormSubmit(e));
        }

        // Filter events
        this.setupFilterListeners();

        // Global events
        document.addEventListener('click', (e) => this.handleGlobalClick(e));
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => this.handleGlobalKeydown(e));
    }

    setupFilterListeners() {
        if (!this.filtersContainer) return;

        // Dynamic filter changes
        this.filtersContainer.addEventListener('change', (e) => {
            if (e.target.matches('select, input[type="checkbox"], input[type="radio"]')) {
                this.handleFilterChange(e.target);
            }
        });

        // Date range inputs
        this.filtersContainer.addEventListener('change', (e) => {
            if (e.target.matches('input[type="date"]')) {
                this.handleDateFilterChange(e.target);
            }
        });

        // Clear filters button
        const clearFiltersBtn = this.filtersContainer.querySelector('#clear-filters-btn');
        if (clearFiltersBtn) {
            clearFiltersBtn.addEventListener('click', () => this.clearFilters());
        }

        // Save search button
        const saveSearchBtn = this.filtersContainer.querySelector('#save-search-btn');
        if (saveSearchBtn) {
            saveSearchBtn.addEventListener('click', () => this.saveCurrentSearch());
        }
    }

    handleSearchInput(e) {
        const query = e.target.value.trim();
        this.currentQuery = query;

        // Clear previous timer
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }

        // Debounce search
        this.debounceTimer = setTimeout(() => {
            if (query.length >= this.options.minQueryLength) {
                this.loadSuggestions(query);
                this.performSearch(query);
            } else if (query.length === 0) {
                this.clearResults();
                this.hideSuggestions();
            }
        }, this.options.debounceDelay);
    }

    handleKeydown(e) {
        if (!this.suggestionsVisible) return;

        const suggestions = this.suggestionsContainer.querySelectorAll('.suggestion-item');
        const activeSuggestion = this.suggestionsContainer.querySelector('.suggestion-item.active');

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                this.navigateSuggestions(suggestions, activeSuggestion, 1);
                break;
            case 'ArrowUp':
                e.preventDefault();
                this.navigateSuggestions(suggestions, activeSuggestion, -1);
                break;
            case 'Enter':
                e.preventDefault();
                if (activeSuggestion) {
                    this.applySuggestion(activeSuggestion);
                } else {
                    this.performSearch(this.currentQuery);
                }
                break;
            case 'Escape':
                this.hideSuggestions();
                break;
        }
    }

    handleFormSubmit(e) {
        e.preventDefault();
        this.performSearch(this.currentQuery);
        this.hideSuggestions();
    }

    handleFilterChange(filterElement) {
        const filterName = filterElement.name;
        let filterValue = filterElement.value;

        if (filterElement.type === 'checkbox') {
            if (filterElement.checked) {
                if (!this.currentFilters[filterName]) {
                    this.currentFilters[filterName] = [];
                }
                this.currentFilters[filterName].push(filterValue);
            } else {
                if (this.currentFilters[filterName]) {
                    this.currentFilters[filterName] = this.currentFilters[filterName].filter(v => v !== filterValue);
                    if (this.currentFilters[filterName].length === 0) {
                        delete this.currentFilters[filterName];
                    }
                }
            }
        } else {
            if (filterValue) {
                this.currentFilters[filterName] = filterValue;
            } else {
                delete this.currentFilters[filterName];
            }
        }

        // Trigger search with filters
        this.performSearch(this.currentQuery);
        this.updateFilterSummary();
    }

    handleDateFilterChange(dateInput) {
        const dateValue = dateInput.value;
        const fieldName = dateInput.name;

        if (dateValue) {
            this.currentFilters[fieldName] = dateValue;
        } else {
            delete this.currentFilters[fieldName];
        }

        this.performSearch(this.currentQuery);
        this.updateFilterSummary();
    }

    handleGlobalClick(e) {
        // Hide suggestions se click fuori
        if (!e.target.closest(this.options.suggestionsContainerSelector) && 
            !e.target.closest(this.options.searchInputSelector)) {
            this.hideSuggestions();
        }

        // Handle suggestion clicks
        if (e.target.closest('.suggestion-item')) {
            this.applySuggestion(e.target.closest('.suggestion-item'));
        }

        // Handle result actions
        if (e.target.matches('[data-action="export-results"]')) {
            this.exportResults(e.target.dataset.format || 'json');
        }

        if (e.target.matches('[data-action="download-multiple"]')) {
            this.triggerMultipleDownload();
        }
    }

    handleGlobalKeydown(e) {
        // Ctrl+K - Focus search
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            if (this.searchInput) {
                this.searchInput.focus();
                this.searchInput.select();
            }
        }

        // Ctrl+E - Export results
        if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
            e.preventDefault();
            this.exportResults('json');
        }
    }

    async loadSuggestions(query) {
        try {
            const url = new URL(this.options.suggestionsUrl, window.location.origin);
            url.searchParams.set('query', query);
            url.searchParams.set('limit', this.options.maxSuggestions);

            const response = await fetch(url);
            const data = await response.json();

            if (data.success) {
                this.displaySuggestions(data.data);
            }
        } catch (error) {
            // Error loading suggestions
        }
    }

    displaySuggestions(suggestions) {
        if (!this.suggestionsContainer) return;

        let html = '';

        // Terms suggestions
        if (suggestions.terms && suggestions.terms.length > 0) {
            html += '<div class="suggestion-group">';
            html += '<div class="suggestion-group-title">Termini suggeriti</div>';
            suggestions.terms.forEach(term => {
                html += `<div class="suggestion-item" data-type="term" data-value="${term}">
                    <i class="fas fa-search"></i> ${term}
                </div>`;
            });
            html += '</div>';
        }

        // Document suggestions
        if (suggestions.documents && suggestions.documents.length > 0) {
            html += '<div class="suggestion-group">';
            html += '<div class="suggestion-group-title">Documenti</div>';
            suggestions.documents.forEach(doc => {
                html += `<div class="suggestion-item" data-type="document" data-value="${doc}">
                    <i class="fas fa-file"></i> ${doc}
                </div>`;
            });
            html += '</div>';
        }

        // Category suggestions
        if (suggestions.categories && suggestions.categories.length > 0) {
            html += '<div class="suggestion-group">';
            html += '<div class="suggestion-group-title">Categorie</div>';
            suggestions.categories.forEach(cat => {
                html += `<div class="suggestion-item" data-type="category" data-value="${cat}">
                    <i class="fas fa-tag"></i> ${cat}
                </div>`;
            });
            html += '</div>';
        }

        // Recent searches
        if (suggestions.recent_searches && suggestions.recent_searches.length > 0) {
            html += '<div class="suggestion-group">';
            html += '<div class="suggestion-group-title">Ricerche recenti</div>';
            suggestions.recent_searches.forEach(search => {
                html += `<div class="suggestion-item" data-type="recent" data-value="${search}">
                    <i class="fas fa-history"></i> ${search}
                </div>`;
            });
            html += '</div>';
        }

        this.suggestionsContainer.innerHTML = html;
        this.showSuggestions();
    }

    showSuggestions() {
        if (this.suggestionsContainer && this.suggestionsContainer.innerHTML.trim()) {
            this.suggestionsContainer.style.display = 'block';
            this.suggestionsVisible = true;
        }
    }

    hideSuggestions(delay = 0) {
        setTimeout(() => {
            if (this.suggestionsContainer) {
                this.suggestionsContainer.style.display = 'none';
                this.suggestionsVisible = false;
            }
        }, delay);
    }

    navigateSuggestions(suggestions, activeSuggestion, direction) {
        let newIndex = 0;

        if (activeSuggestion) {
            activeSuggestion.classList.remove('active');
            const currentIndex = Array.from(suggestions).indexOf(activeSuggestion);
            newIndex = (currentIndex + direction + suggestions.length) % suggestions.length;
        }

        if (suggestions[newIndex]) {
            suggestions[newIndex].classList.add('active');
        }
    }

    applySuggestion(suggestionElement) {
        const value = suggestionElement.dataset.value;
        const type = suggestionElement.dataset.type;

        if (type === 'category') {
            // Apply as filter
            this.applyFilter('tipo_documento', value);
        } else {
            // Apply as search term
            this.searchInput.value = value;
            this.currentQuery = value;
            this.performSearch(value);
        }

        this.hideSuggestions();
    }

    async performSearch(query) {
        if (this.currentRequest) {
            this.currentRequest.abort();
        }

        this.showLoadingState();

        try {
            const controller = new AbortController();
            this.currentRequest = controller;

            const formData = new FormData();
            if (this.options.csrfToken) {
                formData.append('csrf_token', this.options.csrfToken);
            }
            
            if (query) {
                formData.append('query', query);
            }

            // Add filters
            Object.entries(this.currentFilters).forEach(([key, value]) => {
                if (Array.isArray(value)) {
                    value.forEach(v => formData.append(`${key}[]`, v));
                } else {
                    formData.append(key, value);
                }
            });

            formData.append('highlight_terms', 'true');
            formData.append('include_content', 'false');

            const response = await fetch(this.options.searchUrl, {
                method: 'POST',
                body: formData,
                signal: controller.signal
            });

            const data = await response.json();

            if (data.success) {
                this.displayResults(data.data);
                this.addToHistory(query, this.currentFilters);
            } else {
                this.showError(data.error || 'Errore durante la ricerca');
            }

        } catch (error) {
            if (error.name !== 'AbortError') {
                // Search error
                this.showError('Errore di connessione');
            }
        } finally {
            this.hideLoadingState();
            this.currentRequest = null;
        }
    }

    displayResults(data) {
        if (!this.resultsContainer) return;

        const { documents, pagination, suggestions, search_stats } = data;

        let html = '';

        // Search stats
        html += `<div class="search-stats mb-3">
            <div class="row">
                <div class="col-md-6">
                    <span class="text-muted">
                        ${pagination.total} risultati trovati in ${search_stats.search_time}s
                    </span>
                </div>
                <div class="col-md-6 text-end">
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" data-action="export-results" data-format="json">
                            <i class="fas fa-download"></i> JSON
                        </button>
                        <button type="button" class="btn btn-outline-secondary" data-action="export-results" data-format="csv">
                            <i class="fas fa-file-csv"></i> CSV
                        </button>
                        ${documents.length > 1 ? `
                        <button type="button" class="btn btn-outline-primary" data-action="download-multiple">
                            <i class="fas fa-archive"></i> Download ZIP
                        </button>
                        ` : ''}
                    </div>
                </div>
            </div>
        </div>`;

        // Suggested actions
        if (data.suggested_actions && data.suggested_actions.length > 0) {
            html += '<div class="suggested-actions mb-3">';
            html += '<h6>Azioni suggerite:</h6>';
            html += '<div class="d-flex flex-wrap gap-2">';
            data.suggested_actions.forEach(action => {
                html += `<button class="btn btn-sm btn-outline-info" data-action="${action.type}" title="${action.description}">
                    <i class="fas fa-${action.icon}"></i> ${action.title}
                </button>`;
            });
            html += '</div></div>';
        }

        // Results
        if (documents.length > 0) {
            html += '<div class="search-results">';
            documents.forEach(doc => {
                html += this.renderDocumentResult(doc);
            });
            html += '</div>';

            // Pagination
            if (pagination.pages > 1) {
                html += this.renderPagination(pagination);
            }
        } else {
            html += '<div class="no-results text-center py-5">';
            html += '<i class="fas fa-search fa-3x text-muted mb-3"></i>';
            html += '<h5>Nessun risultato trovato</h5>';
            html += '<p class="text-muted">Prova a modificare i termini di ricerca o i filtri.</p>';
            html += '</div>';
        }

        this.resultsContainer.innerHTML = html;
    }

    renderDocumentResult(doc) {
        return `
            <div class="search-result-item card mb-3" data-document-id="${doc.id}">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h6 class="card-title mb-1">
                                <a href="/documento.php?id=${doc.id}" class="text-decoration-none">
                                    ${doc.highlighted_title || doc.titolo}
                                </a>
                                <span class="badge badge-sm bg-secondary ms-2">${doc.codice}</span>
                            </h6>
                            <p class="card-text text-muted small mb-2">
                                ${doc.highlighted_description || doc.descrizione || 'Nessuna descrizione'}
                            </p>
                            <div class="document-meta">
                                <span class="badge bg-light text-dark me-2">
                                    <i class="fas ${this.getFileIcon(doc.tipo_file)}"></i> ${doc.tipo_documento}
                                </span>
                                <span class="text-muted small">
                                    Creato: ${new Date(doc.data_creazione).toLocaleDateString()}
                                </span>
                                ${doc.cartella_nome ? `
                                <span class="text-muted small ms-2">
                                    <i class="fas fa-folder"></i> ${doc.cartella_nome}
                                </span>
                                ` : ''}
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="document-actions">
                                <a href="/documento.php?id=${doc.id}" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i> Visualizza
                                </a>
                                ${doc.file_path ? `
                                <a href="/backend/api/download-file.php?id=${doc.id}" class="btn btn-sm btn-outline-secondary ms-1">
                                    <i class="fas fa-download"></i>
                                </a>
                                ` : ''}
                            </div>
                            ${doc.file_size_formatted ? `
                            <div class="text-muted small mt-2">
                                Dimensione: ${doc.file_size_formatted}
                            </div>
                            ` : ''}
                            ${doc.relevance_score ? `
                            <div class="text-muted small">
                                Rilevanza: ${doc.relevance_score}
                            </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    renderPagination(pagination) {
        const { page, pages, total } = pagination;
        let html = '<nav aria-label="Risultati ricerca">';
        html += '<ul class="pagination justify-content-center">';

        // Previous
        html += `<li class="page-item ${page <= 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${page - 1}">Precedente</a>
        </li>`;

        // Pages
        const maxVisible = 5;
        let start = Math.max(1, page - Math.floor(maxVisible / 2));
        let end = Math.min(pages, start + maxVisible - 1);
        
        if (end - start < maxVisible - 1) {
            start = Math.max(1, end - maxVisible + 1);
        }

        for (let i = start; i <= end; i++) {
            html += `<li class="page-item ${i === page ? 'active' : ''}">
                <a class="page-link" href="#" data-page="${i}">${i}</a>
            </li>`;
        }

        // Next
        html += `<li class="page-item ${page >= pages ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${page + 1}">Successiva</a>
        </li>`;

        html += '</ul></nav>';
        return html;
    }

    applyFilter(filterName, filterValue) {
        const filterElement = this.filtersContainer.querySelector(`[name="${filterName}"]`);
        if (filterElement) {
            if (filterElement.type === 'checkbox') {
                filterElement.checked = true;
            } else {
                filterElement.value = filterValue;
            }
            this.handleFilterChange(filterElement);
        }
    }

    clearFilters() {
        this.currentFilters = {};
        
        if (this.filtersContainer) {
            const inputs = this.filtersContainer.querySelectorAll('input, select');
            inputs.forEach(input => {
                if (input.type === 'checkbox' || input.type === 'radio') {
                    input.checked = false;
                } else {
                    input.value = '';
                }
            });
        }

        this.performSearch(this.currentQuery);
        this.updateFilterSummary();
    }

    updateFilterSummary() {
        const summaryContainer = document.querySelector('#active-filters-summary');
        if (!summaryContainer) return;

        const activeFilters = Object.entries(this.currentFilters);
        
        if (activeFilters.length === 0) {
            summaryContainer.innerHTML = '';
            summaryContainer.style.display = 'none';
            return;
        }

        let html = '<div class="active-filters mb-3">';
        html += '<h6>Filtri attivi:</h6>';
        html += '<div class="d-flex flex-wrap gap-1">';

        activeFilters.forEach(([key, value]) => {
            const displayValue = Array.isArray(value) ? value.join(', ') : value;
            html += `<span class="badge bg-primary">
                ${this.getFilterLabel(key)}: ${displayValue}
                <button type="button" class="btn-close btn-close-white ms-1" data-filter="${key}"></button>
            </span>`;
        });

        html += '</div></div>';
        summaryContainer.innerHTML = html;
        summaryContainer.style.display = 'block';

        // Add remove filter listeners
        summaryContainer.querySelectorAll('.btn-close').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const filterKey = e.target.dataset.filter;
                delete this.currentFilters[filterKey];
                const filterElement = this.filtersContainer.querySelector(`[name="${filterKey}"]`);
                if (filterElement) {
                    if (filterElement.type === 'checkbox' || filterElement.type === 'radio') {
                        filterElement.checked = false;
                    } else {
                        filterElement.value = '';
                    }
                }
                this.performSearch(this.currentQuery);
                this.updateFilterSummary();
            });
        });
    }

    async exportResults(format) {
        try {
            const url = new URL(this.options.exportUrl, window.location.origin);
            url.searchParams.set('format', format);
            
            if (this.currentQuery) {
                url.searchParams.set('query', this.currentQuery);
            }

            Object.entries(this.currentFilters).forEach(([key, value]) => {
                if (Array.isArray(value)) {
                    value.forEach(v => url.searchParams.append(`${key}[]`, v));
                } else {
                    url.searchParams.set(key, value);
                }
            });

            window.open(url, '_blank');
            
        } catch (error) {
            // Export error
            this.showError('Errore durante l\'export');
        }
    }

    triggerMultipleDownload() {
        const resultItems = this.resultsContainer.querySelectorAll('.search-result-item');
        const documentIds = Array.from(resultItems).map(item => item.dataset.documentId).filter(Boolean);
        
        if (documentIds.length === 0) {
            this.showError('Nessun documento da scaricare');
            return;
        }

        // Trigger multi-download se disponibile
        if (window.nexioDownloader) {
            // Simula selezione documenti
            documentIds.forEach(id => {
                const item = document.querySelector(`[data-item-id="${id}"]`);
                if (item) {
                    const checkbox = item.querySelector('input[type="checkbox"]');
                    if (checkbox) {
                        checkbox.checked = true;
                        window.nexioDownloader.handleItemSelection(item, true);
                    }
                }
            });
            
            window.nexioDownloader.openOptionsModal();
        } else {
            this.showError('Sistema download multiplo non disponibile');
        }
    }

    saveCurrentSearch() {
        const searchData = {
            query: this.currentQuery,
            filters: this.currentFilters,
            timestamp: new Date().toISOString()
        };

        const name = prompt('Nome per questa ricerca:');
        if (name) {
            const savedSearches = JSON.parse(localStorage.getItem('nexio_saved_searches') || '[]');
            savedSearches.push({ name, ...searchData });
            localStorage.setItem('nexio_saved_searches', JSON.stringify(savedSearches));
            this.showSuccess('Ricerca salvata con successo');
        }
    }

    addToHistory(query, filters) {
        if (!this.options.enableHistory || !query) return;

        const historyItem = {
            query,
            filters: { ...filters },
            timestamp: new Date().toISOString()
        };

        // Remove duplicates
        this.searchHistory = this.searchHistory.filter(item => 
            item.query !== query || JSON.stringify(item.filters) !== JSON.stringify(filters)
        );

        this.searchHistory.unshift(historyItem);
        this.searchHistory = this.searchHistory.slice(0, 10); // Keep last 10

        this.saveSearchHistory();
        this.updateHistoryDisplay();
    }

    loadSearchHistory() {
        try {
            const saved = localStorage.getItem('nexio_search_history');
            this.searchHistory = saved ? JSON.parse(saved) : [];
        } catch (error) {
            // Error loading search history
            this.searchHistory = [];
        }
    }

    saveSearchHistory() {
        try {
            localStorage.setItem('nexio_search_history', JSON.stringify(this.searchHistory));
        } catch (error) {
            // Error saving search history
        }
    }

    updateHistoryDisplay() {
        if (!this.historyContainer || this.searchHistory.length === 0) return;

        let html = '<h6>Ricerche recenti:</h6>';
        html += '<div class="list-group list-group-flush">';

        this.searchHistory.forEach((item, index) => {
            const filterCount = Object.keys(item.filters).length;
            html += `<div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                <div class="flex-grow-1 cursor-pointer" data-history-index="${index}">
                    <div class="fw-medium">${item.query}</div>
                    ${filterCount > 0 ? `<small class="text-muted">${filterCount} filtri applicati</small>` : ''}
                </div>
                <small class="text-muted">${new Date(item.timestamp).toLocaleDateString()}</small>
            </div>`;
        });

        html += '</div>';
        this.historyContainer.innerHTML = html;

        // Add click listeners
        this.historyContainer.querySelectorAll('[data-history-index]').forEach(item => {
            item.addEventListener('click', (e) => {
                const index = parseInt(e.currentTarget.dataset.historyIndex);
                this.loadSearchFromHistory(index);
            });
        });
    }

    loadSearchFromHistory(index) {
        const historyItem = this.searchHistory[index];
        if (!historyItem) return;

        // Set query
        if (this.searchInput) {
            this.searchInput.value = historyItem.query;
        }
        this.currentQuery = historyItem.query;

        // Set filters
        this.currentFilters = { ...historyItem.filters };
        this.applyFiltersToForm();

        // Perform search
        this.performSearch(historyItem.query);
    }

    applyFiltersToForm() {
        if (!this.filtersContainer) return;

        Object.entries(this.currentFilters).forEach(([key, value]) => {
            const elements = this.filtersContainer.querySelectorAll(`[name="${key}"]`);
            elements.forEach(element => {
                if (element.type === 'checkbox') {
                    element.checked = Array.isArray(value) ? value.includes(element.value) : value === element.value;
                } else if (element.type === 'radio') {
                    element.checked = value === element.value;
                } else {
                    element.value = Array.isArray(value) ? value.join(',') : value;
                }
            });
        });

        this.updateFilterSummary();
    }

    initializeFilters() {
        // Setup any dynamic filter behavior
        this.updateFilterSummary();
    }

    // Utility methods

    getFileIcon(fileType) {
        const type = (fileType || '').toLowerCase();
        
        if (type.includes('pdf')) return 'fa-file-pdf';
        if (type.includes('doc')) return 'fa-file-word';
        if (type.includes('xls')) return 'fa-file-excel';
        if (type.includes('ppt')) return 'fa-file-powerpoint';
        if (['jpg', 'jpeg', 'png', 'gif'].includes(type)) return 'fa-file-image';
        if (type.includes('zip') || type.includes('rar')) return 'fa-file-archive';
        if (type === 'txt' || type === 'csv') return 'fa-file-alt';
        
        return 'fa-file';
    }

    getFilterLabel(filterKey) {
        const labels = {
            'tipo_documento': 'Tipo',
            'norma_iso': 'Norma ISO',
            'stato': 'Stato',
            'cartella_id': 'Cartella',
            'data_da': 'Da',
            'data_a': 'A',
            'creato_da': 'Creato da',
            'contiene_dati_personali': 'Dati personali'
        };
        
        return labels[filterKey] || filterKey;
    }

    showLoadingState() {
        if (this.resultsContainer) {
            this.resultsContainer.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Caricamento...</span>
                    </div>
                    <div class="mt-2">Ricerca in corso...</div>
                </div>
            `;
        }
    }

    hideLoadingState() {
        // Loading state will be replaced by results
    }

    clearResults() {
        if (this.resultsContainer) {
            this.resultsContainer.innerHTML = '';
        }
    }

    loadCSRFToken() {
        const tokenMeta = document.querySelector('meta[name="csrf-token"]');
        if (tokenMeta) {
            this.options.csrfToken = tokenMeta.getAttribute('content');
        }
    }

    // Notification methods
    showSuccess(message) {
        this.showNotification(message, 'success');
    }

    showError(message) {
        this.showNotification(message, 'danger');
    }

    showNotification(message, type) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(alertDiv);
        
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
}

// Initialize quando il DOM è ready
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.nexioSearch === 'undefined') {
        window.nexioSearch = new NexioAdvancedSearch();
    }
});

// Export per uso come modulo
if (typeof module !== 'undefined' && module.exports) {
    module.exports = NexioAdvancedSearch;
}