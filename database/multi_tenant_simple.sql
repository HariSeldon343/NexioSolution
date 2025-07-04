-- Schema Multi-Tenant Semplificato
-- Compatibile con MariaDB/MySQL

-- Tabella aziende
CREATE TABLE IF NOT EXISTS aziende (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(200) NOT NULL,
    codice_fiscale VARCHAR(16),
    partita_iva VARCHAR(11),
    indirizzo VARCHAR(255),
    citta VARCHAR(100),
    cap VARCHAR(10),
    provincia VARCHAR(2),
    telefono VARCHAR(50),
    email VARCHAR(100),
    logo VARCHAR(255),
    stato ENUM('attiva', 'sospesa', 'disattivata') DEFAULT 'attiva',
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabella utenti_aziende
CREATE TABLE IF NOT EXISTS utenti_aziende (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utente_id INT NOT NULL,
    azienda_id INT NOT NULL,
    ruolo_azienda ENUM('proprietario', 'admin', 'utente') DEFAULT 'utente',
    attivo BOOLEAN DEFAULT TRUE,
    data_associazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    UNIQUE KEY unique_utente_azienda (utente_id, azienda_id)
);

-- Tabella tickets  
CREATE TABLE IF NOT EXISTS tickets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    azienda_id INT NOT NULL,
    utente_id INT NOT NULL,
    codice VARCHAR(20) UNIQUE,
    titolo VARCHAR(255) NOT NULL,
    descrizione TEXT,
    categoria ENUM('tecnico', 'amministrativo', 'commerciale', 'altro') DEFAULT 'altro',
    priorita ENUM('bassa', 'media', 'alta', 'urgente') DEFAULT 'media',
    stato ENUM('aperto', 'in_lavorazione', 'in_attesa', 'risolto', 'chiuso') DEFAULT 'aperto',
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    data_chiusura DATETIME,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (utente_id) REFERENCES utenti(id)
);

-- Tabella messaggi_ticket
CREATE TABLE IF NOT EXISTS messaggi_ticket (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ticket_id INT NOT NULL,
    utente_id INT NOT NULL,
    messaggio TEXT NOT NULL,
    tipo ENUM('messaggio', 'nota_interna', 'sistema') DEFAULT 'messaggio',
    data_invio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (utente_id) REFERENCES utenti(id)
);

-- Aggiungi ruolo super_admin agli utenti se non esiste
ALTER TABLE utenti MODIFY COLUMN ruolo ENUM('admin', 'staff', 'utente', 'super_admin') DEFAULT 'utente';

-- Aggiungi azienda_id alle tabelle esistenti
ALTER TABLE documenti ADD COLUMN azienda_id INT AFTER id;
ALTER TABLE documenti ADD FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE;

ALTER TABLE eventi ADD COLUMN azienda_id INT AFTER id;
ALTER TABLE eventi ADD FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE;

ALTER TABLE categorie_documenti ADD COLUMN azienda_id INT AFTER id;
ALTER TABLE categorie_documenti ADD FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE;

ALTER TABLE template_documenti ADD COLUMN azienda_id INT AFTER id;
ALTER TABLE template_documenti ADD FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE;

-- Vista statistiche aziende
CREATE OR REPLACE VIEW vista_statistiche_aziende AS
SELECT 
    a.id,
    a.nome,
    COUNT(DISTINCT ua.utente_id) as numero_utenti,
    COUNT(DISTINCT d.id) as numero_documenti,
    COUNT(DISTINCT e.id) as numero_eventi,
    COUNT(DISTINCT t.id) as tickets_aperti
FROM aziende a
LEFT JOIN utenti_aziende ua ON a.id = ua.azienda_id AND ua.attivo = 1
LEFT JOIN documenti d ON a.id = d.azienda_id
LEFT JOIN eventi e ON a.id = e.azienda_id
LEFT JOIN tickets t ON a.id = t.azienda_id AND t.stato IN ('aperto', 'in_lavorazione')
GROUP BY a.id;

-- Dati di esempio (vengono inseriti dal PHP se non esistono) 