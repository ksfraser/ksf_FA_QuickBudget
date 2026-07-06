<?php
/**
 * QuickBudget Config Page
 *
 * Configuration screen for inflation factors.
 * Pattern: Sales prices style - table with existing rates, edit/insert form below.
 * Supports FR-01 through FR-07.
 */
declare(strict_types=1);

$path_to_root = "../../..";
$page_security = 'SA_KSF_QUICKBUDGETMANAGE';
include_once($path_to_root . "/includes/session.inc");
add_access_extensions();

global $db;

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
    global $path_to_root, $db;

    include_once($path_to_root . "/includes/ui/items_cart.inc");
    include_once(dirname(__DIR__) . '/includes/InflationFactorManager.php');
    include_once(dirname(__DIR__) . '/includes/ScenarioDTO.php');
    include_once(dirname(__DIR__) . '/includes/ScenarioRepository.php');
    include_once(dirname(__DIR__) . '/includes/GroupDAO.php');
    include_once(dirname(__DIR__) . '/includes/GLAccountDAO.php');

    $manager = new InflationFactorManager();
    $manager->loadFromDB((int)($_SESSION['company'] ?? 0));

    // Store in session for quick access
    $_SESSION['ksf_qb_factors'] = $manager->getAllRates();

    // Get scenario multipliers for display
    $scenarioRepo = new ScenarioRepository();
    $scenariosArray = $scenarioRepo->getAllForCompany(0);
    $scenarios = [];
    foreach ($scenariosArray as $scenario) {
        $scenarios[$scenario->getName()] = ['id' => $scenario->getId(), 'multiplier' => $scenario->getMultiplier()];
    }

    page(_("Quick Budget Configuration"), false, false, '', '');

    $perPage = (int)($_GET['per_page'] ?? 10);
    $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 10;

    // Show message if any
    if (isset($_GET['message'])) {
        display_notification($_GET['message']);
    }

echo "<div class='row'>";
     
    // Scenario Multiplier Section (FR-13)
    renderScenarioSection($scenarios, $perPage);

    // Global Rate Section
    renderGlobalSection($manager, $perPage);
    
// Category Rate Section
    renderCategorySection($manager, $perPage);

    // Group Rate Section
    renderGroupSection($perPage);

    // GL-Specific Rate Section
    renderGLSection($perPage);
    
    echo "</div>";

    // Per page selector
    echo "<div class='mt-3'>" . _("Per page:") . " ";
    foreach ([10, 25, 50, 100] as $pp) {
        $active = $pp === $perPage ? ' font-weight-bold' : '';
        echo "<a href='quickbudget_config.php?per_page=$pp' class='$active'>$pp</a> ";
    }
    echo "</div>";

    // Import/Export Section
    echo "<div class='card mt-3'>";
    echo "<h4>" . _("Import/Export") . "</h4>";
    echo "<form method='post' action='quickbudget_config.php?action=import' enctype='multipart/form-data' class='mb-2'>";
    echo "<input type='file' name='csv_file' accept='.csv' class='form-control'>";
    echo "<input type='submit' class='btn btn-secondary mt-2' value='" . _("Import from CSV") . "'>";
    echo "</form>";
    echo "<a href='quickbudget_config.php?action=export' class='btn btn-secondary'>" . _("Export to CSV") . "</a>";
    echo "</div>";

    end_page();
}

function renderScenarioSection(array $scenarios, int $perPage): void
{
    echo "<div class='col-md-12 mb-3'>";
    echo "<div class='card'>";
    echo "<div class='card-header'>" . _("Scenario Multipliers (FR-13)") . "</div>";
    echo "<div class='card-body'>";
    echo "<table class='table table-sm table-striped'>";
    echo "<thead><tr><th>" . _("Scenario") . "</th><th>" . _("Multiplier") . "</th><th>" . _("Description") . "</th></tr></thead>";
    echo "<tbody>";
    foreach ($scenarios as $name => $data) {
        echo "<tr>";
        echo "<form method='post' action='quickbudget_config.php?action=save' style='display:flex; margin:0;'>";
        echo "<td>" . htmlspecialchars($name) . "<input type='hidden' name='type' value='scenario'></td>";
        echo "<td><input type='number' step='0.001' min='0' name='multiplier' value='" . htmlspecialchars((string)$data['multiplier']) . "' class='form-control form-control-sm' style='width:auto;'></td>";
        echo "<td>" . _("Applied to inflation rate before converting to multiplier") . "<input type='hidden' name='per_page' value='$perPage'><input type='hidden' name='scenario_id' value='" . (int)$data['id'] . "'><input type='submit' class='btn btn-sm btn-primary' value='" . _("Save") . "'></td>";
        echo "</form>";
        echo "</tr>";
    }
    echo "</tbody>";
    echo "</table>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
}

