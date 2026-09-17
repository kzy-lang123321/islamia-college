<?php
/**
 * Department Management Module
 * Islamia College of Technology College Management System
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// RBAC check before output
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
            $error = "Insufficient permissions to create departments.";
        } else {
            $name = trim($_POST['dept_name'] ?? '');
            $code = strtoupper(trim($_POST['dept_code'] ?? ''));
            $desc = trim($_POST['description'] ?? '');

            if (empty($name) || empty($code)) {
                $error = "Department name and code are required.";
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO departments (dept_name, dept_code, description) VALUES (?, ?, ?)");
                    $stmt->execute([$name, $code, $desc]);
                    $message = "Department '{$name}' created successfully.";
                } catch (PDOException $e) {
                    error_log("Create department error: " . $e->getMessage());
                    $error = ($e->getCode() == 23000) ? "Department name or code already exists." : "An error occurred while creating the department.";
                }
            }
        }
    } elseif ($action === 'update') {
        if (!$canManage) {
            $error = "Insufficient permissions to modify departments.";
        } else {
            $id   = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['dept_name'] ?? '');
            $code = strtoupper(trim($_POST['dept_code'] ?? ''));
            $desc = trim($_POST['description'] ?? '');

            if ($id <= 0 || empty($name) || empty($code)) {
                $error = "Department name and code are required.";
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE departments SET dept_name = ?, dept_code = ?, description = ? WHERE id = ?");
                    $stmt->execute([$name, $code, $desc, $id]);
                    $message = "Department updated successfully.";
                } catch (PDOException $e) {
                    error_log("Update department error: " . $e->getMessage());
                    $error = "An error occurred while updating the department.";
                }
            }
        }
    } elseif ($action === 'delete') {
        if (!$canManage) {
            $error = "Insufficient permissions to delete departments.";
        } else {
            $id = (int)($_POST['id'] ?? 0);
            try {
                $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Department deleted successfully.";
            } catch (PDOException $e) {
                error_log("Delete department error: " . $e->getMessage());
                $error = "Cannot delete department: academic courses are assigned to this department.";
            }
        }
    }
}

// Fetch departments with active course counts
$departments = $pdo->query("
    SELECT d.*, 
           (SELECT COUNT(*) FROM courses c WHERE c.department_id = d.id) as total_courses
    FROM departments d 
    ORDER BY d.id ASC
")->fetchAll();

$pageTitle = "Academic Departments";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Academic Departments</h1>
        <p class="page-breadcrumb">Manage institutional faculties, academic wings, and department codes</p>
    </div>
    <?php if ($canManage): ?>
    <div>
        <button type="button" class="btn btn-primary" onclick="openModal('addDeptModal')">
            <span>+ Add Department</span>
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

<div class="glass-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Department Code</th>
                    <th>Department Name</th>
                    <th>Description</th>
                    <th>Active Courses</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($departments)): ?>
                    <tr>
                        <td colspan="5" class="empty-state">
                            <div class="empty-state-icon">🏛️</div>
                            <div class="empty-state-title">No academic departments found</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($departments as $d): ?>
                        <tr>
                            <td>
                                <strong style="color: var(--primary-light); font-family: monospace; font-size: 15px;">
                                    <?= htmlspecialchars($d['dept_code']) ?>
                                </strong>
                            </td>
                            <td><strong><?= htmlspecialchars($d['dept_name']) ?></strong></td>
                            <td style="color: var(--text-muted); max-width: 320px;"><?= htmlspecialchars($d['description'] ?: '—') ?></td>
                            <td>
                                <span class="status-badge badge-active">
                                    <?= (int)$d['total_courses'] ?> Courses
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <?php if ($canManage): ?>
                                    <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                        <button type="button" class="btn btn-glass btn-sm" onclick="openEditDeptModal(<?= htmlspecialchars(json_encode($d), ENT_QUOTES) ?>)">Edit</button>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="confirmDeleteDept(<?= $d['id'] ?>, '<?= htmlspecialchars($d['dept_name'], ENT_QUOTES) ?>')">Delete</button>
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

<!-- Modal: Add Department -->
<div class="modal-overlay" id="addDeptModal">
    <div class="modal-dialog" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Create Academic Department</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('addDeptModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="add_dept_code">Department Code <span class="required">*</span></label>
                    <input type="text" id="add_dept_code" name="dept_code" class="form-control" required placeholder="e.g. CS, IT, ENG, MGT" style="text-transform:uppercase;">
                </div>

                <div class="form-group">
                    <label class="form-label" for="add_dept_name">Department Full Name <span class="required">*</span></label>
                    <input type="text" id="add_dept_name" name="dept_name" class="form-control" required placeholder="e.g. Computer Science & IT">
                </div>

                <div class="form-group">
                    <label class="form-label" for="add_dept_desc">Description / Scope</label>
                    <textarea id="add_dept_desc" name="description" class="form-control" placeholder="Brief outline of academic offerings..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-glass" onclick="closeModal('addDeptModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Department</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Department -->
<div class="modal-overlay" id="editDeptModal">
    <div class="modal-dialog" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Edit Academic Department</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('editDeptModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_dept_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="edit_dept_code">Department Code <span class="required">*</span></label>
                    <input type="text" id="edit_dept_code" name="dept_code" class="form-control" required style="text-transform:uppercase;">
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_dept_name">Department Full Name <span class="required">*</span></label>
                    <input type="text" id="edit_dept_name" name="dept_name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_dept_desc">Description / Scope</label>
                    <textarea id="edit_dept_desc" name="description" class="form-control"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-glass" onclick="closeModal('editDeptModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Department</button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden Delete Form with CSRF -->
<form id="delete-dept-form" method="POST" action="" style="display:none;">
    <?= getCsrfField() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_dept_id">
</form>

<script>
function openEditDeptModal(dept) {
    document.getElementById('edit_dept_id').value = dept.id;
    document.getElementById('edit_dept_code').value = dept.dept_code || '';
    document.getElementById('edit_dept_name').value = dept.dept_name || '';
    document.getElementById('edit_dept_desc').value = dept.description || '';
    openModal('editDeptModal');
}

function confirmDeleteDept(id, name) {
    confirmAction(
        'Delete Academic Department?',
        `Are you sure you want to delete '${name}'? This action cannot be performed if courses belong to this department.`,
        () => {
            document.getElementById('delete_dept_id').value = id;
            document.getElementById('delete-dept-form').submit();
        }
    );
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
