<?php
require_once 'backend/config/config.php';

$auth = Auth::getInstance();
$auth->requireAuth();

// Solo i super admin possono accedere
if (!$auth->isSuperAdmin()) {
    header('Location: dashboard.php');
    exit;
}

// Database instance handled by functions
$userId = $_GET['id'] ?? null;

if (!$userId) {
    $_SESSION['error'] = "ID utente non specificato";
    header('Location: gestione-utenti.php');
    exit;
}

// Carica dati utente con tutte le aziende associate
$stmt = db_query("
    SELECT u.* 
    FROM utenti u 
    WHERE u.id = ?
", [$userId]);
$utente = $stmt->fetch();

if (!$utente) {
    $_SESSION['error'] = "Utente non trovato";
    header('Location: gestione-utenti.php');
    exit;
}

// Carica aziende associate all'utente
$stmt = db_query("
    SELECT ua.*, a.nome as azienda_nome,
           up.puo_vedere_documenti, up.puo_creare_documenti, up.puo_modificare_documenti,
           up.puo_eliminare_documenti, up.puo_scaricare_documenti, up.puo_vedere_bozze,
           up.puo_compilare_moduli, up.puo_aprire_ticket, up.puo_gestire_eventi,
           up.puo_vedere_referenti, up.puo_gestire_referenti, up.puo_vedere_log_attivita,
           up.riceve_notifiche_email
    FROM utenti_aziende ua
    JOIN aziende a ON ua.azienda_id = a.id
    LEFT JOIN utenti_permessi up ON ua.utente_id = up.utente_id AND ua.azienda_id = up.azienda_id
    WHERE ua.utente_id = ?
", [$userId]);
$utente_aziende = $stmt->fetchAll();

// Gestione form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome']);
    $cognome = trim($_POST['cognome']);
    $email = trim($_POST['email']);
    $data_nascita = $_POST['data_nascita'];
    $ruolo = $_POST['ruolo'];
    $attivo = isset($_POST['attivo']) ? 1 : 0;
    
    // Validazione
    $errors = [];
    if (empty($nome)) $errors[] = "Il nome è obbligatorio";
    if (empty($cognome)) $errors[] = "Il cognome è obbligatorio";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email non valida";
    }
    
    // Verifica email univoca (escludendo l'utente corrente)
    $stmt = db_query("SELECT id FROM utenti WHERE email = ? AND id != ?", [$email, $userId]);
    if ($stmt->fetch()) {
        $errors[] = "Email già in uso da un altro utente";
    }
    
    if (empty($errors)) {
        try {
            db_connection()->beginTransaction();
            
            // Aggiorna utente
            $stmt = db_query("
                UPDATE utenti 
                SET nome = ?, cognome = ?, email = ?, data_nascita = ?, 
                    ruolo = ?, attivo = ?
                WHERE id = ?
            ", [
                $nome, $cognome, $email, $data_nascita, 
                $ruolo, $attivo, $userId
            ]);
            
            // Gestione aziende e permessi
            // Rimuovi tutte le associazioni esistenti
            db_query("DELETE FROM utenti_aziende WHERE utente_id = ?", [$userId]);
            db_query("DELETE FROM utenti_permessi WHERE utente_id = ?", [$userId]);
            
            // Aggiungi nuove associazioni
            if (isset($_POST['aziende']) && is_array($_POST['aziende'])) {
                foreach ($_POST['aziende'] as $azienda_id) {
                    $ruolo_azienda = $_POST['ruolo_azienda'][$azienda_id] ?? 'utente';
                    $permessi = isset($_POST['permessi'][$azienda_id]) ? $_POST['permessi'][$azienda_id] : [];
                    
                    // Salva in utenti_aziende (per compatibilità)
                    db_query("
                        INSERT INTO utenti_aziende (utente_id, azienda_id, ruolo_azienda, permessi, attivo)
                        VALUES (?, ?, ?, ?, 1)
                    ", [$userId, $azienda_id, $ruolo_azienda, json_encode($permessi)]);
                    
                    // Salva in utenti_permessi
                    db_query("
                        INSERT INTO utenti_permessi (
                            utente_id, azienda_id, 
                            puo_vedere_documenti, puo_creare_documenti, puo_modificare_documenti,
                            puo_eliminare_documenti, puo_scaricare_documenti, puo_vedere_bozze,
                            puo_compilare_moduli, puo_aprire_ticket, puo_gestire_eventi,
                            puo_vedere_referenti, puo_gestire_referenti, puo_vedere_log_attivita,
                            riceve_notifiche_email, puo_creare_eventi
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ", [
                        $userId, $azienda_id,
                        in_array('vedere_documenti', $permessi) ? 1 : 0,
                        in_array('creare_documenti', $permessi) ? 1 : 0,
                        in_array('modificare_documenti', $permessi) ? 1 : 0,
                        in_array('eliminare_documenti', $permessi) ? 1 : 0,
                        in_array('scaricare_documenti', $permessi) ? 1 : 0,
                        in_array('vedere_bozze', $permessi) ? 1 : 0,
                        in_array('compilare_moduli', $permessi) ? 1 : 0,
                        in_array('aprire_ticket', $permessi) ? 1 : 0,
                        in_array('gestire_eventi', $permessi) ? 1 : 0,
                        in_array('vedere_referenti', $permessi) ? 1 : 0,
                        in_array('gestire_referenti', $permessi) ? 1 : 0,
                        in_array('vedere_log', $permessi) ? 1 : 0,
                        in_array('riceve_notifiche', $permessi) ? 1 : 0,
                        in_array('puo_creare_eventi', $permessi) ? 1 : 0
                    ]);
                }
            }
            
            db_connection()->commit();
            
            // Log attività
            if (class_exists('ActivityLogger')) {
                ActivityLogger::getInstance()->log('utente', 'modifica', $userId, "Utente modificato: $email");
            }
            
            $_SESSION['success'] = "Utente aggiornato con successo!";
            header('Location: gestione-utenti.php');
            exit;
            
        } catch (Exception $e) {
            db_connection()->rollback();
            $error = "Errore durante l'aggiornamento: " . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// Carica lista aziende disponibili
$stmt = db_query("SELECT id, nome FROM aziende WHERE stato = 'attiva' ORDER BY nome");
$aziende = $stmt->fetchAll();

$pageTitle = 'Modifica Utente';
include 'components/header.php';
?>

<style>
/* Variabili CSS Nexio */
:root {
    --primary-color: #1b3f76;
    --primary-dark: #0f2847;
    --primary-light: #2a5a9f;
    --border-color: #e8e8e8;
    --text-primary: #2c2c2c;
    --text-secondary: #6b6b6b;
    --bg-primary: #faf8f5;
    --bg-secondary: #ffffff;
    --font-sans: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
}

.content-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: var(--bg-secondary);
    border-radius: 12px;
    border: 1px solid var(--border-color);
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.content-header h1 {
    margin: 0;
    color: var(--text-primary);
    font-size: 1.875rem;
    font-weight: 700;
}

.form-container {
    max-width: 1000px;
    margin: 0 auto;
    background: var(--bg-secondary);
    border-radius: 12px;
    padding: 2rem;
    border: 1px solid var(--border-color);
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    color: var(--text-primary);
    font-weight: 600;
    font-size: 14px;
}

.form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid var(--border-color);
    border-radius: 10px;
    font-size: 15px;
    transition: all 0.3s ease;
    background: var(--bg-secondary);
    font-family: var(--font-sans);
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(43, 87, 154, 0.1);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}

.aziende-section {
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 2px solid var(--border-color);
}

.azienda-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 1.5rem;
    margin-bottom: 1rem;
}

.azienda-header {
    display: flex;
    align-items: center;
    margin-bottom: 1rem;
}

.azienda-header input[type="checkbox"] {
    margin-right: 1rem;
}

.permessi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 0.75rem;
    margin-top: 1rem;
}

