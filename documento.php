<?php
require_once 'backend/config/config.php';
require_once 'backend/utils/ActivityLogger.php';
require_once 'backend/utils/NotificationManager.php';
require_once 'backend/utils/TemplateProcessor.php';

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

$action = $_GET['action'] ?? 'list';
$id = intval($_GET['id'] ?? 0);

// Per azioni di modifica/creazione, reindirizza alla nuova pagina
if ($action == 'nuovo' || $action == 'edit') {
    $params = [];
    if ($action == 'edit' && $id) $params[] = 'id=' . $id;
    if (isset($_GET['template_id'])) $params[] = 'template_id=' . $_GET['template_id'];
    if (isset($_GET['azienda_id'])) $params[] = 'azienda_id=' . $_GET['azienda_id'];
    
    $queryString = !empty($params) ? '?' . implode('&', $params) : '';
    header('Location: editor-onlyoffice-integrated.php' . $queryString);
    exit();
}

// Solo visualizzazione
if ($action != 'view' || !$id) {
    redirect('documenti.php');
}

// Carica dettagli documento per visualizzazione
$sql = "
    SELECT d.*, md.nome as modulo_nome, md.tipo as modulo_tipo,
           u.nome as nome_autore, u.cognome as cognome_autore,
           mt.header_content, mt.footer_content,
           a.nome as azienda_nome, a.indirizzo as azienda_indirizzo,
           a.telefono as azienda_telefono, a.email as azienda_email,
           a.partita_iva as azienda_piva, a.logo as azienda_logo,
           c.codice as classificazione_codice, c.descrizione as classificazione_desc
    FROM documenti d
    LEFT JOIN moduli_documento md ON d.modulo_id = md.id
    LEFT JOIN moduli_template mt ON d.modulo_id = mt.modulo_id
    LEFT JOIN utenti u ON d.creato_da = u.id
    LEFT JOIN aziende a ON d.azienda_id = a.id
    LEFT JOIN classificazione c ON d.classificazione_id = c.id
    WHERE d.id = ?
";
$params = [$id];

// Se ha un'azienda corrente, filtra per azienda (tranne super admin)
if ($currentAzienda && !$auth->isSuperAdmin()) {
    $sql .= " AND d.azienda_id = ?";
    $params[] = $currentAzienda['azienda_id'];
}

$stmt = $db->getConnection()->prepare($sql);
$stmt->execute($params);
$documento = $stmt->fetch();

if (!$documento) {
    redirect('documenti.php');
}

// Prepara i dati per il template processor
$templateData = [
    'azienda' => [
        'nome' => $documento['azienda_nome'],
        'indirizzo' => $documento['azienda_indirizzo'],
        'telefono' => $documento['azienda_telefono'],
        'email' => $documento['azienda_email'],
        'partita_iva' => $documento['azienda_piva'],
        'logo' => $documento['azienda_logo']
    ],
    'documento' => [
        'titolo' => $documento['titolo'],
        'codice' => $documento['codice'],
        'versione_corrente' => $documento['versione_corrente'],
        'data_creazione' => $documento['data_creazione']
    ],
    'classificazione' => [
        'codice' => $documento['classificazione_codice'],
        'descrizione' => $documento['classificazione_desc']
    ]
];

// Processa header e footer con i placeholder
$processedHeader = TemplateProcessor::generateDocumentHeader($documento['header_content'], $templateData);
$processedFooter = TemplateProcessor::generateDocumentFooter($documento['footer_content'], $templateData);

// Decodifica template_data
$template_data = json_decode($documento['template_data'] ?? '{}', true);

$pageTitle = 'Visualizza Documento';
require_once 'components/header.php';
?>

