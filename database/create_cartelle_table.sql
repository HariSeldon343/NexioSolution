-- Creazione tabella cartelle per il sistema filesystem
-- NOTA: creato_da e aggiornato_da permettono NULL per gestire utenti eliminati
CREATE TABLE IF NOT EXISTS `cartelle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `percorso_completo` text NOT NULL,
  `livello` int(11) NOT NULL DEFAULT 0,
  `azienda_id` int(11) NOT NULL,
  `creato_da` int(11) DEFAULT NULL, -- Permette NULL per flessibilità
  `data_creazione` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `aggiornato_da` int(11) DEFAULT NULL,
  `data_aggiornamento` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `colore` varchar(7) DEFAULT '#fbbf24',
  PRIMARY KEY (`id`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_azienda` (`azienda_id`),
  KEY `idx_creato_da` (`creato_da`),
  KEY `idx_percorso` (`percorso_completo`(255)),
  CONSTRAINT `fk_cartelle_azienda` FOREIGN KEY (`azienda_id`) REFERENCES `aziende` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cartelle_parent` FOREIGN KEY (`parent_id`) REFERENCES `cartelle` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cartelle_creato_da` FOREIGN KEY (`creato_da`) REFERENCES `utenti` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cartelle_aggiornato_da` FOREIGN KEY (`aggiornato_da`) REFERENCES `utenti` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Indice per ottimizzare le ricerche gerarchiche
CREATE INDEX idx_cartelle_gerarchia ON cartelle(azienda_id, parent_id, nome);

-- Aggiungi colonna cartella_id alla tabella documenti se non esiste
ALTER TABLE `documenti` 
ADD COLUMN IF NOT EXISTS `cartella_id` int(11) DEFAULT NULL,
ADD KEY IF NOT EXISTS `idx_cartella` (`cartella_id`),
ADD CONSTRAINT IF NOT EXISTS `fk_documenti_cartella` FOREIGN KEY (`cartella_id`) REFERENCES `cartelle` (`id`) ON DELETE SET NULL;