<?php
/**
 * Fee Transactions & Receipts Management Module
 * Islamia College of Technology College Management System
 * Human-Centered Design: Fee Payments & Outstanding Dues
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// RBAC: Restricted to Admin and Accountant roles
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
        $enrollmentId = (int)($_POST['enrollment_id'] ?? 0);
        $amountPaid   = (float)($_POST['amount_paid'] ?? 0);
        $paymentDate  = !empty($_POST['payment_date']) ? $_POST['payment_date'] : date('Y-m-d');
        $paymentMethod = strtolower(trim($_POST['payment_method'] ?? 'cash'));
        $receiptNo    = trim($_POST['receipt_no'] ?? '');
        $remarks      = trim($_POST['remarks'] ?? '');

        if ($enrollmentId <= 0 || $amountPaid <= 0) {
            $error = "Please select a valid student and enter a positive payment amount.";
        } else {
            if (empty($receiptNo)) {
                $receiptNo = 'REC-' . rand(10000, 99999);
            }
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO fee_transactions (enrollment_id, receipt_no, receipt_voucher_no, amount_paid, payment_date, payment_method, payment_mode, remarks)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$enrollmentId, $receiptNo, $receiptNo, $amountPaid, $paymentDate, $paymentMethod, $paymentMethod, $remarks]);
                $newId = (int)$pdo->lastInsertId();
                $message = "Payment of Rs. " . number_format($amountPaid, 0) . " recorded successfully (Receipt No: {$receiptNo}). <a href='receipt.php?id={$newId}' target='_blank' style='color:#38bdf8; text-decoration:underline; font-weight:bold; margin-left:8px;'>Print Receipt Voucher →</a>";
            } catch (PDOException $e) {
                error_log("Create fee error: " . $e->getMessage());
                $error = ($e->getCode() == 23000) ? "Receipt number already exists. Please choose another." : "An error occurred while recording the fee transaction.";
            }
        }
    } elseif ($action === 'delete') {
        if ($userRole !== 'admin') {
            $error = "Only system administrators can delete financial records.";
        } else {
            $id = (int)($_POST['id'] ?? 0);
            try {
                $stmt = $pdo->prepare("DELETE FROM fee_transactions WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Fee transaction deleted successfully.";
            } catch (PDOException $e) {
                error_log("Delete fee error: " . $e->getMessage());
                $error = "An error occurred while deleting the fee transaction.";
            }
        }
    }
}

// Current View: 'all' (Fee Payments) or 'outstanding' (Outstanding Fees)
$currentView = ($_GET['view'] ?? '') === 'outstanding' ? 'outstanding' : 'all';

// Search & Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');
$methodFilter = trim($_GET['method'] ?? '');

// Real Financial Totals
$totalCollected = (float)$pdo->query("SELECT COALESCE(SUM(amount_paid), 0) FROM fee_transactions")->fetchColumn();
$totalAgreed = (float)$pdo->query("SELECT COALESCE(SUM(total_agreed_fee), 0) FROM enrollments")->fetchColumn();
$totalOutstanding = max(0.0, $totalAgreed - $totalCollected);

if ($currentView === 'outstanding') {
    // Query Outstanding Fees
    $where = ["(e.total_agreed_fee - COALESCE((SELECT SUM(amount_paid) FROM fee_transactions WHERE enrollment_id = e.id), 0)) > 0"];
    $params = [];

    if (!empty($search)) {
        $where[] = "(s.full_name LIKE ? OR s.student_reg_no LIKE ? OR c.course_name LIKE ? OR b.batch_code LIKE ?)";
        $term = "%{$search}%";
        $params = array_merge($params, [$term, $term, $term, $term]);
    }

    $whereClause = "WHERE " . implode(" AND ", $where);

    $countStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM enrollments e
        JOIN students s ON e.student_id = s.id
        JOIN batches b ON e.batch_id = b.id
        JOIN courses c ON b.course_id = c.id
        {$whereClause}
    ");
    $countStmt->execute($params);
    $totalRecords = (int)$countStmt->fetchColumn();
    $totalPages = max(1, ceil($totalRecords / $limit));

    $sql = "
        SELECT e.id as enrollment_id, s.id as student_id, s.full_name, s.father_name, s.student_reg_no, s.contact_number,
               b.batch_code, c.course_name, e.total_agreed_fee,
               COALESCE((SELECT SUM(amount_paid) FROM fee_transactions WHERE enrollment_id = e.id), 0) as paid_amount,
               (e.total_agreed_fee - COALESCE((SELECT SUM(amount_paid) FROM fee_transactions WHERE enrollment_id = e.id), 0)) as balance_due
        FROM enrollments e
        JOIN students s ON e.student_id = s.id
        JOIN batches b ON e.batch_id = b.id
        JOIN courses c ON b.course_id = c.id
        {$whereClause}
        ORDER BY balance_due DESC
        LIMIT " . (int)$limit . " OFFSET " . (int)$offset . "
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $outstandingList = $stmt->fetchAll();
} else {
    // Query Fee Payments
    $where = [];
    $params = [];

    if (!empty($search)) {
        $where[] = "(s.full_name LIKE ? OR s.student_reg_no LIKE ? OR f.receipt_no LIKE ? OR f.receipt_voucher_no LIKE ? OR c.course_name LIKE ?)";
        $term = "%{$search}%";
        $params = array_merge($params, [$term, $term, $term, $term, $term]);
    }

    if (!empty($methodFilter)) {
        $where[] = "(f.payment_method = ? OR f.payment_mode = ?)";
        $params[] = $methodFilter;
        $params[] = $methodFilter;
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    $countStmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM fee_transactions f 
        JOIN enrollments e ON f.enrollment_id = e.id 
        JOIN students s ON e.student_id = s.id 
        JOIN batches b ON e.batch_id = b.id 
        JOIN courses c ON b.course_id = c.id 
        {$whereClause}
    ");
    $countStmt->execute($params);
    $totalRecords = (int)$countStmt->fetchColumn();
    $totalPages = max(1, ceil($totalRecords / $limit));

    $sql = "
        SELECT f.*, s.id as student_id, s.full_name, s.student_reg_no, b.batch_code, c.course_name
        FROM fee_transactions f
        JOIN enrollments e ON f.enrollment_id = e.id
        JOIN students s ON e.student_id = s.id
        JOIN batches b ON e.batch_id = b.id
        JOIN courses c ON b.course_id = c.id
        {$whereClause}
        ORDER BY f.payment_date DESC, f.id DESC
        LIMIT " . (int)$limit . " OFFSET " . (int)$offset . "
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
}

// Student Enrollments with balance info for modal dropdown
$activeEnrollments = $pdo->query("
    SELECT e.id, s.student_reg_no, s.full_name, c.course_name, b.batch_code, e.total_agreed_fee,
           (e.total_agreed_fee - COALESCE((SELECT SUM(amount_paid) FROM fee_transactions WHERE enrollment_id = e.id), 0)) as balance_due
    FROM enrollments e
    JOIN students s ON e.student_id = s.id
    JOIN batches b ON e.batch_id = b.id
    JOIN courses c ON b.course_id = c.id
    ORDER BY s.full_name ASC
    LIMIT 600
")->fetchAll();

$pageTitle = $currentView === 'outstanding' ? "Outstanding Student Fees" : "Fee Transactions";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= $currentView === 'outstanding' ? 'Outstanding Student Fees' : 'Fee Transactions' ?></h1>
        <p class="page-breadcrumb">Manage student payments, fee vouchers, and balance recovery without manual calculations</p>
    </div>
    <div>
        <button type="button" class="btn btn-primary" onclick="openModal('addFeeModal')">
            <span>💰 Receive / Submit Payment</span>
        </button>
    </div>
</div>

<?php if ($message): ?>
    <div style="padding:14px 18px; background:rgba(16,185,129,0.12); border:1px solid rgba(16,185,129,0.3); color:#6ee7b7; border-radius:10px; margin-bottom:20px;">
        ✓ <?= $message ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div style="padding:14px 18px; background:rgba(244,63,94,0.12); border:1px solid rgba(244,63,94,0.3); color:#fda4af; border-radius:10px; margin-bottom:20px;">
        ⚠️ <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<!-- Financial Summary Strip -->
<div class="metric-grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); margin-bottom: 24px;">
    <div class="metric-card emerald">
        <div class="metric-header">
            <span class="metric-title">Total Fee Collected</span>
            <span class="metric-icon">💰</span>
        </div>
        <div class="metric-value">Rs. <?= number_format($totalCollected, 0) ?></div>
        <div class="metric-sub">Verified Payment Transactions</div>
    </div>

    <div class="metric-card amber">
        <div class="metric-header">
            <span class="metric-title">Outstanding Dues</span>
            <span class="metric-icon">⏳</span>
        </div>
        <div class="metric-value">Rs. <?= number_format($totalOutstanding, 0) ?></div>
        <div class="metric-sub">Pending Student Balances</div>
    </div>
</div>

<!-- View Switcher Tabs -->
<div style="display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 12px;">
    <a href="?view=all" class="btn <?= $currentView === 'all' ? 'btn-primary' : 'btn-glass' ?>" style="padding: 8px 18px; font-size: 14px;">
        💳 Fee Payments
    </a>
    <a href="?view=outstanding" class="btn <?= $currentView === 'outstanding' ? 'btn-primary' : 'btn-glass' ?>" style="padding: 8px 18px; font-size: 14px;">
        ⏳ Outstanding Fees
    </a>
</div>

<!-- Filters -->
<div class="glass-card" style="padding: 16px 20px; margin-bottom: 24px;">
    <form method="GET" action="" class="filter-bar" style="margin-bottom: 0;">
        <input type="hidden" name="view" value="<?= htmlspecialchars($currentView) ?>">
        <div class="filter-group" style="flex: 1; max-width: 380px;">
            <input type="text" name="search" class="form-control" placeholder="<?= $currentView === 'outstanding' ? 'Search by student name, reg no, course...' : 'Search by student, receipt no, course...' ?>" value="<?= htmlspecialchars($search) ?>" style="min-height: 40px;">
        </div>
        <?php if ($currentView === 'all'): ?>
        <div class="filter-group">
            <select name="method" class="form-control" style="min-height: 40px; min-width: 170px;">
                <option value="">All Payment Modes</option>
                <option value="cash" <?= $methodFilter === 'cash' ? 'selected' : '' ?>>Cash</option>
                <option value="bank" <?= $methodFilter === 'bank' ? 'selected' : '' ?>>Bank Transfer</option>
                <option value="slip" <?= $methodFilter === 'slip' ? 'selected' : '' ?>>Bank Deposit Slip</option>
                <option value="online" <?= $methodFilter === 'online' ? 'selected' : '' ?>>Online / EasyPaisa / JazzCash</option>
            </select>
        </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-glass" style="min-height: 40px;">Search</button>
        <?php if (!empty($search) || !empty($methodFilter)): ?>
            <a href="?view=<?= $currentView ?>" class="btn btn-glass" style="min-height: 40px;">Reset</a>
        <?php endif; ?>
    </form>
</div>

<?php if ($currentView === 'outstanding'): ?>
<!-- OUTSTANDING FEES TABLE -->
<div class="glass-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Registration No.</th>
                    <th>Student Name</th>
                    <th>Father Name</th>
                    <th>Course & Batch</th>
                    <th>Total Agreed Fee</th>
                    <th>Total Paid</th>
                    <th>Balance Due</th>
                    <th style="text-align:right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($outstandingList)): ?>
                    <tr>
                        <td colspan="8" class="empty-state">
                            <div class="empty-state-icon">🎉</div>
                            <div class="empty-state-title">No outstanding fees</div>
                            <div class="empty-state-desc">All student fee dues have been fully settled.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($outstandingList as $row): ?>
                        <tr>
                            <td><strong style="color: var(--primary-light); font-family: monospace;"><?= htmlspecialchars($row['student_reg_no']) ?></strong></td>
                            <td>
                                <a href="../students/view.php?id=<?= $row['student_id'] ?>" style="color: var(--text-primary); text-decoration: none; font-weight: 600;">
                                    <?= htmlspecialchars($row['full_name']) ?>
                                </a>
                            </td>
                            <td style="color: var(--text-muted);"><?= htmlspecialchars($row['father_name'] ?: '—') ?></td>
                            <td><?= htmlspecialchars($row['course_name']) ?> <span style="font-size: 11px; color: var(--text-dim);">(<?= htmlspecialchars($row['batch_code']) ?>)</span></td>
                            <td>Rs. <?= number_format((float)$row['total_agreed_fee'], 0) ?></td>
                            <td style="color: var(--accent-emerald);">Rs. <?= number_format((float)$row['paid_amount'], 0) ?></td>
                            <td><strong style="color: #f87171; font-size: 15px;">Rs. <?= number_format((float)$row['balance_due'], 0) ?></strong></td>
                            <td style="text-align:right;">
                                <button type="button" class="btn btn-primary btn-sm" onclick="selectAndRecordFee(<?= $row['enrollment_id'] ?>, <?= $row['balance_due'] ?>)">
                                    💰 Receive / Submit Payment
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="pagination-wrapper">
            <div>
                Showing <strong><?= min($totalRecords, $offset + 1) ?>–<?= min($totalRecords, $offset + count($outstandingList)) ?></strong> of <strong><?= number_format($totalRecords) ?></strong> students with pending dues
            </div>
            <div class="pagination-links">
                <?php if ($page > 1): ?>
                    <a href="?view=outstanding&page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>" class="page-btn">←</a>
                <?php endif; ?>

                <?php 
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                for ($p = $startPage; $p <= $endPage; $p++): 
                ?>
                    <a href="?view=outstanding&page=<?= $p ?>&search=<?= urlencode($search) ?>" class="page-btn <?= $p === $page ? 'active' : '' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?view=outstanding&page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>" class="page-btn">→</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- FEE PAYMENTS LEDGER TABLE -->
<div class="glass-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Receipt No.</th>
                    <th>Payment Date</th>
                    <th>Student Name</th>
                    <th>Registration No.</th>
                    <th>Course & Batch</th>
                    <th>Amount Paid</th>
                    <th>Payment Mode</th>
                    <th>Remarks</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="9" class="empty-state">
                            <div class="empty-state-icon">💳</div>
                            <div class="empty-state-title">No fee payments found</div>
                            <div class="empty-state-desc">Try adjusting your search or filter criteria.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transactions as $t): ?>
                        <tr>
                            <td><strong style="color: var(--primary-light); font-family: monospace;"><?= htmlspecialchars($t['receipt_no']) ?></strong></td>
                            <td style="color: var(--text-muted); font-size: 13px;"><?= $t['payment_date'] ? date('M d, Y', strtotime($t['payment_date'])) : '—' ?></td>
                            <td>
                                <a href="../students/view.php?id=<?= $t['student_id'] ?>" style="color: var(--text-primary); text-decoration: none; font-weight: 600;">
                                    <?= htmlspecialchars($t['full_name']) ?>
                                </a>
                            </td>
                            <td><span style="font-family: monospace; color: var(--text-muted);"><?= htmlspecialchars($t['student_reg_no']) ?></span></td>
                            <td><?= htmlspecialchars($t['course_name']) ?> <span style="font-size: 11px; color: var(--text-dim);">(<?= htmlspecialchars($t['batch_code']) ?>)</span></td>
                            <td><strong style="color: var(--accent-emerald); font-size: 15px;">Rs. <?= number_format((float)$t['amount_paid'], 0) ?></strong></td>
                            <td><span style="text-transform: capitalize; padding: 3px 8px; border-radius: 4px; background: rgba(255,255,255,0.06); font-size: 12px;"><?= htmlspecialchars($t['payment_method']) ?></span></td>
                            <td style="color: var(--text-muted); max-width: 200px;"><?= htmlspecialchars($t['remarks'] ?: '—') ?></td>
                            <td style="text-align:right;">
                                <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                    <a href="receipt.php?id=<?= $t['id'] ?>" target="_blank" class="btn btn-glass btn-sm" title="Print Fee Receipt Voucher">Receipt</a>
                                    <?php if ($userRole === 'admin'): ?>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="confirmDeleteFee(<?= $t['id'] ?>)">Delete</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="pagination-wrapper">
            <div>
                Showing <strong><?= min($totalRecords, $offset + 1) ?>–<?= min($totalRecords, $offset + count($transactions)) ?></strong> of <strong><?= number_format($totalRecords) ?></strong> fee payments
            </div>
            <div class="pagination-links">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&method=<?= urlencode($methodFilter) ?>" class="page-btn">←</a>
                <?php endif; ?>

                <?php 
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                for ($p = $startPage; $p <= $endPage; $p++): 
                ?>
                    <a href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&method=<?= urlencode($methodFilter) ?>" class="page-btn <?= $p === $page ? 'active' : '' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&method=<?= urlencode($methodFilter) ?>" class="page-btn">→</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Modal: Record Fee Payment -->
<div class="modal-overlay" id="addFeeModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">💰 Payment Received / Submit Fee</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('addFeeModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="add_fee_enrollment">Student Enrollment <span class="required">*</span></label>
                    <select id="add_fee_enrollment" name="enrollment_id" class="form-control" required onchange="updateBalanceHint(this)">
                        <option value="">Choose enrolled student...</option>
                        <?php foreach ($activeEnrollments as $ae): ?>
                            <option value="<?= $ae['id'] ?>" data-balance="<?= (float)$ae['balance_due'] ?>">
                                <?= htmlspecialchars($ae['full_name']) ?> (<?= htmlspecialchars($ae['student_reg_no']) ?>) — <?= htmlspecialchars($ae['course_name']) ?> [Balance Due: Rs. <?= number_format($ae['balance_due'], 0) ?>]
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="balance_hint" style="font-size: 12px; color: #fbbf24; margin-top: 6px; display: none;"></div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="add_fee_amount">Amount Paid (PKR) <span class="required">*</span></label>
                        <input type="number" id="add_fee_amount" name="amount_paid" class="form-control" step="1" required placeholder="e.g. 5000">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_fee_date">Payment Date <span class="required">*</span></label>
                        <input type="date" id="add_fee_date" name="payment_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="add_fee_method">Payment Mode</label>
                        <select id="add_fee_method" name="payment_method" class="form-control">
                            <option value="cash">Cash</option>
                            <option value="bank">Bank Transfer</option>
                            <option value="slip">Bank Deposit Slip</option>
                            <option value="online">Online / EasyPaisa / JazzCash</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_fee_receipt_no">Receipt Voucher No.</label>
                        <input type="text" id="add_fee_receipt_no" name="receipt_no" class="form-control" placeholder="Leave blank to auto-generate">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="add_fee_remarks">Remarks / Installment Description</label>
                    <input type="text" id="add_fee_remarks" name="remarks" class="form-control" placeholder="e.g. 1st installment, admission fee clearance">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-glass" onclick="closeModal('addFeeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">✓ Confirm &amp; Submit Payment Received</button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden Delete Form with CSRF -->
<form id="delete-fee-form" method="POST" action="" style="display:none;">
    <?= getCsrfField() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_fee_id">
</form>

<script>
function selectAndRecordFee(enrollmentId, balanceDue) {
    const sel = document.getElementById('add_fee_enrollment');
    sel.value = enrollmentId;
    updateBalanceHint(sel);
    if (balanceDue > 0) {
        document.getElementById('add_fee_amount').value = balanceDue;
    }
    openModal('addFeeModal');
}

function updateBalanceHint(selectEl) {
    const opt = selectEl.options[selectEl.selectedIndex];
    const hint = document.getElementById('balance_hint');
    if (opt && opt.dataset.balance !== undefined) {
        const bal = parseFloat(opt.dataset.balance);
        hint.style.display = 'block';
        hint.innerHTML = 'Current Outstanding Balance: <strong>Rs. ' + bal.toLocaleString() + '</strong>';
    } else {
        hint.style.display = 'none';
    }
}

function confirmDeleteFee(id) {
    confirmAction(
        'Delete Fee Payment?',
        'Are you sure you want to permanently delete this payment transaction? This will adjust the student balance.',
        () => {
            document.getElementById('delete_fee_id').value = id;
            document.getElementById('delete-fee-form').submit();
        }
    );
}

document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('new') === '1') {
        openModal('addFeeModal');
    } else if (urlParams.get('enrollment_id')) {
        const enrId = urlParams.get('enrollment_id');
        selectAndRecordFee(enrId, 0);
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
