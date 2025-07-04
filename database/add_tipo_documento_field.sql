-- Aggiunge il campo tipo_documento alla tabella moduli_template
ALTER TABLE moduli_template 
ADD COLUMN tipo_documento VARCHAR(20) DEFAULT 'documento' 
COMMENT 'Tipo di editor: documento, foglio, modulo'
AFTER modulo_id;

-- Aggiorna i tipi esistenti basandosi sui nomi dei moduli
UPDATE moduli_template mt
JOIN moduli m ON mt.modulo_id = m.id
SET mt.tipo_documento = CASE
    WHEN LOWER(m.nome) LIKE '%excel%' OR LOWER(m.nome) LIKE '%foglio%' THEN 'foglio'
    WHEN LOWER(m.nome) LIKE '%form%' OR LOWER(m.nome) LIKE '%modulo%' THEN 'modulo'
    ELSE 'documento'
END;

-- Crea indice per performance
CREATE INDEX idx_tipo_documento ON moduli_template(tipo_documento); 