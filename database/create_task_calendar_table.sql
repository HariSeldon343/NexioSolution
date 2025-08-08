-- Tabella per i task assegnati nel calendario
-- Estende il sistema calendario per gestire task di consulenza/operation

CREATE TABLE IF NOT EXISTS task_calendario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Utente assegnato
    utente_assegnato_id INT NOT NULL,
    
    -- Campi obbligatori del task
    attivita ENUM('Consulenza', 'Operation', 'Verifica', 'Office') NOT NULL,
    giornate_previste DECIMAL(3,1) NOT NULL CHECK (giornate_previste >= 0 AND giornate_previste <= 15),
    costo_giornata DECIMAL(10,2) NOT NULL,
    azienda_id INT NOT NULL,
    citta VARCHAR(255) NOT NULL,
    
    -- Prodotto/Servizio con opzioni predefinite + campo libero
    prodotto_servizio_tipo ENUM('predefinito', 'personalizzato') DEFAULT 'predefinito',
    prodotto_servizio_predefinito ENUM('9001', '14001', '27001', '45001', 'Autorizzazione', 'Accreditamento') NULL,
    prodotto_servizio_personalizzato VARCHAR(255) NULL,
    
    -- Date del task
    data_inizio DATE NOT NULL,
    data_fine DATE NOT NULL,
    
    -- Descrizione e note
    descrizione TEXT,
    note TEXT,
    
    -- Stato del task
    stato ENUM('assegnato', 'in_corso', 'completato', 'annullato') DEFAULT 'assegnato',
    
    -- Collegamento con evento calendario (opzionale)
    evento_id INT NULL,
    
    -- Tracking
    assegnato_da INT NOT NULL,
    data_assegnazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultima_modifica TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completato_il TIMESTAMP NULL,
    
    -- Foreign Keys
    FOREIGN KEY (utente_assegnato_id) REFERENCES utenti(id) ON DELETE CASCADE,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (assegnato_da) REFERENCES utenti(id) ON DELETE SET NULL,
    FOREIGN KEY (evento_id) REFERENCES eventi(id) ON DELETE SET NULL,
    
    -- Indexes
    INDEX idx_utente_assegnato (utente_assegnato_id),
    INDEX idx_data_inizio (data_inizio),
    INDEX idx_data_fine (data_fine),
    INDEX idx_attivita (attivita),
    INDEX idx_stato (stato),
    INDEX idx_azienda (azienda_id),
    
    -- Constraint per garantire coerenza prodotto/servizio
    CONSTRAINT chk_prodotto_servizio CHECK (
        (prodotto_servizio_tipo = 'predefinito' AND prodotto_servizio_predefinito IS NOT NULL AND prodotto_servizio_personalizzato IS NULL) OR
        (prodotto_servizio_tipo = 'personalizzato' AND prodotto_servizio_predefinito IS NULL AND prodotto_servizio_personalizzato IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vista per conteggio giornate per tipo di attività
CREATE OR REPLACE VIEW vista_conteggio_giornate_task AS
SELECT 
    utente_assegnato_id,
    attivita,
    SUM(giornate_previste) as totale_giornate,
    COUNT(*) as numero_task,
    SUM(CASE WHEN stato = 'completato' THEN giornate_previste ELSE 0 END) as giornate_completate,
    SUM(CASE WHEN stato IN ('assegnato', 'in_corso') THEN giornate_previste ELSE 0 END) as giornate_pianificate
FROM task_calendario
WHERE stato != 'annullato'
GROUP BY utente_assegnato_id, attivita;

-- Trigger per creare automaticamente un evento quando viene assegnato un task
DELIMITER $$
CREATE TRIGGER after_task_insert 
AFTER INSERT ON task_calendario
FOR EACH ROW
BEGIN
    DECLARE evento_titolo VARCHAR(255);
    DECLARE evento_descrizione TEXT;
    
    -- Costruisci il titolo dell'evento
    SET evento_titolo = CONCAT(
        'Task: ', 
        NEW.attivita, 
        ' - ',
        CASE 
            WHEN NEW.prodotto_servizio_tipo = 'predefinito' THEN NEW.prodotto_servizio_predefinito
            ELSE NEW.prodotto_servizio_personalizzato
        END
    );
    
    -- Costruisci la descrizione dell'evento
    SET evento_descrizione = CONCAT(
        'Attività: ', NEW.attivita, '\n',
        'Giornate previste: ', NEW.giornate_previste, '\n',
        'Città: ', NEW.citta, '\n',
        IFNULL(CONCAT('Note: ', NEW.descrizione), '')
    );
    
    -- Inserisci l'evento nel calendario
    INSERT INTO eventi (
        titolo,
        descrizione,
        data_inizio,
        data_fine,
        luogo,
        tipo,
        azienda_id,
        creato_da,
        creato_il
    ) VALUES (
        evento_titolo,
        evento_descrizione,
        CONCAT(NEW.data_inizio, ' 09:00:00'),
        CONCAT(NEW.data_fine, ' 18:00:00'),
        NEW.citta,
        'altro',
        NEW.azienda_id,
        NEW.assegnato_da,
        NOW()
    );
    
    -- Aggiorna il task con l'ID dell'evento creato
    UPDATE task_calendario 
    SET evento_id = LAST_INSERT_ID() 
    WHERE id = NEW.id;
    
    -- Aggiungi l'utente assegnato come partecipante all'evento
    INSERT INTO evento_partecipanti (
        evento_id,
        utente_id,
        stato_partecipazione,
        creato_il
    ) VALUES (
        LAST_INSERT_ID(),
        NEW.utente_assegnato_id,
        'confermato',
        NOW()
    );
END$$
DELIMITER ;