# FA Module Development — Patterns & Conventions

## Module Bootstrap (quickbudget.php entry point)

```
chdir(__DIR__);                          // resolve includes from module dir
$page_security = 'SA_KSF_QUICKBUDGETVIEW'; // set BEFORE session.inc
include_once($path_to_root . "/includes/session.inc"); // boot FA: $db, TB_PREF, page()
add_access_extensions();                 // register extension security areas
require_once __DIR__ . '/FA_QuickBudget_Module.php'; // load module class + Composer autoload
```

### Session & Security
- `$_SESSION['wa_current_user']->user` — current user ID (int)
- `$_SESSION['wa_current_user']->can_access('SA_xxx')` — permission check (FA 2.4.3; **not** `has_access`)
- `$_SESSION['wa_current_user']->email`, `->real_name` — user details
- `$page_security` must be set before `session.inc` is included
- Extension security areas: `SS_ksf_FA_QuickBudget` (section), `SA_ksf_FA_QuickBudgetVIEW`, `SA_ksf_FA_QuickBudgetMANAGE`
- `add_access_extensions()` must be called after `session.inc` for extension areas to work

### Module Page Pattern
1. `chdir(__DIR__)`
2. Set `$page_security`
3. `include session.inc` (boots FA framework)
4. `add_access_extensions()`
5. `page(_("Title"), false, false, '', $js);` ... `end_page();`

## AJAX / API Pattern

### Session-Expiry Guard
AJAX requests MUST set a session-expiry interceptor BEFORE session.inc:
```php
$_ksf_quickbudget_is_ajax = isset($_GET['action']) && $_GET['action'] !== '';
if ($_ksf_quickbudget_is_ajax) {
    ob_start(function ($html) {
        if (strpos($html, 'user_name_entry_field') !== false) {
            if (!headers_sent()) {
                http_response_code(401);
                header('Content-Type: application/json');
            }
            return json_encode(array('error' => 'session_expired'));
        }
        return $html;
    });
}
```

### FA's Inner Buffer (fmt_errors discard)
After session.inc, discard FA's level-2 `output_html` buffer so PHP warnings don't corrupt JSON:
```php
if ($_ksf_quickbudget_is_ajax) {
    while (ob_get_level() > 1) {
        ob_end_clean();
    }
}
```

### quickbudget_api_run() Wrapper
All AJAX handlers should be wrapped by `quickbudget_api_run()` which:
- Converts PHP warnings/notices to exceptions
- Captures output buffer
- Validates JSON before sending
- Discards FA footer via shutdown function
- Returns `{"error":"..."}` on failure

### Request Payload
```php
$data = quickbudget_request_payload(); // reads php://input for JSON, $_POST for form
```

### JSON Response
```php
\Ksfraser\FA\QuickBudget\Support\JsonResponse::send($result); // outputs JSON + exits
```

## Hook System

### hook_invoke_first vs hook_invoke_all
- `hook_invoke_first("quickbudget_entry_create", $data)` calls each registered hook until one returns non-null
- `hook_invoke_first` is an FA framework function (defined in FA core, not in module code)
- Other modules can register hooks to intercept module operations

### Module Hook Registration (hooks.php)
The `hooks_ksf_FA_QuickBudget` class (extends `hooks`) defines hook methods. These are auto-discovered by FA:
- `install_tabs($app)` — register application tab
- `install_access()` — return `[$security_areas, $security_sections]`
- `activate_extension($company, $check_only)` — SQL install/upgrade

### Hook Methods (convention)
Named by operation, called by `hook_invoke_first`:
- `quickbudget_entry_create(&$data, $opts = [])` → calls `FA_QuickBudget_Module::instance()->create_entry($data)`
- `quickbudget_entry_update(&$data, $opts = [])` → calls `FA_QuickBudget_Module::instance()->update_entry($id, $data)`
- `quickbudget_entry_delete(&$data, $opts = [])` → calls `FA_QuickBudget_Module::instance()->delete_entry($id)`
- `quickbudget_entries_query(&$data, $opts = [])` → calls `FA_QuickBudget_Module::instance()->get_entries()`
- Return null = "not handled" (hook_invoke_first continues), non-null = stops iteration

