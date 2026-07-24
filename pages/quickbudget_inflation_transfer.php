<?php
/**
 * QuickBudget Inflation Transfer to Config
 *
 * Transfers observed historical inflation rates to the budget config.
 * Supports Issue #2: FR-51, FR-52, FR-53.
 *
 * @see Project_Docs/Issue_2_Plan.md - Phase 5
 * @since 1.1.0
 */
declare(strict_types=1);

$path_to_root = "../../..";
$page_security = 'SA_KSF_QUICKBUDGETVIEW';
include_once($path_to_root . "/includes/session.inc");
add_access_extensions();

global $db;

$action = $_GET['action'] ?? $_POST['action'] ?? 'preview';

switch ($action) {
    case 'preview':
        handle_preview();
        break;
    case 'confirm':
        handle_confirm();
        break;
    case 'bulk_preview':
        handle_bulk_preview();
        break;
    case 'bulk_confirm':
        handle_bulk_confirm();
        break;
    default:
        handle_preview();
}

// =========================================================================
// PREVIEW: Show diff between current config and observed rate
// =========================================================================

function handle_preview(): void
{
    global $db;

    $level = $_POST['level'] ?? $_GET['level'] ?? 'gl';
    $referenceId = $_POST['reference_id'] ?? $_GET['reference_id'] ?? '';
    $year = (int)($_POST['year'] ?? $_GET['year'] ?? 0);
    $stat = $_POST['stat'] ?? $_GET['stat'] ?? '';

    if ($referenceId === '') {
        redirect_with_msg(_("No item selected for transfer"));
        return;
    }

    require_once(dirname(__DIR__) . '/includes/InflationFactorManager.php');

    // Get current configured rate
    $manager = new InflationFactorManager();
    $manager->loadFromDB();

    $currentRate = 0.0;
    switch ($level) {
        case 'gl':
            $currentRate = get_rate_for_account_from_manager($manager, $referenceId);
            break;
        case 'category':
            $rate = $manager->getCategoryRate($referenceId);
            $currentRate = $rate !== null ? $rate : $manager->getDefaultRate();
            break;
        case 'class':
            $rate = $manager->getCategoryRate($referenceId);
            $currentRate = $rate !== null ? $rate : $manager->getDefaultRate();
            break;
    }

    // Calculate observed rate
    $observedRate = calculate_observed_rate($level, $referenceId, $year, $stat);

    if ($observedRate === null) {
        redirect_with_msg(_("Could not calculate observed rate for this item"));
        return;
    }

    // Show preview
    page(_("Transfer Rate to Config"), false, false, '', '');

    echo "<div class='card'>";
    echo "<div class='card-header'>" . _("Preview Transfer") . "</div>";
    echo "<div class='card-body'>";

    echo "<table class='table table-bordered'>";
    echo "<tr><th>" . _("Item") . "</th><td>" . htmlspecialchars($referenceId) . " ($level)</td></tr>";
    echo "<tr><th>" . _("Source") . "</th><td>" . ($stat !== '' ? $stat . " CAGR" : "Year $year") . "</td></tr>";
    echo "<tr><th>" . _("Current Configured Rate") . "</th><td>" . number_format($currentRate, 4) . "%</td></tr>";
    echo "<tr><th>" . _("Observed Rate") . "</th><td>" . number_format($observedRate, 4) . "%</td></tr>";
    echo "<tr><th>" . _("Change") . "</th><td>" . number_format($observedRate - $currentRate, 4) . "%</td></tr>";
    echo "</table>";

    echo "<form method='post' action='quickbudget_inflation_transfer.php?action=confirm'>";
    echo "<input type='hidden' name='level' value='" . htmlspecialchars($level) . "'>";
    echo "<input type='hidden' name='reference_id' value='" . htmlspecialchars($referenceId) . "'>";
    echo "<input type='hidden' name='rate' value='" . (string)$observedRate . "'>";
    echo "<input type='submit' class='btn btn-primary' value='" . _("Confirm Transfer") . "'> ";
    echo "<a href='javascript:history.back()' class='btn btn-secondary'>" . _("Cancel") . "</a>";
    echo "</form>";

    echo "</div></div>";
    end_page();
}

// =========================================================================
// CONFIRM: Write the rate to config
// =========================================================================

