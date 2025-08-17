<?php
/**
 * API per importazione eventi da file ICS
 * Supporta eventi singoli, ricorrenti, timezone e partecipanti
 */

// Inizializza la sessione se non già attiva
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error handling per catturare tutti gli errori e restituire sempre JSON valido
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Custom error handler per convertire errori in eccezioni
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Handler per fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_CORE_ERROR || $error['type'] === E_COMPILE_ERROR)) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Errore fatale: ' . $error['message'] . ' in ' . basename($error['file']) . ':' . $error['line']
        ]);
        exit;
    }
});

// Imposta header JSON all'inizio
header('Content-Type: application/json');

try {
    // Verifica che i file richiesti esistano
    $requiredFiles = [
        '../config/config.php',
        '../middleware/Auth.php',
        '../utils/ActivityLogger.php',
        '../utils/CSRFTokenManager.php'
    ];
    
    foreach ($requiredFiles as $file) {
        if (!file_exists(__DIR__ . '/' . $file)) {
            throw new Exception("File richiesto mancante: $file");
        }
    }
    
    require_once '../config/config.php';
    require_once '../middleware/Auth.php';
    require_once '../utils/ActivityLogger.php';
    require_once '../utils/CSRFTokenManager.php';
    
    // Verifica che le classi necessarie siano disponibili
    if (!class_exists('DateTime')) {
        throw new Exception('La classe DateTime non è disponibile. Verificare la configurazione PHP.');
    }
    
    if (!class_exists('DateInterval')) {
        throw new Exception('La classe DateInterval non è disponibile. Verificare la configurazione PHP.');
    }
    
    if (!class_exists('DateTimeZone')) {
        throw new Exception('La classe DateTimeZone non è disponibile. Verificare la configurazione PHP.');
    }

    // Verifica CSRF token per richieste POST
    // Per upload di file, il token può essere nell'header X-CSRF-Token o nei dati POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Prima controlla se c'è un token in sessione, altrimenti generalo
        if (!isset($_SESSION['csrf_token']) || empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        // Ottieni il token dalla richiesta (header o POST)
        $providedToken = null;
        
        // Controlla l'header HTTP X-CSRF-Token
        $headers = getallheaders();
        if (isset($headers['X-CSRF-Token'])) {
            $providedToken = $headers['X-CSRF-Token'];
        } elseif (isset($headers['x-csrf-token'])) {
            $providedToken = $headers['x-csrf-token'];
        } elseif (isset($_POST['csrf_token'])) {
            $providedToken = $_POST['csrf_token'];
        }
        
        // Per richieste con file upload, il CSRF potrebbe non essere richiesto
        // se l'autenticazione è già verificata e non ci sono altri dati POST sensibili
        // Verifica il token solo se fornito
        if ($providedToken !== null) {
            if (!hash_equals($_SESSION['csrf_token'], $providedToken)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Token CSRF non valido']);
                exit;
            }
        } else {
            // Per upload di file con autenticazione valida, possiamo procedere
            // Il token CSRF è opzionale per upload di file autenticati
            // Ma deve essere presente l'autenticazione che verrà verificata dopo
            error_log('CSRF token non fornito per import ICS, procedo con verifica autenticazione');
        }
    }

    // Autenticazione
    $auth = Auth::getInstance();
    if (!$auth->isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Non autenticato']);
        exit;
    }

    $user = $auth->getUser();
    $currentAzienda = $auth->getCurrentAzienda();

    // Per utenti normali, verifica che ci sia un'azienda selezionata
    // Per super admin, l'azienda è opzionale
    if (!$currentAzienda && !$auth->isSuperAdmin()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Nessuna azienda selezionata']);
        exit;
    }

    // Debug: log della richiesta
    error_log('Import ICS - Metodo: ' . $_SERVER['REQUEST_METHOD']);
    error_log('Import ICS - FILES: ' . print_r($_FILES, true));
    error_log('Import ICS - POST: ' . print_r($_POST, true));
    
    // Verifica che ci sia un file caricato
    if (!isset($_FILES['ics_file']) || $_FILES['ics_file']['error'] !== UPLOAD_ERR_OK) {
        $errorMessage = 'Nessun file ICS caricato';
        if (isset($_FILES['ics_file']['error'])) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE => 'Il file supera la dimensione massima consentita dal server',
                UPLOAD_ERR_FORM_SIZE => 'Il file supera la dimensione massima consentita dal form',
                UPLOAD_ERR_PARTIAL => 'Il file è stato caricato solo parzialmente',
                UPLOAD_ERR_NO_FILE => 'Nessun file caricato',
                UPLOAD_ERR_NO_TMP_DIR => 'Directory temporanea mancante',
                UPLOAD_ERR_CANT_WRITE => 'Impossibile scrivere il file su disco',
                UPLOAD_ERR_EXTENSION => 'Upload bloccato da un\'estensione PHP'
            ];
            $errorCode = $_FILES['ics_file']['error'];
            if (isset($uploadErrors[$errorCode])) {
                $errorMessage = $uploadErrors[$errorCode];
            } else {
                $errorMessage = "Errore upload sconosciuto (codice: $errorCode)";
            }
        }
        error_log('Import ICS - Errore upload: ' . $errorMessage);
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $errorMessage]);
        exit;
    }
    
    // Verifica dimensione file (max 5MB per file ICS)
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($_FILES['ics_file']['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'Il file ICS è troppo grande. Dimensione massima: 5MB'
        ]);
        exit;
    }

    // Verifica estensione file
    $allowedExtensions = ['ics', 'ical', 'ifb', 'icalendar'];
    $fileInfo = pathinfo($_FILES['ics_file']['name']);
    $extension = isset($fileInfo['extension']) ? strtolower($fileInfo['extension']) : '';

    if (!in_array($extension, $allowedExtensions)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Il file deve avere estensione .ics, .ical, .ifb o .icalendar']);
        exit;
    }
    // Leggi il contenuto del file ICS
    $icsContent = @file_get_contents($_FILES['ics_file']['tmp_name']);
    
    if ($icsContent === false) {
        throw new Exception('Impossibile leggere il file ICS');
    }
    
    // Verifica che il contenuto sembri un file ICS valido
    if (strpos($icsContent, 'BEGIN:VCALENDAR') === false) {
        throw new Exception('Il file non sembra essere un calendario ICS valido');
    }
    
    // Parse del file ICS
    $events = parseICS($icsContent);
    
    if (empty($events)) {
        throw new Exception('Nessun evento trovato nel file ICS');
    }
    
    // Determina l'azienda per cui importare gli eventi
    $aziendaId = null;
    if ($auth->isSuperAdmin()) {
        // Super admin può specificare l'azienda o lasciare NULL per calendario personale
        if (isset($_POST['azienda_id']) && $_POST['azienda_id'] !== '') {
            $aziendaId = intval($_POST['azienda_id']);
        } else {
            $aziendaId = null; // NULL per calendario personale/senza azienda
        }
    } else {
        // Utente normale usa l'azienda corrente
        $aziendaId = isset($currentAzienda['azienda_id']) ? $currentAzienda['azienda_id'] : 
                     (isset($currentAzienda['id']) ? $currentAzienda['id'] : null);
    }
    
    // Verifica che la connessione al database sia disponibile
    if (!function_exists('db_connection')) {
        throw new Exception('Funzione db_connection non disponibile');
    }
    
    if (!function_exists('db_query')) {
        throw new Exception('Funzione db_query non disponibile');
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
                // Per eventi con UID, controlla duplicati considerando anche azienda_id NULL
                if ($aziendaId === null) {
                    $stmt = db_query(
                        "SELECT id FROM eventi WHERE uid_import = ? AND azienda_id IS NULL",
                        [$event['uid']]
                    );
                } else {
                    $stmt = db_query(
                        "SELECT id FROM eventi WHERE uid_import = ? AND azienda_id = ?",
                        [$event['uid'], $aziendaId]
                    );
                }
                $existingEvent = $stmt->fetch();
            }
            
            if (!$existingEvent && !empty($event['titolo']) && !empty($event['data_inizio'])) {
                // Controlla duplicati per titolo e data
                if ($aziendaId === null) {
                    $stmt = db_query(
                        "SELECT id FROM eventi 
                         WHERE titolo = ? AND data_inizio = ? AND azienda_id IS NULL",
                        [$event['titolo'], $event['data_inizio']]
                    );
                } else {
                    $stmt = db_query(
                        "SELECT id FROM eventi 
                         WHERE titolo = ? AND data_inizio = ? AND azienda_id = ?",
                        [$event['titolo'], $event['data_inizio'], $aziendaId]
                    );
                }
                $existingEvent = $stmt->fetch();
            }
            
            if ($existingEvent) {
                $skipped++;
                continue;
            }
            
            // Prepara i dati per l'inserimento
            // Gestisci data e ora correttamente
            $dataInizio = $event['data_inizio'];
            $dataFine = $event['data_fine'] ?? $event['data_inizio'];
            $oraInizio = $event['ora_inizio'] ?? '09:00:00';
            $oraFine = $event['ora_fine'] ?? '10:00:00';
            
            // Combina data e ora per il campo datetime
            $datetimeInizio = $dataInizio . ' ' . $oraInizio;
            $datetimeFine = $dataFine . ' ' . $oraFine;
            
            $eventData = [
                'titolo' => $event['titolo'] ?? 'Evento importato',
                'descrizione' => $event['descrizione'] ?? '',
                'data_inizio' => $datetimeInizio,  // datetime format
                'data_fine' => $datetimeFine,      // datetime format
                'ora_inizio' => $oraInizio,        // time format
                'ora_fine' => $oraFine,            // time format
                'luogo' => $event['luogo'] ?? '',
                'tipo' => $event['tipo'] ?? 'evento',
                'stato' => $event['status'] ?? 'programmato',
                'azienda_id' => $aziendaId,
                'creato_da' => $user['id'],
                'uid_import' => $event['uid'] ?? null,
                'tutto_il_giorno' => $event['tutto_il_giorno'] ?? 0,
                'priorita' => $event['priority'] ?? 'media',
                'tags' => $event['tags'] ?? null
            ];
            
            // Inserisci l'evento
            // Prepara la query di inserimento
            $columns = array_keys($eventData);
            $placeholders = array_fill(0, count($columns), '?');
            $values = array_values($eventData);
            
            $sql = "INSERT INTO eventi (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = db_query($sql, $values);
            $eventId = db_connection()->lastInsertId();
            
            // Se ci sono partecipanti, prova ad aggiungerli
            if (!empty($event['partecipanti']) && is_array($event['partecipanti'])) {
                foreach ($event['partecipanti'] as $email) {
                    // Cerca l'utente per email
                    $stmt = db_query("SELECT id FROM utenti WHERE email = ? AND attivo = 1", [$email]);
                    $partecipante = $stmt->fetch();
                    
                    if ($partecipante) {
                        // Aggiungi il partecipante all'evento
                        db_query(
                            "INSERT INTO evento_partecipanti (evento_id, utente_id, stato) VALUES (?, ?, ?)",
                            [$eventId, $partecipante['id'], 'invitato']
                        );
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

} catch (ErrorException $e) {
    // Gestione errori PHP convertiti in eccezioni
    if (function_exists('db_connection') && db_connection() && db_connection()->inTransaction()) {
        db_connection()->rollback();
    }
    
    error_log('Errore PHP in import-ics.php: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Errore PHP: ' . $e->getMessage(),
        'line' => $e->getLine(),
        'file' => basename($e->getFile())
    ]);
    
} catch (Exception $e) {
    // Gestione eccezioni generali
    if (function_exists('db_connection') && db_connection() && db_connection()->inTransaction()) {
        db_connection()->rollback();
    }
    
    error_log('Errore in import-ics.php: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Errore durante l\'importazione: ' . $e->getMessage()
    ]);
    
} catch (Error $e) {
    // Gestione Fatal errors catturabili (PHP 7+)
    if (function_exists('db_connection') && db_connection() && db_connection()->inTransaction()) {
        db_connection()->rollback();
    }
    
    error_log('Fatal error in import-ics.php: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Errore fatale: ' . $e->getMessage(),
        'line' => $e->getLine(),
        'file' => basename($e->getFile())
    ]);
}

