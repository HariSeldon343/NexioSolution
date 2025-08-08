-- Creazione sistema di gestione moduli per le aziende

-- Tabella per il mapping azienda-moduli (mancante)
CREATE TABLE IF NOT EXISTS `aziende_moduli` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `azienda_id` int(11) NOT NULL,
    `modulo_id` int(11) NOT NULL,
    `attivo` tinyint(1) NOT NULL DEFAULT 1,
    `configurazione` longtext,
    `data_attivazione` timestamp NOT NULL DEFAULT current_timestamp(),
    `data_disattivazione` timestamp NULL DEFAULT NULL,
    `creato_il` timestamp NOT NULL DEFAULT current_timestamp(),
    `aggiornato_il` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_azienda_modulo` (`azienda_id`, `modulo_id`),
    KEY `idx_azienda` (`azienda_id`),
    KEY `idx_modulo` (`modulo_id`),
    KEY `idx_attivo` (`attivo`),
    FOREIGN KEY (`azienda_id`) REFERENCES `aziende` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`modulo_id`) REFERENCES `moduli_sistema` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Popolamento della tabella moduli_sistema con i moduli della piattaforma
INSERT IGNORE INTO `moduli_sistema` (`codice`, `nome`, `descrizione`, `icona`, `colore`, `url_base`, `ordine`, `attivo`, `richiede_licenza`) VALUES
('dashboard', 'Dashboard', 'Pannello di controllo principale con statistiche e quick actions', 'fas fa-tachometer-alt', '#3b82f6', 'dashboard.php', 1, 1, 0),
('documenti', 'Gestione Documenti', 'Sistema completo di gestione documenti con versioning e workflow', 'fas fa-file-alt', '#10b981', 'documenti.php', 2, 1, 1),
('template', 'Template Documenti', 'Editor template drag-and-drop per creazione documenti dinamici', 'fas fa-file-code', '#8b5cf6', 'template.php', 3, 1, 1),
('archivio', 'Archivio Documenti', 'Archivio documenti con sistema di classificazione avanzato', 'fas fa-archive', '#f59e0b', 'archivio-documenti.php', 4, 1, 1),
('calendario', 'Calendario Eventi', 'Sistema calendario con inviti e notifiche email integrate', 'fas fa-calendar-alt', '#ef4444', 'calendario-eventi.php', 5, 1, 1),
('tickets', 'Sistema Tickets', 'Help desk e sistema di supporto interno con workflow', 'fas fa-ticket-alt', '#06b6d4', 'tickets.php', 6, 1, 1),
('utenti', 'Gestione Utenti', 'Amministrazione utenti, ruoli e permessi multi-tenant', 'fas fa-users', '#6366f1', 'gestione-utenti.php', 7, 1, 0),
('aziende', 'Gestione Aziende', 'Amministrazione aziende e configurazione moduli (solo super admin)', 'fas fa-building', '#84cc16', 'aziende.php', 8, 1, 0),
('referenti', 'Gestione Referenti', 'Database contatti e referenti aziendali', 'fas fa-address-book', '#f97316', 'referenti.php', 9, 1, 1),
('newsletter', 'Newsletter', 'Sistema invio newsletter e gestione campagne email', 'fas fa-envelope', '#ec4899', 'newsletter.php', 10, 1, 1),
('filesystem', 'File System', 'Gestione file system e upload documenti', 'fas fa-folder-open', '#64748b', 'filesystem.php', 11, 1, 1),
('configurazioni', 'Configurazioni', 'Impostazioni sistema e configurazioni avanzate', 'fas fa-cog', '#71717a', 'configurazioni.php', 12, 1, 0),
('onlyoffice', 'OnlyOffice Editor', 'Editor documenti collaborativo in tempo reale', 'fas fa-edit', '#059669', 'nuovo-documento-onlyoffice.php', 13, 1, 1);

-- Attiva tutti i moduli per tutte le aziende esistenti (configurazione iniziale)
INSERT IGNORE INTO `aziende_moduli` (`azienda_id`, `modulo_id`, `attivo`)
SELECT a.id, m.id, 1
FROM `aziende` a
CROSS JOIN `moduli_sistema` m
WHERE a.stato = 'attiva';