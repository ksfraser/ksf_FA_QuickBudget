<?php
/**
 * BudgetLogger
 *
 * Writes detailed GL-level budget generation logs to company/0/logs/.
 * Log format: GL123 - Jan - $120.00
 * File: company/0/logs/quickbudget_YYMMDDHHmmss.txt
 *
 * @since 1.1.0
 */
declare(strict_types=1);

class BudgetLogger
{
    /** @var string Full path to log file */
    private $logFile;

    /** @var resource|null File handle */
    private $handle;

    /** @var array<string> Month names */
    private static $monthNames = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
        5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
        9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
    ];

    /**
     * @param string $companyPath Path to company dir (e.g. $path_to_root . '/company')
     * @param int $companyId Company number (default 0)
     */
    public function __construct(string $companyPath, int $companyId = 0)
    {
        $logDir = $companyPath . '/' . $companyId . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $timestamp = date('ymdHis');
        $this->logFile = $logDir . '/quickbudget_' . $timestamp . '.txt';
        $this->handle = @fopen($this->logFile, 'w');
    }

    public function __destruct()
    {
        $this->close();
    }

    public function close(): void
    {
        if ($this->handle !== null) {
            fclose($this->handle);
            $this->handle = null;
        }
    }

    public function getLogPath(): string
    {
        return $this->logFile;
    }

    // ----------------------------------------------------------------
    // Section headers
    // ----------------------------------------------------------------

    public function logHeader(int $targetYear, int $startMonth, string $scenarioName, float $scenarioMultiplier): void
    {
        $this->write("=== QuickBudget Generation Log ===");
        $this->write("Date:         " . date('Y-m-d H:i:s'));
        $this->write("Target Year:  $targetYear");
        $this->write("Start Month:  " . self::$monthNames[$startMonth] . " ($startMonth)");
        $this->write("Scenario:     $scenarioName (" . number_format($scenarioMultiplier, 2) . "x)");
        $this->write("Source Year:  " . ($targetYear - 1));
        $this->write(str_repeat('=', 50));
        $this->write("");
    }

    public function logSeparator(): void
    {
        $this->write(str_repeat('-', 50));
    }

    // ----------------------------------------------------------------
    // Per-GL logging
    // ----------------------------------------------------------------

    /**
     * Log a single GL account's inflation rate.
     */
    public function logGLHeader(string $accountCode, string $accountName, float $inflationRate): void
    {
        $this->write("GL $accountCode - $accountName");
        $this->write("  Inflation Rate: " . number_format($inflationRate, 4) . "%");
    }

    /**
     * Log one month's actual → budget for a GL account.
     * Format: GL123 - Jan - $120.00
     */
    public function logMonthEntry(string $accountCode, int $month, float $actual, float $budget, float $rateMultiplier): void
    {
        $monthName = self::$monthNames[$month] ?? sprintf('M%02d', $month);
        $actualStr = '$' . number_format($actual, 2);
        $budgetStr = '$' . number_format($budget, 2);

        $this->write("  GL $accountCode - $monthName - Actual: $actualStr -> Budget: $budgetStr (x" . number_format($rateMultiplier, 4) . ")");
    }

    /**
     * Log a skipped month (zero actual and zero budget).
     */
    public function logMonthSkipped(string $accountCode, int $month): void
    {
        $monthName = self::$monthNames[$month] ?? sprintf('M%02d', $month);
        $this->write("  GL $accountCode - $monthName - SKIPPED (zero)");
    }

    // ----------------------------------------------------------------
    // Save logging
    // ----------------------------------------------------------------

    /**
     * Log a DELETE+INSERT pair for budget_trans.
     */
    public function logInsert(string $accountCode, string $sqlDate, float $amount, bool $success, ?string $error = null): void
    {
        $status = $success ? 'OK' : 'FAIL';
        $amountStr = '$' . number_format($amount, 2);
        $line = "  SAVE $accountCode $sqlDate $amountStr [$status]";
        if ($error !== null) {
            $line .= " - $error";
        }
        $this->write($line);
    }

    /**
     * Log a DELETE that returned no rows (nothing to delete).
     */
    public function logDeleteNone(string $accountCode, string $sqlDate): void
    {
        $this->write("  DEL  $accountCode $sqlDate (no existing row)");
    }

    // ----------------------------------------------------------------
    // Summary logging
    // ----------------------------------------------------------------

    public function logSummary(int $glCount, int $entryCount, float $totalBudget): void
    {
        $this->write("");
        $this->write("=== Summary ===");
        $this->write("GL Accounts Processed: $glCount");
        $this->write("Budget Entries Saved:  $entryCount");
        $this->write("Total Budget Amount:   $" . number_format($totalBudget, 2));
    }

    /**
     * Log DB verification query results.
     */
    public function logVerification(int $expectedYear, int $dbCount, array $sampleRows = []): void
    {
        $this->write("");
        $this->write("=== DB Verification ===");
        $this->write("Rows in budget_trans for $expectedYear: $dbCount");

        if (!empty($sampleRows)) {
            $this->write("Sample rows:");
            foreach ($sampleRows as $row) {
                $this->write("  " . $row['account'] . " " . $row['tran_date'] . " $" . number_format((float)$row['amount'], 2));
            }
        }
    }

    /**
     * Log an error message.
     */
    public function logError(string $context, string $message): void
    {
        $this->write("ERROR [$context] $message");
    }

    /**
     * Log a general info message.
     */
    public function logInfo(string $message): void
    {
        $this->write("INFO: $message");
    }

    // ----------------------------------------------------------------
    // Internal
    // ----------------------------------------------------------------

    private function write(string $line): void
    {
        if ($this->handle !== null) {
            fwrite($this->handle, $line . "\n");
        }
        // Also to error_log for debugging
        error_log("QuickBudget LOG: $line");
    }
}
