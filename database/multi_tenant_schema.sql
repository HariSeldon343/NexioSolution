-- Schema aggiuntivo per supporto multi-tenant
-- Da eseguire dopo schema.sql

USE piattaforma_collaborativa;

-- Tabella aziende
CREATE TABLE IF NOT EXISTS aziende (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(200) NOT NULL,
    ragione_sociale VARCHAR(200),
    partita_iva VARCHAR(20) UNIQUE,
    codice_fiscale VARCHAR(20),
    indirizzo VARCHAR(255),
    citta VARCHAR(100),
    cap VARCHAR(10),
    provincia VARCHAR(2),
    telefono VARCHAR(20),
    email VARCHAR(100),
    pec VARCHAR(100),
    logo_path VARCHAR(255),
    settore VARCHAR(100),
    numero_dipendenti INT,
    stato ENUM('attiva', 'sospesa', 'cancellata') DEFAULT 'attiva',
    data_registrazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_scadenza DATE,
    note TEXT,
    creata_da INT,
    FOREIGN KEY (creata_da) REFERENCES utenti(id) ON DELETE SET NULL,
    INDEX idx_stato (stato),
    INDEX idx_partita_iva (partita_iva)
) ENGINE=InnoDB;

-- Tabella relazione utenti-aziende
CREATE TABLE IF NOT EXISTS utenti_aziende (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utente_id INT NOT NULL,
    azienda_id INT NOT NULL,
    ruolo_azienda ENUM('proprietario', 'admin', 'utente', 'ospite') DEFAULT 'utente',
    data_assegnazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assegnato_da INT,
    attivo BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (assegnato_da) REFERENCES utenti(id) ON DELETE SET NULL,
    UNIQUE KEY unique_utente_azienda (utente_id, azienda_id),
    INDEX idx_azienda (azienda_id),
    INDEX idx_utente (utente_id)
) ENGINE=InnoDB;

-- Tabella tickets supporto
CREATE TABLE IF NOT EXISTS tickets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    codice VARCHAR(20) UNIQUE NOT NULL,
    azienda_id INT NOT NULL,
    utente_id INT NOT NULL,
    titolo VARCHAR(200) NOT NULL,
    descrizione TEXT,
    categoria ENUM('tecnico', 'amministrativo', 'formazione', 'altro') DEFAULT 'altro',
    priorita ENUM('bassa', 'media', 'alta', 'urgente') DEFAULT 'media',
    stato ENUM('aperto', 'in_lavorazione', 'in_attesa', 'risolto', 'chiuso') DEFAULT 'aperto',
    assegnato_a INT,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    data_chiusura TIMESTAMP NULL,
    tempo_risoluzione INT COMMENT 'Tempo in minuti',
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    FOREIGN KEY (assegnato_a) REFERENCES utenti(id) ON DELETE SET NULL,
    INDEX idx_azienda (azienda_id),
    INDEX idx_stato (stato),
    INDEX idx_priorita (priorita),
    INDEX idx_data (data_creazione)
) ENGINE=InnoDB;

-- Tabella messaggi ticket
CREATE TABLE IF NOT EXISTS messaggi_ticket (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ticket_id INT NOT NULL,
    utente_id INT NOT NULL,
    messaggio TEXT NOT NULL,
    tipo ENUM('messaggio', 'nota_interna', 'cambio_stato') DEFAULT 'messaggio',
    visibile_cliente BOOLEAN DEFAULT TRUE,
    allegati JSON,
    data_invio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    letto BOOLEAN DEFAULT FALSE,
    letto_da INT,
    data_lettura TIMESTAMP NULL,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    FOREIGN KEY (letto_da) REFERENCES utenti(id) ON DELETE SET NULL,
    INDEX idx_ticket (ticket_id),
    INDEX idx_data (data_invio)
) ENGINE=InnoDB;

-- Aggiornamento tabelle esistenti per supporto multi-tenant
-- Aggiungiamo azienda_id alle tabelle principali

ALTER TABLE documenti 
ADD COLUMN azienda_id INT AFTER id,
ADD FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
ADD INDEX idx_azienda (azienda_id);

ALTER TABLE eventi 
ADD COLUMN azienda_id INT AFTER id,
ADD FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
ADD INDEX idx_azienda (azienda_id);

ALTER TABLE categorie_documenti 
ADD COLUMN azienda_id INT AFTER id,
ADD FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
ADD INDEX idx_azienda (azienda_id);

ALTER TABLE template_documenti 
ADD COLUMN azienda_id INT AFTER id,
ADD FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
ADD INDEX idx_azienda (azienda_id);

-- Aggiornamento ruoli utenti per super admin
ALTER TABLE utenti 
MODIFY COLUMN ruolo ENUM('super_admin', 'admin', 'staff', 'cliente') NOT NULL DEFAULT 'cliente';

-- Aggiornamento utente admin esistente a super_admin
UPDATE utenti SET ruolo = 'super_admin' WHERE username = 'admin';

-- Trigger per generare codice ticket automaticamente
DELIMITER //
CREATE TRIGGER before_insert_ticket
BEFORE INSERT ON tickets
FOR EACH ROW
BEGIN
    DECLARE next_id INT;
    DECLARE new_code VARCHAR(20);
    
    SELECT AUTO_INCREMENT INTO next_id
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tickets';
    
    SET new_code = CONCAT('TK-', YEAR(NOW()), LPAD(next_id, 5, '0'));
    SET NEW.codice = new_code;
END//
DELIMITER ;

-- Vista per statistiche aziende
CREATE VIEW vista_statistiche_aziende AS
SELECT 
    a.id,
    a.nome,
    COUNT(DISTINCT ua.utente_id) as numero_utenti,
    COUNT(DISTINCT d.id) as numero_documenti,
    COUNT(DISTINCT e.id) as numero_eventi,
    COUNT(DISTINCT t.id) as numero_tickets,
    COUNT(DISTINCT CASE WHEN t.stato IN ('aperto', 'in_lavorazione') THEN t.id END) as tickets_aperti
FROM aziende a
LEFT JOIN utenti_aziende ua ON a.id = ua.azienda_id AND ua.attivo = 1
LEFT JOIN documenti d ON a.id = d.azienda_id
LEFT JOIN eventi e ON a.id = e.azienda_id
LEFT JOIN tickets t ON a.id = t.azienda_id
WHERE a.stato = 'attiva'
GROUP BY a.id;

-- Inserimento azienda di test
INSERT INTO aziende (nome, ragione_sociale, partita_iva, email, settore, stato) 
VALUES ('Azienda Demo', 'Demo S.r.l.', '12345678901', 'info@demo.it', 'Servizi', 'attiva');

-- Collegamento admin all'azienda demo come proprietario
INSERT INTO utenti_aziende (utente_id, azienda_id, ruolo_azienda)
SELECT u.id, a.id, 'proprietario'
FROM utenti u, aziende a
WHERE u.username = 'admin' AND a.nome = 'Azienda Demo'; 