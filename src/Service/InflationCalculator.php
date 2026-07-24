<?php
/**
 * InflationCalculator
 *
 * Calculates apparent historical inflation from GL actuals.
 * Supports Issue #2: FR-37 through FR-44.
 *
 * Queries 0_gl_trans to compute year-over-year inflation at
 * GL, category, and class levels. Uses all available data
 * (no artificial year cap).
 *
 * @see Project_Docs/Issue_2_Plan.md - Phase 1A
 * @since 1.1.0
 */
declare(strict_types=1);

class InflationCalculator
{
    /** @var InflationStats */
    private $stats;

    public function __construct(?InflationStats $stats = null)
    {
        $this->stats = $stats ?? new InflationStats();
    }

    /**
     * Get all distinct years that have GL transaction data.
     *
     * @return array<int> Sorted years ascending
     */
    public function getAvailableYears(): array
    {
        global $db;

        if (empty($db)) {
            return [];
        }

        $sql = "SELECT DISTINCT YEAR(tran_date) AS yr
                FROM " . TB_PREF . "gl_trans
                WHERE amount != 0
                ORDER BY yr ASC";
        $result = db_query($sql);
        if (!$result) {
            return [];
        }

        $years = [];
        while ($row = db_fetch_assoc($result)) {
            $years[] = (int)$row['yr'];
        }

        return $years;
    }

    /**
     * Calculate YoY inflation for a specific GL account.
     *
     * Returns an array of entries, one per year where both current and prior year
     * data exist. Each entry:
     *   year, yoy_rate, actual_current, actual_prior
     *
     * @param string $accountCode GL account code
     * @return array<int, array{year: int, yoy_rate: float|null, actual_current: float, actual_prior: float}>
     *   Sorted by year ascending
     */
    public function calculateForGL(string $accountCode): array
    {
        global $db;

        if (empty($db)) {
            return [];
        }

        $yearlyTotals = $this->getYearlyTotalsForGL($accountCode);

        return $this->buildYearlyEntries($yearlyTotals);
    }

    /**
     * Calculate YoY inflation at category level.
     *
     * Sums all GL actuals in the category per year, then computes YoY.
     *
     * @param string $categoryId chart_class.cid
     * @return array<int, array{year: int, yoy_rate: float|null, actual_current: float, actual_prior: float}>
     */
    public function calculateForCategory(string $categoryId): array
    {
        global $db;

        if (empty($db)) {
            return [];
        }

        $sql = "SELECT YEAR(t.tran_date) AS yr, SUM(t.amount) AS total
                FROM " . TB_PREF . "gl_trans t
                INNER JOIN " . TB_PREF . "chart_master cm ON t.account = cm.account_code
                INNER JOIN " . TB_PREF . "chart_types ct ON cm.account_type = ct.id
                WHERE ct.class_id = '" . addslashes($categoryId) . "'
                AND t.amount != 0
                GROUP BY YEAR(t.tran_date)
                ORDER BY yr ASC";

        $yearlyTotals = $this->queryYearlyTotals($sql);

        return $this->buildYearlyEntries($yearlyTotals);
    }

    /**
     * Calculate YoY inflation at class level.
     *
     * Sums all category actuals in the class per year, then computes YoY.
     *
     * @param string $classId chart_class.cid (the class grouping)
     * @return array<int, array{year: int, yoy_rate: float|null, actual_current: float, actual_prior: float}>
     */
    public function calculateForClass(string $classId): array
    {
        global $db;

        if (empty($db)) {
            return [];
        }

        $sql = "SELECT YEAR(t.tran_date) AS yr, SUM(t.amount) AS total
                FROM " . TB_PREF . "gl_trans t
                INNER JOIN " . TB_PREF . "chart_master cm ON t.account = cm.account_code
                INNER JOIN " . TB_PREF . "chart_types ct ON cm.account_type = ct.id
                WHERE ct.class_id = '" . addslashes($classId) . "'
                AND t.amount != 0
                GROUP BY YEAR(t.tran_date)
                ORDER BY yr ASC";

        $yearlyTotals = $this->queryYearlyTotals($sql);

        return $this->buildYearlyEntries($yearlyTotals);
    }

