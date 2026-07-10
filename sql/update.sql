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

-- Add type_id column for type rates (store chart_types.id instead of name)
ALTER TABLE `0_ksf_quickbudget_factors` ADD COLUMN `type_id` INT(11) DEFAULT NULL AFTER `reference_id`;

-- Migrate existing type rates to use type_id (requires matching chart_types.name)
UPDATE `0_ksf_quickbudget_factors` tf
JOIN `0_chart_types` ct ON tf.reference_id = ct.name
SET tf.type_id = ct.id
WHERE tf.factor_type = 'type';
