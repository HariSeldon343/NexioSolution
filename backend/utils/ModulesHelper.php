<?php
/**
 * Helper per la gestione dei moduli azienda
 */

require_once __DIR__ . '/../config/config.php';

class ModulesHelper {
    
    /**
     * Verifica se un modulo è abilitato per l'azienda corrente
     * 
     * @param string $moduloCodice Codice del modulo da verificare
     * @param int|null $aziendaId ID dell'azienda (se null usa quella corrente)
     * @return bool
     */
    public static function isModuleEnabled($moduloCodice, $aziendaId = null) {
        $auth = \Auth::getInstance();
        
        // Se è super admin, ha accesso a tutti i moduli
        if ($auth->isSuperAdmin()) {
            return true;
        }
        
        // Se non è specificata l'azienda, usa quella corrente
        if (!$aziendaId) {
            $aziendaId = $auth->getCurrentCompany();
        }
        
        // Se non c'è un'azienda selezionata, nega l'accesso
        if (!$aziendaId) {
            return false;
        }
        
        // Verifica nel database
        $stmt = db_query("
            SELECT COUNT(*) as count
            FROM moduli_azienda ma
            JOIN moduli_sistema ms ON ma.modulo_id = ms.id
            WHERE ma.azienda_id = ? 
            AND ms.codice = ? 
            AND ma.abilitato = 1
            AND ms.attivo = 1
        ", [$aziendaId, $moduloCodice]);
        
        $result = $stmt->fetch();
        return $result && $result['count'] > 0;
    }
    
    /**
     * Ottiene tutti i moduli abilitati per l'azienda corrente
     * 
     * @param int|null $aziendaId ID dell'azienda (se null usa quella corrente)
     * @return array
     */
    public static function getEnabledModules($aziendaId = null) {
        $auth = \Auth::getInstance();
        
        // Se non è specificata l'azienda, usa quella corrente
        if (!$aziendaId) {
            $aziendaId = $auth->getCurrentCompany();
        }
        
        // Se è super admin e non ha un'azienda selezionata, mostra tutti i moduli
        if ($auth->isSuperAdmin() && !$aziendaId) {
            $stmt = db_query("
                SELECT * FROM moduli_sistema 
                WHERE attivo = 1 
                ORDER BY ordine, nome
            ");
            return $stmt->fetchAll();
        }
        
        // Se non c'è un'azienda selezionata, ritorna array vuoto
        if (!$aziendaId) {
            return [];
        }
        
        // Carica i moduli abilitati per l'azienda
        $sql = "
            SELECT ms.* 
            FROM moduli_sistema ms
            JOIN moduli_azienda ma ON ms.id = ma.modulo_id
            WHERE ma.azienda_id = ? 
            AND ma.abilitato = 1
            AND ms.attivo = 1
            ORDER BY ms.ordine, ms.nome
        ";
        
        // Se è super admin, mostra tutti i moduli indipendentemente dalle autorizzazioni
        if ($auth->isSuperAdmin()) {
            $sql = "
                SELECT * FROM moduli_sistema 
                WHERE attivo = 1 
                ORDER BY ordine, nome
            ";
            $stmt = db_query($sql);
        } else {
            $stmt = db_query($sql, [$aziendaId]);
        }
        
        return $stmt->fetchAll();
    }
    
    /**
     * Controlla l'accesso a un modulo e reindirizza se non autorizzato
     * 
     * @param string $moduloCodice Codice del modulo da verificare
     * @param string $redirectUrl URL di redirect se non autorizzato
     * @return void
     */
    public static function requireModule($moduloCodice, $redirectUrl = null) {
        if (!self::isModuleEnabled($moduloCodice)) {
            $_SESSION['error'] = "Non hai accesso a questo modulo";
            redirect($redirectUrl ?: APP_PATH . '/dashboard.php');
        }
    }
    
    /**
     * Abilita un modulo per un'azienda
     * 
     * @param int $aziendaId ID dell'azienda
     * @param int $moduloId ID del modulo
     * @param int|null $abilitatoDa ID dell'utente che abilita
     * @return bool
     */
    public static function enableModule($aziendaId, $moduloId, $abilitatoDa = null) {
        try {
            $sql = "
                INSERT INTO moduli_azienda (azienda_id, modulo_id, abilitato, abilitato_da) 
                VALUES (?, ?, 1, ?)
                ON DUPLICATE KEY UPDATE 
                    abilitato = 1, 
                    data_abilitazione = CURRENT_TIMESTAMP,
                    abilitato_da = VALUES(abilitato_da)
            ";
            
            db_query($sql, [$aziendaId, $moduloId, $abilitatoDa]);
            return true;
        } catch (\Exception $e) {
            error_log("Errore abilitazione modulo: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Disabilita un modulo per un'azienda
     * 
     * @param int $aziendaId ID dell'azienda
     * @param int $moduloId ID del modulo
     * @return bool
     */
    public static function disableModule($aziendaId, $moduloId) {
        try {
            $sql = "
                UPDATE moduli_azienda 
                SET abilitato = 0 
                WHERE azienda_id = ? AND modulo_id = ?
            ";
            
            db_query($sql, [$aziendaId, $moduloId]);
            return true;
        } catch (\Exception $e) {
            error_log("Errore disabilitazione modulo: " . $e->getMessage());
            return false;
        }
    }
}
?>