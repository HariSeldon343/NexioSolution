<?php
// Disabilita temporaneamente l'output buffering per vedere eventuali errori
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'backend/config/config.php';

// Verifica autenticazione
$auth = Auth::getInstance();
$auth->requireAuth();

$db = Database::getInstance();
$id = intval($_GET['id'] ?? 0);

if (!$id) {
    die('ID documento non specificato');
}

// Query semplificata per il debug
$sql = "SELECT id, titolo, contenuto FROM documenti WHERE id = ?";
$stmt = $db->getConnection()->prepare($sql);
$stmt->execute([$id]);
$documento = $stmt->fetch();

if (!$documento) {
    die('Documento non trovato');
}

// Debug - mostra info sul contenuto
error_log("PDF Debug - ID: $id, Titolo: " . $documento['titolo']);
error_log("PDF Debug - Lunghezza contenuto: " . strlen($documento['contenuto'] ?? ''));
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($documento['titolo']); ?></title>
    <style>
        @page {
            size: A4;
            margin: 20mm;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            font-size: 12pt;
            line-height: 1.6;
            color: #000;
        }
        
        .page {
            background: white;
            padding: 20px;
        }
        
        h1 {
            color: #333;
            font-size: 24pt;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        
        .content {
            margin-top: 20px;
        }
        
        /* Assicura che tutto sia nero su bianco */
        * {
            color: #000 !important;
            background: white !important;
        }
        
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            
            .page {
                page-break-after: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <h1><?php echo htmlspecialchars($documento['titolo']); ?></h1>
        
        <div class="content">
            <?php 
            $contenuto = $documento['contenuto'] ?? '';
            
            if (empty($contenuto)) {
                echo '<p style="color: red;">ATTENZIONE: Il documento non ha contenuto salvato nel database.</p>';
                echo '<p>ID Documento: ' . $id . '</p>';
            } else {
                // Mostra il contenuto
                echo $contenuto;
                
                // Aggiungi info di debug alla fine
                echo '<hr style="margin-top: 50px;">';
                echo '<p style="font-size: 10pt; color: #666;">Debug Info:</p>';
                echo '<ul style="font-size: 10pt; color: #666;">';
                echo '<li>ID Documento: ' . $id . '</li>';
                echo '<li>Lunghezza contenuto: ' . strlen($contenuto) . ' caratteri</li>';
                echo '<li>Data generazione PDF: ' . date('Y-m-d H:i:s') . '</li>';
                echo '</ul>';
            }
            ?>
        </div>
    </div>
    
    <script>
        // Log per debug nella console del browser
        console.log('PDF Debug - Documento caricato');
        console.log('Titolo:', <?php echo json_encode($documento['titolo']); ?>);
        console.log('Contenuto presente:', <?php echo json_encode(!empty($contenuto)); ?>);
        console.log('Lunghezza contenuto:', <?php echo strlen($contenuto); ?>);
    </script>
</body>
</html> 