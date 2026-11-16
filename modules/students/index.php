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

                    // Check if academic program enrollment was filled in
                    $courseId = (int)($_POST['course_id'] ?? 0);
                    $batchId  = (int)($_POST['batch_id'] ?? 0);

                    if ($courseId > 0 || $batchId > 0) {
                        $progCategory    = trim($_POST['program_category'] ?? '');
                        $academicSession = trim($_POST['academic_session'] ?? 'Session 2026-2027');
                        $classLevel      = trim($_POST['class_level'] ?? '');
                        $customClass     = trim($_POST['custom_class_level'] ?? '');
                        if (($classLevel === 'Other' || empty($classLevel)) && !empty($customClass)) {
                            $classLevel = $customClass;
                        }

                        $rollNo   = trim($_POST['roll_number'] ?? '');
                        $enrDate  = !empty($_POST['enrollment_date']) ? $_POST['enrollment_date'] : date('Y-m-d');
                        $admFee   = (float)($_POST['admission_fee'] ?? 0);
                        $tuiFee   = (float)($_POST['tuition_fee'] ?? 0);
                        $examFee  = (float)($_POST['exam_fee'] ?? 0);
                        $discount = (float)($_POST['discount_amount'] ?? 0);

                        $agreedFee = isset($_POST['total_agreed_fee']) && $_POST['total_agreed_fee'] !== ''
                            ? (float)$_POST['total_agreed_fee']
                            : max(0.0, ($admFee + $tuiFee + $examFee - $discount));

                        $enrStatus = strtolower(trim($_POST['enrollment_status'] ?? 'active'));
                        if (!in_array($enrStatus, ['active', 'pending', 'completed'])) {
                            $enrStatus = 'active';
                        }
                        $slipNo = trim($_POST['receipt_slip_no'] ?? '');

                        // Auto-resolve or create batch if needed so fk_enroll_batch constraint is 100% satisfied
                        if ($batchId <= 0 && $courseId > 0) {
                            $bStmt = $pdo->prepare("SELECT id FROM batches WHERE course_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1");
                            $bStmt->execute([$courseId]);
                            $foundBId = $bStmt->fetchColumn();
                            if ($foundBId) {
                                $batchId = (int)$foundBId;
                            } else {
                                $cStmt = $pdo->prepare("SELECT course_code, course_name FROM courses WHERE id = ?");
                                $cStmt->execute([$courseId]);
                                $cRow = $cStmt->fetch();
                                $cCode = $cRow ? $cRow['course_code'] : 'CRS' . $courseId;
                                $cName = $cRow ? $cRow['course_name'] : 'Course ' . $courseId;

                                $sessionSuffix = preg_replace('/[^A-Za-z0-9]/', '', $academicSession);
                                $batchCode = 'B-' . $cCode . '-' . substr($sessionSuffix, -4);

                                $chk = $pdo->prepare("SELECT id FROM batches WHERE batch_code = ?");
                                $chk->execute([$batchCode]);
                                $existingB = $chk->fetchColumn();
                                if ($existingB) {
                                    $batchId = (int)$existingB;
                                } else {
                                    $insB = $pdo->prepare("INSERT INTO batches (batch_code, batch_name, course_id, status) VALUES (?, ?, ?, 'active')");
                                    $insB->execute([$batchCode, $cName . ' (' . $academicSession . ')', $courseId]);
                                    $batchId = (int)$pdo->lastInsertId();
                                }
                            }
                        }

                        if ($courseId <= 0 && $batchId > 0) {
                            $bcStmt = $pdo->prepare("SELECT course_id FROM batches WHERE id = ?");
                            $bcStmt->execute([$batchId]);
                            $courseId = (int)$bcStmt->fetchColumn();
                        }

                        $enrStmt = $pdo->prepare("
                            INSERT INTO enrollments (
                                student_id, course_id, batch_id, roll_number, enrollment_date,
                                academic_session, class_level, tuition_class, tuition_subjects,
                                total_agreed_fee, admission_fee, discount_amount, receipt_slip_no, status
                            )
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $enrStmt->execute([
                            $newStudentId, $courseId, $batchId, $rollNo, $enrDate,
                            $academicSession, $classLevel, $classLevel, $classLevel,
                            $agreedFee, $admFee, $discount, $slipNo, $enrStatus
                        ]);
                    }

                    $pdo->commit();
                    $message = "Student '{$name}' admitted successfully (Registration No: {$regNo})." . (($courseId > 0 || $batchId > 0) ? " Academic program enrollment linked to profile." : "");
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
                $stmt = $pdo->prepare("DELETE FROM students WHERE id = ? AND NOT EXISTS (SELECT 1 FROM enrollments WHERE student_id = students.id) AND NOT EXISTS (SELECT 1 FROM board_examinations WHERE student_id = students.id)");
                $stmt->execute([$id]);
                if ($stmt->rowCount() === 0) {
                    throw new PDOException("Student has linked records or no longer exists.");
                }
                $message = "Student record deleted successfully.";
            } catch (PDOException $e) {
                error_log("Student delete error: " . $e->getMessage());
                $error = "Cannot delete student: this student has active enrollment, fee, or exam records. Please remove those records first.";
            }
        }
    }
}

