-- ================================================
-- Sistema Permessi Granulari per Nexio ISO
-- ================================================
-- Supporta permessi per documenti, cartelle e ISO
-- Multi-tenant con super_admin override
-- ================================================

-- Tabella permessi documenti specifici
CREATE TABLE IF NOT EXISTS document_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    document_id INT NOT NULL,
    user_id INT NOT NULL,
    permission_type VARCHAR(50) NOT NULL, -- view, download, edit, delete, approve, share, version
    granted_by INT NOT NULL,
    azienda_id INT NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    
    FOREIGN KEY (document_id) REFERENCES documenti(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES utenti(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES utenti(id),
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_doc_user_permission (document_id, user_id, permission_type),
    INDEX idx_user_doc (user_id, document_id),
    INDEX idx_doc_permission (document_id, permission_type),
    INDEX idx_azienda (azienda_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella permessi cartelle specifici
CREATE TABLE IF NOT EXISTS folder_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    folder_id INT NOT NULL,
    user_id INT NOT NULL,
    permission_type VARCHAR(50) NOT NULL, -- view, create, edit, delete, manage_permissions
    granted_by INT NOT NULL,
    azienda_id INT NOT NULL,
    inherit_subfolders BOOLEAN DEFAULT TRUE,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    
    FOREIGN KEY (folder_id) REFERENCES cartelle(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES utenti(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES utenti(id),
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_folder_user_permission (folder_id, user_id, permission_type),
    INDEX idx_user_folder (user_id, folder_id),
    INDEX idx_folder_permission (folder_id, permission_type),
    INDEX idx_azienda (azienda_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella permessi ISO
CREATE TABLE IF NOT EXISTS iso_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    permission_name VARCHAR(100) NOT NULL UNIQUE,
    permission_description TEXT,
    permission_category VARCHAR(50), -- configuration, compliance, audit, structure
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella assegnazione permessi ISO a utenti
CREATE TABLE IF NOT EXISTS iso_user_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    permission_id INT NOT NULL,
    azienda_id INT NOT NULL,
    active BOOLEAN DEFAULT TRUE,
    granted_by INT NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES utenti(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES iso_permissions(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES utenti(id),
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_user_iso_permission (user_id, permission_id, azienda_id),
    INDEX idx_user_permission (user_id, permission_id),
    INDEX idx_azienda (azienda_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella permessi gruppi (per future espansioni)
CREATE TABLE IF NOT EXISTS permission_groups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_name VARCHAR(100) NOT NULL,
    group_description TEXT,
    azienda_id INT NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES utenti(id),
    
    UNIQUE KEY unique_group_name_company (group_name, azienda_id),
    INDEX idx_azienda (azienda_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella membri gruppi permessi
CREATE TABLE IF NOT EXISTS permission_group_members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    user_id INT NOT NULL,
    added_by INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (group_id) REFERENCES permission_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES utenti(id) ON DELETE CASCADE,
    FOREIGN KEY (added_by) REFERENCES utenti(id),
    
    UNIQUE KEY unique_group_user (group_id, user_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabella permessi gruppi
CREATE TABLE IF NOT EXISTS group_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    resource_type ENUM('document', 'folder', 'iso', 'system') NOT NULL,
    resource_id INT NULL, -- NULL per permessi generali
    permission_type VARCHAR(50) NOT NULL,
    granted_by INT NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (group_id) REFERENCES permission_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES utenti(id),
    
    INDEX idx_group_resource (group_id, resource_type, resource_id),
    INDEX idx_permission_type (permission_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inserimento permessi ISO predefiniti
INSERT INTO iso_permissions (permission_name, permission_description, permission_category) VALUES
('iso_configure', 'Configurare struttura ISO aziendale', 'configuration'),
('iso_manage_compliance', 'Gestire conformità e audit', 'compliance'),
('iso_audit_access', 'Accedere ai report di audit', 'audit'),
('iso_structure_admin', 'Amministrare struttura documenti ISO', 'structure'),
('iso_create_templates', 'Creare template ISO', 'configuration'),
('iso_approve_documents', 'Approvare documenti ISO', 'compliance'),
('iso_manage_nonconformities', 'Gestire non conformità', 'compliance'),
('iso_view_reports', 'Visualizzare report conformità', 'audit');

-- Vista per permessi effettivi utente (combina ruolo + permessi specifici)
CREATE OR REPLACE VIEW v_user_effective_permissions AS
SELECT 
    u.id as user_id,
    u.username,
    u.ruolo,
    ua.azienda_id,
    a.nome as azienda_nome,
    'role_based' as permission_source,
    CASE 
        WHEN u.ruolo = 'super_admin' THEN '*'
        WHEN u.ruolo = 'utente_speciale' THEN 'elevated'
        ELSE u.ruolo
    END as permission_level
FROM utenti u
LEFT JOIN utenti_aziende ua ON u.id = ua.utente_id
LEFT JOIN aziende a ON ua.azienda_id = a.id
WHERE u.attivo = 1 AND ua.attivo = 1

UNION

SELECT 
    dp.user_id,
    u.username,
    u.ruolo,
    dp.azienda_id,
    a.nome as azienda_nome,
    'document_specific' as permission_source,
    CONCAT('document:', dp.document_id, ':', dp.permission_type) as permission_level
FROM document_permissions dp
JOIN utenti u ON dp.user_id = u.id
JOIN aziende a ON dp.azienda_id = a.id
WHERE dp.expires_at IS NULL OR dp.expires_at > NOW()

UNION

SELECT 
    fp.user_id,
    u.username,
    u.ruolo,
    fp.azienda_id,
    a.nome as azienda_nome,
    'folder_specific' as permission_source,
    CONCAT('folder:', fp.folder_id, ':', fp.permission_type) as permission_level
FROM folder_permissions fp
JOIN utenti u ON fp.user_id = u.id
JOIN aziende a ON fp.azienda_id = a.id
WHERE fp.expires_at IS NULL OR fp.expires_at > NOW();

-- Funzione per verificare accesso documento (super_admin override)
DELIMITER $$
CREATE FUNCTION check_document_access(
    p_user_id INT,
    p_document_id INT,
    p_permission VARCHAR(50)
) RETURNS BOOLEAN
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_user_role VARCHAR(50);
    DECLARE v_company_id INT;
    DECLARE v_doc_company_id INT;
    DECLARE v_has_permission BOOLEAN DEFAULT FALSE;
    
    -- Recupera ruolo utente
    SELECT ruolo INTO v_user_role FROM utenti WHERE id = p_user_id AND attivo = 1;
    
    -- Super admin ha sempre accesso
    IF v_user_role = 'super_admin' THEN
        RETURN TRUE;
    END IF;
    
    -- Recupera azienda documento
    SELECT azienda_id INTO v_doc_company_id FROM documenti WHERE id = p_document_id;
    
    -- Verifica se l'utente appartiene all'azienda del documento
    SELECT COUNT(*) > 0 INTO v_has_permission
    FROM utenti_aziende 
    WHERE utente_id = p_user_id 
    AND azienda_id = v_doc_company_id 
    AND attivo = 1;
    
    IF NOT v_has_permission THEN
        RETURN FALSE;
    END IF;
    
    -- Verifica permessi specifici
    SELECT COUNT(*) > 0 INTO v_has_permission
    FROM document_permissions
    WHERE document_id = p_document_id 
    AND user_id = p_user_id 
    AND permission_type = p_permission
    AND (expires_at IS NULL OR expires_at > NOW());
    
    RETURN v_has_permission;
END$$
DELIMITER ;

-- Funzione per verificare accesso cartella (con ereditarietà)
DELIMITER $$
CREATE FUNCTION check_folder_access(
    p_user_id INT,
    p_folder_id INT,
    p_permission VARCHAR(50)
) RETURNS BOOLEAN
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_user_role VARCHAR(50);
    DECLARE v_folder_company_id INT;
    DECLARE v_parent_id INT;
    DECLARE v_has_permission BOOLEAN DEFAULT FALSE;
    
    -- Recupera ruolo utente
    SELECT ruolo INTO v_user_role FROM utenti WHERE id = p_user_id AND attivo = 1;
    
    -- Super admin ha sempre accesso
    IF v_user_role = 'super_admin' THEN
        RETURN TRUE;
    END IF;
    
    -- Recupera info cartella
    SELECT azienda_id, parent_id INTO v_folder_company_id, v_parent_id 
    FROM cartelle WHERE id = p_folder_id;
    
    -- Verifica se l'utente appartiene all'azienda della cartella
    SELECT COUNT(*) > 0 INTO v_has_permission
    FROM utenti_aziende 
    WHERE utente_id = p_user_id 
    AND azienda_id = v_folder_company_id 
    AND attivo = 1;
    
    IF NOT v_has_permission THEN
        RETURN FALSE;
    END IF;
    
    -- Verifica permessi specifici cartella
    SELECT COUNT(*) > 0 INTO v_has_permission
    FROM folder_permissions
    WHERE folder_id = p_folder_id 
    AND user_id = p_user_id 
    AND permission_type = p_permission
    AND (expires_at IS NULL OR expires_at > NOW());
    
    IF v_has_permission THEN
        RETURN TRUE;
    END IF;
    
    -- Verifica permessi ereditati dal parent
    IF v_parent_id IS NOT NULL THEN
        SELECT COUNT(*) > 0 INTO v_has_permission
        FROM folder_permissions
        WHERE folder_id = v_parent_id 
        AND user_id = p_user_id 
        AND permission_type = p_permission
        AND inherit_subfolders = TRUE
        AND (expires_at IS NULL OR expires_at > NOW());
    END IF;
    
    RETURN v_has_permission;
END$$
DELIMITER ;

-- Trigger per log automatico assegnazione permessi
DELIMITER $$
CREATE TRIGGER log_permission_assignment
AFTER INSERT ON document_permissions
FOR EACH ROW
BEGIN
    INSERT INTO log_attivita (
        utente_id, 
        azienda_id, 
        azione, 
        entita_tipo, 
        entita_id, 
        dettagli
    ) VALUES (
        NEW.granted_by,
        NEW.azienda_id,
        'permission_granted',
        'document',
        NEW.document_id,
        JSON_OBJECT(
            'user_id', NEW.user_id,
            'permission_type', NEW.permission_type
        )
    );
END$$
DELIMITER ;

-- Indici per ottimizzazione query permessi
CREATE INDEX idx_doc_perm_user_type ON document_permissions(user_id, permission_type);
CREATE INDEX idx_folder_perm_user_type ON folder_permissions(user_id, permission_type);
CREATE INDEX idx_folder_inherit ON folder_permissions(folder_id, inherit_subfolders);
CREATE INDEX idx_perm_expires ON document_permissions(expires_at);
CREATE INDEX idx_folder_perm_expires ON folder_permissions(expires_at);

-- Migrazione permessi esistenti (se necessario)
-- Assegna permessi base in base al ruolo aziendale
INSERT IGNORE INTO folder_permissions (folder_id, user_id, permission_type, granted_by, azienda_id)
SELECT 
    c.id as folder_id,
    ua.utente_id as user_id,
    'view' as permission_type,
    1 as granted_by, -- System user
    c.azienda_id
FROM cartelle c
JOIN utenti_aziende ua ON c.azienda_id = ua.azienda_id
WHERE ua.attivo = 1
AND ua.ruolo_azienda IN ('admin', 'manager', 'user');

-- Statistiche permessi per dashboard admin
CREATE OR REPLACE VIEW v_permission_stats AS
SELECT 
    a.id as azienda_id,
    a.nome as azienda_nome,
    COUNT(DISTINCT dp.user_id) as users_with_doc_permissions,
    COUNT(DISTINCT dp.document_id) as documents_with_permissions,
    COUNT(DISTINCT fp.folder_id) as folders_with_permissions,
    COUNT(DISTINCT pg.id) as permission_groups
FROM aziende a
LEFT JOIN document_permissions dp ON a.id = dp.azienda_id
LEFT JOIN folder_permissions fp ON a.id = fp.azienda_id
LEFT JOIN permission_groups pg ON a.id = pg.azienda_id
GROUP BY a.id, a.nome;