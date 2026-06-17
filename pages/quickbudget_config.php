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
    // FR-01: Save global inflation rate
    $rate = (float)($_POST['rate'] ?? 1.0);

    // TODO: Persist to database via InflationFactorRepository

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'rate' => $rate]);
    exit;
}

function handle_import(): void
{
    // FR-04: Import inflation factors from CSV
    // TODO: Implement CSV parsing and import logic

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'imported' => 0]);
    exit;
}

function handle_export(): void
{
    // FR-06: Export inflation factors to CSV
    // TODO: Implement export logic

    header('Content-Type: text/csv');
    echo "type,reference,rate\n";
    exit;
}