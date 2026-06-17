<?php
/**
 * BudgetGeneratorService
 *
 * Core service for generating budgets from actuals with inflation.
 * Supports FR-07 through FR-14.
 */
declare(strict_types=1);

namespace Ksfraser\FA\QuickBudget\Service;

use InflationFactorManager;
use BudgetEntryDTO;

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
     * @param int $scenarioId Scenario to use (default: baseline)
     * @return array<BudgetEntryDTO>
     * @see FR-09
     */
    public function generate(int $targetYear, int $startMonth = 1, int $scenarioId = 0): array
    {
        global $db;

        $sourceYear = $targetYear - 1;
        $entries = [];

        // Get all GL accounts with actuals in source year
        $glAccounts = $this->getGLAccountsWithActuals($sourceYear);

        foreach ($glAccounts as $glAccount) {
            $actuals = $this->getActualsByGL($glAccount, $sourceYear);
            $inflationRate = $this->factorManager->getRateForAccount($glAccount);

            $budgetAmounts = [];
            for ($month = $startMonth; $month <= 12; $month++) {
                $actualAmount = $actuals[$month] ?? 0.0;
                $budgetAmounts[$month] = $actualAmount * $inflationRate;
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
     * Save budget entries to FA budget tables.
     *
     * @param array<BudgetEntryDTO> $entries
     * @param int $company Company ID
     * @return int Number of entries saved
     * @see FR-14
     */
    public function saveToFABudget(array $entries, int $company = 0): int
    {
        global $db;

        $count = 0;
        foreach ($entries as $entry) {
            $monthlyAmounts = $entry->getMonthlyAmounts();
            foreach ($monthlyAmounts as $month => $amount) {
                if ($amount != 0.0) {
                    $sql = "INSERT INTO " . TB_PREF . "ksf_quickbudget_budget
                        (gl_account, year, month, amount, company) VALUES (" .
                        "'" . mysqli_real_escape_string($db, $entry->getGLAccount()) . "', " .
                        (int)$entry->getYear() . ", " .
                        (int)$month . ", " .
                        (float)$amount . ", " .
                        (int)$company . ")";
                    if (db_query($sql, null)) {
                        $count++;
                    }
                }
            }
        }

        return $count;
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

        $sql = "SELECT DISTINCT account_code FROM " . TB_PREF . "gl_trans
            WHERE YEAR(tran_date) = " . (int)$year;
        $result = db_query($sql, null);

        $accounts = [];
        while ($row = db_fetch_assoc($result)) {
            $accounts[] = $row['account_code'];
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
            WHERE account_code = '" . mysqli_real_escape_string($db, $glAccount) . "'
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