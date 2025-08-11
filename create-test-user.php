<?php
/**
 * Create a test user for API testing
 */

require_once 'backend/config/config.php';

echo "Creating test user for API testing...\n";

$username = 'test_api_user';
$password = 'Test123!@#';
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

try {
    // Check if user already exists
    $stmt = db_query("SELECT id FROM utenti WHERE username = ?", [$username]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Update existing user
        $stmt = db_query(
            "UPDATE utenti SET 
                password = ?, 
                nome = 'Test API', 
                cognome = 'User', 
                email = 'test.api@nexio.com',
                ruolo = 'utente',
                attivo = 1
            WHERE username = ?",
            [$hashedPassword, $username]
        );
        echo "Updated existing test user.\n";
    } else {
        // Create new user
        $stmt = db_query(
            "INSERT INTO utenti (username, password, nome, cognome, email, ruolo, attivo) 
            VALUES (?, ?, 'Test API', 'User', 'test.api@nexio.com', 'utente', 1)",
            [$username, $hashedPassword]
        );
        echo "Created new test user.\n";
    }
    
    echo "\n";
    echo "Test User Credentials:\n";
    echo "---------------------\n";
    echo "Username: $username\n";
    echo "Password: $password\n";
    echo "\n";
    echo "You can use these credentials to test the login API.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}