function renderGlobalSection(InflationFactorManager $manager, int $perPage): void
{
    $currentRate = $manager->getDefaultRate();
    
    echo "<div class='col-md-6'>";
    echo "<div class='card mb-3'>";
    echo "<div class='card-header'>" . _("Global Rate") . "</div>";
    echo "<div class='card-body'>";
    echo "<form method='post' action='quickbudget_config.php?action=save'>";
    echo "<input type='hidden' name='type' value='global'>";
    echo "<input type='hidden' name='per_page' value='$perPage'>";
    echo "<input type='number' step='any' name='rate' id='rate' value='" . htmlspecialchars((string)$currentRate) . "' class='form-control'>";
    echo "<input type='submit' class='btn btn-primary mt-2' value='" . _("Save Global Rate") . "'>";
    echo "</form>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
}

function renderCategorySection(InflationFactorManager $manager, int $perPage): void
{
    $categories = ['Income', 'COGS', 'Expenses', 'Assets'];
    $allRates = $manager->getAllRates()['category'] ?? [];
    
    // Paginate rates
    $rateItems = [];
    foreach ($allRates as $ref => $rate) {
        $rateItems[] = ['ref' => $ref, 'rate' => $rate];
    }
    $totalItems = count($rateItems);
    $totalPages = max(1, ceil($totalItems / $perPage));
    $pageNum = min(1, (int)($_GET['cat_page'] ?? 1));
    $offset = ($pageNum - 1) * $perPage;
    $displayItems = array_slice($rateItems, $offset, $perPage);
    
    echo "<div class='col-md-6'>";
    echo "<div class='card mb-3'>";
    echo "<div class='card-header'>" . _("Category Rates") . "</div>";
    echo "<div class='card-body'>";
    
    // Existing rates table
    echo "<table class='table table-sm table-striped'>";
    echo "<thead><tr><th>" . _("Category") . "</th><th>" . _("Rate") . "</th><th>" . _("Actions") . "</th></tr></thead>";
    echo "<tbody>";
    $odd = true;
    foreach ($displayItems as $row) {
        if (empty($row['ref'])) {
            continue;
        }
        $odd = !$odd;
        echo "<tr" . ($odd ? '' : ' class=\"tr_alt\"') . ">";
        echo "<td>" . htmlspecialchars($row['ref']) . "</td>";
        echo "<td>" . htmlspecialchars((string)$row['rate']) . "</td>";
        echo "<td><button type='button' class='btn btn-sm btn-secondary' onclick=\"editCategoryRate('" . $row['ref'] . "', " . $row['rate'] . ")\">" . _("Edit") . "</button></td>";
        echo "</tr>";
    }
    if (empty($displayItems)) {
        echo "<tr><td colspan='3' class='text-center'>" . _("No category rates defined") . "</td></tr>";
    }
    echo "</tbody>";
    echo "</table>";
    
    // Pagination for category
    if ($totalPages > 1) {
        echo "<div class='pagination'>";
        for ($i = 1; $i <= $totalPages; $i++) {
            $active = $i === $pageNum ? ' font-weight-bold' : '';
            echo "<a href='quickbudget_config.php?per_page=$perPage&cat_page=$i' class='mx-1$active'>$i</a>";
        }
        echo "</div>";
    }
    
    // Form for new/edit
    echo "<hr>";
    echo "<form method='post' action='quickbudget_config.php?action=save' id='category-form' class='p-2 border rounded'>";
    echo "<input type='hidden' name='type' value='category'>";
    echo "<input type='hidden' name='per_page' value='$perPage'>";
    echo "<input type='hidden' name='is_edit' id='category_is_edit' value='0'>";
    echo "<select name='reference' id='category_ref' class='form-control mb-2' onchange=\"setCategoryRateFromSelect(this.value)\">";
    foreach ($categories as $cat) {
        $selected = isset($allRates[$cat]) ? ' selected' : '';
        echo "<option value='$cat'$selected>$cat</option>";
    }
    echo "</select>";
    echo "<input type='number' step='any' name='rate' id='category_rate' value='' class='form-control mb-2' placeholder='Rate (e.g., 1.03 for 3%)'>";
    echo "<input type='submit' id='category_submit' class='btn btn-primary' value='" . _("Save Category Rate") . "'>";
    echo "</form>";
    
    echo "<script>
    function editCategoryRate(ref, rate) {
        document.getElementById('category_ref').value = ref;
        document.getElementById('category_rate').value = rate;
        document.getElementById('category_is_edit').value = '1';
        document.getElementById('category_submit').value = '" . _("Update Rate") . "';
        document.getElementById('category_rate').focus();
    }
    function resetCategoryForm() {
        document.getElementById('category_rate').value = '';
        document.getElementById('category_is_edit').value = '0';
        document.getElementById('category_submit').value = '" . _("Save Category Rate") . "';
    }
    function setCategoryRateFromSelect(value) {
        var rateInput = document.getElementById('category_rate');
        var existingRates = " . json_encode($allRates) . ";
        if (existingRates[value]) {
            rateInput.value = existingRates[value];
            document.getElementById('category_is_edit').value = '1';
            document.getElementById('category_submit').value = '" . _("Update Rate") . "';
        } else {
            rateInput.value = '';
            document.getElementById('category_is_edit').value = '0';
            document.getElementById('category_submit').value = '" . _("Save Category Rate") . "';
        }
    }
    </script>";
    
    echo "</div>";
    echo "</div>";
    echo "</div>";
}

