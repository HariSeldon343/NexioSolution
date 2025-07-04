<?php
/**
 * Script di debug per analizzare i template nel database
 */

require_once 'backend/config/config.php';

echo "<h2>🔍 Debug Template Database</h2>\n\n";

try {
    // Query 1: Tutti i template
    echo "<h3>📋 Tutti i template nella tabella:</h3>\n";
    $stmt = db_query("SELECT * FROM templates ORDER BY id");
    $all_templates = $stmt->fetchAll();
    
    if (empty($all_templates)) {
        echo "❌ Nessun template trovato nella tabella 'templates'\n\n";
    } else {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr style='background: #f0f0f0;'><th>ID</th><th>Nome</th><th>Attivo</th><th>Tipo</th><th>Azienda ID</th><th>Creato il</th></tr>\n";
        
        foreach ($all_templates as $tpl) {
            $attivo_text = $tpl['attivo'] ? '✅ Sì' : '❌ No';
            $tipo = $tpl['tipo_template'] ?? 'N/D';
            $azienda = $tpl['azienda_id'] ?? 'NULL';
            echo "<tr>";
            echo "<td>{$tpl['id']}</td>";
            echo "<td>{$tpl['nome']}</td>";
            echo "<td>$attivo_text</td>";
            echo "<td>$tipo</td>";
            echo "<td>$azienda</td>";
            echo "<td>{$tpl['data_creazione']}</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
    
    echo "<br>\n";
    
    // Query 2: Solo template attivi (come gestione-moduli)
    echo "<h3>✅ Template attivi (query gestione-moduli):</h3>\n";
    $stmt = db_query("SELECT * FROM templates WHERE attivo = 1 ORDER BY nome");
    $active_templates = $stmt->fetchAll();
    
    echo "Totale template attivi: <strong>" . count($active_templates) . "</strong><br>\n";
    foreach ($active_templates as $tpl) {
        echo "- ID: {$tpl['id']}, Nome: {$tpl['nome']}<br>\n";
    }
    
    echo "<br>\n";
    
    // Query 3: Template via modello (come template-builder)
    echo "<h3>🔧 Template via modello Template.php:</h3>\n";
    $pdo = db_connection();
    require_once 'backend/models/Template.php';
    $template = new Template($pdo);
    
    // Tutti i template
    $all_via_model = $template->getAll([]);
    echo "Totale template (modello): <strong>" . count($all_via_model) . "</strong><br>\n";
    
    // Solo attivi
    $active_via_model = $template->getAll(['attivo' => 1]);
    echo "Template attivi (modello): <strong>" . count($active_via_model) . "</strong><br>\n";
    
    foreach ($all_via_model as $tpl) {
        $status = $tpl['attivo'] ? '✅' : '❌';
        echo "- $status ID: {$tpl['id']}, Nome: {$tpl['nome']}, Attivo: {$tpl['attivo']}<br>\n";
    }
    
    echo "<br>\n";
    
    // Verifica struttura tabella
    echo "<h3>🗃️ Struttura tabella templates:</h3>\n";
    $stmt = db_query("DESCRIBE templates");
    $structure = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse;'>\n";
    echo "<tr style='background: #f0f0f0;'><th>Campo</th><th>Tipo</th><th>Null</th><th>Default</th></tr>\n";
    foreach ($structure as $field) {
        echo "<tr>";
        echo "<td>{$field['Field']}</td>";
        echo "<td>{$field['Type']}</td>";
        echo "<td>{$field['Null']}</td>";
        echo "<td>{$field['Default']}</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
} catch (Exception $e) {
    echo "❌ Errore: " . $e->getMessage() . "\n";
}

echo "\n<br><a href='template-builder-dragdrop.php'>🔙 Torna al Template Builder</a>\n";
echo "<br><a href='gestione-moduli.php'>🔙 Torna a Gestione Moduli</a>\n";
?>