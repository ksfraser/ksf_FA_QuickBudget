<?php
/**
 * GroupRepository
 *
 * Repository for GL group data from chart_types.
 * Supports FR-03.
 */
declare(strict_types=1);

final class GroupDAO
{
    /**
     * Get all groups (class_id) with their names.
     *
     * @return array<string, string> Group ID => name
     */
    public function getAllGroups(): array
    {
        global $db;
        
        // Defensive: check if DB is valid before querying
        if (!is_resource($db) && !($db instanceof mysqli)) {
            return [];
        }

        $sql = "SELECT DISTINCT class_id, name FROM " . TB_PREF . "chart_types
            WHERE class_id IS NOT NULL AND class_id > 0
            ORDER BY class_id";
        $result = db_query($sql);

        $groups = [];
        if ($result) {
            while ($row = db_fetch_assoc($result)) {
                $groups[$row['class_id']] = $row['name'];
            }
        }

        return $groups;
    }

    /**
     * Get group name by class_id.
     *
     * @param string $classId Group class_id
     * @return string|null Group name or null
     */
    public function getGroupName(string $classId): ?string
    {
        global $db;
        
        // Defensive: check if DB is valid before querying
        if (!is_resource($db) && !($db instanceof mysqli)) {
            return null;
        }

        $result = db_query("SELECT name FROM " . TB_PREF . "chart_types
            WHERE class_id = '" . mysqli_real_escape_string($db, $classId) . "'");
        if (!$result) {
            return null;
        }
        $row = db_fetch_assoc($result);

        return $row ? $row['name'] : null;
    }
}