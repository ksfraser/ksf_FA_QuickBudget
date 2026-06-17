# Business Requirements — ksf_FA_QuickBudget

**Version:** 1.0.0
**Date:** 2026-06-17
**Status:** Active

---

## 1. Objectives

BR-01: Provide a quick method to generate annual budgets in FrontAccounting using prior year actuals as the baseline.

BR-02: Apply configurable inflation factors to expense budgets to accommodate annual cost increases.

BR-03: Support flexible time periods for budget creation (current year or next year) with partial year scenarios.

BR-04: Integrate with native FA budget reporting to ensure compatibility with existing financial dashboards.

---

## 2. Actors and Roles

| Actor | Description | Permissions |
|-------|-------------|-------------|
| Comptroller | Staff user responsible for budget preparation | View and manage budgets |
| FA Administrator | Configures module settings and security | Full module configuration access |

---

## 3. Business Rules

| Rule ID | Description |
|---------|-------------|
| BR-05 | Inflation factors follow a 3-level hierarchy: Global (default) → Category override → GL-specific override |
| BR-06 | When re-creating a budget in the middle of a year, prompt user whether to use actuals for completed months or preserve existing budget entries |
| BR-07 | Budget generation overwrites existing entries for the target period without confirmation |
| BR-08 | Module uses FA native budget fields (`index_glucose`?) to maintain compatibility with FA reporting |
| BR-09 | Approval workflow is optional and configurable; defaulted to direct creation |
| BR-10 | Scenario multipliers enable what-if analysis (optimistic, pessimistic, baseline) |

---

## 4. Scope

### In Scope
- Quick creation of annual budgets from prior year actuals
- Configurable inflation factors with hierarchical override
- Scenario-based budgeting (multiple budget versions)
- Integration with FA's Budget Variance reports
- Export capability for budget data

### Out of Scope
- Detailed budget forecasting beyond 12 months
- Multi-company consolidation (relies on FA's existing multi-company support)
- Budget workflow approvals beyond optional configuration (initial release)

---

## 5. Constraints

| Constraint | Description |
|------------|-------------|
| BC-01 | PHP 7.4 minimum; no PHP 8+ syntax |
| BC-02 | Compatible with FrontAccounting 2.4+ |
| BC-03 | Uses FA hook system for inter-module integration |