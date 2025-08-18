<?php
/**
 * Script di verifica per la fix del bottone OnlyOffice
 * 
 * Questo script verifica che:
 * 1. Il bottone OnlyOffice sia stato convertito in un link
 * 2. Non ci siano più riferimenti a editDocument nell'onclick
 * 3. Il link punti correttamente a onlyoffice-editor.php
 */

$filesystemContent = file_get_contents('filesystem.php');

$checks = [
    'link_implementation' => [
        'description' => 'OnlyOffice button è ora un tag <a>',
        'pattern' => '/<a\s+href="onlyoffice-editor\.php\?id=\$\{file\.id\}"/i',
        'found' => false
    ],
    'no_onclick_editdocument' => [
        'description' => 'Nessun onclick con editDocument',
        'pattern' => '/onclick\s*=\s*["\']editDocument/i',
        'found' => false,
        'should_not_exist' => true
    ],
    'target_blank' => [
        'description' => 'Link apre in nuova finestra (target="_blank")',
        'pattern' => '/target="_blank".*class=".*btn-onlyoffice/is',
        'found' => false
    ],
    'no_button_onlyoffice' => [
        'description' => 'Nessun <button> con classe btn-onlyoffice',
        'pattern' => '/<button[^>]*class="[^"]*btn-onlyoffice/i',
        'found' => false,
        'should_not_exist' => true
    ]
];

// Esegui i controlli
foreach ($checks as $key => &$check) {
    $check['found'] = preg_match($check['pattern'], $filesystemContent) > 0;
}

// Mostra i risultati
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Verifica Fix OnlyOffice</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 10px;
        }
        .check {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .status {
            font-size: 24px;
        }
        .success { color: #4CAF50; }
        .error { color: #f44336; }
        .description {
            flex: 1;
        }
        .summary {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-top: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .summary.success {
            border-left: 5px solid #4CAF50;
        }
        .summary.error {
            border-left: 5px solid #f44336;
        }
        pre {
            background: #f4f4f4;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }
        .recommendation {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin-top: 20px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <h1>🔍 Verifica Fix OnlyOffice in filesystem.php</h1>
    
    <?php
    $allPassed = true;
    foreach ($checks as $check) {
        $passed = isset($check['should_not_exist']) 
            ? !$check['found']  // Per i check negativi
            : $check['found'];   // Per i check positivi
        
        $allPassed = $allPassed && $passed;
        ?>
        <div class="check">
            <span class="status <?= $passed ? 'success' : 'error' ?>">
                <?= $passed ? '✅' : '❌' ?>
            </span>
            <div class="description">
                <strong><?= htmlspecialchars($check['description']) ?></strong>
                <br>
                <small style="color: #666;">
                    <?php if (isset($check['should_not_exist'])): ?>
                        <?= $check['found'] ? '⚠️ Trovato (dovrebbe essere rimosso)' : '✓ Non trovato (corretto)' ?>
                    <?php else: ?>
                        <?= $check['found'] ? '✓ Trovato' : '⚠️ Non trovato' ?>
                    <?php endif; ?>
                </small>
            </div>
        </div>
        <?php
    }
    ?>
    
    <div class="summary <?= $allPassed ? 'success' : 'error' ?>">
        <h2><?= $allPassed ? '✅ Fix Applicata Correttamente!' : '⚠️ Fix Incompleta' ?></h2>
        
        <?php if ($allPassed): ?>
            <p><strong>Ottimo!</strong> Il bottone OnlyOffice è stato convertito correttamente in un link HTML.</p>
            
            <h3>✨ Cosa è stato fatto:</h3>
            <ul>
                <li>Convertito il <code>&lt;button&gt;</code> in un <code>&lt;a&gt;</code> tag</li>
                <li>Rimosso tutto il JavaScript complesso (onclick, editDocument, etc.)</li>
                <li>Il link punta direttamente a <code>onlyoffice-editor.php?id={fileId}</code></li>
                <li>Aggiunto <code>target="_blank"</code> per aprire in nuova finestra</li>
                <li>Mantenuto lo stile visivo con le classi CSS esistenti</li>
            </ul>
            
            <h3>📋 Codice Finale:</h3>
            <pre>&lt;a href="onlyoffice-editor.php?id=${file.id}" 
   target="_blank"
   class="action-btn btn-onlyoffice"
   title="Apri con OnlyOffice" 
   style="display: inline-flex; align-items: center; 
          justify-content: center; text-decoration: none;"&gt;
    &lt;i class="fas fa-file-word"&gt;&lt;/i&gt;
&lt;/a&gt;</pre>
        <?php else: ?>
            <p><strong>Attenzione:</strong> Alcuni check non sono passati. Verifica manualmente il file.</p>
        <?php endif; ?>
    </div>
    
    <div class="recommendation">
        <h3>💡 Vantaggi della Soluzione Implementata:</h3>
        <ol>
            <li><strong>Affidabilità 100%:</strong> I link HTML funzionano sempre, senza dipendenze JavaScript</li>
            <li><strong>Nessun conflitto:</strong> Non c'è rischio di interferenze con form submit o altri event handler</li>
            <li><strong>Accessibilità:</strong> I link sono nativamente accessibili e navigabili da tastiera</li>
            <li><strong>Semplicità:</strong> Codice più pulito e facile da mantenere</li>
            <li><strong>Performance:</strong> Nessun overhead JavaScript per gestire i click</li>
        </ol>
        
        <h4>Test Manuale:</h4>
        <ol>
            <li>Vai su <a href="filesystem.php" target="_blank">filesystem.php</a></li>
            <li>Trova un file DOCX</li>
            <li>Clicca sul bottone OnlyOffice (icona Word)</li>
            <li>Dovrebbe aprirsi l'editor in una nuova finestra senza ricaricare la pagina</li>
        </ol>
    </div>
    
    <div style="margin-top: 30px; text-align: center; color: #666;">
        <small>Script di verifica eseguito: <?= date('Y-m-d H:i:s') ?></small>
    </div>
</body>
</html>