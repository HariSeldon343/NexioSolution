-- Force create aziende table handling tablespace issues
USE NexioSol;

-- First create a temp table with a different name
CREATE TABLE IF NOT EXISTS companies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    telefono VARCHAR(50),
    indirizzo TEXT,
    partita_iva VARCHAR(20),
    codice_fiscale VARCHAR(20),
    logo VARCHAR(255),
    stato ENUM('attiva', 'sospesa', 'cancellata') DEFAULT 'attiva',
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_modifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_stato (stato),
    KEY idx_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert data
INSERT INTO companies (id, nome, email, stato) VALUES
(1, 'Azienda Demo', 'info@aziendademo.it', 'attiva');

-- Since we can't rename to aziende due to tablespace issue, 
-- let's update all references to use 'companies' instead