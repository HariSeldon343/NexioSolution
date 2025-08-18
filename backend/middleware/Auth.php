<?php
/**
 * Classe Auth - Gestione Autenticazione
 * Enhanced with Permission System Integration
 */

require_once __DIR__ . '/../config/config.php';

class Auth {
    private static $instance = null;
    private $user = null;
    private $isAuthenticated = false;
    private $permissionManager = null;
    
    private function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $this->checkAuthentication();
        
        // Inizializza PermissionManager solo se autenticato
        if ($this->isAuthenticated) {
            $this->initPermissionManager();
        }
    }
    
    /**
     * Inizializza il Permission Manager
     */
    private function initPermissionManager() {
        try {
            if (class_exists('PermissionManager')) {
                $this->permissionManager = PermissionManager::getInstance();
            }
        } catch (Exception $e) {
            // Log errore ma non bloccare il sistema
            error_log("Errore inizializzazione PermissionManager: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function checkAuthentication() {
        if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
            $this->isAuthenticated = true;
            $this->user = [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'nome' => $_SESSION['nome'] ?? 'Utente',
                'cognome' => $_SESSION['cognome'] ?? '',
                'ruolo' => $_SESSION['ruolo'] ?? 'utente',
                'email' => $_SESSION['email'] ?? ''
            ];
        }
    }
    
    public function isAuthenticated() {
        return $this->isAuthenticated;
    }
    
    public function getUser() {
        return $this->user;
    }
    
    public function getUserId() {
        return $this->user ? ($this->user['id'] ?? null) : null;
    }
    
    public function requireAuth() {
        if (!$this->isAuthenticated()) {
            // Se siamo in una chiamata API, restituisci errore JSON
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            if (strpos($requestUri, '/api/') !== false || 
                (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
                header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Non autenticato']);
                exit;
            }
            redirect(APP_PATH . '/login.php');
        }
        
        // Controlla se è necessario il cambio password (solo se non siamo già nella pagina di cambio password)
        $currentPage = basename($_SERVER['PHP_SELF']);
        if ($currentPage !== 'cambio-password.php' && $currentPage !== 'logout.php') {
            if ($this->requiresPasswordChange()) {
                redirect(APP_PATH . '/cambio-password.php');
            }
        }
    }
    
    public function isLoggedIn() {
        return $this->isAuthenticated();
    }
    
    public function login($username, $password) {
        try {
            $stmt = db_query("SELECT * FROM utenti WHERE (username = ? OR email = ?) AND attivo = 1", [$username, $username]);
            
            if (!$stmt) {
                return ['success' => false, 'message' => 'Errore di sistema'];
            }
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['nome'] = $user['nome'];
                $_SESSION['cognome'] = $user['cognome'];
                $_SESSION['ruolo'] = $user['ruolo'];
                $_SESSION['email'] = $user['email'];
                
                // Per super admin e utenti speciali, non impostare automaticamente un'azienda
                if (!in_array($user['ruolo'], ['super_admin', 'utente_speciale'])) {
                    // Ottieni la prima azienda associata all'utente
                    $stmt = db_query("
                        SELECT ua.azienda_id, a.nome 
                        FROM utenti_aziende ua
                        JOIN aziende a ON ua.azienda_id = a.id
                        WHERE ua.utente_id = ? AND a.stato = 'attiva'
                        ORDER BY ua.azienda_id ASC
                        LIMIT 1
                    ", [$user['id']]);
                    
                    $userCompany = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($userCompany) {
                        $_SESSION['azienda_id'] = $userCompany['azienda_id'];
                        $_SESSION['azienda_nome'] = $userCompany['nome'];
                    }
                }
                
                $this->isAuthenticated = true;
                $this->user = $user;
                
                // Auto-associa utenti ad aziende basandosi sul dominio email
                $this->autoAssociateUserToCompany($user);
                
                return ['success' => true];
            }
            
            return ['success' => false, 'message' => 'Username o password non validi'];
        } catch (Exception $e) {
            error_log("Errore login: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante il login'];
        }
    }
    
    public function logout() {
        session_destroy();
        $this->isAuthenticated = false;
        $this->user = null;
    }
    
    public function isSuperAdmin() {
        return $this->user && $this->user['ruolo'] === 'super_admin';
    }
    
    /**
     * Verifica lo stato della sessione
     */
    public function checkSession() {
        return $this->isAuthenticated();
    }
    
    /**
     * Ottiene l'azienda corrente dell'utente
     */
    public function getCurrentCompany() {
        // Se c'è già un'azienda in sessione, usala
        if (isset($_SESSION['azienda_id'])) {
            return $_SESSION['azienda_id'];
        }
        
        // Per utenti normali (non super_admin/utente_speciale), prova a recuperare l'azienda
        if ($this->user && !in_array($this->user['ruolo'], ['super_admin', 'utente_speciale'])) {
            // Ottieni la prima azienda associata
            try {
                $stmt = db_query("
                    SELECT ua.azienda_id 
                    FROM utenti_aziende ua
                    JOIN aziende a ON ua.azienda_id = a.id
                    WHERE ua.utente_id = ? AND a.stato = 'attiva'
                    ORDER BY ua.azienda_id ASC
                    LIMIT 1
                ", [$this->user['id']]);
                
                $userCompany = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($userCompany) {
                    $_SESSION['azienda_id'] = $userCompany['azienda_id'];
                    return $userCompany['azienda_id'];
                }
            } catch (Exception $e) {
                error_log("Errore recupero azienda utente: " . $e->getMessage());
            }
        }
        
        return null;
    }
    
    /**
     * Ottiene il nome dell'azienda corrente
     */
    public function getCurrentCompanyName() {
        $companyId = $this->getCurrentCompany();
        if (!$companyId) {
            return null;
        }
        
        try {
            $stmt = db_query("SELECT nome FROM aziende WHERE id = ?", [$companyId]);
            $company = $stmt->fetch();
            return $company ? $company['nome'] : null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Ottiene il nome completo dell'utente
     */
    public function getFullName() {
        if (!$this->user) {
            return '';
        }
        return trim(($this->user['nome'] ?? '') . ' ' . ($this->user['cognome'] ?? ''));
    }
    
    /**
     * Verifica se l'utente è un utente speciale (ha permessi simili a super admin)
     */
    public function isUtenteSpeciale() {
        return $this->user && $this->user['ruolo'] === 'utente_speciale';
    }
    
    /**
     * Verifica se l'utente ha privilegi elevati (super admin o utente speciale)
     */
    public function hasElevatedPrivileges() {
        return $this->user && in_array($this->user['ruolo'], ['super_admin', 'utente_speciale']);
    }
    
    /**
     * Verifica se l'utente ha un permesso specifico (integrazione con PermissionManager)
     */
    public function hasPermission($permission, $context = []) {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        // Super admin ha sempre tutti i permessi
        if ($this->isSuperAdmin()) {
            return true;
        }
        
        // Se PermissionManager è disponibile, usalo
        if ($this->permissionManager) {
            return $this->permissionManager->canPerformAction($permission, $context, $this->user['id']);
        }
        
        // Fallback: permessi base basati su ruolo
        return $this->hasRolePermission($permission);
    }
    
    /**
     * Verifica permessi base basati su ruolo (fallback)
     */
    private function hasRolePermission($permission) {
        $rolePermissions = [
            'super_admin' => ['*'],
            'utente_speciale' => [
                'document_view', 'document_edit', 'document_upload', 'document_approve',
                'folder_view', 'folder_create', 'folder_edit',
                'iso_configure', 'iso_manage_compliance', 'iso_audit_access',
                'company_view', 'company_switch', 'user_view', 'settings_view'
            ],
            'utente' => [
                'document_view', 'document_edit', 'document_upload',
                'folder_view', 'folder_create',
                'company_view'
            ]
        ];
        
        $userRole = $this->user['ruolo'] ?? 'utente';
        $permissions = $rolePermissions[$userRole] ?? [];
        
        return in_array('*', $permissions) || in_array($permission, $permissions);
    }
    
    /**
     * Verifica se l'utente ha un ruolo specifico o superiore
     */
    public function hasRole($role) {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        $roleHierarchy = [
            'super_admin' => 3,
            'utente_speciale' => 2,
            'utente' => 1
        ];
        
        $userLevel = $roleHierarchy[$this->user['ruolo']] ?? 0;
        $requiredLevel = $roleHierarchy[$role] ?? 0;
        
        return $userLevel >= $requiredLevel;
    }
    
    /**
     * Verifica accesso a documento specifico
     */
    public function canAccessDocument($documentId, $action = 'view') {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        // Se PermissionManager è disponibile, usalo
        if ($this->permissionManager) {
            return $this->permissionManager->checkDocumentAccess($documentId, $action, $this->user['id']);
        }
        
        // Fallback: verifica ownership o ruolo admin
        try {
            $stmt = db_query("SELECT creato_da FROM documenti WHERE id = ?", [$documentId]);
            $document = $stmt->fetch();
            
            if (!$document) {
                return false;
            }
            
            // Owner del documento o admin possono accedere
            return $document['creato_da'] == $this->user['id'] || $this->hasElevatedPrivileges();
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Verifica accesso a cartella specifica
     */
    public function canAccessFolder($folderId, $action = 'view') {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        // Se PermissionManager è disponibile, usalo
        if ($this->permissionManager) {
            return $this->permissionManager->checkFolderAccess($folderId, $action, $this->user['id']);
        }
        
        // Fallback: verifica ownership o ruolo admin
        try {
            $stmt = db_query("SELECT creato_da FROM cartelle WHERE id = ?", [$folderId]);
            $folder = $stmt->fetch();
            
            if (!$folder) {
                return false;
            }
            
            // Owner della cartella o admin possono accedere
            return $folder['creato_da'] == $this->user['id'] || $this->hasElevatedPrivileges();
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Verifica se può eseguire azioni ISO
     */
    public function canPerformISOAction($action) {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        // Solo utente_speciale e super_admin per azioni ISO
        if (!in_array($this->user['ruolo'], ['super_admin', 'utente_speciale'])) {
            return false;
        }
        
        // Se PermissionManager è disponibile, usalo per controlli più granulari
        if ($this->permissionManager) {
            return $this->permissionManager->canPerformAction($action, [], $this->user['id']);
        }
        
        return true;
    }
    
    /**
     * Ottieni tutti i permessi dell'utente corrente
     */
    public function getAllPermissions() {
        if (!$this->isAuthenticated()) {
            return [];
        }
        
        if ($this->permissionManager) {
            $company = $this->getCurrentAzienda();
            return $this->permissionManager->getUserPermissions($this->user['id'], $company['id'] ?? null);
        }
        
        // Fallback: restituisci permessi base del ruolo
        $rolePermissions = [
            'super_admin' => ['*'],
            'utente_speciale' => [
                'document_view', 'document_edit', 'document_upload', 'document_approve',
                'folder_view', 'folder_create', 'folder_edit',
                'iso_configure', 'iso_manage_compliance', 'iso_audit_access'
            ],
            'manager' => [
                'document_view', 'document_edit', 'document_upload',
                'folder_view', 'folder_create'
            ],
            'staff' => [
                'document_view', 'document_upload', 'folder_view'
            ],
            'cliente' => [
                'document_view', 'folder_view'
            ]
        ];
        
        return $rolePermissions[$this->user['ruolo']] ?? [];
    }
    
    /**
     * Verifica se può cambiare azienda
     */
    public function canSwitchToCompany($companyId) {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        // Super admin può cambiare a qualsiasi azienda attiva
        if ($this->isSuperAdmin()) {
            try {
                $stmt = db_query("SELECT id FROM aziende WHERE id = ? AND stato = 'attiva'", [$companyId]);
                return $stmt->fetch() !== false;
            } catch (Exception $e) {
                return false;
            }
        }
        
        // Altri utenti solo alle loro aziende
        try {
            $stmt = db_query("
                SELECT ua.azienda_id 
                FROM utenti_aziende ua
                JOIN aziende a ON ua.azienda_id = a.id
                WHERE ua.utente_id = ? AND ua.azienda_id = ? AND a.stato = 'attiva' AND ua.attivo = 1
            ", [$this->user['id'], $companyId]);
            
            return $stmt->fetch() !== false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Cambia azienda attiva (con controlli permessi)
     */
    public function switchCompany($companyId) {
        if (!$this->canSwitchToCompany($companyId)) {
            throw new Exception("Non hai i permessi per accedere a questa azienda");
        }
        
        $_SESSION['azienda_id'] = $companyId;
        
        // Pulisci cache permessi se PermissionManager è disponibile
        if ($this->permissionManager) {
            $this->permissionManager->clearCache();
        }
        
        // Log attività
        if (class_exists('ActivityLogger')) {
            ActivityLogger::getInstance()->log('azienda_switched', 'azienda', $companyId, [
                'user_id' => $this->user['id'],
                'previous_company' => $_SESSION['previous_azienda_id'] ?? null
            ]);
        }
        
        return true;
    }
    
    /**
     * Ottieni aziende accessibili dall'utente
     */
    public function getAccessibleCompanies() {
        if (!$this->isAuthenticated()) {
            return [];
        }
        
        try {
            if ($this->isSuperAdmin()) {
                // Super admin vede tutte le aziende attive
                $stmt = db_query("
                    SELECT id, nome, codice, stato 
                    FROM aziende 
                    WHERE stato = 'attiva' 
                    ORDER BY nome
                ");
            } else {
                // Altri utenti vedono solo le loro aziende
                $stmt = db_query("
                    SELECT a.id, a.nome, a.codice, a.stato, ua.ruolo_azienda
                    FROM aziende a
                    JOIN utenti_aziende ua ON a.id = ua.azienda_id
                    WHERE ua.utente_id = ? AND a.stato = 'attiva' AND ua.attivo = 1
                    ORDER BY a.nome
                ", [$this->user['id']]);
            }
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Errore getAccessibleCompanies: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Verifica se l'utente può eliminare altri utenti (solo super admin)
     */
    public function canDeleteUsers() {
        return $this->user && $this->user['ruolo'] === 'super_admin';
    }
    
    /**
     * Verifica se l'utente può eliminare log (solo super admin)
     */
    public function canDeleteLogs() {
        return $this->user && $this->user['ruolo'] === 'super_admin';
    }
    
    public function canAccess($resource, $permission = 'read') {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        // Super admin può fare tutto
        if ($this->isSuperAdmin()) {
            return true;
        }
        
        // Logica di permessi semplificata
        switch ($resource) {
            case 'documents':
                return in_array($permission, ['read', 'write']);
            case 'users':
                return false; // Solo super_admin può gestire utenti
            default:
                return true;
        }
    }
    
    public function canCreateDocuments() {
        return $this->canAccess('documents', 'write');
    }
    
    public function canEditDocuments() {
        return $this->canAccess('documents', 'write');
    }
    
    public function canDeleteDocuments() {
        return $this->isSuperAdmin();
    }
    
    /**
     * Verifica se l'utente deve cambiare la password
     */
    public function requiresPasswordChange() {
        if (!$this->isAuthenticated() || !$this->user) {
            return false;
        }
        
        try {
            $stmt = db_query("SELECT primo_accesso, password_scadenza FROM utenti WHERE id = ?", [$this->user['id']]);
            $userData = $stmt->fetch();
            
            if (!$userData) {
                return false;
            }
            
            // Primo accesso richiede cambio password
            if ($userData['primo_accesso']) {
                return true;
            }
            
            // Password scaduta richiede cambio password
            if ($userData['password_scadenza'] && strtotime($userData['password_scadenza']) < time()) {
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log('Errore controllo cambio password: ' . $e->getMessage());
            return false;
        }
    }
    
    public function getCurrentAzienda() {
        try {
            // Se c'è un'azienda in sessione, usa quella
            if (isset($_SESSION['azienda_id'])) {
                $stmt = db_query("SELECT * FROM aziende WHERE id = ? AND stato = 'attiva'", [$_SESSION['azienda_id']]);
                $azienda = $stmt ? $stmt->fetch() : null;
                if ($azienda) {
                    return $azienda;
                }
            }
            
            // Se è super admin o utente speciale, non selezionare automaticamente un'azienda
            if ($this->isSuperAdmin() || $this->isUtenteSpeciale()) {
                return null;
            }
            
            // Per utenti normali, prova a trovare l'azienda associata
            if ($this->user) {
                $stmt = db_query("
                    SELECT a.* 
                    FROM aziende a
                    JOIN utenti_aziende ua ON a.id = ua.azienda_id
                    WHERE ua.utente_id = ? AND a.stato = 'attiva'
                    LIMIT 1
                ", [$this->user['id']]);
                return $stmt ? $stmt->fetch() : null;
            }
            
            return null;
        } catch (Exception $e) {
            error_log("getCurrentAzienda error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Verifica se l'utente può gestire eventi
     */
    public function canManageEvents() {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        // Super admin può sempre gestire eventi
        if ($this->isSuperAdmin()) {
            return true;
        }
        
        // Altri utenti autenticati possono gestire eventi
        // (potresti aggiungere logica più specifica basata sui ruoli)
        return true;
    }
    
    /**
     * Verifica se l'utente può vedere tutti gli eventi
     */
    public function canViewAllEvents() {
        return $this->isSuperAdmin();
    }
    
    /**
     * Verifica se l'utente può invitare altri utenti
     */
    public function canInviteUsers() {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        // Solo super admin può invitare
        return $this->isSuperAdmin() || $this->user['ruolo'] === 'proprietario';
    }
    
    public function getCurrentAziendaId() {
        $azienda = $this->getCurrentAzienda();
        return $azienda ? $azienda['id'] : null;
    }
    
    public function hasRoleInAzienda($role) {
        return $this->user && $this->user['ruolo'] === $role;
    }
    
    public function getUserPermissions() {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        return [
            'puo_vedere_bozze' => true,
            'puo_modificare' => true,
            'puo_eliminare' => $this->isSuperAdmin()
        ];
    }
    
    /**
     * Auto-associa utente ad azienda basandosi sul dominio email
     */
    private function autoAssociateUserToCompany($user) {
        try {
            // Non applicare per super admin o utenti speciali
            if (in_array($user['ruolo'], ['super_admin', 'utente_speciale'])) {
                return;
            }
            
            // Estrai il dominio dall'email
            $email = $user['email'];
            if (!$email || strpos($email, '@') === false) {
                return;
            }
            
            $domain = strtolower(substr($email, strpos($email, '@') + 1));
            
            // Mappatura domini -> aziende
            $domainMapping = [
                'romolohospital.com' => 'Romolo Hospital',
                // Aggiungi altre mappature qui se necessario
            ];
            
            if (!isset($domainMapping[$domain])) {
                return;
            }
            
            $aziendaNome = $domainMapping[$domain];
            
            // Trova l'azienda
            $stmt = db_query("SELECT id FROM aziende WHERE nome = ? AND stato = 'attiva'", [$aziendaNome]);
            $azienda = $stmt->fetch();
            
            if (!$azienda) {
                return;
            }
            
            // Verifica se esiste già un'associazione
            $stmt = db_query("SELECT id FROM utenti_aziende WHERE utente_id = ? AND azienda_id = ?", 
                [$user['id'], $azienda['id']]);
            
            if ($stmt->fetch()) {
                // Associazione già esistente
                return;
            }
            
            // Crea l'associazione
            $ruolo = 'referente'; // Ruolo default
            if (strpos($email, 'responsabile') !== false) {
                $ruolo = 'responsabile_aziendale';
            }
            
            db_insert('utenti_aziende', [
                'utente_id' => $user['id'],
                'azienda_id' => $azienda['id'],
                'ruolo_azienda' => $ruolo,
                'assegnato_da' => 1, // Sistema
                'attivo' => 1
            ]);
            
            // Log attività
            if (class_exists('ActivityLogger')) {
                ActivityLogger::getInstance()->log('sistema', 'auto_associazione', $user['id'], 
                    "Auto-associato utente {$user['email']} a {$aziendaNome}");
            }
            
        } catch (Exception $e) {
            // Log errore ma non bloccare il login
            error_log("Errore auto-associazione utente-azienda: " . $e->getMessage());
        }
    }
}
