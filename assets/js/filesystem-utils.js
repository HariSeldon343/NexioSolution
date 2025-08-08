/**
 * Utility functions for filesystem management
 * Provides error handling and common operations
 */

// API Base Configuration
const API_BASE = '/piattaforma-collaborativa/backend/api';

// Enhanced error handler
function handleApiError(error, defaultMessage = 'Si è verificato un errore') {
    console.error('API Error:', error);
    
    // Check if it's a network error
    if (!error.response) {
        return 'Errore di connessione. Verifica la tua connessione internet.';
    }
    
    // Check response status
    const status = error.response.status;
    
    switch (status) {
        case 401:
            // Unauthorized - redirect to login
            window.location.href = '/piattaforma-collaborativa/login.php?expired=1';
            return 'Sessione scaduta. Effettua nuovamente il login.';
            
        case 403:
            return 'Non hai i permessi per eseguire questa operazione.';
            
        case 404:
            return 'Risorsa non trovata.';
            
        case 413:
            return 'File troppo grande.';
            
        case 500:
            return 'Errore del server. Riprova più tardi.';
            
        default:
            // Try to get error message from response
            if (error.response.data && error.response.data.error) {
                return error.response.data.error;
            }
            return defaultMessage;
    }
}

// Safe API call wrapper
async function apiCall(url, options = {}) {
    try {
        const defaultOptions = {
            headers: {
                'Accept': 'application/json',
                'Cache-Control': 'no-cache'
            }
        };
        
        // Merge options
        const finalOptions = {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...(options.headers || {})
            }
        };
        
        // Add JSON content type for POST/PUT with body
        if ((options.method === 'POST' || options.method === 'PUT') && options.body && typeof options.body === 'string') {
            finalOptions.headers['Content-Type'] = 'application/json';
        }
        
        const response = await fetch(url, finalOptions);
        
        // Check if response is ok
        if (!response.ok) {
            // Try to get error message from response
            let errorData;
            try {
                errorData = await response.json();
            } catch (e) {
                errorData = { error: `HTTP ${response.status}: ${response.statusText}` };
            }
            
            throw {
                response: {
                    status: response.status,
                    data: errorData
                }
            };
        }
        
        // Parse JSON response
        const data = await response.json();
        return data;
        
    } catch (error) {
        // Re-throw with proper structure
        if (error.response) {
            throw error;
        }
        
        // Network or other error
        throw {
            response: null,
            message: error.message
        };
    }
}

// File system specific operations
const FileSystemAPI = {
    // Create folder
    async createFolder(name, parentId = null, companyId) {
        return await apiCall(`${API_BASE}/files-api.php`, {
            method: 'POST',
            body: JSON.stringify({
                action: 'create_folder',
                nome: name,
                parent_id: parentId,
                azienda_id: companyId
            })
        });
    },
    
    // Rename item
    async rename(type, id, newName) {
        return await apiCall(`${API_BASE}/files-api.php`, {
            method: 'POST',
            body: JSON.stringify({
                action: 'rename',
                type: type,
                id: id,
                new_name: newName
            })
        });
    },
    
    // Delete item
    async delete(type, id, recursive = true) {
        return await apiCall(`${API_BASE}/files-api.php`, {
            method: 'POST',
            headers: {
                'X-HTTP-Method-Override': 'DELETE'
            },
            body: JSON.stringify({
                _method: 'DELETE',
                type: type,
                id: id,
                recursive: recursive
            })
        });
    },
    
    // Upload file
    async uploadFile(file, folderId, companyId) {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('folder_id', folderId || '');
        formData.append('azienda_id', companyId);
        
        // Don't set Content-Type for FormData, let browser set it
        return await fetch(`${API_BASE}/upload-file.php`, {
            method: 'POST',
            body: formData
        }).then(response => {
            if (!response.ok) {
                throw {
                    response: {
                        status: response.status,
                        data: { error: `HTTP ${response.status}` }
                    }
                };
            }
            return response.json();
        });
    }
};

// Export for use in other scripts
window.FileSystemAPI = FileSystemAPI;
window.handleApiError = handleApiError;