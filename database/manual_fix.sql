-- Script manuale per ricreare tutto il database
USE NexioSol;

-- Disabilita controlli foreign key
SET FOREIGN_KEY_CHECKS = 0;

-- Elimina tutte le tabelle se esistono
DROP TABLE IF EXISTS rate_limit_attempts;
DROP TABLE IF EXISTS rate_limit_whitelist;
DROP TABLE IF EXISTS rate_limit_blacklist;
DROP TABLE IF EXISTS password_history;
DROP TABLE IF EXISTS user_permissions;
DROP TABLE IF EXISTS evento_partecipanti;
DROP TABLE IF EXISTS eventi;
DROP TABLE IF EXISTS documenti;
DROP TABLE IF EXISTS log_attivita;
DROP TABLE IF EXISTS configurazioni;
DROP TABLE IF EXISTS utenti;
DROP TABLE IF EXISTS aziende;
DROP TABLE IF EXISTS log_attivita_new;
DROP TABLE IF EXISTS test_table;

-- Riabilita controlli
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Crea tabella aziende
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

-- 2. Crea tabella utenti
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
    primo_accesso BOOLEAN DEFAULT FALSE,
    password_scadenza DATE,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_accesso DATETIME,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE SET NULL,
    INDEX idx_email (email),
    INDEX idx_attivo (attivo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Crea tabelle rate limit
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

CREATE TABLE rate_limit_whitelist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL UNIQUE,
    reason VARCHAR(255),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES utenti(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- 4. Crea tabella configurazioni
CREATE TABLE configurazioni (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chiave VARCHAR(100) UNIQUE NOT NULL,
    valore TEXT,
    descrizione TEXT,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Crea tabella documenti
CREATE TABLE documenti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titolo VARCHAR(255) NOT NULL,
    contenuto LONGTEXT,
    contenuto_html LONGTEXT,
    tipo_documento VARCHAR(50),
    stato ENUM('bozza', 'pubblicato', 'archiviato') DEFAULT 'bozza',
    azienda_id INT,
    creato_da INT,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aggiornato_da INT,
    data_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (creato_da) REFERENCES utenti(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Crea tabella eventi  
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
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (creato_da) REFERENCES utenti(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Crea tabella evento_partecipanti
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

-- 8. Crea tabella log_attivita
CREATE TABLE log_attivita (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utente_id INT,
    tipo VARCHAR(50) NOT NULL,
    descrizione TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    data_azione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Crea tabella password_history
CREATE TABLE password_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES utenti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Inserisci dati di base
INSERT INTO aziende (nome, codice, stato) VALUES ('Nexio Demo', 'DEMO', 'attiva');

-- Password per 'admin123' con bcrypt cost 12
INSERT INTO utenti (username, password, email, nome, cognome, ruolo, azienda_id, primo_accesso) 
VALUES ('admin', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@nexio.it', 'Admin', 'Sistema', 'super_admin', 1, 0);

-- Configurazioni base
INSERT INTO configurazioni (chiave, valore, descrizione) VALUES
('smtp_enabled', '0', 'Abilita invio email via SMTP'),
('smtp_host', 'smtp.gmail.com', 'Server SMTP'),
('smtp_port', '587', 'Porta SMTP'),
('smtp_encryption', 'tls', 'Tipo di crittografia'),
('smtp_from_email', 'noreply@nexio.it', 'Email mittente'),
('smtp_from_name', 'Nexio Platform', 'Nome mittente');

-- Verifica finale
SELECT 'Setup completato!' as Messaggio;