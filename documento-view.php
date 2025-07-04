<?php
require_once 'backend/config/config.php';

$auth = Auth::getInstance();
$auth->requireAuth();

// Database instance handled by functions
$currentAzienda = $auth->getCurrentAzienda();

// Parametri GET
$doc_id = $_GET['id'] ?? null;
$from_fascicolo = isset($_GET['from_fascicolo']);
$azienda_id = $_GET['azienda_id'] ?? null;

if (!$doc_id) {
    $_SESSION['error'] = "Documento non specificato";
    redirect(APP_PATH . '/documenti.php');
}

// Se viene dal fascicolo, verifica permessi speciali
if ($from_fascicolo && $azienda_id) {
    // Verifica permessi per accedere al fascicolo
    $canAccess = false;
    if ($auth->isSuperAdmin()) {
        $canAccess = true;
    } else {
        // Verifica se l'utente appartiene all'azienda
        $stmt = db_query("SELECT 1 FROM utenti_aziende WHERE utente_id = ? AND azienda_id = ? AND attivo = 1", 
                          [$auth->getUser()['id'], $azienda_id]);
        if ($stmt->fetch()) {
            $canAccess = true;
        }
    }
    
    if (!$canAccess) {
        $_SESSION['error'] = "Non hai i permessi per accedere a questo documento";
        redirect(APP_PATH . '/dashboard.php');
    }
    
    // Carica documento verificando che appartenga all'azienda specificata
    $stmt = db_query("
        SELECT d.*, t.tipo as template_tipo, t.nome as template_nome,
               u.nome as autore_nome, u.cognome as autore_cognome,
               c.nome as categoria_nome, m.nome as modulo_nome
        FROM documenti d
        LEFT JOIN template_documento t ON d.template_id = t.id
        LEFT JOIN utenti u ON d.creato_da = u.id
        LEFT JOIN categorie_documento c ON d.categoria_id = c.id
        LEFT JOIN moduli_documento m ON d.modulo_id = m.id
        WHERE d.id = ? AND d.azienda_id = ?",
        [$doc_id, $azienda_id]
    );
    
    $documento = $stmt->fetch();
    
    if (!$documento) {
        $_SESSION['error'] = "Documento non trovato";
        redirect(APP_PATH . '/esporta-fascicolo.php?azienda_id=' . $azienda_id);
    }
    
    // Carica informazioni azienda
    $stmt = db_query("SELECT * FROM aziende WHERE id = ?", [$azienda_id]);
    $azienda = $stmt->fetch();
    
} else {
    // Comportamento normale con azienda corrente
    if (!$currentAzienda) {
        $_SESSION['error'] = "Seleziona un'azienda";
        redirect(APP_PATH . '/documenti.php');
    }
    
    // Carica documento con template e autore
    $stmt = db_query("
        SELECT d.*, t.tipo as template_tipo, t.nome as template_nome,
               u.nome as autore_nome, u.cognome as autore_cognome,
               c.nome as categoria_nome, m.nome as modulo_nome
        FROM documenti d
        LEFT JOIN template_documento t ON d.template_id = t.id
        LEFT JOIN utenti u ON d.creato_da = u.id
        LEFT JOIN categorie_documento c ON d.categoria_id = c.id
        LEFT JOIN moduli_documento m ON d.modulo_id = m.id
        WHERE d.id = ? AND d.azienda_id = ?",
        [$doc_id, $currentAzienda['id']]
    );
    
    $documento = $stmt->fetch();
    
    if (!$documento) {
        $_SESSION['error'] = "Documento non trovato";
        redirect(APP_PATH . '/documenti.php');
    }
    
    // Carica informazioni azienda per intestazione
    $stmt = db_query("SELECT * FROM aziende WHERE id = ?", [$currentAzienda['id']]);
    $azienda = $stmt->fetch();
}

$pageTitle = 'Visualizza Documento';
require_once 'components/header.php';
?>

