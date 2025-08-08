-- Script completo per creare il database da zero
-- =============================================

-- 1. Crea il database
CREATE DATABASE IF NOT EXISTS NexioSol 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- 2. Usa il database
USE NexioSol;

-- 3. Crea tabella aziende
CREATE TABLE IF NOT EXISTS aziende (
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

-- 4. Crea tabella utenti
CREATE TABLE IF NOT EXISTS utenti (
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

-- 5. Crea tabelle rate limiting
CREATE TABLE IF NOT EXISTS rate_limit_attempts (
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

CREATE TABLE IF NOT EXISTS rate_limit_whitelist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL UNIQUE,
    reason VARCHAR(255),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES utenti(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limit_blacklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL UNIQUE,
    reason VARCHAR(255),
    blocked_until DATETIME,
    permanent BOOLEAN DEFAULT FALSE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES utenti(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Crea tabella configurazioni
CREATE TABLE IF NOT EXISTS configurazioni (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chiave VARCHAR(100) UNIQUE NOT NULL,
    valore TEXT,
    descrizione TEXT,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Inserisci azienda demo
INSERT INTO aziende (nome, codice, stato) 
VALUES ('Azienda Demo', 'DEMO', 'attiva')
ON DUPLICATE KEY UPDATE nome = nome;

-- 8. Inserisci utente admin (password: admin123)
INSERT INTO utenti (username, password, email, nome, cognome, ruolo, azienda_id, primo_accesso) 
VALUES ('admin', '$2y$10$YourHashHere', 'admin@example.com', 'Admin', 'Sistema', 'super_admin', 1, 1)
ON DUPLICATE KEY UPDATE username = username;

-- Nota: La password deve essere generata con PHP usando password_hash('admin123', PASSWORD_BCRYPT)