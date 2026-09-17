<?php
/**
 * Board Examination (KPK TTB) Management Module
 * Islamia College of Technology College Management System
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

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
            $error = "Insufficient permissions to register board examination records.";
        } else {
            $studentId  = (int)($_POST['student_id'] ?? 0);
            $boardName  = trim($_POST['board_name'] ?? 'Trade Testing Board (TTB) Peshawar');
            $regNo      = trim($_POST['board_registration_no'] ?? '');
            $rollNo     = trim($_POST['board_roll_no'] ?? '');
            $trade      = trim($_POST['trade_course_name'] ?? '');
            $regDate    = !empty($_POST['registration_date']) ? $_POST['registration_date'] : null;
            $feePaid    = (float)($_POST['exam_fee_paid'] ?? 0);
            $status     = strtolower(trim($_POST['exam_status'] ?? 'pass'));
            $marks      = !empty($_POST['marks_obtained']) ? (float)$_POST['marks_obtained'] : null;
            $totalMarks = !empty($_POST['total_marks']) ? (float)$_POST['total_marks'] : 1100.0;

            if ($studentId <= 0 || empty($trade)) {
                $error = "Student and trade technology name are required.";
            } else {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO board_examinations (student_id, board_name, board_registration_no, board_roll_no, trade_course_name, registration_date, exam_fee_paid, exam_status, marks_obtained, total_marks)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$studentId, $boardName, $regNo, $rollNo, $trade, $regDate, $feePaid, $status, $marks, $totalMarks]);
                    $message = "Board examination record saved successfully.";
                } catch (PDOException $e) {
                    error_log("Create board exam error: " . $e->getMessage());
                    $error = "An error occurred while saving the board examination record.";
                }
            }
        }
    } elseif ($action === 'update') {
        if (!$canManage) {
            $error = "Insufficient permissions to update examination records.";
        } else {
            $id         = (int)($_POST['id'] ?? 0);
            $regNo      = trim($_POST['board_registration_no'] ?? '');
            $rollNo     = trim($_POST['board_roll_no'] ?? '');
            $trade      = trim($_POST['trade_course_name'] ?? '');
            $status     = strtolower(trim($_POST['exam_status'] ?? 'pass'));
            $marks      = !empty($_POST['marks_obtained']) ? (float)$_POST['marks_obtained'] : null;
            $totalMarks = !empty($_POST['total_marks']) ? (float)$_POST['total_marks'] : 1100.0;

            if ($id <= 0 || empty($trade)) {
                $error = "Record ID and trade name are required.";
            } else {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE board_examinations 
                        SET board_registration_no = ?, board_roll_no = ?, trade_course_name = ?, exam_status = ?, marks_obtained = ?, total_marks = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$regNo, $rollNo, $trade, $status, $marks, $totalMarks, $id]);
                    $message = "Board examination record updated successfully.";
                } catch (PDOException $e) {
                    error_log("Update board exam error: " . $e->getMessage());
                    $error = "An error occurred while updating the examination record.";
                }
            }
        }
    } elseif ($action === 'delete') {
        if (!$canManage) {
            $error = "Insufficient permissions to delete examination records.";
        } else {
            $id = (int)($_POST['id'] ?? 0);
            try {
                $stmt = $pdo->prepare("DELETE FROM board_examinations WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Examination record removed successfully.";
            } catch (PDOException $e) {
                error_log("Delete board exam error: " . $e->getMessage());
                $error = "An error occurred while removing the examination record.";
            }
        }
    }
}

// Search & Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(s.full_name LIKE ? OR b.board_roll_no LIKE ? OR b.board_registration_no LIKE ? OR b.trade_course_name LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term]);
}

if (!empty($statusFilter)) {
    $where[] = "b.exam_status = ?";
    $params[] = $statusFilter;
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Count total
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM board_examinations b 
    JOIN students s ON b.student_id = s.id 
    {$whereClause}
");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRecords / $limit));

// Fetch Records
$sql = "
    SELECT b.*, s.full_name, s.student_reg_no, s.father_name
    FROM board_examinations b
    JOIN students s ON b.student_id = s.id
    {$whereClause}
    ORDER BY b.id DESC
    LIMIT " . (int)$limit . " OFFSET " . (int)$offset . "
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$boardRecords = $stmt->fetchAll();

$studentsList = $pdo->query("SELECT id, student_reg_no, full_name, father_name FROM students ORDER BY full_name ASC LIMIT 500")->fetchAll();

$pageTitle = "Board Examinations";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Board Examinations (KPK TTB)</h1>
        <p class="page-breadcrumb">Trade Testing Board Peshawar • Gazette records, board registration numbers, and marks gazette</p>
    </div>
    <?php if ($canManage): ?>
    <div>
        <button type="button" class="btn btn-primary" onclick="openModal('addExamModal')">
            <span>+ Add Examination Entry</span>
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
        <div class="filter-group" style="flex: 1; max-width: 380px;">
            <input type="text" name="search" class="form-control" placeholder="Search by student, board roll, reg no..." value="<?= htmlspecialchars($search) ?>" style="min-height: 40px;">
        </div>
        <div class="filter-group">
            <select name="status" class="form-control" style="min-height: 40px; min-width: 160px;">
                <option value="">All Exam Statuses</option>
                <option value="pass" <?= $statusFilter === 'pass' ? 'selected' : '' ?>>Pass</option>
                <option value="fail" <?= $statusFilter === 'fail' ? 'selected' : '' ?>>Fail</option>
                <option value="enrolled" <?= $statusFilter === 'enrolled' ? 'selected' : '' ?>>Enrolled / Pending</option>
            </select>
            <button type="submit" class="btn btn-glass" style="min-height: 40px;">Filter</button>
            <?php if (!empty($search) || !empty($statusFilter)): ?>
                <a href="<?= $baseUri ?>/modules/board-examinations/index.php" class="btn btn-glass" style="min-height: 40px;">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Table -->
<div class="glass-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Board Roll No</th>
                    <th>Board Registration</th>
                    <th>Candidate Name</th>
                    <th>Father Name</th>
                    <th>Trade / Technology</th>
                    <th>Marks / Total</th>
                    <th>Percentage</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($boardRecords)): ?>
                    <tr>
                        <td colspan="9" class="empty-state">
                            <div class="empty-state-icon">📋</div>
                            <div class="empty-state-title">No board examination records found</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($boardRecords as $b): 
                        $pct = ($b['total_marks'] > 0 && $b['marks_obtained'] !== null) ? round(($b['marks_obtained'] / $b['total_marks']) * 100, 1) : null;
                    ?>
                        <tr>
                            <td><strong style="color: var(--primary-light); font-family: monospace; font-size: 15px;"><?= htmlspecialchars($b['board_roll_no'] ?: 'Pending') ?></strong></td>
                            <td style="color: var(--text-muted); font-size: 12px; font-family: monospace;"><?= htmlspecialchars($b['board_registration_no'] ?: '—') ?></td>
                            <td>
                                <a href="../students/view.php?id=<?= $b['student_id'] ?>" style="color: var(--text-primary); text-decoration: none; font-weight: 600;">
                                    <?= htmlspecialchars($b['full_name']) ?>
                                </a>
                            </td>
                            <td style="color: var(--text-muted);"><?= htmlspecialchars($b['father_name'] ?: '—') ?></td>
                            <td><?= htmlspecialchars($b['trade_course_name']) ?></td>
                            <td>
                                <?= $b['marks_obtained'] !== null ? "<strong>{$b['marks_obtained']}</strong> / " . (int)$b['total_marks'] : '—' ?>
                            </td>
                            <td><?= $pct !== null ? "{$pct}%" : '—' ?></td>
                            <td>
                                <span class="status-badge <?= $b['exam_status'] === 'pass' ? 'badge-active' : ($b['exam_status'] === 'fail' ? 'badge-inactive' : 'badge-pending') ?>">
                                    <?= ucfirst(htmlspecialchars($b['exam_status'])) ?>
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                    <a href="../students/view.php?id=<?= $b['student_id'] ?>" class="btn btn-glass btn-sm">Profile</a>
                                    <?php if ($canManage): ?>
                                        <button type="button" class="btn btn-glass btn-sm" onclick="openEditExamModal(<?= htmlspecialchars(json_encode($b), ENT_QUOTES) ?>)">Edit</button>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="confirmDeleteExam(<?= $b['id'] ?>)">Delete</button>
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
                Showing <strong><?= min($totalRecords, $offset + 1) ?>–<?= min($totalRecords, $offset + count($boardRecords)) ?></strong> of <strong><?= number_format($totalRecords) ?></strong> examination records
            </div>
            <div class="pagination-links">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>" class="page-btn">←</a>
                <?php endif; ?>

                <?php 
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                for ($p = $startPage; $p <= $endPage; $p++): 
                ?>
                    <a href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>" class="page-btn <?= $p === $page ? 'active' : '' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>" class="page-btn">→</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Add Exam Record -->
<div class="modal-overlay" id="addExamModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Record Board Examination</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('addExamModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="add_exam_student_id">Select Candidate <span class="required">*</span></label>
                    <select id="add_exam_student_id" name="student_id" class="form-control" required>
                        <option value="">Select registered student...</option>
                        <?php foreach ($studentsList as $st): ?>
                            <option value="<?= $st['id'] ?>">
                                <?= htmlspecialchars($st['student_reg_no']) ?> — <?= htmlspecialchars($st['full_name']) ?> (S/O <?= htmlspecialchars($st['father_name'] ?: 'N/A') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="add_board_roll">Board Roll Number</label>
                        <input type="text" id="add_board_roll" name="board_roll_no" class="form-control" placeholder="e.g. 56766">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_board_reg">Board Registration No.</label>
                        <input type="text" id="add_board_reg" name="board_registration_no" class="form-control" placeholder="e.g. ICT/SWT/DIT/18-20/...">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="add_trade_course">Trade / Examination Technology <span class="required">*</span></label>
                    <input type="text" id="add_trade_course" name="trade_course_name" class="form-control" required placeholder="e.g. DIT Part-I, DIT Part-II, Graphic Design">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="add_marks">Marks Obtained</label>
                        <input type="number" id="add_marks" name="marks_obtained" class="form-control" step="0.5" placeholder="e.g. 890">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_total_marks">Total Marks</label>
                        <input type="number" id="add_total_marks" name="total_marks" class="form-control" value="1100" step="1">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_exam_status">Status</label>
                        <select id="add_exam_status" name="exam_status" class="form-control">
                            <option value="pass">Pass</option>
                            <option value="fail">Fail</option>
                            <option value="enrolled">Enrolled / Pending</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-glass" onclick="closeModal('addExamModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Examination Record</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Exam Record -->
<div class="modal-overlay" id="editExamModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Edit Examination Gazette Entry</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('editExamModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_exam_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Candidate Name</label>
                    <input type="text" id="edit_student_name" class="form-control" disabled style="opacity: 0.7;">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="edit_board_roll">Board Roll Number</label>
                        <input type="text" id="edit_board_roll" name="board_roll_no" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_board_reg">Board Registration No.</label>
                        <input type="text" id="edit_board_reg" name="board_registration_no" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_trade_course">Trade / Examination Technology <span class="required">*</span></label>
                    <input type="text" id="edit_trade_course" name="trade_course_name" class="form-control" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="edit_marks">Marks Obtained</label>
                        <input type="number" id="edit_marks" name="marks_obtained" class="form-control" step="0.5">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_total_marks">Total Marks</label>
                        <input type="number" id="edit_total_marks" name="total_marks" class="form-control" step="1">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_exam_status">Status</label>
                        <select id="edit_exam_status" name="exam_status" class="form-control">
                            <option value="pass">Pass</option>
                            <option value="fail">Fail</option>
                            <option value="enrolled">Enrolled / Pending</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-glass" onclick="closeModal('editExamModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Entry</button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden Delete Form with CSRF -->
<form id="delete-exam-form" method="POST" action="" style="display:none;">
    <?= getCsrfField() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_exam_id">
</form>

<script>
function openEditExamModal(record) {
    document.getElementById('edit_exam_id').value = record.id;
    document.getElementById('edit_student_name').value = `${record.student_reg_no} — ${record.full_name}`;
    document.getElementById('edit_board_roll').value = record.board_roll_no || '';
    document.getElementById('edit_board_reg').value = record.board_registration_no || '';
    document.getElementById('edit_trade_course').value = record.trade_course_name || '';
    document.getElementById('edit_marks').value = record.marks_obtained !== null ? record.marks_obtained : '';
    document.getElementById('edit_total_marks').value = record.total_marks || 1100;
    document.getElementById('edit_exam_status').value = record.exam_status || 'pass';
    openModal('editExamModal');
}

function confirmDeleteExam(id) {
    confirmAction(
        'Delete Examination Entry?',
        'Are you sure you want to remove this board examination record?',
        () => {
            document.getElementById('delete_exam_id').value = id;
            document.getElementById('delete-exam-form').submit();
        }
    );
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
