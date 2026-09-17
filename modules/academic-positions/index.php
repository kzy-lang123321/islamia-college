<?php
/**
 * Academic Positions & Merit Awards Module
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
            $error = "Insufficient permissions to record academic distinctions.";
        } else {
            $studentName = trim($_POST['student_name'] ?? '');
            $session     = trim($_POST['session_name'] ?? '');
            $courseName  = trim($_POST['course_name'] ?? '');
            $rank        = (int)($_POST['position_rank'] ?? 1);
            $award       = trim($_POST['award_title'] ?? 'Merit Certificate');
            $marks       = !empty($_POST['marks_obtained']) ? (int)$_POST['marks_obtained'] : null;
            $totalMarks  = !empty($_POST['total_marks']) ? (int)$_POST['total_marks'] : 1100;
            $studentId   = !empty($_POST['student_id']) ? (int)$_POST['student_id'] : null;

            if (empty($studentName) || empty($session) || empty($courseName)) {
                $error = "Student name, session, and course are required.";
            } else {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO academic_positions (student_id, session_name, course_name, position_rank, student_name, marks_obtained, total_marks, remarks)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$studentId, $session, $courseName, $rank, $studentName, $marks, $totalMarks, $award]);
                    $message = "Academic merit distinction recorded successfully.";
                } catch (PDOException $e) {
                    error_log("Create academic position error: " . $e->getMessage());
                    $error = "An error occurred while saving the achievement record.";
                }
            }
        }
    } elseif ($action === 'delete') {
        if (!$canManage) {
            $error = "Insufficient permissions to delete academic distinctions.";
        } else {
            $id = (int)($_POST['id'] ?? 0);
            try {
                $stmt = $pdo->prepare("DELETE FROM academic_positions WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Distinction record deleted successfully.";
            } catch (PDOException $e) {
                error_log("Delete academic position error: " . $e->getMessage());
                $error = "An error occurred while removing the distinction record.";
            }
        }
    }
}

$positions = $pdo->query("
    SELECT p.*, s.student_reg_no, s.id as student_ref_id
    FROM academic_positions p
    LEFT JOIN students s ON p.student_id = s.id
    ORDER BY p.position_rank ASC, p.id DESC
")->fetchAll();

$students = $pdo->query("SELECT id, student_reg_no, full_name, father_name FROM students ORDER BY full_name ASC LIMIT 500")->fetchAll();

$pageTitle = "Academic Achievements & Honors";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Academic Achievements & Honors</h1>
        <p class="page-breadcrumb">KPK Trade Testing Board (TTB) top rankers, merit position holders, and institutional awards</p>
    </div>
    <?php if ($canManage): ?>
    <div>
        <button type="button" class="btn btn-primary" onclick="openModal('addPositionModal')">
            <span>+ Add Merit Distinction</span>
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
                    <th>Merit Rank</th>
                    <th>Candidate Name</th>
                    <th>Registration No.</th>
                    <th>Program / Technology</th>
                    <th>Academic Session</th>
                    <th>Marks / Total</th>
                    <th>Award / Remarks</th>
                    <?php if ($canManage): ?>
                        <th style="text-align:right;">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($positions)): ?>
                    <tr>
                        <td colspan="<?= $canManage ? 8 : 7 ?>" class="empty-state">
                            <div class="empty-state-icon">🏆</div>
                            <div class="empty-state-title">No merit positions recorded</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($positions as $p): ?>
                        <tr>
                            <td>
                                <strong style="color: <?= $p['position_rank'] == 1 ? '#fbbf24' : ($p['position_rank'] == 2 ? '#cbd5e1' : '#f97316') ?>; font-size: 15px;">
                                    🏆 Rank #<?= (int)$p['position_rank'] ?>
                                </strong>
                            </td>
                            <td>
                                <?php if ($p['student_ref_id']): ?>
                                    <a href="../students/view.php?id=<?= $p['student_ref_id'] ?>" style="color: var(--text-primary); text-decoration: none; font-weight: 600;">
                                        <?= htmlspecialchars($p['student_name']) ?>
                                    </a>
                                <?php else: ?>
                                    <strong><?= htmlspecialchars($p['student_name']) ?></strong>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-family: monospace; color: var(--primary-light);">
                                    <?= htmlspecialchars($p['student_reg_no'] ?: '—') ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($p['course_name'] ?: '—') ?></td>
                            <td><?= htmlspecialchars($p['session_name'] ?: '—') ?></td>
                            <td>
                                <?= $p['marks_obtained'] !== null ? "<strong>{$p['marks_obtained']}</strong> / " . (int)$p['total_marks'] : '—' ?>
                            </td>
                            <td style="color: var(--text-muted);"><?= htmlspecialchars($p['remarks'] ?: 'Official Distinction') ?></td>
                            <?php if ($canManage): ?>
                                <td style="text-align:right;">
                                    <button type="button" class="btn btn-danger btn-sm" onclick="confirmDeletePosition(<?= $p['id'] ?>, '<?= htmlspecialchars($p['student_name'], ENT_QUOTES) ?>')">Delete</button>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add Academic Position -->
<div class="modal-overlay" id="addPositionModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Record Merit Distinction</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('addPositionModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="link_student_id">Link to Registered Student</label>
                    <select id="link_student_id" name="student_id" class="form-control" onchange="autoFillStudent(this)">
                        <option value="">Choose registered student (optional)...</option>
                        <?php foreach ($students as $s): ?>
                            <option value="<?= $s['id'] ?>" data-name="<?= htmlspecialchars($s['full_name']) ?>">
                                <?= htmlspecialchars($s['student_reg_no']) ?> — <?= htmlspecialchars($s['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="pos_student_name">Candidate Full Name <span class="required">*</span></label>
                        <input type="text" id="pos_student_name" name="student_name" class="form-control" required placeholder="e.g. Asad Ali">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="pos_rank">Merit Rank <span class="required">*</span></label>
                        <select id="pos_rank" name="position_rank" class="form-control" required>
                            <option value="1">1st Position (Gold)</option>
                            <option value="2">2nd Position (Silver)</option>
                            <option value="3">3rd Position (Bronze)</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="pos_course">Course / Technology <span class="required">*</span></label>
                        <input type="text" id="pos_course" name="course_name" class="form-control" required placeholder="e.g. DIT Part-II">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="pos_session">Academic Session <span class="required">*</span></label>
                        <input type="text" id="pos_session" name="session_name" class="form-control" required placeholder="e.g. 2019-2020">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="pos_marks">Marks Obtained</label>
                        <input type="number" id="pos_marks" name="marks_obtained" class="form-control" placeholder="e.g. 980">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="pos_total">Total Marks</label>
                        <input type="number" id="pos_total" name="total_marks" class="form-control" value="1100">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="pos_remarks">Award Details / Remarks</label>
                    <input type="text" id="pos_remarks" name="award_title" class="form-control" placeholder="e.g. 1st in Swat District, TTB Certificate">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-glass" onclick="closeModal('addPositionModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Distinction</button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden Delete Form with CSRF -->
<form id="delete-pos-form" method="POST" action="" style="display:none;">
    <?= getCsrfField() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_pos_id">
</form>

<script>
function autoFillStudent(select) {
    const selectedOption = select.options[select.selectedIndex];
    const name = selectedOption.getAttribute('data-name');
    if (name) {
        document.getElementById('pos_student_name').value = name;
    }
}

function confirmDeletePosition(id, name) {
    confirmAction(
        'Delete Merit Award?',
        `Are you sure you want to remove the distinction record for '${name}'?`,
        () => {
            document.getElementById('delete_pos_id').value = id;
            document.getElementById('delete-pos-form').submit();
        }
    );
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