function handle_confirm(): void
{
    global $db;

    $level = $_POST['level'] ?? 'gl';
    $referenceId = $_POST['reference_id'] ?? '';
    $rate = (float)($_POST['rate'] ?? 0);

    if ($referenceId === '' || $rate === 0.0) {
        redirect_with_msg(_("Invalid transfer parameters"));
        return;
    }

    require_once(dirname(__DIR__) . '/includes/InflationFactorManager.php');
    require_once(dirname(__DIR__) . '/includes/InflationFactorDTO.php');
    require_once(dirname(__DIR__) . '/includes/InflationFactorRepository.php');

    $repo = new InflationFactorRepository();
    $factorType = ($level === 'class') ? 'category' : $level;
    $factor = new InflationFactorDTO($factorType, $referenceId, $rate);
    $saved = $repo->save($factor);

    if ($saved) {
        // Invalidate cache
        $manager = new InflationFactorManager();
        $manager->invalidateResolvedTypeCache();

        $msg = urlencode(sprintf(
            _("Rate for %s %s updated to %s%%"),
            $level,
            $referenceId,
            number_format($rate, 2)
        ));
    } else {
        $msg = urlencode(_("Error: Failed to save rate"));
    }

    session_write_close();
    header("Location: quickbudget_config.php?message=$msg");
    exit;
}

// =========================================================================
// BULK PREVIEW: Show all items for a level with preview
// =========================================================================

function handle_bulk_preview(): void
{
    global $db;

    $level = $_POST['level'] ?? 'all';
    $stat = $_POST['stat'] ?? 'mean';
    $itemRates = $_POST['item_rates'] ?? [];

    page(_("Bulk Transfer Preview"), false, false, '', '');

    require_once(dirname(__DIR__) . '/includes/InflationFactorManager.php');

    $manager = new InflationFactorManager();
    $manager->loadFromDB();

    echo "<div class='card'>";
    echo "<div class='card-header'>" . _("Bulk Transfer Preview") . "</div>";
    echo "<div class='card-body'>";

    echo "<table class='table table-sm table-striped'>";
    echo "<thead><tr><th>" . _("Item") . "</th><th>" . _("Current Rate") . "</th><th>" . _("Observed Rate") . "</th><th>" . _("Change") . "</th></tr></thead>";
    echo "<tbody>";

    $items = [];
    foreach ($itemRates as $ref => $rate) {
        $currentRate = 0.0;
        switch ($level) {
            case 'gl':
                $currentRate = get_rate_for_account_from_manager($manager, $ref);
                break;
            case 'category':
            case 'class':
                $r = $manager->getCategoryRate($ref);
                $currentRate = $r !== null ? $r : $manager->getDefaultRate();
                break;
        }
        $observedRate = (float)$rate;
        $change = $observedRate - $currentRate;
        $items[] = ['ref' => $ref, 'current' => $currentRate, 'observed' => $observedRate, 'change' => $change];

        echo "<tr>";
        echo "<td>" . htmlspecialchars($ref) . "</td>";
        echo "<td>" . number_format($currentRate, 4) . "%</td>";
        echo "<td>" . number_format($observedRate, 4) . "%</td>";
        echo "<td>" . number_format($change, 4) . "%</td>";
        echo "</tr>";
    }

    echo "</tbody></table>";

    echo "<form method='post' action='quickbudget_inflation_transfer.php?action=bulk_confirm'>";
    echo "<input type='hidden' name='level' value='" . htmlspecialchars($level) . "'>";
    echo "<input type='hidden' name='stat' value='" . htmlspecialchars($stat) . "'>";
    // Re-encode item_rates as JSON
    echo "<input type='hidden' name='item_rates_json' value='" . htmlspecialchars(json_encode($itemRates)) . "'>";
    echo "<input type='submit' class='btn btn-primary' value='" . _("Confirm All Transfers") . "'> ";
    echo "<a href='javascript:history.back()' class='btn btn-secondary'>" . _("Cancel") . "</a>";
    echo "</form>";

    echo "</div></div>";
    end_page();
}

// =========================================================================
// BULK CONFIRM: Write all rates to config
// =========================================================================

