# Architecture — ksf_FA_QuickBudget

**Version:** 1.1.0
**Date:** 2026-07-14
**Status:** Active

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
├── pages/
│   ├── quickbudget.php                      # Main budget creation page
│   ├── quickbudget_config.php               # Inflation factor configuration
│   ├── quickbudget_compare.php              # Actuals vs budget comparison
│   ├── quickbudget_approve.php              # Budget approval workflow
│   ├── quickbudget_report.php               # YOY report + historical inflation (Issue #2)
│   ├── quickbudget_inflation_api.php        # Chart data JSON endpoint (Issue #2)
│   ├── quickbudget_inflation_transfer.php   # Transfer observed rates to config (Issue #2)
│   └── quickbudget_inflation_pdf.php        # PDF export (Issue #2)
├── includes/
│   ├── InflationFactorManager.php           # Inflation factor persistence and resolution
│   ├── InflationFactorDTO.php               # Data transfer for factors
│   ├── InflationFactorRepository.php        # DB persistence for factors
│   ├── GroupDAO.php                         # Read GL groups from chart_types
│   ├── GLAccountDAO.php                     # Read GL accounts from chart_master
│   ├── CategoryDAO.php                      # Read categories from chart_class
│   ├── TypeDAO.php                          # Read types from chart_types
│   ├── BudgetEntryDTO.php                   # Budget entry data transfer
│   ├── ScenarioDTO.php                      # Scenario data transfer
│   └── ScenarioRepository.php               # Scenario DB access
├── src/
│   ├── Controller/BudgetController.php      # HTTP request handling
│   └── Service/
│       ├── BudgetGeneratorService.php       # Core budget generation logic
│       ├── InflationCalculator.php          # Historical inflation calculation (Issue #2)
│       └── InflationStats.php               # Statistical aggregation (Issue #2)
├── assets/
│   ├── comparison.js                        # AJAX comparison loader
│   ├── rate-section.js                      # Rate editing helpers
│   └── inflation_charts.js                  # Chart.js charts (Issue #2)
├── sql/
│   ├── install.sql                          # Table schemas and seed data
│   └── update.sql                           # Migrations
├── cache/
│   └── resolved_type_rates.cache            # JSON cache for resolved type rates
├── tests/
│   └── unit/
│       ├── InflationFactorManagerTest.php
│       ├── BudgetGeneratorServiceTest.php
│       ├── InflationCalculatorTest.php      # (Issue #2)
│       └── InflationStatsTest.php           # (Issue #2)
└── _init/config                             # Module metadata (gzip)
```

---

## 4. Data Model

### 4-1. Inflation Factors Table
```sql
CREATE TABLE `0_ksf_quickbudget_factors` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `factor_type` varchar(32) NOT NULL DEFAULT 'global',
    `reference_id` varchar(64) NOT NULL DEFAULT '',
    `rate` decimal(10,4) NOT NULL DEFAULT '1.0000',
    `company` int(11) NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_factor` (`factor_type`,`reference_id`,`company`)
);
```

Factor types:
- `global`: Default inflation rate for all accounts
- `type`: Override for account types (chart_types.id, with parent chain inheritance)
- `category`: Override for account categories (chart_class.cid)
- `gl`: Override for specific GL account codes (chart_master.account_code)

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
    `tran_date` date NOT NULL,
    `gl_account` varchar(15) NOT NULL,
    `dimension_id` int(11) DEFAULT '0',
    `dimension2_id` int(11) DEFAULT '0',
    `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `approved_by` int(11) DEFAULT NULL,
    `approved_at` datetime DEFAULT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`tran_date`,`gl_account`,`dimension_id`,`dimension2_id`),
    KEY `idx_status` (`status`)
);
```

### 4-5. Historical Inflation Cache Table (Issue #2)
```sql
CREATE TABLE `0_ksf_quickbudget_inflation_history` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `level` enum('gl','category','class') NOT NULL,
    `reference_id` varchar(64) NOT NULL,
    `year` int(11) NOT NULL,
    `yoy_rate` decimal(10,4) DEFAULT NULL,
    `actual_total` decimal(16,2) DEFAULT NULL,
    `computed_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_history` (`level`,`reference_id`,`year`),
    KEY `idx_level_year` (`level`,`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

