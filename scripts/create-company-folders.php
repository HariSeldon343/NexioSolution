<?php
/**
 * Script per creare cartelle per le aziende esistenti
 * Da eseguire una sola volta per inizializzare il sistema
 */

require_once dirname(__DIR__) . '/backend/config/config.php';
require_once dirname(__DIR__) . '/backend/config/database.php';

try {
    // Ottieni tutte le aziende attive
    $stmt = db_query("SELECT id, nome FROM aziende WHERE stato = 'attiva'");
    $aziende = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Trovate " . count($aziende) . " aziende attive\n";
    
    db_begin_transaction();
    
    foreach ($aziende as $azienda) {
        // Verifica se la cartella esiste già
        $check = db_query(
            "SELECT id FROM cartelle WHERE azienda_id = ? AND parent_id IS NULL AND nome = ?",
            [$azienda['id'], $azienda['nome']]
        );
        
        if (!$check->fetch()) {
            // Crea la cartella
            $folderId = db_insert('cartelle', [
                'nome' => $azienda['nome'],
                'parent_id' => null,
                'percorso_completo' => '/' . $azienda['nome'],
                'azienda_id' => $azienda['id'],
                'creato_da' => 1, // Admin user
                'data_creazione' => date('Y-m-d H:i:s')
            ]);
            
            echo "Creata cartella per azienda: {$azienda['nome']} (ID: $folderId)\n";
        } else {
            echo "Cartella già esistente per: {$azienda['nome']}\n";
        }
    }
    
    db_commit();
    echo "\nOperazione completata con successo!\n";
    
} catch (Exception $e) {
    db_rollback();
    echo "Errore: " . $e->getMessage() . "\n";
    exit(1);
}
?>