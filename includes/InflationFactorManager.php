<?php
/**
 * InflationFactorManager
 *
 * Manages inflation factor configuration and resolution.
 * Supports FR-01 through FR-06.
 *
 * @see RTM.md - FR-01 to FR-06
 */
declare(strict_types=1);

final class InflationFactorManager
{
    /** @var float Default rate (1.0 = no inflation) */
    private $globalRate = 1.0;

    /** @var array<string, float> Category rates indexed by category name */
    private $categoryRates = [];

    /** @var array<string, float> GL-specific rates indexed by account code */
    private $glRates = [];

    /**
     * Set the global default inflation rate.
     *
     * @param float $rate Rate multiplier (e.g., 1.0350)
     * @return void
     */
    public function setGlobalRate(float $rate): void
    {
        $this->globalRate = $rate;
    }

    /**
     * Set a category-level inflation rate.
     *
     * @param string $category Category name (e.g., 'Expenses')
     * @param float $rate Rate multiplier
     * @return void
     */
    public function setCategoryRate(string $category, float $rate): void
    {
        $this->categoryRates[$category] = $rate;
    }

    /**
     * Set a GL-account specific inflation rate.
     *
     * @param string $glAccount GL account code
     * @param float $rate Rate multiplier
     * @return void
     */
    public function setGLRate(string $glAccount, float $rate): void
    {
        $this->glRates[$glAccount] = $rate;
    }

    /**
     * Get the global default rate.
     * FR-01 support.
     *
     * @return float Default rate
     */
    public function getDefaultRate(): float
    {
        return $this->globalRate;
    }

    /**
     * Get effective rate for a GL account.
     * Resolves hierarchy: GL → Category → Global.
     *
     * @param string $glAccount GL account code
     * @return float Effective inflation rate
     */
    public function getRateForAccount(string $glAccount): float
    {
        // FR-03: GL-specific takes highest precedence
        if (isset($this->glRates[$glAccount])) {
            return $this->glRates[$glAccount];
        }

        // FR-02: Category-level override (default category for now)
        // FA chart_master typically has account_type or similar field
        // For simplicity, assume 'Expenses' category defaults
        if (isset($this->categoryRates['Expenses'])) {
            return $this->categoryRates['Expenses'];
        }

        // FR-01: Fall back to global default
        return $this->globalRate;
    }
}