<?php
require_once 'backend/config/config.php';

echo "<!DOCTYPE html><html><head><title>Verifica Struttura Database</title></head><body>";
echo "<h2>🔍 Verifica Struttura Database per Sistema Moduli-Template</h2>\n";
echo "<pre>\n";

try {
    // Configurazione database
    $host = 'localhost';
    $dbname = 'piattaforma_collaborativa';
    $username = 'root';
    $password = '';
    
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    echo "✅ Connessione al database riuscita\n\n";

    // Lista delle tabelle necessarie
    $requiredTables = [
        'moduli_documento' => [
            'sql' => "
                CREATE TABLE moduli_documento (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    nome VARCHAR(255) NOT NULL,
                    codice VARCHAR(50) NOT NULL UNIQUE,
                    descrizione TEXT,
                    tipo ENUM('word', 'excel', 'form', 'custom') DEFAULT 'word',
                    icona VARCHAR(100) DEFAULT 'fas fa-file-alt',
                    attivo TINYINT(1) DEFAULT 1,
                    ordine INT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ",
            'sample_data' => [
                ['Documenti Generici', 'DOC_GEN', 'Template per documenti generici', 'word', 'fas fa-file-word', 1, 10],
                ['Procedure Operative', 'PROC_OP', 'Template per procedure operative', 'word', 'fas fa-cogs', 1, 20],
                ['Moduli ISO', 'ISO_MOD', 'Template per moduli ISO', 'form', 'fas fa-certificate', 1, 30],
                ['Report Tecnici', 'REP_TEC', 'Template per report tecnici', 'word', 'fas fa-file-chart', 1, 40]
            ]
        ],
        'moduli_template' => [
            'sql' => "
                CREATE TABLE moduli_template (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    modulo_id INT NOT NULL,
                    nome VARCHAR(255) NOT NULL,
                    descrizione TEXT,
                    contenuto LONGTEXT,
                    header_content TEXT,
                    footer_content TEXT,
                    template_html LONGTEXT,
                    css_personalizzato TEXT,
                    tipo ENUM('word', 'excel', 'form') DEFAULT 'word',
                    logo_header VARCHAR(255),
                    logo_footer VARCHAR(255),
                    attivo TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (modulo_id) REFERENCES moduli_documento(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ",
            'sample_data' => []
        ],
        'azienda_moduli' => [
            'sql' => "
                CREATE TABLE azienda_moduli (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    azienda_id INT NOT NULL,
                    modulo_id INT NOT NULL,
                    attivo TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_azienda_modulo (azienda_id, modulo_id),
                    FOREIGN KEY (modulo_id) REFERENCES moduli_documento(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ",
            'sample_data' => []
        ]
    ];

    // Verifica e crea ogni tabella
    foreach ($requiredTables as $tableName => $tableInfo) {
        echo "📋 Verifica tabella '$tableName'...\n";
        
        $stmt = $pdo->query("SHOW TABLES LIKE '$tableName'");
        $exists = $stmt->rowCount() > 0;
        
        if (!$exists) {
            echo "❌ Tabella '$tableName' non trovata. Creazione...\n";
            $pdo->exec($tableInfo['sql']);
            echo "✅ Tabella '$tableName' creata con successo\n";
            
            // Inserisci dati di esempio se disponibili
            if (!empty($tableInfo['sample_data'])) {
                echo "📝 Inserimento dati di esempio...\n";
                
                if ($tableName === 'moduli_documento') {
                    foreach ($tableInfo['sample_data'] as $data) {
                        $stmt = $pdo->prepare("
                            INSERT INTO moduli_documento (nome, codice, descrizione, tipo, icona, attivo, ordine) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute($data);
                        echo "  + {$data[0]}\n";
                    }
                }
            }
        } else {
            echo "✅ Tabella '$tableName' esiste\n";
            
            // Verifica se ha dati
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $tableName");
            $count = $stmt->fetch()['count'];
            echo "   📊 Record presenti: $count\n";
            
            // Se moduli_documento è vuoto, inserisci dati di esempio
            if ($tableName === 'moduli_documento' && $count == 0) {
                echo "📝 Inserimento moduli di esempio...\n";
                foreach ($tableInfo['sample_data'] as $data) {
                    $stmt = $pdo->prepare("
                        INSERT INTO moduli_documento (nome, codice, descrizione, tipo, icona, attivo, ordine) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute($data);
                    echo "  + {$data[0]}\n";
                }
            }
        }
        echo "\n";
    }

    // Crea template di esempio se non esistono
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM moduli_template");
    $templateCount = $stmt->fetch()['count'];
    
    if ($templateCount == 0) {
        echo "📝 Creazione template di esempio...\n";
        
        // Ottieni i moduli creati
        $stmt = $pdo->query("SELECT id, nome, codice FROM moduli_documento ORDER BY ordine");
        $moduli = $stmt->fetchAll();
        
        foreach ($moduli as $modulo) {
            $templateData = [
                'nome' => "Template " . $modulo['nome'],
                'descrizione' => "Template standard per " . strtolower($modulo['nome']),
                'header_content' => '<div style="text-align: center; font-size: 10px; border-bottom: 1px solid #ccc; padding-bottom: 5px;"><strong>{{AZIENDA}} - ' . $modulo['nome'] . '</strong></div>',
                'footer_content' => '<div style="text-align: center; font-size: 9px; border-top: 1px solid #ccc; padding-top: 5px;">Documento: {{CODICE}} | Data: {{DATA}}</div>',
                'contenuto' => 'Contenuto template per ' . $modulo['nome'] . '\n\nTitolo: {{TITOLO}}\nCodice: {{CODICE}}\nData: {{DATA}}'
            ];
            
            $stmt = $pdo->prepare("
                INSERT INTO moduli_template (modulo_id, nome, descrizione, header_content, footer_content, contenuto, attivo) 
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([
                $modulo['id'],
                $templateData['nome'],
                $templateData['descrizione'],
                $templateData['header_content'],
                $templateData['footer_content'],
                $templateData['contenuto']
            ]);
            
            echo "  + {$templateData['nome']}\n";
        }
        echo "\n";
    }

    // Associa tutti i moduli a tutte le aziende se azienda_moduli è vuoto
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM azienda_moduli");
    $associationCount = $stmt->fetch()['count'];
    
    if ($associationCount == 0) {
        echo "🔗 Creazione associazioni azienda-moduli...\n";
        
        // Ottieni tutte le aziende
        $stmt = $pdo->query("SELECT id, nome FROM aziende WHERE stato = 'attiva'");
        $aziende = $stmt->fetchAll();
        
        // Ottieni tutti i moduli
        $stmt = $pdo->query("SELECT id FROM moduli_documento WHERE attivo = 1");
        $moduli = $stmt->fetchAll();
        
        foreach ($aziende as $azienda) {
            foreach ($moduli as $modulo) {
                $stmt = $pdo->prepare("
                    INSERT INTO azienda_moduli (azienda_id, modulo_id, attivo) 
                    VALUES (?, ?, 1)
                ");
                $stmt->execute([$azienda['id'], $modulo['id']]);
            }
            echo "  + {$azienda['nome']}: " . count($moduli) . " moduli associati\n";
        }
        echo "\n";
    }

    // Riepilogo finale
    echo "🎉 Struttura database verificata e configurata!\n\n";
    
    echo "📊 Riepilogo:\n";
    foreach ($requiredTables as $tableName => $tableInfo) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $tableName");
        $count = $stmt->fetch()['count'];
        echo "  • $tableName: $count record\n";
    }

} catch (PDOException $e) {
    echo "❌ Errore database: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Errore: " . $e->getMessage() . "\n";
}

echo "</pre>\n";
echo '<div style="margin: 20px 0;">';
echo '<a href="gestione-moduli.php" style="display: inline-block; padding: 10px 20px; background: #0078d4; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px;">⚙️ Gestione Moduli</a>';
echo '<a href="gestione-moduli-template.php" style="display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px;">📝 Gestione Template</a>';
echo '<a href="nuovo-documento.php" style="display: inline-block; padding: 10px 20px; background: #ffc107; color: black; text-decoration: none; border-radius: 4px; margin-right: 10px;">📄 Testa Nuovo Documento</a>';
echo '<a href="dashboard.php" style="display: inline-block; padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">← Dashboard</a>';
echo '</div>';
echo "</body></html>";
?> 