<?php
require_once 'backend/config/config.php';

$auth = Auth::getInstance();
$auth->requireAuth();

// Solo super admin può accedere
if (!$auth->isSuperAdmin() && !$auth->hasRoleInAzienda('proprietario') && !$auth->hasRoleInAzienda('admin')) {
    $_SESSION['error'] = "Accesso non autorizzato - Solo gli amministratori possono gestire i moduli";
    header("Location: dashboard.php");
    exit;
}

// Database instance handled by functions
$action = $_GET['action'] ?? 'list';
$azienda_id = $_GET['azienda'] ?? null;

// Carica tutte le aziende
$stmt = db_query("SELECT * FROM aziende WHERE stato = 'attiva' ORDER BY nome");
$aziende = $stmt->fetchAll();

// Carica tutti i moduli (rimuovi duplicati potenziali)
$stmt = db_query("SELECT * FROM moduli_documento WHERE attivo = 1 GROUP BY codice ORDER BY ordine");
$moduli = $stmt->fetchAll();

// Debug: log dei moduli caricati
error_log("Moduli caricati (" . count($moduli) . "):");
foreach ($moduli as $mod) {
    error_log("- ID: {$mod['id']}, Nome: {$mod['nome']}, Codice: {$mod['codice']}");
}

// Gestione POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'save-modules' && $azienda_id) {
        // Salva moduli abilitati per azienda
        $moduli_abilitati = $_POST['moduli'] ?? [];
        
        try {
            db_connection()->beginTransaction();
            
            // Prima rimuovi tutti i moduli per l'azienda
            $stmt = db_query("DELETE FROM azienda_moduli WHERE azienda_id = ?", [$azienda_id]);
            
            // Log per debug
            error_log("Salvando moduli per azienda $azienda_id: " . print_r($moduli_abilitati, true));
            
            // Poi inserisci solo quelli abilitati
            foreach ($moduli_abilitati as $modulo_id) {
                db_query("INSERT INTO azienda_moduli (azienda_id, modulo_id, attivo) VALUES (?, ?, 1)", 
                    [$azienda_id, $modulo_id]);
            }
            
            db_connection()->commit();
            $_SESSION['success'] = "Moduli aggiornati con successo";
            redirect(APP_PATH . '/gestione-moduli.php?azienda=' . $azienda_id);
            
        } catch (Exception $e) {
            db_connection()->rollBack();
            $_SESSION['error'] = "Errore durante il salvataggio: " . $e->getMessage();
        }
    } elseif ($action === 'save-templates' && $azienda_id) {
        // Salva template abilitati per azienda
        $template_abilitati = $_POST['templates'] ?? [];
        
        try {
            db_connection()->beginTransaction();
            
            // Prima rimuovi tutti i template per l'azienda
            $stmt = db_query("DELETE FROM azienda_template WHERE azienda_id = ?", [$azienda_id]);
            
            // Poi inserisci solo quelli abilitati
            foreach ($template_abilitati as $template_id) {
                db_query("INSERT INTO azienda_template (azienda_id, template_id, attivo) VALUES (?, ?, 1)", 
                    [$azienda_id, $template_id]);
            }
            
            db_connection()->commit();
            $_SESSION['success'] = "Template aggiornati con successo";
            redirect(APP_PATH . '/gestione-moduli.php?azienda=' . $azienda_id);
            
        } catch (Exception $e) {
            db_connection()->rollback();
            $_SESSION['error'] = "Errore durante il salvataggio: " . $e->getMessage();
            error_log("Errore salvataggio moduli: " . $e->getMessage());
        }
    } elseif ($action === 'save-theme') {
        // Salva tema personalizzato
        $azienda_id_tema = $_POST['azienda_id'] ?? null;
        $tema_globale = isset($_POST['tema_globale']) ? 1 : 0;
        
        $tema_data = [
            'color_primary' => $_POST['color_primary'] ?? '#6b5cdf',
            'color_secondary' => $_POST['color_secondary'] ?? '#f59e0b',
            'color_success' => $_POST['color_success'] ?? '#10b981',
            'color_danger' => $_POST['color_danger'] ?? '#ef4444',
            'font_family' => $_POST['font_family'] ?? 'system-ui, -apple-system, sans-serif',
            'font_size_base' => $_POST['font_size_base'] ?? '16px',
            'border_radius' => $_POST['border_radius'] ?? '0.375rem'
        ];
        
        try {
            // Se è tema globale, rimuovi altri temi globali
            if ($tema_globale) {
                db_query("UPDATE temi_azienda SET is_global = 0");
            }
            
            // Verifica se esiste già un tema per l'azienda
            if ($azienda_id_tema) {
                $stmt = db_query("SELECT id FROM temi_azienda WHERE azienda_id = ?", [$azienda_id_tema]);
                $existing = $stmt->fetch();
            } else {
                $stmt = db_query("SELECT id FROM temi_azienda WHERE is_global = 1");
                $existing = $stmt->fetch();
            }
            
            $tema_json = json_encode($tema_data);
            
            if ($existing) {
                // Aggiorna tema esistente
                db_query("UPDATE temi_azienda SET 
                    nome_tema = ?,
                    configurazione = ?,
                    is_global = ?,
                    updated_at = NOW()
                    WHERE id = ?", 
                    ['Custom Theme', $tema_json, $tema_globale, $existing['id']]
                );
            } else {
                // Inserisci nuovo tema
                db_query("INSERT INTO temi_azienda (azienda_id, nome_tema, configurazione, is_global) 
                    VALUES (?, ?, ?, ?)",
                    [$azienda_id_tema, 'Custom Theme', $tema_json, $tema_globale]
                );
            }
            
            $_SESSION['success'] = "Tema salvato con successo";
            header("Location: gestione-moduli.php?action=themes");
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = "Errore durante il salvataggio: " . $e->getMessage();
        }
    }
}

