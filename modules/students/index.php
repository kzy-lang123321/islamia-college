<?php
/**
 * Student Management Directory & Records
 * Islamia College of Technology College Management System
 * Human-Centered Design: Student Directory & Seamless Admission Workflow
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// RBAC: Staff, Registrar, and Admin can view student directory
requireRole(['admin', 'registrar', 'staff']);

$pdo = Database::getConnection();
$currentUser = getCurrentUser();
$userRole = strtolower($currentUser['role'] ?? 'staff');
$canManage = in_array($userRole, ['admin', 'registrar']);

$message = '';
$error = '';

// Handle POST actions: Add, Edit, Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfRequest();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        if (!$canManage) {
            $error = "Insufficient permissions to register students.";
        } else {
            $regNo   = trim($_POST['student_reg_no'] ?? '');
            $name    = trim($_POST['full_name'] ?? '');
            $fname   = trim($_POST['father_name'] ?? '');
            $contact = trim($_POST['contact_number'] ?? '');
            $cnic    = trim($_POST['cnic'] ?? '');
            $dob     = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
            $email   = trim($_POST['email'] ?? '');
            $address = trim($_POST['address'] ?? '');

            if (empty($name)) {
                $error = "Student full name is required.";
            } else {
                if (empty($regNo)) {
                    $regNo = 'ICT-STD-' . rand(3000, 9999);
                }
                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare("
                        INSERT INTO students (student_reg_no, full_name, father_name, contact_number, cnic, date_of_birth, email, address)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$regNo, $name, $fname, $contact, $cnic, $dob, $email, $address]);
                    $newStudentId = (int)$pdo->lastInsertId();

                    // Check if academic enrollment was also filled in
                    $batchId = (int)($_POST['batch_id'] ?? 0);
                    if ($batchId > 0) {
                        $rollNo = trim($_POST['roll_number'] ?? '');
                        $enrDate = !empty($_POST['enrollment_date']) ? $_POST['enrollment_date'] : date('Y-m-d');
                        $agreedFee = (float)($_POST['total_agreed_fee'] ?? 0);

                        $enrStmt = $pdo->prepare("
                            INSERT INTO enrollments (student_id, batch_id, roll_number, enrollment_date, total_agreed_fee, status)
                            VALUES (?, ?, ?, ?, ?, 'active')
                        ");
                        $enrStmt->execute([$newStudentId, $batchId, $rollNo, $enrDate, $agreedFee]);
                    }

                    $pdo->commit();
                    $message = "Student '{$name}' registered successfully (Registration No: {$regNo})." . ($batchId > 0 ? " Academic enrollment created." : "");
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log("Student registration error: " . $e->getMessage());
                    $error = ($e->getCode() == 23000) ? "A student with this registration number already exists." : "An error occurred while registering the student.";
                }
            }
        }
    } elseif ($action === 'update') {
        if (!$canManage) {
            $error = "Insufficient permissions to update student records.";
        } else {
            $id      = (int)($_POST['id'] ?? 0);
            $name    = trim($_POST['full_name'] ?? '');
            $fname   = trim($_POST['father_name'] ?? '');
            $contact = trim($_POST['contact_number'] ?? '');
            $cnic    = trim($_POST['cnic'] ?? '');
            $dob     = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
            $email   = trim($_POST['email'] ?? '');
            $address = trim($_POST['address'] ?? '');

            if ($id <= 0 || empty($name)) {
                $error = "Student full name is required.";
            } else {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE students 
                        SET full_name = ?, father_name = ?, contact_number = ?, cnic = ?, date_of_birth = ?, email = ?, address = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $fname, $contact, $cnic, $dob, $email, $address, $id]);
                    $message = "Student record updated successfully.";
                } catch (PDOException $e) {
                    error_log("Student update error: " . $e->getMessage());
                    $error = "An error occurred while updating the student record.";
                }
            }
        }
    } elseif ($action === 'delete') {
        if (!$canManage) {
            $error = "Insufficient permissions to delete student records.";
        } else {
            $id = (int)($_POST['id'] ?? 0);
            try {
                $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Student record deleted successfully.";
            } catch (PDOException $e) {
                error_log("Student delete error: " . $e->getMessage());
                $error = "Cannot delete student: this student has active enrollment, fee, or exam records. Please remove those records first.";
            }
        }
    }
}

// Search & Filter Parameters
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');
$batchFilter = (int)($_GET['batch_id'] ?? 0);
$courseFilter = (int)($_GET['course_id'] ?? 0);

$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(s.full_name LIKE ? OR s.student_reg_no LIKE ? OR s.father_name LIKE ? OR s.contact_number LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term]);
}

if ($batchFilter > 0) {
    $where[] = "s.id IN (SELECT student_id FROM enrollments WHERE batch_id = ?)";
    $params[] = $batchFilter;
}

if ($courseFilter > 0) {
    $where[] = "s.id IN (SELECT e.student_id FROM enrollments e JOIN batches b ON e.batch_id = b.id WHERE b.course_id = ?)";
    $params[] = $courseFilter;
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Total Count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM students s {$whereClause}");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRecords / $limit));

// Fetch Records with active enrollment summary
$sql = "
    SELECT s.*, 
           (SELECT COUNT(*) FROM enrollments e WHERE e.student_id = s.id) as total_enrollments,
           (SELECT c.course_name FROM enrollments e JOIN batches b ON e.batch_id = b.id JOIN courses c ON b.course_id = c.id WHERE e.student_id = s.id ORDER BY e.id DESC LIMIT 1) as latest_course,
           (SELECT b.batch_code FROM enrollments e JOIN batches b ON e.batch_id = b.id WHERE e.student_id = s.id ORDER BY e.id DESC LIMIT 1) as latest_batch
    FROM students s
    {$whereClause}
    ORDER BY s.id DESC
    LIMIT " . (int)$limit . " OFFSET " . (int)$offset . "
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Dropdown filter and admission options
$batchesList = $pdo->query("
    SELECT b.id, b.batch_code, b.batch_name, c.course_name 
    FROM batches b 
    JOIN courses c ON b.course_id = c.id 
    ORDER BY c.course_name ASC, b.batch_code ASC
")->fetchAll();
$coursesList = $pdo->query("SELECT id, course_name, category FROM courses ORDER BY course_name ASC")->fetchAll();

$pageTitle = "Students Directory";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Students</h1>
        <p class="page-breadcrumb">Manage student records, admissions, contact profiles, and academic information</p>
    </div>
    <?php if ($canManage): ?>
    <div>
        <button type="button" class="btn btn-primary" onclick="openAddStudentModal()">
            <span>+ Add Student</span>
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

<!-- Search & Filters -->
<div class="glass-card" style="padding: 16px 20px; margin-bottom: 24px;">
    <form method="GET" action="" class="filter-bar" style="margin-bottom: 0;">
        <div class="filter-group" style="flex: 1; max-width: 340px;">
            <input type="text" name="search" class="form-control" placeholder="Search by name, registration no, phone..." value="<?= htmlspecialchars($search) ?>" style="min-height: 40px;">
        </div>
        <div class="filter-group">
            <select name="course_id" class="form-control" style="min-height: 40px; min-width: 170px;">
                <option value="">All Programs / Courses</option>
                <?php foreach ($coursesList as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $courseFilter === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['course_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <select name="batch_id" class="form-control" style="min-height: 40px; min-width: 160px;">
                <option value="">All Academic Batches</option>
                <?php foreach ($batchesList as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $batchFilter === (int)$b['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($b['batch_code']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-glass" style="min-height: 40px;">Search</button>
            <?php if (!empty($search) || $batchFilter > 0 || $courseFilter > 0): ?>
                <a href="<?= $baseUri ?>/modules/students/index.php" class="btn btn-glass" style="min-height: 40px;">Clear Filters</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Student Roster Table -->
<div class="glass-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Registration No.</th>
                    <th>Student Name</th>
                    <th>Father Name</th>
                    <th>Current Course & Batch</th>
                    <th>Contact Number</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="7" class="empty-state">
                            <div class="empty-state-icon">🎓</div>
                            <div class="empty-state-title">No students found</div>
                            <div class="empty-state-desc">
                                <?php if (!empty($search) || $batchFilter > 0 || $courseFilter > 0): ?>
                                    There are no students matching your search criteria.
                                    <div style="margin-top: 14px;">
                                        <a href="<?= $baseUri ?>/modules/students/index.php" class="btn btn-glass btn-sm">Clear Filters</a>
                                    </div>
                                <?php else: ?>
                                    No student records have been registered yet.
                                    <?php if ($canManage): ?>
                                        <div style="margin-top: 14px;">
                                            <button type="button" class="btn btn-primary btn-sm" onclick="openAddStudentModal()">+ Add First Student</button>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($students as $s): ?>
                        <tr>
                            <td>
                                <strong style="color: var(--primary-light); font-family: monospace; font-size: 13px;">
                                    <?= htmlspecialchars($s['student_reg_no']) ?>
                                </strong>
                            </td>
                            <td>
                                <a href="view.php?id=<?= $s['id'] ?>" style="color: var(--text-primary); text-decoration: none; font-weight: 600;">
                                    <?= htmlspecialchars($s['full_name']) ?>
                                </a>
                                <?php if (!empty($s['cnic'])): ?>
                                    <div style="font-size: 11px; color: var(--text-dim);"><?= htmlspecialchars($s['cnic']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="color: var(--text-muted);"><?= htmlspecialchars($s['father_name'] ?: '—') ?></td>
                            <td>
                                <?php if ($s['latest_course']): ?>
                                    <div><?= htmlspecialchars($s['latest_course']) ?></div>
                                    <div style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($s['latest_batch']) ?></div>
                                <?php else: ?>
                                    <span style="color: var(--text-dim); font-size: 12px;">Not Enrolled</span>
                                <?php endif; ?>
                            </td>
                            <td style="color: var(--text-muted);"><?= htmlspecialchars($s['contact_number'] ?: '—') ?></td>
                            <td>
                                <span class="status-badge <?= $s['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                                    <?= $s['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                    <a href="view.php?id=<?= $s['id'] ?>" class="btn btn-glass btn-sm" title="View 360 Degree Profile">Profile</a>
                                    <?php if ($canManage): ?>
                                        <button type="button" class="btn btn-glass btn-sm" onclick='openEditStudentModal(<?= json_encode($s) ?>)'>Edit</button>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="confirmDeleteStudent(<?= $s['id'] ?>, '<?= addslashes($s['full_name']) ?>', '<?= addslashes($s['student_reg_no']) ?>')">Delete</button>
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
                Showing <strong><?= min($totalRecords, $offset + 1) ?>–<?= min($totalRecords, $offset + count($students)) ?></strong> of <strong><?= number_format($totalRecords) ?></strong> students
            </div>
            <div class="pagination-links">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&batch_id=<?= $batchFilter ?>&course_id=<?= $courseFilter ?>" class="page-btn">←</a>
                <?php endif; ?>

                <?php 
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                for ($p = $startPage; $p <= $endPage; $p++): 
                ?>
                    <a href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&batch_id=<?= $batchFilter ?>&course_id=<?= $courseFilter ?>" class="page-btn <?= $p === $page ? 'active' : '' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&batch_id=<?= $batchFilter ?>&course_id=<?= $courseFilter ?>" class="page-btn">→</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Add Student (Human-Centered Structured Form) -->
<div class="modal-overlay" id="addStudentModal">
    <div class="modal-dialog" style="max-width: 650px;">
        <div class="modal-header">
            <h3 class="modal-title">Register New Student</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('addStudentModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <!-- Section 1: Personal Information -->
                <div style="margin-bottom: 20px;">
                    <h4 style="font-size: 14px; font-weight: 600; color: #38bdf8; margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                        <span>👤</span> Personal Information
                    </h4>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label" for="add_full_name">Full Name <span class="required">*</span></label>
                            <input type="text" id="add_full_name" name="full_name" class="form-control" required placeholder="Enter student's full name">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="add_father_name">Father's Name</label>
                            <input type="text" id="add_father_name" name="father_name" class="form-control" placeholder="Enter father's name">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label" for="add_contact">Phone Number</label>
                            <input type="text" id="add_contact" name="contact_number" class="form-control" placeholder="e.g. 0345-1234567">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="add_cnic">CNIC / Form-B</label>
                            <input type="text" id="add_cnic" name="cnic" class="form-control" placeholder="15602-xxxxxxx-x">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label" for="add_dob">Date of Birth</label>
                            <input type="date" id="add_dob" name="date_of_birth" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="add_email">Email Address</label>
                            <input type="email" id="add_email" name="email" class="form-control" placeholder="student@example.com">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="add_address">Permanent Address</label>
                        <textarea id="add_address" name="address" class="form-control" placeholder="Village, Tehsil, District Swat..." style="height: 60px;"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="add_reg_no">Registration No. (Optional)</label>
                        <input type="text" id="add_reg_no" name="student_reg_no" class="form-control" placeholder="Leave blank to auto-generate (e.g. ICT-STD-XXXX)">
                    </div>
                </div>

                <!-- Section 2: Academic Program (Optional Instant Admission) -->
                <div style="border-top: 1px solid rgba(255,255,255,0.08); padding-top: 16px; margin-bottom: 20px;">
                    <h4 style="font-size: 14px; font-weight: 600; color: #38bdf8; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                        <span>📚</span> Academic Program Enrollment (Optional)
                    </h4>
                    <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 14px;">Directly enroll student into a batch during admission</p>

                    <div class="form-group">
                        <label class="form-label" for="add_batch_id">Select Academic Batch</label>
                        <select id="add_batch_id" name="batch_id" class="form-control">
                            <option value="">-- No immediate batch enrollment --</option>
                            <?php foreach ($batchesList as $b): ?>
                                <option value="<?= $b['id'] ?>">
                                    <?= htmlspecialchars($b['course_name']) ?> — <?= htmlspecialchars($b['batch_code']) ?> (<?= htmlspecialchars($b['batch_name'] ?: 'General Session') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label" for="add_roll_no">Class Roll Number</label>
                            <input type="text" id="add_roll_no" name="roll_number" class="form-control" placeholder="e.g. 01">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="add_enr_date">Enrollment Date</label>
                            <input type="date" id="add_enr_date" name="enrollment_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="add_total_fee">Total Agreed Fee (PKR)</label>
                        <input type="number" step="100" id="add_total_fee" name="total_agreed_fee" class="form-control" placeholder="e.g. 15000">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-glass" onclick="closeModal('addStudentModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Complete Student Admission</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Student -->
<div class="modal-overlay" id="editStudentModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Edit Student Record</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('editStudentModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Registration Number</label>
                    <input type="text" id="edit_reg_no" class="form-control" disabled style="opacity:0.7;">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="edit_full_name">Full Name <span class="required">*</span></label>
                        <input type="text" id="edit_full_name" name="full_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_father_name">Father's Name</label>
                        <input type="text" id="edit_father_name" name="father_name" class="form-control">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="edit_contact">Phone Number</label>
                        <input type="text" id="edit_contact" name="contact_number" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_cnic">CNIC / Form-B</label>
                        <input type="text" id="edit_cnic" name="cnic" class="form-control">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="edit_dob">Date of Birth</label>
                        <input type="date" id="edit_dob" name="date_of_birth" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_email">Email Address</label>
                        <input type="email" id="edit_email" name="email" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_address">Permanent Address</label>
                    <textarea id="edit_address" name="address" class="form-control"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-glass" onclick="closeModal('editStudentModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden form for secure delete with CSRF -->
<form id="delete-student-form" method="POST" action="" style="display:none;">
    <?= getCsrfField() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_student_id">
</form>

<script>
function openAddStudentModal() {
    openModal('addStudentModal');
}

function openEditStudentModal(student) {
    document.getElementById('edit_id').value = student.id;
    document.getElementById('edit_reg_no').value = student.student_reg_no || '';
    document.getElementById('edit_full_name').value = student.full_name || '';
    document.getElementById('edit_father_name').value = student.father_name || '';
    document.getElementById('edit_contact').value = student.contact_number || '';
    document.getElementById('edit_cnic').value = student.cnic || '';
    document.getElementById('edit_dob').value = student.date_of_birth || '';
    document.getElementById('edit_email').value = student.email || '';
    document.getElementById('edit_address').value = student.address || '';
    openModal('editStudentModal');
}

function confirmDeleteStudent(id, name, regNo) {
    confirmAction(
        'Delete Student Record?',
        `Are you sure you want to delete ${name} (${regNo})? This action cannot be easily undone.`,
        () => {
            document.getElementById('delete_student_id').value = id;
            document.getElementById('delete-student-form').submit();
        }
    );
}

document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('new') === '1') {
        openAddStudentModal();
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
