# UAT Plan — ksf_FA_QuickBudget

**Version:** 1.0.0
**Date:** 2026-06-17
**Status:** Draft

---

## UAT Test Cases

| Case ID | Use Case | Preconditions | Test Steps | Expected Result |
|---------|----------|---------------|------------|-----------------|
| UAT-01 | UC-01, UC-02 | User has SA_KSF_QUICKBUDGETMANAGE | 1. Navigate to QuickBudget page<br>2. Select budget period (Jan-Dec)<br>3. Click "Generate Budget"<br>4. Confirm budget saved | Budget entries created for all GL accounts with actuals |
| UAT-02 | UC-02 | Existing budget for current year | 1. Navigate to QuickBudget page<br>2. It is June (month 6)<br>3. Select "Re-create Budget"<br>4. System prompts: "Use actuals for months 1-5 or keep existing?"<br>5. Select option and generate | Months 1-5 preserved if selected; months 6-12 recalculated |
| UAT-03 | UC-03 | User has MANAGE permission | 1. Navigate to Configuration<br>2. Set global rate to 3.5%<>3. Set category rate for "Expenses" to 5%<br>4. Set GL "6000" to 10%<br>5. Save configuration | Rates saved correctly; GL 6000 uses 10% (highest precedence) |
| UAT-04 | UC-04 | - | 1. Navigate to QuickBudget page<br>2. Select "Optimistic" scenario (0.9x)<br>3. Generate budget | Budget amounts are 90% of calculated values |
| UAT-05 | UC-05 | Budget exists for current year | 1. Navigate to Comparison page<br>2. View report<br>3. Filter by GL range<br>4. Export variances | Comparison shows actuals, budget, variance with color coding |
| UAT-06 | UC-06 | Approval workflow enabled | 1. Create budget with approval required<br>2. Login as approver<br>3. Approve budget<br>4. Verify audit trail | Budget status changes to approved; timestamps recorded |
| UAT-07 | UC-07 | Budget exists | 1. Navigate to Export page<br>2. Select "Budget + Comparison"<br>3. Export to CSV | CSV file contains all budget and variance columns |