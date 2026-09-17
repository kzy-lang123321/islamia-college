<?php
/**
 * Persistent Application Header & Sidebar Shell
 * Islamia College of Technology College Management System
 */

require_once __DIR__ . '/auth.php';
checkAuth();

$currentUser = getCurrentUser();
$userRole = strtolower($currentUser['role'] ?? 'staff');
$baseUri = strpos($_SERVER['REQUEST_URI'], '/islamia%20college') !== false ? '/islamia%20college' : '';
$currentScript = $_SERVER['SCRIPT_NAME'];

function isNavActive(string $path, string $currentScript): string {
    return strpos($currentScript, $path) !== false ? 'active' : '';
}

function isSectionActive(array $paths, string $currentScript): bool {
    foreach ($paths as $path) {
        if (strpos($currentScript, $path) !== false) {
            return true;
        }
    }
    return false;
}

$isMainActive      = ($currentScript == $baseUri . '/index.php' || (substr($currentScript, -9) == 'index.php' && strpos($currentScript, 'modules') === false));
$isStudentsActive  = isSectionActive(['students', 'enrollments', 'board-examinations', 'academic-positions'], $currentScript);
$isAcademicsActive = isSectionActive(['departments', 'courses', 'batches'], $currentScript);
$isFinanceActive   = isSectionActive(['fees', 'loans', 'expenses'], $currentScript);
$isStaffActive     = isSectionActive(['staff', 'payroll'], $currentScript);
$isAssetsActive    = isSectionActive(['assets'], $currentScript);
$isReportsActive   = isSectionActive(['reports'], $currentScript);
$isAdminActive     = isSectionActive(['users'], $currentScript);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . " | " : "" ?>Islamia College of Technology, Matta Swat</title>
    
    <!-- Instant Zero-Flicker Theme Detection & Sidebar State -->
    <script>
        (function() {
            var savedTheme = localStorage.getItem('ict_theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
            if (window.innerWidth > 1024 && localStorage.getItem('ict_sidebar_collapsed') === '1') {
                document.documentElement.classList.add('sidebar-collapsed');
            }
        })();
    </script>
    
    <link rel="stylesheet" href="<?= $baseUri ?>/assets/css/futuristic_glass.css">
    <!-- Chart.js for data visualization -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .search-dropdown-list {
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            margin-top: 8px;
            overflow: hidden;
            z-index: 1000;
            max-height: 380px;
            overflow-y: auto;
        }
        .search-result-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 18px;
            text-decoration: none;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border-card);
            transition: background 0.15s ease;
        }
        .search-result-item:hover {
            background: var(--bg-card-hover);
        }
        .search-item-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
        }
        .search-item-sub {
            font-size: 12px;
            color: var(--text-muted);
        }
    </style>
