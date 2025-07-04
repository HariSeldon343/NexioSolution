<?php
/**
 * Migration: Aggiunge campo tipo_template alla tabella templates
 * Rimuove la dipendenza diretta azienda_id dai template
 */

require_once __DIR__ . '/../../backend/config/config.php';

try {
    $pdo = db_connection();
    
    echo "Aggiornamento tabella templates...\n";
    
    // 1. Aggiungi colonna tipo_template se non esiste
    $sql = "
    ALTER TABLE templates 
    ADD COLUMN IF NOT EXISTS tipo_template ENUM('globale', 'personalizzato') 
    DEFAULT 'globale' 
    AFTER azienda_id
    ";
    $pdo->exec($sql);
    echo "✅ Colonna tipo_template aggiunta\n";
    
    // 2. Aggiorna template esistenti con azienda_id a globali
    $sql = "UPDATE templates SET tipo_template = 'globale', azienda_id = NULL WHERE azienda_id IS NOT NULL";
    $pdo->exec($sql);
    echo "✅ Template esistenti aggiornati come globali\n";
    
    // 3. Crea tabella azienda_template per associazioni se non esiste
    $sql = "
    CREATE TABLE IF NOT EXISTS azienda_template (
        id INT AUTO_INCREMENT PRIMARY KEY,
        azienda_id INT NOT NULL,
        template_id INT NOT NULL,
        attivo TINYINT(1) DEFAULT 1,
        configurazione JSON NULL,
        data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE,
        FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE CASCADE,
        UNIQUE KEY unique_azienda_template (azienda_id, template_id)
    )
    ";
    $pdo->exec($sql);
    echo "✅ Tabella azienda_template creata\n";
    
    // 4. Migra associazioni esistenti se presenti
    $stmt = $pdo->query("SELECT id, azienda_id FROM templates WHERE azienda_id IS NOT NULL");
    $templates_con_azienda = $stmt->fetchAll();
    
    foreach ($templates_con_azienda as $template) {
        $pdo->prepare("
            INSERT IGNORE INTO azienda_template (azienda_id, template_id, attivo) 
            VALUES (?, ?, 1)
        ")->execute([$template['azienda_id'], $template['id']]);
    }
    echo "✅ Associazioni migrated: " . count($templates_con_azienda) . " template\n";
    
    echo "\n🎉 Migration completata con successo!\n";
    echo "\n📋 Riepilogo modifiche:\n";
    echo "  - Template ora sono globali per default\n";
    echo "  - Aggiunto campo tipo_template (globale/personalizzato)\n";
    echo "  - Creata tabella azienda_template per associazioni\n";
    echo "  - Template esistenti migrated come globali\n";
    
} catch (Exception $e) {
    echo "❌ Errore durante la migration: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>