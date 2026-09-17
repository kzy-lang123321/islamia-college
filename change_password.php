<?php
/**
 * Forced First-Time Password Change
 * Islamia College of Technology College Management System
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Must be logged in
checkAuth();

$baseUri  = strpos($_SERVER['REQUEST_URI'], '/islamia%20college') !== false ? '/islamia%20college' : '';
$user     = getCurrentUser();
$message  = '';
$msgType  = 'error';
$forced   = (int)($_SESSION['must_change_password'] ?? 0) === 1;

// If not forced and visiting voluntarily — still allow
// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    validateCsrfRequest();

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword     = $_POST['new_password']      ?? '';
    $confirmPassword = $_POST['confirm_password']  ?? '';

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $message = 'All fields are required.';
    } elseif (strlen($newPassword) < 6) {
        $message = 'New password must be at least 6 characters.';
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'New passwords do not match.';
    } elseif ($newPassword === $currentPassword) {
        $message = 'New password must be different from the current one.';
    } else {
        try {
            $pdo  = Database::getConnection();
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $row  = $stmt->fetch();

            if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
                $message = 'Current password is incorrect.';
            } else {
                $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                $upd     = $pdo->prepare("UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?");
                $upd->execute([$newHash, $user['id']]);

                // Clear the forced flag from session
                $_SESSION['must_change_password'] = 0;

                $message = 'Password changed successfully! Redirecting to dashboard…';
                $msgType = 'success';
                header("refresh:2;url={$baseUri}/index.php");
            }
        } catch (Exception $e) {
            error_log('Change password error: ' . $e->getMessage());
            $message = 'An error occurred. Please try again.';
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
    <title><?= $forced ? 'Set Your New Password' : 'Change Password' ?> | Islamia College Management System</title>
    <link rel="stylesheet" href="<?= $baseUri ?>/assets/css/futuristic_glass.css">
    <style>
        body { margin:0; font-family:system-ui,sans-serif; }
        .cp-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            background: #0a0f1d radial-gradient(circle at 50% 20%, #0f1c3f 0%, #0a0f1d 80%);
        }
        .cp-card {
            width: 100%;
            max-width: 480px;
            background: #131d35;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 40px 36px;
            box-shadow: 0 24px 60px rgba(0,0,0,0.6), 0 0 30px rgba(2,132,199,0.12);
        }
        .cp-brand { text-align:center; margin-bottom:24px; }
        .cp-logo { width:65px; height:65px; border-radius:14px; background:#0a0f1d; border:2px solid rgba(56,189,248,0.3); padding:6px; object-fit:contain; margin-bottom:12px; }
        .cp-title { font-size:1.35rem; font-weight:700; color:#f8fafc; margin-bottom:4px; }
        .cp-subtitle { font-size:0.85rem; color:#94a3b8; }

        /* Forced banner */
        .forced-banner {
            background: rgba(251,191,36,0.1);
            border: 1px solid rgba(251,191,36,0.35);
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 22px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 0.85rem;
            color: #fde68a;
            line-height: 1.5;
        }

        .form-group { margin-bottom: 20px; }
        .form-label { display:block; font-size:0.85rem; font-weight:600; color:#cbd5e1; margin-bottom:8px; }
        .input-group { position:relative; display:flex; align-items:center; }
        .input-icon { position:absolute; left:14px; color:#64748b; font-size:1rem; pointer-events:none; }
        .form-control-icon {
            width:100%; padding:13px 44px 13px 42px;
            background:#0b1329; border:1px solid rgba(255,255,255,0.12);
            border-radius:10px; color:#f8fafc; font-size:0.92rem; transition:all 0.2s ease;
        }
        .form-control-icon:focus { outline:none; border-color:#38bdf8; background:#0f1c3f; box-shadow:0 0 0 3px rgba(56,189,248,0.2); }
        .toggle-btn { position:absolute; right:12px; background:none; border:none; color:#94a3b8; cursor:pointer; padding:6px; font-size:1.1rem; transition:color 0.15s; }
        .toggle-btn:hover { color:#38bdf8; }

        .alert-error { background:rgba(244,63,94,0.12); border:1px solid rgba(244,63,94,0.3); color:#fda4af; padding:12px 16px; border-radius:10px; font-size:0.88rem; margin-bottom:20px; display:flex; align-items:center; gap:10px; }
        .alert-success { background:rgba(34,197,94,0.12); border:1px solid rgba(34,197,94,0.3); color:#86efac; padding:12px 16px; border-radius:10px; font-size:0.88rem; margin-bottom:20px; display:flex; align-items:center; gap:10px; }

        .btn-submit {
            width:100%; padding:14px;
            background:linear-gradient(135deg,#0284c7 0%,#0369a1 100%);
            border:1px solid rgba(56,189,248,0.3); border-radius:10px;
            color:#fff; font-size:0.95rem; font-weight:600; cursor:pointer;
            transition:all 0.2s ease; box-shadow:0 4px 14px rgba(2,132,199,0.35);
            display:flex; align-items:center; justify-content:center; gap:8px;
        }
        .btn-submit:hover:not(:disabled) { transform:translateY(-1px); box-shadow:0 6px 20px rgba(2,132,199,0.45); }
        .btn-submit:disabled { opacity:0.6; cursor:not-allowed; }

        .skip-link { display:block; text-align:center; margin-top:16px; color:#64748b; font-size:0.82rem; text-decoration:none; }
        .skip-link:hover { color:#94a3b8; text-decoration:underline; }

        .strength-bar { margin-top:6px; height:4px; border-radius:4px; background:#1e293b; overflow:hidden; }
        .strength-fill { height:100%; width:0; border-radius:4px; transition:width 0.3s,background 0.3s; }
        .strength-label { font-size:0.75rem; color:#64748b; margin-top:4px; }

        .user-badge {
            display:inline-flex; align-items:center; gap:6px;
            background:#0b1329; border:1px solid rgba(56,189,248,0.2);
            border-radius:8px; padding:6px 12px; font-size:0.82rem; color:#7dd3fc;
            margin-bottom:20px;
        }
    </style>
</head>
<body>
<div class="cp-wrapper">
    <div class="cp-card">

        <div class="cp-brand">
            <img src="<?= $baseUri ?>/assets/images/logo.png" alt="ICT Logo" class="cp-logo">
            <h1 class="cp-title"><?= $forced ? '🔐 Set Your New Password' : '🔑 Change Password' ?></h1>
            <p class="cp-subtitle">Islamia College of Technology &bull; Matta, Swat</p>
        </div>

        <div class="user-badge">
            👤 <?= htmlspecialchars($user['full_name']) ?> &nbsp;|&nbsp; <?= ucfirst(htmlspecialchars($user['role'])) ?>
        </div>

        <?php if ($forced && $msgType !== 'success'): ?>
            <div class="forced-banner">
                <span style="font-size:1.3rem;flex-shrink:0;">⚠️</span>
                <div>
                    <strong>Action Required — First Login</strong><br>
                    You are using the default password. You must set a new personal password before you can access the system.
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($message)): ?>
            <div class="<?= $msgType === 'success' ? 'alert-success' : 'alert-error' ?>">
                <span><?= $msgType === 'success' ? '✅' : '⚠️' ?></span>
                <span><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($msgType !== 'success'): ?>
        <form method="POST" action="" id="cp-form">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="change_password">

            <div class="form-group">
                <label class="form-label" for="current_password">Current Password</label>
                <div class="input-group">
                    <span class="input-icon">🔒</span>
                    <input type="password" id="current_password" name="current_password"
                           class="form-control-icon" required
                           placeholder="<?= $forced ? 'Enter default password (12345678)' : 'Enter current password' ?>"
                           autocomplete="current-password">
                    <button type="button" class="toggle-btn" onclick="toggleVis('current_password',this)">👁️</button>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="new_password">New Password</label>
                <div class="input-group">
                    <span class="input-icon">🛡️</span>
                    <input type="password" id="new_password" name="new_password"
                           class="form-control-icon" required
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
                    <span class="input-icon">🛡️</span>
                    <input type="password" id="confirm_password" name="confirm_password"
                           class="form-control-icon" required
                           placeholder="Re-enter new password"
                           autocomplete="new-password">
                    <button type="button" class="toggle-btn" onclick="toggleVis('confirm_password',this)">👁️</button>
                </div>
            </div>

            <button type="submit" class="btn-submit" id="submit-btn">
                🔑 Save New Password
            </button>
        </form>

        <?php if (!$forced): ?>
            <a href="<?= $baseUri ?>/index.php" class="skip-link">← Back to Dashboard</a>
        <?php endif; ?>
        <?php endif; // not success ?>

    </div>
</div>

<script>
function toggleVis(id, btn) {
    const inp = document.getElementById(id);
    if (inp.type === 'password') { inp.type = 'text'; btn.textContent = '🙈'; }
    else { inp.type = 'password'; btn.textContent = '👁️'; }
}

function checkStrength(val) {
    const fill  = document.getElementById('str-fill');
    const label = document.getElementById('str-label');
    let score = 0;
    if (val.length >= 6)  score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const levels = [
        {pct:'20%', color:'#ef4444', text:'Very Weak'},
        {pct:'40%', color:'#f97316', text:'Weak'},
        {pct:'60%', color:'#eab308', text:'Fair'},
        {pct:'80%', color:'#22c55e', text:'Strong'},
        {pct:'100%', color:'#16a34a', text:'Very Strong'},
    ];
    const lvl = levels[Math.max(0, score - 1)] || levels[0];
    fill.style.width      = val.length ? lvl.pct   : '0';
    fill.style.background = val.length ? lvl.color : '';
    label.textContent     = val.length ? lvl.text  : '';
    label.style.color     = lvl.color;
}

document.getElementById('cp-form')?.addEventListener('submit', function() {
    const btn = document.getElementById('submit-btn');
    btn.disabled = true;
    btn.textContent = 'Saving…';
});
</script>
</body>
</html>
