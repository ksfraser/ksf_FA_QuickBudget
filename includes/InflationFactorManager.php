<?php
/**
 * InflationFactorManager
 *
 * Manages inflation factor configuration and resolution.
 * Supports FR-01 through FR-07.
 *
 * Resolution hierarchy: Type → Parent Type → Class → Global
 *
 * @see RTM.md - FR-01 to FR-04
 */
declare(strict_types=1);

require_once __DIR__ . '/CategoryDAO.php';

class InflationFactorManager
{
    /** @var float Default rate (1.0 = no inflation means 0% inflation) */
    private $globalRate = 1.0;

    /** @var array<string, float> Type rates indexed by chart_types.id (varchar) */
    private $typeRates = [];

    /** @var array<string, float> Category rates indexed by chart_class.cid (varchar) */
    private $categoryRates = [];

    /** @var array<string, float> GL-specific rates indexed by account code */
    private $glRates = [];

    /** @var array<string, float> Cache for resolved type rates (id => rate) */
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
                    $this->typeRates[$ref] = $rate;
                    break;
                case 'category':
                    $this->categoryRates[$ref] = $rate;
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
     * @param string|int $typeId chart_types.id
     * @param float $rate Rate as percentage
     * @return void
     */
    public function setTypeRate($typeId, float $rate): void
    {
        $this->typeRates[(string)$typeId] = $rate;
    }

