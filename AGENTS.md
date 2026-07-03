# AGENTS.md - ksf_FA_QuickBudget#

## Architecture Overview#

**FA Module** for Quickly creating this year's budget using last year's actuals.  
** repository** containing init SQL  for the FA ecosystem.

### Core Principles#
- **SOLID**
- **DRY**
- **TDD**
- **DI**
- **SRP**#
- **Versioned**: All infrastructure is version-controlled

### API Response Pattern#
- All AJAX/API endpoints must emit JSON through one guarded request wrapper.
- Convert PHP warnings/notices to exceptions, discard stray buffered output, and return a single JSON error object on failure.
- Never allow hooks or legacy FA output to write directly to the response body for API routes.

## Repository Structure#

```
ksf_FA_QuickBudget/
├── sql/#
│   ├── fa_TBD.sql#
├── includes/#
│   ├── TBD.inc#
├── pages/#
├── hooks.php#
├── composer.json#
└── ProjectDocs/#
    ├── Requirements.md
    └── Architecture.md
```

## Dependencies#

- **FrontAccounting 2.4+**#

## Development Workflow

All development is done in the **devel tree** (`/home/ksf_quickbudget/ksf_FA_QuickBudget`). Do **not** edit files in the UAT bind point directly.

### Workflow Steps
1. **Develop** in this repo (feature branches preferred)
2. **Test**: run repo-appropriate tests
3. **Lint**: `php -l` on modified PHP files (no syntax errors)
4. **Commit** and **Push** branch to GitHub
5. **Merge** to `master` when ready
6. **Push** `master` to GitHub
7. **Deploy** to UAT by pulling in the Infrastructure bind point:

```
    cd /home/kevin/Documents/ksf_Infrastructure/fa_modules/ksf_FA_QuickBudget
    git stash -u
    git pull origin master
    git stash pop
    ```

### UAT Bind Point
| Path | Purpose |
|------|---------|
| `/home/ksf_quickbudget/ksf_FA_QuickBudget` | Devel tree — all development, testing, commits |
| `/home/kevin/Documents/ksf_Infrastructure/fa_modules/ksf_FA_QuickBudget` | UAT bind point — deployment target, integration testing |


## FA DB Layer — Correct API

FA's `$db` is a raw `mysqli` object. **Do not** call `$db->query($sql, $params)`.

```php
// CORRECT — use FA procedural wrappers
global $db;
$escaped = mysqli_real_escape_string($db, $value);  // escape params manually
$result  = db_query("SELECT * FROM " . TB_PREF . "table WHERE col='" . $escaped . "'");
$row     = db_fetch_assoc($result);   // returns false (not null) when exhausted
$count   = db_num_rows($result);
$id      = db_insert_id();            // handles sql_trail case
$aff     = db_num_affected_rows();

// db_query() substitutes TB_PREF via str_replace — multi-company safe
// db_escape() is NOT safe for SQL params — it HTML-decodes/encodes first
```

## FA Page Security — CRITICAL for Module Pages

FA module pages are standalone PHP files accessed directly as URLs
(e.g. `/modules/ksf_FA_Calendar/cal.php`) — they are NOT included from `index.php`.

`page_header()` calls `check_page_security($page_security)` which looks up
`$security_areas[$page_security]`. Extension module security areas (registered
via `hooks.php`) are only populated by `add_access_extensions()`. Stock FA only
calls this from `index.php` and `admin/security_roles.php`.

**Every module page entry script must call `add_access_extensions()` after
`session.inc` and before `page_header()`:**

```php
$path_to_root = "../..";
include_once($path_to_root . "/includes/session.inc");
add_access_extensions();   // ← required on every direct-access module page
// ... set $page_security, include header.inc, call page_header() ...
```

Without this call, `can_access_page()` returns false for all extension areas
and the user sees a security error (~855 bytes blank page).

## KSF Inter-Module Query Hook System

Module entry-point scripts define constants and config that are unavailable
when hooks.php is loaded (install_hooks() runs early in session.inc, before
any page script is reached). To solve this, KSF modules implement a
standardised hook-based query protocol:

### Defined Hook Names

