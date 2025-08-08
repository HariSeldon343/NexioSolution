<?php
/**
 * Script per configurare il sistema di cartelle aziende
 * Esegue il trigger e crea le cartelle per le aziende esistenti
 */

require_once dirname(__DIR__) . '/backend/config/config.php';
require_once dirname(__DIR__) . '/backend/config/database.php';

echo "=== Configurazione Sistema Cartelle Aziende ===\n\n";

try {
    // 1. Elimina il trigger esistente se presente
    echo "1. Rimozione trigger esistente...\n";
    try {
        db_query("DROP TRIGGER IF EXISTS after_azienda_insert");
        echo "   ✓ Trigger rimosso\n";
    } catch (Exception $e) {
        echo "   - Nessun trigger da rimuovere\n";
    }
    
    // 2. Crea il nuovo trigger
    echo "\n2. Creazione trigger per nuove aziende...\n";
    $triggerSQL = "
    CREATE TRIGGER after_azienda_insert
    AFTER INSERT ON aziende
    FOR EACH ROW
    BEGIN
        DECLARE cartella_nome VARCHAR(255);
        DECLARE percorso VARCHAR(1000);
        
        SET cartella_nome = NEW.nome;
        SET percorso = CONCAT('/', cartella_nome);
        
        INSERT INTO cartelle (
            nome,
            parent_id,
            percorso_completo,
            azienda_id,
            creato_da,
            data_creazione
        ) VALUES (
            cartella_nome,
            NULL,
            percorso,
            NEW.id,
            1,
            NOW()
        );
    END";
    
    db_query($triggerSQL);
    echo "   ✓ Trigger creato con successo\n";
    
    // 3. Crea cartelle per aziende esistenti
    echo "\n3. Creazione cartelle per aziende esistenti...\n";
    
    // Ottieni aziende senza cartella root
    $query = "
        SELECT a.id, a.nome 
        FROM aziende a
        LEFT JOIN cartelle c ON c.azienda_id = a.id AND c.parent_id IS NULL AND c.nome = a.nome
        WHERE a.stato = 'attiva' AND c.id IS NULL
    ";
    
    $stmt = db_query($query);
    $aziende = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($aziende) == 0) {
        echo "   - Tutte le aziende hanno già una cartella root\n";
    } else {
        db_begin_transaction();
        
        foreach ($aziende as $azienda) {
            $folderId = db_insert('cartelle', [
                'nome' => $azienda['nome'],
                'parent_id' => null,
                'percorso_completo' => '/' . $azienda['nome'],
                'azienda_id' => $azienda['id'],
                'creato_da' => 1,
                'data_creazione' => date('Y-m-d H:i:s')
            ]);
            
            echo "   ✓ Creata cartella per: {$azienda['nome']} (ID: $folderId)\n";
        }
        
        db_commit();
    }
    
    // 4. Verifica finale
    echo "\n4. Verifica finale...\n";
    $stmt = db_query("
        SELECT COUNT(*) as total FROM aziende WHERE stato = 'attiva'
    ");
    $totaleAziende = $stmt->fetch()['total'];
    
    $stmt = db_query("
        SELECT COUNT(DISTINCT azienda_id) as total 
        FROM cartelle 
        WHERE parent_id IS NULL
    ");
    $totaleCartelle = $stmt->fetch()['total'];
    
    echo "   - Aziende attive: $totaleAziende\n";
    echo "   - Cartelle root: $totaleCartelle\n";
    
    if ($totaleAziende == $totaleCartelle) {
        echo "\n✅ Sistema configurato correttamente!\n";
        echo "   Tutte le aziende hanno una cartella root.\n";
    } else {
        echo "\n⚠️  Attenzione: alcune aziende potrebbero non avere una cartella root.\n";
    }
    
    // 5. Test del trigger
    echo "\n5. Test del trigger (opzionale)...\n";
    echo "   Il trigger creerà automaticamente una cartella per ogni nuova azienda.\n";
    
} catch (Exception $e) {
    echo "\n❌ Errore: " . $e->getMessage() . "\n";
    if (isset($folderId)) {
        db_rollback();
    }
    exit(1);
}

echo "\n=== Configurazione completata con successo! ===\n";
?>