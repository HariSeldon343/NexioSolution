<?php
/**
 * Assegna Template ai Documenti
 * Assegna un template di default ai documenti che non ne hanno uno
 */

// Includi la configurazione
require_once 'backend/config/config.php';

// Avvia la sessione per i messaggi
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<!DOCTYPE html>";
echo "<html lang='it'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>Assegna Template ai Documenti</title>";
echo "<style>";
echo "body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }";
echo ".container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }";
echo ".success { color: #155724; background: #d4edda; padding: 10px; border-radius: 4px; margin: 10px 0; }";
echo ".error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 4px; margin: 10px 0; }";
echo ".info { color: #0c5460; background: #d1ecf1; padding: 10px; border-radius: 4px; margin: 10px 0; }";
echo ".warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 4px; margin: 10px 0; }";
echo ".btn { display: inline-block; padding: 10px 20px; margin: 10px 5px; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; }";
echo ".btn-primary { background: #007bff; color: white; }";
echo ".btn-success { background: #28a745; color: white; }";
echo ".btn-warning { background: #ffc107; color: #212529; }";
echo "table { width: 100%; border-collapse: collapse; margin: 10px 0; }";
echo "th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }";
echo "th { background: #f8f9fa; }";
echo "</style>";
echo "</head>";
echo "<body>";

echo "<div class='container'>";
echo "<h1>📎 Assegna Template ai Documenti</h1>";

