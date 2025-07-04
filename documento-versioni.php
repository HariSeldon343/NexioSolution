<?php
require_once 'backend/config/config.php';

$documento_id = $_GET['id'] ?? null;

if (!$documento_id) {
    $_SESSION['error'] = "Documento non specificato";
    redirect(APP_PATH . '/documenti.php');
}

if (!$auth->isAuthenticated()) {
    redirect(APP_PATH . '/documenti.php');
}

// Carica documento corrente
$stmt = db_query("
    SELECT d.*, u.nome as nome_autore, u.cognome as cognome_autore,
           c.nome as nome_categoria, m.nome as nome_modulo
    FROM documenti d
    LEFT JOIN utenti u ON d.creato_da = u.id
    LEFT JOIN categorie_documento c ON d.categoria_id = c.id
    LEFT JOIN moduli_documento m ON d.modulo_id = m.id
    WHERE d.id = ? AND d.azienda_id = ?
");
$stmt->execute([$documento_id, $currentAzienda]);
$documento = $stmt->fetch();

if (!$documento) {
    $_SESSION['error'] = "Documento non trovato";
    redirect(APP_PATH . '/documenti.php');
}

// Trova il documento padre (se è una versione) o usa l'ID corrente
$documento_padre_id = $documento['documento_padre_id'] ?? $documento_id;

// Carica tutte le versioni
$stmt = db_query("
    SELECT d.*, u.nome as nome_modificatore, u.cognome as cognome_modificatore
    FROM documenti d
    LEFT JOIN utenti u ON d.modificato_da = u.id
    WHERE (d.id = ? OR d.documento_padre_id = ?)
    AND d.azienda_id = ?
    ORDER BY d.versione_numero DESC
");
$stmt->execute([$documento_padre_id, $documento_padre_id, $currentAzienda]);
$versioni = $stmt->fetchAll();

// Gestione ripristino versione
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'restore') {
    if (!$auth->canAccess('documents', 'write')) {
        $_SESSION['error'] = "Non hai i permessi per modificare i documenti";
        redirect($_SERVER['REQUEST_URI']);
    }
    
    $versione_id = $_POST['versione_id'] ?? null;
    
    if ($versione_id) {
        try {
            db_connection()->beginTransaction();
            
            // Trova la versione da ripristinare
            $stmt = db_query("SELECT * FROM documenti WHERE id = ? AND azienda_id = ?");
            $stmt->execute([$versione_id, $currentAzienda]);
            $versione = $stmt->fetch();
            
            if ($versione) {
                // Marca tutte le versioni come non correnti
                $stmt = db_query("
                    UPDATE documenti 
                    SET is_current_version = 0 
                    WHERE (id = ? OR documento_padre_id = ?) AND azienda_id = ?
                ");
                $stmt->execute([$documento_padre_id, $documento_padre_id, $currentAzienda]);
                
                // Crea nuova versione dal ripristino
                $nuovo_numero_versione = getNextVersionNumber($documento_padre_id);
                
                $stmt = db_query("
                    INSERT INTO documenti (
                        azienda_id, titolo, contenuto, categoria_id, modulo_id,
                        versione_numero, documento_padre_id, is_current_version,
                        modifiche_descrizione, modificato_da, creato_da, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $currentAzienda,
                    $versione['titolo'],
                    $versione['contenuto'],
                    $versione['categoria_id'],
                    $versione['modulo_id'],
                    $nuovo_numero_versione,
                    $documento_padre_id,
                    "Ripristinata versione {$versione['versione_numero']}",
                    $user['id'],
                    $user['id']
                ]);
                
                // Log attività
                logActivity('restore', 'documento', "Ripristinata versione {$versione['versione_numero']} del documento", $documento_padre_id);
                
                // Notifica super admin
                notifySuperAdmins($documento_padre_id, 'ripristino', $user['id']);
                
                db_connection()->commit();
                $_SESSION['success'] = "Versione ripristinata con successo";
                redirect($_SERVER['REQUEST_URI']);
            }
            
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = "Errore durante il ripristino: " . $e->getMessage();
        }
    }
}

$pageTitle = "Versioni Documento: " . $documento['titolo'];
require_once 'components/header.php';
?>

<style>
    .version-timeline {
        position: relative;
        padding-left: 30px;
    }
    
    .version-timeline::before {
        content: '';
        position: absolute;
        left: 15px;
        top: 20px;
        bottom: 20px;
        width: 2px;
        background: #e5e7eb;
    }
    
    .version-item {
        position: relative;
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        transition: all 0.3s;
    }
    
    .version-item:hover {
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    
    .version-item.current {
        border-color: var(--color-primary);
        background: #f0f9ff;
    }
    
    .version-item::before {
        content: '';
        position: absolute;
        left: -22px;
        top: 30px;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background: white;
        border: 2px solid #e5e7eb;
    }
    
    .version-item.current::before {
        background: var(--color-primary);
        border-color: var(--color-primary);
    }
    
    .version-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 15px;
    }
    
    .version-info h3 {
        margin: 0 0 5px 0;
        color: #1f2937;
    }
    
    .version-meta {
        display: flex;
        gap: 20px;
        color: #6b7280;
        font-size: 14px;
    }
    
    .version-diff {
        background: #f9fafb;
        border-radius: 4px;
        padding: 15px;
        margin-top: 15px;
        font-family: monospace;
        font-size: 14px;
    }
    
    .diff-added {
        background: #d1fae5;
        color: #065f46;
    }
    
    .diff-removed {
        background: #fee2e2;
        color: #991b1b;
        text-decoration: line-through;
    }
    
    .compare-button {
        background: #f3f4f6;
        border: 1px solid #e5e7eb;
        padding: 5px 10px;
        border-radius: 4px;
        font-size: 12px;
        cursor: pointer;
    }
    
    .compare-button.selected {
        background: var(--color-primary);
        color: white;
        border-color: var(--color-primary);
    }
</style>

<div class="container">
    <div class="page-header">
        <div>
            <h1>📄 Versioni Documento</h1>
            <p class="text-muted"><?php echo htmlspecialchars($documento['titolo']); ?></p>
        </div>
        <div class="header-actions">
            <a href="<?php echo APP_PATH; ?>/documenti.php?modulo=<?php echo $documento['modulo_id']; ?>&categoria=<?php echo $documento['categoria_id']; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Torna ai documenti
            </a>
            <a href="documento-view.php?id=<?php echo $documento_id; ?>" class="btn btn-secondary">
                <i>👁️</i> Visualizza documento
            </a>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-8">
            <h2>Timeline Versioni</h2>
            <div class="version-timeline">
                <?php foreach ($versioni as $versione): ?>
                    <div class="version-item <?php echo $versione['is_current_version'] ? 'current' : ''; ?>">
                        <div class="version-header">
                            <div class="version-info">
                                <h3>
                                    Versione <?php echo $versione['versione_numero']; ?>
                                    <?php if ($versione['is_current_version']): ?>
                                        <span class="badge badge-primary">Corrente</span>
                                    <?php endif; ?>
                                </h3>
                                <div class="version-meta">
                                    <span>👤 <?php echo htmlspecialchars($versione['nome_modificatore'] . ' ' . $versione['cognome_modificatore']); ?></span>
                                    <span>📅 <?php echo format_datetime($versione['created_at']); ?></span>
                                </div>
                                <?php if ($versione['modifiche_descrizione']): ?>
                                    <p class="mt-2 text-muted">
                                        <strong>Modifiche:</strong> <?php echo htmlspecialchars($versione['modifiche_descrizione']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="version-actions">
                                <a href="documento-view.php?id=<?php echo $versione['id']; ?>&version=1" class="btn btn-sm btn-secondary">
                                    <i>👁️</i> Visualizza
                                </a>
                                <?php if (!$versione['is_current_version'] && $auth->canAccess('documents', 'write')): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Vuoi davvero ripristinare questa versione?');">
                                        <input type="hidden" name="action" value="restore">
                                        <input type="hidden" name="versione_id" value="<?php echo $versione['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-warning">
                                            <i>🔄</i> Ripristina
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <button class="compare-button" onclick="toggleCompare(<?php echo $versione['id']; ?>)">
                                    <i>🔍</i> Confronta
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h3>ℹ️ Informazioni Documento</h3>
                </div>
                <div class="card-body">
                    <dl>
                        <dt>Modulo</dt>
                        <dd><?php echo htmlspecialchars($documento['nome_modulo']); ?></dd>
                        
                        <dt>Categoria</dt>
                        <dd><?php echo htmlspecialchars($documento['nome_categoria']); ?></dd>
                        
                        <dt>Creato da</dt>
                        <dd><?php echo htmlspecialchars($documento['nome_autore'] . ' ' . $documento['cognome_autore']); ?></dd>
                        
                        <dt>Data creazione</dt>
                        <dd><?php echo format_datetime($documento['created_at']); ?></dd>
                        
                        <dt>Numero versioni</dt>
                        <dd><?php echo count($versioni); ?></dd>
                        
                        <dt>Ultima modifica</dt>
                        <dd><?php echo format_datetime($versioni[0]['created_at']); ?></dd>
                    </dl>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header">
                    <h3>🔍 Confronta Versioni</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted">Seleziona due versioni da confrontare usando i pulsanti "Confronta" accanto a ogni versione.</p>
                    <div id="compare-selections" style="display: none;">
                        <p><strong>Versione 1:</strong> <span id="compare-v1"></span></p>
                        <p><strong>Versione 2:</strong> <span id="compare-v2"></span></p>
                        <button class="btn btn-primary" onclick="compareVersions()">
                            <i>📊</i> Confronta
                        </button>
                        <button class="btn btn-secondary" onclick="clearCompare()">
                            <i>❌</i> Annulla
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Confronto -->
<div id="compareModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 90%; width: 90%;">
        <div class="modal-header">
            <h2>Confronto Versioni</h2>
            <span class="close" onclick="closeCompareModal()">&times;</span>
        </div>
        <div class="modal-body" id="compareContent">
            <!-- Contenuto confronto verrà caricato qui -->
        </div>
    </div>
</div>

<script>
let compareVersions = [];

function toggleCompare(versionId) {
    const button = event.target.closest('.compare-button');
    
    if (compareVersions.includes(versionId)) {
        // Rimuovi dalla selezione
        compareVersions = compareVersions.filter(v => v !== versionId);
        button.classList.remove('selected');
    } else {
        // Aggiungi alla selezione
        if (compareVersions.length < 2) {
            compareVersions.push(versionId);
            button.classList.add('selected');
        } else {
            alert('Puoi confrontare solo due versioni alla volta');
            return;
        }
    }
    
    updateCompareUI();
}

function updateCompareUI() {
    const compareDiv = document.getElementById('compare-selections');
    
    if (compareVersions.length > 0) {
        compareDiv.style.display = 'block';
        
        // Aggiorna testo versioni selezionate
        if (compareVersions[0]) {
            const v1Text = document.querySelector(`.version-item:has([onclick*="${compareVersions[0]}"]) h3`).textContent;
            document.getElementById('compare-v1').textContent = v1Text;
        }
        
        if (compareVersions[1]) {
            const v2Text = document.querySelector(`.version-item:has([onclick*="${compareVersions[1]}"]) h3`).textContent;
            document.getElementById('compare-v2').textContent = v2Text;
        }
    } else {
        compareDiv.style.display = 'none';
    }
}

function clearCompare() {
    compareVersions = [];
    document.querySelectorAll('.compare-button.selected').forEach(btn => {
        btn.classList.remove('selected');
    });
    updateCompareUI();
}

function compareVersions() {
    if (compareVersions.length !== 2) {
        alert('Seleziona esattamente due versioni da confrontare');
        return;
    }
    
    // Carica confronto via AJAX
    fetch(`documento-compare.php?v1=${compareVersions[0]}&v2=${compareVersions[1]}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('compareContent').innerHTML = html;
            document.getElementById('compareModal').style.display = 'block';
        })
        .catch(error => {
            alert('Errore durante il caricamento del confronto');
            console.error(error);
        });
}

function closeCompareModal() {
    document.getElementById('compareModal').style.display = 'none';
}

// Chiudi modal cliccando fuori
window.onclick = function(event) {
    const modal = document.getElementById('compareModal');
    if (event.target == modal) {
        closeCompareModal();
    }
}
</script>

<?php
require_once 'components/footer.php';

// Funzione helper per ottenere il prossimo numero di versione
function getNextVersionNumber($documento_padre_id) {
    global $db;
    
    $stmt = db_query("
        SELECT MAX(versione_numero) as max_version 
        FROM documenti 
        WHERE (id = ? OR documento_padre_id = ?)
    ");
    $stmt->execute([$documento_padre_id, $documento_padre_id]);
    $result = $stmt->fetch();
    
    return ($result['max_version'] ?? 0) + 1;
}
?> 