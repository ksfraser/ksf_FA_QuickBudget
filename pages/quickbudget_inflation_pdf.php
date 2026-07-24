<?php
/**
 * QuickBudget Inflation PDF Export
 *
 * Generates PDF report of historical inflation analysis using tcpdf.
 * Supports Issue #2: FR-54.
 *
 * @since 1.1.0
 */
declare(strict_types=1);

$path_to_root = "../../..";
$page_security = 'SA_KSF_QUICKBUDGETVIEW';
include_once($path_to_root . "/includes/session.inc");
add_access_extensions();

global $db;

$level = $_GET['level'] ?? 'all';
$referenceId = $_GET['reference_id'] ?? '';

require_once(dirname(__DIR__) . '/vendor/autoload.php');
require_once(dirname(__DIR__) . '/src/Service/InflationStats.php');
require_once(dirname(__DIR__) . '/src/Service/InflationCalculator.php');

$calculator = new InflationCalculator();
$stats = new InflationStats();

// Calculate data and title
[$yearlyEntries, $title] = calculate_pdf_data($level, $referenceId, $calculator);
$computed = $calculator->computeStats($yearlyEntries);

// Create PDF
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

$pdf->SetCreator('ksf_FA_QuickBudget');
$pdf->SetAuthor('QuickBudget');
$pdf->SetTitle(_('Historical Inflation Analysis') . ' - ' . $title);

$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
$pdf->SetMargins(15, 15, 15);
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(true, 15);

$pdf->AddPage();

// Title
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, _('Historical Inflation Analysis'), 0, 1, 'L');
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 8, $title, 0, 1, 'L');
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 6, _('Generated') . ': ' . date('Y-m-d H:i:s'), 0, 1, 'L');
$pdf->Ln(5);

// Summary Statistics
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, _('Summary Statistics'), 0, 1, 'L');
$pdf->SetFont('helvetica', '', 10);

$statsData = [
    [_('Mean'), number_format($computed['stats']['mean'] * 100, 2) . '%'],
    [_('Median'), number_format($computed['stats']['median'] * 100, 2) . '%'],
    [_('Mode'), $computed['stats']['mode'] !== null ? number_format($computed['stats']['mode'] * 100, 2) . '%' : 'N/A'],
    [_('Min'), number_format($computed['stats']['min'] * 100, 2) . '%'],
    [_('Max'), number_format($computed['stats']['max'] * 100, 2) . '%'],
    [_('Std Dev'), number_format($computed['stats']['stddev'] * 100, 2) . '%'],
    [_('Data Points'), (string)$computed['stats']['count']],
];

$pdf->SetFillColor(230, 230, 230);
foreach ($statsData as $i => $row) {
    $fill = ($i % 2 === 0);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(50, 7, $row[0], 1, 0, 'L', $fill);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(40, 7, $row[1], 1, 1, 'R', $fill);
}

$pdf->Ln(5);

// Trend Indicators
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, _('Trend Indicators (CAGR)'), 0, 1, 'L');
$pdf->SetFont('helvetica', '', 10);

foreach ([1, 3, 5, 7, 10] as $period) {
    $val = $computed['trend_indicators'][$period] ?? null;
    $display = $val !== null ? number_format($val * 100, 2) . '%' : 'N/A';
    $pdf->Cell(30, 7, $period . ' ' . _('Year'), 0, 0, 'L');
    $pdf->Cell(30, 7, $display, 0, 1, 'R');
}

$pdf->Ln(5);

// Year-by-Year Data Table
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, _('Year-by-Year Data'), 0, 1, 'L');

$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(50, 50, 50);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(25, 7, _('Year'), 1, 0, 'C', true);
$pdf->Cell(40, 7, _('Prior Actual'), 1, 0, 'R', true);
$pdf->Cell(40, 7, _('Current Actual'), 1, 0, 'R', true);
$pdf->Cell(30, 7, _('YoY Rate'), 1, 0, 'C', true);
$pdf->Cell(25, 7, _('Status'), 1, 1, 'C', true);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', '', 9);

foreach ($yearlyEntries as $entry) {
    $yoy = $entry['yoy_rate'] !== null ? number_format($entry['yoy_rate'] * 100, 2) . '%' : 'N/A';
    $status = '';
    $statusColor = [255, 255, 255];

    if ($entry['yoy_rate'] !== null && $computed['stats']['stddev'] > 0) {
        $withinNorm = $stats->isWithinNorm(
            $entry['yoy_rate'],
            $computed['stats']['mean'],
            $computed['stats']['stddev']
        );
        $status = $withinNorm ? _('Normal') : _('Outlier');
        $statusColor = $withinNorm ? [200, 255, 200] : [255, 200, 200];
    }

    $pdf->SetFillColor(255, 255, 255);
    $pdf->Cell(25, 7, (string)$entry['year'], 1, 0, 'C');
    $pdf->Cell(40, 7, number_format($entry['actual_prior'], 2), 1, 0, 'R');
    $pdf->Cell(40, 7, number_format($entry['actual_current'], 2), 1, 0, 'R');

    $pdf->SetTextColor(0, 100, 0);
    if ($status === _('Outlier')) {
        $pdf->SetTextColor(200, 0, 0);
    }
    $pdf->Cell(30, 7, $yoy, 1, 0, 'C');
    $pdf->SetTextColor(0, 0, 0);

    $pdf->SetFillColor(...$statusColor);
    $pdf->Cell(25, 7, $status, 1, 1, 'C', true);
}

$pdf->Ln(5);

// Footer
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(0, 5, 'ksf_FA_QuickBudget - ' . _('Historical Inflation Analysis'), 0, 1, 'C');

// Output
$pdfName = 'inflation_report_' . $level . '_' . date('Ymd') . '.pdf';
$pdf->Output($pdfName, 'D');
exit;

// =========================================================================
function calculate_pdf_data(string $level, string $referenceId, InflationCalculator $calculator): array
{
    global $db;

    $title = "All Accounts";

    if ($level === 'gl' && $referenceId !== '') {
        $entries = $calculator->calculateForGL($referenceId);
        $sql = "SELECT account_name FROM " . TB_PREF . "chart_master WHERE account_code = '" . addslashes($referenceId) . "'";
        $r = db_query($sql);
        $row = $r ? db_fetch_assoc($r) : null;
        $title = $referenceId . " - " . ($row ? $row['account_name'] : '');
        return [$entries, $title];
    } elseif ($level === 'category' && $referenceId !== '') {
        $entries = $calculator->calculateForCategory($referenceId);
        $sql = "SELECT class_name FROM " . TB_PREF . "chart_class WHERE cid = '" . addslashes($referenceId) . "'";
        $r = db_query($sql);
        $row = $r ? db_fetch_assoc($r) : null;
        $title = "Category: " . ($row ? $row['class_name'] : $referenceId);
        return [$entries, $title];
    } elseif ($level === 'class' && $referenceId !== '') {
        $entries = $calculator->calculateForClass($referenceId);
        $sql = "SELECT class_name FROM " . TB_PREF . "chart_class WHERE cid = '" . addslashes($referenceId) . "'";
        $r = db_query($sql);
        $row = $r ? db_fetch_assoc($r) : null;
        $title = "Class: " . ($row ? $row['class_name'] : $referenceId);
        return [$entries, $title];
    }

    return [$calculator->calculateAll(), $title];
}
