<?php
/**
 * InflationFactorManager
 *
 * Manages inflation factor configuration and resolution.
 * Supports FR-01 through FR-04.
 *
 * @see RTM.md - FR-01 to FR-04
 */
declare(strict_types=1);

class InflationFactorManager
{
    /** @var float Default rate (1.0 = no inflation) */
    private $globalRate = 1.0;

    /** @var array<string, float> Category rates indexed by category name */
    private $categoryRates = [];

    /** @var array<string, float> GL-specific rates indexed by account code */
    private $glRates = [];

    /** @var array<string, float> Group rates indexed by group ID */
    private $groupRates = [];

    /**
     * Load rates from database.
     *
     * @return void
     */
    public function loadFromDB(): void
    {
        global $db;

        $sql = "SELECT factor_type, reference_id, rate FROM " . TB_PREF . "ksf_quickbudget_factors
            WHERE factor_type IS NOT NULL";
        
        $logFile = dirname(__DIR__) . '/logs/debug.log';
        file_put_contents($logFile, date('Y-m-d H:i:s') . " loadFromDB: sql={$sql}\n", FILE_APPEND);
        
        $result = db_query($sql);
        
        if (!$result) {
            error_log("loadFromDB: query failed, result=false");
            return;
        }
        
        $rows = db_num_rows($result);
        error_log("loadFromDB: found {$rows} rows");

        while ($row = db_fetch_assoc($result)) {
            $rate = (float)$row['rate'];
            $type = $row['factor_type'];
            $ref = (string)$row['reference_id'];
            if (empty($type)) {
                continue;
            }
            error_log("loadFromDB: type={$type}, ref={$ref}, rate={$rate}");
            switch ($type) {
                case 'global':
                    $this->globalRate = $rate;
                    break;
                case 'group':
                    $this->groupRates[$ref] = $rate;
                    break;
                case 'category':
                    $this->categoryRates[$ref] = $rate;
                    break;
                case 'gl':
                    $this->glRates[$ref] = $rate;
                    break;
            }
        }
    }

    /**
     * Set the global default inflation rate.
     *
     * @param float $rate Rate multiplier (e.g., 1.0350)
     * @return void
     */
    public function setGlobalRate(float $rate): void
    {
        $this->globalRate = $rate;
    }

    /**
     * Set a category-level inflation rate.
     *
     * @param string $category Category name (e.g., 'Expenses')
     * @param float $rate Rate multiplier
     * @return void
     */
    public function setCategoryRate(string $category, float $rate): void
    {
        $this->categoryRates[$category] = $rate;
    }

    /**
     * Set a GL-account specific inflation rate.
     *
     * @param string $glAccount GL account code
     * @param float $rate Rate multiplier
     * @return void
     */
    public function setGLRate(string $glAccount, float $rate): void
    {
        $this->glRates[$glAccount] = $rate;
    }

    /**
     * Set a group-level inflation rate.
     *
     * @param string $group Group ID (chart_types.class_id)
     * @param float $rate Rate multiplier
     * @return void
     */
    public function setGroupRate(string $group, float $rate): void
    {
        $this->groupRates[$group] = $rate;
    }

    /**
     * Get all rates as array for session storage.
     *
     * @return array All rates indexed by type and reference
     */
    public function getAllRates(): array
    {
        return [
            'global' => $this->globalRate,
            'group' => $this->groupRates,
            'category' => $this->categoryRates,
            'gl' => $this->glRates,
        ];
    }

    /**
     * Get the global default rate.
     * FR-01 support.
     *
     * @return float Default rate
     */
    public function getDefaultRate(): float
    {
        return $this->globalRate;
    }

    /**
     * Get category rate.
     *
     * @param string $category Category name
     * @return float|null Rate or null if not set
     */
    public function getCategoryRate(string $category): ?float
    {
        return $this->categoryRates[$category] ?? null;
    }

    /**
     * Get effective rate for a GL account.
     * Resolves hierarchy: GL → Group → Category → Global.
     *
     * @param string $glAccount GL account code
     * @return float Effective inflation rate
     */
    public function getRateForAccount(string $glAccount): float
    {
        // Hierarchy: GL → Group → Category → Global
        
        // GL-specific takes highest precedence
        if (isset($this->glRates[$glAccount])) {
            return $this->glRates[$glAccount];
        }

        // Get account's group and category
        $accountGroup = $this->getAccountGroup($glAccount);
        
        // Group-level override (from chart_types.class_id)
        if ($accountGroup && isset($this->groupRates[$accountGroup])) {
            return $this->groupRates[$accountGroup];
        }

        // Category-level override based on account type
        $accountCategory = $this->getAccountCategory($glAccount);
        if ($accountCategory && isset($this->categoryRates[$accountCategory])) {
            return $this->categoryRates[$accountCategory];
        }

        // Fall back to checking generic 'Expenses' category for backward compatibility
        if (isset($this->categoryRates['Expenses'])) {
            return $this->categoryRates['Expenses'];
        }

        // Fall back to global default
        return $this->globalRate;
    }

    /**
     * Get account category name based on FA chart_master.account_type.
     * Account types: 0=Bank, 1=Cash, 2=Receivables, 3=Payables, 4=Sales, 5=Purchases,
     *                6=Inventory, 7=COGS, 8=Expense, 9=Other Income, 10=Other Expense
     *
     * @param string $glAccount GL account code
     * @return string|null Category name or null for balance sheet
     */
    private function getAccountCategory(string $glAccount): ?string
    {
        // Check if we're in FA context (real $db connection)
        global $db;
        if (!is_resource($db) && !($db instanceof mysqli)) {
            return null; // Not in FA context, cannot determine category
        }

        $sql = "SELECT account_type FROM " . TB_PREF . "chart_master
            WHERE account_code = '" . mysqli_real_escape_string($db, $glAccount) . "'";
        $result = db_query($sql);
        if (!$result) {
            return null;
        }
        $row = db_fetch_assoc($result);

        if (!$row) {
            return null;
        }

        static $typeMap = [
            4 => 'Income',      // Sales
            9 => 'Income',        // Other Income
            5 => 'COGS',        // Purchases (costs of goods)
            7 => 'COGS',        // COGS
            8 => 'Expenses',    // Expense
            10 => 'Expenses',    // Other Expense
            0 => 'Assets',      // Bank
            1 => 'Assets',      // Cash
            2 => 'Assets',      // Receivables
            3 => 'Assets',      // Payables
            6 => 'Assets',      // Inventory
        ];

        return $typeMap[(int)$row['account_type']] ?? null;
    }

    /**
     * Get account group from chart_types table.
     * Returns the class_id for the GL account (account_type maps to chart_types.id).
     *
     * @param string $glAccount GL account code
     * @return string|null Group ID or null
     */
    private function getAccountGroup(string $glAccount): ?string
    {
        global $db;
        if (!is_resource($db) && !($db instanceof mysqli)) {
            return null;
        }

        // Get chart_types.class_id for the account's account_type
        $sql = "SELECT ct.class_id FROM " . TB_PREF . "chart_master cm
            LEFT JOIN " . TB_PREF . "chart_types ct ON cm.account_type = ct.id
            WHERE cm.account_code = '" . mysqli_real_escape_string($db, $glAccount) . "'";
        $result = db_query($sql);
        if (!$result) {
            return null;
        }
        $row = db_fetch_assoc($result);

        return $row && $row['class_id'] ? (string)$row['class_id'] : null;
    }
}