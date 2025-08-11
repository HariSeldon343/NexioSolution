<?php
/**
 * Test page for Log Attività table display
 * This page tests the table rendering without database dependency
 */

// Minimal setup for testing
define('APP_PATH', '/piattaforma-collaborativa');
$pageTitle = 'Test Log Table';
$bodyClass = 'log-attivita-page';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <meta name="csrf-token" content="test-token">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Base styles -->
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/style.css">
    
    <!-- Log Attività specific styles -->
    <link rel="stylesheet" href="<?php echo APP_PATH; ?>/assets/css/log-attivita.css">
    
    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .page-header {
            margin-bottom: 30px;
        }
        .page-header h1 {
            font-size: 28px;
            color: #1a202c;
            margin: 0 0 10px 0;
        }
        .page-subtitle {
            color: #64748b;
            font-size: 16px;
        }
        .test-info {
            background: #e0f2fe;
            border: 1px solid #7dd3fc;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 30px;
            color: #075985;
        }
    </style>
</head>
<body class="<?php echo $bodyClass; ?>">
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-history"></i> Test Log Attività Table</h1>
            <div class="page-subtitle">Testing clean table display without inline styles</div>
        </div>
        
        <div class="test-info">
            <i class="fas fa-info-circle"></i>
            <strong>Test Page:</strong> This page demonstrates the clean table structure with proper CSS styling.
            All columns should be properly aligned with no overlapping text.
        </div>
        
        <!-- Statistics Row -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-value">1,234</div>
                <div class="stat-label">Attività Totali</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">56</div>
                <div class="stat-label">Attività Oggi</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">89</div>
                <div class="stat-label">Documenti (7gg)</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">12</div>
                <div class="stat-label">Utenti Attivi</div>
            </div>
        </div>
        
        <!-- Table Container -->
        <div class="log-table-container">
            <table class="log-table">
                <thead>
                    <tr>
                        <th>Data/Ora</th>
                        <th>Utente</th>
                        <th>Tipo</th>
                        <th>Azione</th>
                        <th>Dettagli</th>
                        <th>Azienda</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Sample data for testing
                    $sampleLogs = [
                        [
                            'date' => '2025-01-11',
                            'time' => '14:35:22',
                            'user_name' => 'Mario Rossi',
                            'user_email' => 'mario.rossi@example.com',
                            'type' => 'documento',
                            'action' => 'creazione',
                            'details' => 'Creato nuovo documento "Relazione Tecnica Q1 2025.pdf" nella cartella Progetti',
                            'ip' => '192.168.1.100',
                            'company' => 'Azienda Test SRL'
                        ],
                        [
                            'date' => '2025-01-11',
                            'time' => '13:20:15',
                            'user_name' => 'Giulia Bianchi',
                            'user_email' => 'giulia.bianchi@example.com',
                            'type' => 'utente',
                            'action' => 'modifica',
                            'details' => 'Modificato profilo utente: aggiornato numero di telefono e indirizzo email secondario',
                            'ip' => '192.168.1.101',
                            'company' => 'Tech Solutions SpA'
                        ],
                        [
                            'date' => '2025-01-11',
                            'time' => '12:45:00',
                            'user_name' => 'Sistema',
                            'user_email' => '',
                            'type' => 'sistema',
                            'action' => 'backup',
                            'details' => 'Backup automatico completato con successo. File: backup_20250111_124500.sql (125 MB)',
                            'ip' => '127.0.0.1',
                            'company' => '-'
                        ],
                        [
                            'date' => '2025-01-11',
                            'time' => '11:30:45',
                            'user_name' => 'Luca Verdi',
                            'user_email' => 'luca.verdi@example.com',
                            'type' => 'azienda',
                            'action' => 'eliminazione',
                            'details' => 'Eliminata azienda "Old Company SRL" - ID: 45. Tutti i dati associati sono stati archiviati',
                            'ip' => '192.168.1.102',
                            'company' => 'Admin Global'
                        ],
                        [
                            'date' => '2025-01-11',
                            'time' => '10:15:30',
                            'user_name' => 'Anna Neri',
                            'user_email' => 'anna.neri@example.com',
                            'type' => 'documento',
                            'action' => 'download',
                            'details' => 'Scaricato file "Report_Vendite_2024.xlsx" (2.5 MB) dalla cartella Amministrazione/Report',
                            'ip' => '192.168.1.103',
                            'company' => 'Marketing Plus Srl'
                        ],
                        [
                            'date' => '2025-01-10',
                            'time' => '16:45:12',
                            'user_name' => 'Roberto Blu',
                            'user_email' => 'roberto.blu@example.com',
                            'type' => 'utente',
                            'action' => 'login',
                            'details' => 'Accesso effettuato con successo da Chrome 120.0 su Windows 11',
                            'ip' => '192.168.1.104',
                            'company' => 'Design Studio'
                        ],
                        [
                            'date' => '2025-01-10',
                            'time' => '15:20:00',
                            'user_name' => 'Sara Gialli',
                            'user_email' => 'sara.gialli@example.com',
                            'type' => 'documento',
                            'action' => 'modifica',
                            'details' => 'Aggiornato documento "Piano Marketing 2025.docx": modificate sezioni 3.2 e 4.1, aggiunti grafici performance',
                            'ip' => '192.168.1.105',
                            'company' => 'Creative Agency'
                        ],
                        [
                            'date' => '2025-01-10',
                            'time' => '14:00:00',
                            'user_name' => 'Marco Viola',
                            'user_email' => 'marco.viola@example.com',
                            'type' => 'sistema',
                            'action' => 'configurazione',
                            'details' => 'Aggiornate impostazioni SMTP: nuovo server mail.example.com porta 587 con autenticazione TLS',
                            'ip' => '192.168.1.106',
                            'company' => 'IT Services'
                        ]
                    ];
                    
                    foreach ($sampleLogs as $index => $log):
                        $rowClass = $index % 2 == 0 ? 'even' : 'odd';
                    ?>
                    <tr>
                        <td class="log-date-cell">
                            <div class="log-date"><?php echo date('d/m/Y', strtotime($log['date'])); ?></div>
                            <div class="log-time"><?php echo $log['time']; ?></div>
                        </td>
                        <td>
                            <div class="user-info-cell">
                                <div class="user-name"><?php echo htmlspecialchars($log['user_name']); ?></div>
                                <?php if ($log['user_email']): ?>
                                <div class="user-email"><?php echo htmlspecialchars($log['user_email']); ?></div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="entity-type <?php echo $log['type']; ?>"><?php echo $log['type']; ?></span>
                        </td>
                        <td>
                            <span class="action-badge <?php echo $log['action']; ?>">
                                <?php echo ucfirst($log['action']); ?>
                            </span>
                        </td>
                        <td class="details-cell">
                            <?php echo htmlspecialchars($log['details']); ?>
                            <?php if ($log['ip'] && $log['ip'] !== '127.0.0.1'): ?>
                            <div class="ip-info">
                                <i class="fas fa-globe"></i> IP: <?php echo $log['ip']; ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td class="company-cell">
                            <?php echo htmlspecialchars($log['company']); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <div class="pagination">
            <span class="disabled">&laquo; Prima</span>
            <span class="disabled">&lsaquo; Prec</span>
            <span class="current">1</span>
            <a href="#">2</a>
            <a href="#">3</a>
            <a href="#">4</a>
            <a href="#">5</a>
            <a href="#">Succ &rsaquo;</a>
            <a href="#">Ultima &raquo;</a>
        </div>
        
        <div style="margin-top: 40px; padding: 20px; background: #f0f9ff; border: 1px solid #0ea5e9; border-radius: 8px;">
            <h3 style="margin-top: 0; color: #0369a1;">✅ Test Results</h3>
            <ul style="margin: 10px 0; padding-left: 20px; color: #0c4a6e;">
                <li>All columns should be properly aligned with fixed widths</li>
                <li>No text should overlap between columns</li>
                <li>Date/Time column: 150px width</li>
                <li>User column: 180px width</li>
                <li>Type column: 100px width</li>
                <li>Action column: 120px width</li>
                <li>Details column: flexible width with proper word wrapping</li>
                <li>Company column: 150px width</li>
                <li>Table should have zebra striping (alternating row colors)</li>
                <li>Hover effect should highlight rows</li>
                <li>No inline styles or JavaScript interference</li>
            </ul>
        </div>
    </div>
    
    <!-- Load log-attivita JavaScript -->
    <script src="<?php echo APP_PATH; ?>/assets/js/log-attivita.js"></script>
    
    <script>
        // Test that JavaScript cleanup is working
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Test page loaded successfully');
            
            // Check for any inline styles on table rows
            const rows = document.querySelectorAll('.log-table tr');
            let inlineStylesFound = false;
            
            rows.forEach((row, index) => {
                if (row.style.cursor || row.style.userSelect) {
                    console.warn(`Row ${index} has inline styles:`, row.style.cssText);
                    inlineStylesFound = true;
                }
            });
            
            if (!inlineStylesFound) {
                console.log('✅ No problematic inline styles found on table rows');
            }
            
            // Check for expand indicators
            const expandIndicators = document.querySelectorAll('.expand-indicator');
            if (expandIndicators.length === 0) {
                console.log('✅ No expand indicators found');
            } else {
                console.warn('⚠️ Found expand indicators:', expandIndicators.length);
            }
            
            // Check table structure
            const table = document.querySelector('.log-table');
            if (table) {
                console.log('✅ Table structure found and intact');
                console.log('Table layout:', window.getComputedStyle(table).tableLayout);
            }
        });
    </script>
</body>
</html>