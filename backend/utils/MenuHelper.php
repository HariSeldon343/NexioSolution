<?php
/**
 * MenuHelper - Utility per la gestione del menu e navigazione
 * Nexio Platform
 */

class MenuHelper {
    
    /**
     * Determina se una pagina corrisponde all'item di menu corrente
     * @param string $menuUrl URL dell'item di menu
     * @param string $currentPage Pagina corrente
     * @param array $aliases Array di alias/pagine correlate
     * @return bool
     */
    public static function isActivePage($menuUrl, $currentPage, $aliases = []) {
        $basename = basename($menuUrl);
        
        // Confronto diretto
        if ($basename === $currentPage) {
            return true;
        }
        
        // Controlla alias per pagine correlate
        foreach ($aliases as $alias) {
            if (basename($alias) === $currentPage) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Ottiene la configurazione completa del menu con controlli di permesso
     * @param Auth $auth Istanza di autenticazione
     * @return array Array della configurazione menu
     */
    public static function getMenuConfiguration($auth) {
        if (!$auth->isAuthenticated()) {
            return [];
        }
        
        $user = $auth->getUser();
        $userRole = $user['ruolo'] ?? '';
        $isSuperAdmin = $auth->isSuperAdmin();
        
        // Include ModulesHelper se necessario
        if (!class_exists('ModulesHelper')) {
            require_once dirname(__DIR__) . '/utils/ModulesHelper.php';
        }
        
        return [
            // Dashboard - sempre visibile
            'dashboard' => [
                'icon' => 'fas fa-home',
                'title' => 'Dashboard',
                'url' => 'dashboard.php',
                'visible' => true,
                'section' => 'main'
            ],
            
            // AREA OPERATIVA
            'filesystem' => [
                'icon' => 'fas fa-folder-open',
                'title' => 'File Manager',
                'url' => 'filesystem.php',
                'aliases' => ['documenti.php'],
                'visible' => true,
                'section' => 'operativa'
            ],
            
            'calendario' => [
                'icon' => 'fas fa-calendar-alt',
                'title' => 'Calendario',
                'url' => 'calendario-eventi.php',
                'aliases' => ['calendario.php', 'lista-eventi.php', 'eventi.php'],
                'visible' => $isSuperAdmin || ModulesHelper::isModuleEnabled('calendario'),
                'section' => 'operativa'
            ],
            
            'task' => [
                'icon' => 'fas fa-tasks',
                'title' => 'Task',
                'url' => 'task-progress.php',
                'visible' => $isSuperAdmin || $userRole === 'utente_speciale',
                'section' => 'operativa'
            ],
            
            'tickets' => [
                'icon' => 'fas fa-headset',
                'title' => 'Ticket',
                'url' => 'tickets.php',
                'visible' => $isSuperAdmin || ModulesHelper::isModuleEnabled('tickets'),
                'section' => 'operativa'
            ],
            
            'conformita' => [
                'icon' => 'fas fa-clipboard-list',
                'title' => 'Conformità',
                'url' => 'conformita-normativa.php',
                'visible' => $isSuperAdmin || ModulesHelper::isModuleEnabled('conformita_normativa'),
                'section' => 'operativa'
            ],
            
            'ai' => [
                'icon' => 'fas fa-robot',
                'title' => 'AI',
                'url' => 'nexio-ai.php',
                'visible' => $isSuperAdmin || ModulesHelper::isModuleEnabled('nexio_ai'),
                'section' => 'operativa'
            ],
            
            // GESTIONE
            'aziende' => [
                'icon' => 'fas fa-building',
                'title' => 'Aziende',
                'url' => 'aziende.php',
                'visible' => $auth->hasElevatedPrivileges(),
                'section' => 'gestione'
            ],
            
            // AMMINISTRAZIONE
            'utenti' => [
                'icon' => 'fas fa-users',
                'title' => 'Utenti',
                'url' => 'gestione-utenti.php',
                'aliases' => ['utenti.php', 'modifica-utente.php'],
                'visible' => $auth->hasElevatedPrivileges(),
                'section' => 'amministrazione'
            ],
            
            'audit' => [
                'icon' => 'fas fa-history',
                'title' => 'Audit Log',
                'url' => 'log-attivita.php',
                'visible' => $auth->hasElevatedPrivileges(),
                'section' => 'amministrazione'
            ],
            
            'configurazioni' => [
                'icon' => 'fas fa-cog',
                'title' => 'Configurazioni',
                'url' => 'configurazione-email.php',
                'aliases' => ['configurazione-smtp.php', 'configurazione-email-nexio.php'],
                'visible' => $auth->hasElevatedPrivileges(),
                'section' => 'amministrazione'
            ],
            
            // ACCOUNT
            'profilo' => [
                'icon' => 'fas fa-user-circle',
                'title' => 'Il Mio Profilo',
                'url' => 'profilo.php',
                'aliases' => ['cambio-password.php'],
                'visible' => true,
                'section' => 'account'
            ],
            
            'logout' => [
                'icon' => 'fas fa-sign-out-alt',
                'title' => 'Esci',
                'url' => 'logout.php',
                'visible' => true,
                'section' => 'account',
                'color' => '#fc8181',
                'onclick' => "return confirm('Sei sicuro di voler uscire?');"
            ]
        ];
    }
    
    /**
     * Raggruppa i menu item per sezione
     * @param array $menuConfig Configurazione menu
     * @return array Menu raggruppati per sezione
     */
    public static function groupMenuBySection($menuConfig) {
        $grouped = [
            'main' => [],
            'operativa' => [],
            'gestione' => [],
            'amministrazione' => [],
            'account' => []
        ];
        
        foreach ($menuConfig as $key => $item) {
            if ($item['visible']) {
                $section = $item['section'] ?? 'main';
                $grouped[$section][$key] = $item;
            }
        }
        
        return $grouped;
    }
    
    /**
     * Titoli delle sezioni menu
     */
    public static function getSectionTitles() {
        return [
            'main' => '',
            'operativa' => 'AREA OPERATIVA',
            'gestione' => 'GESTIONE',
            'amministrazione' => 'AMMINISTRAZIONE',
            'account' => 'ACCOUNT'
        ];
    }
}