<?php
require_once 'backend/config/config.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$user = $auth->getUser();
// Database instance handled by functions

// Parametri
$azienda_id = $_GET['azienda_id'] ?? null;
$action = $_GET['action'] ?? 'preview';
$document_id = $_GET['document_id'] ?? null;

if (!$azienda_id) {
    $_SESSION['error'] = "Azienda non specificata";
    redirect(APP_PATH . '/aziende.php');
}

// Verifica permessi
$canAccess = false;
if ($auth->isSuperAdmin()) {
    $canAccess = true;
} else {
    // Verifica se l'utente appartiene all'azienda
    $stmt = db_query("SELECT 1 FROM utenti_aziende WHERE utente_id = ? AND azienda_id = ? AND attivo = 1", 
                      [$user['id'], $azienda_id]);
    if ($stmt->fetch()) {
        $canAccess = true;
    }
}

if (!$canAccess) {
    $_SESSION['error'] = "Non hai i permessi per accedere a questa funzionalità";
    redirect(APP_PATH . '/dashboard.php');
}

// Carica dati azienda
$stmt = db_query("SELECT * FROM aziende WHERE id = ?", [$azienda_id]);
$azienda = $stmt->fetch();

if (!$azienda) {
    $_SESSION['error'] = "Azienda non trovata";
    redirect(APP_PATH . '/aziende.php');
}

