<?php
/**
 * InflationFactorDTO
 *
 * Data transfer object for inflation factor configuration.
 * Supports FR-01, FR-02, FR-03, FR-04.
 */
declare(strict_types=1);

require_once __DIR__ . '/FactorTypes.php';

final class InflationFactorDTO
{
    private const ALLOWED_TYPES = [FactorTypes::GLOBAL, FactorTypes::CATEGORY, FactorTypes::GL, FactorTypes::TYPE];

    /** @var int */
    private $id;

    /** @var string One of: 'global', 'category', 'gl', 'type' */
    private $type;

    /** @var string GL account code or category name */
    private $referenceId;

    /** @var float Rate multiplier (e.g., 1.0350 for 3.5%) */
    private $rate;

    public function __construct(
        string $type,
        string $referenceId,
        float $rate
    ) {
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException("Invalid factor_type: $type");
        }
        $this->type = $type;
        $this->referenceId = $referenceId;
        $this->rate = $rate;
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
}
