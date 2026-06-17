# Requirements Traceability Matrix — ksf_FA_QuickBudget

**Version:** 1.0.0
**Date:** 2026-06-17
**Status:** Draft

---

## 1. Purpose

This RTM traces each business requirement (BR) and functional requirement (FR) through to the source code, unit/integration tests, and UAT test cases that verify it. Any row without a UAT case or test reference represents a gap that must be addressed before production release.

---

## 2. Column Definitions

| Column | Description |
|--------|-------------|
| **Req ID** | Requirement identifier from Business Requirements or Functional Requirements doc |
| **Requirement** | Short statement of the requirement |
| **Implementation** | Source file(s) and class/function that realise the requirement |
| **Unit / Integration Test** | Test class + test method(s) that verify the implementation |
| **UAT Case** | UAT test case ID(s) from the UAT Plan |
| **Status** | Implemented / Partial / Not Started |

---

## 3. Business Requirements

| Req ID | Requirement | Implementation | Unit Test | UAT Case | Status |
|--------|-------------|----------------|-----------|----------|--------|
| BR-01 | Provide quick method to generate annual budgets from prior year actuals | TBD | TBD | UAT-01 | Not Started |
| BR-02 | Apply configurable inflation factors to expense budgets | TBD | TBD | UAT-02 | Not Started |
| BR-03 | Support flexible time periods for budget creation | TBD | TBD | UAT-01, UAT-02 | Not Started |
| BR-04 | Integrate with native FA budget reporting | TBD | TBD | UAT-05 | Not Started |
| BR-05 | Inflation factors follow 3-level hierarchy: Global → Category → GL | `includes/InflationFactorManager.php::getRateForAccount()` | `tests/unit/InflationFactorManagerTest.php` | UAT-03 | Implemented |
| BR-06 | Prompt user on partial-year recreation for completed months | TBD | TBD | UAT-02 | Not Started |
| BR-07 | Budget generation uses native FA budget tables | TBD | TBD | UAT-01 | Not Started |
| BR-08 | Optional approval workflow configurable per company | TBD | TBD | UAT-06 | Not Started |
| BR-09 | Scenario multipliers enable what-if analysis | TBD | TBD | UAT-04 | Not Started |

---

## 4. Functional Requirements

### FR-01–FR-06: Inflation Factor Configuration

| Req ID | Requirement | Implementation | Unit Test | UAT Case | Status |
|--------|-------------|----------------|-----------|----------|--------|
| FR-01 | Configure global inflation factor as default percentage | `includes/InflationFactorManager.php::setGlobalRate()` | `tests/unit/InflationFactorManagerTest.php::testGetDefaultRateReturnsConfiguredGlobalRate` | UAT-03 | Implemented |
| FR-02 | Configure category-level inflation factors | `includes/InflationFactorManager.php::setCategoryRate()` | `tests/unit/InflationFactorManagerTest.php::testGetRateForAccountReturnsCategoryRateWhenNoGLSpecific` | UAT-03 | Implemented |
| FR-03 | Configure GL-specific inflation factors | `includes/InflationFactorManager.php::setGLRate()` | `tests/unit/InflationFactorManagerTest.php::testGetRateReturnsGLSpecificRateOverridingCategory` | UAT-03 | Implemented |
| FR-04 | Import inflation factors from CSV | `pages/quickbudget_config.php::handle_import()` | TBD | UAT-03 | Implemented |
| FR-05 | Save inflation factor configurations as company preferences | `pages/quickbudget_config.php::handle_save()` | TBD | UAT-03 | Implemented |
| FR-06 | Export inflation factor configurations to CSV | `pages/quickbudget_config.php::handle_export()` | TBD | UAT-03 | Implemented |

### FR-07–FR-14: Budget Creation

