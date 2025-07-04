-- Tabella per il versioning dei documenti
CREATE TABLE IF NOT EXISTS `documenti_versioni` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `documento_id` int(11) NOT NULL,
    `versione` int(11) NOT NULL,
    `titolo` varchar(255) NOT NULL,
    `contenuto` longtext,
    `stato` enum('bozza','pubblicato','archiviato') DEFAULT 'bozza',
    `creato_da` int(11) NOT NULL,
    `creato_il` datetime NOT NULL,
    `note_versione` text,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_documento_versione` (`documento_id`, `versione`),
    KEY `idx_documento` (`documento_id`),
    KEY `idx_creato_da` (`creato_da`),
    CONSTRAINT `fk_documenti_versioni_documento` FOREIGN KEY (`documento_id`) REFERENCES `documenti` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_documenti_versioni_utente` FOREIGN KEY (`creato_da`) REFERENCES `utenti` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 