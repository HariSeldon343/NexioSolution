<?php
require_once 'backend/config/config.php';

$auth = Auth::getInstance();
$auth->requireAuth();

// Solo admin e super admin possono gestire le classificazioni
if (!$auth->isSuperAdmin() && !$auth->hasRoleInAzienda('admin') && !$auth->hasRoleInAzienda('proprietario')) {
    redirect('dashboard.php');
}

$user = $auth->getUser();
// Database instance handled by functions

// Gestione azioni
$action = $_GET['action'] ?? '';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        $codice = trim($_POST['codice']);
        $descrizione = trim($_POST['descrizione']);
        $parent_id = $_POST['parent_id'] ?: null;
        
        // Determina il livello
        $livello = 1;
        if ($parent_id) {
            $stmt = db_query("SELECT livello FROM classificazione WHERE id = ?", [$parent_id]);
            $parent = $stmt->fetch();
            $livello = $parent['livello'] + 1;
        }
        
        try {
            db_query("INSERT INTO classificazione (codice, descrizione, parent_id, livello) 
                       VALUES (?, ?, ?, ?)", 
                       [$codice, $descrizione, $parent_id, $livello]);
            $message = "Classificazione aggiunta con successo";
        } catch (Exception $e) {
            $error = "Errore nell'aggiunta: " . $e->getMessage();
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id']);
        
        // Verifica che non ci siano documenti associati
        $stmt = db_query("SELECT COUNT(*) as count FROM documenti WHERE classificazione_id = ?", [$id]);
        $count = $stmt->fetch()['count'];
        
        if ($count > 0) {
            $error = "Impossibile eliminare: ci sono $count documenti associati a questa classificazione";
        } else {
            // Verifica che non ci siano sottoclassificazioni
            $stmt = db_query("SELECT COUNT(*) as count FROM classificazione WHERE parent_id = ?", [$id]);
            $subcount = $stmt->fetch()['count'];
            
            if ($subcount > 0) {
                $error = "Impossibile eliminare: ci sono sottoclassificazioni associate";
            } else {
                db_query("DELETE FROM classificazione WHERE id = ?", [$id]);
                $message = "Classificazione eliminata con successo";
            }
        }
    }
}

// Carica tutte le classificazioni (ora sono globali)
$stmt = db_query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM documenti d WHERE d.classificazione_id = c.id) as num_documenti,
           (SELECT COUNT(*) FROM classificazione sub WHERE sub.parent_id = c.id) as num_sottoclassificazioni
    FROM classificazione c
    WHERE c.attivo = 1
    ORDER BY c.codice");
$classificazioni = $stmt->fetchAll();

// Costruisci albero classificazioni
function buildTree($items, $parent_id = null) {
    $tree = [];
    foreach ($items as $item) {
        if ($item['parent_id'] == $parent_id) {
            $item['children'] = buildTree($items, $item['id']);
            $tree[] = $item;
        }
    }
    return $tree;
}

$classificazioni_tree = buildTree($classificazioni);

$pageTitle = 'Gestione Classificazioni';
include 'components/header.php';
?>

