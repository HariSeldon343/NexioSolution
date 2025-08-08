-- Database tables for Nexio Calendar PWA
-- Optional tables for enhanced PWA functionality

-- Table for push notification subscriptions
CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utente_id INT NOT NULL,
    subscription_data JSON NOT NULL COMMENT 'Push subscription object from browser',
    endpoint VARCHAR(500) NOT NULL COMMENT 'Push service endpoint URL',
    p256dh_key VARCHAR(128) COMMENT 'Client public key for encryption',
    auth_key VARCHAR(128) COMMENT 'Client auth secret for encryption',
    user_agent TEXT COMMENT 'Browser user agent string',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_used TIMESTAMP NULL COMMENT 'Last time subscription was used',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Whether subscription is active',
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_endpoint (utente_id, endpoint),
    INDEX idx_active (is_active),
    INDEX idx_last_used (last_used)
) ENGINE=InnoDB COMMENT='Push notification subscriptions for PWA';

-- Table for user preferences (extends existing user_preferences if exists)
CREATE TABLE IF NOT EXISTS user_preferences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utente_id INT NOT NULL,
    tipo ENUM('calendar', 'mobile_calendar', 'notifications', 'ui') NOT NULL DEFAULT 'calendar',
    valore JSON NOT NULL COMMENT 'Preference values as JSON',
    aggiornato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_preference (utente_id, tipo)
) ENGINE=InnoDB COMMENT='User preferences for web and mobile apps';

-- Table for PWA installation tracking
CREATE TABLE IF NOT EXISTS pwa_installations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utente_id INT NOT NULL,
    platform VARCHAR(50) NOT NULL COMMENT 'iOS, Android, Desktop, etc.',
    user_agent TEXT,
    installed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_launch TIMESTAMP NULL,
    launch_count INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    INDEX idx_platform (platform),
    INDEX idx_active (is_active)
) ENGINE=InnoDB COMMENT='Track PWA installations and usage';

-- Table for offline sync queue
CREATE TABLE IF NOT EXISTS offline_sync_queue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utente_id INT NOT NULL,
    action_type ENUM('create', 'update', 'delete') NOT NULL,
    resource_type ENUM('event', 'participant', 'preference') NOT NULL,
    resource_id INT NULL COMMENT 'ID of resource being synced (null for creates)',
    data JSON NOT NULL COMMENT 'Action data to be synced',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sync_attempts INT DEFAULT 0,
    last_sync_attempt TIMESTAMP NULL,
    is_synced BOOLEAN DEFAULT FALSE,
    error_message TEXT NULL,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    INDEX idx_sync_status (is_synced, sync_attempts),
    INDEX idx_user_pending (utente_id, is_synced),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB COMMENT='Queue for offline actions to be synced';

-- Table for mobile app analytics (optional)
CREATE TABLE IF NOT EXISTS mobile_analytics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    utente_id INT NULL,
    session_id VARCHAR(128) NOT NULL,
    event_type ENUM('app_launch', 'view_change', 'event_create', 'event_view', 'sync', 'offline_usage') NOT NULL,
    event_data JSON NULL COMMENT 'Additional event data',
    platform VARCHAR(50),
    app_version VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE SET NULL,
    INDEX idx_event_type (event_type),
    INDEX idx_created_at (created_at),
    INDEX idx_user_events (utente_id, event_type)
) ENGINE=InnoDB COMMENT='Mobile app usage analytics';

-- Add indexes to existing eventi table for mobile performance
ALTER TABLE eventi 
ADD INDEX IF NOT EXISTS idx_mobile_events (azienda_id, data_inizio),
ADD INDEX IF NOT EXISTS idx_user_events (creata_da, data_inizio),
ADD INDEX IF NOT EXISTS idx_date_range (data_inizio, data_fine);

-- Add indexes to evento_partecipanti for better mobile performance
ALTER TABLE evento_partecipanti
ADD INDEX IF NOT EXISTS idx_user_events (utente_id, evento_id),
ADD INDEX IF NOT EXISTS idx_event_participants (evento_id, utente_id);

-- Add mobile-specific columns to eventi table (optional)
ALTER TABLE eventi 
ADD COLUMN IF NOT EXISTS is_mobile_created BOOLEAN DEFAULT FALSE COMMENT 'Whether event was created via mobile app',
ADD COLUMN IF NOT EXISTS sync_version INT DEFAULT 1 COMMENT 'Version for conflict resolution',
ADD COLUMN IF NOT EXISTS last_sync TIMESTAMP NULL COMMENT 'Last sync timestamp for mobile clients';

-- Create view for mobile events with participant count
CREATE OR REPLACE VIEW mobile_events AS
SELECT 
    e.*,
    u.nome as creatore_nome, 
    u.cognome as creatore_cognome,
    a.nome as azienda_nome,
    COUNT(ep.id) as num_partecipanti,
    GROUP_CONCAT(CONCAT(up.nome, ' ', up.cognome) SEPARATOR ', ') as partecipanti_nomi
FROM eventi e 
LEFT JOIN utenti u ON e.creata_da = u.id 
LEFT JOIN aziende a ON e.azienda_id = a.id
LEFT JOIN evento_partecipanti ep ON e.id = ep.evento_id
LEFT JOIN utenti up ON ep.utente_id = up.id
GROUP BY e.id;

-- Create stored procedure for efficient mobile sync
DELIMITER //

