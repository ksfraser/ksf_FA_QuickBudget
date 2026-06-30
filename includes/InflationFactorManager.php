<?php
/**
 * InflationFactorManager
 *
 * Manages inflation factor configuration and resolution.
 * Supports FR-01 through FR-06.
 *
 * @see RTM.md - FR-01 to FR-06
 */
declare(strict_types=1);

final class InflationFactorManager
{
    /** @var float Default rate (1.0 = no inflation) */
    private $globalRate = 1.0;

    /** @var array<string, float> Category rates indexed by category name */
    private $categoryRates = [];

    /** @var array<string, float> GL-specific rates indexed by account code */
    private $glRates = [];

    /**
     * Load rates from database for a company.
     *
     * @param int $company Company ID
     * @return void
     */
    public function loadFromDB(int $company = 0): void
    {
        global $db;

        $sql = "SELECT factor_type, reference_id, rate FROM " . TB_PREF . "ksf_quickbudget_factors
            WHERE company = " . (int)$company;
        $result = db_query($sql, null);

        while ($row = db_fetch_assoc($result)) {
            $rate = (float)$row['rate'];
            switch ($row['factor_type']) {
                case 'global':
                    $this->globalRate = $rate;
                    break;
                case 'category':
                    $this->categoryRates[$row['reference_id']] = $rate;
                    break;
                case 'gl':
                    $this->glRates[$row['reference_id']] = $rate;
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
     * Resolves hierarchy: GL → Category → Global.
     *
     * @param string $glAccount GL account code
     * @return float Effective inflation rate
     */
    public function getRateForAccount(string $glAccount): float
    {
        // FR-03: GL-specific takes highest precedence
        if (isset($this->glRates[$glAccount])) {
            return $this->glRates[$glAccount];
        }

        // FR-02: Category-level override based on account type
        $category = $this->getAccountCategory($glAccount);
        if ($category && isset($this->categoryRates[$category])) {
            return $this->categoryRates[$category];
        }

        // Fall back to checking generic 'Expenses' category for backward compatibility
        if (isset($this->categoryRates['Expenses'])) {
            return $this->categoryRates['Expenses'];
        }

        // FR-01: Fall back to global default
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
        $result = db_query($sql, null);
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
        ];

        return $typeMap[(int)$row['account_type']] ?? null;
    }
}
