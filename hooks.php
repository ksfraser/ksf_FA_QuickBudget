<?php
/**
 * KSF FrontAccounting Module Hooks 
 *
 * This file covers every standard FA module pattern plus the
 * KSF inter-module query hook system.
 *
 * STANDARD FA HOOKS:
 *   install_tabs()      -- add a new top-level FA application tab
 *   install_options()   -- add menu items under existing tabs
 *   install_access()    -- register security sections and areas
 *   activate_extension()-- run SQL install scripts on module activation
 *
 * KSF QUERY HOOK SYSTEM:
 *   ksf_get_value()     -- respond to single-value queries from other modules
 *   ksf_get_values()    -- respond to multi-value queries from other modules
 *   ksf_set_value()     -- receive a value pushed from another module
 *
 * @package   ksf_FA_QuickBudget
 * @version   0.1.0
 */

// ---------------------------------------------------------------------------
// 1. Security section constant (unique per module)
// ---------------------------------------------------------------------------
// Shift value: pick a unique number not used by other modules.
// Range: 100-200 for KSF modules (below 100 reserved by FA core).
define('SS_ksf_FA_QuickBudget', 104 << 8);

// Ensure composer dependencies before loading trait
$moduleDir = dirname(__FILE__);
require_once $moduleDir . '/includes/ComposerDependencies.php';
ComposerDependencies::ensure($moduleDir);

// Load autoloader for trait
$moduleAutoload = $moduleDir . '/vendor/autoload.php';
if (file_exists($moduleAutoload)) {
    require_once $moduleAutoload;
}

// Define fallback trait if ksfraser/traits not available
if (!trait_exists('\Ksfraser\Traits\HookQueryProviderTrait')) {
    trait HookQueryProviderTrait
    {
        public function ksf_get_value(&$key, $opts = null)
        {
            $values = $this->_getAdvertisedValues();
            return array_key_exists($key, $values) ? $values[$key] : null;
        }

        public function ksf_get_values(&$keys = null, $opts = null)
        {
            $values = $this->_getAdvertisedValues();
            if (empty($keys)) {
                return $values;
            }
            return array_intersect_key($values, array_flip($keys));
        }

        public function ksf_set_value(&$data, $opts = null)
        {
            // Default no-op
        }
    }
}

// ---------------------------------------------------------------------------
// 2. Main hooks class
// ---------------------------------------------------------------------------

class hooks_ksf_FA_QuickBudget extends hooks
{
    use HookQueryProviderTrait;

    var $module_name = 'ksf_FA_QuickBudget';
    var $version     = '0.1.0';

    /**
     * Return advertised values for the KSF query hook system.
     * Keys are namespaced as "quickbudget.<value_name>".
     */
    protected function _getAdvertisedValues(): array
    {
        return array(
            'quickbudget.version'     => $this->version,
            'quickbudget.module_name' => $this->module_name,
            'quickbudget.hooks_version' => '2.0',
        );
    }

    // -----------------------------------------------------------------------
    // 3. Standard FA hooks
    // -----------------------------------------------------------------------

    /**
     * install_tabs : Add a new top-level FA application tab.
     * Only override if your module adds a new tab to the FA navigation bar.
     */
    function install_tabs($app)
    {
        // No custom tab - using install_options under GL instead
    }

    /**
     * install_options : Add menu items to existing FA app tabs.
     */
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

    /**
     * install_access : Register security sections and areas.
     */
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

    /**
     * activate_extension : Called on module install/upgrade.
     */
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
}

// ===========================================================================
// 5. Application class : Only needed if install_tabs() adds a new tab
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