---

## 5. Key Classes

| Class | Responsibility |
|-------|----------------|
| `InflationFactorManager` | Load/save inflation factors, resolve hierarchy |
| `InflationFactorRepository` | DB persistence for inflation factors (read/write) |
| `InflationFactorDTO` | Data transfer for factor records |
| `CategoryDAO` | Read category data from chart_class |
| `TypeDAO` | Read type data from chart_types |
| `GLAccountDAO` | Read GL account data from chart_master |
| `BudgetGeneratorService` | Core budget generation from actuals |
| `BudgetController` | HTTP request handling |
| `InflationCalculator` | Historical inflation calculation from gl_trans (Issue #2) |
| `InflationStats` | Statistical aggregation: mean/median/mode/stddev/trend (Issue #2) |

---

## 6. Inflation Factor Resolution Algorithm

```
getInflationFactor(gl_account_code):
    1. Check GL-specific rate (chart_master.account_code)
    2. Else check Type rate (chart_types.id, walk parent chain)
    3. Else check Category rate (chart_class.cid via type's class_id)
    4. Else return global default rate
```

### 6-1. Factor Types Mapping

| Factor Type | Reference Field | Source Table | Description |
|-------------|---------------|------------|-------------|
| `gl` | account_code | chart_master | Specific GL account override |
| `type` | id (int) | chart_types | Account type (with parent chain inheritance) |
| `category` | cid (int) | chart_class | High-level class (Assets, Income, Expenses) |
| `global` | - | constant | Default rate for all accounts |

---

## 7. Historical Inflation Calculation (Issue #2)

### 7-1. Data Flow

```
0_gl_trans (actuals by year)
    |
    v
InflationCalculator
    |-- calculateForGL(accountCode)       -> [{year, yoy_rate, actual_current, actual_prior}, ...]
    |-- calculateForCategory(categoryId)  -> aggregated YoY per year
    |-- calculateForClass(classId)        -> aggregated YoY per year
    |
    v
InflationStats (per-level aggregation)
    |-- getMean(), getMedian(), getMode()
    |-- getMin(), getMax(), getStdDev()
    |-- getTrendSlope() (linear regression)
    |-- getTrendIndicators() -> {1yr, 3yr, 5yr, 7yr, 10yr}
    |-- isWithinNorm($parentStats) -> bool
    |
    v
quickbudget_report.php (tabular + chart view)
    |-- Chart.js line/bar charts via quickbudget_inflation_api.php
    |-- Context display: GL shows category stats; category shows class stats
    |
    v
quickbudget_inflation_transfer.php
    |-- Preview diff (current config vs observed rate)
    |-- Confirm -> write to 0_ksf_quickbudget_factors
    |-- Invalidate resolved type cache
```

### 7-2. YoY Inflation Formula

```
yoy_rate = (currentYearActual - priorYearActual) / priorYearActual
```

- If priorYearActual is 0 or null: yoy_rate = null (excluded from averages)
- All available historical data is used (no year cap)
- 1/3/5/7/10 year indicators show CAGR or simple average for those periods

### 7-3. Aggregation Rules

- **GL level**: Direct calculation per account
- **Category level**: Sum all GL actuals in the category, then compute YoY on the aggregate
- **Class level**: Sum all category actuals in the class, then compute YoY on the aggregate
- Null values excluded from statistical calculations

---

## 8. Integration Points

| Integration | Method |
|-------------|--------|
| FA GL Accounts | Query `chart_master` for GL accounts |
| FA GL Types | Query `chart_types` for type hierarchy |
| FA GL Categories | Query `chart_class` for category grouping |
| FA Actuals | Query `gl_trans` for historical transactions |
| FA Budget | Query/write `budget_trans` for budget data |
| FA Security | `SA_KSF_QUICKBUDGETVIEW`, `SA_KSF_QUICKBUDGETMANAGE` |
| FA Hooks | `ksf_get_value`, `ksf_crud_event` listeners |
| Chart.js | CDN-loaded for line/bar charts (Issue #2) |
| FA PDF | `pdf_report.inc` for PDF generation (Issue #2) |