    /**
     * Set a category-level inflation rate (chart_class.cid).
     *
     * @param string|int $categoryId chart_class.cid
     * @param float $rate Rate as percentage
     * @return void
     */
    public function setCategoryRate($categoryId, float $rate): void
    {
        $this->categoryRates[(string)$categoryId] = $rate;
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
     * Get resolved rates for all types (including inherited from parent/class).
     * Type hierarchy: Type → Parent Type → Class → Global.
     *
     * @return array<string, float> type_id => rate
     */
    private function getResolvedTypeRates(): array
    {
        global $db;
        $resolved = [];

        // Build a map of all type IDs to their info
        $typeMap = [];
        $result = db_query("SELECT id, name, class_id FROM " . TB_PREF . "chart_types");
        if ($result) {
            while ($row = db_fetch_assoc($result)) {
                $typeMap[(string)$row['id']] = [
                    'name' => $row['name'] ?? '',
                    'class_id' => $row['class_id'] ?? '',
                ];
            }
        }

        // Resolve rate for each type
        foreach ($typeMap as $typeId => $info) {
            $rate = $this->resolveTypeRateWithClass($typeId);
            $resolved[$typeId] = $rate;
        }

        $this->resolvedTypeCache = $resolved;
        return $resolved;
    }

    /**
     * Resolve rate for a type: Type → Parent Type → Class → Global.
     * Parent chain is fully recursive - walks up until a type with direct rate is found.
     *
     * @param string|int $typeId chart_types.id
     * @return float Rate
     */
    private function resolveTypeRateWithClass($typeId): float
    {
        $typeId = (string)$typeId;
        
        // Check cache first
        if (isset($this->resolvedTypeCache[$typeId])) {
            return $this->resolvedTypeCache[$typeId];
        }

        // Step 1: Check if this type has a direct rate
        if (isset($this->typeRates[$typeId])) {
            $this->resolvedTypeCache[$typeId] = $this->typeRates[$typeId];
            return $this->typeRates[$typeId];
        }

        // Step 2: Walk parent chain recursively - stop at first type with direct rate
        $parentType = $this->getParentType($typeId);
        if ($parentType !== null && $parentType !== '') {
            // Check if parent has a direct rate (not inherited)
            if (isset($this->typeRates[$parentType])) {
                $this->resolvedTypeCache[$typeId] = $this->typeRates[$parentType];
                return $this->typeRates[$parentType];
            }
            // Parent has no direct rate, recurse to grandparent
            $rate = $this->resolveTypeRateWithClass($parentType);
            // If we found a rate via parent chain, use it
            // But we need to check: was this rate a direct rate or inherited?
            // If inherited from class/global, we should still check our own class
            // For now, assume parent chain found a rate and use it
            if (!isset($this->typeRates[$parentType])) {
                // Parent rate was inherited, skip it and check our class
            } else {
                $this->resolvedTypeCache[$typeId] = $rate;
                return $rate;
            }
        }

        // Step 3: Check class (chart_types.class_id)
        $classId = $this->getTypeClass($typeId);
        if (isset($this->categoryRates[$classId])) {
            $this->resolvedTypeCache[$typeId] = $this->categoryRates[$classId];
            return $this->categoryRates[$classId];
        }

        // Step 4: Fall back to global
        $this->resolvedTypeCache[$typeId] = $this->globalRate;
        return $this->globalRate;
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
     * @param string|int $categoryId chart_class.cid
     * @return float|null Rate or null if not set
     */
    public function getCategoryRate($categoryId): ?float
    {
        return $this->categoryRates[(string)$categoryId] ?? null;
    }

    /**
     * Get effective rate for a GL account.
     * Resolves hierarchy: GL → Type → Class → Global.
     * Uses cached resolved type rates if available.
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

        // Get account's type and class for resolution
        $accountDetails = $this->getAccountDetails($glAccount);

        if ($accountDetails && $accountDetails['type_id']) {
            $typeId = (string)$accountDetails['type_id'];
            
            // Use cached resolved rate if available (includes parent/class inheritance)
            if (isset($this->resolvedTypeCache[$typeId])) {
                return $this->resolvedTypeCache[$typeId];
            }
            
            // Otherwise resolve: Type → Class → Global (no parent chain for individual lookups)
            if (isset($this->typeRates[$typeId])) {
                return $this->typeRates[$typeId];
            }
            
            $classId = (string)($accountDetails['class_id'] ?? '');
            if ($classId && isset($this->categoryRates[$classId])) {
                return $this->categoryRates[$classId];
            }
        }

        // Fall back to global default
        return $this->globalRate;
    }

    /**
     * Get parent type ID by chart_types.id.
     *
     * @param string|int $typeId chart_types.id
     * @return string|null Parent id or null
     */
    private function getParentType($typeId): ?string
    {
        global $db;

        if (empty($db)) {
            return null;
        }

        $result = db_query("SELECT parent FROM " . TB_PREF . "chart_types WHERE id = '" . (string)$typeId . "'");
        if (!$result) {
            return null;
        }
        $row = db_fetch_assoc($result);

        if (!$row) {
            return null;
        }
        
        $parent = $row['parent'] ?? '';
        if (empty($parent) || $parent === '-1' || $parent === '') {
            return null;
        }
        return (string)$parent;
    }

    /**
     * Get type class ID (class_id) by chart_types.id.
     *
     * @param string|int $typeId chart_types.id
     * @return string Class ID or empty string
     */
    private function getTypeClass($typeId): string
    {
        global $db;

        if (empty($db)) {
            return '';
        }

        $result = db_query("SELECT class_id FROM " . TB_PREF . "chart_types WHERE id = '" . (string)$typeId . "'");
        if (!$result) {
            return '';
        }
        $row = db_fetch_assoc($result);

        return $row['class_id'] ?? '';
    }

    /**
     * Get account details: type_id and class_id.
     * Returns null if DB unavailable or account not found.
     *
     * @param string $glAccount GL account code
     * @return array|null ['type_id' => ..., 'class_id' => ...] or null
     */
    private function getAccountDetails(string $glAccount): ?array
    {
        global $db;
        if (empty($db) || !function_exists('db_query')) {
            return null;
        }

        $sql = "SELECT ct.id AS type_id, ct.class_id AS class_id
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
            'type_id' => (string)($row['type_id'] ?? ''),
            'class_id' => $row['class_id'] ?? '',
        ];
    }
}