// Search & Smart Filter Parameters
$isPrintAll   = (isset($_GET['print']) && $_GET['print'] === 'all');
$page         = max(1, (int)($_GET['page'] ?? 1));
$limit        = $isPrintAll ? 999999 : 20;
$offset       = ($page - 1) * $limit;

$search       = trim($_GET['search'] ?? '');
$batchFilter  = (int)($_GET['batch_id'] ?? 0);
$courseFilter = (int)($_GET['course_id'] ?? 0);
$statusFilter = trim($_GET['status'] ?? '');
$rollFilter   = trim($_GET['roll_number'] ?? '');
$dateFrom     = trim($_GET['date_from'] ?? '');
$dateTo       = trim($_GET['date_to'] ?? '');

$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(s.full_name LIKE ? OR s.student_reg_no LIKE ? OR s.father_name LIKE ? OR s.contact_number LIKE ? OR s.cnic LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term, $term]);
}

if (!empty($rollFilter)) {
    $where[] = "s.id IN (SELECT student_id FROM enrollments WHERE roll_number LIKE ?)";
    $params[] = "%{$rollFilter}%";
}

if ($batchFilter > 0) {
    $where[] = "s.id IN (SELECT student_id FROM enrollments WHERE batch_id = ?)";
    $params[] = $batchFilter;
}

if ($courseFilter > 0) {
    $where[] = "s.id IN (SELECT e.student_id FROM enrollments e JOIN batches b ON e.batch_id = b.id WHERE b.course_id = ?)";
    $params[] = $courseFilter;
}

if (!empty($statusFilter)) {
    if ($statusFilter === 'active') {
        $where[] = "s.id IN (SELECT student_id FROM enrollments WHERE status = 'active')";
    } elseif ($statusFilter === 'completed') {
        $where[] = "s.id IN (SELECT student_id FROM enrollments WHERE status = 'completed')";
    } elseif ($statusFilter === 'unassigned') {
        $where[] = "s.id NOT IN (SELECT student_id FROM enrollments)";
    }
}

