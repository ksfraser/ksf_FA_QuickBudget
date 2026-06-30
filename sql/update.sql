-- ksf_FA_QuickBudget update.sql
-- Adds 'group' factor_type to existing installations
-- Run: ALTER TABLE to add group to enum, no data migration needed

-- Add 'group' to factor_type enum if not already present
ALTER TABLE `0_ksf_quickbudget_factors`
    MODIFY COLUMN `factor_type` ENUM('global','group','category','gl') NOT NULL DEFAULT 'global';