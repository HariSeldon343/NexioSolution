<?php
/**
 * Permission Helper Functions - Funzioni di supporto per gestione permessi
 * 
 * Fornisce funzioni di utilità per gestire permessi nel database
 * e operazioni comuni legate alla sicurezza
 * 
 * @author Nexio Platform
 * @version 1.0.0
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../utils/ActivityLogger.php';

/**
 * Inizializza le tabelle dei permessi se non esistono
 */
function initializePermissionTables() {
    try {
        // Crea tabelle permessi documenti se non esistono
        db_query("
            CREATE TABLE IF NOT EXISTS document_permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                document_id INT NOT NULL,
                user_id INT NULL,
                role VARCHAR(50) NULL,
                permission_type ENUM('read', 'write', 'delete', 'share', 'approve', 'download') NOT NULL,
                granted_by INT NOT NULL,
                granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NULL,
                active BOOLEAN DEFAULT TRUE,
                azienda_id INT NOT NULL,
                INDEX idx_document_id (document_id),
                INDEX idx_user_id (user_id),
                INDEX idx_role (role),
                INDEX idx_permission_type (permission_type),
                INDEX idx_azienda_id (azienda_id),
                INDEX idx_active (active),
                INDEX idx_expires_at (expires_at),
                FOREIGN KEY (document_id) REFERENCES documenti(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES utenti(id) ON DELETE CASCADE,
                FOREIGN KEY (granted_by) REFERENCES utenti(id) ON DELETE RESTRICT,
                FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Crea tabelle permessi cartelle se non esistono
        db_query("
            CREATE TABLE IF NOT EXISTS folder_permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                folder_id INT NOT NULL,
                user_id INT NULL,
                role VARCHAR(50) NULL,
                permission_type ENUM('read', 'write', 'delete', 'share', 'create') NOT NULL,
                granted_by INT NOT NULL,
                granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NULL,
                active BOOLEAN DEFAULT TRUE,
                azienda_id INT NOT NULL,
                INDEX idx_folder_id (folder_id),
                INDEX idx_user_id (user_id),
                INDEX idx_role (role),
                INDEX idx_permission_type (permission_type),
                INDEX idx_azienda_id (azienda_id),
                INDEX idx_active (active),
                INDEX idx_expires_at (expires_at),
                FOREIGN KEY (folder_id) REFERENCES cartelle(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES utenti(id) ON DELETE CASCADE,
                FOREIGN KEY (granted_by) REFERENCES utenti(id) ON DELETE RESTRICT,
                FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Crea tabella permessi ISO se non esiste
        db_query("
            CREATE TABLE IF NOT EXISTS iso_permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                permission_name VARCHAR(100) UNIQUE NOT NULL,
                permission_description TEXT,
                category ENUM('document', 'structure', 'compliance', 'audit', 'admin') NOT NULL,
                active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Crea tabella associazioni utenti-permessi ISO
        db_query("
            CREATE TABLE IF NOT EXISTS iso_user_permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                permission_id INT NOT NULL,
                granted_by INT NOT NULL,
                granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NULL,
                active BOOLEAN DEFAULT TRUE,
                azienda_id INT NOT NULL,
                INDEX idx_user_id (user_id),
                INDEX idx_permission_id (permission_id),
                INDEX idx_azienda_id (azienda_id),
                INDEX idx_active (active),
                INDEX idx_expires_at (expires_at),
                UNIQUE KEY unique_user_permission (user_id, permission_id, azienda_id),
                FOREIGN KEY (user_id) REFERENCES utenti(id) ON DELETE CASCADE,
                FOREIGN KEY (permission_id) REFERENCES iso_permissions(id) ON DELETE CASCADE,
                FOREIGN KEY (granted_by) REFERENCES utenti(id) ON DELETE RESTRICT,
                FOREIGN KEY (azienda_id) REFERENCES aziende(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Inserisci permessi ISO base se non esistono
        insertBasicISOPermissions();
        
        return true;
        
    } catch (Exception $e) {
        error_log("Errore inizializzazione tabelle permessi: " . $e->getMessage());
        return false;
    }
}

/**
 * Inserisce permessi ISO di base
 */
function insertBasicISOPermissions() {
    $permissions = [
        ['iso_configure', 'Configurazione strutture ISO', 'structure'],
        ['iso_manage_compliance', 'Gestione conformità', 'compliance'],
        ['iso_audit_access', 'Accesso audit', 'audit'],
        ['iso_structure_admin', 'Amministrazione strutture', 'admin'],
        ['iso_document_approve', 'Approvazione documenti ISO', 'document'],
        ['iso_document_publish', 'Pubblicazione documenti ISO', 'document'],
        ['iso_template_manage', 'Gestione template ISO', 'structure'],
        ['iso_report_generate', 'Generazione report', 'compliance']
    ];
    
    foreach ($permissions as $permission) {
        try {
            $existing = db_query(
                "SELECT id FROM iso_permissions WHERE permission_name = ?", 
                [$permission[0]]
            )->fetch();
            
            if (!$existing) {
                db_insert('iso_permissions', [
                    'permission_name' => $permission[0],
                    'permission_description' => $permission[1],
                    'category' => $permission[2]
                ]);
            }
        } catch (Exception $e) {
            error_log("Errore inserimento permesso {$permission[0]}: " . $e->getMessage());
        }
    }
}

/**
 * Verifica e crea permesso documento specifico
 */
function grantDocumentPermission($documentId, $userId, $permission, $grantedBy, $companyId, $expiresAt = null) {
    try {
        // Verifica che il documento esista e appartenga all'azienda
        $document = db_query(
            "SELECT id FROM documenti WHERE id = ? AND azienda_id = ?", 
            [$documentId, $companyId]
        )->fetch();
        
        if (!$document) {
            throw new Exception("Documento non trovato o non appartiene all'azienda");
        }
        
        // Verifica che l'utente esista
        $user = db_query("SELECT id FROM utenti WHERE id = ?", [$userId])->fetch();
        if (!$user) {
            throw new Exception("Utente non trovato");
        }
        
        // Rimuovi permesso esistente se presente
        db_delete('document_permissions', 
            'document_id = ? AND user_id = ? AND permission_type = ? AND azienda_id = ?',
            [$documentId, $userId, $permission, $companyId]);
        
        // Inserisci nuovo permesso
        $permissionId = db_insert('document_permissions', [
            'document_id' => $documentId,
            'user_id' => $userId,
            'permission_type' => $permission,
            'granted_by' => $grantedBy,
            'expires_at' => $expiresAt,
            'azienda_id' => $companyId
        ]);
        
        // Log attività
        ActivityLogger::getInstance()->log('document_permission_granted', 'document_permissions', $permissionId, [
            'document_id' => $documentId,
            'user_id' => $userId,
            'permission' => $permission,
            'granted_by' => $grantedBy,
            'expires_at' => $expiresAt
        ]);
        
        return $permissionId;
        
    } catch (Exception $e) {
        error_log("Errore grant document permission: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Verifica e crea permesso cartella specifico
 */
function grantFolderPermission($folderId, $userId, $permission, $grantedBy, $companyId, $expiresAt = null) {
    try {
        // Verifica che la cartella esista e appartenga all'azienda
        $folder = db_query(
            "SELECT id FROM cartelle WHERE id = ? AND azienda_id = ?", 
            [$folderId, $companyId]
        )->fetch();
        
        if (!$folder) {
            throw new Exception("Cartella non trovata o non appartiene all'azienda");
        }
        
        // Verifica che l'utente esista
        $user = db_query("SELECT id FROM utenti WHERE id = ?", [$userId])->fetch();
        if (!$user) {
            throw new Exception("Utente non trovato");
        }
        
        // Rimuovi permesso esistente se presente
        db_delete('folder_permissions', 
            'folder_id = ? AND user_id = ? AND permission_type = ? AND azienda_id = ?',
            [$folderId, $userId, $permission, $companyId]);
        
        // Inserisci nuovo permesso
        $permissionId = db_insert('folder_permissions', [
            'folder_id' => $folderId,
            'user_id' => $userId,
            'permission_type' => $permission,
            'granted_by' => $grantedBy,
            'expires_at' => $expiresAt,
            'azienda_id' => $companyId
        ]);
        
        // Log attività
        ActivityLogger::getInstance()->log('folder_permission_granted', 'folder_permissions', $permissionId, [
            'folder_id' => $folderId,
            'user_id' => $userId,
            'permission' => $permission,
            'granted_by' => $grantedBy,
            'expires_at' => $expiresAt
        ]);
        
        return $permissionId;
        
    } catch (Exception $e) {
        error_log("Errore grant folder permission: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Revoca permesso documento
 */
function revokeDocumentPermission($documentId, $userId, $permission, $revokedBy, $companyId) {
    try {
        $result = db_delete('document_permissions', 
            'document_id = ? AND user_id = ? AND permission_type = ? AND azienda_id = ?',
            [$documentId, $userId, $permission, $companyId]);
        
        if ($result) {
            ActivityLogger::getInstance()->log('document_permission_revoked', 'documenti', $documentId, [
                'user_id' => $userId,
                'permission' => $permission,
                'revoked_by' => $revokedBy
            ]);
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Errore revoke document permission: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Revoca permesso cartella
 */
function revokeFolderPermission($folderId, $userId, $permission, $revokedBy, $companyId) {
    try {
        $result = db_delete('folder_permissions', 
            'folder_id = ? AND user_id = ? AND permission_type = ? AND azienda_id = ?',
            [$folderId, $userId, $permission, $companyId]);
        
        if ($result) {
            ActivityLogger::getInstance()->log('folder_permission_revoked', 'cartelle', $folderId, [
                'user_id' => $userId,
                'permission' => $permission,
                'revoked_by' => $revokedBy
            ]);
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Errore revoke folder permission: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Ottieni permessi documento per utente
 */
function getDocumentPermissions($documentId, $userId, $companyId) {
    try {
        $stmt = db_query("
            SELECT permission_type, granted_at, expires_at, granted_by
            FROM document_permissions 
            WHERE document_id = ? AND user_id = ? AND azienda_id = ? AND active = 1
            AND (expires_at IS NULL OR expires_at > NOW())
        ", [$documentId, $userId, $companyId]);
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Errore get document permissions: " . $e->getMessage());
        return [];
    }
}

/**
 * Ottieni permessi cartella per utente
 */
function getFolderPermissions($folderId, $userId, $companyId) {
    try {
        $stmt = db_query("
            SELECT permission_type, granted_at, expires_at, granted_by
            FROM folder_permissions 
            WHERE folder_id = ? AND user_id = ? AND azienda_id = ? AND active = 1
            AND (expires_at IS NULL OR expires_at > NOW())
        ", [$folderId, $userId, $companyId]);
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Errore get folder permissions: " . $e->getMessage());
        return [];
    }
}

/**
 * Ottieni tutti gli utenti con permessi su documento
 */
function getDocumentUsers($documentId, $companyId) {
    try {
        $stmt = db_query("
            SELECT DISTINCT u.id, u.username, u.nome, u.cognome, u.email, u.ruolo,
                   dp.permission_type, dp.granted_at, dp.expires_at
            FROM document_permissions dp
            JOIN utenti u ON dp.user_id = u.id
            WHERE dp.document_id = ? AND dp.azienda_id = ? AND dp.active = 1
            AND (dp.expires_at IS NULL OR dp.expires_at > NOW())
            ORDER BY u.nome, u.cognome
        ", [$documentId, $companyId]);
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Errore get document users: " . $e->getMessage());
        return [];
    }
}

/**
 * Ottieni tutti gli utenti con permessi su cartella
 */
function getFolderUsers($folderId, $companyId) {
    try {
        $stmt = db_query("
            SELECT DISTINCT u.id, u.username, u.nome, u.cognome, u.email, u.ruolo,
                   fp.permission_type, fp.granted_at, fp.expires_at
            FROM folder_permissions fp
            JOIN utenti u ON fp.user_id = u.id
            WHERE fp.folder_id = ? AND fp.azienda_id = ? AND fp.active = 1
            AND (fp.expires_at IS NULL OR fp.expires_at > NOW())
            ORDER BY u.nome, u.cognome
        ", [$folderId, $companyId]);
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Errore get folder users: " . $e->getMessage());
        return [];
    }
}

/**
 * Pulisce permessi scaduti
 */
function cleanupExpiredPermissions() {
    try {
        db_begin_transaction();
        
        // Disattiva permessi documenti scaduti
        $stmt1 = db_query("
            UPDATE document_permissions 
            SET active = 0 
            WHERE expires_at IS NOT NULL AND expires_at <= NOW() AND active = 1
        ");
        
        // Disattiva permessi cartelle scaduti
        $stmt2 = db_query("
            UPDATE folder_permissions 
            SET active = 0 
            WHERE expires_at IS NOT NULL AND expires_at <= NOW() AND active = 1
        ");
        
        // Disattiva permessi ISO scaduti
        $stmt3 = db_query("
            UPDATE iso_user_permissions 
            SET active = 0 
            WHERE expires_at IS NOT NULL AND expires_at <= NOW() AND active = 1
        ");
        
        $cleanedCount = $stmt1->rowCount() + $stmt2->rowCount() + $stmt3->rowCount();
        
        if ($cleanedCount > 0) {
            ActivityLogger::getInstance()->log('permissions_cleanup', 'system', null, [
                'cleaned_permissions' => $cleanedCount,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
        
        db_commit();
        return $cleanedCount;
        
    } catch (Exception $e) {
        db_rollback();
        error_log("Errore cleanup expired permissions: " . $e->getMessage());
        return false;
    }
}

/**
 * Copia permessi da un documento ad un altro
 */
function copyDocumentPermissions($sourceDocumentId, $targetDocumentId, $copiedBy, $companyId) {
    try {
        db_begin_transaction();
        
        // Ottieni permessi del documento sorgente
        $stmt = db_query("
            SELECT user_id, permission_type, expires_at
            FROM document_permissions 
            WHERE document_id = ? AND azienda_id = ? AND active = 1
        ", [$sourceDocumentId, $companyId]);
        
        $permissions = $stmt->fetchAll();
        $copiedCount = 0;
        
        foreach ($permissions as $permission) {
            try {
                grantDocumentPermission(
                    $targetDocumentId,
                    $permission['user_id'],
                    $permission['permission_type'],
                    $copiedBy,
                    $companyId,
                    $permission['expires_at']
                );
                $copiedCount++;
            } catch (Exception $e) {
                // Log errore ma continua con altri permessi
                error_log("Errore copia permesso: " . $e->getMessage());
            }
        }
        
        ActivityLogger::getInstance()->log('document_permissions_copied', 'documenti', $targetDocumentId, [
            'source_document_id' => $sourceDocumentId,
            'copied_permissions' => $copiedCount,
            'copied_by' => $copiedBy
        ]);
        
        db_commit();
        return $copiedCount;
        
    } catch (Exception $e) {
        db_rollback();
        error_log("Errore copy document permissions: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Eredita permessi da cartella parent
 */
function inheritFolderPermissions($childFolderId, $parentFolderId, $inheritedBy, $companyId) {
    try {
        db_begin_transaction();
        
        // Ottieni permessi della cartella parent
        $stmt = db_query("
            SELECT user_id, permission_type, expires_at
            FROM folder_permissions 
            WHERE folder_id = ? AND azienda_id = ? AND active = 1
        ", [$parentFolderId, $companyId]);
        
        $permissions = $stmt->fetchAll();
        $inheritedCount = 0;
        
        foreach ($permissions as $permission) {
            try {
                grantFolderPermission(
                    $childFolderId,
                    $permission['user_id'],
                    $permission['permission_type'],
                    $inheritedBy,
                    $companyId,
                    $permission['expires_at']
                );
                $inheritedCount++;
            } catch (Exception $e) {
                // Log errore ma continua con altri permessi
                error_log("Errore eredità permesso: " . $e->getMessage());
            }
        }
        
        ActivityLogger::getInstance()->log('folder_permissions_inherited', 'cartelle', $childFolderId, [
            'parent_folder_id' => $parentFolderId,
            'inherited_permissions' => $inheritedCount,
            'inherited_by' => $inheritedBy
        ]);
        
        db_commit();
        return $inheritedCount;
        
    } catch (Exception $e) {
        db_rollback();
        error_log("Errore inherit folder permissions: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Ottieni statistiche permessi azienda
 */
function getCompanyPermissionStats($companyId) {
    try {
        $stats = [];
        
        // Conteggio permessi documenti attivi
        $stmt = db_query("
            SELECT COUNT(*) as total_document_permissions
            FROM document_permissions 
            WHERE azienda_id = ? AND active = 1
            AND (expires_at IS NULL OR expires_at > NOW())
        ", [$companyId]);
        $stats['document_permissions'] = $stmt->fetchColumn();
        
        // Conteggio permessi cartelle attivi
        $stmt = db_query("
            SELECT COUNT(*) as total_folder_permissions
            FROM folder_permissions 
            WHERE azienda_id = ? AND active = 1
            AND (expires_at IS NULL OR expires_at > NOW())
        ", [$companyId]);
        $stats['folder_permissions'] = $stmt->fetchColumn();
        
        // Conteggio utenti con permessi speciali
        $stmt = db_query("
            SELECT COUNT(DISTINCT user_id) as users_with_special_permissions
            FROM (
                SELECT user_id FROM document_permissions WHERE azienda_id = ? AND active = 1
                UNION
                SELECT user_id FROM folder_permissions WHERE azienda_id = ? AND active = 1
            ) combined
        ", [$companyId, $companyId]);
        $stats['users_with_permissions'] = $stmt->fetchColumn();
        
        // Permessi in scadenza nei prossimi 7 giorni
        $stmt = db_query("
            SELECT COUNT(*) as expiring_permissions
            FROM (
                SELECT expires_at FROM document_permissions 
                WHERE azienda_id = ? AND active = 1 AND expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
                UNION ALL
                SELECT expires_at FROM folder_permissions 
                WHERE azienda_id = ? AND active = 1 AND expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
            ) combined
        ", [$companyId, $companyId]);
        $stats['expiring_permissions'] = $stmt->fetchColumn();
        
        return $stats;
        
    } catch (Exception $e) {
        error_log("Errore get permission stats: " . $e->getMessage());
        return null;
    }
}

// Inizializza tabelle permessi all'inclusione del file
if (function_exists('db_connection')) {
    initializePermissionTables();
}