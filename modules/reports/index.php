<?php
/**
 * Unified Institutional Reports Hub
 * Islamia College of Technology College Management System
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole(['admin', 'accountant', 'registrar', 'staff']);

$pdo = Database::getConnection();
$currentUser = getCurrentUser();
$userRole = strtolower($currentUser['role'] ?? 'staff');

$reportType = $_GET['type'] ?? 'fees';
$search     = trim($_GET['search'] ?? '');
$export     = $_GET['export'] ?? '';

// CSV Export Handler (Prior to any HTML emission)
if ($export === 'csv') {
    $filename = 'ICT_Report_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $reportType) . '_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');

    if ($reportType === 'students') {
        fputcsv($output, ['Reg No', 'Full Name', 'Father Name', 'Contact', 'Email', 'Address', 'Total Enrollments']);
        $stmt = $pdo->query("
            SELECT s.student_reg_no, s.full_name, s.father_name, s.contact_number, s.email, s.address,
                   (SELECT COUNT(*) FROM enrollments e WHERE e.student_id = s.id) as enrollments
            FROM students s ORDER BY s.id DESC
        ");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            fputcsv($output, $row);
        }
    } elseif ($reportType === 'fees') {
        fputcsv($output, ['Receipt No', 'Payment Date', 'Student Reg', 'Full Name', 'Course', 'Batch', 'Amount Paid (PKR)', 'Mode', 'Remarks']);
        $stmt = $pdo->query("
            SELECT f.receipt_no, f.payment_date, s.student_reg_no, s.full_name, c.course_name, b.batch_code, f.amount_paid, f.payment_method, f.remarks
            FROM fee_transactions f
            JOIN enrollments e ON f.enrollment_id = e.id
            JOIN students s ON e.student_id = s.id
            JOIN batches b ON e.batch_id = b.id
            JOIN courses c ON b.course_id = c.id
            ORDER BY f.payment_date DESC, f.id DESC
        ");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            fputcsv($output, $row);
        }
    } elseif ($reportType === 'dues') {
        fputcsv($output, ['Student Reg', 'Full Name', 'Father Name', 'Phone', 'Course', 'Batch', 'Agreed Fee', 'Paid To Date', 'Outstanding Due (PKR)']);
        $stmt = $pdo->query("
            SELECT s.student_reg_no, s.full_name, s.father_name, s.contact_number, c.course_name, b.batch_code,
                   e.total_agreed_fee,
                   COALESCE((SELECT SUM(amount_paid) FROM fee_transactions WHERE enrollment_id = e.id), 0) as paid,
                   GREATEST(0, e.total_agreed_fee - COALESCE((SELECT SUM(amount_paid) FROM fee_transactions WHERE enrollment_id = e.id), 0)) as balance
            FROM enrollments e
            JOIN students s ON e.student_id = s.id
            JOIN batches b ON e.batch_id = b.id
            JOIN courses c ON b.course_id = c.id
            HAVING balance > 0
            ORDER BY balance DESC
        ");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            fputcsv($output, $row);
        }
    } elseif ($reportType === 'board') {
        fputcsv($output, ['Roll No', 'Registration No', 'Candidate Name', 'Father Name', 'Trade / Technology', 'Marks Obtained', 'Total Marks', 'Status']);
        $stmt = $pdo->query("
            SELECT b.board_roll_no, b.board_registration_no, s.full_name, s.father_name, b.trade_course_name, b.marks_obtained, b.total_marks, b.exam_status
            FROM board_examinations b
            JOIN students s ON b.student_id = s.id
            ORDER BY b.id DESC
        ");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            fputcsv($output, $row);
        }
    } elseif ($reportType === 'expenses') {
        fputcsv($output, ['Month', 'Year', 'Category', 'Payee', 'Due (PKR)', 'Paid (PKR)', 'Balance (PKR)', 'Payment Date', 'Notes']);
        $stmt = $pdo->query("
            SELECT month_name, year_num, expense_type, payee_name, amount_due, amount_paid, balance_dues, payment_date, notes
            FROM campus_expenses
            ORDER BY year_num DESC, id DESC
        ");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            fputcsv($output, $row);
        }
    } elseif ($reportType === 'payroll') {
        fputcsv($output, ['Staff Code', 'Full Name', 'Designation', 'Month', 'Year', 'Agreed Salary', 'P1', 'P2', 'P3', 'Total Paid', 'Dues']);
        $stmt = $pdo->query("
            SELECT s.staff_code, s.full_name, s.designation, p.month_name, p.year_num, p.salary_agreed, p.installment_p1, p.installment_p2, p.installment_p3, p.total_paid, p.dues
            FROM staff_payroll p
            JOIN staff_members s ON p.staff_id = s.id
            ORDER BY p.year_num DESC, p.id DESC
        ");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            fputcsv($output, $row);
        }
    } elseif ($reportType === 'loans') {
        fputcsv($output, ['Borrower Name', 'Date Disbursed', 'Disbursed (PKR)', 'Repaid (PKR)', 'Balance (PKR)', 'Purpose', 'Status']);
        $stmt = $pdo->query("
            SELECT borrower_name, disbursement_date, amount_disbursed, amount_repaid, balance_due, purpose, status
            FROM loans_and_advances
            ORDER BY id DESC
        ");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            fputcsv($output, $row);
        }
    }
    exit;
}

$pageTitle = "Institutional Reports Hub";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Institutional Reports Hub</h1>
        <p class="page-breadcrumb">Live analytical reports, student rosters, financial summaries, and KPK board gazettes</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="?export=csv&type=<?= urlencode($reportType) ?>" class="btn btn-glass">
            <span>📥 Export CSV</span>
        </a>
        <button type="button" class="btn btn-primary" onclick="window.print()">
            <span>🖨️ Print Report</span>
        </button>
    </div>
</div>

<!-- Report Selection Ribbon -->
<div class="glass-card" style="padding: 16px 20px; margin-bottom: 24px;">
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <a href="?type=fees" class="btn btn-sm <?= $reportType === 'fees' ? 'btn-primary' : 'btn-glass' ?>">Fee Collections</a>
        <a href="?type=dues" class="btn btn-sm <?= $reportType === 'dues' ? 'btn-primary' : 'btn-glass' ?>">Outstanding Fees</a>
        <a href="?type=students" class="btn btn-sm <?= $reportType === 'students' ? 'btn-primary' : 'btn-glass' ?>">Students Directory</a>
        <a href="?type=board" class="btn btn-sm <?= $reportType === 'board' ? 'btn-primary' : 'btn-glass' ?>">Board Gazette (TTB)</a>
        <?php if (in_array($userRole, ['admin', 'accountant'])): ?>
            <a href="?type=expenses" class="btn btn-sm <?= $reportType === 'expenses' ? 'btn-primary' : 'btn-glass' ?>">Campus Expenses</a>
            <a href="?type=loans" class="btn btn-sm <?= $reportType === 'loans' ? 'btn-primary' : 'btn-glass' ?>">Loans & Advances</a>
        <?php endif; ?>
        <?php if ($userRole === 'admin'): ?>
            <a href="?type=payroll" class="btn btn-sm <?= $reportType === 'payroll' ? 'btn-primary' : 'btn-glass' ?>">Staff Payroll</a>
        <?php endif; ?>
    </div>
</div>

<!-- Report Content Body -->
<div class="glass-card">
    <?php if ($reportType === 'fees'): 
        $feeData = $pdo->query("
            SELECT f.*, s.student_reg_no, s.full_name, c.course_name, b.batch_code
            FROM fee_transactions f
            JOIN enrollments e ON f.enrollment_id = e.id
            JOIN students s ON e.student_id = s.id
            JOIN batches b ON e.batch_id = b.id
            JOIN courses c ON b.course_id = c.id
            ORDER BY f.payment_date DESC, f.id DESC
            LIMIT 100
        ")->fetchAll();
        $sumFee = array_sum(array_column($feeData, 'amount_paid'));
    ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
            <h3 class="card-title">Fee Collection Statement (Latest 100 Entries)</h3>
            <span style="font-size: 14px; color: var(--accent-emerald); font-weight: 700;">Subtotal: Rs. <?= number_format($sumFee, 0) ?></span>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Receipt No</th>
                        <th>Date</th>
                        <th>Student Name</th>
                        <th>Reg No</th>
                        <th>Program / Batch</th>
                        <th>Amount Paid</th>
                        <th>Mode</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($feeData as $r): ?>
                        <tr>
                            <td><strong style="color: var(--primary-light); font-family: monospace;"><?= htmlspecialchars($r['receipt_no']) ?></strong></td>
                            <td style="color: var(--text-muted); font-size: 13px;"><?= date('M d, Y', strtotime($r['payment_date'])) ?></td>
                            <td><strong><?= htmlspecialchars($r['full_name']) ?></strong></td>
                            <td><?= htmlspecialchars($r['student_reg_no']) ?></td>
                            <td><?= htmlspecialchars($r['course_name']) ?> (<?= htmlspecialchars($r['batch_code']) ?>)</td>
                            <td><strong style="color: var(--accent-emerald);">Rs. <?= number_format((float)$r['amount_paid'], 0) ?></strong></td>
                            <td><span style="text-transform: capitalize;"><?= htmlspecialchars($r['payment_method']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($reportType === 'dues'): 
        $dueData = $pdo->query("
            SELECT s.student_reg_no, s.full_name, s.father_name, s.contact_number, c.course_name, b.batch_code,
                   e.total_agreed_fee,
                   COALESCE((SELECT SUM(amount_paid) FROM fee_transactions WHERE enrollment_id = e.id), 0) as paid,
                   GREATEST(0, e.total_agreed_fee - COALESCE((SELECT SUM(amount_paid) FROM fee_transactions WHERE enrollment_id = e.id), 0)) as balance
            FROM enrollments e
            JOIN students s ON e.student_id = s.id
            JOIN batches b ON e.batch_id = b.id
            JOIN courses c ON b.course_id = c.id
            HAVING balance > 0
            ORDER BY balance DESC
            LIMIT 100
        ")->fetchAll();
        $sumDues = array_sum(array_column($dueData, 'balance'));
    ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
            <h3 class="card-title">Outstanding Student Fee Balances</h3>
            <span style="font-size: 14px; color: var(--accent-amber); font-weight: 700;">Total Pending: Rs. <?= number_format($sumDues, 0) ?></span>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Registration No</th>
                        <th>Student Name</th>
                        <th>Father Name</th>
                        <th>Contact</th>
                        <th>Program / Batch</th>
                        <th>Agreed Fee</th>
                        <th>Amount Paid</th>
                        <th>Pending Due</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dueData as $d): ?>
                        <tr>
                            <td><strong style="color: var(--primary-light); font-family: monospace;"><?= htmlspecialchars($d['student_reg_no']) ?></strong></td>
                            <td><strong><?= htmlspecialchars($d['full_name']) ?></strong></td>
                            <td style="color: var(--text-muted);"><?= htmlspecialchars($d['father_name'] ?: '—') ?></td>
                            <td><?= htmlspecialchars($d['contact_number'] ?: '—') ?></td>
                            <td><?= htmlspecialchars($d['course_name']) ?> (<?= htmlspecialchars($d['batch_code']) ?>)</td>
                            <td>Rs. <?= number_format((float)$d['total_agreed_fee'], 0) ?></td>
                            <td style="color: var(--accent-emerald);">Rs. <?= number_format((float)$d['paid'], 0) ?></td>
                            <td><strong style="color: var(--accent-rose);">Rs. <?= number_format((float)$d['balance'], 0) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($reportType === 'students'): 
        $stData = $pdo->query("
            SELECT s.*, (SELECT COUNT(*) FROM enrollments e WHERE e.student_id = s.id) as total_enrollments
            FROM students s 
            ORDER BY s.id DESC 
            LIMIT 100
        ")->fetchAll();
    ?>
        <h3 class="card-title" style="margin-bottom: 18px;">Registered Students Roster (Latest 100 Records)</h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Reg No</th>
                        <th>Student Full Name</th>
                        <th>Father Name</th>
                        <th>Contact</th>
                        <th>CNIC / Form-B</th>
                        <th>Address</th>
                        <th>Enrollments</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stData as $s): ?>
                        <tr>
                            <td><strong style="color: var(--primary-light); font-family: monospace;"><?= htmlspecialchars($s['student_reg_no']) ?></strong></td>
                            <td><strong><?= htmlspecialchars($s['full_name']) ?></strong></td>
                            <td style="color: var(--text-muted);"><?= htmlspecialchars($s['father_name'] ?: '—') ?></td>
                            <td><?= htmlspecialchars($s['contact_number'] ?: '—') ?></td>
                            <td><?= htmlspecialchars($s['cnic'] ?: '—') ?></td>
                            <td style="color: var(--text-muted); font-size: 13px;"><?= htmlspecialchars($s['address'] ?: '—') ?></td>
                            <td>
                                <span class="status-badge badge-active"><?= (int)$s['total_enrollments'] ?> Enrolled</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($reportType === 'board'): 
        $boardData = $pdo->query("
            SELECT b.*, s.full_name, s.father_name
            FROM board_examinations b
            JOIN students s ON b.student_id = s.id
            ORDER BY b.id DESC
            LIMIT 100
        ")->fetchAll();
    ?>
        <h3 class="card-title" style="margin-bottom: 18px;">KPK Trade Testing Board Examination Gazette</h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Board Roll No</th>
                        <th>Registration No</th>
                        <th>Candidate Name</th>
                        <th>Trade / Course</th>
                        <th>Marks / Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($boardData as $bd): ?>
                        <tr>
                            <td><strong style="color: var(--primary-light); font-family: monospace;"><?= htmlspecialchars($bd['board_roll_no'] ?: 'Pending') ?></strong></td>
                            <td style="font-family: monospace; color: var(--text-muted);"><?= htmlspecialchars($bd['board_registration_no'] ?: '—') ?></td>
                            <td><strong><?= htmlspecialchars($bd['full_name']) ?></strong></td>
                            <td><?= htmlspecialchars($bd['trade_course_name']) ?></td>
                            <td><?= $bd['marks_obtained'] !== null ? "{$bd['marks_obtained']} / " . (int)$bd['total_marks'] : '—' ?></td>
                            <td>
                                <span class="status-badge <?= $bd['exam_status'] === 'pass' ? 'badge-active' : 'badge-inactive' ?>">
                                    <?= ucfirst(htmlspecialchars($bd['exam_status'])) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($reportType === 'expenses'): 
        $expData = $pdo->query("SELECT * FROM campus_expenses ORDER BY year_num DESC, id DESC")->fetchAll();
        $sumExp = array_sum(array_column($expData, 'amount_paid'));
    ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
            <h3 class="card-title">Campus Operational Expenses Report</h3>
            <span style="font-size: 14px; color: var(--accent-emerald); font-weight: 700;">Total Disbursed: Rs. <?= number_format($sumExp, 0) ?></span>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Category</th>
                        <th>Payee Name</th>
                        <th>Billed Amount</th>
                        <th>Amount Paid</th>
                        <th>Balance Due</th>
                        <th>Disbursement Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expData as $x): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($x['month_name']) ?> <?= (int)$x['year_num'] ?></strong></td>
                            <td><?= htmlspecialchars($x['expense_type']) ?></td>
                            <td><?= htmlspecialchars($x['payee_name']) ?></td>
                            <td>Rs. <?= number_format((float)$x['amount_due'], 0) ?></td>
                            <td style="color: var(--accent-emerald);">Rs. <?= number_format((float)$x['amount_paid'], 0) ?></td>
                            <td>Rs. <?= number_format((float)$x['balance_dues'], 0) ?></td>
                            <td><?= $x['payment_date'] ? date('M d, Y', strtotime($x['payment_date'])) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($reportType === 'payroll'): 
        $payData = $pdo->query("
            SELECT p.*, s.full_name, s.staff_code, s.designation
            FROM staff_payroll p
            JOIN staff_members s ON p.staff_id = s.id
            ORDER BY p.year_num DESC, p.id DESC
        ")->fetchAll();
        $sumPay = array_sum(array_column($payData, 'total_paid'));
    ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
            <h3 class="card-title">Staff Monthly Payroll Statement</h3>
            <span style="font-size: 14px; color: var(--accent-emerald); font-weight: 700;">Total Paid: Rs. <?= number_format($sumPay, 0) ?></span>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Staff Code</th>
                        <th>Full Name</th>
                        <th>Designation</th>
                        <th>Period</th>
                        <th>Agreed Salary</th>
                        <th>Disbursed Total</th>
                        <th>Outstanding Dues</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payData as $pd): ?>
                        <tr>
                            <td><strong style="color: var(--primary-light); font-family: monospace;"><?= htmlspecialchars($pd['staff_code']) ?></strong></td>
                            <td><strong><?= htmlspecialchars($pd['full_name']) ?></strong></td>
                            <td><?= htmlspecialchars($pd['designation']) ?></td>
                            <td><?= htmlspecialchars($pd['month_name']) ?> <?= (int)$pd['year_num'] ?></td>
                            <td>Rs. <?= number_format((float)$pd['salary_agreed'], 0) ?></td>
                            <td><strong style="color: var(--accent-emerald);">Rs. <?= number_format((float)$pd['total_paid'], 0) ?></strong></td>
                            <td>Rs. <?= number_format((float)$pd['dues'], 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($reportType === 'loans'): 
        $loanData = $pdo->query("SELECT * FROM loans_and_advances ORDER BY id DESC")->fetchAll();
    ?>
        <h3 class="card-title" style="margin-bottom: 18px;">Institutional Loans & Advances Statement</h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Borrower Name</th>
                        <th>Disbursed Date</th>
                        <th>Disbursed Amount</th>
                        <th>Amount Repaid</th>
                        <th>Balance Due</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($loanData as $ld): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($ld['borrower_name']) ?></strong></td>
                            <td><?= $ld['disbursement_date'] ? date('M d, Y', strtotime($ld['disbursement_date'])) : '—' ?></td>
                            <td>Rs. <?= number_format((float)$ld['amount_disbursed'], 0) ?></td>
                            <td style="color: var(--accent-emerald);">Rs. <?= number_format((float)$ld['amount_repaid'], 0) ?></td>
                            <td><strong style="color: var(--accent-amber);">Rs. <?= number_format((float)$ld['balance_due'], 0) ?></strong></td>
                            <td>
                                <span class="status-badge <?= $ld['status'] === 'settled' ? 'badge-active' : 'badge-pending' ?>">
                                    <?= ucfirst(str_replace('_', ' ', htmlspecialchars($ld['status']))) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
