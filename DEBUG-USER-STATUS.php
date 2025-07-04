<?php
/**
 * Debug dello stato utente attuale
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 DEBUG USER STATUS</h1>\n";

try {
    require_once 'backend/config/config.php';
    
    echo "<h2>1. Stato Sessione:</h2>\n";
    session_start();
    echo "<pre>\n";
    echo "Session ID: " . session_id() . "\n";
    echo "Session Data: " . print_r($_SESSION, true);
    echo "</pre>\n";
    
    echo "<h2>2. Test Auth System:</h2>\n";
    
    $auth = Auth::getInstance();
    echo "Auth instance creata: " . (is_object($auth) ? "✅ SI" : "❌ NO") . "<br>\n";
    echo "User authenticated: " . ($auth->isAuthenticated() ? "✅ SI" : "❌ NO") . "<br>\n";
    
    if ($auth->isAuthenticated()) {
        $user = $auth->getUser();
        echo "<h3>Dati Utente:</h3>\n";
        echo "<pre>" . print_r($user, true) . "</pre>\n";
        
        echo "Is Super Admin: " . ($auth->isSuperAdmin() ? "✅ SI" : "❌ NO") . "<br>\n";
        
        if (method_exists($auth, 'getCurrentAzienda')) {
            $azienda = $auth->getCurrentAzienda();
            echo "Azienda corrente: " . ($azienda ? print_r($azienda, true) : "Nessuna") . "<br>\n";
        }
    } else {
        echo "Utente non autenticato<br>\n";
    }
    
    echo "<h2>3. Database Users:</h2>\n";
    
    try {
        $pdo = db_connection();
        $stmt = $pdo->query("SELECT id, username, email, ruolo, attivo FROM users ORDER BY id");
        $users = $stmt->fetchAll();
        
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Ruolo</th><th>Attivo</th></tr>\n";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>{$user['id']}</td>";
            echo "<td>{$user['username']}</td>";
            echo "<td>{$user['email']}</td>";
            echo "<td><strong>{$user['ruolo']}</strong></td>";
            echo "<td>" . ($user['attivo'] ? "✅" : "❌") . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
        
    } catch (Exception $e) {
        echo "Errore database: " . $e->getMessage() . "<br>\n";
    }
    
    echo "<h2>4. Test Menu Logic:</h2>\n";
    
    if ($auth->isAuthenticated()) {
        $isSuperAdmin = $auth->isSuperAdmin();
        echo "Variable \$isSuperAdmin: " . ($isSuperAdmin ? "true" : "false") . "<br>\n";
        
        echo "<h3>Menu Items che dovrebbero essere visibili:</h3>\n";
        echo "<ul>\n";
        echo "<li>Dashboard ✅</li>\n";
        echo "<li>Aziende " . ($isSuperAdmin ? "✅" : "❌") . "</li>\n";
        echo "<li>Documenti ✅</li>\n";
        echo "<li>Calendario ✅</li>\n";
        echo "<li>Eventi ✅</li>\n";
        echo "<li>Supporto ✅</li>\n";
        echo "<li>Utenti " . ($isSuperAdmin ? "✅" : "❌") . "</li>\n";
        echo "<li>Profilo ✅</li>\n";
        echo "</ul>\n";
    }
    
    echo "<h2>5. Azioni Suggerite:</h2>\n";
    
    if (!$auth->isAuthenticated()) {
        echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px;'>\n";
        echo "<strong>⚠️ NON AUTENTICATO</strong><br>\n";
        echo "1. <a href='login.php'>Vai al Login</a><br>\n";
        echo "2. Usa: admin / admin123<br>\n";
        echo "</div>\n";
    } elseif (!$auth->isSuperAdmin()) {
        echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px;'>\n";
        echo "<strong>⚠️ NON SUPER ADMIN</strong><br>\n";
        echo "1. <a href='FIX-MENU-AZIENDE.php'>Esegui Fix Menu Aziende</a><br>\n";
        echo "2. Oppure fai logout e login di nuovo<br>\n";
        echo "</div>\n";
    } else {
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px;'>\n";
        echo "<strong>✅ TUTTO OK!</strong><br>\n";
        echo "Sei autenticato come Super Admin<br>\n";
        echo "1. <a href='dashboard.php'>Vai alla Dashboard</a><br>\n";
        echo "2. <a href='aziende.php'>Gestione Aziende</a><br>\n";
        echo "</div>\n";
    }
    
} catch (Exception $e) {
    echo "<h2>❌ ERRORE:</h2>\n";
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px;'>\n";
    echo "Errore: " . $e->getMessage() . "<br>\n";
    echo "File: " . $e->getFile() . "<br>\n";
    echo "Linea: " . $e->getLine() . "<br>\n";
    echo "</div>\n";
}
?>