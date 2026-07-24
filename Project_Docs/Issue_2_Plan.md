# Issue #2: Historical Inflation Analysis — Implementation Plan

**Feature:** Calculate apparent historical inflation from GL actuals
**GitHub:** https://github.com/ksfraser/ksf_FA_QuickBudget/issues/2
**Branch:** `feature/issue-2-historical-inflation`

---

## Resolved Design Decisions

| Question | Decision | Rationale |
|----------|----------|-----------|
| Data range | Use ALL available data | Longer trends are more useful; 1/3/5/7/10yr indicators still shown |
| Null handling | Exclude GLs with no data | New GLs likely represent shifted expenses, not new inflation |
| Transfer confirmation | Preview diff before commit | Avoids accidental rate changes |
| Chart library | Chart.js from CDN | Lightweight, no build step, good line/bar support |

---

## Sub-Issues (Implementation Order)

### Phase 1: Core Calculation Engine

#### 1A — Historical Inflation Calculator Service
**New file:** `src/Service/InflationCalculator.php`

Compute year-over-year inflation at three levels:
- **GL Account level:** `(yearN actual - yearN-1 actual) / yearN-1 actual`
- **Category level:** Sum all GL actuals in category, compute YoY on aggregate
- **Class level:** Sum all category actuals in class, compute YoY on aggregate

Methods:
- `calculateForGL(string $accountCode): array` — all available years
- `calculateForCategory(string $categoryId): array`
- `calculateForClass(string $classId): array`
- `calculateAll(string $level): array`
- `getAvailableYears(): array` — distinct years from gl_trans
- `getTrendIndicators(array $yearlyData): array` — 1/3/5/7/10yr values

Data source: `0_gl_trans` grouped by account + year.
Null handling: exclude from averages (FR-44).

#### 1B — Statistical Aggregation Service
**New file:** `src/Service/InflationStats.php`

Given an array of inflation values:
- Mean, Median, Mode, Min, Max
- Standard deviation
- Trend slope (linear regression over available years)
- Period values: 1yr, 3yr, 5yr, 7yr, 10yr (use what's available)
- `isWithinNorm(array $parentStats, float $stdDevBand = 1.0): bool`

#### 1C — New DB Table: Historical Rates Cache
**Add to `sql/install.sql`:**

```sql
CREATE TABLE IF NOT EXISTS `0_ksf_quickbudget_inflation_history` (
  `id` int(11) AUTO_INCREMENT,
  `level` enum('gl','category','class') NOT NULL,
  `reference_id` varchar(64) NOT NULL,
  `year` int(11) NOT NULL,
  `yoy_rate` decimal(10,4) DEFAULT NULL,
  `actual_total` decimal(16,2) DEFAULT NULL,
  `computed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY unique_history (level, reference_id, year),
  KEY idx_level_year (level, year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

Purpose: Cache computed historical rates so charts/reports don't recompute on every load.
Refreshed on-demand via "Recalculate" button.

---

### Phase 2: Report Page (Tabular)

#### 2A — Rewrite `quickbudget_report.php`
**Replace current single-year report with multi-year view.**

Layout:
1. **Filter bar:** Level selector (GL / Category / Class / All),
   Period selector (all available, 1/3/5/7/10yr), specific item dropdown
2. **Summary statistics card:** Mean, median, mode, min, max, std dev
   for the selected scope
3. **Data table:** Year-by-year rows with columns:
   Year | Prior Actual | Current Actual | YoY Rate | Status (normal/high/low)
4. **Transfer button** per row
5. **View toggle:** Tabular vs Chart mode

#### 2B — Context Display
When viewing a specific GL, show category-level 1/3/5/7/10/mean/median/mode stats below.
When viewing a specific category, show class-level stats below.
Flag whether each GL/category is within ±1 std dev of its parent (FR-48).

---

### Phase 3: Charts

#### 3A — Chart.js Integration
**New file:** `assets/inflation_charts.js`

Charts:
- **Line chart:** Year-by-year inflation rate for the selected item,
  with horizontal lines for mean, median, ±1 std dev band
- **Bar chart:** Distribution of inflation rates across all items
  in a category/class for the latest year

CDN: `https://cdn.jsdelivr.net/npm/chart.js`

#### 3B — Chart Data Endpoint
**New file:** `pages/quickbudget_inflation_api.php`

Returns JSON `{labels: [...], datasets: [{label, data}, ...]}` for Chart.js.
Accepts `?level=gl&reference_id=XXX` params.
Uses FR-56.

---

### Phase 4: PDF Export

#### 4A — PDF Generation
**New file:** `pages/quickbudget_inflation_pdf.php`

Uses FA's `pdf_report.inc` or `tcpdf`.
Produces a formatted PDF with:
- Title and filter summary
- Statistics table
- Year-by-year data table
- Footer with generation timestamp

#### 4B — Print Button
Add "Print to PDF" button on the report page that opens the PDF endpoint
with current filter parameters.

---

### Phase 5: Transfer to Config

#### 5A — Transfer Endpoint
**New file:** `pages/quickbudget_inflation_transfer.php`

Endpoint that accepts:
- `level` (gl/category/class)
- `reference_id`
- `year` OR statistic (mean/median/mode)
- Preview: returns diff (current rate vs observed rate)
- Confirm: writes observed rate to `0_ksf_quickbudget_factors`
- Invalidates resolved type cache

#### 5B — Bulk Transfer
When "All" is selected in the report, transfer button pushes observed
rates for all items at that level simultaneously.
Selector for which statistic (1yr, 3yr, 5yr, 7yr, 10yr, mean, median, mode).

---

## Implementation Dependencies

```
Phase 1 (engine) <- Phase 2 (report) <- Phase 3 (charts)
                                       <- Phase 4 (PDF)
Phase 1 (engine) <- Phase 5 (transfer)
```

Phases 2, 3, 4, 5 can be parallelized after Phase 1 completes.

---

## FR Mapping

| Sub-Issue | FR# | Description |
|-----------|-----|-------------|
| 1A | FR-37, FR-38, FR-39, FR-40, FR-44 | Calculate historical inflation at GL/category/class; use all data; exclude nulls |
| 1A | FR-41 | 1/3/5/7/10 year trend indicators |
| 1B | FR-42, FR-43 | Statistics: mean/median/mode/min/max/stddev; trend slope |
| 1C | FR-55 | DB cache table |
| 2A | FR-45, FR-49, FR-50 | Tabular display; filter; tabular+chart modes |
| 2B | FR-47, FR-48 | Context display; flag within norm |
| 3A | FR-46 | Chart.js charts |
| 3B | FR-56 | Chart data API endpoint |
| 4A | FR-54 | PDF export |
| 5A | FR-51, FR-57 | Transfer with preview; transfer endpoint |
| 5B | FR-52, FR-53 | Bulk transfer; statistic selector |
