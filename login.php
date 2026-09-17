<?php
/**
 * Hardened Enterprise Login Portal
 * Islamia College of Technology College Management System
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// If already authenticated, redirect to dashboard
if (!empty($_SESSION['user_id'])) {
    $base = strpos($_SERVER['REQUEST_URI'], '/islamia%20college') !== false ? '/islamia%20college' : '';
    header("Location: {$base}/index.php");
    exit;
}

$error = '';
$infoMessage = '';

if (isset($_GET['timeout'])) {
    $infoMessage = 'Your session has expired due to inactivity. Please sign in again.';
} elseif (isset($_GET['logged_out'])) {
    $infoMessage = 'You have been securely logged out.';
}

// Handle Login Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    validateCsrfRequest();
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';

    $res = loginUser($identifier, $password);
    if ($res['success']) {
        $base = strpos($_SERVER['REQUEST_URI'], '/islamia%20college') !== false ? '/islamia%20college' : '';
        if (!empty($res['must_change_password'])) {
            // First-time login: force password change
            header("Location: {$base}/change_password.php");
        } else {
            header("Location: {$base}/index.php");
        }
        exit;
    } else {
        $error = $res['error'];
    }
}

// Handle Password Reset Request
$resetNotification = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_request') {
    validateCsrfRequest();
    $resetIdentifier = trim($_POST['reset_identifier'] ?? '');
    $res = requestPasswordReset($resetIdentifier);
    $resetNotification = $res['message'];
}

$baseUri = strpos($_SERVER['REQUEST_URI'], '/islamia%20college') !== false ? '/islamia%20college' : '';
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | Islamia College of Technology Management System</title>
    <link rel="stylesheet" href="<?= $baseUri ?>/assets/css/futuristic_glass.css">
    <style>
        .login-page-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            background: #0a0f1d radial-gradient(circle at 50% 20%, #0f1c3f 0%, #0a0f1d 80%);
        }
        .login-card {
            width: 100%;
            max-width: 440px;
            background: #131d35;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 40px 36px;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.6), 0 0 30px rgba(2, 132, 199, 0.12);
        }
        .login-brand {
            text-align: center;
            margin-bottom: 32px;
        }
        .login-logo {
            width: 80px;
            height: 80px;
            border-radius: 18px;
            background: #0a0f1d;
            border: 2px solid rgba(56, 189, 248, 0.3);
            padding: 6px;
            object-fit: contain;
            margin-bottom: 14px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
        }
        .login-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #f8fafc;
            letter-spacing: -0.01em;
            margin-bottom: 4px;
        }
        .login-subtitle {
            font-size: 0.88rem;
            color: #94a3b8;
            font-weight: 400;
        }
        .form-group {
            margin-bottom: 22px;
        }
        .form-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #cbd5e1;
            margin-bottom: 8px;
        }
        .input-group {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-icon {
            position: absolute;
            left: 14px;
            color: #64748b;
            font-size: 1rem;
            pointer-events: none;
        }
        .form-control-with-icon {
            width: 100%;
            padding: 13px 44px 13px 42px;
            background: #0b1329;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 10px;
            color: #f8fafc;
            font-size: 0.92rem;
            transition: all 0.2s ease;
        }
        .form-control-with-icon:focus {
            outline: none;
            border-color: #38bdf8;
            background: #0f1c3f;
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.2);
        }
        .password-toggle-btn {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            transition: color 0.15s ease;
        }
        .password-toggle-btn:hover {
            color: #38bdf8;
        }
        .login-alert {
            background: rgba(244, 63, 94, 0.12);
            border: 1px solid rgba(244, 63, 94, 0.3);
            color: #fda4af;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 0.88rem;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .login-info {
            background: rgba(56, 189, 248, 0.12);
            border: 1px solid rgba(56, 189, 248, 0.3);
            color: #7dd3fc;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 0.88rem;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            border: 1px solid rgba(56, 189, 248, 0.3);
            border-radius: 10px;
            color: #ffffff;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(2, 132, 199, 0.35);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-submit:hover:not(:disabled) {
            background: linear-gradient(135deg, #0369a1 0%, #0284c7 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(2, 132, 199, 0.45);
        }
        .btn-submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        .login-footer-links {
            margin-top: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.82rem;
            color: #94a3b8;
        }
        .login-footer-links a {
            color: #38bdf8;
            text-decoration: none;
            transition: color 0.15s ease;
        }
        .login-footer-links a:hover {
            text-decoration: underline;
        }
        /* Modal for password reset */
        .modal-backdrop {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(8px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-backdrop.active {
            display: flex;
        }
        .reset-card {
            width: 100%;
            max-width: 420px;
            background: #131d35;
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.7);
        }
    </style>
</head>
<body>
    <div class="login-page-wrapper">
        <div class="login-card">
            <div class="login-brand">
                <img src="<?= $baseUri ?>/assets/images/logo.png" alt="ICT Logo" class="login-logo">
                <h1 class="login-title">ISLAMIA COLLEGE</h1>
                <p class="login-subtitle">College Management System • Matta, Swat</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="login-alert" role="alert">
                    <span>⚠️</span>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($infoMessage)): ?>
                <div class="login-info" role="status">
                    <span>ℹ️</span>
                    <span><?= htmlspecialchars($infoMessage) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($resetNotification)): ?>
                <div class="login-info" role="status">
                    <span>📩</span>
                    <span><?= htmlspecialchars($resetNotification) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="login-form">
                <?= getCsrfField() ?>
                <input type="hidden" name="action" value="login">

                <div class="form-group">
                    <label class="form-label" for="identifier">Username, Email, or Phone Number</label>
                    <div class="input-group">
                        <span class="input-icon">👤</span>
                        <input type="text" id="identifier" name="identifier" class="form-control-with-icon" required placeholder="e.g. admin, accountant, or 0345-6896156" autocomplete="username" autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <div style="display:flex; justify-content:space-between; align-items:baseline;">
                        <label class="form-label" for="password">Password</label>
                    </div>
                    <div class="input-group">
                        <span class="input-icon">🔒</span>
                        <input type="password" id="password" name="password" class="form-control-with-icon" required placeholder="Enter your password" autocomplete="current-password">
                        <button type="button" class="password-toggle-btn" id="toggle-password" aria-label="Toggle password visibility" tabindex="-1">
                            <span id="eye-icon">👁️</span>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="submit-btn">
                    <span id="btn-text">Sign In to College System</span>
                    <span id="btn-arrow">→</span>
                </button>
            </form>

            <div class="login-footer-links">
                <a href="javascript:void(0)" onclick="openResetModal()">Forgot password?</a>
                <span>Matta Swat, KP</span>
            </div>
        </div>
    </div>

    <!-- Password Reset Modal -->
    <div class="modal-backdrop" id="reset-modal">
        <div class="reset-card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 style="font-size:1.15rem; color:#f8fafc; font-weight:600;">Account Password Recovery</h3>
                <button type="button" onclick="closeResetModal()" style="background:none; border:none; color:#94a3b8; font-size:1.4rem; cursor:pointer;">&times;</button>
            </div>
            <p style="font-size:0.85rem; color:#94a3b8; margin-bottom:20px; line-height:1.5;">
                Enter your registered username or email address. A recovery notice will be logged for administrative dispatch.
            </p>
            <form method="POST" action="">
                <?= getCsrfField() ?>
                <input type="hidden" name="action" value="reset_request">
                <div class="form-group">
                    <label class="form-label" for="reset_identifier">Username or Email</label>
                    <div class="input-group">
                        <span class="input-icon">✉️</span>
                        <input type="text" id="reset_identifier" name="reset_identifier" class="form-control-with-icon" required placeholder="name@islamia.edu.pk or username">
                    </div>
                </div>
                <div style="display:flex; gap:10px; margin-top:20px;">
                    <button type="button" onclick="closeResetModal()" class="btn btn-glass" style="flex:1; padding:11px; background:#1e293b; border:1px solid rgba(255,255,255,0.1); color:#f8fafc; border-radius:8px; cursor:pointer;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="flex:1; padding:11px; background:#0284c7; border:none; color:#ffffff; border-radius:8px; font-weight:600; cursor:pointer;">Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Safe Password Visibility Toggle - in-memory only, no storage/logging
        const toggleBtn = document.getElementById('toggle-password');
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eye-icon');

        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.textContent = '🙈';
            } else {
                passwordInput.type = 'password';
                eyeIcon.textContent = '👁️';
            }
        });

        // Double submit prevention & loading state
        const loginForm = document.getElementById('login-form');
        const submitBtn = document.getElementById('submit-btn');
        const btnText = document.getElementById('btn-text');
        const btnArrow = document.getElementById('btn-arrow');

        loginForm.addEventListener('submit', function() {
            submitBtn.disabled = true;
            btnText.textContent = 'Signing in...';
            btnArrow.textContent = '⏳';
        });

        // Reset Modal Controls
        function openResetModal() {
            document.getElementById('reset-modal').classList.add('active');
        }
        function closeResetModal() {
            document.getElementById('reset-modal').classList.remove('active');
        }
    </script>
    <script src="<?= $baseUri ?>/assets/js/app.js"></script>
</body>
</html>