<style>
    .document-viewer {
        background: white;
        border-radius: 8px;
        padding: 30px;
        margin-top: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .document-header {
        border-bottom: 2px solid #e5e7eb;
        padding-bottom: 20px;
        margin-bottom: 30px;
    }
    
    .document-meta {
        display: flex;
        gap: 30px;
        margin-top: 10px;
        font-size: 14px;
        color: #6b7280;
    }
    
    .document-content {
        min-height: 400px;
        line-height: 1.6;
    }
    
    .document-content img {
        max-width: 100%;
        height: auto;
    }
    
    .form-field-display {
        margin-bottom: 20px;
        padding: 15px;
        background: #f9fafb;
        border-radius: 6px;
    }
    
    .form-field-display label {
        font-weight: 600;
        display: block;
        margin-bottom: 5px;
        color: #374151;
    }
    
    .form-field-display .value {
        color: #111827;
    }
    
    .document-actions {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
    }
    
    @media print {
        .sidebar, .document-actions, .content-header {
            display: none !important;
        }
        
        .main-content {
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .document-viewer {
            box-shadow: none !important;
            padding: 20px !important;
        }
    }
    
    .company-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 3px solid var(--color-primary);
    }
    
    .company-info h2 {
        margin: 0;
        color: var(--color-primary);
    }
    
    .document-code {
        font-size: 14px;
        color: #6b7280;
        font-weight: 600;
    }
</style>

            <div class="content-header">
                <h1>Visualizza Documento</h1>
                <div class="header-actions">
                    <a href="documenti.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Torna ai documenti
                    </a>
                </div>
            </div>
            
            <div class="document-actions">
                <button onclick="window.print()" class="btn btn-secondary">
                    <i class="fas fa-print"></i> Stampa
                </button>
                <a href="documento-pdf.php?id=<?php echo $doc_id; ?>" class="btn btn-secondary" target="_blank">
                    <i class="fas fa-file-pdf"></i> Esporta PDF
                </a>
                <?php if ($auth->canAccess('documents', 'write')): ?>
                                            <a href="editor-fixed.php?id=<?php echo $doc_id; ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Modifica
                        </a>
                <?php endif; ?>
                <?php if ($documento['versione_numero'] > 1): ?>
                    <a href="documento-versioni.php?id=<?php echo $doc_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-history"></i> Cronologia versioni
                    </a>
                <?php endif; ?>
            </div>
            
            <div class="document-viewer">
                <!-- Header aziendale per stampa -->
                <div class="company-header">
                    <div class="company-info">
                        <h2><?php echo htmlspecialchars($azienda['nome']); ?></h2>
                        <p><?php echo htmlspecialchars($azienda['indirizzo']); ?></p>
                        <p><?php echo htmlspecialchars($azienda['citta'] . ' - ' . $azienda['cap']); ?></p>
                    </div>
                    <div class="document-code">
                        Codice: <?php echo htmlspecialchars($documento['codice']); ?><br>
                        Versione: <?php echo $documento['versione_numero']; ?>
                    </div>
                </div>
                
                <div class="document-header">
                    <h1><?php echo htmlspecialchars($documento['titolo']); ?></h1>
                    <div class="document-meta">
                        <span>
                            <i class="fas fa-folder"></i> 
                            <?php echo htmlspecialchars($documento['modulo_nome'] . ' / ' . $documento['categoria_nome']); ?>
                        </span>
                        <span>
                            <i class="fas fa-user"></i> 
                            <?php echo htmlspecialchars($documento['autore_nome'] . ' ' . $documento['autore_cognome']); ?>
                        </span>
                        <span>
                            <i class="fas fa-calendar"></i> 
                            <?php echo date('d/m/Y H:i', strtotime($documento['created_at'])); ?>
                        </span>
                        <?php if ($documento['template_nome']): ?>
                            <span>
                                <i class="fas fa-file-alt"></i> 
                                Template: <?php echo htmlspecialchars($documento['template_nome']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="document-content">
                    <?php if ($documento['template_tipo'] == 'word' && $documento['contenuto_documento']): ?>
                        <!-- Mostra contenuto HTML dell'editor -->
                        <?php echo $documento['contenuto_documento']; ?>
                        
                    <?php elseif ($documento['template_tipo'] == 'form' && $documento['dati_form']): ?>
                        <!-- Mostra dati del form compilato -->
                        <?php 
                        $dati_form = json_decode($documento['dati_form'], true);
                        if (is_array($dati_form)):
                            foreach ($dati_form as $campo):
                        ?>
                            <div class="form-field-display">
                                <label><?php echo htmlspecialchars($campo['name'] ?? ''); ?></label>
                                <div class="value">
                                    <?php 
                                    if ($campo['type'] == 'checkbox') {
                                        echo $campo['value'] ? '✓ Sì' : '✗ No';
                                    } else {
                                        echo htmlspecialchars($campo['value'] ?? '');
                                    }
                                    ?>
                                </div>
                            </div>
                        <?php 
                            endforeach;
                        endif;
                        ?>
                        
                    <?php elseif ($documento['contenuto']): ?>
                        <!-- Contenuto legacy -->
                        <div style="white-space: pre-wrap;"><?php echo htmlspecialchars($documento['contenuto']); ?></div>
                        
                    <?php else: ?>
                        <p class="text-muted">Nessun contenuto disponibile</p>
                    <?php endif; ?>
                </div>
                
                <?php if ($documento['updated_at'] && $documento['updated_at'] != $documento['created_at']): ?>
                    <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #6b7280;">
                        Ultima modifica: <?php echo date('d/m/Y H:i', strtotime($documento['updated_at'])); ?>
                        <?php if ($documento['modifiche_descrizione']): ?>
                            - <?php echo htmlspecialchars($documento['modifiche_descrizione']); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

<?php require_once 'components/footer.php'; ?> 