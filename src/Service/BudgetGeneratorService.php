<?php
/**
 * BudgetGeneratorService
 *
 * Core service for generating budgets from actuals with inflation.
 * Supports FR-07 through FR-14.
 */
declare(strict_types=1);

final class BudgetGeneratorService
{
    /** @var InflationFactorManager */
    private $factorManager;

    /**
     * @param InflationFactorManager $factorManager
     */
    public function __construct(InflationFactorManager $factorManager)
    {
        $this->factorManager = $factorManager;
    }

    /**
     * Generate budget entries for a time period.
     *
     * @param int $targetYear Year to generate budget for
     * @param int $startMonth Starting month (1-12)
     * @param int $scenarioId Scenario to use (default: 0 = baseline)
     * @return array<BudgetEntryDTO>
     * @see FR-09, FR-13
     */
    public function generate(int $targetYear, int $startMonth = 1, int $scenarioId = 0): array
    {
        global $db;

        $sourceYear = $targetYear - 1;

        // FR-13: Get scenario multiplier (1.0 = baseline)
        $scenarioMultiplier = $this->getScenarioMultiplier($scenarioId);

        $entries = [];

        // Get all GL accounts with actuals in source year
        $glAccounts = $this->getGLAccountsWithActuals($sourceYear);

        foreach ($glAccounts as $glAccount) {
            $actuals = $this->getActualsByGL($glAccount, $sourceYear);
            $inflationRate = $this->factorManager->getRateForAccount($glAccount);
            [$isIncome, $isBalanceSheet] = $this->getAccountType($glAccount);

            $budgetAmounts = [];
            for ($month = $startMonth; $month <= 12; $month++) {
                $actualAmount = $actuals[$month] ?? 0.0;
                // Balance sheet accounts: scenario has no effect (1.0)
                if ($isBalanceSheet) {
                    $effectiveMultiplier = 1.0;
                } elseif ($isIncome) {
                    // Income: inverse multiplier (pessimistic = lower income)
                    $effectiveMultiplier = 1.0 / max($scenarioMultiplier, 0.01);
                } else {
                    // Expenses: direct multiplier (pessimistic = higher expenses)
                    $effectiveMultiplier = $scenarioMultiplier;
                }
                $budgetAmounts[$month] = $actualAmount * $inflationRate * $effectiveMultiplier;
            }

            $entries[] = new BudgetEntryDTO(
                $glAccount,
                $targetYear,
                $budgetAmounts
            );
        }

        return $entries;
    }

    /**
     * Check if GL account is an income or balance sheet account.
     *
     * FA account_type: 0=Bank, 1=Cash, 2=Receivables, 3=Payables, 4=Sales, 5=Purchases,
     *                  6=Inventory, 7=COGS, 8=Expense, 9=Other Income, 10=Other Expense
     *
     * @param string $glAccount GL account code
     * @return array{0: bool, 1: bool} [isIncome, isBalanceSheet]
     * @see FR-13
     */
    private function getAccountType(string $glAccount): array
    {
        global $db;

        // Defend against null/missing $db
        if (!isset($db) || !is_resource($db) && !($db instanceof mysqli)) {
            return [false, false];
        }

        $sql = "SELECT account_type FROM " . TB_PREF . "chart_master
            WHERE account_code = '" . mysqli_real_escape_string($db, $glAccount) . "'";
        $result = db_query($sql, null);
        $row = db_fetch_assoc($result);

        if (!$row) {
            return [false, false];
        }

        $type = (int)$row['account_type'];
        // Income types: 4=Sales, 9=Other Income
        // Balance sheet (no scenario): 0=Bank, 1=Cash, 2=Receivables, 3=Payables, 6=Inventory
        return [$type === 4 || $type === 9, $type <= 6];
    }

