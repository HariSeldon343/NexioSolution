-- ISO Compliance Document Management System
-- Complete database schema for multi-tenant ISO standards management

-- Drop existing tables if they exist
DROP TABLE IF EXISTS iso_audit_logs;
DROP TABLE IF EXISTS iso_search_index;
DROP TABLE IF EXISTS iso_permissions;
DROP TABLE IF EXISTS iso_document_versions;
DROP TABLE IF EXISTS iso_documents;
DROP TABLE IF EXISTS iso_folders;
DROP TABLE IF EXISTS iso_folder_templates;
DROP TABLE IF EXISTS iso_company_configurations;
DROP TABLE IF EXISTS iso_standards;

-- ISO Standards table
CREATE TABLE iso_standards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    version VARCHAR(20),
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Company-specific ISO configurations
CREATE TABLE iso_company_configurations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    azienda_id INT NOT NULL,
    structure_type ENUM('separate', 'integrated', 'custom') DEFAULT 'separate',
    enabled_standards JSON, -- Array of enabled standard IDs
    custom_structure JSON, -- Custom folder structure if type='custom'
    retention_days INT DEFAULT 2555, -- 7 years default
    enable_versioning BOOLEAN DEFAULT TRUE,
    enable_approval_workflow BOOLEAN DEFAULT TRUE,
    enable_fulltext_search BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_company (azienda_id),
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    INDEX idx_structure_type (structure_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Folder templates for standards
CREATE TABLE iso_folder_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    standard_id INT NOT NULL,
    parent_id INT,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    order_position INT DEFAULT 0,
    icon VARCHAR(50),
    metadata JSON, -- Additional folder metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (standard_id) REFERENCES iso_standards(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES iso_folder_templates(id) ON DELETE CASCADE,
    INDEX idx_standard_parent (standard_id, parent_id),
    INDEX idx_order (standard_id, order_position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Actual folder structure per company
CREATE TABLE iso_folders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    azienda_id INT NOT NULL,
    standard_id INT,
    parent_id INT,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    path VARCHAR(1000), -- Full path for fast queries
    depth INT DEFAULT 0, -- Depth level for tree queries
    order_position INT DEFAULT 0,
    icon VARCHAR(50),
    permissions JSON, -- Folder-specific permissions
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (standard_id) REFERENCES iso_standards(id) ON DELETE SET NULL,
    FOREIGN KEY (parent_id) REFERENCES iso_folders(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES utenti(id),
    INDEX idx_company_path (azienda_id, path),
    INDEX idx_parent (parent_id),
    INDEX idx_depth (azienda_id, depth),
    FULLTEXT idx_folder_search (name, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Documents table
CREATE TABLE iso_documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    azienda_id INT NOT NULL,
    folder_id INT NOT NULL,
    document_code VARCHAR(50) NOT NULL,
    title VARCHAR(500) NOT NULL,
    description TEXT,
    file_name VARCHAR(255),
    file_path VARCHAR(500),
    file_size BIGINT,
    file_type VARCHAR(50),
    content_hash VARCHAR(64), -- SHA-256 hash for integrity
    version INT DEFAULT 1,
    status ENUM('draft', 'published', 'under_review', 'approved', 'obsolete') DEFAULT 'draft',
    approval_date DATETIME,
    approved_by INT,
    retention_date DATE, -- GDPR compliance
    tags JSON, -- Array of tags
    metadata JSON, -- Custom metadata
    is_locked BOOLEAN DEFAULT FALSE,
    locked_by INT,
    locked_at DATETIME,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (folder_id) REFERENCES iso_folders(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES utenti(id),
    FOREIGN KEY (locked_by) REFERENCES utenti(id),
    FOREIGN KEY (created_by) REFERENCES utenti(id),
    UNIQUE KEY uk_document_code (azienda_id, document_code),
    INDEX idx_folder (folder_id),
    INDEX idx_status (status),
    INDEX idx_retention (retention_date),
    INDEX idx_created (created_at),
    FULLTEXT idx_document_search (title, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Document versions
CREATE TABLE iso_document_versions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    document_id INT NOT NULL,
    version_number INT NOT NULL,
    file_name VARCHAR(255),
    file_path VARCHAR(500),
    file_size BIGINT,
    content_hash VARCHAR(64),
    changes_summary TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES iso_documents(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES utenti(id),
    UNIQUE KEY uk_document_version (document_id, version_number),
    INDEX idx_document (document_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Granular permissions
CREATE TABLE iso_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    azienda_id INT NOT NULL,
    entity_type ENUM('user', 'role', 'department') NOT NULL,
    entity_id INT NOT NULL,
    resource_type ENUM('folder', 'document', 'all') NOT NULL,
    resource_id INT, -- NULL for 'all'
    permission ENUM('read', 'write', 'delete', 'approve', 'full') NOT NULL,
    granted_by INT NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME,
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES utenti(id),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_resource (resource_type, resource_id),
    INDEX idx_company (azienda_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Full-text search index
CREATE TABLE iso_search_index (
    id INT PRIMARY KEY AUTO_INCREMENT,
    document_id INT NOT NULL,
    content TEXT, -- Extracted text content
    last_indexed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES iso_documents(id) ON DELETE CASCADE,
    UNIQUE KEY uk_document (document_id),
    FULLTEXT idx_content (content)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit logs for GDPR compliance
CREATE TABLE iso_audit_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    azienda_id INT NOT NULL,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NOT NULL,
    changes JSON, -- Before/after values
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_company_date (azienda_id, created_at),
    INDEX idx_user (user_id),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
PARTITION BY RANGE (YEAR(created_at)) (
    PARTITION p2024 VALUES LESS THAN (2025),
    PARTITION p2025 VALUES LESS THAN (2026),
    PARTITION p2026 VALUES LESS THAN (2027),
    PARTITION p2027 VALUES LESS THAN (2028),
    PARTITION p2028 VALUES LESS THAN (2029),
    PARTITION p2029 VALUES LESS THAN (2030),
    PARTITION p2030 VALUES LESS THAN MAXVALUE
);

-- Insert ISO standards
INSERT INTO iso_standards (code, name, description, version) VALUES
('ISO_9001', 'ISO 9001', 'Sistema di Gestione della Qualità', '2015'),
('ISO_14001', 'ISO 14001', 'Sistema di Gestione Ambientale', '2015'),
('ISO_45001', 'ISO 45001', 'Sistema di Gestione della Salute e Sicurezza sul Lavoro', '2018'),
('GDPR', 'GDPR', 'Regolamento Generale sulla Protezione dei Dati', '2016/679');

-- Insert folder templates for ISO 9001
SET @iso9001_id = (SELECT id FROM iso_standards WHERE code = 'ISO_9001');

INSERT INTO iso_folder_templates (standard_id, parent_id, name, description, order_position, icon) VALUES
(@iso9001_id, NULL, 'Manuale_Sistema', 'Manuale del Sistema di Gestione Qualità', 1, 'fa-book'),
(@iso9001_id, NULL, 'Politiche', 'Politiche aziendali per la qualità', 2, 'fa-file-alt'),
(@iso9001_id, NULL, 'Procedure', 'Procedure operative standard', 3, 'fa-tasks'),
(@iso9001_id, NULL, 'Moduli_Registrazioni', 'Moduli e registrazioni del sistema', 4, 'fa-clipboard'),
(@iso9001_id, NULL, 'Audit', 'Audit interni e verifiche', 5, 'fa-search'),
(@iso9001_id, NULL, 'Non_Conformità', 'Gestione delle non conformità', 6, 'fa-exclamation-triangle'),
(@iso9001_id, NULL, 'Azioni_Miglioramento', 'Azioni correttive e preventive', 7, 'fa-chart-line');

-- Add sub-folders for Procedure
SET @proc_id = (SELECT id FROM iso_folder_templates WHERE standard_id = @iso9001_id AND name = 'Procedure');
INSERT INTO iso_folder_templates (standard_id, parent_id, name, description, order_position) VALUES
(@iso9001_id, @proc_id, 'Gestione_Documenti', 'Controllo dei documenti e registrazioni', 1),
(@iso9001_id, @proc_id, 'Gestione_Risorse', 'Gestione delle risorse umane e infrastrutture', 2),
(@iso9001_id, @proc_id, 'Processi_Operativi', 'Processi operativi principali', 3);

-- Insert folder templates for ISO 14001
SET @iso14001_id = (SELECT id FROM iso_standards WHERE code = 'ISO_14001');

INSERT INTO iso_folder_templates (standard_id, parent_id, name, description, order_position, icon) VALUES
(@iso14001_id, NULL, 'Manuale_Sistema', 'Manuale del Sistema di Gestione Ambientale', 1, 'fa-book'),
(@iso14001_id, NULL, 'Politiche', 'Politica ambientale', 2, 'fa-file-alt'),
(@iso14001_id, NULL, 'Procedure', 'Procedure di gestione ambientale', 3, 'fa-tasks'),
(@iso14001_id, NULL, 'Aspetti_Ambientali', 'Identificazione e valutazione aspetti ambientali', 4, 'fa-leaf'),
(@iso14001_id, NULL, 'Conformità_Legale', 'Conformità legislativa ambientale', 5, 'fa-balance-scale'),
(@iso14001_id, NULL, 'Emergenze', 'Gestione delle emergenze ambientali', 6, 'fa-exclamation-circle'),
(@iso14001_id, NULL, 'Indicatori_KPI', 'Indicatori di prestazione ambientale', 7, 'fa-chart-bar');

-- Insert folder templates for ISO 45001
SET @iso45001_id = (SELECT id FROM iso_standards WHERE code = 'ISO_45001');

INSERT INTO iso_folder_templates (standard_id, parent_id, name, description, order_position, icon) VALUES
(@iso45001_id, NULL, 'Manuale_Sistema', 'Manuale del Sistema di Gestione SSL', 1, 'fa-book'),
(@iso45001_id, NULL, 'Politiche', 'Politica per la salute e sicurezza', 2, 'fa-file-alt'),
(@iso45001_id, NULL, 'Valutazione_Rischi', 'Documenti di valutazione dei rischi', 3, 'fa-shield-alt'),
(@iso45001_id, NULL, 'Procedure', 'Procedure di sicurezza', 4, 'fa-tasks'),
(@iso45001_id, NULL, 'Formazione', 'Formazione e competenze SSL', 5, 'fa-graduation-cap'),
(@iso45001_id, NULL, 'Sorveglianza_Sanitaria', 'Sorveglianza sanitaria dei lavoratori', 6, 'fa-heartbeat'),
(@iso45001_id, NULL, 'Gestione_Fornitori', 'Gestione fornitori e appaltatori', 7, 'fa-truck');

-- Insert folder templates for GDPR
SET @gdpr_id = (SELECT id FROM iso_standards WHERE code = 'GDPR');

INSERT INTO iso_folder_templates (standard_id, parent_id, name, description, order_position, icon) VALUES
(@gdpr_id, NULL, 'Politiche', 'Politiche sulla privacy e protezione dati', 1, 'fa-shield-alt'),
(@gdpr_id, NULL, 'Registri_Trattamento', 'Registri delle attività di trattamento', 2, 'fa-clipboard-list'),
(@gdpr_id, NULL, 'Consensi', 'Gestione dei consensi', 3, 'fa-check-square'),
(@gdpr_id, NULL, 'Valutazioni_Impatto', 'DPIA - Valutazioni d\'impatto', 4, 'fa-chart-line'),
(@gdpr_id, NULL, 'Violazioni_Dati', 'Registro violazioni e data breach', 5, 'fa-exclamation-triangle'),
(@gdpr_id, NULL, 'Diritti_Interessati', 'Gestione richieste degli interessati', 6, 'fa-users'),
(@gdpr_id, NULL, 'Formazione', 'Formazione privacy e sensibilizzazione', 7, 'fa-graduation-cap');

-- Create stored procedure to generate folder structure for a company
DELIMITER //

CREATE PROCEDURE create_iso_folder_structure(
    IN p_azienda_id INT,
    IN p_standard_id INT,
    IN p_created_by INT
)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_template_id INT;
    DECLARE v_parent_id INT;
    DECLARE v_name VARCHAR(200);
    DECLARE v_description TEXT;
    DECLARE v_order_position INT;
    DECLARE v_icon VARCHAR(50);
    DECLARE v_new_folder_id INT;
    DECLARE v_parent_folder_id INT;
    
    -- Cursor for templates
    DECLARE template_cursor CURSOR FOR
        SELECT id, parent_id, name, description, order_position, icon
        FROM iso_folder_templates
        WHERE standard_id = p_standard_id
        ORDER BY parent_id IS NULL DESC, parent_id, order_position;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Create temporary mapping table
    CREATE TEMPORARY TABLE IF NOT EXISTS temp_folder_mapping (
        template_id INT,
        folder_id INT,
        PRIMARY KEY (template_id)
    );
    
    OPEN template_cursor;
    
    read_loop: LOOP
        FETCH template_cursor INTO v_template_id, v_parent_id, v_name, v_description, v_order_position, v_icon;
        
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Get parent folder ID from mapping
        IF v_parent_id IS NULL THEN
            SET v_parent_folder_id = NULL;
        ELSE
            SELECT folder_id INTO v_parent_folder_id
            FROM temp_folder_mapping
            WHERE template_id = v_parent_id;
        END IF;
        
        -- Insert folder
        INSERT INTO iso_folders (azienda_id, standard_id, parent_id, name, description, order_position, icon, created_by, path, depth)
        SELECT 
            p_azienda_id,
            p_standard_id,
            v_parent_folder_id,
            v_name,
            v_description,
            v_order_position,
            v_icon,
            p_created_by,
            CASE 
                WHEN v_parent_folder_id IS NULL THEN CONCAT('/', v_name)
                ELSE CONCAT((SELECT path FROM iso_folders WHERE id = v_parent_folder_id), '/', v_name)
            END,
            CASE 
                WHEN v_parent_folder_id IS NULL THEN 0
                ELSE (SELECT depth + 1 FROM iso_folders WHERE id = v_parent_folder_id)
            END;
        
        SET v_new_folder_id = LAST_INSERT_ID();
        
        -- Store mapping
        INSERT INTO temp_folder_mapping (template_id, folder_id)
        VALUES (v_template_id, v_new_folder_id);
        
    END LOOP;
    
    CLOSE template_cursor;
    DROP TEMPORARY TABLE IF EXISTS temp_folder_mapping;
END //

-- Create function to get folder tree as JSON
CREATE FUNCTION get_folder_tree(p_azienda_id INT, p_parent_id INT)
RETURNS JSON
READS SQL DATA
BEGIN
    DECLARE result JSON;
    
    SELECT JSON_ARRAYAGG(
        JSON_OBJECT(
            'id', f.id,
            'name', f.name,
            'description', f.description,
            'icon', f.icon,
            'path', f.path,
            'document_count', (
                SELECT COUNT(*) 
                FROM iso_documents 
                WHERE folder_id = f.id
            ),
            'children', get_folder_tree(p_azienda_id, f.id)
        )
    ) INTO result
    FROM iso_folders f
    WHERE f.azienda_id = p_azienda_id 
    AND (
        (p_parent_id IS NULL AND f.parent_id IS NULL) OR 
        (f.parent_id = p_parent_id)
    )
    ORDER BY f.order_position, f.name;
    
    RETURN COALESCE(result, JSON_ARRAY());
END //

-- Create procedure for advanced document search
CREATE PROCEDURE search_iso_documents(
    IN p_azienda_id INT,
    IN p_search_term VARCHAR(500),
    IN p_folder_id INT,
    IN p_status VARCHAR(20),
    IN p_standard_id INT,
    IN p_limit INT,
    IN p_offset INT
)
BEGIN
    SELECT 
        d.id,
        d.document_code,
        d.title,
        d.description,
        d.file_name,
        d.file_size,
        d.file_type,
        d.version,
        d.status,
        d.created_at,
        d.updated_at,
        f.name AS folder_name,
        f.path AS folder_path,
        s.name AS standard_name,
        u.nome AS created_by_name,
        u.cognome AS created_by_surname,
        MATCH(d.title, d.description) AGAINST(p_search_term IN NATURAL LANGUAGE MODE) AS relevance_doc,
        MATCH(si.content) AGAINST(p_search_term IN NATURAL LANGUAGE MODE) AS relevance_content
    FROM iso_documents d
    JOIN iso_folders f ON d.folder_id = f.id
    LEFT JOIN iso_standards s ON f.standard_id = s.id
    JOIN utenti u ON d.created_by = u.id
    LEFT JOIN iso_search_index si ON d.id = si.document_id
    WHERE d.azienda_id = p_azienda_id
    AND (p_folder_id IS NULL OR d.folder_id = p_folder_id)
    AND (p_status IS NULL OR d.status = p_status)
    AND (p_standard_id IS NULL OR f.standard_id = p_standard_id)
    AND (
        p_search_term IS NULL 
        OR MATCH(d.title, d.description) AGAINST(p_search_term IN NATURAL LANGUAGE MODE)
        OR MATCH(si.content) AGAINST(p_search_term IN NATURAL LANGUAGE MODE)
        OR d.document_code LIKE CONCAT('%', p_search_term, '%')
    )
    ORDER BY 
        CASE WHEN p_search_term IS NOT NULL 
        THEN (relevance_doc + COALESCE(relevance_content, 0)) 
        ELSE 0 END DESC,
        d.updated_at DESC
    LIMIT p_limit OFFSET p_offset;
END //

-- Create trigger for audit logging
CREATE TRIGGER iso_documents_audit_insert
AFTER INSERT ON iso_documents
FOR EACH ROW
BEGIN
    INSERT INTO iso_audit_logs (azienda_id, user_id, action, entity_type, entity_id, changes)
    VALUES (
        NEW.azienda_id,
        NEW.created_by,
        'create',
        'document',
        NEW.id,
        JSON_OBJECT('new', JSON_OBJECT(
            'title', NEW.title,
            'status', NEW.status,
            'folder_id', NEW.folder_id
        ))
    );
END //

CREATE TRIGGER iso_documents_audit_update
AFTER UPDATE ON iso_documents
FOR EACH ROW
BEGIN
    INSERT INTO iso_audit_logs (azienda_id, user_id, action, entity_type, entity_id, changes)
    VALUES (
        NEW.azienda_id,
        NEW.created_by,
        'update',
        'document',
        NEW.id,
        JSON_OBJECT(
            'old', JSON_OBJECT(
                'title', OLD.title,
                'status', OLD.status,
                'version', OLD.version
            ),
            'new', JSON_OBJECT(
                'title', NEW.title,
                'status', NEW.status,
                'version', NEW.version
            )
        )
    );
END //

CREATE TRIGGER iso_documents_audit_delete
BEFORE DELETE ON iso_documents
FOR EACH ROW
BEGIN
    INSERT INTO iso_audit_logs (azienda_id, user_id, action, entity_type, entity_id, changes)
    VALUES (
        OLD.azienda_id,
        OLD.created_by,
        'delete',
        'document',
        OLD.id,
        JSON_OBJECT('old', JSON_OBJECT(
            'title', OLD.title,
            'document_code', OLD.document_code,
            'file_name', OLD.file_name
        ))
    );
END //

DELIMITER ;

-- Create views for reporting
CREATE VIEW v_iso_document_summary AS
SELECT 
    d.azienda_id,
    a.nome AS company_name,
    s.name AS standard_name,
    COUNT(DISTINCT d.id) AS total_documents,
    COUNT(DISTINCT CASE WHEN d.status = 'draft' THEN d.id END) AS draft_documents,
    COUNT(DISTINCT CASE WHEN d.status = 'published' THEN d.id END) AS published_documents,
    COUNT(DISTINCT CASE WHEN d.status = 'approved' THEN d.id END) AS approved_documents,
    SUM(d.file_size) AS total_size_bytes,
    MAX(d.created_at) AS last_document_created
FROM iso_documents d
JOIN aziende a ON d.azienda_id = a.id
JOIN iso_folders f ON d.folder_id = f.id
LEFT JOIN iso_standards s ON f.standard_id = s.id
GROUP BY d.azienda_id, a.nome, s.name;

CREATE VIEW v_iso_folder_stats AS
SELECT 
    f.id AS folder_id,
    f.azienda_id,
    f.name AS folder_name,
    f.path,
    COUNT(DISTINCT d.id) AS document_count,
    SUM(d.file_size) AS total_size_bytes,
    MAX(d.created_at) AS last_activity
FROM iso_folders f
LEFT JOIN iso_documents d ON f.id = d.folder_id
GROUP BY f.id, f.azienda_id, f.name, f.path;

CREATE VIEW v_iso_user_permissions AS
SELECT 
    p.azienda_id,
    p.entity_type,
    p.entity_id,
    CASE 
        WHEN p.entity_type = 'user' THEN u.nome
        ELSE NULL
    END AS user_name,
    p.resource_type,
    p.resource_id,
    CASE 
        WHEN p.resource_type = 'folder' THEN f.name
        WHEN p.resource_type = 'document' THEN d.title
        ELSE 'All Resources'
    END AS resource_name,
    p.permission,
    p.granted_at,
    p.expires_at
FROM iso_permissions p
LEFT JOIN utenti u ON p.entity_type = 'user' AND p.entity_id = u.id
LEFT JOIN iso_folders f ON p.resource_type = 'folder' AND p.resource_id = f.id
LEFT JOIN iso_documents d ON p.resource_type = 'document' AND p.resource_id = d.id;

-- Create procedure to update search index
DELIMITER //

CREATE PROCEDURE update_search_index(IN p_document_id INT)
BEGIN
    DECLARE v_content TEXT;
    
    -- Here you would extract content from the file
    -- For now, we'll concatenate title and description
    SELECT CONCAT(title, ' ', COALESCE(description, ''))
    INTO v_content
    FROM iso_documents
    WHERE id = p_document_id;
    
    INSERT INTO iso_search_index (document_id, content)
    VALUES (p_document_id, v_content)
    ON DUPLICATE KEY UPDATE 
        content = v_content,
        last_indexed = CURRENT_TIMESTAMP;
END //

DELIMITER ;

-- Add indexes for better performance
CREATE INDEX idx_doc_retention ON iso_documents(retention_date) WHERE retention_date IS NOT NULL;
CREATE INDEX idx_audit_cleanup ON iso_audit_logs(created_at);
CREATE INDEX idx_folder_tree ON iso_folders(azienda_id, parent_id, order_position);

-- Create event to clean old audit logs (keep 10 years)
DELIMITER //

CREATE EVENT IF NOT EXISTS clean_old_audit_logs
ON SCHEDULE EVERY 1 DAY
DO
BEGIN
    DELETE FROM iso_audit_logs 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 10 YEAR);
END //

DELIMITER ;