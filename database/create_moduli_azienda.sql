-- Creazione tabella per gestire i moduli abilitati per azienda
USE NexioSol;

-- Tabella per definire i moduli disponibili nel sistema
CREATE TABLE IF NOT EXISTS moduli_sistema (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codice VARCHAR(50) NOT NULL UNIQUE,
    nome VARCHAR(100) NOT NULL,
    descrizione TEXT,
    icona VARCHAR(50) DEFAULT 'fas fa-cube',
    url_pagina VARCHAR(255),
    ordine INT DEFAULT 0,
    attivo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella di associazione moduli-aziende
CREATE TABLE IF NOT EXISTS moduli_azienda (
    id INT AUTO_INCREMENT PRIMARY KEY,
    azienda_id INT NOT NULL,
    modulo_id INT NOT NULL,
    abilitato BOOLEAN DEFAULT TRUE,
    data_abilitazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    abilitato_da INT,
    note TEXT,
    UNIQUE KEY unique_azienda_modulo (azienda_id, modulo_id),
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (modulo_id) REFERENCES moduli_sistema(id) ON DELETE CASCADE,
    FOREIGN KEY (abilitato_da) REFERENCES utenti(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserimento moduli di base
INSERT INTO moduli_sistema (codice, nome, descrizione, icona, url_pagina, ordine) VALUES
('calendario', 'Calendario Eventi', 'Gestione eventi e calendario aziendale', 'fas fa-calendar-alt', 'calendario-eventi.php', 1),
('tickets', 'Sistema Ticketing', 'Sistema di supporto e gestione ticket', 'fas fa-headset', 'tickets.php', 2),
('gestione_requisiti', 'Gestione Requisiti', 'Sistema per la gestione dei requisiti aziendali', 'fas fa-clipboard-list', 'gestione-requisiti.php', 3),
('documenti', 'Gestione Documenti', 'Sistema di gestione documentale', 'fas fa-folder', 'file-manager.php', 4)
ON DUPLICATE KEY UPDATE nome=VALUES(nome), descrizione=VALUES(descrizione);

-- Abilita tutti i moduli per le aziende esistenti (per retrocompatibilità)
INSERT INTO moduli_azienda (azienda_id, modulo_id, abilitato)
SELECT a.id, m.id, TRUE
FROM aziende a
CROSS JOIN moduli_sistema m
WHERE a.stato = 'attiva'
ON DUPLICATE KEY UPDATE abilitato = TRUE;

-- Indici per migliorare le performance
CREATE INDEX idx_moduli_azienda_azienda ON moduli_azienda(azienda_id);
CREATE INDEX idx_moduli_azienda_modulo ON moduli_azienda(modulo_id);
CREATE INDEX idx_moduli_azienda_abilitato ON moduli_azienda(abilitato);