if (!empty($dateFrom)) {
    $where[] = "DATE(s.created_at) >= ?";
    $params[] = $dateFrom;
}
if (!empty($dateTo)) {
    $where[] = "DATE(s.created_at) <= ?";
    $params[] = $dateTo;
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Total Counts: Filtered vs Grand Total
$grandTotalStudents = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM students s {$whereClause}");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRecords / $limit));

// Fetch Records with active enrollment summary
$sql = "
    SELECT s.*, 
           (SELECT COUNT(*) FROM enrollments e WHERE e.student_id = s.id) as total_enrollments,
           (SELECT c.course_name FROM enrollments e JOIN batches b ON e.batch_id = b.id JOIN courses c ON b.course_id = c.id WHERE e.student_id = s.id ORDER BY e.id DESC LIMIT 1) as latest_course,
           (SELECT b.batch_code FROM enrollments e JOIN batches b ON e.batch_id = b.id WHERE e.student_id = s.id ORDER BY e.id DESC LIMIT 1) as latest_batch,
           (SELECT e.roll_number FROM enrollments e WHERE e.student_id = s.id ORDER BY e.id DESC LIMIT 1) as latest_roll_no,
           (SELECT e.status FROM enrollments e WHERE e.student_id = s.id ORDER BY e.id DESC LIMIT 1) as latest_enr_status
    FROM students s
    {$whereClause}
    ORDER BY s.id DESC
    LIMIT " . (int)$limit . " OFFSET " . (int)$offset . "
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Track active filters for pill badges and print metadata
$activeFiltersSummary = [];
if (!empty($search)) $activeFiltersSummary['Search'] = $search;
if (!empty($rollFilter)) $activeFiltersSummary['Roll #'] = $rollFilter;
if (!empty($statusFilter)) $activeFiltersSummary['Status'] = ucfirst($statusFilter);
if (!empty($dateFrom)) $activeFiltersSummary['Admitted From'] = $dateFrom;
if (!empty($dateTo)) $activeFiltersSummary['Admitted To'] = $dateTo;


// Dropdown filter and admission options
$batchesList = $pdo->query("
    SELECT b.id, b.batch_code, b.batch_name, b.course_id, c.course_name 
    FROM batches b 
    JOIN courses c ON b.course_id = c.id 
    ORDER BY c.course_name ASC, b.batch_code ASC
")->fetchAll();

if (!function_exists('mapToCanonicalCategory')) {
    function mapToCanonicalCategory($cat) {
        switch (trim($cat)) {
            case 'Tuition Courses':
            case 'School Academics':
                return 'Tuition Courses';
            case 'Safety Courses':
            case 'Safety':
            case 'Health & Safety':
                return 'Safety Courses';
            case 'Computer Courses':
            case 'Computer / IT':
            case 'Information Technology':
                return 'Computer Courses';
            case 'English Courses':
            case 'Languages':
                return 'English Courses';
            case 'General Courses':
            case 'General':
            case 'Technical / Engineering':
            case 'Vocational':
            case 'Vocational / Technical':
            case 'Vocational & Technical':
            default:
                return 'General Courses';
        }
    }
}

// Fetch all courses with fees and duration for Academic Program Enrollment
$allCoursesRaw = $pdo->query("
    SELECT id, course_code, course_name, category, duration, standard_fee, ttb_exam_fee 
    FROM courses 
    ORDER BY course_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$coursesList = $allCoursesRaw;

$coursesCategorized = [
    'Tuition Courses' => [],
    'Safety Courses' => [],
    'Computer Courses' => [],
    'English Courses' => [],
    'General Courses' => []
];

$coursesDataJson = [];

foreach ($allCoursesRaw as $c) {
    $canon = mapToCanonicalCategory($c['category']);
    $c['canonical_category'] = $canon;
    $coursesCategorized[$canon][] = $c;
    $coursesDataJson[$c['id']] = [
        'id' => (int)$c['id'],
        'code' => $c['course_code'],
        'name' => $c['course_name'],
        'category' => $canon,
        'duration' => $c['duration'] ?: '3 Months',
        'standard_fee' => (float)$c['standard_fee'],
        'ttb_exam_fee' => (float)$c['ttb_exam_fee']
    ];
}

$pageTitle = "Students Directory";
require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($isPrintAll): ?>
    <?php
    $backParams = $_GET;
    unset($backParams['print']);
    $backUrl = 'index.php?' . http_build_query($backParams);
    renderPrintPreviewToolbar('Student Directory & Enrollment Roster', $totalRecords, $backUrl);
    ?>
    <div class="print-paper-sheet">
<?php else: ?>
    <div class="page-header no-print">
        <div>
            <h1 class="page-title">Students Directory</h1>
            <p class="page-breadcrumb">Academic Management &bull; Student profiles, registrations, program enrollments, and status tracking</p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <?php if ($canManage): ?>
                <button type="button" class="btn btn-primary btn-sm" onclick="openAddStudentModal()">
                    <span>➕ Add Student</span>
                </button>
            <?php endif; ?>
            <button type="button" class="btn btn-glass btn-sm" onclick="window.print()" title="Print currently visible table">
                <span>🖨️ Print View</span>
            </button>
            <?php
            $printAllParams = $_GET;
            $printAllParams['print'] = 'all';
            ?>
            <a href="?<?= http_build_query($printAllParams) ?>" class="btn btn-glass btn-sm" title="Preview and print complete filtered dataset (all pages)">
                <span>📄 Full Roster Print Preview</span>
            </a>
        </div>
    </div>
<?php endif; ?>

<?php if ($message): ?>
    <div class="no-print" style="padding:14px 18px; background:rgba(16,185,129,0.12); border:1px solid rgba(16,185,129,0.3); color:#6ee7b7; border-radius:10px; margin-bottom:20px;">
        ✓ <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="no-print" style="padding:14px 18px; background:rgba(244,63,94,0.12); border:1px solid rgba(244,63,94,0.3); color:#fda4af; border-radius:10px; margin-bottom:20px;">
        ⚠️ <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<!-- Smart Multi-Criteria Filters -->
<?php if (!$isPrintAll): ?>
<div class="glass-card no-print" style="padding: 16px 20px; margin-bottom: 20px;">
    <form method="GET" action="" style="margin: 0;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 12px;">
            <!-- Search Keyword -->
            <div>
                <label style="display: block; font-size: 11.5px; font-weight: 600; color: var(--text-muted); margin-bottom: 4px;">Search Record</label>
                <input type="text" name="search" class="form-control" placeholder="Name, Reg #, CNIC, Phone..." value="<?= htmlspecialchars($search) ?>" style="min-height: 38px; width: 100%;">
            </div>
            <!-- Course Filter -->
            <div>
                <label style="display: block; font-size: 11.5px; font-weight: 600; color: var(--text-muted); margin-bottom: 4px;">Program / Course</label>
                <select name="course_id" class="form-control" style="min-height: 38px; width: 100%;">
                    <option value="">All Programs / Courses</option>
                    <?php foreach ($coursesList as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $courseFilter === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['course_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Batch Filter -->
            <div>
                <label style="display: block; font-size: 11.5px; font-weight: 600; color: var(--text-muted); margin-bottom: 4px;">Academic Batch</label>
                <select name="batch_id" class="form-control" style="min-height: 38px; width: 100%;">
                    <option value="">All Batches</option>
                    <?php foreach ($batchesList as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $batchFilter === (int)$b['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['batch_code']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Status Filter -->
            <div>
                <label style="display: block; font-size: 11.5px; font-weight: 600; color: var(--text-muted); margin-bottom: 4px;">Enrollment Status</label>
                <select name="status" class="form-control" style="min-height: 38px; width: 100%;">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active Students</option>
                    <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed / Graduated</option>
                    <option value="unassigned" <?= $statusFilter === 'unassigned' ? 'selected' : '' ?>>Unassigned / No Batch</option>
                </select>
            </div>
            <!-- Roll Number Filter -->
            <div>
                <label style="display: block; font-size: 11.5px; font-weight: 600; color: var(--text-muted); margin-bottom: 4px;">Roll Number</label>
                <input type="text" name="roll_number" class="form-control" placeholder="Specific Roll #..." value="<?= htmlspecialchars($rollFilter) ?>" style="min-height: 38px; width: 100%;">
            </div>
            <!-- Date From -->
            <div>
                <label style="display: block; font-size: 11.5px; font-weight: 600; color: var(--text-muted); margin-bottom: 4px;">Admitted From</label>
                <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>" style="min-height: 38px; width: 100%;">
            </div>
            <!-- Date To -->
            <div>
                <label style="display: block; font-size: 11.5px; font-weight: 600; color: var(--text-muted); margin-bottom: 4px;">Admitted To</label>
                <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>" style="min-height: 38px; width: 100%;">
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-card); padding-top: 12px; flex-wrap: wrap; gap: 10px;">
            <div style="font-size: 12.5px; color: var(--text-muted);">
                Showing <strong><?= count($students) ?></strong> of <strong><?= number_format($totalRecords) ?></strong> matching students (<?= number_format($grandTotalStudents) ?> total in database)
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary btn-sm" style="min-height: 36px;">
                    <span>🔍 Apply Filters</span>
                </button>
                <?php if (!empty($search) || $batchFilter > 0 || $courseFilter > 0 || !empty($statusFilter) || !empty($rollFilter) || !empty($dateFrom) || !empty($dateTo)): ?>
                    <a href="<?= $baseUri ?>/modules/students/index.php" class="btn btn-glass btn-sm" style="min-height: 36px;">
                        <span>✖ Clear Filters</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Student Roster Table -->
<div class="glass-card">
    <?php
    // Institutional Print Header (Shown in browser print & print preview)
    renderPrintHeader('Student Directory & Enrollment Roster', 'Official Institutional Student Records & Academic Status', $activeFiltersSummary);
    ?>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 130px;">Reg No.</th>
                    <th style="width: 110px;">Roll No.</th>
                    <th>Student Name</th>
                    <th>Father Name</th>
                    <th>Program &amp; Batch</th>
                    <th>Contact</th>
                    <th style="width: 90px;">Status</th>
                    <th class="no-print" style="text-align:right; width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="8" class="empty-state">
                            <div class="empty-state-icon">🎓</div>
                            <div class="empty-state-title">No students found matching your criteria</div>
                            <div class="empty-state-desc">
                                <?php if (!empty($search) || $batchFilter > 0 || $courseFilter > 0 || !empty($statusFilter) || !empty($rollFilter) || !empty($dateFrom) || !empty($dateTo)): ?>
                                    Try modifying or clearing your filter criteria to see more records.
                                    <div style="margin-top: 14px;">
                                        <a href="<?= $baseUri ?>/modules/students/index.php" class="btn btn-glass btn-sm">Clear All Filters</a>
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
                                <strong style="color: var(--primary-light); font-family: monospace; font-size: 12.5px;">
                                    <?= htmlspecialchars($s['student_reg_no']) ?>
                                </strong>
                            </td>
                            <td>
                                <span style="font-family: monospace; font-size: 12px; color: var(--text-muted);">
                                    <?= htmlspecialchars($s['latest_roll_no'] ?: '—') ?>
                                </span>
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
                                    <div style="font-weight: 500;"><?= htmlspecialchars($s['latest_course']) ?></div>
                                    <div style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($s['latest_batch']) ?></div>
                                <?php else: ?>
                                    <span style="color: var(--text-dim); font-size: 12px;">Not Enrolled</span>
                                <?php endif; ?>
                            </td>
                            <td style="color: var(--text-muted); font-size: 12px;"><?= htmlspecialchars($s['contact_number'] ?: '—') ?></td>
                            <td>
                                <?php 
                                $enrStatus = strtolower($s['latest_enr_status'] ?? '');
                                $badgeClass = ($enrStatus === 'active') ? 'badge-active' : (($enrStatus === 'completed') ? 'badge-info' : 'badge-inactive');
                                $statusLabel = $enrStatus ? ucfirst($enrStatus) : 'Unassigned';
                                ?>
                                <span class="status-badge <?= $badgeClass ?>">
                                    <?= htmlspecialchars($statusLabel) ?>
                                </span>
                            </td>
                            <td class="no-print" style="text-align:right;">
                                <div style="display: flex; gap: 6px; justify-content: flex-end;">
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

    <?php
    // Institutional Signatures Block
    renderPrintSignatures(['Prepared By (Registrar)', 'Verified By (Academic Head)', 'Principal / Director ICT']);
    ?>

    <!-- Pagination (Preserves every active filter query param) -->
    <?php if ($totalPages > 1 && !$isPrintAll): ?>
        <?php
        $basePaginationParams = $_GET;
        ?>
        <div class="pagination-wrapper no-print">
            <div>
                Showing <strong><?= min($totalRecords, $offset + 1) ?>–<?= min($totalRecords, $offset + count($students)) ?></strong> of <strong><?= number_format($totalRecords) ?></strong> students
            </div>
            <div class="pagination-links">
                <?php if ($page > 1): 
                    $prevParams = $basePaginationParams;
                    $prevParams['page'] = $page - 1;
                ?>
                    <a href="?<?= http_build_query($prevParams) ?>" class="page-btn">←</a>
                <?php endif; ?>

                <?php 
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                for ($p = $startPage; $p <= $endPage; $p++): 
                    $pageParams = $basePaginationParams;
                    $pageParams['page'] = $p;
                ?>
                    <a href="?<?= http_build_query($pageParams) ?>" class="page-btn <?= $p === $page ? 'active' : '' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): 
                    $nextParams = $basePaginationParams;
                    $nextParams['page'] = $page + 1;
                ?>
                    <a href="?<?= http_build_query($nextParams) ?>" class="page-btn">→</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($isPrintAll): ?>
    </div><!-- End .print-paper-sheet -->
<?php endif; ?>
</div>

<!-- Modal: Add Student (Human-Centered Structured Form with Academic Program Enrollment) -->
<div class="modal-overlay" id="addStudentModal">
    <div class="modal-dialog" style="max-width: 820px; width: 95%;">
        <div class="modal-header">
            <div>
                <h3 class="modal-title" style="margin-bottom: 2px;">Student Admission &amp; Program Enrollment</h3>
                <p style="font-size: 12px; color: var(--text-muted); margin: 0;">Register student profile and enroll into academic program</p>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeModal('addStudentModal')">&times;</button>
        </div>
        <form method="POST" action="" id="addStudentForm">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-body" style="padding: 20px 24px;">
                <!-- Stepped Navigation Tabs -->
                <div class="form-step-nav" id="admissionStepNav">
                    <button type="button" class="form-step-btn active" id="btnStep1" onclick="switchAdmissionStep(1)">
                        <span class="form-step-num">1</span>
                        <span>👤 Personal Information</span>
                    </button>
                    <button type="button" class="form-step-btn" id="btnStep2" onclick="switchAdmissionStep(2)">
                        <span class="form-step-num">2</span>
                        <span>📚 Academic Program Enrollment</span>
                    </button>
                </div>

                <!-- Step 1: Personal Information -->
                <div id="step1Pane" class="step-pane active">
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

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label class="form-label" for="add_reg_no">Registration No. (Optional)</label>
                        <input type="text" id="add_reg_no" name="student_reg_no" class="form-control" placeholder="Leave blank to auto-generate (e.g. ICT-STD-XXXX)">
                    </div>

                    <div style="display: flex; justify-content: flex-end;">
                        <button type="button" class="btn btn-primary" onclick="switchAdmissionStep(2)" style="display: inline-flex; align-items: center; gap: 8px;">
                            <span>Next: 📚 Academic Program Enrollment</span>
                            <span>&rarr;</span>
                        </button>
                    </div>
                </div>

                <!-- Step 2: Academic Program Enrollment -->
                <div id="step2Pane" class="step-pane" style="display: none;">
                    <div style="background: rgba(56, 189, 248, 0.04); border: 1px solid rgba(56, 189, 248, 0.15); border-radius: 12px; padding: 14px 18px; margin-bottom: 18px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                            <div style="font-size: 13.5px; font-weight: 700; color: #38bdf8; display: flex; align-items: center; gap: 8px;">
                                <span>📚</span> Academic Program Enrollment Details
                            </div>
                            <span style="font-size: 11.5px; color: var(--text-muted);">Select program, class, session &amp; verify fee structure</span>
                        </div>
                    </div>

                    <!-- Category & Course Selection Grid -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="enr_category">
                                Program Category <span class="required">*</span>
                            </label>
                            <select id="enr_category" name="program_category" class="form-control" onchange="onProgramCategoryChange(this.value)">
                                <option value="">-- Select Program Category --</option>
                                <option value="Tuition Courses">📚 Tuition Courses</option>
                                <option value="Safety Courses">🦺 Safety Courses</option>
                                <option value="Computer Courses">💻 Computer Courses</option>
                                <option value="English Courses">🌐 English Courses</option>
                                <option value="General Courses">🎯 General Courses</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="enr_course_id">
                                Course / Academic Program <span class="required">*</span>
                            </label>
                            <select id="enr_course_id" name="course_id" class="form-control" onchange="onCourseSelected(this.value)">
                                <option value="">-- Select Category First --</option>
                            </select>
                        </div>
                    </div>

                    <!-- Dynamic Course Preview Banner with Duration -->
                    <div id="programPreviewBox" class="program-preview-box" style="display: none;">
                        <div>
                            <div style="font-size: 14px; font-weight: 700; color: #f8fafc;" id="prevCourseName">Course Name</div>
                            <div style="font-size: 12px; color: var(--text-muted);" id="prevCourseMeta">Code: — &bull; Category: —</div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span id="prevDurationPill" class="program-duration-pill">⏱️ Duration: —</span>
                        </div>
                    </div>

                    <!-- Academic Session & Class/Level -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="enr_academic_session">Academic Session / Year <span class="required">*</span></label>
                            <select id="enr_academic_session" name="academic_session" class="form-control">
                                <option value="Session 2026-2027" selected>Session 2026-2027 (Current)</option>
                                <option value="Annual 2026">Annual 2026</option>
                                <option value="Fall 2026">Fall 2026</option>
                                <option value="Spring 2026">Spring 2026</option>
                                <option value="Summer 2026">Summer 2026</option>
                                <option value="Session 2025-2026">Session 2025-2026</option>
                                <option value="Session 2027-2028">Session 2027-2028</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="enr_class_level">Class / Level <span class="required">*</span></label>
                            <select id="enr_class_level" name="class_level" class="form-control" onchange="onClassLevelChange(this.value)">
                                <option value="">-- Select Class / Level --</option>
                            </select>
                            <input type="text" id="custom_class_level" name="custom_class_level" class="form-control" placeholder="Specify custom class or level..." style="display: none; margin-top: 8px;">
                        </div>
                    </div>

                    <!-- Enrollment Date & Roll Number -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="enr_date">Enrollment Date</label>
                            <input type="date" id="enr_date" name="enrollment_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="enr_roll_no">Class Roll Number</label>
                            <input type="text" id="enr_roll_no" name="roll_number" class="form-control" placeholder="e.g. 01 or ICT-26">
                        </div>
                    </div>

                    <!-- Fee Structure Interactive Card -->
                    <div class="fee-structure-card">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 8px;">
                            <div style="font-size: 13px; font-weight: 700; color: #f8fafc; display: flex; align-items: center; gap: 6px;">
                                <span>💰</span> Program Fee Structure (PKR)
                            </div>
                            <span style="font-size: 11px; color: #94a3b8;">Auto-loaded from program defaults &bull; Editable</span>
                        </div>

                        <div class="fee-calc-grid">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label" style="font-size: 11px;" for="fee_admission">Admission Fee</label>
                                <input type="number" step="100" id="fee_admission" name="admission_fee" class="form-control" value="1000" oninput="calculateTotalFee()">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label" style="font-size: 11px;" for="fee_tuition">Tuition Fee</label>
                                <input type="number" step="100" id="fee_tuition" name="tuition_fee" class="form-control" value="0" oninput="calculateTotalFee()">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label" style="font-size: 11px;" for="fee_exam">Exam / Charges</label>
                                <input type="number" step="100" id="fee_exam" name="exam_fee" class="form-control" value="0" oninput="calculateTotalFee()">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label" style="font-size: 11px;" for="fee_discount">Discount / Concession</label>
                                <input type="number" step="100" id="fee_discount" name="discount_amount" class="form-control" value="0" oninput="calculateTotalFee()">
                            </div>
                        </div>

                        <div class="fee-total-highlight">
                            <div class="fee-total-label">
                                <span>💵</span> Total Agreed Fee (PKR):
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="color: #94a3b8; font-size: 14px; font-weight: 600;">Rs.</span>
                                <input type="number" step="100" id="enr_total_agreed_fee" name="total_agreed_fee" class="form-control" style="width: 150px; font-size: 18px; font-weight: 800; color: #34d399; text-align: right; background: rgba(15,23,42,0.8); border-color: rgba(52,211,153,0.4);">
                            </div>
                        </div>
                    </div>

                    <!-- Enrollment Status & Receipt Slip -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="enr_status">Enrollment Status <span class="required">*</span></label>
                            <select id="enr_status" name="enrollment_status" class="form-control">
                                <option value="active" selected>🟢 Active (Currently Enrolled)</option>
                                <option value="pending">🟡 Pending (Awaiting Clearance)</option>
                                <option value="completed">🔵 Completed (Program Finished)</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="enr_slip_no">Receipt / Slip Voucher No.</label>
                            <input type="text" id="enr_slip_no" name="receipt_slip_no" class="form-control" placeholder="e.g. REC-1024 or Bank Slip #">
                        </div>
                    </div>

                    <div style="margin-top: 18px; display: flex; justify-content: flex-start;">
                        <button type="button" class="btn btn-glass btn-sm" onclick="switchAdmissionStep(1)">
                            &larr; Back to Personal Info
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-glass" onclick="closeModal('addStudentModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="padding: 10px 22px; font-weight: 700;">Complete Student Admission &amp; Enrollment</button>
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
const COURSES_DATA = <?= json_encode($coursesDataJson) ?>;
const COURSES_BY_CATEGORY = <?= json_encode($coursesCategorized) ?>;

const CLASS_LEVELS = {
    'Tuition Courses': [
        'Grade 9 (Matric Part 1)',
        'Grade 10 (Matric Part 2)',
        '1st Year / 11th (FSc Pre-Medical / Pre-Eng / ICS)',
        '2nd Year / 12th (FSc Pre-Medical / Pre-Eng / ICS)',
        'Middle Section (Grades 6 - 8)',
        'Junior Section (Grades 1 - 5)'
    ],
    'Safety Courses': [
        'Level 1 - Occupational Safety & Health Awareness',
        'Level 2 - Site Safety Officer & Supervisor',
        'Level 3 - International Diploma (NEBOSH / IOSH / OSHA)',
        'Executive / Advanced Industrial Safety'
    ],
    'Computer Courses': [
        'DIT (Diploma in Information Technology - 1 Year)',
        'CIT (Certificate in Information Technology - 6 Months)',
        'Graphic Design & UI/UX Diploma',
        'Web Development & Coding (Full Stack)',
        'Office Automation & Professional Applications',
        'Advanced IT / Networking Specialization'
    ],
    'English Courses': [
        'Beginner / Elementary (A1 - A2)',
        'Intermediate Spoken & Fluency (B1 - B2)',
        'Advanced English Proficiency (C1)',
        'IELTS Academic Preparation',
        'IELTS General Training',
        'English Grammar & Writing Diploma'
    ],
    'General Courses': [
        'Level 1 - Basic Trade Apprenticeship',
        'Level 2 - Certified Technician / Artisan',
        'Level 3 - Advanced Vocational Diploma',
        'Specialized Short Skill Program (1-3 Months)'
    ]
};

function switchAdmissionStep(step) {
    const pane1 = document.getElementById('step1Pane');
    const pane2 = document.getElementById('step2Pane');
    const btn1 = document.getElementById('btnStep1');
    const btn2 = document.getElementById('btnStep2');

    if (!pane1 || !pane2 || !btn1 || !btn2) return;

    if (step === 1) {
        pane1.style.display = 'block';
        pane2.style.display = 'none';
        btn1.classList.add('active');
        btn2.classList.remove('active');
    } else {
        // Validate student full name before proceeding to step 2
        const nameInput = document.getElementById('add_full_name');
        if (nameInput && !nameInput.value.trim()) {
            nameInput.focus();
            nameInput.reportValidity();
            return;
        }
        pane1.style.display = 'none';
        pane2.style.display = 'block';
        btn1.classList.remove('active');
        btn2.classList.add('active');
    }
}

function onProgramCategoryChange(category) {
    const courseSelect = document.getElementById('enr_course_id');
    const classSelect = document.getElementById('enr_class_level');
    const customClassInput = document.getElementById('custom_class_level');
    const previewBox = document.getElementById('programPreviewBox');

    if (!courseSelect || !classSelect) return;

    courseSelect.innerHTML = '<option value="">-- Select a Course / Program --</option>';
    classSelect.innerHTML = '<option value="">-- Select Class / Level --</option>';
    if (customClassInput) {
        customClassInput.style.display = 'none';
        customClassInput.value = '';
    }

    if (!category || !COURSES_BY_CATEGORY[category]) {
        if (previewBox) previewBox.style.display = 'none';
        resetFeeInputs();
        return;
    }

    // Populate courses
    const courses = COURSES_BY_CATEGORY[category];
    courses.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = `${c.course_name} (${c.course_code})`;
        courseSelect.appendChild(opt);
    });

    // Populate class levels
    const levels = CLASS_LEVELS[category] || [];
    levels.forEach(lvl => {
        const opt = document.createElement('option');
        opt.value = lvl;
        opt.textContent = lvl;
        classSelect.appendChild(opt);
    });
    const otherOpt = document.createElement('option');
    otherOpt.value = 'Other';
    otherOpt.textContent = '✏️ Custom / Other Level...';
    classSelect.appendChild(otherOpt);

    // Auto-select first class level if available
    if (levels.length > 0) {
        classSelect.value = levels[0];
    }

    if (previewBox) previewBox.style.display = 'none';
    resetFeeInputs();
}

function onCourseSelected(courseId) {
    const previewBox = document.getElementById('programPreviewBox');
    const prevCourseName = document.getElementById('prevCourseName');
    const prevCourseMeta = document.getElementById('prevCourseMeta');
    const prevDurationPill = document.getElementById('prevDurationPill');

    const course = COURSES_DATA[courseId];
    if (!course) {
        if (previewBox) previewBox.style.display = 'none';
        resetFeeInputs();
        return;
    }

    // Update preview banner
    if (prevCourseName) prevCourseName.textContent = course.name;
    if (prevCourseMeta) prevCourseMeta.textContent = `Code: ${course.code} • Category: ${course.category}`;
    if (prevDurationPill) prevDurationPill.textContent = `⏱️ Duration: ${course.duration || '3 Months'}`;
    if (previewBox) previewBox.style.display = 'flex';

    // Auto-load fee structure
    const tuiInput = document.getElementById('fee_tuition');
    const examInput = document.getElementById('fee_exam');
    const admInput = document.getElementById('fee_admission');

    if (tuiInput) tuiInput.value = course.standard_fee || 0;
    if (examInput) examInput.value = course.ttb_exam_fee || 0;
    if (admInput && (!admInput.value || admInput.value == '0')) {
        admInput.value = 1000;
    }
    calculateTotalFee();
}

function calculateTotalFee() {
    const adm = parseFloat(document.getElementById('fee_admission')?.value) || 0;
    const tui = parseFloat(document.getElementById('fee_tuition')?.value) || 0;
    const exam = parseFloat(document.getElementById('fee_exam')?.value) || 0;
    const disc = parseFloat(document.getElementById('fee_discount')?.value) || 0;

    const total = Math.max(0, (adm + tui + exam) - disc);
    const totalInput = document.getElementById('enr_total_agreed_fee');
    if (totalInput) {
        totalInput.value = total;
    }
}

function resetFeeInputs() {
    const tui = document.getElementById('fee_tuition');
    const exam = document.getElementById('fee_exam');
    const disc = document.getElementById('fee_discount');
    if (tui) tui.value = 0;
    if (exam) exam.value = 0;
    if (disc) disc.value = 0;
    calculateTotalFee();
}

function onClassLevelChange(val) {
    const customInput = document.getElementById('custom_class_level');
    if (!customInput) return;
    if (val === 'Other') {
        customInput.style.display = 'block';
        customInput.focus();
    } else {
        customInput.style.display = 'none';
        customInput.value = '';
    }
}

function openAddStudentModal() {
    switchAdmissionStep(1);
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

window.switchAdmissionStep = switchAdmissionStep;
window.onProgramCategoryChange = onProgramCategoryChange;
window.onCourseSelected = onCourseSelected;
window.calculateTotalFee = calculateTotalFee;
window.resetFeeInputs = resetFeeInputs;
window.onClassLevelChange = onClassLevelChange;
window.openAddStudentModal = openAddStudentModal;
window.openEditStudentModal = openEditStudentModal;
window.confirmDeleteStudent = confirmDeleteStudent;

document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('new') === '1') {
        openAddStudentModal();
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
