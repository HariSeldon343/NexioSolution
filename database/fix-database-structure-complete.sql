-- Script completo correzione struttura database Nexio
-- Generato: 2025-08-08
-- Questo script corregge PRIMARY KEY e AUTO_INCREMENT per tutte le tabelle critiche

-- Tabella aziende (ha già PRIMARY KEY ma manca AUTO_INCREMENT)
ALTER TABLE `aziende` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;

-- Tabella eventi (manca PRIMARY KEY e AUTO_INCREMENT)
ALTER TABLE `eventi` ADD PRIMARY KEY (`id`);
ALTER TABLE `eventi` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;

-- Tabella tickets (manca PRIMARY KEY e AUTO_INCREMENT)
ALTER TABLE `tickets` ADD PRIMARY KEY (`id`);
ALTER TABLE `tickets` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;

-- Tabella task_calendario (manca PRIMARY KEY e AUTO_INCREMENT)
ALTER TABLE `task_calendario` ADD PRIMARY KEY (`id`);
ALTER TABLE `task_calendario` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;

-- Tabella referenti (manca PRIMARY KEY e AUTO_INCREMENT)
ALTER TABLE `referenti` ADD PRIMARY KEY (`id`);
ALTER TABLE `referenti` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;

-- Tabella utenti_aziende (manca PRIMARY KEY e AUTO_INCREMENT)
ALTER TABLE `utenti_aziende` ADD PRIMARY KEY (`id`);
ALTER TABLE `utenti_aziende` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;

-- Pulizia associazioni non valide
DELETE FROM utenti_aziende 
WHERE utente_id NOT IN (SELECT id FROM utenti) 
   OR azienda_id NOT IN (SELECT id FROM aziende);

-- Aggiungi indici mancanti per performance
-- Indici per eventi
ALTER TABLE `eventi` ADD INDEX IF NOT EXISTS `idx_azienda_id` (`azienda_id`);
ALTER TABLE `eventi` ADD INDEX IF NOT EXISTS `idx_creato_da` (`creato_da`);
ALTER TABLE `eventi` ADD INDEX IF NOT EXISTS `idx_data_inizio` (`data_inizio`);

-- Indici per tickets
ALTER TABLE `tickets` ADD INDEX IF NOT EXISTS `idx_azienda_id` (`azienda_id`);
ALTER TABLE `tickets` ADD INDEX IF NOT EXISTS `idx_utente_id` (`utente_id`);
ALTER TABLE `tickets` ADD INDEX IF NOT EXISTS `idx_stato` (`stato`);
ALTER TABLE `tickets` ADD INDEX IF NOT EXISTS `idx_priorita` (`priorita`);

-- Indici per task_calendario
ALTER TABLE `task_calendario` ADD INDEX IF NOT EXISTS `idx_azienda_id` (`azienda_id`);
ALTER TABLE `task_calendario` ADD INDEX IF NOT EXISTS `idx_utente_assegnato` (`utente_assegnato_id`);
ALTER TABLE `task_calendario` ADD INDEX IF NOT EXISTS `idx_data_inizio` (`data_inizio`);
ALTER TABLE `task_calendario` ADD INDEX IF NOT EXISTS `idx_stato` (`stato`);

-- Indici per referenti
ALTER TABLE `referenti` ADD INDEX IF NOT EXISTS `idx_azienda_id` (`azienda_id`);
ALTER TABLE `referenti` ADD INDEX IF NOT EXISTS `idx_attivo` (`attivo`);

-- Indici per utenti_aziende
ALTER TABLE `utenti_aziende` ADD INDEX IF NOT EXISTS `idx_utente_id` (`utente_id`);
ALTER TABLE `utenti_aziende` ADD INDEX IF NOT EXISTS `idx_azienda_id` (`azienda_id`);
ALTER TABLE `utenti_aziende` ADD INDEX IF NOT EXISTS `idx_attivo` (`attivo`);

-- Verifica finale
SELECT 'Correzioni completate!' as status;