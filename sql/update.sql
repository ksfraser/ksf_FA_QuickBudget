-- ksf_FA_QuickBudget update.sql
-- Migration: migrate reference_id from names to IDs for type/category rates

-- Migrate existing type rates: update reference_id to chart_types.id
UPDATE `0_ksf_quickbudget_factors` tf
JOIN `0_chart_types` ct ON tf.reference_id = ct.name
SET tf.reference_id = CAST(ct.id AS CHAR)
WHERE tf.factor_type = 'type';

-- Migrate existing category rates: update reference_id to chart_class.cid
UPDATE `0_ksf_quickbudget_factors` tf
JOIN `0_chart_class` cc ON tf.reference_id = cc.class_name
SET tf.reference_id = CAST(cc.cid AS CHAR)
WHERE tf.factor_type = 'category';
