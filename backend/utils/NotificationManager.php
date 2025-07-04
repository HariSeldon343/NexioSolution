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
            $sql = "INSERT INTO notifiche_email (
                        destinatario_email, destinatario_nome, oggetto, 
                        contenuto, tipo_notifica, azienda_id, priorita
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                $email, $nome, $oggetto, $contenuto, 
                $tipo, $azienda_id, $priorita
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
     * Notifica creazione documento
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
} 