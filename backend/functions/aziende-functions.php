<?php
/**
 * Funzioni per la gestione delle aziende
 */

function deleteAzienda($aziendaId) {
    try {
        // Verifica se l'azienda esiste
        $stmt = db_query("SELECT * FROM aziende WHERE id = ?", [$aziendaId]);
        if (!$stmt || !$stmt->fetch()) {
            return ["success" => false, "message" => "Azienda non trovata"];
        }
        
        // Soft delete - marca come eliminata invece di cancellare
        $stmt = db_query("UPDATE aziende SET stato = 'eliminata' WHERE id = ?", [$aziendaId]);
        
        if ($stmt) {
            return ["success" => true, "message" => "Azienda eliminata con successo"];
        } else {
            return ["success" => false, "message" => "Errore durante l'eliminazione"];
        }
    } catch (Exception $e) {
        error_log("Errore deleteAzienda: " . $e->getMessage());
        return ["success" => false, "message" => "Errore interno"];
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
        $result = db_update("aziende", $data, "id = :id", [":id" => $id]);
        return ["success" => true, "message" => "Azienda aggiornata con successo"];
    } catch (Exception $e) {
        error_log("Errore updateAzienda: " . $e->getMessage());
        return ["success" => false, "message" => "Errore durante l'aggiornamento"];
    }
}

function getAziende($onlyActive = true) {
    try {
        $sql = "SELECT * FROM aziende";
        if ($onlyActive) {
            $sql .= " WHERE stato = 'attiva'";
        }
        $sql .= " ORDER BY nome";
        
        $stmt = db_query($sql);
        return $stmt ? $stmt->fetchAll() : [];
    } catch (Exception $e) {
        error_log("Errore getAziende: " . $e->getMessage());
        return [];
    }
}
?>