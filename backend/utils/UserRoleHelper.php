<?php
/**
 * UserRoleHelper - Semplificazione logica utenti Nexio
 * 
 * Implementa la logica semplificata dei 3 ruoli:
 * 1. super_admin - Vede tutto, non associato ad aziende
 * 2. utente_speciale - Accesso avanzato, non associato ad aziende 
 * 3. utente - Associato ad azienda, vede solo suoi file
 */

class UserRoleHelper {
    
    /**
     * Verifica se l'utente è un super admin
     */
    public static function isSuperAdmin($user = null) {
        $auth = Auth::getInstance();
        if (!$user) {
            $user = $auth->getUser();
        }
        return $user && $user['ruolo'] === 'super_admin';
    }
    
    /**
     * Verifica se l'utente è un utente speciale
     */
    public static function isUtenteSpeciale($user = null) {
        $auth = Auth::getInstance();
        if (!$user) {
            $user = $auth->getUser();
        }
        return $user && $user['ruolo'] === 'utente_speciale';
    }
    
    /**
     * Verifica se l'utente è un utente normale
     */
    public static function isUtenteNormale($user = null) {
        $auth = Auth::getInstance();
        if (!$user) {
            $user = $auth->getUser();
        }
        return $user && $user['ruolo'] === 'utente';
    }
    
    /**
     * Verifica se l'utente ha accesso globale (non associato ad aziende)
     */
    public static function hasGlobalAccess($user = null) {
        return self::isSuperAdmin($user) || self::isUtenteSpeciale($user);
    }
    
    /**
     * Verifica se l'utente deve essere associato ad un'azienda
     */
    public static function requiresCompanyAssociation($user = null) {
        return self::isUtenteNormale($user);
    }
    
    /**
     * Ottiene l'azienda ID per l'utente corrente
     * Restituisce 0 per utenti con accesso globale, azienda_id per utenti normali
     */
    public static function getUserCompanyId($user = null) {
        $auth = Auth::getInstance();
        if (!$user) {
            $user = $auth->getUser();
        }
        
        // Super admin e utenti speciali: accesso globale (ID speciale 0)
        if (self::hasGlobalAccess($user)) {
            return 0;
        }
        
        // Utenti normali: devono avere un'azienda associata
        $currentAzienda = $auth->getCurrentAzienda();
        return $currentAzienda['azienda_id'] ?? $currentAzienda['id'] ?? null;
    }
    
    /**
     * Verifica se l'utente può accedere ai file di una specifica azienda
     */
    public static function canAccessCompanyFiles($companyId, $user = null) {
        // Super admin e utenti speciali possono accedere a tutto
        if (self::hasGlobalAccess($user)) {
            return true;
        }
        
        // Utenti normali solo alla loro azienda
        $userCompanyId = self::getUserCompanyId($user);
        return $userCompanyId && $userCompanyId == $companyId;
    }
    
    /**
     * Ottiene i permessi del file system per l'utente
     */
    public static function getFileSystemPermissions($user = null) {
        if (self::isSuperAdmin($user)) {
            return [
                'can_upload_global' => true,
                'can_view_all' => true,
                'can_delete_all' => true,
                'can_manage_permissions' => true,
                'can_switch_company_context' => true
            ];
        }
        
        if (self::isUtenteSpeciale($user)) {
            return [
                'can_upload_global' => true,  // Correzione: utenti speciali possono caricare globalmente
                'can_view_all' => true,
                'can_delete_all' => false,
                'can_manage_permissions' => false,
                'can_switch_company_context' => false
            ];
        }
        
        // Utente normale
        return [
            'can_upload_global' => false,
            'can_view_all' => false,
            'can_delete_all' => false,
            'can_manage_permissions' => false,
            'can_switch_company_context' => false
        ];
    }
    
    /**
     * Verifica se l'utente può caricare file senza specifica azienda
     */
    public static function canUploadWithoutCompany($user = null) {
        return self::isSuperAdmin($user) || self::isUtenteSpeciale($user);
    }
    
