-- Add utente_speciale role to utenti table
-- This role has elevated privileges but cannot delete users, companies or logs

ALTER TABLE utenti 
MODIFY COLUMN ruolo ENUM('super_admin', 'admin', 'utente', 'utente_speciale', 'staff', 'cliente') NOT NULL DEFAULT 'utente';

-- Add a comment to document the role purposes
ALTER TABLE utenti COMMENT = 'Users table with roles: super_admin (full access), utente_speciale (admin without delete), admin (legacy), utente (standard user), staff (legacy), cliente (legacy)';