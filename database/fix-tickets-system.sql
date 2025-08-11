-- Fix tickets system tables
-- Ensure proper structure and AUTO_INCREMENT

-- First, ensure tickets table has proper primary key and AUTO_INCREMENT
ALTER TABLE tickets DROP PRIMARY KEY;
ALTER TABLE tickets ADD PRIMARY KEY (id);
ALTER TABLE tickets MODIFY id INT(11) NOT NULL AUTO_INCREMENT;

-- Fix ticket_risposte table
ALTER TABLE ticket_risposte DROP PRIMARY KEY;
ALTER TABLE ticket_risposte ADD PRIMARY KEY (id);
ALTER TABLE ticket_risposte MODIFY id INT(11) NOT NULL AUTO_INCREMENT;

-- Fix ticket_destinatari table (if it has an id column)
ALTER TABLE ticket_destinatari DROP PRIMARY KEY;
ALTER TABLE ticket_destinatari ADD PRIMARY KEY (id);
ALTER TABLE ticket_destinatari MODIFY id INT(11) NOT NULL AUTO_INCREMENT;

-- Ensure no records have ID = 0
UPDATE tickets SET id = (SELECT MAX(id) + 1 FROM (SELECT id FROM tickets) t) WHERE id = 0;
UPDATE ticket_risposte SET id = (SELECT MAX(id) + 1 FROM (SELECT id FROM ticket_risposte) t) WHERE id = 0;
UPDATE ticket_destinatari SET id = (SELECT MAX(id) + 1 FROM (SELECT id FROM ticket_destinatari) t) WHERE id = 0;

-- Set AUTO_INCREMENT values to proper next value
ALTER TABLE tickets AUTO_INCREMENT = 2;
ALTER TABLE ticket_risposte AUTO_INCREMENT = 1;
ALTER TABLE ticket_destinatari AUTO_INCREMENT = 1;