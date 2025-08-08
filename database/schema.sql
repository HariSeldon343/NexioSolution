-- Schema database per piattaforma collaborativa
-- Compatibile con MySQL

CREATE DATABASE IF NOT EXISTS NexioSol CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE NexioSol;

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

-- Tabella per definire i moduli del sistema
CREATE TABLE IF NOT EXISTS moduli_sistema (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codice VARCHAR(50) UNIQUE NOT NULL,
    nome VARCHAR(100) NOT NULL,
    descrizione TEXT,
    icona VARCHAR(50),
    colore VARCHAR(7),
    url_base VARCHAR(255),
    ordine INT DEFAULT 0,
    attivo BOOLEAN DEFAULT TRUE,
    richiede_licenza BOOLEAN DEFAULT TRUE,
    configurazione JSON,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_codice (codice),
    INDEX idx_attivo (attivo)
) ENGINE=InnoDB;

-- Tabella per associare moduli alle aziende
CREATE TABLE IF NOT EXISTS aziende_moduli (
    id INT AUTO_INCREMENT PRIMARY KEY,
    azienda_id INT NOT NULL,
    modulo_id INT NOT NULL,
    attivo BOOLEAN DEFAULT TRUE,
    data_attivazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_disattivazione TIMESTAMP NULL,
    data_scadenza DATE NULL,
    configurazione_custom JSON,
    note TEXT,
    attivato_da INT,
    disattivato_da INT NULL,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_azienda_modulo (azienda_id, modulo_id),
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (modulo_id) REFERENCES moduli_sistema(id) ON DELETE CASCADE,
    FOREIGN KEY (attivato_da) REFERENCES utenti(id),
    FOREIGN KEY (disattivato_da) REFERENCES utenti(id),
    INDEX idx_azienda (azienda_id),
    INDEX idx_modulo (modulo_id),
    INDEX idx_attivo (attivo)
) ENGINE=InnoDB;

-- Inserimento moduli di sistema predefiniti
INSERT INTO moduli_sistema (codice, nome, descrizione, icona, colore, url_base, ordine) VALUES
('EVENTI', 'Gestione Eventi', 'Calendario eventi e gestione partecipanti', 'fas fa-calendar-alt', '#0066cc', '/calendario-eventi.php', 1),
('FILESYSTEM', 'File Manager', 'Gestione documenti e file aziendali', 'fas fa-folder-open', '#f59e0b', '/filesystem.php', 2),
('TICKETS', 'Sistema Ticket', 'Gestione ticket e supporto', 'fas fa-ticket-alt', '#10b981', '/tickets.php', 3),
('CONFORMITA', 'Conformità Normativa', 'Gestione certificazioni ISO e autorizzazioni', 'fas fa-certificate', '#8b5cf6', '/conformita.php', 4);

-- Tabella per i requisiti delle certificazioni ISO
CREATE TABLE IF NOT EXISTS certificazioni_iso (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codice VARCHAR(20) UNIQUE NOT NULL,
    nome VARCHAR(100) NOT NULL,
    descrizione TEXT,
    versione VARCHAR(20),
    icona VARCHAR(50),
    colore VARCHAR(7),
    attiva BOOLEAN DEFAULT TRUE,
    creata_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aggiornata_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_codice (codice),
    INDEX idx_attiva (attiva)
) ENGINE=InnoDB;