</head>
<body>
<div class="app-container">
    <!-- Persistent Sidebar -->
    <aside class="app-sidebar" id="app-sidebar">
        <div class="sidebar-header">
            <img src="<?= $baseUri ?>/assets/images/logo.png" alt="ICT Logo" class="sidebar-logo">
            <div class="sidebar-brand-text">
                <div class="sidebar-title">ICT SWAT</div>
                <div class="sidebar-subtitle">College Management System</div>
            </div>
            <button type="button" class="sidebar-collapse-desktop-btn" id="sidebar-collapse-desktop-btn" title="Toggle Sidebar (Collapse / Expand)" aria-label="Toggle Sidebar">
                <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.2" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
            </button>
            <button type="button" class="sidebar-close-btn" id="sidebar-close-btn" aria-label="Close Sidebar">&times;</button>
        </div>

        <nav class="sidebar-nav">
            <!-- MAIN -->
            <div class="nav-category" data-target="main">Main</div>
            <a href="<?= $baseUri ?>/index.php" class="nav-link <?= $isMainActive ? 'active' : '' ?>" data-tooltip="Dashboard">
                <span class="nav-icon">📊</span>
                <span>Dashboard</span>
            </a>

            <?php if (in_array($userRole, ['admin', 'registrar', 'staff'])): ?>
            <!-- STUDENTS -->
            <div class="nav-category" data-target="students">Students</div>
            <div class="nav-section <?= $isStudentsActive ? 'expanded has-active' : '' ?>" data-section="students">
                <button type="button" class="nav-section-header" aria-expanded="<?= $isStudentsActive ? 'true' : 'false' ?>">
                    <span class="nav-section-left">
                        <span class="nav-icon">🎓</span>
                        <span class="nav-section-title">Students</span>
                    </span>
                    <span class="nav-chevron">
                        <svg class="nav-chevron-icon" viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </span>
                </button>
                <div class="nav-submenu">
                    <div class="nav-submenu-inner">
                        <a href="<?= $baseUri ?>/modules/students/index.php" class="nav-sublink <?= isNavActive('students', $currentScript) ?>">
                            <span class="nav-subicon">🎓</span>
                            <span>Student List</span>
                        </a>
                        <a href="<?= $baseUri ?>/modules/enrollments/index.php" class="nav-sublink <?= isNavActive('enrollments', $currentScript) ?>">
                            <span class="nav-subicon">📝</span>
                            <span>Enrollments</span>
                        </a>
                        <a href="<?= $baseUri ?>/modules/board-examinations/index.php" class="nav-sublink <?= isNavActive('board-examinations', $currentScript) ?>">
                            <span class="nav-subicon">📋</span>
                            <span>Board Examinations</span>
                        </a>
                        <a href="<?= $baseUri ?>/modules/academic-positions/index.php" class="nav-sublink <?= isNavActive('academic-positions', $currentScript) ?>">
                            <span class="nav-subicon">🏆</span>
                            <span>Academic Achievements</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- ACADEMICS -->
            <div class="nav-category" data-target="academics">Academics</div>
            <div class="nav-section <?= $isAcademicsActive ? 'expanded has-active' : '' ?>" data-section="academics">
                <button type="button" class="nav-section-header" aria-expanded="<?= $isAcademicsActive ? 'true' : 'false' ?>">
                    <span class="nav-section-left">
                        <span class="nav-icon">🏛️</span>
                        <span class="nav-section-title">Academics</span>
                    </span>
                    <span class="nav-chevron">
                        <svg class="nav-chevron-icon" viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </span>
                </button>
                <div class="nav-submenu">
                    <div class="nav-submenu-inner">
                        <a href="<?= $baseUri ?>/modules/departments/index.php" class="nav-sublink <?= isNavActive('departments', $currentScript) ?>">
                            <span class="nav-subicon">🏛️</span>
                            <span>Departments</span>
                        </a>
                        <a href="<?= $baseUri ?>/modules/courses/index.php" class="nav-sublink <?= isNavActive('courses', $currentScript) ?>">
                            <span class="nav-subicon">📚</span>
                            <span>Courses</span>
                        </a>
                        <a href="<?= $baseUri ?>/modules/batches/index.php" class="nav-sublink <?= isNavActive('batches', $currentScript) ?>">
                            <span class="nav-subicon">👥</span>
                            <span>Batches</span>
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (in_array($userRole, ['admin', 'accountant'])): ?>
            <!-- FINANCE -->
            <div class="nav-category" data-target="finance">Finance</div>
            <div class="nav-section <?= $isFinanceActive ? 'expanded has-active' : '' ?>" data-section="finance">
                <button type="button" class="nav-section-header" aria-expanded="<?= $isFinanceActive ? 'true' : 'false' ?>">
                    <span class="nav-section-left">
                        <span class="nav-icon">💳</span>
                        <span class="nav-section-title">Finance</span>
                    </span>
                    <span class="nav-chevron">
                        <svg class="nav-chevron-icon" viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </span>
                </button>
                <div class="nav-submenu">
                    <div class="nav-submenu-inner">
                        <a href="<?= $baseUri ?>/modules/fees/index.php?new=1" class="nav-sublink <?= (isNavActive('fees', $currentScript) && ($_GET['new'] ?? '') === '1') ? 'active' : '' ?>">
                            <span class="nav-subicon">💰</span>
                            <span>Payment Received / Submit</span>
                        </a>
                        <a href="<?= $baseUri ?>/modules/fees/index.php" class="nav-sublink <?= (isNavActive('fees', $currentScript) && empty($_GET['view']) && empty($_GET['new'])) ? 'active' : '' ?>">
                            <span class="nav-subicon">💳</span>
                            <span>Fee Payments</span>
                        </a>
                        <a href="<?= $baseUri ?>/modules/fees/index.php?view=outstanding" class="nav-sublink <?= (isNavActive('fees', $currentScript) && ($_GET['view'] ?? '') === 'outstanding') ? 'active' : '' ?>">
                            <span class="nav-subicon">⏳</span>
                            <span>Outstanding Fees</span>
                        </a>
                        <a href="<?= $baseUri ?>/modules/loans/index.php" class="nav-sublink <?= isNavActive('loans', $currentScript) ?>">
                            <span class="nav-subicon">🤝</span>
                            <span>Loans & Advances</span>
                        </a>
                        <a href="<?= $baseUri ?>/modules/expenses/index.php" class="nav-sublink <?= isNavActive('expenses', $currentScript) ?>">
                            <span class="nav-subicon">🏢</span>
                            <span>Expenses</span>
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (in_array($userRole, ['admin'])): ?>
            <!-- STAFF & HR -->
            <div class="nav-category" data-target="staff">Staff & HR</div>
            <div class="nav-section <?= $isStaffActive ? 'expanded has-active' : '' ?>" data-section="staff">
                <button type="button" class="nav-section-header" aria-expanded="<?= $isStaffActive ? 'true' : 'false' ?>">
                    <span class="nav-section-left">
                        <span class="nav-icon">👔</span>
                        <span class="nav-section-title">Staff & HR</span>
                    </span>
                    <span class="nav-chevron">
                        <svg class="nav-chevron-icon" viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </span>
                </button>
                <div class="nav-submenu">
                    <div class="nav-submenu-inner">
                        <a href="<?= $baseUri ?>/modules/staff/index.php" class="nav-sublink <?= isNavActive('staff', $currentScript) ?>">
                            <span class="nav-subicon">👔</span>
                            <span>Staff Members</span>
                        </a>
                        <a href="<?= $baseUri ?>/modules/payroll/index.php" class="nav-sublink <?= isNavActive('payroll', $currentScript) ?>">
                            <span class="nav-subicon">💰</span>
                            <span>Payroll</span>
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (in_array($userRole, ['admin', 'accountant'])): ?>
            <!-- ASSETS -->
            <div class="nav-category" data-target="assets">Assets</div>
            <div class="nav-section <?= $isAssetsActive ? 'expanded has-active' : '' ?>" data-section="assets">
                <button type="button" class="nav-section-header" aria-expanded="<?= $isAssetsActive ? 'true' : 'false' ?>">
                    <span class="nav-section-left">
                        <span class="nav-icon">⚡</span>
                        <span class="nav-section-title">Assets</span>
                    </span>
                    <span class="nav-chevron">
                        <svg class="nav-chevron-icon" viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </span>
                </button>
                <div class="nav-submenu">
                    <div class="nav-submenu-inner">
                        <a href="<?= $baseUri ?>/modules/assets/index.php" class="nav-sublink <?= isNavActive('assets', $currentScript) ?>">
                            <span class="nav-subicon">⚡</span>
                            <span>Capital Assets</span>
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- REPORTS -->
            <div class="nav-category" data-target="reports">Reports</div>
            <div class="nav-section <?= $isReportsActive ? 'expanded has-active' : '' ?>" data-section="reports">
                <button type="button" class="nav-section-header" aria-expanded="<?= $isReportsActive ? 'true' : 'false' ?>">
                    <span class="nav-section-left">
                        <span class="nav-icon">📈</span>
                        <span class="nav-section-title">Reports</span>
                    </span>
                    <span class="nav-chevron">
                        <svg class="nav-chevron-icon" viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </span>
                </button>
                <div class="nav-submenu">
                    <div class="nav-submenu-inner">
                        <a href="<?= $baseUri ?>/modules/reports/index.php" class="nav-sublink <?= isNavActive('reports', $currentScript) ?>">
                            <span class="nav-subicon">📈</span>
                            <span>Reports Hub</span>
                        </a>
                    </div>
                </div>
            </div>

            <?php if ($userRole === 'admin'): ?>
            <!-- ADMINISTRATION -->
            <div class="nav-category" data-target="admin">Administration</div>
            <div class="nav-section <?= $isAdminActive ? 'expanded has-active' : '' ?>" data-section="admin">
                <button type="button" class="nav-section-header" aria-expanded="<?= $isAdminActive ? 'true' : 'false' ?>">
                    <span class="nav-section-left">
                        <span class="nav-icon">🔐</span>
                        <span class="nav-section-title">Administration</span>
                    </span>
                    <span class="nav-chevron">
                        <svg class="nav-chevron-icon" viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </span>
                </button>
                <div class="nav-submenu">
                    <div class="nav-submenu-inner">
                        <a href="<?= $baseUri ?>/modules/users/index.php" class="nav-sublink <?= isNavActive('users', $currentScript) ?>">
                            <span class="nav-subicon">🔐</span>
                            <span>User Management</span>
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ACCOUNT -->
            <div class="nav-category" data-target="account">Account</div>
            <a href="<?= $baseUri ?>/profile.php" class="nav-link <?= strpos($currentScript, 'profile.php') !== false ? 'active' : '' ?>" data-tooltip="Profile &amp; Privacy">
                <span class="nav-icon">👤</span>
                <span>Profile &amp; Privacy</span>
            </a>
            <button type="button" class="nav-link" id="sidebar-theme-toggle" onclick="openModal('themePickerModal')" style="width:100%;text-align:left;background:none;border:none;cursor:pointer;font-family:inherit;" data-tooltip="Colors &amp; Theme Options" title="Colors Cards Changing Options">
                <span class="nav-icon">🎨</span>
                <span>Colors &amp; Theme Options</span>
            </button>
            <a href="<?= $baseUri ?>/logout.php" class="nav-link" data-tooltip="Sign Out">
                <span class="nav-icon">🚪</span>
                <span>Sign Out</span>
            </a>
        </nav>
    </aside>

    <!-- Mobile Sidebar Backdrop Overlay -->
    <div class="sidebar-overlay" id="sidebar-overlay"></div>

    <!-- Main Content Area -->
    <div class="app-main">
        <!-- Persistent Top Header -->
        <header class="app-header">
            <div class="header-left">
                <button class="sidebar-toggle" id="sidebar-toggle" aria-label="Toggle Sidebar">☰</button>
                <div class="global-search-container">
                    <span class="global-search-icon">🔍</span>
                    <input type="text" id="global-search-input" class="global-search-input" placeholder="Search students, staff, courses, roll numbers..." autocomplete="off">
                    <div id="global-search-results" style="display:none;"></div>
                </div>
            </div>

            <div class="header-right">
                <div class="user-profile-container" id="user-profile-dropdown-wrap">
                    <div class="user-profile-pill" id="user-profile-toggle" title="Account Settings &amp; Privacy">
                        <div class="user-avatar" id="header-user-avatar">
                            <?php if (!empty($currentUser['profile_photo'])): ?>
                                <img src="<?= $baseUri ?>/assets/images/users/<?= htmlspecialchars($currentUser['profile_photo']) ?>" alt="Avatar">
                            <?php else: ?>
                                <?= strtoupper(substr($currentUser['full_name'] ?: 'A', 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <div class="user-info">
                            <span class="user-name"><?= htmlspecialchars($currentUser['full_name']) ?></span>
                            <span class="user-role-badge"><?= htmlspecialchars($currentUser['role']) ?></span>
                        </div>
                        <span class="user-dropdown-arrow">
                            <svg viewBox="0 0 24 24" width="12" height="12" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </span>
                    </div>

                    <div class="user-dropdown-menu" id="user-dropdown-menu">
                        <div class="user-dropdown-header">
                            <div class="dropdown-user-name"><?= htmlspecialchars($currentUser['full_name']) ?></div>
                            <div class="dropdown-user-role"><?= strtoupper(htmlspecialchars($currentUser['role'])) ?> &bull; <?= htmlspecialchars($currentUser['username']) ?></div>
                        </div>
                        <a href="<?= $baseUri ?>/profile.php?tab=profile" class="user-dropdown-item">
                            <span>👤</span>
                            <span>Profile Settings</span>
                        </a>
                        <a href="<?= $baseUri ?>/profile.php?tab=security" class="user-dropdown-item">
                            <span>🔑</span>
                            <span>Password &amp; Security</span>
                        </a>
                        <a href="<?= $baseUri ?>/profile.php?tab=privacy" class="user-dropdown-item">
                            <span>🛡️</span>
                            <span>Privacy Options</span>
                        </a>
                        <button type="button" class="user-dropdown-item" onclick="openModal('themePickerModal')" style="width:100%;text-align:left;background:none;border:none;cursor:pointer;font-family:inherit;">
                            <span>🎨</span>
                            <span>Colors Cards Changing Options</span>
                        </button>
                        <div class="user-dropdown-divider"></div>
                        <a href="<?= $baseUri ?>/logout.php" class="user-dropdown-item text-danger">
                            <span>🚪</span>
                            <span>Sign Out</span>
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Body Wrapper -->
        <main class="page-content">
