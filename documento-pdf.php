<?php
require_once 'backend/config/config.php';
require_once 'backend/utils/TemplateProcessor.php';

// Verifica autenticazione
$auth = Auth::getInstance();
$auth->requireAuth();

// Database instance handled by functions
$id = intval($_GET['id'] ?? 0);

if (!$id) {
    die('ID documento non specificato');
}

// Carica documento con tutti i dati necessari
$sql = "
    SELECT d.*, md.nome as modulo_nome, md.tipo as modulo_tipo,
           mt.header_content, mt.footer_content, mt.tipo_documento,
           a.nome as azienda_nome, a.indirizzo as azienda_indirizzo,
           a.telefono as azienda_telefono, a.email as azienda_email,
           a.partita_iva as azienda_piva, a.logo as azienda_logo,
           c.codice as classificazione_codice, c.descrizione as classificazione_desc
    FROM documenti d
    LEFT JOIN moduli_documento md ON d.modulo_id = md.id
    LEFT JOIN moduli_template mt ON d.modulo_id = mt.modulo_id
    LEFT JOIN aziende a ON d.azienda_id = a.id
    LEFT JOIN classificazione c ON d.classificazione_id = c.id
    WHERE d.id = ?
";

$stmt = $db->getConnection()->prepare($sql);
$stmt->execute([$id]);
$documento = $stmt->fetch();

if (!$documento) {
    die('Documento non trovato');
}

// Determina il tipo di documento
$tipoDocumento = 'documento';
if (!empty($documento['tipo_documento'])) {
    $tipoDocumento = $documento['tipo_documento'];
} elseif (!empty($documento['modulo_tipo'])) {
    switch($documento['modulo_tipo']) {
        case 'excel':
            $tipoDocumento = 'foglio';
            break;
        case 'form':
            $tipoDocumento = 'modulo';
            break;
    }
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

// Processa header e footer con il TemplateProcessor
$processedHeader = TemplateProcessor::generateDocumentHeader($documento['header_content'], $templateData);
$processedFooter = TemplateProcessor::generateDocumentFooter($documento['footer_content'], $templateData);

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($documento['titolo']); ?></title>
    <style>
        @page {
            size: A4;
            margin: 0;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: 'Times New Roman', serif;
        }
        
        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 0;
            position: relative;
            page-break-after: always;
            display: flex;
            flex-direction: column;
        }
        
        .page:last-child {
            page-break-after: auto;
        }
        
        .page-header {
            padding: 15mm 25mm 10mm 25mm;
            flex-shrink: 0;
            min-height: 30mm;
        }
        
        .page-content {
            flex: 1;
            padding: 0 25mm;
            font-size: 12pt;
            line-height: 1.8;
            overflow: hidden;
        }
        
        .page-footer {
            padding: 10mm 25mm 15mm 25mm;
            flex-shrink: 0;
            min-height: 25mm;
        }
        
        /* Stili per tabelle in header/footer */
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .page-header td, .page-footer td {
            padding: 8px;
            vertical-align: middle;
        }
        
        h1 { font-size: 24pt; margin: 20px 0 15px; page-break-after: avoid; }
        h2 { font-size: 20pt; margin: 18px 0 12px; page-break-after: avoid; }
        h3 { font-size: 16pt; margin: 16px 0 10px; page-break-after: avoid; }
        h4 { font-size: 14pt; margin: 14px 0 8px; page-break-after: avoid; }
        h5 { font-size: 12pt; margin: 12px 0 6px; page-break-after: avoid; }
        h6 { font-size: 11pt; margin: 10px 0 5px; page-break-after: avoid; }
        
        p { 
            margin: 0 0 12px; 
            text-align: justify;
            page-break-inside: avoid;
        }
        
        ul, ol {
            margin: 12px 0;
            padding-left: 30px;
            page-break-inside: avoid;
        }
        
        li {
            margin: 6px 0;
        }
        
        img {
            max-width: 100%;
            height: auto;
            page-break-inside: avoid;
        }
        
        /* Form styles */
        .form-field {
            margin-bottom: 15px;
            padding: 10px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .form-field label {
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
        }
        
        .form-field .value {
            padding-left: 20px;
        }
        
        /* Excel styles */
        .spreadsheet-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
            margin: 10px 0;
        }
        
        .spreadsheet-table td, .spreadsheet-table th {
            border: 1px solid #ccc;
            padding: 5px;
            text-align: left;
        }
        
        .spreadsheet-table th {
            background: #f0f0f0;
            font-weight: bold;
        }
        
        @media print {
            body {
                margin: 0;
            }
            
            .page {
                margin: 0;
                page-break-after: always;
                min-height: 297mm;
                height: 297mm;
            }
            
            .page:last-child {
                page-break-after: auto;
            }
            
            /* Disabilita i float per evitare problemi */
            * {
                float: none !important;
            }
        }
    </style>
