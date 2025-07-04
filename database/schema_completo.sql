-- Schema completo database Piattaforma Collaborativa
-- Versione: 1.0
-- Data: <?php echo date('Y-m-d'); ?>

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- Database: piattaforma_collaborativa
CREATE DATABASE IF NOT EXISTS piattaforma_collaborativa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE piattaforma_collaborativa;

-- --------------------------------------------------------
-- Tabella aziende
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS aziende (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(255) NOT NULL,
    indirizzo TEXT,
    telefono VARCHAR(20),
    email VARCHAR(255),
    partita_iva VARCHAR(20),
    codice_fiscale VARCHAR(20),
    logo VARCHAR(255),
    attivo BOOLEAN DEFAULT TRUE,
    richiede_conferma_azioni BOOLEAN DEFAULT TRUE,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_nome (nome),
    INDEX idx_attivo (attivo)
);

-- --------------------------------------------------------
-- Tabella utenti
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS utenti (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    cognome VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    ruolo ENUM('super_admin', 'admin', 'user') NOT NULL DEFAULT 'user',
    azienda_id INT,
    attivo BOOLEAN DEFAULT TRUE,
    ultimo_accesso TIMESTAMP NULL,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE SET NULL,
    INDEX idx_email (email),
    INDEX idx_ruolo (ruolo),
    INDEX idx_azienda (azienda_id)
);

-- --------------------------------------------------------
-- Tabella utenti_aziende (relazione many-to-many)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS utenti_aziende (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utente_id INT NOT NULL,
    azienda_id INT NOT NULL,
    ruolo_aziendale VARCHAR(100),
    data_assegnazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    UNIQUE KEY unique_utente_azienda (utente_id, azienda_id),
    INDEX idx_utente (utente_id),
    INDEX idx_azienda (azienda_id)
);

-- --------------------------------------------------------
-- Tabella referenti_aziende
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS referenti_aziende (
    id INT PRIMARY KEY AUTO_INCREMENT,
    azienda_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    cognome VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    telefono VARCHAR(20),
    ruolo_aziendale VARCHAR(100),
    puo_vedere_documenti BOOLEAN DEFAULT TRUE,
    puo_creare_documenti BOOLEAN DEFAULT FALSE,
    puo_modificare_documenti BOOLEAN DEFAULT FALSE,
    puo_eliminare_documenti BOOLEAN DEFAULT FALSE,
    puo_scaricare_documenti BOOLEAN DEFAULT TRUE,
    puo_compilare_moduli BOOLEAN DEFAULT FALSE,
    puo_aprire_ticket BOOLEAN DEFAULT TRUE,
    puo_gestire_eventi BOOLEAN DEFAULT FALSE,
    riceve_notifiche_email BOOLEAN DEFAULT TRUE,
    attivo BOOLEAN DEFAULT TRUE,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    UNIQUE KEY unique_email (email),
    INDEX idx_azienda (azienda_id)
);

-- --------------------------------------------------------
-- Tabella documenti
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS documenti (
    id INT PRIMARY KEY AUTO_INCREMENT,
    titolo VARCHAR(255) NOT NULL,
    descrizione TEXT,
    categoria ENUM('Manuali', 'Procedure', 'Dashboard', 'Moduli') NOT NULL,
    file_path VARCHAR(500),
    file_size INT,
    file_type VARCHAR(100),
    versione VARCHAR(20) DEFAULT '1.0',
    azienda_id INT,
    utente_id INT,
    pubblico BOOLEAN DEFAULT FALSE,
    attivo BOOLEAN DEFAULT TRUE,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE SET NULL,
    INDEX idx_categoria (categoria),
    INDEX idx_azienda (azienda_id),
    INDEX idx_pubblico (pubblico),
    FULLTEXT idx_ricerca (titolo, descrizione)
);

