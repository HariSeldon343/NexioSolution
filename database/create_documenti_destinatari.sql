-- Tabella per i destinatari dei documenti
CREATE TABLE IF NOT EXISTS `documenti_destinatari` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `documento_id` int(11) NOT NULL,
    `referente_id` int(11) NOT NULL,
    `tipo_destinatario` enum('principale','conoscenza') DEFAULT 'principale',
    `data_invio` datetime DEFAULT NULL,
    `data_lettura` datetime DEFAULT NULL,
    `creato_il` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_documento` (`documento_id`),
    KEY `idx_referente` (`referente_id`),
    CONSTRAINT `fk_documenti_destinatari_documento` FOREIGN KEY (`documento_id`) REFERENCES `documenti` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_documenti_destinatari_referente` FOREIGN KEY (`referente_id`) REFERENCES `referenti_aziende` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 