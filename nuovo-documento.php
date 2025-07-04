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
$currentAzienda = $auth->getCurrentAzienda();

// Se non c'è un'azienda selezionata e non è super admin, reindirizza
if (!$currentAzienda && !$auth->isSuperAdmin()) {
    redirect('seleziona-azienda.php');
}

// Verifica permessi
if (!$auth->canAccess('documents', 'create')) {
    $_SESSION['error'] = "Non hai i permessi per creare documenti";
    redirect('dashboard.php');
}

// Carica lista aziende per super admin
$aziende = [];
if ($auth->isSuperAdmin()) {
    $stmt = db_query("SELECT id, nome FROM aziende WHERE stato = 'attiva' ORDER BY nome");
    $aziende = $stmt->fetchAll();
}

// Carica classificazioni (ora sono globali)
$classificazioni = [];
$stmt = db_query("
    SELECT id, codice, descrizione, parent_id, livello 
    FROM classificazione 
    WHERE attivo = 1 
    ORDER BY codice");
$classificazioni = $stmt->fetchAll();

// Carica tutti i template disponibili
$templates = [];
// I template vengono caricati dinamicamente quando si seleziona un'azienda

// Gestione form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $azienda_id = intval($_POST['azienda_id'] ?? ($currentAzienda['azienda_id'] ?? 0));
        $titolo = trim($_POST['titolo'] ?? '');
        $codice = trim($_POST['codice'] ?? '');
        $classificazione_id = intval($_POST['classificazione_id'] ?? 0);
        $template_id = intval($_POST['template_id'] ?? 0);
        $destinatari = $_POST['destinatari'] ?? [];
        
        // Validazione
        if (!$azienda_id || !$titolo || !$codice || !$classificazione_id) {
            throw new Exception("Tutti i campi obbligatori devono essere compilati");
        }
        
        // Verifica unicità codice
        $stmt = db_query("SELECT id FROM documenti WHERE codice = ?", [$codice]);
        if ($stmt->fetch()) {
            throw new Exception("Il codice documento è già in uso");
        }
        
        // Carica template se selezionato
        $contenuto = '';
        $modulo_id = null;
        if ($template_id) {
            $stmt = db_query("
                SELECT mt.*, md.id as modulo_id 
                FROM moduli_template mt
                JOIN moduli_documento md ON mt.modulo_id = md.id
                WHERE mt.id = ?", [$template_id]);
            $template = $stmt->fetch();
            if ($template) {
                $modulo_id = $template['modulo_id'];
                // Sostituisci placeholder nel contenuto
                $contenuto = $template['contenuto'] ?? '';
                $contenuto = str_replace('{{CODICE}}', htmlspecialchars($codice), $contenuto);
                $contenuto = str_replace('{{TITOLO}}', htmlspecialchars($titolo), $contenuto);
                $contenuto = str_replace('{{DATA}}', date('d/m/Y'), $contenuto);
            }
        }
        
        // Crea documento
        $stmt = db_query("
            INSERT INTO documenti (
                azienda_id, titolo, codice, contenuto, 
                classificazione_id, modulo_id, stato, 
                versioning_abilitato, versione_corrente,
                creato_da, aggiornato_da, aggiornato_il
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 1, ?, ?, NOW())",
            [
                $azienda_id, $titolo, $codice, $contenuto,
                $classificazione_id, $modulo_id, $_POST['stato'] ?? 'bozza',
                $user['id'], $user['id']
            ]
        );
        
        $documento_id = db_connection()->lastInsertId();
        
        // Salva destinatari
        if (!empty($destinatari)) {
            foreach ($destinatari as $referente_id) {
                $stmt = db_query("
                    INSERT INTO documenti_destinatari (documento_id, referente_id, tipo_destinatario)
                    VALUES (?, ?, 'principale')",
                    [$documento_id, $referente_id]
                );
            }
            
            // Invia notifiche email
            $stmt = db_query("
                SELECT r.*, a.nome as azienda_nome
                FROM referenti_aziende r
                JOIN aziende a ON r.azienda_id = a.id
                WHERE r.id IN (" . implode(',', array_map('intval', $destinatari)) . ")");
            $referenti = $stmt->fetchAll();
            
            foreach ($referenti as $referente) {
                $notificationManager->sendDocumentNotification(
                    $referente['email'],
                    $referente['nome'] . ' ' . $referente['cognome'],
                    $titolo,
                    $codice,
                    $user['nome'] . ' ' . $user['cognome'],
                    $referente['azienda_nome']
                );
            }
        }
        
        // Log attività
        $logger->log('documento_creato', "Creato documento: $titolo (ID: $documento_id)");
        
        $_SESSION['success'] = "Documento creato con successo";
        
        // Reindirizza all'editor avanzato
        header("Location: editor-nexio-integrated.php?id=$documento_id");
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

$pageTitle = 'Nuovo Documento';
require_once 'components/header.php';
?>

<style>
/* Document Creation Form - Consistent with system design */
.form-wizard {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border: 1px solid #e2e8f0;
    padding: 2rem;
    max-width: 900px;
    margin: 0 auto;
}

.wizard-header {
    text-align: center;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid #e2e8f0;
}

.wizard-steps {
    display: flex;
    justify-content: center;
    margin-bottom: 3rem;
    padding: 0 1rem;
}

.wizard-step {
    display: flex;
    align-items: center;
    color: #718096;
    font-size: 14px;
    font-weight: 500;
    position: relative;
}

.wizard-step.active {
    color: #4299e1;
    font-weight: 600;
}

.wizard-step.completed {
    color: #38a169;
}

.step-number {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #e2e8f0;
    color: #718096;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 0.75rem;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.2s ease;
}

.wizard-step.active .step-number {
    background: #4299e1;
    color: white;
}

.wizard-step.completed .step-number {
    background: #38a169;
    color: white;
}

.step-line {
    width: 80px;
    height: 2px;
    background: #e2e8f0;
    margin: 0 1rem;
    transition: background 0.3s ease;
}

.wizard-step.completed + .step-line {
    background: #38a169;
}

.form-section {
    margin-bottom: 2.5rem;
    padding: 1.5rem;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.form-section h3 {
    font-size: 18px;
    margin-bottom: 1.5rem;
    color: #2d3748;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 600;
}

.form-section h3 i {
    color: #4299e1;
    font-size: 16px;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #4a5568;
    font-size: 14px;
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.2s ease;
    background: white;
}

.form-control:focus {
    outline: none;
    border-color: #4299e1;
    box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

.form-text {
    font-size: 12px;
    color: #718096;
    margin-top: 0.5rem;
    line-height: 1.4;
}

.template-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 1.5rem;
    margin-top: 1.5rem;
}

.template-card {
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.5rem;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
    background: white;
    position: relative;
    overflow: hidden;
}

.template-card:hover {
    border-color: #4299e1;
    background: #f7fafc;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(66, 153, 225, 0.15);
}

.template-card.selected {
    border-color: #4299e1;
    background: #ebf8ff;
    box-shadow: 0 4px 12px rgba(66, 153, 225, 0.2);
}

.template-card i {
    font-size: 2.5rem;
    color: #4299e1;
    margin-bottom: 1rem;
    display: block;
}

.template-card h4 {
    font-size: 14px;
    margin: 0;
    color: #2d3748;
    font-weight: 600;
    line-height: 1.3;
}

.destinatari-list {
    max-height: 320px;
    overflow-y: auto;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 0.75rem;
    background: white;
}

.destinatario-item {
    display: flex;
    align-items: center;
    padding: 0.75rem;
    border-radius: 6px;
    margin-bottom: 0.5rem;
    transition: background 0.2s ease;
    border: 1px solid transparent;
}

.destinatario-item:hover {
    background: #f7fafc;
    border-color: #e2e8f0;
}

.destinatario-item input[type="checkbox"] {
    margin-right: 0.75rem;
    transform: scale(1.1);
}

.destinatario-item label {
    margin: 0 !important;
    cursor: pointer;
    flex: 1;
    font-size: 14px;
    line-height: 1.4;
}

.destinatario-item strong {
    color: #2d3748;
    font-weight: 600;
}

.placeholder-info {
    background: #ebf8ff;
    border: 1px solid #bee3f8;
    border-left: 4px solid #4299e1;
    border-radius: 8px;
    padding: 1.25rem;
    margin: 1.5rem 0;
    font-size: 14px;
    line-height: 1.5;
}

.placeholder-info strong {
    color: #2d3748;
    font-weight: 600;
    display: block;
    margin-bottom: 0.5rem;
}

.placeholder-info code {
    background: #2d3748;
    color: #e2e8f0;
    padding: 3px 6px;
    border-radius: 4px;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 12px;
    font-weight: 500;
    margin: 0 3px;
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    padding-top: 2rem;
    border-top: 1px solid #e2e8f0;
    margin-top: 2rem;
}

.btn {
    padding: 12px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.btn-primary {
    background: #4299e1;
    color: white;
}

.btn-primary:hover:not(:disabled) {
    background: #3182ce;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(66, 153, 225, 0.3);
}

.btn-primary:disabled {
    background: #a0aec0;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.btn-secondary {
    background: #e2e8f0;
    color: #2d3748;
}

.btn-secondary:hover {
    background: #cbd5e0;
    transform: translateY(-1px);
}

/* Content header consistency */
.content-header {
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e2e8f0;
}

.content-header h1 {
    color: #2d3748;
    font-size: 28px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 0;
}

.content-header h1 i {
    color: #4299e1;
    font-size: 24px;
}

.content-header .page-description {
    color: #718096;
    font-size: 16px;
    margin: 0.5rem 0 0 0;
    font-weight: 400;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .form-wizard {
        padding: 1.5rem;
        margin: 1rem;
    }
    
    .wizard-steps {
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .step-line {
        display: none;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .template-grid {
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 1rem;
    }
    
    .form-actions {
        flex-wrap: wrap;
        justify-content: stretch;
    }
    
    .btn {
        flex: 1;
        justify-content: center;
    }
}

/* Status messages */
.alert {
    padding: 1rem 1.25rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    border: 1px solid;
    font-size: 14px;
}

.alert-success {
    background: #c6f6d5;
    color: #22543d;
    border-color: #9ae6b4;
}

.alert-danger {
    background: #fed7d7;
    color: #742a2a;
    border-color: #feb2b2;
}
</style>

<div class="content-header">
    <h1><i class="fas fa-file-plus"></i> Nuovo Documento</h1>
    <p class="page-description">Crea un nuovo documento aziendale con template personalizzati</p>
</div>

<?php if (isset($_SESSION['success'])): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i>
    <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i>
    <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
</div>
<?php endif; ?>

<div class="form-wizard">
    <div class="wizard-header">
        <div class="wizard-steps">
            <div class="wizard-step active">
                <span class="step-number">1</span>
                <span>Informazioni Base</span>
            </div>
            <div class="step-line"></div>
            <div class="wizard-step">
                <span class="step-number">2</span>
                <span>Template</span>
            </div>
            <div class="step-line"></div>
            <div class="wizard-step">
                <span class="step-number">3</span>
                <span>Destinatari</span>
            </div>
        </div>
    </div>
    
    <form method="POST" id="documentForm">
        <!-- Step 1: Informazioni Base -->
        <div class="form-section">
            <h3><i class="fas fa-info-circle"></i> Informazioni Documento</h3>
            
            <?php if ($auth->isSuperAdmin()): ?>
            <div class="form-group">
                <label>Azienda *</label>
                <select name="azienda_id" class="form-control" required>
                    <option value="">Seleziona Azienda</option>
                    <?php foreach ($aziende as $azienda): ?>
                    <option value="<?php echo $azienda['id']; ?>">
                        <?php echo htmlspecialchars($azienda['nome']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
            <input type="hidden" name="azienda_id" value="<?php echo $currentAzienda['azienda_id'] ?? ''; ?>">
            <?php endif; ?>
            
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label>Codice Documento *</label>
                    <input type="text" name="codice" class="form-control" required
                           pattern="[A-Za-z0-9\-_]+"
                           placeholder="es. DOC-2024-001"
                           title="Solo lettere, numeri, trattini e underscore">
                    <small class="form-text text-muted">
                        Questo codice verrà usato come placeholder {{CODICE}} nel template
                    </small>
                </div>
                
                <div class="form-group col-md-6">
                    <label>Titolo *</label>
                    <input type="text" name="titolo" class="form-control" required
                           placeholder="Titolo del documento">
                    <small class="form-text text-muted">
                        Questo titolo verrà usato come placeholder {{TITOLO}} nel template
                    </small>
                </div>
            </div>
            
            <div class="form-group">
                <label>Classificazione *</label>
                <select name="classificazione_id" class="form-control" required id="classificazioneSelect">
                    <option value="">Seleziona classificazione</option>
                    <?php
                    function renderClassificationOptions($items, $level = 0) {
                        foreach ($items as $item) {
                            $indent = str_repeat('&nbsp;&nbsp;&nbsp;', $level);
                            echo "<option value='{$item['id']}' data-level='{$item['livello']}'>";
                            echo $indent . htmlspecialchars($item['codice'] . ' - ' . $item['descrizione']);
                            echo "</option>";
                            
                            // Ricorsione per i figli
                            $children = array_filter($GLOBALS['classificazioni'], function($c) use ($item) {
                                return $c['parent_id'] == $item['id'];
                            });
                            if (!empty($children)) {
                                renderClassificationOptions($children, $level + 1);
                            }
                        }
                    }
                    
                    $rootItems = array_filter($classificazioni, function($c) {
                        return $c['parent_id'] == null;
                    });
                    renderClassificationOptions($rootItems);
                    ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Stato Documento *</label>
                <select name="stato" class="form-control" required>
                    <option value="bozza">Bozza (visibile solo a utenti autorizzati)</option>
                    <option value="pubblicato">Pubblicato (visibile a tutti gli utenti dell'azienda)</option>
                </select>
                <small class="form-text text-muted">
                    I documenti in bozza sono visibili solo al proprietario, super admin e utenti con permesso specifico
                </small>
            </div>
        </div>
        
        <!-- Step 2: Template -->
        <div class="form-section" id="templateSection" style="display: none;">
            <h3><i class="fas fa-file-alt"></i> Seleziona Template</h3>
            
            <div class="placeholder-info">
                <strong>Placeholder disponibili:</strong>
                <code>{{CODICE}}</code> - Codice del documento
                <code>{{TITOLO}}</code> - Titolo del documento
                <code>{{DATA}}</code> - Data corrente
            </div>
            
            <div class="template-grid" id="templateGrid">
                <div class="template-card" data-template-id="0">
                    <i class="fas fa-file"></i>
                    <h4>Documento Vuoto</h4>
                </div>
                <?php foreach ($templates as $template): ?>
                <div class="template-card" data-template-id="<?php echo $template['id']; ?>">
                    <i class="fas fa-file-alt"></i>
                    <h4><?php echo htmlspecialchars($template['modulo_nome']); ?></h4>
                </div>
                <?php endforeach; ?>
            </div>
            <input type="hidden" name="template_id" id="templateId" value="0">
        </div>
        
        <!-- Step 3: Destinatari -->
        <div class="form-section" id="destinatariSection" style="display: none;">
            <h3><i class="fas fa-users"></i> Seleziona Destinatari</h3>
            
            <div class="destinatari-list" id="destinatariList">
                <p class="text-muted">Seleziona prima un'azienda per vedere i destinatari disponibili</p>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="button" class="btn btn-secondary" onclick="history.back()">
                <i class="fas fa-times"></i> Annulla
            </button>
            <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                <i class="fas fa-arrow-right"></i> Crea e Apri Editor
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('documentForm');
    const classificazioneSelect = document.getElementById('classificazioneSelect');
    const templateSection = document.getElementById('templateSection');
    const destinatariSection = document.getElementById('destinatariSection');
    const submitBtn = document.getElementById('submitBtn');
    const aziendaSelect = document.querySelector('[name="azienda_id"]');
    
    let currentStep = 1;
    
    // Gestione cambio classificazione
    classificazioneSelect.addEventListener('change', function() {
        if (this.value) {
            templateSection.style.display = 'block';
            updateWizardStep(2);
            
            // Carica template per l'azienda corrente o tutti i template per Super Admin
            const aziendaId = aziendaSelect ? aziendaSelect.value : 
                             document.querySelector('[name="azienda_id"]').value;
            
            console.log('🔄 Loading templates on classification change - aziendaId:', aziendaId, 'hasAziendaSelect:', !!aziendaSelect);
            
            // Forza il caricamento template quando viene selezionata una classificazione
            if (aziendaSelect) {
                // Super Admin: usa l'azienda selezionata o carica tutti
                const selectedAzienda = aziendaSelect.value;
                console.log('👑 Super Admin - azienda selezionata:', selectedAzienda);
                loadTemplates(selectedAzienda || '');
            } else if (aziendaId) {
                // Utente normale: usa l'azienda corrente
                console.log('👤 Utente normale - caricamento template per azienda:', aziendaId);
                loadTemplates(aziendaId);
            } else {
                // Fallback: carica tutti i template
                console.log('🔄 Fallback - caricamento tutti i template');
                loadTemplates('');
            }
        } else {
            templateSection.style.display = 'none';
            destinatariSection.style.display = 'none';
            updateWizardStep(1);
        }
        checkFormComplete();
    });
    
    // Gestione selezione template
    document.querySelectorAll('.template-card').forEach(card => {
        card.addEventListener('click', function() {
            document.querySelectorAll('.template-card').forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
            document.getElementById('templateId').value = this.dataset.templateId;
            
            // Carica destinatari se azienda selezionata
            const aziendaId = aziendaSelect ? aziendaSelect.value : 
                             document.querySelector('[name="azienda_id"]').value;
            if (aziendaId) {
                loadDestinatari(aziendaId);
            }
            
            checkFormComplete();
        });
    });
    
    // Gestione cambio azienda
    if (aziendaSelect) {
        aziendaSelect.addEventListener('change', function() {
            console.log('🏢 Cambio azienda a:', this.value);
            
            if (this.value) {
                console.log('📋 Caricamento template per azienda:', this.value);
                loadTemplates(this.value);
                
                // Se la sezione template è già visibile e c'è un template selezionato, carica destinatari
                if (templateSection.style.display !== 'none' && document.querySelector('.template-card.selected')) {
                    loadDestinatari(this.value);
                }
            } else {
                console.log('👑 Nessuna azienda selezionata - caricamento template globali');
                // Per Super Admin senza azienda: carica tutti i template
                loadTemplates('');
                
                // Reset sezioni
                templateSection.style.display = 'none';
                destinatariSection.style.display = 'none';
            }
            checkFormComplete();
        });
        
        // Se è già selezionata un'azienda al caricamento pagina, carica i template
        if (aziendaSelect.value) {
            console.log('🔄 Azienda già selezionata al caricamento:', aziendaSelect.value);
            loadTemplates(aziendaSelect.value);
        }
    }
    
    // Carica destinatari
    function loadDestinatari(aziendaId) {
        fetch(`backend/api/get-referenti.php?azienda_id=${aziendaId}`)
            .then(response => response.json())
            .then(data => {
                const destinatariList = document.getElementById('destinatariList');
                const destinatariSection = document.getElementById('destinatariSection');
                
                if (data.length > 0) {
                    // Mostra sezione destinatari solo se ci sono referenti
                    destinatariSection.style.display = 'block';
                    updateWizardStep(3);
                    
                    destinatariList.innerHTML = data.map(ref => `
                        <div class="destinatario-item">
                            <input type="checkbox" name="destinatari[]" 
                                   id="dest_${ref.id}" value="${ref.id}">
                            <label for="dest_${ref.id}" style="margin: 0; cursor: pointer;">
                                <strong>${ref.nome} ${ref.cognome}</strong>
                                ${ref.ruolo_aziendale ? `(${ref.ruolo_aziendale})` : ''}
                                - ${ref.email}
                            </label>
                        </div>
                    `).join('');
                } else {
                    // Nascondi completamente la sezione se non ci sono referenti
                    destinatariSection.style.display = 'none';
                    // Vai direttamente al submit
                    updateWizardStep(2);
                }
            })
            .catch(error => {
                console.error('Errore caricamento destinatari:', error);
                // In caso di errore, nascondi la sezione
                document.getElementById('destinatariSection').style.display = 'none';
            });
    }
    
    // Carica template per azienda
    function loadTemplates(aziendaId) {
        console.log('🔍 Loading templates for azienda:', aziendaId);
        const apiUrl = `backend/api/get-templates.php?azienda_id=${aziendaId}`;
        console.log('📡 API URL:', apiUrl);
        
        fetch(apiUrl)
            .then(response => {
                console.log('📡 Response status:', response.status);
                console.log('📡 Response headers:', response.headers);
                return response.text(); // Prima ottieni il testo per debug
            })
            .then(text => {
                console.log('📄 Raw response:', text);
                try {
                    const templates = JSON.parse(text);
                    console.log('✅ Parsed templates:', templates);
                    return templates;
                } catch (e) {
                    console.error('❌ JSON Parse Error:', e);
                    console.log('🔍 Response text that failed to parse:', text);
                    throw new Error('Invalid JSON response');
                }
            })
            .then(templates => {
                const templateGrid = document.getElementById('templateGrid');
                
                // Mantieni sempre l'opzione documento vuoto
                let html = `
                    <div class="template-card" data-template-id="0">
                        <i class="fas fa-file"></i>
                        <h4>Documento Vuoto</h4>
                    </div>
                `;
                
                if (templates.length > 0) {
                    console.log('✅ Rendering', templates.length, 'templates');
                    templates.forEach(template => {
                        console.log('📄 Template:', template.modulo_nome, 'ID:', template.id);
                        html += `
                            <div class="template-card" data-template-id="${template.id}">
                                <i class="fas ${template.icona || 'fa-file-alt'}"></i>
                                <h4>${template.modulo_nome}</h4>
                            </div>
                        `;
                    });
                } else {
                    console.log('❌ Nessun template ricevuto dall\'API');
                    const aziendaInfo = aziendaId ? `azienda ${aziendaId}` : 'Super Admin (tutti i template)';
                    html += `
                        <div style="grid-column: 1/-1; text-align: center; padding: 2rem; color: #999;">
                            <i class="fas fa-info-circle"></i> Nessun template disponibile per ${aziendaInfo}.
                            <br>Configura i moduli in Gestione Moduli.
                            <br><small>Controlla la console per dettagli debug</small>
                        </div>
                    `;
                }
                
                templateGrid.innerHTML = html;
                
                // Riattacca event listeners
                document.querySelectorAll('.template-card').forEach(card => {
                    card.addEventListener('click', function() {
                        document.querySelectorAll('.template-card').forEach(c => c.classList.remove('selected'));
                        this.classList.add('selected');
                        document.getElementById('templateId').value = this.dataset.templateId;
                        
                        if (aziendaId) {
                            loadDestinatari(aziendaId);
                        }
                        
                        checkFormComplete();
                    });
                });
            })
            .catch(error => {
                console.error('❌ Errore caricamento template:', error);
                console.log('🔍 Error details:', {
                    message: error.message,
                    stack: error.stack,
                    aziendaId: aziendaId,
                    apiUrl: `backend/api/get-templates.php?azienda_id=${aziendaId}`
                });
                
                // Fallback con template di base
                const templateGrid = document.getElementById('templateGrid');
                templateGrid.innerHTML = `
                    <div class="template-card" data-template-id="0">
                        <i class="fas fa-file"></i>
                        <h4>Documento Vuoto</h4>
                    </div>
                    <div class="template-card" data-template-id="1">
                        <i class="fas fa-file-alt"></i>
                        <h4>Template Base</h4>
                    </div>
                    <div style="grid-column: 1/-1; text-align: center; padding: 1rem; color: #dc3545; font-size: 0.9rem;">
                        <i class="fas fa-info-circle"></i> Template caricati in modalità fallback
                        <br><small>Errore: ${error.message}</small>
                        <br><small>Controlla la console per dettagli</small>
                    </div>
                `;
                
                // Riattacca event listeners per i template di fallback
                document.querySelectorAll('.template-card').forEach(card => {
                    card.addEventListener('click', function() {
                        document.querySelectorAll('.template-card').forEach(c => c.classList.remove('selected'));
                        this.classList.add('selected');
                        document.getElementById('templateId').value = this.dataset.templateId;
                        
                        if (aziendaId) {
                            loadDestinatari(aziendaId);
                        }
                        
                        checkFormComplete();
                    });
                });
            });
    }
    
    // Aggiorna wizard steps
    function updateWizardStep(step) {
        currentStep = step;
        document.querySelectorAll('.wizard-step').forEach((s, index) => {
            if (index < step - 1) {
                s.classList.add('completed');
                s.classList.remove('active');
            } else if (index === step - 1) {
                s.classList.add('active');
                s.classList.remove('completed');
            } else {
                s.classList.remove('active', 'completed');
            }
        });
    }
    
    // Verifica completamento form
    function checkFormComplete() {
        const codice = document.querySelector('[name="codice"]').value;
        const titolo = document.querySelector('[name="titolo"]').value;
        const classificazione = classificazioneSelect.value;
        const templateSelected = document.querySelector('.template-card.selected');
        const azienda = aziendaSelect ? aziendaSelect.value : true;
        
        if (codice && titolo && classificazione && templateSelected && azienda) {
            submitBtn.disabled = false;
        } else {
            submitBtn.disabled = true;
        }
    }
    
    // Monitora cambiamenti nei campi
    document.querySelector('[name="codice"]').addEventListener('input', checkFormComplete);
    document.querySelector('[name="titolo"]').addEventListener('input', checkFormComplete);
    
    // Inizializzazione template per Super Admin
    const aziendaId = aziendaSelect ? aziendaSelect.value : 
                     document.querySelector('[name="azienda_id"]').value;
    
    console.log('🔍 Inizializzazione - aziendaId:', aziendaId, 'aziendaSelect:', !!aziendaSelect);
    
    // Per Super Admin: carica tutti i template se non è selezionata un'azienda
    if (aziendaSelect && !aziendaId) {
        console.log('👑 Super Admin - caricamento template globali');
        loadTemplates(''); // Carica tutti i template
    }
    
    // Per utenti normali: carica template dell'azienda corrente
    if (aziendaId && !aziendaSelect) {
        console.log('👤 Utente normale - caricamento template azienda:', aziendaId);
        loadTemplates(aziendaId);
    }
    
    // Se c'è già un'azienda selezionata, carica i destinatari
    if (aziendaId && currentStep >= 3) {
        loadDestinatari(aziendaId);
    }
});
</script>

<?php require_once 'components/footer.php'; ?> 