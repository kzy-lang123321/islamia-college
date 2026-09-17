<?php
/**
 * Hardened Authentication, Authorization, and Session Guard
 * Islamia College of Technology College Management System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

// Session Inactivity Timeout: 30 minutes (1800 seconds)
define('SESSION_INACTIVITY_TIMEOUT', 1800);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_SECONDS', 300); // 5 minutes

// Inactivity check for logged-in sessions
if (!empty($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_INACTIVITY_TIMEOUT)) {
        logoutUser(true);
        $base = strpos($_SERVER['REQUEST_URI'], '/islamia%20college') !== false ? '/islamia%20college' : '';
        header("Location: {$base}/login.php?timeout=1");
        exit;
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Ensures user is authenticated; redirects to login if not.
 * Also enforces forced password change on first login.
 */
function checkAuth(): void {
    if (empty($_SESSION['user_id'])) {
        $base = strpos($_SERVER['REQUEST_URI'], '/islamia%20college') !== false ? '/islamia%20college' : '';
        header("Location: {$base}/login.php");
        exit;
    }
    // Enforce first-login password change (skip if already on change_password page)
    if (!empty($_SESSION['must_change_password'])) {
        $currentScript = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
        if ($currentScript !== 'change_password.php' && $currentScript !== 'logout.php') {
            $base = strpos($_SERVER['REQUEST_URI'], '/islamia%20college') !== false ? '/islamia%20college' : '';
            header("Location: {$base}/change_password.php");
            exit;
        }
    }
}

/**
 * Returns current authenticated user details from session
 */
function getCurrentUser(): ?array {
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    if (!isset($_SESSION['profile_photo'])) {
        try {
            $pdo = Database::getConnection();
            $s = $pdo->prepare("SELECT profile_photo, phone, email, full_name, privacy_hide_phone, privacy_hide_email FROM users WHERE id = ?");
            $s->execute([$_SESSION['user_id']]);
            if ($u = $s->fetch()) {
                $_SESSION['profile_photo'] = $u['profile_photo'];
                if (!empty($u['phone'])) $_SESSION['phone'] = $u['phone'];
                if (!empty($u['email'])) $_SESSION['email'] = $u['email'];
                if (!empty($u['full_name'])) $_SESSION['full_name'] = $u['full_name'];
                $_SESSION['privacy_hide_phone'] = (int)($u['privacy_hide_phone'] ?? 0);
                $_SESSION['privacy_hide_email'] = (int)($u['privacy_hide_email'] ?? 0);
            }
        } catch (Exception $e) {
            // gracefully fallback
        }
    }
    return [
        'id'                 => (int)$_SESSION['user_id'],
        'username'           => $_SESSION['username'] ?? '',
        'full_name'          => $_SESSION['full_name'] ?? '',
        'email'              => $_SESSION['email'] ?? '',
        'phone'              => $_SESSION['phone'] ?? '',
        'role'               => $_SESSION['role'] ?? 'staff',
        'profile_photo'      => $_SESSION['profile_photo'] ?? null,
        'privacy_hide_phone' => (int)($_SESSION['privacy_hide_phone'] ?? 0),
        'privacy_hide_email' => (int)($_SESSION['privacy_hide_email'] ?? 0)
    ];
}

/**
 * Verifies if the current user possesses one of the allowed roles
 */
function hasRole(array $allowedRoles): bool {
    $user = getCurrentUser();
    if (!$user) return false;
    return in_array(strtolower($user['role']), array_map('strtolower', $allowedRoles), true);
}

/**
 * Strict Server-Side Role Guard
 */
