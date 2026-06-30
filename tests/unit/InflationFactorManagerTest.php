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
        $this->manager->setCategoryRate('Expenses', 1.0500);

        $this->assertEquals(1.0500, $this->manager->getRateForAccount('6000'));
    }

    /**
     * FR-03: Configure group-level inflation factors (between category and global)
     * Tests that setGroupRate stores rates correctly.
     * @testdox setGroupRate stores group rate for retrieval
     */
    public function testSetGroupRateStoresGroupRate(): void
    {
        $this->manager->setGlobalRate(1.0200);
        $this->manager->setGroupRate('1', 1.0400);

        $all = $this->manager->getAllRates();
        $this->assertArrayHasKey('group', $all);
        $this->assertArrayHasKey('1', $all['group']);
        $this->assertEquals(1.0400, $all['group']['1']);
    }

    /**
     * FR-03: GL rate overrides category and group
     * @testdox GL-specific rate overrides category and group rates
     */
    public function testGLRateOverridesCategoryAndGroup(): void
    {
        $this->manager->setGlobalRate(1.0200);
        $this->manager->setGroupRate('1', 1.0400);
        $this->manager->setCategoryRate('Expenses', 1.0500);
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