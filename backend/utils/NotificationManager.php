<?php
class NotificationManager {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        $this->pdo = db_connection();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Invia notifica a tutti i referenti di un'azienda
     */
    public function notificaReferentiAzienda($azienda_id, $tipo_notifica, $oggetto, $contenuto) {
        try {
            // Verifica se l'admin vuole inviare questa notifica
            $auth = Auth::getInstance();
            $user = $auth->getUser();
            
            if ($user && $this->adminHaDisabilitatoNotifica($user['id'], $tipo_notifica)) {
                return false;
            }
            
            // Ottieni tutti i referenti attivi che ricevono notifiche
            $sql = "SELECT * FROM referenti_aziende 
                    WHERE azienda_id = ? 
                    AND attivo = TRUE 
                    AND riceve_notifiche_email = TRUE";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$azienda_id]);
            $referenti = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($referenti as $referente) {
                $this->aggiungiNotifica(
                    $referente['email'],
                    $referente['nome'] . ' ' . $referente['cognome'],
                    $oggetto,
                    $this->personalizzaContenuto($contenuto, $referente),
                    $tipo_notifica,
                    $azienda_id
                );
            }
            
            return true;
        } catch (PDOException $e) {
            error_log("Errore NotificationManager - notificaReferentiAzienda: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Aggiungi notifica alla coda
     */
    public function aggiungiNotifica($email, $nome, $oggetto, $contenuto, $tipo = null, $azienda_id = null, $priorita = 5) {
        try {
            // Adatta alla struttura esistente della tabella
            $sql = "INSERT INTO notifiche_email (
                        destinatario, oggetto, contenuto, 
                        tipo, azienda_id, stato
                    ) VALUES (?, ?, ?, ?, ?, 'in_coda')";
            
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                $email, $oggetto, $contenuto, 
                $tipo, $azienda_id
            ]);
        } catch (PDOException $e) {
            error_log("Errore NotificationManager - aggiungiNotifica: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Notifica super admin per tutte le attività
     */
    public function notificaSuperAdmin($tipo_notifica, $oggetto, $contenuto, $azienda_id = null) {
        try {
            // Ottieni tutti i super admin attivi
            $sql = "SELECT * FROM utenti 
                    WHERE ruolo = 'super_admin' 
                    AND attivo = TRUE";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $superAdmins = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($superAdmins as $admin) {
                $this->aggiungiNotifica(
                    $admin['email'],
                    $admin['nome'] . ' ' . $admin['cognome'],
                    $oggetto,
                    $contenuto,
                    $tipo_notifica,
                    $azienda_id,
                    1 // Priorità alta per super admin
                );
            }
            
            return true;
        } catch (PDOException $e) {
            error_log("Errore NotificationManager - notificaSuperAdmin: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Notifica file caricato
     */
    public function notificaFileCaricato($file_info) {
        try {
            // Controlla se le notifiche sono abilitate
            $stmt = $this->pdo->prepare("SELECT valore FROM configurazioni WHERE chiave = 'notify_file_uploaded'");
            $stmt->execute();
            $result = $stmt->fetch();
            
            if (!$result || $result['valore'] != '1') {
                return false;
            }
            
            $oggetto = "Nuovo file caricato: " . $file_info['nome'];
            $contenuto = $this->getTemplateFileCaricato($file_info);
            
            // Notifica referenti azienda
            $result1 = $this->notificaReferentiAzienda(
                $file_info['azienda_id'], 
                'file_caricato',
                $oggetto,
                $contenuto
            );
            
            // Notifica super admin
            $result2 = $this->notificaSuperAdmin(
                'file_caricato',
                $oggetto,
                $contenuto,
                $file_info['azienda_id']
            );
            
            return $result1 || $result2;
        } catch (Exception $e) {
            error_log("Errore notifica file caricato: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Notifica file sostituito
     */
    public function notificaFileSostituito($file_info) {
        try {
            // Controlla se le notifiche sono abilitate
            $stmt = $this->pdo->prepare("SELECT valore FROM configurazioni WHERE chiave = 'notify_file_replaced'");
            $stmt->execute();
            $result = $stmt->fetch();
            
            if (!$result || $result['valore'] != '1') {
                return false;
            }
            
            $oggetto = "File sostituito: " . $file_info['nome'];
            $contenuto = $this->getTemplateFileSostituito($file_info);
            
            // Notifica referenti azienda
            $result1 = $this->notificaReferentiAzienda(
                $file_info['azienda_id'], 
                'file_sostituito',
                $oggetto,
                $contenuto
            );
            
            // Notifica super admin
            $result2 = $this->notificaSuperAdmin(
                'file_sostituito',
                $oggetto,
                $contenuto,
                $file_info['azienda_id']
            );
            
            return $result1 || $result2;
        } catch (Exception $e) {
            error_log("Errore notifica file sostituito: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Notifica file eliminato
     */
    public function notificaFileEliminato($file_info) {
        try {
            // Controlla se le notifiche sono abilitate
            $stmt = $this->pdo->prepare("SELECT valore FROM configurazioni WHERE chiave = 'notify_file_deleted'");
            $stmt->execute();
            $result = $stmt->fetch();
            
            if (!$result || $result['valore'] != '1') {
                return false;
            }
            
            $oggetto = "File eliminato: " . $file_info['nome'];
            $contenuto = $this->getTemplateFileEliminato($file_info);
            
            // Notifica referenti azienda
            $result1 = $this->notificaReferentiAzienda(
                $file_info['azienda_id'], 
                'file_eliminato',
                $oggetto,
                $contenuto
            );
            
            // Notifica super admin
            $result2 = $this->notificaSuperAdmin(
                'file_eliminato',
                $oggetto,
                $contenuto,
                $file_info['azienda_id']
            );
            
            return $result1 || $result2;
        } catch (Exception $e) {
            error_log("Errore notifica file eliminato: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Notifica cartella creata
     */
    public function notificaCartellaCreata($cartella_info) {
        try {
            // Controlla se le notifiche sono abilitate
            $stmt = $this->pdo->prepare("SELECT valore FROM configurazioni WHERE chiave = 'notify_folder_created'");
            $stmt->execute();
            $result = $stmt->fetch();
            
            if (!$result || $result['valore'] != '1') {
                return false;
            }
            
            $oggetto = "Nuova cartella creata: " . $cartella_info['nome'];
            $contenuto = $this->getTemplateCartellaCreata($cartella_info);
            
            // Notifica referenti azienda
            $result1 = $this->notificaReferentiAzienda(
                $cartella_info['azienda_id'], 
                'cartella_creata',
                $oggetto,
                $contenuto
            );
            
            // Notifica super admin
            $result2 = $this->notificaSuperAdmin(
                'cartella_creata',
                $oggetto,
                $contenuto,
                $cartella_info['azienda_id']
            );
            
            return $result1 || $result2;
        } catch (Exception $e) {
            error_log("Errore notifica cartella creata: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Notifica creazione documento (legacy - mantenuto per compatibilità)
     */
    public function notificaDocumentoCreato($documento_id) {
        $sql = "SELECT d.*, a.nome as azienda_nome, u.nome as creatore_nome, u.cognome as creatore_cognome 
                FROM documenti d
                JOIN aziende a ON d.azienda_id = a.id
                LEFT JOIN utenti u ON d.creato_da = u.id
                WHERE d.id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$documento_id]);
        $documento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$documento) return false;
        
        $oggetto = "Nuovo documento creato: " . $documento['titolo'];
        $contenuto = $this->getTemplateDocumentoCreato($documento);
        
        // Notifica referenti azienda
        $result1 = $this->notificaReferentiAzienda(
            $documento['azienda_id'], 
            'documento_creato',
            $oggetto,
            $contenuto
        );
        
        // Notifica super admin
        $result2 = $this->notificaSuperAdmin(
            'documento_creato',
            $oggetto,
            $contenuto,
            $documento['azienda_id']
        );
        
        return $result1 || $result2;
    }
    
    /**
     * Notifica modifica documento
     */
    public function notificaDocumentoModificato($documento_id, $modifiche = []) {
        $sql = "SELECT d.*, a.nome as azienda_nome, u.nome as modificatore_nome, u.cognome as modificatore_cognome 
                FROM documenti d
                JOIN aziende a ON d.azienda_id = a.id
                LEFT JOIN utenti u ON d.aggiornato_da = u.id
                WHERE d.id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$documento_id]);
        $documento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$documento) return false;
        
        $oggetto = "Documento modificato: " . $documento['titolo'];
        $contenuto = $this->getTemplateDocumentoModificato($documento, $modifiche);
        
        // Notifica referenti azienda
        $result1 = $this->notificaReferentiAzienda(
            $documento['azienda_id'], 
            'documento_modificato',
            $oggetto,
            $contenuto
        );
        
        // Notifica super admin
        $result2 = $this->notificaSuperAdmin(
            'documento_modificato',
            $oggetto,
            $contenuto,
            $documento['azienda_id']
        );
        
        return $result1 || $result2;
    }
    
    /**
     * Verifica se admin ha disabilitato notifica
     */
    private function adminHaDisabilitatoNotifica($admin_id, $tipo_notifica) {
        $sql = "SELECT invia_a_referenti FROM preferenze_notifiche_admin 
                WHERE admin_id = ? AND tipo_notifica = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$admin_id, $tipo_notifica]);
        $pref = $stmt->fetch(PDO::FETCH_ASSOC);
        return $pref && !$pref['invia_a_referenti'];
    }
    
    /**
     * Template email file caricato
     */
    private function getTemplateFileCaricato($file_info) {
        require_once __DIR__ . '/EmailTemplate.php';
        
        $creator = [
            'nome' => $file_info['caricato_da_nome'] ?? 'Utente',
            'cognome' => $file_info['caricato_da_cognome'] ?? ''
        ];
        
        return EmailTemplate::fileUploaded($file_info, $creator);
    }
    
    /**
     * Template email file sostituito
     */
    private function getTemplateFileSostituito($file_info) {
        require_once __DIR__ . '/EmailTemplate.php';
        
        $updatedBy = [
            'nome' => $file_info['sostituito_da_nome'] ?? 'Utente',
            'cognome' => $file_info['sostituito_da_cognome'] ?? ''
        ];
        
        return EmailTemplate::fileReplaced($file_info, $updatedBy);
    }
    
    /**
     * Template email file eliminato
     */
    private function getTemplateFileEliminato($file_info) {
        require_once __DIR__ . '/EmailTemplate.php';
        
        $deletedBy = [
            'nome' => $file_info['eliminato_da_nome'] ?? 'Utente',
            'cognome' => $file_info['eliminato_da_cognome'] ?? ''
        ];
        
        return EmailTemplate::fileDeleted($file_info, $deletedBy);
    }
    
    /**
     * Template email cartella creata
     */
    private function getTemplateCartellaCreata($cartella_info) {
        require_once __DIR__ . '/EmailTemplate.php';
        
        $creator = [
            'nome' => $cartella_info['creata_da_nome'] ?? 'Utente',
            'cognome' => $cartella_info['creata_da_cognome'] ?? ''
        ];
        
        return EmailTemplate::folderCreated($cartella_info, $creator);
    }
    
    /**
     * Template email documento creato
     */
    private function getTemplateDocumentoCreato($documento) {
        require_once __DIR__ . '/EmailTemplate.php';
        
        $creator = [
            'nome' => $documento['creatore_nome'],
            'cognome' => $documento['creatore_cognome']
        ];
        
        $documento['nome'] = $documento['titolo'];
        $documento['tipo'] = $documento['categoria'] ?? 'Documento';
        
        return EmailTemplate::newDocument($documento, $creator);
    }
    
    /**
     * Template email documento modificato
     */
    private function getTemplateDocumentoModificato($documento, $modifiche) {
        require_once __DIR__ . '/EmailTemplate.php';
        
        $updatedBy = [
            'nome' => $documento['modificatore_nome'],
            'cognome' => $documento['modificatore_cognome']
        ];
        
        $documento['nome'] = $documento['titolo'];
        
        return EmailTemplate::documentModified($documento, $updatedBy);
    }
    
    /**
     * Personalizza contenuto per referente
     */
    private function personalizzaContenuto($contenuto, $referente) {
        $contenuto = str_replace('{nome}', $referente['nome'], $contenuto);
        $contenuto = str_replace('{cognome}', $referente['cognome'], $contenuto);
        $contenuto = str_replace('{ruolo}', $referente['ruolo_aziendale'], $contenuto);
        return $contenuto;
    }
    
    /**
     * Invia notifica diretta per nuovo documento
     */
    public function sendDocumentNotification($email, $titolo_documento, $documento_id, $nome_destinatario) {
        require_once __DIR__ . '/EmailTemplate.php';
        
        $oggetto = "Nuovo documento: " . $titolo_documento;
        
        $documento = [
            'id' => $documento_id,
            'nome' => $titolo_documento,
            'tipo' => 'Documento'
        ];
        
        $creator = [
            'nome' => 'Sistema',
            'cognome' => ''
        ];
        
        $contenuto = EmailTemplate::newDocument($documento, $creator);
        
        // Aggiungi alla coda notifiche
        $this->aggiungiNotifica(
            $email,
            $nome_destinatario,
            $oggetto,
            $contenuto,
            'documento_destinatario',
            null,
            1 // Alta priorità
        );
        
        // Prova a inviare immediatamente
        try {
            require_once __DIR__ . '/Mailer.php';
            $mailer = Mailer::getInstance();
            
            if ($mailer->isConfigured()) {
                $mailer->send($email, $oggetto, $contenuto);
            }
        } catch (Exception $e) {
            error_log("Errore invio email immediata: " . $e->getMessage());
        }
        
        return true;
    }
    
    /**
     * Notifica assegnazione task
     */
    public function notificaTaskAssegnato($task, $assegnato_a, $assegnato_da) {
        try {
            require_once __DIR__ . '/EmailTemplate.php';
            
            $oggetto = "Nuovo Task Assegnato - " . $task['attivita'];
            $contenuto = EmailTemplate::taskAssigned($task, $assegnato_a, $assegnato_da);
            
            // Aggiungi alla coda notifiche
            $this->aggiungiNotifica(
                $assegnato_a['email'],
                $assegnato_a['nome'] . ' ' . $assegnato_a['cognome'],
                $oggetto,
                $contenuto,
                'task_assegnato',
                $task['azienda_id'],
                1 // Alta priorità
            );
            
            // Prova a inviare immediatamente
            try {
                require_once __DIR__ . '/Mailer.php';
                $mailer = Mailer::getInstance();
                
                if ($mailer->isConfigured()) {
                    $risultato = $mailer->send($assegnato_a['email'], $oggetto, $contenuto);
                    if ($risultato) {
                        error_log("Email task assegnato inviata con successo a: " . $assegnato_a['email']);
                    } else {
                        error_log("Errore invio email task assegnato a: " . $assegnato_a['email']);
                    }
                }
            } catch (Exception $e) {
                error_log("Errore invio email task assegnato: " . $e->getMessage());
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Errore notifica task assegnato: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Notifica cambio stato task
     */
    public function notificaTaskStatoCambiato($task, $old_status, $new_status, $changed_by, $assegnato_da) {
        try {
            require_once __DIR__ . '/EmailTemplate.php';
            
            // Mappa degli stati in italiano
            $stati = [
                'assegnato' => 'Assegnato',
                'in_corso' => 'In Corso',
                'completato' => 'Completato',
                'annullato' => 'Annullato'
            ];
            
            $nuovo_stato = $stati[$new_status] ?? $new_status;
            
            $oggetto = "Task Aggiornato - " . $task['attivita'] . " - Stato: " . $nuovo_stato;
            $contenuto = EmailTemplate::taskStatusChanged($task, $old_status, $new_status, $changed_by);
            
            // Notifica chi ha assegnato il task
            if ($assegnato_da && isset($assegnato_da['email'])) {
                $this->aggiungiNotifica(
                    $assegnato_da['email'],
                    $assegnato_da['nome'] . ' ' . $assegnato_da['cognome'],
                    $oggetto,
                    $contenuto,
                    'task_aggiornato',
                    $task['azienda_id'],
                    1 // Alta priorità
                );
                
                // Se il task è completato, invia anche una notifica specifica
                if ($new_status == 'completato') {
                    $oggetto_completato = "Task Completato - " . $task['attivita'];
                    $contenuto_completato = EmailTemplate::taskCompleted($task, $changed_by, $assegnato_da);
                    
                    $this->aggiungiNotifica(
                        $assegnato_da['email'],
                        $assegnato_da['nome'] . ' ' . $assegnato_da['cognome'],
                        $oggetto_completato,
                        $contenuto_completato,
                        'task_completato',
                        $task['azienda_id'],
                        1 // Alta priorità
                    );
                }
                
                // Prova a inviare immediatamente
                try {
                    require_once __DIR__ . '/Mailer.php';
                    $mailer = Mailer::getInstance();
                    
                    if ($mailer->isConfigured()) {
                        $risultato = $mailer->send($assegnato_da['email'], $oggetto, $contenuto);
                        if ($risultato) {
                            error_log("Email cambio stato task inviata con successo a: " . $assegnato_da['email']);
                        } else {
                            error_log("Errore invio email cambio stato task a: " . $assegnato_da['email']);
                        }
                    }
                } catch (Exception $e) {
                    error_log("Errore invio email cambio stato task: " . $e->getMessage());
                }
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Errore notifica cambio stato task: " . $e->getMessage());
            return false;
        }
    }
} 