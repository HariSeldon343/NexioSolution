<?php
/**
 * Script di test per l'editor di documenti
 * Crea un documento di esempio e lo inserisce nel database
 */

require_once 'backend/config/config.php';
require_once 'vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

// Crea un documento di test
$phpWord = new PhpWord();

// Aggiungi metadati
$properties = $phpWord->getDocInfo();
$properties->setCreator('Nexio Test');
$properties->setCompany('Nexio');
$properties->setTitle('Documento di Test');
$properties->setDescription('Documento di test per editor online');
$properties->setSubject('Test Editor');

// Aggiungi contenuto
$section = $phpWord->addSection();

// Titolo principale
$section->addTitle('Documento di Test per Editor Online', 1);

// Paragrafo introduttivo
$section->addText(
    'Questo è un documento di test creato per verificare il funzionamento dell\'editor online di Nexio.',
    ['size' => 12]
);

// Sottotitolo
$section->addTitle('Funzionalità Supportate', 2);

// Lista puntata
$section->addListItem('Formattazione testo (grassetto, corsivo, sottolineato)');
$section->addListItem('Titoli e sottotitoli (H1-H6)');
$section->addListItem('Liste puntate e numerate');
$section->addListItem('Tabelle');
$section->addListItem('Indice automatico (TOC)');
$section->addListItem('Collaborazione in tempo reale');

// Tabella di esempio
$section->addTitle('Tabella di Esempio', 2);
$table = $section->addTable();
$table->addRow();
$table->addCell(2000)->addText('Colonna 1', ['bold' => true]);
$table->addCell(2000)->addText('Colonna 2', ['bold' => true]);
$table->addCell(2000)->addText('Colonna 3', ['bold' => true]);

for ($i = 1; $i <= 3; $i++) {
    $table->addRow();
    $table->addCell(2000)->addText("Riga $i, Col 1");
    $table->addCell(2000)->addText("Riga $i, Col 2");
    $table->addCell(2000)->addText("Riga $i, Col 3");
}

// Testo formattato
$section->addTitle('Formattazione Testo', 2);
$textrun = $section->addTextRun();
$textrun->addText('Questo è testo ');
$textrun->addText('grassetto', ['bold' => true]);
$textrun->addText(', questo è ');
$textrun->addText('corsivo', ['italic' => true]);
$textrun->addText(', e questo è ');
$textrun->addText('sottolineato', ['underline' => 'single']);
$textrun->addText('.');

// Salva il documento
$filename = 'test_document_' . time() . '.docx';
$filepath = 'uploads/documenti/' . $filename;

// Crea directory se non esiste
if (!is_dir('uploads/documenti')) {
    mkdir('uploads/documenti', 0755, true);
}

$writer = IOFactory::createWriter($phpWord, 'Word2007');
$writer->save($filepath);

// Inserisci nel database
try {
    $stmt = db_query(
        "INSERT INTO documenti (nome, percorso_file, dimensione, tipo_documento, azienda_id, creato_da, data_creazione)
         VALUES (?, ?, ?, ?, NULL, 1, NOW())",
        [
            'Test Editor Document.docx',
            $filename,
            filesize($filepath),
            'documento'
        ]
    );
    
    $documentId = db_connection()->lastInsertId();
    
    echo "<!DOCTYPE html>
<html lang='it'>
<head>
    <meta charset='UTF-8'>
    <title>Test Editor Documenti</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .btn {
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
        }
        .btn:hover {
            background: #0056b3;
        }
        .btn-success {
            background: #28a745;
        }
        .btn-success:hover {
            background: #218838;
        }
        h1 {
            color: #333;
        }
        pre {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <h1>✅ Test Editor Documenti Completato</h1>
    
    <div class='success'>
        <strong>Documento di test creato con successo!</strong><br>
        ID Documento: $documentId<br>
        Nome File: $filename<br>
        Dimensione: " . number_format(filesize($filepath) / 1024, 2) . " KB
    </div>
    
    <div class='info'>
        <strong>Prossimi passi:</strong><br>
        1. Apri l'editor per modificare il documento<br>
        2. Prova le funzionalità di formattazione<br>
        3. Salva il documento e verifica il versionamento<br>
        4. Apri in due browser per testare la collaborazione real-time
    </div>
    
    <div class='buttons'>
        <a href='document-editor.php?id=$documentId' class='btn btn-success' target='_blank'>
            📝 Apri nell'Editor Online
        </a>
        <a href='filesystem.php' class='btn'>
            📁 Vai al File Manager
        </a>
        <a href='dashboard.php' class='btn'>
            🏠 Torna alla Dashboard
        </a>
    </div>
    
    <h2>Test Plan</h2>
    <pre>
1. ✅ PHPWord installato con Composer
2. ✅ Pagina editor (document-editor.php) creata
3. ✅ API salvataggio (document-edit-save.php) creata
4. ✅ Hook 'Modifica online' aggiunto in filesystem.php
5. ✅ Documento di test creato
6. ⏳ Test apertura nell'editor
7. ⏳ Test salvataggio e versionamento
8. ⏳ Test collaborazione real-time (richiede WebSocket server attivo)
    </pre>
    
    <h2>Comandi Utili</h2>
    <pre>
# Avvia WebSocket server per collaborazione real-time:
/mnt/c/xampp/php/php.exe backend/websocket/server.php

# Verifica installazione PHPWord:
/mnt/c/xampp/php/php.exe composer.phar show phpoffice/phpword

# Test sintassi PHP:
/mnt/c/xampp/php/php.exe -l document-editor.php
/mnt/c/xampp/php/php.exe -l backend/api/document-edit-save.php
    </pre>
</body>
</html>";
    
} catch (Exception $e) {
    echo "Errore: " . $e->getMessage();
}
?>