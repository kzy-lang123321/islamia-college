<?php
/**
 * User & Role Management Module (RBAC)
 * Islamia College of Technology College Management System
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// Strict Server-Side Role Enforcement (Admin only) before any HTML emission
requireRole(['admin']);

$pdo = Database::getConnection();
$currentUser = getCurrentUser();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfRequest();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username = strtolower(trim($_POST['username'] ?? ''));
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $phone    = trim($_POST['phone'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $role     = strtolower(trim($_POST['role'] ?? 'staff'));

        // If password is left empty, default password is set to phone number
        if (empty($password) && !empty($phone)) {
            $password = $phone;
        }

        if (empty($username) || empty($fullName)) {
            $error = "Username and full name are required.";
        } elseif (empty($password)) {
            $error = "Please provide a password or enter a phone number to use as the default password.";
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, phone, password_hash, full_name, role, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([$username, !empty($email) ? $email : null, !empty($phone) ? $phone : null, $hash, $fullName, $role]);
                $message = "User '{$username}' created successfully. Default password is set to: " . htmlspecialchars($password);
            } catch (PDOException $e) {
                error_log("Create user error: " . $e->getMessage());
                $error = ($e->getCode() == 23000) ? "Username, phone, or email already exists." : "An error occurred while creating the user account.";
            }
        }
    } elseif ($action === 'reset_pass') {
        $id = (int)($_POST['id'] ?? 0);
        $newPass = trim($_POST['new_password'] ?? '');
        $resetToPhone = isset($_POST['reset_to_phone']);

        if ($id <= 0) {
            $error = "Invalid user record.";
        } else {
            $uStmt = $pdo->prepare("SELECT phone FROM users WHERE id = ?");
            $uStmt->execute([$id]);
            $targetUser = $uStmt->fetch();

            if ($resetToPhone) {
                if (empty($targetUser['phone'])) {
                    $error = "User does not have a phone number registered to reset to.";
                } else {
                    $hash = password_hash($targetUser['phone'], PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $stmt->execute([$hash, $id]);
                    $message = "Password successfully reset to default phone number: " . htmlspecialchars($targetUser['phone']);
                }
            } elseif (strlen($newPass) < 4) {
                $error = "Password must be at least 4 characters long.";
            } else {
                $hash = password_hash($newPass, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$hash, $id]);
                $message = "Password updated securely.";
            }
        }
    } elseif ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$currentUser['id']) {
            $error = "You cannot deactivate your own active session.";
        } else {
            $stmt = $pdo->prepare("UPDATE users SET is_active = 1 - is_active WHERE id = ?");
            $stmt->execute([$id]);
            $message = "User status updated.";
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$currentUser['id']) {
            $error = "You cannot delete your own active administrator account.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $message = "User deleted successfully.";
        }
    }
}

$users = $pdo->query("SELECT id, username, email, phone, full_name, role, is_active, created_at, updated_at FROM users ORDER BY id ASC")->fetchAll();

$pageTitle = "User Management";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">User Management & Security (RBAC)</h1>
        <p class="page-breadcrumb">Administration • Role-Based Access Control and System Authentication</p>
    </div>
    <div>
        <button type="button" class="btn btn-primary" onclick="openModal('addUserModal')">
            <span>+ Add System User</span>
        </button>
    </div>
</div>

<?php if ($message): ?>
    <div style="padding:14px 18px; background:rgba(16,185,129,0.12); border:1px solid rgba(16,185,129,0.3); color:#6ee7b7; border-radius:10px; margin-bottom:20px; font-size:14px;">
        ✓ <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div style="padding:14px 18px; background:rgba(244,63,94,0.12); border:1px solid rgba(244,63,94,0.3); color:#fda4af; border-radius:10px; margin-bottom:20px; font-size:14px;">
        ⚠️ <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="glass-card">
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Phone / Default Password</th>
                    <th>Institutional Email</th>
                    <th>Access Role</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><strong style="color: var(--primary-light);"><?= htmlspecialchars($u['username']) ?></strong></td>
                        <td><?= htmlspecialchars($u['full_name']) ?></td>
                        <td>
                            <?php if (!empty($u['phone'])): ?>
                                <span style="font-family: monospace; font-size: 13px; color: #38bdf8; background: rgba(56,189,248,0.1); padding: 2px 8px; border-radius: 4px;">
                                    📞 <?= htmlspecialchars($u['phone']) ?>
                                </span>
                            <?php else: ?>
                                <span style="color: var(--text-dim);">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="color: var(--text-muted);"><?= htmlspecialchars($u['email'] ?: '—') ?></td>
                        <td>
                            <span style="display:inline-block; padding:4px 10px; border-radius:4px; font-size:12px; font-weight:600; text-transform:uppercase; background:rgba(2,132,199,0.15); color:var(--primary-light);">
                                <?= htmlspecialchars($u['role']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge <?= $u['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                                <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td style="color:var(--text-muted); font-size:13px;"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                        <td style="text-align:right;">
                            <div style="display:flex; gap:8px; justify-content:flex-end;">
                                <button type="button" class="btn btn-glass btn-sm" onclick="openResetModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['phone'] ?? '', ENT_QUOTES) ?>')">
                                    Password
                                </button>
                                <?php if ($u['id'] !== $currentUser['id']): ?>
                                    <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Toggle status for this user?');">
                                        <?= getCsrfField() ?>
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn btn-glass btn-sm">
                                            <?= $u['is_active'] ? 'Lock' : 'Unlock' ?>
                                        </button>
                                    </form>
                                    <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Permanently remove this user?');">
                                        <?= getCsrfField() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                <?php else: ?>
                                    <span style="font-size:12px; color:var(--text-dim); padding:6px 10px;">(Current)</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add New System User -->
<div class="modal-overlay" id="addUserModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Create System User</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('addUserModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="username">Username <span class="required">*</span></label>
                    <input type="text" id="username" name="username" class="form-control" required placeholder="e.g. javed.iqbal" autocomplete="off">
                </div>

                <div class="form-group">
                    <label class="form-label" for="full_name">Full Name <span class="required">*</span></label>
                    <input type="text" id="full_name" name="full_name" class="form-control" required placeholder="e.g. Javed Iqbal">
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">Phone Number <span class="required">*</span></label>
                    <input type="text" id="phone" name="phone" class="form-control" required placeholder="e.g. 0345-1234567">
                    <span style="font-size:12px; color:var(--text-muted); margin-top:4px; display:block;">
                        💡 <strong>HCD Note:</strong> This phone number serves as the user's <em>default login password</em>.
                    </span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Institutional Email</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="e.g. javed@islamia.edu.pk">
                </div>

                <div class="form-group">
                    <label class="form-label" for="role">Assigned Role <span class="required">*</span></label>
                    <select id="role" name="role" class="form-control" required>
                        <option value="staff">Staff (Academics & Reports View)</option>
                        <option value="registrar">Registrar (Student & Academic Management)</option>
                        <option value="accountant">Accountant (Fee, Expense & Loan Management)</option>
                        <option value="admin">System Administrator (Full Privileges)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="new_password_input">Custom Password <span style="font-weight:normal; color:var(--text-muted);">(Optional)</span></label>
                    <div class="input-group">
                        <input type="password" id="new_password_input" name="password" class="form-control" placeholder="Leave empty to use phone number as password">
                        <button type="button" class="password-toggle-btn" tabindex="-1">👁️</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-glass" onclick="closeModal('addUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Reset User Password -->
<div class="modal-overlay" id="resetPassModal">
    <div class="modal-dialog" style="max-width:440px;">
        <div class="modal-header">
            <h3 class="modal-title">Reset Password</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('resetPassModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="reset_pass">
            <input type="hidden" name="id" id="resetUserId">
            <div class="modal-body">
                <p style="font-size:13px; color:var(--text-muted); margin-bottom:16px;">
                    Managing password credentials for <strong id="resetUsernameDisplay" style="color:var(--text-primary);"></strong>.
                </p>

                <div style="background:rgba(2,132,199,0.1); border:1px solid rgba(2,132,199,0.25); border-radius:8px; padding:12px 14px; margin-bottom:16px;">
                    <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-size:13px; color:var(--text-primary);">
                        <input type="checkbox" name="reset_to_phone" id="resetToPhoneCheck" value="1" onchange="toggleCustomPassInput(this.checked)">
                        <span>Reset to default phone password (<span id="resetPhoneDisplay" style="font-family:monospace; color:#38bdf8;"></span>)</span>
                    </label>
                </div>

                <div class="form-group" id="customPassGroup">
                    <label class="form-label" for="reset_new_password">Or Set Custom Password</label>
                    <div class="input-group">
                        <input type="password" id="reset_new_password" name="new_password" class="form-control" minlength="4" placeholder="Enter new custom password">
                        <button type="button" class="password-toggle-btn" tabindex="-1">👁️</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-glass" onclick="closeModal('resetPassModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Password</button>
            </div>
        </form>
    </div>
</div>

<script>
function openResetModal(userId, username, phone) {
    document.getElementById('resetUserId').value = userId;
    document.getElementById('resetUsernameDisplay').textContent = username;
    document.getElementById('resetPhoneDisplay').textContent = phone || 'None';
    const check = document.getElementById('resetToPhoneCheck');
    check.checked = !!phone;
    check.disabled = !phone;
    toggleCustomPassInput(check.checked);
    openModal('resetPassModal');
}

function toggleCustomPassInput(resetToPhone) {
    const group = document.getElementById('customPassGroup');
    const input = document.getElementById('reset_new_password');
    if (resetToPhone) {
        group.style.opacity = '0.4';
        input.disabled = true;
        input.required = false;
        input.value = '';
    } else {
        group.style.opacity = '1';
        input.disabled = false;
        input.required = true;
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
