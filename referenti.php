<?php
require_once 'backend/config/config.php';
require_once 'backend/utils/ActivityLogger.php';
require_once 'backend/utils/NotificationManager.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$user = $auth->getUser();
// Database instance handled by functions
$logger = ActivityLogger::getInstance();
$notificationManager = NotificationManager::getInstance();

// Solo admin possono accedere
if (!$auth->canAccess('users', 'write')) {
    redirect(APP_PATH . '/dashboard.php');
}

$azienda_id = $_GET['azienda_id'] ?? null;
$action = $_GET['action'] ?? 'list';
$referente_id = $_GET['id'] ?? null;

// Verifica se l'utente è proprietario dell'azienda
$isProprietario = false;
if ($azienda_id) {
    $stmt = db_query("SELECT ruolo_azienda FROM utenti_aziende WHERE utente_id = :utente_id AND azienda_id = :azienda_id AND attivo = 1", 
                       ['utente_id' => $user['id'], 'azienda_id' => $azienda_id]);
    $ruoloAzienda = $stmt->fetch();
    $isProprietario = $ruoloAzienda && $ruoloAzienda['ruolo_azienda'] === 'proprietario';
}

// Può eliminare definitivamente: super admin o proprietario azienda
$canDeletePermanently = $auth->isSuperAdmin() || $isProprietario;

if (!$azienda_id) {
    $_SESSION['error'] = "Azienda non specificata";
    redirect(APP_PATH . '/aziende.php');
}

// Carica azienda
$stmt = db_query("SELECT * FROM aziende WHERE id = :id", ['id' => $azienda_id]);
$azienda = $stmt->fetch();

if (!$azienda) {
    $_SESSION['error'] = "Azienda non trovata";
    redirect(APP_PATH . '/aziende.php');
}

// Verifica limite referenti dell'azienda
$max_referenti = $azienda['max_referenti'] ?? 5;

// Gestione azione riattiva (GET)
if ($action === 'attiva' && $referente_id && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = db_query("SELECT * FROM referenti_aziende WHERE id = :id", ['id' => $referente_id]);
        $referente = $stmt->fetch();
        
        // Verifica il numero di referenti attivi
        $stmt = db_query("SELECT COUNT(*) as count FROM referenti_aziende WHERE azienda_id = :azienda_id AND attivo = 1", 
                         ['azienda_id' => $azienda_id]);
        $attivi = $stmt->fetch()['count'];
        
        if ($attivi >= $max_referenti) {
            $_SESSION['error'] = "Non puoi attivare questo referente. Limite massimo di $max_referenti referenti attivi raggiunto.";
        } else {
            db_update('referenti_aziende', ['attivo' => 1], 'id = :id', ['id' => $referente_id]);
            
            // Riattiva anche l'utente associato
            if ($referente['utente_id']) {
                db_update('utenti', ['attivo' => 1], 'id = :id', ['id' => $referente['utente_id']]);
            }
            
            $logger->log('referente', 'riattivazione', $referente_id, 
                "Riattivato referente {$referente['nome']} {$referente['cognome']}");
            
            $_SESSION['success'] = "Referente riattivato con successo!";
        }
        redirect(APP_PATH . "/referenti.php?azienda_id=$azienda_id");
    } catch (Exception $e) {
        $_SESSION['error'] = "Errore durante la riattivazione: " . $e->getMessage();
        redirect(APP_PATH . "/referenti.php?azienda_id=$azienda_id");
    }
}

