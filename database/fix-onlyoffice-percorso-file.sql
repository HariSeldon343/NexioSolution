-- Fix OnlyOffice Integration: Add missing percorso_file column
-- This column is required for OnlyOffice document storage paths

-- Check if percorso_file column exists, if not add it
ALTER TABLE documenti 
ADD COLUMN IF NOT EXISTS percorso_file VARCHAR(500) DEFAULT NULL 
COMMENT 'Percorso del file per OnlyOffice' 
AFTER file_path;

-- Add index for faster lookups
ALTER TABLE documenti 
ADD INDEX IF NOT EXISTS idx_percorso_file (percorso_file);

-- Update existing records to use file_path value if percorso_file is empty
UPDATE documenti 
SET percorso_file = file_path 
WHERE percorso_file IS NULL AND file_path IS NOT NULL;

-- For OnlyOffice documents, update path format
UPDATE documenti 
SET percorso_file = CONCAT('documents/onlyoffice/', id, '_', UNIX_TIMESTAMP(), '.docx')
WHERE percorso_file IS NULL 
AND nome_file LIKE '%.docx';

-- Display result
SELECT 
    'percorso_file column added successfully' as Status,
    COUNT(*) as Total_Documents,
    SUM(CASE WHEN percorso_file IS NOT NULL THEN 1 ELSE 0 END) as Documents_With_Path
FROM documenti;