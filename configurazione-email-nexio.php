<?php
require_once 'backend/config/config.php';
require_once 'backend/middleware/Auth.php';

$auth = new Auth();
$auth->requireAuth();
$auth->requireRole(['admin', 'super_admin']);

$message = '';
$messageType = '';

// Gestione form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'save_config':
                // Salva configurazione
                $configs = [
                    'smtp_enabled' => $_POST['smtp_enabled'] ?? '0',
                    'smtp_host' => $_POST['smtp_host'] ?? '',
                    'smtp_port' => $_POST['smtp_port'] ?? '587',
                    'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',
                    'smtp_username' => $_POST['smtp_username'] ?? '',
                    'smtp_password' => $_POST['smtp_password'] ?? '',
                    'smtp_from_email' => $_POST['smtp_from_email'] ?? '',
                    'smtp_from_name' => $_POST['smtp_from_name'] ?? '',
                ];
                
                foreach ($configs as $key => $value) {
                    $exists = db_query("SELECT id FROM configurazioni WHERE chiave = ?", [$key])->fetchColumn();
                    if ($exists) {
                        db_query("UPDATE configurazioni SET valore = ? WHERE chiave = ?", [$value, $key]);
                    } else {
                        db_query("INSERT INTO configurazioni (chiave, valore) VALUES (?, ?)", [$key, $value]);
                    }
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
                    $exists = db_query("SELECT id FROM configurazioni WHERE chiave = ?", [$notif])->fetchColumn();
                    if ($exists) {
                        db_query("UPDATE configurazioni SET valore = ? WHERE chiave = ?", [$value, $notif]);
                    } else {
                        db_query("INSERT INTO configurazioni (chiave, valore) VALUES (?, ?)", [$notif, $value]);
                    }
                }
                
                $message = "Configurazione salvata con successo!";
                $messageType = 'success';
                break;
                
            case 'test_email':
                // Test invio email
                require_once 'backend/utils/Mailer.php';
                $mailer = Mailer::getInstance();
                $mailer->reloadConfig();
                
                $testEmail = $_POST['test_email'] ?? $_SESSION['user_email'];
                if ($mailer->sendTestEmail($testEmail)) {
                    $message = "Email di test inviata a $testEmail! Controlla la casella di posta (incluso spam).";
                    $messageType = 'success';
                } else {
                    $message = "Errore nell'invio dell'email di test. Verifica la configurazione.";
                    $messageType = 'danger';
                }
                break;
        }
    }
}

// Carica configurazione attuale
$config = [];
$stmt = db_query("SELECT chiave, valore FROM configurazioni WHERE chiave LIKE 'smtp_%' OR chiave LIKE 'notify_%'");
while ($row = $stmt->fetch()) {
    $config[$row['chiave']] = $row['valore'];
}

// Valori di default
$defaults = [
    'smtp_enabled' => '0',
    'smtp_host' => 'mail.nexiosolution.it',
    'smtp_port' => '465',
    'smtp_encryption' => 'ssl',
    'smtp_username' => 'info@nexiosolution.it',
    'smtp_from_email' => 'info@nexiosolution.it',
    'smtp_from_name' => 'Nexio Solution',
];

foreach ($defaults as $key => $value) {
    if (!isset($config[$key])) {
        $config[$key] = $value;
    }
}

