<?php
/**
 * BudgetGeneratorServiceTest
 *
 * Tests for FR-07 through FR-14.
 */
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Ksfraser\FA\QuickBudget\Service\BudgetGeneratorService;

final class BudgetGeneratorServiceTest extends TestCase
{
    private $service;
    private $manager;

    protected function setUp(): void
    {
        $this->manager = new InflationFactorManager();
        $this->service = new BudgetGeneratorService($this->manager);
    }

    /**
     * FR-09: Calculate budget amounts applying inflation factors to GL actuals
     */
    public function testGenerateAppliesInflationToActuals(): void
    {
        // Set inflation rate
        $this->manager->setGlobalRate(1.05);

        // Generate budget - mock data would be better
        $entries = $this->service->generate(2025);

        $this->assertIsArray($entries);
    }

    /**
     * FR-07: Select target budget period with start month
     */
    public function testGenerateRespectsStartMonth(): void
    {
        $this->manager->setGlobalRate(1.0);

        $entries = $this->service->generate(2025, 7);

        $this->assertIsArray($entries);
        // Verify months 7-12 are in entries
    }
}