function renderGroupSection(int $perPage): void
{
    $groupDAO = new GroupDAO();
    $allGroups = $groupDAO->getAllGroups();
    
    // Get existing group rates from session
    $allRates = $_SESSION['ksf_qb_factors']['group'] ?? [];
    
    // Paginate rates
    $rateItems = [];
    foreach ($allRates as $ref => $rate) {
        $rateItems[] = ['ref' => $ref, 'rate' => $rate, 'name' => $allGroups[$ref] ?? $ref];
    }
    $totalItems = count($rateItems);
    $totalPages = max(1, ceil($totalItems / $perPage));
    $pageNum = min(1, (int)($_GET['group_page'] ?? 1));
    $offset = ($pageNum - 1) * $perPage;
    $displayItems = array_slice($rateItems, $offset, $perPage);
    
    echo "<div class='col-md-6'>";
    echo "<div class='card mb-3'>";
    echo "<div class='card-header'>" . _("Group Rates") . "</div>";
    echo "<div class='card-body'>";
    
    // Existing rates table
    echo "<table class='table table-sm table-striped'>";
    echo "<thead><tr><th>" . _("Group") . "</th><th>" . _("Rate") . "</th><th>" . _("Actions") . "</th></tr></thead>";
    echo "<tbody>";
    $odd = true;
    foreach ($displayItems as $row) {
        if (empty($row['ref'])) {
            continue;
        }
        $odd = !$odd;
        echo "<tr" . ($odd ? '' : ' class=\"tr_alt\"') . ">";
        echo "<td>" . htmlspecialchars($row['name'] . ' (' . $row['ref'] . ')') . "</td>";
        echo "<td>" . htmlspecialchars((string)$row['rate']) . "</td>";
        echo "<td><button type='button' class='btn btn-sm btn-secondary' onclick=\"editGroupRate('" . $row['ref'] . "', " . $row['rate'] . ")\">" . _("Edit") . "</button></td>";
        echo "</tr>";
    }
    if (empty($displayItems)) {
        echo "<tr><td colspan='3' class='text-center'>" . _("No group rates defined") . "</td></tr>";
    }
    echo "</tbody>";
    echo "</table>";
    
    // Pagination for groups
    if ($totalPages > 1) {
        echo "<div class='pagination'>";
        for ($i = 1; $i <= $totalPages; $i++) {
            $active = $i === $pageNum ? ' font-weight-bold' : '';
            echo "<a href='quickbudget_config.php?per_page=$perPage&group_page=$i' class='mx-1$active'>$i</a>";
        }
        echo "</div>";
    }
    
    // Form for new/edit
    echo "<hr>";
    echo "<form method='post' action='quickbudget_config.php?action=save' id='group-form' class='p-2 border rounded'>";
    echo "<input type='hidden' name='type' value='group'>";
    echo "<input type='hidden' name='per_page' value='$perPage'>";
    echo "<input type='hidden' name='is_edit' id='group_is_edit' value='0'>";
    echo "<select name='reference' id='group_ref' class='form-control mb-2' onchange=\"setGroupRateFromSelect(this.value)\">";
    foreach ($allGroups as $id => $name) {
        if (empty($id)) {
            continue;
        }
        $selected = isset($allRates[$id]) ? ' selected' : '';
        echo "<option value='" . htmlspecialchars((string)$id) . "'$selected>" . htmlspecialchars((string)$name . ' (' . $id . ')') . "</option>";
    }
    echo "</select>";
    echo "<input type='number' step='any' name='rate' id='group_rate' value='' class='form-control mb-2' placeholder='Rate (e.g., 1.03 for 3%)'>";
    echo "<input type='submit' id='group_submit' class='btn btn-primary' value='" . _("Save Group Rate") . "'>";
    echo "</form>";
    
    echo "<script>
    function editGroupRate(ref, rate) {
        document.getElementById('group_ref').value = ref;
        document.getElementById('group_rate').value = rate;
        document.getElementById('group_is_edit').value = '1';
        document.getElementById('group_submit').value = '" . _("Update Rate") . "';
        document.getElementById('group_rate').focus();
    }
    function setGroupRateFromSelect(value) {
        var rateInput = document.getElementById('group_rate');
        var existingRates = " . json_encode($allRates) . ";
        if (existingRates[value]) {
            rateInput.value = existingRates[value];
            document.getElementById('group_is_edit').value = '1';
            document.getElementById('group_submit').value = '" . _("Update Rate") . "';
        } else {
            rateInput.value = '';
            document.getElementById('group_is_edit').value = '0';
            document.getElementById('group_submit').value = '" . _("Save Group Rate") . "';
        }
    }
    </script>";
    
    echo "</div>";
    echo "</div>";
    echo "</div>";
}

