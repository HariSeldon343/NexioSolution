<?php
session_start();
require_once 'backend/config/config.php';

echo "<h1>Check Auth Status</h1>";

echo "<h2>1. Stato Sessione PHP:</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h2>2. Cookie di Sessione:</h2>";
echo "<pre>";
print_r($_COOKIE);
echo "</pre>";

// Se non c'è user_id nella sessione, proviamo a vedere se c'è nel cookie o altro
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color:red'>❌ Nessun user_id nella sessione</p>";
    
    // Verifica se c'è un utente super_admin nel database
    $db = Database::getInstance();
    $stmt = $db->query("SELECT * FROM utenti WHERE ruolo = 'super_admin' LIMIT 1");
    $superAdmin = $stmt->fetch();
    
    if ($superAdmin) {
        echo "<h3>Super Admin trovato nel database:</h3>";
        echo "<p>Email: " . $superAdmin['email'] . "</p>";
        echo "<p>Nome: " . $superAdmin['nome'] . " " . $superAdmin['cognome'] . "</p>";
        
        // Opzione per fare login automatico
        if (isset($_GET['auto_login'])) {
            $_SESSION['user_id'] = $superAdmin['id'];
            $_SESSION['user'] = [
                'id' => $superAdmin['id'],
                'email' => $superAdmin['email'],
                'nome' => $superAdmin['nome'],
                'cognome' => $superAdmin['cognome'],
                'ruolo' => $superAdmin['ruolo']
            ];
            $_SESSION['user_role'] = $superAdmin['ruolo'];
            $_SESSION['logged_in'] = true;
            
            echo "<p style='color:green'>✅ Login automatico effettuato!</p>";
            echo "<p><a href='gestione-moduli-template.php'>Vai alla gestione template</a></p>";
            
            // Reload per applicare
            echo "<script>setTimeout(() => location.reload(), 1000);</script>";
        } else {
            echo "<p><a href='?auto_login=1' class='btn' style='background:green;color:white;padding:10px;text-decoration:none;'>Effettua login automatico come Super Admin</a></p>";
        }
    } else {
        echo "<p style='color:red'>❌ Nessun super admin nel database</p>";
        
        // Crea un super admin di default
        if (isset($_GET['create_super_admin'])) {
            $password = password_hash('admin123', PASSWORD_DEFAULT);
            $db->query("INSERT INTO utenti (email, password, nome, cognome, ruolo, stato) VALUES (?, ?, ?, ?, ?, ?)",
                ['admin@example.com', $password, 'Super', 'Admin', 'super_admin', 'attivo']
            );
            echo "<p style='color:green'>✅ Super Admin creato!</p>";
            echo "<p>Email: admin@example.com</p>";
            echo "<p>Password: admin123</p>";
            echo "<p><a href='check-auth-status.php'>Ricarica questa pagina</a></p>";
        } else {
            echo "<p><a href='?create_super_admin=1' class='btn' style='background:blue;color:white;padding:10px;text-decoration:none;'>Crea Super Admin di default</a></p>";
        }
    }
} else {
    echo "<p style='color:green'>✅ Utente loggato con ID: " . $_SESSION['user_id'] . "</p>";
    if (isset($_SESSION['user']['ruolo'])) {
        echo "<p>Ruolo: " . $_SESSION['user']['ruolo'] . "</p>";
    }
}

echo "<hr>";
echo "<p><a href='login.php'>Vai al Login normale</a></p>";
echo "<p><a href='gestione-moduli-template.php'>Vai alla gestione template</a></p>";
echo "<p><a href='dashboard.php'>Vai alla Dashboard</a></p>";
?> 