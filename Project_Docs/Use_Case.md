# Use Cases — ksf_FA_QuickBudget

**Version:** 1.0.0
**Date:** 2026-06-16
**Status:** Active

---

## Actors

| Actor | Description |
|-------|-------------|
| **Comptroller** | Staff user with Financials access  |
| **FA Administrator** | Configures module, security areas, and calendar sources |

---

## UC-01: Create Budget

**Actor:** Comptroller
**Trigger:** Navigate to QuickBudget module in FA

**Main Flow:**
1. User opens `quickbudget.php`
2. FA session is authenticated; `$page_security` checked
3. Admin screen rendered with list of variables
4. User launches Budget Creation (date from current view)
5. Module calculates budget entries for every GL using module variables.
6. Budget created

---

## UC-02: Re-Create a Budget

**Actor:** Comptroller
**Trigger:** Navigate to QuickBudget module in FA

**Main Flow:**
1. User opens `quickbudget.php`
2. FA session is authenticated; `$page_security` checked
3. Admin screen rendered with list of variables
4. User launches Budget Recreation (date from current view)
5. Module calculates budget entries for every GL using module variables.
6. Budget created

**Alternate Flow — Recreating Current Year:**
- Recreate budget entries only for months that have not completed.  e.g. recreate in June, only recalculate for June, July, August...December

---
