<?php
/**
 * QuickBudget Main Page
 *
 * Entry point for budget generation.
 * Supports FR-07, FR-08, FR-09, FR-12.
 */
declare(strict_types=1);

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

$path_to_root = "../..";
$page_security = 'SA_KSF_QUICKBUDGETVIEW';
include_once($path_to_root . "/includes/session.inc");
add_access_extensions();

global $db;

if ($_ksf_quickbudget_is_ajax) {
    while (ob_get_level() > 1) {
        ob_end_clean();
    }
}

$page = isset($_GET['action']) ? $_GET['action'] : 'view';

switch ($page) {
    case 'create':
        handle_create();
        break;
    case 'compare':
        handle_compare();
        break;
    default:
        render_view();
}

function render_view(): void
{
    global $path_to_root;

    include_once($path_to_root . "/includes/ui/items_cart.inc");
    include_once($path_to_root . "/admin/db/chart_db.inc");

    page(_("Quick Budget"), false, false, '', '');

    echo "<div class='card'>";
    echo "<h3>" . _("Generate Budget") . "</h3>";
    echo "<form id='quickbudget-form' method='post' action='quickbudget.php?action=create'>";

    echo "<table class='table table-bordered'>";
    echo "<tr><th>" . _("Target Year") . "</th><td>";
    echo "<select name='target_year' id='target_year'>";
    for ($y = date('Y'); $y <= date('Y') + 2; $y++) {
        $selected = ($y == date('Y') + 1) ? ' selected' : '';
        echo "<option value='$y'$selected>$y</option>";
    }
    echo "</select></td></tr>";

    echo "<tr><th>" . _("Start Month") . "</th><td>";
    echo "<select name='start_month' id='start_month'>";
    for ($m = 1; $m <= 12; $m++) {
        echo "<option value='$m'>" . date('F', mktime(0, 0, 0, $m, 1)) . "</option>";
    }
    echo "</select></td></tr>";

    echo "<tr><th>" . _("Scenario") . "</th><td>";
    echo "<select name='scenario_id' id='scenario_id'>";
    echo "<option value='0'>Baseline</option>";
    echo "<option value='1'>Optimistic (0.9x)</option>";
    echo "<option value='2'>Pessimistic (1.1x)</option>";
    echo "</select></td></tr>";
    echo "</table>";

    echo "<input type='submit' class='btn btn-primary' value='" . _("Generate Budget") . "'>";
    echo "</form>";
    echo "</div>";

    end_page();
}

function handle_create(): void
{
    global $db, $path_to_root;

    // FR-11: Validate source period
    $targetYear = (int)($_POST['target_year'] ?? date('Y') + 1);
    $startMonth = (int)($_POST['start_month'] ?? 1);

    // TODO: Validate actuals exist for source year

    // FR-09: Generate budget
    include_once(dirname(__DIR__) . '/includes/InflationFactorManager.php');
    include_once(__DIR__ . '/../src/Service/BudgetGeneratorService.php');

    $manager = new InflationFactorManager();
    $service = new \Ksfraser\FA\QuickBudget\Service\BudgetGeneratorService($manager);

    $entries = $service->generate($targetYear, $startMonth);

    // FR-14: Save to FA budget tables (placeholder)
    $result = array(
        'success' => true,
        'message' => 'Budget generated for ' . count($entries) . ' GL accounts',
        'year' => $targetYear
    );

    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

function handle_compare(): void
{
    header('Content-Type: application/json');
    echo json_encode(array('error' => 'Not implemented'));
    exit;
}