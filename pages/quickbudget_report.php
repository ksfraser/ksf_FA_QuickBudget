<?php
/**
 * QuickBudget YOY Comparison Report
 *
 * Year-over-year actual vs budget comparison with inflation analysis.
 * Supports FR-29, FR-30.
 */
declare(strict_types=1);

$path_to_root = "../../..";
$page_security = 'SA_KSF_QUICKBUDGETVIEW';
include_once($path_to_root . "/includes/session.inc");
add_access_extensions();

global $db;

$page = isset($_GET['action']) ? $_GET['action'] : 'view';

switch ($page) {
    case 'data':
        handle_data();
        break;
    default:
        render_view();
}

function render_view(): void
{
    global $path_to_root;

    include_once($path_to_root . "/includes/ui/items_cart.inc");

    page(_("YOY Budget vs Actual Report"), false, false, '', '');

    echo "<div class='card'>";
    echo "<h3>" . _("Year-over-Year Comparison") . "</h3>";

    echo "<form id='report-form' method='post' action='quickbudget_report.php?action=data'>";
    echo "<table class='table'>";
    echo "<tr><td>" . _("Target Year") . ": <select name='year' id='year'>";
    for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++) {
        echo "<option value='$y'>$y</option>";
    }
    echo "</select></td></tr>";
    echo "<tr><td>" . _("Minimum Amount") . ": <input type='number' step='0.01' name='min_amount' id='min_amount' value='1000'></td></tr>";
    echo "</table>";
    echo "<input type='submit' class='btn btn-primary' value='" . _("Generate Report") . "'>";
    echo "</form>";

    echo "<div id='report-results'></div>";

    end_page();
}

function handle_data(): void
{
    global $db;

    $year = (int)($_POST['year'] ?? date('Y'));
    $minAmount = (float)($_POST['min_amount'] ?? 1000);

    // Get GL accounts with actuals in target year
    $sql = "SELECT DISTINCT account as gl_account FROM " . TB_PREF . "gl_trans
        WHERE YEAR(tran_date) = " . (int)$year . " AND ABS(amount) >= " . (float)$minAmount;
    $result = db_query($sql, null);

    $accounts = [];
    while ($row = db_fetch_assoc($result)) {
        $accounts[] = $row['gl_account'];
    }

    $reportData = [];
    $summary = ['total_actual' => 0, 'total_budget' => 0, 'count' => 0];
    $inflationRates = [];

    foreach ($accounts as $account) {
        $currentActual = getActualTotal($account, $year);
        $priorActual = getActualTotal($account, $year - 1);
        $currentBudget = getBudgetTotal($account, $year);
        $priorBudget = getBudgetTotal($account, $year - 1);

        // YOY inflation = (current_actual / prior_actual) - 1, if no prior actual use prior_budget
        $base = $priorActual > 0 ? $priorActual : $priorBudget;
        $inflation = $base > 0 ? ($currentActual / $base) - 1 : null;

        if ($inflation !== null && $currentActual >= $minAmount) {
            $inflationRates[] = $inflation;
            $reportData[] = [
                'gl_account' => $account,
                'current_actual' => $currentActual,
                'prior_actual' => $priorActual,
                'current_budget' => $currentBudget,
                'prior_budget' => $priorBudget,
                'inflation' => $inflation,
                'variance' => $currentActual - $currentBudget,
            ];
            $summary['total_actual'] += $currentActual;
            $summary['total_budget'] += $currentBudget;
            $summary['count']++;
        }
    }

    // Calculate statistics
    $avgInflation = count($inflationRates) > 0 ? array_sum($inflationRates) / count($inflationRates) : 0;
    $stdDev = calculateStdDev($inflationRates, $avgInflation);

    // Filter outliers (> 1 SD from mean)
    $outliers = array_filter($reportData, function($row) use ($avgInflation, $stdDev) {
        return abs($row['inflation'] - $avgInflation) > $stdDev;
    });

    header('Content-Type: application/json');
    echo json_encode([
        'summary' => $summary,
        'avg_inflation' => $avgInflation,
        'std_dev' => $stdDev,
        'outliers' => array_values($outliers),
        'all_data' => $reportData,
    ]);
    exit;
}

function getActualTotal(string $account, int $year): float
{
    global $db;

    $sql = "SELECT SUM(amount) as total FROM " . TB_PREF . "gl_trans
        WHERE account = '" . mysqli_real_escape_string($db, $account) . "'
        AND YEAR(tran_date) = " . (int)$year;
    $result = db_query($sql, null);
    $row = db_fetch_assoc($result);

    return $row ? (float)$row['total'] : 0;
}

function getBudgetTotal(string $account, int $year): float
{
    global $db;

    $sql = "SELECT SUM(amount) as total FROM " . TB_PREF . "budget_trans
        WHERE account = '" . mysqli_real_escape_string($db, $account) . "'
        AND YEAR(tran_date) = " . (int)$year;
    $result = db_query($sql, null);
    $row = db_fetch_assoc($result);

    return $row ? (float)$row['total'] : 0;
}

function calculateStdDev(array $values, float $mean): float
{
    if (count($values) < 2) {
        return 0;
    }

    $sum = 0;
    foreach ($values as $v) {
        $sum += pow($v - $mean, 2);
    }

    return sqrt($sum / count($values));
}