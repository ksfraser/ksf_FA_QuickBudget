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
    /** @var string Cache file path for resolved type rates */
    private $cacheFile;

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
     * Constructor - initializes cache file path.
     */
    public function __construct()
    {
        $this->cacheFile = dirname(__DIR__) . '/cache/resolved_type_rates.cache';
    }

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
      * Load resolved type rates from cache file.
      * Returns null if cache is corrupt or missing - caller should rebuild.
      *
      * @return array<string, float>|null
      */
    private function loadResolvedTypeCache(): ?array
    {
        if (!file_exists($this->cacheFile)) {
            return null;
        }

        $content = file_get_contents($this->cacheFile);
        if ($content === false) {
            return null;
        }

        $cached = json_decode($content, true);
        if (!is_array($cached)) {
            return null;
        }

        return $cached;
    }

    /**
      * Save resolved type rates to cache file.
      *
      * @param array<string, float> $resolved Rates to cache
      * @return void
      */
    private function saveResolvedTypeCache(array $resolved): void
    {
        $json = json_encode($resolved);
        if ($json !== false) {
            $dir = dirname($this->cacheFile);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            file_put_contents($this->cacheFile, $json);
        }
    }

    /**
      * Invalidate the resolved type cache (call after saving rates).
      *
      * @return void
      */
    public function invalidateResolvedTypeCache(): void
    {
        $this->resolvedTypeCache = [];
        if (file_exists($this->cacheFile)) {
            @unlink($this->cacheFile);
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
      * Load cached rates from session data.
      *
      * @param array $sessionRates Rates from $_SESSION['ksf_qb_factors']
      * @return void
      */
    public function loadFromSession(array $sessionRates): void
    {
        if (isset($sessionRates['global'])) {
            $this->globalRate = (float)$sessionRates['global'];
        }
        foreach ($sessionRates['type'] ?? [] as $typeId => $rate) {
            $this->typeRates[(string)$typeId] = (float)$rate;
        }
        foreach ($sessionRates['category'] ?? [] as $catId => $rate) {
            $this->categoryRates[(string)$catId] = (float)$rate;
        }
        foreach ($sessionRates['gl'] ?? [] as $glAccount => $rate) {
            $this->glRates[$glAccount] = (float)$rate;
        }
        // Also load resolved types into cache
        foreach ($sessionRates['resolved_types'] ?? [] as $typeId => $rate) {
            $this->resolvedTypeCache[(string)$typeId] = (float)$rate;
        }
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
      * Uses cached file if available, rebuilds if missing or corrupt.
      *
      * @return array<string, float> type_id => rate
      */
    private function getResolvedTypeRates(): array
    {
        global $db;

        // Try to load from cache first
        if (empty($this->resolvedTypeCache)) {
            $cached = $this->loadResolvedTypeCache();
            if ($cached !== null) {
                $this->resolvedTypeCache = $cached;
                error_log("getResolvedTypeRates: loaded cache from file, " . count($this->resolvedTypeCache) . " types");
                return $this->resolvedTypeCache;
            }
            error_log("getResolvedTypeRates: cache not found or corrupt, rebuilding");
        }

        $resolved = [];

        // Build a map of all type IDs
        $typeIds = [];
        $result = db_query("SELECT id FROM " . TB_PREF . "chart_types");
        if ($result) {
            while ($row = db_fetch_assoc($result)) {
                $typeIds[] = (string)$row['id'];
            }
        }

        // Resolve rate for each type (bulk load for efficiency)
        foreach ($typeIds as $typeId) {
            // Walk up the parent chain until we find a type with a direct rate
            $current = $typeId;
            $foundDirectRate = false;
            
            while ($current !== null && $current !== '') {
                if (isset($this->typeRates[$current])) {
                    $resolved[$typeId] = $this->typeRates[$current];
                    $this->resolvedTypeCache[$typeId] = $this->typeRates[$current];
                    $foundDirectRate = true;
                    break;
                }
                $current = $this->getParentType($current);
            }
            
            if (!$foundDirectRate) {
                // No parent had a direct rate, check class then global
                $classId = $this->getTypeClass($typeId);
                if ($classId && isset($this->categoryRates[$classId])) {
                    $resolved[$typeId] = $this->categoryRates[$classId];
                    $this->resolvedTypeCache[$typeId] = $this->categoryRates[$classId];
                } else {
                    $resolved[$typeId] = $this->globalRate;
                    $this->resolvedTypeCache[$typeId] = $this->globalRate;
                }
            }
        }

        // Save to cache file for next time
        $this->saveResolvedTypeCache($this->resolvedTypeCache);

        return $resolved;
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

            // Try to load from file cache if not already in memory
            if (empty($this->resolvedTypeCache)) {
                $cached = $this->loadResolvedTypeCache();
                if ($cached !== null) {
                    $this->resolvedTypeCache = $cached;
                }
            }

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