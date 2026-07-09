<?php
/**
 * GroupDAO
 *
 * Repository for GL group data from chart_types.
 * Groups are chart_types entries for display in DDL.
 * Supports FR-03: Group-level inflation factors.
 *
 * Hierarchy for rate resolution:
 *   GL → Type (chart_types.name) → Parent (chart_types.class_id) → Category (chart_class) → Global
 */
declare(strict_types=1);

final class GroupDAO
{
    /**
     * Get all chart types for group rate configuration.
     * Returns id => name mapping for DDL.
     *
     * @return array<int, string> id => name
     */
    public function getAllGroups(): array
    {
        global $db;

        if (!is_resource($db) && !($db instanceof mysqli)) {
            return [];
        }

        $sql = "SELECT id, name FROM " . TB_PREF . "chart_types
            ORDER BY id";
        $result = db_query($sql);

        $groups = [];
        if ($result) {
            while ($row = db_fetch_assoc($result)) {
                $groups[(int)$row['id']] = $row['name'];
            }
        } else {
            $error = $db ? (method_exists($db, 'error') ? $db->error : 'no error property') : 'no db connection';
            error_log("GroupDAO::getAllGroups query failed: $sql - DB error: $error");
        }

        return $groups;
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

        $result = db_query("SELECT name FROM " . TB_PREF . "chart_types
            WHERE id = " . (int)$typeId);
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

        $result = db_query("SELECT class_id FROM " . TB_PREF . "chart_types
            WHERE id = " . (int)$typeId);
        if (!$result) {
            return null;
        }
        $row = db_fetch_assoc($result);

        return $row && $row['class_id'] ? (int)$row['class_id'] : null;
    }
}