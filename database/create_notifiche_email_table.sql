-- Tabella per gestire le notifiche email
CREATE TABLE IF NOT EXISTS `notifiche_email` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `destinatario_email` varchar(255) NOT NULL,
    `destinatario_nome` varchar(255) NOT NULL,
    `oggetto` varchar(255) NOT NULL,
    `contenuto` text NOT NULL,
    `tipo_notifica` varchar(50) NOT NULL,
    `azienda_id` int(11) DEFAULT NULL,
    `priorita` int(11) NOT NULL DEFAULT 5,
    `stato` enum('in_attesa','inviata','errore') NOT NULL DEFAULT 'in_attesa',
    `tentativi` int(11) NOT NULL DEFAULT 0,
    `errore_messaggio` text DEFAULT NULL,
    `data_creazione` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `data_invio` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_stato` (`stato`),
    KEY `idx_azienda` (`azienda_id`),
    KEY `idx_priorita` (`priorita`),
    KEY `idx_data_creazione` (`data_creazione`),
    CONSTRAINT `fk_notifiche_azienda` FOREIGN KEY (`azienda_id`) REFERENCES `aziende` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabella per i messaggi dei ticket (se non esiste)
CREATE TABLE IF NOT EXISTS `ticket_messaggi` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `ticket_id` int(11) NOT NULL,
    `utente_id` int(11) NOT NULL,
    `messaggio` text NOT NULL,
    `tipo` enum('messaggio','risposta','nota_interna','cambio_stato') NOT NULL DEFAULT 'messaggio',
    `data_invio` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ticket` (`ticket_id`),
    KEY `idx_utente` (`utente_id`),
    KEY `idx_data` (`data_invio`),
    CONSTRAINT `fk_messaggi_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_messaggi_utente` FOREIGN KEY (`utente_id`) REFERENCES `utenti` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aggiungi colonne mancanti alla tabella tickets se non esistono
ALTER TABLE `tickets` 
ADD COLUMN IF NOT EXISTS `codice` varchar(20) UNIQUE DEFAULT NULL AFTER `id`,
ADD COLUMN IF NOT EXISTS `categoria` varchar(50) DEFAULT 'altro' AFTER `descrizione`,
ADD COLUMN IF NOT EXISTS `priorita` enum('bassa','media','alta','urgente') DEFAULT 'media' AFTER `categoria`,
ADD COLUMN IF NOT EXISTS `stato` enum('aperto','in_lavorazione','in_attesa','risolto','chiuso') DEFAULT 'aperto' AFTER `priorita`,
ADD COLUMN IF NOT EXISTS `assegnato_a` int(11) DEFAULT NULL AFTER `utente_id`,
ADD COLUMN IF NOT EXISTS `data_aggiornamento` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `data_creazione`,
ADD COLUMN IF NOT EXISTS `data_risoluzione` timestamp NULL DEFAULT NULL AFTER `data_aggiornamento`,
ADD COLUMN IF NOT EXISTS `data_chiusura` timestamp NULL DEFAULT NULL AFTER `data_risoluzione`;

-- Indici per migliorare le performance
ALTER TABLE `tickets`
ADD INDEX IF NOT EXISTS `idx_codice` (`codice`),
ADD INDEX IF NOT EXISTS `idx_stato` (`stato`),
ADD INDEX IF NOT EXISTS `idx_priorita` (`priorita`),
ADD INDEX IF NOT EXISTS `idx_assegnato` (`assegnato_a`);

-- Vista per statistiche tickets
CREATE OR REPLACE VIEW v_ticket_stats AS
SELECT 
    t.azienda_id,
    a.nome as azienda_nome,
    COUNT(DISTINCT t.id) as totale_tickets,
    SUM(CASE WHEN t.stato = 'aperto' THEN 1 ELSE 0 END) as tickets_aperti,
    SUM(CASE WHEN t.stato = 'in_lavorazione' THEN 1 ELSE 0 END) as tickets_in_lavorazione,
    SUM(CASE WHEN t.stato = 'risolto' THEN 1 ELSE 0 END) as tickets_risolti,
    SUM(CASE WHEN t.stato = 'chiuso' THEN 1 ELSE 0 END) as tickets_chiusi,
    SUM(CASE WHEN t.priorita = 'urgente' THEN 1 ELSE 0 END) as tickets_urgenti,
    AVG(CASE 
        WHEN t.data_risoluzione IS NOT NULL 
        THEN TIMESTAMPDIFF(HOUR, t.data_creazione, t.data_risoluzione)
        ELSE NULL 
    END) as tempo_medio_risoluzione_ore
FROM tickets t
JOIN aziende a ON t.azienda_id = a.id
GROUP BY t.azienda_id, a.nome; 