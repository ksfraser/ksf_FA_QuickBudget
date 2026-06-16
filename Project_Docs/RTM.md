# Requirements Traceability Matrix — ksf_FA_QuickBudget

**Version:** 1.0.0
**Date:** 2026-06-16
**Status:** Active

---

## 1. Purpose

This RTM traces each business requirement (BR) and functional requirement (FR) through to the
source code, unit/integration tests, and UAT test cases that verify it. Any row without a
UAT case or test reference represents a gap that must be addressed before production release.

---

## 2. Column Definitions

| Column | Description |
|--------|-------------|
| **Req ID** | Requirement identifier from Business Requirements or Functional Requirements doc |
| **Requirement** | Short statement of the requirement |
| **Implementation** | Source file(s) and class/function that realise the requirement |
| **Unit / Integration Test** | Test class + test method(s) that verify the implementation |
| **UAT Case** | UAT test case ID(s) from the UAT Plan |
| **Status** | Implemented / Partial / Not Started |

---

## 3. Business Requirements

| Req ID | Requirement | Implementation | Unit Test | UAT Case | Status |
|--------|-------------|---------------|-----------|----------|--------|
| BC-01 | PHP 7.4 minimum; no PHP 8+ syntax | All source files — `declare(strict_types=1)`, no readonly/match/named args | CI PHP version check | — | Implemented |
| BC-02 | FA hook/event integration | `hooks.php` — `hooks_ksf_FA_Calendar` class | `HooksTest` (29 tests) | UAT-01 | Implemented |
| BR-09 | Entry access control: Edit/Delete buttons conditionally shown | `cal_ajax_get_entry()` returns `can_edit`/`can_delete` booleans; JS in `openDetailPanel()` shows/hides buttons | `FA_Cal_ModuleTest::testGetEntryReturnsCanEditCanDelete` (pending) | UAT-60–UAT-68 | Implemented |
| BR-09a | Edit button: assigned_to, creator, invitee, or MANAGE role | `cal_can_edit_entry()` in `cal.php` | `FA_Cal_ModuleTest::testCanEditEntry*` (pending) | UAT-60–UAT-64 | Implemented |
| BR-09b | Delete button: assigned_to or MANAGE role only | `cal_can_delete_entry()` in `cal.php` | `FA_Cal_ModuleTest::testCanDeleteEntry*` (pending) | UAT-65–UAT-68 | Implemented |

---

## 4. Functional Requirements

### FR-01–FR-06: Calendar Display

| Req ID | Requirement | Implementation | Unit Test | UAT Case | Status |
|--------|-------------|---------------|-----------|----------|--------|
| FR-01 | FullCalendar v5 embedded | `cal.php` — `cal_view_calendar()`; local assets | — | UAT-01 | Implemented |
| FR-02 | Month/week/day/list views | FullCalendar `headerToolbar` config in `cal.php` | — | UAT-06–UAT-08 | Implemented |
| FR-03 | User-configurable default view | `FA_Calendar_Module::get_user_preferences()` / `save_user_preferences()` | `HooksTest` | UAT-49 | Implemented |
| FR-04 | User-configurable week start | Preferences stored per user; passed to FullCalendar `firstDay` | `HooksTest` | UAT-50 | Implemented |
| FR-05 | Events loaded via AJAX | `cal_ajax_get_events()`, FullCalendar `events` URL feed | `HooksTest::testCalendarEntriesQueryHookExists` | UAT-01 | Implemented |
| FR-06 | Sources: PM, CRM, HRM, client dates, user | Hook `calendar_entries_query`; `fa_cal_sources` | `HooksTest` | UAT-01 | Implemented |

---

## 5. Traceability Summary

| Category | Total Reqs | Fully Traced | Partial (test gap) | Not Started |
|----------|-----------|-------------|-------------------|-------------|
| Business Requirements (BG/BR/BC) | 27 | 23 | 4 | 0 |
| Functional Requirements (FR) | 91 | 85 | 6 | 0 |
| **Total** | **118** | **108** | **10** | **0** |

All partial items are in the "Implemented (test pending)" state — feature code exists and
the manual test can verify it, but the automated unit/integration tests have not yet been
written. See Test Plan §4 (Coverage Gaps) and §5 (New Tests Required) for the full list.

---

## 6. Document Cross-References

| Document | Location |
|----------|----------|
| Business Requirements | `Project_Docs/Business_Requirements.md` |
| Functional Requirements | `Project_Docs/Functional_Requirements.md` |
| Architecture | `Project_Docs/Architecture.md` |
| Use Cases | `Project_Docs/Use_Case.md` |
| Test Plan | `Project_Docs/Test_Plan.md` |
| UAT Plan | `Project_Docs/UAT_Plan.md` |
