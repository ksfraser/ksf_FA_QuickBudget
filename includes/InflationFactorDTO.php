<?php
/**
 * InflationFactorDTO
 *
 * Data transfer object for inflation factor configuration.
 * Supports FR-01, FR-02, FR-03, FR-04.
 */
declare(strict_types=1);

final class InflationFactorDTO
{
    /** @var int */
    private $id;

    /** @var string One of: 'global', 'category', 'gl' */
    private $type;

    /** @var string GL account code or category name */
    private $referenceId;

    /** @var float Rate multiplier (e.g., 1.0350 for 3.5%) */
    private $rate;

    /** @var int Company ID */
    private $company;

    public function __construct(
        string $type,
        string $referenceId,
        float $rate,
        int $company = 0
    ) {
        $this->type = $type;
        $this->referenceId = $referenceId;
        $this->rate = $rate;
        $this->company = $company;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getReferenceId(): string
    {
        return $this->referenceId;
    }

    public function getRate(): float
    {
        return $this->rate;
    }

    public function getCompany(): int
    {
        return $this->company;
    }
}
