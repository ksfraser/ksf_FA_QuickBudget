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
- Recreate budget entries only for months that have not completed. e.g. recreate in June, only recalculate for June, July, August...December
- Prompt user whether to use actuals for completed months or preserve existing budget entries

---

## UC-03: Configure Inflation Factors

**Actor:** FA Administrator
**Trigger:** Navigate to QuickBudget configuration screen

**Preconditions:** User has SA_KSF_QUICKBUDGETMANAGE permission

**Main Flow:**
1. User opens `quickbudget_config.php`
2. Global inflation factor displayed with default value
3. Admin sets global inflation percentage
4. Admin navigates to category configuration
5. Admin sets category-level inflation factors (overrides global)
6. Admin navigates to GL-specific configuration
7. Admin sets GL-specific inflation factors (overrides category and global)
8. Admin saves configuration (stored as company preferences)

**Alternate Flow — Import from CSV:**
1. Admin clicks "Import" button
2. Admin uploads CSV with GL accounts, categories, and rates
3. System validates and imports data
4. Imported values override existing configurations

---

## UC-04: Create Scenario-Based Budget

**Actor:** Comptroller
**Trigger:** Need to model different financial scenarios

**Main Flow:**
1. User opens `quickbudget.php`
2. User selects scenario multiplier (baseline=1.0, optimistic=0.9, pessimistic=1.1)
3. User launches Budget Creation
4. Module calculates budget using (actuals × inflation × scenario) formula
5. Budget created with scenario tag for filtering

---

## UC-05: View Budget Comparison

**Actor:** Comptroller
**Trigger:** Navigate to QuickBudget comparison screen

**Main Flow:**
1. User opens `quickbudget_compare.php`
2. System displays side-by-side comparison of actuals vs budget by GL account
3. User filters by month range (optional)
4. User filters by GL account range (optional)
5. System shows variance amounts and percentages with color coding

---

## UC-06: Approve Budget

**Actor:** Comptroller (with MANAGE permission)
**Trigger:** Budget requires approval workflow

**Main Flow:**
1. User opens `quickbudget_approve.php`
2. System shows pending budgets with calculated amounts
3. User reviews budget details
4. User clicks "Approve" or "Reject"
5. System records approval status with timestamp and user ID
6. System sends notification to budget owner

---

## UC-07: Export Budget Report

**Actor:** Comptroller
**Trigger:** Need budget data for external analysis

**Main Flow:**
1. User opens `quickbudget_export.php`
2. User selects export type: Budget only or Budget + Comparison
3. User selects GL account range and month range (optional)
4. System generates CSV file
5. System prompts download of generated file

---
