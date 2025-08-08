<?php
require_once dirname(__DIR__) . '/../backend/config/config.php';

header('Content-Type: application/json');

$auth = Auth::getInstance();
$auth->requireAuth();

$user = $auth->getUser();
$isSuperAdmin = $auth->isSuperAdmin();
$currentAzienda = $auth->getCurrentAzienda();

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {
        case 'list':
            handleListDocuments();
            break;
            
        case 'search':
            handleSearchDocuments();
            break;
            
        case 'upload':
            handleUploadDocument();
            break;
            
        case 'download':
            handleDownloadDocument();
            break;
            
        case 'delete':
            handleDeleteDocument();
            break;
        case 'get_spazio':
            // Ottiene lo spazio documentale corrente
            $tipo = $_GET['tipo'] ?? 'azienda';
            $azienda_id = $_GET['azienda_id'] ?? ($currentAzienda['azienda_id'] ?? null);
            
            if ($tipo === 'super_admin' && !$isSuperAdmin) {
                throw new Exception("Accesso negato allo spazio super admin");
            }
            
            $query = "SELECT * FROM spazi_documentali WHERE tipo = ?";
            $params = [$tipo];
            
            if ($tipo === 'azienda' && $azienda_id) {
                $query .= " AND azienda_id = ?";
                $params[] = $azienda_id;
            }
            
            $stmt = db_query($query, $params);
            $spazio = $stmt->fetch();
            
            if (!$spazio && $tipo === 'azienda' && $azienda_id) {
                // Crea automaticamente lo spazio per l'azienda
                $azienda = db_query("SELECT nome FROM aziende WHERE id = ?", [$azienda_id])->fetch();
                if ($azienda) {
                    db_query("INSERT INTO spazi_documentali (tipo, azienda_id, nome) VALUES (?, ?, ?)",
                            ['azienda', $azienda_id, "Documenti " . $azienda['nome']]);
                    $spazio_id = db_connection()->lastInsertId();
                    $spazio = db_query("SELECT * FROM spazi_documentali WHERE id = ?", [$spazio_id])->fetch();
                }
            }
            
            echo json_encode(['success' => true, 'spazio' => $spazio]);
            break;
            
        case 'get_cartelle':
            // Ottiene le cartelle di uno spazio
            $spazio_id = $_GET['spazio_id'] ?? null;
            $parent_id = $_GET['parent_id'] ?? null;
            
            if (!$spazio_id) {
                throw new Exception("ID spazio mancante");
            }
            
            $query = "SELECT c.*, COUNT(DISTINCT sc.id) as num_sottocartelle, COUNT(DISTINCT d.id) as num_documenti
                      FROM cartelle_iso c
                      LEFT JOIN cartelle_iso sc ON sc.parent_id = c.id
                      LEFT JOIN documenti_iso d ON d.cartella_id = c.id
                      WHERE c.spazio_id = ?";
            $params = [$spazio_id];
            
            if ($parent_id === 'null' || $parent_id === null) {
                $query .= " AND c.parent_id IS NULL";
            } else {
                $query .= " AND c.parent_id = ?";
                $params[] = $parent_id;
            }
            
            $query .= " GROUP BY c.id ORDER BY c.ordine, c.nome";
            
            $stmt = db_query($query, $params);
            $cartelle = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'cartelle' => $cartelle]);
            break;
            
        case 'get_documenti':
            // Ottiene i documenti di una cartella
            $spazio_id = $_GET['spazio_id'] ?? null;
            $cartella_id = $_GET['cartella_id'] ?? null;
            $search = $_GET['search'] ?? '';
            
            if (!$spazio_id) {
                throw new Exception("ID spazio mancante");
            }
            
            $query = "SELECT d.*, u.nome as creato_da_nome, u.cognome as creato_da_cognome,
                             c.nome as classificazione_nome
                      FROM documenti_iso d
                      LEFT JOIN utenti u ON d.creato_da = u.id
                      LEFT JOIN classificazioni_iso c ON d.classificazione = c.codice AND d.tipo_iso = c.tipo_iso
                      WHERE d.spazio_id = ?";
            $params = [$spazio_id];
            
            if ($cartella_id === 'null' || $cartella_id === null) {
                $query .= " AND d.cartella_id IS NULL";
            } else {
                $query .= " AND d.cartella_id = ?";
                $params[] = $cartella_id;
            }
            
            if ($search) {
                $query .= " AND (d.titolo LIKE ? OR d.descrizione LIKE ? OR d.tags LIKE ?)";
                $searchParam = "%$search%";
                $params[] = $searchParam;
                $params[] = $searchParam;
                $params[] = $searchParam;
            }
            
            $query .= " ORDER BY d.data_modifica DESC";
            
            $stmt = db_query($query, $params);
            $documenti = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'documenti' => $documenti]);
            break;
            
        case 'crea_cartella':
            // Crea una nuova cartella
            $spazio_id = $_POST['spazio_id'] ?? null;
            $parent_id = $_POST['parent_id'] ?? null;
            $nome = $_POST['nome'] ?? '';
            $descrizione = $_POST['descrizione'] ?? '';
            $tipo_iso = $_POST['tipo_iso'] ?? null;
            $icona = $_POST['icona'] ?? 'fas fa-folder';
            $colore = $_POST['colore'] ?? '#fbbf24';
            
            if (!$spazio_id || !$nome) {
                throw new Exception("Dati mancanti");
            }
            
            // Verifica permessi
            $spazio = db_query("SELECT * FROM spazi_documentali WHERE id = ?", [$spazio_id])->fetch();
            if (!$spazio) {
                throw new Exception("Spazio non trovato");
            }
            
            if ($spazio['tipo'] === 'super_admin' && !$isSuperAdmin) {
                throw new Exception("Permessi insufficienti");
            }
            
            if ($spazio['tipo'] === 'azienda' && $spazio['azienda_id'] != ($currentAzienda['azienda_id'] ?? null) && !$isSuperAdmin) {
                throw new Exception("Permessi insufficienti per questa azienda");
            }
            
            db_query("INSERT INTO cartelle_iso (spazio_id, parent_id, nome, descrizione, tipo_iso, icona, colore) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)",
                     [$spazio_id, $parent_id, $nome, $descrizione, $tipo_iso, $icona, $colore]);
            
            $cartella_id = db_connection()->lastInsertId();
            
            // Log attività
            $logger = ActivityLogger::getInstance();
            $logger->log('cartella_iso', 'creazione', $cartella_id, [
                'nome' => $nome,
                'spazio_id' => $spazio_id,
                'parent_id' => $parent_id
            ]);
            
            echo json_encode(['success' => true, 'cartella_id' => $cartella_id]);
            break;
            
        case 'upload_documento':
            // Upload di un documento
            if (!isset($_FILES['file'])) {
                throw new Exception("Nessun file caricato");
            }
            
            $spazio_id = $_POST['spazio_id'] ?? null;
            $cartella_id = $_POST['cartella_id'] ?? null;
            $titolo = $_POST['titolo'] ?? '';
            $descrizione = $_POST['descrizione'] ?? '';
            $tipo_iso = $_POST['tipo_iso'] ?? null;
            $classificazione = $_POST['classificazione'] ?? null;
            $tags = $_POST['tags'] ?? '';
            
            if (!$spazio_id) {
                throw new Exception("ID spazio mancante");
            }
            
            // Verifica permessi
            $spazio = db_query("SELECT * FROM spazi_documentali WHERE id = ?", [$spazio_id])->fetch();
            if (!$spazio) {
                throw new Exception("Spazio non trovato");
            }
            
            if ($spazio['tipo'] === 'super_admin' && !$isSuperAdmin) {
                throw new Exception("Permessi insufficienti");
            }
            
            if ($spazio['tipo'] === 'azienda' && $spazio['azienda_id'] != ($currentAzienda['azienda_id'] ?? null) && !$isSuperAdmin) {
                throw new Exception("Permessi insufficienti per questa azienda");
            }
            
            $file = $_FILES['file'];
            $file_name = $file['name'];
            $file_tmp = $file['tmp_name'];
            $file_size = $file['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Genera codice univoco
            $codice = 'DOC-' . date('Ymd') . '-' . uniqid();
            
            // Determina il percorso di upload
            $upload_base = __DIR__ . '/../../uploads/iso_documents/';
            if ($spazio['tipo'] === 'super_admin') {
                $upload_dir = $upload_base . 'super_admin/';
            } else {
                $upload_dir = $upload_base . 'azienda_' . $spazio['azienda_id'] . '/';
            }
            
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $unique_name = $codice . '.' . $file_ext;
            $file_path = $upload_dir . $unique_name;
            $relative_path = 'uploads/iso_documents/' . 
                            ($spazio['tipo'] === 'super_admin' ? 'super_admin/' : 'azienda_' . $spazio['azienda_id'] . '/') . 
                            $unique_name;
            
            if (!move_uploaded_file($file_tmp, $file_path)) {
                throw new Exception("Errore nel caricamento del file");
            }
            
            // Se non è specificato un titolo, usa il nome del file
            if (!$titolo) {
                $titolo = pathinfo($file_name, PATHINFO_FILENAME);
            }
            
            // Inserisci il documento nel database
            db_query("INSERT INTO documenti_iso (spazio_id, cartella_id, titolo, codice, descrizione, file_path, 
                                                tipo_file, dimensione_file, tipo_iso, classificazione, tags, creato_da) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                     [$spazio_id, $cartella_id, $titolo, $codice, $descrizione, $relative_path, 
                      $file_ext, $file_size, $tipo_iso, $classificazione, $tags, $user['id']]);
            
            $documento_id = db_connection()->lastInsertId();
            
            // Crea prima versione
            db_query("INSERT INTO versioni_documenti_iso (documento_id, versione, file_path, dimensione_file, note_versione, creato_da)
                      VALUES (?, 1, ?, ?, 'Versione iniziale', ?)",
                     [$documento_id, $relative_path, $file_size, $user['id']]);
            
            // Log attività
            $logger = ActivityLogger::getInstance();
            $logger->log('documento_iso', 'upload', $documento_id, [
                'titolo' => $titolo,
                'codice' => $codice,
                'dimensione' => $file_size
            ]);
            
            echo json_encode(['success' => true, 'documento_id' => $documento_id, 'codice' => $codice]);
            break;
            
        case 'crea_struttura_iso':
            // Crea la struttura ISO per un'azienda
            $spazio_id = $_POST['spazio_id'] ?? null;
            $tipo_iso = $_POST['tipo_iso'] ?? null;
            $modalita = $_POST['modalita'] ?? 'integrato';
            
            if (!$spazio_id || !$tipo_iso) {
                throw new Exception("Dati mancanti");
            }
            
            // Verifica permessi
            $spazio = db_query("SELECT * FROM spazi_documentali WHERE id = ?", [$spazio_id])->fetch();
            if (!$spazio) {
                throw new Exception("Spazio non trovato");
            }
            
            if ($spazio['tipo'] === 'super_admin' && !$isSuperAdmin) {
                throw new Exception("Permessi insufficienti");
            }
            
            // Chiama la stored procedure per creare la struttura
            $stmt = db_query("CALL crea_struttura_iso(?, ?, ?)", [$spazio_id, $tipo_iso, $modalita]);
            
            echo json_encode(['success' => true, 'message' => 'Struttura ISO creata con successo']);
            break;
            
        case 'get_impostazioni_iso':
            // Ottiene le impostazioni ISO per un'azienda
            $azienda_id = $_GET['azienda_id'] ?? ($currentAzienda['azienda_id'] ?? null);
            
            if (!$azienda_id) {
                throw new Exception("ID azienda mancante");
            }
            
            $stmt = db_query("SELECT * FROM impostazioni_iso_azienda WHERE azienda_id = ?", [$azienda_id]);
            $impostazioni = $stmt->fetch();
            
            if (!$impostazioni) {
                // Crea impostazioni di default
                db_query("INSERT INTO impostazioni_iso_azienda (azienda_id) VALUES (?)", [$azienda_id]);
                $impostazioni = db_query("SELECT * FROM impostazioni_iso_azienda WHERE azienda_id = ?", [$azienda_id])->fetch();
            }
            
            echo json_encode(['success' => true, 'impostazioni' => $impostazioni]);
            break;
            
        case 'salva_impostazioni_iso':
            // Salva le impostazioni ISO per un'azienda
            $azienda_id = $_POST['azienda_id'] ?? ($currentAzienda['azienda_id'] ?? null);
            $modalita = $_POST['modalita'] ?? 'integrato';
            $iso_9001 = $_POST['iso_9001'] ?? false;
            $iso_14001 = $_POST['iso_14001'] ?? false;
            $iso_45001 = $_POST['iso_45001'] ?? false;
            $iso_27001 = $_POST['iso_27001'] ?? false;
            
            if (!$azienda_id) {
                throw new Exception("ID azienda mancante");
            }
            
            // Verifica permessi
            if (!$isSuperAdmin && $azienda_id != ($currentAzienda['azienda_id'] ?? null)) {
                throw new Exception("Permessi insufficienti");
            }
            
            db_query("UPDATE impostazioni_iso_azienda 
                      SET modalita = ?, iso_9001_attivo = ?, iso_14001_attivo = ?, 
                          iso_45001_attivo = ?, iso_27001_attivo = ?
                      WHERE azienda_id = ?",
                     [$modalita, $iso_9001, $iso_14001, $iso_45001, $iso_27001, $azienda_id]);
            
            echo json_encode(['success' => true, 'message' => 'Impostazioni salvate con successo']);
            break;
            
        case 'elimina_cartella':
            // Elimina una cartella (solo se vuota)
            $cartella_id = $_POST['cartella_id'] ?? null;
            
            if (!$cartella_id) {
                throw new Exception("ID cartella mancante");
            }
            
            // Verifica che la cartella sia vuota
            $count = db_query("SELECT COUNT(*) as cnt FROM cartelle_iso WHERE parent_id = ?", [$cartella_id])->fetch()['cnt'];
            if ($count > 0) {
                throw new Exception("La cartella contiene sottocartelle");
            }
            
            $count = db_query("SELECT COUNT(*) as cnt FROM documenti_iso WHERE cartella_id = ?", [$cartella_id])->fetch()['cnt'];
            if ($count > 0) {
                throw new Exception("La cartella contiene documenti");
            }
            
            // Verifica che non sia una cartella protetta
            $cartella = db_query("SELECT * FROM cartelle_iso WHERE id = ?", [$cartella_id])->fetch();
            if ($cartella['protetta']) {
                throw new Exception("Impossibile eliminare una cartella protetta");
            }
            
            db_query("DELETE FROM cartelle_iso WHERE id = ?", [$cartella_id]);
            
            echo json_encode(['success' => true, 'message' => 'Cartella eliminata']);
            break;
            
        case 'elimina_documento':
            // Elimina un documento
            $documento_id = $_POST['documento_id'] ?? null;
            
            if (!$documento_id) {
                throw new Exception("ID documento mancante");
            }
            
            // Recupera info documento
            $doc = db_query("SELECT * FROM documenti_iso WHERE id = ?", [$documento_id])->fetch();
            if (!$doc) {
                throw new Exception("Documento non trovato");
            }
            
            // Verifica permessi
            $spazio = db_query("SELECT * FROM spazi_documentali WHERE id = ?", [$doc['spazio_id']])->fetch();
            if ($spazio['tipo'] === 'super_admin' && !$isSuperAdmin) {
                throw new Exception("Permessi insufficienti");
            }
            
            // Elimina file fisico
            if ($doc['file_path'] && file_exists(__DIR__ . '/../../' . $doc['file_path'])) {
                unlink(__DIR__ . '/../../' . $doc['file_path']);
            }
            
            // Elimina file delle versioni
            $versioni = db_query("SELECT file_path FROM versioni_documenti_iso WHERE documento_id = ?", [$documento_id])->fetchAll();
            foreach ($versioni as $versione) {
                if ($versione['file_path'] && file_exists(__DIR__ . '/../../' . $versione['file_path'])) {
                    unlink(__DIR__ . '/../../' . $versione['file_path']);
                }
            }
            
            // Elimina dal database
            db_query("DELETE FROM documenti_iso WHERE id = ?", [$documento_id]);
            
            echo json_encode(['success' => true, 'message' => 'Documento eliminato']);
            break;
            
        case 'download_documento':
            // Download di un documento
            $documento_id = $_GET['documento_id'] ?? null;
            
            if (!$documento_id) {
                throw new Exception("ID documento mancante");
            }
            
            $doc = db_query("SELECT * FROM documenti_iso WHERE id = ?", [$documento_id])->fetch();
            if (!$doc) {
                throw new Exception("Documento non trovato");
            }
            
            $file_path = __DIR__ . '/../../' . $doc['file_path'];
            if (!file_exists($file_path)) {
                throw new Exception("File non trovato");
            }
            
            // Log download
            $logger = ActivityLogger::getInstance();
            $logger->log('documento_iso', 'download', $documento_id, [
                'titolo' => $doc['titolo'],
                'codice' => $doc['codice']
            ]);
            
            // Invia il file
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $doc['titolo'] . '.' . $doc['tipo_file'] . '"');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit;
            
        case 'get_classificazioni':
            // Ottiene le classificazioni per un tipo ISO
            $tipo_iso = $_GET['tipo_iso'] ?? null;
            
            $query = "SELECT * FROM classificazioni_iso";
            $params = [];
            
            if ($tipo_iso) {
                $query .= " WHERE tipo_iso = ?";
                $params[] = $tipo_iso;
            }
            
            $query .= " ORDER BY tipo_iso, ordine, nome";
            
            $stmt = db_query($query, $params);
            $classificazioni = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'classificazioni' => $classificazioni]);
            break;
            
        case 'initialize_spaces':
            // Inizializza gli spazi documentali di base
            if (!$isSuperAdmin) {
                throw new Exception("Solo i super admin possono inizializzare gli spazi");
            }
            
            try {
                error_log("ISO API: Inizio inizializzazione spazi documentali");
                
                // Verifica prima che le tabelle esistano
                $tabelle_richieste = [
                    'spazi_documentali',
                    'cartelle_iso',
                    'documenti_iso',
                    'versioni_documenti_iso',
                    'classificazioni_iso',
                    'impostazioni_iso_azienda'
                ];
                
                foreach ($tabelle_richieste as $tabella) {
                    try {
                        $test = db_query("SELECT 1 FROM $tabella LIMIT 1");
                        error_log("ISO API: Tabella $tabella verificata OK");
                    } catch (Exception $e) {
                        error_log("ISO API: Tabella $tabella mancante: " . $e->getMessage());
                        throw new Exception("Tabella $tabella non trovata. Eseguire prima lo script di creazione tabelle.");
                    }
                }
                
                db_connection()->beginTransaction();
                
                // Verifica se esiste già lo spazio super admin
                $stmt = db_query("SELECT id FROM spazi_documentali WHERE tipo = 'super_admin'");
                if (!$stmt->fetch()) {
                    // Crea spazio super admin
                    db_query("INSERT INTO spazi_documentali (tipo, nome, descrizione) VALUES (?, ?, ?)",
                            ['super_admin', 'Documenti Sistema', 'Spazio documentale riservato ai super amministratori']);
                    $spazio_id = db_connection()->lastInsertId();
                    
                    // Crea struttura base per super admin
                    $cartelle_base = [
                        ['nome' => 'Template ISO', 'icona' => 'fas fa-file-alt', 'colore' => '#3b82f6'],
                        ['nome' => 'Procedure Sistema', 'icona' => 'fas fa-cogs', 'colore' => '#8b5cf6'],
                        ['nome' => 'Documentazione Tecnica', 'icona' => 'fas fa-book', 'colore' => '#10b981'],
                        ['nome' => 'Archivio Audit', 'icona' => 'fas fa-archive', 'colore' => '#f59e0b']
                    ];
                    
                    foreach ($cartelle_base as $index => $cartella) {
                        db_query("INSERT INTO cartelle_iso (spazio_id, nome, icona, colore, ordine, protetta) VALUES (?, ?, ?, ?, ?, ?)",
                                [$spazio_id, $cartella['nome'], $cartella['icona'], $cartella['colore'], $index + 1, 1]);
                    }
                }
                
                // Verifica se esistono spazi per le aziende attive
                $aziende = db_query("SELECT id, nome FROM aziende WHERE stato = 'attiva'")->fetchAll();
                foreach ($aziende as $azienda) {
                    $stmt = db_query("SELECT id FROM spazi_documentali WHERE tipo = 'azienda' AND azienda_id = ?", [$azienda['id']]);
                    if (!$stmt->fetch()) {
                        // Crea spazio per l'azienda
                        db_query("INSERT INTO spazi_documentali (tipo, azienda_id, nome, descrizione) VALUES (?, ?, ?, ?)",
                                ['azienda', $azienda['id'], "Documenti " . $azienda['nome'], 
                                 "Spazio documentale dell'azienda " . $azienda['nome']]);
                    }
                }
                
                // Verifica e popola classificazioni ISO se non esistono
                $stmt = db_query("SELECT COUNT(*) as cnt FROM classificazioni_iso");
                $result = $stmt->fetch();
                if ($result['cnt'] == 0) {
                    // Inserisci classificazioni predefinite
                    $classificazioni = [
                        // ISO 9001
                        ['ISO_9001', 'POL', 'Politiche', 'Documenti di politica aziendale', 1],
                        ['ISO_9001', 'MAN', 'Manuale', 'Manuale del sistema di gestione', 2],
                        ['ISO_9001', 'PROC', 'Procedure', 'Procedure documentate', 3],
                        ['ISO_9001', 'IST', 'Istruzioni', 'Istruzioni operative', 4],
                        ['ISO_9001', 'MOD', 'Moduli', 'Moduli e registrazioni', 5],
                        ['ISO_9001', 'REG', 'Registrazioni', 'Registrazioni qualità', 6],
                        ['ISO_9001', 'AUDIT', 'Audit', 'Rapporti di audit interno', 7],
                        ['ISO_9001', 'NC', 'Non Conformità', 'Gestione non conformità', 8],
                        ['ISO_9001', 'RIES', 'Riesame', 'Documenti di riesame direzione', 9],
                        
                        // ISO 14001
                        ['ISO_14001', 'POL-AMB', 'Politica Ambientale', 'Politica ambientale aziendale', 1],
                        ['ISO_14001', 'ASP-AMB', 'Aspetti Ambientali', 'Valutazione aspetti ambientali', 2],
                        ['ISO_14001', 'OBJ-AMB', 'Obiettivi Ambientali', 'Obiettivi e traguardi ambientali', 3],
                        ['ISO_14001', 'PROC-AMB', 'Procedure Ambientali', 'Procedure gestione ambientale', 4],
                        ['ISO_14001', 'EME-AMB', 'Emergenze Ambientali', 'Gestione emergenze ambientali', 5],
                        ['ISO_14001', 'MON-AMB', 'Monitoraggio Ambientale', 'Monitoraggio prestazioni ambientali', 6],
                        
                        // ISO 45001
                        ['ISO_45001', 'POL-SIC', 'Politica Sicurezza', 'Politica salute e sicurezza', 1],
                        ['ISO_45001', 'RIS-SIC', 'Valutazione Rischi', 'Documenti valutazione rischi', 2],
                        ['ISO_45001', 'DVR', 'DVR', 'Documento Valutazione Rischi', 3],
                        ['ISO_45001', 'PROC-SIC', 'Procedure Sicurezza', 'Procedure sicurezza lavoro', 4],
                        ['ISO_45001', 'FOR-SIC', 'Formazione Sicurezza', 'Registri formazione sicurezza', 5],
                        ['ISO_45001', 'DPI', 'DPI', 'Gestione dispositivi protezione', 6],
                        ['ISO_45001', 'INF-SIC', 'Infortuni', 'Registro infortuni e incidenti', 7],
                        
                        // ISO 27001
                        ['ISO_27001', 'POL-SEC', 'Politica Sicurezza IT', 'Politica sicurezza informazioni', 1],
                        ['ISO_27001', 'RIS-SEC', 'Risk Assessment', 'Valutazione rischi informatici', 2],
                        ['ISO_27001', 'SOA', 'SOA', 'Statement of Applicability', 3],
                        ['ISO_27001', 'PROC-SEC', 'Procedure Sicurezza IT', 'Procedure sicurezza informatica', 4],
                        ['ISO_27001', 'INC-SEC', 'Incident Management', 'Gestione incidenti sicurezza', 5],
                        ['ISO_27001', 'ACC-SEC', 'Controllo Accessi', 'Procedure controllo accessi', 6],
                        ['ISO_27001', 'BCP', 'Business Continuity', 'Piano continuità operativa', 7]
                    ];
                    
                    foreach ($classificazioni as $class) {
                        db_query("INSERT INTO classificazioni_iso (tipo_iso, codice, nome, descrizione, ordine) VALUES (?, ?, ?, ?, ?)",
                                $class);
                    }
                }
                
                db_connection()->commit();
                error_log("ISO API: Inizializzazione completata con successo");
                echo json_encode(['success' => true, 'message' => 'Spazi documentali inizializzati con successo']);
                
            } catch (Exception $e) {
                error_log("ISO API: Errore durante inizializzazione: " . $e->getMessage());
                if (db_connection()->inTransaction()) {
                    db_connection()->rollBack();
                }
                throw $e;
            }
            break;
            
        case 'verify_system':
            // Verifica che il sistema ISO sia correttamente inizializzato
            try {
                $checks = [];
                $allPassed = true;
                
                // Verifica tabelle
                $tabelle_richieste = [
                    'spazi_documentali',
                    'cartelle_iso',
                    'documenti_iso',
                    'versioni_documenti_iso',
                    'classificazioni_iso',
                    'impostazioni_iso_azienda'
                ];
                
                foreach ($tabelle_richieste as $tabella) {
                    try {
                        $stmt = db_query("SELECT 1 FROM $tabella LIMIT 1");
                        $checks['tabella_' . $tabella] = true;
                    } catch (Exception $e) {
                        $checks['tabella_' . $tabella] = false;
                        $allPassed = false;
                    }
                }
                
                // Verifica spazio super admin
                $stmt = db_query("SELECT COUNT(*) as cnt FROM spazi_documentali WHERE tipo = 'super_admin'");
                $result = $stmt->fetch();
                $checks['spazio_super_admin'] = $result['cnt'] > 0;
                if (!$checks['spazio_super_admin']) $allPassed = false;
                
                // Verifica classificazioni
                $stmt = db_query("SELECT COUNT(*) as cnt FROM classificazioni_iso");
                $result = $stmt->fetch();
                $checks['classificazioni'] = $result['cnt'] > 0;
                if (!$checks['classificazioni']) $allPassed = false;
                
                // Verifica cartelle base
                $stmt = db_query("SELECT COUNT(*) as cnt FROM cartelle_iso WHERE protetta = 1");
                $result = $stmt->fetch();
                $checks['cartelle_base'] = $result['cnt'] > 0;
                if (!$checks['cartelle_base']) $allPassed = false;
                
                echo json_encode([
                    'success' => $allPassed,
                    'checks' => $checks,
                    'message' => $allPassed ? 'Sistema ISO correttamente inizializzato' : 'Sistema ISO non completamente inizializzato'
                ]);
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Errore durante la verifica: ' . $e->getMessage()
                ]);
            }
            break;
            
        default:
            throw new Exception("Azione non valida");
    }
    
} catch (Exception $e) {
    error_log("Errore ISO Documents API: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Assicuriamoci che il content-type sia corretto
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'error' => true
    ]);
}

