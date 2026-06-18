<?php
/**
 * KSF FrontAccounting Module Hooks 
 *
 * This file covers every standard FA module pattern plus the
 * KSF inter-module query hook system.
 *
 * STANDARD FA HOOKS:
 *   install_tabs()      — add a new top-level FA application tab
 *   install_options()   — add menu items under existing tabs
 *   install_access()    — register security sections and areas
 *   activate_extension()— run SQL install scripts on module activation
 *
 * KSF QUERY HOOK SYSTEM:
 *   ksf_get_value()     — respond to single-value queries from other modules
 *   ksf_get_values()    — respond to multi-value queries from other modules
 *   ksf_set_value()     — receive a value pushed from another module
 *
 * @package   ksf_FA_ksf_QuickBudget
 * @version   0.1.0
 */

// ---------------------------------------------------------------------------
// 1. Security section constant (unique per module)
// ---------------------------------------------------------------------------
// Shift value: pick a unique number not used by other modules.
// Range: 100-200 for KSF modules (below 100 reserved by FA core).
define('SS_ksf_FA_QuickBudget', 104 << 8);

// ---------------------------------------------------------------------------
// Ensure Composer autoloader is loaded before the class definition so that
// trait dependencies (HookQueryProviderTrait, CrudEventEmitterTrait, etc.)
// are available at class-load time.
// ---------------------------------------------------------------------------
$moduleAutoload = dirname(__FILE__) . '/vendor/autoload.php';
if (file_exists($moduleAutoload)) {
    require_once $moduleAutoload;
}

// ---------------------------------------------------------------------------
// 2. Main hooks class
// ---------------------------------------------------------------------------

class hooks_ksf_FA_QuickBudget extends hooks
{
    var $module_name = 'ksf_FA_QuickBudget';
    var $version     = '0.1.0';

    // KSF Query Hook System — implements ksf_get_value, ksf_get_values, ksf_set_value
    // via trait if available, otherwise provides minimal fallback
    public function ksf_get_value(&$key, $opts = null)
    {
        return $this->_ksf_get_value($key, $opts);
    }

    public function ksf_get_values(&$keys, $opts = null)
    {
        return $this->_ksf_get_values($keys, $opts);
    }

    public function ksf_set_value(&$data, $opts = null)
    {
        return $this->_ksf_set_value($data, $opts);
    }

    // Fallback implementations when trait not available
    protected function _ksf_get_value(&$key, $opts = null)
    {
        if ($key === 'quickbudget.version') {
            return $this->version;
        }
        return null;
    }

