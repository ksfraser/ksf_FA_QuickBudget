<?php
/**
 * PHPUnit bootstrap for ksf_FA_QuickBudget
 *
 * Loads required classes for testing without FA dependencies.
 */
declare(strict_types=1);

// Mock TB_PREF constant for tests
if (!defined('TB_PREF')) {
    define('TB_PREF', '');
}

// Mock global $db object
$GLOBALS['db'] = new stdClass();

// Mock FA database functions for unit tests
if (!function_exists('db_query')) {
    function db_query($sql, $display_error = null) {
        return new MockDbResult();
    }
}

if (!function_exists('db_fetch_assoc')) {
    function db_fetch_assoc($result) {
        return false;
    }
}

// Mock DB result class
class MockDbResult {}

// Load required include files
require_once __DIR__ . '/../../includes/BudgetEntryDTO.php';
require_once __DIR__ . '/../../includes/InflationFactorDTO.php';
require_once __DIR__ . '/../../includes/InflationFactorManager.php';
require_once __DIR__ . '/../../src/Service/BudgetGeneratorService.php';