/**
 * Lista documenti in una cartella
 */
function handleListDocuments() {
    global $auth, $user;
    
    $folderId = $_GET['folder_id'] ?? null;
    $companyId = $auth->getCurrentCompany();
    
    try {
        $query = "SELECT 
                    d.id,
                    d.codice AS code,
                    d.titolo AS title,
                    d.descrizione AS description,
                    d.file_path,
                    d.stato AS status,
                    d.versione AS version,
                    d.formato AS file_extension,
                    d.dimensione_file AS file_size,
                    d.data_creazione AS created_at,
                    d.data_modifica AS updated_at,
                    u.nome AS created_by_name,
                    u.cognome AS created_by_surname
                  FROM documenti d
                  LEFT JOIN utenti u ON d.creato_da = u.id
                  WHERE d.azienda_id = ?";
        
        $params = [$companyId];
        
        if ($folderId !== null) {
            $query .= " AND d.cartella_id = ?";
            $params[] = $folderId;
        } else {
            $query .= " AND d.cartella_id IS NULL";
        }
        
        $query .= " AND d.stato != 'archiviato' ORDER BY d.data_modifica DESC";
        
        $stmt = db_query($query, $params);
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formatta i documenti
        foreach ($documents as &$doc) {
            $doc['created_by_name'] = trim($doc['created_by_name'] . ' ' . $doc['created_by_surname']);
            unset($doc['created_by_surname']);
        }
        
        echo json_encode([
            'success' => true,
            'documents' => $documents
        ]);
        
    } catch (Exception $e) {
        throw new Exception("Errore nel caricamento documenti: " . $e->getMessage());
    }
}