### Hook Callback Convention
Methods receive `&$data` by reference (mutated for results) and `$opts` (optional context). Return non-null to claim the hook.

## Module Class Pattern (FA_QuickBudget_Module)

### Singleton
```php
class FA_QuickBudget_Module {
    private static $instance = null;
    public static function instance(): self { ... }
}
```

### Event Controller Pattern
```php
$controller = new \Ksfraser\FA\QuickBudget\Controller\EventController(
    module: \FA_QuickBudget_Module::instance(),
    userId: (int) $_SESSION['wa_current_user']->user,
    hookInvoker: fn($name, $payload) => hook_invoke_first($name, $payload) // optional
);
$result = $controller->create($data);
```

### EventController methods
- `create(array $data): array` — sanitizes, validates, creates entry, reconciles invitees
- `update(int $id, array $data): array` — permission check, edit-scope handling
- `delete(int $id): array` — full delete

## Permission Model

```php
// Module-level (FA security areas):
$_SESSION['wa_current_user']->can_access('SA_KSF_QUICKBUDGETMANAGE')

// Entry-level (quickbudget.php):
function quickbudget_can_edit_entry(array $entry, int $userId): bool
function quickbudget_can_delete_entry(array $entry, int $userId): bool
// Allowed if: MANAGE access OR assigned_to == userId OR user_id == userId
```

## Budget Service

```php
$service = FA_QuickBudget_Module::instance()->getBudgetService();
```

## DB / SQL Patterns

- Tables: `0_ksf_quickbudget_factors`, `0_ksf_quickbudget_scenarios`
- `db_query($sql, 'Error message')` — second arg is error context; pass `null` to suppress display_db_error exit
- `TB_PREF` — table prefix constant (usually empty string in modern FA)


## Integration Tests (curl-based)

```php
// Cookie-based auth via exec curl (PHP libcurl cookie handling is incompatible with FA):
$resp = $this->execCurl('POST', '/index.php',
    'user_name_entry_field=user&password=pass&company_login_name=0&Submit=Login');
// Use -c / -b cookie jar, -D header file, --data for POST
// FA_INTEGRATION_BASE_URL env var (default http://localhost:8080)
```

## Namespace / Class Loader

- Composer autoloader booted from `hooks.php` and `FA_Cal_Module.php` via `require_once __DIR__ . '/vendor/autoload.php'`
- Module class: `FA_QuickBudget_Module` (alias `FA_Cal_Module`)
- Namespaces: `Ksfraser\FA\QuickBudget\Controller`, `Ksfraser\FA\QuickBudget\View`, `Ksfraser\FA\QuickBudget\Support`
- Third-party: `KsfCommon\ContactType\ContactTypeRegistry`

## Key "Gotchas"

1. **Buffer ordering**: session.inc calls `ob_start('output_html')` (level 2). For AJAX, discard this inner buffer BEFORE returning JSON, or warnings/notices will be rendered as HTML and corrupt the response.
2. **PHP 7.4**: target FA container runs 7.4 — no match expressions, no readonly properties, `: mixed` return type only from PHP 8.0
3. **`db_query` error message**: passing a non-null second arg triggers `display_db_error()` which calls `exit` with HTML output. Pass `null` to get `false` return on failure.
4. **`hook_invoke_first` vs direct module call**: hooks allow other modules to intercept operations. `EventController.create()` calls `$module->create_entry()` directly (not via hook), while `EventController.update()`/`delete()` use `invokeHook()` which CAN use `hook_invoke_first` if a hookInvoker is provided.
5. **Double-conversion bug**: `convertAllDay()` converts bool → 'yes'/'no'. If called on already-converted data, `'no' ? 'yes' : 'no'` → `'yes'` (all non-all-day events become all-day). Always convert once before any validation/save.
6. **Reconciliation key presence**: the inline update handler reconciles invitees when the `invitees`/`invitees_json` key is present (even empty array). EventController's handlers check `$reconcileInvitees` flag (passed from `update()`) to match this behavior.
