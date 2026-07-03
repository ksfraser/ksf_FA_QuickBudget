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
     * Get all inflation factors for a company.
     *
     * @param int $company Company ID
     * @return array<InflationFactorDTO>
     */
    public function getAllForCompany(int $company): array
    {
        global $db;

        $result = db_query("SELECT * FROM " . TB_PREF . "ksf_quickbudget_factors WHERE company=" . (int)$company);
        if (!$result) {
            return [];
        }
        $factors = [];

        while ($row = db_fetch_assoc($result)) {
            $factors[] = new InflationFactorDTO(
                $row['factor_type'],
                $row['reference_id'],
                (float)$row['rate'],
                (int)$row['company']
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

        $sql = "INSERT INTO " . TB_PREF . "ksf_quickbudget_factors
            (factor_type, reference_id, rate, company)
            VALUES (" .
            "'" . $factor->getType() . "', " .
            "'" . mysqli_real_escape_string($db, $factor->getReferenceId()) . "', " .
            (float)$factor->getRate() . ", " .
            (int)$factor->getCompany() .
            ") ON DUPLICATE KEY UPDATE rate=" . (float)$factor->getRate();

        return db_query($sql) !== false;
    }

    /**
     * Import factors from CSV data.
     *
     * @param array $csvRows Array of ['type' => ..., 'reference' => ..., 'rate' => ...]
     * @param int $company Company ID
     * @return int Number of factors imported
     */
    public function importFromCsv(array $csvRows, int $company): int
    {
        $count = 0;
        foreach ($csvRows as $row) {
            $factor = new InflationFactorDTO(
                $row['type'],
                $row['reference'],
                (float)$row['rate'],
                $company
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
     * @param int $company Company ID
     * @return array Array of ['type', 'reference', 'rate'] rows
     */
    public function exportToCsv(int $company): array
    {
        $factors = $this->getAllForCompany($company);
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
