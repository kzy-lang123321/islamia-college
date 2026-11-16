<?php
/**
 * Persistent Application Header & Sidebar Shell
 * Islamia Academy / Islamia College of Technology Management System
 * Premium Commercial-Grade Enterprise Navigation & Header
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

$isDashboardActive      = ($currentScript == $baseUri . '/index.php' || (substr($currentScript, -9) == 'index.php' && strpos($currentScript, 'modules') === false && strpos($currentScript, 'profile') === false && strpos($currentScript, 'change_password') === false));
$isStudentsActive       = (strpos($currentScript, 'modules/students') !== false || strpos($currentScript, 'modules/enrollments') !== false);
$isCoursesActive        = (strpos($currentScript, 'modules/courses') !== false || strpos($currentScript, 'modules/departments') !== false);
$isCalendarActive       = (strpos($currentScript, 'modules/batches') !== false || strpos($currentScript, 'modules/board-examinations') !== false);
$isFinanceActive        = (strpos($currentScript, 'modules/fees') !== false || strpos($currentScript, 'modules/loans') !== false || strpos($currentScript, 'modules/expenses') !== false);
$isInsightsActive       = (strpos($currentScript, 'modules/reports') !== false && (($_GET['view'] ?? '') === 'analytics' || ($_GET['tab'] ?? '') === 'analytics'));
$isSecurityActive       = (strpos($currentScript, 'change_password.php') !== false || (strpos($currentScript, 'profile.php') !== false && ($_GET['tab'] ?? '') === 'security'));
$isReportsActive        = ((strpos($currentScript, 'modules/reports') !== false && !$isInsightsActive) || strpos($currentScript, 'modules/academic-positions') !== false);
$isAdministrationActive = (strpos($currentScript, 'modules/staff') !== false || strpos($currentScript, 'modules/payroll') !== false || strpos($currentScript, 'modules/assets') !== false || strpos($currentScript, 'modules/users') !== false);
$isSettingsActive       = (strpos($currentScript, 'profile.php') !== false && !$isSecurityActive);

// 10 Core Sidebar Navigation Items matching the provided design
$sidebarNavItems = [
    [
        'id' => 'dashboard',
        'label' => 'Dashboard',
        'url' => $baseUri . '/index.php',
        'icon' => 'dashboard',
        'active' => $isDashboardActive,
        'roles' => ['admin', 'registrar', 'accountant', 'staff'],
        'sublinks' => []
    ],
    [
        'id' => 'students',
        'label' => 'Students',
        'url' => $baseUri . '/modules/students/index.php',
        'icon' => 'students',
        'active' => $isStudentsActive,
        'roles' => ['admin', 'registrar', 'staff'],
        'sublinks' => [
            ['label' => 'All Students Directory', 'url' => $baseUri . '/modules/students/index.php'],
            ['label' => 'Student Enrollments', 'url' => $baseUri . '/modules/enrollments/index.php'],
            ['label' => 'Admissions & Registration', 'url' => $baseUri . '/modules/students/index.php?new=1']
        ]
    ],
    [
        'id' => 'courses',
        'label' => 'Courses',
        'url' => $baseUri . '/modules/courses/index.php',
        'icon' => 'courses',
        'active' => $isCoursesActive,
        'roles' => ['admin', 'registrar', 'staff'],
        'sublinks' => [
            ['label' => 'Courses & Subjects', 'url' => $baseUri . '/modules/courses/index.php'],
            ['label' => 'Academic Departments', 'url' => $baseUri . '/modules/departments/index.php']
        ]
    ],
    [
        'id' => 'calendar',
        'label' => 'Calendar',
        'url' => $baseUri . '/modules/batches/index.php',
        'icon' => 'calendar',
        'active' => $isCalendarActive,
        'roles' => ['admin', 'registrar', 'staff'],
        'sublinks' => [
            ['label' => 'Batches & Academic Sessions', 'url' => $baseUri . '/modules/batches/index.php'],
            ['label' => 'Board Examination Schedules', 'url' => $baseUri . '/modules/board-examinations/index.php']
        ]
    ],
    [
        'id' => 'finance',
        'label' => 'Finance',
        'url' => $baseUri . '/modules/fees/index.php',
        'icon' => 'finance',
        'active' => $isFinanceActive,
        'roles' => ['admin', 'accountant'],
        'sublinks' => [
            ['label' => 'Fee Counter / Receive Fee', 'url' => $baseUri . '/modules/fees/index.php?view=collect'],
            ['label' => 'Fee Payment Ledger', 'url' => $baseUri . '/modules/fees/index.php'],
            ['label' => 'Outstanding Dues List', 'url' => $baseUri . '/modules/fees/index.php?view=outstanding'],
            ['label' => 'Daily Collection Report', 'url' => $baseUri . '/modules/fees/index.php?view=daily'],
            ['label' => 'Loans & Staff Advances', 'url' => $baseUri . '/modules/loans/index.php'],
            ['label' => 'Campus Expenses Tracker', 'url' => $baseUri . '/modules/expenses/index.php']
        ]
    ],
    [
        'id' => 'insights',
        'label' => 'Insights',
        'url' => $baseUri . '/modules/reports/index.php?view=analytics',
        'icon' => 'insights',
        'active' => $isInsightsActive,
        'roles' => ['admin', 'accountant', 'registrar'],
        'sublinks' => [
            ['label' => 'Institutional Analytics', 'url' => $baseUri . '/modules/reports/index.php?view=analytics'],
            ['label' => 'Academic Achievements', 'url' => $baseUri . '/modules/academic-positions/index.php']
        ]
    ],
    [
        'id' => 'security',
        'label' => 'Security',
        'url' => $baseUri . '/change_password.php',
        'icon' => 'security',
        'active' => $isSecurityActive,
        'roles' => ['admin', 'registrar', 'accountant', 'staff'],
        'sublinks' => [
            ['label' => 'Change Account Password', 'url' => $baseUri . '/change_password.php'],
            ['label' => 'Profile & Account Security', 'url' => $baseUri . '/profile.php#security']
        ]
    ],
    [
        'id' => 'reports',
        'label' => 'Reports',
        'url' => $baseUri . '/modules/reports/index.php',
        'icon' => 'reports',
        'active' => $isReportsActive,
        'roles' => ['admin', 'accountant', 'registrar'],
        'sublinks' => [
            ['label' => 'Central Reports Hub', 'url' => $baseUri . '/modules/reports/index.php'],
            ['label' => 'Board Exams Analysis', 'url' => $baseUri . '/modules/board-examinations/index.php'],
            ['label' => 'Daily Revenue Summary', 'url' => $baseUri . '/modules/fees/index.php?view=daily']
        ]
    ],
    [
        'id' => 'administration',
        'label' => 'Administration',
        'url' => ($userRole === 'admin' ? $baseUri . '/modules/staff/index.php' : $baseUri . '/modules/assets/index.php'),
        'icon' => 'administration',
        'active' => $isAdministrationActive,
        'roles' => ['admin', 'accountant'],
        'sublinks' => array_values(array_filter([
            $userRole === 'admin' ? ['label' => 'Faculty & Staff Directory', 'url' => $baseUri . '/modules/staff/index.php'] : null,
            $userRole === 'admin' ? ['label' => 'Staff Monthly Payroll', 'url' => $baseUri . '/modules/payroll/index.php'] : null,
            ['label' => 'Capital Assets Management', 'url' => $baseUri . '/modules/assets/index.php'],
            $userRole === 'admin' ? ['label' => 'User & Role Management', 'url' => $baseUri . '/modules/users/index.php'] : null
        ]))
    ],
    [
        'id' => 'settings',
        'label' => 'Settings',
        'url' => $baseUri . '/profile.php',
        'icon' => 'settings',
        'active' => $isSettingsActive,
        'roles' => ['admin', 'registrar', 'accountant', 'staff'],
        'sublinks' => [
            ['label' => 'User Profile & Preferences', 'url' => $baseUri . '/profile.php'],
            ['label' => 'Sign Out', 'url' => $baseUri . '/logout.php']
        ]
    ]
];

// Crisp SVG vector icons precisely matching the Edu Insight design screenshot
function getNavSvg(string $name, int $size = 20): string {
    switch ($name) {
        // 1. Dashboard (2x2 rounded squares)
        case 'dashboard':
            return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="currentColor" class="nav-svg-icon" aria-hidden="true">'
                 . '<rect x="3" y="3" width="7.5" height="7.5" rx="2.5"></rect>'
                 . '<rect x="13.5" y="3" width="7.5" height="7.5" rx="2.5"></rect>'
                 . '<rect x="3" y="13.5" width="7.5" height="7.5" rx="2.5"></rect>'
                 . '<rect x="13.5" y="13.5" width="7.5" height="7.5" rx="2.5"></rect>'
                 . '</svg>';

        // 2. Students (People silhouettes with sparkle star)
        case 'students':
            return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="currentColor" class="nav-svg-icon" aria-hidden="true">'
                 . '<circle cx="8" cy="7.5" r="3.2"></circle>'
                 . '<path d="M2.5 19.5 C2.5 15.5 5 13 8 13 C11 13 13.5 15.5 13.5 19.5 C13.5 20 13 20.5 12.5 20.5 L3.5 20.5 C3 20.5 2.5 20 2.5 19.5 Z"></path>'
                 . '<circle cx="16" cy="8.5" r="2.8"></circle>'
                 . '<path d="M12.5 15 C13.5 14 15 13.5 16.5 13.5 C19.5 13.5 21.5 15.5 21.5 19.5 C21.5 20 21 20.5 20.5 20.5 L14.5 20.5 C14.5 18 13.5 16.2 12.5 15 Z"></path>'
                 . '<path d="M4 10.5 C4.5 10.5 5 10 5 9.5 C5 10 5.5 10.5 6 10.5 C5.5 10.5 5 11 5 11.5 C5 11 4.5 10.5 4 10.5 Z" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"></path>'
                 . '</svg>';

        // 3. Courses (Open book with bookmark)
        case 'courses':
            return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="currentColor" class="nav-svg-icon" aria-hidden="true">'
                 . '<path d="M3.5 5.5 C3.5 4.1 4.6 3 6 3 C8.5 3 10.5 4.2 12 5.5 C13.5 4.2 15.5 3 18 3 C19.4 3 20.5 4.1 20.5 5.5 L20.5 17 C20.5 18.1 19.6 19 18.5 19 C16 19 13.8 17.8 12 16.5 C10.2 17.8 8 19 5.5 19 C4.4 19 3.5 18.1 3.5 17 Z"></path>'
                 . '<path d="M15.5 3.5 L15.5 9 L17 7.8 L18.5 9 L18.5 3.5 Z" fill="#eb6226" opacity="0.9"></path>'
                 . '</svg>';

        // 4. Calendar (Calendar pad with "31")
        case 'calendar':
            return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="currentColor" class="nav-svg-icon" aria-hidden="true">'
                 . '<path fill-rule="evenodd" d="M6 3 C6 2.4 6.4 2 7 2 C7.6 2 8 2.4 8 3 L8 4 L16 4 L16 3 C16 2.4 16.4 2 17 2 C17.6 2 18 2.4 18 3 L18 4 L19 4 C20.7 4 22 5.3 22 7 L22 19 C22 20.7 20.7 22 19 22 L5 22 C3.3 22 2 20.7 2 19 L2 7 C2 5.3 3.3 4 5 4 L6 4 Z M4 9 L20 9 L20 19 C20 19.6 19.6 20 19 20 L5 20 C4.4 20 4 19.6 4 19 Z"></path>'
                 . '<path d="M8 12.5 C8 12 8.4 11.5 9.2 11.5 C10 11.5 10.5 11.9 10.5 12.5 C10.5 12.9 10.3 13.3 9.8 13.5 C10.4 13.7 10.7 14.2 10.7 14.8 C10.7 15.6 10 16.2 9 16.2 C8.2 16.2 7.7 15.7 7.7 15.1 L8.8 15.1 C8.8 15.3 9 15.5 9.3 15.5 C9.6 15.5 9.8 15.3 9.8 14.9 C9.8 14.5 9.5 14.3 9.1 14.3 L8.7 14.3 L8.7 13.6 L9.1 13.6 C9.4 13.6 9.6 13.4 9.6 13.1 C9.6 12.8 9.4 12.6 9.1 12.6 C8.8 12.6 8.7 12.8 8.7 13 Z M13.5 11.5 L14.7 11.5 L14.7 16 L13.5 16 L13.5 12.7 L12.7 13.1 L12.7 12.3 Z"></path>'
                 . '</svg>';

        // 5. Finance (Classical Bank/Temple)
        case 'finance':
            return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="currentColor" class="nav-svg-icon" aria-hidden="true">'
                 . '<path d="M12 2 L2 7.5 L22 7.5 Z"></path>'
                 . '<rect x="3" y="8.5" width="18" height="2" rx="0.5"></rect>'
                 . '<rect x="4.5" y="11.5" width="2.2" height="6.5" rx="0.6"></rect>'
                 . '<rect x="9" y="11.5" width="2.2" height="6.5" rx="0.6"></rect>'
                 . '<rect x="13" y="11.5" width="2.2" height="6.5" rx="0.6"></rect>'
                 . '<rect x="17.3" y="11.5" width="2.2" height="6.5" rx="0.6"></rect>'
                 . '<rect x="2" y="19" width="20" height="2.5" rx="0.8"></rect>'
                 . '</svg>';

        // 6. Insights (Card with two vertical rounded bars)
        case 'insights':
            return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="currentColor" class="nav-svg-icon" aria-hidden="true">'
                 . '<rect x="3" y="3" width="18" height="18" rx="5.5" fill="none" stroke="currentColor" stroke-width="2.2"></rect>'
                 . '<rect x="7.5" y="11" width="3.5" height="6" rx="1.75"></rect>'
                 . '<rect x="13" y="7" width="3.5" height="10" rx="1.75"></rect>'
                 . '</svg>';

        // 7. Security (Padlock with cutout code holes)
        case 'security':
            return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="currentColor" class="nav-svg-icon" aria-hidden="true">'
                 . '<path fill-rule="evenodd" d="M7 10 L7 7 C7 4.2 9.2 2 12 2 C14.8 2 17 4.2 17 7 L17 10 L18.5 10 C19.9 10 21 11.1 21 12.5 L21 19.5 C21 20.9 19.9 22 18.5 22 L5.5 22 C4.1 22 3 20.9 3 19.5 L3 12.5 C3 11.1 4.1 10 5.5 10 Z M9 10 L15 10 L15 7 C15 5.3 13.7 4 12 4 C10.3 4 9 5.3 9 7 Z M7.5 16 C7.5 15.4 7.9 15 8.5 15 C9.1 15 9.5 15.4 9.5 16 C9.5 16.6 9.1 17 8.5 17 C7.9 17 7.5 16.6 7.5 16 Z M11 16 C11 15.4 11.4 15 12 15 C12.6 15 13 15.4 13 16 C13 16.6 12.6 17 12 17 C11.4 17 11 16.6 11 16 Z M14.5 16 C14.5 15.4 14.9 15 15.5 15 C16.1 15 16.5 15.4 16.5 16 C16.5 16.6 16.1 17 15.5 17 C14.9 17 14.5 16.6 14.5 16 Z"></path>'
                 . '</svg>';

        // 8. Reports (Presentation easel whiteboard stand)
        case 'reports':
            return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nav-svg-icon" aria-hidden="true">'
                 . '<rect x="3" y="3.5" width="18" height="12" rx="2"></rect>'
                 . '<path d="M6 11.5 L9.5 8 L13 10.5 L17.5 6" stroke-width="1.8"></path>'
                 . '<rect x="11" y="1.5" width="2" height="2" rx="0.5" fill="currentColor"></rect>'
                 . '<rect x="2" y="15" width="20" height="1.8" rx="0.8" fill="currentColor"></rect>'
                 . '<path d="M6.5 16.8 L4.5 22.5 M17.5 16.8 L19.5 22.5 M12 16.8 L12 22.5"></path>'
                 . '</svg>';

        // 9. Administration (Protective shield with user badge)
        case 'administration':
            return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round" stroke-linecap="round" class="nav-svg-icon" aria-hidden="true">'
                 . '<path d="M12 2 L4 5 L4 12 C4 17 7.5 21 12 22 C16.5 21 20 17 20 12 L20 5 Z"></path>'
                 . '<circle cx="12" cy="9.5" r="2.5" fill="currentColor"></circle>'
                 . '<path d="M7.8 16 C8.2 14 10 13 12 13 C14 13 15.8 14 16.2 16"></path>'
                 . '</svg>';

        // 10. Settings (Hexagonal nut with center circle)
        case 'settings':
            return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="currentColor" class="nav-svg-icon" aria-hidden="true">'
                 . '<path fill-rule="evenodd" d="M12 2.5 L20.2 7.2 L20.2 16.8 L12 21.5 L3.8 16.8 L3.8 7.2 Z M12 9 C10.3 9 9 10.3 9 12 C9 13.7 10.3 15 12 15 C13.7 15 15 13.7 15 12 C15 10.3 13.7 9 12 9 Z"></path>'
                 . '</svg>';

        // Standard Utility & Header Icons
        case 'academic':
            return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" class="nav-svg-icon"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path></svg>';
        case 'logout':
            return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" class="nav-svg-icon"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>';
        case 'bell':
            return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" class="nav-svg-icon"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>';
        case 'search':
            return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" class="nav-svg-icon"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>';
        case 'menu':
            return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" class="nav-svg-icon"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>';
        case 'chevron-down':
            return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" class="nav-svg-icon"><polyline points="6 9 12 15 18 9"></polyline></svg>';
        case 'chevron-right':
            return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" class="nav-svg-icon"><polyline points="9 18 15 12 9 6"></polyline></svg>';
        case 'collapse-left':
            return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" class="nav-svg-icon"><polyline points="11 17 6 12 11 7"></polyline><polyline points="18 17 13 12 18 7"></polyline></svg>';
        case 'collapse-right':
            return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" class="nav-svg-icon"><polyline points="13 17 18 12 13 7"></polyline><polyline points="6 17 11 12 6 7"></polyline></svg>';
        case 'close':
            return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" class="nav-svg-icon"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
        default:
            return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" class="nav-svg-icon"><circle cx="12" cy="12" r="8"></circle></svg>';
    }
}

// Dynamic Breadcrumbs Helper
function getBreadcrumbs(string $currentScript, string $baseUri, array $query = []): array {
    $crumbs = [
        ['label' => 'Home', 'url' => $baseUri . '/index.php']
    ];
    
    if (strpos($currentScript, 'modules/students') !== false) {
        $crumbs[] = ['label' => 'Academic Management', 'url' => $baseUri . '/modules/students/index.php'];
        $crumbs[] = ['label' => 'Students', 'url' => $baseUri . '/modules/students/index.php', 'current' => true];
    } elseif (strpos($currentScript, 'modules/enrollments') !== false) {
        $crumbs[] = ['label' => 'Academic Management', 'url' => $baseUri . '/modules/enrollments/index.php'];
        $crumbs[] = ['label' => 'Enrollments', 'url' => $baseUri . '/modules/enrollments/index.php', 'current' => true];
    } elseif (strpos($currentScript, 'modules/courses') !== false) {
        $crumbs[] = ['label' => 'Academic Management', 'url' => $baseUri . '/modules/courses/index.php'];
        $crumbs[] = ['label' => 'Courses & Subjects', 'url' => $baseUri . '/modules/courses/index.php', 'current' => true];
    } elseif (strpos($currentScript, 'modules/departments') !== false) {
        $crumbs[] = ['label' => 'Academic Management', 'url' => $baseUri . '/modules/departments/index.php'];
        $crumbs[] = ['label' => 'Departments', 'url' => $baseUri . '/modules/departments/index.php', 'current' => true];
    } elseif (strpos($currentScript, 'modules/batches') !== false) {
        $crumbs[] = ['label' => 'Academic Management', 'url' => $baseUri . '/modules/batches/index.php'];
        $crumbs[] = ['label' => 'Batches', 'url' => $baseUri . '/modules/batches/index.php', 'current' => true];
    } elseif (strpos($currentScript, 'modules/board-examinations') !== false) {
        $crumbs[] = ['label' => 'Academic Management', 'url' => $baseUri . '/modules/board-examinations/index.php'];
        $crumbs[] = ['label' => 'Board Examinations', 'url' => $baseUri . '/modules/board-examinations/index.php', 'current' => true];
    } elseif (strpos($currentScript, 'modules/academic-positions') !== false) {
        $crumbs[] = ['label' => 'Academic Management', 'url' => $baseUri . '/modules/academic-positions/index.php'];
        $crumbs[] = ['label' => 'Academic Achievements', 'url' => $baseUri . '/modules/academic-positions/index.php', 'current' => true];
    } elseif (strpos($currentScript, 'modules/fees') !== false) {
        $crumbs[] = ['label' => 'Finance', 'url' => $baseUri . '/modules/fees/index.php'];
        $view = $query['view'] ?? '';
        if ($view === 'collect' || ($query['new'] ?? '') === '1') {
            $crumbs[] = ['label' => 'Fee Counter', 'url' => $baseUri . '/modules/fees/index.php?view=collect', 'current' => true];
        } elseif ($view === 'outstanding') {
            $crumbs[] = ['label' => 'Outstanding Dues', 'url' => $baseUri . '/modules/fees/index.php?view=outstanding', 'current' => true];
        } elseif ($view === 'daily') {
            $crumbs[] = ['label' => 'Daily Collection', 'url' => $baseUri . '/modules/fees/index.php?view=daily', 'current' => true];
        } else {
            $crumbs[] = ['label' => 'Fee Ledger', 'url' => $baseUri . '/modules/fees/index.php', 'current' => true];
        }
    } elseif (strpos($currentScript, 'modules/loans') !== false) {
        $crumbs[] = ['label' => 'Finance', 'url' => $baseUri . '/modules/loans/index.php'];
        $crumbs[] = ['label' => 'Loans & Advances', 'url' => $baseUri . '/modules/loans/index.php', 'current' => true];
    } elseif (strpos($currentScript, 'modules/expenses') !== false) {
        $crumbs[] = ['label' => 'Finance', 'url' => $baseUri . '/modules/expenses/index.php'];
        $crumbs[] = ['label' => 'Campus Expenses', 'url' => $baseUri . '/modules/expenses/index.php', 'current' => true];
    } elseif (strpos($currentScript, 'modules/staff') !== false) {
        $crumbs[] = ['label' => 'Staff & Administration', 'url' => $baseUri . '/modules/staff/index.php'];
        $crumbs[] = ['label' => 'Faculty & Staff', 'url' => $baseUri . '/modules/staff/index.php', 'current' => true];
    } elseif (strpos($currentScript, 'modules/payroll') !== false) {
        $crumbs[] = ['label' => 'Staff & Administration', 'url' => $baseUri . '/modules/payroll/index.php'];
        $crumbs[] = ['label' => 'Payroll', 'url' => $baseUri . '/modules/payroll/index.php', 'current' => true];
    } elseif (strpos($currentScript, 'modules/assets') !== false) {
        $crumbs[] = ['label' => 'Staff & Administration', 'url' => $baseUri . '/modules/assets/index.php'];
        $crumbs[] = ['label' => 'Capital Assets', 'url' => $baseUri . '/modules/assets/index.php', 'current' => true];
    } elseif (strpos($currentScript, 'modules/users') !== false) {
        $crumbs[] = ['label' => 'Staff & Administration', 'url' => $baseUri . '/modules/users/index.php'];
        $crumbs[] = ['label' => 'User Management', 'url' => $baseUri . '/modules/users/index.php', 'current' => true];
    } elseif (strpos($currentScript, 'modules/reports') !== false) {
        $crumbs[] = ['label' => 'Reports & Analytics', 'url' => $baseUri . '/modules/reports/index.php'];
        $crumbs[] = ['label' => 'Reports Hub', 'url' => $baseUri . '/modules/reports/index.php', 'current' => true];
    } elseif (strpos($currentScript, 'profile.php') !== false) {
        $crumbs[] = ['label' => 'System & Account', 'url' => $baseUri . '/profile.php'];
        $crumbs[] = ['label' => 'Profile & Privacy', 'url' => $baseUri . '/profile.php', 'current' => true];
    } else {
        $crumbs[] = ['label' => 'Overview', 'url' => $baseUri . '/index.php'];
        $crumbs[] = ['label' => 'Dashboard', 'url' => $baseUri . '/index.php', 'current' => true];
    }
    
    return $crumbs;
}

// Live Authentic Notifications from Database
$notifications = [];
try {
    require_once __DIR__ . '/../config/database.php';
    $headerPdo = Database::getConnection();
    
    // Check students with dues
    $duesStmt = $headerPdo->query("
        SELECT COUNT(*) as count, SUM(balance) as total_dues FROM (
            SELECT (e.total_agreed_fee + COALESCE((SELECT SUM(fine_amount) FROM fee_transactions WHERE enrollment_id = e.id AND is_cancelled = 0), 0) - COALESCE((SELECT SUM(amount_paid) FROM fee_transactions WHERE enrollment_id = e.id AND is_cancelled = 0), 0)) as balance
            FROM enrollments e
            HAVING balance > 0
        ) as sub
    ");
    $duesData = $duesStmt->fetch(PDO::FETCH_ASSOC);
    $duesCount = (int)($duesData['count'] ?? 0);
    $duesTotal = (float)($duesData['total_dues'] ?? 0);
    
    if ($duesCount > 0) {
        $notifications[] = [
            'type' => 'warning',
            'title' => $duesCount . ' Students with Outstanding Dues',
            'desc' => 'Total pending balance: Rs. ' . number_format($duesTotal, 0),
            'url' => $baseUri . '/modules/fees/index.php?view=outstanding'
        ];
    }
    
    // Check today's fee collections
    $todayStmt = $headerPdo->prepare("SELECT COUNT(*) as count, COALESCE(SUM(amount_paid), 0) as total FROM fee_transactions WHERE payment_date = ? AND is_cancelled = 0");
    $todayStmt->execute([date('Y-m-d')]);
    $todayData = $todayStmt->fetch(PDO::FETCH_ASSOC);
    $todayCount = (int)($todayData['count'] ?? 0);
    $todayTotal = (float)($todayData['total'] ?? 0);
    
    if ($todayCount > 0) {
        $notifications[] = [
            'type' => 'info',
            'title' => "Today's Collections: Rs. " . number_format($todayTotal, 0),
            'desc' => $todayCount . ' receipts processed today',
            'url' => $baseUri . '/modules/fees/index.php?view=daily'
        ];
    }
} catch (Exception $e) {
    // Graceful fallback
}
$notifCount = count($notifications);
$pageCrumbs = getBreadcrumbs($currentScript, $baseUri, $_GET);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . " | " : "" ?>Islamia Academy Management System</title>
    
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
    <link rel="stylesheet" href="<?= $baseUri ?>/assets/css/edu_sidebar.css">
    <link rel="stylesheet" href="<?= $baseUri ?>/assets/css/print.css">
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
    <!-- Persistent Sidebar (Edu Insight Dual-Track UI/UX) -->
    <aside class="app-sidebar edu-sidebar" id="app-sidebar">
        <!-- Sidebar Branding & Banner Header -->
        <div class="edu-sidebar-header">
            <!-- Left White Square Logo Rail Section (width: 72px) -->
            <a href="<?= $baseUri ?>/index.php" class="edu-sidebar-logo-box" title="Islamia Academy / Edu Insight">
                <img src="<?= $baseUri ?>/assets/images/edu_logo.svg" alt="Edu Insight Logo" class="edu-sidebar-logo-img">
            </a>

            <!-- Right Banner Area (Visible in Expanded Desktop Mode) -->
            <div class="edu-sidebar-banner">
                <img src="<?= $baseUri ?>/assets/images/sidebar_banner.jpg" alt="Online Courses" class="edu-banner-img">
                <div class="edu-banner-overlay">
                    <div class="edu-banner-badge">ONLINE COURSES</div>
                    <div class="edu-banner-sub">Edu Insight • Islamia College</div>
                </div>

                <!-- Desktop Rail Collapse Button -->
                <button type="button" class="edu-sidebar-toggle-btn" id="sidebar-collapse-desktop-btn" aria-label="Toggle Sidebar" title="Collapse / Expand Sidebar">
                    <span class="collapse-icon-expand">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                    </span>
                    <span class="collapse-icon-collapsed">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </span>
                </button>
            </div>

            <!-- Mobile Drawer Close Button -->
            <button type="button" class="sidebar-close-btn" id="sidebar-close-btn" aria-label="Close Sidebar">&times;</button>
        </div>

        <!-- Main Navigation Body (Orange Rail + White Panel) -->
        <div class="edu-sidebar-body">
            <nav class="edu-sidebar-nav" role="navigation" aria-label="Main Navigation">
                <?php foreach ($sidebarNavItems as $item): 
                    if (!in_array($userRole, $item['roles'])) continue;
                    $isActive = $item['active'];
                    $hasSub = !empty($item['sublinks']);
                ?>
                <div class="edu-nav-item <?= $isActive ? 'active' : '' ?> <?= $hasSub ? 'has-sub' : '' ?>" data-item-id="<?= $item['id'] ?>">
                    <a href="<?= htmlspecialchars($item['url']) ?>" class="edu-nav-link" data-tooltip="<?= htmlspecialchars($item['label']) ?>">
                        <!-- Left Rail Icon Box (Lives on vibrant orange background track) -->
                        <div class="edu-nav-icon-wrap">
                            <span class="edu-nav-icon">
                                <?= getNavSvg($item['icon'], 22) ?>
                            </span>
                        </div>

                        <!-- Right Label Box (Lives on clean white panel) -->
                        <div class="edu-nav-label-wrap">
                            <span class="edu-nav-label-pill"><?= htmlspecialchars($item['label']) ?></span>
                            <?php if ($hasSub): ?>
                            <span class="edu-nav-sub-indicator" title="View options">
                                <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                            </span>
                            <?php endif; ?>
                        </div>
                    </a>

                    <?php if ($hasSub): ?>
                    <!-- Floating Submenu Popover on Hover -->
                    <div class="edu-nav-subflyout">
                        <div class="edu-subflyout-header">
                            <span><?= htmlspecialchars($item['label']) ?></span>
                        </div>
                        <div class="edu-subflyout-list">
                            <?php foreach ($item['sublinks'] as $sublink): 
                                $isSubActive = (strpos($currentScript, str_replace(['.php', '?view=', '?tab='], '', $sublink['url'])) !== false);
                            ?>
                            <a href="<?= htmlspecialchars($sublink['url']) ?>" class="edu-subflyout-link <?= $isSubActive ? 'active' : '' ?>">
                                <span class="edu-subflyout-bullet"></span>
                                <span><?= htmlspecialchars($sublink['label']) ?></span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </nav>
        </div>
    </aside>

    <!-- Mobile Sidebar Backdrop Overlay -->
    <div class="sidebar-overlay" id="sidebar-overlay"></div>

    <!-- Main Content Area -->
    <div class="app-main">
        <!-- Persistent Top Header -->
        <header class="app-header">
            <!-- Left Section: Toggle & Breadcrumbs -->
            <div class="header-left">
                <button type="button" class="sidebar-toggle-btn" id="sidebar-toggle" aria-label="Toggle Sidebar" title="Toggle Sidebar">
                    <?= getNavSvg('menu', 20) ?>
                </button>

                <!-- Dynamic Breadcrumbs Trail -->
                <nav class="header-breadcrumbs" aria-label="Breadcrumb">
                    <?php 
                    $totalCrumbs = count($pageCrumbs);
                    foreach ($pageCrumbs as $idx => $crumb): 
                        $isLast = ($idx === $totalCrumbs - 1);
                    ?>
                        <?php if ($isLast): ?>
                            <span class="breadcrumb-item current" aria-current="page"><?= htmlspecialchars($crumb['label']) ?></span>
                        <?php else: ?>
                            <a href="<?= htmlspecialchars($crumb['url']) ?>" class="breadcrumb-item"><?= htmlspecialchars($crumb['label']) ?></a>
                            <span class="breadcrumb-sep"><?= getNavSvg('chevron-right', 12) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>

                <?php if (isset($pageTitle) && !empty($pageTitle)): ?>
                    <div class="header-page-title"><?= htmlspecialchars($pageTitle) ?></div>
                <?php endif; ?>
            </div>

            <!-- Right Section: Search, Notifications & Profile -->
            <div class="header-right">
                <!-- Global Search -->
                <div class="global-search-container">
                    <span class="global-search-icon"><?= getNavSvg('search', 16) ?></span>
                    <input type="text" id="global-search-input" class="global-search-input" placeholder="Search students, staff, roll #..." autocomplete="off">
                    <span class="global-search-kbd"><kbd>Ctrl</kbd> + <kbd>K</kbd></span>
                    <div id="global-search-results" class="search-dropdown-list" style="display:none;"></div>
                </div>

                <!-- Notifications Dropdown -->
                <div class="notifications-container" id="notifications-container">
                    <button type="button" class="notifications-btn" id="notifications-toggle" aria-label="Notifications" aria-expanded="false" title="System Notifications">
                        <?= getNavSvg('bell', 18) ?>
                        <?php if ($notifCount > 0): ?>
                            <span class="notifications-badge" id="notifications-badge"><?= $notifCount ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="notifications-dropdown" id="notifications-dropdown">
                        <div class="notifications-header">
                            <div class="notifications-title">Notifications &amp; Alerts</div>
                            <?php if ($notifCount > 0): ?>
                                <span class="notifications-count-pill"><?= $notifCount ?> active</span>
                            <?php endif; ?>
                        </div>
                        <div class="notifications-list">
                            <?php if ($notifCount > 0): ?>
                                <?php foreach ($notifications as $notif): ?>
                                    <a href="<?= htmlspecialchars($notif['url']) ?>" class="notification-item">
                                        <div class="notification-icon-wrap <?= $notif['type'] ?>">
                                            <?php if ($notif['type'] === 'warning'): ?>
                                                <?= getNavSvg('dues', 16) ?>
                                            <?php else: ?>
                                                <?= getNavSvg('daily', 16) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="notification-content">
                                            <div class="notification-item-title"><?= htmlspecialchars($notif['title']) ?></div>
                                            <div class="notification-item-desc"><?= htmlspecialchars($notif['desc']) ?></div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="padding: 24px 16px; text-align: center; color: var(--text-muted); font-size: 13px;">
                                    All clear! No pending financial or administrative alerts.
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="notifications-footer">
                            <a href="<?= $baseUri ?>/modules/fees/index.php?view=outstanding" class="notifications-view-all">View All Outstanding Dues &rarr;</a>
                        </div>
                    </div>
                </div>

                <!-- User Profile Pill & Dropdown -->
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
                            <span class="user-role-badge"><?= strtoupper(htmlspecialchars($currentUser['role'])) ?></span>
                        </div>
                        <span class="user-dropdown-arrow">
                            <?= getNavSvg('chevron-down', 13) ?>
                        </span>
                    </div>

                    <div class="user-dropdown-menu" id="user-dropdown-menu">
                        <div class="user-dropdown-header">
                            <div class="dropdown-user-name"><?= htmlspecialchars($currentUser['full_name']) ?></div>
                            <div class="dropdown-user-role"><?= strtoupper(htmlspecialchars($currentUser['role'])) ?> &bull; <?= htmlspecialchars($currentUser['username']) ?></div>
                        </div>
                        <a href="<?= $baseUri ?>/profile.php?tab=profile" class="user-dropdown-item">
                            <span class="user-dropdown-icon"><?= getNavSvg('profile', 15) ?></span>
                            <span>Profile Settings</span>
                        </a>
                        <a href="<?= $baseUri ?>/profile.php?tab=security" class="user-dropdown-item">
                            <span class="user-dropdown-icon"><?= getNavSvg('security', 15) ?></span>
                            <span>Password &amp; Security</span>
                        </a>
                        <a href="<?= $baseUri ?>/profile.php?tab=privacy" class="user-dropdown-item">
                            <span class="user-dropdown-icon"><?= getNavSvg('privacy', 15) ?></span>
                            <span>Privacy Options</span>
                        </a>
                        <div class="user-dropdown-divider"></div>
                        <a href="<?= $baseUri ?>/logout.php" class="user-dropdown-item text-danger">
                            <span class="user-dropdown-icon"><?= getNavSvg('logout', 15) ?></span>
                            <span>Sign Out</span>
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Body Wrapper -->
        <main class="page-content">
