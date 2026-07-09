<?php
/**
 * CategoryDAO
 *
 * Repository for GL account category data.
 * Queries chart_types and chart_class for dynamic COA-based categories.
 * Supports FR-02.
 */
declare(strict_types=1);

final class CategoryDAO
{
    /** @var array<string, string>|null Cached class_id => class_name map */
    private static $classMapCache = null;

    /** @var array<int, string>|null Cached account_type => class_id map */
    private static $typeToClassMapCache = null;

    /**
     * Get account_type to class_id mapping.
     * Uses chart_types.ctype to chart_class.cid relationship.
     *
     * @return array<int, string> account_type => class_id
     */
    public function getAccountTypeToClassMap(): array
    {
        global $db;

        if (self::$typeToClassMapCache !== null) {
            return self::$typeToClassMapCache;
        }

        $map = [];
        if (is_resource($db) || ($db instanceof mysqli)) {
            $sql = "SELECT id, ctype FROM " . TB_PREF . "chart_types ORDER BY id";
            $result = db_query($sql);
            if ($result) {
                while ($row = db_fetch_assoc($result)) {
                    $map[(int)$row['id']] = (string)$row['ctype'];
                }
            }
        }

        self::$typeToClassMapCache = $map;
        return $map;
    }

    /**
     * Get class_id to class_name mapping from chart_class.
     *
     * @return array<string, string> class_id => class_name (lowercased)
     */
    public function getClassMap(): array
    {
        global $db;

        if (self::$classMapCache !== null) {
            return self::$classMapCache;
        }

        $map = [];
        if (is_resource($db) || ($db instanceof mysqli)) {
            $sql = "SELECT cid, class_name FROM " . TB_PREF . "chart_class";
            $result = db_query($sql);
            if ($result) {
                while ($row = db_fetch_assoc($result)) {
                    $map[(string)$row['cid']] = strtolower($row['class_name']);
                }
            }
        }

        self::$classMapCache = $map;
        return $map;
    }

    /**
     * Get all available category names for UI dropdowns.
     * Returns unique class names from chart_class.
     *
     * @return array<string> Unique lowercased class names
     */
    public function getAllCategories(): array
    {
        return array_values($this->getClassMap());
    }
}