-- Company Document Schemas Table
-- Stores the selected ISO schemas/standards for each company

CREATE TABLE IF NOT EXISTS company_document_schemas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    azienda_id INT NOT NULL,
    schema_type VARCHAR(50) NOT NULL,
    schema_config JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_company_schema (azienda_id, schema_type),
    FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES utenti(id),
    INDEX idx_active (azienda_id, is_active),
    INDEX idx_schema_type (schema_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default schema types
INSERT INTO company_document_schemas (azienda_id, schema_type, schema_config, created_by)
SELECT 
    a.id,
    'iso9001',
    JSON_OBJECT(
        'name', 'ISO 9001:2015',
        'description', 'Sistema di Gestione della Qualità',
        'folders', JSON_ARRAY()
    ),
    1
FROM aziende a
WHERE NOT EXISTS (
    SELECT 1 FROM company_document_schemas 
    WHERE azienda_id = a.id AND schema_type = 'iso9001'
);