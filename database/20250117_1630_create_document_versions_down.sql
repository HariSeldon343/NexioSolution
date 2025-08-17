-- Rollback: Rimozione sistema di versionamento documenti
-- Data: 2025-01-17 16:30
-- ATTENZIONE: Questo script rimuoverà TUTTI i dati di versionamento!

START TRANSACTION;

-- Rimuovi foreign key dalla tabella documenti
ALTER TABLE `documenti` 
DROP FOREIGN KEY IF EXISTS `fk_documenti_current_version`;

-- Rimuovi colonne aggiunte a documenti
ALTER TABLE `documenti`
DROP COLUMN IF EXISTS `current_version_id`,
DROP COLUMN IF EXISTS `contenuto_html`,
DROP COLUMN IF EXISTS `enable_versioning`;

-- Rimuovi indice
ALTER TABLE `documenti`
DROP INDEX IF EXISTS `idx_current_version`;

-- Elimina le tabelle di supporto
DROP TABLE IF EXISTS `document_version_comparisons`;
DROP TABLE IF EXISTS `document_version_views`;
DROP TABLE IF EXISTS `document_versions`;

-- Elimina viste
DROP VIEW IF EXISTS `v_latest_document_versions`;

-- Elimina stored procedures
DROP PROCEDURE IF EXISTS `create_document_version`;

COMMIT;

SELECT 'Rollback completato. Sistema di versionamento rimosso.' as risultato;