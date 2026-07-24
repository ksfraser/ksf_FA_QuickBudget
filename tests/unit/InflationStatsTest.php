<?php
/**
 * InflationStatsTest
 *
 * Unit tests for statistical aggregation of historical inflation data.
 * Covers FR-42, FR-43.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../src/Service/InflationStats.php';

use PHPUnit\Framework\TestCase;

class InflationStatsTest extends TestCase
{
    private $stats;

    protected function setUp(): void
    {
        $this->stats = new InflationStats();
    }

    public function testMean(): void
    {
        $this->assertEqualsWithDelta(3.0, $this->stats->mean([1.0, 2.0, 3.0, 4.0, 5.0]), 0.001);
    }

    public function testMeanSingleValue(): void
    {
        $this->assertEqualsWithDelta(5.0, $this->stats->mean([5.0]), 0.001);
    }

    public function testMeanEmpty(): void
    {
        $this->assertEquals(0.0, $this->stats->mean([]));
    }

    public function testMedianOddCount(): void
    {
        $this->assertEqualsWithDelta(3.0, $this->stats->median([1.0, 2.0, 3.0, 4.0, 5.0]), 0.001);
    }

    public function testMedianEvenCount(): void
    {
        $this->assertEqualsWithDelta(2.5, $this->stats->median([1.0, 2.0, 3.0, 4.0]), 0.001);
    }

    public function testMedianEmpty(): void
    {
        $this->assertEquals(0.0, $this->stats->median([]));
    }

    public function testModeWithRepeats(): void
    {
        $this->assertEqualsWithDelta(3.0, $this->stats->mode([1.0, 3.0, 3.0, 5.0]), 0.001);
    }

    public function testModeNoRepeats(): void
    {
        $this->assertNull($this->stats->mode([1.0, 2.0, 3.0]));
    }

    public function testModeEmpty(): void
    {
        $this->assertNull($this->stats->mode([]));
    }

    public function testStdDev(): void
    {
        // Population std dev of [2, 4, 4, 4, 5, 5, 7, 9]
        $values = [2.0, 4.0, 4.0, 4.0, 5.0, 5.0, 7.0, 9.0];
        $mean = $this->stats->mean($values);
        $this->assertEqualsWithDelta(2.0, $this->stats->stdDev($values, $mean), 0.01);
    }

    public function testStdDevSingleValue(): void
    {
        $this->assertEquals(0.0, $this->stats->stdDev([5.0]));
    }

    public function testStdDevEmpty(): void
    {
        $this->assertEquals(0.0, $this->stats->stdDev([]));
    }

    public function testCAGR(): void
    {
        // 1000 to 1100 over 1 year = 10%
        $this->assertEqualsWithDelta(0.10, $this->stats->cagr(1000, 1100, 1), 0.001);
    }

    public function testCAGROverMultipleYears(): void
    {
        // 1000 to 1210 over 2 years = ~10% per year
        $this->assertEqualsWithDelta(0.10, $this->stats->cagr(1000, 1210, 2), 0.001);
    }

    public function testCAGRZeroStart(): void
    {
        $this->assertNull($this->stats->cagr(0, 100, 1));
    }

    public function testCAGRZeroYears(): void
    {
        $this->assertNull($this->stats->cagr(100, 200, 0));
    }

    public function testCAGRNegativeValues(): void
    {
        $this->assertNull($this->stats->cagr(-100, 200, 1));
    }

    public function testTrendSlope(): void
    {
        // Linear: 100, 110, 120, 130, 140
        $data = [2020 => 100.0, 2021 => 110.0, 2022 => 120.0, 2023 => 130.0, 2024 => 140.0];
        $this->assertEqualsWithDelta(10.0, $this->stats->trendSlope($data), 0.01);
    }

    public function testTrendSlopeSingleEntry(): void
    {
        $this->assertEquals(0.0, $this->stats->trendSlope([2024 => 100.0]));
    }

    public function testIsWithinNormTrue(): void
    {
        $this->assertTrue($this->stats->isWithinNorm(5.0, 5.0, 1.0, 1.0));
        $this->assertTrue($this->stats->isWithinNorm(5.5, 5.0, 1.0, 1.0));
    }

    public function testIsWithinNormFalse(): void
    {
        $this->assertFalse($this->stats->isWithinNorm(7.0, 5.0, 1.0, 1.0));
    }

    public function testIsWithinNormZeroStdDev(): void
    {
        // If no variance, everything is "normal"
        $this->assertTrue($this->stats->isWithinNorm(100.0, 5.0, 0.0, 1.0));
    }

    public function testCalculateFull(): void
    {
        $rates = [0.02, 0.03, 0.03, 0.05, 0.03];
        $result = $this->stats->calculate($rates);

        $this->assertArrayHasKey('mean', $result);
        $this->assertArrayHasKey('median', $result);
        $this->assertArrayHasKey('mode', $result);
        $this->assertArrayHasKey('min', $result);
        $this->assertArrayHasKey('max', $result);
        $this->assertArrayHasKey('stddev', $result);
        $this->assertArrayHasKey('count', $result);

        $this->assertEqualsWithDelta(0.032, $result['mean'], 0.001);
        $this->assertEqualsWithDelta(0.03, $result['median'], 0.001);
        $this->assertEqualsWithDelta(0.03, $result['mode'], 0.001);
        $this->assertEqualsWithDelta(0.02, $result['min'], 0.001);
        $this->assertEqualsWithDelta(0.05, $result['max'], 0.001);
        $this->assertEquals(5, $result['count']);
    }

    public function testCalculateEmpty(): void
    {
        $result = $this->stats->calculate([]);

        $this->assertEquals(0.0, $result['mean']);
        $this->assertEquals(0, $result['count']);
        $this->assertNull($result['mode']);
    }

    public function testGetTrendIndicators(): void
    {
        $yearlyData = [
            ['year' => 2019, 'yoy_rate' => 0.02, 'actual_total' => 10000],
            ['year' => 2020, 'yoy_rate' => 0.03, 'actual_total' => 10300],
            ['year' => 2021, 'yoy_rate' => 0.025, 'actual_total' => 10558],
            ['year' => 2022, 'yoy_rate' => 0.04, 'actual_total' => 10980],
            ['year' => 2023, 'yoy_rate' => 0.035, 'actual_total' => 11364],
            ['year' => 2024, 'yoy_rate' => 0.03, 'actual_total' => 11705],
        ];

        $indicators = $this->stats->getTrendIndicators($yearlyData);

        $this->assertArrayHasKey(1, $indicators);
        $this->assertArrayHasKey(3, $indicators);
        $this->assertArrayHasKey(5, $indicators);

        // 1yr CAGR: 11705/11364 ^ 1/1 - 1 ~ 3%
        $this->assertEqualsWithDelta(0.03, $indicators[1], 0.005);

        // 5yr CAGR: 11705/10000 ^ 1/5 - 1 ~ 3.2%
        $this->assertEqualsWithDelta(0.032, $indicators[5], 0.005);
    }

    public function testGetTrendIndicatorsInsufficientData(): void
    {
        // Only 2 years of data
        $yearlyData = [
            ['year' => 2023, 'yoy_rate' => 0.03, 'actual_total' => 10000],
            ['year' => 2024, 'yoy_rate' => 0.04, 'actual_total' => 10400],
        ];

        $indicators = $this->stats->getTrendIndicators($yearlyData);

        // 1yr should work
        $this->assertNotNull($indicators[1]);
        // 3/5/7/10yr should be null (insufficient data)
        $this->assertNull($indicators[3]);
        $this->assertNull($indicators[5]);
        $this->assertNull($indicators[7]);
        $this->assertNull($indicators[10]);
    }
}
