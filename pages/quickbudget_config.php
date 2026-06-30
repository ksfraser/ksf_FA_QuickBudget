<?php
/**
 * QuickBudget Config Page
 *
 * Configuration screen for inflation factors.
 * Supports FR-01 through FR-06.
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
$page_security = 'SA_KSF_QUICKBUDGETMANAGE';
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
    case 'save':
        handle_save();
        break;
    case 'import':
        handle_import();
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
    include_once(dirname(__DIR__) . '/includes/InflationFactorManager.php');
    include_once(dirname(__DIR__) . '/includes/InflationFactorRepository.php');

    $manager = new InflationFactorManager();
    $manager->loadFromDB((int)($_SESSION['company'] ?? 0));
    $currentRate = $manager->getDefaultRate();

    page(_("Quick Budget Configuration"), false, false, '', '');

    echo "<div class='card'>";
    echo "<h3>" . _("Inflation Factors") . "</h3>";

    echo "<h4>" . _("Global Rate") . "</h4>";
    echo "<form id='global-form' method='post' action='quickbudget_config.php?action=save'>";
    echo "<input type='hidden' name='type' value='global'>";
    echo "<input type='number' step='0.0001' name='rate' id='rate' value='" . htmlspecialchars((string)$currentRate) . "' class='form-control'>";
    echo "<input type='submit' class='btn btn-primary' value='" . _("Save Global Rate") . "'>";
    echo "</form>";

    echo "<h4>" . _("Category Rate") . "</h4>";
    echo "<form id='category-form' method='post' action='quickbudget_config.php?action=save'>";
    echo "<input type='hidden' name='type' value='category'>";
    echo "<select name='reference' id='category_ref' class='form-control'>";
    $categories = ['Income', 'COGS', 'Expenses'];
    foreach ($categories as $cat) {
        $catRate = $manager->getCategoryRate($cat);
        $selected = $catRate ? ' selected' : '';
        echo "<option value='$cat'$selected>$cat</option>";
    }
    echo "</select>";
    echo "<input type='number' step='0.0001' name='rate' id='category_rate' value='' class='form-control' placeholder='Rate'>";
    echo "<input type='submit' class='btn btn-primary' value='" . _("Save Category Rate") . "'>";
    echo "</form>";

    echo "<h4>" . _("GL-Specific Rate") . "</h4>";
    echo "<form id='gl-form' method='post' action='quickbudget_config.php?action=save'>";
    echo "<input type='hidden' name='type' value='gl'>";
    echo "<select name='reference' id='gl_ref' class='form-control'>";
    $glResult = db_query("SELECT account_code, account_name FROM " . TB_PREF . "chart_master WHERE account_code IS NOT NULL ORDER BY account_code");
    while ($glRow = db_fetch_assoc($glResult)) {
        echo "<option value='" . htmlspecialchars($glRow['account_code']) . "'>" . htmlspecialchars($glRow['account_code'] . ' - ' . $glRow['account_name']) . "</option>";
    }
    echo "</select>";
    echo "<input type='number' step='0.0001' name='rate' id='gl_rate' value='' class='form-control' placeholder='Rate'>";
    echo "<input type='submit' class='btn btn-primary' value='" . _("Save GL Rate") . "'>";
    echo "</form>";

    $repo = new InflationFactorRepository();
    $factors = $repo->getAllForCompany((int)($_SESSION['company'] ?? 0));

    $perPage = (int)($_GET['per_page'] ?? 10);
    $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 10;

    // Category rates section
    $catFactors = array_filter($factors, fn($f) => $f->getType() === 'category');
    $catPage = (int)($_GET['cat_page'] ?? 1);
    $catOffset = ($catPage - 1) * $perPage;
    $catTotalPages = max(1, ceil(count($catFactors) / $perPage));
    $catDisplay = array_slice($catFactors, $catOffset, $perPage);

    echo "<h4>" . _("Category Rates") . "</h4>";
    echo "<table class='table table-striped'>";
    echo "<tr><th>" . _("Category") . "</th><th>" . _("Rate") . "</th></tr>";
    foreach ($catDisplay as $f) {
        echo "<tr><td>" . htmlspecialchars($f->getReferenceId()) . "</td><td>" . htmlspecialchars((string)$f->getRate()) . "</td></tr>";
    }
    echo "</table>";
    if ($catTotalPages > 1) {
        echo "<div>" . _("Pages:") . " ";
        for ($i = 1; $i <= $catTotalPages; $i++) {
            $active = $i === $catPage ? ' font-weight-bold' : '';
            echo "<a href='quickbudget_config.php?cat_page=$i&per_page=$perPage' class='$active'>$i</a> ";
        }
        echo "</div>";
    }

    // GL rates section
    $glFactors = array_filter($factors, fn($f) => $f->getType() === 'gl');
    $glPageNum = (int)($_GET['gl_page'] ?? 1);
    $glOffset = ($glPageNum - 1) * $perPage;
    $glTotalPages = max(1, ceil(count($glFactors) / $perPage));
    $glDisplay = array_slice($glFactors, $glOffset, $perPage);

    echo "<h4>" . _("GL-Specific Rates") . "</h4>";
    echo "<table class='table table-striped'>";
    echo "<tr><th>" . _("GL Account") . "</th><th>" . _("Rate") . "</th></tr>";
    foreach ($glDisplay as $f) {
        echo "<tr><td>" . htmlspecialchars($f->getReferenceId()) . "</td><td>" . htmlspecialchars((string)$f->getRate()) . "</td></tr>";
    }
    echo "</table>";
    if ($glTotalPages > 1) {
        echo "<div>" . _("Pages:") . " ";
        for ($i = 1; $i <= $glTotalPages; $i++) {
            $active = $i === $glPageNum ? ' font-weight-bold' : '';
            echo "<a href='quickbudget_config.php?gl_page=$i&per_page=$perPage' class='$active'>$i</a> ";
        }
        echo "</div>";
    }

    // Per page selector (affects both)
    echo "<div>" . _("Per page:") . " ";
    foreach ([10, 25, 50, 100] as $pp) {
        $active = $pp === $perPage ? ' font-weight-bold' : '';
        echo "<a href='quickbudget_config.php?per_page=$pp' class='$active'>$pp</a> ";
    }
    echo "</div>";

    echo "<h4>" . _("Import from CSV") . "</h4>";
    echo "<form id='import-form' method='post' action='quickbudget_config.php?action=import' enctype='multipart/form-data'>";
    echo "<input type='file' name='csv_file' accept='.csv' class='form-control'>";
    echo "<input type='submit' class='btn btn-secondary' value='" . _("Import") . "'>";
    echo "</form>";

    echo "<script>
    function handleAjax(formId, successMsg) {
        document.getElementById(formId).addEventListener('submit', function(e) {
            e.preventDefault();
            var formData = new FormData(this);
            fetch(this.action, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(d => alert(d.success ? successMsg + d.rate : d.error));
        });
    }
    handleAjax('global-form', 'Rate saved: ');
    handleAjax('category-form', 'Category rate saved: ');
    handleAjax('gl-form', 'GL rate saved: ');
    document.getElementById('import-form').addEventListener('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        fetch(this.action, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(d => alert(d.success ? 'Imported ' + d.imported + ' factors' : d.error));
    });
    </script>";

    echo "</div>";

    end_page();
}

function handle_save(): void
{
    // FR-01, FR-02, FR-03, FR-05: Save inflation factor
    include_once(dirname(__DIR__) . '/includes/InflationFactorManager.php');
    include_once(dirname(__DIR__) . '/includes/InflationFactorDTO.php');
    include_once(dirname(__DIR__) . '/includes/InflationFactorRepository.php');

    $type = $_POST['type'] ?? 'global';
    $rate = (float)($_POST['rate'] ?? 1.0);
    $reference = $_POST['reference'] ?? '';
    $company = (int)($_SESSION['company'] ?? 0);

    $manager = new InflationFactorManager();
    $repo = new InflationFactorRepository();

    switch ($type) {
        case 'global':
            $manager->setGlobalRate($rate);
            $factor = new InflationFactorDTO($type, '', $rate, $company);
            break;
        case 'category':
            $manager->setCategoryRate($reference, $rate);
            $factor = new InflationFactorDTO($type, $reference, $rate, $company);
            break;
        case 'gl':
            $manager->setGLRate($reference, $rate);
            $factor = new InflationFactorDTO($type, $reference, $rate, $company);
            break;
        default:
            $factor = null;
    }

    if ($factor) {
        $repo->save($factor);
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'type' => $type, 'rate' => $rate]);
    exit;
}

function handle_import(): void
{
    // FR-04: Import inflation factors from CSV
    include_once(dirname(__DIR__) . '/includes/InflationFactorDTO.php');
    include_once(dirname(__DIR__) . '/includes/InflationFactorRepository.php');

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'No file uploaded']);
        exit;
    }

    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, 'r');
    if (!$handle) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Cannot open file']);
        exit;
    }

    $csvRows = [];
    $header = fgetcsv($handle);
    while (($row = fgetcsv($handle)) !== false) {
        $csvRows[] = array_combine($header, $row);
    }
    fclose($handle);

    $repo = new InflationFactorRepository();
    $count = $repo->importFromCsv($csvRows, (int)($_SESSION['company'] ?? 0));

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'imported' => $count]);
    exit;
}

function handle_export(): void
{
    // FR-06: Export inflation factors to CSV
    include_once(dirname(__DIR__) . '/includes/InflationFactorRepository.php');
    $repo = new InflationFactorRepository();

    $rows = $repo->exportToCsv((int)($_SESSION['company'] ?? 0));

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="inflation_factors.csv"');

    echo "type,reference_id,rate\n";
    foreach ($rows as $row) {
        echo "{$row['type']},{$row['reference']},{$row['rate']}\n";
    }
    exit;
}