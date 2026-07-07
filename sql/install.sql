-- ksf_FA_QuickBudget install.sql
-- Creates tables for inflation factors and budget scenarios

-- Inflation factors table for FR-01 through FR-04 (global, group, category, gl)
CREATE TABLE IF NOT EXISTS `0_ksf_quickbudget_factors` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `factor_type` enum('global','group','category','gl') NOT NULL DEFAULT 'global',
    `reference_id` varchar(64) NOT NULL DEFAULT '',
    `rate` decimal(10,4) NOT NULL DEFAULT '1.0000',
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_factor` (`factor_type`,`reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Budget scenarios for FR-13
CREATE TABLE IF NOT EXISTS `0_ksf_quickbudget_scenarios` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(64) NOT NULL,
    `multiplier` decimal(10,4) NOT NULL DEFAULT '1.0000',
    `description` text,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Seed default scenarios for FR-13
INSERT IGNORE INTO `0_ksf_quickbudget_scenarios` (`name`, `multiplier`, `description`)
VALUES 
    ('Baseline', 1.0000, 'Standard budget projection'),
    ('Optimistic', 0.9000, '10% below calculated amounts'),
    ('Pessimistic', 1.1000, '10% above calculated amounts');

-- Budget entries for FR-14 (kept for scenarios and approval tracking)
CREATE TABLE IF NOT EXISTS `0_ksf_quickbudget_budget` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `gl_account` varchar(15) NOT NULL,
    `year` int(11) NOT NULL,
    `month` int(11) NOT NULL,
    `amount` decimal(16,2) NOT NULL DEFAULT '0.00',
    `scenario` varchar(32) NOT NULL DEFAULT 'baseline',
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_budget` (`gl_account`,`year`,`month`,`scenario`),
    KEY `idx_account_year` (`gl_account`,`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Budget approvals for FR-20-24 (references FA budget_trans dates)
CREATE TABLE IF NOT EXISTS `0_ksf_quickbudget_approvals` (
    `tran_date` date NOT NULL,
    `gl_account` varchar(15) NOT NULL,
    `dimension_id` int(11) DEFAULT '0',
    `dimension2_id` int(11) DEFAULT '0',
    `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `approved_by` int(11) DEFAULT NULL,
    `approved_at` datetime DEFAULT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`tran_date`,`gl_account`,`dimension_id`,`dimension2_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