/**
 * Cerca documenti
 */
function handleSearchDocuments() {
    global $auth;
    
    $query = $_GET['query'] ?? '';
    $companyId = $auth->getCurrentCompany();
    
    if (strlen($query) < 3) {
        echo json_encode([
            'success' => false,
            'error' => 'La query deve essere di almeno 3 caratteri'
        ]);
        return;
    }
    
    try {
        $sql = "SELECT 
                    d.id,
                    d.codice AS code,
                    d.titolo AS title,
                    d.descrizione AS description,
                    d.file_path,
                    d.stato AS status,
                    d.versione AS version,
                    d.formato AS file_extension,
                    d.dimensione_file AS file_size,
                    d.data_creazione AS created_at,
                    d.data_modifica AS updated_at,
                    c.nome AS folder_name,
                    c.percorso_completo AS folder_path,
                    u.nome AS created_by_name,
                    u.cognome AS created_by_surname
                  FROM documenti d
                  LEFT JOIN cartelle c ON d.cartella_id = c.id
                  LEFT JOIN utenti u ON d.creato_da = u.id
                  WHERE d.azienda_id = ?
                  AND d.stato != 'archiviato'
                  AND (
                      d.titolo LIKE ? OR 
                      d.descrizione LIKE ? OR 
                      d.codice LIKE ?
                  )
                  ORDER BY d.data_modifica DESC
                  LIMIT 50";
        
        $searchPattern = '%' . $query . '%';
        $stmt = db_query($sql, [$companyId, $searchPattern, $searchPattern, $searchPattern]);
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formatta i documenti
        foreach ($documents as &$doc) {
            $doc['created_by_name'] = trim($doc['created_by_name'] . ' ' . $doc['created_by_surname']);
            unset($doc['created_by_surname']);
        }
        
        echo json_encode([
            'success' => true,
            'documents' => $documents
        ]);
        
    } catch (Exception $e) {
        throw new Exception("Errore nella ricerca: " . $e->getMessage());
    }
}

