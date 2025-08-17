-- Fix Company Soft Delete Status
-- Date: 2025-08-12
-- Purpose: Ensure company deletion status is properly synchronized between UI and database

-- The system uses soft delete with 'stato' column:
-- 'attiva' = active/visible company
-- 'cancellata' = deleted/hidden company
-- 'sospesa' = suspended company (optional status)

-- Check current status of all companies
SELECT id, nome, stato, data_creazione 
FROM aziende 
ORDER BY id;

-- Fix status for companies based on requirements:
-- Sud Marmi (id: 5) should be active
UPDATE aziende SET stato = 'attiva' WHERE id = 5 AND stato != 'attiva';

-- Test 1 (id: 7) should be deleted  
UPDATE aziende SET stato = 'cancellata' WHERE id = 7 AND stato != 'cancellata';

-- Romolo Hospital (id: 8) should be deleted
UPDATE aziende SET stato = 'cancellata' WHERE id = 8 AND stato != 'cancellata';

-- Verify the changes
SELECT 
    id, 
    nome, 
    stato,
    CASE stato 
        WHEN 'attiva' THEN 'Visible in UI'
        WHEN 'cancellata' THEN 'Hidden (Soft Deleted)'
        WHEN 'sospesa' THEN 'Suspended'
        ELSE 'Unknown Status'
    END as status_description
FROM aziende 
ORDER BY stato, nome;

-- Count companies by status
SELECT 
    stato, 
    COUNT(*) as total 
FROM aziende 
GROUP BY stato;

-- IMPORTANT NOTES:
-- 1. The aziende.php page only shows companies where stato = 'attiva'
-- 2. Deletion is handled by backend/functions/aziende-functions.php::deleteAzienda()
-- 3. Soft delete updates stato to 'cancellata' and deactivates related users
-- 4. To permanently delete (hard delete), use: DELETE FROM aziende WHERE id = X;
--    BUT this may cause foreign key constraint errors if company has related data