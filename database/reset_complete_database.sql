-- Script di reset completo del database
-- ATTENZIONE: Questo script ricrea tutto da zero

-- Usa il database
USE NexioSol;

-- Disabilita controlli foreign key temporaneamente
SET FOREIGN_KEY_CHECKS = 0;

-- Droppa tutte le tabelle esistenti se presenti
DROP TABLE IF EXISTS rate_limit_attempts;
DROP TABLE IF EXISTS rate_limit_whitelist;
DROP TABLE IF EXISTS rate_limit_blacklist;
DROP TABLE IF EXISTS password_history;
DROP TABLE IF EXISTS user_permissions;
DROP TABLE IF EXISTS notifiche_email;
DROP TABLE IF EXISTS ticket_destinatari;
DROP TABLE IF EXISTS tickets;
DROP TABLE IF EXISTS evento_partecipanti;
DROP TABLE IF EXISTS eventi;
DROP TABLE IF EXISTS documenti_destinatari;
DROP TABLE IF EXISTS documenti_versioni;
DROP TABLE IF EXISTS documenti;
DROP TABLE IF EXISTS moduli_documento;
DROP TABLE IF EXISTS moduli_template;
DROP TABLE IF EXISTS template_documenti;
DROP TABLE IF EXISTS classificazione;
DROP TABLE IF EXISTS referenti_aziende;
DROP TABLE IF EXISTS referenti;
DROP TABLE IF EXISTS newsletter;
DROP TABLE IF EXISTS configurazioni;
DROP TABLE IF EXISTS log_attivita;
DROP TABLE IF EXISTS utenti_aziende;
DROP TABLE IF EXISTS utenti;
DROP TABLE IF EXISTS aziende;
DROP TABLE IF EXISTS log_attivita_new;
DROP TABLE IF EXISTS test_table;

-- Riabilita controlli
SET FOREIGN_KEY_CHECKS = 1;

