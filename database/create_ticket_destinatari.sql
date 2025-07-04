-- Tabella per destinatari multipli dei ticket
CREATE TABLE IF NOT EXISTS ticket_destinatari (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    utente_id INT NOT NULL,
    tipo ENUM('principale', 'cc') DEFAULT 'cc',
    letto BOOLEAN DEFAULT FALSE,
    data_lettura TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    UNIQUE KEY uk_ticket_utente (ticket_id, utente_id),
    INDEX idx_ticket (ticket_id),
    INDEX idx_utente (utente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migrazione dei ticket esistenti con assegnato_a
INSERT INTO ticket_destinatari (ticket_id, utente_id, tipo)
SELECT id, assegnato_a, 'principale'
FROM tickets
WHERE assegnato_a IS NOT NULL
ON DUPLICATE KEY UPDATE tipo = tipo; 