<style>
    /* Pagina A4 */
    .a4-page {
        width: 794px; /* A4 width in pixels at 96dpi */
        min-height: 1123px; /* A4 height in pixels at 96dpi */
        margin: 20px auto;
        background: white;
        box-shadow: 0 0 20px rgba(0,0,0,0.1);
        position: relative;
        page-break-after: always;
        display: flex;
        flex-direction: column;
    }
    
    .page-header {
        flex-shrink: 0;
        padding: 20px 60px;
        border-bottom: 2px solid #333;
    }
    
    .page-footer {
        flex-shrink: 0;
        padding: 20px 60px;
        border-top: 2px solid #333;
        margin-top: auto;
    }
    
    .page-content {
        flex: 1;
        padding: 60px;
        overflow: hidden;
        font-family: 'Times New Roman', serif;
        font-size: 12pt;
        line-height: 1.8;
    }
    
    /* Stili per header/footer processati */
    .page-header table, .page-footer table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .page-header td, .page-footer td {
        padding: 8px;
        vertical-align: middle;
    }
    
    .page-number {
        text-align: center;
        font-size: 11pt;
    }
    
    .document-view {
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        overflow: hidden;
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .document-header-info {
        background: var(--bg-secondary);
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-color);
    }
    
    .document-meta-row {
        display: flex;
        gap: 2rem;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }
    
    .meta-item {
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }
    
    .meta-label {
        font-weight: 600;
        color: var(--text-secondary);
        font-size: 0.875rem;
    }
    
    .meta-value {
        color: var(--text-primary);
    }
    
    .document-content-view {
        padding: 3rem;
        max-width: 800px;
        margin: 0 auto;
        line-height: 1.8;
        font-family: 'Times New Roman', serif;
    }
    
    .document-content-view h1 {
        font-size: 2rem;
        margin-bottom: 2rem;
        color: var(--text-primary);
    }
    
    .document-body {
        font-size: 1rem;
        color: #333;
    }
    
    .document-body h1 { font-size: 1.8rem; margin: 1.5rem 0 1rem; }
    .document-body h2 { font-size: 1.5rem; margin: 1.3rem 0 0.8rem; }
    .document-body h3 { font-size: 1.3rem; margin: 1.2rem 0 0.7rem; }
    .document-body h4 { font-size: 1.1rem; margin: 1rem 0 0.6rem; }
    .document-body h5 { font-size: 1rem; margin: 0.8rem 0 0.5rem; }
    .document-body h6 { font-size: 0.9rem; margin: 0.7rem 0 0.4rem; }
    
    .document-body p {
        margin: 0 0 1rem;
        text-align: justify;
    }
    
    .document-body ul, .document-body ol {
        margin: 0 0 1rem 2rem;
    }
    
    .document-body blockquote {
        margin: 1rem 0;
        padding: 0.5rem 1rem;
        border-left: 4px solid var(--primary-color);
        background: var(--bg-secondary);
    }
    
    .status-badge {
        display: inline-block;
        padding: 0.375rem 1rem;
        border-radius: 20px;
        font-size: 0.813rem;
        font-weight: 500;
    }
    
    .status-bozza {
        background: #fef3c7;
        color: #92400e;
    }
    
    .status-pubblicato {
        background: #d1fae5;
        color: #065f46;
    }
    
    .status-archiviato {
        background: #e5e7eb;
        color: #374151;
    }
    
    .document-actions {
        display: flex;
        gap: 1rem;
        margin-bottom: 2rem;
    }
    
    @media print {
        .content-header,
        .document-header-info,
        .document-actions {
            display: none !important;
        }
        
        .document-view {
            box-shadow: none;
            border: none;
        }
        
        .a4-page {
            margin: 0;
            box-shadow: none;
            page-break-after: always;
        }
        
        @page {
            size: A4;
            margin: 0;
        }
    }
</style>

<div class="content-header">
    <h1><i class="fas fa-file-alt"></i> <?php echo $pageTitle; ?></h1>
    <div class="header-actions">
        <a href="documenti.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Torna ai documenti
        </a>
    </div>
</div>