/**
 * Upload documento
 */
function handleUploadDocument() {
    global $auth, $user;
    
    if (!isset($_FILES['file'])) {
        echo json_encode([
            'success' => false,
            'error' => 'Nessun file caricato'
        ]);
        return;
    }
    
    $file = $_FILES['file'];
    $title = $_POST['title'] ?? '';
    $code = $_POST['code'] ?? '';
    $description = $_POST['description'] ?? '';
    $folderId = $_POST['folder_id'] ?? null;
    $documentType = $_POST['document_type'] ?? '';
    $status = $_POST['status'] ?? 'published';
    $version = $_POST['version'] ?? '1.0';
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    // Verifica CSRF
    if (!isset($_SESSION['csrf_token']) || $csrfToken !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Token CSRF non valido']);
        return;
    }
    
    $companyId = $auth->getCurrentCompany();
    
    try {
        // Validazione file
        $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png'];
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($fileExtension, $allowedExtensions)) {
            throw new Exception("Tipo di file non consentito");
        }
        
        // Genera codice se non fornito
        if (empty($code)) {
            $code = 'DOC-' . date('Ymd') . '-' . uniqid();
        }
        
        // Crea directory di upload se non esiste
        $uploadDir = dirname(__DIR__, 2) . '/uploads/documenti/azienda_' . $companyId . '/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Nome file univoco
        $fileName = $code . '.' . $fileExtension;
        $filePath = $uploadDir . $fileName;
        $relativePath = 'uploads/documenti/azienda_' . $companyId . '/' . $fileName;
        
        // Sposta il file
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception("Errore nel caricamento del file");
        }
        
        // Se non è specificato un titolo, usa il nome del file
        if (empty($title)) {
            $title = pathinfo($file['name'], PATHINFO_FILENAME);
        }
        
        // Inserisci nel database
        db_begin_transaction();
        
        try {
            $documentId = db_insert('documenti', [
                'codice' => $code,
                'titolo' => $title,
                'descrizione' => $description,
                'cartella_id' => $folderId,
                'file_path' => $relativePath,
                'formato' => $fileExtension,
                'dimensione_file' => $file['size'],
                'tipo_documento' => $documentType,
                'stato' => $status,
                'versione' => 1,
                'azienda_id' => $companyId,
                'creato_da' => $user['id'],
                'aggiornato_da' => $user['id']
            ]);
            
            // Log attività
            ActivityLogger::getInstance()->log('documento_caricato', 'documenti', $documentId, [
                'titolo' => $title,
                'codice' => $code,
                'dimensione' => $file['size']
            ]);
            
            db_commit();
            
            echo json_encode([
                'success' => true,
                'document_id' => $documentId,
                'code' => $code
            ]);
            
        } catch (Exception $e) {
            db_rollback();
            // Rimuovi il file se il database fallisce
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            throw $e;
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Download documento
 */
function handleDownloadDocument() {
    global $auth;
    
    $documentId = $_GET['id'] ?? null;
    
    if (!$documentId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID documento mancante']);
        return;
    }
    
    $companyId = $auth->getCurrentCompany();
    
    try {
        // Recupera documento
        $stmt = db_query(
            "SELECT * FROM documenti WHERE id = ? AND azienda_id = ?",
            [$documentId, $companyId]
        );
        $document = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$document) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Documento non trovato']);
            return;
        }
        
        $filePath = dirname(__DIR__, 2) . '/' . $document['file_path'];
        
        if (!file_exists($filePath)) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'File non trovato']);
            return;
        }
        
        // Log download
        ActivityLogger::getInstance()->log('documento_scaricato', 'documenti', $documentId, [
            'titolo' => $document['titolo'],
            'codice' => $document['codice']
        ]);
        
        // Invia file
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $document['titolo'] . '.' . $document['formato'] . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Elimina documento
 */
