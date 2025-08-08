<?php
/**
 * Check Authentication API
 * Returns authentication status for mobile app
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

require_once '../middleware/Auth.php';

try {
    $auth = Auth::getInstance();
    
    if ($auth->isAuthenticated()) {
        $user = $auth->getUser();
        $azienda = $auth->getAzienda();
        
        echo json_encode([
            'authenticated' => true,
            'user' => [
                'id' => $user['id'],
                'nome' => $user['nome'],
                'email' => $user['email'],
                'ruolo' => $user['ruolo'],
                'azienda_id' => $user['azienda_id']
            ],
            'azienda' => [
                'id' => $azienda['id'],
                'nome' => $azienda['nome']
            ]
        ]);
    } else {
        echo json_encode([
            'authenticated' => false,
            'message' => 'Utente non autenticato'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'authenticated' => false,
        'error' => $e->getMessage()
    ]);
}
?>