// Gestione azioni POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'nuovo' || $action === 'edit') {
        $data = [
            'azienda_id' => $azienda_id,
            'nome' => sanitize_input($_POST['nome'] ?? ''),
            'cognome' => sanitize_input($_POST['cognome'] ?? ''),
            'email' => sanitize_input($_POST['email'] ?? ''),
            'telefono' => sanitize_input($_POST['telefono'] ?? ''),
            'ruolo_aziendale' => sanitize_input($_POST['ruolo_aziendale'] ?? ''),
            // Permessi
            'puo_vedere_documenti' => isset($_POST['puo_vedere_documenti']) ? 1 : 0,
            'puo_creare_documenti' => isset($_POST['puo_creare_documenti']) ? 1 : 0,
            'puo_modificare_documenti' => isset($_POST['puo_modificare_documenti']) ? 1 : 0,
            'puo_eliminare_documenti' => isset($_POST['puo_eliminare_documenti']) ? 1 : 0,
            'puo_scaricare_documenti' => isset($_POST['puo_scaricare_documenti']) ? 1 : 0,
            'puo_compilare_moduli' => isset($_POST['puo_compilare_moduli']) ? 1 : 0,
            'puo_aprire_ticket' => isset($_POST['puo_aprire_ticket']) ? 1 : 0,
            'puo_gestire_eventi' => isset($_POST['puo_gestire_eventi']) ? 1 : 0,
            'puo_vedere_referenti' => isset($_POST['puo_vedere_referenti']) ? 1 : 0,
            'puo_gestire_referenti' => isset($_POST['puo_gestire_referenti']) ? 1 : 0,
            'puo_vedere_log' => isset($_POST['puo_vedere_log']) ? 1 : 0,
            'riceve_notifiche_email' => isset($_POST['riceve_notifiche_email']) ? 1 : 0,
            'attivo' => isset($_POST['attivo']) ? 1 : 0
        ];
        
        // Password solo per nuovo referente
        $password = trim($_POST['password'] ?? '');
        
        // Validazione
        $errors = [];
        if (empty($data['nome'])) $errors[] = "Nome obbligatorio";
        if (empty($data['cognome'])) $errors[] = "Cognome obbligatorio";
        if (empty($data['email'])) $errors[] = "Email obbligatoria";
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = "Email non valida";
        
        // Per nuovo referente, verifica password
        if ($action === 'nuovo') {
            if (empty($password)) $errors[] = "Password obbligatoria";
            if (strlen($password) < 8) $errors[] = "La password deve essere di almeno 8 caratteri";
            
            // Verifica limite referenti
            $stmt = db_query("SELECT COUNT(*) as count FROM referenti_aziende WHERE azienda_id = :id AND attivo = 1", 
                               ['id' => $azienda_id]);
            $count = $stmt->fetch()['count'];
            if ($count >= $max_referenti) {
                $errors[] = "Massimo $max_referenti referenti attivi per azienda";
            }
            
            // Verifica email duplicata
            $stmt = db_query("SELECT id FROM utenti WHERE email = :email", ['email' => $data['email']]);
            if ($stmt->fetch()) {
                $errors[] = "Email già registrata nel sistema";
            }
        }
        
        if (empty($errors)) {
            try {
                $db->getConnection()->beginTransaction();
                
                if ($action === 'nuovo') {
                    // Crea prima l'utente
                    $userData = [
                        'username' => $data['email'], // Username = email
                        'email' => $data['email'],
                        'password' => password_hash($password, PASSWORD_DEFAULT),
                        'nome' => $data['nome'],
                        'cognome' => $data['cognome'],
                        'telefono' => $data['telefono'],
                        'ruolo' => 'cliente', // Ruolo base per referenti
                        'attivo' => $data['attivo'],
                        'primo_accesso' => 1, // Forza cambio password al primo accesso
                        'password_scadenza' => date('Y-m-d', strtotime('+90 days'))
                    ];
                    
                    db_insert('utenti', $userData);
                    $utente_id = db_connection()->lastInsertId();
                    
                    // Associa l'utente all'azienda
                    db_insert('utenti_aziende', [
                        'utente_id' => $utente_id,
                        'azienda_id' => $azienda_id,
                        'ruolo_azienda' => 'utente',
                        'attivo' => 1
                    ]);
                    
                    // Aggiungi utente_id ai dati del referente
                    $data['utente_id'] = $utente_id;
                    
                    // Crea il referente
                    db_insert('referenti_aziende', $data);
                    $referente_id = db_connection()->lastInsertId();
                    
                    $logger->log('referente', 'creazione', $referente_id,
                        "Creato referente {$data['nome']} {$data['cognome']} per azienda {$azienda['nome']}");
                    
                    // Notifica email di benvenuto al referente
                    $oggetto = "Benvenuto in " . APP_NAME;
                    $contenuto = "
                        <h2>Benvenuto {$data['nome']} {$data['cognome']}</h2>
                        <p>Sei stato aggiunto come referente dell'azienda <strong>{$azienda['nome']}</strong>.</p>
                        <p>Il tuo ruolo: <strong>{$data['ruolo_aziendale']}</strong></p>
                        <h3>Credenziali di accesso:</h3>
                        <table style='border-collapse: collapse;'>
                            <tr>
                                <td style='padding: 8px; border: 1px solid #ddd;'><strong>Email:</strong></td>
                                <td style='padding: 8px; border: 1px solid #ddd;'>{$data['email']}</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px; border: 1px solid #ddd;'><strong>Password:</strong></td>
                                <td style='padding: 8px; border: 1px solid #ddd;'>{$password}</td>
                            </tr>
                        </table>
                        <p style='color: #1b3f76; font-weight: bold; margin-top: 20px;'>
                            ⚠️ Al primo accesso ti verrà richiesto di cambiare la password
                        </p>
                        <p>Puoi accedere alla piattaforma all'indirizzo: <a href='" . APP_URL . "'>" . APP_URL . "</a></p>
                    ";
                    
                    $notificationManager->aggiungiNotifica(
                        $data['email'],
                        $data['nome'] . ' ' . $data['cognome'],
                        $oggetto,
                        $contenuto,
                        'referente_creato',
                        $azienda_id,
                        1 // Alta priorità
                    );
                    
                    $_SESSION['success'] = "Referente creato con successo! Email con credenziali inviata.";
                } else {
                    // Aggiornamento referente esistente
                    $stmt = db_query("SELECT * FROM referenti_aziende WHERE id = :id", ['id' => $referente_id]);
                    $dati_precedenti = $stmt->fetch();
                    
                    db_update('referenti_aziende', $data, 'id = :id', ['id' => $referente_id]);
                    
                    // Se cambia lo stato attivo/inattivo, aggiorna anche l'utente
                    if (isset($dati_precedenti['utente_id']) && $dati_precedenti['utente_id']) {
                        db_update('utenti', ['attivo' => $data['attivo']], 'id = :id', ['id' => $dati_precedenti['utente_id']]);
                    }
                    
                    $logger->log('referente', 'modifica', $referente_id,
                        "Modificato referente {$data['nome']} {$data['cognome']}");
                    
                    $_SESSION['success'] = "Referente aggiornato con successo!";
                }
                
                $db->getConnection()->commit();
                redirect(APP_PATH . "/referenti.php?azienda_id=$azienda_id");
                
            } catch (Exception $e) {
                $db->getConnection()->rollback();
                $error = "Errore: " . $e->getMessage();
            }
        } else {
            $error = implode('<br>', $errors);
        }
        
    } elseif ($action === 'delete' && $referente_id) {
        if (isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
            try {
                $stmt = db_query("SELECT * FROM referenti_aziende WHERE id = :id", ['id' => $referente_id]);
                $referente = $stmt->fetch();
                
                db_update('referenti_aziende', ['attivo' => 0], 'id = :id', ['id' => $referente_id]);
                
                // Disattiva anche l'utente associato
                if ($referente['utente_id']) {
                    db_update('utenti', ['attivo' => 0], 'id = :id', ['id' => $referente['utente_id']]);
                }
                
                $logger->log('referente', 'disattivazione', $referente_id, 
                    "Disattivato referente {$referente['nome']} {$referente['cognome']}");
                
                $_SESSION['success'] = "Referente disattivato con successo!";
                redirect(APP_PATH . "/referenti.php?azienda_id=$azienda_id");
            } catch (Exception $e) {
                $_SESSION['error'] = "Errore durante la disattivazione: " . $e->getMessage();
            }
        }
    } elseif ($action === 'elimina' && $referente_id && $canDeletePermanently) {
        // Solo super admin o proprietario può eliminare definitivamente
        if (isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
            try {
                $stmt = db_query("SELECT * FROM referenti_aziende WHERE id = :id", ['id' => $referente_id]);
                $referente = $stmt->fetch();
                
                db_delete('referenti_aziende', 'id = :id', ['id' => $referente_id]);
                
                $logger->log('referente', 'eliminazione', $referente_id, 
                    "Eliminato definitivamente referente {$referente['nome']} {$referente['cognome']}");
                
                $_SESSION['success'] = "Referente eliminato definitivamente!";
                redirect(APP_PATH . "/referenti.php?azienda_id=$azienda_id");
            } catch (Exception $e) {
                $_SESSION['error'] = "Errore durante l'eliminazione: " . $e->getMessage();
            }
        }
    }
}

