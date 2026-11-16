<?php
/**
 * Enrollment Management Module
 * Islamia College of Technology College Management System
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// RBAC: Staff, Registrar, and Admin can view enrollments
requireRole(['admin', 'registrar', 'staff']);

$pdo = Database::getConnection();
$currentUser = getCurrentUser();
$userRole = strtolower($currentUser['role'] ?? 'staff');
$canManage = in_array($userRole, ['admin', 'registrar']);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfRequest();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        if (!$canManage) {
            $error = "Insufficient permissions to register enrollments.";
        } else {
            $studentId = (int)($_POST['student_id'] ?? 0);
            $batchId   = (int)($_POST['batch_id'] ?? 0);
            $rollNo    = trim($_POST['roll_number'] ?? '');
            $date      = !empty($_POST['enrollment_date']) ? $_POST['enrollment_date'] : date('Y-m-d');
            $fee       = (float)($_POST['total_agreed_fee'] ?? 0);
            $admFee    = (float)($_POST['admission_fee'] ?? 0);
            $slipNo    = trim($_POST['receipt_slip_no'] ?? '');
            $status    = strtolower(trim($_POST['status'] ?? 'active'));

            if ($studentId <= 0 || $batchId <= 0) {
                $error = "Valid student and academic batch selection are required.";
            } else {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO enrollments (student_id, batch_id, roll_number, enrollment_date, total_agreed_fee, admission_fee, receipt_slip_no, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$studentId, $batchId, $rollNo, $date, $fee, $admFee, $slipNo, $status]);
                    $message = "Enrollment recorded successfully.";
                } catch (PDOException $e) {
                    error_log("Create enrollment error: " . $e->getMessage());
                    $error = ($e->getCode() == 23000) ? "This student is already enrolled in this batch." : "An error occurred while creating the enrollment.";
                }
            }
        }
    } elseif ($action === 'update') {
        if (!$canManage) {
            $error = "Insufficient permissions to modify enrollments.";
        } else {
            $id     = (int)($_POST['id'] ?? 0);
            $rollNo = trim($_POST['roll_number'] ?? '');
            $fee    = (float)($_POST['total_agreed_fee'] ?? 0);
            $admFee = (float)($_POST['admission_fee'] ?? 0);
            $slipNo = trim($_POST['receipt_slip_no'] ?? '');
            $status = strtolower(trim($_POST['status'] ?? 'active'));

            if ($id <= 0) {
                $error = "Invalid enrollment record.";
            } else {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE enrollments 
                        SET roll_number = ?, total_agreed_fee = ?, admission_fee = ?, receipt_slip_no = ?, status = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$rollNo, $fee, $admFee, $slipNo, $status, $id]);
                    $message = "Enrollment updated successfully.";
                } catch (PDOException $e) {
                    error_log("Update enrollment error: " . $e->getMessage());
                    $error = "An error occurred while updating the enrollment record.";
                }
            }
        }
    } elseif ($action === 'delete') {
        if (!$canManage) {
            $error = "Insufficient permissions to delete enrollments.";
        } else {
            $id = (int)($_POST['id'] ?? 0);
            try {
                $stmt = $pdo->prepare("DELETE FROM enrollments WHERE id = ? AND NOT EXISTS (SELECT 1 FROM fee_transactions WHERE enrollment_id = enrollments.id)");
                $stmt->execute([$id]);
                if ($stmt->rowCount() === 0) {
                    throw new PDOException("Enrollment has linked payments or no longer exists.");
                }
                $message = "Enrollment removed successfully.";
            } catch (PDOException $e) {
                error_log("Delete enrollment error: " . $e->getMessage());
                $error = "Cannot delete enrollment: active fee payments are linked to this record.";
            }
        }
    }
}

// Pagination & Filters
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');
$batchFilter = (int)($_GET['batch_id'] ?? 0);

$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(s.full_name LIKE ? OR s.student_reg_no LIKE ? OR e.roll_number LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term]);
}

if ($batchFilter > 0) {
    $where[] = "e.batch_id = ?";
    $params[] = $batchFilter;
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Count Total
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM enrollments e 
    JOIN students s ON e.student_id = s.id 
    {$whereClause}
");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRecords / $limit));

// Fetch Records with course and batch details
$sql = "
    SELECT e.*, s.full_name, s.student_reg_no, s.father_name, b.batch_code, c.course_name,
           (SELECT COALESCE(SUM(amount_paid), 0) FROM fee_transactions f WHERE f.enrollment_id = e.id) as fee_paid
    FROM enrollments e
    JOIN students s ON e.student_id = s.id
    JOIN batches b ON e.batch_id = b.id
    JOIN courses c ON b.course_id = c.id
    {$whereClause}
    ORDER BY e.id DESC
    LIMIT " . (int)$limit . " OFFSET " . (int)$offset . "
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$enrollments = $stmt->fetchAll();

// Dropdown lists
$studentsList = $pdo->query("SELECT id, student_reg_no, full_name, father_name FROM students ORDER BY full_name ASC LIMIT 1000")->fetchAll();
$batchesList = $pdo->query("
    SELECT b.id, b.batch_code, c.course_name 
    FROM batches b 
    JOIN courses c ON b.course_id = c.id 
    ORDER BY b.batch_code ASC
")->fetchAll();

$pageTitle = "Student Enrollments";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Student Enrollments</h1>
        <p class="page-breadcrumb">Manage program admissions, batch assignments, and fee schedules</p>
    </div>
    <?php if ($canManage): ?>
    <div>
        <button type="button" class="btn btn-primary" onclick="openModal('addEnrollModal')">
            <span>+ New Enrollment</span>
        </button>
    </div>
    <?php endif; ?>
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

<!-- Filters -->
<div class="glass-card" style="padding: 16px 20px; margin-bottom: 24px;">
    <form method="GET" action="" class="filter-bar" style="margin-bottom: 0;">
        <div class="filter-group" style="flex: 1; max-width: 360px;">
            <input type="text" name="search" class="form-control" placeholder="Search by student, reg no, class roll..." value="<?= htmlspecialchars($search) ?>" style="min-height: 40px;">
        </div>
        <div class="filter-group">
            <select name="batch_id" class="form-control" style="min-height: 40px; min-width: 200px;">
                <option value="">All Academic Batches</option>
                <?php foreach ($batchesList as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $batchFilter === (int)$b['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($b['batch_code']) ?> — <?= htmlspecialchars($b['course_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-glass" style="min-height: 40px;">Filter</button>
            <?php if (!empty($search) || $batchFilter > 0): ?>
                <a href="<?= $baseUri ?>/modules/enrollments/index.php" class="btn btn-glass" style="min-height: 40px;">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Enrollments Table -->
<div class="glass-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Registration No.</th>
                    <th>Student Full Name</th>
                    <th>Course / Technology</th>
                    <th>Batch</th>
                    <th>Roll No</th>
                    <th>Agreed Fee</th>
                    <th>Paid / Remaining</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($enrollments)): ?>
                    <tr>
                        <td colspan="9" class="empty-state">
                            <div class="empty-state-icon">📝</div>
                            <div class="empty-state-title">No enrollments recorded</div>
                            <div class="empty-state-desc">There are no enrollment records matching your current filter.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($enrollments as $e): 
                        $remaining = max(0.0, (float)$e['total_agreed_fee'] - (float)$e['fee_paid']);
                    ?>
                        <tr>
                            <td><strong style="color: var(--primary-light);"><?= htmlspecialchars($e['student_reg_no']) ?></strong></td>
                            <td>
                                <a href="../students/view.php?id=<?= $e['student_id'] ?>" style="color: var(--text-primary); text-decoration: none; font-weight: 600;">
                                    <?= htmlspecialchars($e['full_name']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($e['course_name']) ?></td>
                            <td><span style="padding: 3px 8px; border-radius: 4px; background: rgba(255,255,255,0.06); font-size: 12px;"><?= htmlspecialchars($e['batch_code']) ?></span></td>
                            <td style="color: var(--text-muted);"><?= htmlspecialchars($e['roll_number'] ?: '—') ?></td>
                            <td><strong>Rs. <?= number_format((float)$e['total_agreed_fee'], 0) ?></strong></td>
                            <td>
                                <div style="font-size: 13px;">
                                    <span style="color: var(--accent-emerald);">Rs. <?= number_format((float)$e['fee_paid'], 0) ?></span>
                                    <?php if ($remaining > 0): ?>
                                        <div style="font-size: 11px; color: var(--accent-amber);">Due: Rs. <?= number_format($remaining, 0) ?></div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge <?= $e['status'] === 'active' ? 'badge-active' : 'badge-inactive' ?>">
                                    <?= ucfirst(htmlspecialchars($e['status'])) ?>
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                    <a href="../students/view.php?id=<?= $e['student_id'] ?>" class="btn btn-glass btn-sm">Profile</a>
                                    <?php if ($canManage): ?>
                                        <button type="button" class="btn btn-glass btn-sm" onclick="openEditEnrollModal(<?= htmlspecialchars(json_encode($e), ENT_QUOTES) ?>)">Edit</button>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="confirmDeleteEnroll(<?= $e['id'] ?>)">Delete</button>
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
                Showing <strong><?= min($totalRecords, $offset + 1) ?>–<?= min($totalRecords, $offset + count($enrollments)) ?></strong> of <strong><?= number_format($totalRecords) ?></strong> enrollments
            </div>
            <div class="pagination-links">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&batch_id=<?= $batchFilter ?>" class="page-btn">←</a>
                <?php endif; ?>

                <?php 
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                for ($p = $startPage; $p <= $endPage; $p++): 
                ?>
                    <a href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&batch_id=<?= $batchFilter ?>" class="page-btn <?= $p === $page ? 'active' : '' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&batch_id=<?= $batchFilter ?>" class="page-btn">→</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: New Enrollment -->
<div class="modal-overlay" id="addEnrollModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Record New Enrollment</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('addEnrollModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="add_student_id">Select Student <span class="required">*</span></label>
                    <select id="add_student_id" name="student_id" class="form-control" required>
                        <option value="">Choose registered student...</option>
                        <?php foreach ($studentsList as $st): ?>
                            <option value="<?= $st['id'] ?>">
                                <?= htmlspecialchars($st['student_reg_no']) ?> — <?= htmlspecialchars($st['full_name']) ?> (S/O <?= htmlspecialchars($st['father_name'] ?: 'N/A') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="add_batch_id">Select Academic Batch & Program <span class="required">*</span></label>
                    <select id="add_batch_id" name="batch_id" class="form-control" required>
                        <option value="">Choose academic batch...</option>
                        <?php foreach ($batchesList as $bt): ?>
                            <option value="<?= $bt['id'] ?>">
                                <?= htmlspecialchars($bt['batch_code']) ?> — <?= htmlspecialchars($bt['course_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="add_roll">Class Roll Number</label>
                        <input type="text" id="add_roll" name="roll_number" class="form-control" placeholder="e.g. 101">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_date">Admission Date</label>
                        <input type="date" id="add_date" name="enrollment_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="add_fee">Total Agreed Fee (PKR) <span class="required">*</span></label>
                        <input type="number" id="add_fee" name="total_agreed_fee" class="form-control" step="100" required placeholder="e.g. 15000">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_adm_fee">Admission Fee (PKR)</label>
                        <input type="number" id="add_adm_fee" name="admission_fee" class="form-control" step="100" placeholder="e.g. 2000">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="add_slip">Receipt / Slip No</label>
                        <input type="text" id="add_slip" name="receipt_slip_no" class="form-control" placeholder="e.g. SLIP-2024-01">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_status">Enrollment Status</label>
                        <select id="add_status" name="status" class="form-control">
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="dropped">Dropped</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-glass" onclick="closeModal('addEnrollModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Enrollment</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Enrollment -->
<div class="modal-overlay" id="editEnrollModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Edit Enrollment Record</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('editEnrollModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_enroll_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Student & Program</label>
                    <input type="text" id="edit_student_name" class="form-control" disabled style="opacity: 0.7;">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="edit_roll">Class Roll Number</label>
                        <input type="text" id="edit_roll" name="roll_number" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_status">Status</label>
                        <select id="edit_status" name="status" class="form-control">
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="dropped">Dropped</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="edit_fee">Total Agreed Fee (PKR) <span class="required">*</span></label>
                        <input type="number" id="edit_fee" name="total_agreed_fee" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_adm_fee">Admission Fee (PKR)</label>
                        <input type="number" id="edit_adm_fee" name="admission_fee" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_slip">Receipt Slip No</label>
                    <input type="text" id="edit_slip" name="receipt_slip_no" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-glass" onclick="closeModal('editEnrollModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Enrollment</button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden form for secure delete with CSRF -->
<form id="delete-enroll-form" method="POST" action="" style="display:none;">
    <?= getCsrfField() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_enroll_id">
</form>

<script>
function openEditEnrollModal(enroll) {
    document.getElementById('edit_enroll_id').value = enroll.id;
    document.getElementById('edit_student_name').value = `${enroll.student_reg_no} — ${enroll.full_name} (${enroll.course_name})`;
    document.getElementById('edit_roll').value = enroll.roll_number || '';
    document.getElementById('edit_fee').value = enroll.total_agreed_fee || 0;
    document.getElementById('edit_adm_fee').value = enroll.admission_fee || 0;
    document.getElementById('edit_slip').value = enroll.receipt_slip_no || '';
    document.getElementById('edit_status').value = enroll.status || 'active';
    openModal('editEnrollModal');
}

function confirmDeleteEnroll(id) {
    confirmAction(
        'Delete Enrollment Record?',
        'Are you sure you want to remove this student enrollment? Any linked fee receipts may prevent deletion.',
        () => {
            document.getElementById('delete_enroll_id').value = id;
            document.getElementById('delete-enroll-form').submit();
        }
    );
}

document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('new') === '1') {
        openModal('addEnrollModal');
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
