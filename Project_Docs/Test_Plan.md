# Test Plan — ksf_FA_QuickBudget

**Version:** 1.0.0
**Date:** 2026-06-17
**Status:** Draft

---

## 1. Unit Tests

### 1-1. InflationFactorManager
| Test | Description |
|------|-------------|
| `testGetGlobalFactorReturnsDefault` | Verify default rate when no overrides exist |
| `testGetCategoryFactorOverridesGlobal` | Category factor takes precedence over global |
| `testGetGLFactorOverridesCategory` | GL-specific factor takes highest precedence |
| `testImportFactorsFromCSV` | CSV import creates factor records |

### 1-2. QuickBudgetService
| Test | Description |
|------|-------------|
| `testCalculateBudgetAppliesInflation` | Verify inflation applied correctly to actuals |
| `testCalculateBudgetSkipsZeroActuals` | GL accounts with no actuals are skipped |
| `testCalculateBudgetRespectsTimePeriod` | Correct months selected for target period |
| `testCalculateBudgetWithScenario` | Scenario multiplier applied correctly |

### 1-3. BudgetController
| Test | Description |
|------|-------------|
| `testCreateActionValidatesInput` | Bad input returns error response |
| `testCreateActionSavesToFABudget` | Native FA budget table updated |
| `testCompareActionReturnsJson` | Comparison data in correct format |

---

## 2. Integration Tests

| Test ID | Description | Reference |
|---------|-------------|-----------|
| INT-01 | End-to-end budget creation via AJAX | FR-09 |
| INT-02 | Inflation factor persistence across requests | FR-05 |
| INT-03 | Budget comparison query performance | FR-15 |
| INT-04 | Security check on protected endpoints | FR-23 |

---

## 3. Coverage Gaps

| Req ID | Gap Description |
|--------|-----------------|
| FR-19 | Color-coding CSS not implemented |
| FR-20-24 | Approval workflow not implemented |
| FR-25-28 | CSV export formatting not implemented |

---

## 4. Test Execution

```bash
# Run unit tests
php vendor/bin/phpunit tests/

# Run PHP lint on modified files
php -l pages/*.php
php -l includes/*.php
```