| Hook Name | FA Function | Direction | Purpose |
|---|---|---|---|
| `ksf_get_value` | `hook_invoke_first` | Consumer → Provider | Query a single named value |
| `ksf_get_values` | `hook_invoke_all` | Consumer → All Providers | Query multiple values |
| `ksf_set_value` | `hook_invoke_all` | Sender → All Modules | Push a value / notify |

### Consumer Pattern (any module page or service)

```php
// Single value — use a variable (FA passes $data by reference)
$key = 'calendar.api_version';
$apiVersion = hook_invoke_first('ksf_get_value', $key);
if ($apiVersion !== null) {
    // calendar module is installed and responded
}

// Multiple values — same rule, no array literals by reference
$queryKeys = ['calendar.api_version', 'rbac.hooks_version'];
$all = hook_invoke_all('ksf_get_values', $queryKeys);
```

### Provider Pattern (in hooks.php)

Use `Ksfraser\Traits\HookQueryProviderTrait` (published in `ksfraser/traits`):

```php
class hooks_ksf_FA_MyModule extends hooks {
    use \Ksfraser\Traits\HookQueryProviderTrait;

    protected function _getAdvertisedValues(): array
    {
        return array(
            'my_module.version'  => '1.2.0',
            'my_module.api_key'  => defined('MY_API_KEY') ? MY_API_KEY : null,
            'my_module.pref'     => function_exists('get_company_pref')
                ? get_company_pref('my_pref') : null,
        );
    }
}
```

The trait provides `ksf_get_value()`, `ksf_get_values()`, and
`ksf_set_value()` — no need to implement them manually.

### Key Namespacing Convention

Keys MUST be namespaced as `<module>.<value_name>` to prevent collisions
(e.g. `calendar.api_version`, `rbac.hooks_version`).


### Full Template

A ready-to-copy hooks.php template with all patterns is at:
`doc/templates/hooks-template.php`

### Extending for Module-Specific Queries

Beyond the generic `ksf_get_value` pattern, modules may register
domain-specific hook names for richer queries:

```php
// In hooks.php
function calendar_entry_create(&$data, $opts = array()) { ... }
function calendar_entries_query(&$data, $opts = array())  { ... }
```

These follow FA's standard convention: `&$data` passed by reference,
`$opts` for context, return null for "not handled".

### Why Not a Service Locator / DI Container?

- FA has no DI container and adding one is a breaking change.
- `hook_invoke_first` / `hook_invoke_all` are already available and tested.
- The pattern works identically in FA 2.4+ without any core modifications.
- Modules that don't implement a given hook simply don't respond — no crash.

---

## KSF CRUD Event Hook System

FA's `hook_invoke_all()` enables any module to react when another module
creates, updates, or deletes a record. The KSF framework standardises this
with a two-level dispatch pattern.

### Hook Names

| Hook Name | Dispatch | Purpose |
|---|---|---|
| `<module>_<action>_<recordType>` | `hook_invoke_all` | Targeted — only interested modules implement |
| `ksf_crud_event` | `hook_invoke_all` | Broadcast — all modules receive the full payload |

**Actions**: `created`, `updated`, `deleted`

### Emitter Pattern (service or page script)

Use `Ksfraser\Traits\CrudEventEmitterTrait` in any service class:

```php
use Ksfraser\Traits\CrudEventEmitterTrait;

class CalendarService {
    use CrudEventEmitterTrait;

    public function createEntry(array $data): int {
        $id = $this->repo->insert($data);
        $this->emitCreated('calendar', 'entry', $id, $data);
        return $id;
    }
}
```

### Listener Pattern (in hooks.php)

```php
class hooks_ksf_FA_SomeModule extends hooks {

    // Specific listener — only fires for calendar_created_entry
    function calendar_created_entry(&$payload, $opts = []) {
        $entryId = $payload['record_id'];
        // create a related record in this module
    }

    // Generic listener — catches all CRUD events from any module
    function ksf_crud_event(&$payload, $opts = []) {
        if ($payload['action'] === 'deleted' && $payload['module'] === 'crm') {
            // clean up related data
        }
    }
}
```

### Payload Structure

```php
$payload = [
    'action'      => 'created',        // string: created|updated|deleted
    'module'      => 'calendar',       // string: module slug
    'record_type' => 'entry',          // string: record type slug
    'record_id'   => 42,               // int|string: primary key
    'data'        => [...],            // array: additional context
];
```

