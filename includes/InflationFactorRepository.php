<?php
/**
 * InflationFactorRepository
 *
 * Repository for inflation factor persistence.
 * Supports FR-04 and FR-05.
 */
declare(strict_types=1);

final class InflationFactorRepository
{
    /**
     * Get all inflation factors.
     *
     * @return array<InflationFactorDTO>
     */
    public function getAll(): array
    {
        global $db;

        $result = db_query("SELECT factor_type, reference_id, rate FROM " . TB_PREF . "ksf_quickbudget_factors WHERE factor_type IS NOT NULL ");
        if (!$result) {
            return [];
        }
        $factors = [];

        while ($row = db_fetch_assoc($result)) {
            $factors[] = new InflationFactorDTO(
                $row['factor_type'],
                $row['reference_id'],
                (float)$row['rate']
            );
        }

        return $factors;
    }

    /**
     * Save an inflation factor.
     * Uses company=0 for backward compatibility with old schema.
     *
     * @param InflationFactorDTO $factor
     * @return bool Success
     */
    public function save(InflationFactorDTO $factor): bool
    {
        global $db;

        $escapedRef = mysqli_real_escape_string($db, $factor->getReferenceId());
        $sql = "INSERT INTO " . TB_PREF . "ksf_quickbudget_factors
            (factor_type, reference_id, rate, company)
            VALUES (" .
            "'" . $factor->getType() . "', " .
            "'" . $escapedRef . "', " .
            (float)$factor->getRate() . ", 0" .
            ") ON DUPLICATE KEY UPDATE rate=" . (float)$factor->getRate();

        error_log("save: SQL={$sql}");
        
        $logFile = dirname(__DIR__) . '/logs/debug.log';
        file_put_contents($logFile, date('Y-m-d H:i:s') . " save: SQL={$sql}\n", FILE_APPEND);
        
        $result = db_query($sql, null);
        if ($result === false) {
            error_log("InflationFactorRepository::save failed SQL: " . $sql . " - DB error: " . ($db && $db->error ? $db->error : 'unknown'));
            return false;
        }
        return true;
    }

    /**
     * Import factors from CSV data.
     *
     * @param array $csvRows Array of ['type' => ..., 'reference' => ..., 'rate' => ...]
     * @return int Number of factors imported
     */
    public function importFromCsv(array $csvRows): int
    {
        $count = 0;
        foreach ($csvRows as $row) {
            $factor = new InflationFactorDTO(
                $row['type'],
                $row['reference'],
                (float)$row['rate']
            );
            if ($this->save($factor)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Export all factors as CSV-formatted array.
     *
     * @return array Array of ['type', 'reference', 'rate'] rows
     */
    public function exportToCsv(): array
    {
        $factors = $this->getAll();
        $rows = [];

        foreach ($factors as $factor) {
            $rows[] = [
                'type' => $factor->getType(),
                'reference' => $factor->getReferenceId(),
                'rate' => $factor->getRate()
            ];
        }

        return $rows;
    }
}
