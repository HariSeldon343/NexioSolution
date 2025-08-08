<?php
/**
 * ISO Export API
 * 
 * API per l'esportazione della struttura documentale ISO
 * 
 * @package Nexio\API
 * @version 1.0.0
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../middleware/Auth.php';
require_once __DIR__ . '/../utils/ActivityLogger.php';


// Autenticazione
$auth = Auth::getInstance();
if (!$auth->checkSession()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Non autenticato']);
    exit;
}

$user = $auth->getUser();
$userId = $user['id'];
$isSuperAdmin = $auth->isSuperAdmin();
$isUtenteSpeciale = $auth->isUtenteSpeciale();
$logger = ActivityLogger::getInstance();

// Solo admin possono esportare
if (!$isSuperAdmin && !$isUtenteSpeciale) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Permessi insufficienti']);
    exit;
}

// Parametri
$action = $_GET['action'] ?? 'export';
$companyId = isset($_GET['company_id']) ? intval($_GET['company_id']) : null;
$format = $_GET['format'] ?? 'excel'; // excel, csv, json, pdf
$includeDocuments = isset($_GET['include_documents']) ? filter_var($_GET['include_documents'], FILTER_VALIDATE_BOOLEAN) : false;

// Validazione azienda
if (!$companyId) {
    $currentCompany = $auth->getCurrentAzienda();
    $companyId = $currentCompany['azienda_id'] ?? null;
}

if (!$companyId) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'ID azienda mancante']);
    exit;
}

// Verifica accesso
if (!$isSuperAdmin && $companyId != $auth->getCurrentAzienda()['azienda_id']) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Accesso negato']);
    exit;
}

try {
    switch ($format) {
        case 'excel':
            exportToExcel($companyId, $includeDocuments);
            break;
            
        case 'csv':
            exportToCSV($companyId, $includeDocuments);
            break;
            
        case 'json':
            exportToJSON($companyId, $includeDocuments);
            break;
            
        case 'pdf':
            exportToPDF($companyId, $includeDocuments);
            break;
            
        default:
            throw new Exception('Formato di esportazione non supportato');
    }
    
    // Log attività
    $logger->log('export_struttura_iso', 'export', null, [
        'azienda_id' => $companyId,
        'formato' => $format,
        'include_documenti' => $includeDocuments
    ]);
    
} catch (Exception $e) {
    error_log("Export Error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Esporta in formato Excel
 */