// Carica dati per le varie azioni
if ($action === 'edit' && $referente_id) {
    $stmt = db_query("SELECT * FROM referenti_aziende WHERE id = :id AND azienda_id = :azienda_id", 
                       ['id' => $referente_id, 'azienda_id' => $azienda_id]);
    $referente = $stmt->fetch();
    
    if (!$referente) {
        $_SESSION['error'] = "Referente non trovato";
        redirect(APP_PATH . "/referenti.php?azienda_id=$azienda_id");
    }
} elseif ($action === 'nuovo') {
    // Inizializza array vuoto per nuovo referente per evitare errori undefined
    $referente = [];
} else {
    // Lista referenti
    $stmt = db_query("SELECT * FROM referenti_aziende WHERE azienda_id = :azienda_id ORDER BY attivo DESC, cognome, nome", 
                       ['azienda_id' => $azienda_id]);
    $referenti = $stmt->fetchAll();
}

$pageTitle = 'Gestione Referenti - ' . htmlspecialchars($azienda['nome']);
require_once 'components/header.php';
?>

<style>
    /* Stili Nexio per referenti */
    :root {
        --primary-color: #1b3f76;
        --primary-dark: #0f2847;
        --primary-light: #2a5a9f;
        --border-color: #e8e8e8;
        --text-primary: #2c2c2c;
        --text-secondary: #6b6b6b;
        --bg-primary: #faf8f5;
        --bg-secondary: #ffffff;
    }
    
    .permission-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 0.75rem;
        margin-top: 1rem;
    }
    
    .permission-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .permission-item input[type="checkbox"] {
        margin-right: 0.5rem;
    }
    
    .permission-item label {
        cursor: pointer;
        user-select: none;
        flex: 1;
        font-weight: 400;
        margin: 0;
        color: var(--text-primary);
    }
    
    .referente-card {
        background: var(--bg-secondary);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        border: 1px solid var(--border-color);
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        transition: all 0.3s ease;
    }
    
    .referente-card:hover {
        box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        transform: translateY(-2px);
        border-color: var(--primary-color);
    }
    
    .referente-card.inactive {
        opacity: 0.7;
        background: #f9f9f9;
    }
    
    .referente-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 1rem;
    }
    
    .referente-info {
        flex: 1;
    }
    
    .referente-name {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.375rem;
    }
    
    .referente-role {
        color: var(--text-secondary);
        font-size: 0.875rem;
        margin-bottom: 0.75rem;
    }
    
    .referente-contact {
        font-size: 0.875rem;
        color: var(--text-primary);
        line-height: 1.6;
    }
    
    .referente-contact div {
        margin-bottom: 0.25rem;
    }
    
    .permissions-summary {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid var(--border-color);
    }
    
    .permission-badge {
        background: rgba(43, 87, 154, 0.1);
        color: var(--primary-dark);
        padding: 0.375rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 500;
        border: 1px solid var(--primary-light);
    }
    
    .permission-badge.denied {
        background: #fee;
        color: #ef4444;
        border-color: #fecaca;
    }
    
    .info-message {
        background: rgba(43, 87, 154, 0.1);
        border: 1px solid var(--primary-light);
        border-radius: 10px;
        padding: 1rem;
        color: var(--primary-dark);
        font-weight: 500;
    }
    
    .confirm-dialog {
        background: #fef2f2;
        border: 2px solid #fecaca;
        border-radius: 12px;
        padding: 2rem;
        margin: 2rem 0;
    }
    
    .confirm-dialog h3 {
        color: #dc2626;
        margin-bottom: 1rem;
    }
    
    .form-container {
        background: var(--bg-secondary);
        border-radius: 12px;
        padding: 2rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        border: 1px solid var(--border-color);
        margin-bottom: 1.5rem;
    }
    
    .form-container h2 {
        color: var(--text-primary);
        font-size: 1.25rem;
        margin-bottom: 1.5rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid var(--primary-light);
    }
    
    /* Override per bottoni */
    .btn {
        border-radius: 10px;
        padding: 0.75rem 1.5rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
        box-shadow: 0 4px 15px rgba(43, 87, 154, 0.3);
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(43, 87, 154, 0.4);
    }
    
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: var(--text-secondary);
    }
    
    .empty-state i {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.3;
    }
    
    .password-field {
        position: relative;
    }
    
    .password-toggle {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: var(--text-secondary);
        cursor: pointer;
        padding: 0.5rem;
    }
    
    .password-toggle:hover {
        color: var(--primary-color);
    }
