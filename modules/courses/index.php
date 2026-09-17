<?php
/**
 * Course Catalog Management Module
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
            $error = "Insufficient permissions to add courses.";
        } else {
            $code    = strtoupper(trim($_POST['course_code'] ?? ''));
            $name    = trim($_POST['course_name'] ?? '');
            $deptId  = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
            $cat     = trim($_POST['category'] ?? 'Information Technology');
            $dur     = trim($_POST['duration'] ?? '1 Year');
            $fee     = (float)($_POST['standard_fee'] ?? 0);
            $ttbFee  = (float)($_POST['ttb_exam_fee'] ?? 0);
            $desc    = trim($_POST['description'] ?? '');

            if (empty($code) || empty($name)) {
                $error = "Course code and course name are required.";
            } else {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO courses (course_code, course_name, department_id, category, duration, standard_fee, ttb_exam_fee, description)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$code, $name, $deptId, $cat, $dur, $fee, $ttbFee, $desc]);
                    $message = "Course '{$name}' created successfully.";
                } catch (PDOException $e) {
                    error_log("Create course error: " . $e->getMessage());
                    $error = ($e->getCode() == 23000) ? "Course code already exists." : "An error occurred while creating the course.";
                }
            }
        }
    } elseif ($action === 'update') {
        if (!$canManage) {
            $error = "Insufficient permissions to modify courses.";
        } else {
            $id      = (int)($_POST['id'] ?? 0);
            $name    = trim($_POST['course_name'] ?? '');
            $deptId  = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
            $cat     = trim($_POST['category'] ?? 'Information Technology');
            $dur     = trim($_POST['duration'] ?? '1 Year');
            $fee     = (float)($_POST['standard_fee'] ?? 0);
            $ttbFee  = (float)($_POST['ttb_exam_fee'] ?? 0);
            $desc    = trim($_POST['description'] ?? '');

            if ($id <= 0 || empty($name)) {
                $error = "Course ID and name are required.";
            } else {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE courses 
                        SET course_name = ?, department_id = ?, category = ?, duration = ?, standard_fee = ?, ttb_exam_fee = ?, description = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $deptId, $cat, $dur, $fee, $ttbFee, $desc, $id]);
                    $message = "Course updated successfully.";
                } catch (PDOException $e) {
                    error_log("Update course error: " . $e->getMessage());
                    $error = "An error occurred while updating the course.";
                }
            }
        }
    } elseif ($action === 'delete') {
        if (!$canManage) {
            $error = "Insufficient permissions to delete courses.";
        } else {
            $id = (int)($_POST['id'] ?? 0);
            try {
                $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Course deleted successfully.";
            } catch (PDOException $e) {
                error_log("Delete course error: " . $e->getMessage());
                $error = "Cannot delete course: active batches are assigned to this course.";
            }
        }
    }
}

// Search & Filter
$search = trim($_GET['search'] ?? '');
$catFilter = trim($_GET['category'] ?? '');

$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(c.course_name LIKE ? OR c.course_code LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term]);
}

if (!empty($catFilter)) {
    $where[] = "c.category = ?";
    $params[] = $catFilter;
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Fetch Courses with batch and enrollment count
$sql = "
    SELECT c.*, d.dept_name,
           (SELECT COUNT(*) FROM batches b WHERE b.course_id = c.id) as total_batches,
           (SELECT COUNT(*) FROM enrollments e JOIN batches b ON e.batch_id = b.id WHERE b.course_id = c.id) as total_students
    FROM courses c
    LEFT JOIN departments d ON c.department_id = d.id
    {$whereClause}
    ORDER BY c.category ASC, c.course_name ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$courses = $stmt->fetchAll();

// Categories for filter
$categoriesList = $pdo->query("SELECT DISTINCT category FROM courses ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);
$departmentsList = $pdo->query("SELECT id, dept_name FROM departments ORDER BY dept_name ASC")->fetchAll();

$pageTitle = "Courses Master Catalog";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Courses Master Catalog</h1>
        <p class="page-breadcrumb">Manage academic programs, certifications, standard tuition fees, and TTB board exam fees</p>
    </div>
    <?php if ($canManage): ?>
    <div>
        <button type="button" class="btn btn-primary" onclick="openModal('addCourseModal')">
            <span>+ Add New Course</span>
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
            <input type="text" name="search" class="form-control" placeholder="Search by course name or code..." value="<?= htmlspecialchars($search) ?>" style="min-height: 40px;">
        </div>
        <div class="filter-group">
            <select name="category" class="form-control" style="min-height: 40px; min-width: 200px;">
                <option value="">All Academic Disciplines</option>
                <?php foreach ($categoriesList as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>" <?= $catFilter === $cat ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-glass" style="min-height: 40px;">Filter</button>
            <?php if (!empty($search) || !empty($catFilter)): ?>
                <a href="<?= $baseUri ?>/modules/courses/index.php" class="btn btn-glass" style="min-height: 40px;">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Courses Table -->
<div class="glass-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Course Code</th>
                    <th>Course Title</th>
                    <th>Category</th>
                    <th>Duration</th>
                    <th>Standard Fee</th>
                    <th>TTB Board Fee</th>
                    <th>Batches / Enrolled</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($courses)): ?>
                    <tr>
                        <td colspan="8" class="empty-state">
                            <div class="empty-state-icon">📚</div>
                            <div class="empty-state-title">No courses found</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($courses as $c): ?>
                        <tr>
                            <td>
                                <strong style="color: var(--primary-light); font-family: monospace;">
                                    <?= htmlspecialchars($c['course_code']) ?>
                                </strong>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($c['course_name']) ?></strong>
                                <?php if ($c['dept_name']): ?>
                                    <div style="font-size: 11px; color: var(--text-dim);"><?= htmlspecialchars($c['dept_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><span style="font-size: 13px; color: var(--text-secondary);"><?= htmlspecialchars($c['category']) ?></span></td>
                            <td><?= htmlspecialchars($c['duration'] ?: '—') ?></td>
                            <td>Rs. <?= number_format((float)$c['standard_fee'], 0) ?></td>
                            <td>Rs. <?= number_format((float)$c['ttb_exam_fee'], 0) ?></td>
                            <td>
                                <span class="status-badge badge-active">
                                    <?= (int)$c['total_batches'] ?> Batches • <?= (int)$c['total_students'] ?> Students
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <?php if ($canManage): ?>
                                    <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                        <button type="button" class="btn btn-glass btn-sm" onclick="openEditCourseModal(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)">Edit</button>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="confirmDeleteCourse(<?= $c['id'] ?>, '<?= htmlspecialchars($c['course_name'], ENT_QUOTES) ?>')">Delete</button>
                                    </div>
                                <?php else: ?>
                                    <span style="color: var(--text-dim); font-size: 12px;">View Only</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add Course -->
<div class="modal-overlay" id="addCourseModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Create Academic Program</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('addCourseModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="add_course_code">Course Code <span class="required">*</span></label>
                        <input type="text" id="add_course_code" name="course_code" class="form-control" required placeholder="e.g. DIT, GD, CIT" style="text-transform:uppercase;">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_course_name">Course Title <span class="required">*</span></label>
                        <input type="text" id="add_course_name" name="course_name" class="form-control" required placeholder="e.g. Diploma in Information Technology">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="add_category">Category / Discipline</label>
                        <select id="add_category" name="category" class="form-control">
                            <option value="Information Technology">Information Technology</option>
                            <option value="Technical & Vocational">Technical & Vocational</option>
                            <option value="Medical & Health">Medical & Health</option>
                            <option value="Management & Commerce">Management & Commerce</option>
                            <option value="Language & Literacy">Language & Literacy</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_dept_id">Department</label>
                        <select id="add_dept_id" name="department_id" class="form-control">
                            <option value="">None / Independent</option>
                            <?php foreach ($departmentsList as $dp): ?>
                                <option value="<?= $dp['id'] ?>"><?= htmlspecialchars($dp['dept_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="add_duration">Duration</label>
                        <input type="text" id="add_duration" name="duration" class="form-control" placeholder="e.g. 1 Year">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_fee">Standard Fee (PKR)</label>
                        <input type="number" id="add_fee" name="standard_fee" class="form-control" step="100" placeholder="e.g. 15000">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_ttb_fee">TTB Exam Fee</label>
                        <input type="number" id="add_ttb_fee" name="ttb_exam_fee" class="form-control" step="100" placeholder="e.g. 2500">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="add_desc">Description / Syllabus Overview</label>
                    <textarea id="add_desc" name="description" class="form-control" placeholder="Outline of curriculum..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-glass" onclick="closeModal('addCourseModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Course</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Course -->
<div class="modal-overlay" id="editCourseModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Edit Course Record</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('editCourseModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_course_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Course Code</label>
                    <input type="text" id="edit_course_code" class="form-control" disabled style="opacity:0.7;">
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_course_name">Course Title <span class="required">*</span></label>
                    <input type="text" id="edit_course_name" name="course_name" class="form-control" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="edit_category">Category</label>
                        <select id="edit_category" name="category" class="form-control">
                            <option value="Information Technology">Information Technology</option>
                            <option value="Technical & Vocational">Technical & Vocational</option>
                            <option value="Medical & Health">Medical & Health</option>
                            <option value="Management & Commerce">Management & Commerce</option>
                            <option value="Language & Literacy">Language & Literacy</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_dept_id">Department</label>
                        <select id="edit_dept_id" name="department_id" class="form-control">
                            <option value="">None / Independent</option>
                            <?php foreach ($departmentsList as $dp): ?>
                                <option value="<?= $dp['id'] ?>"><?= htmlspecialchars($dp['dept_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="edit_duration">Duration</label>
                        <input type="text" id="edit_duration" name="duration" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_fee">Standard Fee</label>
                        <input type="number" id="edit_fee" name="standard_fee" class="form-control" step="100">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_ttb_fee">TTB Exam Fee</label>
                        <input type="number" id="edit_ttb_fee" name="ttb_exam_fee" class="form-control" step="100">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_desc">Description</label>
                    <textarea id="edit_desc" name="description" class="form-control"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-glass" onclick="closeModal('editCourseModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Course</button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden Delete Form with CSRF -->
<form id="delete-course-form" method="POST" action="" style="display:none;">
    <?= getCsrfField() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_course_id">
</form>

<script>
function openEditCourseModal(course) {
    document.getElementById('edit_course_id').value = course.id;
    document.getElementById('edit_course_code').value = course.course_code || '';
    document.getElementById('edit_course_name').value = course.course_name || '';
    document.getElementById('edit_category').value = course.category || 'Information Technology';
    document.getElementById('edit_dept_id').value = course.department_id || '';
    document.getElementById('edit_duration').value = course.duration || '';
    document.getElementById('edit_fee').value = course.standard_fee || 0;
    document.getElementById('edit_ttb_fee').value = course.ttb_exam_fee || 0;
    document.getElementById('edit_desc').value = course.description || '';
    openModal('editCourseModal');
}

function confirmDeleteCourse(id, name) {
    confirmAction(
        'Delete Academic Course?',
        `Are you sure you want to delete '${name}'? Batches and students linked to this course will be affected.`,
        () => {
            document.getElementById('delete_course_id').value = id;
            document.getElementById('delete-course-form').submit();
        }
    );
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
