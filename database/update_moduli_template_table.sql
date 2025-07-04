-- Aggiorna la tabella moduli_template per supportare tipi di template e header/footer
ALTER TABLE moduli_template 
ADD COLUMN IF NOT EXISTS tipo ENUM('word', 'excel', 'form') DEFAULT 'word' AFTER modulo_id,
ADD COLUMN IF NOT EXISTS header_content LONGTEXT AFTER contenuto,
ADD COLUMN IF NOT EXISTS footer_content LONGTEXT AFTER header_content,
ADD COLUMN IF NOT EXISTS logo_header VARCHAR(255) AFTER footer_content,
ADD COLUMN IF NOT EXISTS logo_footer VARCHAR(255) AFTER logo_header;

-- Aggiungi indice per il tipo
CREATE INDEX IF NOT EXISTS idx_moduli_template_tipo ON moduli_template(tipo); 