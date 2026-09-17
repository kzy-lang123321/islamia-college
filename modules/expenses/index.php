<?php
/**
 * Campus Expenses & Facility Rent Module
 * Islamia College of Technology College Management System
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole(['admin', 'accountant']);

$pdo = Database::getConnection();
$currentUser = getCurrentUser();
$userRole = strtolower($currentUser['role'] ?? 'accountant');
$canManage = in_array($userRole, ['admin', 'accountant']);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfRequest();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $type    = trim($_POST['expense_type'] ?? 'Building Rent');
        $month   = strtoupper(trim($_POST['month_name'] ?? ''));
        $year    = (int)($_POST['year_num'] ?? date('Y'));
        $due     = (float)($_POST['amount_due'] ?? 0);
        $paid    = (float)($_POST['amount_paid'] ?? 0);
        $date    = !empty($_POST['payment_date']) ? $_POST['payment_date'] : date('Y-m-d');
        $payee   = trim($_POST['payee_name'] ?? 'Campus Landlord');
        $notes   = trim($_POST['notes'] ?? '');
        $balance = max(0.0, $due - $paid);

        if (empty($month) || $due <= 0) {
            $error = "Billing month and valid due amount are required.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO campus_expenses (expense_type, month_name, year_num, amount_due, amount_paid, payment_date, balance_dues, payee_name, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$type, $month, $year, $due, $paid, $date, $balance, $payee, $notes]);
                $message = "Expense of Rs. " . number_format($paid, 0) . " recorded for {$month} {$year}.";
            } catch (PDOException $e) {
                error_log("Create expense error: " . $e->getMessage());
                $error = "An error occurred while saving the campus expense.";
            }
        }
    } elseif ($action === 'delete') {
        if ($userRole !== 'admin') {
            $error = "Only system administrators can delete expense records.";
        } else {
            $id = (int)($_POST['id'] ?? 0);
            try {
                $stmt = $pdo->prepare("DELETE FROM campus_expenses WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Expense record deleted successfully.";
            } catch (PDOException $e) {
                error_log("Delete expense error: " . $e->getMessage());
                $error = "An error occurred while deleting the expense record.";
            }
        }
    }
}

$expenses = $pdo->query("SELECT * FROM campus_expenses ORDER BY year_num DESC, id DESC")->fetchAll();

$totalDue     = (float)array_sum(array_column($expenses, 'amount_due'));
$totalPaid    = (float)array_sum(array_column($expenses, 'amount_paid'));
$totalBalance = (float)array_sum(array_column($expenses, 'balance_dues'));

$pageTitle = "Campus Operational Expenses";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Campus Operational Expenses</h1>
        <p class="page-breadcrumb">Financial Management • Campus building rent, facility utilities, and operational costs</p>
    </div>
    <div>
        <button type="button" class="btn btn-primary" onclick="openModal('addExpenseModal')">
            <span>+ Record Expense</span>
        </button>
    </div>
</div>

<?php if ($message): ?>
    <div style="padding:14px 18px; background:rgba(16,185,129,0.12); border:1px solid rgba(16,185,129,0.3); color:#6ee7b7; border-radius:10px; margin-bottom:20px;">
        ✓ <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div style="padding:14px 18px; background:rgba(244,63,94,0.12); border:1px solid rgba(244,63,94,0.3); color:#fda4af; border-radius:10px; margin-bottom:20px;">
        ⚠️ <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<!-- Summary Metrics -->
<div class="metric-grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); margin-bottom: 24px;">
    <div class="metric-card">
        <div class="metric-header">
            <span class="metric-title">Total Billed</span>
            <span class="metric-icon">🏢</span>
        </div>
        <div class="metric-value">Rs. <?= number_format($totalDue, 0) ?></div>
        <div class="metric-sub">Facility Rent & Utilities</div>
    </div>
    <div class="metric-card emerald">
        <div class="metric-header">
            <span class="metric-title">Total Paid</span>
            <span class="metric-icon">💰</span>
        </div>
        <div class="metric-value">Rs. <?= number_format($totalPaid, 0) ?></div>
        <div class="metric-sub">Disbursed Operational Funds</div>
    </div>
    <div class="metric-card amber">
        <div class="metric-header">
            <span class="metric-title">Outstanding Liabilities</span>
            <span class="metric-icon">⏳</span>
        </div>
        <div class="metric-value">Rs. <?= number_format($totalBalance, 0) ?></div>
        <div class="metric-sub">Payable Dues</div>
    </div>
</div>

<!-- Expenses Table -->
<div class="glass-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Billing Period</th>
                    <th>Expense Type</th>
                    <th>Payee Name</th>
                    <th>Billed Amount</th>
                    <th>Paid Amount</th>
                    <th>Payment Date</th>
                    <th>Balance Due</th>
                    <th>Remarks</th>
                    <?php if ($userRole === 'admin'): ?>
                        <th style="text-align:right;">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($expenses)): ?>
                    <tr>
                        <td colspan="<?= $userRole === 'admin' ? 9 : 8 ?>" class="empty-state">
                            <div class="empty-state-icon">🏢</div>
                            <div class="empty-state-title">No expense records found</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($expenses as $x): ?>
                        <tr>
                            <td><strong style="color: var(--primary-light);"><?= htmlspecialchars($x['month_name']) ?> <?= (int)$x['year_num'] ?></strong></td>
                            <td><span style="padding: 3px 8px; border-radius: 4px; background: rgba(255,255,255,0.06); font-size: 12px;"><?= htmlspecialchars($x['expense_type']) ?></span></td>
                            <td><?= htmlspecialchars($x['payee_name'] ?: 'Campus Landlord') ?></td>
                            <td>Rs. <?= number_format((float)$x['amount_due'], 0) ?></td>
                            <td><strong style="color: var(--accent-emerald);">Rs. <?= number_format((float)$x['amount_paid'], 0) ?></strong></td>
                            <td style="color: var(--text-muted); font-size: 13px;"><?= $x['payment_date'] ? date('M d, Y', strtotime($x['payment_date'])) : '—' ?></td>
                            <td>
                                <strong style="color: <?= $x['balance_dues'] > 0 ? 'var(--accent-amber)' : 'var(--text-muted)' ?>;">
                                    Rs. <?= number_format((float)$x['balance_dues'], 0) ?>
                                </strong>
                            </td>
                            <td style="color: var(--text-muted); max-width: 200px;"><?= htmlspecialchars($x['notes'] ?: '—') ?></td>
                            <?php if ($userRole === 'admin'): ?>
                                <td style="text-align:right;">
                                    <button type="button" class="btn btn-danger btn-sm" onclick="confirmDeleteExpense(<?= $x['id'] ?>)">Delete</button>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Record Expense -->
<div class="modal-overlay" id="addExpenseModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Record Campus Expense</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('addExpenseModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="add_exp_month">Billing Month <span class="required">*</span></label>
                        <input type="text" id="add_exp_month" name="month_name" class="form-control" required placeholder="e.g. JANUARY, FEBRUARY" style="text-transform:uppercase;">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_exp_year">Year <span class="required">*</span></label>
                        <input type="number" id="add_exp_year" name="year_num" class="form-control" required value="<?= date('Y') ?>">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="add_exp_type">Expense Category</label>
                        <select id="add_exp_type" name="expense_type" class="form-control">
                            <option value="Building Rent">Building Rent</option>
                            <option value="Electricity / WAPDA">Electricity / WAPDA</option>
                            <option value="Internet & Telecom">Internet & Telecom</option>
                            <option value="Campus Maintenance">Campus Maintenance</option>
                            <option value="Office Supplies">Office Supplies</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_exp_payee">Payee / Beneficiary</label>
                        <input type="text" id="add_exp_payee" name="payee_name" class="form-control" value="Campus Landlord">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="add_exp_due">Amount Due (PKR) <span class="required">*</span></label>
                        <input type="number" id="add_exp_due" name="amount_due" class="form-control" required step="100" placeholder="e.g. 25000">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_exp_paid">Amount Paid (PKR) <span class="required">*</span></label>
                        <input type="number" id="add_exp_paid" name="amount_paid" class="form-control" required step="100" placeholder="e.g. 25000">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="add_exp_date">Disbursement Date</label>
                    <input type="date" id="add_exp_date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="add_exp_notes">Notes / Receipt Voucher Details</label>
                    <textarea id="add_exp_notes" name="notes" class="form-control" placeholder="Context or receipt voucher reference..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-glass" onclick="closeModal('addExpenseModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Expense</button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden Delete Form with CSRF -->
<form id="delete-exp-form" method="POST" action="" style="display:none;">
    <?= getCsrfField() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_exp_id">
</form>

<script>
function confirmDeleteExpense(id) {
    confirmAction(
        'Delete Campus Expense?',
        'Are you sure you want to delete this expense record from the ledger?',
        () => {
            document.getElementById('delete_exp_id').value = id;
            document.getElementById('delete-exp-form').submit();
        }
    );
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