</style>

<?php if ($action === 'nuovo' || $action === 'edit'): ?>
    <!-- Form Referente -->
    <div class="content-header">
        <h1><i class="fas fa-user-plus"></i> <?php echo $action === 'nuovo' ? 'Nuovo Referente' : 'Modifica Referente'; ?></h1>
        <div class="header-actions">
            <a href="<?php echo APP_PATH; ?>/referenti.php?azienda_id=<?php echo $azienda_id; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Torna ai referenti
            </a>
        </div>
    </div>
    
    <div class="info-message">
        <i class="fas fa-info-circle"></i> Azienda: <strong><?php echo htmlspecialchars($azienda['nome']); ?></strong>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <form method="post" action="" id="referenteForm">
        <div class="form-container">
            <h2><i class="fas fa-user"></i> Informazioni Referente</h2>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="nome">Nome <span class="required">*</span></label>
                    <input type="text" id="nome" name="nome" class="form-control" required 
                           value="<?php echo htmlspecialchars($_POST['nome'] ?? $referente['nome'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="cognome">Cognome <span class="required">*</span></label>
                    <input type="text" id="cognome" name="cognome" class="form-control" required 
                           value="<?php echo htmlspecialchars($_POST['cognome'] ?? $referente['cognome'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="email">Email <span class="required">*</span></label>
                    <input type="email" id="email" name="email" class="form-control" required 
                           <?php echo $action === 'edit' ? 'readonly' : ''; ?>
                           value="<?php echo htmlspecialchars($_POST['email'] ?? $referente['email'] ?? ''); ?>">
                    <?php if ($action === 'edit'): ?>
                        <small class="text-muted">L'email non può essere modificata</small>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="telefono">Telefono</label>
                    <input type="tel" id="telefono" name="telefono" class="form-control"
                           value="<?php echo htmlspecialchars($_POST['telefono'] ?? $referente['telefono'] ?? ''); ?>">
                </div>
            </div>
            
            <?php if ($action === 'nuovo'): ?>
            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <div class="password-field">
                        <input type="password" id="password" name="password" class="form-control" required
                               minlength="8" placeholder="Minimo 8 caratteri">
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                    <small class="text-muted">La password dovrà essere cambiata al primo accesso</small>
                </div>
                
                <div class="form-group">
                    <label for="ruolo_aziendale">Ruolo Aziendale</label>
                    <input type="text" id="ruolo_aziendale" name="ruolo_aziendale" class="form-control"
                           placeholder="es: Responsabile Qualità, Amministratore, etc."
                           value="<?php echo htmlspecialchars($_POST['ruolo_aziendale'] ?? $referente['ruolo_aziendale'] ?? ''); ?>">
                </div>
            </div>
            <?php else: ?>
            <div class="form-group">
                <label for="ruolo_aziendale">Ruolo Aziendale</label>
                <input type="text" id="ruolo_aziendale" name="ruolo_aziendale" class="form-control"
                       placeholder="es: Responsabile Qualità, Amministratore, etc."
                       value="<?php echo htmlspecialchars($_POST['ruolo_aziendale'] ?? $referente['ruolo_aziendale'] ?? ''); ?>">
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="attivo" value="1" 
                           <?php echo ($_POST['attivo'] ?? $referente['attivo'] ?? 1) ? 'checked' : ''; ?>>
                    Referente attivo
                </label>
            </div>
        </div>
        
        <div class="form-container">
            <h2><i class="fas fa-shield-alt"></i> Permessi</h2>
            
            <div class="permission-grid">
                <div class="permission-item">
                    <input type="checkbox" id="puo_vedere_documenti" name="puo_vedere_documenti" value="1"
                           <?php echo ($_POST['puo_vedere_documenti'] ?? $referente['puo_vedere_documenti'] ?? 1) ? 'checked' : ''; ?>>
                    <label for="puo_vedere_documenti">
                        <i class="fas fa-eye"></i> Può vedere documenti
                    </label>
                </div>
                
                <div class="permission-item">
                    <input type="checkbox" id="puo_creare_documenti" name="puo_creare_documenti" value="1"
                           <?php echo ($_POST['puo_creare_documenti'] ?? $referente['puo_creare_documenti'] ?? 0) ? 'checked' : ''; ?>>
                    <label for="puo_creare_documenti">
                        <i class="fas fa-plus"></i> Può creare documenti
                    </label>
                </div>
                
                <div class="permission-item">
                    <input type="checkbox" id="puo_modificare_documenti" name="puo_modificare_documenti" value="1"
                           <?php echo ($_POST['puo_modificare_documenti'] ?? $referente['puo_modificare_documenti'] ?? 0) ? 'checked' : ''; ?>>
                    <label for="puo_modificare_documenti">
                        <i class="fas fa-edit"></i> Può modificare documenti
                    </label>
                </div>
                
                <div class="permission-item">
                    <input type="checkbox" id="puo_eliminare_documenti" name="puo_eliminare_documenti" value="1"
                           <?php echo ($_POST['puo_eliminare_documenti'] ?? $referente['puo_eliminare_documenti'] ?? 0) ? 'checked' : ''; ?>>
                    <label for="puo_eliminare_documenti">
                        <i class="fas fa-trash"></i> Può eliminare documenti
                    </label>
                </div>
                
                <div class="permission-item">
                    <input type="checkbox" id="puo_scaricare_documenti" name="puo_scaricare_documenti" value="1"
                           <?php echo ($_POST['puo_scaricare_documenti'] ?? $referente['puo_scaricare_documenti'] ?? 1) ? 'checked' : ''; ?>>
                    <label for="puo_scaricare_documenti">
                        <i class="fas fa-download"></i> Può scaricare documenti
                    </label>
                </div>
                
                <div class="permission-item">
                    <input type="checkbox" id="puo_compilare_moduli" name="puo_compilare_moduli" value="1"
                           <?php echo ($_POST['puo_compilare_moduli'] ?? $referente['puo_compilare_moduli'] ?? 0) ? 'checked' : ''; ?>>
                    <label for="puo_compilare_moduli">
                        <i class="fas fa-file-alt"></i> Può compilare moduli
                    </label>
                </div>
                
                <div class="permission-item">
                    <input type="checkbox" id="puo_aprire_ticket" name="puo_aprire_ticket" value="1"
                           <?php echo ($_POST['puo_aprire_ticket'] ?? $referente['puo_aprire_ticket'] ?? 1) ? 'checked' : ''; ?>>
                    <label for="puo_aprire_ticket">
                        <i class="fas fa-ticket-alt"></i> Può aprire ticket
                    </label>
                </div>
                
                <div class="permission-item">
                    <input type="checkbox" id="puo_gestire_eventi" name="puo_gestire_eventi" value="1"
                           <?php echo ($_POST['puo_gestire_eventi'] ?? $referente['puo_gestire_eventi'] ?? 0) ? 'checked' : ''; ?>>
                    <label for="puo_gestire_eventi">
                        <i class="fas fa-calendar"></i> Può gestire eventi
                    </label>
                </div>
                
                <div class="permission-item">
                    <input type="checkbox" id="puo_vedere_referenti" name="puo_vedere_referenti" value="1"
                           <?php echo ($_POST['puo_vedere_referenti'] ?? $referente['puo_vedere_referenti'] ?? 0) ? 'checked' : ''; ?>>
                    <label for="puo_vedere_referenti">
                        <i class="fas fa-users"></i> Può vedere referenti
                    </label>
                </div>
                
                <div class="permission-item">
                    <input type="checkbox" id="puo_gestire_referenti" name="puo_gestire_referenti" value="1"
                           <?php echo ($_POST['puo_gestire_referenti'] ?? $referente['puo_gestire_referenti'] ?? 0) ? 'checked' : ''; ?>>
                    <label for="puo_gestire_referenti">
                        <i class="fas fa-user-cog"></i> Può gestire referenti
                    </label>
                </div>
                
                <div class="permission-item">
                    <input type="checkbox" id="puo_vedere_log" name="puo_vedere_log" value="1"
                           <?php echo ($_POST['puo_vedere_log'] ?? $referente['puo_vedere_log'] ?? 0) ? 'checked' : ''; ?>>
                    <label for="puo_vedere_log">
                        <i class="fas fa-file-alt"></i> Può vedere log attività
                    </label>
                </div>
                
                <div class="permission-item">
                    <input type="checkbox" id="riceve_notifiche_email" name="riceve_notifiche_email" value="1"
                           <?php echo ($_POST['riceve_notifiche_email'] ?? $referente['riceve_notifiche_email'] ?? 1) ? 'checked' : ''; ?>>
                    <label for="riceve_notifiche_email">
                        <i class="fas fa-envelope"></i> Riceve notifiche email
                    </label>
                </div>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> <?php echo $action === 'nuovo' ? 'Crea Referente' : 'Salva Modifiche'; ?>
            </button>
            <a href="<?php echo APP_PATH; ?>/referenti.php?azienda_id=<?php echo $azienda_id; ?>" class="btn btn-secondary">
                Annulla
            </a>
        </div>
    </form>
    
