<?php
require_once 'backend/config/config.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$user = $auth->getUser();
// Database instance handled by functions
$currentAzienda = $auth->getCurrentAzienda();

$message = '';
$error = '';

// Carica dati completi utente dal database
$stmt = db_query("SELECT * FROM utenti WHERE id = ?", [$user['id']]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

// Gestisci aggiornamento profilo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['update_profile'])) {
            // Aggiorna informazioni profilo
            $nome = sanitize_input($_POST['nome'] ?? '');
            $cognome = sanitize_input($_POST['cognome'] ?? '');
            $email = sanitize_input($_POST['email'] ?? '');
            $telefono = sanitize_input($_POST['telefono'] ?? '');
            
            if (empty($nome) || empty($cognome) || empty($email)) {
                throw new Exception('Nome, cognome ed email sono obbligatori');
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Email non valida');
            }
            
            // Verifica che l'email non sia già in uso da un altro utente
            $stmt = db_query("SELECT id FROM utenti WHERE email = ? AND id != ?", [$email, $user['id']]);
            if ($stmt->fetch()) {
                throw new Exception('Email già in uso da un altro utente');
            }
            
            // Aggiorna database
            db_query("
                UPDATE utenti 
                SET nome = ?, cognome = ?, email = ?, telefono = ?
                WHERE id = ?
            ", [$nome, $cognome, $email, $telefono, $user['id']]);
            
            // Aggiorna sessione
            $_SESSION['user']['nome'] = $nome;
            $_SESSION['user']['cognome'] = $cognome;
            $_SESSION['user']['email'] = $email;
            $_SESSION['user']['telefono'] = $telefono;
            
            $message = 'Profilo aggiornato con successo';
            
            // Ricarica i dati utente
            $user = $auth->getUser();
            $stmt = db_query("SELECT * FROM utenti WHERE id = ?", [$user['id']]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } elseif (isset($_POST['change_password'])) {
            // Cambia password
            $password_attuale = $_POST['password_attuale'] ?? '';
            $password_nuova = $_POST['password_nuova'] ?? '';
            $password_conferma = $_POST['password_conferma'] ?? '';
            
            if (empty($password_attuale) || empty($password_nuova) || empty($password_conferma)) {
                throw new Exception('Compila tutti i campi password');
            }
            
            // Verifica password attuale
            $stmt = db_query("SELECT password FROM utenti WHERE id = ?", [$user['id']]);
            $currentPassword = $stmt->fetchColumn();
            
            if (!password_verify($password_attuale, $currentPassword)) {
                throw new Exception('Password attuale non corretta');
            }
            
            // Verifica che le nuove password coincidano
            if ($password_nuova !== $password_conferma) {
                throw new Exception('Le nuove password non coincidono');
            }
            
            // Verifica lunghezza minima
            if (strlen($password_nuova) < 6) {
                throw new Exception('La password deve essere di almeno 6 caratteri');
            }
            
            // Aggiorna password
            $password_hash = password_hash($password_nuova, PASSWORD_DEFAULT);
            db_query("UPDATE utenti SET password = ? WHERE id = ?", [$password_hash, $user['id']]);
            
            $message = 'Password aggiornata con successo';
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = 'Profilo';
require_once 'components/header.php';
?>

<!-- Clean Dashboard Styles -->
<link rel="stylesheet" href="assets/css/dashboard-clean.css">

<div class="page-header">
    <h1><i class="fas fa-user"></i> Il Mio Profilo</h1>
    <div class="page-subtitle">Gestisci le tue informazioni personali e impostazioni</div>
</div>

<?php if ($message): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-error">
    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<div class="grid-responsive grid-2">
    <!-- Informazioni profilo -->
    <div class="content-card">
        <div class="panel-header">
            <h2><i class="fas fa-user-circle"></i> Informazioni Personali</h2>
        </div>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="nome">Nome <span class="required">*</span></label>
                <input type="text" id="nome" name="nome" class="form-control" 
                       value="<?php echo htmlspecialchars($userData['nome'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="cognome">Cognome <span class="required">*</span></label>
                <input type="text" id="cognome" name="cognome" class="form-control"
                       value="<?php echo htmlspecialchars($userData['cognome'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email <span class="required">*</span></label>
                <input type="email" id="email" name="email" class="form-control"
                       value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="telefono">Telefono</label>
                <input type="tel" id="telefono" name="telefono" class="form-control"
                       value="<?php echo htmlspecialchars($userData['telefono'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label>Username</label>
                <input type="text" class="form-control" 
                       value="<?php echo htmlspecialchars($userData['username'] ?? ''); ?>" 
                       readonly style="background: #f7fafc; cursor: not-allowed;">
            </div>
            
            <div class="form-group">
                <label>Ruolo</label>
                <input type="text" class="form-control"
                       value="<?php echo ucfirst($userData['ruolo'] ?? ''); ?>" 
                       readonly style="background: #f7fafc; cursor: not-allowed;">
            </div>
            
            <div class="form-group">
                <label>Ultimo accesso</label>
                <input type="text" class="form-control"
                       value="<?php echo $userData['ultimo_accesso'] ? format_datetime($userData['ultimo_accesso']) : 'Mai'; ?>" 
                       readonly style="background: #f7fafc; cursor: not-allowed;">
            </div>
            
            <button type="submit" name="update_profile" class="btn btn-primary">
                <i class="fas fa-save"></i> Salva Modifiche
            </button>
        </form>
    </div>
    
    <!-- Cambio password -->
    <div class="content-card">
        <div class="panel-header">
            <h2><i class="fas fa-lock"></i> Cambia Password</h2>
        </div>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="password_attuale">Password Attuale <span class="required">*</span></label>
                <input type="password" id="password_attuale" name="password_attuale" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="password_nuova">Nuova Password <span class="required">*</span></label>
                <input type="password" id="password_nuova" name="password_nuova" class="form-control" required>
                <small >Minimo 6 caratteri</small>
            </div>
            
            <div class="form-group">
                <label for="password_conferma">Conferma Nuova Password <span class="required">*</span></label>
                <input type="password" id="password_conferma" name="password_conferma" class="form-control" required>
            </div>
            
            <button type="submit" name="change_password" class="btn btn-primary">
                <i class="fas fa-key"></i> Cambia Password
            </button>
        </form>
        
        <?php if ($auth->isSuperAdmin() || $auth->hasRoleInAzienda('proprietario')): ?>
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
            <h3 >
                <i class="fas fa-shield-alt"></i> Opzioni Sicurezza
            </h3>
            <p >Funzionalità avanzate di sicurezza</p>
            <div style="margin-top: 15px;">
                <button class="btn btn-secondary" disabled>
                    <i class="fas fa-mobile-alt"></i> Abilita 2FA (Prossimamente)
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Informazioni azienda se presente -->
<?php if ($currentAzienda): ?>
<div class="content-card" style="margin-top: 30px;">
    <div class="panel-header">
        <h2><i class="fas fa-building"></i> Azienda Corrente</h2>
    </div>
    
    <div class="info-grid">
        <div class="info-item">
            <div class="info-label">Nome Azienda</div>
            <div class="info-value"><?php echo htmlspecialchars($currentAzienda['azienda_nome'] ?? ''); ?></div>
        </div>
        
        <div class="info-item">
            <div class="info-label">Ruolo nell'Azienda</div>
            <div class="info-value">
                <?php 
                $ruoli = [
                    'proprietario' => 'Proprietario',
                    'admin' => 'Amministratore',
                    'utente' => 'Utente',
                    'ospite' => 'Ospite'
                ];
                echo $ruoli[$currentAzienda['ruolo_azienda'] ?? ''] ?? ucfirst($currentAzienda['ruolo_azienda'] ?? 'Utente');
                ?>
            </div>
        </div>
        
        <?php 
        // Carica info azienda
        $stmtAz = db_query("SELECT * FROM aziende WHERE id = ?", [$currentAzienda['azienda_id'] ?? $currentAzienda['id'] ?? 0]);
        $aziendaInfo = $stmtAz->fetch(PDO::FETCH_ASSOC);
        ?>
        
        <?php if ($aziendaInfo): ?>
        <div class="info-item">
            <div class="info-label">Email Azienda</div>
            <div class="info-value"><?php echo htmlspecialchars($aziendaInfo['email'] ?? 'Non specificata'); ?></div>
        </div>
        
        <div class="info-item">
            <div class="info-label">Telefono Azienda</div>
            <div class="info-value"><?php echo htmlspecialchars($aziendaInfo['telefono'] ?? 'Non specificato'); ?></div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once 'components/footer.php'; ?> 