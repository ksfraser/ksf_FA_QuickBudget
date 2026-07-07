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

    public function __construct(
        string $name,
        float $multiplier,
        int $id = 0
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->multiplier = $multiplier;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMultiplier(): float
    {
        return $this->multiplier;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }
}
