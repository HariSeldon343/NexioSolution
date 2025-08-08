<?php
require_once 'backend/config/config.php';
require_once 'backend/functions/aziende-functions.php';

$auth = Auth::getInstance();
$auth->requireAuth();

// Solo super admin e utenti speciali possono accedere
if (!$auth->hasElevatedPrivileges()) {
    $_SESSION['error'] = "Accesso negato. Solo utenti con privilegi elevati possono gestire le aziende.";
    redirect(APP_PATH . '/dashboard.php');
}

$user = $auth->getUser();
// Database instance handled by functions

$action = $_GET['action'] ?? 'list';
$aziendaId = $_GET['id'] ?? null;

// La funzione deleteAzienda è ora in backend/functions/aziende-functions.php

// Gestione azioni POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Se l'action viene dal POST (come per delete), usa quella
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
    }
    // Se l'ID viene dal POST (come per delete), usa quello
    if (isset($_POST['id'])) {
        $aziendaId = $_POST['id'];
    }
    
    // Gestione AJAX
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
        header('Content-Type: application/json');
        
        if ($action === 'delete' && $aziendaId) {
            $response = deleteAzienda($aziendaId);
            echo json_encode($response);
            exit;
        }
    }
    
    if ($action === 'nuovo' || $action === 'edit') {
        // Verifica se la colonna responsabile_id esiste prima di includerla nei dati
        $responsabile_column_exists = false;
        try {
            // Prova a fare una query che usa la colonna responsabile_id
            $stmt = db_query("SELECT responsabile_id FROM aziende LIMIT 1");
            $responsabile_column_exists = true;
        } catch (Exception $e) {
            $responsabile_column_exists = false;
        }
        
        // Raccolta dati
        $data = [
            'nome' => trim($_POST['nome'] ?? ''),
            'ragione_sociale' => trim($_POST['ragione_sociale'] ?? ''),
            'partita_iva' => trim($_POST['partita_iva'] ?? ''),
            'codice_fiscale' => trim($_POST['codice_fiscale'] ?? ''),
            'indirizzo' => trim($_POST['indirizzo'] ?? ''),
            'citta' => trim($_POST['citta'] ?? ''),
            'cap' => trim($_POST['cap'] ?? ''),
            'provincia' => trim($_POST['provincia'] ?? ''),
            'telefono' => trim($_POST['telefono'] ?? ''),
            'pec' => trim($_POST['pec'] ?? ''),
            'settore' => trim($_POST['settore'] ?? ''),
            'numero_dipendenti' => $_POST['numero_dipendenti'] ? intval($_POST['numero_dipendenti']) : null,
            'stato' => $_POST['stato'] ?? 'attiva',
            'note' => trim($_POST['note'] ?? ''),
            'max_referenti' => $_POST['max_referenti'] ? intval($_POST['max_referenti']) : 5
        ];
        
        // Aggiungi responsabile_id solo se la colonna esiste
        if ($responsabile_column_exists) {
            $data['responsabile_id'] = $_POST['responsabile_id'] ? intval($_POST['responsabile_id']) : null;
        }
        
        // Validazione
        $errors = [];
        
        // Gestione upload logo
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            // Su Windows, usa __DIR__ che è più affidabile
            $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'loghi' . DIRECTORY_SEPARATOR;
            
            // Crea directory se non esiste
            if (!file_exists($uploadDir)) {
                // Su Windows, ignora il parametro mode di mkdir
                if (!mkdir($uploadDir, 0777, true)) {
                    $errors[] = "Impossibile creare la directory di upload: " . $uploadDir;
                }
            }
            
            // Verifica che la directory sia scrivibile
            if (!is_writable($uploadDir)) {
                $errors[] = "La directory di upload non è scrivibile: " . $uploadDir;
            } else {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $maxSize = 2 * 1024 * 1024; // 2MB
                
                if (!in_array($_FILES['logo']['type'], $allowedTypes)) {
                    $errors[] = "Formato logo non supportato. Sono consentiti: JPEG, PNG, GIF, WebP";
                } elseif ($_FILES['logo']['size'] > $maxSize) {
                    $errors[] = "Il logo deve essere massimo 2MB";
                } else {
                    $extension = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                    $fileName = 'logo_' . ($aziendaId ?: 'new_' . time()) . '.' . $extension;
                    $uploadPath = $uploadDir . $fileName;
                    
                    // Debug info
                    error_log("Upload attempt - Source: " . $_FILES['logo']['tmp_name']);
                    error_log("Upload attempt - Destination: " . $uploadPath);
                    
                    if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadPath)) {
                        $data['logo_path'] = '/uploads/loghi/' . $fileName;
                    } else {
                        $uploadError = error_get_last();
                        $errors[] = "Errore durante il caricamento del logo. Dettagli: " . ($uploadError['message'] ?? 'Errore sconosciuto');
                        error_log("Upload failed: " . print_r($uploadError, true));
                    }
                }
            }
        }
        if (empty($data['nome'])) {
            $errors[] = "Il nome è obbligatorio";
        }
        if (empty($data['ragione_sociale'])) {
            $errors[] = "La ragione sociale è obbligatoria";
        }
        if (empty($data['partita_iva'])) {
            $errors[] = "La partita IVA è obbligatoria";
        }
        if (empty($data['indirizzo'])) {
            $errors[] = "L'indirizzo è obbligatorio";
        }
        if (empty($data['citta'])) {
            $errors[] = "La città è obbligatoria";
        }
        if (empty($data['cap'])) {
            $errors[] = "Il CAP è obbligatorio";
        }
        if (empty($data['provincia'])) {
            $errors[] = "La provincia è obbligatoria";
        }
        if (!empty($data['pec']) && !filter_var($data['pec'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "PEC non valida";
        }
        
        if (empty($errors)) {
            try {
                db_connection()->beginTransaction();
                
                if ($action === 'nuovo') {
                    $data['creata_da'] = $user['id'];
                    db_insert('aziende', $data);
                    $aziendaId = db_connection()->lastInsertId();
                    $_SESSION['success'] = "Azienda creata con successo!";
                } else {
                    db_update('aziende', $data, 'id = :id', ['id' => $aziendaId]);
                    $_SESSION['success'] = "Azienda aggiornata con successo!";
                }
                
                // Gestione moduli (solo per Super Admin)
                if ($auth->isSuperAdmin()) {
                    // Prima rimuovi tutti i moduli esistenti per l'azienda
                    db_query("DELETE FROM moduli_azienda WHERE azienda_id = ?", [$aziendaId]);
                    
                    // Poi aggiungi i moduli selezionati
                    $moduli_selezionati = $_POST['moduli'] ?? [];
                    if (!empty($moduli_selezionati)) {
                        foreach ($moduli_selezionati as $modulo_id) {
                            db_query("
                                INSERT INTO moduli_azienda (azienda_id, modulo_id, abilitato, abilitato_da) 
                                VALUES (?, ?, 1, ?)
                            ", [$aziendaId, $modulo_id, $user['id']]);
                        }
                    }
                }
                
                db_connection()->commit();
                redirect(APP_PATH . '/aziende.php?action=view&id=' . $aziendaId);
            } catch (Exception $e) {
                if (db_connection()->inTransaction()) {
                    db_connection()->rollback();
                }
                $error = "Errore durante il salvataggio: " . $e->getMessage();
            }
        } else {
            $error = implode('<br>', $errors);
        }
    } elseif ($action === 'delete' && $aziendaId && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        // Gestione non-AJAX per retrocompatibilità
        $result = deleteAzienda($aziendaId);
        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
                redirect(APP_PATH . '/aziende.php');
        } else {
            $_SESSION['error'] = $result['message'];
                redirect(APP_PATH . '/aziende.php?action=view&id=' . $aziendaId);
        }
    } elseif ($action === 'add_user' && $aziendaId) {
        // Aggiungi utente all'azienda
        $utenteId = $_POST['utente_id'] ?? null;
        $ruoloAzienda = $_POST['ruolo_azienda'] ?? null;
        
        if ($utenteId && $ruoloAzienda) {
            try {
                // Carica informazioni azienda per verificare limiti
                $stmt = db_query("SELECT max_referenti FROM aziende WHERE id = :id", ['id' => $aziendaId]);
                $azienda_info = $stmt->fetch();
                $max_referenti = $azienda_info['max_referenti'] ?? 5;
                
                // Validazioni per il ruolo selezionato
                $errors = [];
                
                if ($ruoloAzienda === 'responsabile_aziendale') {
                    // Verifica che non ci sia già un responsabile aziendale
                    $stmt = db_query("SELECT COUNT(*) as count FROM utenti_aziende WHERE azienda_id = :azienda_id AND ruolo_azienda = 'responsabile_aziendale' AND attivo = 1", 
                                   ['azienda_id' => $aziendaId]);
                    $existing_responsabile = $stmt->fetch()['count'];
                    
                    if ($existing_responsabile > 0) {
                        $errors[] = "Esiste già un Responsabile Aziendale per questa azienda.";
                    }
                } elseif ($ruoloAzienda === 'referente') {
                    // Verifica limite massimo referenti
                    $stmt = db_query("SELECT COUNT(*) as count FROM utenti_aziende WHERE azienda_id = :azienda_id AND ruolo_azienda = 'referente' AND attivo = 1", 
                                   ['azienda_id' => $aziendaId]);
                    $existing_referenti = $stmt->fetch()['count'];
                    
                    if ($existing_referenti >= $max_referenti) {
                        $errors[] = "Limite massimo di $max_referenti referenti raggiunto per questa azienda.";
                    }
                }
                
                if (empty($errors)) {
                    db_insert('utenti_aziende', [
                        'utente_id' => $utenteId,
                        'azienda_id' => $aziendaId,
                        'ruolo_azienda' => $ruoloAzienda,
                        'assegnato_da' => $user['id'],
                        'attivo' => 1
                    ]);
                    
                    $ruoli_nomi = [
                        'responsabile_aziendale' => 'Responsabile Aziendale',
                        'referente' => 'Referente',
                        'ospite' => 'Ospite'
                    ];
                    $nome_ruolo = $ruoli_nomi[$ruoloAzienda] ?? $ruoloAzienda;
                    
                    $_SESSION['success'] = "Utente aggiunto come $nome_ruolo con successo!";
                } else {
                    $_SESSION['error'] = implode('<br>', $errors);
                }
            } catch (Exception $e) {
                $_SESSION['error'] = "Errore: L'utente potrebbe essere già associato a questa azienda.";
            }
            redirect(APP_PATH . '/aziende.php?action=view&id=' . $aziendaId);
        } else {
            $_SESSION['error'] = "Seleziona un utente e un ruolo validi.";
            redirect(APP_PATH . '/aziende.php?action=view&id=' . $aziendaId);
        }
    } elseif ($action === 'update_modules' && $aziendaId) {
        // Aggiorna moduli azienda
        $moduli_selezionati = $_POST['moduli'] ?? [];
        
        try {
            // Inizia transazione
            db_connection()->beginTransaction();
            
            // Carica moduli attualmente attivi
            $stmt = db_query("SELECT modulo_id FROM aziende_moduli WHERE azienda_id = :azienda_id AND attivo = 1", 
                           ['azienda_id' => $aziendaId]);
            $moduli_attuali = array_column($stmt->fetchAll(), 'modulo_id');
            
            // Moduli da aggiungere
            $da_aggiungere = array_diff($moduli_selezionati, $moduli_attuali);
            
            // Moduli da disattivare
            $da_disattivare = array_diff($moduli_attuali, $moduli_selezionati);
            
            // Aggiungi nuovi moduli
            foreach ($da_aggiungere as $modulo_id) {
                // Verifica se esiste già un record disattivato
                $stmt = db_query("SELECT id FROM aziende_moduli WHERE azienda_id = :azienda_id AND modulo_id = :modulo_id", 
                               ['azienda_id' => $aziendaId, 'modulo_id' => $modulo_id]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    // Riattiva il modulo esistente
                    db_update('aziende_moduli', 
                            ['attivo' => 1, 'data_attivazione' => date('Y-m-d H:i:s'), 'attivato_da' => $user['id']], 
                            'id = :id', 
                            ['id' => $existing['id']]);
                } else {
                    // Crea nuovo record
                    db_insert('aziende_moduli', [
                        'azienda_id' => $aziendaId,
                        'modulo_id' => $modulo_id,
                        'attivo' => 1,
                        'attivato_da' => $user['id']
                    ]);
                }
            }
            
            // Disattiva moduli rimossi
            foreach ($da_disattivare as $modulo_id) {
                db_update('aziende_moduli', 
                        ['attivo' => 0, 'data_disattivazione' => date('Y-m-d H:i:s'), 'disattivato_da' => $user['id']], 
                        'azienda_id = :azienda_id AND modulo_id = :modulo_id', 
                        ['azienda_id' => $aziendaId, 'modulo_id' => $modulo_id]);
            }
            
            // Conferma transazione
            db_connection()->commit();
            
            $_SESSION['success'] = "Moduli aggiornati con successo!";
            
            // Log attività
            $logger = ActivityLogger::getInstance();
            $logger->log('azienda', 'aggiornamento_moduli', $aziendaId, 
                        ['aggiunti' => count($da_aggiungere), 'rimossi' => count($da_disattivare)]);
            
        } catch (Exception $e) {
            db_connection()->rollBack();
            $_SESSION['error'] = "Errore durante l'aggiornamento dei moduli: " . $e->getMessage();
        }
        
        redirect(APP_PATH . '/aziende.php?action=view&id=' . $aziendaId);
    } elseif ($action === 'remove_user' && $aziendaId) {
        // Rimuovi utente dall'azienda
        $utenteId = $_POST['utente_id'] ?? null;
        
        if ($utenteId) {
            db_update('utenti_aziende', 
                ['attivo' => 0], 
                'utente_id = :utente_id AND azienda_id = :azienda_id', 
                ['utente_id' => $utenteId, 'azienda_id' => $aziendaId]
            );
            $_SESSION['success'] = "Utente rimosso dall'azienda!";
            redirect(APP_PATH . '/aziende.php?action=view&id=' . $aziendaId);
        }
    }
}

