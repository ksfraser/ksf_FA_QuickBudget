<?php
/**
 * InflationFactorManager
 *
 * Manages inflation factor configuration and resolution.
 * Supports FR-01 through FR-04.
 *
 * @see RTM.md - FR-01 to FR-04
 */
declare(strict_types=1);

require_once __DIR__ . '/CategoryDAO.php';

class InflationFactorManager
{
    /** @var float Default rate (1.0 = no inflation means 0% inflation) */
    private $globalRate = 1.0;

    /** @var array<string, float> Type rates indexed by chart_types.name (e.g., 'Utilities') */
    private $typeRates = [];

    /** @var array<string, float> Category rates indexed by chart_class.class_name (e.g., 'expenses') */
    private $categoryRates = [];

    /** @var array<string, float> GL-specific rates indexed by account code */
    private $glRates = [];

        /**
     * Load rates from database.
     *
     * @return void
     */
    public function loadFromDB(): void
    {
        global $db;

        $sql = "SELECT factor_type, reference_id, rate FROM " . TB_PREF . "ksf_quickbudget_factors
            WHERE factor_type IS NOT NULL";
        
        $logFile = dirname(__DIR__) . '/logs/debug.log';
        file_put_contents($logFile, date('Y-m-d H:i:s') . " loadFromDB: sql=" . $sql . "\n", FILE_APPEND);
        
        $result = db_query($sql);
        
        if (!$result) {
            error_log("loadFromDB: query failed, result=false");
            return;
        }
        
        $rows = db_num_rows($result);
        error_log("loadFromDB: found {$rows} rows");

        while ($row = db_fetch_assoc($result)) {
            $rate = (float)$row['rate'];
            $type = $row['factor_type'];
            $ref = (string)$row['reference_id'];
            if (empty($type)) {
                continue;
            }
            error_log("loadFromDB: type={$type}, ref={$ref}, rate={$rate}");
            switch ($type) {
                case 'global':
                    $this->globalRate = $rate;
                    break;
                case 'type':
                    $this->typeRates[strtolower($ref)] = $rate;
                    break;
                case 'category':
                    $this->categoryRates[strtolower($ref)] = $rate;
                    break;
                case 'gl':
                    $this->glRates[$ref] = $rate;
                    break;
            }
        }
    }

    /**
     * Set the global default inflation rate.
     *
     * @param float $rate Rate as percentage (e.g., 3.5 for 3.5%)
     * @return void
     */
    public function setGlobalRate(float $rate): void
    {
        $this->globalRate = $rate;
    }

    /**
     * Set a type-level inflation rate (chart_types.name).
     *
     * @param string $typeName chart_types.name
     * @param float $rate Rate as percentage
     * @return void
     */
    public function setTypeRate(string $typeName, float $rate): void
    {
        $this->typeRates[strtolower($typeName)] = $rate;
    }

    /**
     * Set a category-level inflation rate.
     *
     * @param string $category Category name (chart_class.class_name)
     * @param float $rate Rate as percentage
     * @return void
     */
    public function setCategoryRate(string $category, float $rate): void
    {
        $this->categoryRates[strtolower($category)] = $rate;
    }

    /**
     * Set a GL-account specific inflation rate.
     *
     * @param string $glAccount GL account code
     * @param float $rate Rate as percentage
     * @return void
     */
    public function setGLRate(string $glAccount, float $rate): void
    {
        $this->glRates[$glAccount] = $rate;
    }