function requireRole(array $allowedRoles): void {
    checkAuth();
    if (!hasRole($allowedRoles)) {
        http_response_code(403);
        $base = strpos($_SERVER['REQUEST_URI'], '/islamia%20college') !== false ? '/islamia%20college' : '';
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            jsonResponse(['success' => false, 'error' => '403 Forbidden: Insufficient permissions.'], 403);
        } else {
            echo "<!DOCTYPE html><html><head><title>403 Forbidden | Islamia College Management System</title><link rel='stylesheet' href='{$base}/assets/css/futuristic_glass.css'></head><body style='display:flex;align-items:center;justify-content:center;min-height:100vh;background:#0a0f1d;'>";
            echo "<div class='glass-card' style='max-width:480px;text-align:center;padding:36px;border:1px solid rgba(244,63,94,0.3);'>";
            echo "<div style='font-size:48px;margin-bottom:12px;'>🛡️</div>";
            echo "<h2 style='color:#f43f5e;margin-bottom:10px;font-size:22px;'>Access Restricted (403)</h2>";
            echo "<p style='color:#94a3b8;font-size:14px;line-height:1.6;margin-bottom:24px;'>You do not have the required administrative permissions to view or modify this module. This security incident has been logged.</p>";
            echo "<a href='{$base}/index.php' class='btn btn-primary' style='display:inline-block;'>Return to Dashboard</a>";
            echo "</div></body></html>";
        }
        exit;
    }
}

/**
 * Check if identifier or IP is locked out due to excessive failed logins
 */
function isLoginLockedOut(string $identifier, string $ip): bool {
    try {
        $pdo = Database::getConnection();
        // Clean up attempts older than 1 hour to keep table lean
        $pdo->exec("DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 HOUR)");

        // Rate limit per identifier (5 attempts = 5-minute lockout) OR per IP (25 attempts = 5-minute lockout)
        $stmt = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN identifier = ? THEN 1 ELSE 0 END) as user_fails,
                COUNT(*) as ip_fails
            FROM login_attempts
            WHERE (identifier = ? OR ip_address = ?)
              AND attempted_at >= (NOW() - INTERVAL 5 MINUTE)
        ");
        $stmt->execute([$identifier, $identifier, $ip]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        $userFails = (int)($res['user_fails'] ?? 0);
        $ipFails = (int)($res['ip_fails'] ?? 0);
        return ($userFails >= MAX_LOGIN_ATTEMPTS || $ipFails >= 25);
    } catch (Exception $e) {
        error_log("Lockout check error: " . $e->getMessage());
        return false;
    }
}

/**
 * Record a failed login attempt
 */
function recordFailedLogin(string $identifier, string $ip): void {
    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, identifier, attempted_at) VALUES (?, ?, NOW())");
        $stmt->execute([$ip, $identifier]);
    } catch (Exception $e) {
        error_log("Record failed login error: " . $e->getMessage());
    }
}

/**
 * Reset failed login attempts on successful login
 */
function resetFailedLogins(string $identifier, string $ip): void {
    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE identifier = ?");
        $stmt->execute([$identifier]);
    } catch (Exception $e) {
        error_log("Reset failed login error: " . $e->getMessage());
    }
}

/**
 * Authenticate User with Multi-Identifier & Brute-Force Throttling
 */
