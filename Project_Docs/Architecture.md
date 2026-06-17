# Architecture — ksf_FA_QuickBudget

**Version:** 1.0.0
**Date:** 2026-06-17
**Status:** Draft

---

## 1. Overview

The QuickBudget module provides a streamlined workflow for generating annual budgets in FrontAccounting using prior year actuals as baseline data. It integrates with FA's native budget infrastructure to ensure compatibility with existing reports.

---

## 2. Design Goals

- **SOLID**: Single responsibility per class
- **DRY**: Reusable components for inflation factor calculation
- **TDD**: Test-driven development for core calculations
- **DI**: Dependency injection via FA_QuickBudget_Module singleton
- **SRP**: Separate concerns: configuration, calculation, output, approval

---

## 3. Module Components

```
ksf_FA_QuickBudget/
├── hooks.php                    # FA module hooks entry point
├── pages/quickbudget.php          # Main budget creation page
├── pages/quickbudget_config.php   # Inflation factor configuration
├── pages/quickbudget_compare.php  # Actuals vs budget comparison
├── pages/quickbudget_approve.php  # Budget approval workflow
├── pages/quickbudget_export.php   # Export functionality
├── includes/
│   ├── QuickBudgetService.php     # Core budget calculation logic
│   ├── InflationFactorManager.php # Inflation factor persistence
│   └── BudgetComparison.php       # Comparison report logic
├── sql/
│   └── install.sql              # Table schemas and seed data
├── src/
│   ├── Controller/
│   │   └── BudgetController.php
│   └── Support/
│       └── JsonResponse.php
└── _init/config                 # Module metadata (gzip)
```

---

## 4. Data Model

### 4-1. Inflation Factors Table
```sql
CREATE TABLE `0_ksf_quickbudget_factors` (
    `id` int AUTO_INCREMENT PRIMARY KEY,
    `factor_type` enum('global','category','gl') NOT NULL,
    `reference_id` varchar(64), -- GL account code or category name
    `rate` decimal(10,4) NOT NULL, -- e.g., 1.0350 for 3.5%
    `company` int,
    UNIQUE KEY `unique_factor` (`factor_type`, `reference_id`, `company`)
);
```

### 4-2. Budget Scenarios Table
```sql
CREATE TABLE `0_ksf_quickbudget_scenarios` (
    `id` int AUTO_INCREMENT PRIMARY KEY,
    `name` varchar(64) NOT NULL,
    `multiplier` decimal(10,4) NOT NULL,
    `description` text,
    `company` int
);
```

### 4-3. Budget Approvals Table (if approval workflow enabled)
| Note: Native FA budget tables defined in `gl/gl_budget.php` are used for storage |
| `budget_id` references the budget entry in native FA tables

```sql
CREATE TABLE `0_ksf_quickbudget_approvals` (
    `budget_id` int,
    `status` enum('pending','approved','rejected') DEFAULT 'pending',
    `approved_by` int,
    `approved_at` datetime,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP
);
```

---

## 5. Key Classes

| Class | Responsibility |
|-------|----------------|
| `FA_QuickBudget_Module` | Singleton module bootstrap, provides service access |
| `QuickBudgetService` | Core budget generation logic |
| `InflationFactorManager` | Load/save inflation factors, resolve hierarchy |
| `BudgetController` | HTTP request handling for create/compare/export |
| `BudgetComparison` | Generate actuals vs budget comparison data |

---

## 6. Inflation Factor Resolution Algorithm

```
getInflationFactor(gl_account_code):
    1. Check 0_ksf_quickbudget_factors for GL-specific rate
    2. Else check category-level rate (by GL account type)
    3. Else return global default rate
```

---

## 7. Integration Points

| Integration | Method |
|-------------|--------|
| FA GL Accounts | Query `chart_master` for GL accounts |
| FA Actuals | Query `gl_trans` for historical transactions |
| FA Budgets | Write to native FA budget tables via `gl/gl_budget.php` functions |
| FA Hooks | `ksf_get_value`, `ksf_crud_event` listeners |
| FA Security | `SA_KSF_QUICKBUDGETVIEW`, `SA_KSF_QUICKBUDGETMANAGE` |