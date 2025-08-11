<?php
require_once 'backend/config/config.php';
require_once 'backend/middleware/Auth.php';

$auth = Auth::getInstance();
$auth->requireAuth();

// Verifica se l'utente è admin
if (!$auth->hasElevatedPrivileges()) {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$messageType = '';

// Gestione test email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_email') {
    require_once 'backend/utils/Mailer.php';
    
    $testEmail = $_POST['test_email'] ?? $_SESSION['user_email'];
    $mailer = Mailer::getInstance();
    
    // Forza reload configurazione
    $mailer->reloadConfig();
    
    if ($mailer->sendTestEmail($testEmail)) {
        $message = "Email di test inviata/salvata! Controlla la sezione Notifiche Email.";
        $messageType = 'success';
    } else {
        $message = "Errore nell'invio dell'email di test.";
        $messageType = 'danger';
    }
}

// Salvataggio configurazione
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_config') {
    try {
        // Configurazioni SMTP
        $configs = [
            'smtp_enabled' => $_POST['smtp_enabled'] ?? '0',
            'smtp_host' => $_POST['smtp_host'] ?? '',
            'smtp_port' => $_POST['smtp_port'] ?? '587',
            'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',
            'smtp_username' => $_POST['smtp_username'] ?? '',
            'smtp_password' => $_POST['smtp_password'] ?? '',
            'smtp_from_email' => $_POST['smtp_from_email'] ?? '',
            'smtp_from_name' => $_POST['smtp_from_name'] ?? ''
        ];
        
        foreach ($configs as $key => $value) {
            // Non sovrascrivere password vuota se già presente
            if ($key === 'smtp_password' && empty($value)) {
                $existing = db_query("SELECT valore FROM configurazioni WHERE chiave = 'smtp_password'")->fetchColumn();
                if ($existing) continue;
            }
            
            db_query("
                INSERT INTO configurazioni (chiave, valore) 
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE valore = VALUES(valore)
            ", [$key, $value]);
        }
        
        // Notifiche
        $notifications = [
            'notify_event_created',
            'notify_event_modified',
            'notify_ticket_created',
            'notify_ticket_status_changed',
            'notify_document_created',
            'notify_document_modified',
            'notify_document_shared',
            'notify_user_created',
            'notify_password_reset'
        ];
        
        foreach ($notifications as $notif) {
            $value = isset($_POST[$notif]) ? '1' : '0';
            db_query("
                INSERT INTO configurazioni (chiave, valore) 
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE valore = VALUES(valore)
            ", [$notif, $value]);
        }
        
        $message = "Configurazione salvata con successo!";
        $messageType = 'success';
        
    } catch (Exception $e) {
        $message = "Errore nel salvataggio: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// Carica configurazione attuale
$config = [];
try {
    $stmt = db_query("SELECT chiave, valore FROM configurazioni WHERE chiave LIKE 'smtp_%' OR chiave LIKE 'notify_%'");
    while ($row = $stmt->fetch()) {
        $config[$row['chiave']] = $row['valore'];
    }
} catch (Exception $e) {
    // Ignora errori
}

// Valori di default
$defaults = [
    'smtp_enabled' => '0',
    'smtp_host' => 'mail.nexiosolution.it',
    'smtp_port' => '465',
    'smtp_encryption' => 'ssl',
    'smtp_username' => 'info@nexiosolution.it',
    'smtp_from_email' => 'info@nexiosolution.it',
    'smtp_from_name' => 'Nexio Solution'
];

foreach ($defaults as $key => $value) {
    if (!isset($config[$key])) {
        $config[$key] = $value;
    }
}

// Conta notifiche email
$emailCount = 0;
try {
    $userEmail = $_SESSION['user_email'] ?? $user['email'] ?? '';
    if ($userEmail) {
        $stmt = db_query("
            SELECT COUNT(*) FROM email_notifications 
            WHERE to_email = ?
        ", [$userEmail]);
        if ($stmt) {
            $emailCount = $stmt->fetchColumn();
        }
    }
} catch (Exception $e) {
    // Tabella non ancora creata o errore query
    error_log("Error counting email notifications: " . $e->getMessage());
}

$bodyClass = 'configurazione-email-page';
require_once 'components/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
<?php 
                require_once 'components/page-header.php';
                renderPageHeader('Configurazione Email', 'Impostazioni del sistema email', 'envelope'); 
                ?>
                
                <div class="action-bar">
                    <?php if ($emailCount > 0): ?>
                    <a href="notifiche-email.php" class="btn btn-info me-2">
                        <i class="fas fa-envelope"></i> Visualizza Notifiche (<?php echo $emailCount; ?>)
                    </a>
                    <?php endif; ?>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Torna alla Dashboard
                    </a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">⚠️ Nota Importante</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning">
                                <strong>Sistema Email Automatico:</strong>
                                <ul class="mb-0">
                                    <li>Le email vengono inviate tramite <strong>Brevo API</strong> (ex SendinBlue)</li>
                                    <li>Sistema configurato con API Key attiva</li>
                                    <li>Fallback automatico su altri servizi se necessario</li>
                                    <li>Tutte le email vengono salvate nel database locale</li>
                                    <li>Puoi visualizzarle nella sezione "Notifiche Email"</li>
                                </ul>
                            </div>
                            <div class="alert alert-success mt-2">
                                <strong>✅ Brevo SMTP Configurato:</strong> 
                                <br>Server: smtp-relay.brevo.com:587
                                <br>Username: 92cc1e001@smtp-brevo.com
                                <br><small>Fallback automatico su <strong>ElasticEmail</strong> se necessario</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="mb-0">Configurazione Server SMTP (Opzionale)</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="save_config">
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="smtp_enabled" 
                                           name="smtp_enabled" value="1" 
                                           <?php echo ($config['smtp_enabled'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="smtp_enabled">
                                        Abilita invio email tramite SMTP (se disponibile)
                                    </label>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label class="form-label">Server SMTP</label>
                                            <input type="text" class="form-control" name="smtp_host" 
                                                   value="<?php echo htmlspecialchars($config['smtp_host']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Porta</label>
                                            <select class="form-select" name="smtp_port">
                                                <option value="25" <?php echo $config['smtp_port'] == '25' ? 'selected' : ''; ?>>25</option>
                                                <option value="465" <?php echo $config['smtp_port'] == '465' ? 'selected' : ''; ?>>465 (SSL)</option>
                                                <option value="587" <?php echo $config['smtp_port'] == '587' ? 'selected' : ''; ?>>587 (TLS)</option>
                                                <option value="2525" <?php echo $config['smtp_port'] == '2525' ? 'selected' : ''; ?>>2525</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Username</label>
                                            <input type="text" class="form-control" name="smtp_username" 
                                                   value="<?php echo htmlspecialchars($config['smtp_username']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Password</label>
                                            <input type="password" class="form-control" name="smtp_password" 
                                                   placeholder="<?php echo !empty($config['smtp_password']) ? '••••••••' : ''; ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Email Mittente</label>
                                            <input type="email" class="form-control" name="smtp_from_email" 
                                                   value="<?php echo htmlspecialchars($config['smtp_from_email']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Nome Mittente</label>
                                            <input type="text" class="form-control" name="smtp_from_name" 
                                                   value="<?php echo htmlspecialchars($config['smtp_from_name']); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <h5>Notifiche Automatiche</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="notify_event_created" 
                                                   <?php echo ($config['notify_event_created'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Eventi creati</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="notify_ticket_created" 
                                                   <?php echo ($config['notify_ticket_created'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Ticket creati</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="notify_user_created" 
                                                   <?php echo ($config['notify_user_created'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Nuovi utenti</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="notify_password_reset" 
                                                   <?php echo ($config['notify_password_reset'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Reset password</label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Salva Configurazione
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Test Invio Email</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="test_email">
                                
                                <div class="mb-3">
                                    <label class="form-label">Email destinatario</label>
                                    <input type="email" class="form-control" name="test_email" 
                                           value="<?php echo $_SESSION['user_email']; ?>" required>
                                </div>
                                
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-paper-plane"></i> Invia Test
                                </button>
                                
                                <div class="alert alert-info mt-3">
                                    <small>L'email verrà salvata nelle tue Notifiche Email</small>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="mb-0">Come Funziona</h5>
                        </div>
                        <div class="card-body">
                            <ol class="small">
                                <li>Il sistema prova automaticamente diversi metodi di invio</li>
                                <li>Se l'invio fallisce, l'email viene salvata nel database</li>
                                <li>Puoi vedere tutte le email nella sezione "Notifiche Email"</li>
                                <li>Non è necessaria configurazione SMTP</li>
                            </ol>
                            
                            <hr>
                            
                            <h6>Debug (Solo Admin)</h6>
                            <ul class="small">
                                <li><a href="email-status.php">📊 Stato Sistema Email</a></li>
                                <li><a href="test-notifiche-complete.php">🔔 Test Notifiche Complete</a></li>
                                <li><a href="test-brevo-smtp.php" target="_blank">Test Brevo SMTP</a></li>
                                <li><a href="test-brevo-email.php" target="_blank">Test Brevo API</a></li>
                                <li><a href="test-email-finale.php" target="_blank">Test Sistema Email</a></li>
                                <li><a href="logs/error.log" target="_blank">Log Errori</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #e2e8f0;
}
.form-control, .form-select {
    border: 1px solid #d1d5db;
}
.form-control:focus, .form-select:focus {
    border-color: #4299e1;
    box-shadow: 0 0 0 0.2rem rgba(66, 153, 225, 0.25);
}
</style>

<?php require_once 'components/footer.php'; ?>