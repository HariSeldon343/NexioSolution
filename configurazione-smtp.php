<?php
require_once 'backend/config/config.php';

// Solo super admin
$auth = Auth::getInstance();
$auth->requireAuth();

if (!$auth->isSuperAdmin()) {
    die('Accesso negato - Solo super admin');
}

$pageTitle = 'Configurazione SMTP';
$message = '';

// Salva configurazione
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    $updates = [
        'smtp_enabled' => isset($_POST['smtp_enabled']) ? '1' : '0',
        'smtp_host' => trim($_POST['smtp_host']),
        'smtp_port' => trim($_POST['smtp_port']),
        'smtp_encryption' => $_POST['smtp_encryption'],
        'smtp_username' => trim($_POST['smtp_username']),
        'smtp_password' => trim($_POST['smtp_password']),
        'smtp_from_email' => trim($_POST['smtp_from_email']),
        'smtp_from_name' => trim($_POST['smtp_from_name'])
    ];
    
    try {
        foreach ($updates as $key => $value) {
            db_query("
                UPDATE configurazioni 
                SET valore = ? 
                WHERE chiave = ?
            ", [$value, $key]);
        }
        
        $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Configurazione SMTP aggiornata con successo!</div>';
        
        // Log attività
        ActivityLogger::getInstance()->log('configurazione', 'modifica', 0, 'Aggiornata configurazione SMTP');
        
    } catch (Exception $e) {
        $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Errore: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Carica configurazione attuale
$config_stmt = db_query("
    SELECT chiave, valore 
    FROM configurazioni 
    WHERE chiave LIKE 'smtp_%' 
    ORDER BY chiave
");
$configs = [];
while ($row = $config_stmt->fetch()) {
    $configs[$row['chiave']] = $row['valore'];
}

include 'components/header.php';
?>

<style>
.config-form {
    background: white;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    max-width: 800px;
    margin: 0 auto;
}
.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 8px;
}
.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}
.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
.form-group {
    margin-bottom: 20px;
}
.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #333;
}
.form-control {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 16px;
}
.form-control:focus {
    outline: none;
    border-color: #2d5a9f;
    box-shadow: 0 0 0 2px rgba(45, 90, 159, 0.2);
}
.checkbox-wrapper {
    display: flex;
    align-items: center;
    margin-bottom: 20px;
}
.checkbox-wrapper input[type="checkbox"] {
    margin-right: 10px;
    width: 20px;
    height: 20px;
}
.info-box {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
    border-left: 4px solid #2d5a9f;
}
.btn {
    padding: 12px 30px;
    border: none;
    border-radius: 5px;
    font-size: 16px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}
.btn-primary {
    background: #2d5a9f;
    color: white;
}
.btn-primary:hover {
    background: #1e4080;
}
.btn-secondary {
    background: #6c757d;
    color: white;
    margin-left: 10px;
}
.btn-secondary:hover {
    background: #5a6268;
}
.section-title {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e0e0e0;
}
</style>

<div class="container mt-4">
    <h1><i class="fas fa-cog"></i> Configurazione Server SMTP</h1>
    
    <?php echo $message; ?>
    
    <form method="POST" class="config-form">
        <div class="info-box">
            <i class="fas fa-info-circle"></i> Configura i parametri del server SMTP per l'invio delle email dal sistema.
        </div>
        
        <div class="checkbox-wrapper">
            <input type="checkbox" 
                   id="smtp_enabled" 
                   name="smtp_enabled" 
                   value="1" 
                   <?php echo ($configs['smtp_enabled'] ?? '0') == '1' ? 'checked' : ''; ?>>
            <label for="smtp_enabled">Abilita invio email tramite SMTP</label>
        </div>
        
        <div class="section-title">Parametri Server</div>
        
        <div class="form-group">
            <label for="smtp_host">Server SMTP</label>
            <input type="text" 
                   class="form-control" 
                   id="smtp_host" 
                   name="smtp_host" 
                   value="<?php echo htmlspecialchars($configs['smtp_host'] ?? ''); ?>"
                   placeholder="es. mail.nexiosolution.it">
        </div>
        
        <div class="form-group">
            <label for="smtp_port">Porta</label>
            <input type="number" 
                   class="form-control" 
                   id="smtp_port" 
                   name="smtp_port" 
                   value="<?php echo htmlspecialchars($configs['smtp_port'] ?? ''); ?>"
                   placeholder="es. 465">
        </div>
        
        <div class="form-group">
            <label for="smtp_encryption">Tipo di Crittografia</label>
            <select class="form-control" id="smtp_encryption" name="smtp_encryption">
                <option value="" <?php echo ($configs['smtp_encryption'] ?? '') == '' ? 'selected' : ''; ?>>Nessuna</option>
                <option value="ssl" <?php echo ($configs['smtp_encryption'] ?? '') == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                <option value="tls" <?php echo ($configs['smtp_encryption'] ?? '') == 'tls' ? 'selected' : ''; ?>>TLS</option>
            </select>
        </div>
        
        <div class="section-title">Credenziali</div>
        
        <div class="form-group">
            <label for="smtp_username">Username SMTP</label>
            <input type="text" 
                   class="form-control" 
                   id="smtp_username" 
                   name="smtp_username" 
                   value="<?php echo htmlspecialchars($configs['smtp_username'] ?? ''); ?>"
                   placeholder="es. info@nexiosolution.it">
        </div>
        
        <div class="form-group">
            <label for="smtp_password">Password SMTP</label>
            <input type="password" 
                   class="form-control" 
                   id="smtp_password" 
                   name="smtp_password" 
                   value="<?php echo htmlspecialchars($configs['smtp_password'] ?? ''); ?>"
                   placeholder="Inserisci la password">
        </div>
        
        <div class="section-title">Mittente</div>
        
        <div class="form-group">
            <label for="smtp_from_email">Email Mittente</label>
            <input type="email" 
                   class="form-control" 
                   id="smtp_from_email" 
                   name="smtp_from_email" 
                   value="<?php echo htmlspecialchars($configs['smtp_from_email'] ?? ''); ?>"
                   placeholder="es. info@nexiosolution.it">
        </div>
        
        <div class="form-group">
            <label for="smtp_from_name">Nome Mittente</label>
            <input type="text" 
                   class="form-control" 
                   id="smtp_from_name" 
                   name="smtp_from_name" 
                   value="<?php echo htmlspecialchars($configs['smtp_from_name'] ?? ''); ?>"
                   placeholder="es. Nexio Solution">
        </div>
        
        <div style="margin-top: 30px;">
            <button type="submit" name="save_config" class="btn btn-primary">
                <i class="fas fa-save"></i> Salva Configurazione
            </button>
            <a href="test-email.php" class="btn btn-secondary">
                <i class="fas fa-envelope"></i> Test Email
            </a>
        </div>
    </form>
    
    <div style="margin-top: 30px; padding: 20px; background: #fff3cd; border-radius: 8px; border: 1px solid #ffeaa7;">
        <h3><i class="fas fa-exclamation-triangle"></i> Configurazione Attuale Nexio</h3>
        <ul style="margin: 10px 0;">
            <li><strong>Server SMTP:</strong> mail.nexiosolution.it</li>
            <li><strong>Porta:</strong> 465 (SSL)</li>
            <li><strong>Username:</strong> info@nexiosolution.it</li>
            <li><strong>Email Mittente:</strong> info@nexiosolution.it</li>
        </ul>
    </div>
</div>

<?php include 'components/footer.php'; ?>