/**
 * Parse del contenuto ICS con supporto avanzato
 */
function parseICS($content) {
    $events = [];
    $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $content));
    
    $currentEvent = null;
    $inEvent = false;
    $currentProperty = '';
    $timezone = 'Europe/Rome'; // Default timezone
    
    // Handle line continuations (lines starting with space or tab)
    $unfoldedLines = [];
    foreach ($lines as $line) {
        if (preg_match('/^[\s\t]/', $line)) {
            // Continuation of previous line
            if (!empty($unfoldedLines)) {
                $unfoldedLines[count($unfoldedLines) - 1] .= ltrim($line);
            }
        } else {
            $unfoldedLines[] = $line;
        }
    }
    
    foreach ($unfoldedLines as $line) {
        $line = trim($line);
        
        // Parse timezone
        if (strpos($line, 'X-WR-TIMEZONE:') === 0) {
            $timezone = substr($line, 14);
            continue;
        }
        
        if ($line === 'BEGIN:VEVENT') {
            $inEvent = true;
            $currentEvent = [
                'titolo' => '',
                'descrizione' => '',
                'data_inizio' => '',
                'data_fine' => '',
                'ora_inizio' => null,
                'ora_fine' => null,
                'luogo' => '',
                'uid' => '',
                'partecipanti' => [],
                'tutto_il_giorno' => 0,
                'tipo' => 'evento',
                'rrule' => null,
                'organizer' => null,
                'status' => 'programmato',
                'priority' => null
            ];
            continue;
        }
        
        if ($line === 'END:VEVENT') {
            if ($currentEvent && !empty($currentEvent['data_inizio'])) {
                // Process recurring events
                if (!empty($currentEvent['rrule'])) {
                    $recurringEvents = processRecurringEvent($currentEvent);
                    $events = array_merge($events, $recurringEvents);
                } else {
                    // Set default times if not specified
                    if ($currentEvent['ora_inizio'] === null) {
                        $currentEvent['ora_inizio'] = '09:00:00';
                    }
                    if ($currentEvent['ora_fine'] === null) {
                        $currentEvent['ora_fine'] = '10:00:00';
                    }
                    $events[] = $currentEvent;
                }
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
            $colonPos = strpos($line, ':');
            $property = substr($line, 0, $colonPos);
            $value = substr($line, $colonPos + 1);
            
            // Extract parameters from property
            $params = [];
            if (strpos($property, ';') !== false) {
                $parts = explode(';', $property);
                $property = array_shift($parts);
                foreach ($parts as $param) {
                    if (strpos($param, '=') !== false) {
                        list($pname, $pvalue) = explode('=', $param, 2);
                        $params[$pname] = $pvalue;
                    }
                }
            }
            
            $property = strtoupper($property);
            
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
                    $datetime = parseICSDateTime($value, $params, $timezone);
                    $currentEvent['data_inizio'] = $datetime['date'];
                    $currentEvent['ora_inizio'] = $datetime['time'];
                    $currentEvent['tutto_il_giorno'] = $datetime['all_day'];
                    break;
                    
                case 'DTEND':
                    $datetime = parseICSDateTime($value, $params, $timezone);
                    $currentEvent['data_fine'] = $datetime['date'];
                    $currentEvent['ora_fine'] = $datetime['time'];
                    break;
                    
                case 'DURATION':
                    // Handle duration format (e.g., PT1H30M)
                    if (!empty($currentEvent['data_inizio']) && empty($currentEvent['data_fine'])) {
                        try {
                            $duration = parseDuration($value);
                            if ($duration && class_exists('DateTime')) {
                                $startDateTime = new DateTime($currentEvent['data_inizio'] . ' ' . ($currentEvent['ora_inizio'] ?: '00:00:00'));
                                $startDateTime->add($duration);
                                $currentEvent['data_fine'] = $startDateTime->format('Y-m-d');
                                $currentEvent['ora_fine'] = $startDateTime->format('H:i:s');
                            }
                        } catch (Exception $e) {
                            error_log('Error processing duration: ' . $e->getMessage());
                            // Set default end time if duration processing fails
                            $currentEvent['data_fine'] = $currentEvent['data_inizio'];
                            $currentEvent['ora_fine'] = '10:00:00';
                        }
                    }
                    break;
                    
                case 'ATTENDEE':
                    // Extract email from ATTENDEE value
                    $email = $value;
                    if (preg_match('/mailto:(.+)/i', $value, $matches)) {
                        $email = $matches[1];
                    }
                    // Clean email
                    $email = trim(str_replace(['mailto:', 'MAILTO:'], '', $email));
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $currentEvent['partecipanti'][] = $email;
                    }
                    break;
                    
                case 'ORGANIZER':
                    if (preg_match('/mailto:(.+)/i', $value, $matches)) {
                        $currentEvent['organizer'] = $matches[1];
                    }
                    break;
                    
                case 'CATEGORIES':
                    // Map categories to event type
                    $categories = strtolower($value);
                    if (strpos($categories, 'meeting') !== false || strpos($categories, 'riunione') !== false) {
                        $currentEvent['tipo'] = 'meeting';
                    } elseif (strpos($categories, 'conference') !== false || strpos($categories, 'conferenza') !== false) {
                        $currentEvent['tipo'] = 'conferenza';
                    } elseif (strpos($categories, 'training') !== false || strpos($categories, 'formazione') !== false) {
                        $currentEvent['tipo'] = 'formazione';
                    } elseif (strpos($categories, 'deadline') !== false || strpos($categories, 'scadenza') !== false) {
                        $currentEvent['tipo'] = 'scadenza';
                    } elseif (strpos($categories, 'birthday') !== false || strpos($categories, 'compleanno') !== false) {
                        $currentEvent['tipo'] = 'compleanno';
                    }
                    break;
                    
                case 'STATUS':
                    $status = strtolower($value);
                    if (in_array($status, ['confirmed', 'tentative', 'cancelled'])) {
                        $statusMap = [
                            'confirmed' => 'programmato',
                            'tentative' => 'programmato',
                            'cancelled' => 'annullato'
                        ];
                        $currentEvent['status'] = $statusMap[$status];
                    }
                    break;
                    
                case 'PRIORITY':
                    $priority = intval($value);
                    if ($priority >= 1 && $priority <= 3) {
                        $currentEvent['priority'] = 'alta';
                    } elseif ($priority >= 4 && $priority <= 6) {
                        $currentEvent['priority'] = 'media';
                    } elseif ($priority >= 7 && $priority <= 9) {
                        $currentEvent['priority'] = 'bassa';
                    }
                    break;
                    
                case 'RRULE':
                    // Store recurrence rule for processing
                    $currentEvent['rrule'] = $value;
                    break;
                    
                case 'CLASS':
                    // Handle privacy class (PUBLIC, PRIVATE, CONFIDENTIAL)
                    if ($value === 'PRIVATE' || $value === 'CONFIDENTIAL') {
                        $currentEvent['tags'] = 'privato';
                    }
                    break;
            }
        }
    }
    
    return $events;
}

