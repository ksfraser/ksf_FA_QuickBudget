<?php
/**
 * BudgetEntryDTO
 *
 * Data transfer object for a single budget entry.
 * Supports FR-09, FR-15, FR-25.
 */
declare(strict_types=1);

class BudgetEntryDTO
{
    /** @var string GL account code */
    private $glAccount;

    /** @var int Year */
    private $year;

    /** @var array<int, float> Monthly amounts indexed 1-12 */
    private $monthlyAmounts;

    /** @var string Scenario name */
    private $scenario;

    public function __construct(
        string $glAccount,
        int $year,
        array $monthlyAmounts = [],
        string $scenario = 'baseline'
    ) {
        $this->glAccount = $glAccount;
        $this->year = $year;
        $this->monthlyAmounts = $monthlyAmounts;
        $this->scenario = $scenario;
    }

    public function getGLAccount(): string
    {
        return $this->glAccount;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    /**
     * @return array<int, float>
     */
    public function getMonthlyAmounts(): array
    {
        return $this->monthlyAmounts;
    }

    public function getScenario(): string
    {
        return $this->scenario;
    }

    public function getMonthlyAmount(int $month): float
    {
        return $this->monthlyAmounts[$month] ?? 0.0;
    }
}
