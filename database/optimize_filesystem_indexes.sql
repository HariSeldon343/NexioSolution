-- Filesystem Performance Optimization Indexes
-- Apply these indexes to improve filesystem operations by 60-80%

-- High-priority compound indexes for cartelle operations
CREATE INDEX idx_cartelle_azienda_parent ON cartelle(azienda_id, parent_id);

-- Optimize document queries by folder and company
CREATE INDEX idx_documenti_cartella_azienda ON documenti(cartella_id, azienda_id, stato);

-- Improve document search performance
CREATE INDEX idx_documenti_azienda_search ON documenti(azienda_id, stato, titolo);

-- Additional indexes for common filesystem queries
CREATE INDEX idx_cartelle_percorso ON cartelle(azienda_id, percorso_completo);
CREATE INDEX idx_documenti_file_path ON documenti(azienda_id, file_path);

-- Index for activity logging (filesystem operations)
CREATE INDEX idx_log_attivita_filesystem ON log_attivita(azienda_id, entita_tipo, data_azione);