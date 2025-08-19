<?php
/**
 * Test diretto dell'endpoint OnlyOffice
 */

// Simula ambiente di richiesta
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['csrf_token'] = 'test-token';
$_SESSION['azienda_id'] = 1;

// Imposta parametri GET per test
$_GET['action'] = 'server_status';

// Cattura output
ob_start();

try {
    // Includi direttamente l'endpoint
    require_once 'backend/api/onlyoffice-auth.php';
} catch (Exception $e) {
    echo "Exception catturata: " . $e->getMessage() . "\n";
}

$output = ob_get_clean();

// Analizza output
echo "=== Output ricevuto ===\n";
echo $output . "\n\n";

// Verifica se è JSON valido
$json = json_decode($output, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "✓ JSON valido!\n";
    echo "Contenuto:\n";
    print_r($json);
} else {
    echo "❌ JSON non valido: " . json_last_error_msg() . "\n";
    
    // Mostra primi caratteri per debug
    echo "Primi 100 caratteri:\n";
    for ($i = 0; $i < min(100, strlen($output)); $i++) {
        $char = $output[$i];
        $ord = ord($char);
        echo "[$i] '$char' (ASCII: $ord)\n";
        if ($ord < 32 && $ord !== 10 && $ord !== 13) {
            echo "  ^ Carattere di controllo non valido!\n";
        }
    }
}
?>