<style>
    .classification-tree {
        list-style: none;
        padding-left: 0;
    }
    
    .classification-tree ul {
        list-style: none;
        padding-left: 2rem;
    }
    
    .classification-item {
        padding: 0.75rem;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        margin-bottom: 0.5rem;
        background: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .classification-item:hover {
        background: var(--bg-hover);
    }
    
    .classification-info {
        flex: 1;
    }
    
    .classification-code {
        font-weight: 600;
        color: var(--primary-color);
        margin-right: 1rem;
    }
    
    .classification-stats {
        font-size: 0.85rem;
        color: var(--text-secondary);
        margin-top: 0.25rem;
    }
    
    .classification-actions {
        display: flex;
        gap: 0.5rem;
    }
    
    .add-form {
        background: var(--bg-secondary);
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 2rem;
    }
    
    .info-box {
        background: #e3f2fd;
        border: 1px solid #90caf9;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1.5rem;
        color: #1565c0;
    }
    
    .info-box i {
        margin-right: 0.5rem;
    }
</style>

<div class="content-header">
    <h1><i class="fas fa-folder-tree"></i> Gestione Classificazioni</h1>
    <div class="header-actions">
        <button class="btn btn-primary" onclick="toggleAddForm()">
            <i class="fas fa-plus"></i> Nuova Classificazione
        </button>
    </div>
</div>

<div class="info-box">
    <i class="fas fa-info-circle"></i>
    Le classificazioni sono globali e valide per tutte le aziende del sistema.
</div>

<?php if ($message): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<!-- Form aggiunta -->
<div id="addForm" class="add-form" style="display: none;">
    <h3>Aggiungi Nuova Classificazione</h3>
    <form method="POST" action="?action=add">
        <div class="form-row">
            <div class="form-group" style="width: 150px;">
                <label>Codice</label>
                <input type="text" name="codice" class="form-control" required 
                       placeholder="es. 01.01">
            </div>
            <div class="form-group" style="flex: 1;">
                <label>Descrizione</label>
                <input type="text" name="descrizione" class="form-control" required
                       placeholder="es. Manuale Qualità">
            </div>
            <div class="form-group" style="width: 300px;">
                <label>Classificazione Padre</label>
                <select name="parent_id" class="form-control">
                    <option value="">-- Nessuna (primo livello) --</option>
                    <?php
                    function printOptions($items, $level = 0) {
                        foreach ($items as $item) {
                            echo '<option value="' . $item['id'] . '">';
                            echo str_repeat('&nbsp;&nbsp;', $level * 3);
                            echo htmlspecialchars($item['codice'] . ' - ' . $item['descrizione']);
                            echo '</option>';
                            if (!empty($item['children'])) {
                                printOptions($item['children'], $level + 1);
                            }
                        }
                    }
                    printOptions($classificazioni_tree);
                    ?>
                </select>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Salva
            </button>
            <button type="button" class="btn btn-secondary" onclick="toggleAddForm()">
                <i class="fas fa-times"></i> Annulla
            </button>
        </div>
    </form>
</div>

<!-- Lista classificazioni -->
<div class="card">
    <?php if (empty($classificazioni)): ?>
        <div class="empty-state">
            <i class="fas fa-folder-tree"></i>
            <h3>Nessuna classificazione presente</h3>
            <p>Aggiungi la prima classificazione per iniziare a organizzare i documenti</p>
        </div>
    <?php else: ?>
        <ul class="classification-tree">
            <?php
            function renderTree($items) {
                foreach ($items as $item) {
                    ?>
                    <li>
                        <div class="classification-item">
                            <div class="classification-info">
                                <span class="classification-code"><?php echo htmlspecialchars($item['codice']); ?></span>
                                <span><?php echo htmlspecialchars($item['descrizione']); ?></span>
                                <div class="classification-stats">
                                    <?php if ($item['num_documenti'] > 0): ?>
                                        <i class="fas fa-file-alt"></i> <?php echo $item['num_documenti']; ?> documenti
                                    <?php endif; ?>
                                    <?php if ($item['num_sottoclassificazioni'] > 0): ?>
                                        <i class="fas fa-folder"></i> <?php echo $item['num_sottoclassificazioni']; ?> sottoclassificazioni
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="classification-actions">
                                <?php if ($item['num_documenti'] == 0 && $item['num_sottoclassificazioni'] == 0): ?>
                                <form method="POST" action="?action=delete" style="display: inline;">
                                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-small" 
                                            onclick="return confirm('Eliminare questa classificazione?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!empty($item['children'])): ?>
                        <ul>
                            <?php renderTree($item['children']); ?>
                        </ul>
                        <?php endif; ?>
                    </li>
                    <?php
                }
            }
            renderTree($classificazioni_tree);
            ?>
        </ul>
    <?php endif; ?>
</div>

<script>
function toggleAddForm() {
    const form = document.getElementById('addForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}
</script>

<?php include 'components/footer.php'; ?> 