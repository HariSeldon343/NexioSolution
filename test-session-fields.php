<?php
/**
 * Test Session Field Validation
 * Verifica che i campi sessione siano accessibili senza warning
 */

// Start session
session_start();

// Simula una sessione utente con i campi corretti (come da Auth.php)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['nome'] = 'Test';
    $_SESSION['cognome'] = 'User';
    $_SESSION['user_role'] = 'super_admin';
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Test access to session fields with null coalescing
$userId = $_SESSION['user_id'] ?? null;
$nome = $_SESSION['nome'] ?? 'Utente';
$cognome = $_SESSION['cognome'] ?? '';
$role = $_SESSION['user_role'] ?? 'utente';

// Display results
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Test Session Fields</title>
    <style>
        body { font-family: sans-serif; padding: 20px; }
        .field { margin: 10px 0; padding: 10px; background: #f0f0f0; }
        .success { color: green; }
        .warning { color: orange; }
        .error { color: red; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>Session Field Test</h1>
    
    <div class="field">
        <h3>Session Data:</h3>
        <pre><?php print_r($_SESSION); ?></pre>
    </div>
    
    <div class="field">
        <h3>Field Access Test:</h3>
        <p>User ID: <span class="<?php echo $userId ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($userId ?? 'MISSING'); ?></span></p>
        <p>Nome: <span class="<?php echo $nome !== 'Utente' ? 'success' : 'warning'; ?>"><?php echo htmlspecialchars($nome); ?></span></p>
        <p>Cognome: <span class="<?php echo $cognome ? 'success' : 'warning'; ?>"><?php echo htmlspecialchars($cognome ?: '(vuoto)'); ?></span></p>
        <p>Role: <span class="<?php echo $role ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($role); ?></span></p>
        <p>Full Name: <span class="success"><?php echo htmlspecialchars(trim($nome . ' ' . $cognome)); ?></span></p>
    </div>
    
    <div class="field">
        <h3>OnlyOffice User Format:</h3>
        <?php
        $onlyofficeUser = [
            'id' => (string)($userId ?? '0'),
            'name' => trim(($nome ?? 'Utente') . ' ' . ($cognome ?? ''))
        ];
        ?>
        <pre><?php echo json_encode($onlyofficeUser, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
    </div>
    
    <div class="field">
        <h3>PHP Error Reporting:</h3>
        <?php
        // Test for warnings
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        
        // This would cause warning if not handled properly
        $testNome = $_SESSION['nome'] ?? 'Default';
        $testCognome = $_SESSION['cognome'] ?? '';
        
        // These would cause warnings (commented out to avoid actual warnings)
        // $wrongField1 = $_SESSION['user_nome'];  // Would cause "Undefined array key"
        // $wrongField2 = $_SESSION['user_cognome'];  // Would cause "Undefined array key"
        
        echo "<p class='success'>✓ No warnings when using null coalescing operator (??)</p>";
        ?>
    </div>
    
    <div class="field">
        <h3>Links:</h3>
        <ul>
            <li><a href="test-onlyoffice-open-document.php">Test OnlyOffice Open Document (Fixed)</a></li>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="filesystem.php">File System</a></li>
        </ul>
    </div>
</body>
</html>