-- Tabella per i referenti aziendali (max 5 per azienda)
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

-- Tabella per il logging di tutte le attività
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

-- Tabella per le notifiche email programmate
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

-- Tabella per le preferenze notifiche amministratori
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

-- Vista per i log con informazioni complete
CREATE VIEW IF NOT EXISTS vista_log_attivita AS
SELECT 
    la.*,
    COALESCE(u.email, r.email) as email_utente,
    COALESCE(CONCAT(u.nome, ' ', u.cognome), CONCAT(r.nome, ' ', r.cognome)) as nome_completo,
    a.nome as nome_azienda,
    CASE 
        WHEN la.utente_id IS NOT NULL THEN 'utente_sistema'
        WHEN la.referente_id IS NOT NULL THEN 'referente_azienda'
        ELSE 'sconosciuto'
    END as tipo_utente
FROM log_attivita la
LEFT JOIN utenti u ON la.utente_id = u.id
LEFT JOIN referenti_aziende r ON la.referente_id = r.id
LEFT JOIN aziende a ON la.azienda_id = a.id
ORDER BY la.creato_il DESC; 