-- Script correzione database Nexio
-- Generato: 2025-08-08 18:14:41

ALTER TABLE `aziende` ADD PRIMARY KEY (`id`);
ALTER TABLE `aziende` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `utenti_aziende` ADD PRIMARY KEY (`id`);
ALTER TABLE `utenti_aziende` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `eventi` ADD PRIMARY KEY (`id`);
ALTER TABLE `eventi` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `tickets` ADD PRIMARY KEY (`id`);
ALTER TABLE `tickets` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `task_calendario` ADD PRIMARY KEY (`id`);
ALTER TABLE `task_calendario` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `referenti` ADD PRIMARY KEY (`id`);
ALTER TABLE `referenti` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT;
DELETE FROM utenti_aziende WHERE utente_id NOT IN (SELECT id FROM utenti) OR azienda_id NOT IN (SELECT id FROM aziende);