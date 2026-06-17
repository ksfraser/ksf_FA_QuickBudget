<?php
/**
 * QuickBudget Comparison Page
 *
 * Display actuals vs budget comparison.
 * Supports FR-15 through FR-19, FR-25, FR-28.
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
    case 'data':
        handle_data();
        break;
    case 'export':
        handle_export();
        break;
    default:
        render_view();
}

function render_view(): void
{
    global $path_to_root;

    include_once($path_to_root . "/includes/ui/items_cart.inc");

    page(_("Budget Comparison"), false, false, '', '');

    echo "<style>
    .variance-positive { background-color: #d4edda; }
    .variance-negative { background-color: #f8d7da; }
    </style>";

    echo "<div class='card'>";
    echo "<h3>" . _("Actuals vs Budget Comparison") . "</h3>";

    echo "<form id='compare-form' method='post' action='quickbudget_compare.php?action=export'>";
    echo "<table class='table'>";
    echo "<tr>";
    echo "<td>" . _("Year") . ": <select name='year' id='year'>";
    for ($y = date('Y') - 1; $y <= date('Y') + 2; $y++) {
        $selected = ($y == date('Y')) ? ' selected' : '';
        echo "<option value='$y'$selected>$y</option>";
    }
    echo "</select></td>";

    echo "<td>" . _("From Month") . ": <select name='start_month' id='start_month'>";
    for ($m = 1; $m <= 12; $m++) {
        echo "<option value='$m'>" . date('F', mktime(0, 0, 0, $m, 1)) . "</option>";
    }
    echo "</select></td>";

    echo "<td>" . _("To Month") . ": <select name='end_month' id='end_month'>";
    for ($m = 1; $m <= 12; $m++) {
        $selected = ($m == 12) ? ' selected' : '';
        echo "<option value='$m'$selected>" . date('F', mktime(0, 0, 0, $m, 1)) . "</option>";
    }
    echo "</select></td>";
    echo "<td><input type='submit' class='btn btn-primary' value='" . _("Export CSV") . "'></td>";
    echo "</tr>";
    echo "</table>";
    echo "</form>";

    echo "<div id='comparison-results'></div>";

    echo "</div>";

    end_page();
}

function handle_data(): void
{
    $year = (int)($_POST['year'] ?? date('Y'));
    $startMonth = (int)($_POST['start_month'] ?? 1);
    $endMonth = (int)($_POST['end_month'] ?? 12);

    // FR-15: Get comparison data
    // TODO: Implement actual query logic

    header('Content-Type: application/json');
    echo json_encode([
        'actuals' => [],
        'budget' => [],
        'variance' => []
    ]);
    exit;
}

function handle_export(): void
{
    // FR-26: Export comparison report to CSV
    // TODO: Implement export logic

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="budget_comparison.csv"');
    echo "GL,Actual,Budget,Variance,Percent\n";
    exit;
}