try {
    // Ottieni la connessione al database
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Verifica template disponibili
    echo "<h3>1. Verifica Template Disponibili</h3>";
    $stmt = $pdo->query("SELECT id, nome FROM moduli_template ORDER BY id");
    $templates = $stmt->fetchAll();
    
    if (empty($templates)) {
        echo "<div class='error'>❌ Nessun template trovato! Devi prima creare i template.</div>";
        echo "<div class='info'><a href='create-sample-templates.php' class='btn btn-warning'>📄 Crea Template di Esempio</a></div>";
    } else {
        echo "<div class='success'>✅ Trovati " . count($templates) . " template disponibili</div>";
        
        echo "<table>";
        echo "<tr><th>ID</th><th>Nome Template</th></tr>";
        foreach ($templates as $template) {
            echo "<tr>";
            echo "<td>" . $template['id'] . "</td>";
            echo "<td>" . htmlspecialchars($template['nome']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Verifica documenti senza template
    echo "<h3>2. Verifica Documenti Senza Template</h3>";
    $stmt = $pdo->query("
        SELECT id, titolo, user_id, template_id, created_at 
        FROM documenti 
        WHERE template_id IS NULL OR template_id = 0
        ORDER BY created_at DESC
    ");
    $documenti_senza_template = $stmt->fetchAll();
    
    if (empty($documenti_senza_template)) {
        echo "<div class='success'>✅ Tutti i documenti hanno già un template assegnato</div>";
    } else {
        echo "<div class='warning'>⚠️ Trovati " . count($documenti_senza_template) . " documenti senza template</div>";
        
        echo "<table>";
        echo "<tr><th>ID</th><th>Titolo</th><th>User ID</th><th>Creato</th></tr>";
        foreach ($documenti_senza_template as $doc) {
            echo "<tr>";
            echo "<td>" . $doc['id'] . "</td>";
            echo "<td>" . htmlspecialchars($doc['titolo']) . "</td>";
            echo "<td>" . $doc['user_id'] . "</td>";
            echo "<td>" . date('d/m/Y H:i', strtotime($doc['created_at'])) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Assegna template se ci sono template e documenti senza template
    if (!empty($templates) && !empty($documenti_senza_template)) {
        echo "<h3>3. Assegnazione Template di Default</h3>";
        
        // Prendi il primo template come default
        $default_template = $templates[0];
        
        echo "<div class='info'>📎 Assegno il template '" . htmlspecialchars($default_template['nome']) . "' (ID: " . $default_template['id'] . ") come default</div>";
        
        $documenti_aggiornati = 0;
        $errori = [];
        
        foreach ($documenti_senza_template as $doc) {
            try {
                $stmt = $pdo->prepare("UPDATE documenti SET template_id = ? WHERE id = ?");
                $stmt->execute([$default_template['id'], $doc['id']]);
                
                echo "<div class='success'>✅ Documento '" . htmlspecialchars($doc['titolo']) . "' (ID: " . $doc['id'] . ") aggiornato</div>";
                $documenti_aggiornati++;
                
            } catch (Exception $e) {
                $errore = "Errore aggiornamento documento ID " . $doc['id'] . ": " . $e->getMessage();
                $errori[] = $errore;
                echo "<div class='error'>❌ $errore</div>";
            }
        }
        
        echo "<h4>📊 Riepilogo Assegnazione:</h4>";
        echo "<table>";
        echo "<tr><th>Risultato</th><th>Quantità</th></tr>";
        echo "<tr><td>✅ Documenti aggiornati</td><td>$documenti_aggiornati</td></tr>";
        echo "<tr><td>❌ Errori</td><td>" . count($errori) . "</td></tr>";
        echo "</table>";
        
        if ($documenti_aggiornati > 0) {
            echo "<div class='success'>";
            echo "<h4>🎉 Assegnazione completata!</h4>";
            echo "<p>$documenti_aggiornati documenti ora hanno un template assegnato.</p>";
            echo "</div>";
        }
    }
    
    // Verifica finale
    echo "<h3>4. Verifica Finale</h3>";
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as totale_documenti,
            COUNT(template_id) as documenti_con_template,
            COUNT(*) - COUNT(template_id) as documenti_senza_template
        FROM documenti
    ");
    $stats = $stmt->fetch();
    
    echo "<table>";
    echo "<tr><th>Statistica</th><th>Valore</th></tr>";
    echo "<tr><td>📄 Totale documenti</td><td>" . $stats['totale_documenti'] . "</td></tr>";
    echo "<tr><td>✅ Con template</td><td>" . $stats['documenti_con_template'] . "</td></tr>";
    echo "<tr><td>❌ Senza template</td><td>" . $stats['documenti_senza_template'] . "</td></tr>";
    echo "</table>";
    
    if ($stats['documenti_senza_template'] == 0) {
        echo "<div class='success'>🎉 Perfetto! Tutti i documenti hanno un template assegnato.</div>";
    } else {
        echo "<div class='warning'>⚠️ Ci sono ancora " . $stats['documenti_senza_template'] . " documenti senza template.</div>";
    }
    
    // Mostra alcuni documenti con template per verifica
    echo "<h3>5. Esempi Documenti con Template</h3>";
    $stmt = $pdo->query("
        SELECT d.id, d.titolo, d.template_id, mt.nome as template_nome
        FROM documenti d
        LEFT JOIN moduli_template mt ON d.template_id = mt.id
        WHERE d.template_id IS NOT NULL
        ORDER BY d.id DESC
        LIMIT 5
    ");
    $esempi = $stmt->fetchAll();
    
    if (!empty($esempi)) {
        echo "<table>";
        echo "<tr><th>Doc ID</th><th>Titolo</th><th>Template ID</th><th>Template Nome</th><th>Test</th></tr>";
        foreach ($esempi as $esempio) {
            echo "<tr>";
            echo "<td>" . $esempio['id'] . "</td>";
            echo "<td>" . htmlspecialchars($esempio['titolo']) . "</td>";
            echo "<td>" . $esempio['template_id'] . "</td>";
            echo "<td>" . htmlspecialchars($esempio['template_nome']) . "</td>";
            echo "<td><a href='editor-completo/index.php?id=" . $esempio['id'] . "' class='btn btn-primary'>Test</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='warning'>⚠️ Nessun documento con template trovato</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h3>❌ Errore durante l'operazione:</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "<div style='margin-top: 30px;'>";
echo "<a href='create-sample-templates.php' class='btn btn-warning'>📄 Gestisci Template</a>";
echo "<a href='editor-completo/index.php?id=44' class='btn btn-primary'>📝 Testa Editor</a>";
echo "<a href='dashboard.php' class='btn btn-success'>🏠 Dashboard</a>";
echo "</div>";

echo "</div>";
echo "</body>";
echo "</html>";
?> 