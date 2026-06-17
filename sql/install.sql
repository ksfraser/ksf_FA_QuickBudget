-- ksf_FA_QuickBudget install.sql
-- Creates tables for inflation factors and budget scenarios

-- Inflation factors table for FR-01 through FR-06
CREATE TABLE IF NOT EXISTS `0_ksf_quickbudget_factors` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `factor_type` enum('global','category','gl') NOT NULL DEFAULT 'global',
    `reference_id` varchar(64) NOT NULL DEFAULT '',
    `rate` decimal(10,4) NOT NULL DEFAULT '1.0000',
    `company` int(11) NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_factor` (`factor_type`,`reference_id`,`company`),
    KEY `idx_company_type` (`company`,`factor_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Budget scenarios for FR-13
CREATE TABLE IF NOT EXISTS `0_ksf_quickbudget_scenarios` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(64) NOT NULL,
    `multiplier` decimal(10,4) NOT NULL DEFAULT '1.0000',
    `description` text,
    `company` int(11) NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    KEY `idx_company` (`company`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Seed default scenarios for FR-13
INSERT IGNORE INTO `0_ksf_quickbudget_scenarios` (`name`, `multiplier`, `description`, `company`)
VALUES 
    ('Baseline', 1.0000, 'Standard budget projection', 0),
    ('Optimistic', 0.9000, '10% below calculated amounts', 0),
    ('Pessimistic', 1.1000, '10% above calculated amounts', 0);