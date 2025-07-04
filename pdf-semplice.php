<?php
// PDF Semplice per test
require_once 'backend/config/config.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$db = Database::getInstance();
$id = intval($_GET['id'] ?? 0);

// Carica documento
$stmt = $db->getConnection()->prepare("SELECT titolo, contenuto FROM documenti WHERE id = ?");
$stmt->execute([$id]);
$doc = $stmt->fetch();

if (!$doc) {
    die('Documento non trovato');
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>PDF - <?php echo htmlspecialchars($doc['titolo']); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            color: black;
            background: white;
        }
        h1 { 
            color: black;
            border-bottom: 2px solid black;
            padding-bottom: 10px;
        }
        @media print {
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <h1><?php echo htmlspecialchars($doc['titolo']); ?></h1>
    
    <?php if (empty($doc['contenuto'])): ?>
        <p style="color: red; font-weight: bold;">
            ATTENZIONE: Questo documento non ha contenuto salvato nel database!
        </p>
        <p>ID Documento: <?php echo $id; ?></p>
        <p>Per aggiungere contenuto, usa l'editor.</p>
    <?php else: ?>
        <?php echo $doc['contenuto']; ?>
    <?php endif; ?>
    
    <hr style="margin-top: 50px;">
    <p style="font-size: 12px; color: #666;">
        Documento ID: <?php echo $id; ?> | 
        Generato: <?php echo date('d/m/Y H:i:s'); ?>
    </p>
</body>
</html> 