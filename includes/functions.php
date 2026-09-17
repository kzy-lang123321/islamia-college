<?php
/**
 * Core Helper, Security, and Session Functions
 * Islamia College of Technology College Management System
 */

// Production error reporting: log internally, never show stack traces
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Security Response Headers
if (!headers_sent()) {
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("X-XSS-Protection: 1; mode=block");
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    // Safe CSP that allows local assets and Chart.js CDN
    header("Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://fonts.googleapis.com https://fonts.gstatic.com data:;");
}

// Secure session configuration
if (session_status() === PHP_SESSION_NONE) {
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

/**
 * HTML Output Escaper for XSS Prevention
 */
function e(?string $string): string {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

/**
 * Return JSON response and terminate
 */
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * CSRF Token Generator
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Returns hidden HTML input for CSRF
 */
function getCsrfField(): string {
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validates CSRF Token
 */
function verifyCsrfToken(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Guard for state-changing HTTP requests (POST, PUT, DELETE)
 */
function validateCsrfRequest(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!verifyCsrfToken($token)) {
            http_response_code(403);
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                jsonResponse(['success' => false, 'error' => 'Security token invalid or expired. Please refresh the page.'], 403);
            } else {
                echo "<div style='background:#0f172a; color:#f8fafc; font-family:sans-serif; padding:40px; text-align:center; min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center;'>";
                echo "<h2 style='color:#f43f5e; margin-bottom:12px;'>403 - Invalid Security Token</h2>";
                echo "<p style='color:#94a3b8; max-width:480px;'>Your session token is expired or invalid. Please refresh the page and try again to protect your account.</p>";
                echo "<a href='javascript:history.back()' style='margin-top:16px; color:#38bdf8; text-decoration:none;'>← Go Back</a>";
                echo "</div>";
            }
            exit;
        }
    }
}

/**
 * Sanitize User Input Strings
 */
function sanitizeInput(string $data): string {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Flash Notification Helpers
 */
function setFlashMessage(string $type, string $message): void {
    $_SESSION['flash_message'] = [
        'type'    => $type, // 'success', 'error', 'warning', 'info'
        'message' => $message
    ];
}

function getFlashMessage(): ?array {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

/**
 * Get Client IP Address Safely
 */
function getClientIp(): string {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}
