-- ksf_FA_QuickBudget update.sql
-- Adds 'group' factor_type to existing installations

-- Add 'group' to factor_type enum if not already present
ALTER TABLE `0_ksf_quickbudget_factors`
    MODIFY COLUMN `factor_type` ENUM('global','group','category','gl') NOT NULL DEFAULT 'global';

-- Remove company column (each FA company uses separate prefixed tables)
ALTER TABLE `0_ksf_quickbudget_factors` DROP COLUMN `company`;
ALTER TABLE `0_ksf_quickbudget_scenarios` DROP COLUMN `company`;

-- Fix unique key to not include company
ALTER TABLE `0_ksf_quickbudget_factors` DROP INDEX `unique_factor`;
ALTER TABLE `0_ksf_quickbudget_factors` ADD UNIQUE KEY `unique_factor` (`factor_type`,`reference_id`);
