<?php
/**
 * InflationStats
 *
 * Statistical aggregation for historical inflation data.
 * Computes mean, median, mode, min, max, std dev, CAGR, and trend slope.
 *
 * @see Project_Docs/Issue_2_Plan.md - Phase 1B
 * @since 1.1.0
 */
declare(strict_types=1);

class InflationStats
{
    /**
     * Compute all statistics for a set of YoY inflation rates.
     *
     * @param array<float> $rates YoY inflation rates (excluding nulls)
     * @return array{mean: float, median: float, mode: float|null, min: float, max: float, stddev: float, count: int}
     */
    public function calculate(array $rates): array
    {
        if (empty($rates)) {
            return [
                'mean' => 0.0,
                'median' => 0.0,
                'mode' => null,
                'min' => 0.0,
                'max' => 0.0,
                'stddev' => 0.0,
                'count' => 0,
            ];
        }

        $sorted = $rates;
        sort($sorted, SORT_NUMERIC);

        $mean = $this->mean($sorted);
        $median = $this->median($sorted);
        $mode = $this->mode($sorted);
        $min = $sorted[0];
        $max = end($sorted);
        $stddev = $this->stdDev($sorted, $mean);

        return [
            'mean' => $mean,
            'median' => $median,
            'mode' => $mode,
            'min' => $min,
            'max' => $max,
            'stddev' => $stddev,
            'count' => count($rates),
        ];
    }

    /**
     * Compute mean of an array of numbers.
     *
     * @param array<float> $values Sorted numeric array
     * @return float
     */
    public function mean(array $values): float
    {
        if (empty($values)) {
            return 0.0;
        }
        return array_sum($values) / count($values);
    }

    /**
     * Compute median of an array of numbers.
     *
     * @param array<float> $values Sorted numeric array
     * @return float
     */
    public function median(array $values): float
    {
        $count = count($values);
        if ($count === 0) {
            return 0.0;
        }

        $mid = intdiv($count, 2);
        if ($count % 2 === 0) {
            return ($values[$mid - 1] + $values[$mid]) / 2.0;
        }
        return $values[$mid];
    }

    /**
     * Compute mode (most frequent value) of an array.
     * Returns null if no value repeats.
     *
     * @param array<float> $values Sorted numeric array
     * @return float|null
     */
    public function mode(array $values): ?float
    {
        if (empty($values)) {
            return null;
        }

        $counts = [];
        foreach ($values as $v) {
            $key = (string)round($v, 4);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        $maxCount = max($counts);
        if ($maxCount === 1) {
            return null; // no repeats
        }

        // Return the smallest value with the highest frequency
        foreach ($counts as $val => $cnt) {
            if ($cnt === $maxCount) {
                return (float)$val;
            }
        }

        return null;
    }

    /**
     * Compute population standard deviation.
     *
     * @param array<float> $values Sorted numeric array
     * @param float $mean Pre-computed mean (optional)
     * @return float
     */
    public function stdDev(array $values, ?float $mean = null): float
    {
        $count = count($values);
        if ($count < 2) {
            return 0.0;
        }

        if ($mean === null) {
            $mean = $this->mean($values);
        }

        $sum = 0.0;
        foreach ($values as $v) {
            $sum += ($v - $mean) ** 2;
        }

        return sqrt($sum / $count);
    }

    /**
     * Compute Compound Annual Growth Rate (CAGR).
     *
     * CAGR = (endValue / startValue) ^ (1 / years) - 1
     *
     * @param float $startValue Earlier value
     * @param float $endValue Later value
     * @param int $years Number of years between values
     * @return float|null CAGR as a decimal (e.g., 0.03 = 3%), or null if invalid
     */
    public function cagr(float $startValue, float $endValue, int $years): ?float
    {
        if ($years <= 0 || $startValue <= 0 || $endValue <= 0) {
            return null;
        }

        $ratio = $endValue / $startValue;
        $cagr = pow($ratio, 1.0 / $years) - 1.0;

        return $cagr;
    }

    /**
     * Compute trend slope via linear regression (least squares).
     *
     * @param array<int, float> $yearlyData Year => value (must have at least 2 entries)
     * @return float Slope (change in value per year)
     */
    public function trendSlope(array $yearlyData): float
    {
        $n = count($yearlyData);
        if ($n < 2) {
            return 0.0;
        }

        $years = array_keys($yearlyData);
        $values = array_values($yearlyData);

        $sumX = 0.0;
        $sumY = 0.0;
        $sumXY = 0.0;
        $sumX2 = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $x = (float)$years[$i];
            $y = $values[$i];
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }

        $denominator = ($n * $sumX2) - ($sumX * $sumX);
        if (abs($denominator) < 1e-10) {
            return 0.0;
        }

        return (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
    }

    /**
     * Compute CAGR trend indicators for 1/3/5/7/10 year periods.
     *
     * @param array<int, array{year: int, yoy_rate: float, actual_total: float}> $yearlyData
     *   Sorted by year ascending. Each entry has year, yoy_rate, and actual_total.
     * @return array{1yr: float|null, 3yr: float|null, 5yr: float|null, 7yr: float|null, 10yr: float|null}
     *   CAGRs as decimals (e.g., 0.03 = 3%)
     */
    public function getTrendIndicators(array $yearlyData): array
    {
        $periods = [1, 3, 5, 7, 10];
        $indicators = [];

        // Sort by year ascending
        usort($yearlyData, function ($a, $b) {
            return $a['year'] <=> $b['year'];
        });

        $latestYear = end($yearlyData)['year'] ?? 0;

        foreach ($periods as $period) {
            $targetYear = $latestYear - $period;
            $startEntry = null;
            $endEntry = null;

            foreach ($yearlyData as $entry) {
                if ($entry['year'] === $targetYear) {
                    $startEntry = $entry;
                }
                if ($entry['year'] === $latestYear) {
                    $endEntry = $entry;
                }
            }

            if ($startEntry && $endEntry && $startEntry['actual_total'] > 0 && $endEntry['actual_total'] > 0) {
                $indicators[$period] = $this->cagr(
                    $startEntry['actual_total'],
                    $endEntry['actual_total'],
                    $period
                );
            } else {
                $indicators[$period] = null;
            }
        }

        return $indicators;
    }

    /**
     * Check if a value is within N standard deviations of the mean.
     *
     * @param float $value The value to check
     * @param float $mean The mean of the distribution
     * @param float $stdDev The standard deviation
     * @param float $band Number of standard deviations (default 1.0)
     * @return bool True if within the band
     */
    public function isWithinNorm(float $value, float $mean, float $stdDev, float $band = 1.0): bool
    {
        if ($stdDev <= 0) {
            return true; // no variance = everything is "normal"
        }
        return abs($value - $mean) <= ($band * $stdDev);
    }
}
