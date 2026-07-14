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

    /** @var array<int, float> Type rates indexed by chart_types.id */
    private $typeRates = [];

    /** @var array<int, float> Category rates indexed by chart_class.cid */
    private $categoryRates = [];

    /** @var array<string, float> GL-specific rates indexed by account code */
    private $glRates = [];

    /** @var array<int, float> Cache for resolved type rates */
    private $resolvedTypeCache = [];

    /**
     * Load rates from database.
     *
     * @return void
     */
    public function loadFromDB(): void
    {
        global $db;

        $sql = "SELECT factor_type, reference_id, rate FROM " . TB_PREF . "ksf_quickbudget_factors WHERE factor_type IS NOT NULL";
        
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
                    $this->typeRates[(int)$ref] = $rate;
                    break;
                case 'category':
                    $this->categoryRates[(int)$ref] = $rate;
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
     * Set a type-level inflation rate (chart_types.id).
     *
     * @param int $typeId chart_types.id
     * @param float $rate Rate as percentage
     * @return void
     */
    public function setTypeRate(int $typeId, float $rate): void
    {
        $this->typeRates[$typeId] = $rate;
    }

    /**
     * Set a category-level inflation rate (chart_class.cid).
     *
     * @param int $categoryId chart_class.cid
     * @param float $rate Rate as percentage
     * @return void
     */
    public function setCategoryRate(int $categoryId, float $rate): void
    {
        $this->categoryRates[$categoryId] = $rate;
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
     * Includes resolved rates for type hierarchy.
     *
     * @return array All rates indexed by type and reference
     */
    public function getAllRates(): array
    {
        return [
            'global' => $this->globalRate,
            'type' => $this->typeRates,
            'resolved_types' => $this->getResolvedTypeRates(),
            'category' => $this->categoryRates,
            'gl' => $this->glRates,
        ];
    }

    /**
     * Get resolved rates for all types (type → class → parent type → global).
     *
     * @return array<string, float> lowercase_name => rate
     */
    private function getResolvedTypeRates(): array
    {
        global $db;
        $resolved = [];

        // Build a map of all type IDs to their names and class IDs
        $typeMap = [];
        $result = db_query("SELECT id, name, ctype FROM " . TB_PREF . "chart_types");
        if ($result) {
            while ($row = db_fetch_assoc($result)) {
                $typeMap[(int)$row['id']] = ['name' => strtolower($row['name']), 'ctype' => (int)($row['ctype'] ?? 0)];
            }
        }

        // Resolve rate for each type (global rate is fallback)
        foreach ($typeMap as $typeId => $info) {
            $checked = []; // Reset checked array for each type
            $rate = $this->getResolvedRateForType($typeId, $info['ctype'], $checked);
            // Always add - use global as fallback
            $resolved[$info['name']] = $rate ?? $this->globalRate;
        }

        return $resolved;
    }

    /**
     * Resolve rate for a type: type → class → parent type → global.
     *
     * @param int|null $typeId chart_types.id
     * @param int $classId chart_types.ctype (chart_class.cid)
     * @param array<int,bool> $checked Types already checked (for recursion prevention)
     * @return float|null Rate or null if not found
     */
    private function getResolvedRateForType(?int $typeId, int $classId, array &$checked = []): ?float
    {
        // Check if this type has a rate (keyed by ID now)
        if ($typeId && isset($this->typeRates[$typeId])) {
            return $this->typeRates[$typeId];
        }

        // Check class rate (ctype → chart_class.cid)
        if ($classId && isset($this->categoryRates[$classId])) {
            return $this->categoryRates[$classId];
        }

        // Get parent type and recurse (class_id → chart_types.id)
        if ($typeId && isset($this->resolvedTypeCache[$typeId])) {
            return $this->resolvedTypeCache[$typeId];
        }
        if ($typeId && isset($checked[$typeId])) {
            return null; // Prevent infinite loop
        }
        if ($typeId) {
            $checked[$typeId] = true;
        }

        // Get parent type chain
        $parentType = $this->getParentType($typeId);
        if ($parentType !== null) {
            // Get parent type's class ID
            $parentClassId = $this->getTypeClass($parentType);
            $rate = $this->getResolvedRateForType($parentType, $parentClassId, $checked);
            if ($rate !== null) {
                if ($typeId) {
                    $this->resolvedTypeCache[$typeId] = $rate;
                }
                return $rate;
            }
        }

        return null;
    }

    /**
     * Get type class ID (ctype) by chart_types.id.
     *
     * @param int $typeId chart_types.id
     * @return int Class ID or 0
     */
    private function getTypeClass(int $typeId): int
    {
        global $db;

        if (!is_resource($db) && !($db instanceof mysqli)) {
            return 0;
        }

        $result = db_query("SELECT ctype FROM " . TB_PREF . "chart_types WHERE id = " . (int)$typeId);
        if (!$result) {
            return 0;
        }
        $row = db_fetch_assoc($result);

        return (int)($row['ctype'] ?? 0);
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
     * @param int $categoryId chart_class.cid
     * @return float|null Rate or null if not set
     */
    public function getCategoryRate(int $categoryId): ?float
    {
        return $this->categoryRates[$categoryId] ?? null;
    }

    /**
     * Get effective rate for a GL account.
     * Resolves hierarchy: GL → Type (incl. class/parent) → Category → Global.
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

        // Type-level override (chart_types.id) - includes class/parent chain resolution
        if ($accountDetails && $accountDetails['type_id']) {
            $checked = [];
            $typeRate = $this->getResolvedRateForType($accountDetails['type_id'], $accountDetails['class_id'], $checked);
            if ($typeRate !== null) {
                return $typeRate;
            }
        }

        // Category-level override (chart_class.cid)
        if ($accountDetails && $this->categoryRates) {
            $classId = $accountDetails['class_id'] ?? 0;
            if (isset($this->categoryRates[$classId])) {
                return $this->categoryRates[$classId];
            }
        }

        // Fall back to global default
        return $this->globalRate;
    }

    /**
     * Get parent type ID by chart_types.id.
     *
     * @param int|null $typeId chart_types.id
     * @return int|null Parent id or null
     */
    private function getParentType(?int $typeId): ?int
    {
        global $db;

        if (!$typeId || (!is_resource($db) && !($db instanceof mysqli))) {
            return null;
        }

        $result = db_query("SELECT class_id FROM " . TB_PREF . "chart_types WHERE id = " . (int)$typeId);
        if (!$result) {
            return null;
        }
        $row = db_fetch_assoc($result);

        return $row && $row['class_id'] ? (int)$row['class_id'] : null;
    }

    /**
     * Get account details: type_id, parent_id, and class_id.
     * Returns null if DB unavailable or account not found.
     *
     * @param string $glAccount GL account code
     * @return array|null ['type_id' => ..., 'parent_id' => ..., 'class_id' => ...] or null
     */
    private function getAccountDetails(string $glAccount): ?array
    {
        global $db;
        // Skip DB check in test mode (db will be mocked)
        if (empty($db)) {
            return null;
        }

        $sql = "SELECT ct.id AS type_id, ct.name AS type_name, ct.class_id AS parent_id, ct.ctype AS class_id
            FROM " . TB_PREF . "chart_master cm
            LEFT JOIN " . TB_PREF . "chart_types ct ON cm.account_type = ct.id
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
            'class_id' => (int)($row['class_id'] ?? 0),
        ];
    }
}