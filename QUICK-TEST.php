<?php
/**
 * TEST RAPIDO - Verifica stato del sistema
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 TEST RAPIDO SISTEMA</h1>\n";

echo "<style>
.test-ok { color: green; font-weight: bold; }
.test-error { color: red; font-weight: bold; }
.test-warning { color: orange; font-weight: bold; }
</style>\n";

try {
    echo "<h2>1. Test caricamento configurazione...</h2>\n";
    require_once 'backend/config/config.php';
    echo "<span class='test-ok'>✅ Config caricata</span><br>\n";
    
    echo "<h2>2. Test connessione database...</h2>\n";
    try {
        $pdo = db_connection();
        echo "<span class='test-ok'>✅ Database connesso</span><br>\n";
        
        // Test esistenza tabelle
        $tables = ['aziende', 'users'];
        foreach ($tables as $table) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
                $count = $stmt->fetchColumn();
                echo "<span class='test-ok'>✅ Tabella '$table' presente ($count record)</span><br>\n";
            } catch (Exception $e) {
                echo "<span class='test-error'>❌ Tabella '$table' mancante</span><br>\n";
            }
        }
    } catch (Exception $e) {
        echo "<span class='test-error'>❌ Errore database: " . $e->getMessage() . "</span><br>\n";
    }
    
    echo "<h2>3. Test sistema Auth...</h2>\n";
    try {
        $auth = Auth::getInstance();
        echo "<span class='test-ok'>✅ Auth class caricata</span><br>\n";
        
        // Test metodi
        $methods = ['isAuthenticated', 'isSuperAdmin', 'getCurrentAzienda', 'getUserPermissions'];
        foreach ($methods as $method) {
            if (method_exists($auth, $method)) {
                echo "<span class='test-ok'>✅ Metodo $method presente</span><br>\n";
            } else {
                echo "<span class='test-error'>❌ Metodo $method mancante</span><br>\n";
            }
        }
    } catch (Exception $e) {
        echo "<span class='test-error'>❌ Errore Auth: " . $e->getMessage() . "</span><br>\n";
    }
    
    echo "<h2>4. Test file essenziali...</h2>\n";
    $essential_files = [
        'dashboard.php',
        'aziende.php', 
        'login.php',
        'components/header.php',
        'components/menu.php',
        'backend/functions/aziende-functions.php'
    ];
    
    foreach ($essential_files as $file) {
        if (file_exists($file)) {
            echo "<span class='test-ok'>✅ $file presente</span><br>\n";
        } else {
            echo "<span class='test-error'>❌ $file mancante</span><br>\n";
        }
    }
    
    echo "<h2>5. Test pagine principali...</h2>\n";
    
    // Test dashboard
    echo "<p><strong>Dashboard:</strong> <a href='dashboard.php' target='_blank'>dashboard.php</a> ";
    if (file_exists('dashboard.php')) {
        echo "<span class='test-ok'>✅</span></p>\n";
    } else {
        echo "<span class='test-error'>❌</span></p>\n";
    }
    
    // Test login
    echo "<p><strong>Login:</strong> <a href='login.php' target='_blank'>login.php</a> ";
    if (file_exists('login.php')) {
        echo "<span class='test-ok'>✅</span></p>\n";
    } else {
        echo "<span class='test-error'>❌</span></p>\n";
    }
    
    // Test aziende
    echo "<p><strong>Aziende:</strong> <a href='aziende.php' target='_blank'>aziende.php</a> ";
    if (file_exists('aziende.php')) {
        echo "<span class='test-ok'>✅</span></p>\n";
    } else {
        echo "<span class='test-error'>❌</span></p>\n";
    }
    
    echo "<div style='background: #e3f2fd; padding: 20px; border-radius: 5px; margin: 20px 0;'>\n";
    echo "<h3>🎯 STATO SISTEMA:</h3>\n";
    
    $all_ok = true;
    
    // Verifica critica
    if (!file_exists('backend/config/config.php')) {
        echo "<p class='test-error'>❌ Configurazione mancante</p>\n";
        $all_ok = false;
    }
    
    if (!file_exists('dashboard.php')) {
        echo "<p class='test-error'>❌ Dashboard mancante</p>\n";
        $all_ok = false;
    }
    
    if (!file_exists('backend/middleware/Auth.php')) {
        echo "<p class='test-error'>❌ Sistema Auth mancante</p>\n";
        $all_ok = false;
    }
    
    if ($all_ok) {
        echo "<h4 class='test-ok'>🎉 SISTEMA PRONTO!</h4>\n";
        echo "<p>Se hai problemi di accesso:</p>\n";
        echo "<ol>\n";
        echo "<li>Esegui <a href='IMMEDIATE-FIX.php' target='_blank'>IMMEDIATE-FIX.php</a></li>\n";
        echo "<li>Prova il login con admin/admin123</li>\n";
        echo "<li>Verifica che il database sia attivo</li>\n";
        echo "</ol>\n";
    } else {
        echo "<h4 class='test-error'>⚠️ SISTEMA NON PRONTO</h4>\n";
        echo "<p>Esegui subito <a href='IMMEDIATE-FIX.php' target='_blank'>IMMEDIATE-FIX.php</a></p>\n";
    }
    
    echo "</div>\n";
    
} catch (Exception $e) {
    echo "<h2 class='test-error'>❌ ERRORE CRITICO:</h2>\n";
    echo "<div style='background: #ffebee; border: 2px solid #f44336; padding: 15px; border-radius: 5px;'>\n";
    echo "<p><strong>Errore:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>\n";
    echo "<p><strong>Linea:</strong> " . $e->getLine() . "</p>\n";
    echo "<p><strong>Soluzione:</strong> Esegui <a href='IMMEDIATE-FIX.php'>IMMEDIATE-FIX.php</a></p>\n";
    echo "</div>\n";
}
?>