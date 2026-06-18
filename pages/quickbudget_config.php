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

$path_to_root = "../..";
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

    page(_("Quick Budget Configuration"), false, false, '', '');

    echo "<div class='card'>";
    echo "<h3>" . _("Inflation Factors") . "</h3>";

    echo "<h4>" . _("Global Rate") . "</h4>";
    echo "<form id='global-form' method='post' action='quickbudget_config.php?action=save'>";
    echo "<input type='hidden' name='type' value='global'>";
    echo "<input type='number' step='0.0001' name='rate' id='rate' value='1.0350' class='form-control'>";
    echo "<input type='submit' class='btn btn-primary' value='" . _("Save Global Rate") . "'>";
    echo "</form>";

    echo "<h4>" . _("Import from CSV") . "</h4>";
    echo "<form id='import-form' method='post' action='quickbudget_config.php?action=import' enctype='multipart/form-data'>";
    echo "<input type='file' name='csv_file' accept='.csv' class='form-control'>";
    echo "<input type='submit' class='btn btn-secondary' value='" . _("Import") . "'>";
    echo "</form>";

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