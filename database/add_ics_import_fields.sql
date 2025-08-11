-- Add fields for ICS import tracking
ALTER TABLE eventi 
ADD COLUMN IF NOT EXISTS uid_import VARCHAR(255) DEFAULT NULL COMMENT 'UID from imported ICS file',
ADD COLUMN IF NOT EXISTS tutto_il_giorno TINYINT(1) DEFAULT 0 COMMENT 'All-day event flag',
ADD INDEX idx_uid_import (uid_import);

-- Add evento_partecipanti table if not exists
CREATE TABLE IF NOT EXISTS evento_partecipanti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evento_id INT NOT NULL,
    utente_id INT NOT NULL,
    stato ENUM('invitato', 'confermato', 'rifiutato', 'forse') DEFAULT 'invitato',
    notificato TINYINT(1) DEFAULT 0,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (evento_id) REFERENCES eventi(id) ON DELETE CASCADE,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    UNIQUE KEY unique_evento_utente (evento_id, utente_id),
    INDEX idx_evento (evento_id),
    INDEX idx_utente (utente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;