// Se richiesto un documento specifico
if ($action === 'print-document' && $document_id) {
    $stmt = db_query("
        SELECT d.*, md.nome as modulo_nome, c.descrizione as classificazione_nome,
               u.nome as assegnato_nome, u.cognome as assegnato_cognome
        FROM documenti d
        LEFT JOIN moduli_documento md ON d.modulo_id = md.id
        LEFT JOIN classificazioni c ON d.classificazione_id = c.id
        LEFT JOIN utenti u ON d.assegnato_a = u.id
        WHERE d.id = ? AND d.azienda_id = ? AND d.attivo = 1
    ", [$document_id, $azienda_id]);
    $documento = $stmt->fetch();
    
    if (!$documento) {
        $_SESSION['error'] = "Documento non trovato";
        redirect(APP_PATH . '/esporta-fascicolo.php?azienda_id=' . $azienda_id);
    }
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <title><?php echo htmlspecialchars($documento['titolo']); ?> - <?php echo htmlspecialchars($azienda['nome']); ?></title>
        <style>
            @media print {
                @page {
                    size: A4;
                    margin: 2cm;
                }
            }
            
            body {
                font-family: Arial, sans-serif;
                font-size: 11pt;
                line-height: 1.6;
                color: #333;
                max-width: 210mm;
                margin: 0 auto;
                padding: 20px;
            }
            
            .header {
                text-align: center;
                border-bottom: 3px solid #2d5a9f;
                padding-bottom: 20px;
                margin-bottom: 30px;
            }
            
            .header h1 {
                color: #2d5a9f;
                margin: 0 0 10px 0;
                font-size: 24pt;
            }
            
            .document-meta {
                background: #f5f5f5;
                padding: 15px;
                border-radius: 5px;
                margin-bottom: 20px;
            }
            
            .meta-item {
                margin-bottom: 8px;
            }
            
            .meta-label {
                font-weight: bold;
                color: #666;
                display: inline-block;
                width: 150px;
            }
            
            .document-content {
                margin-top: 30px;
                padding: 20px;
                background: white;
                border: 1px solid #e0e0e0;
            }
            
            .no-print {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 1000;
            }
            
            @media print {
                .no-print {
                    display: none !important;
                }
            }
            
            .btn-print {
                background: #2d5a9f;
                color: white;
                padding: 10px 20px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
            }
            
            .btn-print:hover {
                background: #1e3d6f;
            }
        </style>
    </head>
    <body>
        <div class="no-print">
            <button onclick="window.print()" class="btn-print">
                <i class="fas fa-print"></i> Stampa/Salva PDF
            </button>
        </div>
        
        <div class="header">
            <h1><?php echo htmlspecialchars($documento['titolo']); ?></h1>
            <div style="color: #666;"><?php echo htmlspecialchars($azienda['nome']); ?></div>
        </div>
        
        <div class="document-meta">
            <div class="meta-item">
                <span class="meta-label">Codice Documento:</span>
                <span><?php echo htmlspecialchars($documento['codice']); ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Tipo Documento:</span>
                <span><?php echo htmlspecialchars($documento['modulo_nome'] ?? 'Non specificato'); ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Classificazione:</span>
                <span><?php echo htmlspecialchars($documento['classificazione_nome'] ?? 'Non classificato'); ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Data Creazione:</span>
                <span><?php echo date('d/m/Y H:i', strtotime($documento['data_creazione'])); ?></span>
            </div>
            <?php if ($documento['assegnato_nome']): ?>
            <div class="meta-item">
                <span class="meta-label">Assegnato a:</span>
                <span><?php echo htmlspecialchars($documento['assegnato_nome'] . ' ' . $documento['assegnato_cognome']); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($documento['numero_protocollo']): ?>
            <div class="meta-item">
                <span class="meta-label">Numero Protocollo:</span>
                <span><?php echo htmlspecialchars($documento['numero_protocollo']); ?> del <?php echo date('d/m/Y', strtotime($documento['data_protocollo'])); ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if ($documento['descrizione']): ?>
        <div style="margin-bottom: 20px;">
            <h2 style="color: #2d5a9f;">Descrizione</h2>
            <p><?php echo nl2br(htmlspecialchars($documento['descrizione'])); ?></p>
        </div>
        <?php endif; ?>
        
        <?php if ($documento['contenuto']): ?>
        <div class="document-content">
            <?php echo $documento['contenuto']; ?>
        </div>
        <?php endif; ?>
        
        <script>
            // Auto-print on load if requested
            <?php if (isset($_GET['autoprint'])): ?>
            window.onload = function() {
                window.print();
            }
            <?php endif; ?>
        </script>
    </body>
    </html>
    <?php
    exit;
}

// Carica tutti i documenti dell'azienda
$stmt = db_query("
    SELECT d.*, md.nome as modulo_nome, c.descrizione as classificazione_nome,
           u.nome as assegnato_nome, u.cognome as assegnato_cognome
    FROM documenti d
    LEFT JOIN moduli_documento md ON d.modulo_id = md.id
    LEFT JOIN classificazioni c ON d.classificazione_id = c.id
    LEFT JOIN utenti u ON d.assegnato_a = u.id
    WHERE d.azienda_id = ? AND d.attivo = 1
    ORDER BY d.data_creazione DESC
", [$azienda_id]);
$documenti = $stmt->fetchAll();

// Se richiesta la stampa del fascicolo completo
if ($action === 'print-all') {
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <title>Fascicolo Documentale - <?php echo htmlspecialchars($azienda['nome']); ?></title>
        <style>
            @media print {
                @page {
                    size: A4;
                    margin: 2cm;
                }
                
                .document {
                    page-break-inside: avoid;
                }
                
                .page-break {
                    page-break-after: always;
                }
            }
            
            body {
                font-family: Arial, sans-serif;
                font-size: 11pt;
                line-height: 1.6;
                color: #333;
                max-width: 210mm;
                margin: 0 auto;
                padding: 20px;
            }
            
            .header {
                text-align: center;
                border-bottom: 3px solid #2d5a9f;
                padding-bottom: 20px;
                margin-bottom: 30px;
            }
            
            .header h1 {
                color: #2d5a9f;
                margin: 0 0 10px 0;
                font-size: 24pt;
            }
            
            .header .subtitle {
                color: #666;
                font-size: 12pt;
            }
            
            .company-info {
                background: #f5f5f5;
                padding: 15px;
                border-radius: 5px;
                margin-bottom: 30px;
            }
            
            .company-info h2 {
                color: #2d5a9f;
                margin-top: 0;
                font-size: 16pt;
            }
            
            .info-grid {
                display: table;
                width: 100%;
            }
            
            .info-row {
                display: table-row;
            }
            
            .info-label {
                display: table-cell;
                width: 30%;
                padding: 5px 10px 5px 0;
                font-weight: bold;
                color: #666;
            }
            
            .info-value {
                display: table-cell;
                width: 70%;
                padding: 5px 0;
            }
            
            .documents-section {
                margin-top: 40px;
            }
            
            .documents-section h2 {
                color: #2d5a9f;
                border-bottom: 2px solid #2d5a9f;
                padding-bottom: 10px;
                font-size: 18pt;
            }
            
            .document {
                border: 1px solid #ddd;
                padding: 15px;
                margin-bottom: 20px;
                background: #fafafa;
            }
            
            .document h3 {
                color: #2d5a9f;
                margin-top: 0;
                font-size: 14pt;
            }
            
            .document-meta {
                font-size: 10pt;
                color: #666;
                margin-bottom: 10px;
            }
            
            .document-content {
                margin-top: 15px;
                padding: 15px;
                background: white;
                border: 1px solid #e0e0e0;
            }
            
            .footer {
                margin-top: 50px;
                text-align: center;
                font-size: 9pt;
                color: #666;
                border-top: 1px solid #ddd;
                padding-top: 10px;
            }
            
            .statistics {
                background: #e8f0fe;
                padding: 15px;
                border-radius: 5px;
                margin-bottom: 30px;
                text-align: center;
            }
            
            .stat-value {
                font-size: 20pt;
                font-weight: bold;
                color: #2d5a9f;
            }
            
            .no-print {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 1000;
                display: flex;
                gap: 10px;
            }
            
            @media print {
                .no-print {
                    display: none !important;
                }
            }
            
            .btn-print {
                background: #2d5a9f;
                color: white;
                padding: 10px 20px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
            }
            
            .btn-print:hover {
                background: #1e3d6f;
            }
            
            .btn-secondary {
                background: #6c757d;
                color: white;
                padding: 10px 20px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
                text-decoration: none;
                display: inline-block;
            }
        </style>
    </head>
    <body>
        <div class="no-print">
            <button onclick="window.print()" class="btn-print">
                <i class="fas fa-print"></i> Stampa/Salva PDF
            </button>
            <a href="esporta-fascicolo.php?azienda_id=<?php echo $azienda_id; ?>" class="btn-secondary">
                Torna all'anteprima
            </a>
        </div>
        
        <div class="header">
            <h1>Fascicolo Documentale</h1>
            <div class="subtitle"><?php echo htmlspecialchars($azienda['nome']); ?></div>
            <div class="subtitle">Generato il <?php echo date('d/m/Y H:i'); ?></div>
        </div>
        
        <div class="company-info">
            <h2>Informazioni Azienda</h2>
            <div class="info-grid">
                <?php if ($azienda['ragione_sociale']): ?>
                <div class="info-row">
                    <div class="info-label">Ragione Sociale:</div>
                    <div class="info-value"><?php echo htmlspecialchars($azienda['ragione_sociale']); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if ($azienda['partita_iva']): ?>
                <div class="info-row">
                    <div class="info-label">Partita IVA:</div>
                    <div class="info-value"><?php echo htmlspecialchars($azienda['partita_iva']); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if ($azienda['codice_fiscale']): ?>
                <div class="info-row">
                    <div class="info-label">Codice Fiscale:</div>
                    <div class="info-value"><?php echo htmlspecialchars($azienda['codice_fiscale']); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if ($azienda['indirizzo'] || $azienda['citta']): ?>
                <div class="info-row">
                    <div class="info-label">Indirizzo:</div>
                    <div class="info-value">
                        <?php 
                        $indirizzo_parts = [];
                        if ($azienda['indirizzo']) $indirizzo_parts[] = $azienda['indirizzo'];
                        if ($azienda['cap']) $indirizzo_parts[] = $azienda['cap'];
                        if ($azienda['citta']) $indirizzo_parts[] = $azienda['citta'];
                        if ($azienda['provincia']) $indirizzo_parts[] = '(' . $azienda['provincia'] . ')';
                        echo htmlspecialchars(implode(' ', $indirizzo_parts));
                        ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($azienda['telefono']): ?>
                <div class="info-row">
                    <div class="info-label">Telefono:</div>
                    <div class="info-value"><?php echo htmlspecialchars($azienda['telefono']); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if ($azienda['email']): ?>
                <div class="info-row">
                    <div class="info-label">Email:</div>
                    <div class="info-value"><?php echo htmlspecialchars($azienda['email']); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="statistics">
            <h2 style="margin-top: 0;">Riepilogo Documenti</h2>
            <div class="stat-value"><?php echo count($documenti); ?></div>
            <div>Documenti Totali</div>
        </div>
        
        <div class="documents-section">
            <h2>Documenti</h2>
            
            <?php if (empty($documenti)): ?>
                <p>Nessun documento presente nel fascicolo.</p>
            <?php else: ?>
                <?php foreach ($documenti as $index => $doc): ?>
                <div class="document">
                    <h3><?php echo ($index + 1) . '. ' . htmlspecialchars($doc['titolo']); ?></h3>
                    
                    <div class="document-meta">
                        <strong>Codice:</strong> <?php echo htmlspecialchars($doc['codice']); ?> |
                        <strong>Tipo:</strong> <?php echo htmlspecialchars($doc['modulo_nome'] ?? 'N/D'); ?> |
                        <strong>Classificazione:</strong> <?php echo htmlspecialchars($doc['classificazione_nome'] ?? 'N/D'); ?> |
                        <strong>Data:</strong> <?php echo date('d/m/Y', strtotime($doc['data_creazione'])); ?>
                        
                        <?php if ($doc['assegnato_nome']): ?>
                        <br><strong>Assegnato a:</strong> <?php echo htmlspecialchars($doc['assegnato_nome'] . ' ' . $doc['assegnato_cognome']); ?>
                        <?php endif; ?>
                        
                        <?php if ($doc['numero_protocollo']): ?>
                        <br><strong>Protocollo:</strong> <?php echo htmlspecialchars($doc['numero_protocollo']); ?>
                        del <?php echo date('d/m/Y', strtotime($doc['data_protocollo'])); ?>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($doc['descrizione']): ?>
                    <div class="document-content">
                        <strong>Descrizione:</strong><br>
                        <?php echo nl2br(htmlspecialchars($doc['descrizione'])); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($doc['contenuto']): ?>
                    <div class="document-content">
                        <?php echo $doc['contenuto']; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($index < count($documenti) - 1): ?>
                <div class="page-break"></div>
                <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="footer">
            Fascicolo documentale di <?php echo htmlspecialchars($azienda['nome']); ?> - 
            Generato da <?php echo htmlspecialchars($user['nome'] . ' ' . $user['cognome']); ?> - 
            <?php echo date('d/m/Y H:i'); ?>
        </div>
        
        <script>
            // Auto-print on load if requested
            <?php if (isset($_GET['autoprint'])): ?>
            window.onload = function() {
                window.print();
            }
            <?php endif; ?>
        </script>
    </body>
    </html>
    <?php
    exit;
}

// Se non è download, mostra anteprima
$pageTitle = 'Esporta Fascicolo Documentale';
require_once 'components/header.php';
?>

<style>
.export-container {
    max-width: 1200px;
    margin: 0 auto;
}

.export-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.export-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    padding: 25px;
    text-align: center;
    transition: all 0.3s;
}

.export-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.export-icon {
    font-size: 48px;
    color: #2d5a9f;
    margin-bottom: 15px;
}

.export-title {
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 10px;
    color: #333;
}

.export-description {
    color: #666;
    margin-bottom: 20px;
    font-size: 14px;
}

.documents-list {
    background: white;
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.documents-table {
    width: 100%;
    border-collapse: collapse;
}

.documents-table th {
    background: #f8f9fa;
    padding: 12px;
    text-align: left;
    font-weight: 600;
    color: #495057;
    border-bottom: 2px solid #dee2e6;
}

.documents-table td {
    padding: 12px;
    border-bottom: 1px solid #f0f0f0;
}

.documents-table tr:hover {
    background: #f8f9fa;
}

.doc-title {
    font-weight: 500;
    color: #2c3e50;
}

.doc-code {
    font-family: monospace;
    background: #e9ecef;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 12px;
}

.doc-type {
    display: inline-block;
    padding: 3px 8px;
    background: #e3f2fd;
    color: #1976d2;
    border-radius: 4px;
    font-size: 12px;
}

.doc-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.btn-small {
    padding: 5px 12px;
    font-size: 13px;
}

.info-banner {
    background: #e3f2fd;
    border: 1px solid #90caf9;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.info-banner i {
    color: #1976d2;
}
</style>

<div class="content-header">
    <h1><i class="fas fa-file-pdf"></i> Fascicolo Documentale</h1>
    <div class="header-actions">
        <a href="<?php echo $auth->isSuperAdmin() ? 'aziende.php?action=view&id=' . $azienda_id : 'dashboard.php'; ?>" 
           class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Indietro
        </a>
    </div>
</div>

<div class="export-container">
    <div class="info-banner">
        <i class="fas fa-info-circle"></i>
        <strong>Come esportare in PDF:</strong> Clicca su "Visualizza Fascicolo Completo" o su un singolo documento, 
        poi usa la funzione di stampa del browser (Ctrl+P) e seleziona "Salva come PDF" come stampante.
    </div>
    
    <h2 style="margin-bottom: 20px;">Fascicolo: <?php echo htmlspecialchars($azienda['nome']); ?></h2>
    
    <div class="export-options">
        <div class="export-card">
            <div class="export-icon">📑</div>
            <div class="export-title">Fascicolo Completo</div>
            <div class="export-description">
                Esporta tutti i documenti in un unico PDF con informazioni azienda e indice
            </div>
            <a href="esporta-fascicolo.php?azienda_id=<?php echo $azienda_id; ?>&action=print-all" 
               target="_blank" class="btn btn-primary">
                <i class="fas fa-file-pdf"></i> Visualizza Fascicolo Completo
            </a>
        </div>
        
        <div class="export-card">
            <div class="export-icon">📄</div>
            <div class="export-title">Documenti Singoli</div>
            <div class="export-description">
                Esporta singoli documenti selezionandoli dalla lista sottostante
            </div>
            <div style="color: #666; font-size: 14px;">
                <i class="fas fa-arrow-down"></i> Scorri per vedere la lista documenti
            </div>
        </div>
    </div>
    
    <div class="documents-list">
        <h3 style="margin-bottom: 20px;">
            <i class="fas fa-list"></i> Lista Documenti 
            <span style="font-size: 16px; color: #666;">(<?php echo count($documenti); ?> documenti)</span>
        </h3>
        
        <?php if (empty($documenti)): ?>
            <p style="text-align: center; color: #666; padding: 40px;">
                Nessun documento presente nel fascicolo.
            </p>
        <?php else: ?>
            <table class="documents-table">
                <thead>
                    <tr>
                        <th>Titolo</th>
                        <th>Codice</th>
                        <th>Tipo</th>
                        <th>Data</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documenti as $doc): ?>
                    <tr>
                        <td>
                            <div class="doc-title"><?php echo htmlspecialchars($doc['titolo']); ?></div>
                        </td>
                        <td>
                            <span class="doc-code"><?php echo htmlspecialchars($doc['codice']); ?></span>
                        </td>
                        <td>
                            <span class="doc-type">
                                <?php echo htmlspecialchars($doc['modulo_nome'] ?? 'Non specificato'); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo date('d/m/Y', strtotime($doc['data_creazione'])); ?>
                        </td>
                        <td>
                            <div class="doc-actions">
                                <a href="documento-view.php?id=<?php echo $doc['id']; ?>&from_fascicolo=1&azienda_id=<?php echo $azienda_id; ?>" 
                                   class="btn btn-secondary btn-small" target="_blank">
                                    <i class="fas fa-eye"></i> Visualizza
                                </a>
                                <a href="esporta-fascicolo.php?azienda_id=<?php echo $azienda_id; ?>&action=print-document&document_id=<?php echo $doc['id']; ?>" 
                                   class="btn btn-primary btn-small" target="_blank">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <div class="info-banner" style="margin-top: 30px;">
        <h4 style="margin-top: 0;"><i class="fas fa-lightbulb"></i> Suggerimenti per l'esportazione:</h4>
        <ul style="margin-bottom: 0;">
            <li>Per salvare come PDF: premi <strong>Ctrl+P</strong> (o <strong>Cmd+P</strong> su Mac)</li>
            <li>Nella finestra di stampa, seleziona <strong>"Salva come PDF"</strong> o <strong>"Microsoft Print to PDF"</strong></li>
            <li>Puoi personalizzare margini e orientamento nelle opzioni di stampa</li>
            <li>Il fascicolo completo inserisce un'interruzione di pagina tra ogni documento</li>
        </ul>
    </div>
</div>

<?php require_once 'components/footer.php'; ?> 