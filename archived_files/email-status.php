<?php
require_once 'backend/config/config.php';
require_once 'backend/middleware/Auth.php';

$auth = Auth::getInstance();
$auth->requireAuth();

if (!$auth->hasElevatedPrivileges()) {
    header('Location: dashboard.php');
    exit;
}

require_once 'components/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h1>Stato Sistema Email</h1>
            
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">🚀 Servizi Email</h5>
                        </div>
                        <div class="card-body">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Servizio</th>
                                        <th>Stato</th>
                                        <th>Note</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>Brevo SMTP</strong></td>
                                        <td><span class="badge bg-info">Configurato</span></td>
                                        <td>smtp-relay.brevo.com:587</td>
                                    </tr>
                                    <tr>
                                        <td><strong>ElasticEmail</strong></td>
                                        <td><span class="badge bg-success">✓ Funzionante</span></td>
                                        <td>Servizio primario attuale</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Database Locale</strong></td>
                                        <td><span class="badge bg-success">✓ Funzionante</span></td>
                                        <td>Backup di tutte le email</td>
                                    </tr>
                                    <tr>
                                        <td><strong>SMTP</strong></td>
                                        <td><span class="badge bg-danger">Porte Bloccate</span></td>
                                        <td>Porte 25, 465, 587 bloccate</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">📊 Statistiche Email</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $stats = [];
                            
                            // Total emails
                            $total = db_query("SELECT COUNT(*) FROM email_notifications")->fetchColumn();
                            
                            // By status
                            $statuses = db_query("
                                SELECT status, COUNT(*) as count 
                                FROM email_notifications 
                                GROUP BY status
                            ")->fetchAll(PDO::FETCH_KEY_PAIR);
                            
                            // Today's emails
                            $today = db_query("
                                SELECT COUNT(*) FROM email_notifications 
                                WHERE DATE(created_at) = CURDATE()
                            ")->fetchColumn();
                            
                            // Success rate (ElasticEmail)
                            $elastic_success = db_query("
                                SELECT COUNT(*) FROM log_attivita 
                                WHERE azione = 'email_sent' 
                                AND dettagli LIKE '%ElasticEmail%'
                                AND dettagli LIKE '%success%'
                            ")->fetchColumn();
                            ?>
                            
                            <div class="row text-center">
                                <div class="col-md-6 mb-3">
                                    <h3><?php echo $total; ?></h3>
                                    <p class="text-muted">Email Totali</p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <h3><?php echo $today; ?></h3>
                                    <p class="text-muted">Email Oggi</p>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <h6>Stato Email:</h6>
                            <ul>
                                <li>In attesa: <?php echo $statuses['pending'] ?? 0; ?></li>
                                <li>Visualizzate: <?php echo $statuses['viewed'] ?? 0; ?></li>
                                <li>Inviate: <?php echo $statuses['sent'] ?? 0; ?></li>
                                <li>Fallite: <?php echo $statuses['failed'] ?? 0; ?></li>
                            </ul>
                            
                            <hr>
                            
                            <p><strong>Email inviate con successo via ElasticEmail:</strong> <?php echo $elastic_success; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header bg-warning">
                    <h5 class="mb-0">⚙️ Configurazione Brevo</h5>
                </div>
                <div class="card-body">
                    <p>Per attivare Brevo:</p>
                    <ol>
                        <li>Accedi a <a href="https://app.brevo.com" target="_blank">app.brevo.com</a></li>
                        <li>Verifica che l'API Key sia attiva nel menu "SMTP & API"</li>
                        <li>Aggiungi e verifica l'email mittente: <strong>info@nexiosolution.it</strong></li>
                        <li>Controlla i limiti del piano (free: 300 email/giorno)</li>
                    </ol>
                    
                    <div class="alert alert-info mt-3">
                        <strong>API Key configurata:</strong><br>
                        <code>xsmtpsib-...<?php echo substr(str_repeat('*', 60), 0, 60); ?>...LY59N8</code>
                    </div>
                </div>
            </div>
            
            <div class="mt-4">
                <a href="configurazione-email.php" class="btn btn-primary">Configurazione Email</a>
                <a href="notifiche-email.php" class="btn btn-info">Visualizza Notifiche</a>
                <a href="test-brevo-email.php" class="btn btn-warning">Test Brevo</a>
                <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'components/footer.php'; ?>