    /**
     * Get all rates as array for session storage.
     *
     * @return array All rates indexed by type and reference
     */
    public function getAllRates(): array
    {
        return [
            'global' => $this->globalRate,
            'type' => $this->typeRates,
            'category' => $this->categoryRates,
            'gl' => $this->glRates,
        ];
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
     * Get category rate.
     *
     * @param string $category Category name
     * @return float|null Rate or null if not set
     */
    public function getCategoryRate(string $category): ?float
    {
        return $this->categoryRates[$category] ?? null;
    }

/**
      * Get effective rate for a GL account.
      * Resolves hierarchy: GL → Type → Parent → Category → Global.
      *
      * @param string $glAccount GL account code
      * @return float Effective inflation rate
      */
    public function getRateForAccount(string $glAccount): float
    {
        // GL-specific takes highest precedence
        if (isset($this->glRates[$glAccount])) {
            return $this->glRates[$glAccount];
        }

        // Get account's type, parent, and class for resolution
        $accountDetails = $this->getAccountDetails($glAccount);

        // Type-level override (chart_types.name) - case-insensitive
        if ($accountDetails && $this->typeRates) {
            $typeKey = strtolower($accountDetails['type_name']);
            if (isset($this->typeRates[$typeKey])) {
                return $this->typeRates[$typeKey];
            }
        }

        // Parent-level override (chart_types.class_id recursion)
        if ($accountDetails && $this->typeRates && $accountDetails['type_id']) {
            $parentRate = $this->getResolvedRateForType($accountDetails['type_id']);
            if ($parentRate !== null) {
                return $parentRate;
            }
        }

        // Category-level override (chart_class.class_name)
        if ($accountDetails && $this->categoryRates) {
            $classKey = $accountDetails['class_name'];
            if (isset($this->categoryRates[$classKey])) {
                return $this->categoryRates[$classKey];
            }
        }

        // Fall back to global default
        return $this->globalRate;
    }

    /**
     * Recursively resolve rate for a type by walking parent chain.
     *
     * @param int|null $typeId chart_types.id
     * @return float|null Rate or null if not found
     */
    private function getResolvedRateForType(?int $typeId): ?float
    {
        if (!$typeId || empty($this->typeRates)) {
            return null;
        }

        static $checkedTypes = [];
        if (isset($this->resolvedTypeCache[$typeId])) {
            return $this->resolvedTypeCache[$typeId];
        }
        if (in_array($typeId, $checkedTypes)) {
            return null; // Prevent infinite loop
        }
        $checkedTypes[] = $typeId;

        // Check if this type has a rate
        $typeName = $this->getTypeName($typeId);
        if ($typeName && isset($this->typeRates[strtolower($typeName)])) {
            $this->resolvedTypeCache[$typeId] = $this->typeRates[strtolower($typeName)];
            return $this->resolvedTypeCache[$typeId];
        }

        // Get parent and recurse
        $parentType = $this->getParentType($typeId);
        if ($parentType) {
            $rate = $this->getResolvedRateForType($parentType);
            if ($rate !== null) {
                $this->resolvedTypeCache[$typeId] = $rate;
                return $rate;
            }
        }

        return null;
    }

    /** @var array<int, float> Cache for resolved type rates */
    private $resolvedTypeCache = [];

    /**
     * Get parent type ID by chart_types.id.
     *
     * @param int $typeId chart_types.id
     * @return int|null Parent id or null
     */
    private function getParentType(int $typeId): ?int
    {
        global $db;

        if (!is_resource($db) && !($db instanceof mysqli)) {
            return null;
        }

        $result = db_query("SELECT class_id FROM " . TB_PREF . "chart_types
            WHERE id = " . (int)$typeId);
        if (!$result) {
            return null;
        }
        $row = db_fetch_assoc($result);

        return $row && $row['class_id'] ? (int)$row['class_id'] : null;
    }

    /**
     * Get type name by chart_types.id.
     *
     * @param int $typeId chart_types.id
     * @return string|null Type name or null
     */
    private function getTypeName(int $typeId): ?string
    {
        global $db;

        if (!is_resource($db) && !($db instanceof mysqli)) {
            return null;
        }

        $result = db_query("SELECT name FROM " . TB_PREF . "chart_types
            WHERE id = " . (int)$typeId);
        if (!$result) {
            return null;
        }
        $row = db_fetch_assoc($result);

        return $row ? $row['name'] : null;
    }

    /**
     * Get account details: type_id, parent_id, and class_name.
     * Returns null if DB unavailable or account not found.
     *
     * @param string $glAccount GL account code
     * @return array|null ['type_id' => ..., 'parent_id' => ..., 'class_name' => ...] or null
     */
    private function getAccountDetails(string $glAccount): ?array
    {
        global $db;
        // Skip DB check in test mode (db will be mocked)
        if (empty($db)) {
            return null;
        }

        $sql = "SELECT ct.id AS type_id, ct.name AS type_name, ct.class_id AS parent_id, cc.class_name
            FROM " . TB_PREF . "chart_master cm
            LEFT JOIN " . TB_PREF . "chart_types ct ON cm.account_type = ct.id
            LEFT JOIN " . TB_PREF . "chart_class cc ON ct.ctype = cc.cid
            WHERE cm.account_code = '" . addslashes($glAccount) . "'";
        $result = db_query($sql);
        if (!$result) {
            return null;
        }
        $row = db_fetch_assoc($result);

        if (!$row) {
            return null;
        }

        return [
            'type_id' => (int)($row['type_id'] ?? 0),
            'type_name' => $row['type_name'] ?? '',
            'parent_id' => (int)($row['parent_id'] ?? 0),
            'class_name' => strtolower($row['class_name'] ?? ''),
        ];
    }
}