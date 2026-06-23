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
├── hooks.php                                # FA module hooks entry point
├── pages/quickbudget.php                      # Main budget creation page
├── pages/quickbudget_config.php               # Inflation factor configuration
├── pages/quickbudget_compare.php              # Actuals vs budget comparison
├── pages/quickbudget_approve.php              # Budget approval workflow
├── includes/
│   ├── InflationFactorManager.php             # Inflation factor persistence and resolution
│   ├── InflationFactorDTO.php                 # Data transfer for factors
│   ├── InflationFactorRepository.php          # DB persistence for factors
│   ├── BudgetEntryDTO.php                   # Budget entry data transfer
│   └── ScenarioDTO.php                      # Scenario data transfer
├── src/
│   ├── Controller/BudgetController.php        # HTTP request handling
│   └── Service/BudgetGeneratorService.php     # Core budget generation logic
├── sql/
│   └── install.sql                          # Table schemas and seed data
├── tests/
│   └── unit/InflationFactorManagerTest.php    # Unit tests
└── _init/config                             # Module metadata (gzip)
```

---

## 4. Data Model

### 4-1. Inflation Factors Table
```sql
CREATE TABLE `0_ksf_quickbudget_factors` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `factor_type` enum('global','category','gl') NOT NULL DEFAULT 'global',
    `reference_id` varchar(64) NOT NULL DEFAULT '',
    `rate` decimal(10,4) NOT NULL DEFAULT '1.0000',
    `company` int(11) NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_factor` (`factor_type`,`reference_id`,`company`)
);
```

### 4-2. Budget Scenarios Table
```sql
CREATE TABLE `0_ksf_quickbudget_scenarios` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(64) NOT NULL,
    `multiplier` decimal(10,4) NOT NULL DEFAULT '1.0000',
    `description` text,
    `company` int(11) NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`)
);
```

### 4-3. Budget Entries Table
```sql
CREATE TABLE `0_ksf_quickbudget_budget` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `gl_account` varchar(15) NOT NULL,
    `year` int(11) NOT NULL,
    `month` int(11) NOT NULL,
    `amount` decimal(16,2) NOT NULL DEFAULT '0.00',
    `scenario` varchar(32) NOT NULL DEFAULT 'baseline',
    `company` int(11) NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_budget` (`gl_account`,`year`,`month`,`scenario`,`company`)
);
```

### 4-4. Budget Approvals Table
```sql
CREATE TABLE `0_ksf_quickbudget_approvals` (
    `budget_id` int(11) NOT NULL,
    `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `approved_by` int(11) DEFAULT NULL,
    `approved_at` datetime DEFAULT NULL,
    PRIMARY KEY (`budget_id`)
);
```

---

## 5. Key Classes

| Class | Responsibility |
|-------|----------------|
| `InflationFactorManager` | Load/save inflation factors, resolve hierarchy |
| `InflationFactorRepository` | DB persistence for inflation factors |
| `BudgetGeneratorService` | Core budget generation from actuals |
| `BudgetController` | HTTP request handling |

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
| FA Security | `SA_KSF_QUICKBUDGETVIEW`, `SA_KSF_QUICKBUDGETMANAGE` |
| FA Hooks | `ksf_get_value`, `ksf_crud_event` listeners |