### Comparison: FA Native vs KSF CRUD Events

| Aspect | FA `db_prewrite`/`db_postwrite` | KSF `ksf_crud_event` |
|--------|----------------------------------|----------------------|
| Scope | Core FA tables (`0_debtors_master`, etc.) | Any module's custom tables |
| Module tables | Not fired (bypassed) | Primary use case |
| Granularity | Table-level | Record-type + action |
| Trait available | No | `CrudEventEmitterTrait` |

### See Also

- `ksfraser/traits` — `Ksfraser\Traits\CrudEventEmitterTrait`
- `doc/templates/hooks-template.php` — ready-to-copy hooks.php with CRUD stubs

---

## FA install.sql / Schema Convention

**Use `0_` as the table prefix literal in all SQL files.**

`db_import()` (called by `update_databases()`) substitutes `0_` with the actual
company table prefix via `str_replace("0_", $connection["tbpref"], $line)`.
**`@TB_PREF@` and `{TB_PREF}` are NOT recognised — they are NOT substituted.**

```sql
-- CORRECT
CREATE TABLE IF NOT EXISTS `0_ksf_integrity_log` ( ... );

-- WRONG — these are silently treated as literal table names
CREATE TABLE IF NOT EXISTS `@TB_PREF@ksf_integrity_log` ( ... );
CREATE TABLE IF NOT EXISTS `{TB_PREF}ksf_integrity_log` ( ... );
```

### `activate_extension()` Table-Check Convention

The second element of each update array is the **bare table name** (without prefix)
that `check_table()` uses to determine whether the SQL has already been applied:

```php
function activate_extension($company, $check_only = true)
{
    // 'install.sql' => array('<bare_table_name_to_probe>')
    $updates = array(
        'install.sql' => array('ksf_integrity_log'),
    );
    return $this->update_databases($company, $updates, $check_only);
}
```

If `{tbpref}ksf_integrity_log` exists the SQL is skipped; otherwise it runs.
Pass the **first (or most diagnostic) table** created by the SQL file.

---

## FA Module `_init/config` Convention

Every FA module **must** have `_init/config` as a **gzip-compressed** file in
`Key: Value` format. Plain-text or `ini`-style files (`name=value`) are **not**
read correctly by FA's `get_control_file()`.

### Canonical format

```
Name: ksf_FA_<ModuleName>
Version: <FA_version>-<build>
Description: KSF FrontAccounting Module
```

- `<FA_version>` must match the FA version the module targets (e.g. `2.4.3`)
- `<build>` is a zero-based integer incremented on each module release (`0`, `1`, …)
- **Current required minimum**: `2.4.3-0`

### How to create / update

```bash
# Create
printf 'Name: ksf_FA_MyModule\nVersion: 2.4.3-0\nDescription: KSF FrontAccounting Module\n\n' \
  | gzip -9 > _init/config

# Verify
zcat _init/config
```

---

## FA hooks.php — Canonical Class Pattern

Every module **must** have a `hooks_<module_name> extends hooks` class.
Plain functions (`hook_menu_insert`, `hook_db_install`, etc.) are the old FA 2.3
style and do **not** support install tabs, security areas, or DB activation.

```php
<?php
// Security section — pick a unique number not used by any other module.
// Core FA uses 1–53. KSF modules start at 114. Increment by 1 for each new module.
// Current highest: SS_WORKFLOW = 143. Next available: 144.
define('SS_ksf_FA_MyModule', 144 << 8);

class hooks_ksf_FA_MyModule extends hooks
{
    var $module_name = 'ksf_FA_MyModule';  // must match the directory name
    var $version     = '1.0.0';

    /**
     * Register application tab(s) in FA's main menu.
     */
    function install_tabs($app)
    {
        set_ext_domain('modules/ksf_FA_MyModule');
        $app->add_application(new mymodule_app());
        set_ext_domain();
    }

    /**
     * Declare security areas. Return [ $security_areas, $security_sections ].
     */
    function install_access()
    {
        $security_sections[SS_ksf_FA_MyModule] = _("My Module");
        $security_areas['SA_MYMODULE_VIEW'] = array(
            SS_ksf_FA_MyModule | 1, _("View My Module"),
        );
        $security_areas['SA_MYMODULE_MANAGE'] = array(
            SS_ksf_FA_MyModule | 2, _("Manage My Module"),
        );
        return array($security_areas, $security_sections);
    }

    /**
     * Run sql/install.sql when the module is activated.
     * $check_only=true means probe only (used by the extension manager UI).
     */
    function activate_extension($company, $check_only = true)
    {
        $updates = array(
            'install.sql' => array('ksf_mymodule_records'),
        );
        return $this->update_databases($company, $updates, $check_only);
    }
}

class mymodule_app extends application
{
    function __construct()
    {
        parent::__construct("MyModule", _($this->help_context = "&My Module"));

        $this->add_module(_("My Module"));
        $this->add_lapp_function(
            0, _("&Overview"),
            "modules/ksf_FA_MyModule/pages/overview.php",
            'SA_MYMODULE_VIEW', MENU_INQUIRY
        );
        $this->add_extensions();
    }
}
```

