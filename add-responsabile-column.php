<?php
/**
 * Aggiunge la colonna responsabile_id alla tabella aziende
 */

require_once 'backend/config/config.php';

// Determina se siamo in modalità web o CLI
$is_web = !empty($_SERVER['HTTP_HOST']);

if ($is_web) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><title>Aggiunta Colonna Responsabile</title>";
    echo "<style>body{font-family:Arial,sans-serif;max-width:800px;margin:50px auto;padding:20px;}";
    echo ".success{color:green;}.error{color:red;}.info{color:blue;}</style></head><body>";
    echo "<h2>🔧 Aggiunta Colonna Responsabile ID</h2>";
} else {
    echo "=== AGGIUNTA COLONNA RESPONSABILE_ID ===\n\n";
}

try {
    $pdo = db_connect();
    
    // Verifica se la colonna responsabile_id esiste già
    if ($is_web) {
        echo "<p>1. Verifica presenza colonna responsabile_id...</p>";
    } else {
        echo "1. Verifica presenza colonna responsabile_id... ";
    }
    
    // Verifica con un approccio più robusto
    $column_exists = false;
    try {
        $stmt = db_query("SELECT responsabile_id FROM aziende LIMIT 1");
        $column_exists = true;
    } catch (Exception $e) {
        $column_exists = false;
    }
    
    if (!$column_exists) {
        if ($is_web) {
            echo "<p class='info'>   - Aggiunta colonna responsabile_id...</p>";
        } else {
            echo "\n   - Aggiunta colonna responsabile_id... ";
        }
        
        // Aggiungi la colonna
        $pdo->exec("ALTER TABLE aziende ADD COLUMN responsabile_id INT NULL");
        
        // Aggiungi l'indice
        $pdo->exec("ALTER TABLE aziende ADD INDEX idx_responsabile_id (responsabile_id)");
        
        // Aggiungi la foreign key (con gestione errori per MySQL strict mode)
        try {
            $pdo->exec("ALTER TABLE aziende ADD FOREIGN KEY (responsabile_id) REFERENCES utenti(id) ON DELETE SET NULL");
        } catch (Exception $e) {
            // Se fallisce la foreign key, non è un problema critico
            if ($is_web) {
                echo "<p class='info'>   - Foreign key non aggiunta (normale se MySQL strict mode): " . $e->getMessage() . "</p>";
            } else {
                echo "   - Foreign key non aggiunta: " . $e->getMessage() . "\n";
            }
        }
        
        if ($is_web) {
            echo "<p class='success'>✓ OK - Colonna aggiunta con successo!</p>";
        } else {
            echo "✓ OK\n";
        }
    } else {
        if ($is_web) {
            echo "<p class='success'>✓ Colonna già presente</p>";
        } else {
            echo "✓ Colonna già presente\n";
        }
    }
    
    if ($is_web) {
        echo "<h3 class='success'>✅ COMPLETATO</h3>";
        echo "<p>La colonna responsabile_id è stata aggiunta alla tabella aziende.</p>";
        echo "<p>Ora le aziende possono avere un responsabile assegnato!</p>";
        echo "<p><a href='aziende.php'>← Torna alla gestione aziende</a></p>";
    } else {
        echo "\n✅ COMPLETATO - La colonna responsabile_id è stata aggiunta alla tabella aziende\n";
        echo "Ora le aziende possono avere un responsabile assegnato!\n\n";
    }
    
} catch (Exception $e) {
    if ($is_web) {
        echo "<p class='error'>❌ ERRORE: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p>Assicurati che il database sia configurato correttamente.</p>";
    } else {
        echo "\n❌ ERRORE: " . $e->getMessage() . "\n";
        echo "Assicurati che il database sia configurato correttamente.\n";
    }
}

if ($is_web) {
    echo "</body></html>";
}