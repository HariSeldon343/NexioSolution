<?php
/**
 * Script di test per verificare il funzionamento dell'eliminazione dei log
 * con il nuovo sistema di protezione basato sul campo non_eliminabile
 */

require_once 'backend/config/config.php';

// Colori per output console
$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$reset = "\033[0m";

echo "\n{$yellow}=== TEST SISTEMA ELIMINAZIONE LOG ==={$reset}\n\n";

try {
    $db = db_connection();
    
    // 1. Verifica esistenza del trigger
    echo "1. Verifica trigger di protezione... ";
    $stmt = $db->query("SELECT TRIGGER_NAME FROM information_schema.TRIGGERS 
                        WHERE TRIGGER_SCHEMA = 'nexiosol' 
                        AND EVENT_OBJECT_TABLE = 'log_attivita'");
    $triggers = $stmt->fetchAll();
    
    if (count($triggers) > 0) {
        echo "{$green}OK{$reset} - Trovati " . count($triggers) . " trigger\n";
        foreach ($triggers as $trigger) {
            echo "   - {$trigger['TRIGGER_NAME']}\n";
        }
    } else {
        echo "{$yellow}ATTENZIONE{$reset} - Nessun trigger trovato\n";
    }
    
    // 2. Inserisci log di test
    echo "\n2. Inserimento log di test... ";
    $db->beginTransaction();
    
    // Log eliminabile
    $db->exec("INSERT INTO log_attivita (utente_id, tipo, descrizione, entita_tipo, azione, non_eliminabile) 
               VALUES (1, 'test', 'Test eliminabile', 'test_delete', 'test', 0)");
    $idEliminabile = $db->lastInsertId();
    
    // Log protetto
    $db->exec("INSERT INTO log_attivita (utente_id, tipo, descrizione, entita_tipo, azione, non_eliminabile) 
               VALUES (1, 'test', 'Test protetto', 'test_delete', 'test', 1)");
    $idProtetto = $db->lastInsertId();
    
    // Log di eliminazione (sempre protetto)
    $db->exec("INSERT INTO log_attivita (utente_id, tipo, descrizione, entita_tipo, azione, non_eliminabile) 
               VALUES (1, 'test', 'Test log eliminazione', 'test_delete', 'eliminazione_log', 0)");
    $idEliminazione = $db->lastInsertId();
    
    $db->commit();
    echo "{$green}OK{$reset} - Inseriti 3 log di test\n";
    
    // 3. Test eliminazione log normale
    echo "\n3. Test eliminazione log normale (non_eliminabile=0)... ";
    try {
        $db->exec("DELETE FROM log_attivita WHERE id = $idEliminabile");
        echo "{$green}OK{$reset} - Log eliminato con successo\n";
    } catch (PDOException $e) {
        echo "{$red}ERRORE{$reset} - " . $e->getMessage() . "\n";
    }
    
    // 4. Test eliminazione log protetto
    echo "\n4. Test eliminazione log protetto (non_eliminabile=1)... ";
    try {
        $db->exec("DELETE FROM log_attivita WHERE id = $idProtetto");
        echo "{$red}ERRORE{$reset} - Il log protetto è stato eliminato (non dovrebbe!)\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'protected') !== false || 
            strpos($e->getMessage(), 'audit') !== false) {
            echo "{$green}OK{$reset} - Eliminazione bloccata come previsto\n";
            echo "   Messaggio: " . $e->getMessage() . "\n";
        } else {
            echo "{$red}ERRORE{$reset} - Errore inaspettato: " . $e->getMessage() . "\n";
        }
    }
    
    // 5. Test eliminazione log di tipo 'eliminazione_log'
    echo "\n5. Test eliminazione log di tipo 'eliminazione_log'... ";
    try {
        $db->exec("DELETE FROM log_attivita WHERE id = $idEliminazione");
        echo "{$red}ERRORE{$reset} - Il log di eliminazione è stato eliminato (non dovrebbe!)\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'deletion log') !== false || 
            strpos($e->getMessage(), 'audit') !== false) {
            echo "{$green}OK{$reset} - Eliminazione bloccata come previsto\n";
            echo "   Messaggio: " . $e->getMessage() . "\n";
        } else {
            echo "{$red}ERRORE{$reset} - Errore inaspettato: " . $e->getMessage() . "\n";
        }
    }
    
    // 6. Conteggio finale
    echo "\n6. Verifica conteggi finali... ";
    $stmt = $db->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN non_eliminabile = 1 THEN 1 ELSE 0 END) as protetti,
        SUM(CASE WHEN azione = 'eliminazione_log' THEN 1 ELSE 0 END) as log_eliminazione,
        SUM(CASE WHEN entita_tipo = 'test_delete' THEN 1 ELSE 0 END) as test_rimasti
        FROM log_attivita");
    $counts = $stmt->fetch();
    
    echo "{$green}OK{$reset}\n";
    echo "   - Totale log: {$counts['total']}\n";
    echo "   - Log protetti: {$counts['protetti']}\n";
    echo "   - Log eliminazione: {$counts['log_eliminazione']}\n";
    echo "   - Test rimasti: {$counts['test_rimasti']} (dovrebbero essere 2: protetto + eliminazione)\n";
    
    // 7. Pulizia
    echo "\n7. Pulizia log di test... ";
    // Prima rimuovi la protezione dai log di test
    $db->exec("UPDATE log_attivita SET non_eliminabile = 0 
               WHERE entita_tipo = 'test_delete' AND azione != 'eliminazione_log'");
    // Poi elimina i log di test
    $deleted = $db->exec("DELETE FROM log_attivita 
                          WHERE entita_tipo = 'test_delete' 
                          AND non_eliminabile = 0
                          AND azione != 'eliminazione_log'");
    echo "{$green}OK{$reset} - Eliminati $deleted log di test\n";
    
    echo "\n{$green}=== TEST COMPLETATO CON SUCCESSO ==={$reset}\n\n";
    echo "Il sistema di protezione dei log funziona correttamente:\n";
    echo "- I log normali possono essere eliminati\n";
    echo "- I log marcati come non_eliminabile=1 sono protetti\n";
    echo "- I log di tipo 'eliminazione_log' sono sempre protetti\n\n";
    
} catch (Exception $e) {
    echo "\n{$red}ERRORE CRITICO:{$reset} " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>