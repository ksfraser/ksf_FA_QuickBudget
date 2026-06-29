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

$path_to_root = "../../..";
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
    case 'export':
        handle_export();
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

    // JavaScript to handle form submission
    echo "<script>
    document.getElementById('quickbudget-form').addEventListener('submit', function(e) {
        e.preventDefault();
        var form = this;
        var formData = new FormData(form);

        fetch(form.action, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert(data.message);
                // Refresh to show clean UI
                window.location.reload();
            } else if (data.prompt) {
                if (confirm(data.message)) {
                    formData.set('confirmed', '1');
                    fetch(form.action, { method: 'POST', body: formData })
                        .then(r => {
                            if (!r.ok) throw new Error('HTTP ' + r.status);
                            return r.json();
                        })
                        .then(d => alert(d.message))
                        .catch(err => alert('Error: ' + err.message));
                }
            } else {
                alert(data.error || 'Unknown error');
            }
        })
        .catch(err => {
            console.error('Budget generation error:', err);
            alert('Error: ' + err.message);
        });
    });
    </script>";

    end_page();
}

function handle_create(): void
{
    global $db, $path_to_root;

    $targetYear = (int)($_POST['target_year'] ?? date('Y') + 1);
    $startMonth = (int)($_POST['start_month'] ?? 1);
    $scenarioId = (int)($_POST['scenario_id'] ?? 0);

    // FR-11: Validate source period has completed actuals
    $sourceYear = $targetYear - 1;
    $completedMonths = get_completed_months_for_year($sourceYear);
    if ($completedMonths < $startMonth - 1) {
        $message = sprintf(
            _("Cannot generate budget: source year has only %d completed months, need %d"),
            $completedMonths,
            $startMonth - 1
        );
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $message]);
        exit;
    }

    // FR-12: Check for existing budget - prompt if exists for months before startMonth
    $existingCount = get_existing_budget_count($targetYear, $startMonth > 1 ? 1 : $startMonth);
    if ($existingCount > 0 && $startMonth > 1) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'prompt' => true,
            'message' => sprintf(_("Budget already exists for %d months. Overwrite?"), $existingCount)
        ]);
        exit;
    }

    // FR-09: Generate budget
    include_once(dirname(__DIR__) . '/includes/InflationFactorManager.php');
    include_once(__DIR__ . '/../src/Service/BudgetGeneratorService.php');

    $manager = new InflationFactorManager();
    $manager->loadFromDB((int)($_SESSION['company'] ?? 0));

    $service = new \Ksfraser\FA\QuickBudget\Service\BudgetGeneratorService($manager);

$entries = $service->generate($targetYear, $startMonth, $scenarioId);

    // Debug: log source year and entry count
    error_log("QuickBudget DEBUG: targetYear=$targetYear, sourceYear=" . ($targetYear - 1) . ", entries=" . count($entries));

    // FR-14: Save to FA native budget tables
    $saved = $service->saveToFABudget($entries, (int)($_SESSION['company'] ?? 0), $path_to_root);

    $result = array(
        'success' => true,
        'message' => 'Budget generated for ' . count($entries) . ' GL accounts, saved ' . $saved . ' entries',
        'year' => $targetYear
    );

    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

/**
 * FR-12: Get count of existing budget entries for a year/month range from native FA tables
 */
function get_existing_budget_count(int $year, int $fromMonth = 1): int
{
    global $db;

    $sql = "SELECT COUNT(*) as cnt FROM " . TB_PREF . "budget_trans
        WHERE YEAR(tran_date) = " . (int)$year . " AND MONTH(tran_date) >= " . (int)$fromMonth;
    $result = db_query($sql, null);
    $row = db_fetch_assoc($result);

    return $row ? (int)$row['cnt'] : 0;
}

/**
 * Get count of months with completed actuals for a year.
 * FR-11 support: validate source period.
 */
function get_completed_months_for_year(int $year): int
{
    global $db;

    $currentYear = (int)date('Y');
    $currentMonth = (int)date('m');

    if ($year > $currentYear) {
        return 0; // Future year, no actuals yet
    }

    $sql = "SELECT COUNT(DISTINCT MONTH(tran_date)) as months
        FROM " . TB_PREF . "gl_trans
        WHERE YEAR(tran_date) = " . (int)$year . " AND tran_date <= LAST_DAY(CONCAT('$year-', LPAD($currentMonth, 2, '0'), '-01'))";
    $result = db_query($sql, null);
    $row = db_fetch_assoc($result);

    return $row ? (int)$row['months'] : 0;
}

function handle_compare(): void
{
    header('Content-Type: application/json');
    echo json_encode(array('error' => 'Not implemented'));
    exit;
}

function handle_export(): void
{
    // FR-25, FR-27: Export budget data to CSV with all 12 months from native FA tables
    global $db;

    $year = (int)($_GET['year'] ?? date('Y'));

    $sql = "SELECT account as gl_account, MONTH(tran_date) as month, SUM(amount) as amount
        FROM " . TB_PREF . "budget_trans
        WHERE YEAR(tran_date) = " . (int)$year . "
        GROUP BY account, MONTH(tran_date)
        ORDER BY gl_account, month";
    $result = db_query($sql, null);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="budget_' . $year . '.csv"');

    echo "GL Account,Jan,Feb,Mar,Apr,May,Jun,Jul,Aug,Sep,Oct,Nov,Dec\n";

    $currentAccount = null;
    $months = array_fill(1, 12, 0);

    while ($row = db_fetch_assoc($result)) {
        if ($row['gl_account'] !== $currentAccount) {
            if ($currentAccount !== null) {
                echo $currentAccount . "," . implode(',', $months) . "\n";
            }
            $currentAccount = $row['gl_account'];
            $months = array_fill(1, 12, 0);
        }
        $months[(int)$row['month']] = $row['amount'];
    }

    if ($currentAccount !== null) {
        echo $currentAccount . "," . implode(',', $months) . "\n";
    }

    exit;
}