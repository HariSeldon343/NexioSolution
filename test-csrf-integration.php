<?php
/**
 * Test CSRF Integration
 * 
 * Script per testare l'integrazione del sistema CSRF
 * DA ESEGUIRE SOLO IN AMBIENTE DI TEST
 */

// Simula l'avvio di una sessione per il test
session_start();

require_once 'backend/config/config.php';
require_once 'backend/utils/CSRFTokenManager.php';

try {
    // Test generazione token
    $csrfManager = CSRFTokenManager::getInstance();
    $token1 = $csrfManager->getToken();
    
    echo "✓ Token generato: " . substr($token1, 0, 16) . "...\n";
    
    // Test validazione token corretto
    $_POST['csrf_token'] = $token1;
    $csrfManager->verifyRequest();
    echo "✓ Validazione token corretto: OK\n";
    
    // Test token non valido
    $_POST['csrf_token'] = 'invalid_token';
    try {
        $csrfManager->verifyRequest();
        echo "✗ Validazione token non valido: FALLITO (doveva lanciare eccezione)\n";
    } catch (Exception $e) {
        echo "✓ Validazione token non valido: OK (eccezione catturata)\n";
    }
    
    // Test token mancante
    unset($_POST['csrf_token']);
    try {
        $csrfManager->verifyRequest();
        echo "✗ Validazione token mancante: FALLITO (doveva lanciare eccezione)\n";
    } catch (Exception $e) {
        echo "✓ Validazione token mancante: OK (eccezione catturata)\n";
    }
    
    // Test rinnovo token
    $newToken = $csrfManager->renewToken();
    echo "✓ Token rinnovato: " . substr($newToken, 0, 16) . "...\n";
    
    if ($token1 !== $newToken) {
        echo "✓ Token rinnovato è diverso dal precedente\n";
    } else {
        echo "✗ Token rinnovato è uguale al precedente\n";
    }
    
    echo "\n=== Test CSRF completato ===\n";
    
} catch (Exception $e) {
    echo "✗ Errore durante i test: " . $e->getMessage() . "\n";
}
?>