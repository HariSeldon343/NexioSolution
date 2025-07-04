-- Schema database per piattaforma collaborativa
-- Compatibile con MySQL

CREATE DATABASE IF NOT EXISTS piattaforma_collaborativa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE piattaforma_collaborativa;

-- Tabella utenti
CREATE TABLE IF NOT EXISTS utenti (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    nome VARCHAR(100) NOT NULL,
    cognome VARCHAR(100) NOT NULL,
    telefono VARCHAR(20),
    ruolo ENUM('admin', 'staff', 'cliente') NOT NULL DEFAULT 'cliente',
    attivo BOOLEAN DEFAULT TRUE,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_accesso TIMESTAMP NULL,
    INDEX idx_ruolo (ruolo),
    INDEX idx_attivo (attivo)
) ENGINE=InnoDB;

-- Tabella sessioni
CREATE TABLE IF NOT EXISTS sessioni (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utente_id INT NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_scadenza TIMESTAMP NOT NULL,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_scadenza (data_scadenza)
) ENGINE=InnoDB;

-- Tabella categorie documenti
CREATE TABLE IF NOT EXISTS categorie_documenti (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    descrizione TEXT,
    icona VARCHAR(50),
    ordinamento INT DEFAULT 0,
    creato_da INT,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (creato_da) REFERENCES utenti(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Inserimento categorie predefinite
INSERT INTO categorie_documenti (nome, descrizione, icona, ordinamento) VALUES
('Manuali', 'Manuali operativi e di gestione', 'book', 1),
('Procedure', 'Procedure operative standard', 'clipboard', 2),
('Dashboard', 'Dashboard di registrazioni', 'chart-bar', 3),
('Moduli', 'Moduli e form compilabili', 'document', 4);

-- Tabella template documenti
CREATE TABLE IF NOT EXISTS template_documenti (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(200) NOT NULL,
    descrizione TEXT,
    categoria_id INT,
    contenuto_html LONGTEXT,
    campi_editabili JSON,
    creato_da INT,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultima_modifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id) REFERENCES categorie_documenti(id) ON DELETE SET NULL,
    FOREIGN KEY (creato_da) REFERENCES utenti(id) ON DELETE SET NULL,
    INDEX idx_categoria (categoria_id)
) ENGINE=InnoDB;

-- Tabella documenti
CREATE TABLE IF NOT EXISTS documenti (
    id INT PRIMARY KEY AUTO_INCREMENT,
    codice VARCHAR(50) UNIQUE NOT NULL,
    titolo VARCHAR(200) NOT NULL,
    template_id INT,
    categoria_id INT,
    contenuto LONGTEXT,
    dati_compilati JSON,
    versione INT DEFAULT 1,
    stato ENUM('bozza', 'pubblicato', 'archiviato') DEFAULT 'bozza',
    iso_compliance VARCHAR(50),
    creato_da INT,
    modificato_da INT,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultima_modifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES template_documenti(id) ON DELETE SET NULL,
    FOREIGN KEY (categoria_id) REFERENCES categorie_documenti(id) ON DELETE SET NULL,
    FOREIGN KEY (creato_da) REFERENCES utenti(id) ON DELETE SET NULL,
    FOREIGN KEY (modificato_da) REFERENCES utenti(id) ON DELETE SET NULL,
    INDEX idx_stato (stato),
    INDEX idx_categoria (categoria_id),
    FULLTEXT idx_ricerca (titolo, contenuto)
) ENGINE=InnoDB;

-- Tabella versioni documenti
CREATE TABLE IF NOT EXISTS versioni_documenti (
    id INT PRIMARY KEY AUTO_INCREMENT,
    documento_id INT NOT NULL,
    versione INT NOT NULL,
    contenuto LONGTEXT,
    dati_compilati JSON,
    modificato_da INT,
    note_modifica TEXT,
    data_modifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (documento_id) REFERENCES documenti(id) ON DELETE CASCADE,
    FOREIGN KEY (modificato_da) REFERENCES utenti(id) ON DELETE SET NULL,
    UNIQUE KEY unique_doc_version (documento_id, versione)
) ENGINE=InnoDB;

-- Tabella permessi documenti
CREATE TABLE IF NOT EXISTS permessi_documenti (
    id INT PRIMARY KEY AUTO_INCREMENT,
    documento_id INT NOT NULL,
    utente_id INT,
    ruolo VARCHAR(20),
    permesso ENUM('lettura', 'scrittura', 'admin') NOT NULL,
    data_assegnazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (documento_id) REFERENCES documenti(id) ON DELETE CASCADE,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    UNIQUE KEY unique_permission (documento_id, utente_id, ruolo),
    INDEX idx_documento (documento_id),
    INDEX idx_utente (utente_id)
) ENGINE=InnoDB;

-- Tabella eventi calendario
CREATE TABLE IF NOT EXISTS eventi (
    id INT PRIMARY KEY AUTO_INCREMENT,
    titolo VARCHAR(200) NOT NULL,
    descrizione TEXT,
    luogo VARCHAR(200),
    tipo ENUM('riunione', 'formazione', 'evento', 'altro') NOT NULL DEFAULT 'riunione',
    data_inizio DATETIME NOT NULL,
    data_fine DATETIME NOT NULL,
    tutto_il_giorno BOOLEAN DEFAULT FALSE,
    ricorrenza ENUM('no', 'giornaliera', 'settimanale', 'mensile', 'annuale') DEFAULT 'no',
    ricorrenza_fine DATE,
    colore VARCHAR(7) DEFAULT '#0066cc',
    creato_da INT,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultima_modifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (creato_da) REFERENCES utenti(id) ON DELETE SET NULL,
    INDEX idx_date (data_inizio, data_fine)
) ENGINE=InnoDB;

-- Tabella partecipanti eventi
CREATE TABLE IF NOT EXISTS partecipanti_eventi (
    id INT PRIMARY KEY AUTO_INCREMENT,
    evento_id INT NOT NULL,
    utente_id INT NOT NULL,
    stato ENUM('invitato', 'confermato', 'rifiutato', 'forse') DEFAULT 'invitato',
    notifica_email BOOLEAN DEFAULT TRUE,
    notifica_sms BOOLEAN DEFAULT FALSE,
    data_invito TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_risposta TIMESTAMP NULL,
    FOREIGN KEY (evento_id) REFERENCES eventi(id) ON DELETE CASCADE,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    UNIQUE KEY unique_participant (evento_id, utente_id),
    INDEX idx_evento (evento_id),
    INDEX idx_utente (utente_id)
) ENGINE=InnoDB;

-- Tabella notifiche
CREATE TABLE IF NOT EXISTS notifiche (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tipo ENUM('evento_creato', 'evento_modificato', 'evento_cancellato', 'documento_condiviso', 'documento_modificato') NOT NULL,
    utente_id INT NOT NULL,
    oggetto_tipo ENUM('evento', 'documento') NOT NULL,
    oggetto_id INT NOT NULL,
    titolo VARCHAR(200) NOT NULL,
    messaggio TEXT,
    letta BOOLEAN DEFAULT FALSE,
    email_inviata BOOLEAN DEFAULT FALSE,
    sms_inviato BOOLEAN DEFAULT FALSE,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    INDEX idx_utente_letta (utente_id, letta),
    INDEX idx_data (data_creazione)
) ENGINE=InnoDB;

-- Tabella log attività
CREATE TABLE IF NOT EXISTS log_attivita (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utente_id INT,
    azione VARCHAR(100) NOT NULL,
    tipo_oggetto VARCHAR(50),
    id_oggetto INT,
    dettagli JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    data_azione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE SET NULL,
    INDEX idx_utente (utente_id),
    INDEX idx_data (data_azione),
    INDEX idx_azione (azione)
) ENGINE=InnoDB;

-- Tabella configurazioni
CREATE TABLE IF NOT EXISTS configurazioni (
    id INT PRIMARY KEY AUTO_INCREMENT,
    chiave VARCHAR(100) UNIQUE NOT NULL,
    valore TEXT,
    tipo ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    descrizione TEXT,
    modificabile BOOLEAN DEFAULT TRUE,
    ultima_modifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Inserimento configurazioni predefinite
INSERT INTO configurazioni (chiave, valore, tipo, descrizione) VALUES
('smtp_host', 'smtp.gmail.com', 'string', 'Host server SMTP'),
('smtp_port', '587', 'number', 'Porta server SMTP'),
('smtp_username', '', 'string', 'Username SMTP'),
('smtp_password', '', 'string', 'Password SMTP'),
('smtp_encryption', 'tls', 'string', 'Tipo di crittografia SMTP'),
('sms_provider', 'twilio', 'string', 'Provider SMS'),
('sms_api_key', '', 'string', 'API Key provider SMS'),
('sms_api_secret', '', 'string', 'API Secret provider SMS'),
('sms_sender', '', 'string', 'Numero o nome mittente SMS'),
('timezone', 'Europe/Rome', 'string', 'Fuso orario del sistema'),
('date_format', 'd/m/Y', 'string', 'Formato data'),
('time_format', 'H:i', 'string', 'Formato ora'),
('session_timeout', '3600', 'number', 'Timeout sessione in secondi'),
('max_upload_size', '10485760', 'number', 'Dimensione massima upload in bytes (10MB)');

-- Creazione utente amministratore predefinito (password: admin123)
INSERT INTO utenti (username, password, email, nome, cognome, ruolo) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@piattaforma.it', 'Amministratore', 'Sistema', 'admin'); 