<?php
/**
 * Esportazione Calendario in formato ICS
 */

require_once 'backend/config/config.php';
require_once 'backend/utils/ICSGenerator.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$user = $auth->getUser();
$currentAzienda = $auth->getCurrentAzienda();

// Parametri di esportazione
$tipo = $_GET['tipo'] ?? 'calendario'; // 'calendario' o 'evento'
$evento_id = intval($_GET['evento_id'] ?? 0);
$periodo = $_GET['periodo'] ?? 'mese'; // 'mese', 'trimestre', 'anno', 'tutto'
$formato = $_GET['formato'] ?? 'download'; // 'download' o 'inline'

try {
    $icsGenerator = new ICSGenerator();
    
    if ($tipo === 'evento' && $evento_id > 0) {
        // Esporta singolo evento
        
        // Verifica permessi e carica evento
        $stmt = db_query("SELECT * FROM eventi WHERE id = ?", [$evento_id]);
        $evento = $stmt->fetch();
        
        if (!$evento) {
            throw new Exception("Evento non trovato");
        }
        
        // Verifica permessi di accesso
        if (!$auth->canViewAllEvents()) {
            $aziendaId = $currentAzienda['id'] ?? $currentAzienda['azienda_id'] ?? null;
            if ($evento['azienda_id'] != $aziendaId && $evento['creato_da'] != $user['id']) {
                throw new Exception("Non hai i permessi per accedere a questo evento");
            }
        }
        
        // Carica dati organizer
        $stmt = db_query("SELECT nome, cognome, email FROM utenti WHERE id = ?", [$evento['creato_da']]);
        $organizer = $stmt->fetch();
        
        // Genera ICS per singolo evento
        $content = $icsGenerator->generateEventICS($evento, $organizer);
        $filename = 'evento_' . $evento['id'] . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $evento['titolo']) . '.ics';
        
    } else {
        // Esporta calendario completo
        
        // Determina range di date basato sul periodo
        $whereClause = "WHERE 1=1";
        $params = [];
        
        // Filtro per azienda se non super admin
        if (!$auth->canViewAllEvents()) {
            $aziendaId = $currentAzienda['id'] ?? $currentAzienda['azienda_id'] ?? null;
            if ($aziendaId) {
                $whereClause .= " AND (e.azienda_id = ? OR e.creato_da = ?)";
                $params[] = $aziendaId;
                $params[] = $user['id'];
            }
        }
        
        // Filtro temporale
        switch ($periodo) {
            case 'mese':
                $whereClause .= " AND e.data_inizio >= ? AND e.data_inizio <= ?";
                $params[] = date('Y-m-01 00:00:00');
                $params[] = date('Y-m-t 23:59:59');
                break;
                
            case 'trimestre':
                $currentMonth = date('n');
                $quarterStart = floor(($currentMonth - 1) / 3) * 3 + 1;
                $quarterEnd = $quarterStart + 2;
                $whereClause .= " AND e.data_inizio >= ? AND e.data_inizio <= ?";
                $params[] = date('Y-' . sprintf('%02d', $quarterStart) . '-01 00:00:00');
                $params[] = date('Y-' . sprintf('%02d', $quarterEnd) . '-t 23:59:59');
                break;
                
            case 'anno':
                $whereClause .= " AND YEAR(e.data_inizio) = ?";
                $params[] = date('Y');
                break;
                
            case 'tutto':
                // Nessun filtro temporale
                break;
                
            default:
                throw new Exception("Periodo non valido");
        }
        
        // Carica eventi
        $sql = "SELECT e.*, 
                       u.nome as creatore_nome, u.cognome as creatore_cognome, u.email as creatore_email
                FROM eventi e 
                LEFT JOIN utenti u ON e.creato_da = u.id 
                $whereClause
                ORDER BY e.data_inizio ASC";
        
        $stmt = db_query($sql, $params);
        $eventi = $stmt->fetchAll();
        
        if (empty($eventi)) {
            throw new Exception("Nessun evento trovato per il periodo selezionato");
        }
        
        // Determina nome calendario
        $nomeAzienda = $currentAzienda['nome'] ?? 'Nexio Solution';
        $periodoDesc = [
            'mese' => date('F Y'),
            'trimestre' => 'Q' . ceil(date('n')/3) . ' ' . date('Y'),
            'anno' => date('Y'),
            'tutto' => 'Tutti gli eventi'
        ];
        
        $calendarName = $nomeAzienda . ' - Calendario ' . $periodoDesc[$periodo];
        
        // Genera ICS
        $content = $icsGenerator->generateCalendarICS($eventi, $calendarName);
        $filename = 'calendario_' . strtolower($nomeAzienda) . '_' . $periodo . '_' . date('Y-m-d') . '.ics';
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    }
    
    // Log dell'esportazione
    try {
        $dettagli = [
            'tipo' => $tipo,
            'periodo' => $periodo,
            'evento_id' => $evento_id,
            'filename' => $filename,
            'num_eventi' => $tipo === 'evento' ? 1 : count($eventi ?? [])
        ];
        
        db_query("
            INSERT INTO log_attivita (utente_id, tipo_entita, azione, dettagli, creato_il)
            VALUES (?, ?, ?, ?, NOW())
        ", [$user['id'], 'calendario', 'esportazione_ics', json_encode($dettagli)]);
    } catch (Exception $e) {
        // Log non critico, continua
        error_log('Errore log esportazione ICS: ' . $e->getMessage());
    }
    
    // Output del file
    if ($formato === 'inline') {
        // Mostra contenuto inline per debug
        header('Content-Type: text/plain; charset=utf-8');
        echo $content;
    } else {
        // Download del file
        $icsGenerator->downloadICS($content, $filename);
    }
    
} catch (Exception $e) {
    // Gestione errori
    $_SESSION['error'] = "Errore durante l'esportazione: " . $e->getMessage();
    redirect(APP_PATH . '/calendario-eventi.php');
}
?>