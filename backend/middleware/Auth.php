<?php
/**
 * Classe Auth - Gestione Autenticazione
 */

class Auth {
    private static $instance = null;
    private $user = null;
    private $isAuthenticated = false;
    
    private function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $this->checkAuthentication();
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
                'ruolo' => $_SESSION['ruolo'] ?? 'cliente',
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
    
    public function requireAuth() {
        if (!$this->isAuthenticated()) {
            // Se siamo in una chiamata API, restituisci errore JSON
            if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false || 
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
                $_SESSION['azienda_id'] = $user['azienda_id'] ?? null;
                
                $this->isAuthenticated = true;
                $this->user = $user;
                
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
        return $this->user && ($this->user['ruolo'] === 'super_admin' || $this->user['ruolo'] === 'admin');
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
                return $this->user['ruolo'] === 'admin';
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
            $stmt = db_query("SELECT * FROM aziende WHERE stato = 'attiva' LIMIT 1");
            return $stmt ? $stmt->fetch() : null;
        } catch (Exception $e) {
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
        
        // Super admin e admin possono invitare
        return $this->isSuperAdmin() || in_array($this->user['ruolo'], ['admin', 'proprietario']);
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
}
