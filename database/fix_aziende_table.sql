-- Fix per la tabella aziende con problema di tablespace
USE NexioSol;

-- Disabilita controlli foreign key temporaneamente
SET FOREIGN_KEY_CHECKS = 0;

-- Prova a fare drop della tabella
DROP TABLE IF EXISTS aziende;

-- Crea la tabella aziende
CREATE TABLE aziende (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    codice VARCHAR(50) UNIQUE,
    partita_iva VARCHAR(20),
    codice_fiscale VARCHAR(20),
    indirizzo TEXT,
    telefono VARCHAR(50),
    cap VARCHAR(10),
    citta VARCHAR(100),
    provincia VARCHAR(2),
    email VARCHAR(255),
    pec VARCHAR(255),
    sito_web VARCHAR(255),
    logo VARCHAR(255),
    settore VARCHAR(100),
    numero_dipendenti INT,
    fatturato_annuo DECIMAL(15,2),
    data_fondazione DATE,
    descrizione TEXT,
    stato ENUM('attiva', 'sospesa', 'cancellata') DEFAULT 'attiva',
    piano ENUM('base', 'professional', 'enterprise') DEFAULT 'base',
    scadenza_piano DATE,
    limite_utenti INT DEFAULT 5,
    limite_spazio_mb INT DEFAULT 1024,
    note TEXT,
    creato_da INT,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aggiornato_da INT,
    aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (creato_da) REFERENCES utenti(id) ON DELETE SET NULL,
    FOREIGN KEY (aggiornato_da) REFERENCES utenti(id) ON DELETE SET NULL,
    INDEX idx_stato (stato),
    INDEX idx_nome (nome),
    INDEX idx_codice (codice)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Riabilita controlli
SET FOREIGN_KEY_CHECKS = 1;

-- Inserisci azienda di default
INSERT INTO aziende (id, nome, codice, email, stato) VALUES
(1, 'Azienda Demo', 'DEMO001', 'info@aziendademo.it', 'attiva')
ON DUPLICATE KEY UPDATE nome=nome;