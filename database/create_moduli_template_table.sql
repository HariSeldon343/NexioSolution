-- Tabella per i template dei moduli documento
CREATE TABLE IF NOT EXISTS moduli_template (
    id INT AUTO_INCREMENT PRIMARY KEY,
    modulo_id INT NOT NULL,
    contenuto LONGTEXT,
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (modulo_id) REFERENCES moduli_documento(id) ON DELETE CASCADE,
    UNIQUE KEY uk_modulo_template (modulo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aggiungi indice per performance
CREATE INDEX idx_moduli_template_modulo ON moduli_template(modulo_id);

-- Inserisci alcuni template di esempio per i moduli esistenti
INSERT INTO moduli_template (modulo_id, contenuto) 
SELECT id, CONCAT('<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>', nome, '</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 40px;
            line-height: 1.6;
        }
        .header { 
            text-align: center; 
            margin-bottom: 30px;
            border-bottom: 2px solid #1b3f76;
            padding-bottom: 20px;
        }
        .content { 
            margin: 30px 0;
        }
        .footer { 
            margin-top: 50px; 
            border-top: 1px solid #ccc; 
            padding-top: 20px;
            text-align: center;
            color: #666;
        }
        h1 { color: #1b3f76; }
        h2 { color: #2c2c2c; }
        .info-box {
            background: #f5f5f5;
            padding: 15px;
            border-left: 4px solid #1b3f76;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{azienda_nome}</h1>
        <h2>', nome, '</h2>
        <p>Documento n. {numero_documento} del {data_documento}</p>
    </div>
    
    <div class="content">
        <div class="info-box">
            <p><strong>Tipo documento:</strong> ', nome, '</p>
            <p><strong>Codice modulo:</strong> ', codice, '</p>
        </div>
        
        <h3>Contenuto del documento</h3>
        <p>Questo è un template di esempio per il modulo ', nome, '.</p>
        <p>Puoi modificare questo template dalla sezione "Gestione Template Documenti".</p>
        
        <h3>Informazioni aggiuntive</h3>
        <ul>
            <li>Azienda: {azienda_nome}</li>
            <li>Indirizzo: {azienda_indirizzo}</li>
            <li>Creato da: {utente_nome} {utente_cognome}</li>
            <li>Data creazione: {data_corrente}</li>
        </ul>
    </div>
    
    <div class="footer">
        <p>Documento generato automaticamente dal sistema Nexio</p>
        <p>&copy; ', YEAR(NOW()), ' {azienda_nome} - Tutti i diritti riservati</p>
    </div>
</body>
</html>')
FROM moduli_documento 
WHERE attivo = 1
ON DUPLICATE KEY UPDATE contenuto = contenuto; 