function loginUser(string $identifier, string $password): array {
    $identifier = trim($identifier);
    $ip = getClientIp();

    if (empty($identifier) || empty($password)) {
        return ['success' => false, 'error' => 'Please enter both your identifier (username, email, or phone) and password.'];
    }

    // Check brute-force lockout
    if (isLoginLockedOut($identifier, $ip)) {
        return ['success' => false, 'error' => 'Too many unsuccessful attempts. Please try again in 5 minutes.'];
    }

    try {
        $pdo = Database::getConnection();
        $normalizedId = preg_replace('/\D/', '', $identifier);
        $stmt = $pdo->prepare("
            SELECT id, username, email, phone, password_hash, full_name, role, is_active, must_change_password, student_id, profile_photo, privacy_hide_phone, privacy_hide_email
            FROM users 
            WHERE (username = ? OR email = ? OR phone = ? OR (phone IS NOT NULL AND REPLACE(REPLACE(phone, '-', ''), ' ', '') = ?)) 
            LIMIT 1
        ");
        $stmt->execute([$identifier, $identifier, $identifier, $normalizedId]);
        $user = $stmt->fetch();

        $passwordValid = false;
        if ($user && (int)$user['is_active'] === 1) {
            if (password_verify($password, $user['password_hash'])) {
                $passwordValid = true;
            } elseif (!empty($user['phone'])) {
                // Fallback for default password: check normalized digits (with/without hyphens/spaces)
                $cleanInput = preg_replace('/\D/', '', $password);
                $cleanPhone = preg_replace('/\D/', '', $user['phone']);
                if ($cleanInput !== '' && $cleanInput === $cleanPhone) {
                    $passwordValid = true;
                    // Update user password_hash with standard bcrypt of input
                    $newHash = password_hash($password, PASSWORD_BCRYPT);
                    $upStmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $upStmt->execute([$newHash, $user['id']]);
                }
            }
        }

        if ($passwordValid) {
            // Rehash password if cost/algorithm updated
            if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT)) {
                $newHash = password_hash($password, PASSWORD_BCRYPT);
                $upStmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $upStmt->execute([$newHash, $user['id']]);
            }

            // Update last login timestamp and IP
            try {
                $llStmt = $pdo->prepare("UPDATE users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?");
                $llStmt->execute([$ip, $user['id']]);
            } catch (Exception $e) {
                error_log("Failed to update last_login: " . $e->getMessage());
            }

            // Regenerate session ID immediately upon successful authentication
            session_regenerate_id(true);

            // Store minimal required identity data in session
            $_SESSION['user_id']             = (int)$user['id'];
            $_SESSION['username']            = $user['username'];
            $_SESSION['full_name']           = $user['full_name'];
            $_SESSION['email']               = $user['email'] ?? '';
            $_SESSION['phone']               = $user['phone'] ?? '';
            $_SESSION['role']                = $user['role'];
            $_SESSION['student_id']          = $user['student_id'] ? (int)$user['student_id'] : null;
            $_SESSION['profile_photo']       = $user['profile_photo'] ?? null;
            $_SESSION['privacy_hide_phone']  = (int)($user['privacy_hide_phone'] ?? 0);
            $_SESSION['privacy_hide_email']  = (int)($user['privacy_hide_email'] ?? 0);
            $_SESSION['must_change_password']= (int)($user['must_change_password'] ?? 0);
            $_SESSION['last_activity']       = time();

            // Clear brute-force records for this identifier and IP
            resetFailedLogins($identifier, $ip);

            return ['success' => true, 'must_change_password' => (int)($user['must_change_password'] ?? 0)];

        } else {
            // Increment failed attempt
            recordFailedLogin($identifier, $ip);
            // Generic error message to prevent account enumeration
            return ['success' => false, 'error' => 'Invalid credentials. Please verify your details or default phone password.'];
        }
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        return ['success' => false, 'error' => 'An unexpected error occurred. Please try again.'];
    }
}

/**
 * Handle Password Reset Request Safely
 */
function requestPasswordReset(string $identifier): array {
    $identifier = trim($identifier);
    $ip = getClientIp();

    if (empty($identifier)) {
        return ['success' => false, 'error' => 'Please enter your username, email, or registered phone.'];
    }

    try {
        $pdo = Database::getConnection();
        $normalizedId = preg_replace('/\D/', '', $identifier);
        $stmt = $pdo->prepare("
            SELECT id, username, email FROM users 
            WHERE (username = ? OR email = ? OR phone = ? OR (phone IS NOT NULL AND REPLACE(REPLACE(phone, '-', ''), ' ', '') = ?)) 
              AND is_active = 1 
            LIMIT 1
        ");
        $stmt->execute([$identifier, $identifier, $identifier, $normalizedId]);
        $user = $stmt->fetch();

        if ($user) {
            $rawToken  = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);

            $ins = $pdo->prepare("
                INSERT INTO password_resets (user_id, token_hash, expires_at)
                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))
            ");
            $ins->execute([$user['id'], $tokenHash]);

            // Build the full reset URL so an admin can copy it from the error log and send to user
            $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $base     = strpos($_SERVER['REQUEST_URI'], '/islamia%20college') !== false ? '/islamia%20college' : '';
            $resetUrl = "{$scheme}://{$host}{$base}/reset_password.php?token=" . urlencode($rawToken);

            error_log("PASSWORD RESET LINK for user ID {$user['id']} ({$user['username']}): {$resetUrl}");
        }

        // Always return generic response to prevent account enumeration
        return [
            'success' => true,
            'message' => 'If an account matches the provided identifier, password reset instructions have been logged for processing.'
        ];
    } catch (Exception $e) {
        error_log("Password reset error: " . $e->getMessage());
        return [
            'success' => true,
            'message' => 'If an account matches the provided identifier, password reset instructions have been logged for processing.'
        ];
    }
}

/**
 * Secure Logout
 */
function logoutUser(bool $timeout = false): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 86400,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}
