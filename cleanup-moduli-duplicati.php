<?php
/**
 * Script per rimuovere moduli duplicati dalla tabella moduli_documento
 * Da eseguire solo se necessario
 */

require_once 'backend/config/config.php';

echo "🔍 Controllo duplicati nella tabella moduli_documento...\n\n";

try {
    // Trova duplicati per codice
    $stmt = db_query("
        SELECT codice, COUNT(*) as count, GROUP_CONCAT(id) as ids
        FROM moduli_documento 
        GROUP BY codice 
        HAVING COUNT(*) > 1
    ");
    
    $duplicati = $stmt->fetchAll();
    
    if (empty($duplicati)) {
        echo "✅ Nessun duplicato trovato.\n";
        exit;
    }
    
    echo "⚠️  Trovati " . count($duplicati) . " gruppi di moduli duplicati:\n\n";
    
    foreach ($duplicati as $dup) {
        echo "📋 Codice: {$dup['codice']} - {$dup['count']} duplicati (IDs: {$dup['ids']})\n";
        
        // Ottieni dettagli dei duplicati
        $ids = explode(',', $dup['ids']);
        $stmt2 = db_query("
            SELECT id, nome, descrizione, attivo, created_at 
            FROM moduli_documento 
            WHERE id IN (" . implode(',', array_map('intval', $ids)) . ")
            ORDER BY created_at ASC
        ");
        
        $dettagli = $stmt2->fetchAll();
        
        foreach ($dettagli as $index => $det) {
            $stato = $det['attivo'] ? 'ATTIVO' : 'DISATTIVO';
            $marker = $index === 0 ? '✅ MANTIENI' : '❌ RIMUOVI';
            echo "   $marker ID: {$det['id']} - {$det['nome']} ($stato) - {$det['created_at']}\n";
        }
        echo "\n";
    }
    
    echo "🛠️  Per procedere con la pulizia automatica, decommentare il codice seguente:\n";
    echo "// ATTENZIONE: Questo rimuoverà i duplicati mantenendo solo il primo record\n\n";
    
    /*
    // DECOMMENTARE SOLO SE SI VUOLE PROCEDERE CON LA PULIZIA
    echo "🚀 Avvio pulizia automatica...\n\n";
    
    db_connection()->beginTransaction();
    
    foreach ($duplicati as $dup) {
        $ids = explode(',', $dup['ids']);
        
        // Mantieni solo il primo (più vecchio)
        $daMantenere = array_shift($ids);
        
        if (!empty($ids)) {
            $stmt = db_query("DELETE FROM moduli_documento WHERE id IN (" . implode(',', array_map('intval', $ids)) . ")");
            echo "🗑️  Rimossi " . count($ids) . " duplicati per il codice: {$dup['codice']}\n";
        }
    }
    
    db_connection()->commit();
    echo "\n✅ Pulizia completata!\n";
    */
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        db_connection()->rollBack();
    }
    echo "❌ Errore: " . $e->getMessage() . "\n";
}

echo "\n📝 Note:\n";
echo "- Questo script identifica i duplicati per codice modulo\n";
echo "- Mantiene sempre il record più vecchio (primo creato)\n";
echo "- I record inattivi vengono comunque rimossi se duplicati\n";
echo "- Prima di procedere, verificare che non ci siano dipendenze\n";