-- ksf_FA_QuickBudget update.sql
-- Migration: enum to varchar, drop company column

-- Convert ENUM to VARCHAR for flexibility  
ALTER TABLE `0_ksf_quickbudget_factors` 
    MODIFY COLUMN `factor_type` VARCHAR(32) NOT NULL DEFAULT 'global';

-- Drop company column (FA uses TB_PREF for multi-company isolation)
ALTER TABLE `0_ksf_quickbudget_factors` DROP COLUMN IF EXISTS `company`;

-- Recreate unique key without company
ALTER TABLE `0_ksf_quickbudget_factors` DROP INDEX IF EXISTS `unique_factor`;
ALTER TABLE `0_ksf_quickbudget_factors` ADD UNIQUE KEY `unique_factor` (`factor_type`,`reference_id`);