function exportToExcel($companyId, $includeDocuments) {
    require_once BASE_PATH . '/vendor/autoload.php';
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Intestazioni
    $headers = ['ID', 'Percorso', 'Nome', 'Standard ISO', 'Livello', 'Documenti', 'Dimensione Totale'];
    if ($includeDocuments) {
        $headers = array_merge($headers, ['Codice Doc', 'Titolo Doc', 'Tipo Doc', 'Data Creazione']);
    }
    
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '1', $header);
        $sheet->getStyle($col . '1')->getFont()->setBold(true);
        $col++;
    }
    
    // Dati cartelle
    $data = getFolderStructure($companyId, $includeDocuments);
    $row = 2;
    
    foreach ($data as $item) {
        $sheet->setCellValue('A' . $row, $item['id']);
        $sheet->setCellValue('B' . $row, $item['percorso_completo']);
        $sheet->setCellValue('C' . $row, $item['nome']);
        $sheet->setCellValue('D' . $row, $item['iso_standard_codice'] ?? '');
        $sheet->setCellValue('E' . $row, $item['level']);
        $sheet->setCellValue('F' . $row, $item['document_count']);
        $sheet->setCellValue('G' . $row, formatBytes($item['total_size']));
        
        if ($includeDocuments && !empty($item['documents'])) {
            foreach ($item['documents'] as $doc) {
                $sheet->setCellValue('H' . $row, $doc['codice']);
                $sheet->setCellValue('I' . $row, $doc['titolo']);
                $sheet->setCellValue('J' . $row, $doc['tipo_documento']);
                $sheet->setCellValue('K' . $row, $doc['data_creazione']);
                $row++;
            }
        } else {
            $row++;
        }
    }
    
    // Auto-resize colonne
    foreach (range('A', $sheet->getHighestColumn()) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Output
    $filename = 'struttura_iso_' . date('Y-m-d') . '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

/**
 * Esporta in formato CSV
 */
function exportToCSV($companyId, $includeDocuments) {
    $filename = 'struttura_iso_' . date('Y-m-d') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // BOM per Excel
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // Intestazioni
    $headers = ['ID', 'Percorso', 'Nome', 'Standard ISO', 'Livello', 'Documenti', 'Dimensione Totale'];
    if ($includeDocuments) {
        $headers = array_merge($headers, ['Codice Doc', 'Titolo Doc', 'Tipo Doc', 'Data Creazione']);
    }
    fputcsv($output, $headers, ';');
    
    // Dati
    $data = getFolderStructure($companyId, $includeDocuments);
    
    foreach ($data as $item) {
        $row = [
            $item['id'],
            $item['percorso_completo'],
            $item['nome'],
            $item['iso_standard_codice'] ?? '',
            $item['level'],
            $item['document_count'],
            formatBytes($item['total_size'])
        ];
        
        if ($includeDocuments && !empty($item['documents'])) {
            foreach ($item['documents'] as $doc) {
                $docRow = array_merge($row, [
                    $doc['codice'],
                    $doc['titolo'],
                    $doc['tipo_documento'],
                    $doc['data_creazione']
                ]);
                fputcsv($output, $docRow, ';');
            }
        } else {
            fputcsv($output, $row, ';');
        }
    }
    
    fclose($output);
    exit;
}

/**
 * Esporta in formato JSON
 */
function exportToJSON($companyId, $includeDocuments) {
    $data = [
        'export_date' => date('Y-m-d H:i:s'),
        'company_id' => $companyId,
        'structure' => getFolderStructure($companyId, $includeDocuments, true)
    ];
    
    // Aggiungi informazioni azienda
    $company = db_query("SELECT * FROM aziende WHERE id = ?", [$companyId])->fetch(PDO::FETCH_ASSOC);
    if ($company) {
        $data['company'] = [
            'id' => $company['id'],
            'nome' => $company['nome'],
            'codice' => $company['codice']
        ];
    }
    
    // Aggiungi configurazione ISO
    $isoConfig = db_query("
        SELECT * FROM aziende_iso_config 
        WHERE azienda_id = ?
    ", [$companyId])->fetch(PDO::FETCH_ASSOC);
    
    if ($isoConfig) {
        $data['iso_configuration'] = [
            'structure_type' => $isoConfig['tipo_struttura'],
            'active_standards' => json_decode($isoConfig['standards_attivi'], true),
            'activation_date' => $isoConfig['data_attivazione']
        ];
    }
    
    $filename = 'struttura_iso_' . date('Y-m-d') . '.json';
    
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Esporta in formato PDF
 */
function exportToPDF($companyId, $includeDocuments) {
    require_once BASE_PATH . '/vendor/autoload.php';
    
    // Ottieni dati
    $company = db_query("SELECT * FROM aziende WHERE id = ?", [$companyId])->fetch(PDO::FETCH_ASSOC);
    $structure = getFolderStructure($companyId, $includeDocuments, true);
    
    // Crea HTML
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; font-size: 10pt; }
            h1, h2, h3 { color: #333; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .level-1 { padding-left: 20px; }
            .level-2 { padding-left: 40px; }
            .level-3 { padding-left: 60px; }
            .level-4 { padding-left: 80px; }
            .footer { text-align: center; font-size: 8pt; color: #666; margin-top: 30px; }
            .badge { 
                display: inline-block; 
                padding: 2px 8px; 
                border-radius: 3px; 
                font-size: 8pt; 
                margin-left: 5px;
            }
            .badge-iso9001 { background-color: #3b82f6; color: white; }
            .badge-iso14001 { background-color: #10b981; color: white; }
            .badge-iso45001 { background-color: #ef4444; color: white; }
            .badge-gdpr { background-color: #f59e0b; color: white; }
        </style>
    </head>
    <body>
        <h1>Struttura Documentale ISO</h1>
        <p><strong>Azienda:</strong> ' . htmlspecialchars($company['nome']) . '</p>
        <p><strong>Data esportazione:</strong> ' . date('d/m/Y H:i') . '</p>
        
        <h2>Struttura delle Cartelle</h2>
        <table>
            <thead>
                <tr>
                    <th>Cartella</th>
                    <th>Standard ISO</th>
                    <th>Documenti</th>
                    <th>Dimensione</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($structure as $folder) {
        $indent = 'level-' . $folder['level'];
        $badge = '';
        
        if ($folder['iso_standard_codice']) {
            $badgeClass = 'badge-' . strtolower($folder['iso_standard_codice']);
            $badge = '<span class="badge ' . $badgeClass . '">' . $folder['iso_standard_codice'] . '</span>';
        }
        
        $html .= '
                <tr>
                    <td class="' . $indent . '">' . htmlspecialchars($folder['nome']) . '</td>
                    <td>' . $badge . '</td>
                    <td>' . $folder['document_count'] . '</td>
                    <td>' . formatBytes($folder['total_size']) . '</td>
                </tr>';
    }
    
    $html .= '
            </tbody>
        </table>';
    
    if ($includeDocuments) {
        $html .= '
        <h2>Documenti</h2>
        <table>
            <thead>
                <tr>
                    <th>Codice</th>
                    <th>Titolo</th>
                    <th>Cartella</th>
                    <th>Tipo</th>
                    <th>Data</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($structure as $folder) {
            if (!empty($folder['documents'])) {
                foreach ($folder['documents'] as $doc) {
                    $html .= '
                <tr>
                    <td>' . htmlspecialchars($doc['codice']) . '</td>
                    <td>' . htmlspecialchars($doc['titolo']) . '</td>
                    <td>' . htmlspecialchars($folder['nome']) . '</td>
                    <td>' . htmlspecialchars($doc['tipo_documento']) . '</td>
                    <td>' . date('d/m/Y', strtotime($doc['data_creazione'])) . '</td>
                </tr>';
                }
            }
        }
        
        $html .= '
            </tbody>
        </table>';
    }
    
    $html .= '
        <div class="footer">
            <p>Documento generato automaticamente dal sistema Nexio ISO Management</p>
        </div>
    </body>
    </html>';
    
    // Genera PDF
    $dompdf = new \Dompdf\Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    $filename = 'struttura_iso_' . date('Y-m-d') . '.pdf';
    $dompdf->stream($filename, ['Attachment' => true]);
    exit;
}

/**
 * Ottieni struttura cartelle con documenti
 */
function getFolderStructure($companyId, $includeDocuments = false, $hierarchical = false) {
    $folders = [];
    
    // Query ricorsiva per ottenere tutte le cartelle
    $query = "
        WITH RECURSIVE folder_tree AS (
            SELECT 
                c.*,
                0 as level,
                CAST(c.nome AS CHAR(1000)) as path
            FROM cartelle c
            WHERE c.azienda_id = ? AND c.parent_id IS NULL
            
            UNION ALL
            
            SELECT 
                c.*,
                ft.level + 1,
                CONCAT(ft.path, ' / ', c.nome) as path
            FROM cartelle c
            INNER JOIN folder_tree ft ON c.parent_id = ft.id
            WHERE c.azienda_id = ?
        )
        SELECT 
            ft.*,
            COUNT(DISTINCT d.id) as document_count,
            COALESCE(SUM(d.dimensione_file), 0) as total_size
        FROM folder_tree ft
        LEFT JOIN documenti d ON ft.id = d.cartella_id
        GROUP BY ft.id
        ORDER BY ft.path
    ";
    
    $stmt = db_query($query, [$companyId, $companyId]);
    
    while ($folder = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($includeDocuments) {
            // Carica documenti della cartella
            $docStmt = db_query("
                SELECT id, codice, titolo, tipo_documento, data_creazione, dimensione_file
                FROM documenti
                WHERE cartella_id = ?
                ORDER BY codice
            ", [$folder['id']]);
            
            $folder['documents'] = $docStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        $folders[] = $folder;
    }
    
    if ($hierarchical) {
        return buildHierarchy($folders);
    }
    
    return $folders;
}

/**
 * Costruisci gerarchia cartelle
 */
function buildHierarchy($folders) {
    $hierarchy = [];
    $indexed = [];
    
    // Indicizza per ID
    foreach ($folders as $folder) {
        $indexed[$folder['id']] = $folder;
        $indexed[$folder['id']]['children'] = [];
    }
    
    // Costruisci albero
    foreach ($indexed as $id => $folder) {
        if ($folder['parent_id'] === null) {
            $hierarchy[] = &$indexed[$id];
        } else {
            $indexed[$folder['parent_id']]['children'][] = &$indexed[$id];
        }
    }
    
    return $hierarchy;
}

/**
 * Formatta dimensione in bytes
 */
function formatBytes($bytes, $precision = 2) {
    if ($bytes == 0) return '0 B';
    
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes) / log(1024));
    
    return round($bytes / pow(1024, $i), $precision) . ' ' . $units[$i];
}
?>