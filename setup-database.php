<?php
/**
 * Setup Database - Nexio Platform
 * Crea automaticamente il database e le tabelle necessarie
 */

// Configurazione database
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'piattaforma_collaborativa';

$success_steps = [];
$error_steps = [];

try {
    // 1. Connessione a MySQL senza specificare il database
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $success_steps[] = "✅ Connessione a MySQL Server riuscita";
    
    // 2. Crea il database se non esiste
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $success_steps[] = "✅ Database '$database' creato/verificato";
    
    // 3. Seleziona il database
    $pdo->exec("USE `$database`");
    $success_steps[] = "✅ Database '$database' selezionato";
    
    // 4. Crea tabelle essenziali per far funzionare l'autenticazione
    $tables_sql = [
        // Tabella utenti (essenziale per il login)
        "CREATE TABLE IF NOT EXISTS `utenti` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `username` varchar(50) NOT NULL UNIQUE,
            `password` varchar(255) NOT NULL,
            `email` varchar(100) NOT NULL UNIQUE,
            `nome` varchar(100) NOT NULL,
            `cognome` varchar(100) NOT NULL,
            `ruolo` enum('admin','staff','cliente') NOT NULL DEFAULT 'cliente',
            `attivo` boolean DEFAULT TRUE,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        // Tabella aziende (per il multi-tenant)
        "CREATE TABLE IF NOT EXISTS `aziende` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nome` varchar(255) NOT NULL,
            `codice` varchar(50) UNIQUE,
            `indirizzo` text,
            `telefono` varchar(50),
            `email` varchar(100),
            `partita_iva` varchar(20),
            `codice_fiscale` varchar(20),
            `stato` enum('attiva','sospesa','archiviata') DEFAULT 'attiva',
            `logo` varchar(255),
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        // Tabella moduli_documento (per OnlyOffice)
        "CREATE TABLE IF NOT EXISTS `moduli_documento` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `codice` varchar(50) UNIQUE NOT NULL,
            `nome` varchar(100) NOT NULL,
            `descrizione` text,
            `tipo` varchar(20) DEFAULT 'word',
            `icona` varchar(50),
            `ordine` int(11) DEFAULT 0,
            `attivo` boolean DEFAULT TRUE,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        // Tabella documenti
        "CREATE TABLE IF NOT EXISTS `documenti` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `titolo` varchar(255) NOT NULL,
            `codice` varchar(100),
            `contenuto` longtext,
            `azienda_id` int(11),
            `modulo_id` int(11),
            `creato_da` int(11),
            `stato` enum('bozza','pubblicato','archiviato') DEFAULT 'bozza',
            `data_creazione` timestamp DEFAULT CURRENT_TIMESTAMP,
            `ultima_modifica` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            FOREIGN KEY (`azienda_id`) REFERENCES `aziende`(`id`),
            FOREIGN KEY (`modulo_id`) REFERENCES `moduli_documento`(`id`),
            FOREIGN KEY (`creato_da`) REFERENCES `utenti`(`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
    
    foreach ($tables_sql as $sql) {
        $pdo->exec($sql);
    }
    $success_steps[] = "✅ Tabelle essenziali create";
    
    // 5. Crea utente admin di default se non esiste
    $check_admin = $pdo->query("SELECT COUNT(*) FROM utenti WHERE username = 'admin'")->fetchColumn();
    if ($check_admin == 0) {
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO utenti (username, password, email, nome, cognome, ruolo) VALUES 
                   ('admin', '$admin_password', 'admin@nexio.com', 'Amministratore', 'Sistema', 'admin')");
        $success_steps[] = "✅ Utente admin creato (Username: admin, Password: admin123)";
    } else {
        $success_steps[] = "✅ Utente admin già esistente";
    }
    
    // 6. Crea azienda di default se non esiste
    $check_azienda = $pdo->query("SELECT COUNT(*) FROM aziende")->fetchColumn();
    if ($check_azienda == 0) {
        $pdo->exec("INSERT INTO aziende (nome, codice, email) VALUES 
                   ('Nexio Platform', 'NEXIO', 'info@nexio.com')");
        $success_steps[] = "✅ Azienda di default creata";
    } else {
        $success_steps[] = "✅ Azienda già esistente";
    }
    
    // 7. Crea moduli documento base per OnlyOffice
    $check_moduli = $pdo->query("SELECT COUNT(*) FROM moduli_documento")->fetchColumn();
    if ($check_moduli == 0) {
        $moduli = [
            ['WORD', 'Documento Word', 'Documenti di testo', 'word', 'fa-file-word'],
            ['EXCEL', 'Foglio Excel', 'Fogli di calcolo', 'excel', 'fa-file-excel'],
            ['POWERPOINT', 'Presentazione', 'Presentazioni', 'powerpoint', 'fa-file-powerpoint']
        ];
        
        foreach ($moduli as $i => $modulo) {
            $pdo->exec("INSERT INTO moduli_documento (codice, nome, descrizione, tipo, icona, ordine) VALUES 
                       ('{$modulo[0]}', '{$modulo[1]}', '{$modulo[2]}', '{$modulo[3]}', '{$modulo[4]}', " . ($i + 1) . ")");
        }
        $success_steps[] = "✅ Moduli documento creati per OnlyOffice";
    } else {
        $success_steps[] = "✅ Moduli documento già esistenti";
    }
    
    $success_steps[] = "🎉 Setup database completato con successo!";
    
} catch (Exception $e) {
    $error_steps[] = "❌ Errore: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Database - Nexio Platform</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            color: #333;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        
        .content {
            padding: 40px;
        }
        
        .step {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .step.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .step.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .btn {
            display: inline-block;
            padding: 15px 30px;
            background: #0078d4;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin: 20px 10px;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: #106ebe;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .config-info {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-database"></i> Setup Database Nexio Platform</h1>
            <p>Configurazione automatica del database</p>
        </div>
        
        <div class="content">
            <?php if (!empty($success_steps)): ?>
                <h2><i class="fas fa-check-circle"></i> Operazioni completate:</h2>
                <?php foreach ($success_steps as $step): ?>
                    <div class="step success"><?php echo $step; ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($error_steps)): ?>
                <h2><i class="fas fa-exclamation-circle"></i> Errori:</h2>
                <?php foreach ($error_steps as $step): ?>
                    <div class="step error"><?php echo $step; ?></div>
                <?php endforeach; ?>
                
                <div class="config-info">
                    <strong>Verifica la configurazione:</strong><br>
                    - Host: <?php echo $host; ?><br>
                    - Username: <?php echo $username; ?><br>
                    - Password: <?php echo empty($password) ? '(vuota)' : '***'; ?><br>
                    - Database: <?php echo $database; ?>
                </div>
            <?php endif; ?>
            
            <?php if (empty($error_steps)): ?>
                <div style="text-align: center; margin-top: 30px;">
                    <h3>🎉 Database configurato con successo!</h3>
                    <p>Ora puoi accedere alla piattaforma:</p>
                    
                    <a href="index.php" class="btn btn-success">
                        <i class="fas fa-home"></i> Vai alla Homepage
                    </a>
                    
                    <a href="login.php" class="btn">
                        <i class="fas fa-sign-in-alt"></i> Accedi (admin/admin123)
                    </a>
                    
                    <div class="config-info">
                        <strong>Credenziali di accesso:</strong><br>
                        Username: <strong>admin</strong><br>
                        Password: <strong>admin123</strong>
                    </div>
                </div>
            <?php else: ?>
                <div style="text-align: center; margin-top: 30px;">
                    <button onclick="location.reload()" class="btn">
                        <i class="fas fa-redo"></i> Riprova Setup
                    </button>
                    
                    <a href="test-onlyoffice-simple.php" class="btn">
                        <i class="fas fa-file-word"></i> Test OnlyOffice (senza DB)
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 