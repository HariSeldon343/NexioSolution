<?php
/**
 * Funzioni per la gestione delle aziende
 */

function deleteAzienda($aziendaId) {
    // Input validation
    if (empty($aziendaId) || !is_numeric($aziendaId)) {
        error_log("deleteAzienda: Invalid ID provided - " . var_export($aziendaId, true));
        return ["success" => false, "message" => "ID azienda non valido"];
    }
    
    try {
        // Begin transaction for data consistency
        db_connection()->beginTransaction();
        
        // Verify company exists and get current state
        $stmt = db_query("SELECT id, nome, stato FROM aziende WHERE id = ?", [$aziendaId]);
        $azienda = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$azienda) {
            db_connection()->rollback();
            return ["success" => false, "message" => "Azienda non trovata"];
        }
        
        // Check if already deleted
        if ($azienda['stato'] === 'cancellata') {
            db_connection()->rollback();
            return ["success" => false, "message" => "Azienda già eliminata"];
        }
        
        // Check if data_cancellazione column exists first
        $column_exists = false;
        try {
            $check_stmt = db_query("SHOW COLUMNS FROM aziende LIKE 'data_cancellazione'");
            $column_exists = $check_stmt->rowCount() > 0;
        } catch (Exception $e) {
            // Column check failed, assume it doesn't exist
            $column_exists = false;
        }
        
        // Soft delete - mark as deleted instead of actual deletion
        if ($column_exists) {
            $update_sql = "UPDATE aziende SET stato = 'cancellata', data_cancellazione = NOW() WHERE id = ? AND stato != 'cancellata'";
        } else {
            $update_sql = "UPDATE aziende SET stato = 'cancellata' WHERE id = ? AND stato != 'cancellata'";
        }
        
        $stmt = db_query($update_sql, [$aziendaId]);
        
        if ($stmt && $stmt->rowCount() > 0) {
            // Deactivate related users to prevent access issues
            $stmt_users = db_query("UPDATE utenti_aziende SET attivo = 0 WHERE azienda_id = ?", [$aziendaId]);
            
            // Clear any session data that might reference this company
            if (isset($_SESSION['azienda_id']) && $_SESSION['azienda_id'] == $aziendaId) {
                unset($_SESSION['azienda_id']);
                unset($_SESSION['azienda_corrente']);
                unset($_SESSION['current_azienda']);
                // Force user to reselect a company if they were working with the deleted one
                if (isset($_SESSION['user'])) {
                    $_SESSION['force_company_selection'] = true;
                }
            }
            
            // Commit transaction
            db_connection()->commit();
            
            // Log activity
            error_log("Company successfully deleted: ID={$aziendaId}, Name={$azienda['nome']}");
            return ["success" => true, "message" => "Azienda eliminata con successo"];
        } else {
            db_connection()->rollback();
            error_log("Error deleting company: ID={$aziendaId}, no rows affected");
            return ["success" => false, "message" => "Errore durante l'eliminazione: nessuna modifica effettuata"];
        }
        
    } catch (PDOException $e) {
        if (db_connection()->inTransaction()) {
            db_connection()->rollback();
        }
        error_log("PDO Error deleteAzienda: " . $e->getMessage() . " - ID: " . $aziendaId);
        
        // Handle specific database errors
        if ($e->getCode() === '23000') {
            return ["success" => false, "message" => "Impossibile eliminare: l'azienda è collegata ad altri dati"];
        }
        
        return ["success" => false, "message" => "Errore database durante l'eliminazione"];
    } catch (Exception $e) {
        if (db_connection()->inTransaction()) {
            db_connection()->rollback();
        }
        error_log("Generic Error deleteAzienda: " . $e->getMessage() . " - ID: " . $aziendaId);
        return ["success" => false, "message" => "Errore interno del server"];
    }
}

function createAzienda($data) {
    try {
        $id = db_insert("aziende", $data);
        return ["success" => true, "id" => $id, "message" => "Azienda creata con successo"];
    } catch (Exception $e) {
        error_log("Errore createAzienda: " . $e->getMessage());
        return ["success" => false, "message" => "Errore durante la creazione"];
    }
}

function updateAzienda($id, $data) {
    try {
        $result = db_update("aziende", $data, "id = ?", [$id]);
        return ["success" => true, "message" => "Azienda aggiornata con successo"];
    } catch (Exception $e) {
        error_log("Errore updateAzienda: " . $e->getMessage());
        return ["success" => false, "message" => "Errore durante l'aggiornamento"];
    }
}

function getAziende($onlyActive = true) {
    try {
        $sql = "SELECT DISTINCT * FROM aziende";
        if ($onlyActive) {
            $sql .= " WHERE stato = 'attiva'";
        }
        $sql .= " ORDER BY nome";
        
        $stmt = db_query($sql);
        $companies_raw = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        
        // Remove any potential duplicates by ID to ensure data consistency
        $companies = [];
        $seen_ids = [];
        foreach ($companies_raw as $company) {
            if (!in_array($company['id'], $seen_ids)) {
                $seen_ids[] = $company['id'];
                $companies[] = $company;
            }
        }
        
        return $companies;
        
    } catch (Exception $e) {
        error_log("Error getAziende: " . $e->getMessage());
        return [];
    }
}
?>