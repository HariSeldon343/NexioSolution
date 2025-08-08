-- Tabella per assegnazioni multiple dei task
CREATE TABLE IF NOT EXISTS task_assegnazioni (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    utente_id INT NOT NULL,
    percentuale_completamento DECIMAL(5,2) DEFAULT 0,
    ultimo_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES task_calendario(id) ON DELETE CASCADE,
    FOREIGN KEY (utente_id) REFERENCES utenti(id),
    UNIQUE KEY unique_task_user (task_id, utente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella per i giorni specifici del task (non consecutivi)
CREATE TABLE IF NOT EXISTS task_giorni (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    data_giorno DATE NOT NULL,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES task_calendario(id) ON DELETE CASCADE,
    UNIQUE KEY unique_task_day (task_id, data_giorno)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella per lo storico degli aggiornamenti di progresso
CREATE TABLE IF NOT EXISTS task_progressi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    utente_id INT NOT NULL,
    percentuale_precedente DECIMAL(5,2),
    percentuale_nuova DECIMAL(5,2),
    note TEXT,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES task_calendario(id) ON DELETE CASCADE,
    FOREIGN KEY (utente_id) REFERENCES utenti(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aggiungi colonna per tracciare se il task usa giorni specifici
ALTER TABLE task_calendario 
ADD COLUMN usa_giorni_specifici BOOLEAN DEFAULT FALSE AFTER data_fine,
ADD COLUMN percentuale_completamento_totale DECIMAL(5,2) DEFAULT 0 AFTER stato;

-- Migra i task esistenti alla nuova struttura
INSERT INTO task_assegnazioni (task_id, utente_id, percentuale_completamento)
SELECT id, utente_assegnato_id, 
    CASE 
        WHEN stato = 'completato' THEN 100
        WHEN stato = 'in_corso' THEN 50
        ELSE 0
    END
FROM task_calendario
WHERE utente_assegnato_id IS NOT NULL
ON DUPLICATE KEY UPDATE percentuale_completamento = VALUES(percentuale_completamento);