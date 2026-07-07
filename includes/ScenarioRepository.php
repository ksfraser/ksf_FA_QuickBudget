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
     * Get all scenarios.
     */
    public function getAll(): array
    {
        global $db;

        $sql = "SELECT id, name, multiplier FROM " . TB_PREF . "ksf_quickbudget_scenarios";
        $result = db_query($sql);
        $scenarios = [];

        if ($result) {
            while ($row = db_fetch_assoc($result)) {
                $scenarios[] = new ScenarioDTO(
                    $row['name'],
                    (float)$row['multiplier'],
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

        $sql = "SELECT multiplier FROM " . TB_PREF . "ksf_quickbudget_scenarios WHERE id = " . (int)$scenarioId;
        $result = db_query($sql);
        if (!$result) {
            return 1.0;
        }
        $row = db_fetch_assoc($result);

        return $row ? (float)$row['multiplier'] : 1.0;
    }
}
