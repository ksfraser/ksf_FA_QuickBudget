<?php
/**
 * InflationFactorRepository
 *
 * Master repository for inflation factor persistence.
 * Supports FR-04 and FR-05.
 */
declare(strict_types=1);

/**
 * Safe escape wrapper for DB strings.
 */
function safe_escape($db, string $str): string
{
    if ($db instanceof mysqli) {
        return mysqli_real_escape_string($db, $str);
    }
    return addslashes($str);
}

require_once __DIR__ . '/InflationFactorDTO.php';

final class InflationFactorRepository
{
    /**
     * Get all inflation factors, optionally filtered by type.
     *
     * @param string|null $factorType One of FactorTypes constants, or null for all
     * @return array<InflationFactorDTO>
     */
    public function getAll(?string $factorType = null): array
    {
        global $db;

        $sql = "SELECT factor_type, reference_id, rate FROM " . TB_PREF . "ksf_quickbudget_factors";
        if ($factorType !== null) {
            $sql .= " WHERE factor_type = '" . safe_escape($db, $factorType) . "'";
        }
        $sql .= " ORDER BY factor_type, reference_id";

        $result = db_query($sql);
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
     *
     * @param InflationFactorDTO $factor
     * @return bool Success
     */
    public function save(InflationFactorDTO $factor): bool
    {
        global $db;

        $escapedRef = safe_escape($db, $factor->getReferenceId());
        $sql = "INSERT INTO " . TB_PREF . "ksf_quickbudget_factors
            (factor_type, reference_id, rate)
            VALUES (" .
            "'" . $factor->getType() . "', " .
            "'" . $escapedRef . "', " .
            (float)$factor->getRate() .
            ") ON DUPLICATE KEY UPDATE rate=" . (float)$factor->getRate();

        $logFile = dirname(__DIR__) . '/logs/debug.log';
        file_put_contents($logFile, date('Y-m-d H:i:s') . " InflationFactorRepository::save SQL={$sql}\n", FILE_APPEND);

        $result = db_query($sql);
        if ($result === false) {
            $dbError = $db && method_exists($db, 'error') ? $db->error : 'unknown';
            error_log("InflationFactorRepository::save failed: $sql - $dbError");
            file_put_contents($logFile, date('Y-m-d H:i:s') . " save FAILED: {$dbError}\n", FILE_APPEND);
            return false;
        }
        // INSERT/UPDATE may return true, null, or a result - all indicate success
        $insertId = db_insert_id();
        file_put_contents($logFile, date('Y-m-d H:i:s') . " save SUCCESS: id={$insertId}\n", FILE_APPEND);
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