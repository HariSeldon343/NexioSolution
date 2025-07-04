<?php
/**
 * Modello User
 * Gestisce tutte le operazioni relative agli utenti
 */

class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Trova un utente per ID
     */
    public function findById($id) {
        $sql = "SELECT id, username, email, nome, cognome, telefono, ruolo, attivo, 
                       data_creazione, ultimo_accesso 
                FROM utenti 
                WHERE id = :id";
        
        $stmt = $this->db->query($sql, ['id' => $id]);
        return $stmt->fetch();
    }
    
    /**
     * Trova un utente per username
     */
    public function findByUsername($username) {
        $sql = "SELECT * FROM utenti WHERE username = :username";
        $stmt = $this->db->query($sql, ['username' => $username]);
        return $stmt->fetch();
    }
    
    /**
     * Trova un utente per email
     */
    public function findByEmail($email) {
        $sql = "SELECT * FROM utenti WHERE email = :email";
        $stmt = $this->db->query($sql, ['email' => $email]);
        return $stmt->fetch();
    }
    
    /**
     * Verifica le credenziali di login
     */
    public function verifyCredentials($username, $password) {
        $user = $this->findByUsername($username);
        
        if (!$user) {
            // Prova con l'email
            $user = $this->findByEmail($username);
        }
        
        if ($user && password_verify($password, $user['password'])) {
            if (!$user['attivo']) {
                return ['success' => false, 'message' => 'Account non attivo'];
            }
            
            // Aggiorna ultimo accesso
            $this->updateLastAccess($user['id']);
            
            // Rimuovi la password dai dati restituiti
            unset($user['password']);
            
            return ['success' => true, 'user' => $user];
        }
        
        return ['success' => false, 'message' => MSG_LOGIN_FAILED];
    }
    
    /**
     * Crea un nuovo utente
     */
    public function create($data) {
        try {
            // Valida i dati
            $validation = $this->validateUserData($data);
            if (!$validation['valid']) {
                return ['success' => false, 'message' => $validation['message']];
            }
            
            // Hash della password
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => HASH_COST]);
            
            // Rimuovi campi non necessari
            unset($data['password_confirm']);
            
            // Imposta valori predefiniti
            $data['attivo'] = $data['attivo'] ?? true;
            $data['ruolo'] = $data['ruolo'] ?? ROLE_CLIENTE;
            
            $userId = $this->db->insert('utenti', $data);
            
            return ['success' => true, 'id' => $userId];
            
        } catch (Exception $e) {
            error_log("Errore creazione utente: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante la creazione dell\'utente'];
        }
    }
    
    /**
     * Aggiorna un utente
     */
    public function update($id, $data) {
        try {
            // Se è presente una nuova password, esegui l'hash
            if (!empty($data['password'])) {
                $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => HASH_COST]);
            } else {
                // Rimuovi il campo password se vuoto
                unset($data['password']);
            }
            
            // Rimuovi campi non necessari
            unset($data['password_confirm'], $data['id']);
            
            $affected = $this->db->update('utenti', $data, 'id = :id', ['id' => $id]);
            
            return ['success' => true, 'affected' => $affected];
            
        } catch (Exception $e) {
            error_log("Errore aggiornamento utente: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante l\'aggiornamento dell\'utente'];
        }
    }
    
    /**
     * Elimina un utente
     */
    public function delete($id) {
        try {
            $affected = $this->db->delete('utenti', 'id = :id', ['id' => $id]);
            return ['success' => true, 'affected' => $affected];
        } catch (Exception $e) {
            error_log("Errore eliminazione utente: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante l\'eliminazione dell\'utente'];
        }
    }
    
    /**
     * Ottiene tutti gli utenti con filtri opzionali
     */
    public function getAll($filters = []) {
        $sql = "SELECT id, username, email, nome, cognome, telefono, ruolo, attivo, 
                       data_creazione, ultimo_accesso 
                FROM utenti 
                WHERE 1=1";
        $params = [];
        
        // Applica filtri
        if (!empty($filters['ruolo'])) {
            $sql .= " AND ruolo = :ruolo";
            $params['ruolo'] = $filters['ruolo'];
        }
        
        if (isset($filters['attivo'])) {
            $sql .= " AND attivo = :attivo";
            $params['attivo'] = $filters['attivo'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (username LIKE :search OR email LIKE :search OR 
                          nome LIKE :search OR cognome LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        // Ordinamento
        $orderBy = $filters['order_by'] ?? 'cognome, nome';
        $orderDir = $filters['order_dir'] ?? 'ASC';
        $sql .= " ORDER BY {$orderBy} {$orderDir}";
        
        // Paginazione
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT :limit";
            $params['limit'] = $filters['limit'];
            
            if (!empty($filters['offset'])) {
                $sql .= " OFFSET :offset";
                $params['offset'] = $filters['offset'];
            }
        }
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Conta il numero totale di utenti
     */
    public function count($filters = []) {
        $sql = "SELECT COUNT(*) as total FROM utenti WHERE 1=1";
        $params = [];
        
        if (!empty($filters['ruolo'])) {
            $sql .= " AND ruolo = :ruolo";
            $params['ruolo'] = $filters['ruolo'];
        }
        
        if (isset($filters['attivo'])) {
            $sql .= " AND attivo = :attivo";
            $params['attivo'] = $filters['attivo'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (username LIKE :search OR email LIKE :search OR 
                          nome LIKE :search OR cognome LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        $stmt = $this->db->query($sql, $params);
        $result = $stmt->fetch();
        return $result['total'];
    }
    
    /**
     * Cambia la password di un utente
     */
    public function changePassword($userId, $oldPassword, $newPassword) {
        $user = $this->findById($userId);
        
        if (!$user) {
            return ['success' => false, 'message' => 'Utente non trovato'];
        }
        
        // Recupera la password hashata
        $sql = "SELECT password FROM utenti WHERE id = :id";
        $stmt = $this->db->query($sql, ['id' => $userId]);
        $userData = $stmt->fetch();
        
        if (!password_verify($oldPassword, $userData['password'])) {
            return ['success' => false, 'message' => 'Password attuale non corretta'];
        }
        
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
        
        $this->db->update('utenti', ['password' => $hashedPassword], 'id = :id', ['id' => $userId]);
        
        return ['success' => true, 'message' => 'Password aggiornata con successo'];
    }
    
    /**
     * Attiva/disattiva un utente
     */
    public function toggleActive($userId) {
        $sql = "UPDATE utenti SET attivo = NOT attivo WHERE id = :id";
        $stmt = $this->db->query($sql, ['id' => $userId]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Aggiorna l'ultimo accesso
     */
    private function updateLastAccess($userId) {
        $this->db->update('utenti', [
            'ultimo_accesso' => date('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $userId]);
    }
    
    /**
     * Valida i dati utente
     */
    private function validateUserData($data, $isUpdate = false) {
        $errors = [];
        
        // Username
        if (!$isUpdate || !empty($data['username'])) {
            if (empty($data['username'])) {
                $errors[] = "Il nome utente è obbligatorio";
            } elseif (strlen($data['username']) < 3) {
                $errors[] = "Il nome utente deve essere lungo almeno 3 caratteri";
            } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $data['username'])) {
                $errors[] = "Il nome utente può contenere solo lettere, numeri e underscore";
            } else {
                // Verifica unicità
                $existing = $this->findByUsername($data['username']);
                if ($existing && (!$isUpdate || $existing['id'] != $data['id'])) {
                    $errors[] = "Nome utente già in uso";
                }
            }
        }
        
        // Email
        if (!$isUpdate || !empty($data['email'])) {
            if (empty($data['email'])) {
                $errors[] = "L'email è obbligatoria";
            } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Email non valida";
            } else {
                // Verifica unicità
                $existing = $this->findByEmail($data['email']);
                if ($existing && (!$isUpdate || $existing['id'] != $data['id'])) {
                    $errors[] = "Email già in uso";
                }
            }
        }
        
        // Password (solo per creazione o se fornita in aggiornamento)
        if (!$isUpdate || !empty($data['password'])) {
            if (empty($data['password'])) {
                $errors[] = "La password è obbligatoria";
            } elseif (strlen($data['password']) < 8) {
                $errors[] = "La password deve essere lunga almeno 8 caratteri";
            } elseif (!empty($data['password_confirm']) && $data['password'] !== $data['password_confirm']) {
                $errors[] = "Le password non corrispondono";
            }
        }
        
        // Nome e cognome
        if (empty($data['nome'])) {
            $errors[] = "Il nome è obbligatorio";
        }
        if (empty($data['cognome'])) {
            $errors[] = "Il cognome è obbligatorio";
        }
        
        // Ruolo
        if (!empty($data['ruolo']) && !in_array($data['ruolo'], [ROLE_ADMIN, ROLE_STAFF, ROLE_CLIENTE])) {
            $errors[] = "Ruolo non valido";
        }
        
        return [
            'valid' => empty($errors),
            'message' => implode(', ', $errors)
        ];
    }
    
    /**
     * Ottiene i permessi di un utente per un documento
     */
    public function getDocumentPermission($userId, $documentId) {
        $sql = "SELECT permesso FROM permessi_documenti 
                WHERE utente_id = :user_id AND documento_id = :doc_id";
        
        $stmt = $this->db->query($sql, [
            'user_id' => $userId,
            'doc_id' => $documentId
        ]);
        
        $result = $stmt->fetch();
        return $result ? $result['permesso'] : null;
    }
    
    /**
     * Verifica se un utente ha un determinato ruolo
     */
    public function hasRole($userId, $role) {
        $user = $this->findById($userId);
        return $user && $user['ruolo'] === $role;
    }
    
    /**
     * Verifica se un utente è amministratore
     */
    public function isAdmin($userId) {
        return $this->hasRole($userId, ROLE_ADMIN);
    }
} 