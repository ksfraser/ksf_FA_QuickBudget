<?php
/**
 * QuickBudget Approval Page
 *
 * Budget approval workflow.
 * Supports FR-20 through FR-24.
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
    case 'submit':
        handle_submit();
        break;
    case 'approve':
        handle_approve();
        break;
    case 'reject':
        handle_reject();
        break;
    default:
        render_view();
}

function render_view(): void
{
    global $path_to_root;

    page(_("Budget Approval"), false, false, '', '');

    echo "<div class='card'>";
    echo "<h3>" . _("Pending Budget Approvals") . "</h3>";

    // FR-21: List pending budgets from FA native budget_trans
    $year = (int)date('Y');
    $pending = get_pending_budgets($year);
    if (empty($pending)) {
        echo "<p>" . _("No pending budgets") . "</p>";
    } else {
        echo "<table class='table'>";
        echo "<tr><th>" . _("Date") . "</th><th>" . _("GL Account") . "</th><th>" . _("Amount") . "</th><th>" . _("Action") . "</th></tr>";
        foreach ($pending as $budget) {
            echo "<tr>";
            echo "<td>{$budget['tran_date']}</td>";
            echo "<td>{$budget['gl_account']}</td>";
            echo "<td>" . number_format($budget['amount'], 2) . "</td>";
            echo "<td>";
            echo "<button aspect='nonajax' onclick='approveBudget(\"{$budget['tran_date']}\",\"{$budget['gl_account']}\")' class='btn btn-success'>" . _("Approve") . "</button>";
            echo "<button aspect='nonajax' onclick='rejectBudget(\"{$budget['tran_date']}\",\"{$budget['gl_account']}\")' class='btn btn-danger'>" . _("Reject") . "</button>";
            echo "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    echo "</div>";

    end_page();
}

function handle_submit(): void
{
    // FR-21: Submit budget for approval (placeholder)
    $budgetId = (int)($_POST['budget_id'] ?? 0);

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Budget submitted for approval']);
    exit;
}

function handle_approve(): void
{
    // FR-22: Approve budget with audit trail
    global $db;
    $tranDate = $_POST['tran_date'] ?? '';
    $glAccount = $_POST['gl_account'] ?? '';

    $sql = "UPDATE " . TB_PREF . "ksf_quickbudget_approvals
        SET status = 'approved', approved_by = " . (int)($_SESSION['wa_current_user']->user ?? 0) . ",
            approved_at = NOW() 
        WHERE tran_date = '" . mysqli_real_escape_string($db, $tranDate) . "'
        AND gl_account = '" . mysqli_real_escape_string($db, $glAccount) . "'";

    db_query($sql, null);

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Budget approved']);
    exit;
}

function handle_reject(): void
{
    // FR-22: Reject budget with audit trail
    global $db;
    $tranDate = $_POST['tran_date'] ?? '';
    $glAccount = $_POST['gl_account'] ?? '';

    $sql = "UPDATE " . TB_PREF . "ksf_quickbudget_approvals
        SET status = 'rejected', approved_by = " . (int)($_SESSION['wa_current_user']->user ?? 0) . ",
            approved_at = NOW()
        WHERE tran_date = '" . mysqli_real_escape_string($db, $tranDate) . "'
        AND gl_account = '" . mysqli_real_escape_string($db, $glAccount) . "'";

    db_query($sql, null);

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Budget rejected']);
    exit;
}

function get_pending_budgets(int $year): array
{
    // Query FA native budget_trans table for pending approvals
    $sql = "SELECT bt.tran_date, bt.account as gl_account, bt.amount
        FROM " . TB_PREF . "budget_trans bt
        LEFT JOIN " . TB_PREF . "ksf_quickbudget_approvals qa 
            ON bt.tran_date = qa.tran_date AND bt.account = qa.gl_account
        WHERE YEAR(bt.tran_date) = " . (int)$year . "
        AND (qa.status IS NULL OR qa.status = 'pending')";
    $result = db_query($sql, null);

    $pending = [];
    while ($row = db_fetch_assoc($result)) {
        $pending[] = $row;
    }

    return $pending;
}