<?php
/**
 * Student 360° Comprehensive Profile
 * Islamia College of Technology College Management System
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole(['admin', 'registrar', 'staff']);

$pdo = Database::getConnection();

$studentId = (int)($_GET['id'] ?? 0);
if ($studentId <= 0) {
    header("Location: index.php");
    exit;
}

// Fetch Student Data
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if (!$student) {
    $pageTitle = "Student Not Found";
    require_once __DIR__ . '/../../includes/header.php';
    echo "<div class='glass-card' style='text-align:center; padding:48px 24px; max-width:500px; margin:40px auto;'>";
    echo "<div style='font-size:48px; margin-bottom:12px;'>🎓</div>";
    echo "<h2 style='color:#f43f5e; margin-bottom:8px;'>Student Not Found</h2>";
    echo "<p style='color:var(--text-muted); margin-bottom:20px;'>The requested student record does not exist or has been removed.</p>";
    echo "<a href='index.php' class='btn btn-primary'>← Return to Student Directory</a>";
    echo "</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$pageTitle = $student['full_name'] . " (" . $student['student_reg_no'] . ")";
require_once __DIR__ . '/../../includes/header.php';

// 1. Enrollments
$enrollStmt = $pdo->prepare("
    SELECT e.*, b.batch_code, b.batch_name, c.course_name, c.course_code, c.category, c.duration
    FROM enrollments e
    JOIN batches b ON e.batch_id = b.id
    JOIN courses c ON b.course_id = c.id
    WHERE e.student_id = ?
    ORDER BY e.id DESC
");
$enrollStmt->execute([$studentId]);
$enrollments = $enrollStmt->fetchAll();

// 2. Fee Transactions
$feeStmt = $pdo->prepare("
    SELECT f.*, b.batch_code, c.course_name
    FROM fee_transactions f
    JOIN enrollments e ON f.enrollment_id = e.id
    JOIN batches b ON e.batch_id = b.id
    JOIN courses c ON b.course_id = c.id
    WHERE e.student_id = ?
    ORDER BY f.payment_date DESC, f.id DESC
");
$feeStmt->execute([$studentId]);
$feeTransactions = $feeStmt->fetchAll();

// 3. Board Examinations (KPK TTB)
$boardStmt = $pdo->prepare("
    SELECT * FROM board_examinations
    WHERE student_id = ?
    ORDER BY id DESC
");
$boardStmt->execute([$studentId]);
$boardExams = $boardStmt->fetchAll();

// 4. Academic Achievements & Positions
$posStmt = $pdo->prepare("
    SELECT * FROM academic_positions
    WHERE student_id = ?
    ORDER BY id DESC
");
$posStmt->execute([$studentId]);
$positions = $posStmt->fetchAll();

// Financial Calculations
$totalAgreed = (float)array_sum(array_column($enrollments, 'total_agreed_fee'));
$totalPaid = (float)array_sum(array_column($feeTransactions, 'amount_paid'));
$totalDues = max(0.0, $totalAgreed - $totalPaid);
?>

<div class="page-header no-print">
    <div>
        <div class="page-breadcrumb">
            <a href="index.php" style="color: var(--primary-light); text-decoration: none;">Students</a> • 
            <span><?= htmlspecialchars($student['student_reg_no']) ?></span>
        </div>
        <h1 class="page-title"><?= htmlspecialchars($student['full_name']) ?></h1>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="index.php" class="btn btn-glass">← Back to Directory</a>
        <button type="button" class="btn btn-primary" onclick="window.print()">🖨️ Print Profile</button>
    </div>
</div>

<?php
renderPrintHeader('Student Profile & Academic Record', 'Registration No: ' . $student['student_reg_no'] . ' • Student: ' . $student['full_name'], [
    'Registration #' => $student['student_reg_no'],
    'Full Name' => $student['full_name'],
    'Father Name' => $student['father_name'] ?: 'N/A',
    'Contact' => $student['contact_number'] ?: 'N/A',
    'CNIC' => $student['cnic'] ?: 'N/A',
    'Admitted' => date('d M Y', strtotime($student['created_at']))
]);
?>

<!-- Profile Banner Card -->
<div class="glass-card" style="margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
    <div style="display: flex; align-items: center; gap: 20px;">
        <div style="width: 68px; height: 68px; border-radius: var(--radius-lg); background: linear-gradient(135deg, var(--primary) 0%, var(--accent-indigo) 100%); display: flex; align-items: center; justify-content: center; font-size: 26px; font-weight: 700; color: #ffffff; box-shadow: 0 4px 20px rgba(2, 132, 199, 0.4);">
            <?= strtoupper(substr($student['full_name'], 0, 1)) ?>
        </div>
        <div>
            <h2 style="font-size: 20px; font-weight: 700; color: var(--text-primary); margin-bottom: 4px;"><?= htmlspecialchars($student['full_name']) ?></h2>
            <div style="font-size: 13px; color: var(--text-muted);">
                Father Name: <strong style="color: var(--text-primary);"><?= htmlspecialchars($student['father_name'] ?: '—') ?></strong> • 
                Phone: <strong style="color: var(--primary-light);"><?= htmlspecialchars($student['contact_number'] ?: '—') ?></strong>
            </div>
            <div style="font-size: 12px; color: var(--text-dim); margin-top: 2px;">
                Address: <?= htmlspecialchars($student['address'] ?: 'Not recorded') ?>
            </div>
        </div>
    </div>

    <!-- Financial Quick Summary -->
    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
        <div style="background: var(--bg-surface); border: 1px solid var(--border-card); border-radius: var(--radius-md); padding: 10px 16px; text-align: center;">
            <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Agreed Fee</div>
            <div style="font-size: 18px; font-weight: 700; color: var(--text-primary);">Rs. <?= number_format($totalAgreed, 0) ?></div>
        </div>
        <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: var(--radius-md); padding: 10px 16px; text-align: center;">
            <div style="font-size: 11px; color: var(--accent-emerald); text-transform: uppercase; font-weight: 600;">Total Paid</div>
            <div style="font-size: 18px; font-weight: 700; color: var(--accent-emerald);">Rs. <?= number_format($totalPaid, 0) ?></div>
        </div>
        <div style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.2); border-radius: var(--radius-md); padding: 10px 16px; text-align: center;">
            <div style="font-size: 11px; color: var(--accent-amber); text-transform: uppercase; font-weight: 600;">Remaining Balance</div>
            <div style="font-size: 18px; font-weight: 700; color: var(--accent-amber);">Rs. <?= number_format($totalDues, 0) ?></div>
        </div>
    </div>
</div>

<!-- Tabs Navigation -->
<div class="tab-wrapper">
    <div class="tab-nav">
        <button type="button" class="tab-btn active" data-tab="tab-overview">Overview</button>
        <button type="button" class="tab-btn" data-tab="tab-enrollment">Enrollment History (<?= count($enrollments) ?>)</button>
        <button type="button" class="tab-btn" data-tab="tab-fees">Fee Ledger (<?= count($feeTransactions) ?>)</button>
        <button type="button" class="tab-btn" data-tab="tab-exams">Board Examinations (<?= count($boardExams) ?>)</button>
        <button type="button" class="tab-btn" data-tab="tab-achievements">Academic Achievements (<?= count($positions) ?>)</button>
    </div>

    <!-- 1. Overview Tab -->
    <div class="tab-pane active" id="tab-overview">
        <div class="glass-card">
            <h3 class="card-title" style="margin-bottom: 18px; border-bottom: 1px solid var(--border-card); padding-bottom: 10px;">
                Student Demographics & Identity
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px;">
                <div>
                    <span class="metric-title">Registration Number</span>
                    <p style="font-size: 15px; font-weight: 600; color: var(--primary-light); font-family: monospace; margin-top: 4px;">
                        <?= htmlspecialchars($student['student_reg_no']) ?>
                    </p>
                </div>
                <div>
                    <span class="metric-title">Full Name</span>
                    <p style="font-size: 15px; font-weight: 600; color: var(--text-primary); margin-top: 4px;">
                        <?= htmlspecialchars($student['full_name']) ?>
                    </p>
                </div>
                <div>
                    <span class="metric-title">Father Name</span>
                    <p style="font-size: 15px; font-weight: 600; color: var(--text-primary); margin-top: 4px;">
                        <?= htmlspecialchars($student['father_name'] ?: '—') ?>
                    </p>
                </div>
                <div>
                    <span class="metric-title">CNIC / Form-B</span>
                    <p style="font-size: 15px; font-weight: 600; color: var(--text-primary); margin-top: 4px;">
                        <?= htmlspecialchars($student['cnic'] ?: '—') ?>
                    </p>
                </div>
                <div>
                    <span class="metric-title">Date of Birth</span>
                    <p style="font-size: 15px; font-weight: 600; color: var(--text-primary); margin-top: 4px;">
                        <?= !empty($student['date_of_birth']) ? date('M d, Y', strtotime($student['date_of_birth'])) : '—' ?>
                    </p>
                </div>
                <div>
                    <span class="metric-title">Contact Phone</span>
                    <p style="font-size: 15px; font-weight: 600; color: var(--text-primary); margin-top: 4px;">
                        <?= htmlspecialchars($student['contact_number'] ?: '—') ?>
                    </p>
                </div>
                <div>
                    <span class="metric-title">Email Address</span>
                    <p style="font-size: 15px; font-weight: 600; color: var(--text-primary); margin-top: 4px;">
                        <?= htmlspecialchars($student['email'] ?: '—') ?>
                    </p>
                </div>
                <div>
                    <span class="metric-title">Permanent Address</span>
                    <p style="font-size: 15px; font-weight: 600; color: var(--text-primary); margin-top: 4px;">
                        <?= htmlspecialchars($student['address'] ?: '—') ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Enrollment History Tab -->
    <div class="tab-pane" id="tab-enrollment">
        <div class="glass-card">
            <h3 class="card-title" style="margin-bottom: 18px;">Academic Enrollments</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Program / Course</th>
                            <th>Category</th>
                            <th>Academic Session</th>
                            <th>Class / Level</th>
                            <th>Batch Code</th>
                            <th>Duration</th>
                            <th>Enrollment Date</th>
                            <th>Agreed Fee</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($enrollments)): ?>
                            <tr><td colspan="9" class="empty-state">No enrollments recorded for this student.</td></tr>
                        <?php else: ?>
                            <?php foreach ($enrollments as $en): ?>
                                <tr>
                                    <td>
                                        <strong style="color: var(--primary-light);"><?= htmlspecialchars($en['course_name']) ?></strong>
                                        <?php if (!empty($en['roll_number'])): ?>
                                            <div style="font-size: 11.5px; color: var(--text-muted);">Roll No: <strong style="color: #f8fafc;"><?= htmlspecialchars($en['roll_number']) ?></strong></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><span style="font-size: 12px; color: var(--text-secondary);"><?= htmlspecialchars($en['category']) ?></span></td>
                                    <td><span style="font-size: 12px; color: #7dd3fc; font-weight: 500;"><?= htmlspecialchars($en['academic_session'] ?: 'Session 2026-2027') ?></span></td>
                                    <td><?= htmlspecialchars($en['class_level'] ?: ($en['tuition_class'] ?: '—')) ?></td>
                                    <td><span style="padding: 3px 8px; border-radius: 4px; background: rgba(255,255,255,0.06); font-size: 12px; font-family: monospace;"><?= htmlspecialchars($en['batch_code']) ?></span></td>
                                    <td><?= htmlspecialchars($en['duration'] ?: '—') ?></td>
                                    <td><?= $en['enrollment_date'] ? date('M d, Y', strtotime($en['enrollment_date'])) : '—' ?></td>
                                    <td>
                                        <strong>Rs. <?= number_format((float)$en['total_agreed_fee'], 0) ?></strong>
                                        <?php if ((float)$en['admission_fee'] > 0): ?>
                                            <div style="font-size: 10.5px; color: var(--text-dim);">Adm: Rs. <?= number_format((float)$en['admission_fee'], 0) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge badge-<?= htmlspecialchars(strtolower($en['status'])) ?>">
                                            <?= ucfirst(htmlspecialchars($en['status'])) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 3. Fee Ledger Tab -->
    <div class="tab-pane" id="tab-fees">
        <div class="glass-card">
            <h3 class="card-title" style="margin-bottom: 18px;">Institutional Fee Ledger</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Receipt No.</th>
                            <th>Payment Date</th>
                            <th>Program / Batch</th>
                            <th>Amount Paid</th>
                            <th>Payment Mode</th>
                            <th>Remarks</th>
                            <th>Voucher</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($feeTransactions)): ?>
                            <tr><td colspan="7" class="empty-state">No payment receipts on record.</td></tr>
                        <?php else: ?>
                            <?php foreach ($feeTransactions as $f): ?>
                                <tr>
                                    <td><strong style="color: var(--primary-light);"><?= htmlspecialchars($f['receipt_no']) ?></strong></td>
                                    <td><?= $f['payment_date'] ? date('M d, Y', strtotime($f['payment_date'])) : '—' ?></td>
                                    <td><?= htmlspecialchars($f['course_name']) ?> (<?= htmlspecialchars($f['batch_code']) ?>)</td>
                                    <td><strong style="color: var(--accent-emerald);">Rs. <?= number_format((float)$f['amount_paid'], 0) ?></strong></td>
                                    <td><span style="text-transform: capitalize;"><?= htmlspecialchars($f['payment_method']) ?></span></td>
                                    <td style="color: var(--text-muted);"><?= htmlspecialchars($f['remarks'] ?: '—') ?></td>
                                    <td>
                                        <a href="../fees/receipt.php?id=<?= $f['id'] ?>" target="_blank" class="btn btn-glass btn-sm">Receipt</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 4. Board Examinations Tab -->
    <div class="tab-pane" id="tab-exams">
        <div class="glass-card">
            <h3 class="card-title" style="margin-bottom: 18px;">KPK Trade Testing Board (TTB) Examinations</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Roll Number</th>
                            <th>Registration No</th>
                            <th>Session / Year</th>
                            <th>Marks Obtained</th>
                            <th>Total Marks</th>
                            <th>Percentage</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($boardExams)): ?>
                            <tr><td colspan="7" class="empty-state">No board examination records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($boardExams as $bx): 
                                $pct = ($bx['total_marks'] > 0) ? round(($bx['marks_obtained'] / $bx['total_marks']) * 100, 1) : 0;
                            ?>
                                <tr>
                                    <td><strong style="color: var(--primary-light);"><?= htmlspecialchars($bx['board_roll_no'] ?: '—') ?></strong></td>
                                    <td><?= htmlspecialchars($bx['board_registration_no'] ?: '—') ?></td>
                                    <td><?= htmlspecialchars($bx['session_year'] ?: '—') ?></td>
                                    <td><strong><?= (int)$bx['marks_obtained'] ?></strong></td>
                                    <td><?= (int)$bx['total_marks'] ?></td>
                                    <td><?= $pct ?>%</td>
                                    <td>
                                        <span class="status-badge <?= $bx['exam_status'] === 'pass' ? 'badge-active' : 'badge-inactive' ?>">
                                            <?= ucfirst(htmlspecialchars($bx['exam_status'])) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 5. Academic Achievements Tab -->
    <div class="tab-pane" id="tab-achievements">
        <div class="glass-card">
            <h3 class="card-title" style="margin-bottom: 18px;">Board Merit Positions & Institutional Honors</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Merit Rank</th>
                            <th>Program / Course</th>
                            <th>Academic Session</th>
                            <th>Award Details / Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($positions)): ?>
                            <tr><td colspan="4" class="empty-state">No board merit positions recorded for this student.</td></tr>
                        <?php else: ?>
                            <?php foreach ($positions as $p): ?>
                                <tr>
                                    <td>
                                        <strong style="color: <?= $p['position_rank'] == 1 ? '#fbbf24' : ($p['position_rank'] == 2 ? '#cbd5e1' : '#f97316') ?>; font-size: 16px;">
                                            🏆 Rank #<?= (int)$p['position_rank'] ?>
                                        </strong>
                                    </td>
                                    <td><?= htmlspecialchars($p['course_name'] ?: '—') ?></td>
                                    <td><?= htmlspecialchars($p['session_name'] ?: '—') ?></td>
                                    <td style="color: var(--text-muted);"><?= htmlspecialchars($p['remarks'] ?: 'Official Merit Distinction') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
renderPrintSignatures(['Prepared By (Registrar)', 'Accounts Officer', 'Principal / Director ICT']);
require_once __DIR__ . '/../../includes/footer.php'; 
?>
