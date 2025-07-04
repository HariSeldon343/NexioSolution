-- Aggiungo campo contenuto_html alla tabella documenti
ALTER TABLE documenti ADD COLUMN contenuto_html LONGTEXT DEFAULT NULL AFTER contenuto; 