<?php
/**
 * BudgetController
 *
 * HTTP request handler for budget operations.
 * Supports FR-07 through FR-28.
 */
declare(strict_types=1);

namespace Ksfraser\FA\QuickBudget\Controller;

use Ksfraser\FA\QuickBudget\Service\BudgetGeneratorService;
use InflationFactorManager;

final class BudgetController
{
    /** @var BudgetGeneratorService */
    private $budgetService;

    /** @var InflationFactorManager */
    private $factorManager;

    public function __construct()
    {
        $this->factorManager = new InflationFactorManager();
        $this->budgetService = new BudgetGeneratorService($this->factorManager);
    }

    /**
     * Handle create action.
     * FR-09: Calculate budget amounts applying inflation factors.
     *
     * @return array Response data
     */
    public function create(array $data): array
    {
        $targetYear = (int)($data['target_year'] ?? date('Y') + 1);
        $startMonth = (int)($data['start_month'] ?? 1);
        $scenarioId = (int)($data['scenario_id'] ?? 0);

        $entries = $this->budgetService->generate($targetYear, $startMonth);

        return [
            'success' => true,
            'entries' => $entries,
            'year' => $targetYear
        ];
    }

    /**
     * Handle compare action.
     * FR-15: Display side-by-side comparison.
     *
     * @return array Comparison data
     */
    public function compare(array $data): array
    {
        $year = (int)($data['year'] ?? date('Y'));
        $startMonth = (int)($data['start_month'] ?? 1);
        $endMonth = (int)($data['end_month'] ?? 12);

        // TODO: Implement comparison logic

        return [
            'actuals' => [],
            'budget' => [],
            'variance' => []
        ];
    }

    /**
     * Handle export action.
     * FR-25: Export budget data to CSV.
     *
     * @return array Export data
     */
    public function export(array $data): array
    {
        $year = (int)($data['year'] ?? date('Y'));

        // TODO: Implement export logic

        return [];
    }
}