require_once 'components/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Configurazione Email</h1>
                <a href="configurazione-email.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Torna a Configurazioni
                </a>
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
                            <h5 class="mb-0">Configurazione Server SMTP</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="save_config">
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="smtp_enabled" 
                                           name="smtp_enabled" value="1" 
                                           <?php echo $config['smtp_enabled'] === '1' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="smtp_enabled">
                                        Abilita invio email tramite SMTP
                                    </label>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label class="form-label">Server SMTP</label>
                                            <input type="text" class="form-control" name="smtp_host" 
                                                   value="<?php echo htmlspecialchars($config['smtp_host']); ?>" 
                                                   placeholder="mail.example.com">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Porta</label>
                                            <select class="form-select" name="smtp_port">
                                                <option value="25" <?php echo $config['smtp_port'] == '25' ? 'selected' : ''; ?>>25 (Standard)</option>
                                                <option value="465" <?php echo $config['smtp_port'] == '465' ? 'selected' : ''; ?>>465 (SSL)</option>
                                                <option value="587" <?php echo $config['smtp_port'] == '587' ? 'selected' : ''; ?>>587 (TLS)</option>
                                                <option value="2525" <?php echo $config['smtp_port'] == '2525' ? 'selected' : ''; ?>>2525 (Alternative)</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Crittografia</label>
                                    <select class="form-select" name="smtp_encryption">
                                        <option value="" <?php echo $config['smtp_encryption'] == '' ? 'selected' : ''; ?>>Nessuna</option>
                                        <option value="ssl" <?php echo $config['smtp_encryption'] == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                        <option value="tls" <?php echo $config['smtp_encryption'] == 'tls' ? 'selected' : ''; ?>>TLS</option>
                                    </select>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Username SMTP</label>
                                            <input type="text" class="form-control" name="smtp_username" 
                                                   value="<?php echo htmlspecialchars($config['smtp_username']); ?>" 
                                                   placeholder="user@example.com">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Password SMTP</label>
                                            <input type="password" class="form-control" name="smtp_password" 
                                                   value="<?php echo htmlspecialchars($config['smtp_password'] ?? ''); ?>" 
                                                   placeholder="••••••••">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Email Mittente</label>
                                            <input type="email" class="form-control" name="smtp_from_email" 
                                                   value="<?php echo htmlspecialchars($config['smtp_from_email']); ?>" 
                                                   placeholder="noreply@example.com" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Nome Mittente</label>
                                            <input type="text" class="form-control" name="smtp_from_name" 
                                                   value="<?php echo htmlspecialchars($config['smtp_from_name']); ?>" 
                                                   placeholder="Nexio Platform">
                                        </div>
                                    </div>
                                </div>
                                
                                <hr class="my-4">
                                
                                <h5 class="mb-3">Notifiche Automatiche</h5>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Eventi</h6>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="notify_event_created" 
                                                   name="notify_event_created" value="1" 
                                                   <?php echo ($config['notify_event_created'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="notify_event_created">
                                                Nuovo evento creato
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="notify_event_modified" 
                                                   name="notify_event_modified" value="1" 
                                                   <?php echo ($config['notify_event_modified'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="notify_event_modified">
                                                Evento modificato
                                            </label>
                                        </div>
                                        
                                        <h6 class="mt-3">Ticket</h6>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="notify_ticket_created" 
                                                   name="notify_ticket_created" value="1" 
                                                   <?php echo ($config['notify_ticket_created'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="notify_ticket_created">
                                                Nuovo ticket
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="notify_ticket_status_changed" 
                                                   name="notify_ticket_status_changed" value="1" 
                                                   <?php echo ($config['notify_ticket_status_changed'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="notify_ticket_status_changed">
                                                Cambio stato ticket
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <h6>Documenti</h6>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="notify_document_created" 
                                                   name="notify_document_created" value="1" 
                                                   <?php echo ($config['notify_document_created'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="notify_document_created">
                                                Nuovo documento
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="notify_document_modified" 
                                                   name="notify_document_modified" value="1" 
                                                   <?php echo ($config['notify_document_modified'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="notify_document_modified">
                                                Documento modificato
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="notify_document_shared" 
                                                   name="notify_document_shared" value="1" 
                                                   <?php echo ($config['notify_document_shared'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="notify_document_shared">
                                                Documento condiviso
                                            </label>
                                        </div>
                                        
                                        <h6 class="mt-3">Utenti</h6>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="notify_user_created" 
                                                   name="notify_user_created" value="1" 
                                                   <?php echo ($config['notify_user_created'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="notify_user_created">
                                                Nuovo utente
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="notify_password_reset" 
                                                   name="notify_password_reset" value="1" 
                                                   <?php echo ($config['notify_password_reset'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="notify_password_reset">
                                                Reset password
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Salva Configurazione
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
                                    <label class="form-label">Email destinatario test</label>
                                    <input type="email" class="form-control" name="test_email" 
                                           value="<?php echo $_SESSION['user_email']; ?>" required>
                                    <small class="text-muted">Inserisci l'email dove vuoi ricevere il test</small>
                                </div>
                                
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="bi bi-send"></i> Invia Email di Test
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="mb-0">Informazioni</h5>
                        </div>
                        <div class="card-body">
                            <h6>Credenziali Nexio Preconfigurate:</h6>
                            <ul class="small">
                                <li><strong>Server:</strong> mail.nexiosolution.it</li>
                                <li><strong>Porta:</strong> 465 (SSL) o 587 (TLS)</li>
                                <li><strong>Username:</strong> info@nexiosolution.it</li>
                                <li><strong>Password:</strong> ••••••••</li>
                            </ul>
                            
                            <hr>
                            
                            <h6>Note Importanti:</h6>
                            <ul class="small">
                                <li>Il sistema supporta PHPMailer con fallback automatici</li>
                                <li>Le email potrebbero finire nello spam al primo invio</li>
                                <li>Verifica che il server permetta connessioni SMTP in uscita</li>
                                <li>I log degli invii sono disponibili in Audit Log</li>
                            </ul>
                            
                            <?php if ($_SESSION['user_role'] === 'super_admin'): ?>
                            <hr>
                            <h6>Debug:</h6>
                            <ul class="small">
                                <li><a href="test-phpmailer-nexio.php" target="_blank">Test PHPMailer</a></li>
                                <li><a href="test-email-nexio.php" target="_blank">Test Completo</a></li>
                                <li><a href="logs/error.log" target="_blank">Error Log</a></li>
                            </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'components/footer.php'; ?>