function renderGLSection(int $perPage): void
{
    $glDAO = new GLAccountDAO();
    $allGL = $glDAO->getAllGLAccounts();
    
    // Get existing GL rates from session
    $allRates = $_SESSION['ksf_qb_factors']['gl'] ?? [];
    
    // Paginate rates
    $rateItems = [];
    foreach ($allRates as $ref => $rate) {
        $rateItems[] = ['ref' => $ref, 'rate' => $rate, 'name' => $allGL[$ref] ?? $ref];
    }
    $totalItems = count($rateItems);
    $totalPages = max(1, ceil($totalItems / $perPage));
    $pageNum = min(1, (int)($_GET['gl_page'] ?? 1));
    $offset = ($pageNum - 1) * $perPage;
    $displayItems = array_slice($rateItems, $offset, $perPage);
    
    echo "<div class='col-md-6'>";
    echo "<div class='card mb-3'>";
    echo "<div class='card-header'>" . _("GL-Specific Rates") . "</div>";
    echo "<div class='card-body'>";
    
    // Existing rates table
    echo "<table class='table table-sm table-striped'>";
    echo "<thead><tr><th>" . _("GL Account") . "</th><th>" . _("Rate") . "</th><th>" . _("Actions") . "</th></tr></thead>";
    echo "<tbody>";
    $odd = true;
    foreach ($displayItems as $row) {
        if (empty($row['ref'])) {
            continue;
        }
        $odd = !$odd;
        echo "<tr" . ($odd ? '' : ' class=\"tr_alt\"') . ">";
        echo "<td>" . htmlspecialchars($row['ref'] . ' - ' . $row['name']) . "</td>";
        echo "<td>" . htmlspecialchars((string)$row['rate']) . "</td>";
        echo "<td><button type='button' class='btn btn-sm btn-secondary' onclick=\"editGLRate('" . $row['ref'] . "', " . $row['rate'] . ")\">" . _("Edit") . "</button></td>";
        echo "</tr>";
    }
    if (empty($displayItems)) {
        echo "<tr><td colspan='3' class='text-center'>" . _("No GL-specific rates defined") . "</td></tr>";
    }
    echo "</tbody>";
    echo "</table>";
    
    // Pagination for GL
    if ($totalPages > 1) {
        echo "<div class='pagination'>";
        for ($i = 1; $i <= $totalPages; $i++) {
            $active = $i === $pageNum ? ' font-weight-bold' : '';
            echo "<a href='quickbudget_config.php?per_page=$perPage&gl_page=$i' class='mx-1$active'>$i</a>";
        }
        echo "</div>";
    }
    
    // Form for new/edit
    echo "<hr>";
    echo "<form method='post' action='quickbudget_config.php?action=save' id='gl-form' class='p-2 border rounded'>";
    echo "<input type='hidden' name='type' value='gl'>";
    echo "<input type='hidden' name='per_page' value='$perPage'>";
    echo "<input type='hidden' name='is_edit' id='gl_is_edit' value='0'>";
    echo "<select name='reference' id='gl_ref' class='form-control mb-2' onchange=\"setGLRateFromSelect(this.value)\">";
    foreach ($allGL as $code => $name) {
        if (empty($code)) {
            continue;
        }
        $selected = isset($allRates[$code]) ? ' selected' : '';
        echo "<option value='" . htmlspecialchars((string)$code) . "'$selected>" . htmlspecialchars((string)$code . ' - ' . $name) . "</option>";
    }
    echo "</select>";
    echo "<input type='number' step='any' name='rate' id='gl_rate' value='' class='form-control mb-2' placeholder='Rate (e.g., 1.03 for 3%)'>";
    echo "<input type='submit' id='gl_submit' class='btn btn-primary' value='" . _("Save GL Rate") . "'>";
    echo "</form>";
    
    echo "<script>
    function editGLRate(ref, rate) {
        document.getElementById('gl_ref').value = ref;
        document.getElementById('gl_rate').value = rate;
        document.getElementById('gl_is_edit').value = '1';
        document.getElementById('gl_submit').value = '" . _("Update Rate") . "';
        document.getElementById('gl_rate').focus();
    }
    function setGLRateFromSelect(value) {
        var rateInput = document.getElementById('gl_rate');
        var existingRates = " . json_encode($allRates) . ";
        if (existingRates[value]) {
            rateInput.value = existingRates[value];
            document.getElementById('gl_is_edit').value = '1';
            document.getElementById('gl_submit').value = '" . _("Update Rate") . "';
        } else {
            rateInput.value = '';
            document.getElementById('gl_is_edit').value = '0';
            document.getElementById('gl_submit').value = '" . _("Save GL Rate") . "';
        }
    }
    </script>";
    
    echo "</div>";
    echo "</div>";
    echo "</div>";
}

