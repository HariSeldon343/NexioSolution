-- Creazione tabella tasks per la gestione dei task aziendali
USE nexiosol;

CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titolo VARCHAR(255) NOT NULL,
    descrizione TEXT,
    azienda_id INT,
    assegnato_a INT,
    creato_da INT NOT NULL,
    priorita ENUM('bassa', 'media', 'alta') DEFAULT 'media',
    stato ENUM('nuovo', 'in_corso', 'in_attesa', 'completato', 'annullato') DEFAULT 'nuovo',
    data_scadenza DATE,
    data_completamento DATETIME,
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE SET NULL,
    FOREIGN KEY (assegnato_a) REFERENCES utenti(id) ON DELETE SET NULL,
    FOREIGN KEY (creato_da) REFERENCES utenti(id) ON DELETE CASCADE,
    
    INDEX idx_azienda (azienda_id),
    INDEX idx_assegnato (assegnato_a),
    INDEX idx_stato (stato),
    INDEX idx_priorita (priorita),
    INDEX idx_scadenza (data_scadenza)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserimento di alcuni task di esempio
INSERT INTO tasks (titolo, descrizione, azienda_id, assegnato_a, creato_da, priorita, stato, data_scadenza) VALUES
('Completare documentazione progetto', 'Preparare la documentazione completa per il nuovo progetto aziendale', 1, 1, 1, 'alta', 'in_corso', DATE_ADD(NOW(), INTERVAL 7 DAY)),
('Revisione contratti fornitori', 'Rivedere e aggiornare i contratti con i fornitori principali', 1, 2, 1, 'media', 'nuovo', DATE_ADD(NOW(), INTERVAL 14 DAY)),
('Formazione nuovo personale', 'Organizzare sessioni di formazione per i nuovi dipendenti', 1, 3, 1, 'bassa', 'nuovo', DATE_ADD(NOW(), INTERVAL 30 DAY));