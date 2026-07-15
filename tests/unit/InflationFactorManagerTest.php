<?php
/**
 * InflationFactorManagerTest
 *
 * Tests for FR-01 through FR-06: Inflation factor configuration.
 *
 * @testdox InflationFactorManager
 */
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class InflationFactorManagerTest extends TestCase
{
    private $manager;

    protected function setUp(): void
    {
        $this->manager = new InflationFactorManager();
        $this->manager->invalidateResolvedTypeCache(); // Clear cache for isolated tests
    }

    /**
     * FR-01: Configure global inflation factor as default percentage
     * @testdox getDefaultRate returns configured global rate
     */
    public function testGetDefaultRateReturnsConfiguredGlobalRate(): void
    {
        $this->manager->setGlobalRate(1.0350);
        $this->assertEquals(1.0350, $this->manager->getDefaultRate());
    }

    /**
     * FR-02: Configure category-level inflation factors (override global)
     * @testdox getRate returns category rate when no GL-specific rate exists
     */
    public function testGetRateReturnsCategoryRateWhenNoGLSpecific(): void
    {
        $this->manager->setGlobalRate(1.0200);
        $this->manager->setCategoryRate(1, 1.0500);

        $this->assertEquals(1.0500, $this->manager->getRateForAccount('6000'));
    }

    /**
     * FR-03: Configure type-level inflation factors
     * Tests that setTypeRate stores rates correctly by ID.
     * @testdox setTypeRate stores type rate by ID for retrieval
     */
    public function testSetTypeRateStoresTypeRate(): void
    {
        $this->manager->setGlobalRate(1.0200);
        $this->manager->setTypeRate(1, 1.0400);

        $all = $this->manager->getAllRates();
        $this->assertArrayHasKey('type', $all);
        $this->assertArrayHasKey('1', $all['type']);
    }

    /**
     * FR-03: GL rate overrides type and category
     * @testdox GL-specific rate overrides type and category rates
     */
    public function testGLRateOverridesTypeAndCategory(): void
    {
        $this->manager->setGlobalRate(1.0200);
        $this->manager->setTypeRate(1, 1.0400);
        $this->manager->setCategoryRate(1, 1.0500);
        $this->manager->setGLRate('6000', 1.1000);

        $this->assertEquals(1.1000, $this->manager->getRateForAccount('6000'));
    }

    /**
     * FR-01: Default rate when no factors configured
     * @testdox getDefaultRate returns 1.0 when not configured
     */
    public function testGetDefaultRateReturnsOneWhenNotConfigured(): void
    {
        $this->assertEquals(1.0, $this->manager->getDefaultRate());
    }

    /**
     * FR-04: Configure GL-specific inflation factors (highest precedence)
     * Tests that setGLRate stores rates correctly.
     * @testdox setGLRate stores GL rate for retrieval
     */
    public function testSetGLRateStoresGLRate(): void
    {
        $this->manager->setGlobalRate(1.0200);
        $this->manager->setGLRate('6000', 1.1000);

        $all = $this->manager->getAllRates();
        $this->assertArrayHasKey('gl', $all);
        $this->assertArrayHasKey('6000', $all['gl']);
        $this->assertEquals(1.1000, $all['gl']['6000']);
    }
}