function handleDeleteDocument() {
    global $auth, $user;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Metodo non consentito']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $documentId = $data['id'] ?? null;
    $csrfToken = $data['csrf_token'] ?? '';
    
    // Verifica CSRF
    if (!isset($_SESSION['csrf_token']) || $csrfToken !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Token CSRF non valido']);
        return;
    }
    
    if (!$documentId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID documento mancante']);
        return;
    }
    
    $companyId = $auth->getCurrentCompany();
    
    try {
        // Verifica permessi
        if (!$auth->hasElevatedPrivileges()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Permessi insufficienti']);
            return;
        }
        
        // Recupera documento
        $stmt = db_query(
            "SELECT * FROM documenti WHERE id = ? AND azienda_id = ?",
            [$documentId, $companyId]
        );
        $document = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$document) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Documento non trovato']);
            return;
        }
        
        db_begin_transaction();
        
        try {
            // Elimina file fisico
            if ($document['file_path']) {
                $filePath = dirname(__DIR__, 2) . '/' . $document['file_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            
            // Elimina dal database
            db_delete('documenti', 'id = ?', [$documentId]);
            
            // Log attività
            ActivityLogger::getInstance()->log('documento_eliminato', 'documenti', $documentId, [
                'titolo' => $document['titolo'],
                'codice' => $document['codice']
            ]);
            
            db_commit();
            
            echo json_encode(['success' => true, 'message' => 'Documento eliminato con successo']);
            
        } catch (Exception $e) {
            db_rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>