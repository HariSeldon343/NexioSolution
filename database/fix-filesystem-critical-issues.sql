-- CRITICAL FILESYSTEM FIXES - Apply these immediately
-- Date: 2025-07-30
-- Purpose: Fix folder creation, deletion, and upload issues

-- 1. FIX FOLDER CREATION ISSUES
-- Remove problematic UNIQUE constraint that causes silent failures
ALTER TABLE cartelle DROP INDEX IF EXISTS uk_cartelle_nome_parent_azienda;

-- Add more flexible constraint
ALTER TABLE cartelle 
ADD CONSTRAINT chk_cartelle_nome_length 
CHECK (LENGTH(nome) >= 1 AND LENGTH(nome) <= 200);

-- Add performance indexes
CREATE INDEX IF NOT EXISTS idx_cartelle_lookup ON cartelle(azienda_id, parent_id, nome);
CREATE INDEX IF NOT EXISTS idx_cartelle_parent ON cartelle(parent_id);

-- 2. FIX FOLDER DELETION ISSUES  
-- Update foreign key constraints to handle cascading properly
ALTER TABLE cartelle DROP FOREIGN KEY IF EXISTS fk_cartelle_parent;
ALTER TABLE cartelle 
ADD CONSTRAINT fk_cartelle_parent 
FOREIGN KEY (parent_id) REFERENCES cartelle(id) 
ON DELETE CASCADE ON UPDATE CASCADE;

-- Ensure documenti can handle null cartella_id
ALTER TABLE documenti MODIFY COLUMN cartella_id INT NULL;

-- 3. FIX UPLOAD ISSUES
-- Add missing fields for proper file management
ALTER TABLE documenti 
ADD COLUMN IF NOT EXISTS tipo_mime VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS hash_file VARCHAR(64) NULL,
ADD COLUMN IF NOT EXISTS tags JSON NULL,
ADD COLUMN IF NOT EXISTS dimensione_file BIGINT NULL;

-- Add index for file lookup
CREATE INDEX IF NOT EXISTS idx_documenti_hash ON documenti(hash_file);
CREATE INDEX IF NOT EXISTS idx_documenti_cartella ON documenti(cartella_id, azienda_id);

-- 4. ENSURE PROPER DEFAULTS
-- Update documenti table to handle proper defaults
ALTER TABLE documenti 
MODIFY COLUMN stato ENUM('bozza', 'pubblicato', 'archiviato') DEFAULT 'pubblicato',
MODIFY COLUMN versione INT DEFAULT 1;

-- 5. ADD HELPFUL FUNCTIONS
DELIMITER //

-- Function to clean up orphaned folders
CREATE PROCEDURE IF NOT EXISTS CleanupOrphanedFolders()
BEGIN
    -- Remove folders with invalid parent references
    DELETE FROM cartelle 
    WHERE parent_id IS NOT NULL 
    AND parent_id NOT IN (SELECT DISTINCT id FROM cartelle AS c2);
    
    -- Update percorso_completo for all folders
    UPDATE cartelle c1 
    SET percorso_completo = (
        SELECT GROUP_CONCAT(c2.nome ORDER BY level ASC SEPARATOR '/')
        FROM (
            SELECT c3.id, c3.nome, 
                   @level := CASE WHEN @parent = c3.parent_id THEN @level + 1 ELSE 0 END AS level,
                   @parent := c3.id
            FROM cartelle c3
            WHERE c3.azienda_id = c1.azienda_id
            ORDER BY c3.parent_id, c3.id
        ) c2
        WHERE c2.id = c1.id
    );
END //

DELIMITER ;

-- Show completion message
SELECT 'CRITICAL FILESYSTEM FIXES APPLIED SUCCESSFULLY' AS status;