<div class="document-view">
    <div class="document-header-info">
        <div class="document-meta-row">
            <div class="meta-item">
                <span class="meta-label">Template:</span>
                <span class="meta-value"><?php echo htmlspecialchars($documento['modulo_nome'] ?? 'Modulo non trovato'); ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Azienda:</span>
                <span class="meta-value"><?php echo htmlspecialchars($documento['azienda_nome']); ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Codice:</span>
                <span class="meta-value"><?php echo htmlspecialchars($documento['codice']); ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Versione:</span>
                <span class="meta-value"><?php echo htmlspecialchars($documento['versione_corrente'] ?? '1'); ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Stato:</span>
                <span class="status-badge status-<?php echo $documento['stato']; ?>">
                    <?php echo ucfirst($documento['stato']); ?>
                </span>
            </div>
        </div>
        
        <div class="document-meta-row">
            <div class="meta-item">
                <span class="meta-label">Creato da:</span>
                <span class="meta-value"><?php echo htmlspecialchars($documento['nome_autore'] . ' ' . $documento['cognome_autore']); ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Data creazione:</span>
                <span class="meta-value"><?php echo format_date($documento['data_creazione']); ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Ultima modifica:</span>
                <span class="meta-value"><?php echo format_datetime($documento['ultima_modifica']); ?></span>
            </div>
        </div>
    </div>
    
    <div class="document-actions" style="padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color);">
        <?php if ($auth->canAccess('documents', 'write')): ?>
                                <a href="editor-onlyoffice-integrated.php?id=<?php echo $documento['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Modifica con OnlyOffice
                        </a>
        <?php endif; ?>
        
        <?php if ($auth->canAccess('documents', 'export')): ?>
        <a href="documento-pdf.php?id=<?php echo $documento['id']; ?>" class="btn btn-secondary" target="_blank">
            <i class="fas fa-file-pdf"></i> Esporta PDF
        </a>
        <?php endif; ?>
        
        <button onclick="window.print()" class="btn btn-secondary">
            <i class="fas fa-print"></i> Stampa
        </button>
    </div>
    
    <div id="document-pages">
        <?php 
        // Prepara il contenuto
        $contenuto = $template_data['contenuto'] ?? $documento['contenuto'];
        
        // Divide il contenuto in pagine (semplificato per ora)
        // In un'implementazione reale, dovremmo calcolare l'altezza del contenuto
        $contenutoArray = explode('<div style="page-break-after:always"></div>', $contenuto);
        $totalPages = count($contenutoArray);
        $currentPage = 1;
        
        foreach ($contenutoArray as $pageContent): 
        ?>
        <div class="a4-page">
            <div class="page-header">
                <?php echo $processedHeader; ?>
            </div>
            
            <div class="page-content">
        <h1><?php echo htmlspecialchars($documento['titolo']); ?></h1>
        
                <?php if ($currentPage == 1): ?>
        <?php if (!empty($template_data['data'])): ?>
        <p><strong>Data:</strong> <?php echo format_date($template_data['data']); ?></p>
        <?php endif; ?>
        
        <?php if (!empty($template_data['destinatario'])): ?>
        <p><strong>Destinatario:</strong> <?php echo htmlspecialchars($template_data['destinatario']); ?></p>
        <?php endif; ?>
        
        <?php if (!empty($template_data['oggetto'])): ?>
        <p><strong>Oggetto:</strong> <?php echo htmlspecialchars($template_data['oggetto']); ?></p>
                    <?php endif; ?>
        <?php endif; ?>
        
        <div class="document-body">
                    <?php echo $pageContent; ?>
                </div>
            </div>
            
            <div class="page-footer">
                <?php 
                // Sostituisci il placeholder del numero di pagina
                $footerWithPageNumber = str_replace(
                    ['{{pagina_corrente}}', '{{totale_pagine}}'],
                    [$currentPage, $totalPages],
                    $processedFooter
                );
                echo $footerWithPageNumber;
                ?>
            </div>
        </div>
        <?php 
        $currentPage++;
        endforeach; 
        ?>
    </div>
</div>

<script>
// Script per dividere automaticamente il contenuto in pagine se troppo lungo
document.addEventListener('DOMContentLoaded', function() {
    // Questa è una versione semplificata
    // In produzione, servirebbe un algoritmo più sofisticato per dividere il contenuto
});
</script>

<?php require_once 'components/footer.php'; ?> 