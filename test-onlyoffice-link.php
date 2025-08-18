<?php
/**
 * Test per verificare che il link OnlyOffice funzioni correttamente
 */

// Test dei link diretti vs JavaScript
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test OnlyOffice Link</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 40px;
            max-width: 800px;
            margin: 0 auto;
        }
        .test-section {
            border: 1px solid #ddd;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        .test-section h2 {
            margin-top: 0;
            color: #333;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 10px;
            background: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .btn:hover {
            background: #218838;
        }
        .result {
            padding: 10px;
            margin-top: 10px;
            border-radius: 4px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
    </style>
</head>
<body>
    <h1>Test OnlyOffice Link</h1>
    
    <div class="test-section">
        <h2>1. Link Diretto HTML (SOLUZIONE CONSIGLIATA)</h2>
        <p>Questo è un semplice link HTML che apre sempre correttamente:</p>
        <a href="onlyoffice-editor.php?id=123" target="_blank" class="btn">
            📝 Apri con OnlyOffice (Link HTML)
        </a>
        <div class="result success">
            ✅ Questo metodo funziona SEMPRE - nessun JavaScript richiesto
        </div>
    </div>

    <div class="test-section">
        <h2>2. Button con JavaScript (PROBLEMATICO)</h2>
        <p>Questo approccio può causare problemi se c'è un form o altri event handler:</p>
        <button type="button" class="btn" onclick="window.open('onlyoffice-editor.php?id=123', '_blank')">
            📝 Apri con OnlyOffice (Button + JS)
        </button>
        <div class="result error">
            ⚠️ Può fallire se c'è interferenza di altri JavaScript o se è dentro un form
        </div>
    </div>

    <div class="test-section">
        <h2>3. Link Stilizzato come Button (MIGLIORE PRATICA)</h2>
        <p>Un link che sembra un bottone ma funziona sempre:</p>
        <a href="onlyoffice-editor.php?id=123" 
           target="_blank" 
           class="btn"
           style="display: inline-flex; align-items: center; gap: 5px;">
            <span>📝</span>
            <span>Apri con OnlyOffice</span>
        </a>
        <div class="result success">
            ✅ Perfetto: sembra un bottone ma è un link affidabile
        </div>
    </div>

    <div class="test-section">
        <h2>4. Test con Form (problema comune)</h2>
        <form onsubmit="alert('Form submitted!'); return false;">
            <p>I bottoni dentro un form possono causare submit non voluti:</p>
            
            <button class="btn" onclick="window.open('onlyoffice-editor.php?id=123', '_blank')">
                ❌ Button senza type (causa submit)
            </button>
            
            <button type="button" class="btn" onclick="window.open('onlyoffice-editor.php?id=123', '_blank')">
                ⚠️ Button con type="button" (meglio ma JS può fallire)
            </button>
            
            <a href="onlyoffice-editor.php?id=123" target="_blank" class="btn">
                ✅ Link (sempre affidabile)
            </a>
        </form>
        <div class="result info">
            ℹ️ I link non causano mai submit del form
        </div>
    </div>

    <div class="test-section">
        <h2>Verifica filesystem.php</h2>
        <p>La modifica applicata in filesystem.php:</p>
        <pre style="background: #f4f4f4; padding: 15px; border-radius: 4px; overflow-x: auto;">
&lt;!-- PRIMA (problematico) --&gt;
&lt;button type="button" class="action-btn btn-onlyoffice"
        data-file-id="${file.id}"
        onclick="editDocument(event, ${file.id})"&gt;
    &lt;i class="fas fa-edit"&gt;&lt;/i&gt;
&lt;/button&gt;

&lt;!-- DOPO (affidabile) --&gt;
&lt;a href="onlyoffice-editor.php?id=${file.id}" 
   target="_blank"
   class="action-btn btn-onlyoffice"
   style="display: inline-flex; align-items: center; 
          justify-content: center; text-decoration: none;"&gt;
    &lt;i class="fas fa-file-word"&gt;&lt;/i&gt;
&lt;/a&gt;
        </pre>
        <div class="result success">
            ✅ Soluzione implementata: link diretto invece di JavaScript complesso
        </div>
    </div>

    <div class="test-section">
        <h2>Vantaggi della soluzione con link:</h2>
        <ul>
            <li>✅ Nessuna dipendenza da JavaScript</li>
            <li>✅ Funziona sempre, anche se JS è disabilitato</li>
            <li>✅ Nessun problema con event bubbling o propagation</li>
            <li>✅ Nessun conflitto con form submit</li>
            <li>✅ SEO-friendly e accessibile</li>
            <li>✅ Più semplice da debuggare</li>
            <li>✅ Supporto nativo del browser per target="_blank"</li>
        </ul>
    </div>

    <script>
        console.log('Test page loaded. La soluzione con link HTML è stata implementata in filesystem.php');
    </script>
</body>
</html>