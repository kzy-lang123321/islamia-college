<?php
/**
 * Password Reset Page
 * Islamia College of Technology College Management System
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$baseUri  = strpos($_SERVER['REQUEST_URI'], '/islamia%20college') !== false ? '/islamia%20college' : '';
$token    = trim($_GET['token'] ?? '');
$message  = '';
$msgType  = 'error';
$tokenValid = false;
$userId   = null;

if (!empty($token)) {
    try {
        $pdo       = Database::getConnection();
        $tokenHash = hash('sha256', $token);
        $stmt      = $pdo->prepare("
            SELECT pr.user_id, pr.expires_at, pr.used
            FROM   password_resets pr
            WHERE  pr.token_hash = ?
            LIMIT  1
        ");
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch();
        if ($row && !(int)$row['used'] && strtotime($row['expires_at']) > time()) {
            $tokenValid = true;
            $userId     = (int)$row['user_id'];
        } else {
            $message = 'This reset link is invalid or has already expired. Please request a new one.';
        }
    } catch (Exception $e) {
        error_log('Reset token validation error: ' . $e->getMessage());
        $message = 'A system error occurred. Please try again later.';
    }
} else {
    $message = 'No reset token provided. Please use the link sent to you.';
}

$success = false;
if ($tokenValid && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'do_reset') {
    validateCsrfRequest();
    $newPassword = $_POST['new_password']     ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) < 6) {
        $message = 'Password must be at least 6 characters long.';
    } elseif ($newPassword !== $confirmPass) {
        $message = 'Passwords do not match. Please try again.';
    } else {
        try {
            $pdo     = Database::getConnection();
            $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $upd = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $upd->execute([$newHash, $userId]);
            $tokenHash = hash('sha256', $token);
            $del = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token_hash = ?");
            $del->execute([$tokenHash]);
            $success = true;
            $message = 'Your password has been reset successfully. You can now sign in.';
            $msgType = 'success';
        } catch (Exception $e) {
            error_log('Password reset save error: ' . $e->getMessage());
            $message = 'Could not update your password. Please try again.';
        }
    }
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Islamia College Management System</title>
    <link rel="stylesheet" href="<?= $baseUri ?>/assets/css/futuristic_glass.css">
    <style>
        .rp-wrapper { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px 16px; background:#0a0f1d radial-gradient(circle at 50% 20%,#0f1c3f 0%,#0a0f1d 80%); }
        .rp-card { width:100%; max-width:440px; background:#131d35; border:1px solid rgba(255,255,255,0.1); border-radius:20px; padding:40px 36px; box-shadow:0 24px 60px rgba(0,0,0,0.6),0 0 30px rgba(2,132,199,0.12); }
        .rp-brand { text-align:center; margin-bottom:28px; }
        .rp-logo { width:70px; height:70px; border-radius:16px; background:#0a0f1d; border:2px solid rgba(56,189,248,0.3); padding:6px; object-fit:contain; margin-bottom:12px; }
        .rp-title { font-size:1.4rem; font-weight:700; color:#f8fafc; margin-bottom:4px; }
        .rp-subtitle { font-size:0.85rem; color:#94a3b8; }
        .form-group { margin-bottom:20px; }
        .form-label { display:block; font-size:0.85rem; font-weight:600; color:#cbd5e1; margin-bottom:8px; }
        .input-group { position:relative; display:flex; align-items:center; }
        .input-icon { position:absolute; left:14px; color:#64748b; font-size:1rem; pointer-events:none; }
        .form-control-with-icon { width:100%; padding:13px 44px 13px 42px; background:#0b1329; border:1px solid rgba(255,255,255,0.12); border-radius:10px; color:#f8fafc; font-size:0.92rem; transition:all 0.2s ease; }
        .form-control-with-icon:focus { outline:none; border-color:#38bdf8; background:#0f1c3f; box-shadow:0 0 0 3px rgba(56,189,248,0.2); }
        .toggle-btn { position:absolute; right:12px; background:none; border:none; color:#94a3b8; cursor:pointer; padding:6px; font-size:1.1rem; transition:color 0.15s; }
        .toggle-btn:hover { color:#38bdf8; }
        .alert-error { background:rgba(244,63,94,0.12); border:1px solid rgba(244,63,94,0.3); color:#fda4af; padding:12px 16px; border-radius:10px; font-size:0.88rem; margin-bottom:20px; display:flex; align-items:center; gap:10px; }
        .alert-success { background:rgba(34,197,94,0.12); border:1px solid rgba(34,197,94,0.3); color:#86efac; padding:12px 16px; border-radius:10px; font-size:0.88rem; margin-bottom:20px; display:flex; align-items:center; gap:10px; }
        .btn-submit { width:100%; padding:14px; background:linear-gradient(135deg,#0284c7 0%,#0369a1 100%); border:1px solid rgba(56,189,248,0.3); border-radius:10px; color:#fff; font-size:0.95rem; font-weight:600; cursor:pointer; transition:all 0.2s ease; box-shadow:0 4px 14px rgba(2,132,199,0.35); display:flex; align-items:center; justify-content:center; gap:8px; }
        .btn-submit:hover { transform:translateY(-1px); box-shadow:0 6px 20px rgba(2,132,199,0.45); }
        .back-link { display:block; text-align:center; margin-top:18px; color:#38bdf8; font-size:0.85rem; text-decoration:none; }
        .back-link:hover { text-decoration:underline; }
        .strength-bar { margin-top:6px; height:4px; border-radius:4px; background:#1e293b; overflow:hidden; }
        .strength-fill { height:100%; width:0; border-radius:4px; transition:width 0.3s,background 0.3s; }
        .strength-label { font-size:0.75rem; color:#64748b; margin-top:4px; }
    </style>
</head>
<body>
<div class="rp-wrapper">
    <div class="rp-card">
        <div class="rp-brand">
            <img src="<?= $baseUri ?>/assets/images/logo.png" alt="ICT Logo" class="rp-logo">
            <h1 class="rp-title">Reset Password</h1>
            <p class="rp-subtitle">Islamia College of Technology &bull; Matta, Swat</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="<?= $msgType === 'success' ? 'alert-success' : 'alert-error' ?>">
                <span><?= $msgType === 'success' ? '✅' : '⚠️' ?></span>
                <span><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <a href="<?= $baseUri ?>/login.php" class="btn-submit" style="text-decoration:none;">
                🔐 Go to Sign In
            </a>
        <?php elseif ($tokenValid): ?>
            <form method="POST" action="" id="reset-form">
                <?= getCsrfField() ?>
                <input type="hidden" name="action" value="do_reset">

                <div class="form-group">
                    <label class="form-label" for="new_password">New Password</label>
                    <div class="input-group">
                        <span class="input-icon">🔒</span>
                        <input type="password" id="new_password" name="new_password"
                               class="form-control-with-icon" required
                               placeholder="At least 6 characters"
                               autocomplete="new-password"
                               oninput="checkStrength(this.value)">
                        <button type="button" class="toggle-btn" onclick="toggleVis('new_password',this)">👁️</button>
                    </div>
                    <div class="strength-bar"><div class="strength-fill" id="str-fill"></div></div>
                    <div class="strength-label" id="str-label"></div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm New Password</label>
                    <div class="input-group">
                        <span class="input-icon">🔒</span>
                        <input type="password" id="confirm_password" name="confirm_password"
                               class="form-control-with-icon" required
                               placeholder="Re-enter new password"
                               autocomplete="new-password">
                        <button type="button" class="toggle-btn" onclick="toggleVis('confirm_password',this)">👁️</button>
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="submit-btn">
                    🔑 Set New Password
                </button>
            </form>
        <?php else: ?>
            <a href="<?= $baseUri ?>/login.php" class="btn-submit" style="text-decoration:none;">
                ← Back to Sign In
            </a>
        <?php endif; ?>

        <a href="<?= $baseUri ?>/login.php" class="back-link">← Back to Sign In</a>
    </div>
</div>
<script>
function toggleVis(id, btn) {
    const inp = document.getElementById(id);
    if (inp.type === 'password') { inp.type = 'text'; btn.textContent = '🙈'; }
    else { inp.type = 'password'; btn.textContent = '👁️'; }
}
function checkStrength(val) {
    const fill = document.getElementById('str-fill');
    const label = document.getElementById('str-label');
    let score = 0;
    if (val.length >= 6)  score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const levels = [
        {pct:'20%',color:'#ef4444',text:'Very Weak'},
        {pct:'40%',color:'#f97316',text:'Weak'},
        {pct:'60%',color:'#eab308',text:'Fair'},
        {pct:'80%',color:'#22c55e',text:'Strong'},
        {pct:'100%',color:'#16a34a',text:'Very Strong'},
    ];
    const lvl = levels[Math.max(0, score-1)] || levels[0];
    fill.style.width      = val.length ? lvl.pct   : '0';
    fill.style.background = val.length ? lvl.color : '';
    label.textContent     = val.length ? lvl.text  : '';
    label.style.color     = lvl.color;
}
document.getElementById('reset-form')?.addEventListener('submit', function() {
    const btn = document.getElementById('submit-btn');
    btn.disabled = true;
    btn.textContent = 'Saving…';
});
</script>
</body>
</html>