    protected function _ksf_get_values(&$keys, $opts = null)
    {
        $result = [];
        foreach ($keys as $key) {
            $value = $this->_ksf_get_value($key, $opts);
            if ($value !== null) {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    protected function _ksf_set_value(&$data, $opts = null)
    {
        return null; // No-op by default
    }

    // =======================================================================
    // 2a. install_tabs — Add a new top-level FA application tab
    //
    // Only override if your module adds a new tab to the FA navigation bar.
    // For most modules that add menu items to existing tabs, use
    // install_options() instead and leave this as a no-op.
    // =======================================================================
    function install_tabs($app)
    {
        // Example: add a new application tab
        // set_ext_domain('modules/ksf_FA_ksf_QuickBudget');
        // $app->add_application(new ksf_QuickBudget_app());
        // set_ext_domain();
    }

    // =======================================================================
    // 2b. install_options — Add menu items to existing FA app tabs
    //
    // Use switch($app->id) to target existing apps ('CRM', 'HR', 'Projects',
    // 'GL', 'AP', 'AR', 'Stock', 'Manufacturing', 'System', etc.).
    // =======================================================================
function install_options($app)
    {
        global $path_to_root;

        switch ($app->id) {
             case 'GL':
                  $app->add_lapp_function(2, _("Quick Budget"),
                      $path_to_root . "/modules/" . $this->module_name . "/quickbudget.php",
                      'SA_KSF_QUICKBUDGETVIEW', MENU_ENTRY);
                  break;
        }
    }

    // =======================================================================
    // 2c. install_access — Register security sections and areas
    //
    // Every module page needs a corresponding SA_ constant.
    // SA_KSF_QUICKBUDGETVIEW   — read access
    // SA_KSF_QUICKBUDGETMANAGE — write access
    // =======================================================================
    function install_access()
    {
        $security_sections[SS_ksf_FA_QuickBudget] = _("Quick Budget");

        $security_areas['SA_KSF_QUICKBUDGETVIEW'] = array(
            SS_ksf_FA_QuickBudget | 1,
            _("View Quick Budget")
        );
        $security_areas['SA_KSF_QUICKBUDGETMANAGE'] = array(
            SS_ksf_FA_QuickBudget | 2,
            _("Manage Quick Budget")
        );

        return array($security_areas, $security_sections);
    }

    // =======================================================================
    // 2d. activate_extension — Called on module install/upgrade
    //
    // Runs SQL install scripts from the sql/ subdirectory.
    // Uses @TB_PREF@ placeholder — FA's update_databases() substitutes it.
    // =======================================================================
    function activate_extension($company, $check_only = true)
    {
        $updates = array();

        if (file_exists(dirname(__FILE__) . '/sql/install.sql')) {
            $updates['install.sql'] = array('ksf_quickbudget_budget');
        }

        if (file_exists(dirname(__FILE__) . '/sql/update.sql')) {
            $updates['update.sql'] = array('ksf_quickbudget_budget');
        }

        if (!empty($updates)) {
            return $this->update_databases($company, $updates, $check_only);
        }

        return true;
    }

    // -----------------------------------------------------------------------
    // KSF Query Hook System + CRUD Event Listener stubs
    // -----------------------------------------------------------------------
    // Implemented above with fallback versions. Override for domain-specific queries.
    // -----------------------------------------------------------------------

    // =======================================================================
    // 4. Private helpers
    // =======================================================================

    /**
     * Return all values this module advertises for the query hook system.
     *
     * Each key is namespaced as "<module>.<value_name>" to prevent
     * collisions between modules. Values can be:
     *   - PHP constants (via defined() guard)
     *   - FA company preferences (via get_company_pref())
     *   - Module version strings
     *   - Configuration arrays
     *
     * @return array<string, mixed>
     */
    protected function _getAdvertisedValues(): array
    {
        return array(
            // ---- PHP constants (with defined() guard) ----
            // '<module>.api_version' => defined('<MODULE>_API_VERSION')
            //     ? constant('<MODULE>_API_VERSION') : null,

            // ---- FA company preferences ----
            // '<module>.some_pref' => function_exists('get_company_pref')
            //     ? get_company_pref('pref_name') : null,

            // ---- Metadata ----
            '<module>.version'     => $this->version,
            '<module>.module_name' => $this->module_name,
            '<module>.hooks_version' => '2.0',  // Helps consumers know which
                                                 // hook patterns are supported
        );
    }

    /**
     * Ensure Composer autoloader is available.
     *
     * Runs `composer install` on module activation if vendor/ is missing.
     * Safe to call from any method — checks file_exists first.
     *
     * @return void
     */
    private function ensure_composer_dependencies()
    {
        $module_dir    = dirname(__FILE__);
        $autoload_path = $module_dir . '/vendor/autoload.php';

        if (file_exists($autoload_path)) {
            return;
        }

        $composer_path = $module_dir . '/composer.json';
        if (!file_exists($composer_path)) {
            return;
        }

        chdir($module_dir);
        $output      = array();
        $return_code = 0;
        exec('composer install --no-interaction --prefer-dist 2>&1', $output, $return_code);
        if ($return_code !== 0) {
            error_log('KSF Module: composer install failed: ' . implode("\n", $output));
        }
    }
}

// ===========================================================================
// 5. Application class — Only needed if install_tabs() adds a new tab
// ===========================================================================
// Uncomment and customise if your module creates a new top-level FA tab.
//
// class ksf_QuickBudget_app extends application {
//     function __construct() {
//         parent::__construct("<TabId>",
//             _($this->help_context = "&<TabLabel>"));
//
//         $this->add_module(_("<SectionName>"));
//         $this->add_lapp_function(0, _("&Page Title"),
//             "modules/ksf_FA_ksf_QuickBudget/page.php",
//             'SA_KSF_QUICKBUDGETVIEW', MENU_MAIN);
//
//         $this->add_extensions();
//     }
// }
