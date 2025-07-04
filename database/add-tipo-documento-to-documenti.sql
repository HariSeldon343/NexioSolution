-- Aggiungi campo tipo_documento alla tabella documenti
ALTER TABLE documenti 
ADD COLUMN IF NOT EXISTS tipo_documento ENUM('documento', 'foglio', 'modulo') DEFAULT 'documento' AFTER stato;

-- Aggiorna i documenti esistenti basandosi sul nome del modulo
UPDATE documenti d
JOIN moduli_documento m ON d.modulo_id = m.id
SET d.tipo_documento = 'foglio'
WHERE LOWER(m.nome) LIKE '%excel%' 
   OR LOWER(m.nome) LIKE '%foglio%' 
   OR LOWER(m.nome) LIKE '%budget%'
   OR LOWER(m.nome) LIKE '%regist%';

UPDATE documenti d
JOIN moduli_documento m ON d.modulo_id = m.id
SET d.tipo_documento = 'modulo'
WHERE LOWER(m.nome) LIKE '%form%' 
   OR LOWER(m.nome) LIKE '%modulo%' 
   OR LOWER(m.nome) LIKE '%richiesta%';

-- Aggiorna anche basandosi sul titolo del documento se non ha modulo
UPDATE documenti 
SET tipo_documento = 'foglio'
WHERE modulo_id IS NULL 
  AND (LOWER(titolo) LIKE '%excel%' 
       OR LOWER(titolo) LIKE '%foglio%' 
       OR LOWER(titolo) LIKE '%budget%');

UPDATE documenti 
SET tipo_documento = 'modulo'
WHERE modulo_id IS NULL 
  AND (LOWER(titolo) LIKE '%form%' 
       OR LOWER(titolo) LIKE '%modulo%' 
       OR LOWER(titolo) LIKE '%richiesta%');

-- Verifica i risultati
SELECT id, titolo, tipo_documento, 
       (SELECT nome FROM moduli_documento WHERE id = documenti.modulo_id) as modulo_nome
FROM documenti 
WHERE titolo LIKE '%Excel%' 
   OR titolo LIKE '%Form%' 
   OR titolo LIKE '%Budget%' 
   OR titolo LIKE '%Richiesta%'
ORDER BY id DESC; 