-- Tabella per i requisiti specifici di ogni certificazione
CREATE TABLE IF NOT EXISTS requisiti_certificazione (
    id INT AUTO_INCREMENT PRIMARY KEY,
    certificazione_id INT NOT NULL,
    codice_requisito VARCHAR(50) NOT NULL,
    titolo VARCHAR(255) NOT NULL,
    descrizione TEXT,
    categoria VARCHAR(100),
    tipo ENUM('obbligatorio', 'raccomandato', 'opzionale') DEFAULT 'obbligatorio',
    parent_id INT NULL,
    ordine INT DEFAULT 0,
    note TEXT,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (certificazione_id) REFERENCES certificazioni_iso(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES requisiti_certificazione(id) ON DELETE SET NULL,
    INDEX idx_certificazione (certificazione_id),
    INDEX idx_parent (parent_id),
    UNIQUE KEY unique_cert_requisito (certificazione_id, codice_requisito)
) ENGINE=InnoDB;

-- Inserimento certificazioni ISO predefinite
INSERT INTO certificazioni_iso (codice, nome, descrizione, versione, icona, colore) VALUES
('ISO9001', 'ISO 9001', 'Sistema di Gestione della Qualità', '2015', 'fas fa-medal', '#3b82f6'),
('ISO14001', 'ISO 14001', 'Sistema di Gestione Ambientale', '2015', 'fas fa-leaf', '#10b981'),
('ISO45001', 'ISO 45001', 'Sistema di Gestione della Salute e Sicurezza sul Lavoro', '2018', 'fas fa-hard-hat', '#f59e0b');

-- Tabella per le autorizzazioni sanitarie regionali
CREATE TABLE IF NOT EXISTS autorizzazioni_sanitarie (
    id INT AUTO_INCREMENT PRIMARY KEY,
    regione ENUM('calabria', 'sicilia') NOT NULL,
    tipo_struttura VARCHAR(100) NOT NULL,
    codice VARCHAR(50) UNIQUE NOT NULL,
    nome VARCHAR(255) NOT NULL,
    descrizione TEXT,
    normativa_riferimento TEXT,
    icona VARCHAR(50),
    attiva BOOLEAN DEFAULT TRUE,
    creata_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aggiornata_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_regione (regione),
    INDEX idx_tipo (tipo_struttura),
    INDEX idx_codice (codice)
) ENGINE=InnoDB;

-- Tabella per i requisiti delle autorizzazioni sanitarie
CREATE TABLE IF NOT EXISTS requisiti_autorizzazione (
    id INT AUTO_INCREMENT PRIMARY KEY,
    autorizzazione_id INT NOT NULL,
    codice_requisito VARCHAR(50) NOT NULL,
    titolo VARCHAR(255) NOT NULL,
    descrizione TEXT,
    categoria VARCHAR(100),
    tipo ENUM('strutturale', 'organizzativo', 'tecnologico', 'personale') DEFAULT 'strutturale',
    obbligatorio BOOLEAN DEFAULT TRUE,
    parent_id INT NULL,
    ordine INT DEFAULT 0,
    riferimento_normativo TEXT,
    note TEXT,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (autorizzazione_id) REFERENCES autorizzazioni_sanitarie(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES requisiti_autorizzazione(id) ON DELETE SET NULL,
    INDEX idx_autorizzazione (autorizzazione_id),
    INDEX idx_parent (parent_id),
    UNIQUE KEY unique_auth_requisito (autorizzazione_id, codice_requisito)
) ENGINE=InnoDB;

-- Tabella per tracciare il progresso della conformità per azienda
CREATE TABLE IF NOT EXISTS conformita_azienda (
    id INT AUTO_INCREMENT PRIMARY KEY,
    azienda_id INT NOT NULL,
    tipo ENUM('certificazione', 'autorizzazione') NOT NULL,
    riferimento_id INT NOT NULL, -- ID della certificazione o autorizzazione
    stato ENUM('in_preparazione', 'in_corso', 'completata', 'scaduta', 'sospesa') DEFAULT 'in_preparazione',
    percentuale_completamento DECIMAL(5,2) DEFAULT 0.00,
    data_inizio DATE,
    data_target DATE,
    data_completamento DATE NULL,
    data_scadenza DATE NULL,
    note TEXT,
    responsabile_id INT,
    creata_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aggiornata_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (responsabile_id) REFERENCES utenti(id),
    INDEX idx_azienda (azienda_id),
    INDEX idx_tipo (tipo),
    INDEX idx_stato (stato)
) ENGINE=InnoDB;

-- Tabella per le checklist di conformità
CREATE TABLE IF NOT EXISTS checklist_conformita (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conformita_id INT NOT NULL,
    requisito_id INT NOT NULL,
    tipo_requisito ENUM('certificazione', 'autorizzazione') NOT NULL,
    stato ENUM('non_iniziato', 'in_corso', 'completato', 'non_applicabile') DEFAULT 'non_iniziato',
    percentuale_completamento DECIMAL(5,2) DEFAULT 0.00,
    data_verifica DATE NULL,
    verificato_da INT NULL,
    evidenze TEXT,
    note TEXT,
    documento_riferimento_id INT NULL,
    creata_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aggiornata_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (conformita_id) REFERENCES conformita_azienda(id) ON DELETE CASCADE,
    FOREIGN KEY (verificato_da) REFERENCES utenti(id),
    FOREIGN KEY (documento_riferimento_id) REFERENCES documenti(id),
    INDEX idx_conformita (conformita_id),
    INDEX idx_stato (stato)
) ENGINE=InnoDB; 