    /**
     * Get scenario multiplier from database.
     *
     * @param int $scenarioId
     * @return float Multiplier (default 1.0 for baseline)
     * @see FR-13
     */
    private function getScenarioMultiplier(int $scenarioId): float
    {
        global $db;

        if ($scenarioId <= 0) {
            return 1.0;
        }

        $sql = "SELECT multiplier FROM " . TB_PREF . "ksf_quickbudget_scenarios
            WHERE id = " . (int)$scenarioId;
        $result = db_query($sql, null);
        $row = db_fetch_assoc($result);

        return $row ? (float)$row['multiplier'] : 1.0;
    }

    /**
     * Save budget entries to FA native budget_trans table.
     *
     * @param array<BudgetEntryDTO> $entries
     * @param int $company Company ID
     * @param string $pathToRoot Path to FA root for including functions
     * @param bool $submitForApproval Whether to create approval records
     * @return int Number of entries saved
     * @see FR-14, FR-21
     */
    public function saveToFABudget(array $entries, int $company = 0, string $pathToRoot = '', bool $submitForApproval = false): int
    {
        global $db;

        // Include FA's GL budget functions if path provided
        if ($pathToRoot && file_exists($pathToRoot . "/gl/includes/db/gl_db_trans.inc")) {
            include_once($pathToRoot . "/gl/includes/db/gl_db_trans.inc");
        }

        $count = 0;
        foreach ($entries as $entry) {
            $monthlyAmounts = $entry->getMonthlyAmounts();
            foreach ($monthlyAmounts as $month => $amount) {
                if ($amount != 0.0 && function_exists('add_update_gl_budget_trans')) {
                    $date = sprintf('%04d-%02d-01', $entry->getYear(), $month);
                    add_update_gl_budget_trans(
                        $date,
                        $entry->getGLAccount(),
                        0,
                        0,
                        $amount
                    );

                    // FR-21: Submit for approval if requested
                    if ($submitForApproval) {
                        $this->submitForApproval($date, $entry->getGLAccount());
                    }

                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Create approval record for a budget entry.
     *
     * @param string $tranDate Date in Y-m-d format
     * @param string $glAccount GL account code
     * @return void
     * @see FR-21
     */
    private function submitForApproval(string $tranDate, string $glAccount): void
    {
        global $db;

        $sql = "INSERT IGNORE INTO " . TB_PREF . "ksf_quickbudget_approvals
            (tran_date, gl_account, status)
            VALUES ('" . mysqli_real_escape_string($db, $tranDate) . "',
                '" . mysqli_real_escape_string($db, $glAccount) . "',
                'pending')";
        db_query($sql, null);
    }

    /**
     * Get GL accounts that have actuals in the specified year.
     *
     * @param int $year
     * @return array<string>
     */
    private function getGLAccountsWithActuals(int $year): array
    {
        global $db;

        $sql = "SELECT DISTINCT account FROM " . TB_PREF . "gl_trans
            WHERE YEAR(tran_date) = " . (int)$year;
        $result = db_query($sql, null);

        $accounts = [];
        while ($row = db_fetch_assoc($result)) {
            $accounts[] = $row['account'];
        }

        return $accounts;
    }

    /**
     * Get monthly actuals for a GL account.
     *
     * @param string $glAccount GL account code
     * @param int $year
     * @return array<int, float> Monthly amounts indexed 1-12
     */
    private function getActualsByGL(string $glAccount, int $year): array
    {
        global $db;

        $sql = "SELECT MONTH(tran_date) as month, SUM(amount) as total
            FROM " . TB_PREF . "gl_trans
            WHERE account = '" . mysqli_real_escape_string($db, $glAccount) . "'
            AND YEAR(tran_date) = " . (int)$year . "
            GROUP BY MONTH(tran_date)";
        $result = db_query($sql, null);

        $monthly = [];
        while ($row = db_fetch_assoc($result)) {
            $monthly[(int)$row['month']] = (float)$row['total'];
        }

        return $monthly;
    }
}