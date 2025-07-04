<?php
// Migration per sistema documentale modulare
require_once __DIR__ . '/../../includes/config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // 1. Tabella moduli documentali (categorie principali)
    $db->exec("
        CREATE TABLE IF NOT EXISTS moduli_documento (
            id INT AUTO_INCREMENT PRIMARY KEY,
            codice VARCHAR(50) UNIQUE NOT NULL,
            nome VARCHAR(100) NOT NULL,
            descrizione TEXT,
            icona VARCHAR(50),
            ordine INT DEFAULT 0,
            attivo BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // 2. Tabella categorie documento (struttura ad albero)
    $db->exec("
        CREATE TABLE IF NOT EXISTS categorie_documento (
            id INT AUTO_INCREMENT PRIMARY KEY,
            modulo_id INT NOT NULL,
            parent_id INT DEFAULT NULL,
            nome VARCHAR(100) NOT NULL,
            descrizione TEXT,
            percorso VARCHAR(500),
            livello INT DEFAULT 0,
            ordine INT DEFAULT 0,
            attivo BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (modulo_id) REFERENCES moduli_documento(id) ON DELETE CASCADE,
            FOREIGN KEY (parent_id) REFERENCES categorie_documento(id) ON DELETE CASCADE,
            INDEX idx_percorso (percorso),
            INDEX idx_parent (parent_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // 3. Tabella moduli abilitati per azienda
    $db->exec("
        CREATE TABLE IF NOT EXISTS azienda_moduli (
            id INT AUTO_INCREMENT PRIMARY KEY,
            azienda_id INT NOT NULL,
            modulo_id INT NOT NULL,
            abilitato BOOLEAN DEFAULT TRUE,
            configurazione JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
            FOREIGN KEY (modulo_id) REFERENCES moduli_documento(id) ON DELETE CASCADE,
            UNIQUE KEY unique_azienda_modulo (azienda_id, modulo_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // 4. Modifica tabella documenti per supportare categorie e versioning
    $db->exec("
        ALTER TABLE documenti 
        ADD COLUMN IF NOT EXISTS categoria_id INT AFTER categoria,
        ADD COLUMN IF NOT EXISTS modulo_id INT AFTER categoria_id,
        ADD COLUMN IF NOT EXISTS versione_numero INT DEFAULT 1,
        ADD COLUMN IF NOT EXISTS documento_padre_id INT DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS is_current_version BOOLEAN DEFAULT TRUE,
        ADD COLUMN IF NOT EXISTS modifiche_descrizione TEXT,
        ADD COLUMN IF NOT EXISTS modificato_da INT,
        ADD FOREIGN KEY (categoria_id) REFERENCES categorie_documento(id) ON DELETE SET NULL,
        ADD FOREIGN KEY (modulo_id) REFERENCES moduli_documento(id) ON DELETE SET NULL,
        ADD FOREIGN KEY (documento_padre_id) REFERENCES documenti(id) ON DELETE CASCADE,
        ADD FOREIGN KEY (modificato_da) REFERENCES utenti(id) ON DELETE SET NULL,
        ADD INDEX idx_versioning (documento_padre_id, versione_numero)
    ");
    
    // 5. Tabella personalizzazione tema per azienda
    $db->exec("
        CREATE TABLE IF NOT EXISTS temi_azienda (
            id INT AUTO_INCREMENT PRIMARY KEY,
            azienda_id INT UNIQUE,
            tema_globale BOOLEAN DEFAULT FALSE,
            
            -- Colori
            color_primary VARCHAR(7) DEFAULT '#6b5cdf',
            color_secondary VARCHAR(7) DEFAULT '#f59e0b',
            color_success VARCHAR(7) DEFAULT '#10b981',
            color_danger VARCHAR(7) DEFAULT '#ef4444',
            color_warning VARCHAR(7) DEFAULT '#f59e0b',
            color_info VARCHAR(7) DEFAULT '#3b82f6',
            color_dark VARCHAR(7) DEFAULT '#1f2937',
            color_light VARCHAR(7) DEFAULT '#f9fafb',
            
            -- Tipografia
            font_family VARCHAR(200) DEFAULT 'system-ui, -apple-system, sans-serif',
            font_size_base VARCHAR(10) DEFAULT '16px',
            font_weight_normal INT DEFAULT 400,
            font_weight_bold INT DEFAULT 700,
            
            -- Layout
            border_radius VARCHAR(10) DEFAULT '0.375rem',
            box_shadow VARCHAR(100) DEFAULT '0 1px 3px rgba(0,0,0,0.1)',
            
            -- Logo e branding
            logo_url VARCHAR(500),
            favicon_url VARCHAR(500),
            nome_personalizzato VARCHAR(100),
            
            -- Template documenti
            header_template TEXT,
            footer_template TEXT,
            
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // 6. Inserisci moduli di default
    $moduli_default = [
        ['codice' => 'sistema', 'nome' => 'Documenti di sistema', 'icona' => '📋', 'ordine' => 1],
        ['codice' => 'origine_esterna', 'nome' => 'Documenti di origine esterna', 'icona' => '📥', 'ordine' => 2],
        ['codice' => 'registrazioni', 'nome' => 'Registrazioni', 'icona' => '📝', 'ordine' => 3],
        ['codice' => 'risorse', 'nome' => 'Risorse', 'icona' => '👥', 'ordine' => 4],
        ['codice' => 'miglioramento', 'nome' => 'Miglioramento', 'icona' => '📈', 'ordine' => 5],
        ['codice' => 'analisi', 'nome' => 'Analisi e valutazioni', 'icona' => '📊', 'ordine' => 6],
        ['codice' => 'setup', 'nome' => 'Setup', 'icona' => '⚙️', 'ordine' => 7]
    ];
    
    $stmt = $db->prepare("INSERT INTO moduli_documento (codice, nome, icona, ordine) VALUES (?, ?, ?, ?)");
    foreach ($moduli_default as $modulo) {
        $stmt->execute([$modulo['codice'], $modulo['nome'], $modulo['icona'], $modulo['ordine']]);
    }
    
    // 7. Inserisci categorie per ogni modulo
    $categorie = [
        'sistema' => [
            'Documenti di origine esterna',
            'Prescrizioni legali e altre',
            'Registrazioni',
            'Risorse',
            'Schede personale',
            'Classroom',
            'Infrastruttura (per data)',
            'Infrastruttura (per tipo)'
        ],
        'miglioramento' => [
            'Indicatori e obiettivi',
            'Audit',
            'Non conformità e azioni correttive',
            'Azioni di miglioramento'
        ],
        'analisi' => [
            'Soddisfazione del cliente',
            'Valutazione dei fornitori (per anno)',
            'Valutazione dei fornitori (per fornitore)',
            'Modulo ambiente',
            'Modulo 231',
            'Risk based thinking',
            'Cruscotto'
        ]
    ];
    
    $stmt_cat = $db->prepare("
        INSERT INTO categorie_documento (modulo_id, nome, percorso, livello) 
        VALUES ((SELECT id FROM moduli_documento WHERE codice = ?), ?, ?, 1)
    ");
    
    foreach ($categorie as $modulo_codice => $cats) {
        foreach ($cats as $i => $cat) {
            $percorso = $modulo_codice . '/' . str_replace(' ', '_', strtolower($cat));
            $stmt_cat->execute([$modulo_codice, $cat, $percorso]);
        }
    }
    
    // 8. Trigger per gestire versioning
    $db->exec("
        CREATE TRIGGER IF NOT EXISTS before_document_update
        BEFORE UPDATE ON documenti
        FOR EACH ROW
        BEGIN
            IF OLD.contenuto != NEW.contenuto OR OLD.titolo != NEW.titolo THEN
                -- Marca la versione corrente come non corrente
                UPDATE documenti 
                SET is_current_version = FALSE 
                WHERE documento_padre_id = OLD.id OR id = OLD.id;
            END IF;
        END;
    ");
    
    // 9. Tabella notifiche documenti
    $db->exec("
        CREATE TABLE IF NOT EXISTS notifiche_documento (
            id INT AUTO_INCREMENT PRIMARY KEY,
            documento_id INT NOT NULL,
            tipo_modifica VARCHAR(50),
            utente_id INT NOT NULL,
            destinatari JSON,
            inviata BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (documento_id) REFERENCES documenti(id) ON DELETE CASCADE,
            FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    echo "✅ Tabelle per sistema documentale modulare create con successo!\n";
    
} catch (Exception $e) {
    echo "❌ Errore: " . $e->getMessage() . "\n";
} 