// Se è selezionata un'azienda, carica i moduli abilitati
$moduli_abilitati = [];
$template_abilitati = [];
if ($azienda_id) {
    $stmt = db_query("SELECT modulo_id FROM azienda_moduli WHERE azienda_id = ? AND attivo = 1", [$azienda_id]);
    $moduli_abilitati = array_column($stmt->fetchAll(), 'modulo_id');
    
    // Carica template abilitati per l'azienda
    $stmt = db_query("SELECT template_id FROM azienda_template WHERE azienda_id = ? AND attivo = 1", [$azienda_id]);
    $template_abilitati = array_column($stmt->fetchAll(), 'template_id');
}

// Carica tutti i template drag-and-drop disponibili
$stmt = db_query("SELECT * FROM templates WHERE attivo = 1 ORDER BY nome");
$templates_dragdrop = $stmt->fetchAll();

// Debug: Log dei template caricati
error_log("Gestione Moduli - Template dragdrop caricati (" . count($templates_dragdrop) . "):");
foreach ($templates_dragdrop as $tpl) {
    error_log("- ID: {$tpl['id']}, Nome: {$tpl['nome']}, Attivo: {$tpl['attivo']}");
}

// Carica temi esistenti
$stmt = db_query("
    SELECT t.*, a.nome as nome_azienda 
    FROM temi_azienda t
    LEFT JOIN aziende a ON t.azienda_id = a.id
    ORDER BY t.is_global DESC, a.nome
");
$temi = $stmt->fetchAll();

$pageTitle = 'Gestione Moduli';
require_once 'components/header.php';
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
    
    body {
        background: var(--bg-primary);
        color: var(--text-primary);
        font-family: var(--font-sans);
    }
    
    .content-section {
        background: var(--bg-secondary);
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        border: 1px solid var(--border-color);
        margin-bottom: 20px;
    }
    
    .azienda-selector {
        max-width: 600px;
        margin: 0 auto;
        text-align: center;
    }
    
    .azienda-selector h3 {
        color: var(--text-primary);
        font-size: 1.5rem;
        margin-bottom: 1rem;
    }
    
    .azienda-selector p {
        color: var(--text-secondary);
        margin-bottom: 1.5rem;
    }
    
    .select-wrapper {
        position: relative;
    }
    
    .select-wrapper select {
        width: 100%;
        padding: 0.875rem 1rem;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        font-size: 15px;
        background: var(--bg-secondary);
        color: var(--text-primary);
        appearance: none;
        cursor: pointer;
        transition: all 0.3s ease;
        font-family: var(--font-sans);
    }
    
    .select-wrapper::after {
        content: '▼';
        position: absolute;
        right: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-secondary);
        pointer-events: none;
    }
    
    .select-wrapper select:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(43, 87, 154, 0.1);
    }
    
    .module-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    
    .module-card {
        background: var(--bg-secondary);
        border: 2px solid var(--border-color);
        border-radius: 12px;
        padding: 25px;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
        text-align: center;
    }
    
    .module-card:hover {
        border-color: var(--primary-color);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.08);
    }
    
    .module-card.selected {
        background: #e8f0fe;
        border-color: var(--primary-color);
        box-shadow: 0 4px 12px rgba(43, 87, 154, 0.2);
    }
    
    .module-card.selected::after {
        content: '✓';
        position: absolute;
        top: 15px;
        right: 15px;
        width: 24px;
        height: 24px;
        background: var(--primary-color);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
    }
    
    .module-card.selected .module-icon {
        background: rgba(43, 87, 154, 0.1);
        color: var(--primary-color);
    }
    
    .module-icon {
        font-size: 48px;
        margin-bottom: 15px;
        display: block;
    }
    
    .module-card h4 {
        color: var(--text-primary);
        font-size: 1.125rem;
        font-weight: 600;
        margin-bottom: 8px;
    }
    
    .module-card p {
        color: var(--text-secondary);
        font-size: 0.875rem;
        line-height: 1.5;
    }
    
    .module-code {
        display: inline-block;
        background: rgba(212, 165, 116, 0.1);
        color: var(--primary-dark);
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-family: monospace;
        margin-top: 8px;
    }
    
    .form-actions {
        display: flex;
        justify-content: center;
        gap: 1rem;
        margin-top: 2rem;
        padding-top: 2rem;
        border-top: 1px solid var(--border-color);
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
        background: var(--bg-primary);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
    }
    
    .btn-secondary:hover {
        background: var(--border-color);
    }
    
    .alert {
        padding: 1rem 1.5rem;
        border-radius: 10px;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }
    
    .alert-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
    
    .info-box {
        background: rgba(43, 87, 154, 0.1);
        border: 1px solid var(--primary-light);
        padding: 1rem;
        border-radius: 10px;
        margin-bottom: 1.5rem;
        color: var(--primary-dark);
        text-align: center;
    }
    
    .info-box i {
        margin-right: 0.5rem;
    }
    
    .alert {
        padding: 1rem;
        margin: 1rem 0;
        border-radius: 8px;
        border: 1px solid transparent;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .alert-info {
        background-color: #e7f3ff;
        border-color: #b3d7ff;
        color: #0c5aa6;
    }
    
    .module-grid .alert {
        grid-column: 1 / -1;
        margin: 0;
    }
    
    @media (max-width: 768px) {
        .module-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="content-header">
    <h1>⚙️ Gestione Moduli</h1>
    <div class="header-actions">
        <a href="gestione-moduli-template.php" class="btn btn-secondary">
            <i class="fas fa-file-code"></i> Gestione Template
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

<div class="content-section">
    <div class="azienda-selector">
        <h3>Seleziona Azienda</h3>
        <p>Scegli l'azienda per cui vuoi configurare i moduli disponibili</p>
        
        <div class="select-wrapper">
            <select id="azienda_select" onchange="selectAzienda(this.value)">
                <option value="">-- Seleziona un'azienda --</option>
                <?php foreach ($aziende as $azienda): ?>
                    <option value="<?php echo $azienda['id']; ?>" <?php echo $azienda_id == $azienda['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($azienda['nome']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    
    <?php if ($azienda_id): ?>
        <?php
        $azienda_selezionata = array_filter($aziende, function($a) use ($azienda_id) {
            return $a['id'] == $azienda_id;
        });
        $azienda_selezionata = reset($azienda_selezionata);
        ?>
        
        <div class="info-box" style="margin-top: 2rem;">
            <i class="fas fa-building"></i>
            <strong><?php echo htmlspecialchars($azienda_selezionata['nome']); ?></strong> - 
            Seleziona i moduli che questa azienda potrà utilizzare
        </div>
        
        <form method="POST" action="?action=save-modules&azienda=<?php echo $azienda_id; ?>">
            <div class="module-grid">
                <?php if (empty($moduli)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Nessun modulo disponibile nel sistema.
                    </div>
                <?php endif; ?>
                
                <?php foreach ($moduli as $modulo): ?>
                    <div class="module-card <?php echo in_array($modulo['id'], $moduli_abilitati) ? 'selected' : ''; ?>" data-module-id="<?php echo $modulo['id']; ?>">
                        <input type="checkbox" 
                               name="moduli[]" 
                               value="<?php echo $modulo['id']; ?>"
                               <?php echo in_array($modulo['id'], $moduli_abilitati) ? 'checked' : ''; ?>
                               class="module-checkbox"
                               style="position: absolute; opacity: 0; pointer-events: none;">
                        <div class="module-icon">
                            <?php if ($modulo['icona'] && strpos($modulo['icona'], 'fa-') !== false): ?>
                                <i class="fas <?php echo htmlspecialchars($modulo['icona']); ?>"></i>
                            <?php elseif ($modulo['icona']): ?>
                                <?php echo htmlspecialchars($modulo['icona']); ?>
                            <?php else: ?>
                                <i class="fas fa-file-alt"></i>
                            <?php endif; ?>
                        </div>
                        <h4><?php echo htmlspecialchars($modulo['nome']); ?></h4>
                        <?php if ($modulo['descrizione']): ?>
                            <p><?php echo htmlspecialchars($modulo['descrizione']); ?></p>
                        <?php endif; ?>
                        <span class="module-code"><?php echo htmlspecialchars($modulo['codice']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salva Moduli
                </button>
            </div>
        </form>
        
        <!-- Sezione Template Drag-and-Drop -->
        <div class="info-box" style="margin-top: 3rem;">
            <i class="fas fa-layer-group"></i>
            <strong>Template Drag-and-Drop</strong> - 
            Seleziona i template di intestazione/piè di pagina disponibili per questa azienda
        </div>
        
        <form method="POST" action="?action=save-templates&azienda=<?php echo $azienda_id; ?>">
            <div class="module-grid">
                <?php foreach ($templates_dragdrop as $template): ?>
                    <div class="module-card <?php echo in_array($template['id'], $template_abilitati) ? 'selected' : ''; ?>" data-template-id="<?php echo $template['id']; ?>">
                        <input type="checkbox" 
                               name="templates[]" 
                               value="<?php echo $template['id']; ?>"
                               <?php echo in_array($template['id'], $template_abilitati) ? 'checked' : ''; ?>
                               class="template-checkbox"
                               style="position: absolute; opacity: 0; pointer-events: none;">
                        <div class="module-icon">
                            <i class="fas fa-file-contract"></i>
                        </div>
                        <h4><?php echo htmlspecialchars($template['nome']); ?></h4>
                        <?php if ($template['descrizione']): ?>
                            <p><?php echo htmlspecialchars($template['descrizione']); ?></p>
                        <?php endif; ?>
                        <span class="module-code">Template <?php echo $template['tipo_template'] ?? 'globale'; ?></span>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($templates_dragdrop)): ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 2rem; color: #666;">
                        <i class="fas fa-info-circle" style="font-size: 2rem; margin-bottom: 1rem; color: #ccc;"></i>
                        <h4>Nessun template disponibile</h4>
                        <p>Crea dei template nel <a href="template-builder-dragdrop.php">Template Builder</a> per renderli disponibili alle aziende.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salva Template
                </button>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Annulla
                </a>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
function selectAzienda(aziendaId) {
    if (aziendaId) {
        window.location.href = '?azienda=' + aziendaId;
    } else {
        window.location.href = '?';
    }
}

// Click su module card per checkbox
document.addEventListener('DOMContentLoaded', function() {
    const moduleCards = document.querySelectorAll('.module-card');
    
    moduleCards.forEach(card => {
        card.addEventListener('click', function(e) {
            // Previeni click multipli
            e.preventDefault();
            e.stopPropagation();
            
            const checkbox = this.querySelector('input[type="checkbox"]');
            if (checkbox) {
                // Toggle checkbox
                checkbox.checked = !checkbox.checked;
                
                // Toggle classe selected
                if (checkbox.checked) {
                    this.classList.add('selected');
                } else {
                    this.classList.remove('selected');
                }
                
                // Debug
                const type = checkbox.classList.contains('template-checkbox') ? 'Template' : 'Modulo';
                console.log(type + ' ' + checkbox.value + ' selezionato: ' + checkbox.checked);
            }
        });
    });
    
    // Debug: verifica stato iniziale
    const checkboxes = document.querySelectorAll('.module-checkbox');
    console.log('Moduli totali:', checkboxes.length);
    checkboxes.forEach(cb => {
        if (cb.checked) {
            console.log('Modulo ' + cb.value + ' già selezionato');
        }
    });
});
</script>

<?php require_once 'components/footer.php'; ?> 