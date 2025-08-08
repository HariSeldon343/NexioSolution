<?php
session_start();
require_once 'backend/config/config.php';

// Verifica accesso (solo super admin)
if (!isset($_SESSION['user_id']) || $_SESSION['tipo'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

$query_result = null;
$error = null;

if ($_POST['sql_query'] ?? '') {
    $sql = trim($_POST['sql_query']);
    try {
        if (stripos($sql, 'SELECT') === 0 || stripos($sql, 'SHOW') === 0 || stripos($sql, 'DESCRIBE') === 0) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $query_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $query_result = "Query eseguita con successo. Righe interessate: " . $stmt->rowCount();
        }
    } catch (PDOException $e) {
        $error = "Errore: " . $e->getMessage();
    }
}

// Ottieni lista tabelle
try {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $tables = [];
    $error = "Errore connessione: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Manager - Nexio</title>
    <link href="assets/css/modern-theme.css" rel="stylesheet">
    <style>
        .db-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .query-box { background: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
        .result-table { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .result-table table { width: 100%; border-collapse: collapse; }
        .result-table th { background: #333; color: white; padding: 12px; text-align: left; }
        .result-table td { padding: 8px 12px; border-bottom: 1px solid #eee; }
        .result-table tr:nth-child(even) { background: #f8f9fa; }
        .tables-list { background: white; border-radius: 8px; padding: 20px; }
        .table-item { display: inline-block; margin: 5px; padding: 8px 12px; background: #e9ecef; border-radius: 4px; text-decoration: none; color: #333; }
        .table-item:hover { background: #333; color: white; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 10px 0; }
        textarea { font-family: 'Courier New', monospace; }
    </style>
</head>
<body>
    <?php include 'components/header.php'; ?>
    
    <div class="db-container">
        <h1>🗄️ Database Manager</h1>
        <p>Gestione diretta del database <strong><?= DB_NAME ?></strong></p>
        
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="query-box">
            <h3>Esegui Query SQL</h3>
            <form method="POST">
                <div class="form-group">
                    <textarea name="sql_query" rows="5" class="form-control" placeholder="Inserisci la tua query SQL qui...
Esempi:
- SHOW TABLES;
- SELECT * FROM utenti LIMIT 10;
- DESCRIBE utenti;"><?= htmlspecialchars($_POST['sql_query'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Esegui Query</button>
                <button type="button" onclick="document.querySelector('textarea').value='SHOW TABLES;'" class="btn btn-secondary">Mostra Tabelle</button>
                <button type="button" onclick="document.querySelector('textarea').value='SELECT * FROM utenti LIMIT 10;'" class="btn btn-secondary">Mostra Utenti</button>
            </form>
        </div>
        
        <?php if ($query_result): ?>
            <div class="result-table">
                <h3>Risultato</h3>
                <?php if (is_array($query_result) && !empty($query_result)): ?>
                    <table>
                        <thead>
                            <tr>
                                <?php foreach (array_keys($query_result[0]) as $column): ?>
                                    <th><?= htmlspecialchars($column) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($query_result as $row): ?>
                                <tr>
                                    <?php foreach ($row as $value): ?>
                                        <td><?= htmlspecialchars($value ?? 'NULL') ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p><strong>Righe trovate:</strong> <?= count($query_result) ?></p>
                <?php else: ?>
                    <div class="success"><?= htmlspecialchars($query_result) ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="tables-list">
            <h3>Tabelle disponibili</h3>
            <?php if (!empty($tables)): ?>
                <?php foreach ($tables as $table): ?>
                    <a href="#" onclick="document.querySelector('textarea').value='SELECT * FROM <?= $table ?> LIMIT 10;'; return false;" class="table-item">
                        <?= htmlspecialchars($table) ?>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Nessuna tabella trovata o errore di connessione.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'components/footer.php'; ?>
</body>
</html> 