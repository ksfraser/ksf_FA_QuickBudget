# Functional Requirements — ksf_FA_QuickBudget

**Version:** 1.1.0
**Date:** 2026-07-14
**Status:** Active

---

## 1. Budget Configuration

| ID | Requirement | Source Use Case |
|----|-------------|-----------------|
| FR-01 | Configure global inflation factor as default percentage (e.g., 3.5%) | UC-03 |
| FR-02 | Configure category-level inflation factors (Assets, Income, COGS, Expenses) that override global | UC-03 |
| FR-03 | Configure group-level inflation factors for GL groups within chart_class | UC-03 |
| FR-04 | Configure GL-specific inflation factors that override global, category, and group | UC-03 |
| FR-05 | Import inflation factors from CSV with GL account, category, and rate columns | UC-03 |
| FR-06 | Save inflation factor configurations as company preferences | UC-03 |
| FR-07 | Export current inflation factor configurations to CSV | UC-03 |

---

## 2. Budget Creation

| ID | Requirement | Source Use Case |
|----|-------------|-----------------|
| FR-08 | Select target budget period (12-month window) with start month selection | UC-01, UC-02 |
| FR-09 | Select source period for actuals (prior year same months) | UC-01, UC-02 |
| FR-10 | Calculate budget amounts by applying inflation factors to GL account actuals | UC-01, UC-02 |
| FR-11 | Skip GL accounts with no actuals in source period | UC-01, UC-02 |
| FR-12 | Validate source period has completed actuals before generating | UC-02 |
| FR-13 | Prompt user on partial-year recreation: use actuals for completed months or preserve existing | UC-02 |
| FR-14 | Support scenario multipliers for what-if analysis (baseline=1.0, optimistic=0.9, pessimistic=1.1) | UC-04 |
| FR-15 | Generate budgets using native FA budget tables for reporting compatibility | UC-01, UC-02 |

---

## 3. Budget Comparison and Reporting

| ID | Requirement | Source Use Case |
|----|-------------|-----------------|
| FR-16 | Display side-by-side comparison of actuals vs budget by GL account | UC-05 |
| FR-17 | Show variance amounts and percentages | UC-05 |
| FR-18 | Filter comparison by month range | UC-05 |
| FR-19 | Filter comparison by GL account range | UC-05 |
| FR-20 | Color-code variances (green for favorable, red for unfavorable) | UC-05 |

---

## 4. Budget Approval

| ID | Requirement | Source Use Case |
|----|-------------|-----------------|
| FR-21 | Optional approval workflow configurable per company | UC-06 |
| FR-22 | Submit generated budget for approval | UC-06 |
| FR-23 | Approve or reject pending budget with audit trail | UC-06 |
| FR-24 | Approve button only visible to users with MANAGE permission | UC-06 |
| FR-25 | Send notification on budget approval/rejection | UC-06 |

---

## 5. Budget Export

| ID | Requirement | Source Use Case |
|----|-------------|-----------------|
| FR-26 | Export budget data to CSV format | UC-07 |
| FR-27 | Export comparison report to CSV | UC-07 |
| FR-28 | Include all 12 months of budget data per GL account | UC-07 |
| FR-29 | Include variance columns when exporting comparison | UC-07 |

---

## 6. Historical Inflation Analysis (Issue #2)

| ID | Requirement | Source Use Case |
|----|-------------|-----------------|
| FR-37 | Calculate year-over-year inflation for each GL account from `gl_trans` actuals | UC-08 |
| FR-38 | Calculate year-over-year inflation aggregated at category level (`chart_class`) | UC-08 |
| FR-39 | Calculate year-over-year inflation aggregated at class level (`chart_class` class grouping) | UC-08 |
| FR-40 | Use all available historical data; no artificial year cap | UC-08 |
| FR-41 | Show 1/3/5/7/10 year trend indicators for each GL, category, and class | UC-08 |
| FR-42 | Compute statistics: mean, median, mode, min, max, standard deviation | UC-08 |
| FR-43 | Compute trend slope via linear regression over available years | UC-08 |
| FR-44 | Exclude GL accounts with no data from statistical averages | UC-08 |
| FR-45 | Display tabular year-by-year data: Year, Prior Actual, Current Actual, YoY Rate, Status | UC-08 |
| FR-46 | Display charts (line, bar) using Chart.js for selected item and distribution | UC-08 |
| FR-47 | Context display: when viewing a GL show its category stats; when viewing a category show its class stats | UC-08 |
| FR-48 | Flag whether a GL/category is within plus/minus 1 std dev of its parent group | UC-08 |
| FR-49 | Filter by: specific GL, all GLs, specific category, all categories, specific class, all classes, ALL | UC-08 |
| FR-50 | Provide both tabular and chart display modes | UC-08 |
| FR-51 | Transfer observed rate to config for a single item (GL/category/class) with preview diff before commit | UC-08 |
| FR-52 | Bulk transfer observed rates for all items at a selected level | UC-08 |
| FR-53 | Transfer selector: choose 1yr, 3yr, 5yr, 7yr, 10yr value, mean, median, or mode | UC-08 |
| FR-54 | Print to PDF with filter selection (specific item or ALL), tabular + chart | UC-08 |
| FR-55 | Cache computed historical rates in `0_ksf_quickbudget_inflation_history` table | UC-08 |

---

## 7. Security

| ID | Requirement | Source |
|----|-------------|--------|
| FR-30 | SA_KSF_QUICKBUDGETVIEW — read access to budget screens | hooks.php |
| FR-31 | SA_KSF_QUICKBUDGETMANAGE — create, modify, approve budgets | hooks.php |
| FR-32 | All module pages call add_access_extensions() after session.inc | AGENTS.md |

---

## 8. AJAX/API Endpoints

| ID | Endpoint | Purpose |
|----|----------|---------|
| FR-33 | POST /quickbudget.php?action=calculate | Calculate and preview budget |
| FR-34 | POST /quickbudget.php?action=create | Generate and save budget |
| FR-35 | POST /quickbudget.php?action=compare | Get actuals vs budget comparison data |
| FR-36 | POST /quickbudget.php?action=export | Export budget/actuals CSV |
| FR-56 | GET /quickbudget_inflation_api.php | Chart data JSON endpoint for historical inflation |
| FR-57 | POST /quickbudget_inflation_transfer.php | Transfer observed rate to config (single or bulk) |
