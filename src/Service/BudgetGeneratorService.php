<?php
/**
 * BudgetGeneratorService
 *
 * Core service for generating budgets from actuals with inflation.
 * Supports FR-07 through FR-14.
 */
declare(strict_types=1);

/**
 * Safe escape wrapper for DB strings.
 * Handles mysqli, mock objects, and null.
 */
function safe_escape($db, string $str): string
{
    if ($db instanceof mysqli) {
        return mysqli_real_escape_string($db, $str);
    }
    return addslashes($str);
}

final class BudgetGeneratorService
{
    /** @var InflationFactorManager */
    private $factorManager;

    /** @var BudgetLogger|null */
    private $logger;

    /**
     * @param InflationFactorManager $factorManager
     * @param BudgetLogger|null $logger
     */
    public function __construct(InflationFactorManager $factorManager, ?BudgetLogger $logger = null)
    {
        $this->factorManager = $factorManager;
        $this->logger = $logger;
    }

    public function setLogger(BudgetLogger $logger): void
    {
        $this->logger = $logger;
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

        $scenarioMultiplier = $this->getScenarioMultiplier($scenarioId);
        $scenarioName = $this->getScenarioName($scenarioId);

        if ($this->logger) {
            $this->logger->logHeader($targetYear, $startMonth, $scenarioName, $scenarioMultiplier);
            $this->logger->logInfo("Source year: $sourceYear");
            $this->logger->logSeparator();
            $this->logger->logInfo("");
        }

        $entries = [];
        $glAccounts = $this->getGLAccountsWithActuals($sourceYear);

        if ($this->logger) {
            $this->logger->logInfo("GL accounts with actuals in $sourceYear: " . count($glAccounts));
            $this->logger->logInfo("");
        }

        foreach ($glAccounts as $glAccount) {
            $actuals = $this->getActualsByGL($glAccount, $sourceYear);
            $inflationRate = $this->factorManager->getRateForAccount($glAccount);
            $accountName = $this->getAccountName($glAccount);

            if ($this->logger) {
                $this->logger->logGLHeader($glAccount, $accountName, $inflationRate);
            }

            $budgetAmounts = [];
            $glTotalBudget = 0.0;
            $glTotalActual = 0.0;

            for ($month = $startMonth; $month <= 12; $month++) {
                $actualAmount = $actuals[$month] ?? 0.0;
                $effectiveRate = $inflationRate * $scenarioMultiplier;
                $rateMultiplier = 1.0 + ($effectiveRate / 100.0);
                $budgetAmounts[$month] = $actualAmount * $rateMultiplier;

                if ($this->logger) {
                    if ($actualAmount != 0.0 || $budgetAmounts[$month] != 0.0) {
                        $this->logger->logMonthEntry($glAccount, $month, $actualAmount, $budgetAmounts[$month], $rateMultiplier);
                    }
                }

                $glTotalActual += $actualAmount;
                $glTotalBudget += $budgetAmounts[$month];
            }

            if ($this->logger) {
                $this->logger->logInfo("  Total: actual $" . number_format($glTotalActual, 2) . " -> budget $" . number_format($glTotalBudget, 2));
                $this->logger->logInfo("");
            }

            $entries[] = new BudgetEntryDTO($glAccount, $targetYear, $budgetAmounts);
        }

        if ($this->logger) {
            $this->logger->logInfo("Generation complete: " . count($entries) . " GL accounts");
        }

        return $entries;
    }

    private function getScenarioMultiplier(int $scenarioId): float
    {
        global $db;
        if ($scenarioId <= 0) return 1.0;

        $sql = "SELECT multiplier FROM " . TB_PREF . "ksf_quickbudget_scenarios WHERE id = " . (int)$scenarioId;
        $result = db_query($sql, null);
        $row = db_fetch_assoc($result);
        return $row ? (float)$row['multiplier'] : 1.0;
    }

    private function getScenarioName(int $scenarioId): string
    {
        global $db;
        if ($scenarioId <= 0) return 'Baseline';

        $sql = "SELECT name FROM " . TB_PREF . "ksf_quickbudget_scenarios WHERE id = " . (int)$scenarioId;
        $result = db_query($sql, null);
        $row = db_fetch_assoc($result);
        return $row ? $row['name'] : 'Unknown';
    }

