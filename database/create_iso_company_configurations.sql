-- Create ISO Company Configurations table
-- This table stores company-specific ISO document management configurations

CREATE TABLE IF NOT EXISTS iso_company_configurations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    azienda_id INT NOT NULL,
    structure_type ENUM('separate', 'integrated', 'custom') DEFAULT 'separate',
    enabled_standards JSON COMMENT 'Array of enabled standard IDs',
    custom_structure JSON COMMENT 'Custom folder structure if type=custom',
    retention_days INT DEFAULT 2555 COMMENT '7 years default retention',
    enable_versioning BOOLEAN DEFAULT TRUE,
    enable_approval_workflow BOOLEAN DEFAULT TRUE,
    enable_fulltext_search BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_company (azienda_id),
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    INDEX idx_structure_type (structure_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default ISO standards if not exists
CREATE TABLE IF NOT EXISTS iso_standards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    version VARCHAR(20),
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default ISO standards
INSERT IGNORE INTO iso_standards (code, name, description, version) VALUES
('ISO9001', 'ISO 9001:2015', 'Sistema di Gestione della Qualità', '2015'),
('ISO14001', 'ISO 14001:2015', 'Sistema di Gestione Ambientale', '2015'),
('ISO45001', 'ISO 45001:2018', 'Sistema di Gestione della Salute e Sicurezza sul Lavoro', '2018'),
('ISO27001', 'ISO 27001:2022', 'Sistema di Gestione della Sicurezza delle Informazioni', '2022');