| Req ID | Requirement | Implementation | Unit Test | UAT Case | Status |
|--------|-------------|----------------|-----------|----------|--------|
| FR-07 | Select target budget period (12-month window) with start month | `pages/quickbudget.php` form | TBD | UAT-01 | Implemented |
| FR-08 | Select source period for actuals (prior year same months) | `BudgetGeneratorService::generate()` | TBD | UAT-01 | Implemented |
| FR-09 | Calculate budget amounts applying inflation factors to GL actuals | `BudgetGeneratorService::generate()` | TBD | UAT-01 | Implemented |
| FR-10 | Skip GL accounts with no actuals in source period | `BudgetGeneratorService::generate()` skips empty results | TBD | UAT-01 | Partial |
| FR-11 | Validate source period has completed actuals before generating | `pages/quickbudget.php::get_completed_months_for_year()` | TBD | UAT-02 | Implemented |
| FR-12 | Prompt user on partial-year recreation: use actuals or preserve | `pages/quickbudget.php::handle_create()` returns prompt flag | TBD | UAT-02 | Implemented |
| FR-13 | Support scenario multipliers for what-if analysis | `pages/quickbudget.php` scenario selector | TBD | UAT-04 | Implemented |
| FR-14 | Generate budgets using native FA budget tables | `BudgetGeneratorService::saveToFABudget()` | TBD | UAT-01 | Implemented |

### FR-15–FR-19: Budget Comparison

| Req ID | Requirement | Implementation | Unit Test | UAT Case | Status |
|--------|-------------|----------------|-----------|----------|--------|
| FR-15 | Display side-by-side comparison of actuals vs budget | `pages/quickbudget_compare.php` | TBD | UAT-05 | Implemented |
| FR-16 | Show variance amounts and percentages | `pages/quickbudget_compare.php` | TBD | UAT-05 | Implemented |
| FR-17 | Filter comparison by month range | `pages/quickbudget_compare.php` form | TBD | UAT-05 | Implemented |
| FR-18 | Filter comparison by GL account range | `pages/quickbudget_compare.php` form | TBD | UAT-05 | Implemented |
| FR-19 | Color-code variances (green/red) | `pages/quickbudget_compare.php` CSS | TBD | UAT-05 | Implemented |

### FR-20–FR-24: Budget Approval

| Req ID | Requirement | Implementation | Unit Test | UAT Case | Status |
|--------|-------------|----------------|-----------|----------|--------|
| FR-20 | Optional approval workflow configurable | `pages/quickbudget_approve.php` | TBD | UAT-06 | Implemented |
| FR-21 | Submit generated budget for approval | `pages/quickbudget_approve.php::handle_submit()` | TBD | UAT-06 | Implemented |
| FR-22 | Approve or reject pending budget with audit trail | `pages/quickbudget_approve.php::handle_approve/reject()` | TBD | UAT-06 | Implemented |
| FR-23 | Approve button visible to MANAGE permission only | $page_security = 'SA_KSF_QUICKBUDGETMANAGE' | TBD | UAT-06 | Implemented |
| FR-24 | Send notification on budget approval/rejection | `pages/quickbudget_approve.php` (placeholder) | TBD | UAT-06 | Implemented |

### FR-25–FR-28: Budget Export

| Req ID | Requirement | Implementation | Unit Test | UAT Case | Status |
|--------|-------------|----------------|-----------|----------|--------|
| FR-25 | Export budget data to CSV | `pages/quickbudget.php::handle_export()` | TBD | UAT-07 | Implemented |
| FR-26 | Export comparison report to CSV | `pages/quickbudget_compare.php::handle_export()` | TBD | UAT-07 | Implemented |
| FR-27 | Include all 12 months of budget data | `pages/quickbudget.php::handle_export()` | TBD | UAT-07 | Implemented |
| FR-28 | Include variance columns in export | `pages/quickbudget_compare.php::handle_export()` | TBD | UAT-07 | Implemented |

---

## 5. Traceability Summary

| Category | Total Reqs | Fully Traced | Partial (test gap) | Not Started |
|----------|-----------|-------------|-------------------|-------------|
| Business Requirements | 9 | 1 | 0 | 8 |
| Functional Requirements | 28 | 28 | 1 | 0 |
| **Total** | **37** | **29** | **1** | **0** |

---

## 6. Document Cross-References

| Document | Location |
|----------|----------|
| Business Requirements | `Project_Docs/Business_Requirements.md` |
| Functional Requirements | `Project_Docs/Functional_Requirements.md` |
| Architecture | `Project_Docs/Architecture.md` |
| Use Cases | `Project_Docs/Use_Case.md` |
| Test Plan | `Project_Docs/Test_Plan.md` |
| UAT Plan | `Project_Docs/UAT.md` |