</head>
<body>
    <?php 
    $contenuto = $documento['contenuto'] ?? '';
    
    if ($tipoDocumento === 'documento'): 
        // Per documenti Word, dividi il contenuto in pagine
        $pages = [];
        
        // Se il contenuto è presente, dividilo rispettando i page break
        if (!empty($contenuto)) {
            // Prima cerca tutti gli <hr> che rappresentano interruzioni di pagina nell'editor
            $parts = preg_split('/<hr[^>]*>/i', $contenuto);
            
            // Se ci sono interruzioni di pagina esplicite, usale
            if (count($parts) > 1) {
                foreach ($parts as $part) {
                    if (trim($part) !== '') {
                        $pages[] = $part;
                    }
                }
            } else {
                // Altrimenti cerca elementi con page-break-after
                // Prima prova a dividere per elementi con stile page-break
                $tempPages = [];
                
                // Cerca elementi con page-break-after: always
                if (preg_match_all('/<[^>]+style=["\'][^"\']*page-break-after:\s*always[^"\']*["\'][^>]*>/i', $contenuto, $matches, PREG_OFFSET_CAPTURE)) {
                    $lastPos = 0;
                    foreach ($matches[0] as $match) {
                        $matchPos = $match[1] + strlen($match[0]);
                        // Trova la fine del tag
                        $endTag = strpos($contenuto, '>', $matchPos);
                        if ($endTag !== false) {
                            // Trova il tag di chiusura corrispondente
                            preg_match('/<(\w+)/', $match[0], $tagMatch);
                            if (isset($tagMatch[1])) {
                                $closeTag = strpos($contenuto, '</' . $tagMatch[1] . '>', $endTag);
                                if ($closeTag !== false) {
                                    $endPos = $closeTag + strlen('</' . $tagMatch[1] . '>');
                                    $tempPages[] = substr($contenuto, $lastPos, $endPos - $lastPos);
                                    $lastPos = $endPos;
                                }
                            }
                        }
                    }
                    // Aggiungi il resto del contenuto
                    if ($lastPos < strlen($contenuto)) {
                        $tempPages[] = substr($contenuto, $lastPos);
                    }
                    
                    if (!empty($tempPages)) {
                        $pages = $tempPages;
                    }
                }
                
                // Se non ci sono page break espliciti, usa la divisione automatica
                if (empty($pages)) {
                    // Non dividere automaticamente, usa tutto il contenuto come una pagina
                    $pages[] = $contenuto;
                    

                }
            }
        } else {
            // Se non c'è contenuto, crea almeno una pagina
            $pages[] = '<p>&nbsp;</p>';
        }
        
        $totalPages = count($pages);
        
        // Renderizza le pagine
        foreach ($pages as $index => $pageContent):
            $currentPage = $index + 1;
    ?>
        <div class="page">
            <div class="page-header">
                <?php echo $processedHeader; ?>
            </div>
            
            <div class="page-content">
                <?php echo $pageContent; ?>
            </div>
            
            <div class="page-footer">
                <?php 
                // Sostituisci i placeholder dei numeri di pagina
                $footerHtml = $processedFooter;
                
                // Sostituisci gli span generati dal TemplateProcessor
                $footerHtml = preg_replace('/<span class="page-number"><\/span>/', $currentPage, $footerHtml);
                $footerHtml = preg_replace('/<span class="total-pages"><\/span>/', $totalPages, $footerHtml);
                
                // Sostituisci anche eventuali placeholder testuali rimasti
                $footerHtml = str_replace('{numero_pagina}', $currentPage, $footerHtml);
                $footerHtml = str_replace('{totale_pagine}', $totalPages, $footerHtml);
                $footerHtml = str_replace('{{numero_pagina}}', $currentPage, $footerHtml);
                $footerHtml = str_replace('{{totale_pagine}}', $totalPages, $footerHtml);
                
                echo $footerHtml;
                ?>
            </div>
        </div>
    <?php 
        endforeach;
        
    elseif ($tipoDocumento === 'modulo'):
        // Per form, renderizza i campi del form
    ?>
        <div class="page">
            <div class="page-header">
                <?php echo $processedHeader; ?>
            </div>
            
            <div class="page-content">
                <h1><?php echo htmlspecialchars($documento['titolo']); ?></h1>
                
                <?php
                // Decodifica i dati del form
                $formData = json_decode($contenuto, true);
                
                if (is_array($formData)):
                    foreach ($formData as $field):
                        if (isset($field['type']) && isset($field['label'])):
                ?>
                    <div class="form-field">
                        <label><?php echo htmlspecialchars($field['label']); ?></label>
                        <div class="value">
                            <?php 
                            switch($field['type']) {
                                case 'text':
                                case 'number':
                                case 'date':
                                case 'email':
                                    echo '________________________________';
                                    break;
                                case 'textarea':
                                    echo '<div style="border: 1px solid #ccc; min-height: 60px; margin-top: 5px;"></div>';
                                    break;
                                case 'select':
                                case 'radio-group':
                                    if (isset($field['values']) && is_array($field['values'])) {
                                        foreach ($field['values'] as $option) {
                                            echo '☐ ' . htmlspecialchars($option['label'] ?? $option['value'] ?? '') . '<br>';
                                        }
                                    }
                                    break;
                                case 'checkbox-group':
                                    if (isset($field['values']) && is_array($field['values'])) {
                                        foreach ($field['values'] as $option) {
                                            echo '☐ ' . htmlspecialchars($option['label'] ?? $option['value'] ?? '') . '<br>';
                                        }
                                    }
                                    break;
                                case 'checkbox':
                                    echo '☐ ' . htmlspecialchars($field['label']);
                                    break;
                                case 'header':
                                    echo '<h3>' . htmlspecialchars($field['label']) . '</h3>';
                                    break;
                                case 'paragraph':
                                    echo '<p>' . htmlspecialchars($field['label']) . '</p>';
                                    break;
                            }
                            ?>
                        </div>
                    </div>
                <?php
                        endif;
                    endforeach;
                endif;
                ?>
            </div>
            
            <div class="page-footer">
                <?php 
                $footerHtml = $processedFooter;
                $footerHtml = preg_replace('/<span class="page-number"><\/span>/', '1', $footerHtml);
                $footerHtml = preg_replace('/<span class="total-pages"><\/span>/', '1', $footerHtml);
                $footerHtml = str_replace(['{numero_pagina}', '{totale_pagine}'], ['1', '1'], $footerHtml);
                echo $footerHtml;
                ?>
            </div>
        </div>
    <?php
    elseif ($tipoDocumento === 'foglio'):
        // Per Excel, prova a renderizzare una tabella
    ?>
        <div class="page">
            <div class="page-header">
                <?php echo $processedHeader; ?>
            </div>
            
            <div class="page-content">
                <h1><?php echo htmlspecialchars($documento['titolo']); ?></h1>
                
                <?php
                // Decodifica i dati Excel
                $excelData = json_decode($contenuto, true);
                
                if (is_array($excelData) && !empty($excelData)):
                    // Prendi il primo foglio
                    $sheet = $excelData[0] ?? [];
                    $rows = $sheet['rows'] ?? [];
                    
                    if (!empty($rows)):
                ?>
                    <table class="spreadsheet-table">
                        <?php
                        // Trova il numero massimo di colonne
                        $maxCols = 0;
                        foreach ($rows as $row) {
                            if (isset($row['cells']) && is_array($row['cells'])) {
                                $maxCols = max($maxCols, max(array_keys($row['cells'])) + 1);
                            }
                        }
                        
                        // Renderizza le righe
                        for ($i = 0; $i < min(50, count($rows)); $i++):
                            if (isset($rows[$i]) && isset($rows[$i]['cells'])):
                        ?>
                            <tr>
                                <?php for ($j = 0; $j < $maxCols; $j++): ?>
                                    <td>
                                        <?php 
                                        $cell = $rows[$i]['cells'][$j] ?? null;
                                        if ($cell && isset($cell['text'])) {
                                            echo htmlspecialchars($cell['text']);
                                        }
                                        ?>
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        <?php 
                            endif;
                        endfor; 
                        ?>
                    </table>
                <?php
                    else:
                        echo '<p>Foglio di calcolo vuoto</p>';
                    endif;
                else:
                    echo '<p>Impossibile leggere i dati del foglio di calcolo</p>';
                endif;
                ?>
            </div>
            
            <div class="page-footer">
                <?php 
                $footerHtml = $processedFooter;
                $footerHtml = preg_replace('/<span class="page-number"><\/span>/', '1', $footerHtml);
                $footerHtml = preg_replace('/<span class="total-pages"><\/span>/', '1', $footerHtml);
                $footerHtml = str_replace(['{numero_pagina}', '{totale_pagine}'], ['1', '1'], $footerHtml);
                echo $footerHtml;
                ?>
            </div>
        </div>
    <?php endif; ?>
    
    <script>
        // Auto-print quando la pagina è caricata
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html> 