<?php elseif ($action === 'delete' && $referente_id): ?>
    <!-- Conferma disattivazione -->
    <?php
    $stmt = db_query("SELECT * FROM referenti_aziende WHERE id = :id", ['id' => $referente_id]);
    $referente = $stmt->fetch();
    ?>
    
    <div class="content-header">
        <h1><i class="fas fa-user-lock"></i> Disattiva Referente</h1>
    </div>
    
    <div class="confirm-dialog">
        <h3><i class="fas fa-exclamation-triangle"></i> Conferma disattivazione</h3>
        <p>Stai per disattivare il referente:</p>
        <p><strong><?php echo htmlspecialchars($referente['nome'] . ' ' . $referente['cognome']); ?></strong></p>
        <p>Email: <?php echo htmlspecialchars($referente['email']); ?></p>
        <p style="color: #666; margin-top: 15px;">Il referente potrà essere riattivato in seguito.</p>
        
        <form method="post" action="" style="margin-top: 20px;">
            <input type="hidden" name="confirm" value="yes">
            <button type="submit" class="btn btn-danger">
                <i class="fas fa-lock"></i> Conferma Disattivazione
            </button>
            <a href="<?php echo APP_PATH; ?>/referenti.php?azienda_id=<?php echo $azienda_id; ?>" class="btn btn-secondary">
                Annulla
            </a>
        </form>
    </div>
    
