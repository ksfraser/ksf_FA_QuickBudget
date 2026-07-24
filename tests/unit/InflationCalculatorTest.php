<?php
/**
 * InflationCalculatorTest
 *
 * Unit tests for historical inflation calculation.
 * DB-dependent methods (calculateForGL, etc.) need integration testing.
 * Pure computation (computeStats) is tested here.
 *
 * @see Project_Docs/Issue_2_Plan.md - Phase 1A
 */
declare(strict_types=1);

require_once __DIR__ . '/../../src/Service/InflationStats.php';
require_once __DIR__ . '/../../src/Service/InflationCalculator.php';

use PHPUnit\Framework\TestCase;

class InflationCalculatorTest extends TestCase
{
    public function testConstructorCreatesDefaultStats(): void
    {
        $calc = new InflationCalculator();
        $this->assertInstanceOf(InflationCalculator::class, $calc);
    }

    public function testConstructorAcceptsInjectedStats(): void
    {
        $stats = new InflationStats();
        $calc = new InflationCalculator($stats);
        $this->assertInstanceOf(InflationCalculator::class, $calc);
    }

    // ------------------------------------------------------------------
    // computeStats tests (pure computation, no DB)
    // ------------------------------------------------------------------

    public function testComputeStatsReturnsExpectedKeys(): void
    {
        $calc = new InflationCalculator();

        $entries = [
            ['year' => 2020, 'yoy_rate' => 0.03, 'actual_current' => 10300, 'actual_prior' => 10000],
            ['year' => 2021, 'yoy_rate' => 0.025, 'actual_current' => 10558, 'actual_prior' => 10300],
            ['year' => 2022, 'yoy_rate' => 0.04, 'actual_current' => 10980, 'actual_prior' => 10558],
            ['year' => 2023, 'yoy_rate' => 0.035, 'actual_current' => 11364, 'actual_prior' => 10980],
            ['year' => 2024, 'yoy_rate' => 0.03, 'actual_current' => 11705, 'actual_prior' => 11364],
        ];

        $result = $calc->computeStats($entries);

        $this->assertArrayHasKey('stats', $result);
        $this->assertArrayHasKey('trend_indicators', $result);
        $this->assertArrayHasKey('trend_slope', $result);
        $this->assertArrayHasKey('yearly_data', $result);

        // Stats sub-keys
        $this->assertArrayHasKey('mean', $result['stats']);
        $this->assertArrayHasKey('median', $result['stats']);
        $this->assertArrayHasKey('mode', $result['stats']);
        $this->assertArrayHasKey('min', $result['stats']);
        $this->assertArrayHasKey('max', $result['stats']);
        $this->assertArrayHasKey('stddev', $result['stats']);
        $this->assertArrayHasKey('count', $result['stats']);

        // Trend indicators
        $this->assertArrayHasKey(1, $result['trend_indicators']);
        $this->assertArrayHasKey(3, $result['trend_indicators']);
        $this->assertArrayHasKey(5, $result['trend_indicators']);
        $this->assertArrayHasKey(7, $result['trend_indicators']);
        $this->assertArrayHasKey(10, $result['trend_indicators']);
    }

    public function testComputeStatsExcludesNullRates(): void
    {
        $calc = new InflationCalculator();

        $entries = [
            ['year' => 2020, 'yoy_rate' => null, 'actual_current' => 10000, 'actual_prior' => 0],
            ['year' => 2021, 'yoy_rate' => 0.03, 'actual_current' => 10300, 'actual_prior' => 10000],
            ['year' => 2022, 'yoy_rate' => null, 'actual_current' => 10500, 'actual_prior' => 0],
            ['year' => 2023, 'yoy_rate' => 0.04, 'actual_current' => 10920, 'actual_prior' => 10500],
        ];

        $result = $calc->computeStats($entries);

        $this->assertEquals(2, $result['stats']['count']);
        $this->assertCount(2, $result['yearly_data']);
    }

    public function testComputeStatsEmptyEntries(): void
    {
        $calc = new InflationCalculator();
        $result = $calc->computeStats([]);

        $this->assertEquals(0, $result['stats']['count']);
        $this->assertNull($result['stats']['mode']);
        $this->assertEquals(0.0, $result['trend_slope']);
    }

    public function testComputeStatsSingleEntry(): void
    {
        $calc = new InflationCalculator();

        $entries = [
            ['year' => 2024, 'yoy_rate' => 0.05, 'actual_current' => 10500, 'actual_prior' => 10000],
        ];

        $result = $calc->computeStats($entries);

        $this->assertEquals(1, $result['stats']['count']);
        $this->assertEqualsWithDelta(0.05, $result['stats']['mean'], 0.001);
        $this->assertEqualsWithDelta(0.05, $result['stats']['median'], 0.001);
    }

