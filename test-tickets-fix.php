<?php
/**
 * Test page per verificare le correzioni ai warnings PHP in tickets.php
 */

// Abilita la visualizzazione di tutti gli errori
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'backend/config/config.php';
require_once 'backend/middleware/Auth.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$pageTitle = 'Test Tickets - Verifica Warnings PHP';
define('APP_PATH', '/piattaforma-collaborativa');
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/nexio-improvements.css">
    <style>
        .test-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
        }
        .test-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .test-result {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .test-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .test-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        .test-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .code-block {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1>Test Correzioni Tickets - Warnings PHP</h1>
        
        <div class="test-section">
            <h2>1. Test Null Coalescing per Categoria e Priorità</h2>
            <?php
            // Simula un ticket con valori null
            $testTicket1 = [
                'categoria' => null,
                'priorita' => null,
                'azienda_nome' => null,
                'creatore_nome' => null,
                'creatore_cognome' => null
            ];
            
            echo '<div class="test-result test-success">';
            echo '<strong>Test con valori NULL:</strong><br>';
            echo 'Categoria: ' . ucfirst($testTicket1['categoria'] ?? '') . '<br>';
            echo 'Priorità: ' . ucfirst($testTicket1['priorita'] ?? 'media') . '<br>';
            echo 'Azienda: ' . htmlspecialchars($testTicket1['azienda_nome'] ?? 'N/A') . '<br>';
            echo 'Creatore: ' . htmlspecialchars(($testTicket1['creatore_nome'] ?? '') . ' ' . ($testTicket1['creatore_cognome'] ?? '')) . '<br>';
            echo '</div>';
            
            // Test con valori validi
            $testTicket2 = [
                'categoria' => 'tecnico',
                'priorita' => 'alta',
                'azienda_nome' => 'Test Company',
                'creatore_nome' => 'Mario',
                'creatore_cognome' => 'Rossi'
            ];
            
            echo '<div class="test-result test-success">';
            echo '<strong>Test con valori validi:</strong><br>';
            echo 'Categoria: ' . ucfirst($testTicket2['categoria'] ?? '') . '<br>';
            echo 'Priorità: ' . ucfirst($testTicket2['priorita'] ?? 'media') . '<br>';
            echo 'Azienda: ' . htmlspecialchars($testTicket2['azienda_nome'] ?? 'N/A') . '<br>';
            echo 'Creatore: ' . htmlspecialchars(($testTicket2['creatore_nome'] ?? '') . ' ' . ($testTicket2['creatore_cognome'] ?? '')) . '<br>';
            echo '</div>';
            ?>
        </div>
        
        <div class="test-section">
            <h2>2. Test Bottone Eliminazione per Super Admin</h2>
            <?php if ($auth->isSuperAdmin()): ?>
                <div class="test-result test-success">
                    <strong> Sei un Super Admin</strong><br>
                    Il bottone di eliminazione sarà visibile per i ticket chiusi.
                </div>
                
                <div style="margin-top: 20px;">
                    <h3>Esempio bottone eliminazione:</h3>
                    <button type="button" 
                            class="btn btn-danger delete-ticket-btn"
                            data-ticket-id="999"
                            data-ticket-code="DEMO-001"
                            data-ticket-status="chiuso"
                            disabled>
                        <i class="fas fa-trash"></i> Elimina Ticket Chiuso (Demo)
                    </button>
                    <p style="margin-top: 10px; color: #666;">
                        <small>Questo è solo un esempio. Il bottone è disabilitato per sicurezza.</small>
                    </p>
                </div>
            <?php else: ?>
                <div class="test-result test-warning">
                    <strong>  Non sei un Super Admin</strong><br>
                    Il bottone di eliminazione non sarà visibile per te.
                </div>
            <?php endif; ?>
        </div>
        
        <div class="test-section">
            <h2>3. Verifica API Delete Ticket</h2>
            <?php
            $apiPath = 'backend/api/delete-ticket.php';
            if (file_exists($apiPath)) {
                echo '<div class="test-result test-success">';
                echo '<strong> API delete-ticket.php trovata</strong><br>';
                echo 'Path: ' . $apiPath . '<br>';
                
                // Verifica sintassi
                $output = [];
                $return_var = 0;
                exec("/mnt/c/xampp/php/php.exe -l $apiPath 2>&1", $output, $return_var);
                
                if ($return_var === 0) {
                    echo 'Sintassi PHP: <span style="color: green;"> OK</span>';
                } else {
                    echo 'Sintassi PHP: <span style="color: red;"> Errore</span><br>';
                    echo '<pre>' . implode("\n", $output) . '</pre>';
                }
                echo '</div>';
            } else {
                echo '<div class="test-result test-error">';
                echo '<strong> API delete-ticket.php non trovata</strong>';
                echo '</div>';
            }
            ?>
        </div>
        
        <div class="test-section">
            <h2>4. Verifica JavaScript Enhancement</h2>
            <?php
            $jsPath = 'assets/js/tickets-enhancements.js';
            if (file_exists($jsPath)) {
                echo '<div class="test-result test-success">';
                echo '<strong> File tickets-enhancements.js trovato</strong><br>';
                echo 'Path: ' . $jsPath . '<br>';
                echo 'Dimensione: ' . filesize($jsPath) . ' bytes<br>';
                echo 'Ultima modifica: ' . date('d/m/Y H:i:s', filemtime($jsPath));
                echo '</div>';
                
                // Verifica che contenga la funzione di eliminazione
                $jsContent = file_get_contents($jsPath);
                if (strpos($jsContent, 'initTicketDeletion') !== false) {
                    echo '<div class="test-result test-success">';
                    echo '<strong> Funzione initTicketDeletion trovata</strong>';
                    echo '</div>';
                }
                if (strpos($jsContent, 'delete-ticket-btn') !== false) {
                    echo '<div class="test-result test-success">';
                    echo '<strong> Gestione classe delete-ticket-btn trovata</strong>';
                    echo '</div>';
                }
            } else {
                echo '<div class="test-result test-error">';
                echo '<strong> File tickets-enhancements.js non trovato</strong>';
                echo '</div>';
            }
            ?>
        </div>
        
        <div class="test-section">
            <h2>5. Query Database Ticket di Test</h2>
            <?php
            try {
                // Cerca un ticket chiuso per testing
                $stmt = db_query("
                    SELECT t.*, a.nome as azienda_nome, 
                           u.nome as creatore_nome, u.cognome as creatore_cognome
                    FROM tickets t
                    LEFT JOIN aziende a ON t.azienda_id = a.id
                    LEFT JOIN utenti u ON t.utente_id = u.id
                    WHERE t.stato = 'chiuso'
                    LIMIT 1
                ");
                
                if ($ticket = $stmt->fetch()) {
                    echo '<div class="test-result test-success">';
                    echo '<strong> Ticket chiuso trovato per test:</strong><br>';
                    echo 'Codice: ' . htmlspecialchars($ticket['codice'] ?? 'N/A') . '<br>';
                    echo 'Oggetto: ' . htmlspecialchars($ticket['oggetto'] ?? 'N/A') . '<br>';
                    echo 'Categoria (con null check): ' . ucfirst($ticket['categoria'] ?? '') . '<br>';
                    echo 'Priorità (con null check): ' . ucfirst($ticket['priorita'] ?? 'media') . '<br>';
                    echo 'Azienda (con null check): ' . htmlspecialchars($ticket['azienda_nome'] ?? 'N/A') . '<br>';
                    echo 'Creatore (con null check): ' . htmlspecialchars(($ticket['creatore_nome'] ?? '') . ' ' . ($ticket['creatore_cognome'] ?? '')) . '<br>';
                    echo '</div>';
                } else {
                    echo '<div class="test-result test-warning">';
                    echo '<strong>  Nessun ticket chiuso trovato nel database</strong><br>';
                    echo 'Non ci sono ticket chiusi per testare la funzionalità di eliminazione.';
                    echo '</div>';
                }
            } catch (Exception $e) {
                echo '<div class="test-result test-error">';
                echo '<strong> Errore query database:</strong><br>';
                echo htmlspecialchars($e->getMessage());
                echo '</div>';
            }
            ?>
        </div>
        
        <div class="test-section">
            <h2>Riepilogo Correzioni Applicate</h2>
            <div class="code-block">
                <strong>1. Null coalescing per categoria:</strong><br>
                PRIMA: &lt;?php echo ucfirst($ticket['categoria']); ?&gt;<br>
                DOPO: &lt;?php echo ucfirst($ticket['categoria'] ?? ''); ?&gt;<br><br>
                
                <strong>2. Null coalescing per priorità:</strong><br>
                PRIMA: &lt;?php echo ucfirst($ticket['priorita']); ?&gt;<br>
                DOPO: &lt;?php echo ucfirst($ticket['priorita'] ?? 'media'); ?&gt;<br><br>
                
                <strong>3. Null coalescing per azienda:</strong><br>
                PRIMA: &lt;?php echo htmlspecialchars($ticket['azienda_nome']); ?&gt;<br>
                DOPO: &lt;?php echo htmlspecialchars($ticket['azienda_nome'] ?? 'N/A'); ?&gt;<br><br>
                
                <strong>4. Null coalescing per nome/cognome:</strong><br>
                PRIMA: &lt;?php echo htmlspecialchars($ticket['creatore_nome'] . ' ' . $ticket['creatore_cognome']); ?&gt;<br>
                DOPO: &lt;?php echo htmlspecialchars(($ticket['creatore_nome'] ?? '') . ' ' . ($ticket['creatore_cognome'] ?? '')); ?&gt;
            </div>
        </div>
        
        <div style="margin-top: 40px; text-align: center;">
            <a href="tickets.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Torna ai Tickets
            </a>
        </div>
    </div>
    
    <script src="https://kit.fontawesome.com/your-fontawesome-kit.js"></script>
</body>
</html>