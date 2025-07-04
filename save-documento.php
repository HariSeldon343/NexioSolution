<?php
require_once 'backend/config/config.php';
require_once 'backend/utils/ActivityLogger.php';
require_once 'backend/utils/NotificationManager.php';

$auth = Auth::getInstance();
$auth->requireAuth();

$user = $auth->getUser();
// Database instance handled by functions
$logger = ActivityLogger::getInstance();
$notificationManager = NotificationManager::getInstance();

// Validazione input
$id = intval($_POST['id'] ?? 0);
$titolo = trim($_POST['titolo'] ?? '');
$contenuto = $_POST['contenuto'] ?? '';
$azienda_id = intval($_POST['azienda_id'] ?? 0);
$classificazione_id = intval($_POST['classificazione_id'] ?? 0);
$stato = $_POST['stato'] ?? 'bozza';
$versioning_abilitato = isset($_POST['versioning_abilitato']) ? 1 : 0;
$destinatari = $_POST['destinatari'] ?? [];
$tipo_destinatario = $_POST['tipo_destinatario'] ?? [];

// Validazione base
if (empty($titolo) || empty($azienda_id) || empty($classificazione_id)) {
    $_SESSION['error'] = "Compila tutti i campi obbligatori";
    redirect('editor-documenti-completo.php' . ($id ? '?id=' . $id : ''));
}

try {
    db_connection()->beginTransaction();
    
    if ($id) {
        // Modifica documento esistente
        $stmt = db_query("SELECT * FROM documenti WHERE id = ?", [$id]);
        $documento = $stmt->fetch();
        
        if (!$documento) {
            throw new Exception("Documento non trovato");
        }
        
        // Se il versioning è abilitato, crea una nuova versione
        if ($documento['versioning_abilitato']) {
            // Salva la versione corrente
            db_query("INSERT INTO documenti_versioni (documento_id, versione, titolo, contenuto, 
                                                       stato, creato_da, creato_il) 
                       VALUES (?, ?, ?, ?, ?, ?, ?)",
                       [$id, $documento['versione_corrente'], $documento['titolo'], 
                        $documento['contenuto'], $documento['stato'], 
                        $documento['aggiornato_da'] ?? $documento['creato_da'], 
                        $documento['aggiornato_il'] ?? $documento['creato_il']]);
            
            // Incrementa la versione
            $nuova_versione = $documento['versione_corrente'] + 1;
            
            // Aggiorna il documento con la nuova versione
            db_query("UPDATE documenti SET 
                           titolo = ?,
                           contenuto = ?,
                           classificazione_id = ?,
                           stato = ?,
                           versione_corrente = ?,
                           aggiornato_da = ?,
                           aggiornato_il = NOW()
                       WHERE id = ?",
                       [$titolo, $contenuto, $classificazione_id, $stato, 
                        $nuova_versione, $user['id'], $id]);
            
            $logger->log('documento_versione_creata', 
                        "Creata versione $nuova_versione del documento #$id", 
                        ['documento_id' => $id, 'versione' => $nuova_versione]);
        } else {
            // Aggiorna normalmente senza versioning
            db_query("UPDATE documenti SET 
                           titolo = ?,
                           contenuto = ?,
                           classificazione_id = ?,
                           stato = ?,
                           versioning_abilitato = ?,
                           aggiornato_da = ?,
                           aggiornato_il = NOW()
                       WHERE id = ?",
                       [$titolo, $contenuto, $classificazione_id, $stato, 
                        $versioning_abilitato, $user['id'], $id]);
        }
        
        $logger->log('documento_aggiornato', "Aggiornato documento #$id", 
                    ['documento_id' => $id]);
        
    } else {
        // Nuovo documento - questo caso non dovrebbe più verificarsi
        // perché la creazione avviene in nuovo-documento.php
        throw new Exception("Usa nuovo-documento.php per creare documenti");
    }
    
    // Gestisci destinatari (solo se la tabella esiste)
    try {
        // Rimuovi destinatari esistenti
        db_query("DELETE FROM documenti_destinatari WHERE documento_id = ?", [$id]);
        
        // Aggiungi nuovi destinatari
        foreach ($destinatari as $referente_id) {
            $tipo = $tipo_destinatario[$referente_id] ?? 'principale';
            db_query("INSERT INTO documenti_destinatari (documento_id, referente_id, tipo_destinatario) 
                       VALUES (?, ?, ?)",
                       [$id, $referente_id, $tipo]);
        }
        
        // Invia notifiche ai destinatari
        if ($stato == 'pubblicato' && !empty($destinatari)) {
            $stmt = db_query("
                SELECT r.email, r.nome, r.cognome 
                FROM referenti_aziende r
                WHERE r.id IN (" . implode(',', array_map('intval', $destinatari)) . ")");
            
            while ($destinatario = $stmt->fetch()) {
                $notificationManager->sendDocumentNotification(
                    $destinatario['email'],
                    $titolo,
                    $id,
                    $destinatario['nome'] . ' ' . $destinatario['cognome']
                );
            }
        }
    } catch (Exception $e) {
        // Log dell'errore ma continua (la tabella potrebbe non esistere)
        error_log("Errore gestione destinatari: " . $e->getMessage());
    }
    
    db_connection()->commit();
    
    $_SESSION['success'] = $id ? "Documento aggiornato con successo" : "Documento creato con successo";
    redirect('documento-view.php?id=' . $id);
    
} catch (Exception $e) {
    db_connection()->rollback();
    $_SESSION['error'] = "Errore nel salvataggio: " . $e->getMessage();
    redirect('editor-documenti-completo.php' . ($id ? '?id=' . $id : ''));
}
?> 