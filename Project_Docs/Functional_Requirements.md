# Functional Requirements — ksf_FA_QuickBudget

**Version:** 1.0.0
**Date:** 2026-06-17
**Status:** Active

---

## 1. Budget Configuration

| ID | Requirement | Source Use Case |
|----|-------------|-----------------|
| FR-01 | Configure global inflation factor as default percentage (e.g., 3.5%) | UC-03 |
| FR-02 | Configure category-level inflation factors that override global for grouped GL accounts | UC-03 |
| FR-03 | Configure GL-specific inflation factors that override both global and category | UC-03 |
| FR-04 | Import inflation factors from CSV with GL account, category, and rate columns | UC-03 |
| FR-05 | Save inflation factor configurations as company preferences | UC-03 |
| FR-06 | Export current inflation factor configurations to CSV | UC-03 |

---

## 2. Budget Creation

| ID | Requirement | Source Use Case |
|----|-------------|-----------------|
| FR-07 | Select target budget period (12-month window) with start month selection | UC-01, UC-02 |
| FR-08 | Select source period for actuals (prior year same months) | UC-01, UC-02 |
| FR-09 | Calculate budget amounts by applying inflation factors to GL account actuals | UC-01, UC-02 |
| FR-10 | Skip GL accounts with no actuals in source period | UC-01, UC-02 |
| FR-11 | Validate source period has completed actuals before generating | UC-02 |
| FR-12 | Prompt user on partial-year recreation: use actuals for completed months or preserve existing | UC-02 |
| FR-13 | Support scenario multipliers for what-if analysis (baseline=1.0, optimistic=0.9, pessimistic=1.1) | UC-04 |
| FR-14 | Generate budgets using native FA budget tables for reporting compatibility | UC-01, UC-02 |

---

## 3. Budget Comparison and Reporting

| ID | Requirement | Source Use Case |
|----|-------------|-----------------|
| FR-15 | Display side-by-side comparison of actuals vs budget by GL account | UC-05 |
| FR-16 | Show variance amounts and percentages | UC-05 |
| FR-17 | Filter comparison by month range | UC-05 |
| FR-18 | Filter comparison by GL account range | UC-05 |
| FR-19 | Color-code variances (green for favorable, red for unfavorable) | UC-05 |

---

## 4. Budget Approval

| ID | Requirement | Source Use Case |
|----|-------------|-----------------|
| FR-20 | Optional approval workflow configurable per company | UC-06 |
| FR-21 | Submit generated budget for approval | UC-06 |
| FR-22 | Approve or reject pending budget with audit trail | UC-06 |
| FR-23 | Approve button only visible to users with MANAGE permission | UC-06 |
| FR-24 | Send notification on budget approval/rejection | UC-06 |

---

## 5. Budget Export

| ID | Requirement | Source Use Case |
|----|-------------|-----------------|
| FR-25 | Export budget data to CSV format | UC-07 |
| FR-26 | Export comparison report to CSV | UC-07 |
| FR-27 | Include all 12 months of budget data per GL account | UC-07 |
| FR-28 | Include variance columns when exporting comparison | UC-07 |

---

## 6. Security

| ID | Requirement | Source |
|----|-------------|--------|
| FR-29 | SA_KSF_QUICKBUDGETVIEW — read access to budget screens | hooks.php |
| FR-30 | SA_KSF_QUICKBUDGETMANAGE — create, modify, approve budgets | hooks.php |
| FR-31 | All module pages call add_access_extensions() after session.inc | AGENTS.md |

---

## 7. AJAX/API Endpoints

| ID | Endpoint | Purpose |
|----|----------|---------|
| FR-32 | POST /quickbudget.php?action=calculate | Calculate and preview budget |
| FR-33 | POST /quickbudget.php?action=create | Generate and save budget |
| FR-34 | POST /quickbudget.php?action=compare | Get actuals vs budget comparison data |
| FR-35 | POST /quickbudget.php?action=export | Export budget/actuals CSV |