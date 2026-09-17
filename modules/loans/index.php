<?php
/**
 * Loans & Advances (Amin Qarzi) Management Module
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
        $name    = trim($_POST['borrower_name'] ?? '');
        $amount  = (float)($_POST['amount_disbursed'] ?? 0);
        $purpose = trim($_POST['purpose'] ?? '');
        $date    = !empty($_POST['disbursement_date']) ? $_POST['disbursement_date'] : date('Y-m-d');

        if (empty($name) || $amount <= 0) {
            $error = "Borrower name and positive loan amount are required.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO loans_and_advances (borrower_name, amount_disbursed, amount_repaid, balance_due, purpose, disbursement_date, status)
                    VALUES (?, ?, 0.00, ?, ?, ?, 'pending')
                ");
                $stmt->execute([$name, $amount, $amount, $purpose, $date]);
                $message = "Loan/advance of Rs. " . number_format($amount, 0) . " recorded for {$name}.";
            } catch (PDOException $e) {
                error_log("Create loan error: " . $e->getMessage());
                $error = "An error occurred while saving the loan record.";
            }
        }
    } elseif ($action === 'repay') {
        $id       = (int)($_POST['id'] ?? 0);
        $repayAmt = (float)($_POST['amount_repaid'] ?? 0);

        if ($id <= 0 || $repayAmt <= 0) {
            $error = "Valid loan record and repayment amount are required.";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM loans_and_advances WHERE id = ?");
            $stmt->execute([$id]);
            $loan = $stmt->fetch();

            if ($loan) {
                $newRepaid = (float)$loan['amount_repaid'] + $repayAmt;
                $newBalance = max(0.0, (float)$loan['amount_disbursed'] - $newRepaid);
                $newStatus = $newBalance <= 0 ? 'settled' : 'partially_paid';

                $updateStmt = $pdo->prepare("UPDATE loans_and_advances SET amount_repaid = ?, balance_due = ?, status = ? WHERE id = ?");
                $updateStmt->execute([$newRepaid, $newBalance, $newStatus, $id]);
                $message = "Repayment of Rs. " . number_format($repayAmt, 0) . " recorded successfully.";
            }
        }
    } elseif ($action === 'delete') {
        if ($userRole !== 'admin') {
            $error = "Only system administrators can delete loan records.";
        } else {
            $id = (int)($_POST['id'] ?? 0);
            try {
                $stmt = $pdo->prepare("DELETE FROM loans_and_advances WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Loan record deleted successfully.";
            } catch (PDOException $e) {
                error_log("Delete loan error: " . $e->getMessage());
                $error = "An error occurred while deleting the loan record.";
            }
        }
    }
}

// Summary Metrics
$totalDisbursed = (float)$pdo->query("SELECT COALESCE(SUM(amount_disbursed), 0) FROM loans_and_advances")->fetchColumn();
$totalRepaid    = (float)$pdo->query("SELECT COALESCE(SUM(amount_repaid), 0) FROM loans_and_advances")->fetchColumn();
$totalBalance   = max(0.0, $totalDisbursed - $totalRepaid);

$loans = $pdo->query("SELECT * FROM loans_and_advances ORDER BY id DESC")->fetchAll();

$pageTitle = "Loans & Advances (Amin Qarzi)";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Loans & Institutional Advances</h1>
        <p class="page-breadcrumb">Financial Management • Institutional advances (Amin Qarzi) and repayment tracking</p>
    </div>
    <div>
        <button type="button" class="btn btn-primary" onclick="openModal('addLoanModal')">
            <span>+ Record New Advance</span>
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

<!-- Metrics -->
<div class="metric-grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); margin-bottom: 24px;">
    <div class="metric-card">
        <div class="metric-header">
            <span class="metric-title">Total Disbursed</span>
            <span class="metric-icon">🤝</span>
        </div>
        <div class="metric-value">Rs. <?= number_format($totalDisbursed, 0) ?></div>
        <div class="metric-sub">Principal Institutional Advances</div>
    </div>
    <div class="metric-card emerald">
        <div class="metric-header">
            <span class="metric-title">Total Repaid</span>
            <span class="metric-icon">💰</span>
        </div>
        <div class="metric-value">Rs. <?= number_format($totalRepaid, 0) ?></div>
        <div class="metric-sub">Recovered Funds</div>
    </div>
    <div class="metric-card amber">
        <div class="metric-header">
            <span class="metric-title">Outstanding Balance</span>
            <span class="metric-icon">⏳</span>
        </div>
        <div class="metric-value">Rs. <?= number_format($totalBalance, 0) ?></div>
        <div class="metric-sub">Receivable Loans</div>
    </div>
</div>

<!-- Loans Table -->
<div class="glass-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Borrower Name</th>
                    <th>Date Disbursed</th>
                    <th>Disbursed Amount</th>
                    <th>Amount Repaid</th>
                    <th>Balance Due</th>
                    <th>Purpose / Notes</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($loans)): ?>
                    <tr>
                        <td colspan="8" class="empty-state">
                            <div class="empty-state-icon">🤝</div>
                            <div class="empty-state-title">No loan or advance records found</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($loans as $l): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($l['borrower_name']) ?></strong></td>
                            <td style="color: var(--text-muted); font-size: 13px;"><?= $l['disbursement_date'] ? date('M d, Y', strtotime($l['disbursement_date'])) : '—' ?></td>
                            <td>Rs. <?= number_format((float)$l['amount_disbursed'], 0) ?></td>
                            <td style="color: var(--accent-emerald);">Rs. <?= number_format((float)$l['amount_repaid'], 0) ?></td>
                            <td>
                                <strong style="color: <?= $l['balance_due'] > 0 ? 'var(--accent-amber)' : 'var(--text-muted)' ?>;">
                                    Rs. <?= number_format((float)$l['balance_due'], 0) ?>
                                </strong>
                            </td>
                            <td style="color: var(--text-muted); max-width: 240px;"><?= htmlspecialchars($l['purpose'] ?: 'Institutional Advance') ?></td>
                            <td>
                                <span class="status-badge <?= $l['status'] === 'settled' ? 'badge-active' : ($l['status'] === 'partially_paid' ? 'badge-partial' : 'badge-pending') ?>">
                                    <?= ucfirst(str_replace('_', ' ', htmlspecialchars($l['status']))) ?>
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                    <?php if ($l['balance_due'] > 0): ?>
                                        <button type="button" class="btn btn-glass btn-sm" onclick="openRepayModal(<?= $l['id'] ?>, '<?= htmlspecialchars($l['borrower_name'], ENT_QUOTES) ?>', <?= (float)$l['balance_due'] ?>)">Repay</button>
                                    <?php endif; ?>
                                    <?php if ($userRole === 'admin'): ?>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="confirmDeleteLoan(<?= $l['id'] ?>, '<?= htmlspecialchars($l['borrower_name'], ENT_QUOTES) ?>')">Delete</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: New Loan -->
<div class="modal-overlay" id="addLoanModal">
    <div class="modal-dialog" style="max-width: 480px;">
        <div class="modal-header">
            <h3 class="modal-title">Record Advance / Loan</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('addLoanModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="add_borrower">Borrower Full Name <span class="required">*</span></label>
                    <input type="text" id="add_borrower" name="borrower_name" class="form-control" required placeholder="e.g. Faraz / Staff / Amin Qarzi">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="add_loan_amount">Amount (PKR) <span class="required">*</span></label>
                        <input type="number" id="add_loan_amount" name="amount_disbursed" class="form-control" required step="100" placeholder="e.g. 10000">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_loan_date">Disbursement Date</label>
                        <input type="date" id="add_loan_date" name="disbursement_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="add_purpose">Purpose / Operational Context</label>
                    <textarea id="add_purpose" name="purpose" class="form-control" placeholder="Context of advance..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-glass" onclick="closeModal('addLoanModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Advance</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Repay Loan -->
<div class="modal-overlay" id="repayLoanModal">
    <div class="modal-dialog" style="max-width: 420px;">
        <div class="modal-header">
            <h3 class="modal-title">Record Loan Repayment</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('repayLoanModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="repay">
            <input type="hidden" name="id" id="repay_loan_id">
            <div class="modal-body">
                <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px;">
                    Recording repayment for <strong id="repay_borrower_name" style="color: var(--text-primary);"></strong>. Outstanding balance: <strong id="repay_balance_display" style="color: var(--accent-amber);"></strong>.
                </p>
                <div class="form-group">
                    <label class="form-label" for="repay_amount_input">Repayment Amount (PKR) <span class="required">*</span></label>
                    <input type="number" id="repay_amount_input" name="amount_repaid" class="form-control" required step="100">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-glass" onclick="closeModal('repayLoanModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Record Repayment</button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden Delete Form with CSRF -->
<form id="delete-loan-form" method="POST" action="" style="display:none;">
    <?= getCsrfField() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_loan_id">
</form>

<script>
function openRepayModal(id, borrower, balance) {
    document.getElementById('repay_loan_id').value = id;
    document.getElementById('repay_borrower_name').textContent = borrower;
    document.getElementById('repay_balance_display').textContent = 'Rs. ' + balance.toLocaleString();
    document.getElementById('repay_amount_input').value = balance;
    document.getElementById('repay_amount_input').max = balance;
    openModal('repayLoanModal');
}

function confirmDeleteLoan(id, name) {
    confirmAction(
        'Delete Loan Record?',
        `Are you sure you want to delete the loan record for '${name}'?`,
        () => {
            document.getElementById('delete_loan_id').value = id;
            document.getElementById('delete-loan-form').submit();
        }
    );
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
