<?php
/**
 * ScenarioDTO
 *
 * Data transfer object for budget scenarios.
 * Supports FR-13.
 */
declare(strict_types=1);

final class ScenarioDTO
{
    /** @var int */
    private $id;

    /** @var string */
    private $name;

    /** @var float Multiplier (0.9 = optimistic, 1.0 = baseline, 1.1 = pessimistic) */
    private $multiplier;

    /** @var string Description of scenario */
    private $description;

    /** @var int Company ID */
    private $company;

    public function __construct(
        string $name,
        float $multiplier,
        string $description = '',
        int $company = 0
    ) {
        $this->name = $name;
        $this->multiplier = $multiplier;
        $this->description = $description;
        $this->company = $company;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMultiplier(): float
    {
        return $this->multiplier;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getCompany(): int
    {
        return $this->company;
    }
}