.permesso-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
}

.btn-primary {
    background: var(--primary-color);
    color: white;
}

.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(43, 87, 154, 0.3);
}

.btn-secondary {
    background: #f3f4f6;
    color: var(--text-primary);
}

.btn-secondary:hover {
    background: #e5e7eb;
}

.btn-warning {
    background: #fbbf24;
    color: #78350f;
}

.btn-warning:hover {
    background: #f59e0b;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid var(--border-color);
}

.info-box {
    background: var(--bg-primary);
    border: 1px solid var(--primary-light);
    padding: 1.5rem;
    border-radius: 10px;
    margin: 1.5rem 0;
}

.info-box h4 {
    color: var(--primary-dark);
    margin-bottom: 0.75rem;
    font-size: 1.125rem;
}

.info-box p {
    margin: 0.5rem 0;
    color: var(--text-secondary);
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    font-weight: 400;
}

.required {
    color: #ef4444;
}
</style>

<div class="content-header">
    <h1>Modifica Utente</h1>
    <div class="header-actions">
        <a href="<?php echo APP_PATH; ?>/gestione-utenti.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Torna alla lista
        </a>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
    </div>
<?php endif; ?>

<div class="form-container">
    <form method="POST" action="">
        <div class="form-row">
            <div class="form-group">
                <label for="nome">Nome <span class="required">*</span></label>
                <input type="text" name="nome" id="nome" class="form-control" required 
                       value="<?php echo htmlspecialchars($utente['nome']); ?>">
            </div>
            
            <div class="form-group">
                <label for="cognome">Cognome <span class="required">*</span></label>
                <input type="text" name="cognome" id="cognome" class="form-control" required 
                       value="<?php echo htmlspecialchars($utente['cognome']); ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="email">Email <span class="required">*</span></label>
                <input type="email" name="email" id="email" class="form-control" required 
                       value="<?php echo htmlspecialchars($utente['email']); ?>">
            </div>
            
            <div class="form-group">
                <label for="data_nascita">Data di Nascita</label>
                <input type="date" name="data_nascita" id="data_nascita" class="form-control" 
                       value="<?php echo $utente['data_nascita']; ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="ruolo">Ruolo Sistema <span class="required">*</span></label>
                <select name="ruolo" id="ruolo" class="form-control" required>
                    <option value="utente" <?php echo $utente['ruolo'] == 'utente' ? 'selected' : ''; ?>>Utente</option>
                    <option value="super_admin" <?php echo $utente['ruolo'] == 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="attivo" value="1" <?php echo $utente['attivo'] ? 'checked' : ''; ?>>
                    Utente attivo
                </label>
            </div>
        </div>
        
        <!-- Sezione Aziende e Permessi -->
        <div class="aziende-section">
            <h3>Aziende e Permessi</h3>
            <p class="text-muted">Seleziona le aziende a cui l'utente ha accesso e configura i relativi permessi.</p>
            
            <?php
            // Crea array delle aziende già associate
            $aziende_associate = [];
            foreach ($utente_aziende as $ua) {
                $aziende_associate[$ua['azienda_id']] = $ua;
            }
            
            foreach ($aziende as $azienda): 
                $is_associated = isset($aziende_associate[$azienda['id']]);
                $ua_data = $is_associated ? $aziende_associate[$azienda['id']] : null;
                
                // Costruisci array permessi dai campi del database
                $permessi = [];
                if ($ua_data) {
                    if ($ua_data['puo_vedere_documenti']) $permessi[] = 'vedere_documenti';
                    if ($ua_data['puo_creare_documenti']) $permessi[] = 'creare_documenti';
                    if ($ua_data['puo_modificare_documenti']) $permessi[] = 'modificare_documenti';
                    if ($ua_data['puo_eliminare_documenti']) $permessi[] = 'eliminare_documenti';
                    if ($ua_data['puo_scaricare_documenti']) $permessi[] = 'scaricare_documenti';
                    if ($ua_data['puo_vedere_bozze']) $permessi[] = 'vedere_bozze';
                    if ($ua_data['puo_compilare_moduli']) $permessi[] = 'compilare_moduli';
                    if ($ua_data['puo_aprire_ticket']) $permessi[] = 'aprire_ticket';
                    if ($ua_data['puo_gestire_eventi']) $permessi[] = 'gestire_eventi';
                    if ($ua_data['puo_vedere_referenti']) $permessi[] = 'vedere_referenti';
                    if ($ua_data['puo_gestire_referenti']) $permessi[] = 'gestire_referenti';
                    if ($ua_data['puo_vedere_log_attivita']) $permessi[] = 'vedere_log';
                    if ($ua_data['riceve_notifiche_email']) $permessi[] = 'riceve_notifiche';
                    if (isset($ua_data['puo_creare_eventi']) && $ua_data['puo_creare_eventi']) $permessi[] = 'puo_creare_eventi';
                }
            ?>
            <div class="azienda-card">
                <div class="azienda-header">
                    <input type="checkbox" name="aziende[]" value="<?php echo $azienda['id']; ?>" 
                           id="azienda_<?php echo $azienda['id']; ?>"
                           <?php echo $is_associated ? 'checked' : ''; ?>
                           onchange="toggleAziendaPermessi(<?php echo $azienda['id']; ?>)">
                    <label for="azienda_<?php echo $azienda['id']; ?>">
                        <strong><?php echo htmlspecialchars($azienda['nome']); ?></strong>
                    </label>
                </div>
                
                <div id="permessi_<?php echo $azienda['id']; ?>" style="<?php echo $is_associated ? '' : 'display:none;'; ?>">
                    <div class="form-group">
                        <label>Ruolo nell'azienda</label>
                        <select name="ruolo_azienda[<?php echo $azienda['id']; ?>]" class="form-control">
                            <option value="utente" <?php echo ($ua_data['ruolo_azienda'] ?? '') == 'utente' ? 'selected' : ''; ?>>Utente</option>
                            <option value="admin" <?php echo ($ua_data['ruolo_azienda'] ?? '') == 'admin' ? 'selected' : ''; ?>>Amministratore</option>
                            <option value="proprietario" <?php echo ($ua_data['ruolo_azienda'] ?? '') == 'proprietario' ? 'selected' : ''; ?>>Proprietario</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Permessi specifici</label>
                        <div class="permessi-grid">
                            <?php
                            $permessi_disponibili = [
                                'vedere_documenti' => ['icon' => 'fa-eye', 'label' => 'Può vedere documenti'],
                                'creare_documenti' => ['icon' => 'fa-plus', 'label' => 'Può creare documenti'],
                                'modificare_documenti' => ['icon' => 'fa-edit', 'label' => 'Può modificare documenti'],
                                'eliminare_documenti' => ['icon' => 'fa-trash', 'label' => 'Può eliminare documenti'],
                                'scaricare_documenti' => ['icon' => 'fa-download', 'label' => 'Può scaricare documenti'],
                                'vedere_bozze' => ['icon' => 'fa-eye-slash', 'label' => 'Può vedere documenti in bozza'],
                                'compilare_moduli' => ['icon' => 'fa-file-alt', 'label' => 'Può compilare moduli'],
                                'aprire_ticket' => ['icon' => 'fa-ticket-alt', 'label' => 'Può aprire ticket'],
                                'gestire_eventi' => ['icon' => 'fa-calendar', 'label' => 'Può gestire eventi'],
                                'vedere_referenti' => ['icon' => 'fa-users', 'label' => 'Può vedere referenti'],
                                'gestire_referenti' => ['icon' => 'fa-user-cog', 'label' => 'Può gestire referenti'],
                                'vedere_log' => ['icon' => 'fa-file-alt', 'label' => 'Può vedere log attività'],
                                'riceve_notifiche' => ['icon' => 'fa-envelope', 'label' => 'Riceve notifiche email'],
                                'puo_creare_eventi' => ['icon' => 'fa-calendar', 'label' => 'Può creare eventi e invitare partecipanti']
                            ];
                            
                            foreach ($permessi_disponibili as $key => $perm):
                            ?>
                            <div class="permesso-item">
                                <input type="checkbox" 
                                       name="permessi[<?php echo $azienda['id']; ?>][]" 
                                       value="<?php echo $key; ?>"
                                       id="perm_<?php echo $azienda['id']; ?>_<?php echo $key; ?>"
                                       <?php echo in_array($key, $permessi) ? 'checked' : ''; ?>>
                                <label for="perm_<?php echo $azienda['id']; ?>_<?php echo $key; ?>">
                                    <i class="fas <?php echo $perm['icon']; ?>"></i> <?php echo $perm['label']; ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="info-box">
            <h4>Informazioni Utente</h4>
            <p><strong>Username:</strong> <?php echo htmlspecialchars($utente['username']); ?></p>
            <p><strong>Data creazione:</strong> <?php echo format_datetime($utente['data_creazione']); ?></p>
            <?php if ($utente['ultimo_accesso']): ?>
                <p><strong>Ultimo accesso:</strong> <?php echo format_datetime($utente['ultimo_accesso']); ?></p>
            <?php endif; ?>
            <?php if ($utente['primo_accesso']): ?>
                <p style="color: #dc2626;"><strong>⚠️ Primo accesso non ancora effettuato - Cambio password richiesto</strong></p>
            <?php else: ?>
                <p style="color: #059669;"><strong>✓ Primo accesso completato</strong></p>
            <?php endif; ?>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Salva Modifiche
            </button>
            <a href="<?php echo APP_PATH; ?>/gestione-utenti.php" class="btn btn-secondary">
                Annulla
            </a>
            
            <div style="margin-left: auto;">
                <button type="button" class="btn btn-warning" onclick="resetUserPassword(<?php echo $userId; ?>)">
                    <i class="fas fa-key"></i> Reset Password
                </button>
            </div>
        </div>
    </form>
</div>

<script>
// Mostra/nascondi permessi per azienda
function toggleAziendaPermessi(aziendaId) {
    const checkbox = document.getElementById('azienda_' + aziendaId);
    const permessiDiv = document.getElementById('permessi_' + aziendaId);
    
    if (checkbox.checked) {
        permessiDiv.style.display = '';
    } else {
        permessiDiv.style.display = 'none';
    }
}

// Reset password
async function resetUserPassword(userId) {
    if (!confirm('Sei sicuro di voler resettare la password per questo utente?')) {
        return;
    }
    
    try {
        const response = await fetch('<?php echo APP_PATH; ?>/gestione-utenti.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: `action=reset_password&user_id=${userId}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Password resettata!\n\n' + data.message.replace(/<[^>]*>/g, ''));
        } else {
            alert('Errore: ' + data.message);
        }
    } catch (error) {
        alert('Errore durante il reset della password');
    }
}
</script>

<?php include 'components/footer.php'; ?> 