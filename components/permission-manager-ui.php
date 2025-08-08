<?php
/**
 * UI Component per gestione permessi
 * Include modal e funzioni JavaScript per assegnare/revocare permessi
 * 
 * @package Nexio
 * @version 1.0.0
 */
?>

<!-- Modal Gestione Permessi -->
<div class="modal fade" id="permissionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user-shield"></i> Gestione Permessi
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Info risorsa -->
                <div class="alert alert-info" id="resourceInfo">
                    <strong>Risorsa:</strong> <span id="resourceName"></span>
                    <input type="hidden" id="resourceType" value="">
                    <input type="hidden" id="resourceId" value="">
                </div>
                
                <!-- Tab navigation -->
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#currentPermissions">
                            Permessi Attuali
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#assignPermissions">
                            Assegna Permessi
                        </a>
                    </li>
                </ul>
                
                <!-- Tab content -->
                <div class="tab-content mt-3">
                    <!-- Permessi attuali -->
                    <div class="tab-pane fade show active" id="currentPermissions">
                        <div class="table-responsive">
                            <table class="table table-striped" id="permissionsTable">
                                <thead>
                                    <tr>
                                        <th>Utente</th>
                                        <th>Permessi</th>
                                        <th>Assegnato da</th>
                                        <th>Data</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody id="permissionsTableBody">
                                    <!-- Popolato via JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Assegna permessi -->
                    <div class="tab-pane fade" id="assignPermissions">
                        <form id="assignPermissionsForm">
                            <!-- Selezione utenti -->
                            <div class="mb-3">
                                <label class="form-label">Seleziona Utenti</label>
                                <select class="form-select" id="userSelect" multiple size="5">
                                    <!-- Popolato via AJAX -->
                                </select>
                                <small class="text-muted">Tieni premuto Ctrl per selezioni multiple</small>
                            </div>
                            
                            <!-- Selezione permessi -->
                            <div class="mb-3">
                                <label class="form-label">Permessi da Assegnare</label>
                                <div id="permissionCheckboxes">
                                    <!-- Popolato dinamicamente in base al tipo di risorsa -->
                                </div>
                            </div>
                            
                            <!-- Opzioni aggiuntive per cartelle -->
                            <div class="mb-3" id="folderOptions" style="display:none;">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="inheritSubfolders" checked>
                                    <label class="form-check-label" for="inheritSubfolders">
                                        Eredita permessi alle sottocartelle
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Data scadenza (opzionale) -->
                            <div class="mb-3">
                                <label class="form-label">Scadenza Permessi (opzionale)</label>
                                <input type="datetime-local" class="form-control" id="expiresAt">
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-check"></i> Assegna Permessi
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Gestione permessi
const PermissionManager = {
    currentResource: null,
    
    // Inizializza il manager
    init() {
        // Event listeners
        document.getElementById('assignPermissionsForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.assignPermissions();
        });
        
        // Carica utenti disponibili
        this.loadAvailableUsers();
    },
    
    // Apri modal per risorsa
    openForResource(type, id, name) {
        this.currentResource = { type, id, name };
        
        // Aggiorna UI
        document.getElementById('resourceName').textContent = name;
        document.getElementById('resourceType').value = type;
        document.getElementById('resourceId').value = id;
        
        // Mostra/nascondi opzioni specifiche
        document.getElementById('folderOptions').style.display = 
            type === 'folder' ? 'block' : 'none';
        
        // Carica permessi attuali
        this.loadCurrentPermissions();
        
        // Carica checkbox permessi disponibili
        this.loadAvailablePermissions(type);
        
        // Mostra modal
        const modal = new bootstrap.Modal(document.getElementById('permissionModal'));
        modal.show();
    },
    
    // Carica permessi attuali
    async loadCurrentPermissions() {
        try {
            const response = await fetch(`backend/api/permission-management-api.php?action=${this.currentResource.type}_permissions&${this.currentResource.type}_id=${this.currentResource.id}`);
            const data = await response.json();
            
            if (data.success) {
                this.displayCurrentPermissions(data.data);
            } else {
                console.error('Errore caricamento permessi:', data.error);
            }
        } catch (error) {
            console.error('Errore:', error);
        }
    },
    
    // Visualizza permessi attuali
    displayCurrentPermissions(permissions) {
        const tbody = document.getElementById('permissionsTableBody');
        tbody.innerHTML = '';
        
        if (permissions.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center text-muted">
                        Nessun permesso specifico assegnato
                    </td>
                </tr>
            `;
            return;
        }
        
        permissions.forEach(perm => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${perm.nome} ${perm.cognome} (${perm.username})</td>
                <td><span class="badge bg-primary">${perm.permission_type}</span></td>
                <td>${perm.granted_by_name}</td>
                <td>${new Date(perm.granted_at).toLocaleDateString()}</td>
                <td>
                    <button class="btn btn-sm btn-danger" onclick="PermissionManager.revokePermission(${perm.user_id}, '${perm.permission_type}')">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
    },
    
    // Carica utenti disponibili
    async loadAvailableUsers() {
        try {
            const response = await fetch('backend/api/utenti.php?action=list_active');
            const data = await response.json();
            
            if (data.success) {
                const select = document.getElementById('userSelect');
                select.innerHTML = '';
                
                data.users.forEach(user => {
                    const option = document.createElement('option');
                    option.value = user.id;
                    option.textContent = `${user.nome} ${user.cognome} (${user.username})`;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Errore caricamento utenti:', error);
        }
    },
    
    // Carica permessi disponibili per tipo
    loadAvailablePermissions(type) {
        const container = document.getElementById('permissionCheckboxes');
        container.innerHTML = '';
        
        const permissions = type === 'folder' ? 
            ['view', 'create', 'edit', 'delete', 'manage_permissions'] :
            ['view', 'download', 'edit', 'delete', 'approve', 'share', 'version'];
        
        const labels = {
            view: 'Visualizza',
            create: 'Crea contenuti',
            edit: 'Modifica',
            delete: 'Elimina',
            manage_permissions: 'Gestisci permessi',
            download: 'Scarica',
            approve: 'Approva',
            share: 'Condividi',
            version: 'Gestione versioni'
        };
        
        permissions.forEach(perm => {
            const div = document.createElement('div');
            div.className = 'form-check form-check-inline';
            div.innerHTML = `
                <input class="form-check-input" type="checkbox" id="perm_${perm}" value="${perm}">
                <label class="form-check-label" for="perm_${perm}">
                    ${labels[perm] || perm}
                </label>
            `;
            container.appendChild(div);
        });
    },
    
    // Assegna permessi
    async assignPermissions() {
        const userSelect = document.getElementById('userSelect');
        const selectedUsers = Array.from(userSelect.selectedOptions).map(opt => opt.value);
        
        if (selectedUsers.length === 0) {
            alert('Seleziona almeno un utente');
            return;
        }
        
        // Raccogli permessi selezionati
        const permissions = [];
        document.querySelectorAll('#permissionCheckboxes input:checked').forEach(cb => {
            permissions.push(cb.value);
        });
        
        if (permissions.length === 0) {
            alert('Seleziona almeno un permesso');
            return;
        }
        
        try {
            const response = await fetch('backend/api/permission-management-api.php?action=assign_bulk_permissions', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    user_ids: selectedUsers,
                    permissions: permissions,
                    resource_type: this.currentResource.type,
                    resource_id: this.currentResource.id,
                    expires_at: document.getElementById('expiresAt').value || null
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert(data.message);
                this.loadCurrentPermissions(); // Ricarica lista
                document.getElementById('assignPermissionsForm').reset();
            } else {
                alert('Errore: ' + data.error);
            }
        } catch (error) {
            console.error('Errore:', error);
            alert('Errore durante l\'assegnazione dei permessi');
        }
    },
    
    // Revoca permesso
    async revokePermission(userId, permissionType) {
        if (!confirm('Vuoi davvero revocare questo permesso?')) {
            return;
        }
        
        try {
            const response = await fetch('backend/api/permission-management-api.php', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    user_id: userId,
                    permission_type: permissionType,
                    resource_type: this.currentResource.type,
                    resource_id: this.currentResource.id
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert(data.message);
                this.loadCurrentPermissions(); // Ricarica lista
            } else {
                alert('Errore: ' + data.error);
            }
        } catch (error) {
            console.error('Errore:', error);
            alert('Errore durante la revoca del permesso');
        }
    }
};

// Inizializza quando DOM è pronto
document.addEventListener('DOMContentLoaded', () => {
    PermissionManager.init();
});
</script>