// Initialize variables to avoid undefined variable issues
$azienda = null;
$utentiAzienda = [];
$responsabile_column_exists = false;

// Carica dati per le varie azioni
if ($action === 'view' && $aziendaId) {
    // Dettaglio azienda - verifica se la colonna responsabile_id esiste
    
    try {
        // Prova a selezionare con responsabile_id
        $stmt = db_query("SELECT *, responsabile_id FROM aziende WHERE id = :id", ['id' => $aziendaId]);
        $azienda = $stmt->fetch(PDO::FETCH_ASSOC); // Use FETCH_ASSOC for consistency
        $responsabile_column_exists = true;
    } catch (Exception $e) {
        // Se fallisce, prova senza responsabile_id
        $stmt = db_query("SELECT * FROM aziende WHERE id = :id", ['id' => $aziendaId]);
        $azienda = $stmt->fetch(PDO::FETCH_ASSOC); // Use FETCH_ASSOC for consistency
        if ($azienda) {
            $azienda['responsabile_id'] = null;
        }
        $responsabile_column_exists = false;
    }
    
    if (!$azienda) {
        $_SESSION['error'] = "Azienda non trovata.";
        redirect(APP_PATH . '/aziende.php');
    }
    
    // Carica utenti dell'azienda
    $stmt = db_query("
        SELECT ua.*, u.nome, u.cognome, u.email, u.username
        FROM utenti_aziende ua
        JOIN utenti u ON ua.utente_id = u.id
        WHERE ua.azienda_id = :azienda_id AND ua.attivo = 1
        ORDER BY u.cognome, u.nome
    ", ['azienda_id' => $aziendaId]);
    $utentiAzienda = $stmt->fetchAll();
    
    // Carica statistiche con query dirette
    $stats = [];
    try {
        // Conta utenti attivi per l'azienda
        $stmt = db_query("SELECT COUNT(DISTINCT ua.utente_id) as numero_utenti FROM utenti_aziende ua WHERE ua.azienda_id = :id AND ua.attivo = 1", ['id' => $aziendaId]);
        $utenti_result = $stmt->fetch();
        $stats['numero_utenti'] = $utenti_result['numero_utenti'] ?? 0;
        
        // Conta referenti attivi per l'azienda  
        $stmt = db_query("SELECT COUNT(*) as numero_referenti FROM referenti WHERE azienda_id = :id AND attivo = 1", ['id' => $aziendaId]);
        $referenti_result = $stmt->fetch();
        $stats['numero_referenti'] = $referenti_result['numero_referenti'] ?? 0;
        
        // Conta documenti per l'azienda
        $stmt = db_query("SELECT COUNT(*) as numero_documenti FROM documenti WHERE azienda_id = :id", ['id' => $aziendaId]);
        $documenti_result = $stmt->fetch();
        $stats['numero_documenti'] = $documenti_result['numero_documenti'] ?? 0;
        
        // Conta eventi per l'azienda
        $stmt = db_query("SELECT COUNT(*) as numero_eventi FROM eventi WHERE azienda_id = :id", ['id' => $aziendaId]);
        $eventi_result = $stmt->fetch();
        $stats['numero_eventi'] = $eventi_result['numero_eventi'] ?? 0;
        
        // Conta tickets aperti per l'azienda
        $stmt = db_query("SELECT COUNT(*) as tickets_aperti FROM tickets WHERE azienda_id = :id AND stato IN ('aperto', 'in_lavorazione')", ['id' => $aziendaId]);
        $tickets_result = $stmt->fetch();
        $stats['tickets_aperti'] = $tickets_result['tickets_aperti'] ?? 0;
        
    } catch (Exception $e) {
        error_log("Errore nel caricamento statistiche azienda {$aziendaId}: " . $e->getMessage());
        $stats = [
            'numero_utenti' => 0,
            'numero_referenti' => 0, 
            'numero_documenti' => 0,
            'numero_eventi' => 0,
            'tickets_aperti' => 0
        ];
    }
    
} elseif ($action === 'edit' && $aziendaId) {
    // Modifica azienda - verifica se la colonna responsabile_id esiste
    $responsabile_column_exists = false;
    $azienda = null; // Initialize to avoid undefined variable issues
    
    try {
        // Prova a selezionare con responsabile_id
        $stmt = db_query("SELECT *, responsabile_id FROM aziende WHERE id = :id", ['id' => $aziendaId]);
        $azienda = $stmt->fetch(PDO::FETCH_ASSOC); // Use FETCH_ASSOC to ensure array format
        $responsabile_column_exists = true;
    } catch (Exception $e) {
        // Se fallisce, prova senza responsabile_id
        $stmt = db_query("SELECT * FROM aziende WHERE id = :id", ['id' => $aziendaId]);
        $azienda = $stmt->fetch(PDO::FETCH_ASSOC); // Use FETCH_ASSOC to ensure array format
        if ($azienda) {
            $azienda['responsabile_id'] = null;
        }
        $responsabile_column_exists = false;
    }
    
    if (!$azienda) {
        $_SESSION['error'] = "Azienda non trovata.";
        redirect(APP_PATH . '/aziende.php');
    }
    
    // Debug: Log the loaded company data to verify it's correct
    error_log("Edit Company Data Loaded: " . json_encode($azienda));
    
    // Ensure $azienda is properly defined for form use
    if (!is_array($azienda)) {
        $_SESSION['error'] = "Errore nel caricamento dati azienda.";
        redirect(APP_PATH . '/aziende.php');
    }
} else {
    // Lista aziende - verifica se la colonna responsabile_id esiste e carica statistiche
    // Clear any potential caching issues
    $responsabile_column_exists = false;
    $aziende = [];
    
    try {
        // First check if responsabile_id column exists
        $check_stmt = db_query("SHOW COLUMNS FROM aziende LIKE 'responsabile_id'");
        $responsabile_column_exists = $check_stmt && $check_stmt->fetch();
        
        // Build query based on column existence - use explicit SELECT to avoid duplicates
        if ($responsabile_column_exists) {
            $query = "SELECT a.id, a.nome, a.ragione_sociale, a.partita_iva, a.codice_fiscale, a.indirizzo, a.citta, a.cap, a.provincia, a.telefono, a.email, a.pec, a.settore, a.numero_dipendenti, a.stato, a.note, a.max_referenti, a.logo_path, a.data_creazione, a.responsabile_id FROM aziende a WHERE a.stato = 'attiva' ORDER BY a.nome";
        } else {
            $query = "SELECT a.id, a.nome, a.ragione_sociale, a.partita_iva, a.codice_fiscale, a.indirizzo, a.citta, a.cap, a.provincia, a.telefono, a.email, a.pec, a.settore, a.numero_dipendenti, a.stato, a.note, a.max_referenti, a.logo_path, a.data_creazione FROM aziende a WHERE a.stato = 'attiva' ORDER BY a.nome";
        }
        
        $stmt = db_query($query);
        $aziende_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Remove any potential duplicates by ID - ensure unique companies only
        $aziende = [];
        $seen_ids = [];
        foreach ($aziende_raw as $company_item) {
            if (!in_array($company_item['id'], $seen_ids)) {
                $seen_ids[] = $company_item['id'];
                if (!$responsabile_column_exists) {
                    $company_item['responsabile_id'] = null;
                }
                $aziende[] = $company_item;
            }
        }
        
    } catch (Exception $e) {
        error_log("Error loading companies: " . $e->getMessage());
        $aziende = [];
        $responsabile_column_exists = false;
    }
    
    // Load statistics for each company with separate queries - avoid reference issues
    $aziende_with_stats = [];
    foreach ($aziende as $azienda_item) {
        $azienda = $azienda_item; // Create a copy to avoid reference issues
        try {
            // Count active users for company
            $stmt = db_query("SELECT COUNT(DISTINCT ua.utente_id) as numero_utenti FROM utenti_aziende ua WHERE ua.azienda_id = ? AND ua.attivo = 1", [$azienda['id']]);
            $utenti_result = $stmt->fetch(PDO::FETCH_ASSOC);
            $azienda['numero_utenti'] = intval($utenti_result['numero_utenti'] ?? 0);
            
            // Count active referenti for company - handle table existence
            try {
                $stmt = db_query("SELECT COUNT(*) as numero_referenti FROM referenti_aziende WHERE azienda_id = ? AND attivo = 1", [$azienda['id']]);
                $referenti_result = $stmt->fetch(PDO::FETCH_ASSOC);
                $azienda['numero_referenti'] = intval($referenti_result['numero_referenti'] ?? 0);
            } catch (Exception $ref_e) {
                // Fallback to legacy referenti table if referenti_aziende doesn't exist
                try {
                    $stmt = db_query("SELECT COUNT(*) as numero_referenti FROM referenti WHERE azienda_id = ? AND attivo = 1", [$azienda['id']]);
                    $referenti_result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $azienda['numero_referenti'] = intval($referenti_result['numero_referenti'] ?? 0);
                } catch (Exception $ref_e2) {
                    $azienda['numero_referenti'] = 0;
                }
            }
            
            // Count documents for company
            try {
                $stmt = db_query("SELECT COUNT(*) as numero_documenti FROM documenti WHERE azienda_id = ?", [$azienda['id']]);
                $documenti_result = $stmt->fetch(PDO::FETCH_ASSOC);
                $azienda['numero_documenti'] = intval($documenti_result['numero_documenti'] ?? 0);
            } catch (Exception $doc_e) {
                $azienda['numero_documenti'] = 0;
            }
            
            // Count events for company
            try {
                $stmt = db_query("SELECT COUNT(*) as numero_eventi FROM eventi WHERE azienda_id = ?", [$azienda['id']]);
                $eventi_result = $stmt->fetch(PDO::FETCH_ASSOC);
                $azienda['numero_eventi'] = intval($eventi_result['numero_eventi'] ?? 0);
            } catch (Exception $ev_e) {
                $azienda['numero_eventi'] = 0;
            }
            
            // Count open tickets for company
            try {
                $stmt = db_query("SELECT COUNT(*) as tickets_aperti FROM tickets WHERE azienda_id = ? AND stato IN ('aperto', 'in_lavorazione')", [$azienda['id']]);
                $tickets_result = $stmt->fetch(PDO::FETCH_ASSOC);
                $azienda['tickets_aperti'] = intval($tickets_result['tickets_aperti'] ?? 0);
            } catch (Exception $tick_e) {
                $azienda['tickets_aperti'] = 0;
            }
            
        } catch (Exception $e) {
            error_log("Error loading stats for company {$azienda['id']}: " . $e->getMessage());
            $azienda['numero_utenti'] = 0;
            $azienda['numero_referenti'] = 0;
            $azienda['numero_documenti'] = 0;
            $azienda['numero_eventi'] = 0;
            $azienda['tickets_aperti'] = 0;
        }
        
        $aziende_with_stats[] = $azienda;
    }
    
    // Replace the original array with the one including stats
    $aziende = $aziende_with_stats;
}

$pageTitle = 'Gestione Aziende';
require_once 'components/header.php';
?>

<style>
/* Page header styles moved to main style.css */
    /* Variabili CSS Nexio */
    :root {
        --primary-color: #2d5a9f;
        --primary-dark: #0f2847;
        --primary-light: #2a5a9f;
        --border-color: #e8e8e8;
        --text-primary: #2c2c2c;
        --text-secondary: #6b6b6b;
        --bg-primary: #faf8f5;
        --bg-secondary: #ffffff;
        --font-sans: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    }

    body {
        font-family: var(--font-sans);
        color: var(--text-primary);
    }

    /* Using page-header from dashboard instead of content-header */
    
    .btn {
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: 10px;
        font-size: 15px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
    }
    
    .btn-primary {
        background: var(--primary-color);
        color: white;
    }
    
    .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(43, 87, 154, 0.3);
    }
    
    .btn-secondary {
        background: var(--bg-primary);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
    }
    
    .btn-secondary:hover {
        background: var(--border-color);
    }
    
    .btn-danger {
        background: #ef4444;
        color: white;
    }
    
    .btn-danger:hover {
        background: #dc2626;
    }
    
    .btn-small {
        padding: 0.5rem 1rem;
        font-size: 14px;
    }
    
    .form-container {
        background: var(--bg-secondary);
        border-radius: 12px;
        padding: 2rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        border: 1px solid var(--border-color);
        margin-bottom: 2rem;
    }
    
    .form-container h2 {
        color: var(--text-primary);
        font-size: 1.5rem;
        font-weight: 600;
        margin-bottom: 1.5rem;
    }
    
    .form-group {
        margin-bottom: 1.5rem;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        color: var(--text-primary);
        font-weight: 600;
        font-size: 14px;
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        font-size: 15px;
        transition: all 0.3s ease;
        background: var(--bg-secondary);
        font-family: var(--font-sans);
    }
    
    .form-group input[type="file"] {
        padding: 0.5rem;
        background: var(--bg-primary);
        border: 2px dashed var(--border-color);
        cursor: pointer;
    }
    
    .form-group input[type="file"]:hover {
        border-color: var(--primary-color);
        background: rgba(43, 87, 154, 0.05);
    }
    
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(43, 87, 154, 0.1);
    }
    
    .form-text {
        display: block;
        margin-top: 0.25rem;
        font-size: 13px;
        color: var(--text-secondary);
    }
    
    .required {
        color: #ef4444;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
    }
    
    .form-actions {
        display: flex;
        gap: 1rem;
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 1px solid var(--border-color);
    }
    
    .alert {
        padding: 1rem 1.5rem;
        border-radius: 10px;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }
    
    .alert-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
    }
    
    .info-item {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .info-label {
        font-size: 13px;
        color: var(--text-secondary);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .info-value {
        font-size: 16px;
        color: var(--text-primary);
        font-weight: 500;
    }
    
    .status-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        font-size: 13px;
        font-weight: 500;
        border-radius: 6px;
    }
    
    .status-attiva {
        background: #d1fae5;
        color: #065f46;
    }
    
    .status-sospesa {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    /* Stat card styles moved to dashboard-clean.css */
    
    .aziende-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .azienda-card {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        border: 1px solid #e5e7eb;
        transition: all 0.2s ease;
    }
    
    .azienda-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        transform: translateY(-2px);
    }
    
    .azienda-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 15px;
    }
    
    .azienda-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 5px;
    }
    
    .azienda-stats {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid var(--border-color);
    }
    
    .azienda-stat {
        text-align: center;
    }
    
    .azienda-stat-value {
        font-size: 20px;
        font-weight: 600;
        color: var(--text-primary);
    }
    
    .azienda-stat-label {
        font-size: 12px;
        color: var(--text-secondary);
    }
    
    .users-grid {
        display: grid;
        gap: 10px;
        margin-top: 20px;
    }
    
    .user-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px;
        background: var(--bg-primary);
        border-radius: 8px;
        border: 1px solid var(--border-color);
    }
    
    .add-user-form {
        background: var(--bg-primary);
        padding: 20px;
        border-radius: 10px;
        margin-top: 20px;
        border: 1px solid var(--border-color);
    }
    
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: var(--text-secondary);
    }
    
    .empty-state i {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.3;
    }
    
    .empty-state h2 {
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }
    
    @media (max-width: 768px) {
        
        .form-row {
            grid-template-columns: 1fr;
        }
        
        .aziende-grid {
            grid-template-columns: 1fr;
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<?php
// Ensure $azienda is properly initialized for forms
if ($action === 'nuovo' && !isset($azienda)) {
    $azienda = []; // Initialize empty array for new company form
} elseif ($action === 'edit' && !isset($azienda)) {
    // This should not happen if edit logic above worked, but safety check
    $_SESSION['error'] = "Errore nel caricamento dati azienda per modifica.";
    redirect(APP_PATH . '/aziende.php');
}
?>

<div class="content-wrapper">
    <?php if ($action === 'nuovo' || $action === 'edit'): ?>
    <!-- Form Azienda Header -->
    <div class="page-header">
        <h1><i class="fas fa-building"></i> <?php echo $action === 'nuovo' ? 'Nuova Azienda' : 'Modifica Azienda'; ?></h1>
        <div class="page-subtitle">Inserisci o modifica i dati dell'azienda</div>
    </div>
    
    <?php elseif ($action === 'view' && $azienda): ?>
    <!-- Dettaglio Azienda Header -->
    <div class="page-header">
        <h1><i class="fas fa-building"></i> <?php echo htmlspecialchars($azienda['nome']); ?></h1>
        <div class="page-subtitle">Dettagli e statistiche dell'azienda</div>
    </div>
    
    <?php else: ?>
    <!-- Lista Aziende Header -->
    <div class="page-header">
        <h1><i class="fas fa-building"></i> Gestione Aziende</h1>
        <div class="page-subtitle">Amministra le aziende del sistema</div>
    </div>
    
    <!-- Action button moved outside header -->
    <div style="margin-bottom: 2rem;">
        <a href="<?php echo APP_PATH; ?>/aziende.php?action=nuovo" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nuova Azienda
        </a>
    </div>
    
    <?php endif; ?>

<?php if ($action === 'nuovo' || $action === 'edit'): ?>
    <!-- Form Azienda -->
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="content-card">
        <form method="post" action="" enctype="multipart/form-data">
            <div class="form-row">
                <div class="form-group">
                    <label for="nome">Nome Azienda <span class="required">*</span></label>
                    <input type="text" id="nome" name="nome" required 
                           value="<?php echo htmlspecialchars($_POST['nome'] ?? $azienda['nome'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="ragione_sociale">Ragione Sociale <span class="required">*</span></label>
                    <input type="text" id="ragione_sociale" name="ragione_sociale" required
                           value="<?php echo htmlspecialchars($_POST['ragione_sociale'] ?? $azienda['ragione_sociale'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="partita_iva">Partita IVA <span class="required">*</span></label>
                    <input type="text" id="partita_iva" name="partita_iva" maxlength="20" required
                           value="<?php echo htmlspecialchars($_POST['partita_iva'] ?? $azienda['partita_iva'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="codice_fiscale">Codice Fiscale</label>
                    <input type="text" id="codice_fiscale" name="codice_fiscale" maxlength="20"
                           value="<?php echo htmlspecialchars($_POST['codice_fiscale'] ?? $azienda['codice_fiscale'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="indirizzo">Indirizzo <span class="required">*</span></label>
                <input type="text" id="indirizzo" name="indirizzo" required
                       value="<?php echo htmlspecialchars($_POST['indirizzo'] ?? $azienda['indirizzo'] ?? ''); ?>">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="citta">Città <span class="required">*</span></label>
                    <input type="text" id="citta" name="citta" required
                           value="<?php echo htmlspecialchars($_POST['citta'] ?? $azienda['citta'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="cap">CAP <span class="required">*</span></label>
                    <input type="text" id="cap" name="cap" maxlength="10" required
                           value="<?php echo htmlspecialchars($_POST['cap'] ?? $azienda['cap'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="provincia">Provincia <span class="required">*</span></label>
                    <input type="text" id="provincia" name="provincia" maxlength="2" required
                           value="<?php echo htmlspecialchars($_POST['provincia'] ?? $azienda['provincia'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="telefono">Telefono</label>
                    <input type="tel" id="telefono" name="telefono" 
                           value="<?php echo htmlspecialchars($_POST['telefono'] ?? $azienda['telefono'] ?? ''); ?>">
                </div>
                
                
                <div class="form-group">
                    <label for="pec">PEC</label>
                    <input type="email" id="pec" name="pec" 
                           value="<?php echo htmlspecialchars($_POST['pec'] ?? $azienda['pec'] ?? ''); ?>">
                </div>
            </div>
            
            <!-- Sezione Logo -->
            <div class="form-group">
                <label for="logo">Logo Aziendale</label>
                <input type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/gif,image/webp">
                <small class="form-text">Formato supportati: JPEG, PNG, GIF, WebP. Dimensione massima: 2MB</small>
                <?php if (isset($azienda['logo_path']) && !empty($azienda['logo_path'])): ?>
                    <div style="margin-top: 10px;">
                        <img src="<?php echo APP_PATH . htmlspecialchars($azienda['logo_path']); ?>" 
                             alt="Logo attuale" style="max-width: 200px; max-height: 100px; border: 1px solid #ddd; border-radius: 4px;">
                        <p style="font-size: 12px; color: #666; margin-top: 5px;">Logo attuale (verrà sostituito se carichi un nuovo file)</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="settore">Settore</label>
                    <input type="text" id="settore" name="settore" 
                           value="<?php echo htmlspecialchars($_POST['settore'] ?? $azienda['settore'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="numero_dipendenti">Numero Dipendenti</label>
                    <input type="number" id="numero_dipendenti" name="numero_dipendenti" min="0"
                           value="<?php echo htmlspecialchars($_POST['numero_dipendenti'] ?? $azienda['numero_dipendenti'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="max_referenti">Max Referenti/Utenti <span class="required">*</span></label>
                    <input type="number" id="max_referenti" name="max_referenti" min="1" required
                           value="<?php echo htmlspecialchars($_POST['max_referenti'] ?? $azienda['max_referenti'] ?? 5); ?>">
                    <small class="form-text">Numero massimo di utenti che possono essere associati all'azienda</small>
                </div>
            </div>
            
            <?php
            // Mostra il campo responsabile solo se la colonna esiste nel database
            $show_responsabile_field = false;
            try {
                // Prova a fare una query che usa la colonna responsabile_id
                $stmt = db_query("SELECT responsabile_id FROM aziende WHERE id = :id LIMIT 1", ['id' => $aziendaId]);
                $show_responsabile_field = true;
            } catch (Exception $e) {
                // Se la query fallisce, la colonna probabilmente non esiste
                $show_responsabile_field = false;
            }
            
            if ($show_responsabile_field): ?>
            <div class="form-row">
                <div class="form-group">
                    <label for="responsabile_id">Responsabile Azienda</label>
                    <select id="responsabile_id" name="responsabile_id">
                        <option value="">-- Seleziona un responsabile --</option>
                        <?php
                        // Carica tutti gli utenti attivi per la selezione del responsabile
                        $stmt = db_query("SELECT id, nome, cognome, email FROM utenti WHERE attivo = 1 ORDER BY cognome, nome");
                        $utenti = $stmt->fetchAll();
                        
                        foreach ($utenti as $utente): ?>
                            <option value="<?php echo $utente['id']; ?>" 
                                    <?php echo ($_POST['responsabile_id'] ?? $azienda['responsabile_id'] ?? '') == $utente['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($utente['cognome'] . ' ' . $utente['nome'] . ' (' . $utente['email'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text">Il responsabile deve essere creato prima nella sezione Gestione Utenti. Senza responsabile l'azienda non sarà utilizzabile (eccetto per super admin).</small>
                </div>
            <?php else: ?>
            <div class="form-row">
                <div class="form-group">
                    <div style="background: #fef3cd; border: 1px solid #fde68a; border-radius: 8px; padding: 10px; color: #92400e;">
                        <i class="fas fa-info-circle"></i> <strong>Nota:</strong> Per abilitare la gestione dei responsabili azienda, esegui lo script <code>add-responsabile-column.php</code>
                    </div>
                </div>
            <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="stato">Stato</label>
                <select id="stato" name="stato">
                    <option value="attiva" <?php echo ($_POST['stato'] ?? $azienda['stato'] ?? '') == 'attiva' ? 'selected' : ''; ?>>Attiva</option>
                    <option value="sospesa" <?php echo ($_POST['stato'] ?? $azienda['stato'] ?? '') == 'sospesa' ? 'selected' : ''; ?>>Sospesa</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="note">Note</label>
                <textarea id="note" name="note" rows="3"><?php echo htmlspecialchars($_POST['note'] ?? $azienda['note'] ?? ''); ?></textarea>
            </div>
            
            <?php if ($auth->isSuperAdmin()): ?>
            <!-- Sezione Moduli Abilitati (solo per Super Admin) -->
            <div class="form-section" style="margin-top: 30px; padding: 20px; background: #f3f4f6; border-radius: 8px;">
                <h3 style="margin-bottom: 15px;"><i class="fas fa-puzzle-piece"></i> Moduli Abilitati</h3>
                <p style="color: #6b7280; margin-bottom: 20px;">Seleziona i moduli che saranno disponibili per questa azienda.</p>
                
                <?php
                // Carica solo i moduli che devono essere controllati manualmente
                $stmt = db_query("
                    SELECT * FROM moduli_sistema 
                    WHERE attivo = 1 
                    AND codice IN ('calendario', 'tickets', 'conformita_normativa', 'nexio_ai')
                    ORDER BY ordine, nome
                ");
                $moduli_disponibili = $stmt->fetchAll();
                
                // Se stiamo modificando, carica moduli attivi per l'azienda
                $moduli_attivi_ids = [];
                if ($action === 'edit' && $aziendaId) {
                    $stmt = db_query("
                        SELECT modulo_id 
                        FROM moduli_azienda 
                        WHERE azienda_id = ? AND abilitato = 1
                    ", [$aziendaId]);
                    $moduli_attivi_ids = array_column($stmt->fetchAll(), 'modulo_id');
                }
                ?>
                
                <div style="display: grid; gap: 10px;">
                    <?php foreach ($moduli_disponibili as $modulo): ?>
                    <label style="display: flex; align-items: center; gap: 10px; padding: 12px; background: white; border-radius: 8px; border: 1px solid #e5e7eb; cursor: pointer; transition: all 0.2s;">
                        <input type="checkbox" name="moduli[]" value="<?php echo $modulo['id']; ?>" 
                               <?php echo in_array($modulo['id'], $moduli_attivi_ids) ? 'checked' : ''; ?>
                               style="width: 18px; height: 18px;">
                        <div style="background: #667eea; color: white; width: 30px; height: 30px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 14px;">
                            <i class="<?php echo $modulo['icona']; ?>"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 500;"><?php echo htmlspecialchars($modulo['nome']); ?></div>
                            <div style="font-size: 12px; color: #6b7280;"><?php echo htmlspecialchars($modulo['descrizione']); ?></div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> <?php echo $action === 'nuovo' ? 'Crea Azienda' : 'Salva Modifiche'; ?>
                </button>
                <a href="<?php echo APP_PATH; ?>/aziende.php<?php echo $action === 'edit' ? '?action=view&id=' . $aziendaId : ''; ?>" class="btn btn-secondary">Annulla</a>
            </div>
        </form>
    </div>
    
<?php elseif ($action === 'view' && $azienda): ?>
    <!-- Dettaglio Azienda -->
    <div class="action-bar" style="margin-bottom: 1.5rem;">
        <a href="<?php echo APP_PATH; ?>/referenti.php?azienda_id=<?php echo $azienda['id']; ?>" class="btn btn-primary">
            <i class="fas fa-users"></i> Gestisci Referenti
        </a>
        <a href="<?php echo APP_PATH; ?>/aziende.php?action=edit&id=<?php echo $azienda['id']; ?>" class="btn btn-secondary">
            <i class="fas fa-edit"></i> Modifica Azienda
        </a>
        <?php if ($auth->isSuperAdmin()): ?>
        <button type="button" class="btn btn-danger" onclick="confirmDeleteAzienda(<?php echo $azienda['id']; ?>, '<?php echo htmlspecialchars($azienda['nome']); ?>')">
            <i class="fas fa-trash"></i> Elimina Azienda
        </button>
        <?php endif; ?>
    </div>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    
    <!-- Informazioni Azienda -->
    <div class="content-card">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
            <h2 style="margin: 0;">Informazioni Azienda</h2>
            <?php if (isset($azienda['logo_path']) && !empty($azienda['logo_path'])): ?>
                <div style="text-align: right;">
                    <img src="<?php echo APP_PATH . htmlspecialchars($azienda['logo_path']); ?>" 
                         alt="Logo <?php echo htmlspecialchars($azienda['nome']); ?>" 
                         style="max-width: 150px; max-height: 80px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                </div>
            <?php endif; ?>
        </div>
        
        <div class="info-grid">
            <?php if ($azienda['ragione_sociale']): ?>
            <div class="info-item">
                <div class="info-label">Ragione Sociale</div>
                <div class="info-value"><?php echo htmlspecialchars($azienda['ragione_sociale']); ?></div>
            </div>
            <?php endif; ?>
            
            <?php if ($azienda['partita_iva']): ?>
            <div class="info-item">
                <div class="info-label">Partita IVA</div>
                <div class="info-value"><?php echo htmlspecialchars($azienda['partita_iva']); ?></div>
            </div>
            <?php endif; ?>
            
            <?php if ($azienda['codice_fiscale']): ?>
            <div class="info-item">
                <div class="info-label">Codice Fiscale</div>
                <div class="info-value"><?php echo htmlspecialchars($azienda['codice_fiscale']); ?></div>
            </div>
            <?php endif; ?>
            
            <div class="info-item">
                <div class="info-label">Stato</div>
                <div class="info-value">
                    <span class="status-badge status-<?php echo $azienda['stato']; ?>">
                        <?php echo ucfirst($azienda['stato']); ?>
                    </span>
                </div>
            </div>
        </div>
        
        <?php if ($azienda['indirizzo'] || $azienda['citta'] || $azienda['cap']): ?>
        <div class="info-item" style="margin-top: 20px;">
            <div class="info-label">Indirizzo</div>
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
        
        <div class="info-grid" style="margin-top: 20px;">
            <?php if ($azienda['telefono']): ?>
            <div class="info-item">
                <div class="info-label">Telefono</div>
                <div class="info-value"><?php echo htmlspecialchars($azienda['telefono']); ?></div>
            </div>
            <?php endif; ?>
            
            <?php if ($azienda['email']): ?>
            <div class="info-item">
                <div class="info-label">Email</div>
                <div class="info-value"><?php echo htmlspecialchars($azienda['email']); ?></div>
            </div>
            <?php endif; ?>
            
            <?php if ($azienda['pec']): ?>
            <div class="info-item">
                <div class="info-label">PEC</div>
                <div class="info-value"><?php echo htmlspecialchars($azienda['pec']); ?></div>
            </div>
            <?php endif; ?>
            
            <?php
            // Mostra informazioni responsabile solo se la colonna esiste
            // Usa la variabile già impostata precedentemente durante il caricamento dei dati
            // Se non è definita, prova un controllo diretto
            if (!isset($responsabile_column_exists)) {
                $responsabile_column_exists = false;
                try {
                    $stmt = db_query("SELECT responsabile_id FROM aziende WHERE id = :id LIMIT 1", ['id' => $azienda['id']]);
                    $responsabile_column_exists = true;
                } catch (Exception $e) {
                    $responsabile_column_exists = false;
                }
            }
            
            if ($responsabile_column_exists):
                if ($azienda['responsabile_id']): 
                    $stmt = db_query("SELECT nome, cognome, email FROM utenti WHERE id = :id", ['id' => $azienda['responsabile_id']]);
                    $responsabile = $stmt->fetch();
                    if ($responsabile):
            ?>
            <div class="info-item">
                <div class="info-label">Responsabile</div>
                <div class="info-value"><?php echo htmlspecialchars($responsabile['nome'] . ' ' . $responsabile['cognome'] . ' (' . $responsabile['email'] . ')'); ?></div>
            </div>
            <?php 
                    endif; 
                else: 
            ?>
            <div class="info-item">
                <div class="info-label">Responsabile</div>
                <div class="info-value" style="color: #ef4444;">⚠️ Nessun responsabile assegnato - Azienda non utilizzabile</div>
            </div>
            <?php 
                endif;
            endif; 
            ?>
        </div>
    </div>
    
    <!-- Statistiche -->
    <div class="stats-grid" style="margin-top: 30px;">
        <div class="stat-card">
            <div class="stat-icon" style="background: #dbeafe; color: #1e40af;">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-value" style="font-size: 32px; font-weight: 700; color: #1a202c; margin-bottom: 5px;">
                <?php echo $stats['numero_utenti'] ?? 0; ?>
            </div>
            <div class="stat-label" style="color: #718096; font-size: 14px;">Utenti Sistema</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #e9d5ff; color: #6b21a8;">
                <i class="fas fa-building"></i>
            </div>
            <div class="stat-value" style="font-size: 32px; font-weight: 700; color: #1a202c; margin-bottom: 5px;">
                <?php 
                $stmt_ref = db_query("SELECT COUNT(*) as count FROM referenti WHERE azienda_id = :id AND attivo = 1", ['id' => $azienda['id']]);
                echo $stmt_ref->fetch()['count'] ?? 0;
                ?>
            </div>
            <div class="stat-label" style="color: #718096; font-size: 14px;">Referenti Azienda</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #d1fae5; color: #047857;">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="stat-value" style="font-size: 32px; font-weight: 700; color: #1a202c; margin-bottom: 5px;">
                <?php echo $stats['numero_documenti'] ?? 0; ?>
            </div>
            <div class="stat-label" style="color: #718096; font-size: 14px;">Documenti</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #fef3c7; color: #92400e;">
                <i class="fas fa-calendar"></i>
            </div>
            <div class="stat-value" style="font-size: 32px; font-weight: 700; color: #1a202c; margin-bottom: 5px;">
                <?php echo $stats['numero_eventi'] ?? 0; ?>
            </div>
            <div class="stat-label" style="color: #718096; font-size: 14px;">Eventi</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #fee2e2; color: #b91c1c;">
                <i class="fas fa-ticket-alt"></i>
            </div>
            <div class="stat-value" style="font-size: 32px; font-weight: 700; color: #1a202c; margin-bottom: 5px;">
                <?php echo $stats['tickets_aperti'] ?? 0; ?>
            </div>
            <div class="stat-label" style="color: #718096; font-size: 14px;">Tickets Aperti</div>
        </div>
    </div>
    
    <!-- Moduli Abilitati -->
    <div class="content-card" style="margin-top: 30px;">
        <h2 style="margin-bottom: 20px;"><i class="fas fa-puzzle-piece"></i> Moduli Abilitati</h2>
        
        <?php
        // Carica SOLO i moduli che richiedono abilitazione manuale
        $stmt = db_query("
            SELECT ms.*, ma.abilitato
            FROM moduli_sistema ms
            LEFT JOIN moduli_azienda ma ON ms.id = ma.modulo_id AND ma.azienda_id = :azienda_id
            WHERE ms.codice IN ('calendario', 'tickets', 'conformita_normativa', 'nexio_ai')
            AND ms.attivo = 1
            ORDER BY ms.ordine, ms.nome
        ", ['azienda_id' => $aziendaId]);
        $moduli_controllati = $stmt->fetchAll();
        
        // Crea array per controllo rapido dei moduli abilitati
        $moduli_attivi_ids = [];
        foreach ($moduli_controllati as $mod) {
            if ($mod['abilitato']) {
                $moduli_attivi_ids[] = $mod['id'];
            }
        }
        ?>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px; margin-bottom: 20px;">
            <?php foreach ($moduli_controllati as $modulo): ?>
            <?php if ($modulo['abilitato']): ?>
            <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px;">
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 10px;">
                    <div style="background: #dbeafe; color: #1e40af; width: 48px; height: 48px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                        <i class="<?php echo $modulo['icona']; ?>"></i>
                    </div>
                    <div style="flex: 1;">
                        <h4 style="margin: 0; color: #1f2937;"><?php echo htmlspecialchars($modulo['nome']); ?></h4>
                        <p style="margin: 5px 0 0 0; font-size: 13px; color: #6b7280;">
                            <?php echo htmlspecialchars($modulo['descrizione']); ?>
                        </p>
                    </div>
                </div>
                <div style="margin-top: 10px; padding: 8px; background: rgba(0,0,0,0.05); border-radius: 6px; font-size: 12px; color: #4b5563;">
                    <i class="fas fa-check-circle" style="color: #48bb78;"></i> Modulo abilitato
                </div>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
        
        <?php 
        // Conta i moduli attivi
        $moduli_attivi_count = 0;
        foreach ($moduli_controllati as $mod) {
            if ($mod['abilitato']) {
                $moduli_attivi_count++;
            }
        }
        
        if ($moduli_attivi_count == 0): ?>
        <div style="text-align: center; padding: 40px; background: #f9fafb; border-radius: 12px; border: 2px dashed #e5e7eb;">
            <i class="fas fa-puzzle-piece" style="font-size: 48px; color: #d1d5db; margin-bottom: 15px;"></i>
            <p style="color: #6b7280;">Nessun modulo attivo per questa azienda</p>
        </div>
        <?php endif; ?>
        
        <!-- Form per aggiungere/rimuovere moduli - SOLO I 3 CONTROLLATI -->
        <div style="background: #f9fafb; border-radius: 12px; padding: 20px; margin-top: 20px;">
            <h4 style="margin: 0 0 15px 0;">Gestione Moduli</h4>
            <p style="color: #6b7280; font-size: 14px; margin-bottom: 15px;">
                Questi moduli richiedono abilitazione manuale. Il File Manager è sempre disponibile per tutte le aziende.
            </p>
            <form method="post" action="">
                <input type="hidden" name="action" value="update_modules">
                <div style="display: grid; gap: 10px;">
                    <?php foreach ($moduli_controllati as $modulo): ?>
                    <label style="display: flex; align-items: center; gap: 10px; padding: 12px; background: white; border-radius: 8px; border: 1px solid #e5e7eb; cursor: pointer; transition: all 0.2s;">
                        <input type="checkbox" name="moduli[]" value="<?php echo $modulo['id']; ?>" 
                               <?php echo in_array($modulo['id'], $moduli_attivi_ids) ? 'checked' : ''; ?>
                               style="width: 18px; height: 18px;">
                        <div style="background: <?php echo $modulo['colore']; ?>; color: white; width: 30px; height: 30px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 14px;">
                            <i class="<?php echo $modulo['icona']; ?>"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 500;"><?php echo htmlspecialchars($modulo['nome']); ?></div>
                            <div style="font-size: 12px; color: #6b7280;"><?php echo htmlspecialchars($modulo['descrizione']); ?></div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top: 15px;">
                    <i class="fas fa-save"></i> Salva Moduli
                </button>
            </form>
        </div>
    </div>
    
    <!-- Utenti Azienda -->
    <div class="content-card" style="margin-top: 30px;">
        <h2 style="margin-bottom: 20px;">Utenti Azienda</h2>
        
        <?php
        // Calcola i ruoli attuali per mostrare i limiti
        $ruoli_count = [
            'responsabile_aziendale' => 0,
            'referente' => 0,
            'ospite' => 0
        ];
        
        foreach ($utentiAzienda as $ua) {
            // Normalizza i ruoli legacy e gestisce valori vuoti
            $ruolo_normalizzato = trim($ua['ruolo_azienda']);
            
            // Se il ruolo è vuoto, assegna 'referente' come default
            if (empty($ruolo_normalizzato)) {
                $ruolo_normalizzato = 'referente';
                
                // Aggiorna il database per prevenire il problema in futuro
                try {
                    db_update('utenti_aziende', 
                        ['ruolo_azienda' => 'referente'], 
                        'utente_id = :utente_id AND azienda_id = :azienda_id AND (ruolo_azienda = \'\' OR ruolo_azienda IS NULL)', 
                        ['utente_id' => $ua['utente_id'], 'azienda_id' => $aziendaId]
                    );
                } catch (Exception $e) {
                    error_log("Errore aggiornamento ruolo vuoto: " . $e->getMessage());
                }
            }
            
            // Normalizza i ruoli legacy
            if ($ruolo_normalizzato === 'proprietario') $ruolo_normalizzato = 'responsabile_aziendale';
            if ($ruolo_normalizzato === 'admin' || $ruolo_normalizzato === 'utente') $ruolo_normalizzato = 'referente';
            
            if (isset($ruoli_count[$ruolo_normalizzato])) {
                $ruoli_count[$ruolo_normalizzato]++;
            }
        }
        ?>
        
        <div style="background: #f8f9fa; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
                <div>
                    <strong>Responsabile Aziendale:</strong> 
                    <span style="color: <?php echo $ruoli_count['responsabile_aziendale'] > 0 ? '#059669' : '#dc2626'; ?>">
                        <?php echo $ruoli_count['responsabile_aziendale']; ?>/1
                    </span>
                </div>
                <div>
                    <strong>Referenti:</strong> 
                    <span style="color: <?php echo $ruoli_count['referente'] < ($azienda['max_referenti'] ?? 5) ? '#059669' : '#dc2626'; ?>">
                        <?php echo $ruoli_count['referente']; ?>/<?php echo $azienda['max_referenti'] ?? 5; ?>
                    </span>
                </div>
                <div>
                    <strong>Ospiti:</strong> 
                    <span style="color: #6b7280;">
                        <?php echo $ruoli_count['ospite']; ?> (illimitati)
                    </span>
                </div>
            </div>
        </div>
        
        <div class="users-grid">
            <?php foreach ($utentiAzienda as $ua): ?>
            <div class="user-item">
                <div>
                    <div style="font-weight: 600; color: #2d3748;">
                        <?php echo htmlspecialchars($ua['nome'] . ' ' . $ua['cognome']); ?>
                    </div>
                    <div style="font-size: 13px; color: #718096;">
                        <?php echo htmlspecialchars($ua['email']); ?> • 
                        <?php 
                        $ruoli = [
                            'responsabile_aziendale' => 'Responsabile Aziendale',
                            'referente' => 'Referente',
                            'ospite' => 'Ospite',
                            // Ruoli legacy per compatibilità
                            'proprietario' => 'Responsabile Aziendale',
                            'admin' => 'Referente',
                            'utente' => 'Referente'
                        ];
                        
                        // Colori per i ruoli
                        $ruolo_colori = [
                            'responsabile_aziendale' => 'background: #fee2e2; color: #b91c1c;',
                            'referente' => 'background: #dbeafe; color: #1e40af;',
                            'ospite' => 'background: #f3f4f6; color: #4b5563;',
                            // Legacy
                            'proprietario' => 'background: #fee2e2; color: #b91c1c;',
                            'admin' => 'background: #dbeafe; color: #1e40af;',
                            'utente' => 'background: #dbeafe; color: #1e40af;'
                        ];
                        
                        // Gestisce ruoli vuoti o non riconosciuti
                        $ruolo_display = trim($ua['ruolo_azienda']);
                        if (empty($ruolo_display)) {
                            $ruolo_display = 'referente'; // Default fallback
                        }
                        
                        $nome_ruolo = $ruoli[$ruolo_display] ?? ucfirst(str_replace('_', ' ', $ruolo_display));
                        $stile_ruolo = $ruolo_colori[$ruolo_display] ?? 'background: #e2e8f0; color: #1f2937;';
                        
                        // Se il nome del ruolo è ancora vuoto, mostra un placeholder
                        if (empty($nome_ruolo)) {
                            $nome_ruolo = 'Ruolo Non Specificato';
                        }
                        ?>
                        <span style="<?php echo $stile_ruolo; ?> padding: 4px 10px; border-radius: 12px; font-weight: 500; font-size: 12px;">
                            <?php echo $nome_ruolo; ?>
                        </span>
                    </div>
                </div>
                <form method="post" action="" style="display: inline;">
                    <input type="hidden" name="utente_id" value="<?php echo $ua['utente_id']; ?>">
                    <button type="submit" name="action" value="remove_user" class="btn btn-danger btn-small" 
                            onclick="return confirm('Rimuovere questo utente dall\'azienda?')">
                        <i class="fas fa-times"></i> Rimuovi
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Form aggiungi utente -->
        <div class="add-user-form">
            <h3 style="margin-bottom: 15px;">Aggiungi Utente</h3>
            <form method="post" action="">
                <input type="hidden" name="action" value="add_user">
                <div class="form-row">
                    <div class="form-group">
                        <label for="utente_id">Seleziona Utente</label>
                        <select id="utente_id" name="utente_id" required>
                            <option value="">-- Seleziona --</option>
                            <?php
                            // Carica utenti non ancora associati
                            $existingUserIds = array_column($utentiAzienda, 'utente_id');
                            $sql = "SELECT id, nome, cognome, email FROM utenti WHERE attivo = 1";
                            if (!empty($existingUserIds)) {
                                $placeholders = str_repeat('?,', count($existingUserIds) - 1) . '?';
                                $sql .= " AND id NOT IN ($placeholders)";
                            }
                            $sql .= " ORDER BY cognome, nome";
                            
                            if (!empty($existingUserIds)) {
                                $stmt = db_query($sql, $existingUserIds);
                            } else {
                                $stmt = db_query($sql);
                            }
                            $availableUsers = $stmt->fetchAll();
                            
                            foreach ($availableUsers as $u): ?>
                                <option value="<?php echo $u['id']; ?>">
                                    <?php echo htmlspecialchars($u['cognome'] . ' ' . $u['nome'] . ' (' . $u['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="ruolo_azienda">Ruolo nell'Azienda</label>
                        <select id="ruolo_azienda" name="ruolo_azienda" required>
                            <option value="">-- Seleziona ruolo --</option>
                            <option value="responsabile_aziendale">Responsabile Aziendale</option>
                            <option value="referente">Referente</option>
                            <option value="ospite">Ospite</option>
                        </select>
                        <small class="form-text">
                            <strong>Responsabile Aziendale:</strong> Accesso completo all'azienda<br>
                            <strong>Referente:</strong> Gestione documenti e operazioni aziendali (limite: <?php echo $azienda['max_referenti'] ?? 5; ?>)<br>
                            <strong>Ospite:</strong> Solo visualizzazione documenti
                        </small>
                    </div>
                    
                    <div class="form-group" style="display: flex; align-items: flex-end;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Aggiungi
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
<?php else: ?>
    <!-- Lista Aziende -->
    
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (empty($aziende)): ?>
        <div class="empty-state">
            <i class="fas fa-building"></i>
            <h2>Nessuna azienda presente</h2>
            <p>Crea la prima azienda per iniziare a utilizzare il sistema.</p>
            <a href="<?php echo APP_PATH; ?>/aziende.php?action=nuovo" class="btn btn-primary" style="margin-top: 20px;">
                <i class="fas fa-plus"></i> Crea Azienda
            </a>
        </div>
    <?php else: ?>
        <div class="aziende-grid">
            <?php foreach ($aziende as $azienda_item): 
                // Use consistent variable naming to avoid conflicts with detail view
                $current_azienda = $azienda_item;
            ?>
            <div class="azienda-card">
                <div class="azienda-header">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <?php if (isset($current_azienda['logo_path']) && !empty($current_azienda['logo_path'])): ?>
                            <img src="<?php echo APP_PATH . htmlspecialchars($current_azienda['logo_path']); ?>" 
                                 alt="Logo <?php echo htmlspecialchars($current_azienda['nome']); ?>" 
                                 style="width: 40px; height: 40px; object-fit: contain; border-radius: 4px; border: 1px solid #e0e0e0;">
                        <?php endif; ?>
                        <div>
                            <div class="azienda-title"><?php echo htmlspecialchars($current_azienda['nome']); ?></div>
                            <?php if (!empty($current_azienda['ragione_sociale'])): ?>
                            <div style="font-size: 13px; color: #718096;">
                                <?php echo htmlspecialchars($current_azienda['ragione_sociale']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="status-badge status-<?php echo $current_azienda['stato']; ?>">
                        <?php echo ucfirst($current_azienda['stato']); ?>
                    </span>
                </div>
                
                <?php if (!empty($current_azienda['citta']) || !empty($current_azienda['provincia'])): ?>
                <div style="font-size: 14px; color: #718096; margin-bottom: 15px;">
                    <i class="fas fa-map-marker-alt"></i> 
                    <?php 
                    $location = [];
                    if (!empty($current_azienda['citta'])) $location[] = $current_azienda['citta'];
                    if (!empty($current_azienda['provincia'])) $location[] = '(' . $current_azienda['provincia'] . ')';
                    echo htmlspecialchars(implode(' ', $location));
                    ?>
                </div>
                <?php endif; ?>
                
                <div class="azienda-stats">
                    <div class="azienda-stat">
                        <div class="azienda-stat-value"><?php echo $current_azienda['numero_utenti'] ?? 0; ?></div>
                        <div class="azienda-stat-label">Utenti</div>
                    </div>
                    <div class="azienda-stat">
                        <div class="azienda-stat-value"><?php echo $current_azienda['numero_documenti'] ?? 0; ?></div>
                        <div class="azienda-stat-label">Documenti</div>
                    </div>
                    <div class="azienda-stat">
                        <div class="azienda-stat-value"><?php echo $current_azienda['numero_eventi'] ?? 0; ?></div>
                        <div class="azienda-stat-label">Eventi</div>
                    </div>
                    <div class="azienda-stat">
                        <div class="azienda-stat-value"><?php echo $current_azienda['tickets_aperti'] ?? 0; ?></div>
                        <div class="azienda-stat-label">Tickets</div>
                    </div>
                </div>
                
                <?php
                // Verifica se l'azienda ha un responsabile (solo se la colonna esiste)
                $has_responsabile = false;
                $is_super_admin = $auth->isSuperAdmin();
                
                // Use the $responsabile_column_exists variable already set
                if ($responsabile_column_exists && isset($current_azienda['responsabile_id']) && $current_azienda['responsabile_id']) {
                    try {
                        $stmt_resp = db_query("SELECT id FROM utenti WHERE id = ? AND attivo = 1", [$current_azienda['responsabile_id']]);
                        $has_responsabile = $stmt_resp->fetch() !== false;
                    } catch (Exception $e) {
                        $has_responsabile = false;
                    }
                }
                ?>
                
                <?php if (!$has_responsabile && !$is_super_admin): ?>
                <div style="background: #fee2e2; border: 1px solid #fecaca; border-radius: 8px; padding: 10px; margin: 15px 0; color: #991b1b;">
                    <i class="fas fa-exclamation-triangle"></i> <strong>Azienda non utilizzabile</strong><br>
                    <small>Nessun responsabile assegnato. Contatta l'amministratore.</small>
                </div>
                <?php elseif (!$has_responsabile): ?>
                <div style="background: #fef3cd; border: 1px solid #fde68a; border-radius: 8px; padding: 10px; margin: 15px 0; color: #92400e;">
                    <i class="fas fa-exclamation-triangle"></i> <strong>Attenzione:</strong> Nessun responsabile assegnato
                </div>
                <?php endif; ?>
                
                <div style="margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="<?php echo APP_PATH; ?>/aziende.php?action=view&id=<?php echo $current_azienda['id']; ?>" class="btn btn-primary btn-small">
                        <i class="fas fa-eye"></i> Dettagli
                    </a>
                    <a href="<?php echo APP_PATH; ?>/aziende.php?action=edit&id=<?php echo $current_azienda['id']; ?>" class="btn btn-secondary btn-small">
                        <i class="fas fa-edit"></i> Modifica
                    </a>
                    <?php if ($auth->isSuperAdmin()): ?>
                    <button type="button" class="btn btn-danger btn-small" onclick="confirmDeleteAzienda(<?php echo $current_azienda['id']; ?>, '<?php echo htmlspecialchars(addslashes($current_azienda['nome'])); ?>')">
                        <i class="fas fa-trash"></i> Elimina
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<script>
// Gestione dinamica form utenti azienda
document.addEventListener('DOMContentLoaded', function() {
    const ruoloSelect = document.getElementById('ruolo_azienda');
    if (ruoloSelect) {
        // Dati dei ruoli attuali
        const ruoliCount = {
            responsabile_aziendale: <?php echo $ruoli_count['responsabile_aziendale'] ?? 0; ?>,
            referente: <?php echo $ruoli_count['referente'] ?? 0; ?>,
            ospite: <?php echo $ruoli_count['ospite'] ?? 0; ?>
        };
        
        const maxReferenti = <?php echo $azienda['max_referenti'] ?? 5; ?>;
        
        function updateRuoloOptions() {
            const options = ruoloSelect.options;
            
            for (let i = 0; i < options.length; i++) {
                const option = options[i];
                const value = option.value;
                
                // Reset
                option.disabled = false;
                option.style.color = '';
                
                if (value === 'responsabile_aziendale' && ruoliCount.responsabile_aziendale >= 1) {
                    option.disabled = true;
                    option.style.color = '#9ca3af';
                    option.text = option.text.replace(' (Non disponibile)', '') + ' (Non disponibile)';
                } else if (value === 'referente' && ruoliCount.referente >= maxReferenti) {
                    option.disabled = true;
                    option.style.color = '#9ca3af';
                    option.text = option.text.replace(' (Limite raggiunto)', '') + ' (Limite raggiunto)';
                }
            }
        }
        
        updateRuoloOptions();
    }
});
</script>

<?php require_once 'components/footer.php'; ?>

<script>
// Funzione principale per l'eliminazione delle aziende
function confirmDeleteAzienda(aziendaId, aziendaNome) {
    // Verifica che confirmDeleteAction sia disponibile, altrimenti usa alert normale
    if (typeof confirmDeleteAction !== 'undefined' && typeof window.confirmDelete !== 'undefined') {
        // Usa il sistema di conferma avanzato se disponibile
        confirmDeleteAction({
            itemType: 'azienda',
            itemName: aziendaNome,
            onConfirm: function() {
                executeDelete(aziendaId, aziendaNome);
            }
        });
    } else {
        // Fallback al sistema di conferma semplice
        if (confirm(`Sei sicuro di voler eliminare l'azienda "${aziendaNome}"?\n\nQuesta azione non può essere annullata.`)) {
            executeDelete(aziendaId, aziendaNome);
        }
    }
}

// Funzione che esegue effettivamente l'eliminazione
async function executeDelete(aziendaId, aziendaNome) {
    try {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', aziendaId);
        
        const response = await fetch('aziende.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const contentType = response.headers.get("content-type");
        let data;
        
        if (contentType && contentType.indexOf("application/json") !== -1) {
            data = await response.json();
        } else {
            const text = await response.text();
            console.error('Risposta non JSON ricevuta:', text);
            throw new Error('Risposta del server non valida');
        }
        
        if (data.success) {
            // Usa alert con successo, senza dipendenze esterne
            alert(data.message || 'Azienda eliminata con successo');
            // Ricarica la pagina per mostrare la lista aggiornata
            window.location.href = '<?php echo APP_PATH; ?>/aziende.php';
        } else {
            alert('Errore: ' + (data.message || 'Errore durante l\'eliminazione'));
        }
    } catch (error) {
        console.error('Errore durante l\'eliminazione:', error);
        alert('Errore di comunicazione con il server: ' + error.message);
    }
}
</script> 