-- --------------------------------------------------------
-- Tabella eventi
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS eventi (
    id INT PRIMARY KEY AUTO_INCREMENT,
    titolo VARCHAR(255) NOT NULL,
    descrizione TEXT,
    data_inizio DATETIME NOT NULL,
    data_fine DATETIME,
    tipo ENUM('riunione', 'scadenza', 'formazione', 'altro') NOT NULL DEFAULT 'altro',
    luogo VARCHAR(255),
    azienda_id INT,
    utente_id INT,
    colore VARCHAR(7) DEFAULT '#6366f1',
    ricorrente BOOLEAN DEFAULT FALSE,
    ricorrenza_tipo ENUM('giornaliera', 'settimanale', 'mensile', 'annuale'),
    ricorrenza_fine DATE,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE SET NULL,
    INDEX idx_data (data_inizio),
    INDEX idx_azienda (azienda_id),
    INDEX idx_tipo (tipo)
);

-- --------------------------------------------------------
-- Tabella log_attivita
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS log_attivita (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utente_id INT,
    referente_id INT,
    azienda_id INT,
    tipo_entita VARCHAR(50) NOT NULL,
    id_entita INT,
    azione VARCHAR(50) NOT NULL,
    dettagli TEXT,
    dati_precedenti TEXT,
    dati_nuovi TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE SET NULL,
    FOREIGN KEY (referente_id) REFERENCES referenti_aziende(id) ON DELETE SET NULL,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    INDEX idx_azienda (azienda_id),
    INDEX idx_tipo (tipo_entita),
    INDEX idx_azione (azione),
    INDEX idx_data (creato_il)
);

-- --------------------------------------------------------
-- Tabella sessioni
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS sessioni (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utente_id INT NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    data_login TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_logout TIMESTAMP NULL,
    data_ultima_attivita TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_utente (utente_id),
    INDEX idx_data (data_login)
);

-- --------------------------------------------------------
-- Tabella tickets
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS tickets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    azienda_id INT NOT NULL,
    utente_id INT,
    oggetto VARCHAR(255) NOT NULL,
    descrizione TEXT NOT NULL,
    priorita ENUM('bassa', 'media', 'alta', 'urgente') DEFAULT 'media',
    stato ENUM('aperto', 'in_lavorazione', 'in_attesa', 'risolto', 'chiuso') DEFAULT 'aperto',
    categoria VARCHAR(50),
    assegnato_a INT,
    risolto_da INT,
    data_risoluzione TIMESTAMP NULL,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE SET NULL,
    FOREIGN KEY (assegnato_a) REFERENCES utenti(id) ON DELETE SET NULL,
    FOREIGN KEY (risolto_da) REFERENCES utenti(id) ON DELETE SET NULL,
    INDEX idx_azienda (azienda_id),
    INDEX idx_stato (stato),
    INDEX idx_priorita (priorita),
    INDEX idx_data (creato_il)
);

-- --------------------------------------------------------
-- Tabella messaggi_ticket
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS messaggi_ticket (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ticket_id INT NOT NULL,
    utente_id INT,
    messaggio TEXT NOT NULL,
    allegato VARCHAR(500),
    interno BOOLEAN DEFAULT FALSE,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE SET NULL,
    INDEX idx_ticket (ticket_id),
    INDEX idx_data (creato_il)
);

-- --------------------------------------------------------
-- Tabella notifiche_email
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifiche_email (
    id INT PRIMARY KEY AUTO_INCREMENT,
    destinatario_email VARCHAR(255) NOT NULL,
    destinatario_nome VARCHAR(255),
    oggetto VARCHAR(255) NOT NULL,
    contenuto TEXT NOT NULL,
    tipo_notifica VARCHAR(50),
    azienda_id INT,
    priorita INT DEFAULT 5,
    tentativi INT DEFAULT 0,
    stato ENUM('in_coda', 'inviata', 'errore') DEFAULT 'in_coda',
    errore_messaggio TEXT,
    programmata_per TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    inviata_il TIMESTAMP NULL,
    creata_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    INDEX idx_stato (stato),
    INDEX idx_programmata (programmata_per),
    INDEX idx_azienda (azienda_id)
);

-- --------------------------------------------------------
-- Tabella preferenze_notifiche_admin
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS preferenze_notifiche_admin (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    tipo_notifica VARCHAR(50) NOT NULL,
    invia_a_referenti BOOLEAN DEFAULT TRUE,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES utenti(id) ON DELETE CASCADE,
    UNIQUE KEY unique_admin_tipo (admin_id, tipo_notifica)
);

