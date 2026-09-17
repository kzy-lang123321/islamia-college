<?php
/**
 * Staff & Faculty Management Module with Profile Photos
 * Islamia College of Technology College Management System
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole(['admin']);

$pdo         = Database::getConnection();
$currentUser = getCurrentUser();
$message     = '';
$error       = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfRequest();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $code       = strtoupper(trim($_POST['staff_code']        ?? ''));
        $name       = trim($_POST['full_name']                    ?? '');
        $desig      = trim($_POST['designation']                  ?? '');
        $phone      = trim($_POST['phone']                        ?? '');
        $salaryType = ($_POST['salary_type'] ?? 'fixed') === 'percentage' ? 'percentage' : 'fixed';
        $baseSalary = $salaryType === 'fixed'      ? (float)($_POST['base_salary']       ?? 0) : 0;
        $salaryPct  = $salaryType === 'percentage' ? (float)($_POST['salary_percentage'] ?? 0) : 0;

        if (empty($code) || empty($name)) {
            $error = 'Staff code and full name are required.';
        } elseif ($salaryType === 'percentage' && ($salaryPct <= 0 || $salaryPct > 100)) {
            $error = 'Percentage must be between 0.01 and 100.';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Insert into staff_members
                $stmt = $pdo->prepare('INSERT INTO staff_members (staff_code,full_name,designation,phone,base_salary,salary_type,salary_percentage,is_active) VALUES (?,?,?,?,?,?,?,1)');
                $stmt->execute([$code,$name,$desig,$phone,$baseSalary,$salaryType,$salaryPct]);
                
                // Insert into users for login
                $username = strtolower(str_replace(' ', '', $name));
                // Default password is their numbers (phone) or 'staff123' if none
                $password = preg_replace('/\D/', '', $phone);
                if (empty($password)) $password = 'staff123';
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                
                $uStmt = $pdo->prepare('INSERT INTO users (username, full_name, phone, role, password_hash, is_active, must_change_password) VALUES (?, ?, ?, ?, ?, 1, 1)');
                $uStmt->execute([$username, $name, $phone, 'staff', $passwordHash]);
                
                $pdo->commit();
                $message = "Staff member '{$name}' registered successfully with username '{$username}' and password '{$password}'.";
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log('Create staff: ' . $e->getMessage());
                $error = ($e->getCode() == 23000) ? 'Staff code or username already exists.' : 'Error creating staff record.';
            }
        }

    } elseif ($action === 'update') {
        $id         = (int)($_POST['id']                          ?? 0);
        $name       = trim($_POST['full_name']                    ?? '');
        $desig      = trim($_POST['designation']                  ?? '');
        $phone      = trim($_POST['phone']                        ?? '');
        $salaryType = ($_POST['salary_type'] ?? 'fixed') === 'percentage' ? 'percentage' : 'fixed';
        $baseSalary = $salaryType === 'fixed'      ? (float)($_POST['base_salary']       ?? 0) : 0;
        $salaryPct  = $salaryType === 'percentage' ? (float)($_POST['salary_percentage'] ?? 0) : 0;
        $active     = isset($_POST['is_active']) ? 1 : 0;

        if ($id <= 0 || empty($name)) {
            $error = 'Staff ID and name are required.';
        } elseif ($salaryType === 'percentage' && ($salaryPct <= 0 || $salaryPct > 100)) {
            $error = 'Percentage must be between 0.01 and 100.';
        } else {
            try {
                $stmt = $pdo->prepare('UPDATE staff_members SET full_name=?,designation=?,phone=?,base_salary=?,salary_type=?,salary_percentage=?,is_active=? WHERE id=?');
                $stmt->execute([$name,$desig,$phone,$baseSalary,$salaryType,$salaryPct,$active,$id]);
                $message = 'Staff profile updated successfully.';
            } catch (PDOException $e) {
                error_log('Update staff: ' . $e->getMessage());
                $error = 'Error updating staff record.';
            }
        }

    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $r = $pdo->prepare('SELECT profile_photo FROM staff_members WHERE id=?');
            $r->execute([$id]);
            $row = $r->fetch();
            if (!empty($row['profile_photo'])) {
                $f = __DIR__ . '/../../assets/images/staff/' . basename($row['profile_photo']);
                if (file_exists($f)) @unlink($f);
            }
            $stmt = $pdo->prepare('DELETE FROM staff_members WHERE id=?');
            $stmt->execute([$id]);
            $message = 'Staff member deleted.';
        } catch (PDOException $e) {
            error_log('Delete staff: ' . $e->getMessage());
            $error = 'Cannot delete: payroll records are linked to this staff member.';
        }
    }
}

$staffList = $pdo->query("
    SELECT s.*,
           (SELECT COUNT(*) FROM staff_payroll p WHERE p.staff_id=s.id) AS payroll_entries,
           (SELECT COALESCE(SUM(total_paid),0) FROM staff_payroll p WHERE p.staff_id=s.id) AS total_received
    FROM staff_members s ORDER BY s.id ASC
")->fetchAll();

$baseUri   = strpos($_SERVER['REQUEST_URI'], '/islamia%20college') !== false ? '/islamia%20college' : '';
$pageTitle = 'Staff & Faculty Directory';
require_once __DIR__ . '/../../includes/header.php';
?>
<style>
.staff-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:20px;}
.staff-card{background:var(--bg-card);border:1px solid var(--border-card);border-radius:16px;overflow:hidden;transition:transform 0.2s,box-shadow 0.2s,border-color 0.2s;display:flex;flex-direction:column;box-shadow:var(--shadow-sm);}
.staff-card:hover{transform:translateY(-3px);box-shadow:var(--shadow-md);border-color:var(--primary-light);}
.staff-card-photo{position:relative;width:100%;height:180px;background:var(--bg-card-subtle);display:flex;align-items:center;justify-content:center;overflow:hidden;}
.staff-card-photo img{width:100%;height:100%;object-fit:cover;}
.photo-placeholder{width:80px;height:80px;border-radius:50%;background:rgba(21,94,239,0.08);border:2px dashed var(--primary-light);display:flex;align-items:center;justify-content:center;font-size:2rem;color:var(--text-muted);font-weight:700;}
.photo-upload-overlay{position:absolute;inset:0;background:rgba(0,0,0,0.6);backdrop-filter:blur(2px);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;opacity:0;transition:opacity 0.2s;cursor:pointer;}
.staff-card-photo:hover .photo-upload-overlay{opacity:1;}
.photo-upload-btn{background:var(--primary);border:none;border-radius:8px;color:#ffffff;font-size:0.8rem;font-weight:700;padding:8px 16px;cursor:pointer;display:flex;align-items:center;gap:6px;}
.photo-remove-btn{background:rgba(244,63,94,0.9);border:none;border-radius:8px;color:#fff;font-size:0.75rem;font-weight:600;padding:5px 12px;cursor:pointer;display:flex;align-items:center;gap:5px;}
.upload-progress{position:absolute;bottom:0;left:0;height:3px;background:var(--primary);width:0;transition:width 0.3s;border-radius:3px;}
.view-toggle{display:flex;gap:4px;background:var(--bg-card-subtle);border:1px solid var(--border-card);border-radius:8px;padding:4px;}
.view-toggle-btn{border:none;background:transparent;color:var(--text-muted);padding:6px 12px;border-radius:6px;cursor:pointer;font-size:0.85rem;transition:all 0.15s;}
.view-toggle-btn.active{background:var(--bg-card);color:var(--primary);box-shadow:var(--shadow-sm);font-weight:600;}
.salary-pill-pct{background:rgba(217,119,6,0.12);border:1px solid rgba(217,119,6,0.3);color:#b45309;padding:3px 10px;border-radius:20px;font-size:0.75rem;font-weight:600;}
[data-theme="dark"] .salary-pill-pct{background:rgba(251,191,36,0.15);border-color:rgba(251,191,36,0.35);color:#fde68a;}
.salary-pill-fixed{background:rgba(21,94,239,0.1);border:1px solid rgba(21,94,239,0.25);color:var(--primary);padding:3px 10px;border-radius:20px;font-size:0.75rem;font-weight:600;}
[data-theme="dark"] .salary-pill-fixed{background:rgba(56,189,248,0.12);border-color:rgba(56,189,248,0.3);color:#7dd3fc;}
</style>

<div class="page-header">
    <div>
        <h1 class="page-title">Staff &amp; Faculty Directory</h1>
        <p class="page-breadcrumb">Human Resources &bull; Profiles, photos &amp; compensation</p>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
        <div class="view-toggle">
            <button class="view-toggle-btn active" id="btnGrid" onclick="setView('grid')">⊞ Cards</button>
            <button class="view-toggle-btn" id="btnList" onclick="setView('list')">☰ List</button>
        </div>
        <button type="button" class="btn btn-primary" onclick="openModal('addStaffModal')">+ Add Staff Member</button>
    </div>
</div>

<?php if ($message): ?>
<div style="padding:14px 18px;background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.3);color:#6ee7b7;border-radius:10px;margin-bottom:20px;">✓ <?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div style="padding:14px 18px;background:rgba(244,63,94,0.12);border:1px solid rgba(244,63,94,0.3);color:#fda4af;border-radius:10px;margin-bottom:20px;">⚠️ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div id="upload-toast" style="display:none;position:fixed;bottom:24px;right:24px;z-index:9999;padding:14px 20px;border-radius:12px;font-size:0.88rem;font-weight:600;max-width:340px;box-shadow:0 8px 24px rgba(0,0,0,0.5);"></div>
<!-- CARD GRID VIEW -->
<div id="view-grid">
<?php if (empty($staffList)): ?>
    <div class="glass-card" style="text-align:center;padding:40px;color:#64748b;">No staff members found.</div>
<?php else: ?>
<div class="staff-grid">
<?php foreach ($staffList as $st):
    $hasPhoto = !empty($st['profile_photo']);
    $photoUrl = $hasPhoto ? $baseUri . '/assets/images/staff/' . htmlspecialchars($st['profile_photo']) : '';
    $words    = array_filter(explode(' ', trim($st['full_name'])));
    $initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(array_values($words), 0, 2)));
?>
<div class="staff-card" id="card-<?= $st['id'] ?>">
    <div class="staff-card-photo" id="photo-area-<?= $st['id'] ?>">
        <?php if ($hasPhoto): ?>
            <img src="<?= $photoUrl ?>" alt="<?= htmlspecialchars($st['full_name']) ?>" id="photo-img-<?= $st['id'] ?>">
        <?php else: ?>
            <div class="photo-placeholder" id="photo-placeholder-<?= $st['id'] ?>"><?= htmlspecialchars($initials) ?></div>
        <?php endif; ?>
        <div class="photo-upload-overlay">
            <label class="photo-upload-btn">
                📤 Upload Photo
                <input type="file" accept="image/jpeg,image/png,image/webp" style="display:none;" onchange="uploadPhoto(<?= $st['id'] ?>, this)">
            </label>
            <button class="photo-remove-btn" id="remove-btn-<?= $st['id'] ?>" onclick="removePhoto(<?= $st['id'] ?>, event)" style="<?= $hasPhoto ? '' : 'display:none;' ?>">🗑 Remove Photo</button>
        </div>
        <div class="upload-progress" id="progress-<?= $st['id'] ?>"></div>
    </div>
    <div style="padding:18px;flex:1;">
        <div style="font-size:1rem;font-weight:700;color:var(--text-primary);margin-bottom:2px;"><?= htmlspecialchars($st['full_name']) ?></div>
        <div style="font-size:0.75rem;color:var(--primary);font-family:monospace;margin-bottom:4px;"><?= htmlspecialchars($st['staff_code']) ?></div>
        <div style="font-size:0.8rem;color:var(--text-secondary);margin-bottom:8px;"><?= htmlspecialchars($st['designation'] ?: 'Faculty Member') ?></div>
        <div style="font-size:0.78rem;color:var(--text-muted);margin-bottom:10px;">📞 <?= htmlspecialchars($st['phone'] ?: '—') ?></div>
        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;">
            <?php if ($st['salary_type'] === 'percentage'): ?>
                <span class="salary-pill-pct">📊 <?= number_format((float)$st['salary_percentage'],2) ?>% of Fees</span>
            <?php else: ?>
                <span class="salary-pill-fixed">💰 Rs. <?= number_format((float)$st['base_salary'],0) ?></span>
            <?php endif; ?>
            <span class="status-badge <?= $st['is_active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $st['is_active'] ? 'Active' : 'Inactive' ?></span>
        </div>
        <div style="font-size:0.75rem;color:var(--text-muted);">Payroll: <?= (int)$st['payroll_entries'] ?> months &bull; Rs. <?= number_format((float)$st['total_received'],0) ?> paid</div>
    </div>
    <div style="display:flex;gap:8px;padding:0 18px 18px;">
        <button class="btn btn-glass btn-sm" style="flex:1;" onclick="openEditStaffModal(<?= htmlspecialchars(json_encode($st),ENT_QUOTES) ?>)">✏️ Edit Info</button>
        <button class="btn btn-danger btn-sm" onclick="confirmDeleteStaff(<?= $st['id'] ?>,'<?= htmlspecialchars($st['full_name'],ENT_QUOTES) ?>')">🗑</button>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
<!-- LIST VIEW -->
<div id="view-list" style="display:none;">
<div class="glass-card">
<div class="table-responsive">
<table class="data-table">
<thead><tr><th style="width:56px;">Photo</th><th>Code</th><th>Full Name</th><th>Designation</th><th>Contact</th><th>Salary</th><th>Payroll</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
<tbody>
<?php if (empty($staffList)): ?>
<tr><td colspan="9" class="empty-state">No staff members found.</td></tr>
<?php else: foreach ($staffList as $st):
    $hasPhoto = !empty($st['profile_photo']);
    $photoUrl = $hasPhoto ? $baseUri . '/assets/images/staff/' . htmlspecialchars($st['profile_photo']) : '';
    $words    = array_filter(explode(' ', trim($st['full_name'])));
    $initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(array_values($words), 0, 2)));
?>
<tr>
<td>
    <label for="list-file-<?= $st['id'] ?>" style="cursor:pointer;display:block;position:relative;width:42px;height:42px;" title="Click to change photo">
        <?php if ($hasPhoto): ?>
            <img src="<?= $photoUrl ?>" id="list-img-<?= $st['id'] ?>" style="width:42px;height:42px;border-radius:50%;object-fit:cover;border:2px solid rgba(56,189,248,0.4);">
        <?php else: ?>
            <div id="list-img-<?= $st['id'] ?>" style="width:42px;height:42px;border-radius:50%;background:rgba(56,189,248,0.12);border:2px dashed rgba(56,189,248,0.3);display:flex;align-items:center;justify-content:center;font-size:0.8rem;color:#64748b;font-weight:700;"><?= htmlspecialchars($initials) ?></div>
        <?php endif; ?>
        <div style="position:absolute;bottom:-2px;right:-2px;background:#38bdf8;border-radius:50%;width:16px;height:16px;display:flex;align-items:center;justify-content:center;font-size:9px;color:#0a0f1d;font-weight:900;">📷</div>
        <input type="file" id="list-file-<?= $st['id'] ?>" accept="image/jpeg,image/png,image/webp" style="display:none;" onchange="uploadPhoto(<?= $st['id'] ?>, this, true)">
    </label>
</td>
<td><strong style="color:var(--primary-light);font-family:monospace;font-size:13px;"><?= htmlspecialchars($st['staff_code']) ?></strong></td>
<td><strong><?= htmlspecialchars($st['full_name']) ?></strong></td>
<td><?= htmlspecialchars($st['designation'] ?: 'Faculty Member') ?></td>
<td style="color:var(--text-muted);"><?= htmlspecialchars($st['phone'] ?: '—') ?></td>
<td><?php if ($st['salary_type']==='percentage'): ?><span class="salary-pill-pct">📊 <?= number_format((float)$st['salary_percentage'],2) ?>%</span><?php else: ?><span class="salary-pill-fixed">💰 Rs. <?= number_format((float)$st['base_salary'],0) ?></span><?php endif; ?></td>
<td><a href="../payroll/index.php?staff_id=<?= $st['id'] ?>" class="status-badge badge-active" style="text-decoration:none;"><?= (int)$st['payroll_entries'] ?> mo. (Rs. <?= number_format((float)$st['total_received'],0) ?>)</a></td>
<td><span class="status-badge <?= $st['is_active']?'badge-active':'badge-inactive' ?>"><?= $st['is_active']?'Active':'Inactive' ?></span></td>
<td style="text-align:right;"><div style="display:flex;gap:6px;justify-content:flex-end;"><button class="btn btn-glass btn-sm" onclick="openEditStaffModal(<?= htmlspecialchars(json_encode($st),ENT_QUOTES) ?>)">✏️ Edit</button><button class="btn btn-danger btn-sm" onclick="confirmDeleteStaff(<?= $st['id'] ?>,'<?= htmlspecialchars($st['full_name'],ENT_QUOTES) ?>')">🗑</button></div></td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div></div></div>
<!-- ADD MODAL -->
<div class="modal-overlay" id="addStaffModal"><div class="modal-dialog" style="max-width:540px;"><div class="modal-header"><h3 class="modal-title">➕ Register Staff Member</h3><button type="button" class="modal-close-btn" onclick="closeModal('addStaffModal')">&times;</button></div>
<form method="POST" action=""><?= getCsrfField() ?><input type="hidden" name="action" value="create">
<div class="modal-body">
<div style="display:grid;grid-template-columns:1fr 2fr;gap:16px;">
<div class="form-group"><label class="form-label">Staff Code <span class="required">*</span></label><input type="text" name="staff_code" class="form-control" required placeholder="STF-05" style="text-transform:uppercase;"></div>
<div class="form-group"><label class="form-label">Full Name <span class="required">*</span></label><input type="text" name="full_name" class="form-control" required placeholder="e.g. Dr. Muhammad Ishaq"></div>
</div>
<div class="form-group"><label class="form-label">Designation</label><input type="text" name="designation" class="form-control" placeholder="e.g. Senior Instructor"></div>
<div class="form-group"><label class="form-label">Contact Phone</label><input type="text" name="phone" class="form-control" placeholder="0345-xxxxxxx"></div>
<div class="form-group"><label class="form-label">Compensation Type <span class="required">*</span></label>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
<label id="add_card_fixed" onclick="setSalaryType('add','fixed')" style="cursor:pointer;border:2px solid rgba(56,189,248,0.5);border-radius:10px;padding:12px 14px;display:flex;align-items:center;gap:10px;background:rgba(56,189,248,0.1);transition:all 0.2s;"><input type="radio" name="salary_type" value="fixed" id="add_type_fixed" checked onchange="setSalaryType('add','fixed')" style="accent-color:#38bdf8;"><div><div style="font-weight:600;color:#7dd3fc;font-size:0.88rem;">💰 Fixed Monthly</div><div style="font-size:0.75rem;color:#64748b;">Set amount in PKR</div></div></label>
<label id="add_card_pct" onclick="setSalaryType('add','percentage')" style="cursor:pointer;border:2px solid rgba(255,255,255,0.08);border-radius:10px;padding:12px 14px;display:flex;align-items:center;gap:10px;background:transparent;transition:all 0.2s;"><input type="radio" name="salary_type" value="percentage" id="add_type_pct" onchange="setSalaryType('add','percentage')" style="accent-color:#fbbf24;"><div><div style="font-weight:600;color:#fde68a;font-size:0.88rem;">📊 Percentage of Fees</div><div style="font-size:0.75rem;color:#64748b;">% of fee collection</div></div></label>
</div></div>
<div class="form-group" id="add_fixed_group"><label class="form-label">Monthly Salary (PKR)</label><input type="number" name="base_salary" class="form-control" step="500" min="0" placeholder="e.g. 35000"></div>
<div class="form-group" id="add_pct_group" style="display:none;"><label class="form-label">Fee Collection Percentage (%)</label><div style="position:relative;"><input type="number" id="add_salary_pct" name="salary_percentage" class="form-control" step="0.01" min="0.01" max="100" placeholder="e.g. 10" style="padding-right:50px;" oninput="document.getElementById('add_pct_preview').textContent=parseFloat(this.value||0).toFixed(2)+'%'"><span style="position:absolute;right:14px;top:50%;transform:translateY(-50%);color:#fbbf24;font-weight:700;">%</span></div><p style="font-size:0.75rem;color:#94a3b8;margin-top:6px;">💡 Will earn <strong style="color:#fde68a;" id="add_pct_preview">0%</strong> of monthly fees.</p></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-glass" onclick="closeModal('addStaffModal')">Cancel</button><button type="submit" class="btn btn-primary">Save Member</button></div>
</form></div></div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="editStaffModal"><div class="modal-dialog" style="max-width:540px;"><div class="modal-header"><h3 class="modal-title">✏️ Edit Staff Profile</h3><button type="button" class="modal-close-btn" onclick="closeModal('editStaffModal')">&times;</button></div>
<form method="POST" action=""><?= getCsrfField() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" id="edit_staff_id">
<div class="modal-body">
<div class="form-group"><label class="form-label">Staff Code</label><input type="text" id="edit_staff_code" class="form-control" disabled style="opacity:0.6;"></div>
<div class="form-group"><label class="form-label">Full Name <span class="required">*</span></label><input type="text" id="edit_staff_name" name="full_name" class="form-control" required></div>
<div class="form-group"><label class="form-label">Designation</label><input type="text" id="edit_staff_desig" name="designation" class="form-control"></div>
<div class="form-group"><label class="form-label">Phone</label><input type="text" id="edit_staff_phone" name="phone" class="form-control"></div>
<div class="form-group"><label class="form-label">Compensation Type <span class="required">*</span></label>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
<label id="edit_card_fixed" onclick="setSalaryType('edit','fixed')" style="cursor:pointer;border:2px solid rgba(255,255,255,0.08);border-radius:10px;padding:12px 14px;display:flex;align-items:center;gap:10px;background:transparent;transition:all 0.2s;"><input type="radio" name="salary_type" value="fixed" id="edit_type_fixed" onchange="setSalaryType('edit','fixed')" style="accent-color:#38bdf8;"><div><div style="font-weight:600;color:#7dd3fc;font-size:0.88rem;">💰 Fixed Monthly</div><div style="font-size:0.75rem;color:#64748b;">Set amount in PKR</div></div></label>
<label id="edit_card_pct" onclick="setSalaryType('edit','percentage')" style="cursor:pointer;border:2px solid rgba(255,255,255,0.08);border-radius:10px;padding:12px 14px;display:flex;align-items:center;gap:10px;background:transparent;transition:all 0.2s;"><input type="radio" name="salary_type" value="percentage" id="edit_type_pct" onchange="setSalaryType('edit','percentage')" style="accent-color:#fbbf24;"><div><div style="font-weight:600;color:#fde68a;font-size:0.88rem;">📊 Percentage of Fees</div><div style="font-size:0.75rem;color:#64748b;">% of fee collection</div></div></label>
</div></div>
<div class="form-group" id="edit_fixed_group"><label class="form-label">Monthly Salary (PKR)</label><input type="number" id="edit_base_salary" name="base_salary" class="form-control" step="500" min="0"></div>
<div class="form-group" id="edit_pct_group" style="display:none;"><label class="form-label">Fee Collection Percentage (%)</label><div style="position:relative;"><input type="number" id="edit_salary_pct" name="salary_percentage" class="form-control" step="0.01" min="0.01" max="100" style="padding-right:50px;" oninput="document.getElementById('edit_pct_preview').textContent=parseFloat(this.value||0).toFixed(2)+'%'"><span style="position:absolute;right:14px;top:50%;transform:translateY(-50%);color:#fbbf24;font-weight:700;">%</span></div><p style="font-size:0.75rem;color:#94a3b8;margin-top:6px;">💡 Will earn <strong style="color:#fde68a;" id="edit_pct_preview">0%</strong> of monthly fees.</p></div>
<div class="form-group" style="display:flex;align-items:center;gap:10px;padding:12px 14px;background:rgba(255,255,255,0.03);border-radius:8px;border:1px solid rgba(255,255,255,0.06);"><input type="checkbox" id="edit_staff_active" name="is_active" value="1" style="width:18px;height:18px;accent-color:#22c55e;"><label for="edit_staff_active" style="font-size:14px;color:var(--text-primary);cursor:pointer;margin:0;">Active Faculty Member</label></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-glass" onclick="closeModal('editStaffModal')">Cancel</button><button type="submit" class="btn btn-primary">Update Profile</button></div>
</form></div></div>

<form id="delete-staff-form" method="POST" action="" style="display:none;"><?= getCsrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" id="delete_staff_id"></form>
<script>
const CSRF_TOKEN = "<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES) ?>";
const BASE_URI   = "<?= $baseUri ?>";

function setView(v){
    document.getElementById('view-grid').style.display = v==='grid'?'':'none';
    document.getElementById('view-list').style.display = v==='list'?'':'none';
    document.getElementById('btnGrid').classList.toggle('active',v==='grid');
    document.getElementById('btnList').classList.toggle('active',v==='list');
    localStorage.setItem('staffView',v);
}
document.addEventListener('DOMContentLoaded',()=>{
    setView(localStorage.getItem('staffView')||'grid');
    if(new URLSearchParams(window.location.search).get('new')==='1') openModal('addStaffModal');
});

function showToast(msg,type){
    const t=document.getElementById('upload-toast');
    t.textContent=msg; t.style.display='block';
    t.style.background=type==='success'?'rgba(16,185,129,0.95)':'rgba(244,63,94,0.95)';
    t.style.color='#fff';
    clearTimeout(t._timer); t._timer=setTimeout(()=>{t.style.display='none';},4000);
}

function uploadPhoto(staffId,input,isList){
    const file=input.files[0]; if(!file) return;
    if(!['image/jpeg','image/jpg','image/png','image/webp'].includes(file.type)){showToast('Only JPG, PNG and WebP allowed.','error');input.value='';return;}
    if(file.size>3*1024*1024){showToast('Max file size is 3 MB.','error');input.value='';return;}
    const fd=new FormData();
    fd.append('photo',file); fd.append('staff_id',staffId); fd.append('csrf_token',CSRF_TOKEN);
    const bar=document.getElementById('progress-'+staffId);
    if(bar){bar.style.width='0';}
    const xhr=new XMLHttpRequest();
    xhr.open('POST',BASE_URI+'/api/staff/upload_photo.php',true);
    if(bar) xhr.upload.onprogress=e=>{if(e.lengthComputable) bar.style.width=Math.round(e.loaded/e.total*100)+'%';};
    xhr.onload=function(){
        if(bar) setTimeout(()=>{bar.style.width='0';},600);
        let res; try{res=JSON.parse(xhr.responseText);}catch{res={success:false,error:'Server error.'};}
        if(res.success){
            const url=res.photo_url+'?t='+Date.now();
            // update card view
            let imgEl=document.getElementById('photo-img-'+staffId);
            const ph=document.getElementById('photo-placeholder-'+staffId);
            const area=document.getElementById('photo-area-'+staffId);
            const rmBtn=document.getElementById('remove-btn-'+staffId);
            if(!imgEl && area){
                imgEl=document.createElement('img');
                imgEl.id='photo-img-'+staffId; imgEl.alt='Staff photo';
                imgEl.style.cssText='width:100%;height:100%;object-fit:cover;position:absolute;inset:0;';
                area.insertBefore(imgEl,area.firstChild);
            }
            if(imgEl) imgEl.src=url;
            if(ph) ph.style.display='none';
            if(rmBtn) rmBtn.style.display='flex';
            // update list view
            const li=document.getElementById('list-img-'+staffId);
            if(li){
                const ni=document.createElement('img');
                ni.src=url; ni.id='list-img-'+staffId;
                ni.style.cssText='width:42px;height:42px;border-radius:50%;object-fit:cover;border:2px solid rgba(56,189,248,0.4);';
                li.replaceWith(ni);
            }
            showToast('Photo uploaded successfully!','success');
        } else { showToast(res.error||'Upload failed.','error'); }
        input.value='';
    };
    xhr.onerror=()=>{showToast('Network error.','error');input.value='';};
    xhr.send(fd);
}

function removePhoto(staffId,e){
    e.stopPropagation();
    if(!confirm('Remove this staff member\'s profile photo?')) return;
    const fd=new FormData();
    fd.append('staff_id',staffId); fd.append('csrf_token',CSRF_TOKEN); fd.append('remove','1');
    fetch(BASE_URI+'/api/staff/remove_photo.php',{method:'POST',body:fd})
        .then(r=>r.json()).then(res=>{
            if(res.success){
                const imgEl=document.getElementById('photo-img-'+staffId);
                const ph=document.getElementById('photo-placeholder-'+staffId);
                const rmBtn=document.getElementById('remove-btn-'+staffId);
                if(imgEl) imgEl.remove();
                if(ph) ph.style.display='flex';
                if(rmBtn) rmBtn.style.display='none';
                const li=document.getElementById('list-img-'+staffId);
                if(li && li.tagName==='IMG'){
                    const div=document.createElement('div');
                    div.id='list-img-'+staffId;
                    div.style.cssText='width:42px;height:42px;border-radius:50%;background:rgba(56,189,248,0.12);border:2px dashed rgba(56,189,248,0.3);display:flex;align-items:center;justify-content:center;font-size:0.8rem;color:#64748b;font-weight:700;';
                    div.textContent='?'; li.replaceWith(div);
                }
                showToast('Photo removed.','success');
            } else { showToast(res.error||'Failed to remove.','error'); }
        }).catch(()=>showToast('Network error.','error'));
}

function setSalaryType(prefix,type){
    const isFixed=type==='fixed';
    const fc=document.getElementById(prefix+'_card_fixed');
    const pc=document.getElementById(prefix+'_card_pct');
    if(isFixed){fc.style.border='2px solid rgba(56,189,248,0.5)';fc.style.background='rgba(56,189,248,0.1)';pc.style.border='2px solid rgba(255,255,255,0.08)';pc.style.background='transparent';document.getElementById(prefix+'_type_fixed').checked=true;}
    else{pc.style.border='2px solid rgba(251,191,36,0.5)';pc.style.background='rgba(251,191,36,0.08)';fc.style.border='2px solid rgba(255,255,255,0.08)';fc.style.background='transparent';document.getElementById(prefix+'_type_pct').checked=true;}
    document.getElementById(prefix+'_fixed_group').style.display=isFixed?'':'none';
    document.getElementById(prefix+'_pct_group').style.display=isFixed?'none':'';
    const sp=document.getElementById(prefix+'_salary_pct'); if(sp&&isFixed) sp.value='';
    const bs=document.getElementById(prefix+'_base_salary'); if(bs&&!isFixed) bs.value='';
}

function openEditStaffModal(s){
    document.getElementById('edit_staff_id').value=s.id;
    document.getElementById('edit_staff_code').value=s.staff_code||'';
    document.getElementById('edit_staff_name').value=s.full_name||'';
    document.getElementById('edit_staff_desig').value=s.designation||'';
    document.getElementById('edit_staff_phone').value=s.phone||'';
    document.getElementById('edit_staff_active').checked=parseInt(s.is_active)===1;
    const t=s.salary_type||'fixed'; setSalaryType('edit',t);
    if(t==='percentage'){document.getElementById('edit_salary_pct').value=s.salary_percentage||'';document.getElementById('edit_pct_preview').textContent=parseFloat(s.salary_percentage||0).toFixed(2)+'%';}
    else{document.getElementById('edit_base_salary').value=s.base_salary||'';}
    openModal('editStaffModal');
}

function confirmDeleteStaff(id,name){
    confirmAction('Delete Staff Member?',`Are you sure you want to remove '${name}'? Their profile photo will also be deleted.`,()=>{
        document.getElementById('delete_staff_id').value=id;
        document.getElementById('delete-staff-form').submit();
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>