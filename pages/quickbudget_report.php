<?php
/**
 * QuickBudget Historical Inflation Report
 *
 * Multi-year YoY inflation analysis with trends, charts, context display,
 * and transfer to config. Supports Issue #2: FR-37 through FR-57.
 *
 * @see Project_Docs/Issue_2_Plan.md - Phase 2
 * @since 1.1.0
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
    case 'chart_data':
        handle_chart_data();
        break;
    default:
        render_view();
}

// =========================================================================
// VIEW RENDERING
// =========================================================================

function render_view(): void
{
    global $path_to_root;

    page(_("Historical Inflation Analysis"), false, false, '', '');

    if (isset($_GET['message'])) {
        display_notification($_GET['message']);
    }

    echo "<div class='row'>";
    render_filter_bar();
    echo "</div>";

    echo "<div id='report-results'>";

    echo "<p class='text-muted'>" . _("Select filters and click Generate to view historical inflation data.") . "</p>";
    echo "</div>";


    echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
    echo '<script src="../assets/inflation_charts.js"></script>';
    end_page();
}

function render_filter_bar(): void
{
    $level = $_GET['level'] ?? 'all';
    $referenceId = $_GET['reference_id'] ?? '';
    $viewMode = $_GET['view'] ?? 'table';

    echo "<div class='col-md-12'>";
    echo "<div class='card mb-3'>";
    echo "<div class='card-header'>" . _("Filters") . "</div>";
    echo "<div class='card-body'>";
    echo "<form method='get' action='quickbudget_report.php' id='filter-form'>";
    echo "<div class='row'>";

    // Level selector
    echo "<div class='col-md-2'>";
    echo "<label>" . _("Level") . "</label>";
    echo "<select name='level' id='level-select' class='form-control form-control-sm'>";
    echo "<option value='all'" . ($level === 'all' ? ' selected' : '') . ">" . _("ALL") . "</option>";
    echo "<option value='gl'" . ($level === 'gl' ? ' selected' : '') . ">" . _("GL Account") . "</option>";
    echo "<option value='category'" . ($level === 'category' ? ' selected' : '') . ">" . _("Category") . "</option>";
    echo "<option value='class'" . ($level === 'class' ? ' selected' : '') . ">" . _("Class") . "</option>";
    echo "</select></div>";

    // Reference selector (populated by JS based on level)
    echo "<div class='col-md-3'>";
    echo "<label>" . _("Item") . "</label>";
    echo "<select name='reference_id' id='reference-select' class='form-control form-control-sm'>";
    echo "<option value=''>" . _("-- All --") . "</option>";
    // Options populated dynamically
    echo "</select></div>";

    // View mode toggle
    echo "<div class='col-md-2'>";
    echo "<label>" . _("View") . "</label>";
    echo "<select name='view' class='form-control form-control-sm'>";
    echo "<option value='table'" . ($viewMode === 'table' ? ' selected' : '') . ">" . _("Table") . "</option>";
    echo "<option value='chart'" . ($viewMode === 'chart' ? ' selected' : '') . ">" . _("Chart") . "</option>";
    echo "</select></div>";

    // Buttons
    echo "<div class='col-md-3 mt-4'>";
    echo "<input type='submit' class='btn btn-primary btn-sm' value='" . _("Generate") . "'> ";
    echo "<a href='quickbudget_report.php' class='btn btn-secondary btn-sm'>" . _("Reset") . "</a>";
    echo "<a id='print-pdf-btn' href='quickbudget_inflation_pdf.php?level=" . htmlspecialchars($level) . "&reference_id=" . htmlspecialchars($referenceId) . "' target='_blank' class='btn btn-outline-primary btn-sm'>" . _("Print to PDF") . "</a>";
    echo "</div>";

    echo "</div>"; // row
    echo "</form>";

    // Inline JS for level->reference cascading
    render_reference_js();

    echo "</div>"; // card-body
    echo "</div>"; // card
    echo "</div>"; // col
}

function render_reference_js(): void
{
    echo <<<'JS'
<script>
document.getElementById('level-select').addEventListener('change', function() {
    var level = this.value;
    var refSelect = document.getElementById('reference-select');
    refSelect.innerHTML = '<option value="">-- All --</option>';
    if (level === 'all') return;

    fetch('quickbudget_report.php?action=data&level=' + level + '&reference_id=&list_only=1')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.items) {
                data.items.forEach(function(item) {
                    var opt = document.createElement('option');
                    opt.value = item.id;
                    opt.textContent = item.name;
                    refSelect.appendChild(opt);
                });
            }
        });
});
</script>
JS;
}

// =========================================================================
// DATA HANDLING
// =========================================================================

function handle_data(): void
{
    global $db;

    $level = $_GET['level'] ?? 'all';
    $referenceId = $_GET['reference_id'] ?? '';
    $listOnly = isset($_GET['list_only']);

    header('Content-Type: application/json');

    // List-only mode: return items for dropdown
    if ($listOnly) {
        echo json_encode(get_items_for_level($level));
        exit;
    }

    // Calculate inflation data
    $data = calculate_report_data($level, $referenceId);
    echo json_encode($data);
    exit;
}

function handle_chart_data(): void
{
    global $db;

    $level = $_GET['level'] ?? 'all';
    $referenceId = $_GET['reference_id'] ?? '';

    header('Content-Type: application/json');

    $data = calculate_report_data($level, $referenceId);
    echo json_encode($data);
    exit;
}

function get_items_for_level(string $level): array
{
    global $db;

    $items = [];

    switch ($level) {
        case 'gl':
            $sql = "SELECT DISTINCT cm.account_code AS id,
                           CONCAT(cm.account_code, ' - ', cm.account_name) AS name
                    FROM " . TB_PREF . "chart_master cm
                    INNER JOIN " . TB_PREF . "gl_trans t ON t.account = cm.account_code
                    WHERE t.amount != 0
                    ORDER BY cm.account_code";
            $result = db_query($sql);
            if ($result) {
                while ($row = db_fetch_assoc($result)) {
                    $items[] = ['id' => $row['id'], 'name' => $row['name']];
                }
            }
            break;

        case 'category':
            $sql = "SELECT DISTINCT cc.cid AS id, cc.class_name AS name
                    FROM " . TB_PREF . "chart_class cc
                    INNER JOIN " . TB_PREF . "chart_types ct ON ct.class_id = cc.cid
                    INNER JOIN " . TB_PREF . "chart_master cm ON cm.account_type = ct.id
                    INNER JOIN " . TB_PREF . "gl_trans t ON t.account = cm.account_code
                    WHERE t.amount != 0
                    ORDER BY cc.cid";
            $result = db_query($sql);
            if ($result) {
                while ($row = db_fetch_assoc($result)) {
                    $items[] = ['id' => $row['id'], 'name' => $row['name']];
                }
            }
            break;

        case 'class':
            $sql = "SELECT DISTINCT cc.cid AS id, cc.class_name AS name
                    FROM " . TB_PREF . "chart_class cc
                    INNER JOIN " . TB_PREF . "chart_types ct ON ct.class_id = cc.cid
                    INNER JOIN " . TB_PREF . "chart_master cm ON cm.account_type = ct.id
                    INNER JOIN " . TB_PREF . "gl_trans t ON t.account = cm.account_code
                    WHERE t.amount != 0
                    ORDER BY cc.cid";
            $result = db_query($sql);
            if ($result) {
                while ($row = db_fetch_assoc($result)) {
                    $items[] = ['id' => $row['id'], 'name' => $row['name']];
                }
            }
            break;
    }

    return $items;
}

function calculate_report_data(string $level, string $referenceId): array
{
    global $db;

    require_once(dirname(__DIR__) . '/src/Service/InflationStats.php');
    require_once(dirname(__DIR__) . '/src/Service/InflationCalculator.php');

    $calculator = new InflationCalculator();
    $stats = new InflationStats();

    $result = [
        'items' => [],
        'summary' => null,
        'context' => null,
    ];

    if ($level === 'all') {
        // Aggregate across all levels
        $yearlyEntries = $calculator->calculateAll();
        $computed = $calculator->computeStats($yearlyEntries);
        $result['items'] = format_yearly_entries('ALL', 'All Accounts', $yearlyEntries);
        $result['summary'] = $computed['stats'];
        $result['trend_indicators'] = $computed['trend_indicators'];
    } elseif ($level === 'gl' && $referenceId !== '') {
        // Single GL account
        $yearlyEntries = $calculator->calculateForGL($referenceId);
        $computed = $calculator->computeStats($yearlyEntries);
        $accountName = get_account_name($referenceId);
        $result['items'] = format_yearly_entries($referenceId, $accountName, $yearlyEntries);
        $result['summary'] = $computed['stats'];
        $result['trend_indicators'] = $computed['trend_indicators'];

        // Context: show category stats
        $categoryId = get_account_category($referenceId);
        if ($categoryId) {
            $catEntries = $calculator->calculateForCategory($categoryId);
            $catComputed = $calculator->computeStats($catEntries);
            $catName = get_category_name($categoryId);
            $result['context'] = [
                'level' => 'category',
                'id' => $categoryId,
                'name' => $catName,
                'stats' => $catComputed['stats'],
                'trend_indicators' => $catComputed['trend_indicators'],
                'within_norm' => $stats->isWithinNorm(
                    $computed['stats']['mean'],
                    $catComputed['stats']['mean'],
                    $catComputed['stats']['stddev']
                ),
            ];
        }
    } elseif ($level === 'category' && $referenceId !== '') {
        // Single category
        $yearlyEntries = $calculator->calculateForCategory($referenceId);
        $computed = $calculator->computeStats($yearlyEntries);
        $catName = get_category_name($referenceId);
        $result['items'] = format_yearly_entries($referenceId, $catName, $yearlyEntries);
        $result['summary'] = $computed['stats'];
        $result['trend_indicators'] = $computed['trend_indicators'];

        // Context: show class stats (in FA, class = category is top level)
        // For now, show ALL stats as context
        $allEntries = $calculator->calculateAll();
        $allComputed = $calculator->computeStats($allEntries);
        $result['context'] = [
            'level' => 'all',
            'id' => 'ALL',
            'name' => 'All Accounts',
            'stats' => $allComputed['stats'],
            'trend_indicators' => $allComputed['trend_indicators'],
            'within_norm' => $stats->isWithinNorm(
                $computed['stats']['mean'],
                $allComputed['stats']['mean'],
                $allComputed['stats']['stddev']
            ),
        ];
    } elseif ($level === 'class' && $referenceId !== '') {
        // Single class
        $yearlyEntries = $calculator->calculateForClass($referenceId);
        $computed = $calculator->computeStats($yearlyEntries);
        $className = get_category_name($referenceId);
        $result['items'] = format_yearly_entries($referenceId, $className, $yearlyEntries);
        $result['summary'] = $computed['stats'];
        $result['trend_indicators'] = $computed['trend_indicators'];
    } elseif ($level === 'gl' && $referenceId === '') {
        // All GLs
        $allAccounts = $calculator->getGLAccounts();
        $allItems = [];
        foreach ($allAccounts as $code => $info) {
            $entries = $calculator->calculateForGL($code);
            $computed = $calculator->computeStats($entries);
            $allItems[] = [
                'id' => $code,
                'name' => $info['account_name'],
                'stats' => $computed['stats'],
                'trend_indicators' => $computed['trend_indicators'],
                'yearly_entries' => $entries,
            ];
        }
        $result['items'] = $allItems;
    } elseif ($level === 'category' && $referenceId === '') {
        // All categories
        $categories = $calculator->getCategories();
        $allItems = [];
        foreach ($categories as $cid => $info) {
            $entries = $calculator->calculateForCategory($cid);
            $computed = $calculator->computeStats($entries);
            $allItems[] = [
                'id' => $cid,
                'name' => $info['class_name'],
                'stats' => $computed['stats'],
                'trend_indicators' => $computed['trend_indicators'],
                'yearly_entries' => $entries,
            ];
        }
        $result['items'] = $allItems;
    } elseif ($level === 'class' && $referenceId === '') {
        // All classes
        $classes = $calculator->getClasses();
        $allItems = [];
        foreach ($classes as $cid => $info) {
            $entries = $calculator->calculateForClass($cid);
            $computed = $calculator->computeStats($entries);
            $allItems[] = [
                'id' => $cid,
                'name' => $info['class_name'],
                'stats' => $computed['stats'],
                'trend_indicators' => $computed['trend_indicators'],
                'yearly_entries' => $entries,
            ];
        }
        $result['items'] = $allItems;
    }

    return $result;
}

function format_yearly_entries(string $id, string $name, array $yearlyEntries): array
{
    $items = [];
    foreach ($yearlyEntries as $entry) {
        $items[] = [
            'id' => $id,
            'name' => $name,
            'year' => $entry['year'],
            'yoy_rate' => $entry['yoy_rate'],
            'actual_current' => $entry['actual_current'],
            'actual_prior' => $entry['actual_prior'],
        ];
    }
    return $items;
}

// =========================================================================
// HELPER LOOKUPS
// =========================================================================

function get_account_name(string $accountCode): string
{
    global $db;

    $sql = "SELECT account_name FROM " . TB_PREF . "chart_master
            WHERE account_code = '" . addslashes($accountCode) . "'";
    $result = db_query($sql);
    if ($result && $row = db_fetch_assoc($result)) {
        return $row['account_name'];
    }
    return $accountCode;
}

function get_account_category(string $accountCode): ?string
{
    global $db;

    $sql = "SELECT ct.class_id FROM " . TB_PREF . "chart_master cm
            INNER JOIN " . TB_PREF . "chart_types ct ON cm.account_type = ct.id
            WHERE cm.account_code = '" . addslashes($accountCode) . "'";
    $result = db_query($sql);
    if ($result && $row = db_fetch_assoc($result)) {
        return $row['class_id'] ?? null;
    }
    return null;
}

function get_category_name(string $categoryId): string
{
    global $db;

    $sql = "SELECT class_name FROM " . TB_PREF . "chart_class
            WHERE cid = '" . addslashes($categoryId) . "'";
    $result = db_query($sql);
    if ($result && $row = db_fetch_assoc($result)) {
        return $row['class_name'];
    }
    return "Category $categoryId";
}
