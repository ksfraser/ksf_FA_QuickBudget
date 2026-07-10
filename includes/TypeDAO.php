<?php
/**
 * TypeDAO
 *
 * Repository for GL type data from chart_types.
 * Types are chart_types entries for display in DDL.
 * Supports FR-03: Type-level inflation factors.
 *
 * Hierarchy for rate resolution:
 *   GL → Type (chart_types.name) → Parent (chart_types.class_id) → Category (chart_class) → Global
 */
declare(strict_types=1);

final class TypeDAO
{
    /**
     * Get all chart types for type rate configuration.
     * Returns id => name mapping for DDL.
     *
     * @return array<int, string> id => name
     */
    public function getAllTypes(): array
    {
        global $db;

        if (!is_resource($db) && !($db instanceof mysqli)) {
            return [];
        }

        $sql = "SELECT id, name FROM " . TB_PREF . "chart_types ORDER BY id";
        $result = db_query($sql);

        $types = [];
        if ($result) {
            while ($row = db_fetch_assoc($result)) {
                $types[(int)$row['id']] = $row['name'];
            }
        } else {
            $error = $db ? (method_exists($db, 'error') ? $db->error : 'no error property') : 'no db connection';
            error_log("TypeDAO::getAllTypes query failed: $sql - DB error: $error");
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
     * @param int $typeId chart_types.id
     * @return string|null Type name or null
     */
    public function getTypeName(int $typeId): ?string
    {
        global $db;

        if (!is_resource($db) && !($db instanceof mysqli)) {
            return null;
        }

        $result = db_query("SELECT name FROM " . TB_PREF . "chart_types WHERE id = " . (int)$typeId);
        if (!$result) {
            return null;
        }
        $row = db_fetch_assoc($result);

        return $row ? $row['name'] : null;
    }

    /**
     * Get parent type ID by chart_types.id.
     *
     * @param int $typeId chart_types.id
     * @return int|null Parent id or null
     */
    public function getParentType(int $typeId): ?int
    {
        global $db;

        if (!is_resource($db) && !($db instanceof mysqli)) {
            return null;
        }

        $result = db_query("SELECT class_id FROM " . TB_PREF . "chart_types WHERE id = " . (int)$typeId);
        if (!$result) {
            return null;
        }
        $row = db_fetch_assoc($result);

        return $row && $row['class_id'] ? (int)$row['class_id'] : null;
    }
}