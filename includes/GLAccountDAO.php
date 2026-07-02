<?php
/**
 * GLAccountRepository
 *
 * Repository for GL account data from chart_master.
 * Supports FR-04.
 */
declare(strict_types=1);

final class GLAccountDAO
{
    /**
     * Get all GL accounts with their names.
     *
     * @return array<string, string> Account code => name
     */
    public function getAllGLAccounts(): array
    {
        global $db;

        $result = db_query("SELECT account_code, account_name FROM " . TB_PREF . "chart_master
            WHERE account_code IS NOT NULL
            ORDER BY account_code", "Cannot read GL accounts");

        $accounts = [];
        while ($row = db_fetch_assoc($result)) {
            $accounts[$row['account_code']] = $row['account_name'];
        }

        return $accounts;
    }

    /**
     * Get GL account name by code.
     *
     * @param string $accountCode GL account code
     * @return string|null Account name or null
     */
    public function getAccountName(string $accountCode): ?string
    {
        global $db;

        $result = db_query("SELECT account_name FROM " . TB_PREF . "chart_master
            WHERE account_code = '" . mysqli_real_escape_string($db, $accountCode) . "'", "Cannot read account name");
        $row = db_fetch_assoc($result);

        return $row ? $row['account_name'] : null;
    }

    /**
     * Get account type for a GL account.
     *
     * @param string $accountCode GL account code
     * @return int|null Account type (0-10) or null
     */
    public function getAccountType(string $accountCode): ?int
    {
        global $db;

        $result = db_query("SELECT account_type FROM " . TB_PREF . "chart_master
            WHERE account_code = '" . mysqli_real_escape_string($db, $accountCode) . "'", "Cannot read account type");
        $row = db_fetch_assoc($result);

        return $row ? (int)$row['account_type'] : null;
    }

    /**
     * Get account group class_id for a GL account.
     *
     * @param string $accountCode GL account code
     * @return string|null Group class_id or null
     */
    public function getAccountGroup(string $accountCode): ?string
    {
        global $db;

        $result = db_query("SELECT ct.class_id FROM " . TB_PREF . "chart_master cm
            LEFT JOIN " . TB_PREF . "chart_types ct ON cm.account_type = ct.id
            WHERE cm.account_code = '" . mysqli_real_escape_string($db, $accountCode) . "'", "Cannot read account group");
        $row = db_fetch_assoc($result);

        return $row && $row['class_id'] ? (string)$row['class_id'] : null;
    }
}