<?php elseif ($action === 'elimina' && $referente_id && $canDeletePermanently): ?>
    <!-- Conferma eliminazione definitiva -->
    <?php
    $stmt = db_query("SELECT * FROM referenti_aziende WHERE id = :id", ['id' => $referente_id]);
    $referente = $stmt->fetch();
    ?>
    
    <div class="content-header">
        <h1><i class="fas fa-user-times"></i> Elimina Definitivamente Referente</h1>
    </div>
    
    <div class="confirm-dialog" style="background: #fee; border-color: #fcc;">
        <h3 style="color: #c00;">🗑️ ATTENZIONE: Eliminazione Definitiva</h3>
        <p style="color: #c00; font-weight: bold;">Questa azione è IRREVERSIBILE!</p>
        <p>Stai per eliminare definitivamente il referente:</p>
        <p><strong><?php echo htmlspecialchars($referente['nome'] . ' ' . $referente['cognome']); ?></strong></p>
        <p>Email: <?php echo htmlspecialchars($referente['email']); ?></p>
        
        <form method="post" action="" style="margin-top: 20px;">
            <input type="hidden" name="confirm" value="yes">
            <button type="submit" class="btn btn-danger" style="background: #c00;">
                ⚠️ Elimina Definitivamente
            </button>
            <a href="<?php echo APP_PATH; ?>/referenti.php?azienda_id=<?php echo $azienda_id; ?>" class="btn btn-secondary">
                Annulla
            </a>
        </form>
    </div>
    