-- --------------------------------------------------------
-- Tabella configurazioni
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS configurazioni (
    id INT PRIMARY KEY AUTO_INCREMENT,
    chiave VARCHAR(100) UNIQUE NOT NULL,
    valore TEXT,
    tipo VARCHAR(50) DEFAULT 'string',
    descrizione TEXT,
    modificabile BOOLEAN DEFAULT TRUE,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_chiave (chiave)
);

-- --------------------------------------------------------
-- Vista log_attivita completa
-- --------------------------------------------------------
CREATE OR REPLACE VIEW vista_log_attivita AS
SELECT 
    log_attivita.*,
    COALESCE(utenti.email, referenti_aziende.email) as email_utente,
    COALESCE(
        CONCAT(utenti.nome, ' ', utenti.cognome), 
        CONCAT(referenti_aziende.nome, ' ', referenti_aziende.cognome)
    ) as nome_completo,
    aziende.nome as nome_azienda,
    CASE 
        WHEN log_attivita.utente_id IS NOT NULL THEN 'utente_sistema'
        WHEN log_attivita.referente_id IS NOT NULL THEN 'referente_azienda'
        ELSE 'sconosciuto'
    END as tipo_utente
FROM log_attivita
LEFT JOIN utenti ON log_attivita.utente_id = utenti.id
LEFT JOIN referenti_aziende ON log_attivita.referente_id = referenti_aziende.id
LEFT JOIN aziende ON log_attivita.azienda_id = aziende.id
ORDER BY log_attivita.creato_il DESC;

-- --------------------------------------------------------
-- Vista statistiche aziende
-- --------------------------------------------------------
CREATE OR REPLACE VIEW vista_statistiche_aziende AS
SELECT 
    a.id,
    a.nome,
    COUNT(DISTINCT u.id) as totale_utenti,
    COUNT(DISTINCT d.id) as totale_documenti,
    COUNT(DISTINCT e.id) as totale_eventi,
    COUNT(DISTINCT t.id) as totale_tickets
FROM aziende a
LEFT JOIN utenti_aziende ua ON a.id = ua.azienda_id
LEFT JOIN utenti u ON ua.utente_id = u.id
LEFT JOIN documenti d ON a.id = d.azienda_id
LEFT JOIN eventi e ON a.id = e.azienda_id
LEFT JOIN tickets t ON a.id = t.azienda_id
WHERE a.attivo = TRUE
GROUP BY a.id;

-- --------------------------------------------------------
-- Dati iniziali di configurazione
-- --------------------------------------------------------
INSERT INTO configurazioni (chiave, valore, tipo, descrizione) VALUES
('site_name', 'Piattaforma Collaborativa', 'string', 'Nome del sito'),
('site_email', 'info@example.com', 'string', 'Email principale del sito'),
('max_upload_size', '10485760', 'integer', 'Dimensione massima upload in bytes (10MB)'),
('session_timeout', '3600', 'integer', 'Timeout sessione in secondi (1 ora)'),
('enable_notifications', 'true', 'boolean', 'Abilita sistema notifiche'),
('maintenance_mode', 'false', 'boolean', 'Modalità manutenzione');

-- --------------------------------------------------------
-- Utente amministratore di default (CAMBIARE PASSWORD!)
-- --------------------------------------------------------
-- Password: admin123 (da cambiare al primo accesso)
INSERT INTO utenti (nome, cognome, email, password, ruolo, attivo) VALUES
('Admin', 'Sistema', 'admin@example.com', '$2y$10$8K1WDiKlQW7oV5N8kPpW8eZH9BxO6F5Ld0bPqQoqXvZoJ8nR5kK8y', 'super_admin', 1);

-- --------------------------------------------------------
-- Azienda di default
-- --------------------------------------------------------
INSERT INTO aziende (nome, indirizzo, telefono, email, attivo) VALUES
('Azienda Demo', 'Via Demo 1, 00100 Roma', '+39 06 12345678', 'info@aziendademo.it', 1);

-- Associa admin all'azienda
UPDATE utenti SET azienda_id = 1 WHERE id = 1; 