    /**
     * Calculate YoY inflation aggregated across ALL accounts.
     *
     * @return array<int, array{year: int, yoy_rate: float|null, actual_current: float, actual_prior: float}>
     */
    public function calculateAll(): array
    {
        global $db;

        if (empty($db)) {
            return [];
        }

        $sql = "SELECT YEAR(tran_date) AS yr, SUM(amount) AS total
                FROM " . TB_PREF . "gl_trans
                WHERE amount != 0
                GROUP BY YEAR(tran_date)
                ORDER BY yr ASC";

        $yearlyTotals = $this->queryYearlyTotals($sql);

        return $this->buildYearlyEntries($yearlyTotals);
    }

    /**
     * Get all GL accounts with data in a given year.
     *
     * @param int|null $year Filter by year (null = all years)
     * @return array<string, array{account_code: string, account_name: string, class_id: string, class_name: string}>
     */
    public function getGLAccounts(?int $year = null): array
    {
        global $db;

        if (empty($db)) {
            return [];
        }

        $where = $year !== null
            ? "WHERE YEAR(t.tran_date) = " . (int)$year . " AND t.amount != 0"
            : "WHERE t.amount != 0";

        $sql = "SELECT DISTINCT t.account AS account_code,
                       cm.account_name,
                       ct.class_id,
                       cc.class_name
                FROM " . TB_PREF . "gl_trans t
                INNER JOIN " . TB_PREF . "chart_master cm ON t.account = cm.account_code
                INNER JOIN " . TB_PREF . "chart_types ct ON cm.account_type = ct.id
                INNER JOIN " . TB_PREF . "chart_class cc ON ct.class_id = cc.cid
                $where
                ORDER BY t.account ASC";

        $result = db_query($sql);
        if (!$result) {
            return [];
        }

        $accounts = [];
        while ($row = db_fetch_assoc($result)) {
            $code = (string)$row['account_code'];
            $accounts[$code] = [
                'account_code' => $code,
                'account_name' => (string)$row['account_name'],
                'class_id' => (string)$row['class_id'],
                'class_name' => (string)$row['class_name'],
            ];
        }

        return $accounts;
    }

    /**
     * Get all categories (chart_class) that have GL data.
     *
     * @return array<string, array{cid: string, class_name: string}>
     */
    public function getCategories(): array
    {
        global $db;

        if (empty($db)) {
            return [];
        }

        $sql = "SELECT DISTINCT cc.cid, cc.class_name
                FROM " . TB_PREF . "chart_class cc
                INNER JOIN " . TB_PREF . "chart_types ct ON ct.class_id = cc.cid
                INNER JOIN " . TB_PREF . "chart_master cm ON cm.account_type = ct.id
                INNER JOIN " . TB_PREF . "gl_trans t ON t.account = cm.account_code
                WHERE t.amount != 0
                ORDER BY cc.cid ASC";

        $result = db_query($sql);
        if (!$result) {
            return [];
        }

        $categories = [];
        while ($row = db_fetch_assoc($result)) {
            $cid = (string)$row['cid'];
            $categories[$cid] = [
                'cid' => $cid,
                'class_name' => (string)$row['class_name'],
            ];
        }

        return $categories;
    }

    /**
     * Get all classes (chart_class) that have GL data.
     *
     * @return array<string, array{cid: string, class_name: string}>
     */
    public function getClasses(): array
    {
        // In FA, chart_class IS the class level (Assets, Liabilities, Income, Expenses)
        // so getCategories() and getClasses() return the same thing.
        // This method exists for API consistency.
        return $this->getCategories();
    }

