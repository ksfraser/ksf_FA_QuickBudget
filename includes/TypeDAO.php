<?php
/**
 * TypeDAO
 *
 * Repository for GL type data from chart_types.
 * Types are chart_types entries for display in DDL.
 * Supports FR-03: Type-level inflation factors.
 *
 * Hierarchy for rate resolution:
 *   GL → Type (chart_types.id) → Parent (chart_types.parent) → Category (chart_class) → Global
 */
declare(strict_types=1);

final class TypeDAO
{
    /**
     * Get all chart types for type rate configuration.
     * Returns id (string) => name mapping for DDL.
     *
     * @return array<string, string> id => name
     */
    public function getAllTypes(): array
    {
        global $db;

        if (empty($db)) {
            return [];
        }

        $sql = "SELECT id, name FROM " . TB_PREF . "chart_types ORDER BY id";
        $result = db_query($sql);

        $types = [];
        if ($result) {
            while ($row = db_fetch_assoc($result)) {
                $types[(string)$row['id']] = $row['name'];
            }
        }

        return $types;
    }

    /**
     * Get types indexed by lowercase name for rate lookup.
     * Used when rates are stored by name but we need name-to-display mapping.
     *
     * @return array<string, string> lowercase_name => display_name
     */
    public function getAllTypesByName(): array
    {
        $types = $this->getAllTypes();
        $byName = [];
        foreach ($types as $id => $name) {
            $byName[strtolower($name)] = $name;
        }
        return $byName;
    }

    /**
     * Get type name by chart_types.id.
     *
     * @param string|int $typeId chart_types.id
     * @return string|null Type name or null
     */
    public function getTypeName($typeId): ?string
    {
        global $db;

        if (empty($db)) {
            return null;
        }

        $result = db_query("SELECT name FROM " . TB_PREF . "chart_types WHERE id = '" . (string)$typeId . "'");
        if (!$result) {
            return null;
        }
        $row = db_fetch_assoc($result);

        return $row ? $row['name'] : null;
    }

    /**
     * Get parent type ID by chart_types.id.
     *
     * @param string|int $typeId chart_types.id
     * @return string|null Parent id or null
     */
    public function getParentType($typeId): ?string
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
     * Get type ID by name.
     * Used when saving rates to store stable ID instead of mutable name.
     *
     * @param string $typeName chart_types.name
     * @return string|null chart_types.id or null if not found
     */
    public function getTypeIdByName(string $typeName): ?string
    {
        global $db;

        if (empty($db)) {
            return null;
        }

        $result = db_query("SELECT id FROM " . TB_PREF . "chart_types WHERE name = '" . addslashes($typeName) . "'");
        if (!$result) {
            return null;
        }
        $row = db_fetch_assoc($result);

        return $row ? $row['id'] : null;
    }
}