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

    // FR-21: List pending budgets
    $pending = get_pending_budgets();
    if (empty($pending)) {
        echo "<p>" . _("No pending budgets") . "</p>";
    } else {
        echo "<table class='table'>";
        echo "<tr><th>" . _("Year") . "</th><th>" . _("GL Account") . "</th><th>" . _("Amount") . "</th><th>" . _("Action") . "</th></tr>";
        foreach ($pending as $budget) {
            echo "<tr>";
            echo "<td>{$budget['year']}</td>";
            echo "<td>{$budget['gl_account']}</td>";
            echo "<td>" . number_format($budget['amount'], 2) . "</td>";
            echo "<td>";
            echo "<button onclick='approveBudget({$budget['id']})' class='btn btn-success'>" . _("Approve") . "</button>";
            echo "<button onclick='rejectBudget({$budget['id']})' class='btn btn-danger'>" . _("Reject") . "</button>";
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
    $budgetId = (int)($_POST['budget_id'] ?? 0);

    $sql = "UPDATE " . TB_PREF . "ksf_quickbudget_approvals
        SET status = 'approved', approved_by = " . (int)($_SESSION['wa_current_user']->user ?? 0) . ",
            approved_at = NOW() WHERE budget_id = " . (int)$budgetId;

    // FR-24: Send notification (placeholder)
    db_query($sql, null);

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Budget approved']);
    exit;
}

function handle_reject(): void
{
    // FR-22: Reject budget with audit trail
    $budgetId = (int)($_POST['budget_id'] ?? 0);

    $sql = "UPDATE " . TB_PREF . "ksf_quickbudget_approvals
        SET status = 'rejected', approved_by = " . (int)($_SESSION['wa_current_user']->user ?? 0) . ",
            approved_at = NOW() WHERE budget_id = " . (int)$budgetId;

    db_query($sql, null);

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Budget rejected']);
    exit;
}

function get_pending_budgets(): array
{
    global $db;

    $sql = "SELECT b.id, b.gl_account, b.year, b.amount
        FROM " . TB_PREF . "ksf_quickbudget_budget b
        LEFT JOIN " . TB_PREF . "ksf_quickbudget_approvals a ON b.id = a.budget_id
        WHERE a.status IS NULL OR a.status = 'pending'";
    $result = db_query($sql, null);

    $pending = [];
    while ($row = db_fetch_assoc($result)) {
        $pending[] = $row;
    }

    return $pending;
}