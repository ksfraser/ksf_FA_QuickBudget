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

// Mock global $db object - mock that passes DB check
$db = new stdClass();
$GLOBALS['db'] = $db;

// Mock mysqli_real_escape_string for tests (accepts any object as first param)
if (!function_exists('mysqli_real_escape_string')) {
    function mysqli_real_escape_string($link, $escapestr) {
        return addslashes($escapestr);
    }
}

// Mock FA database functions for unit tests
if (!function_exists('db_query')) {
    function db_query($sql, $display_error = null) {
        // Return true for INSERT/UPDATE/DELETE to simulate success
        if (stripos($sql, 'INSERT') !== false || stripos($sql, 'UPDATE') !== false || stripos($sql, 'DELETE') !== false) {
            return true;
        }
        return new MockDbResult($sql);
    }
}

if (!function_exists('db_fetch_assoc')) {
    function db_fetch_assoc($result) {
        return $result->fetch();
    }
}

// Mock DB result class that returns appropriate mock data based on SQL
class MockDbResult {
    private $sql;
    private $fetchCount = 0;

    public function __construct($sql = '') {
        $this->sql = $sql ?? '';
    }

    public function fetch() {
        $this->fetchCount++;
        // Handle gl_trans queries for account list (getGLAccountsWithActuals)
        if (stripos($this->sql, 'gl_trans') !== false && stripos($this->sql, 'account') !== false && stripos($this->sql, 'YEAR') !== false && stripos($this->sql, 'MONTH') === false) {
            if ($this->fetchCount === 1) {
                return ['account' => '6000'];
            }
            return false;
        }
        
        // Handle gl_trans queries for actuals (MONTH/tran_date)
        if (stripos($this->sql, 'gl_trans') !== false && stripos($this->sql, 'MONTH') !== false) {
            if ($this->fetchCount === 1) {
                return ['month' => 1, 'total' => 1000.00];
            }
            return false;
        }

        // Handle account details query (chart_master join)
        if (stripos($this->sql, 'chart_master') !== false) {
            return ['type_id' => '1', 'type_name' => 'Utilities', 'class_name' => 'expenses'];
        }

        // Handle chart_types queries for type name/parent/class
        if (stripos($this->sql, 'chart_types') !== false) {
            return ['id' => '1', 'name' => 'Utilities', 'class_id' => '2', 'ctype' => '1'];
        }

        // Handle chart_class queries
        if (stripos($this->sql, 'chart_class') !== false) {
            return ['cid' => '1', 'class_name' => 'expenses'];
        }

        return false;
    }
}

// Load required include files
require_once __DIR__ . '/../../includes/BudgetEntryDTO.php';
require_once __DIR__ . '/../../includes/InflationFactorDTO.php';
require_once __DIR__ . '/../../includes/CategoryDAO.php';
require_once __DIR__ . '/../../includes/InflationFactorManager.php';
require_once __DIR__ . '/../../src/Service/BudgetGeneratorService.php';
