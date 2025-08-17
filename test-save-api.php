<?php
/**
 * Test script per verificare il funzionamento dell'API save-advanced-document
 */

// Inizializza sessione e autenticazione
session_start();
require_once 'backend/middleware/Auth.php';

$auth = Auth::getInstance();

// Simula un utente autenticato per il test
if (!$auth->isAuthenticated()) {
    die("Per testare l'API devi essere autenticato. Vai a /login.php");
}

$user = $auth->getUser();

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Save API</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
        }
        .test-section {
            background: #f5f5f5;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .success {
            color: green;
            background: #e7f5e7;
            padding: 10px;
            border-radius: 3px;
        }
        .error {
            color: red;
            background: #ffe7e7;
            padding: 10px;
            border-radius: 3px;
        }
        button {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            margin: 5px;
        }
        button:hover {
            background: #0056b3;
        }
        #response {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 5px;
            white-space: pre-wrap;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <h1>Test API Save Advanced Document</h1>
    
    <div class="test-section">
        <h2>Informazioni Utente</h2>
        <p>Utente: <?php echo htmlspecialchars($user['nome'] . ' ' . $user['cognome']); ?></p>
        <p>ID: <?php echo $user['id']; ?></p>
        <p>Ruolo: <?php echo htmlspecialchars($user['role'] ?? 'utente'); ?></p>
    </div>

    <div class="test-section">
        <h2>Test 1: Crea nuovo documento</h2>
        <button onclick="testCreateDocument()">Crea Documento</button>
    </div>

    <div class="test-section">
        <h2>Test 2: Aggiorna documento esistente</h2>
        <input type="number" id="docId" placeholder="ID Documento" value="">
        <button onclick="testUpdateDocument()">Aggiorna Documento</button>
    </div>

    <div id="response"></div>

    <script>
        function testCreateDocument() {
            const data = {
                title: 'Test Documento ' + new Date().toLocaleString(),
                content: '<h1>Test Documento</h1><p>Questo è un documento di test creato alle ' + new Date().toLocaleString() + '</p>',
                plainText: 'Test Documento\nQuesto è un documento di test',
                stats: {
                    words: 10,
                    characters: 50
                },
                settings: {
                    fontSize: '14px',
                    fontFamily: 'Arial'
                }
            };

            fetch('/piattaforma-collaborativa/backend/api/save-advanced-document.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                document.getElementById('response').innerHTML = 
                    '<div class="' + (result.success ? 'success' : 'error') + '">' +
                    JSON.stringify(result, null, 2) + 
                    '</div>';
                
                if (result.docId) {
                    document.getElementById('docId').value = result.docId;
                }
            })
            .catch(error => {
                document.getElementById('response').innerHTML = 
                    '<div class="error">Errore: ' + error.toString() + '</div>';
            });
        }

        function testUpdateDocument() {
            const docId = document.getElementById('docId').value;
            if (!docId) {
                alert('Inserisci un ID documento valido');
                return;
            }

            const data = {
                docId: parseInt(docId),
                title: 'Documento Aggiornato ' + new Date().toLocaleString(),
                content: '<h1>Documento Aggiornato</h1><p>Questo documento è stato aggiornato alle ' + new Date().toLocaleString() + '</p>',
                plainText: 'Documento Aggiornato\nQuesto documento è stato aggiornato',
                stats: {
                    words: 15,
                    characters: 75
                },
                settings: {
                    fontSize: '16px',
                    fontFamily: 'Georgia'
                }
            };

            fetch('/piattaforma-collaborativa/backend/api/save-advanced-document.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                document.getElementById('response').innerHTML = 
                    '<div class="' + (result.success ? 'success' : 'error') + '">' +
                    JSON.stringify(result, null, 2) + 
                    '</div>';
            })
            .catch(error => {
                document.getElementById('response').innerHTML = 
                    '<div class="error">Errore: ' + error.toString() + '</div>';
            });
        }
    </script>
</body>
</html>