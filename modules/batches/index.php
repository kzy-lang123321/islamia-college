<?php
/**
 * Academic Batches Management Module
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
            $error = "Insufficient permissions to create batches.";
        } else {
            $code      = strtoupper(trim($_POST['batch_code'] ?? ''));
            $name      = trim($_POST['batch_name'] ?? '');
            $courseId  = (int)($_POST['course_id'] ?? 0);
            $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
            $endDate   = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
            $status    = strtolower(trim($_POST['status'] ?? 'active'));

            if (empty($code) || empty($name) || $courseId <= 0) {
                $error = "Batch code, name, and valid course selection are required.";
            } else {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO batches (batch_code, batch_name, course_id, start_date, end_date, status)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$code, $name, $courseId, $startDate, $endDate, $status]);
                    $message = "Batch '{$code}' created successfully.";
                } catch (PDOException $e) {
                    error_log("Create batch error: " . $e->getMessage());
                    $error = ($e->getCode() == 23000) ? "Batch code already exists." : "An error occurred while creating the batch.";
                }
            }
        }
    } elseif ($action === 'update') {
        if (!$canManage) {
            $error = "Insufficient permissions to modify batches.";
        } else {
            $id        = (int)($_POST['id'] ?? 0);
            $name      = trim($_POST['batch_name'] ?? '');
            $courseId  = (int)($_POST['course_id'] ?? 0);
            $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
            $endDate   = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
            $status    = strtolower(trim($_POST['status'] ?? 'active'));

            if ($id <= 0 || empty($name) || $courseId <= 0) {
                $error = "Batch record, name, and valid course are required.";
            } else {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE batches 
                        SET batch_name = ?, course_id = ?, start_date = ?, end_date = ?, status = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $courseId, $startDate, $endDate, $status, $id]);
                    $message = "Batch updated successfully.";
                } catch (PDOException $e) {
                    error_log("Update batch error: " . $e->getMessage());
                    $error = "An error occurred while updating the batch.";
                }
            }
        }
    } elseif ($action === 'delete') {
        if (!$canManage) {
            $error = "Insufficient permissions to delete batches.";
        } else {
            $id = (int)($_POST['id'] ?? 0);
            try {
                $stmt = $pdo->prepare("DELETE FROM batches WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Batch deleted successfully.";
            } catch (PDOException $e) {
                error_log("Delete batch error: " . $e->getMessage());
                $error = "Cannot delete batch: students are enrolled in this batch session.";
            }
        }
    }
}

// Search & Filter
$search = trim($_GET['search'] ?? '');
$courseFilter = (int)($_GET['course_id'] ?? 0);

$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(b.batch_code LIKE ? OR b.batch_name LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term]);
}

if ($courseFilter > 0) {
    $where[] = "b.course_id = ?";
    $params[] = $courseFilter;
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Fetch Batches with enrollment count
$sql = "
    SELECT b.*, c.course_name, c.course_code,
           (SELECT COUNT(*) FROM enrollments e WHERE e.batch_id = b.id) as total_students
    FROM batches b
    JOIN courses c ON b.course_id = c.id
    {$whereClause}
    ORDER BY b.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$batches = $stmt->fetchAll();

$coursesList = $pdo->query("SELECT id, course_code, course_name FROM courses ORDER BY course_name ASC")->fetchAll();

$pageTitle = "Academic Batches";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Academic Batches</h1>
        <p class="page-breadcrumb">Manage intake cohorts, academic sessions, and enrollment rosters</p>
    </div>
    <?php if ($canManage): ?>
    <div>
        <button type="button" class="btn btn-primary" onclick="openModal('addBatchModal')">
            <span>+ Add New Batch</span>
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
            <input type="text" name="search" class="form-control" placeholder="Search by batch code or name..." value="<?= htmlspecialchars($search) ?>" style="min-height: 40px;">
        </div>
        <div class="filter-group">
            <select name="course_id" class="form-control" style="min-height: 40px; min-width: 220px;">
                <option value="">All Academic Programs</option>
                <?php foreach ($coursesList as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $courseFilter === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['course_code']) ?> — <?= htmlspecialchars($c['course_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-glass" style="min-height: 40px;">Filter</button>
            <?php if (!empty($search) || $courseFilter > 0): ?>
                <a href="<?= $baseUri ?>/modules/batches/index.php" class="btn btn-glass" style="min-height: 40px;">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Batches Table -->
<div class="glass-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Batch Code</th>
                    <th>Batch Session Title</th>
                    <th>Associated Program</th>
                    <th>Timeline</th>
                    <th>Enrollments</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($batches)): ?>
                    <tr>
                        <td colspan="7" class="empty-state">
                            <div class="empty-state-icon">👥</div>
                            <div class="empty-state-title">No academic batches found</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($batches as $b): ?>
                        <tr>
                            <td>
                                <strong style="color: var(--primary-light); font-family: monospace; font-size: 15px;">
                                    <?= htmlspecialchars($b['batch_code']) ?>
                                </strong>
                            </td>
                            <td><strong><?= htmlspecialchars($b['batch_name']) ?></strong></td>
                            <td><?= htmlspecialchars($b['course_name']) ?></td>
                            <td style="color: var(--text-muted); font-size: 13px;">
                                <?= $b['start_date'] ? date('M Y', strtotime($b['start_date'])) : '—' ?>
                                to
                                <?= $b['end_date'] ? date('M Y', strtotime($b['end_date'])) : 'Present' ?>
                            </td>
                            <td>
                                <a href="../students/index.php?batch_id=<?= $b['id'] ?>" class="status-badge badge-active" style="text-decoration:none;">
                                    <?= (int)$b['total_students'] ?> Enrolled
                                </a>
                            </td>
                            <td>
                                <span class="status-badge <?= $b['status'] === 'active' ? 'badge-active' : 'badge-inactive' ?>">
                                    <?= ucfirst(htmlspecialchars($b['status'])) ?>
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                    <a href="../students/index.php?batch_id=<?= $b['id'] ?>" class="btn btn-glass btn-sm">Roster</a>
                                    <?php if ($canManage): ?>
                                        <button type="button" class="btn btn-glass btn-sm" onclick="openEditBatchModal(<?= htmlspecialchars(json_encode($b), ENT_QUOTES) ?>)">Edit</button>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="confirmDeleteBatch(<?= $b['id'] ?>, '<?= htmlspecialchars($b['batch_code'], ENT_QUOTES) ?>')">Delete</button>
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

<!-- Modal: Add Batch -->
<div class="modal-overlay" id="addBatchModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Create Academic Batch</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('addBatchModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="add_batch_code">Batch Code <span class="required">*</span></label>
                        <input type="text" id="add_batch_code" name="batch_code" class="form-control" required placeholder="e.g. DIT-2023-A" style="text-transform:uppercase;">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_batch_name">Session Title <span class="required">*</span></label>
                        <input type="text" id="add_batch_name" name="batch_name" class="form-control" required placeholder="e.g. DIT Annual Intake 2023-24">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="add_course_select">Associated Academic Program <span class="required">*</span></label>
                    <select id="add_course_select" name="course_id" class="form-control" required>
                        <option value="">Select course...</option>
                        <?php foreach ($coursesList as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['course_code']) ?> — <?= htmlspecialchars($c['course_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="add_start_date">Start Date</label>
                        <input type="date" id="add_start_date" name="start_date" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_end_date">End Date</label>
                        <input type="date" id="add_end_date" name="end_date" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_batch_status">Status</label>
                        <select id="add_batch_status" name="status" class="form-control">
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-glass" onclick="closeModal('addBatchModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Batch</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Batch -->
<div class="modal-overlay" id="editBatchModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Edit Academic Batch</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('editBatchModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_batch_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Batch Code</label>
                    <input type="text" id="edit_batch_code" class="form-control" disabled style="opacity:0.7;">
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_batch_name">Session Title <span class="required">*</span></label>
                    <input type="text" id="edit_batch_name" name="batch_name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_course_select">Associated Academic Program <span class="required">*</span></label>
                    <select id="edit_course_select" name="course_id" class="form-control" required>
                        <?php foreach ($coursesList as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['course_code']) ?> — <?= htmlspecialchars($c['course_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="edit_start_date">Start Date</label>
                        <input type="date" id="edit_start_date" name="start_date" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_end_date">End Date</label>
                        <input type="date" id="edit_end_date" name="end_date" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_batch_status">Status</label>
                        <select id="edit_batch_status" name="status" class="form-control">
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-glass" onclick="closeModal('editBatchModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Batch</button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden Delete Form with CSRF -->
<form id="delete-batch-form" method="POST" action="" style="display:none;">
    <?= getCsrfField() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_batch_id">
</form>

<script>
function openEditBatchModal(batch) {
    document.getElementById('edit_batch_id').value = batch.id;
    document.getElementById('edit_batch_code').value = batch.batch_code || '';
    document.getElementById('edit_batch_name').value = batch.batch_name || '';
    document.getElementById('edit_course_select').value = batch.course_id || '';
    document.getElementById('edit_start_date').value = batch.start_date || '';
    document.getElementById('edit_end_date').value = batch.end_date || '';
    document.getElementById('edit_batch_status').value = batch.status || 'active';
    openModal('editBatchModal');
}

function confirmDeleteBatch(id, code) {
    confirmAction(
        'Delete Academic Batch?',
        `Are you sure you want to remove batch '${code}'? This action cannot be performed if student enrollments exist.`,
        () => {
            document.getElementById('delete_batch_id').value = id;
            document.getElementById('delete-batch-form').submit();
        }
    );
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
