<?php
/**
 * API per importazione eventi da file ICS
 */

require_once '../config/config.php';
require_once '../middleware/Auth.php';
require_once '../utils/ActivityLogger.php';

header('Content-Type: application/json');

// Autenticazione
$auth = Auth::getInstance();
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non autenticato']);
    exit;
}

$user = $auth->getUser();
$currentAzienda = $auth->getCurrentAzienda();

// Verifica che ci sia un'azienda selezionata
if (!$currentAzienda && !$auth->isSuperAdmin()) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Nessuna azienda selezionata']);
    exit;
}

// Verifica che ci sia un file caricato
if (!isset($_FILES['ics_file']) || $_FILES['ics_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Nessun file ICS caricato o errore nel caricamento']);
    exit;
}

// Verifica estensione file
$fileInfo = pathinfo($_FILES['ics_file']['name']);
if (strtolower($fileInfo['extension']) !== 'ics') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Il file deve avere estensione .ics']);
    exit;
}

try {
    // Leggi il contenuto del file ICS
    $icsContent = file_get_contents($_FILES['ics_file']['tmp_name']);
    
    if ($icsContent === false) {
        throw new Exception('Impossibile leggere il file ICS');
    }
    
    // Parse del file ICS
    $events = parseICS($icsContent);
    
    if (empty($events)) {
        throw new Exception('Nessun evento trovato nel file ICS');
    }
    
    // Determina l'azienda per cui importare gli eventi
    $aziendaId = null;
    if ($auth->isSuperAdmin()) {
        // Super admin può specificare l'azienda
        $aziendaId = isset($_POST['azienda_id']) ? intval($_POST['azienda_id']) : null;
    } else {
        // Utente normale usa l'azienda corrente
        $aziendaId = isset($currentAzienda['azienda_id']) ? $currentAzienda['azienda_id'] : 
                     (isset($currentAzienda['id']) ? $currentAzienda['id'] : null);
    }
    
    // Inizia transazione
    db_connection()->beginTransaction();
    
    $imported = 0;
    $skipped = 0;
    $errors = [];
    
    foreach ($events as $event) {
        try {
            // Controlla se l'evento esiste già (basato su UID se presente, altrimenti titolo e data)
            $existingEvent = null;
            
            if (!empty($event['uid'])) {
                $stmt = db_query(
                    "SELECT id FROM eventi WHERE uid_import = ? AND azienda_id = ?",
                    [$event['uid'], $aziendaId]
                );
                $existingEvent = $stmt->fetch();
            }
            
            if (!$existingEvent && !empty($event['titolo']) && !empty($event['data_inizio'])) {
                $stmt = db_query(
                    "SELECT id FROM eventi 
                     WHERE titolo = ? AND data_inizio = ? AND azienda_id = ?",
                    [$event['titolo'], $event['data_inizio'], $aziendaId]
                );
                $existingEvent = $stmt->fetch();
            }
            
            if ($existingEvent) {
                $skipped++;
                continue;
            }
            
            // Prepara i dati per l'inserimento
            $eventData = [
                'titolo' => $event['titolo'] ?? 'Evento importato',
                'descrizione' => $event['descrizione'] ?? '',
                'data_inizio' => $event['data_inizio'],
                'data_fine' => $event['data_fine'] ?? $event['data_inizio'],
                'ora_inizio' => $event['ora_inizio'] ?? '09:00:00',
                'ora_fine' => $event['ora_fine'] ?? '10:00:00',
                'luogo' => $event['luogo'] ?? '',
                'tipo' => $event['tipo'] ?? 'meeting',
                'azienda_id' => $aziendaId,
                'creato_da' => $user['id'],
                'uid_import' => $event['uid'] ?? null,
                'tutto_il_giorno' => $event['tutto_il_giorno'] ?? 0
            ];
            
            // Inserisci l'evento
            $eventId = db_insert('eventi', $eventData);
            
            // Se ci sono partecipanti, prova ad aggiungerli
            if (!empty($event['partecipanti']) && is_array($event['partecipanti'])) {
                foreach ($event['partecipanti'] as $email) {
                    // Cerca l'utente per email
                    $stmt = db_query("SELECT id FROM utenti WHERE email = ? AND attivo = 1", [$email]);
                    $partecipante = $stmt->fetch();
                    
                    if ($partecipante) {
                        // Aggiungi il partecipante all'evento
                        db_insert('evento_partecipanti', [
                            'evento_id' => $eventId,
                            'utente_id' => $partecipante['id'],
                            'stato' => 'invitato'
                        ]);
                    }
                }
            }
            
            $imported++;
            
        } catch (Exception $e) {
            $errors[] = "Errore importazione evento '{$event['titolo']}': " . $e->getMessage();
        }
    }
    
    // Commit transazione
    db_connection()->commit();
    
    // Log dell'importazione
    $logger = ActivityLogger::getInstance();
    $logger->log(
        'calendario_import',
        "Importati $imported eventi da file ICS, $skipped già esistenti",
        [
            'file_name' => $_FILES['ics_file']['name'],
            'imported' => $imported,
            'skipped' => $skipped,
            'total' => count($events)
        ]
    );
    
    // Prepara risposta
    $response = [
        'success' => true,
        'message' => "Importazione completata: $imported eventi importati, $skipped già esistenti",
        'imported' => $imported,
        'skipped' => $skipped,
        'total' => count($events)
    ];
    
    if (!empty($errors)) {
        $response['warnings'] = $errors;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    if (db_connection()->inTransaction()) {
        db_connection()->rollback();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Errore durante l\'importazione: ' . $e->getMessage()
    ]);
}

