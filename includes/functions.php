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
    if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
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
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

/**
 * Universal Official Print Letterhead Header
 */
function renderPrintHeader(string $docTitle, string $docSubtitle = '', array $activeFilters = []): void {
    $now = date('d M Y, h:i A');
    $user = $_SESSION['full_name'] ?? ($_SESSION['username'] ?? 'System User');
    ?>
    <div class="print-header-institution print-only">
        <div class="print-header-inner">
            <div class="print-brand-left">
                <div class="print-logo-box">ICT</div>
                <div>
                    <h1 class="print-inst-name">ISLAMIA COLLEGE OF TECHNOLOGY</h1>
                    <p class="print-inst-sub">Matta Swat, Khyber Pakhtunkhwa &bull; Affiliated with KPK Trade Testing Board (TTB)</p>
                    <p class="print-inst-sub" style="font-size: 8pt; color: #64748b;">Phone: +92 946 795000 &bull; Web: www.ictswat.edu.pk &bull; Management Information System</p>
                </div>
            </div>
            <div class="print-doc-meta">
                <div class="print-doc-title"><?= htmlspecialchars($docTitle) ?></div>
                <?php if (!empty($docSubtitle)): ?>
                    <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($docSubtitle) ?></div>
                <?php endif; ?>
                <div><strong>Printed Date:</strong> <?= $now ?></div>
                <div><strong>Generated By:</strong> <?= htmlspecialchars($user) ?></div>
            </div>
        </div>
        <?php if (!empty($activeFilters)): ?>
            <div style="margin-top: 8px; padding-top: 6px; border-top: 1px dashed #cbd5e1; font-size: 8.5pt; color: #475569; display: flex; flex-wrap: wrap; gap: 12px;">
                <strong style="color: #0f172a;">Active Criteria:</strong>
                <?php foreach ($activeFilters as $k => $v): if ($v === '' || $v === null || $v === 0) continue; ?>
                    <span><strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', $k))) ?>:</strong> <?= htmlspecialchars((string)$v) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Universal Official Print Verification Signatures
 */
function renderPrintSignatures(array $signers = ['Prepared By', 'Verified / Accounts Officer', 'Principal / Director ICT']): void {
    ?>
    <div class="print-signatures-block print-only">
        <div class="print-sig-row">
            <?php foreach ($signers as $title): ?>
                <div class="print-sig-col">
                    <div class="print-sig-line"></div>
                    <div class="print-sig-label"><?= htmlspecialchars($title) ?></div>
                    <div class="print-sig-sub">Signature &amp; Official Stamp</div>
                </div>
            <?php endforeach; ?>
        </div>
        <div style="margin-top: 24px; text-align: center; font-size: 7.5pt; color: #94a3b8; border-top: 1px solid #f1f5f9; padding-top: 6px;">
            This is an official computer-generated document from Islamia College of Technology Management System. Valid only with authorized signatures and institutional stamp.
        </div>
    </div>
    <?php
}

/**
 * Universal On-Screen Print Preview Toolbar
 */
function renderPrintPreviewToolbar(string $docTitle, int $recordCount, string $backUrl): void {
    ?>
    <div class="print-preview-toolbar no-print">
        <div class="print-preview-info">
            <span class="print-preview-badge">Print Preview</span>
            <span class="print-preview-text">
                <strong><?= htmlspecialchars($docTitle) ?></strong> &bull; <?= number_format($recordCount) ?> record<?= $recordCount === 1 ? '' : 's' ?> ready for A4 printing
            </span>
        </div>
        <div class="print-preview-actions">
            <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-glass btn-sm" style="color: #cbd5e1; border-color: rgba(255,255,255,0.2);">
                <span>← Exit Preview</span>
            </a>
            <button type="button" class="btn btn-primary btn-sm" onclick="window.print()" style="background: #eb6226; border-color: #eb6226; color: #fff;">
                <span>🖨️ Confirm &amp; Print</span>
            </button>
        </div>
    </div>
    <script>
        document.body.classList.add('print-preview-mode');
    </script>
    <?php
}
