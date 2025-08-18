-- Aggiunge colonne mancanti per tracking editor OnlyOffice
-- Data: 2025-08-18

-- Aggiungi colonne per tracking real-time degli editor
ALTER TABLE documenti 
ADD COLUMN IF NOT EXISTS is_editing TINYINT(1) DEFAULT 0 COMMENT 'Indica se il documento è in fase di editing',
ADD COLUMN IF NOT EXISTS editing_users TEXT COMMENT 'JSON array degli utenti che stanno editando',
ADD COLUMN IF NOT EXISTS editing_started_at TIMESTAMP NULL COMMENT 'Timestamp inizio editing',
ADD COLUMN IF NOT EXISTS current_version INT DEFAULT 1 COMMENT 'Versione corrente del documento',
ADD COLUMN IF NOT EXISTS total_versions INT DEFAULT 0 COMMENT 'Numero totale di versioni',
ADD INDEX idx_is_editing (is_editing),
ADD INDEX idx_editing_started (editing_started_at);

-- Crea tabella per editor attivi (collaborazione real-time)
CREATE TABLE IF NOT EXISTS document_active_editors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    user_id INT NOT NULL,
    user_name VARCHAR(255),
    user_color VARCHAR(7) DEFAULT '#000000',
    connection_id VARCHAR(255),
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    UNIQUE KEY unique_editor (document_id, user_id),
    INDEX idx_document_editors (document_id),
    INDEX idx_user_activity (user_id, is_active),
    INDEX idx_last_activity (last_activity),
    FOREIGN KEY (document_id) REFERENCES documenti(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES utenti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crea tabella per azioni collaborative
CREATE TABLE IF NOT EXISTS document_collaborative_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    user_id INT NOT NULL,
    action_type ENUM('open', 'edit', 'save', 'close', 'comment', 'review') NOT NULL,
    action_details TEXT,
    performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_document_actions (document_id, performed_at),
    INDEX idx_user_actions (user_id, performed_at),
    FOREIGN KEY (document_id) REFERENCES documenti(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES utenti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aggiungi trigger per cleanup automatico editor inattivi
DELIMITER $$
CREATE TRIGGER cleanup_inactive_editors
BEFORE UPDATE ON document_active_editors
FOR EACH ROW
BEGIN
    -- Se l'editor è stato inattivo per più di 5 minuti, segnalo come non attivo
    IF NEW.last_activity < DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN
        SET NEW.is_active = 0;
    END IF;
END$$
DELIMITER ;

-- Aggiungi stored procedure per cleanup periodico
DELIMITER $$
CREATE PROCEDURE IF NOT EXISTS cleanup_old_editors()
BEGIN
    -- Rimuovi editor inattivi da più di 30 minuti
    DELETE FROM document_active_editors 
    WHERE last_activity < DATE_SUB(NOW(), INTERVAL 30 MINUTE);
    
    -- Aggiorna stato editing nei documenti
    UPDATE documenti d
    LEFT JOIN (
        SELECT document_id, COUNT(*) as active_count
        FROM document_active_editors
        WHERE is_active = 1
        GROUP BY document_id
    ) ae ON d.id = ae.document_id
    SET d.is_editing = COALESCE(ae.active_count, 0) > 0,
        d.editing_users = CASE 
            WHEN ae.active_count > 0 THEN (
                SELECT JSON_ARRAYAGG(
                    JSON_OBJECT('id', user_id, 'name', user_name, 'color', user_color)
                )
                FROM document_active_editors
                WHERE document_id = d.id AND is_active = 1
            )
            ELSE NULL
        END;
END$$
DELIMITER ;

-- Crea evento per cleanup automatico ogni 5 minuti
CREATE EVENT IF NOT EXISTS cleanup_editor_sessions
ON SCHEDULE EVERY 5 MINUTE
DO CALL cleanup_old_editors();

COMMIT;