### `add_lapp_function` menu type constants

| Constant | FA Menu Section |
|---|---|
| `MENU_MAIN` | Main entry (top of sub-menu) |
| `MENU_ENTRY` | Data entry form |
| `MENU_INQUIRY` | Inquiry / report (read-only) |
| `MENU_REPORT` | Report |
| `MENU_SETTINGS` | Configuration |

### HookQueryProviderTrait — IMPORTANT WARNING

`\Ksfraser\Traits\HookQueryProviderTrait` requires `vendor/autoload.php` to be
loaded at hooks-load time. **Do NOT** add `use \Ksfraser\Traits\HookQueryProviderTrait`
to a `hooks.php` class unless `vendor/autoload.php` is unconditionally included at the
top of hooks.php **and** the vendor directory is present in the deployed module.

If `vendor/` is absent (clean clone, no `composer install`) the `require_once` will
fatal-error and crash FA's bootstrap for **all** pages — not just the module's own pages.

Safe alternative: load `vendor/autoload.php` lazily inside the individual hook methods
that actually need Composer classes, guarded by `file_exists()`:

```php
function my_hook_method(&$data, $opts = array())
{
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        return null; // Composer deps not installed — skip gracefully
    }
    require_once $autoload;
    // ... use namespaced classes here ...
}
```

## CRM Tag Type Constants

The CRM module defines tag types extending FA's `0_tags` + `0_tag_associations` tables. These are managed via `ksf_FA_CRM/pages/crm_tags.php` (not FA's `admin/tags.php`).

| Constant | Value | Entity | DB Table |
|----------|-------|--------|----------|
| `TAG_CUSTOMER` | 3 | Customer | `debtors_master` |
| `TAG_CONTACT` | 4 | Contact | `crm_contacts` |
| `TAG_OPPORTUNITY` | 5 | Opportunity | `crm_opportunities` |
| `TAG_LEAD` | 6 | Lead | `crm_leads` |
| `TAG_COMMUNICATION` | 7 | Communication | `crm_communications` |

Usage in pages (uses FA's existing tag helpers):
```php
include_once($path_to_root . "/admin/db/tags_db.inc");
include_once($path_to_root . "/modules/ksf_FA_CRM/includes/crm_tags.inc");

// Load existing tags
$tags_result = get_tags_associated_with_record(TAG_CUSTOMER, $entity_id);
$tagids = array();
while ($tag = db_fetch($tags_result))
    $tagids[] = $tag['id'];
$_POST['entity_tags'] = $tagids;

// Render tag selector
tag_list_row(_("Tags:"), 'entity_tags', null, TAG_CUSTOMER, true);

// Save
update_tag_associations(TAG_CUSTOMER, $entity_id, $_POST['entity_tags']);
```

## CRM Module Split

The monolith `ksf_CRM` has been split into two repositories:

| Repository | Type | Namespace | Contents |
|-----------|------|-----------|---------|
| `ksf_CRM` | Business logic | `Ksfraser\CRM\*` | Entities, services, events — no FA deps |
| `ksf_FA_CRM` | FA adapter | `Ksfraser\FA\CRM\*` | hooks.php, pages/, includes/, sql/ |

## Dependencies

- **FrontAccounting 2.4+**
- **MariaDB 10.5+**
- **PHP 7.4** (hard constraint — no PHP 8+ syntax)