-- 1. TABELLA AZIENDE
CREATE TABLE aziende (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    codice VARCHAR(50) UNIQUE,
    partita_iva VARCHAR(20),
    codice_fiscale VARCHAR(20),
    indirizzo TEXT,
    telefono VARCHAR(50),
    email VARCHAR(255),
    stato ENUM('attiva', 'sospesa', 'cancellata') DEFAULT 'attiva',
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. TABELLA UTENTI
CREATE TABLE utenti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    nome VARCHAR(100),
    cognome VARCHAR(100),
    ruolo ENUM('super_admin', 'admin', 'utente', 'cliente') DEFAULT 'utente',
    azienda_id INT,
    attivo BOOLEAN DEFAULT TRUE,
    primo_accesso BOOLEAN DEFAULT TRUE,
    password_scadenza DATE,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_accesso DATETIME,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE SET NULL,
    INDEX idx_email (email),
    INDEX idx_attivo (attivo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. TABELLA RATE LIMIT ATTEMPTS
CREATE TABLE rate_limit_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(50) NOT NULL,
    identifier VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    success BOOLEAN DEFAULT FALSE,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_action_identifier (action, identifier),
    INDEX idx_attempted_at (attempted_at),
    INDEX idx_ip (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. TABELLA RATE LIMIT WHITELIST
CREATE TABLE rate_limit_whitelist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL UNIQUE,
    reason VARCHAR(255),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES utenti(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. TABELLA RATE LIMIT BLACKLIST
CREATE TABLE rate_limit_blacklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL UNIQUE,
    reason VARCHAR(255),
    blocked_until DATETIME,
    permanent BOOLEAN DEFAULT FALSE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES utenti(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. TABELLA CONFIGURAZIONI
CREATE TABLE configurazioni (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chiave VARCHAR(100) UNIQUE NOT NULL,
    valore TEXT,
    descrizione TEXT,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. TABELLA DOCUMENTI
CREATE TABLE documenti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titolo VARCHAR(255) NOT NULL,
    contenuto LONGTEXT,
    contenuto_html LONGTEXT,
    tipo_documento VARCHAR(50),
    stato ENUM('bozza', 'pubblicato', 'archiviato') DEFAULT 'bozza',
    azienda_id INT,
    classificazione_id INT,
    creato_da INT,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aggiornato_da INT,
    data_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (creato_da) REFERENCES utenti(id) ON DELETE SET NULL,
    INDEX idx_stato (stato),
    INDEX idx_azienda (azienda_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. TABELLA EVENTI
CREATE TABLE eventi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titolo VARCHAR(255) NOT NULL,
    descrizione TEXT,
    data_inizio DATETIME NOT NULL,
    data_fine DATETIME,
    luogo VARCHAR(255),
    tipo VARCHAR(50) DEFAULT 'riunione',
    stato ENUM('programmato', 'in_corso', 'completato', 'annullato') DEFAULT 'programmato',
    azienda_id INT,
    creato_da INT,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aggiornato_da INT,
    data_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (creato_da) REFERENCES utenti(id) ON DELETE SET NULL,
    INDEX idx_data_inizio (data_inizio),
    INDEX idx_stato (stato)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. TABELLA EVENTO PARTECIPANTI
CREATE TABLE evento_partecipanti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evento_id INT NOT NULL,
    utente_id INT NOT NULL,
    stato ENUM('invitato', 'confermato', 'rifiutato', 'forse') DEFAULT 'invitato',
    notifica_inviata BOOLEAN DEFAULT FALSE,
    data_invito TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evento_id) REFERENCES eventi(id) ON DELETE CASCADE,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    UNIQUE KEY unique_evento_utente (evento_id, utente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. TABELLA LOG ATTIVITA
CREATE TABLE log_attivita (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utente_id INT,
    tipo VARCHAR(50) NOT NULL,
    descrizione TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    data_azione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE SET NULL,
    INDEX idx_tipo (tipo),
    INDEX idx_data_azione (data_azione)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. TABELLA PASSWORD HISTORY
CREATE TABLE password_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES utenti(id) ON DELETE CASCADE,
    INDEX idx_user_created (user_id, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. TABELLA CLASSIFICAZIONE
CREATE TABLE classificazione (
    id INT AUTO_INCREMENT PRIMARY KEY,
    azienda_id INT NOT NULL,
    codice VARCHAR(20) NOT NULL,
    descrizione VARCHAR(255) NOT NULL,
    parent_id INT DEFAULT NULL,
    livello INT NOT NULL DEFAULT 1,
    attivo BOOLEAN DEFAULT TRUE,
    note TEXT,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES classificazione(id) ON DELETE CASCADE,
    UNIQUE KEY unique_codice_azienda (azienda_id, codice),
    INDEX idx_parent (parent_id),
    INDEX idx_azienda (azienda_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. TABELLA REFERENTI
CREATE TABLE referenti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    cognome VARCHAR(100),
    email VARCHAR(255),
    telefono VARCHAR(50),
    azienda VARCHAR(255),
    ruolo VARCHAR(100),
    note TEXT,
    stato ENUM('attivo', 'inattivo') DEFAULT 'attivo',
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_stato (stato)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. TABELLA TICKETS (per supporto)
CREATE TABLE tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    oggetto VARCHAR(255) NOT NULL,
    descrizione TEXT,
    stato ENUM('aperto', 'in_lavorazione', 'risolto', 'chiuso') DEFAULT 'aperto',
    priorita ENUM('bassa', 'media', 'alta', 'urgente') DEFAULT 'media',
    categoria VARCHAR(50),
    azienda_id INT,
    creato_da INT,
    assegnato_a INT,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    data_chiusura DATETIME,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (creato_da) REFERENCES utenti(id) ON DELETE SET NULL,
    FOREIGN KEY (assegnato_a) REFERENCES utenti(id) ON DELETE SET NULL,
    INDEX idx_stato (stato),
    INDEX idx_priorita (priorita)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. INSERISCI DATI DI BASE

-- Inserisci azienda demo
INSERT INTO aziende (nome, codice, stato) VALUES ('Nexio Demo', 'DEMO', 'attiva');

-- Inserisci utente admin (password: admin123)
-- La password è già hashata con bcrypt
INSERT INTO utenti (username, password, email, nome, cognome, ruolo, azienda_id, primo_accesso) 
VALUES ('admin', '$2y$12$4G4X5X.5X5X5X5X5X5X5XeH2kF6P8Z5X5X5X5X5X5X5X5X5X5X5X5', 'admin@nexio.it', 'Admin', 'Sistema', 'super_admin', 1, 0);

-- Configurazioni email di default
INSERT INTO configurazioni (chiave, valore, descrizione) VALUES
('smtp_enabled', '0', 'Abilita invio email via SMTP'),
('smtp_host', 'smtp.gmail.com', 'Server SMTP'),
('smtp_port', '587', 'Porta SMTP'),
('smtp_encryption', 'tls', 'Tipo di crittografia (tls/ssl)'),
('smtp_username', '', 'Username SMTP'),
('smtp_password', '', 'Password SMTP'),
('smtp_from_email', 'noreply@nexio.it', 'Email mittente'),
('smtp_from_name', 'Nexio Platform', 'Nome mittente');

-- Messaggio finale
SELECT 'Database creato con successo!' as Messaggio;