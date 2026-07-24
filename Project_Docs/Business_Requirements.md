# Business Requirements — ksf_FA_QuickBudget

**Version:** 1.1.0
**Date:** 2026-07-14
**Status:** Active

---

## 1. Objectives

BR-01: Provide a quick method to generate annual budgets in FrontAccounting using prior year actuals as the baseline.

BR-02: Apply configurable inflation factors to expense budgets to accommodate annual cost increases.

BR-03: Support flexible time periods for budget creation (current year or next year) with partial year scenarios.

BR-04: Integrate with native FA budget reporting to ensure compatibility with existing financial dashboards.

BR-10: Calculate apparent historical inflation from GL actuals at GL, category, and class levels to inform budget planning.

BR-11: Display multi-year trends with charts and allow transfer of observed inflation rates to the budget configuration.

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
| BR-05 | Inflation factors follow a 4-level hierarchy: Global (default) -> Category override -> Type override (with parent chain) -> GL-specific override |
| BR-06 | When re-creating a budget in the middle of a year, prompt user whether to use actuals for completed months or preserve existing budget entries |
| BR-07 | Budget generation overwrites existing entries for the target period without confirmation |
| BR-08 | Module uses FA native budget tables to maintain compatibility with FA reporting |
| BR-09 | Approval workflow is optional and configurable; defaulted to direct creation |
| BR-12 | Historical inflation calculation uses all available gl_trans data (no artificial year cap) but displays 1/3/5/7/10 year trend indicators |
| BR-13 | GL accounts with no historical data are excluded from averages and trend calculations |
| BR-14 | Transfer of observed rates to config requires a preview diff and user confirmation before writing |
| BR-15 | Category and class level aggregations sum GL actuals before computing YoY, so a new GL appearing does not distort the category/class trend |

---

## 4. Scope

### In Scope
- Quick creation of annual budgets from prior year actuals
- Configurable inflation factors with hierarchical override
- Scenario-based budgeting (multiple budget versions)
- Integration with FA's Budget Variance reports
- Export capability for budget data
- Historical inflation analysis with multi-year trends (Issue #2)
- Charts via Chart.js for inflation visualization (Issue #2)
- PDF export of inflation reports (Issue #2)
- Transfer observed inflation rates to budget config (Issue #2)

### Out of Scope
- Detailed budget forecasting beyond 12 months
- Multi-company consolidation (relies on FA's existing multi-company support)
- Budget workflow approvals beyond optional configuration (initial release)
- Predictive forecasting or ML-based inflation modeling

---

## 5. Constraints

| Constraint | Description |
|------------|-------------|
| BC-01 | PHP 7.4 minimum; no PHP 8+ syntax |
| BC-02 | Compatible with FrontAccounting 2.4+ |
| BC-03 | Uses FA hook system for inter-module integration |
| BC-04 | Chart.js loaded from CDN (no npm build pipeline) |
