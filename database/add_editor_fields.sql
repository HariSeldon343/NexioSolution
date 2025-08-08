-- Aggiunge i campi necessari per l'editor completo alla tabella documenti

-- Aggiunge campo per il frontespizio
ALTER TABLE documenti ADD COLUMN frontespizio LONGTEXT DEFAULT NULL AFTER contenuto_html;

-- Aggiunge campo per collegare il template
ALTER TABLE documenti ADD COLUMN template_id INT DEFAULT NULL AFTER frontespizio;

-- Aggiunge indice per migliorare le performance
ALTER TABLE documenti ADD INDEX idx_template_id (template_id);
ALTER TABLE documenti ADD INDEX idx_user_updated (user_id, updated_at);

-- Aggiunge foreign key per template_id se la tabella moduli_template esiste
-- ALTER TABLE documenti ADD FOREIGN KEY (template_id) REFERENCES moduli_template(id) ON DELETE SET NULL; 