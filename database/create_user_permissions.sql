-- Tabella per i permessi granulari degli utenti
CREATE TABLE IF NOT EXISTS utenti_permessi (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utente_id INT NOT NULL,
    azienda_id INT NOT NULL,
    
    -- Permessi documenti
    puo_vedere_documenti BOOLEAN DEFAULT FALSE,
    puo_creare_documenti BOOLEAN DEFAULT FALSE,
    puo_modificare_documenti BOOLEAN DEFAULT FALSE,
    puo_eliminare_documenti BOOLEAN DEFAULT FALSE,
    puo_scaricare_documenti BOOLEAN DEFAULT FALSE,
    
    -- Permessi moduli
    puo_compilare_moduli BOOLEAN DEFAULT FALSE,
    
    -- Permessi ticket
    puo_aprire_ticket BOOLEAN DEFAULT FALSE,
    
    -- Permessi eventi
    puo_gestire_eventi BOOLEAN DEFAULT FALSE,
    
    -- Permessi referenti
    puo_vedere_referenti BOOLEAN DEFAULT FALSE,
    puo_gestire_referenti BOOLEAN DEFAULT FALSE,
    
    -- Permessi log
    puo_vedere_log_attivita BOOLEAN DEFAULT FALSE,
    
    -- Permessi notifiche
    riceve_notifiche_email BOOLEAN DEFAULT TRUE,
    
    -- Metadata
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_company_permissions (utente_id, azienda_id),
    INDEX idx_utente (utente_id),
    INDEX idx_azienda (azienda_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migrazione: copia i permessi esistenti dalla tabella utenti_aziende
INSERT INTO utenti_permessi (utente_id, azienda_id, puo_vedere_documenti, puo_creare_documenti, 
                            puo_modificare_documenti, puo_eliminare_documenti, puo_scaricare_documenti,
                            puo_compilare_moduli, puo_aprire_ticket, puo_gestire_eventi,
                            puo_vedere_referenti, puo_gestire_referenti, puo_vedere_log_attivita)
SELECT 
    ua.utente_id,
    ua.azienda_id,
    -- Permessi base per ruolo
    TRUE as puo_vedere_documenti, -- Tutti possono vedere
    CASE 
        WHEN ua.ruolo_azienda IN ('proprietario', 'admin', 'utente') THEN TRUE 
        ELSE FALSE 
    END as puo_creare_documenti,
    CASE 
        WHEN ua.ruolo_azienda IN ('proprietario', 'admin', 'utente') THEN TRUE 
        ELSE FALSE 
    END as puo_modificare_documenti,
    CASE 
        WHEN ua.ruolo_azienda IN ('proprietario', 'admin') THEN TRUE 
        ELSE FALSE 
    END as puo_eliminare_documenti,
    TRUE as puo_scaricare_documenti, -- Tutti possono scaricare
    TRUE as puo_compilare_moduli, -- Tutti possono compilare
    TRUE as puo_aprire_ticket, -- Tutti possono aprire ticket
    CASE 
        WHEN ua.ruolo_azienda IN ('proprietario', 'admin') THEN TRUE 
        ELSE FALSE 
    END as puo_gestire_eventi,
    TRUE as puo_vedere_referenti, -- Tutti possono vedere
    CASE 
        WHEN ua.ruolo_azienda IN ('proprietario', 'admin') THEN TRUE 
        ELSE FALSE 
    END as puo_gestire_referenti,
    CASE 
        WHEN ua.ruolo_azienda IN ('proprietario', 'admin') THEN TRUE 
        ELSE FALSE 
    END as puo_vedere_log_attivita
FROM utenti_aziende ua
WHERE ua.attivo = 1
ON DUPLICATE KEY UPDATE utente_id = utente_id; -- Non fare nulla se esiste già 