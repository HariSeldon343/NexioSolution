<?php
require_once 'backend/config/config.php';

$auth = Auth::getInstance();
$auth->requireAuth();

// Solo admin può configurare email
if (!$auth->isSuperAdmin()) {
    redirect(APP_PATH . '/dashboard.php');
}

// Database instance handled by functions
$message = '';
$error = '';

// Test email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
    try {
        require_once 'backend/utils/Mailer.php';
        $mailer = Mailer::getInstance();
        $mailer->reloadConfig();
        
        $testEmail = $_POST['test_email'];
        if ($mailer->sendTestEmail($testEmail)) {
            $message = "Email di test inviata con successo a $testEmail!";
        } else {
            $error = "Errore nell'invio dell'email di test. Verifica la configurazione SMTP. ";
            $error .= "Assicurati che il server SMTP, porta, credenziali e crittografia siano corretti per il tuo provider email.";
        }
    } catch (Exception $e) {
        $error = "Errore: " . $e->getMessage();
    }
}

// Salva configurazione
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['test_email'])) {
    try {
        // Inizio transazione gestita automaticamente
        
        // Configurazioni da salvare
        $configs = [
            'smtp_enabled' => $_POST['smtp_enabled'] ?? '0',
            'smtp_host' => $_POST['smtp_host'] ?? '',
            'smtp_port' => $_POST['smtp_port'] ?? '587',
            'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',
            'smtp_username' => $_POST['smtp_username'] ?? '',
            'smtp_password' => $_POST['smtp_password'] ?? '',
            'smtp_from_email' => $_POST['smtp_from_email'] ?? '',
            'smtp_from_name' => $_POST['smtp_from_name'] ?? '',
            // Fallback options
            'email_fallback_enabled' => $_POST['email_fallback_enabled'] ?? '1',
            'email_fallback_method' => $_POST['email_fallback_method'] ?? 'mail',
            'email_queue_enabled' => $_POST['email_queue_enabled'] ?? '1',
            // Notifiche
            'notify_document_created' => $_POST['notify_document_created'] ?? '0',
            'notify_document_modified' => $_POST['notify_document_modified'] ?? '0',
            'notify_ticket_created' => $_POST['notify_ticket_created'] ?? '0',
            'notify_ticket_status_changed' => $_POST['notify_ticket_status_changed'] ?? '0',
            'notify_event_created' => $_POST['notify_event_created'] ?? '0',
            'notify_event_modified' => $_POST['notify_event_modified'] ?? '0',
            'notify_user_created' => $_POST['notify_user_created'] ?? '0'
        ];
        
        // Salva ogni configurazione
        foreach ($configs as $key => $value) {
            // Non salvare password vuota se già presente una password
            if ($key === 'smtp_password' && empty($value)) {
                $stmt = db_query("SELECT valore FROM configurazioni WHERE chiave = 'smtp_password'");
                if ($stmt && $stmt->fetch()) {
                    continue; // Salta se c'è già una password salvata
                }
            }
            
            db_query("
                INSERT INTO configurazioni (chiave, valore) 
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE valore = VALUES(valore)
            ", [$key, $value]);
        }
        
        $message = "Configurazione salvata con successo!";
        
    } catch (Exception $e) {
        $error = "Errore nel salvataggio: " . $e->getMessage();
    }
}

// Carica configurazione attuale
$config = [];
try {
    $stmt = db_query("
        SELECT chiave, valore 
        FROM configurazioni 
        WHERE chiave LIKE 'smtp_%' OR chiave LIKE 'notify_%' OR chiave LIKE 'email_%'
    ");
    
    if ($stmt) {
        while ($row = $stmt->fetch()) {
            $config[$row['chiave']] = $row['valore'];
        }
    }
} catch (Exception $e) {
    $error = "Errore nel caricamento della configurazione: " . $e->getMessage();
}

// Controlla lo stato della coda email
$queueStats = [];
try {
    // Email in coda
    $stmt = db_query("SELECT COUNT(*) as count FROM notifiche_email WHERE stato = 'pending'");
    if ($stmt) $queueStats['pending'] = $stmt->fetch()['count'];
    
    // Email inviate oggi
    $stmt = db_query("SELECT COUNT(*) as count FROM notifiche_email WHERE stato = 'sent' AND DATE(inviato_il) = CURDATE()");
    if ($stmt) $queueStats['sent_today'] = $stmt->fetch()['count'];
    
    // Email fallite
    $stmt = db_query("SELECT COUNT(*) as count FROM notifiche_email WHERE stato = 'failed'");
    if ($stmt) $queueStats['failed'] = $stmt->fetch()['count'];
} catch (Exception $e) {
    // Tabella potrebbe non esistere
}

$pageTitle = 'Configurazione Email';
require_once 'components/header.php';
?>

<style>
.email-config-container {
    max-width: 1000px;
    margin: 0 auto;
}

.config-section {
    background: white;
    border-radius: 12px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    border: 1px solid #e8e8e8;
}

.config-section h2 {
    margin-bottom: 25px;
    color: #2c2c2c;
    font-size: 1.25rem;
    border-bottom: 2px solid #2d5a9f;
    padding-bottom: 10px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.config-toggle {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 24px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 16px;
    width: 16px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .toggle-slider {
    background-color: #2d5a9f;
}

input:checked + .toggle-slider:before {
    transform: translateX(26px);
}

.smtp-settings {
    transition: all 0.3s ease;
}

.smtp-settings.disabled {
    opacity: 0.5;
    pointer-events: none;
}

.notification-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 15px;
}

.notification-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 8px;
}

.queue-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    text-align: center;
    border: 1px solid #e8e8e8;
}

.stat-value {
    font-size: 2rem;
    font-weight: bold;
    color: #2d5a9f;
}

.stat-label {
    color: #6b6b6b;
    margin-top: 5px;
}

.test-section {
    background: #e3f2fd;
    padding: 20px;
    border-radius: 10px;
    border: 1px solid #90caf9;
}

.info-box {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.info-box i {
    color: #f39c12;
}

.encryption-options {
    display: flex;
    gap: 15px;
    margin-top: 10px;
}

.radio-option {
    display: flex;
    align-items: center;
    gap: 5px;
}

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
</style>

<div class="content-header">
    <h1><i class="fas fa-envelope"></i> Configurazione Email</h1>
</div>

<?php if ($message): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo $message; ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
    </div>
<?php endif; ?>

<div class="email-config-container">
    <!-- Statistiche Coda Email -->
    <?php if (!empty($queueStats)): ?>
    <div class="queue-stats">
        <div class="stat-card">
            <div class="stat-value"><?php echo $queueStats['pending'] ?? 0; ?></div>
            <div class="stat-label">Email in Coda</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $queueStats['sent_today'] ?? 0; ?></div>
            <div class="stat-label">Inviate Oggi</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $queueStats['failed'] ?? 0; ?></div>
            <div class="stat-label">Fallite</div>
        </div>
    </div>
    <?php endif; ?>
    
    <form method="POST" action="">
        <!-- Configurazione SMTP -->
        <div class="config-section">
            <h2><i class="fas fa-server"></i> Configurazione Server SMTP</h2>
            
            <div class="config-toggle">
                <label class="toggle-switch">
                    <input type="checkbox" name="smtp_enabled" value="1" 
                           <?php echo ($config['smtp_enabled'] ?? '0') === '1' ? 'checked' : ''; ?>
                           onchange="toggleSmtpSettings(this)">
                    <span class="toggle-slider"></span>
                </label>
                <span>Abilita invio email tramite SMTP</span>
            </div>
            
            <div class="smtp-settings <?php echo ($config['smtp_enabled'] ?? '0') !== '1' ? 'disabled' : ''; ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Server SMTP <span class="required">*</span></label>
                        <input type="text" name="smtp_host" class="form-control" 
                               placeholder="smtp.gmail.com"
                               value="<?php echo htmlspecialchars($config['smtp_host'] ?? ''); ?>">
                        <small class="text-muted">Es: smtp.gmail.com, smtp.office365.com</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Porta <span class="required">*</span></label>
                        <input type="number" name="smtp_port" class="form-control" 
                               placeholder="587"
                               value="<?php echo htmlspecialchars($config['smtp_port'] ?? '587'); ?>">
                        <small class="text-muted">587 (TLS), 465 (SSL), 25 (no encryption)</small>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Crittografia</label>
                        <div class="encryption-options">
                            <label class="radio-option">
                                <input type="radio" name="smtp_encryption" value="tls" 
                                       <?php echo ($config['smtp_encryption'] ?? 'tls') === 'tls' ? 'checked' : ''; ?>>
                                TLS (Consigliato)
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="smtp_encryption" value="ssl" 
                                       <?php echo ($config['smtp_encryption'] ?? '') === 'ssl' ? 'checked' : ''; ?>>
                                SSL
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="smtp_encryption" value="none" 
                                       <?php echo ($config['smtp_encryption'] ?? '') === 'none' ? 'checked' : ''; ?>>
                                Nessuna
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Username SMTP</label>
                        <input type="text" name="smtp_username" class="form-control" 
                               placeholder="tua.email@gmail.com"
                               value="<?php echo htmlspecialchars($config['smtp_username'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Password SMTP</label>
                        <input type="password" name="smtp_password" class="form-control" 
                               placeholder="<?php echo !empty($config['smtp_password']) ? '••••••••' : 'Inserisci password'; ?>">
                        <small class="text-muted">Lascia vuoto per mantenere la password esistente</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Email Mittente <span class="required">*</span></label>
                        <input type="email" name="smtp_from_email" class="form-control" 
                               placeholder="noreply@tuodominio.com"
                               value="<?php echo htmlspecialchars($config['smtp_from_email'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Nome Mittente <span class="required">*</span></label>
                        <input type="text" name="smtp_from_name" class="form-control" 
                               placeholder="Piattaforma Collaborativa"
                               value="<?php echo htmlspecialchars($config['smtp_from_name'] ?? 'Piattaforma Collaborativa'); ?>">
                    </div>
                </div>
            </div>
            
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <strong>Configurazioni comuni:</strong>
                <ul style="margin-top: 10px;">
                    <li><strong>Gmail/Google Workspace:</strong> smtp.gmail.com, porta 587, TLS</li>
                    <li><strong>Outlook/Office 365:</strong> smtp.office365.com, porta 587, TLS</li>
                    <li><strong>GoDaddy:</strong> smtpout.secureserver.net, porta 587, TLS</li>
                    <li><strong>Aruba:</strong> smtps.aruba.it, porta 465, SSL</li>
                    <li><strong>Yahoo:</strong> smtp.mail.yahoo.com, porta 587, TLS</li>
                </ul>
                <p style="margin-top: 10px; color: #e67e22;"><strong>Nota:</strong> Per Gmail, potrebbe essere necessario creare una "password per le app" invece della password normale.</p>
            </div>
        </div>
        
        <!-- Opzioni Fallback -->
        <div class="config-section">
            <h2><i class="fas fa-shield-alt"></i> Opzioni di Fallback</h2>
            
            <div class="config-toggle">
                <label class="toggle-switch">
                    <input type="checkbox" name="email_fallback_enabled" value="1" 
                           <?php echo ($config['email_fallback_enabled'] ?? '1') === '1' ? 'checked' : ''; ?>>
                    <span class="toggle-slider"></span>
                </label>
                <span>Abilita metodo di fallback se SMTP fallisce</span>
            </div>
            
            <div class="form-group">
                <label>Metodo di Fallback</label>
                <select name="email_fallback_method" class="form-control">
                    <option value="mail" <?php echo ($config['email_fallback_method'] ?? 'mail') === 'mail' ? 'selected' : ''; ?>>
                        Funzione mail() di PHP
                    </option>
                    <option value="sendmail" <?php echo ($config['email_fallback_method'] ?? '') === 'sendmail' ? 'selected' : ''; ?>>
                        Sendmail
                    </option>
                    <option value="queue" <?php echo ($config['email_fallback_method'] ?? '') === 'queue' ? 'selected' : ''; ?>>
                        Solo coda (processamento manuale)
                    </option>
                </select>
            </div>
            
            <div class="config-toggle">
                <label class="toggle-switch">
                    <input type="checkbox" name="email_queue_enabled" value="1" 
                           <?php echo ($config['email_queue_enabled'] ?? '1') === '1' ? 'checked' : ''; ?>>
                    <span class="toggle-slider"></span>
                </label>
                <span>Salva sempre le email in coda per processamento asincrono</span>
            </div>
        </div>
        
        <!-- Notifiche -->
        <div class="config-section">
            <h2><i class="fas fa-bell"></i> Notifiche Automatiche</h2>
            
            <div class="notification-grid">
                <div class="notification-item">
                    <input type="checkbox" id="notify_document_created" name="notify_document_created" value="1"
                           <?php echo ($config['notify_document_created'] ?? '0') === '1' ? 'checked' : ''; ?>>
                    <label for="notify_document_created">Documento creato</label>
                </div>
                
                <div class="notification-item">
                    <input type="checkbox" id="notify_document_modified" name="notify_document_modified" value="1"
                           <?php echo ($config['notify_document_modified'] ?? '0') === '1' ? 'checked' : ''; ?>>
                    <label for="notify_document_modified">Documento modificato</label>
                </div>
                
                <div class="notification-item">
                    <input type="checkbox" id="notify_ticket_created" name="notify_ticket_created" value="1"
                           <?php echo ($config['notify_ticket_created'] ?? '0') === '1' ? 'checked' : ''; ?>>
                    <label for="notify_ticket_created">Ticket creato</label>
                </div>
                
                <div class="notification-item">
                    <input type="checkbox" id="notify_ticket_status_changed" name="notify_ticket_status_changed" value="1"
                           <?php echo ($config['notify_ticket_status_changed'] ?? '0') === '1' ? 'checked' : ''; ?>>
                    <label for="notify_ticket_status_changed">Stato ticket cambiato</label>
                </div>
                
                <div class="notification-item">
                    <input type="checkbox" id="notify_event_created" name="notify_event_created" value="1"
                           <?php echo ($config['notify_event_created'] ?? '0') === '1' ? 'checked' : ''; ?>>
                    <label for="notify_event_created">Evento creato</label>
                </div>
                
                <div class="notification-item">
                    <input type="checkbox" id="notify_event_modified" name="notify_event_modified" value="1"
                           <?php echo ($config['notify_event_modified'] ?? '0') === '1' ? 'checked' : ''; ?>>
                    <label for="notify_event_modified">Evento modificato</label>
                </div>
                
                <div class="notification-item">
                    <input type="checkbox" id="notify_user_created" name="notify_user_created" value="1"
                           <?php echo ($config['notify_user_created'] ?? '0') === '1' ? 'checked' : ''; ?>>
                    <label for="notify_user_created">Utente creato</label>
                </div>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Salva Configurazione
            </button>
        </div>
    </form>
    
    <!-- Test Email -->
    <div class="config-section">
        <h2><i class="fas fa-vial"></i> Test Invio Email</h2>
        
        <div class="test-section">
            <form method="POST" action="">
                <div class="form-group">
                    <label>Email destinatario test</label>
                    <input type="email" name="test_email" class="form-control" 
                           placeholder="test@example.com" required>
                    <small class="text-muted">Inserisci un'email valida per ricevere il messaggio di test</small>
                </div>
                
                <button type="submit" class="btn btn-secondary">
                    <i class="fas fa-paper-plane"></i> Invia Email di Test
                </button>
            </form>
        </div>
    </div>
    
    <!-- Link utili -->
    <div class="config-section">
        <h2><i class="fas fa-tools"></i> Strumenti</h2>
        
        <div class="form-actions">
            <a href="<?php echo APP_PATH; ?>/processa-email.php" class="btn btn-secondary">
                <i class="fas fa-sync"></i> Processa Coda Email
            </a>
            <a href="<?php echo APP_PATH; ?>/email-log.php" class="btn btn-secondary">
                <i class="fas fa-list"></i> Visualizza Log Email
            </a>
        </div>
    </div>
</div>

<script>
function toggleSmtpSettings(checkbox) {
    const smtpSettings = document.querySelector('.smtp-settings');
    if (checkbox.checked) {
        smtpSettings.classList.remove('disabled');
    } else {
        smtpSettings.classList.add('disabled');
    }
}
</script>

<?php require_once 'components/footer.php'; ?>
 