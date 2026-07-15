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
    include_once(dirname(__DIR__) . '/includes/CategoryDAO.php');
    include_once(dirname(__DIR__) . '/includes/TypeDAO.php');
    include_once(dirname(__DIR__) . '/includes/GLAccountDAO.php');
    include_once(dirname(__DIR__) . '/includes/RateSectionRenderer.php');

    $manager = new InflationFactorManager();
    $manager->loadFromDB();

    // Store in session for quick access
    $_SESSION['ksf_qb_factors'] = $manager->getAllRates();

    // Get scenario multipliers for display
    $scenarioRepo = new ScenarioRepository();
    $scenariosArray = $scenarioRepo->getAll();
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

    $typeDAO = new TypeDAO();
      
    echo "<div class='row'>";
      
    // Scenario Multiplier Section (FR-13)
    renderScenarioSection($scenarios, $perPage);

    // Global Rate Section
    renderGlobalSection($manager, $perPage);

    // Category Rate Section
    renderCategorySection($manager, $perPage);

    // Type Rate Section
    $allTypes = $typeDAO->getAllTypes(); // Returns id => name
    renderTypeSection($perPage, $manager->getAllRates()['type'] ?? [], $allTypes);

    // GL-Specific Rate Section
    renderGLSection($perPage, $manager->getAllRates()['gl'] ?? []);
    
    echo "</div>";

    // Type Rate Cache modal (rendered at end for Bootstrap)
    echo RateSectionRenderer::renderTypeCache($manager->getAllRates(), $allTypes);

    // Per page selector
    echo "<div class='mt-3'>" . _("Per page:") . " ";
    foreach ([10, 25, 50, 100] as $pp) {
        $active = $pp === $perPage ? ' font-weight-bold' : '';
        echo "<a href='quickbudget_config.php?per_page=$pp' class='$active'>$pp</a> ";
    }
    echo "</div>";

    // Import/Export Section
    renderImportExport();

    end_page();
}

function renderImportExport(): void
{
    echo "<div class='card mt-3'>";
    echo "<h4>" . _("Import/Export") . "</h4>";
    echo "<form method='post' action='quickbudget_config.php?action=import' enctype='multipart/form-data' class='mb-2'>";
    echo "<input type='file' name='csv_file' accept='.csv' class='form-control'>";
    echo "<input type='submit' class='btn btn-secondary mt-2' value='" . _("Import from CSV") . "'>";
    echo "</form>";
    echo "<a href='quickbudget_config.php?action=export' class='btn btn-secondary'>" . _("Export to CSV") . "</a>";
    echo "</div>";
}

function renderScenarioSection(array $scenarios, int $perPage): void
{
    echo "<div class='col-md-12 mb-3'>";
    echo "<div class='card'>";
    echo "<div class='card-header'>" . _("Scenario Multipliers (FR-13)") . "</div>";
    echo "<div class='card-body'>";
    echo "<table class='table table-sm table-striped border' border=1>";
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
    echo "<div class='card mb-3' style='border: 1px solid #ddd;'>";
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
    $categoryDAO = new CategoryDAO();
    $categories = $categoryDAO->getAllCategories();
    $allRates = $manager->getAllRates()['category'] ?? [];
    
    echo RateSectionRenderer::render('category', 'Category Rates', 'Category', $allRates, $categories, $perPage, 'cat_page');
}

function renderTypeSection(int $perPage, array $typeRates = [], array $allTypes = []): void
{
    if (empty($allTypes)) {
        echo "<div class='col-md-6'>";
        echo "<div class='card mb-3' style='border: 1px solid #ddd;'>";
        echo "<div class='card-header'>" . _("Type Rates") . "</div>";
        echo "<div class='card-body'>";
        echo "<p class='text-warning'>No types available in chart_types table";
        echo "</div></div></div>";
        return;
    }
    
    echo RateSectionRenderer::render('type', 'Type Rates', 'Type', $typeRates, $allTypes, $perPage, 'type_page');
}

function renderGLSection(int $perPage, array $glRates = []): void
{
    $glDAO = new GLAccountDAO();
    $allGL = $glDAO->getAllGLAccounts();
    
    echo RateSectionRenderer::render('gl', 'GL-Specific Rates', 'GL Account', $glRates, $allGL, $perPage, 'gl_page', true);
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
    $isEdit = (int)($_POST['is_edit'] ?? 0);

    // Log POST data
    $logFile = dirname(__DIR__) . "/logs/debug.log";
    file_put_contents($logFile, date('Y-m-d H:i:s') . " handle_save: type=" . $type . ", ref=" . $reference . ", rate=" . $rate . "\n", FILE_APPEND);
    
    $manager = new InflationFactorManager();
    $repo = new InflationFactorRepository();

    switch ($type) {
        case 'global':
            $manager->setGlobalRate($rate);
            $factor = new InflationFactorDTO($type, '', $rate);
            break;
        case 'category':
            $manager->setCategoryRate((int)$reference, $rate);
            $factor = new InflationFactorDTO($type, (string)$reference, $rate);
            break;
        case 'type':
            $manager->setTypeRate((int)$reference, $rate);
            $factor = new InflationFactorDTO($type, (string)$reference, $rate);
            break;
        case 'gl':
            $manager->setGLRate($reference, $rate);
            $factor = new InflationFactorDTO($type, (string)$reference, $rate);
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

    $saveSuccess = true;
    if ($factor) {
        $saved = $repo->save($factor);
        error_log("handle_save: type={$type}, ref={$reference}, rate={$rate}, sessionCompany=" . ($_SESSION['company'] ?? 'not set') . ", saved=" . ($saved ? 'true' : 'false'));
        if (!$saved) {
            error_log("handle_save: save failed for type={$type}, ref={$reference}");
            $saveSuccess = false;
        }
        $manager->loadFromDB();
        $manager->invalidateResolvedTypeCache(); // Clear file cache on rate changes
        $_SESSION['ksf_qb_factors'] = $manager->getAllRates();
    }

    if (!$saveSuccess) {
        $msg = urlencode(_("Error: Rate save failed - check logs"));
    } else {
        $msg = urlencode($isEdit ? _("Rate updated successfully") : _("Rate saved successfully"));
    }
    session_write_close();
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
    $count = $repo->importFromCsv($csvRows);

    // Invalidate cache after import
    $manager = new InflationFactorManager();
    $manager->invalidateResolvedTypeCache();

    $msg = urlencode("Imported $count factors");
    header("Location: quickbudget_config.php?message=$msg");
    exit;
}

function handle_export(): void
{
    include_once(dirname(__DIR__) . '/includes/InflationFactorRepository.php');
    include_once(dirname(__DIR__) . '/includes/InflationFactorDTO.php');
    $repo = new InflationFactorRepository();

    $rows = $repo->exportToCsv();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="inflation_factors.csv"');

    echo "type,reference_id,rate\n";
    foreach ($rows as $row) {
        echo "{$row['type']},{$row['reference']},{$row['rate']}\n";
    }
    exit;
}