<?php
/**
 * ScenarioRepository
 *
 * Repository for budget scenario data.
 * Supports FR-13.
 */
declare(strict_types=1);

final class ScenarioRepository
{
    /**
     * Get all scenarios for a company.
     */
    public function getAllForCompany(int $company = 0): array
    {
        global $db;

        $result = db_query("SELECT id, name, multiplier FROM " . TB_PREF . "ksf_quickbudget_scenarios WHERE company = " . (int)$company);
        $scenarios = [];

        if ($result) {
            while ($row = db_fetch_assoc($result)) {
                $scenarios[] = new ScenarioDTO(
                    $row['name'],
                    (float)$row['multiplier'],
                    '',
                    (int)$row['company'],
                    (int)$row['id']
                );
            }
        }

        return $scenarios;
    }

    /**
     * Get scenario multiplier by ID.
     */
    public function getMultiplierById(int $scenarioId): float
    {
        global $db;

        $result = db_query("SELECT multiplier FROM " . TB_PREF . "ksf_quickbudget_scenarios WHERE id = " . (int)$scenarioId, "Cannot read scenario");
        $row = db_fetch_assoc($result);

        return $row ? (float)$row['multiplier'] : 1.0;
    }
}