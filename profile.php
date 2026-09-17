<?php
/**
 * User Profile Settings, Password Security & Privacy Center
 * Islamia College of Technology College Management System
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Enforce authentication
checkAuth();

$pdo = Database::getConnection();
$sessionUser = getCurrentUser();
$userId = (int)$sessionUser['id'];

$successMessage = '';
$errorMessage   = '';
$activeTab      = isset($_GET['tab']) && in_array($_GET['tab'], ['profile', 'security', 'privacy', 'appearance']) ? $_GET['tab'] : 'profile';

// Detect AJAX request
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
       || !empty($_POST['is_ajax']);

// Fetch latest user data directly from database
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userData) {
    logoutUser();
    header("Location: login.php");
    exit;
}

// Handle POST Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfRequest();
    $action = trim($_POST['action'] ?? '');

    // 1. UPDATE PROFILE
    if ($action === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');

        if (empty($fullName)) {
            $errorMessage = 'Full Name is required.';
            $activeTab = 'profile';
        } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = 'Please provide a valid email address.';
            $activeTab = 'profile';
        } else {
            // Check email uniqueness if email provided
            $emailCollision = false;
            if (!empty($email)) {
                $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
                $checkStmt->execute([$email, $userId]);
                if ($checkStmt->fetch()) {
                    $emailCollision = true;
                    $errorMessage = 'This email address is already registered to another user account.';
                    $activeTab = 'profile';
                }
            }

            if (!$emailCollision) {
                $newPhotoName = $userData['profile_photo'];

                // Handle Photo Upload if submitted via form
                if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                    $fileTmp  = $_FILES['profile_photo']['tmp_name'];
                    $fileSize = $_FILES['profile_photo']['size'];
                    $fileName = $_FILES['profile_photo']['name'];
                    $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
                    $maxSize     = 5 * 1024 * 1024; // 5 MB

                    $finfo    = new finfo(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo->file($fileTmp);
                    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

                    if (!in_array($fileExt, $allowedExts) || !in_array($mimeType, $allowedMimes)) {
                        $errorMessage = 'Invalid file type. Only JPG, PNG, and WebP images are allowed.';
                        $activeTab = 'profile';
                    } elseif ($fileSize > $maxSize) {
                        $errorMessage = 'Photo exceeds maximum allowed size of 5 MB.';
                        $activeTab = 'profile';
                    } else {
                        $uploadDir = __DIR__ . '/assets/images/users/';
                        if (!is_dir($uploadDir)) {
                            @mkdir($uploadDir, 0755, true);
                        }

                        // Generate unique filename
                        $newPhotoName = 'user_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExt;
                        $destPath = $uploadDir . $newPhotoName;

                        if (move_uploaded_file($fileTmp, $destPath)) {
                            // Unlink old photo
                            if (!empty($userData['profile_photo'])) {
                                $oldFile = $uploadDir . basename($userData['profile_photo']);
                                if (file_exists($oldFile) && is_file($oldFile)) {
                                    @unlink($oldFile);
                                }
                            }
                        } else {
                            $errorMessage = 'Failed to save uploaded photo. Please check folder permissions.';
                            $activeTab = 'profile';
                        }
                    }
                }

                if (empty($errorMessage)) {
                    try {
                        $updateStmt = $pdo->prepare("
                            UPDATE users 
                            SET full_name = ?, email = ?, phone = ?, profile_photo = ?, updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $updateStmt->execute([
                            $fullName,
                            !empty($email) ? $email : null,
                            !empty($phone) ? $phone : null,
                            $newPhotoName,
                            $userId
                        ]);

                        // Sync session
                        $_SESSION['full_name']     = $fullName;
                        $_SESSION['email']         = $email;
                        $_SESSION['phone']         = $phone;
                        $_SESSION['profile_photo'] = $newPhotoName;

                        // Also sync staff_members if linked or matching
                        try {
                            $staffSync = $pdo->prepare("
                                UPDATE staff_members 
                                SET full_name = ?, phone = COALESCE(?, phone)" . ($newPhotoName ? ", profile_photo = ?" : "") . "
                                WHERE phone = ? OR full_name = ?
                            ");
                            $params = [$fullName, $phone];
                            if ($newPhotoName) $params[] = $newPhotoName;
                            $params[] = $userData['phone'];
                            $params[] = $userData['full_name'];
                            $staffSync->execute($params);
                        } catch (Exception $e) {
                            // Ignore non-fatal staff sync
                        }

                        // Refresh local userData
                        $userData['full_name']     = $fullName;
                        $userData['email']         = $email;
                        $userData['phone']         = $phone;
                        $userData['profile_photo'] = $newPhotoName;

                        $successMessage = 'Profile information updated successfully.';
                        $activeTab = 'profile';

                        if ($isAjax) {
                            header('Content-Type: application/json');
                            echo json_encode([
                                'success' => true,
                                'message' => $successMessage,
                                'full_name' => $fullName,
                                'photo_url' => $newPhotoName ? 'assets/images/users/' . $newPhotoName : null
                            ]);
                            exit;
                        }
                    } catch (Exception $e) {
                        error_log('Profile update error: ' . $e->getMessage());
                        $errorMessage = 'Error updating profile. Please try again.';
                        $activeTab = 'profile';
                    }
                }
            }
        }

        if ($isAjax && !empty($errorMessage)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $errorMessage]);
            exit;
        }

    // 2. REMOVE PHOTO
    } elseif ($action === 'remove_photo') {
        if (!empty($userData['profile_photo'])) {
            $photoPath = __DIR__ . '/assets/images/users/' . basename($userData['profile_photo']);
            if (file_exists($photoPath) && is_file($photoPath)) {
                @unlink($photoPath);
            }
            $pdo->prepare("UPDATE users SET profile_photo = NULL WHERE id = ?")->execute([$userId]);
            $_SESSION['profile_photo'] = null;
            $userData['profile_photo'] = null;
        }

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Profile photo removed successfully.']);
            exit;
        }

        $successMessage = 'Profile photo removed successfully.';
        $activeTab = 'profile';

    // 3. CHANGE PASSWORD
    } elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['new_password']     ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $errorMessage = 'All password fields are required.';
            $activeTab = 'security';
        } elseif (strlen($newPassword) < 8) {
            $errorMessage = 'New password must be at least 8 characters in length.';
            $activeTab = 'security';
        } elseif ($newPassword !== $confirmPassword) {
            $errorMessage = 'The new password and confirmation password do not match.';
            $activeTab = 'security';
        } elseif ($newPassword === $currentPassword) {
            $errorMessage = 'New password must be different from your current password.';
            $activeTab = 'security';
        } else {
            // Verify current password
            $passwordMatches = password_verify($currentPassword, $userData['password_hash']);
            
            // Check default password fallback if not yet rehashed
            if (!$passwordMatches && !empty($userData['phone'])) {
                $cleanInput = preg_replace('/\D/', '', $currentPassword);
                $cleanPhone = preg_replace('/\D/', '', $userData['phone']);
                if ($cleanInput !== '' && $cleanInput === $cleanPhone) {
                    $passwordMatches = true;
                }
            }

            if (!$passwordMatches) {
                $errorMessage = 'The current password you entered is incorrect.';
                $activeTab = 'security';
            } else {
                try {
                    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                    $upStmt = $pdo->prepare("UPDATE users SET password_hash = ?, must_change_password = 0, updated_at = NOW() WHERE id = ?");
                    $upStmt->execute([$newHash, $userId]);

                    $_SESSION['must_change_password'] = 0;
                    $userData['must_change_password'] = 0;
                    $userData['password_hash'] = $newHash;

                    $successMessage = 'Password updated successfully! Your account is now secured with your new password.';
                    $activeTab = 'security';

                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'message' => $successMessage]);
                        exit;
                    }
                } catch (Exception $e) {
                    error_log('Password change error: ' . $e->getMessage());
                    $errorMessage = 'Error updating password. Please try again.';
                    $activeTab = 'security';
                }
            }
        }

        if ($isAjax && !empty($errorMessage)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $errorMessage]);
            exit;
        }

    // 4. UPDATE PRIVACY SETTINGS
    } elseif ($action === 'update_privacy') {
        $hidePhone = isset($_POST['privacy_hide_phone']) ? 1 : 0;
        $hideEmail = isset($_POST['privacy_hide_email']) ? 1 : 0;

        try {
            $privStmt = $pdo->prepare("UPDATE users SET privacy_hide_phone = ?, privacy_hide_email = ?, updated_at = NOW() WHERE id = ?");
            $privStmt->execute([$hidePhone, $hideEmail, $userId]);

            $_SESSION['privacy_hide_phone'] = $hidePhone;
            $_SESSION['privacy_hide_email'] = $hideEmail;
            $userData['privacy_hide_phone'] = $hidePhone;
            $userData['privacy_hide_email'] = $hideEmail;

            $successMessage = 'Privacy preferences saved successfully.';
            $activeTab = 'privacy';

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => $successMessage]);
                exit;
            }
        } catch (Exception $e) {
            error_log('Privacy update error: ' . $e->getMessage());
            $errorMessage = 'Error updating privacy preferences.';
            $activeTab = 'privacy';
        }

        if ($isAjax && !empty($errorMessage)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $errorMessage]);
            exit;
        }

    // 5. TERMINATE OTHER SESSIONS
    } elseif ($action === 'terminate_other_sessions') {
        // Regenerate current session ID and invalidate session fixation
        session_regenerate_id(true);
        $successMessage = 'All other active sessions have been terminated. Your current session has been re-secured with a new cryptographic ID.';
        $activeTab = 'privacy';

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $successMessage]);
            exit;
        }
    }
}

// Helpers for view
$baseUri     = strpos($_SERVER['REQUEST_URI'], '/islamia%20college') !== false ? '/islamia%20college' : '';
$hasPhoto    = !empty($userData['profile_photo']) && file_exists(__DIR__ . '/assets/images/users/' . $userData['profile_photo']);
$photoUrl    = $hasPhoto ? $baseUri . '/assets/images/users/' . htmlspecialchars($userData['profile_photo']) : '';
$initialName = !empty($userData['full_name']) ? strtoupper(substr($userData['full_name'], 0, 1)) : 'U';

// Parse client IP and User-Agent
$clientIp   = getClientIp();
$userAgent  = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Browser';
$browserName = 'Web Browser';
if (strpos($userAgent, 'Chrome') !== false) {
    $browserName = 'Google Chrome';
} elseif (strpos($userAgent, 'Firefox') !== false) {
    $browserName = 'Mozilla Firefox';
} elseif (strpos($userAgent, 'Safari') !== false) {
    $browserName = 'Apple Safari';
} elseif (strpos($userAgent, 'Edge') !== false) {
    $browserName = 'Microsoft Edge';
}

$pageTitle = 'Profile & Privacy Settings';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Profile &amp; Privacy Settings</h1>
        <p class="page-breadcrumb">Account Center &bull; Manage your personal details, credentials &amp; privacy</p>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
        <span class="status-badge badge-active" style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;font-size:12px;">
            <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#22c55e;"></span>
            <?= strtoupper(htmlspecialchars($userData['role'])) ?>
        </span>
        <a href="<?= $baseUri ?>/index.php" class="btn btn-glass btn-sm">← Back to Dashboard</a>
    </div>
</div>

<!-- Alert Notifications -->
<?php if (!empty($successMessage)): ?>
<div style="padding:14px 18px;background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.3);color:#6ee7b7;border-radius:12px;margin-bottom:22px;display:flex;align-items:center;gap:12px;">
    <span style="font-size:18px;">✓</span>
    <span style="font-size:14px;font-weight:500;"><?= htmlspecialchars($successMessage) ?></span>
</div>
<?php endif; ?>

<?php if (!empty($errorMessage)): ?>
<div style="padding:14px 18px;background:rgba(244,63,94,0.12);border:1px solid rgba(244,63,94,0.3);color:#fda4af;border-radius:12px;margin-bottom:22px;display:flex;align-items:center;gap:12px;">
    <span style="font-size:18px;">⚠️</span>
    <span style="font-size:14px;font-weight:500;"><?= htmlspecialchars($errorMessage) ?></span>
</div>
<?php endif; ?>

<!-- Toast for Asynchronous Interactions -->
<div id="profile-toast" style="display:none;position:fixed;bottom:24px;right:24px;z-index:9999;padding:14px 22px;border-radius:12px;font-size:0.9rem;font-weight:600;box-shadow:0 12px 36px rgba(0,0,0,0.6);backdrop-filter:blur(8px);"></div>

<!-- User Identity Hero Card -->
<div class="profile-hero">
    <div class="profile-avatar-box" id="hero-avatar-box">
        <?php if ($hasPhoto): ?>
            <img src="<?= $photoUrl ?>" alt="<?= htmlspecialchars($userData['full_name']) ?>" id="hero-avatar-img">
        <?php else: ?>
            <span class="profile-avatar-placeholder" id="hero-avatar-placeholder"><?= htmlspecialchars($initialName) ?></span>
        <?php endif; ?>
        <label class="profile-avatar-overlay" for="avatar-quick-input" title="Click to upload profile photo">
            <span style="font-size:18px;">📷</span>
            <span>Change</span>
            <input type="file" id="avatar-quick-input" accept="image/jpeg,image/png,image/webp" style="display:none;" onchange="handleInstantPhotoUpload(this)">
        </label>
    </div>

    <div style="flex:1;min-width:240px;">
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:6px;">
            <h2 style="font-size:22px;font-weight:700;color:#f8fafc;margin:0;">
                <?= htmlspecialchars($userData['full_name']) ?>
            </h2>
            <span style="background:rgba(56,189,248,0.15);border:1px solid rgba(56,189,248,0.3);color:#7dd3fc;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:0.04em;">
                <?= strtoupper(htmlspecialchars($userData['role'])) ?>
            </span>
        </div>

        <div style="display:flex;align-items:center;gap:18px;flex-wrap:wrap;color:#94a3b8;font-size:13.5px;margin-bottom:12px;">
            <span><strong style="color:#64748b;">Username:</strong> <code style="color:#38bdf8;font-family:monospace;">@<?= htmlspecialchars($userData['username']) ?></code></span>
            <span><strong style="color:#64748b;">Email:</strong> <?= htmlspecialchars($userData['email'] ?: 'No email configured') ?></span>
            <span><strong style="color:#64748b;">Phone:</strong> <?= htmlspecialchars($userData['phone'] ?: 'No phone configured') ?></span>
        </div>

        <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;font-size:12px;color:#64748b;">
            <span>Account ID: <code style="color:#cbd5e1;">#<?= (int)$userData['id'] ?></code></span>
            <span>Registered: <strong style="color:#cbd5e1;"><?= date('M d, Y', strtotime($userData['created_at'])) ?></strong></span>
            <?php if (!empty($userData['last_login_at'])): ?>
                <span>Last Login: <strong style="color:#cbd5e1;"><?= date('M d, Y H:i', strtotime($userData['last_login_at'])) ?></strong> (IP: <?= htmlspecialchars($userData['last_login_ip'] ?: '—') ?>)</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Tabs Navigation -->
<div class="profile-tabs-nav">
    <button type="button" class="profile-tab-btn <?= $activeTab === 'profile' ? 'active' : '' ?>" onclick="switchTab('profile')">
        <span>👤</span>
        <span>Personal Details</span>
    </button>
    <button type="button" class="profile-tab-btn <?= $activeTab === 'security' ? 'active' : '' ?>" onclick="switchTab('security')">
        <span>🔑</span>
        <span>Password &amp; Security</span>
    </button>
    <button type="button" class="profile-tab-btn <?= $activeTab === 'privacy' ? 'active' : '' ?>" onclick="switchTab('privacy')">
        <span>🛡️</span>
        <span>Privacy &amp; Sessions</span>
    </button>
    <button type="button" class="profile-tab-btn <?= $activeTab === 'appearance' ? 'active' : '' ?>" onclick="switchTab('appearance')">
        <span>🎨</span>
        <span>Theme &amp; Appearance</span>
    </button>
</div>

<!-- TAB 1: PERSONAL DETAILS -->
<div id="tab-pane-profile" class="tab-pane <?= $activeTab === 'profile' ? 'active' : '' ?>" style="<?= $activeTab === 'profile' ? '' : 'display:none;' ?>">
    <div class="profile-grid-main">
        
        <!-- Left: Photo Management Card -->
        <div class="glass-card" style="text-align:center;padding:28px 20px;">
            <h3 class="card-title" style="margin-bottom:18px;font-size:16px;">Profile Photo</h3>
            
            <div style="position:relative;width:130px;height:130px;margin:0 auto 18px;border-radius:50%;overflow:hidden;background:#0f172a;border:3px solid rgba(56,189,248,0.3);box-shadow:0 8px 24px rgba(0,0,0,0.4);display:flex;align-items:center;justify-content:center;" id="card-avatar-box">
                <?php if ($hasPhoto): ?>
                    <img src="<?= $photoUrl ?>" alt="Avatar" id="card-avatar-img" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                    <div id="card-avatar-placeholder" style="font-size:48px;font-weight:700;color:var(--primary-light);"><?= htmlspecialchars($initialName) ?></div>
                <?php endif; ?>
            </div>

            <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:16px;">
                <label class="btn btn-primary btn-sm" style="cursor:pointer;width:100%;">
                    <span>📤 Upload New Photo</span>
                    <input type="file" id="form-file-input" accept="image/jpeg,image/png,image/webp" style="display:none;" onchange="handleInstantPhotoUpload(this)">
                </label>

                <button type="button" class="btn btn-danger btn-sm" id="btn-remove-photo" style="width:100%;<?= $hasPhoto ? '' : 'display:none;' ?>" onclick="confirmRemovePhoto()">
                    <span>🗑 Remove Photo</span>
                </button>
            </div>

            <p style="font-size:12px;color:var(--text-dim);line-height:1.5;margin:0;">
                Supported formats: JPG, PNG, WebP.<br>Maximum file size: 5 MB.
            </p>
        </div>

        <!-- Right: Profile Info Form -->
        <div class="glass-card">
            <h3 class="card-title" style="margin-bottom:20px;">Edit Personal Information</h3>

            <form method="POST" action="profile.php?tab=profile" enctype="multipart/form-data">
                <?= getCsrfField() ?>
                <input type="hidden" name="action" value="update_profile">

                <div class="profile-form-row">
                    <div class="form-group">
                        <label class="form-label">Full Name <span class="required">*</span></label>
                        <input type="text" name="full_name" class="form-control" required value="<?= htmlspecialchars($userData['full_name']) ?>" placeholder="e.g. Amin Ullah">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($userData['email'] ?? '') ?>" placeholder="e.g. director@ictswat.edu.pk">
                    </div>
                </div>

                <div class="profile-form-row">
                    <div class="form-group">
                        <label class="form-label">Contact Phone Number</label>
                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($userData['phone'] ?? '') ?>" placeholder="03xx-xxxxxxx">
                        <span style="font-size:11.5px;color:var(--text-dim);margin-top:4px;display:block;">Used for campus communications &amp; SMS alerts.</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Username (System Identifier)</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($userData['username']) ?>" disabled style="opacity:0.6;cursor:not-allowed;">
                        <span style="font-size:11.5px;color:var(--text-dim);margin-top:4px;display:block;">Username is permanent and managed by administrators.</span>
                    </div>
                </div>

                <div class="profile-form-row">
                    <div class="form-group">
                        <label class="form-label">Account Role</label>
                        <input type="text" class="form-control" value="<?= strtoupper(htmlspecialchars($userData['role'])) ?>" disabled style="opacity:0.6;cursor:not-allowed;text-transform:uppercase;font-weight:700;letter-spacing:0.04em;">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Account Status</label>
                        <div style="display:flex;align-items:center;gap:10px;height:44px;">
                            <span class="status-badge badge-active" style="padding:6px 14px;">
                                Active &amp; Verified
                            </span>
                        </div>
                    </div>
                </div>

                <div style="margin-top:24px;display:flex;justify-content:flex-end;">
                    <button type="submit" class="btn btn-primary" style="min-width:180px;">
                        <span>💾 Save Profile Changes</span>
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>

<!-- TAB 2: PASSWORD & SECURITY -->
<div id="tab-pane-security" class="tab-pane <?= $activeTab === 'security' ? 'active' : '' ?>" style="<?= $activeTab === 'security' ? '' : 'display:none;' ?>">
    <div class="profile-grid-security">
        
        <!-- Left: Password Form -->
        <div class="glass-card">
            <h3 class="card-title" style="margin-bottom:8px;">Change Account Password</h3>
            <p style="font-size:13.5px;color:var(--text-dim);margin-bottom:24px;line-height:1.5;">
                Choose a strong, unique password to keep your administrative records and student information secure.
            </p>

            <form method="POST" action="profile.php?tab=security" id="form-change-password">
                <?= getCsrfField() ?>
                <input type="hidden" name="action" value="change_password">

                <div class="form-group">
                    <label class="form-label">Current Password <span class="required">*</span></label>
                    <div class="input-group" style="position:relative;">
                        <input type="password" name="current_password" id="current_password" class="form-control" required placeholder="Enter your current password" style="padding-right:44px;">
                        <button type="button" class="password-toggle-btn" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:#94a3b8;cursor:pointer;font-size:16px;">👁️</button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">New Password <span class="required">*</span></label>
                    <div class="input-group" style="position:relative;">
                        <input type="password" name="new_password" id="new_password" class="form-control" required placeholder="Minimum 8 characters" style="padding-right:44px;" oninput="evaluatePasswordStrength(this.value)">
                        <button type="button" class="password-toggle-btn" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:#94a3b8;cursor:pointer;font-size:16px;">👁️</button>
                    </div>

                    <!-- Password Strength Meter -->
                    <div class="pw-strength-container">
                        <div class="pw-strength-track">
                            <div class="pw-strength-bar" id="pw-strength-fill"></div>
                        </div>
                        <div class="pw-strength-text">
                            <span style="color:#64748b;">Strength</span>
                            <span id="pw-strength-label" style="color:#64748b;">Too weak</span>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Confirm New Password <span class="required">*</span></label>
                    <div class="input-group" style="position:relative;">
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" required placeholder="Re-enter your new password" style="padding-right:44px;" oninput="checkPasswordMatch()">
                        <button type="button" class="password-toggle-btn" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:#94a3b8;cursor:pointer;font-size:16px;">👁️</button>
                    </div>
                    <div id="pw-match-label" style="font-size:12px;margin-top:5px;display:none;"></div>
                </div>

                <div style="margin-top:28px;display:flex;justify-content:flex-end;">
                    <button type="submit" class="btn btn-primary" style="min-width:180px;">
                        <span>🔒 Update Password</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Right: Password Guidelines -->
        <div class="glass-card">
            <h3 class="card-title" style="margin-bottom:14px;font-size:15px;">Password Requirements</h3>
            
            <ul style="list-style:none;padding:0;margin:0 0 20px 0;display:flex;flex-direction:column;gap:10px;font-size:13px;">
                <li id="rule-length" style="display:flex;align-items:center;gap:10px;color:#64748b;">
                    <span class="rule-icon">⚪</span> At least 8 characters
                </li>
                <li id="rule-upper" style="display:flex;align-items:center;gap:10px;color:#64748b;">
                    <span class="rule-icon">⚪</span> At least one uppercase letter (A-Z)
                </li>
                <li id="rule-lower" style="display:flex;align-items:center;gap:10px;color:#64748b;">
                    <span class="rule-icon">⚪</span> At least one lowercase letter (a-z)
                </li>
                <li id="rule-num" style="display:flex;align-items:center;gap:10px;color:#64748b;">
                    <span class="rule-icon">⚪</span> At least one number (0-9)
                </li>
                <li id="rule-special" style="display:flex;align-items:center;gap:10px;color:#64748b;">
                    <span class="rule-icon">⚪</span> One special symbol (@, #, $, etc.)
                </li>
            </ul>

            <div style="padding:14px;background:rgba(56,189,248,0.06);border:1px solid rgba(56,189,248,0.2);border-radius:10px;">
                <div style="font-weight:600;font-size:12.5px;color:#7dd3fc;margin-bottom:4px;">💡 Security Tip</div>
                <div style="font-size:12px;color:#94a3b8;line-height:1.4;">
                    Avoid reusing passwords across personal email or social accounts. For maximum security, update your college credentials periodically.
                </div>
            </div>
        </div>

    </div>
</div>

<!-- TAB 3: PRIVACY & SESSIONS -->
<div id="tab-pane-privacy" class="tab-pane <?= $activeTab === 'privacy' ? 'active' : '' ?>" style="<?= $activeTab === 'privacy' ? '' : 'display:none;' ?>">
    <div class="profile-grid-privacy">
        
        <!-- Left: Directory Privacy Options -->
        <div class="glass-card">
            <h3 class="card-title" style="margin-bottom:6px;">Directory Visibility &amp; Privacy</h3>
            <p style="font-size:13px;color:var(--text-dim);margin-bottom:20px;line-height:1.5;">
                Control how your contact information appears in public staff listings, student portals, and phone directories.
            </p>

            <form method="POST" action="profile.php?tab=privacy">
                <?= getCsrfField() ?>
                <input type="hidden" name="action" value="update_privacy">

                <!-- Hide Phone Switch -->
                <div class="switch-container">
                    <div>
                        <div class="switch-label-title">Hide Phone Number</div>
                        <div class="switch-label-desc">
                            When enabled, your phone number is masked from student portal directories and public staff views. Only senior administrators can view it.
                        </div>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="privacy_hide_phone" value="1" <?= (int)($userData['privacy_hide_phone'] ?? 0) === 1 ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <!-- Hide Email Switch -->
                <div class="switch-container">
                    <div>
                        <div class="switch-label-title">Hide Email Address</div>
                        <div class="switch-label-desc">
                            When enabled, your direct email address is kept private and hidden in public directories to avoid spam.
                        </div>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="privacy_hide_email" value="1" <?= (int)($userData['privacy_hide_email'] ?? 0) === 1 ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <div style="margin-top:24px;display:flex;justify-content:flex-end;">
                    <button type="submit" class="btn btn-primary">
                        <span>🛡️ Save Privacy Preferences</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Right: Active Session & Device Controls -->
        <div class="glass-card">
            <h3 class="card-title" style="margin-bottom:6px;">Active Session &amp; Device</h3>
            <p style="font-size:13px;color:var(--text-dim);margin-bottom:20px;line-height:1.5;">
                Security metadata and device information for your currently authenticated session.
            </p>

            <div style="background:rgba(255,255,255,0.02);border:1px solid var(--border-card);border-radius:12px;padding:16px;margin-bottom:20px;">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                    <span style="font-size:24px;">💻</span>
                    <div>
                        <div style="font-size:14px;font-weight:600;color:#f8fafc;"><?= htmlspecialchars($browserName) ?> on Current Device</div>
                        <div style="font-size:12px;color:#38bdf8;">Current Active Session &bull; IP: <?= htmlspecialchars($clientIp) ?></div>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:12.5px;color:#94a3b8;border-top:1px solid rgba(255,255,255,0.06);padding-top:12px;">
                    <div>
                        <span style="color:#64748b;">Inactivity Timeout:</span><br>
                        <strong style="color:#cbd5e1;">30 minutes</strong>
                    </div>
                    <div>
                        <span style="color:#64748b;">Session Security:</span><br>
                        <strong style="color:#22c55e;">HttpOnly &amp; Strict</strong>
                    </div>
                </div>
            </div>

            <!-- Terminate Other Sessions -->
            <div style="border:1px solid rgba(244,63,94,0.25);background:rgba(244,63,94,0.04);border-radius:12px;padding:18px;">
                <div style="font-size:14px;font-weight:600;color:#fda4af;margin-bottom:6px;">
                    ⚠️ Invalidate Other Sessions
                </div>
                <p style="font-size:12.5px;color:#94a3b8;line-height:1.5;margin-bottom:14px;">
                    If you suspect unauthorized access or forgot to sign out on a shared college computer, you can terminate all other active browser sessions immediately.
                </p>

                <form method="POST" action="profile.php?tab=privacy" id="form-terminate-sessions">
                    <?= getCsrfField() ?>
                    <input type="hidden" name="action" value="terminate_other_sessions">
                    <button type="button" class="btn btn-danger btn-sm" onclick="confirmTerminateSessions()">
                        <span>🔒 Terminate Other Sessions</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Theme & Color Cards Changing Options (Directly Below Privacy & Settings) -->
        <div class="glass-card" style="grid-column: 1 / -1; margin-top: 14px;">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:16px;border-bottom:1px solid var(--border-card);padding-bottom:14px;">
                <div>
                    <h3 class="card-title" style="margin-bottom:4px;font-size:18px;">🎨 Color Cards Changing Options</h3>
                    <p style="font-size:13.5px;color:var(--text-muted);margin:0;">
                        Click any color card below to instantly change the system color palette. Your choice is saved automatically.
                    </p>
                </div>
                <div style="display:flex;gap:10px;align-items:center;">
                    <button type="button" class="btn btn-glass btn-sm" onclick="toggleTheme()" style="gap:8px;">
                        <span class="theme-icon-light">☀️</span>
                        <span class="theme-icon-dark">🌙</span>
                        <span class="theme-label-light">Toggle Light/Dark</span>
                        <span class="theme-label-dark">Toggle Light/Dark</span>
                    </button>
                </div>
            </div>

            <div class="theme-picker-grid">
                <!-- Suggested Direction -->
                <div class="theme-card-option" data-theme-key="light" onclick="setTheme('light')">
                    <div class="theme-card-header">
                        <span class="theme-card-title">⭐ Suggested Light</span>
                        <span class="theme-card-badge active-badge">✓ Active</span>
                        <span class="theme-card-badge">Default</span>
                    </div>
                    <div class="theme-card-preview">
                        <div class="theme-preview-swatch" style="background:#F4F7FB;color:#172033;">#F4F7FB</div>
                        <div class="theme-preview-swatch" style="background:#155EEF;color:#ffffff;">#155EEF</div>
                        <div class="theme-preview-swatch" style="background:#0EA5E9;color:#ffffff;">#0EA5E9</div>
                        <div class="theme-preview-swatch" style="background:#7C3AED;color:#ffffff;">#7C3AED</div>
                    </div>
                    <div style="font-size:12px;color:var(--text-muted);line-height:1.4;">Clean light background, royal blue brand, cyan accents.</div>
                    <button type="button" class="btn btn-glass btn-sm apply-theme-btn">Apply Palette</button>
                </div>

                <!-- 1. Clean Blue -->
                <div class="theme-card-option" data-theme-key="clean-blue" onclick="setTheme('clean-blue')">
                    <div class="theme-card-header">
                        <span class="theme-card-title">1. Clean Blue</span>
                        <span class="theme-card-badge active-badge">✓ Active</span>
                    </div>
                    <div class="theme-card-preview">
                        <div class="theme-preview-swatch" style="background:#F5F9FF;color:#172033;">#F5F9FF</div>
                        <div class="theme-preview-swatch" style="background:#1565C0;color:#ffffff;">#1565C0</div>
                        <div class="theme-preview-swatch" style="background:#00A6E8;color:#ffffff;">#00A6E8</div>
                        <div class="theme-preview-swatch" style="background:#FFFFFF;color:#172033;border-left:1px solid #D0E1F9;">Cards</div>
                    </div>
                    <div style="font-size:12px;color:var(--text-muted);line-height:1.4;">Fresh modern blue palette with crisp high contrast.</div>
                    <button type="button" class="btn btn-glass btn-sm apply-theme-btn">Apply Palette</button>
                </div>

                <!-- 2. Royal Blue -->
                <div class="theme-card-option" data-theme-key="royal-blue" onclick="setTheme('royal-blue')">
                    <div class="theme-card-header">
                        <span class="theme-card-title">2. Royal Blue</span>
                        <span class="theme-card-badge active-badge">✓ Active</span>
                    </div>
                    <div class="theme-card-preview">
                        <div class="theme-preview-swatch" style="background:#F4F7FC;color:#172033;">#F4F7FC</div>
                        <div class="theme-preview-swatch" style="background:#243B7A;color:#ffffff;">#243B7A</div>
                        <div class="theme-preview-swatch" style="background:#3B82F6;color:#ffffff;">#3B82F6</div>
                        <div class="theme-preview-swatch" style="background:#FFFFFF;color:#172033;border-left:1px solid #D5DFEE;">Cards</div>
                    </div>
                    <div style="font-size:12px;color:var(--text-muted);line-height:1.4;">Authoritative collegiate deep royal blue &amp; bright blue.</div>
                    <button type="button" class="btn btn-glass btn-sm apply-theme-btn">Apply Palette</button>
                </div>

                <!-- 3. Teal Academic -->
                <div class="theme-card-option" data-theme-key="teal" onclick="setTheme('teal')">
                    <div class="theme-card-header">
                        <span class="theme-card-title">3. Teal Academic</span>
                        <span class="theme-card-badge active-badge">✓ Active</span>
                    </div>
                    <div class="theme-card-preview">
                        <div class="theme-preview-swatch" style="background:#F2FAF9;color:#17313B;">#F2FAF9</div>
                        <div class="theme-preview-swatch" style="background:#087F8C;color:#ffffff;">#087F8C</div>
                        <div class="theme-preview-swatch" style="background:#14B8A6;color:#ffffff;">#14B8A6</div>
                        <div class="theme-preview-swatch" style="background:#FFFFFF;color:#17313B;border-left:1px solid #CFEAE6;">Cards</div>
                    </div>
                    <div style="font-size:12px;color:var(--text-muted);line-height:1.4;">Calm, balanced teal tones for extended screen time.</div>
                    <button type="button" class="btn btn-glass btn-sm apply-theme-btn">Apply Palette</button>
                </div>

                <!-- 4. Purple Academic -->
                <div class="theme-card-option" data-theme-key="purple" onclick="setTheme('purple')">
                    <div class="theme-card-header">
                        <span class="theme-card-title">4. Purple Academic</span>
                        <span class="theme-card-badge active-badge">✓ Active</span>
                    </div>
                    <div class="theme-card-preview">
                        <div class="theme-preview-swatch" style="background:#F8F6FF;color:#24134F;">#F8F6FF</div>
                        <div class="theme-preview-swatch" style="background:#5B2CC9;color:#ffffff;">#5B2CC9</div>
                        <div class="theme-preview-swatch" style="background:#7C5CFC;color:#ffffff;">#7C5CFC</div>
                        <div class="theme-preview-swatch" style="background:#FFFFFF;color:#24134F;border-left:1px solid #DFD5FA;">Cards</div>
                    </div>
                    <div style="font-size:12px;color:var(--text-muted);line-height:1.4;">Distinguished university purple with bright accents.</div>
                    <button type="button" class="btn btn-glass btn-sm apply-theme-btn">Apply Palette</button>
                </div>

                <!-- 5. Navy + Cyan -->
                <div class="theme-card-option" data-theme-key="navy-cyan" onclick="setTheme('navy-cyan')">
                    <div class="theme-card-header">
                        <span class="theme-card-title">5. Navy + Cyan</span>
                        <span class="theme-card-badge active-badge">✓ Active</span>
                    </div>
                    <div class="theme-card-preview">
                        <div class="theme-preview-swatch" style="background:#F3F7FB;color:#172033;">#F3F7FB</div>
                        <div class="theme-preview-swatch" style="background:#123B63;color:#ffffff;">#123B63</div>
                        <div class="theme-preview-swatch" style="background:#00AEEF;color:#ffffff;">#00AEEF</div>
                        <div class="theme-preview-swatch" style="background:#FFFFFF;color:#172033;border-left:1px solid #D3DFEC;">Cards</div>
                    </div>
                    <div style="font-size:12px;color:var(--text-muted);line-height:1.4;">Deep corporate navy paired with vibrant cyan highlights.</div>
                    <button type="button" class="btn btn-glass btn-sm apply-theme-btn">Apply Palette</button>
                </div>

                <!-- 6. Slate + Blue -->
                <div class="theme-card-option" data-theme-key="slate-blue" onclick="setTheme('slate-blue')">
                    <div class="theme-card-header">
                        <span class="theme-card-title">6. Slate + Blue</span>
                        <span class="theme-card-badge active-badge">✓ Active</span>
                    </div>
                    <div class="theme-card-preview">
                        <div class="theme-preview-swatch" style="background:#F6F8FA;color:#0F172A;">#F6F8FA</div>
                        <div class="theme-preview-swatch" style="background:#334155;color:#ffffff;">#334155</div>
                        <div class="theme-preview-swatch" style="background:#2563EB;color:#ffffff;">#2563EB</div>
                        <div class="theme-preview-swatch" style="background:#FFFFFF;color:#0F172A;border-left:1px solid #D5DDE6;">Cards</div>
                    </div>
                    <div style="font-size:12px;color:var(--text-muted);line-height:1.4;">Minimalist slate gray structure with bright blue actions.</div>
                    <button type="button" class="btn btn-glass btn-sm apply-theme-btn">Apply Palette</button>
                </div>

                <!-- 7. Green Academic -->
                <div class="theme-card-option" data-theme-key="green" onclick="setTheme('green')">
                    <div class="theme-card-header">
                        <span class="theme-card-title">7. Green Academic</span>
                        <span class="theme-card-badge active-badge">✓ Active</span>
                    </div>
                    <div class="theme-card-preview">
                        <div class="theme-preview-swatch" style="background:#F4FAF7;color:#17342A;">#F4FAF7</div>
                        <div class="theme-preview-swatch" style="background:#176B4D;color:#ffffff;">#176B4D</div>
                        <div class="theme-preview-swatch" style="background:#22A06B;color:#ffffff;">#22A06B</div>
                        <div class="theme-preview-swatch" style="background:#FFFFFF;color:#17342A;border-left:1px solid #D1E7DC;">Cards</div>
                    </div>
                    <div style="font-size:12px;color:var(--text-muted);line-height:1.4;">Natural emerald and forest green scholastic theme.</div>
                    <button type="button" class="btn btn-glass btn-sm apply-theme-btn">Apply Palette</button>
                </div>

                <!-- 8. Warm Professional -->
                <div class="theme-card-option" data-theme-key="warm" onclick="setTheme('warm')">
                    <div class="theme-card-header">
                        <span class="theme-card-title">8. Warm Professional</span>
                        <span class="theme-card-badge active-badge">✓ Active</span>
                    </div>
                    <div class="theme-card-preview">
                        <div class="theme-preview-swatch" style="background:#FAF9F6;color:#1F2937;">#FAF9F6</div>
                        <div class="theme-preview-swatch" style="background:#374151;color:#ffffff;">#374151</div>
                        <div class="theme-preview-swatch" style="background:#D97706;color:#ffffff;">#D97706</div>
                        <div class="theme-preview-swatch" style="background:#FFFFFF;color:#1F2937;border-left:1px solid #DDD7CE;">Cards</div>
                    </div>
                    <div style="font-size:12px;color:var(--text-muted);line-height:1.4;">Warm cream background with warm charcoal and amber.</div>
                    <button type="button" class="btn btn-glass btn-sm apply-theme-btn">Apply Palette</button>
                </div>

                <!-- Classic Dark Navy -->
                <div class="theme-card-option" data-theme-key="dark" onclick="setTheme('dark')">
                    <div class="theme-card-header">
                        <span class="theme-card-title">🌙 Classic Dark Navy</span>
                        <span class="theme-card-badge active-badge">✓ Active</span>
                        <span class="theme-card-badge">Dark</span>
                    </div>
                    <div class="theme-card-preview">
                        <div class="theme-preview-swatch" style="background:#0A0F1D;color:#f8fafc;">#0A0F1D</div>
                        <div class="theme-preview-swatch" style="background:#0284C7;color:#ffffff;">#0284C7</div>
                        <div class="theme-preview-swatch" style="background:#38BDF8;color:#0A0F1D;">#38BDF8</div>
                        <div class="theme-preview-swatch" style="background:#131D35;color:#f8fafc;border-left:1px solid rgba(255,255,255,0.1);">Cards</div>
                    </div>
                    <div style="font-size:12px;color:var(--text-muted);line-height:1.4;">Deep space navy futuristic high-contrast dark theme.</div>
                    <button type="button" class="btn btn-glass btn-sm apply-theme-btn">Apply Palette</button>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- TAB 4: THEME & APPEARANCE -->
<div id="tab-pane-appearance" class="tab-pane <?= $activeTab === 'appearance' ? 'active' : '' ?>" style="<?= $activeTab === 'appearance' ? '' : 'display:none;' ?>">
    <div class="glass-card" style="margin-bottom:24px;">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:12px;">
            <div>
                <h3 class="card-title" style="margin-bottom:4px;font-size:18px;">🎨 Application Color Themes &amp; Appearance</h3>
                <p style="font-size:13.5px;color:var(--text-muted);margin:0;">
                    Choose a professional color palette designed for high legibility, clean aesthetics, and optimal daily productivity.
                </p>
            </div>
            <button type="button" class="btn btn-glass btn-sm" onclick="toggleTheme()" style="gap:8px;">
                <span class="theme-icon-light">☀️ Switch to Light</span>
                <span class="theme-icon-dark">🌙 Switch to Dark</span>
            </button>
        </div>

        <div class="theme-picker-grid">
            <!-- Suggested Direction -->
            <div class="theme-card-option" data-theme-key="light" onclick="setTheme('light')">
                <div class="theme-card-header">
                    <span class="theme-card-title">⭐ Suggested Light</span>
                    <span class="theme-card-badge active-badge">✓ Active</span>
                    <span class="theme-card-badge">Default</span>
                </div>
                <div class="theme-card-preview">
                    <div class="theme-preview-swatch" style="background:#F4F7FB;color:#172033;">#F4F7FB</div>
                    <div class="theme-preview-swatch" style="background:#155EEF;color:#ffffff;">#155EEF</div>
                    <div class="theme-preview-swatch" style="background:#0EA5E9;color:#ffffff;">#0EA5E9</div>
                    <div class="theme-preview-swatch" style="background:#7C3AED;color:#ffffff;">#7C3AED</div>
                </div>
                <div style="font-size:12px;color:var(--text-muted);">Clean light background, royal blue brand, cyan accents.</div>
            </div>

            <!-- 1. Clean Blue -->
            <div class="theme-card-option" data-theme-key="clean-blue" onclick="setTheme('clean-blue')">
                <div class="theme-card-header">
                    <span class="theme-card-title">1. Clean Blue</span>
                    <span class="theme-card-badge active-badge">✓ Active</span>
                </div>
                <div class="theme-card-preview">
                    <div class="theme-preview-swatch" style="background:#F5F9FF;color:#172033;">#F5F9FF</div>
                    <div class="theme-preview-swatch" style="background:#1565C0;color:#ffffff;">#1565C0</div>
                    <div class="theme-preview-swatch" style="background:#00A6E8;color:#ffffff;">#00A6E8</div>
                    <div class="theme-preview-swatch" style="background:#FFFFFF;color:#172033;border-left:1px solid #D0E1F9;">Cards</div>
                </div>
                <div style="font-size:12px;color:var(--text-muted);">Fresh modern blue palette with crisp contrast.</div>
            </div>

            <!-- 2. Royal Blue -->
            <div class="theme-card-option" data-theme-key="royal-blue" onclick="setTheme('royal-blue')">
                <div class="theme-card-header">
                    <span class="theme-card-title">2. Royal Blue</span>
                    <span class="theme-card-badge active-badge">✓ Active</span>
                </div>
                <div class="theme-card-preview">
                    <div class="theme-preview-swatch" style="background:#F4F7FC;color:#172033;">#F4F7FC</div>
                    <div class="theme-preview-swatch" style="background:#243B7A;color:#ffffff;">#243B7A</div>
                    <div class="theme-preview-swatch" style="background:#3B82F6;color:#ffffff;">#3B82F6</div>
                    <div class="theme-preview-swatch" style="background:#FFFFFF;color:#172033;border-left:1px solid #D5DFEE;">Cards</div>
                </div>
                <div style="font-size:12px;color:var(--text-muted);">Authoritative collegiate deep royal blue &amp; bright blue.</div>
            </div>

            <!-- 3. Teal Academic -->
            <div class="theme-card-option" data-theme-key="teal" onclick="setTheme('teal')">
                <div class="theme-card-header">
                    <span class="theme-card-title">3. Teal Academic</span>
                    <span class="theme-card-badge active-badge">✓ Active</span>
                </div>
                <div class="theme-card-preview">
                    <div class="theme-preview-swatch" style="background:#F2FAF9;color:#17313B;">#F2FAF9</div>
                    <div class="theme-preview-swatch" style="background:#087F8C;color:#ffffff;">#087F8C</div>
                    <div class="theme-preview-swatch" style="background:#14B8A6;color:#ffffff;">#14B8A6</div>
                    <div class="theme-preview-swatch" style="background:#FFFFFF;color:#17313B;border-left:1px solid #CFEAE6;">Cards</div>
                </div>
                <div style="font-size:12px;color:var(--text-muted);">Calm, balanced teal tones for extended screen time.</div>
            </div>

            <!-- 4. Purple Academic -->
            <div class="theme-card-option" data-theme-key="purple" onclick="setTheme('purple')">
                <div class="theme-card-header">
                    <span class="theme-card-title">4. Purple Academic</span>
                    <span class="theme-card-badge active-badge">✓ Active</span>
                </div>
                <div class="theme-card-preview">
                    <div class="theme-preview-swatch" style="background:#F8F6FF;color:#24134F;">#F8F6FF</div>
                    <div class="theme-preview-swatch" style="background:#5B2CC9;color:#ffffff;">#5B2CC9</div>
                    <div class="theme-preview-swatch" style="background:#7C5CFC;color:#ffffff;">#7C5CFC</div>
                    <div class="theme-preview-swatch" style="background:#FFFFFF;color:#24134F;border-left:1px solid #DFD5FA;">Cards</div>
                </div>
                <div style="font-size:12px;color:var(--text-muted);">Distinguished university purple with bright accents.</div>
            </div>

            <!-- 5. Navy + Cyan -->
            <div class="theme-card-option" data-theme-key="navy-cyan" onclick="setTheme('navy-cyan')">
                <div class="theme-card-header">
                    <span class="theme-card-title">5. Navy + Cyan</span>
                    <span class="theme-card-badge active-badge">✓ Active</span>
                </div>
                <div class="theme-card-preview">
                    <div class="theme-preview-swatch" style="background:#F3F7FB;color:#172033;">#F3F7FB</div>
                    <div class="theme-preview-swatch" style="background:#123B63;color:#ffffff;">#123B63</div>
                    <div class="theme-preview-swatch" style="background:#00AEEF;color:#ffffff;">#00AEEF</div>
                    <div class="theme-preview-swatch" style="background:#FFFFFF;color:#172033;border-left:1px solid #D3DFEC;">Cards</div>
                </div>
                <div style="font-size:12px;color:var(--text-muted);">Deep corporate navy paired with vibrant cyan highlights.</div>
            </div>

            <!-- 6. Slate + Blue -->
            <div class="theme-card-option" data-theme-key="slate-blue" onclick="setTheme('slate-blue')">
                <div class="theme-card-header">
                    <span class="theme-card-title">6. Slate + Blue</span>
                    <span class="theme-card-badge active-badge">✓ Active</span>
                </div>
                <div class="theme-card-preview">
                    <div class="theme-preview-swatch" style="background:#F6F8FA;color:#0F172A;">#F6F8FA</div>
                    <div class="theme-preview-swatch" style="background:#334155;color:#ffffff;">#334155</div>
                    <div class="theme-preview-swatch" style="background:#2563EB;color:#ffffff;">#2563EB</div>
                    <div class="theme-preview-swatch" style="background:#FFFFFF;color:#0F172A;border-left:1px solid #D5DDE6;">Cards</div>
                </div>
                <div style="font-size:12px;color:var(--text-muted);">Minimalist slate gray structure with bright blue actions.</div>
            </div>

            <!-- 7. Green Academic -->
            <div class="theme-card-option" data-theme-key="green" onclick="setTheme('green')">
                <div class="theme-card-header">
                    <span class="theme-card-title">7. Green Academic</span>
                    <span class="theme-card-badge active-badge">✓ Active</span>
                </div>
                <div class="theme-card-preview">
                    <div class="theme-preview-swatch" style="background:#F4FAF7;color:#17342A;">#F4FAF7</div>
                    <div class="theme-preview-swatch" style="background:#176B4D;color:#ffffff;">#176B4D</div>
                    <div class="theme-preview-swatch" style="background:#22A06B;color:#ffffff;">#22A06B</div>
                    <div class="theme-preview-swatch" style="background:#FFFFFF;color:#17342A;border-left:1px solid #D1E7DC;">Cards</div>
                </div>
                <div style="font-size:12px;color:var(--text-muted);">Natural emerald and forest green scholastic theme.</div>
            </div>

            <!-- 8. Warm Professional -->
            <div class="theme-card-option" data-theme-key="warm" onclick="setTheme('warm')">
                <div class="theme-card-header">
                    <span class="theme-card-title">8. Warm Professional</span>
                    <span class="theme-card-badge active-badge">✓ Active</span>
                </div>
                <div class="theme-card-preview">
                    <div class="theme-preview-swatch" style="background:#FAF9F6;color:#1F2937;">#FAF9F6</div>
                    <div class="theme-preview-swatch" style="background:#374151;color:#ffffff;">#374151</div>
                    <div class="theme-preview-swatch" style="background:#D97706;color:#ffffff;">#D97706</div>
                    <div class="theme-preview-swatch" style="background:#FFFFFF;color:#1F2937;border-left:1px solid #DDD7CE;">Cards</div>
                </div>
                <div style="font-size:12px;color:var(--text-muted);">Warm cream background with warm charcoal and amber.</div>
            </div>

            <!-- Classic Dark Navy -->
            <div class="theme-card-option" data-theme-key="dark" onclick="setTheme('dark')">
                <div class="theme-card-header">
                    <span class="theme-card-title">🌙 Classic Dark Navy</span>
                    <span class="theme-card-badge active-badge">✓ Active</span>
                    <span class="theme-card-badge">Dark</span>
                </div>
                <div class="theme-card-preview">
                    <div class="theme-preview-swatch" style="background:#0A0F1D;color:#f8fafc;">#0A0F1D</div>
                    <div class="theme-preview-swatch" style="background:#0284C7;color:#ffffff;">#0284C7</div>
                    <div class="theme-preview-swatch" style="background:#38BDF8;color:#0A0F1D;">#38BDF8</div>
                    <div class="theme-preview-swatch" style="background:#131D35;color:#f8fafc;border-left:1px solid rgba(255,255,255,0.1);">Cards</div>
                </div>
                <div style="font-size:12px;color:var(--text-muted);">Deep space navy futuristic high-contrast dark theme.</div>
            </div>
        </div>
    </div>
</div>

<!-- Modal / Scripts for Interactive Tabs, Instant Photo Upload, and Strength Check -->
<form id="remove-photo-form" method="POST" action="profile.php?tab=profile" style="display:none;">
    <?= getCsrfField() ?>
    <input type="hidden" name="action" value="remove_photo">
</form>

<script>
const CSRF_TOKEN = "<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES) ?>";
const BASE_URI   = "<?= $baseUri ?>";

function switchTab(tabId) {
    document.querySelectorAll('.profile-tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(pane => {
        pane.classList.remove('active');
        pane.style.display = 'none';
    });

    const activeBtn = Array.from(document.querySelectorAll('.profile-tab-btn')).find(b => b.getAttribute('onclick').includes(tabId));
    if (activeBtn) activeBtn.classList.add('active');

    const activePane = document.getElementById('tab-pane-' + tabId);
    if (activePane) {
        activePane.classList.add('active');
        activePane.style.display = '';
    }

    // Update URL query string without reloading page
    const newUrl = new URL(window.location);
    newUrl.searchParams.set('tab', tabId);
    window.history.replaceState({}, '', newUrl);
}

function showToast(message, type) {
    const toast = document.getElementById('profile-toast');
    if (!toast) return;
    toast.textContent = message;
    toast.style.display = 'block';
    toast.style.background = type === 'success' ? 'rgba(16, 185, 129, 0.95)' : 'rgba(244, 63, 94, 0.95)';
    toast.style.color = '#ffffff';
    clearTimeout(toast._timer);
    toast._timer = setTimeout(() => { toast.style.display = 'none'; }, 4000);
}

// Instant Photo Upload
function handleInstantPhotoUpload(input) {
    const file = input.files[0];
    if (!file) return;

    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        showToast('Only JPG, PNG, and WebP images are allowed.', 'error');
        input.value = '';
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        showToast('Image size exceeds maximum limit of 5 MB.', 'error');
        input.value = '';
        return;
    }

    const formData = new FormData();
    formData.append('profile_photo', file);
    formData.append('action', 'update_profile');
    formData.append('full_name', <?= json_encode($userData['full_name']) ?>);
    formData.append('email', <?= json_encode($userData['email'] ?? '') ?>);
    formData.append('phone', <?= json_encode($userData['phone'] ?? '') ?>);
    formData.append('csrf_token', CSRF_TOKEN);
    formData.append('is_ajax', '1');

    showToast('Uploading photo...', 'success');

    fetch('profile.php', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(res => {
        if (res.success && res.photo_url) {
            const freshUrl = BASE_URI + '/' + res.photo_url + '?t=' + Date.now();

            // 1. Update Hero Avatar
            const heroBox = document.getElementById('hero-avatar-box');
            let heroImg = document.getElementById('hero-avatar-img');
            const heroPh = document.getElementById('hero-avatar-placeholder');
            if (!heroImg && heroBox) {
                heroImg = document.createElement('img');
                heroImg.id = 'hero-avatar-img';
                heroImg.style.cssText = 'width:100%;height:100%;object-fit:cover;';
                heroBox.insertBefore(heroImg, heroBox.firstChild);
            }
            if (heroImg) heroImg.src = freshUrl;
            if (heroPh) heroPh.style.display = 'none';

            // 2. Update Card Avatar
            const cardBox = document.getElementById('card-avatar-box');
            let cardImg = document.getElementById('card-avatar-img');
            const cardPh = document.getElementById('card-avatar-placeholder');
            if (!cardImg && cardBox) {
                cardImg = document.createElement('img');
                cardImg.id = 'card-avatar-img';
                cardImg.style.cssText = 'width:100%;height:100%;object-fit:cover;';
                cardBox.insertBefore(cardImg, cardBox.firstChild);
            }
            if (cardImg) cardImg.src = freshUrl;
            if (cardPh) cardPh.style.display = 'none';

            // 3. Update Header Avatar
            const headerAvatar = document.getElementById('header-user-avatar');
            if (headerAvatar) {
                headerAvatar.innerHTML = `<img src="${freshUrl}" alt="Avatar">`;
            }

            // 4. Show remove button
            const rmBtn = document.getElementById('btn-remove-photo');
            if (rmBtn) rmBtn.style.display = '';

            showToast('Profile photo updated successfully!', 'success');
        } else {
            showToast(res.error || 'Upload failed.', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Network error during upload.', 'error');
    })
    .finally(() => {
        input.value = '';
    });
}

function confirmRemovePhoto() {
    confirmAction('Remove Profile Photo?', 'Are you sure you want to remove your profile photo? Your avatar will display your initials instead.', () => {
        const formData = new FormData();
        formData.append('action', 'remove_photo');
        formData.append('csrf_token', CSRF_TOKEN);
        formData.append('is_ajax', '1');

        fetch('profile.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                const initial = "<?= htmlspecialchars($initialName) ?>";

                // Reset Hero Avatar
                const heroImg = document.getElementById('hero-avatar-img');
                const heroPh  = document.getElementById('hero-avatar-placeholder');
                if (heroImg) heroImg.remove();
                if (heroPh) {
                    heroPh.style.display = '';
                } else {
                    const box = document.getElementById('hero-avatar-box');
                    const ph = document.createElement('span');
                    ph.className = 'profile-avatar-placeholder';
                    ph.id = 'hero-avatar-placeholder';
                    ph.textContent = initial;
                    box.insertBefore(ph, box.firstChild);
                }

                // Reset Card Avatar
                const cardImg = document.getElementById('card-avatar-img');
                const cardPh  = document.getElementById('card-avatar-placeholder');
                if (cardImg) cardImg.remove();
                if (cardPh) {
                    cardPh.style.display = '';
                } else {
                    const box = document.getElementById('card-avatar-box');
                    const ph = document.createElement('div');
                    ph.id = 'card-avatar-placeholder';
                    ph.style.cssText = 'font-size:48px;font-weight:700;color:var(--primary-light);';
                    ph.textContent = initial;
                    box.insertBefore(ph, box.firstChild);
                }

                // Reset Header Avatar
                const headerAvatar = document.getElementById('header-user-avatar');
                if (headerAvatar) {
                    headerAvatar.textContent = initial;
                }

                const rmBtn = document.getElementById('btn-remove-photo');
                if (rmBtn) rmBtn.style.display = 'none';

                showToast('Profile photo removed.', 'success');
            } else {
                showToast(res.error || 'Failed to remove photo.', 'error');
            }
        })
        .catch(() => showToast('Network error.', 'error'));
    });
}

function confirmTerminateSessions() {
    confirmAction(
        'Terminate Other Sessions?',
        'This will invalidate all other active browser logins on any desktop or mobile device. You will stay signed in on this current browser.',
        () => {
            document.getElementById('form-terminate-sessions').submit();
        }
    );
}

// Password Strength Evaluation
function evaluatePasswordStrength(password) {
    const fill  = document.getElementById('pw-strength-fill');
    const label = document.getElementById('pw-strength-label');

    const hasLength  = password.length >= 8;
    const hasUpper   = /[A-Z]/.test(password);
    const hasLower   = /[a-z]/.test(password);
    const hasNum     = /[0-9]/.test(password);
    const hasSpecial = /[^A-Za-z0-9]/.test(password);

    updateRule('rule-length', hasLength);
    updateRule('rule-upper', hasUpper);
    updateRule('rule-lower', hasLower);
    updateRule('rule-num', hasNum);
    updateRule('rule-special', hasSpecial);

    let score = 0;
    if (hasLength) score++;
    if (hasUpper) score++;
    if (hasLower) score++;
    if (hasNum) score++;
    if (hasSpecial) score++;

    if (!password) {
        fill.style.width = '0';
        label.textContent = 'Too weak';
        label.style.color = '#64748b';
        return;
    }

    if (score <= 2) {
        fill.style.width = '30%';
        fill.style.backgroundColor = '#f43f5e';
        label.textContent = 'Weak';
        label.style.color = '#f43f5e';
    } else if (score === 3 || score === 4) {
        fill.style.width = '65%';
        fill.style.backgroundColor = '#fbbf24';
        label.textContent = 'Good';
        label.style.color = '#fbbf24';
    } else {
        fill.style.width = '100%';
        fill.style.backgroundColor = '#22c55e';
        label.textContent = 'Strong';
        label.style.color = '#22c55e';
    }

    checkPasswordMatch();
}

function updateRule(elementId, passed) {
    const el = document.getElementById(elementId);
    if (!el) return;
    const icon = el.querySelector('.rule-icon');
    if (passed) {
        el.style.color = '#86efac';
        if (icon) icon.textContent = '✓';
    } else {
        el.style.color = '#64748b';
        if (icon) icon.textContent = '⚪';
    }
}

function checkPasswordMatch() {
    const np = document.getElementById('new_password').value;
    const cp = document.getElementById('confirm_password').value;
    const matchLabel = document.getElementById('pw-match-label');

    if (!cp) {
        matchLabel.style.display = 'none';
        return;
    }

    matchLabel.style.display = 'block';
    if (np === cp) {
        matchLabel.textContent = '✓ Passwords match';
        matchLabel.style.color = '#22c55e';
    } else {
        matchLabel.textContent = '⚠️ Passwords do not match';
        matchLabel.style.color = '#f43f5e';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Check URL parameters for tab selection
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam  = urlParams.get('tab');
    if (tabParam && ['profile', 'security', 'privacy'].includes(tabParam)) {
        switchTab(tabParam);
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
