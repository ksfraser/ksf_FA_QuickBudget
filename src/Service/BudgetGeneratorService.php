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

            $budgetAmounts = [];
            for ($month = $startMonth; $month <= 12; $month++) {
                $actualAmount = $actuals[$month] ?? 0.0;
                
                // Convert percentage to multiplier: 3% -> 1.03
                $rateMultiplier = $inflationRate;
                if ($inflationRate > 10 && $inflationRate <= 100) {
                    $rateMultiplier = 1.0 + ($inflationRate / 100.0);
                }
                
                // Apply scenario: if rate is 0 (fixed contract), no scenario adjustment
                $effectiveScenario = $inflationRate == 0 ? 1.0 : $scenarioMultiplier;
                
                $budgetAmounts[$month] = $actualAmount * $rateMultiplier * $effectiveScenario;
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

        $count = 0;
        foreach ($entries as $entry) {
            $monthlyAmounts = $entry->getMonthlyAmounts();
            foreach ($monthlyAmounts as $month => $amount) {
                if ($amount != 0.0) {
                    $sqlDate = sprintf('%04d-%02d-01', $entry->getYear(), $month);

                    // Direct DB insert with Y-m-d format (MySQL native)
                    $sql = "INSERT INTO " . TB_PREF . "budget_trans
                        (tran_date, account, dimension_id, dimension2_id, amount)
                        VALUES ('" . mysqli_real_escape_string($db, $sqlDate) . "',
                            '" . mysqli_real_escape_string($db, $entry->getGLAccount()) . "',
                            0, 0, " . (float)$amount . ")
                        ON DUPLICATE KEY UPDATE amount=VALUES(amount)";
                    $result = db_query($sql);
                    if (!$result) {
                        error_log("QuickBudget ERROR: SQL: $sql");
                    }
                    $count++;
                }
            }
        }

        // FR-21: Submit for approval if requested
        if ($submitForApproval) {
            foreach ($entries as $entry) {
                $monthlyAmounts = $entry->getMonthlyAmounts();
                foreach ($monthlyAmounts as $month => $amount) {
                    if ($amount != 0.0) {
                        $date = sprintf('%04d-%02d-01', $entry->getYear(), $month);
                        $this->submitForApproval($date, $entry->getGLAccount());
                    }
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