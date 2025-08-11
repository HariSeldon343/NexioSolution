-- Creazione tabella moduli se non esiste
CREATE TABLE IF NOT EXISTS moduli (
    id INT(11) NOT NULL AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    codice VARCHAR(50) NOT NULL UNIQUE,
    descrizione TEXT,
    icona VARCHAR(50) DEFAULT 'fas fa-cube',
    attivo TINYINT(1) DEFAULT 1,
    ordine INT DEFAULT 0,
    route VARCHAR(255),
    permessi_richiesti TEXT,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_codice (codice),
    INDEX idx_attivo (attivo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserimento moduli di base
INSERT IGNORE INTO moduli (nome, codice, descrizione, icona, route, ordine) VALUES
('Dashboard', 'dashboard', 'Dashboard principale', 'fas fa-tachometer-alt', 'dashboard.php', 1),
('Documenti', 'documenti', 'Gestione documentale', 'fas fa-file-alt', 'filesystem.php', 2),
('Calendario', 'calendario', 'Calendario eventi', 'fas fa-calendar', 'calendario.php', 3),
('Tickets', 'tickets', 'Sistema di ticketing', 'fas fa-ticket-alt', 'tickets.php', 4),
('Referenti', 'referenti', 'Gestione referenti aziendali', 'fas fa-users', 'referenti.php', 5),
('Report', 'report', 'Report e statistiche', 'fas fa-chart-bar', 'report.php', 6),
('Configurazione', 'configurazione', 'Configurazione sistema', 'fas fa-cog', 'configurazione.php', 7),
('Email', 'email', 'Sistema di notifiche email', 'fas fa-envelope', 'notifiche-email.php', 8),
('ISO Compliance', 'iso_compliance', 'Gestione conformità ISO', 'fas fa-shield-alt', 'conformita-normativa.php', 9),
('Template', 'template', 'Gestione template documenti', 'fas fa-file-code', 'gestione-template.php', 10);

-- Verifica inserimento
SELECT * FROM moduli ORDER BY ordine;