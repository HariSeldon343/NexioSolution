<?php
// Script per aggiornare la password dell'utente
require_once 'backend/config/config.php';

// Configurazione
$email = 'asamodeo@fortibyte.it';
$newPassword = 'Ricord@1991';

try {
    // Hash della password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Aggiorna la password nel database
    $sql = "UPDATE utenti SET password = :password WHERE email = :email";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':password' => $hashedPassword,
        ':email' => $email
    ]);
    
    if ($stmt->rowCount() > 0) {
        echo "✅ Password aggiornata con successo per l'utente: $email\n";
        echo "Nuova password: $newPassword\n";
    } else {
        // Verifica se l'utente esiste
        $checkSql = "SELECT * FROM utenti WHERE email = :email";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([':email' => $email]);
        
        if ($checkStmt->rowCount() == 0) {
            echo "❌ Errore: Utente con email '$email' non trovato nel database.\n";
            
            // Mostra tutti gli utenti esistenti
            echo "\n📋 Utenti esistenti nel database:\n";
            $usersSql = "SELECT id, nome, cognome, email FROM utenti";
            $usersStmt = $pdo->query($usersSql);
            $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($users) > 0) {
                foreach ($users as $user) {
                    echo "- ID: {$user['id']}, Nome: {$user['nome']} {$user['cognome']}, Email: {$user['email']}\n";
                }
            } else {
                echo "Nessun utente trovato nel database.\n";
            }
        }
    }
    
} catch (PDOException $e) {
    echo "❌ Errore database: " . $e->getMessage() . "\n";
}

// Elimina questo file dopo l'uso per sicurezza
echo "\n⚠️  IMPORTANTE: Elimina questo file dopo l'uso per motivi di sicurezza!\n";
?>