    /**
     * Ottiene il filtro SQL per i documenti basato sul ruolo utente
     */
    public static function getDocumentSqlFilter($tableAlias = 'd', $user = null) {
        $auth = Auth::getInstance();
        if (!$user) {
            $user = $auth->getUser();
        }
        
        // Super admin vede tutto
        if (self::isSuperAdmin($user)) {
            return '';
        }
        
        // Utente speciale vede tutto
        if (self::isUtenteSpeciale($user)) {
            return '';
        }
        
        // Utente normale solo della sua azienda
        $companyId = self::getUserCompanyId($user);
        if ($companyId) {
            return " AND {$tableAlias}.azienda_id = {$companyId}";
        }
        
        // Se non ha azienda, non vede nulla
        return " AND 1 = 0";
    }
    
    /**
     * Determina il contesto di upload per l'utente
     */
    public static function getUploadContext($user = null, $requestedCompanyId = null) {
        if (self::isSuperAdmin($user)) {
            // Super admin può scegliere: se specifica azienda la usa, altrimenti globale
            return $requestedCompanyId ?? 0;
        }
        
        if (self::isUtenteSpeciale($user)) {
            // Utente speciale non può scegliere, sempre globale
            return 0;
        }
        
        // Utente normale sempre nella sua azienda
        $companyId = self::getUserCompanyId($user);
        if (!$companyId) {
            throw new Exception('Utente non associato ad alcuna azienda');
        }
        
        return $companyId;
    }
    
    /**
     * Ottiene la descrizione del ruolo utente
     */
    public static function getRoleDescription($user = null) {
        if (self::isSuperAdmin($user)) {
            return 'Super Amministratore - Accesso Globale Sistema';
        }
        
        if (self::isUtenteSpeciale($user)) {
            return 'Utente Speciale - Accesso Avanzato';
        }
        
        if (self::isUtenteNormale($user)) {
            $auth = Auth::getInstance();
            $currentAzienda = $auth->getCurrentAzienda();
            $companyName = $currentAzienda['nome'] ?? 'Azienda';
            return "Utente - {$companyName}";
        }
        
        return 'Ruolo non riconosciuto';
    }
    
    /**
     * Verifica se l'utente può modificare le impostazioni globali
     */
    public static function canModifyGlobalSettings($user = null) {
        return self::isSuperAdmin($user);
    }
    
    /**
     * Verifica se l'utente può gestire altri utenti
     */
    public static function canManageUsers($user = null) {
        return self::isSuperAdmin($user);
    }
    
    /**
     * Verifica se l'utente può vedere i log di sistema
     */
    public static function canViewSystemLogs($user = null) {
        return self::hasGlobalAccess($user);
    }
    
    /**
     * Ottiene le aziende visibili all'utente
     */
    public static function getVisibleCompanies($user = null) {
        $auth = Auth::getInstance();
        if (!$user) {
            $user = $auth->getUser();
        }
        
        if (self::isSuperAdmin($user)) {
            // Super admin vede tutte le aziende
            $stmt = db_query("SELECT * FROM aziende WHERE stato = 'attiva' ORDER BY nome");
            return $stmt->fetchAll();
        }
        
        if (self::isUtenteSpeciale($user)) {
            // Utente speciale vede tutte le aziende in sola lettura
            $stmt = db_query("SELECT * FROM aziende WHERE stato = 'attiva' ORDER BY nome");
            return $stmt->fetchAll();
        }
        
        // Utente normale vede solo la sua azienda
        $companyId = self::getUserCompanyId($user);
        if ($companyId) {
            $stmt = db_query("SELECT * FROM aziende WHERE id = ? AND stato = 'attiva'", [$companyId]);
            return $stmt->fetchAll();
        }
        
        return [];
    }
    
    /**
     * Verifica la conformità del sistema di ruoli
     */
    public static function validateRoleSystem() {
        $validRoles = ['super_admin', 'utente_speciale', 'utente'];
        
        // Controlla se ci sono ruoli non standard nel database
        $stmt = db_query("SELECT DISTINCT ruolo FROM utenti WHERE ruolo IS NOT NULL");
        $existingRoles = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $invalidRoles = array_diff($existingRoles, $validRoles);
        
        if (!empty($invalidRoles)) {
            error_log("ATTENZIONE: Trovati ruoli non standard nel sistema: " . implode(', ', $invalidRoles));
            return false;
        }
        
        return true;
    }
}
?>