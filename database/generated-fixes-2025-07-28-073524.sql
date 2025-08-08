-- Generated Database Fixes for Nexio Platform
-- Generated: 2025-07-28 07:35:24

-- Add index for eventi.data_inizio
ALTER TABLE `eventi` ADD INDEX `idx_data_inizio` (`data_inizio`);

