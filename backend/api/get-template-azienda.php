<?php
/**
 * API per recuperare il template associato all'azienda corrente
 * Utilizza la tabella moduli_template con header_content e footer_content
 */

require_once __DIR__ . '/../config/config.php';

// Funzione helper per risposta JSON
function json_response($data) {
    echo json_encode($data);
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $auth = Auth::getInstance();
    $auth->requireAuth();
    
    $user = $auth->getUser();
    $currentAzienda = $auth->getCurrentAzienda();
    
    // Se non c'è azienda corrente, prova a impostarla dalla sessione o dalla prima disponibile
    if (!$currentAzienda) {
        // Prova a caricare dalla sessione
        if (isset($_SESSION['azienda_corrente'])) {
            $auth->setCurrentAzienda($_SESSION['azienda_corrente']);
            $currentAzienda = $auth->getCurrentAzienda();
        }
        
        // Se ancora non c'è, prova la prima azienda disponibile per l'utente
        if (!$currentAzienda) {
            $userAziende = $auth->getUserAziende();
            if (!empty($userAziende)) {
                $auth->setCurrentAzienda($userAziende[0]['azienda_id']);
                $currentAzienda = $auth->getCurrentAzienda();
            }
        }
        
        // Se è super admin e non ha aziende, usa la prima azienda del sistema
        if (!$currentAzienda && $user['ruolo'] === 'super_admin') {
            $stmt = db_query("SELECT id FROM aziende WHERE stato = 'attiva' ORDER BY id ASC LIMIT 1");
            $firstAzienda = $stmt->fetch();
            if ($firstAzienda) {
                $auth->setCurrentAzienda($firstAzienda['id']);
                $currentAzienda = $auth->getCurrentAzienda();
            }
        }
        
        // Se ancora non c'è azienda, restituisci errore
        if (!$currentAzienda) {
            json_response([
                'success' => false,
                'error' => 'Nessuna azienda disponibile per l\'utente',
                'debug' => [
                    'user_role' => $user['ruolo'] ?? 'unknown',
                    'session_azienda' => $_SESSION['azienda_corrente'] ?? null,
                    'user_aziende_count' => count($auth->getUserAziende())
                ]
            ]);
            exit;
        }
    }
    
    $aziendaId = $currentAzienda['azienda_id'] ?? $currentAzienda['id'];
    $moduloId = $_GET['modulo_id'] ?? 1; // Default al primo modulo se non specificato
    
    // Recupera informazioni azienda complete
    $stmt = db_query("SELECT * FROM aziende WHERE id = ?", [$aziendaId]);
    $azienda = $stmt->fetch();
    
    if (!$azienda) {
        json_response([
            'success' => false,
            'error' => 'Azienda non trovata'
        ]);
        exit;
    }
    
    // Recupera il template dal sistema moduli_template
    $stmt = db_query("
        SELECT mt.*, md.nome as modulo_nome, md.icona, md.descrizione as modulo_descrizione
        FROM moduli_template mt
        INNER JOIN moduli_documento md ON mt.modulo_id = md.id
        LEFT JOIN azienda_moduli am ON (am.azienda_id = ? AND am.modulo_id = md.id)
        WHERE mt.attivo = 1 
        AND md.attivo = 1
        AND (am.attivo = 1 OR ? = 1)
        ORDER BY 
            CASE WHEN mt.modulo_id = ? THEN 0 ELSE 1 END,
            mt.created_at DESC
        LIMIT 1
    ", [$aziendaId, $user['ruolo'] === 'super_admin' ? 1 : 0, $moduloId]);
    
    $template = $stmt->fetch();
    
    if (!$template) {
        // Fallback: cerca qualsiasi template disponibile
        $stmt = db_query("
            SELECT mt.*, md.nome as modulo_nome, md.icona, md.descrizione as modulo_descrizione
            FROM moduli_template mt
            INNER JOIN moduli_documento md ON mt.modulo_id = md.id
            WHERE mt.attivo = 1 AND md.attivo = 1
            ORDER BY mt.created_at DESC
            LIMIT 1
        ");
        $template = $stmt->fetch();
    }
    
    if ($template) {
        // Variabili per sostituzione nei template
        $variables = [
            '{nome_azienda}' => $azienda['nome'] ?? 'Azienda',
            '{logo_azienda}' => $azienda['logo'] ? 'uploads/loghi/' . $azienda['logo'] : '',
            '{indirizzo_azienda}' => $azienda['indirizzo'] ?? '',
            '{telefono_azienda}' => $azienda['telefono'] ?? '',
            '{email_azienda}' => $azienda['email'] ?? '',
            '{partita_iva}' => $azienda['partita_iva'] ?? '',
            '{codice_fiscale}' => $azienda['codice_fiscale'] ?? '',
            '{data_corrente}' => date('d/m/Y'),
            '{anno}' => date('Y'),
            '{numero_pagina}' => '1',
            '{totale_pagine}' => '1'
        ];
        
        // Processa il template sostituendo le variabili
        $processed_template = [
            'id' => $template['id'],
            'nome' => $template['nome'] ?? 'Template',
            'modulo_id' => $template['modulo_id'],
            'modulo_nome' => $template['modulo_nome'] ?? 'Documento',
            'modulo_icona' => $template['icona'] ?? 'fa-file-alt',
            'tipo' => $template['tipo'] ?? 'word',
            'header_html' => str_replace(
                array_keys($variables), 
                array_values($variables), 
                $template['header_content'] ?? ''
            ),
            'footer_html' => str_replace(
                array_keys($variables), 
                array_values($variables), 
                $template['footer_content'] ?? ''
            ),
            'content_html' => str_replace(
                array_keys($variables), 
                array_values($variables), 
                $template['contenuto'] ?? ''
            ),
            'logo_header' => $template['logo_header'] ?? null,
            'logo_footer' => $template['logo_footer'] ?? null,
            'created_at' => $template['created_at'] ?? date('Y-m-d H:i:s'),
            'updated_at' => $template['aggiornato_il'] ?? date('Y-m-d H:i:s')
        ];
        
        // Configurazioni template
        $template_config = [
            'header_config' => [
                'show_logo' => !empty($template['logo_header']),
                'show_company_name' => strpos($template['header_content'] ?? '', '{nome_azienda}') !== false,
                'show_date' => strpos($template['header_content'] ?? '', '{data_corrente}') !== false,
                'height' => '80px'
            ],
            'footer_config' => [
                'show_copyright' => strpos($template['footer_content'] ?? '', '©') !== false,
                'show_page_numbers' => strpos($template['footer_content'] ?? '', '{numero_pagina}') !== false,
                'show_logo' => !empty($template['logo_footer']),
                'height' => '50px'
            ],
            'page_config' => [
                'format' => 'A4',
                'orientation' => 'portrait',
                'margins' => [30, 25, 30, 25] // top, right, bottom, left in mm
            ]
        ];
        
        json_response([
            'success' => true,
            'template' => array_merge($processed_template, $template_config),
            'azienda' => [
                'id' => $azienda['id'],
                'nome' => $azienda['nome'],
                'logo' => $azienda['logo'],
                'indirizzo' => $azienda['indirizzo'],
                'telefono' => $azienda['telefono'],
                'email' => $azienda['email'],
                'partita_iva' => $azienda['partita_iva'],
                'codice_fiscale' => $azienda['codice_fiscale']
            ],
            'variables' => $variables,
            'debug' => [
                'azienda_id' => $aziendaId,
                'modulo_id' => $moduloId,
                'template_found' => $template['id'],
                'user_role' => $user['ruolo'] ?? 'unknown'
            ]
        ]);
    } else {
        // Template di fallback se nessun template trovato
        $defaultTemplate = [
            'id' => 0,
            'nome' => 'Template Default',
            'modulo_nome' => 'Documento Generico',
            'tipo' => 'word',
            'header_html' => '
                <div class="header-default" style="text-align: center; border-bottom: 2px solid #0078d4; padding: 15px;">
                    <h2 style="color: #0078d4; margin: 0;">' . ($azienda['nome'] ?? 'Azienda') . '</h2>
                    <p style="margin: 5px 0; font-size: 12px; color: #666;">
                        ' . ($azienda['indirizzo'] ?? '') . 
                        ($azienda['telefono'] ? ' | Tel: ' . $azienda['telefono'] : '') .
                        ($azienda['email'] ? ' | Email: ' . $azienda['email'] : '') . '
                    </p>
                </div>',
            'footer_html' => '
                <div class="footer-default" style="text-align: center; border-top: 1px solid #ccc; padding: 10px; font-size: 11px; color: #666;">
                    <p>© ' . date('Y') . ' ' . ($azienda['nome'] ?? 'Azienda') . 
                    ($azienda['partita_iva'] ? ' - P.IVA: ' . $azienda['partita_iva'] : '') . 
                    ' | Pagina 1</p>
                </div>',
            'content_html' => '<div class="document-content" style="padding: 20px; min-height: 400px;"></div>',
            'header_config' => [
                'show_logo' => true,
                'show_company_name' => true,
                'show_date' => false,
                'height' => '80px'
            ],
            'footer_config' => [
                'show_copyright' => true,
                'show_page_numbers' => true,
                'height' => '50px'
            ],
            'page_config' => [
                'format' => 'A4',
                'orientation' => 'portrait',
                'margins' => [30, 25, 30, 25]
            ]
        ];
        
        json_response([
            'success' => true,
            'template' => $defaultTemplate,
            'is_default' => true,
            'message' => 'Utilizzando template di default - nessun template personalizzato trovato',
            'azienda' => [
                'id' => $azienda['id'],
                'nome' => $azienda['nome'],
                'logo' => $azienda['logo'],
                'indirizzo' => $azienda['indirizzo'],
                'telefono' => $azienda['telefono'],
                'email' => $azienda['email'],
                'partita_iva' => $azienda['partita_iva'],
                'codice_fiscale' => $azienda['codice_fiscale']
            ]
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error in get-template-azienda: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    json_response([
        'success' => false,
        'error' => 'Errore durante il recupero del template',
        'details' => $e->getMessage()
    ]);
}
?>