function handle_bulk_confirm(): void
{
    global $db;

    $level = $_POST['level'] ?? 'all';
    $itemRatesJson = $_POST['item_rates_json'] ?? '{}';
    $itemRates = json_decode($itemRatesJson, true);

    if (empty($itemRates)) {
        redirect_with_msg(_("No items to transfer"));
        return;
    }

    require_once(dirname(__DIR__) . '/includes/InflationFactorDTO.php');
    require_once(dirname(__DIR__) . '/includes/InflationFactorRepository.php');

    $repo = new InflationFactorRepository();
    $count = 0;

    foreach ($itemRates as $ref => $rate) {
        $factorType = ($level === 'class') ? 'category' : ($level === 'all' ? 'global' : $level);
        $factor = new InflationFactorDTO($factorType, $ref, (float)$rate);
        if ($repo->save($factor)) {
            $count++;
        }
    }

    // Invalidate cache
    $manager = new InflationFactorManager();
    $manager->invalidateResolvedTypeCache();

    $msg = urlencode(sprintf(_("Transferred %d rates to config"), $count));
    session_write_close();
    header("Location: quickbudget_config.php?message=$msg");
    exit;
}

// =========================================================================
// HELPERS
// =========================================================================

function get_rate_for_account_from_manager(InflationFactorManager $manager, string $accountCode): float
{
    global $db;

    $sql = "SELECT ct.id AS type_id, ct.class_id
            FROM " . TB_PREF . "chart_master cm
            LEFT JOIN " . TB_PREF . "chart_types ct ON cm.account_type = ct.id
            WHERE cm.account_code = '" . addslashes($accountCode) . "'";
    $result = db_query($sql);
    if ($result && $row = db_fetch_assoc($result)) {
        $typeId = (string)$row['type_id'];
        $classId = (string)$row['class_id'];

        // Check type rate
        $typeRates = $manager->getAllRates()['type'] ?? [];
        if (isset($typeRates[$typeId])) {
            return $typeRates[$typeId];
        }
        // Check category rate
        $catRates = $manager->getAllRates()['category'] ?? [];
        if (isset($catRates[$classId])) {
            return $catRates[$classId];
        }
    }
    return $manager->getDefaultRate();
}

function calculate_observed_rate(string $level, string $referenceId, int $year, string $stat): ?float
{
    require_once(dirname(__DIR__) . '/src/Service/InflationStats.php');
    require_once(dirname(__DIR__) . '/src/Service/InflationCalculator.php');

    $calculator = new InflationCalculator();

    switch ($level) {
        case 'gl':
            $entries = $calculator->calculateForGL($referenceId);
            break;
        case 'category':
            $entries = $calculator->calculateForCategory($referenceId);
            break;
        case 'class':
            $entries = $calculator->calculateForClass($referenceId);
            break;
        default:
            return null;
    }

    if (empty($entries)) {
        return null;
    }

    // Get the latest year's YoY rate if no specific year/stat
    if ($stat !== '') {
        $statsObj = new InflationStats();
        $computed = $calculator->computeStats($entries);
        $indicators = $computed['trend_indicators'];

        switch ($stat) {
            case '1yr': return $indicators[1] !== null ? $indicators[1] * 100 : null;
            case '3yr': return $indicators[3] !== null ? $indicators[3] * 100 : null;
            case '5yr': return $indicators[5] !== null ? $indicators[5] * 100 : null;
            case '7yr': return $indicators[7] !== null ? $indicators[7] * 100 : null;
            case '10yr': return $indicators[10] !== null ? $indicators[10] * 100 : null;
            case 'mean': return $computed['stats']['mean'] * 100;
            case 'median': return $computed['stats']['median'] * 100;
            case 'mode': return $computed['stats']['mode'] !== null ? $computed['stats']['mode'] * 100 : null;
        }
    }

    if ($year > 0) {
        foreach ($entries as $entry) {
            if ($entry['year'] === $year && $entry['yoy_rate'] !== null) {
                return $entry['yoy_rate'] * 100;
            }
        }
    }

    // Default: latest year
    $latest = end($entries);
    return $latest['yoy_rate'] !== null ? $latest['yoy_rate'] * 100 : null;
}

function redirect_with_msg(string $msg): void
{
    session_write_close();
    header("Location: quickbudget_report.php?message=" . urlencode($msg));
    exit;
}
