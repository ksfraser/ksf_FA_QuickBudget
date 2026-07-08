-- ksf_FA_QuickBudget update.sql
-- Migration to remove company column (FA uses TB_PREF for multi-company isolation)

-- Add 'group' to factor_type enum if not already present
ALTER TABLE `0_ksf_quickbudget_factors`
    MODIFY COLUMN `factor_type` ENUM('global','group','category','gl') NOT NULL DEFAULT 'global';

-- Drop company column and index (compatible with both old and new schema)
ALTER TABLE `0_ksf_quickbudget_factors` DROP COLUMN `company`, DROP INDEX `idx_company_type`;
ALTER TABLE `0_ksf_quickbudget_factors` DROP INDEX `unique_factor`;
ALTER TABLE `0_ksf_quickbudget_factors` ADD UNIQUE KEY `unique_factor` (`factor_type`,`reference_id`);

ALTER TABLE `0_ksf_quickbudget_scenarios` DROP COLUMN `company`, DROP INDEX `idx_company`;
