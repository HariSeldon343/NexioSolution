/**
 * Permission Manager JS - Sistema di gestione permessi frontend
 * 
 * Gestisce l'interfaccia utente dinamica basata sui permessi,
 * nasconde/mostra elementi e gestisce stati dei controlli
 * 
 * @author Nexio Platform
 * @version 1.0.0
 */

class PermissionManager {
    constructor() {
        this.userPermissions = [];
        this.csrfToken = null;
        this.currentUser = null;
        this.currentCompany = null;
        this.permissionCache = new Map();
        this.cacheTimeout = 5 * 60 * 1000; // 5 minuti
        
        this.init();
    }
    
    /**
     * Inizializza il sistema di permessi
     */
    async init() {
        try {
            // Carica permessi utente corrente
            await this.loadUserPermissions();
            
            // Applica permessi a tutti gli elementi con attributi data-permission
            this.applyPermissionsToDOM();
            
            // Setup listener per aggiornamenti dinamici
            this.setupEventListeners();
            
            // Auto-refresh permessi ogni 5 minuti
            setInterval(() => this.refreshPermissions(), 5 * 60 * 1000);
            
            console.log('Permission Manager initialized');
        } catch (error) {
            console.error('Errore inizializzazione Permission Manager:', error);
        }
    }
    
    /**
     * Carica permessi utente dal server
     */
    async loadUserPermissions() {
        try {
            const response = await fetch('/backend/api/get-user-permissions.php', {
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
            
            if (data.success) {
                this.userPermissions = data.permissions || [];
                this.currentUser = data.user || null;
                this.currentCompany = data.company || null;
                this.csrfToken = data.csrf_token || null;
                
                // Aggiorna meta tag CSRF
                this.updateCSRFMeta();
                
                console.log('Permessi caricati:', this.userPermissions);
            } else {
                throw new Error(data.error || 'Errore caricamento permessi');
            }
        } catch (error) {
            console.error('Errore caricamento permessi:', error);
            // Fallback: nascondi tutto tranne elementi base
            this.applyFallbackPermissions();
        }
    }
    
    /**
     * Verifica se l'utente ha un permesso specifico
     */
    hasPermission(permission) {
        if (!this.userPermissions) return false;
        
        // Super admin ha sempre tutti i permessi
        if (this.userPermissions.includes('*')) return true;
        
        // Verifica permesso specifico
        return this.userPermissions.includes(permission);
    }
    
    /**
     * Verifica permesso per documento specifico
     */
    async hasDocumentPermission(documentId, action = 'view') {
        const cacheKey = `doc_${documentId}_${action}`;
        
        // Check cache
        if (this.permissionCache.has(cacheKey)) {
            const cached = this.permissionCache.get(cacheKey);
            if (Date.now() - cached.timestamp < this.cacheTimeout) {
                return cached.result;
            }
        }
        
        try {
            const response = await fetch('/backend/api/check-document-permission.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrfToken
                },
                body: JSON.stringify({
                    document_id: documentId,
                    action: action
                }),
                credentials: 'same-origin'
            });
            
            const data = await response.json();
            const result = data.success && data.has_permission;
            
            // Cache result
            this.permissionCache.set(cacheKey, {
                result: result,
                timestamp: Date.now()
            });
            
            return result;
        } catch (error) {
            console.error('Errore verifica permesso documento:', error);
            return false;
        }
    }
    
    /**
     * Verifica permesso per cartella specifica
     */
    async hasFolderPermission(folderId, action = 'view') {
        const cacheKey = `folder_${folderId}_${action}`;
        
        // Check cache
        if (this.permissionCache.has(cacheKey)) {
            const cached = this.permissionCache.get(cacheKey);
            if (Date.now() - cached.timestamp < this.cacheTimeout) {
                return cached.result;
            }
        }
        
        try {
            const response = await fetch('/backend/api/check-folder-permission.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrfToken
                },
                body: JSON.stringify({
                    folder_id: folderId,
                    action: action
                }),
                credentials: 'same-origin'
            });
            
            const data = await response.json();
            const result = data.success && data.has_permission;
            
            // Cache result
            this.permissionCache.set(cacheKey, {
                result: result,
                timestamp: Date.now()
            });
            