<?php else: ?>
    <!-- Lista Referenti -->
    <div class="content-header">
        <h1><i class="fas fa-users"></i> Referenti - <?php echo htmlspecialchars($azienda['nome']); ?></h1>
        <div class="header-actions">
            <?php 
            $attivi = count(array_filter($referenti, function($r) { return $r['attivo']; }));
            if ($attivi < $max_referenti): 
            ?>
            <a href="<?php echo APP_PATH; ?>/referenti.php?azienda_id=<?php echo $azienda_id; ?>&action=nuovo" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nuovo Referente
            </a>
            <?php endif; ?>
            <a href="<?php echo APP_PATH; ?>/aziende.php?action=view&id=<?php echo $azienda_id; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Torna all'azienda
            </a>
        </div>
    </div>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    
    <div class="info-message">
        <i class="fas fa-info-circle"></i> 
        Questa azienda può avere massimo <strong><?php echo $max_referenti; ?> referenti attivi</strong>.
        Referenti attivi: <strong><?php echo $attivi; ?>/<?php echo $max_referenti; ?></strong>
    </div>
    
    <?php if (empty($referenti)): ?>
        <div class="empty-state">
            <i class="fas fa-users"></i>
            <h2>Nessun referente presente</h2>
            <p>Aggiungi i referenti per questa azienda.</p>
            <a href="<?php echo APP_PATH; ?>/referenti.php?azienda_id=<?php echo $azienda_id; ?>&action=nuovo" class="btn btn-primary" style="margin-top: 20px;">
                <i class="fas fa-plus"></i> Aggiungi Referente
            </a>
        </div>
    <?php else: ?>
        <?php foreach ($referenti as $ref): ?>
        <div class="referente-card <?php echo !$ref['attivo'] ? 'inactive' : ''; ?>">
            <div class="referente-header">
                <div class="referente-info">
                    <div class="referente-name">
                        <?php echo htmlspecialchars($ref['nome'] . ' ' . $ref['cognome']); ?>
                        <?php if (!$ref['attivo']): ?>
                            <span class="status-badge status-inattivo">Disattivo</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($ref['ruolo_aziendale']): ?>
                        <div class="referente-role"><?php echo htmlspecialchars($ref['ruolo_aziendale']); ?></div>
                    <?php endif; ?>
                    <div class="referente-contact">
                        <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($ref['email']); ?></div>
                        <?php if ($ref['telefono']): ?>
                            <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($ref['telefono']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="actions">
                    <a href="<?php echo APP_PATH; ?>/referenti.php?azienda_id=<?php echo $azienda_id; ?>&action=edit&id=<?php echo $ref['id']; ?>" 
                       class="btn btn-secondary btn-small">
                        <i class="fas fa-edit"></i> Modifica
                    </a>
                    <?php if ($ref['attivo']): ?>
                    <a href="<?php echo APP_PATH; ?>/referenti.php?azienda_id=<?php echo $azienda_id; ?>&action=delete&id=<?php echo $ref['id']; ?>" 
                       class="btn btn-danger btn-small">
                        <i class="fas fa-lock"></i> Disattiva
                    </a>
                    <?php else: ?>
                    <a href="<?php echo APP_PATH; ?>/referenti.php?azienda_id=<?php echo $azienda_id; ?>&action=attiva&id=<?php echo $ref['id']; ?>" 
                       class="btn btn-success btn-small">
                        <i class="fas fa-unlock"></i> Riattiva
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($canDeletePermanently): ?>
                    <a href="<?php echo APP_PATH; ?>/referenti.php?azienda_id=<?php echo $azienda_id; ?>&action=elimina&id=<?php echo $ref['id']; ?>" 
                       class="btn btn-danger btn-small" style="background: #c00;" 
                       title="Elimina definitivamente">
                        <i class="fas fa-trash-alt"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="permissions-summary">
                <?php if ($ref['puo_vedere_documenti'] ?? false): ?>
                    <span class="permission-badge"><i class="fas fa-eye"></i> Vedere documenti</span>
                <?php endif; ?>
                <?php if ($ref['puo_creare_documenti'] ?? false): ?>
                    <span class="permission-badge"><i class="fas fa-plus"></i> Creare documenti</span>
                <?php endif; ?>
                <?php if ($ref['puo_modificare_documenti'] ?? false): ?>
                    <span class="permission-badge"><i class="fas fa-edit"></i> Modificare documenti</span>
                <?php endif; ?>
                <?php if ($ref['puo_eliminare_documenti'] ?? false): ?>
                    <span class="permission-badge"><i class="fas fa-trash"></i> Eliminare documenti</span>
                <?php endif; ?>
                <?php if ($ref['puo_scaricare_documenti'] ?? false): ?>
                    <span class="permission-badge"><i class="fas fa-download"></i> Scaricare documenti</span>
                <?php endif; ?>
                <?php if ($ref['puo_compilare_moduli'] ?? false): ?>
                    <span class="permission-badge"><i class="fas fa-file-alt"></i> Compilare moduli</span>
                <?php endif; ?>
                <?php if ($ref['puo_aprire_ticket'] ?? false): ?>
                    <span class="permission-badge"><i class="fas fa-ticket-alt"></i> Aprire ticket</span>
                <?php endif; ?>
                <?php if ($ref['puo_gestire_eventi'] ?? false): ?>
                    <span class="permission-badge"><i class="fas fa-calendar"></i> Gestire eventi</span>
                <?php endif; ?>
                <?php if ($ref['puo_vedere_referenti'] ?? false): ?>
                    <span class="permission-badge"><i class="fas fa-users"></i> Vedere referenti</span>
                <?php endif; ?>
                <?php if ($ref['puo_gestire_referenti'] ?? false): ?>
                    <span class="permission-badge"><i class="fas fa-user-cog"></i> Gestire referenti</span>
                <?php endif; ?>
                <?php if ($ref['puo_vedere_log'] ?? false): ?>
                    <span class="permission-badge"><i class="fas fa-file-alt"></i> Vedere log attività</span>
                <?php endif; ?>
                <?php if (!($ref['riceve_notifiche_email'] ?? true)): ?>
                    <span class="permission-badge denied"><i class="fas fa-envelope-slash"></i> No notifiche email</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
<?php endif; ?>

<?php require_once 'components/footer.php'; ?>

<script>
// Controllo permessi correlati
document.getElementById('puo_modificare_documenti')?.addEventListener('change', function() {
    if (this.checked) {
        document.getElementById('puo_vedere_documenti').checked = true;
    }
});

document.getElementById('puo_eliminare_documenti')?.addEventListener('change', function() {
    if (this.checked) {
        document.getElementById('puo_vedere_documenti').checked = true;
        document.getElementById('puo_modificare_documenti').checked = true;
    }
});

// Toggle password visibility
function togglePassword() {
    const passwordField = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');
    
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        passwordField.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
}

// Conferma prima di inviare il form
document.getElementById('referenteForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const action = '<?php echo $action; ?>';
    const message = action === 'nuovo' 
        ? 'Confermi di voler creare questo referente?\nVerrà creato anche un account utente associato.'
        : 'Confermi di voler salvare le modifiche?';
    
    if (confirm(message)) {
        this.submit();
    }
});
</script> 