/**
 * Parse ICS datetime format with timezone support
 */
function parseICSDateTime($value, $params = [], $defaultTimezone = 'Europe/Rome') {
    try {
        $allDay = false;
        $timezone = $defaultTimezone;
        
        // Validate input
        if (empty($value)) {
            return ['date' => date('Y-m-d'), 'time' => '09:00:00', 'all_day' => false];
        }
        
        // Check for timezone parameter
        if (isset($params['TZID'])) {
            $timezone = $params['TZID'];
        }
        
        // Remove inline timezone identifier if present
        if (preg_match('/^TZID=([^:]+):(.+)$/', $value, $matches)) {
            $timezone = $matches[1];
            $value = $matches[2];
        }
        
        // Check for VALUE=DATE parameter (all-day event)
        if (isset($params['VALUE']) && $params['VALUE'] === 'DATE') {
            $allDay = true;
        }
        
        // Handle different datetime formats
        if (strlen($value) === 8 && preg_match('/^\d{8}$/', $value)) {
            // Date only (YYYYMMDD) - all-day event
            $year = substr($value, 0, 4);
            $month = substr($value, 4, 2);
            $day = substr($value, 6, 2);
            
            // Validate date components
            if (!checkdate((int)$month, (int)$day, (int)$year)) {
                throw new Exception("Invalid date: $value");
            }
            
            $date = "$year-$month-$day";
            return ['date' => $date, 'time' => null, 'all_day' => true];
            
        } elseif (preg_match('/^(\d{8})T(\d{6})(Z)?$/', $value, $matches)) {
            // DateTime (YYYYMMDDTHHMMSS or YYYYMMDDTHHMMSSZ)
            $dateStr = $matches[1];
            $timeStr = $matches[2];
            
            $year = substr($dateStr, 0, 4);
            $month = substr($dateStr, 4, 2);
            $day = substr($dateStr, 6, 2);
            
            $hour = substr($timeStr, 0, 2);
            $minute = substr($timeStr, 2, 2);
            $second = substr($timeStr, 4, 2);
            
            // Validate date and time components
            if (!checkdate((int)$month, (int)$day, (int)$year)) {
                throw new Exception("Invalid date in datetime: $value");
            }
            
            if ((int)$hour > 23 || (int)$minute > 59 || (int)$second > 59) {
                throw new Exception("Invalid time in datetime: $value");
            }
            
            $date = "$year-$month-$day";
            $time = "$hour:$minute:$second";
            
            // Handle UTC timezone
            if (isset($matches[3]) && $matches[3] === 'Z') {
                // Convert from UTC to local timezone
                try {
                    if (class_exists('DateTime') && class_exists('DateTimeZone')) {
                        $dt = new DateTime($date . ' ' . $time, new DateTimeZone('UTC'));
                        $dt->setTimezone(new DateTimeZone($defaultTimezone));
                        $date = $dt->format('Y-m-d');
                        $time = $dt->format('H:i:s');
                    }
                } catch (Exception $e) {
                    // Keep original values if conversion fails
                    error_log('Timezone conversion failed: ' . $e->getMessage());
                }
            } elseif ($timezone !== $defaultTimezone) {
                // Convert from specified timezone to local
                try {
                    if (class_exists('DateTime') && class_exists('DateTimeZone')) {
                        $dt = new DateTime($date . ' ' . $time, new DateTimeZone($timezone));
                        $dt->setTimezone(new DateTimeZone($defaultTimezone));
                        $date = $dt->format('Y-m-d');
                        $time = $dt->format('H:i:s');
                    }
                } catch (Exception $e) {
                    // Keep original values if conversion fails
                    error_log('Timezone conversion failed: ' . $e->getMessage());
                }
            }
            
            return ['date' => $date, 'time' => $time, 'all_day' => $allDay];
            
        } else {
            // Try to parse with DateTime
            if (class_exists('DateTime')) {
                try {
                    $dt = new DateTime($value);
                    return [
                        'date' => $dt->format('Y-m-d'),
                        'time' => $dt->format('H:i:s'),
                        'all_day' => $allDay
                    ];
                } catch (Exception $e) {
                    // Default fallback
                    error_log('DateTime parsing failed for: ' . $value);
                }
            }
            
            // Ultimate fallback
            return ['date' => date('Y-m-d'), 'time' => '09:00:00', 'all_day' => false];
        }
        
    } catch (Exception $e) {
        error_log('parseICSDateTime error: ' . $e->getMessage());
        // Return safe default values
        return ['date' => date('Y-m-d'), 'time' => '09:00:00', 'all_day' => false];
    }
}