/**
 * Parse del contenuto ICS
 */
function parseICS($content) {
    $events = [];
    $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $content));
    
    $currentEvent = null;
    $inEvent = false;
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        if ($line === 'BEGIN:VEVENT') {
            $inEvent = true;
            $currentEvent = [
                'titolo' => '',
                'descrizione' => '',
                'data_inizio' => '',
                'data_fine' => '',
                'ora_inizio' => '',
                'ora_fine' => '',
                'luogo' => '',
                'uid' => '',
                'partecipanti' => [],
                'tutto_il_giorno' => 0
            ];
            continue;
        }
        
        if ($line === 'END:VEVENT') {
            if ($currentEvent && !empty($currentEvent['data_inizio'])) {
                $events[] = $currentEvent;
            }
            $currentEvent = null;
            $inEvent = false;
            continue;
        }
        
        if (!$inEvent || !$currentEvent) {
            continue;
        }
        
        // Parse delle proprietà
        if (strpos($line, ':') !== false) {
            list($property, $value) = explode(':', $line, 2);
            $property = strtoupper($property);
            
            // Rimuovi parametri dalla proprietà
            if (strpos($property, ';') !== false) {
                list($property, ) = explode(';', $property, 2);
            }
            
            switch ($property) {
                case 'SUMMARY':
                    $currentEvent['titolo'] = decodeICSText($value);
                    break;
                    
                case 'DESCRIPTION':
                    $currentEvent['descrizione'] = decodeICSText($value);
                    break;
                    
                case 'LOCATION':
                    $currentEvent['luogo'] = decodeICSText($value);
                    break;
                    
                case 'UID':
                    $currentEvent['uid'] = $value;
                    break;
                    
                case 'DTSTART':
                    $datetime = parseICSDateTime($value);
                    $currentEvent['data_inizio'] = $datetime['date'];
                    $currentEvent['ora_inizio'] = $datetime['time'];
                    if (strlen($value) === 8) { // Format YYYYMMDD means all-day event
                        $currentEvent['tutto_il_giorno'] = 1;
                    }
                    break;
                    
                case 'DTEND':
                    $datetime = parseICSDateTime($value);
                    $currentEvent['data_fine'] = $datetime['date'];
                    $currentEvent['ora_fine'] = $datetime['time'];
                    break;
                    
                case 'ATTENDEE':
                    // Extract email from ATTENDEE value
                    if (preg_match('/mailto:(.+)/i', $value, $matches)) {
                        $currentEvent['partecipanti'][] = $matches[1];
                    }
                    break;
                    
                case 'CATEGORIES':
                    // Map categories to event type
                    $categories = strtolower($value);
                    if (strpos($categories, 'meeting') !== false) {
                        $currentEvent['tipo'] = 'meeting';
                    } elseif (strpos($categories, 'conference') !== false) {
                        $currentEvent['tipo'] = 'conferenza';
                    } elseif (strpos($categories, 'training') !== false) {
                        $currentEvent['tipo'] = 'formazione';
                    }
                    break;
            }
        }
    }
    
    return $events;
}

/**
 * Parse ICS datetime format
 */
function parseICSDateTime($value) {
    // Remove timezone identifier if present
    $value = preg_replace('/^TZID=[^:]+:/', '', $value);
    
    // Handle different datetime formats
    if (strlen($value) === 8) {
        // Date only (YYYYMMDD)
        $date = substr($value, 0, 4) . '-' . substr($value, 4, 2) . '-' . substr($value, 6, 2);
        return ['date' => $date, 'time' => '00:00:00'];
    } elseif (strlen($value) === 15 && substr($value, 8, 1) === 'T') {
        // DateTime (YYYYMMDDTHHMMSS)
        $date = substr($value, 0, 4) . '-' . substr($value, 4, 2) . '-' . substr($value, 6, 2);
        $time = substr($value, 9, 2) . ':' . substr($value, 11, 2) . ':' . substr($value, 13, 2);
        return ['date' => $date, 'time' => $time];
    } elseif (strlen($value) === 16 && substr($value, 8, 1) === 'T' && substr($value, 15, 1) === 'Z') {
        // DateTime UTC (YYYYMMDDTHHMMSSZ)
        $date = substr($value, 0, 4) . '-' . substr($value, 4, 2) . '-' . substr($value, 6, 2);
        $time = substr($value, 9, 2) . ':' . substr($value, 11, 2) . ':' . substr($value, 13, 2);
        return ['date' => $date, 'time' => $time];
    } else {
        // Try to parse with strtotime
        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return [
                'date' => date('Y-m-d', $timestamp),
                'time' => date('H:i:s', $timestamp)
            ];
        }
    }
    
    // Default fallback
    return ['date' => date('Y-m-d'), 'time' => '09:00:00'];
}

/**
 * Decode ICS text (handle escaping)
 */
function decodeICSText($text) {
    // Unescape ICS text
    $text = str_replace('\\n', "\n", $text);
    $text = str_replace('\\,', ',', $text);
    $text = str_replace('\\;', ';', $text);
    $text = str_replace('\\\\', '\\', $text);
    
    return $text;
}
?>