    public function testComputeStatsTrendSlopePositive(): void
    {
        $calc = new InflationCalculator();

        // Inflation increasing over time
        $entries = [
            ['year' => 2020, 'yoy_rate' => 0.02, 'actual_current' => 10000, 'actual_prior' => 9800],
            ['year' => 2021, 'yoy_rate' => 0.03, 'actual_current' => 10300, 'actual_prior' => 10000],
            ['year' => 2022, 'yoy_rate' => 0.04, 'actual_current' => 10712, 'actual_prior' => 10300],
            ['year' => 2023, 'yoy_rate' => 0.05, 'actual_current' => 11248, 'actual_prior' => 10712],
            ['year' => 2024, 'yoy_rate' => 0.06, 'actual_current' => 11923, 'actual_prior' => 11248],
        ];

        $result = $calc->computeStats($entries);

        // Actual totals are increasing, so slope should be positive
        $this->assertGreaterThan(0, $result['trend_slope']);
    }

    public function testComputeStatsTrendIndicatorsMatchData(): void
    {
        $calc = new InflationCalculator();

        $entries = [
            ['year' => 2020, 'yoy_rate' => null, 'actual_current' => 10000, 'actual_prior' => 0],
            ['year' => 2021, 'yoy_rate' => 0.03, 'actual_current' => 10300, 'actual_prior' => 10000],
            ['year' => 2022, 'yoy_rate' => 0.03, 'actual_current' => 10609, 'actual_prior' => 10300],
            ['year' => 2023, 'yoy_rate' => 0.03, 'actual_current' => 10927, 'actual_prior' => 10609],
            ['year' => 2024, 'yoy_rate' => 0.03, 'actual_current' => 11255, 'actual_prior' => 10927],
        ];

        $result = $calc->computeStats($entries);

        // 1yr CAGR: 11255/10927 ^ 1 - 1 ~ 3%
        $this->assertEqualsWithDelta(0.03, $result['trend_indicators'][1], 0.005);

        // 3yr CAGR: 11255/10300 ^ 1/3 - 1 ~ 3%
        $this->assertEqualsWithDelta(0.03, $result['trend_indicators'][3], 0.005);

        // 5yr should be null (only 4 years of data with values)
        $this->assertNull($result['trend_indicators'][5]);
    }

    public function testComputeStatsMeanAndMedian(): void
    {
        $calc = new InflationCalculator();

        $entries = [
            ['year' => 2020, 'yoy_rate' => 0.02, 'actual_current' => 10000, 'actual_prior' => 9800],
            ['year' => 2021, 'yoy_rate' => 0.03, 'actual_current' => 10300, 'actual_prior' => 10000],
            ['year' => 2022, 'yoy_rate' => 0.03, 'actual_current' => 10609, 'actual_prior' => 10300],
            ['year' => 2023, 'yoy_rate' => 0.035, 'actual_current' => 10980, 'actual_prior' => 10609],
            ['year' => 2024, 'yoy_rate' => 0.04, 'actual_current' => 11419, 'actual_prior' => 10980],
        ];

        $result = $calc->computeStats($entries);

        // Mean of [0.02, 0.03, 0.03, 0.035, 0.04] = 0.155/5 = 0.031
        $this->assertEqualsWithDelta(0.031, $result['stats']['mean'], 0.001);

        // Median of sorted [0.02, 0.03, 0.03, 0.035, 0.04] = 0.03
        $this->assertEqualsWithDelta(0.03, $result['stats']['median'], 0.001);

        // Mode = 0.03 (appears twice)
        $this->assertEqualsWithDelta(0.03, $result['stats']['mode'], 0.001);

        // Min/Max
        $this->assertEqualsWithDelta(0.02, $result['stats']['min'], 0.001);
        $this->assertEqualsWithDelta(0.04, $result['stats']['max'], 0.001);
    }

    // ------------------------------------------------------------------
    // DB-dependent methods: mark as needing integration tests
    // ------------------------------------------------------------------

    /**
     * @group integration
     */
    public function testGetAvailableYearsNeedsDB(): void
    {
        $this->markTestSkipped('Requires FA database connection - integration test');
    }

    /**
     * @group integration
     */
    public function testCalculateForGLNeedsDB(): void
    {
        $this->markTestSkipped('Requires FA database connection - integration test');
    }

    /**
     * @group integration
     */
    public function testCalculateForCategoryNeedsDB(): void
    {
        $this->markTestSkipped('Requires FA database connection - integration test');
    }

    /**
     * @group integration
     */
    public function testCalculateForClassNeedsDB(): void
    {
        $this->markTestSkipped('Requires FA database connection - integration test');
    }

    /**
     * @group integration
     */
    public function testCalculateAllNeedsDB(): void
    {
        $this->markTestSkipped('Requires FA database connection - integration test');
    }

    /**
     * @group integration
     */
    public function testGetGLAccountsNeedsDB(): void
    {
        $this->markTestSkipped('Requires FA database connection - integration test');
    }

    /**
     * @group integration
     */
    public function testGetCategoriesNeedsDB(): void
    {
        $this->markTestSkipped('Requires FA database connection - integration test');
    }

    /**
     * @group integration
     */
    public function testGetClassesNeedsDB(): void
    {
        $this->markTestSkipped('Requires FA database connection - integration test');
    }

    /**
     * @group integration
     */
    public function testGetParentClassNeedsDB(): void
    {
        $this->markTestSkipped('Requires FA database connection - integration test');
    }
}
