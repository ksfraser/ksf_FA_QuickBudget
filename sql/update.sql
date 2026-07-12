-- ksf_FA_QuickBudget update.sql
-- Migration: migrate reference_id from names to IDs for type/category rates

-- Add type_id column to temporarily hold migrated IDs
ALTER TABLE `0_ksf_quickbudget_factors` ADD COLUMN `type_id` INT(11) DEFAULT NULL;

-- Migrate existing type rates: store chart_types.id in type_id
UPDATE `0_ksf_quickbudget_factors` tf
JOIN `0_chart_types` ct ON tf.reference_id = ct.name
SET tf.type_id = ct.id
WHERE tf.factor_type = 'type';

-- Migrate existing category rates: store chart_class.cid in type_id
UPDATE `0_ksf_quickbudget_factors` tf
JOIN `0_chart_class` cc ON tf.reference_id = cc.class_name
SET tf.type_id = cc.cid
WHERE tf.factor_type = 'category';

-- Drop the temporary type_id column (reference_id now holds IDs)
ALTER TABLE `0_ksf_quickbudget_factors` DROP COLUMN `type_id`;
