-- Semplificare la logica dei ruoli utenti
-- Manteniamo solo: super_admin, utente_speciale, utente

-- Prima, aggiorna tutti i ruoli legacy a quelli nuovi
UPDATE utenti SET ruolo = 'utente' WHERE ruolo IN ('staff', 'cliente', 'admin');

-- Adesso modifica l'ENUM per contenere solo i ruoli desiderati
ALTER TABLE utenti 
MODIFY COLUMN ruolo ENUM('super_admin', 'utente_speciale', 'utente') NOT NULL DEFAULT 'utente';

-- Aggiorna il commento della tabella
ALTER TABLE utenti COMMENT = 'Users table with simplified roles: super_admin (full access), utente_speciale (admin without company restriction), utente (standard company user)';

-- Crea una vista per la gestione dei ruoli
CREATE OR REPLACE VIEW v_user_roles AS
SELECT 
    u.id,
    u.nome,
    u.cognome,
    u.email,
    u.ruolo,
    u.azienda_id,
    a.nome as azienda_nome,
    CASE 
        WHEN u.ruolo = 'super_admin' THEN 'Amministratore Sistema'
        WHEN u.ruolo = 'utente_speciale' THEN 'Utente Speciale'
        WHEN u.ruolo = 'utente' THEN 'Utente Standard'
        ELSE 'Sconosciuto'
    END as ruolo_descrizione,
    CASE 
        WHEN u.ruolo = 'super_admin' THEN TRUE
        WHEN u.ruolo = 'utente_speciale' THEN TRUE
        ELSE FALSE
    END as ha_privilegi_elevati,
    CASE 
        WHEN u.ruolo = 'super_admin' THEN TRUE
        ELSE FALSE
    END as puo_gestire_aziende
FROM utenti u
LEFT JOIN aziende a ON u.azienda_id = a.id;

-- Assicurati che ci sia almeno un super_admin
INSERT IGNORE INTO utenti (nome, cognome, email, password, ruolo, stato, created_by, primo_accesso) 
SELECT 
    'Super', 
    'Admin',
    'admin@nexio.local',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
    'super_admin',
    'attivo',
    NULL,
    TRUE
WHERE NOT EXISTS (SELECT 1 FROM utenti WHERE ruolo = 'super_admin' LIMIT 1);