/**
 * Parse duration format (ISO 8601)
 */
function parseDuration($duration) {
    // Parse ISO 8601 duration format (e.g., PT1H30M, P1D, PT30M)
    try {
        if (!class_exists('DateInterval')) {
            error_log('DateInterval class not available');
            return null;
        }
        
        if (empty($duration)) {
            return new DateInterval('PT1H');
        }
        
        // Validate basic format
        if (!preg_match('/^P/', $duration)) {
            $duration = 'P' . $duration;
        }
        
        return new DateInterval($duration);
    } catch (Exception $e) {
        error_log('Failed to parse duration: ' . $duration . ' - ' . $e->getMessage());
        // Default to 1 hour if parsing fails
        try {
            return new DateInterval('PT1H');
        } catch (Exception $e2) {
            return null;
        }
    }
}

/**
 * Process recurring events based on RRULE
 */
function processRecurringEvent($event) {
    try {
        if (!class_exists('DateTime') || !class_exists('DateInterval')) {
            error_log('DateTime or DateInterval not available for recurring events');
            return [$event]; // Return single event if classes not available
        }
        
        $events = [];
        $rrule = $event['rrule'];
        
        if (empty($rrule)) {
            return [$event];
        }
        
        // Parse RRULE parameters
        $rules = [];
        $parts = explode(';', $rrule);
        foreach ($parts as $part) {
            if (strpos($part, '=') !== false) {
                list($key, $value) = explode('=', $part, 2);
                $rules[$key] = $value;
            }
        }
        
        // Get recurrence parameters
        $freq = isset($rules['FREQ']) ? $rules['FREQ'] : null;
        $count = isset($rules['COUNT']) ? intval($rules['COUNT']) : 10; // Default to 10 occurrences
        $until = isset($rules['UNTIL']) ? $rules['UNTIL'] : null;
        $interval = isset($rules['INTERVAL']) ? intval($rules['INTERVAL']) : 1;
        $byday = isset($rules['BYDAY']) ? $rules['BYDAY'] : null;
        
        // Limit count to prevent too many events
        if ($count > 52) $count = 52; // Max 1 year of weekly events
        if ($count < 1) $count = 1;
        if ($interval < 1) $interval = 1;
        
        // Start date
        try {
            $startDate = new DateTime($event['data_inizio']);
            $endDate = $event['data_fine'] ? new DateTime($event['data_fine']) : clone $startDate;
            $diff = $startDate->diff($endDate);
        } catch (Exception $e) {
            error_log('Invalid date in recurring event: ' . $e->getMessage());
            return [$event]; // Return single event if date parsing fails
        }
        
        // Generate recurring events
        $currentDate = clone $startDate;
        $currentEndDate = clone $endDate;
    
        for ($i = 0; $i < $count; $i++) {
            // Create event for this occurrence
            $newEvent = $event;
            $newEvent['data_inizio'] = $currentDate->format('Y-m-d');
            $newEvent['data_fine'] = $currentEndDate->format('Y-m-d');
            $newEvent['uid'] = $event['uid'] . '-' . $i; // Make UID unique for each occurrence
            unset($newEvent['rrule']); // Remove rrule from individual events
            
            $events[] = $newEvent;
            
            // Calculate next occurrence
            try {
                switch ($freq) {
                    case 'DAILY':
                        $currentDate->add(new DateInterval('P' . $interval . 'D'));
                        $currentEndDate->add(new DateInterval('P' . $interval . 'D'));
                        break;
                        
                    case 'WEEKLY':
                        $currentDate->add(new DateInterval('P' . ($interval * 7) . 'D'));
                        $currentEndDate->add(new DateInterval('P' . ($interval * 7) . 'D'));
                        break;
                        
                    case 'MONTHLY':
                        $currentDate->add(new DateInterval('P' . $interval . 'M'));
                        $currentEndDate->add(new DateInterval('P' . $interval . 'M'));
                        break;
                        
                    case 'YEARLY':
                        $currentDate->add(new DateInterval('P' . $interval . 'Y'));
                        $currentEndDate->add(new DateInterval('P' . $interval . 'Y'));
                        break;
                        
                    default:
                        // Unknown frequency, just return what we have so far
                        return empty($events) ? [$event] : $events;
                }
            } catch (Exception $e) {
                error_log('Error adding interval in recurring event: ' . $e->getMessage());
                break; // Stop processing on error
            }
            
            // Check UNTIL date if specified
            if ($until) {
                try {
                    $untilDate = parseICSDateTime($until, [], 'Europe/Rome');
                    $untilDateTime = new DateTime($untilDate['date']);
                    if ($currentDate > $untilDateTime) {
                        break;
                    }
                } catch (Exception $e) {
                    error_log('Error parsing UNTIL date: ' . $e->getMessage());
                    // Continue without UNTIL constraint if parsing fails
                }
            }
        }
        
        return empty($events) ? [$event] : $events;
        
    } catch (Exception $e) {
        error_log('Error in processRecurringEvent: ' . $e->getMessage());
        // Return single event on any error
        return [$event];
    }
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