    /**
     * Save budget entries to FA native budget_trans table.
     *
     * @param array<BudgetEntryDTO> $entries
     * @param string $pathToRoot Path to FA root for including functions
     * @param bool $submitForApproval Whether to create approval records
     * @return int Number of entries saved
     * @see FR-14, FR-21
     */
    public function saveToFABudget(array $entries, string $pathToRoot = '', bool $submitForApproval = false): int
    {
        global $db;

        $count = 0;
        $totalAmount = 0.0;
        $errors = 0;

        if ($this->logger) {
            $this->logger->logInfo("");
            $this->logger->logSeparator();
            $this->logger->logInfo("Saving to FA budget_trans...");
            $this->logger->logInfo("");
        }

        foreach ($entries as $entry) {
            $monthlyAmounts = $entry->getMonthlyAmounts();
            foreach ($monthlyAmounts as $month => $amount) {
                if ($amount != 0.0) {
                    $sqlDate = sprintf('%04d-%02d-01', $entry->getYear(), $month);

                    $deleteSql = "DELETE FROM " . TB_PREF . "budget_trans
                        WHERE tran_date = '" . safe_escape($db, $sqlDate) . "'
                        AND account = '" . safe_escape($db, $entry->getGLAccount()) . "'";
                    $deleteResult = db_query($deleteSql);

                    if (!$deleteResult) {
                        $errors++;
                        if ($this->logger) {
                            $this->logger->logError('DELETE', "Failed for " . $entry->getGLAccount() . " $sqlDate");
                        }
                    }

                    $insertSql = "INSERT INTO " . TB_PREF . "budget_trans
                        (tran_date, account, dimension_id, dimension2_id, amount, memo_)
                        VALUES ('" . safe_escape($db, $sqlDate) . "',
                            '" . safe_escape($db, $entry->getGLAccount()) . "',
                            0, 0, " . (float)$amount . ", 'QuickBudget')";
                    $result = db_query($insertSql);

                    if ($result) {
                        $count++;
                        $totalAmount += $amount;
                    } else {
                        $errors++;
                        $err = db_error_msg($db);
                        if ($this->logger) {
                            $this->logger->logError('INSERT', "Failed: " . $entry->getGLAccount() . " $sqlDate $" . number_format($amount, 2) . " - $err");
                        }
                        error_log("QuickBudget ERROR: INSERT failed for " . $entry->getGLAccount() . ": $insertSql - $err");
                    }
                }
            }
        }

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

        if ($this->logger) {
            $this->logger->logSummary(
                count($entries),
                $count,
                $totalAmount
            );
            if ($errors > 0) {
                $this->logger->logError('SAVE', "$errors errors occurred during save");
            }
        }

        return $count;
    }

    private function submitForApproval(string $tranDate, string $glAccount): void
    {
        global $db;

        $sql = "INSERT IGNORE INTO " . TB_PREF . "ksf_quickbudget_approvals
            (tran_date, gl_account, status)
            VALUES ('" . safe_escape($db, $tranDate) . "',
                '" . safe_escape($db, $glAccount) . "',
                'pending')";
        db_query($sql, null);
    }

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
            WHERE account = '" . safe_escape($db, $glAccount) . "'
            AND YEAR(tran_date) = " . (int)$year . "
            GROUP BY MONTH(tran_date)";
        $result = db_query($sql, null);

        $monthly = [];
        while ($row = db_fetch_assoc($result)) {
            $monthly[(int)$row['month']] = (float)$row['total'];
        }
        return $monthly;
    }

    private function getAccountName(string $accountCode): string
    {
        global $db;

        $sql = "SELECT account_name FROM " . TB_PREF . "chart_master
            WHERE account_code = '" . safe_escape($db, $accountCode) . "'";
        $result = db_query($sql, null);
        if ($result && $row = db_fetch_assoc($result)) {
            return $row['account_name'];
        }
        return $accountCode;
    }
}
