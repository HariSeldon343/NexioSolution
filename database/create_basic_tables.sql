-- Create basic tables for filesystem functionality
USE NexioSol;

-- Create utenti table first (without foreign keys)
CREATE TABLE IF NOT EXISTS utenti (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    nome VARCHAR(100) NOT NULL,
    cognome VARCHAR(100) NOT NULL,
    telefono VARCHAR(20),
    ruolo ENUM('super_admin', 'admin', 'staff', 'cliente') NOT NULL DEFAULT 'cliente',
    attivo BOOLEAN DEFAULT TRUE,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_accesso TIMESTAMP NULL,
    INDEX idx_ruolo (ruolo),
    INDEX idx_attivo (attivo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create aziende table (without foreign keys)
CREATE TABLE IF NOT EXISTS aziende (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(255) NOT NULL,
    codice VARCHAR(50) UNIQUE,
    partita_iva VARCHAR(20),
    codice_fiscale VARCHAR(20),
    indirizzo TEXT,
    telefono VARCHAR(50),
    email VARCHAR(255),
    stato ENUM('attiva', 'sospesa', 'cancellata') DEFAULT 'attiva',
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_modifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_stato (stato),
    INDEX idx_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create utenti_aziende relationship table
CREATE TABLE IF NOT EXISTS utenti_aziende (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utente_id INT NOT NULL,
    azienda_id INT NOT NULL,
    ruolo ENUM('admin', 'staff', 'viewer') DEFAULT 'staff',
    data_associazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_utente_azienda (utente_id, azienda_id),
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create documenti table (already exists but let's ensure it has all columns)
ALTER TABLE documenti 
    ADD COLUMN IF NOT EXISTS dimensione_file BIGINT DEFAULT 0,
    ADD COLUMN IF NOT EXISTS azienda_id INT NOT NULL DEFAULT 1 AFTER formato;

-- Insert default admin user (password: admin123)
INSERT INTO utenti (username, password, email, nome, cognome, ruolo) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@piattaforma.it', 'Amministratore', 'Sistema', 'super_admin')
ON DUPLICATE KEY UPDATE ruolo='super_admin';

-- Insert default company
INSERT INTO aziende (id, nome, codice, email, stato) VALUES
(1, 'Azienda Demo', 'DEMO001', 'info@aziendademo.it', 'attiva')
ON DUPLICATE KEY UPDATE nome=nome;

-- Associate admin with default company
INSERT INTO utenti_aziende (utente_id, azienda_id, ruolo)
SELECT u.id, 1, 'admin' FROM utenti u WHERE u.username = 'admin'
ON DUPLICATE KEY UPDATE ruolo='admin';

-- Now add foreign keys to cartelle table if they don't exist
SET @constraint_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = 'NexioSol' 
    AND TABLE_NAME = 'cartelle' 
    AND CONSTRAINT_NAME = 'fk_cartelle_azienda'
);

SET @sql = IF(@constraint_exists = 0,
    'ALTER TABLE cartelle ADD CONSTRAINT fk_cartelle_azienda FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE',
    'SELECT "Constraint already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;