    /**
     * Get the parent class for a given category.
     * In FA, categories ARE the top level, so this returns the same category
     * unless we implement a sub-class hierarchy.
     *
     * For now, returns null (no parent above category).
     *
     * @param string $categoryId chart_class.cid
     * @return string|null Parent class id or null
     */
    public function getParentClass(string $categoryId): ?string
    {
        // FA's chart_class is the top level - no parent above it
        return null;
    }

    /**
     * Compute all statistics for a set of yearly entries.
     *
     * @param array<int, array{year: int, yoy_rate: float|null, actual_current: float, actual_prior: float}> $yearlyEntries
     * @return array{stats: array, trend_indicators: array, yearly_data: array}
     */
    public function computeStats(array $yearlyEntries): array
    {
        // Extract non-null YoY rates
        $rates = [];
        $yearlyData = [];

        foreach ($yearlyEntries as $entry) {
            if ($entry['yoy_rate'] !== null) {
                $rates[] = $entry['yoy_rate'];
                $yearlyData[] = [
                    'year' => $entry['year'],
                    'yoy_rate' => $entry['yoy_rate'],
                    'actual_total' => $entry['actual_current'],
                ];
            }
        }

        $stats = $this->stats->calculate($rates);
        $trendIndicators = $this->stats->getTrendIndicators($yearlyData);
        $slope = $this->stats->trendSlope(
            array_combine(
                array_column($yearlyData, 'year'),
                array_column($yearlyData, 'actual_total')
            )
        );

        return [
            'stats' => $stats,
            'trend_indicators' => $trendIndicators,
            'trend_slope' => $slope,
            'yearly_data' => $yearlyData,
        ];
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    /**
     * Get yearly totals for a single GL account.
     *
     * @param string $accountCode
     * @return array<int, float> Year => total
     */
    private function getYearlyTotalsForGL(string $accountCode): array
    {
        global $db;

        $sql = "SELECT YEAR(tran_date) AS yr, SUM(amount) AS total
                FROM " . TB_PREF . "gl_trans
                WHERE account = '" . addslashes($accountCode) . "'
                AND amount != 0
                GROUP BY YEAR(tran_date)
                ORDER BY yr ASC";

        return $this->queryYearlyTotals($sql);
    }

    /**
     * Execute a SQL query that returns year => total.
     *
     * @param string $sql SQL with YEAR(tran_date) AS yr, SUM(amount) AS total
     * @return array<int, float> Year => total
     */
    private function queryYearlyTotals(string $sql): array
    {
        global $db;

        $result = db_query($sql);
        if (!$result) {
            return [];
        }

        $totals = [];
        while ($row = db_fetch_assoc($result)) {
            $yr = (int)$row['yr'];
            $total = (float)$row['total'];
            if ($total != 0.0) {
                $totals[$yr] = $total;
            }
        }

        return $totals;
    }

    /**
     * Build yearly entries with YoY rates from yearly totals.
     *
     * @param array<int, float> $yearlyTotals Year => total (sorted ascending)
     * @return array<int, array{year: int, yoy_rate: float|null, actual_current: float, actual_prior: float}>
     */
    private function buildYearlyEntries(array $yearlyTotals): array
    {
        if (empty($yearlyTotals)) {
            return [];
        }

        ksort($yearlyTotals);
        $years = array_keys($yearlyTotals);
        $entries = [];

        for ($i = 0; $i < count($years); $i++) {
            $year = $years[$i];
            $current = $yearlyTotals[$year];

            if ($i === 0) {
                // First year has no prior - no YoY possible
                $entries[] = [
                    'year' => $year,
                    'yoy_rate' => null,
                    'actual_current' => $current,
                    'actual_prior' => 0.0,
                ];
                continue;
            }

            $prior = $yearlyTotals[$years[$i - 1]];

            if ($prior > 0) {
                $yoy = ($current / $prior) - 1.0;
            } else {
                $yoy = null; // can't compute YoY if prior is zero
            }

            $entries[] = [
                'year' => $year,
                'yoy_rate' => $yoy,
                'actual_current' => $current,
                'actual_prior' => $prior,
            ];
        }

        return $entries;
    }
}