function handle_save(): void
{
    include_once(dirname(__DIR__) . '/includes/InflationFactorManager.php');
    include_once(dirname(__DIR__) . '/includes/InflationFactorDTO.php');
    include_once(dirname(__DIR__) . '/includes/InflationFactorRepository.php');

    $type = $_POST['type'] ?? 'global';
    $rate = (float)($_POST['rate'] ?? 1.0);
    $reference = $_POST['reference'] ?? '';
    $perPage = (int)($_POST['per_page'] ?? 10);
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
        case 'group':
            $manager->setGroupRate($reference, $rate);
            $factor = new InflationFactorDTO($type, $reference, $rate, $company);
            break;
        case 'gl':
            $manager->setGLRate($reference, $rate);
            $factor = new InflationFactorDTO($type, $reference, $rate, $company);
            break;
        case 'scenario':
            $scenarioId = (int)($_POST['scenario_id'] ?? 0);
            $multiplier = (float)($_POST['multiplier'] ?? 1.0);
            db_query("UPDATE " . TB_PREF . "ksf_quickbudget_scenarios 
                    SET multiplier = " . (float)$multiplier . " WHERE id = " . (int)$scenarioId);
            $factor = null;
            break;
        default:
            $factor = null;
    }

    $isEdit = (int)($_POST['is_edit'] ?? 0);
    if ($factor) {
        $repo->save($factor);
    }

    $msg = urlencode($isEdit ? _("Rate updated successfully") : _("Rate saved successfully"));
    header("Location: quickbudget_config.php?per_page=$perPage&message=$msg");
    exit;
}

function handle_import(): void
{
    include_once(dirname(__DIR__) . '/includes/InflationFactorDTO.php');
    include_once(dirname(__DIR__) . '/includes/InflationFactorRepository.php');

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $msg = urlencode(_("No file uploaded"));
        header("Location: quickbudget_config.php?message=$msg");
        exit;
    }

    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, 'r');
    if (!$handle) {
        $msg = urlencode(_("Cannot open file"));
        header("Location: quickbudget_config.php?message=$msg");
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

    $msg = urlencode("Imported $count factors");
    header("Location: quickbudget_config.php?message=$msg");
    exit;
}

function handle_export(): void
{
    include_once(dirname(__DIR__) . '/includes/InflationFactorRepository.php');
    include_once(dirname(__DIR__) . '/includes/InflationFactorDTO.php');
    $repo = new InflationFactorRepository();

    $company = (int)($_SESSION['company'] ?? 0);
    $rows = $repo->exportToCsv($company);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="inflation_factors.csv"');

    echo "type,reference_id,rate\n";
    foreach ($rows as $row) {
        echo "{$row['type']},{$row['reference']},{$row['rate']}\n";
    }
    exit;
}
