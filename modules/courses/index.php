<?php
/**
 * Courses & Subjects Catalog Management Module
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

$primaryCategories = [
    'Tuition Courses',
    'Safety Courses',
    'Computer Courses',
    'English Courses',
    'General Courses'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfRequest();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        if (!$canManage) {
            $error = "Insufficient permissions to add subjects.";
        } else {
            $code    = strtoupper(trim($_POST['course_code'] ?? ''));
            $name    = trim($_POST['course_name'] ?? '');
            $deptId  = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
            $cat     = trim($_POST['category'] ?? 'General Courses');
            $dur     = trim($_POST['duration'] ?? '1 Year');
            $fee     = (float)($_POST['standard_fee'] ?? 0);
            $ttbFee  = (float)($_POST['ttb_exam_fee'] ?? 0);
            $desc    = trim($_POST['description'] ?? '');

            if (empty($code) || empty($name)) {
                $error = "Subject code and subject title are required.";
            } else {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO courses (course_code, course_name, department_id, category, duration, standard_fee, ttb_exam_fee, description)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$code, $name, $deptId, $cat, $dur, $fee, $ttbFee, $desc]);
                    $message = "Subject '{$name}' created successfully under category '{$cat}'.";
                } catch (PDOException $e) {
                    error_log("Create course error: " . $e->getMessage());
                    $error = ($e->getCode() == 23000) ? "Subject code already exists." : "An error occurred while creating the subject.";
                }
            }
        }
    } elseif ($action === 'update') {
        if (!$canManage) {
            $error = "Insufficient permissions to modify subjects.";
        } else {
            $id      = (int)($_POST['id'] ?? 0);
            $name    = trim($_POST['course_name'] ?? '');
            $deptId  = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
            $cat     = trim($_POST['category'] ?? 'General Courses');
            $dur     = trim($_POST['duration'] ?? '1 Year');
            $fee     = (float)($_POST['standard_fee'] ?? 0);
            $ttbFee  = (float)($_POST['ttb_exam_fee'] ?? 0);
            $desc    = trim($_POST['description'] ?? '');

            if ($id <= 0 || empty($name)) {
                $error = "Subject ID and title are required.";
            } else {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE courses 
                        SET course_name = ?, department_id = ?, category = ?, duration = ?, standard_fee = ?, ttb_exam_fee = ?, description = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $deptId, $cat, $dur, $fee, $ttbFee, $desc, $id]);
                    $message = "Subject updated successfully.";
                } catch (PDOException $e) {
                    error_log("Update course error: " . $e->getMessage());
                    $error = "An error occurred while updating the subject.";
                }
            }
        }
    } elseif ($action === 'delete') {
        if (!$canManage) {
            $error = "Insufficient permissions to delete subjects.";
        } else {
            $id = (int)($_POST['id'] ?? 0);
            try {
                $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Subject deleted successfully.";
            } catch (PDOException $e) {
                error_log("Delete course error: " . $e->getMessage());
                $error = "Cannot delete subject: active batches or student enrollments are assigned to this subject.";
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
    // Intelligent category matching for primary categories and legacy mappings
    if ($catFilter === 'Tuition Courses') {
        $where[] = "(c.category = 'Tuition Courses' OR c.category = 'School Academics')";
    } elseif ($catFilter === 'Safety Courses') {
        $where[] = "(c.category = 'Safety Courses' OR c.category IN ('Safety', 'Health & Safety'))";
    } elseif ($catFilter === 'Computer Courses') {
        $where[] = "(c.category = 'Computer Courses' OR c.category IN ('Computer / IT', 'Information Technology'))";
    } elseif ($catFilter === 'English Courses') {
        $where[] = "(c.category = 'English Courses' OR c.category = 'Languages')";
    } elseif ($catFilter === 'General Courses') {
        $where[] = "(c.category = 'General Courses' OR c.category IN ('General', 'Technical / Engineering', 'Vocational', 'Vocational / Technical', 'Vocational & Technical'))";
    } else {
        $where[] = "c.category = ?";
        $params[] = $catFilter;
    }
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

// Categories for filter (distinct existing in DB)
$dbCategories = $pdo->query("SELECT DISTINCT category FROM courses WHERE category IS NOT NULL AND category != '' ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);
$otherCategories = array_diff($dbCategories, $primaryCategories);
$departmentsList = $pdo->query("SELECT id, dept_name FROM departments ORDER BY dept_name ASC")->fetchAll();

$pageTitle = "Courses & Subjects Master Catalog";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Courses &amp; Subjects Catalog</h1>
        <p class="page-breadcrumb">Manage academic programs, subject curricula, standard tuition fees, and TTB board exam fees</p>
    </div>
    <?php if ($canManage): ?>
    <!-- "Add Subject" Action with Interactive Dropdown Menu -->
    <div class="dropdown-container" id="addSubjectDropdownContainer">
        <button type="button" class="btn btn-primary dropdown-trigger" id="addSubjectBtn" aria-expanded="false" aria-haspopup="true" title="Click to select course category and add a subject">
            <span>➕ Add Subject</span>
            <svg class="dropdown-chevron" viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
        </button>
        <div class="dropdown-menu-custom" id="addSubjectMenu" role="menu" aria-labelledby="addSubjectBtn">
            <div class="dropdown-menu-header">
                <span>Select Course Category</span>
                <span style="font-size: 10px; color: var(--primary-light); font-weight: 700;">5 CATEGORIES</span>
            </div>
            
            <!-- 1. Tuition Courses -->
            <button type="button" class="dropdown-category-item" onclick="selectCourseCategory('Tuition Courses')" role="menuitem">
                <span class="dropdown-category-icon" style="background: rgba(14, 165, 233, 0.15); color: #0ea5e9;">📘</span>
                <div class="dropdown-category-text">
                    <div class="dropdown-category-title">Tuition Courses</div>
                    <div class="dropdown-category-desc">Grades 4–12, School Coaching &amp; Academic Subjects</div>
                </div>
                <span class="dropdown-category-arrow">&rarr;</span>
            </button>
            
            <!-- 2. Safety Courses -->
            <button type="button" class="dropdown-category-item" onclick="selectCourseCategory('Safety Courses')" role="menuitem">
                <span class="dropdown-category-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">🛡️</span>
                <div class="dropdown-category-text">
                    <div class="dropdown-category-title">Safety Courses</div>
                    <div class="dropdown-category-desc">OSHA, IOSH, NEBOSH, HSE &amp; Certifications</div>
                </div>
                <span class="dropdown-category-arrow">&rarr;</span>
            </button>
            
            <!-- 3. Computer Courses -->
            <button type="button" class="dropdown-category-item" onclick="selectCourseCategory('Computer Courses')" role="menuitem">
                <span class="dropdown-category-icon" style="background: rgba(99, 102, 241, 0.15); color: #818cf8;">💻</span>
                <div class="dropdown-category-text">
                    <div class="dropdown-category-title">Computer Courses</div>
                    <div class="dropdown-category-desc">DIT, CIT, Graphic Design, Web Dev &amp; AI</div>
                </div>
                <span class="dropdown-category-arrow">&rarr;</span>
            </button>
            
            <!-- 4. English Courses -->
            <button type="button" class="dropdown-category-item" onclick="selectCourseCategory('English Courses')" role="menuitem">
                <span class="dropdown-category-icon" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">🔤</span>
                <div class="dropdown-category-text">
                    <div class="dropdown-category-title">English Courses</div>
                    <div class="dropdown-category-desc">ELL, Spoken English, IELTS, TOEFL &amp; PTE</div>
                </div>
                <span class="dropdown-category-arrow">&rarr;</span>
            </button>
            
            <!-- 5. General Courses -->
            <button type="button" class="dropdown-category-item" onclick="selectCourseCategory('General Courses')" role="menuitem">
                <span class="dropdown-category-icon" style="background: rgba(236, 72, 153, 0.15); color: #f43f5e;">🌐</span>
                <div class="dropdown-category-text">
                    <div class="dropdown-category-title">General Courses</div>
                    <div class="dropdown-category-desc">Vocational Trades, Technical &amp; Short Courses</div>
                </div>
                <span class="dropdown-category-arrow">&rarr;</span>
            </button>
        </div>
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
            <input type="text" name="search" class="form-control" placeholder="Search by subject name or code..." value="<?= htmlspecialchars($search) ?>" style="min-height: 40px;">
        </div>
        <div class="filter-group">
            <select name="category" class="form-control" style="min-height: 40px; min-width: 220px;">
                <option value="">All Course Categories</option>
                <optgroup label="Core Course Categories">
                    <?php foreach ($primaryCategories as $pCat): ?>
                        <option value="<?= htmlspecialchars($pCat) ?>" <?= $catFilter === $pCat ? 'selected' : '' ?>>
                            <?= htmlspecialchars($pCat) ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
                <?php if (!empty($otherCategories)): ?>
                <optgroup label="Other Categories in Database">
                    <?php foreach ($otherCategories as $oCat): ?>
                        <option value="<?= htmlspecialchars($oCat) ?>" <?= $catFilter === $oCat ? 'selected' : '' ?>>
                            <?= htmlspecialchars($oCat) ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
                <?php endif; ?>
            </select>
            <button type="submit" class="btn btn-glass" style="min-height: 40px;">Filter</button>
            <?php if (!empty($search) || !empty($catFilter)): ?>
                <a href="<?= $baseUri ?>/modules/courses/index.php" class="btn btn-glass" style="min-height: 40px;">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Courses & Subjects Table -->
<div class="glass-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Subject Code</th>
                    <th>Subject / Course Title</th>
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
                            <div class="empty-state-title">No subjects found</div>
                            <div class="empty-state-desc">Try clearing filters or click "Add Subject" above to register a new subject.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($courses as $c): ?>
                        <?php
                            $catBadgeStyle = 'background: rgba(255, 255, 255, 0.06); color: var(--text-secondary); border: 1px solid var(--border-card);';
                            $cleanCat = trim($c['category']);
                            if (stripos($cleanCat, 'Tuition') !== false || stripos($cleanCat, 'School') !== false) {
                                $catBadgeStyle = 'background: rgba(14, 165, 233, 0.12); color: #0284c7; border: 1px solid rgba(14, 165, 233, 0.3);';
                            } elseif (stripos($cleanCat, 'Safety') !== false) {
                                $catBadgeStyle = 'background: rgba(245, 158, 11, 0.12); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.3);';
                            } elseif (stripos($cleanCat, 'Computer') !== false || stripos($cleanCat, 'Information Technology') !== false) {
                                $catBadgeStyle = 'background: rgba(99, 102, 241, 0.12); color: #4f46e5; border: 1px solid rgba(99, 102, 241, 0.3);';
                            } elseif (stripos($cleanCat, 'English') !== false || stripos($cleanCat, 'Language') !== false) {
                                $catBadgeStyle = 'background: rgba(16, 185, 129, 0.12); color: #059669; border: 1px solid rgba(16, 185, 129, 0.3);';
                            } elseif (stripos($cleanCat, 'General') !== false || stripos($cleanCat, 'Technical') !== false || stripos($cleanCat, 'Vocational') !== false) {
                                $catBadgeStyle = 'background: rgba(236, 72, 153, 0.12); color: #db2777; border: 1px solid rgba(236, 72, 153, 0.3);';
                            }
                        ?>
                        <tr>
                            <td>
                                <strong style="color: var(--primary-light); font-family: monospace; font-size: 13px;">
                                    <?= htmlspecialchars($c['course_code']) ?>
                                </strong>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($c['course_name']) ?></strong>
                                <?php if ($c['dept_name']): ?>
                                    <div style="font-size: 11px; color: var(--text-dim); margin-top: 2px;"><?= htmlspecialchars($c['dept_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; <?= $catBadgeStyle ?>">
                                    <?= htmlspecialchars($c['category']) ?>
                                </span>
                            </td>
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

<!-- Modal: Add Subject -->
<div class="modal-overlay" id="addCourseModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="addSubjectModalTitle">Add Subject</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('addCourseModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <!-- Dynamic Category Guidance Banner -->
                <div class="category-guidance-banner" id="add_category_guidance">
                    <span class="category-guidance-icon" id="add_guidance_icon">📘</span>
                    <div class="category-guidance-body">
                        <div class="category-guidance-header">
                            <span class="category-guidance-title" id="add_guidance_title">Tuition Courses</span>
                            <span class="category-guidance-badge" id="add_guidance_badge">Category Selected</span>
                        </div>
                        <div class="category-guidance-desc" id="add_guidance_desc">
                            Academic coaching and school subjects for Grades 4 through 12.
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="add_course_code">Subject Code <span class="required">*</span></label>
                        <input type="text" id="add_course_code" name="course_code" class="form-control" required placeholder="e.g. TUI-MATH-9" style="text-transform:uppercase;">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_course_name">Subject / Course Title <span class="required">*</span></label>
                        <input type="text" id="add_course_name" name="course_name" class="form-control" required placeholder="e.g. Mathematics Coaching Class 9th / 10th">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="add_category">Course Category <span class="required">*</span></label>
                        <select id="add_category" name="category" class="form-control" onchange="onCategorySelectChange(this.value)">
                            <optgroup label="Core Course Categories">
                                <option value="Tuition Courses">Tuition Courses</option>
                                <option value="Safety Courses">Safety Courses</option>
                                <option value="Computer Courses">Computer Courses</option>
                                <option value="English Courses">English Courses</option>
                                <option value="General Courses">General Courses</option>
                            </optgroup>
                            <?php if (!empty($otherCategories)): ?>
                            <optgroup label="Other Categories in Database">
                                <?php foreach ($otherCategories as $dp): ?>
                                    <option value="<?= htmlspecialchars($dp) ?>"><?= htmlspecialchars($dp) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endif; ?>
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
                        <input type="text" id="add_duration" name="duration" class="form-control" placeholder="e.g. 1 Year / 3 Months">
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
                    <textarea id="add_desc" name="description" class="form-control" placeholder="Outline of curriculum, learning objectives, and topics..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-glass" onclick="closeModal('addCourseModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Subject</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Subject -->
<div class="modal-overlay" id="editCourseModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Edit Subject Record</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('editCourseModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_course_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Subject Code</label>
                    <input type="text" id="edit_course_code" class="form-control" disabled style="opacity:0.7;">
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_course_name">Subject / Course Title <span class="required">*</span></label>
                    <input type="text" id="edit_course_name" name="course_name" class="form-control" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="edit_category">Course Category <span class="required">*</span></label>
                        <select id="edit_category" name="category" class="form-control">
                            <optgroup label="Core Course Categories">
                                <option value="Tuition Courses">Tuition Courses</option>
                                <option value="Safety Courses">Safety Courses</option>
                                <option value="Computer Courses">Computer Courses</option>
                                <option value="English Courses">English Courses</option>
                                <option value="General Courses">General Courses</option>
                            </optgroup>
                            <?php if (!empty($otherCategories)): ?>
                            <optgroup label="Other Categories in Database">
                                <?php foreach ($otherCategories as $dp): ?>
                                    <option value="<?= htmlspecialchars($dp) ?>"><?= htmlspecialchars($dp) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endif; ?>
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
                    <label class="form-label" for="edit_desc">Description / Syllabus Overview</label>
                    <textarea id="edit_desc" name="description" class="form-control"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-glass" onclick="closeModal('editCourseModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Subject</button>
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
const categoryMetadata = {
    'Tuition Courses': {
        icon: '📘',
        prefix: 'TUI-',
        duration: 'Per Month / Term',
        namePlaceholder: 'e.g. Physics Coaching (Class 9th / 10th)',
        descPlaceholder: 'Curriculum includes Board textbook chapters, numerical practice, weekly tests, and revision...',
        tip: 'Academic coaching, tuition classes, and subject-specific tutoring (Grades 4 to 12).',
        tint: 'rgba(14, 165, 233, 0.12)',
        border: 'rgba(14, 165, 233, 0.35)',
        badgeBg: 'rgba(14, 165, 233, 0.2)',
        color: '#0284c7'
    },
    'Safety Courses': {
        icon: '🛡️',
        prefix: 'SAF-',
        duration: '02 Weeks',
        namePlaceholder: 'e.g. IOSH Managing Safely (UK) / OSHA 30 Construction',
        descPlaceholder: 'Workplace hazard identification, HSE management standards, risk assessment, incident reporting...',
        tip: 'Occupational health, safety, and environmental qualifications (IOSH, OSHA, NEBOSH, HSE).',
        tint: 'rgba(245, 158, 11, 0.12)',
        border: 'rgba(245, 158, 11, 0.35)',
        badgeBg: 'rgba(245, 158, 11, 0.2)',
        color: '#d97706'
    },
    'Computer Courses': {
        icon: '💻',
        prefix: 'COMP-',
        duration: '1 Year',
        namePlaceholder: 'e.g. Diploma in Information Technology (DIT) / CIT',
        descPlaceholder: 'Computing fundamentals, office tools, programming, web design, database and graphic applications...',
        tip: 'Information technology diplomas, software training, coding, and digital certifications.',
        tint: 'rgba(99, 102, 241, 0.12)',
        border: 'rgba(99, 102, 241, 0.35)',
        badgeBg: 'rgba(99, 102, 241, 0.2)',
        color: '#4f46e5'
    },
    'English Courses': {
        icon: '🔤',
        prefix: 'ENG-',
        duration: '3 Months',
        namePlaceholder: 'e.g. English Language Learning (ELL) / IELTS Academic',
        descPlaceholder: 'Spoken English, grammar mechanics, vocabulary building, listening comprehension, accent training...',
        tip: 'Language proficiency, spoken English, exam prep (IELTS, TOEFL, PTE, Duolingo, Oxford).',
        tint: 'rgba(16, 185, 129, 0.12)',
        border: 'rgba(16, 185, 129, 0.35)',
        badgeBg: 'rgba(16, 185, 129, 0.2)',
        color: '#059669'
    },
    'General Courses': {
        icon: '🌐',
        prefix: 'GEN-',
        duration: '1-3 Months',
        namePlaceholder: 'e.g. Building Electrician / Auto CAD Civil Drafting',
        descPlaceholder: 'Hands-on technical workshop training, tools handling, blueprint reading, practical trade drills...',
        tip: 'Technical engineering trades, vocational certifications, and short skill training programs.',
        tint: 'rgba(236, 72, 153, 0.12)',
        border: 'rgba(236, 72, 153, 0.35)',
        badgeBg: 'rgba(236, 72, 153, 0.2)',
        color: '#db2777'
    }
};

function selectCourseCategory(category) {
    // Close dropdown
    const container = document.getElementById('addSubjectDropdownContainer');
    if (container) {
        container.classList.remove('open');
        const trigger = container.querySelector('.dropdown-trigger');
        if (trigger) trigger.setAttribute('aria-expanded', 'false');
        const parentHeader = container.closest('.page-header');
        if (parentHeader) parentHeader.classList.remove('dropdown-open-parent');
    }
    openAddSubjectModal(category);
}
window.selectCourseCategory = selectCourseCategory;

function openAddSubjectModal(category) {
    category = category || 'Tuition Courses';
    const meta = categoryMetadata[category] || categoryMetadata['General Courses'];

    // Update modal title & badge
    const titleEl = document.getElementById('addSubjectModalTitle');
    if (titleEl) {
        titleEl.innerHTML = `Add Subject <span style="font-size:12px; font-weight:700; padding:3px 9px; border-radius:12px; background:${meta.badgeBg}; color:${meta.color}; border:1px solid ${meta.border}; margin-left:8px;">${category}</span>`;
    }
    
    // Set category select
    const catSelect = document.getElementById('add_category');
    if (catSelect) catSelect.value = category;

    // Apply smart guidance & defaults
    applyCategoryDefaults(category);

    openModal('addCourseModal');

    // Autofocus subject code input
    setTimeout(() => {
        const codeInput = document.getElementById('add_course_code');
        if (codeInput) {
            codeInput.focus();
            if (codeInput.value) codeInput.setSelectionRange(codeInput.value.length, codeInput.value.length);
        }
    }, 150);
}

function onCategorySelectChange(category) {
    applyCategoryDefaults(category);
    const meta = categoryMetadata[category] || categoryMetadata['General Courses'];
    const titleEl = document.getElementById('addSubjectModalTitle');
    if (titleEl) {
        titleEl.innerHTML = `Add Subject <span style="font-size:12px; font-weight:700; padding:3px 9px; border-radius:12px; background:${meta.badgeBg}; color:${meta.color}; border:1px solid ${meta.border}; margin-left:8px;">${category}</span>`;
    }
}

function applyCategoryDefaults(category) {
    const meta = categoryMetadata[category] || categoryMetadata['General Courses'];

    // Guidance banner elements
    const banner = document.getElementById('add_category_guidance');
    const icon = document.getElementById('add_guidance_icon');
    const title = document.getElementById('add_guidance_title');
    const desc = document.getElementById('add_guidance_desc');
    const badge = document.getElementById('add_guidance_badge');

    if (banner) {
        banner.style.background = meta.tint;
        banner.style.borderColor = meta.border;
    }
    if (icon) icon.textContent = meta.icon;
    if (title) title.textContent = category;
    if (desc) desc.textContent = meta.tip;
    if (badge) {
        badge.textContent = `${category} Selected`;
        badge.style.background = meta.badgeBg;
        badge.style.color = meta.color;
        badge.style.border = `1px solid ${meta.border}`;
    }

    // Input placeholders & prefix
    const codeInput = document.getElementById('add_course_code');
    const nameInput = document.getElementById('add_course_name');
    const durInput = document.getElementById('add_duration');
    const descInput = document.getElementById('add_desc');

    if (codeInput && (!codeInput.value || isDefaultCategoryPrefix(codeInput.value))) {
        codeInput.value = meta.prefix;
    }
    if (durInput && (!durInput.value || isDefaultDuration(durInput.value))) {
        durInput.value = meta.duration;
    }
    if (nameInput) {
        nameInput.placeholder = meta.namePlaceholder;
    }
    if (descInput) {
        descInput.placeholder = meta.descPlaceholder;
    }
}

function isDefaultCategoryPrefix(val) {
    return ['TUI-', 'SAF-', 'COMP-', 'ENG-', 'GEN-'].includes(val.trim());
}

function isDefaultDuration(val) {
    return ['Per Month / Term', '02 Weeks', '1 Year', '3 Months', '1-3 Months'].includes(val.trim());
}

function openEditCourseModal(course) {
    document.getElementById('edit_course_id').value = course.id;
    document.getElementById('edit_course_code').value = course.course_code || '';
    document.getElementById('edit_course_name').value = course.course_name || '';
    document.getElementById('edit_category').value = course.category || 'General Courses';
    document.getElementById('edit_dept_id').value = course.department_id || '';
    document.getElementById('edit_duration').value = course.duration || '';
    document.getElementById('edit_fee').value = course.standard_fee || 0;
    document.getElementById('edit_ttb_fee').value = course.ttb_exam_fee || 0;
    document.getElementById('edit_desc').value = course.description || '';
    openModal('editCourseModal');
}

function confirmDeleteCourse(id, name) {
    confirmAction(
        'Delete Academic Subject?',
        `Are you sure you want to delete '${name}'? Linked batches and enrollment records will be affected.`,
        () => {
            document.getElementById('delete_course_id').value = id;
            document.getElementById('delete-course-form').submit();
        }
    );
}

// Auto-trigger Add Subject modal if launched with query parameter
<?php if (!empty($_GET['new'])): ?>
document.addEventListener('DOMContentLoaded', () => {
    const requestedCategory = <?= json_encode($_GET['category'] ?? 'Tuition Courses') ?>;
    openAddSubjectModal(requestedCategory);
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