            return result;
        } catch (error) {
            console.error('Errore verifica permesso cartella:', error);
            return false;
        }
    }
    
    /**
     * Applica permessi a tutti gli elementi DOM
     */
    applyPermissionsToDOM() {
        // Elementi con data-permission (permessi generali)
        document.querySelectorAll('[data-permission]').forEach(element => {
            const permission = element.getAttribute('data-permission');
            const hasAccess = this.hasPermission(permission);
            
            this.setElementAccess(element, hasAccess);
        });
        
        // Elementi con data-document-permission (permessi documenti)
        document.querySelectorAll('[data-document-permission]').forEach(async element => {
            const permission = element.getAttribute('data-document-permission');
            const documentId = element.getAttribute('data-document-id');
            
            if (documentId) {
                const hasAccess = await this.hasDocumentPermission(documentId, permission);
                this.setElementAccess(element, hasAccess);
            }
        });
        
        // Elementi con data-folder-permission (permessi cartelle)
        document.querySelectorAll('[data-folder-permission]').forEach(async element => {
            const permission = element.getAttribute('data-folder-permission');
            const folderId = element.getAttribute('data-folder-id');
            
            if (folderId) {
                const hasAccess = await this.hasFolderPermission(folderId, permission);
                this.setElementAccess(element, hasAccess);
            }
        });
        
        // Elementi con data-role (basati su ruolo)
        document.querySelectorAll('[data-role]').forEach(element => {
            const requiredRole = element.getAttribute('data-role');
            const userRole = this.currentUser?.ruolo;
            
            const hasAccess = this.checkRoleAccess(userRole, requiredRole);
            this.setElementAccess(element, hasAccess);
        });
        
        // Menu items dinamici
        this.updateNavigationMenu();
    }
    
    /**
     * Imposta accesso elemento
     */
    setElementAccess(element, hasAccess) {
        if (hasAccess) {
            element.style.display = '';
            element.removeAttribute('disabled');
            element.classList.remove('permission-denied', 'd-none');
            element.classList.add('permission-granted');
        } else {
            const hideMethod = element.getAttribute('data-hide-method') || 'hide';
            
            switch (hideMethod) {
                case 'disable':
                    element.setAttribute('disabled', 'disabled');
                    element.classList.add('disabled', 'permission-denied');
                    break;
                case 'readonly':
                    element.setAttribute('readonly', 'readonly');
                    element.classList.add('readonly', 'permission-denied');
                    break;
                default:
                    element.style.display = 'none';
                    element.classList.add('d-none', 'permission-denied');
            }
            
            element.classList.remove('permission-granted');
        }
    }
    
    /**
     * Verifica accesso basato su ruolo
     */
    checkRoleAccess(userRole, requiredRole) {
        if (!userRole || !requiredRole) return false;
        
        // Hierarchy dei ruoli
        const roleHierarchy = {
            'super_admin': 5,
            'utente_speciale': 4,
            'admin': 3,
            'manager': 2,
            'staff': 1,
            'cliente': 0
        };
        
        const userLevel = roleHierarchy[userRole] || 0;
        const requiredLevel = roleHierarchy[requiredRole] || 0;
        
        return userLevel >= requiredLevel;
    }
    
    /**
     * Aggiorna menu di navigazione dinamicamente
     */
    updateNavigationMenu() {
        // Menu principale
        const menuItems = document.querySelectorAll('.nav-item[data-module]');
        menuItems.forEach(item => {
            const module = item.getAttribute('data-module');
            const permission = `${module}_access`;
            
            if (!this.hasPermission(permission)) {
                item.style.display = 'none';
            }
        });
        
        // Sottomenu
        const subMenus = document.querySelectorAll('.dropdown-menu .dropdown-item[data-permission]');
        subMenus.forEach(item => {
            const permission = item.getAttribute('data-permission');
            
            if (!this.hasPermission(permission)) {
                item.style.display = 'none';
            }
        });
        
        // Nascondi dropdown vuoti
        document.querySelectorAll('.nav-item.dropdown').forEach(dropdown => {
            const visibleItems = dropdown.querySelectorAll('.dropdown-item:not([style*="display: none"])');
            if (visibleItems.length === 0) {
                dropdown.style.display = 'none';
            }
        });
    }
    
    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Intercetta click su elementi senza permessi
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('permission-denied') || 
                e.target.closest('.permission-denied')) {
                e.preventDefault();
                e.stopPropagation();
                
                this.showPermissionDeniedMessage();
            }
        });
        
        // Gestione form con permessi
        document.addEventListener('submit', (e) => {
            const form = e.target;
            const permission = form.getAttribute('data-permission');
            
            if (permission && !this.hasPermission(permission)) {
                e.preventDefault();
                this.showPermissionDeniedMessage();
            }
        });
        
        // Aggiorna CSRF token automaticamente
        document.addEventListener('DOMContentLoaded', () => {
            this.updateCSRFInputs();
        });
    }
    
    /**
     * Mostra messaggio permessi negati
     */
    showPermissionDeniedMessage() {
        // Usa sistema notifiche esistente se disponibile
        if (window.showNotification) {
            window.showNotification('Permessi insufficienti per questa operazione', 'warning');
        } else if (window.toastr) {
            toastr.warning('Permessi insufficienti per questa operazione');
        } else {
            alert('Permessi insufficienti per questa operazione');
        }
    }
    
    /**
     * Refresh permessi
     */
    async refreshPermissions() {
        await this.loadUserPermissions();
        this.permissionCache.clear();
        this.applyPermissionsToDOM();
        console.log('Permessi aggiornati');
    }
    
    /**
     * Applica permessi fallback in caso di errore
     */
    applyFallbackPermissions() {
        // Nascondi tutti gli elementi admin
        document.querySelectorAll('[data-permission*="admin"], [data-permission*="delete"]').forEach(el => {
            el.style.display = 'none';
        });
        
        // Disabilita tutti i form di modifica
        document.querySelectorAll('form[data-permission]').forEach(form => {
            const inputs = form.querySelectorAll('input, textarea, select, button');
            inputs.forEach(input => input.setAttribute('disabled', 'disabled'));
        });
    }
    
    /**
     * Aggiorna meta tag CSRF
     */
    updateCSRFMeta() {
        if (this.csrfToken) {
            let metaTag = document.querySelector('meta[name="csrf-token"]');
            if (!metaTag) {
                metaTag = document.createElement('meta');
                metaTag.name = 'csrf-token';
                document.head.appendChild(metaTag);
            }
            metaTag.content = this.csrfToken;
        }
    }
    
    /**
     * Aggiorna tutti gli input CSRF hidden
     */
    updateCSRFInputs() {
        if (this.csrfToken) {
            document.querySelectorAll('input[name="csrf_token"]').forEach(input => {
                input.value = this.csrfToken;
            });
        }
    }
    
    /**
     * Gestione permessi per tabelle/liste
     */
    updateTableActions(tableSelector = 'table[data-permissions]') {
        document.querySelectorAll(tableSelector).forEach(table => {
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(async row => {
                const documentId = row.getAttribute('data-document-id');
                const folderId = row.getAttribute('data-folder-id');
                
                // Azioni sui documenti
                if (documentId) {
                    const editBtn = row.querySelector('.btn-edit');
                    const deleteBtn = row.querySelector('.btn-delete');
                    const downloadBtn = row.querySelector('.btn-download');
                    
                    if (editBtn) {
                        const canEdit = await this.hasDocumentPermission(documentId, 'edit');
                        this.setElementAccess(editBtn, canEdit);
                    }
                    
                    if (deleteBtn) {
                        const canDelete = await this.hasDocumentPermission(documentId, 'delete');
                        this.setElementAccess(deleteBtn, canDelete);
                    }
                    
                    if (downloadBtn) {
                        const canDownload = await this.hasDocumentPermission(documentId, 'download');
                        this.setElementAccess(downloadBtn, canDownload);
                    }
                }
                
                // Azioni sulle cartelle
                if (folderId) {
                    const editBtn = row.querySelector('.btn-edit');
                    const deleteBtn = row.querySelector('.btn-delete');
                    
                    if (editBtn) {
                        const canEdit = await this.hasFolderPermission(folderId, 'edit');
                        this.setElementAccess(editBtn, canEdit);
                    }
                    
                    if (deleteBtn) {
                        const canDelete = await this.hasFolderPermission(folderId, 'delete');
                        this.setElementAccess(deleteBtn, canDelete);
                    }
                }
            });
        });
    }
    
    /**
     * Gestione upload files con permessi
     */
    checkUploadPermissions(folderId = null) {
        if (folderId) {
            return this.hasFolderPermission(folderId, 'write');
        }
        
        return this.hasPermission('document_upload');
    }
    
    /**
     * Utility per aggiungere permessi dinamicamente a elementi
     */
    addPermissionToElement(element, permission, resourceId = null, resourceType = null) {
        if (resourceType === 'document' && resourceId) {
            element.setAttribute('data-document-permission', permission);
            element.setAttribute('data-document-id', resourceId);
        } else if (resourceType === 'folder' && resourceId) {
            element.setAttribute('data-folder-permission', permission);
            element.setAttribute('data-folder-id', resourceId);
        } else {
            element.setAttribute('data-permission', permission);
        }
        
        // Applica immediatamente
        this.applyPermissionsToDOM();
    }
    
    /**
     * Gestione permessi ISO specifici
     */
    checkISOPermission(action) {
        const isoPermissions = [
            'iso_configure',
            'iso_manage_compliance',
            'iso_audit_access',
            'iso_structure_admin'
        ];
        
        return isoPermissions.includes(action) && this.hasPermission(action);
    }
    
    /**
     * Gestione modale di assegnazione permessi (per admin)
     */
    openPermissionModal(resourceType, resourceId) {
        if (!this.hasPermission('folder_manage_permissions') && 
            !this.hasPermission('system_admin')) {
            this.showPermissionDeniedMessage();
            return;
        }
        
        // Carica modale permessi
        const modal = document.getElementById('permissionModal');
        if (modal) {
            modal.setAttribute('data-resource-type', resourceType);
            modal.setAttribute('data-resource-id', resourceId);
            
            // Carica utenti e permessi attuali
            this.loadPermissionModalData(resourceType, resourceId);
            
            // Mostra modale
            if (window.bootstrap) {
                new bootstrap.Modal(modal).show();
            } else if (window.$) {
                $(modal).modal('show');
            }
        }
    }
    
    /**
     * Carica dati per modale permessi
     */
    async loadPermissionModalData(resourceType, resourceId) {
        try {
            const response = await fetch('/backend/api/get-resource-permissions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrfToken
                },
                body: JSON.stringify({
                    resource_type: resourceType,
                    resource_id: resourceId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.populatePermissionModal(data.users, data.permissions);
            }
        } catch (error) {
            console.error('Errore caricamento dati permessi:', error);
        }
    }
    
    /**
     * Popola modale permessi
     */
    populatePermissionModal(users, permissions) {
        // Implementazione specifica per il layout della modale
        const userList = document.getElementById('permission-user-list');
        if (userList) {
            userList.innerHTML = '';
            
            users.forEach(user => {
                const userRow = this.createUserPermissionRow(user, permissions);
                userList.appendChild(userRow);
            });
        }
    }
    
    /**
     * Crea riga utente per modale permessi
     */
    createUserPermissionRow(user, currentPermissions) {
        const row = document.createElement('div');
        row.className = 'row align-items-center mb-2 p-2 border-bottom';
        
        const userPerms = currentPermissions.filter(p => p.user_id === user.id);
        
        row.innerHTML = `
            <div class="col-md-4">
                <strong>${user.nome} ${user.cognome}</strong><br>
                <small class="text-muted">${user.email}</small>
            </div>
            <div class="col-md-8">
                <div class="permission-checkboxes">
                    <label><input type="checkbox" value="read" ${userPerms.some(p => p.permission_type === 'read') ? 'checked' : ''}> Lettura</label>
                    <label><input type="checkbox" value="write" ${userPerms.some(p => p.permission_type === 'write') ? 'checked' : ''}> Scrittura</label>
                    <label><input type="checkbox" value="delete" ${userPerms.some(p => p.permission_type === 'delete') ? 'checked' : ''}> Eliminazione</label>
                    <label><input type="checkbox" value="share" ${userPerms.some(p => p.permission_type === 'share') ? 'checked' : ''}> Condivisione</label>
                </div>
            </div>
        `;
        
        return row;
    }
}

// Inizializza sistema permessi quando DOM è pronto
document.addEventListener('DOMContentLoaded', () => {
    window.permissionManager = new PermissionManager();
});

// Funzioni helper globali
window.hasPermission = function(permission) {
    return window.permissionManager ? window.permissionManager.hasPermission(permission) : false;
};

window.checkDocumentPermission = async function(documentId, action) {
    return window.permissionManager ? 
        await window.permissionManager.hasDocumentPermission(documentId, action) : false;
};

window.checkFolderPermission = async function(folderId, action) {
    return window.permissionManager ? 
        await window.permissionManager.hasFolderPermission(folderId, action) : false;
};

window.refreshPermissions = function() {
    if (window.permissionManager) {
        window.permissionManager.refreshPermissions();
    }
};

window.openPermissionModal = function(resourceType, resourceId) {
    if (window.permissionManager) {
        window.permissionManager.openPermissionModal(resourceType, resourceId);
    }
};