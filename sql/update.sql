-- ksf_FA_QuickBudget update.sql
-- Migration to remove company column (FA uses TB_PREF for multi-company isolation)

-- Add 'group' to factor_type enum if not already present
ALTER TABLE `0_ksf_quickbudget_factors`
    MODIFY COLUMN `factor_type` ENUM('global','group','category','gl') NOT NULL DEFAULT 'global';

-- Drop company column (note: may already be dropped in fresh installs)
-- Uncomment if needed: ALTER TABLE `0_ksf_quickbudget_factors` DROP COLUMN `company`;

-- Drop index on company if exists
ALTER TABLE `0_ksf_quickbudget_factors` DROP INDEX `idx_company_type`;

-- Fix unique key to not include company
ALTER TABLE `0_ksf_quickbudget_factors` DROP INDEX `unique_factor`;
ALTER TABLE `0_ksf_quickbudget_factors` ADD UNIQUE KEY `unique_factor` (`factor_type`,`reference_id`);

-- Drop company index from scenarios (column may already be dropped)
ALTER TABLE `0_ksf_quickbudget_scenarios` DROP INDEX `idx_company`;