CREATE PROCEDURE GetMobileEventsSync(
    IN user_id INT,
    IN azienda_id INT,
    IN last_sync TIMESTAMP,
    IN is_super_admin BOOLEAN,
    IN start_date DATE,
    IN end_date DATE
)
BEGIN
    DECLARE WHERE_CLAUSE TEXT DEFAULT 'WHERE 1=1';
    
    -- Build WHERE clause based on permissions
    IF NOT is_super_admin THEN
        SET WHERE_CLAUSE = CONCAT(WHERE_CLAUSE, ' AND (e.azienda_id = ', azienda_id, ' OR e.creata_da = ', user_id, ')');
    END IF;
    
    -- Add date filters
    IF start_date IS NOT NULL AND end_date IS NOT NULL THEN
        SET WHERE_CLAUSE = CONCAT(WHERE_CLAUSE, ' AND DATE(e.data_inizio) BETWEEN "', start_date, '" AND "', end_date, '"');
    END IF;
    
    -- Add sync filter
    IF last_sync IS NOT NULL THEN
        SET WHERE_CLAUSE = CONCAT(WHERE_CLAUSE, ' AND (e.creato_il > "', last_sync, '" OR e.aggiornato_il > "', last_sync, '")');
    END IF;
    
    SET @sql = CONCAT('
        SELECT e.*, 
               u.nome as creatore_nome, u.cognome as creatore_cognome,
               a.nome as azienda_nome,
               COUNT(ep.id) as num_partecipanti
        FROM eventi e 
        LEFT JOIN utenti u ON e.creata_da = u.id 
        LEFT JOIN aziende a ON e.azienda_id = a.id
        LEFT JOIN evento_partecipanti ep ON e.id = ep.evento_id
        ', WHERE_CLAUSE, '
        GROUP BY e.id
        ORDER BY e.data_inizio ASC
        LIMIT 500'
    );
    
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
END//

DELIMITER ;

-- Create function to check mobile permissions
DELIMITER //

CREATE FUNCTION CanUserAccessMobileEvent(
    user_id INT,
    user_azienda_id INT,
    is_super_admin BOOLEAN,
    event_azienda_id INT,
    event_creator_id INT
) RETURNS BOOLEAN
READS SQL DATA
DETERMINISTIC
BEGIN
    -- Super admin can access all events
    IF is_super_admin THEN
        RETURN TRUE;
    END IF;
    
    -- User can access events they created
    IF event_creator_id = user_id THEN
        RETURN TRUE;
    END IF;
    
    -- User can access events from their company
    IF user_azienda_id IS NOT NULL AND event_azienda_id = user_azienda_id THEN
        RETURN TRUE;
    END IF;
    
    RETURN FALSE;
END//

DELIMITER ;

-- Insert sample data for PWA features (optional)
INSERT IGNORE INTO user_preferences (utente_id, tipo, valore) 
SELECT 
    id as utente_id,
    'mobile_calendar' as tipo,
    JSON_OBJECT(
        'defaultView', 'month',
        'startWeek', 'monday',
        'timeFormat', '24h',
        'notifications', true,
        'theme', 'auto',
        'syncInterval', 300,
        'offlineMode', true,
        'touchGestures', true
    ) as valore
FROM utenti 
WHERE attivo = 1 
AND NOT EXISTS (
    SELECT 1 FROM user_preferences up 
    WHERE up.utente_id = utenti.id AND up.tipo = 'mobile_calendar'
);

-- Create trigger to clean old analytics data
DELIMITER //

CREATE TRIGGER clean_old_analytics
    BEFORE INSERT ON mobile_analytics
    FOR EACH ROW
BEGIN
    -- Delete analytics older than 90 days
    DELETE FROM mobile_analytics 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
END//

DELIMITER ;

-- Create trigger to update event sync version
DELIMITER //

CREATE TRIGGER update_event_sync_version
    BEFORE UPDATE ON eventi
    FOR EACH ROW
BEGIN
    -- Increment sync version on any update
    SET NEW.sync_version = OLD.sync_version + 1;
    SET NEW.last_sync = NOW();
END//

DELIMITER ;

-- Grant permissions (adjust as needed for your user)
-- GRANT SELECT, INSERT, UPDATE, DELETE ON nexio.push_subscriptions TO 'nexio_user'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON nexio.user_preferences TO 'nexio_user'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON nexio.pwa_installations TO 'nexio_user'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON nexio.offline_sync_queue TO 'nexio_user'@'localhost';
-- GRANT SELECT, INSERT ON nexio.mobile_analytics TO 'nexio_user'@'localhost';
-- GRANT EXECUTE ON PROCEDURE nexio.GetMobileEventsSync TO 'nexio_user'@'localhost';
-- GRANT EXECUTE ON FUNCTION nexio.CanUserAccessMobileEvent TO 'nexio_user'@'localhost';

-- Verification queries
SELECT 'PWA Tables Created Successfully' as status;
SELECT COUNT(*) as push_subscriptions_ready FROM information_schema.tables WHERE table_name = 'push_subscriptions';
SELECT COUNT(*) as user_preferences_ready FROM information_schema.tables WHERE table_name = 'user_preferences';
SELECT COUNT(*) as pwa_installations_ready FROM information_schema.tables WHERE table_name = 'pwa_installations';
SELECT COUNT(*) as offline_sync_queue_ready FROM information_schema.tables WHERE table_name = 'offline_sync_queue';
SELECT COUNT(*) as mobile_analytics_ready FROM information_schema.tables WHERE table_name = 'mobile_analytics';